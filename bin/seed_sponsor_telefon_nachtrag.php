<?php
/**
 * Einmaliger Seed: fehlende Telefonnummern nachtragen (13.08.2026).
 * Grundlage: intern/sponsoren-abarbeitung-2026-08-12.md Teil B3.
 *
 * Betrifft die Einträge, die bisher nur eine E-Mail hatten. Quelle je Eintrag im Code;
 * 11 von 13 aus der Primärquelle (Impressum/Kontaktseite der Firma selbst), zwei nur über
 * Branchenverzeichnisse — die sind unten ausdrücklich als solche markiert.
 *
 * Zusätzlich drei Datenkorrekturen, die bei der Recherche auffielen (Ort/Website).
 * Guards wie gehabt; vorhandene Werte werden nie überschrieben.
 *
 * Aufruf: ssh strato-marktlauf "cd ~/marktlauf/Homepage && MARKTLAUF_CLI=1 php bin/seed_sponsor_telefon_nachtrag.php"
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli' && getenv('MARKTLAUF_CLI') !== '1') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../src/db.php';

$pdo = getDbConnection();

const PROTECTED_STATUS = ['zugesagt', 'bestaetigt', 'abgerechnet', 'bezahlt'];

/** Telefon am ersten Ansprechpartner setzen — nur wenn dort noch keines steht. */
function telefonNachtragen(PDO $pdo, int $id, string $firmaPrefix, string $telefon, string $quelle): void
{
    $st = $pdo->prepare('SELECT firma, status FROM sponsors WHERE id = :id');
    $st->execute(['id' => $id]);
    $s = $st->fetch();
    if ($s === false) {
        echo "SKIP #{$id}: nicht gefunden\n";
        return;
    }
    if (mb_stripos((string) $s['firma'], $firmaPrefix) !== 0) {
        echo "SKIP #{$id}: Firma-Guard verletzt (ist '{$s['firma']}')\n";
        return;
    }
    if (in_array((string) $s['status'], PROTECTED_STATUS, true)) {
        echo "SKIP #{$id} {$s['firma']}: Schutzstatus '{$s['status']}'\n";
        return;
    }

    $ap = $pdo->prepare('SELECT id, telefon FROM sponsor_ansprechpartner WHERE sponsor_id = :sid ORDER BY id LIMIT 1');
    $ap->execute(['sid' => $id]);
    $row = $ap->fetch();

    if ($row === false) {
        $pdo->prepare('INSERT INTO sponsor_ansprechpartner (sponsor_id, telefon) VALUES (:sid, :t)')
            ->execute(['sid' => $id, 't' => $telefon]);
        echo "TEL #{$id} {$s['firma']}: {$telefon} (neuer AP, {$quelle})\n";
        return;
    }
    if (trim((string) $row['telefon']) !== '') {
        echo "OK  #{$id} {$s['firma']}: Telefon bereits vorhanden ('{$row['telefon']}')\n";
        return;
    }
    $pdo->prepare('UPDATE sponsor_ansprechpartner SET telefon = :t WHERE id = :apid')
        ->execute(['t' => $telefon, 'apid' => $row['id']]);
    echo "TEL #{$id} {$s['firma']}: {$telefon} ({$quelle})\n";
}

/** Notiz anhängen (Marker verhindert Doppel-Anhänge). */
function notizAnhaengen(PDO $pdo, int $id, string $notiz, string $marker): void
{
    $st = $pdo->prepare('SELECT notizen, status FROM sponsors WHERE id = :id');
    $st->execute(['id' => $id]);
    $row = $st->fetch();
    if ($row === false || in_array((string) $row['status'], PROTECTED_STATUS, true)) {
        return;
    }
    $ist = (string) ($row['notizen'] ?? '');
    if (mb_stripos($ist, $marker) !== false) {
        return;
    }
    $pdo->prepare('UPDATE sponsors SET notizen = :n WHERE id = :id')->execute([
        'n'  => (trim($ist) === '' ? '' : rtrim($ist) . "\n\n") . $notiz,
        'id' => $id,
    ]);
    echo "NOT #{$id}: Hinweis ergänzt\n";
}

// --- Primärquelle: eigenes Impressum / eigene Kontaktseite ------------------
telefonNachtragen($pdo, 4,  'Energieagentur',        '08092 33090-30',  'energieagentur-ebe-m.de/Impressum');
telefonNachtragen($pdo, 9,  'Radsport Kirchseeon',   '0152 58460698',   'radlarzt.de/impressum.html');
telefonNachtragen($pdo, 13, 'Glonntaler',            '08136 8095337',   'glonntaler.de/kontakt');
telefonNachtragen($pdo, 15, 'Dipl.-Ing. Emil Hönninger', '08091 5508-0', 'hoenninger.de/impressum');
telefonNachtragen($pdo, 20, 'Privatbrauerei Schweiger', '08121 929-0',  'schweiger-bier.de/impressum');
telefonNachtragen($pdo, 22, 'Emberger',              '08092 9006',      'emberger-bau.de/impressum');
telefonNachtragen($pdo, 26, 'HÖRMANN Automotive',    '08091 5630-0',    'hoermann-automotive.com/impressum');
telefonNachtragen($pdo, 27, 'HÖRMANN Warnsysteme',   '08091 5630-300',  'hoermann-ws.de/impressum');
telefonNachtragen($pdo, 28, 'HÖRMANN Kommunikation', '08091 5630-200',  'hoermann-kn.de/impressum');
telefonNachtragen($pdo, 36, 'machtSINN',             '08024 6088924',   'machtsinn.bayern/impressum');
telefonNachtragen($pdo, 12, 'Energie-Spezialisten',  '089 80958347',    'energie-spezialisten.de/impressum');

// --- Nur über Branchenverzeichnisse belegt (im Eintrag kenntlich gemacht) ---
telefonNachtragen($pdo, 19, 'Bauzentrum Honold',     '08092 2329-0',    'Verzeichnisse 11880/hagebau.de');
telefonNachtragen($pdo, 34, 'Heizung-Sanitär-Weiler', '08091 2235',     'Verzeichnis dasoertliche.de');

// --- Hinweise zu unsicheren bzw. korrigierten Einträgen ---------------------
notizAnhaengen($pdo, 19,
    'Recherche 13.08.2026: ACHTUNG Namensprüfung nötig. Der Markt firmiert als „hagebaumarkt Ebersberg GmbH & Co. KG", Langwied 2, 85560 Ebersberg (GF Joachim Ricker, Mathias Lehmann; Gesellschafter Bauzentrum Mayer/Gural/Josef Schwarz & Sohn). Ein „Bauzentrum Honold" ist in keiner Quelle auffindbar — der Name im CRM ist vermutlich falsch. Die Domain hagebaumarkt-ebersberg.de antwortet nicht (mehrfach geprüft), daher Telefon 08092 2329-0 nur über drei übereinstimmende Verzeichnisse belegt, nicht über die Firma selbst.',
    'ACHTUNG Namensprüfung nötig');

notizAnhaengen($pdo, 34,
    'Recherche 13.08.2026: Keine eigene Website, nur Verzeichnisse — Telefon daher nicht firmenoffiziell bestätigt. Ilching 15, 85614 Kirchseeon. Das Örtliche führt „Weiler Brigitte" mit Festnetz 08091 2235 (hier hinterlegt), 11880 führt unter derselben Adresse eine Mobilnummer 0157 89110817. Creditreform kennt den Betrieb als „Florian Weiler Sanitär- und Heizungsbau". Zuerst Festnetz probieren.',
    'nicht firmenoffiziell bestätigt');

notizAnhaengen($pdo, 12,
    'Recherche 13.08.2026: ORT KORRIGIERT — die Firma sitzt laut eigenem Impressum in München (Pienzenauerstr. 52, 81679 München, HRB 281411, GF Dr. Günther Westner, Daniel Keller, Christoph Braun), nicht in Kirchseeon. Website ist aktiv (Beiträge bis August 2026). Der frühere Vermerk „keinen Kontakt gefunden" dürfte daher rühren, dass unter Kirchseeon gesucht wurde. Bitte prüfen, ob wirklich diese Firma gemeint war — ein lokaler Bezug ist nicht auffindbar.',
    'ORT KORRIGIERT');

notizAnhaengen($pdo, 13,
    'Recherche 13.08.2026: Kein regionaler Bezug — Sitz und Werk liegen in Markt Indersdorf, Landkreis Dachau (Vorwahl 08136; das ist die Glonn bei Dachau, nicht der Markt Glonn im Lkr. Ebersberg). Firmenname und Domain stimmen, es ist also keine Verwechslung, aber auch kein ortsansässiger Betrieb. Verwaltung/Verkauf 08136 8095337, Mischanlage 08136 5060.',
    'Kein regionaler Bezug');

notizAnhaengen($pdo, 4,
    'Recherche 13.08.2026: Geschäftsführer Dr. Willie Stiehler hat keine eigene Durchwahl — gleiche Nummer wie die Zentrale. Zweitbüro München/Haar: 089 27780890-0.',
    'keine eigene Durchwahl');

notizAnhaengen($pdo, 9,
    'Recherche 13.08.2026: Firmiert als Radlarzt UG (haftungsbeschränkt), Rathausstraße 14, Kirchseeon. Im Impressum steht nur eine Mobilnummer, kein Festnetz.',
    'Radlarzt UG');

$hoermann = 'Recherche 13.08.2026: Die drei HÖRMANN-Töchter sitzen unter derselben Adresse (Hauptstr. 45-47, Kirchseeon) am Anlagenanschluss 08091 5630, haben aber je eine eigene Durchwahlgruppe: Automotive -0 (zugleich Zentrale), Kommunikation & Netze -200, Warnsysteme -300. Direkt bei der jeweiligen Gesellschaft anrufen, nicht über die Zentrale.';
foreach ([26, 27, 28] as $hid) {
    notizAnhaengen($pdo, $hid, $hoermann, 'eigene Durchwahlgruppe');
}

// --- Website-Korrekturen ---------------------------------------------------
// ea-ebe-m.de leitet per 302 auf die tatsächliche Domain um.
$st = $pdo->prepare("UPDATE sponsors SET website = 'energieagentur-ebe-m.de' WHERE id = 4 AND website = 'ea-ebe-m.de'");
$st->execute();
echo $st->rowCount() > 0 ? "WEB #4: auf energieagentur-ebe-m.de korrigiert (alte Domain leitet um)\n" : "OK  #4: Website bereits korrekt\n";

// Ort-Korrektur Energie-Spezialisten (Beleg: eigenes Impressum)
$st = $pdo->prepare("UPDATE sponsors SET ort = 'München' WHERE id = 12 AND ort = 'Kirchseeon' AND status NOT IN ('zugesagt','bestaetigt','abgerechnet','bezahlt')");
$st->execute();
echo $st->rowCount() > 0 ? "ORT #12: Kirchseeon → München korrigiert\n" : "OK  #12: Ort bereits korrekt\n";

// --- Allianz: lokale Generalvertretung als Kontaktzeile -------------------
// Die Nummer stand bisher nur im Notizfeld — als Ansprechpartner ist sie in der
// Maske direkt nutzbar (und taucht in Listen/Exporten auf).
$st = $pdo->prepare("SELECT COUNT(*) FROM sponsor_ansprechpartner WHERE sponsor_id = 80 AND telefon = '08091 5383836'");
$st->execute();
if ((int) $st->fetchColumn() === 0) {
    $pdo->prepare("
        INSERT INTO sponsor_ansprechpartner (sponsor_id, nachname, funktion, telefon)
        VALUES (80, 'Gillhuber e.K.', 'Generalvertretung Kirchseeon', '08091 5383836')
    ")->execute();
    echo "AP  #80 Allianz: Generalvertretung Gillhuber als Kontakt angelegt\n";
} else {
    echo "OK  #80 Allianz: Kontakt bereits vorhanden\n";
}

echo "Fertig.\n";
