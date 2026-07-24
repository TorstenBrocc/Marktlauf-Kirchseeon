<?php
/**
 * Vereins-/Laufevent-Anschreiben: Defaults, Laden, Platzhalter, Kontext.
 *
 * Bewusst KEIN eigener Markdown-Renderer: die generischen, abgesicherten
 * Renderer aus src/sponsor_brief.php (sponsorApplyInline, sponsorMdToHtml,
 * sponsorBriefRenderHtml/Text, sponsorSignatur, sponsorFormatDatum) werden hier
 * wiederverwendet. Dieses Modul liefert nur die vereins-/eventspezifischen
 * Vorlagen, Platzhalter und den Platzhalter-Kontext.
 *
 * Zwei Vorlagen (Slugs):
 *   - 'verein'    → Einladung an Sportvereine (mitlaufen + digitale Vernetzung)
 *   - 'laufevent' → Veranstalter-zu-Veranstalter (Terminabgleich, gegenseitig bewerben)
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/sponsor_brief.php'; // generische Renderer + Signatur-Datenquelle

/** Gültige Vorlagen-Slugs (= Anschreiben-Typen). */
function vereinBriefSlugs(): array {
    return ['verein', 'laufevent'];
}

function vereinBriefSlugValid(string $slug): bool {
    return in_array($slug, vereinBriefSlugs(), true);
}

/**
 * Standard-Vorlagen (Betreff + Markdown-Körper). Einzige Quelle der Wahrheit.
 * @return array<string, array{name:string, betreff:string, koerper_md:string}>
 */
function vereinBriefDefaults(): array {
    $verein = <<<MD
{{anrede}}

am **{{event_datum}}** veranstaltet der ATSV Kirchseeon 1906 e.V. wieder den **Marktlauf Kirchseeon** – in diesem Jahr unter dem Motto „E-Mobilität & Sport" und eingebettet in den Energie- & Umwelttag der Gemeinde. Start und Ziel sind am JEK, Westring 6, 85614 Kirchseeon.

Von der Bambini-Distanz (500 m) über die Schülerläufe (1 km und 2 km) bis zu den 5-km- und 10-km-Strecken für Jugendliche und Erwachsene ist für jede Altersklasse und jedes Leistungsniveau etwas dabei – ein Familienfest ebenso wie ein Wettkampf. Die ideale Gelegenheit, mit einer Gruppe oder Vereinsmannschaft aus {{name}} gemeinsam an den Start zu gehen.

Wir würden uns sehr freuen, wenn Ihr die Einladung an Eure Mitglieder weitergebt – besonders an Eure lauf- und leichtathletikbegeisterten Sportlerinnen und Sportler. Für Rückfragen zu einer gemeinsamen Anmeldung sind wir jederzeit da.

**Ein Gedanke über den Lauftag hinaus:** Wir möchten die regionale Laufszene enger vernetzen. Verratet Ihr uns Eure Online-Kanäle (Website, Instagram, Facebook, ggf. Strava-Club)? Dann verlinken und markieren wir Euch gern in unserer Ankündigung – und freuen uns, wenn Ihr den Marktlauf im Gegenzug bei Euren Mitgliedern teilt. Sehr gern verlinken wir uns auch wechselseitig auf unseren Websites und stimmen einen gemeinsamen Post oder eine kurze Erwähnung in Euren Kanälen ab.

Alle Infos zu Strecken, Zeitplan und Anmeldung findet Ihr unter:
https://atsv-kirchseeon-marktlauf.de

Über eine Weitergabe an Eure Mitglieder und ein zahlreiches Wiedersehen an der Startlinie freuen wir uns sehr.

{{signatur}}
MD;

    $laufevent = <<<MD
{{anrede}}

wir melden uns als Team des **Marktlauf Kirchseeon** (ATSV Kirchseeon 1906 e.V.). Am **{{event_datum}}** veranstalten wir unseren Lauf am Westring in Kirchseeon – von der Bambini-Distanz bis 10 km, eingebettet in den Energie- & Umwelttag der Gemeinde.

Als Veranstalter im selben Umkreis liegt uns an einer guten Nachbarschaft in der regionalen Laufszene. Deshalb kommen wir mit ein paar Ideen auf {{name}} zu:

- **Termine im Blick behalten:** Wir gleichen unsere Lauftermine gern miteinander ab, damit wir uns nicht gegenseitig die Teilnehmer streitig machen.
- **Gegenseitig bewerben:** Wir teilen Euren Lauf in unseren Kanälen und bei unseren Läuferinnen und Läufern – und freuen uns, wenn Ihr das mit dem Marktlauf ebenso macht.
- **Wechselseitig verlinken:** Gern nehmen wir Euch in einen Bereich „Läufe in der Region" auf unserer Website auf und freuen uns über einen Link zurück.
- **Erfahrungen austauschen:** Zeitmessung, Genehmigungen, Helfer, Sponsoren – vieles lässt sich unter Veranstaltern leichter lösen.

Verratet Ihr uns Eure Online-Kanäle (Website, Instagram, Facebook)? Dann können wir direkt mit dem gegenseitigen Verlinken und Teilen loslegen.

Mehr zu unserem Lauf: https://atsv-kirchseeon-marktlauf.de

Über eine kurze Rückmeldung, ob Ihr Lust auf so eine Vernetzung habt, freuen wir uns sehr.

{{signatur}}
MD;

    return [
        'verein' => [
            'name'       => 'Vereins-Einladung',
            'betreff'    => 'Einladung zum Marktlauf Kirchseeon – lauft mit {{name}} mit!',
            'koerper_md' => $verein,
        ],
        'laufevent' => [
            'name'       => 'Laufevent / Veranstalter-Vernetzung',
            'betreff'    => 'Grüße vom Marktlauf Kirchseeon – Terminabgleich & gegenseitiges Bewerben',
            'koerper_md' => $laufevent,
        ],
    ];
}

/** Liste der verfügbaren Platzhalter für die Editor-Referenz. */
function vereinBriefPlatzhalterHilfe(): array {
    return [
        '{{anrede}}'      => "Persönliche/kollegiale Anrede – wird automatisch generiert:\n"
                             . "• Frau + Nachname → \"Liebe Frau Hopfes,\"\n"
                             . "• Herr + Nachname → \"Lieber Herr Müller,\"\n"
                             . "• Verein ohne Kontakt → \"Liebe Sportfreundinnen und Sportfreunde des TSV …,\"\n"
                             . "• Laufevent ohne Kontakt → \"Liebe Laufsport-Kolleginnen und -Kollegen vom …,\"",
        '{{vorname}}'     => 'Vorname des Ansprechpartners',
        '{{name}}'        => 'Name des Vereins bzw. des Laufevents',
        '{{event_datum}}' => 'Datum des Marktlaufs (aus Einstellungen, sonst 20. September 2026)',
        '{{signatur}}'    => "Signatur-Block (Sportliche Grüße, Name, Aufgabe, Telefon, E-Mail)\n"
                             . "Die persönlichen Daten stammen aus der Benutzerverwaltung (dein Profil).",
    ];
}

/**
 * Vorlage laden: DB-Überschreibung über Default gemergt.
 * @return array{name:string, betreff:string, koerper_md:string}
 */
function vereinBriefLoad(PDO $pdo, string $slug): array {
    $defaults = vereinBriefDefaults();
    $base = $defaults[$slug] ?? $defaults['verein'];

    try {
        $stmt = $pdo->prepare('SELECT name, betreff, koerper_md FROM verein_briefvorlagen WHERE slug = :slug');
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();
        if ($row) {
            $betreff = trim((string) ($row['betreff'] ?? ''));
            $koerper = trim((string) ($row['koerper_md'] ?? ''));
            return [
                'name'       => (string) ($row['name'] ?? $base['name']),
                'betreff'    => $betreff !== '' ? $betreff : $base['betreff'],
                'koerper_md' => $koerper !== '' ? $koerper : $base['koerper_md'],
            ];
        }
    } catch (PDOException $e) {
        // Tabelle evtl. noch nicht migriert -> Default nutzen
    }

    return $base;
}

/* ---- Platzhalter-Kontext -------------------------------------------------- */

/**
 * Kollegiale Anrede mit kaskadierendem Fallback. Persönlich, wenn ein
 * Ansprechpartner (Anrede + Nachname) hinterlegt ist, sonst kollektiv –
 * je nach Kategorie (Verein vs. Laufevent) im passenden Ton.
 */
function vereinAnrede(string $kategorie, string $anrede, string $nachname, string $name = ''): string {
    $nachname = trim($nachname);
    if ($nachname !== '' && $anrede === 'Frau') {
        return "Liebe Frau {$nachname},";
    }
    if ($nachname !== '' && $anrede === 'Herr') {
        return "Lieber Herr {$nachname},";
    }
    $name = trim($name);
    if ($kategorie === 'laufevent') {
        return $name !== ''
            ? "Liebe Laufsport-Kolleginnen und -Kollegen vom {$name},"
            : 'Liebe Laufsport-Kolleginnen und -Kollegen,';
    }
    return $name !== ''
        ? "Liebe Sportfreundinnen und Sportfreunde des {$name},"
        : 'Liebe Sportfreundinnen und Sportfreunde,';
}

/**
 * Signatur-Block (HTML + Text). Nutzt dieselbe Datenquelle wie die Sponsoren
 * (Benutzerprofil bzw. Config-Fallback), aber mit „Sportliche Grüße" und
 * Vereins-Rolle statt Sponsoring-Rolle.
 */
function vereinSignatur(PDO $pdo, int $userId): array {
    $sig = sponsorSignatur($pdo, $userId);
    $role = $sig['role'] !== '' && stripos($sig['role'], 'sponsor') === false
        ? $sig['role']
        : 'Marktlauf-Organisationsteam · ATSV Kirchseeon 1906 e.V.';

    $sigRoleHtml  = 'Marktlauf-Organisationsteam · ATSV Kirchseeon 1906 e.V.<br>';
    $roleLine     = $role !== '' ? htmlspecialchars($role) . '<br>' : '';
    $sigPhoneHtml = $sig['phone'] !== '' ? 'T: ' . htmlspecialchars($sig['phone']) : '';
    $sigEmailHtml = $sig['email'] !== '' ? ($sigPhoneHtml !== '' ? ' | ' : '') . 'M: <a href="mailto:' . htmlspecialchars($sig['email']) . '">' . htmlspecialchars($sig['email']) . '</a>' : '';
    $html = '<p>Sportliche Grüße<br><br>'
        . '<strong>' . htmlspecialchars($sig['name']) . '</strong><br>'
        . $sigRoleHtml
        . ($sigPhoneHtml . $sigEmailHtml !== '' ? $sigPhoneHtml . $sigEmailHtml . '<br>' : '')
        . 'W: <a href="https://atsv-kirchseeon-marktlauf.de">atsv-kirchseeon-marktlauf.de</a></p>';

    $parts = [];
    if ($sig['phone'] !== '') $parts[] = 'T: ' . $sig['phone'];
    if ($sig['email'] !== '') $parts[] = 'M: ' . $sig['email'];
    $parts[] = 'W: atsv-kirchseeon-marktlauf.de';
    $text = "Sportliche Grüße\n\n{$sig['name']}\nMarktlauf-Organisationsteam · ATSV Kirchseeon 1906 e.V.\n" . implode(' | ', $parts);

    return ['html' => $html, 'text' => $text];
}

/** Event-Datum aus Einstellungen (mit den Sponsoren geteilt), sonst Default. */
function vereinEventDatum(PDO $pdo): string {
    $eventDatum = '20. September 2026';
    try {
        $stmt = $pdo->prepare("SELECT `value` FROM einstellungen WHERE `key` = 'sponsor_brief_event_datum'");
        $stmt->execute();
        $val = (string) ($stmt->fetchColumn() ?: '');
        if ($val !== '') {
            $eventDatum = sponsorFormatDatum($val, $eventDatum);
        }
    } catch (PDOException $e) {}
    return $eventDatum;
}

/**
 * Platzhalter-Kontext aufbauen — gleiche Struktur wie sponsorBriefContext,
 * damit die generischen Renderer (sponsorBriefRenderHtml/Text) direkt greifen.
 * @return array{inline:array<string,string>, blocksHtml:array<string,string>, blocksText:array<string,string>}
 */
function vereinBriefContext(PDO $pdo, int $userId, string $kategorie, string $anrede, string $vorname, string $nachname, string $name): array {
    $sig = vereinSignatur($pdo, $userId);
    return [
        'inline' => [
            '{{anrede}}'      => vereinAnrede($kategorie, $anrede, $nachname, $name),
            '{{vorname}}'     => trim($vorname),
            '{{name}}'        => trim($name) !== '' ? trim($name) : 'Eurem Verein',
            '{{event_datum}}' => vereinEventDatum($pdo),
        ],
        'blocksHtml' => [
            'signatur' => $sig['html'],
        ],
        'blocksText' => [
            'signatur' => $sig['text'],
        ],
    ];
}

/** Beispiel-Kontext für die Editor-Vorschau (keine echten Empfängerdaten nötig). */
function vereinBriefBeispielContext(PDO $pdo, int $userId = 0, string $kategorie = 'verein'): array {
    $name = $kategorie === 'laufevent' ? 'TSV Beispielhausen' : 'TSV Musterverein';
    return vereinBriefContext($pdo, $userId, $kategorie, '', '', '', $name);
}
