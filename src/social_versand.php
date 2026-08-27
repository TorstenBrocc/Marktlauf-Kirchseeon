<?php
/**
 * Gemeinsame Social-Post-Versandlogik (Post-Wirkung-Spec / Auto-Versand-Stichtag-Spec §3.1).
 *
 * Ein Pfad fuer BEIDE Ausloeser — den manuellen Klick (orga/api/post_dispatch.php) und den
 * Auto-Versand-Timer am Stichtag (bin/social_versand.php). So gibt es keine zweite Wahrheit:
 * Bild->JPEG, Make.com-Dispatch, Versand-Log am Post (status 'gesendet'), Fahrplan-Fortschritt
 * (Wiederkehr rueckt vor, sonst 'erledigt') und die "Post ist live"-Mail liegen HIER.
 *
 * versendePost() macht KEINE HTTP-Ausgabe und ruft nie exit — der Aufrufer uebersetzt das
 * Ergebnis (Web -> JSON/HTTP-Code, CLI -> Log/Exit).
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/social_dispatcher.php';
require_once __DIR__ . '/social_anlaesse.php';
require_once __DIR__ . '/social_verstaerker.php';
require_once __DIR__ . '/channels/mail.php';

/**
 * Versendet einen bereits geladenen Post ueber Make.com und verbucht das Ergebnis.
 *
 * @param array    $post            Zeile aus post_race_contents (SELECT *).
 * @param string[] $channels        Bereits validiert auf ['instagram','facebook'].
 * @param string   $ersterKommentar Optionaler erster Kommentar (Link+Hashtags).
 * @param ?int     $scheduledTime   Unix-Zeit fuer terminierten Versand (Spec §4a/S3): gesetzt ->
 *                                  Make terminiert FB nativ, Post wird 'terminiert' (nicht live),
 *                                  KEINE Live-Mail (feuert erst der Finalizer zum Slot). null =
 *                                  sofort (Klick/Catch-up) + Live-Mail wie bisher.
 * @return array{ok:bool,message:string,code?:int,fallback?:bool} — code nur bei Validierungsfehler.
 */
function versendePost(PDO $pdo, array $post, array $channels, string $ersterKommentar = '', ?int $scheduledTime = null): array
{
    $postId = (int) ($post['id'] ?? 0);
    if ($postId <= 0 || $channels === []) {
        return ['ok' => false, 'code' => 422, 'message' => 'Post oder Kanäle fehlen.'];
    }

    $text = trim((string) ($post['llm_text_social'] ?? ''));
    if ($text === '') {
        return ['ok' => false, 'code' => 422, 'message' => 'Kein Social-Text am Post — bitte zuerst Schritt 1.'];
    }

    // Hashtags an die Caption anhaengen (Design-Entscheid 2026-08-26: Hashtags leben in der
    // Caption). Die KI schreibt bewusst keine (llm_client.php), und der Versand hat sie bisher
    // fallengelassen -> Posts gingen ohne Hashtags raus, obwohl die UI sie verspricht. Quelle:
    // Einstellung social_hashtags, sonst der gegrillte Default. Doppel-Schutz, falls der Text
    // (manuell) schon Hashtags enthaelt.
    $hashtags = '';
    try {
        $stmt = $pdo->query("SELECT `value` FROM einstellungen WHERE `key` = 'social_hashtags' LIMIT 1");
        $hashtags = trim((string) ($stmt->fetchColumn() ?: ''));
    } catch (PDOException $e) {
        // Einstellung fehlt -> Default unten
    }
    if ($hashtags === '') {
        $hashtags = socialHashtagsDefault();
    }
    if ($hashtags !== '' && !str_contains($text, '#')) {
        $text = rtrim($text) . "\n\n" . $hashtags;
    }

    $bildPfad = trim((string) ($post['bild_pfad'] ?? ''));
    if (in_array('instagram', $channels, true) && $bildPfad === '') {
        return ['ok' => false, 'code' => 422, 'message' => 'Instagram braucht ein Bild — bitte zuerst Schritt 3 (oder Instagram abwählen).'];
    }

    // Bild fuer den Versand: PNG-Master -> JPEG (Instagram akzeptiert nur JPEG),
    // deterministischer Name = eine Versanddatei je Post, kein Muellberg.
    $imageUrl = '';
    if ($bildPfad !== '') {
        $dir    = dirname(__DIR__) . '/assets/social';
        $quelle = $dir . '/' . basename($bildPfad);
        if (!is_file($quelle)) {
            return ['ok' => false, 'code' => 422, 'message' => 'Bilddatei fehlt auf dem Server — bitte Grafik in Schritt 3 neu übernehmen.'];
        }
        $sendDatei = 'post-' . $postId . '-send.jpg';
        $ok = false;
        if (function_exists('imagecreatefromstring')) {
            $src = @imagecreatefromstring((string) file_get_contents($quelle));
            if ($src !== false) {
                $w = imagesx($src);
                $h = imagesy($src);
                $canvas = imagecreatetruecolor($w, $h);
                $white  = imagecolorallocate($canvas, 255, 255, 255);
                imagefilledrectangle($canvas, 0, 0, $w, $h, $white);
                imagecopy($canvas, $src, 0, 0, 0, 0, $w, $h);
                $ok = imagejpeg($canvas, $dir . '/' . $sendDatei, 90);
                imagedestroy($src);
                imagedestroy($canvas);
            }
        }
        if (!$ok) {
            // Ohne GD: PNG direkt senden (Facebook ok; Instagram lehnt evtl. ab -> Fehler kommt von Make)
            $sendDatei = basename($bildPfad);
            logError('versendePost: GD nicht verfügbar — sende PNG statt JPEG (Post ' . $postId . ').');
        }
        $baseUrl  = rtrim((string) (getConfig()['app']['url'] ?? ''), '/');
        $imageUrl = $baseUrl . '/assets/social/' . $sendDatei;
    }

    // Terminierter FB-Versand (Spec §4b, Entscheid TT 2026-08-27): der Beitrag ist bis zum Slot
    // unveroeffentlicht -> make kann keinen ersten Kommentar setzen (schlaegt sonst fehl). Damit der
    // klickbare Anmelde-Link nicht verloren geht, wandert der CTA+Link in die FB-Caption (FB-Caption-
    // Links sind klickbar, anders als IG) und der erste Kommentar wird geleert -> der bestehende
    // make-Filter "nur wenn erster Kommentar" ueberspringt den Kommentar-Schritt von selbst.
    $dispatchKommentar = $ersterKommentar;
    if ($scheduledTime !== null && $ersterKommentar !== '') {
        $text = rtrim($text) . "\n\n" . $ersterKommentar;
        $dispatchKommentar = '';
    }

    $ergebnis = socialDispatch($text, $imageUrl, $channels, $postId, $dispatchKommentar, $scheduledTime);

    if (!empty($ergebnis['fallback'])) {
        return [
            'ok'       => false,
            'fallback' => true,
            'message'  => (string) ($ergebnis['message'] ?? 'Auto-Posting nicht verfügbar — bitte manuell posten.'),
        ];
    }

    $terminiert = $scheduledTime !== null;

    // Versand-Log am Post + Fahrplan-Eintrag abschliessen (Wiederkehr rueckt vor). Terminiert:
    // an Meta uebergeben, aber NOCH NICHT live -> status 'terminiert', terminiert_fuer haelt den
    // Slot; live schaltet + Live-Mail feuert erst der Finalizer zum Slot (§4b), sonst Mail zu frueh.
    try {
        if ($terminiert) {
            $pdo->prepare(
                "UPDATE post_race_contents
                    SET status = 'terminiert', terminiert_fuer = FROM_UNIXTIME(:ts),
                        gesendet_kanaele = :kanaele, gesendet_ergebnis = :ergebnis
                  WHERE id = :id"
            )->execute([
                'ts'       => $scheduledTime,
                'kanaele'  => implode(',', $channels),
                'ergebnis' => mb_substr((string) ($ergebnis['message'] ?? 'bei Facebook terminiert'), 0, 255),
                'id'       => $postId,
            ]);
        } else {
            $pdo->prepare(
                "UPDATE post_race_contents
                    SET status = 'gesendet', gesendet_am = NOW(),
                        gesendet_kanaele = :kanaele, gesendet_ergebnis = :ergebnis
                  WHERE id = :id"
            )->execute([
                'kanaele'  => implode(',', $channels),
                'ergebnis' => mb_substr((string) ($ergebnis['message'] ?? 'an Make.com übergeben'), 0, 255),
                'id'       => $postId,
            ]);
        }

        $stmt = $pdo->prepare("SELECT id, zieldatum, frequenz_tage, ende FROM social_fahrplan WHERE post_id = :pid AND status = 'offen' LIMIT 1");
        $stmt->execute(['pid' => $postId]);
        if ($eintrag = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $vorgerueckt = false;
            if ($eintrag['frequenz_tage'] && $eintrag['zieldatum']) {
                $naechstes = (new DateTime($eintrag['zieldatum']))
                    ->modify('+' . (int) $eintrag['frequenz_tage'] . ' days')
                    ->format('Y-m-d');
                if ($eintrag['ende'] === null || $naechstes <= $eintrag['ende']) {
                    $pdo->prepare('UPDATE social_fahrplan SET zieldatum = :d, post_id = NULL WHERE id = :id')
                        ->execute(['d' => $naechstes, 'id' => $eintrag['id']]);
                    $vorgerueckt = true;
                }
            }
            if (!$vorgerueckt) {
                $pdo->prepare("UPDATE social_fahrplan SET status = 'erledigt' WHERE id = :id")
                    ->execute(['id' => $eintrag['id']]);
            }
        }
    } catch (PDOException $e) {
        logError('versendePost: Log/Fahrplan-Update fehlgeschlagen: ' . $e->getMessage());
    }

    // Live-Mail nur beim SOFORT-Versand (Post ist jetzt live). Beim terminierten Versand feuert
    // der Finalizer die Mail zum echten Slot (§4b) — sonst ginge die "erste-Stunde"-Mail Stunden
    // zu frueh raus.
    if (!$terminiert) {
        socialLiveMail($pdo, $post);
    }

    return [
        'ok'      => true,
        'message' => $terminiert
            ? 'Für ' . (new DateTimeImmutable('@' . $scheduledTime))->setTimezone(new DateTimeZone('Europe/Berlin'))->format('d.m. H:i') . ' Uhr bei Facebook terminiert (Instagram: Handoff in der Meta Business Suite).'
            : (string) ($ergebnis['message'] ?? 'An Make.com übergeben.'),
    ];
}

/**
 * "Post ist live"-Mail ans Orga-Team (Verstaerker der ersten Stunde, Inhaber-Entscheid 2026-08-14).
 * EINE Sammel-Mail (To: info@ via mailBccAddress(), Orga/Admins in BCC) statt einer je Empfaenger —
 * sonst stapeln sich die info@-BCC-Kopien (Mail-Flut). Kein Dashboard-Link: der Fahrplan-Eintrag
 * ist beim Lesen schon vorgerueckt. Fire-and-forget, Fehler nur ins Log. Ausgelagert, weil sie
 * beim Sofort-Versand (versendePost) UND beim Finalizer (finalisiereTerminiertePosts) feuert.
 */
function socialLiveMail(PDO $pdo, array $post): void
{
    try {
        $def   = socialAnlaesse()[(string) ($post['anlass_key'] ?? '')] ?? null;
        $thema = $def ? $def['ui'] : 'Social-Post';
        $mailText = "„{$thema}\"\n\n"
            . "**Die erste Stunde entscheidet - eine Sache reicht - gern auch mehr:**\n\n"
            . implode("\n", socialVerstaerkerErsteStunde()) . "\n"
            . "\nHier geht's direkt hin — der neue Post ist ganz oben:\n"
            . "→ Instagram: https://www.instagram.com/atsv_marktlauf_kirchseeon\n"
            . "→ Facebook: https://www.facebook.com/profile.php?id=61591689790244\n"
            . "\nDanke dir — gemeinsam läuft's besser! 🏃";
        $body = marktlaufMailBody($mailText);
        // marktlaufMailBody kann kein Fett — **…** nur fuer diese Mail uebersetzen
        $body['html'] = preg_replace('~\*\*(.+?)\*\*~s', '<strong>$1</strong>', $body['html']);
        $body['text'] = str_replace('**', '', $body['text']);
        $orgaMails = $pdo->query("
            SELECT name, email FROM users
            WHERE active = 1 AND role IN ('admin','orga') AND NULLIF(TRIM(email),'') IS NOT NULL
        ")->fetchAll();
        $empfaenger = array_values(array_unique(array_filter(array_map(
            static fn(array $o): string => trim((string) $o['email']),
            $orgaMails
        ))));
        if ($empfaenger !== []) {
            sendMail(
                mailBccAddress(),
                'Neuer Social-Post - Deine (Re-)Aktion ist gefragt! Jede Minute zählt 💚',
                $body['text'],
                $body['html'],
                [],
                $empfaenger
            );
        }
    } catch (Throwable $e) {
        logError('socialLiveMail: fehlgeschlagen: ' . $e->getMessage());
    }
}

/**
 * Finalisiert terminierte Posts, deren Slot erreicht ist (Spec §4b). Meta hat FB zum
 * scheduled_publish_time live geschaltet — FB ruft dazu nicht zurueck, deshalb ist der stuendliche
 * Timer (bin/social_versand.php) der Ausloeser: status 'terminiert' -> 'gesendet' + gesendet_am,
 * und die "erste-Stunde"-Mail feuert JETZT (nah am echten Veroeffentlichungszeitpunkt).
 * Idempotent: nach dem Flip faellt der Post aus dem Filter.
 *
 * @return int Anzahl finalisierter Posts.
 */
function finalisiereTerminiertePosts(PDO $pdo): int
{
    $posts = $pdo->query(
        "SELECT * FROM post_race_contents
          WHERE status = 'terminiert' AND terminiert_fuer IS NOT NULL AND terminiert_fuer <= NOW()
          ORDER BY terminiert_fuer ASC"
    )->fetchAll(PDO::FETCH_ASSOC);

    $n = 0;
    foreach ($posts as $post) {
        try {
            $pdo->prepare("UPDATE post_race_contents SET status = 'gesendet', gesendet_am = NOW() WHERE id = :id")
                ->execute(['id' => (int) $post['id']]);
            socialLiveMail($pdo, $post);
            $n++;
        } catch (Throwable $e) {
            logError('finalisiereTerminiertePosts: Post ' . (int) ($post['id'] ?? 0) . ': ' . $e->getMessage());
        }
    }
    return $n;
}
