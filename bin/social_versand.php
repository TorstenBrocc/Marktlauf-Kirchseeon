#!/usr/bin/env php
<?php
/**
 * CLI-Tool: "Beste Sendezeit"-Timer — zweiphasig je Lauf (Spec social-auto-versand-beste-zeit-spec.md
 * §4b/S3/S4, Bau-Entscheid TT 2026-08-27). Grundlage #3: social-auto-versand-stichtag-spec.md.
 *
 * Phase 1 — FINALISIEREN: terminierte Posts, deren Slot erreicht ist, live schalten
 *   (status 'terminiert' -> 'gesendet') + "erste-Stunde"-Mail feuern. FB ruft zur echten
 *   Veroeffentlichung nicht zurueck, deshalb finalisiert der stuendliche Timer nah am Slot.
 * Phase 2 — TERMINIEREN: NUR vom Menschen freigegebene Opt-in-Posts (status='approved' UND
 *   auto_versand=1 UND faellig). Faellig = Fahrplan-Stichtag heute bis 2 Tage alt (Catch-up gegen
 *   GitHub-Actions-Verzug/Wochenende). Zielzeit = geplante_uhrzeit › bester FB-Slot › 12:00.
 *   Liegt sie >15 Min in der Zukunft -> FB nativ terminiert (Meta scheduled_publish_time);
 *   sonst (Catch-up) -> FB sofort. INSTAGRAM postet der Timer NIE (§4a) -> Handoff-Kachel im
 *   Post-Detail. Gemeinsame Versandlogik (src/social_versand.php), identisch zum manuellen Klick.
 *
 * Cron: stuendlich 06:00-22:00 CEST (social_versand.yml) — frueh terminieren, nah am Slot finalisieren.
 *
 * Aufruf (SSH):  MARKTLAUF_CLI=1 php bin/social_versand.php
 *
 * Concurrency: MySQL GET_LOCK, damit Cron + ein manueller Lauf sich nicht ueberholen und
 * denselben Post doppelt senden. Niemals als Web-Request laufen lassen.
 */

// Strato: SSH-Shell liefert cgi-fcgi statt cli → Bypass via MARKTLAUF_CLI=1
if (php_sapi_name() !== 'cli' && getenv('MARKTLAUF_CLI') !== '1') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/logger.php';
require_once __DIR__ . '/../src/social_versand.php';

const SOCIAL_VERSAND_LOCK = 'social_versand_stichtag';

function svLog(string $m): void
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $m . "\n";
}

try {
    $pdo = getDbConnection();

    // Nur ein Versandlauf gleichzeitig (Cron + manuell duerfen sich nicht ueberholen).
    $lock = $pdo->query("SELECT GET_LOCK('" . SOCIAL_VERSAND_LOCK . "', 5)")->fetchColumn();
    if ((int) $lock !== 1) {
        svLog('Lock belegt — anderer Lauf aktiv, Abbruch.');
        exit(0);
    }

    // Phase 1 — Finalisieren: terminierte Posts, deren Slot erreicht ist, live schalten + Mail.
    $finalisiert = finalisiereTerminiertePosts($pdo);
    if ($finalisiert > 0) {
        svLog("$finalisiert terminierte(r) Post(s) live geschaltet (finalisiert).");
    }

    // Phase 2 — Terminieren: faellige Auto-Posts (freigegeben + Opt-in + Stichtag im Catch-up-
    // Fenster heute .. -2 Tage). f.zieldatum wird mitgelesen, um den Slot-Zeitpunkt zu bilden.
    $posts = $pdo->query(
        "SELECT p.*, f.zieldatum AS f_zieldatum
           FROM post_race_contents p
           JOIN social_fahrplan f ON f.post_id = p.id AND f.status = 'offen'
          WHERE p.status = 'approved'
            AND p.auto_versand = 1
            AND f.zieldatum IS NOT NULL
            AND f.zieldatum <= CURDATE()
            AND f.zieldatum >= (CURDATE() - INTERVAL 2 DAY)
          ORDER BY f.zieldatum ASC, p.id ASC"
    )->fetchAll(PDO::FETCH_ASSOC);

    if ($posts === []) {
        svLog('Keine faelligen Auto-Posts.');
        $pdo->query("SELECT RELEASE_LOCK('" . SOCIAL_VERSAND_LOCK . "')");
        exit(0);
    }

    // Best-Zeiten einmal laden (Einstellungen), fuer die FB-Slot-Berechnung je Post.
    $bszStruktur = besteSendezeitenStruktur($pdo);
    $jetzt       = time();
    $vorlaufSek  = 15 * 60; // FB braucht >=10 Min Vorlauf; +Puffer gegen GH-Cron-Verzug.

    svLog(count($posts) . ' faellige(r) Auto-Post(s).');
    foreach ($posts as $post) {
        $postId = (int) $post['id'];

        // Instagram postet der Timer NIE (§4a) -> nur Facebook, sofern der Post FB als Opt-in-Kanal
        // hat. IG-only-Opt-in (oder kein FB) -> kein Auto-Versand, IG laeuft ueber die Handoff-Kachel.
        $optIn = array_map('trim', explode(',', (string) ($post['auto_versand_channels'] ?? '')));
        if ($optIn === [''] || $optIn === []) {
            $optIn = ['facebook']; // leer = Default FB (IG ist ohnehin Handoff)
        }
        if (!in_array('facebook', $optIn, true)) {
            svLog("Post $postId uebersprungen — nur Instagram gewaehlt, laeuft ueber Meta-Business-Handoff.");
            continue;
        }
        $channels = ['facebook'];

        // Zielzeit: Wunsch-Sendezeit › bester FB-Slot fuer den Wochentag › 12:00 (Fallback mittags).
        $zieldatum = (string) ($post['f_zieldatum'] ?? '');
        $wochentag = $zieldatum !== '' ? (int) date('N', (int) strtotime($zieldatum)) : 0;
        $uhrzeit   = '';
        if (!empty($post['geplante_uhrzeit'])) {
            $uhrzeit = substr((string) $post['geplante_uhrzeit'], 0, 5);
        } elseif ($wochentag > 0) {
            $uhrzeit = besteSlotFuer($bszStruktur, 'facebook', $wochentag);
        }
        if ($uhrzeit === '') {
            $uhrzeit = '12:00';
        }
        // Slot-Zeit ist als Europe/Berlin gemeint (die Best-Zeiten sind lokale Uhrzeiten) — explizit
        // in dieser Zone bilden, unabhaengig von der php.ini-Default-TZ des Servers (sonst wuerde der
        // Slot um den Offset verschoben terminiert).
        $slotDt = DateTimeImmutable::createFromFormat('Y-m-d H:i', $zieldatum . ' ' . $uhrzeit, new DateTimeZone('Europe/Berlin'));
        $slotTs = $slotDt ? $slotDt->getTimestamp() : (int) strtotime($zieldatum . ' ' . $uhrzeit);

        // >15 Min in der Zukunft -> FB terminieren; sonst (Catch-up / Slot vorbei) -> sofort.
        $scheduledTime = ($slotTs > $jetzt + $vorlaufSek) ? $slotTs : null;

        // Erster Kommentar: gespeicherter Wert am Post (im CLI gibt es keinen Screen).
        $ersterKommentar = trim((string) ($post['erster_kommentar'] ?? ''));

        try {
            $r = versendePost($pdo, $post, $channels, $ersterKommentar, $scheduledTime);
            if (!empty($r['ok'])) {
                $wie = $scheduledTime !== null
                    ? ('terminiert fuer ' . (new DateTimeImmutable('@' . $scheduledTime))->setTimezone(new DateTimeZone('Europe/Berlin'))->format('d.m. H:i'))
                    : 'sofort gesendet';
                svLog("Post $postId $wie (facebook).");
            } else {
                // Fallback/Fehler: Post bleibt 'approved' fuer den naechsten Lauf (bis Catch-up-Grenze).
                logError("social_versand: Post $postId nicht gesendet: " . (string) ($r['message'] ?? '?'));
                svLog("Post $postId NICHT gesendet: " . (string) ($r['message'] ?? '?'));
            }
        } catch (Throwable $e) {
            logError("social_versand: Post $postId Ausnahme: " . $e->getMessage());
            svLog("Post $postId Ausnahme: " . $e->getMessage());
        }
    }

    $pdo->query("SELECT RELEASE_LOCK('" . SOCIAL_VERSAND_LOCK . "')");
} catch (Throwable $e) {
    logError('social_versand: Lauf-Fehler: ' . $e->getMessage());
    fwrite(STDERR, 'Fehler: ' . $e->getMessage() . "\n");
    exit(1);
}
