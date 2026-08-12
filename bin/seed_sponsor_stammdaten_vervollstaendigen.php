<?php
/**
 * Einmaliger Seed: Sponsoren-Stammdaten vervollstaendigen (2026-08-12).
 * Grundlage: intern/sponsor-stammdaten-abgleich-2026-08.md §Nachtrag.
 *
 * Drei Bloecke:
 *   1. Telefon-Normalisierung — verlustfrei. Der Juli-CSV-Import hat Nummern als
 *      "49810629061" / "+4981062744" abgelegt (Landesvorwahl ohne Formatierung).
 *      Diese werden rein mechanisch ins deutsche Format "08106 29061" gebracht;
 *      es wird KEINE Ziffer erfunden. Nummern in Exponentialschreibweise
 *      ("4,98106E+11") sind dagegen echt verloren und werden hier NICHT angefasst,
 *      sondern nur durch recherchierte Werte ersetzt (Block 3).
 *   2. Ort + Website aus dem eigenen Datensatz ableiten (Adresse steht in
 *      notizen/kontaktweg; Website = Domain der hinterlegten Firmen-Mail, sofern
 *      kein Freemailer und Domain erreichbar).
 *   3. Recherchierte/verifizierte Telefonnummern setzen (Quelle je Eintrag im Code).
 *
 * Guards: Sponsoren mit Status zugesagt/bestaetigt/abgerechnet/bezahlt bleiben
 * unangetastet. Vorhandene Werte werden nie ueberschrieben (nur leere gefuellt) —
 * einzige Ausnahme: die nachweislich kaputten Telefon-Rohwerte aus Block 1/3.
 *
 * Aufruf: ssh strato-marktlauf "cd ~/marktlauf/Homepage && MARKTLAUF_CLI=1 php bin/seed_sponsor_stammdaten_vervollstaendigen.php"
 */

declare(strict_types=1);

// Nur per CLI/SSH ausführbar (Strato-SSH meldet cgi statt cli → Bypass via MARKTLAUF_CLI=1).
if (php_sapi_name() !== 'cli' && getenv('MARKTLAUF_CLI') !== '1') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../src/db.php';

$pdo = getDbConnection();

const PROTECTED_STATUS = ['zugesagt', 'bestaetigt', 'abgerechnet', 'bezahlt'];

/** Sponsor-Status laden; null wenn nicht vorhanden. */
function sponsorStatus(PDO $pdo, int $id): ?string
{
    $st = $pdo->prepare('SELECT status FROM sponsors WHERE id = :id');
    $st->execute(['id' => $id]);
    $s = $st->fetchColumn();
    return $s === false ? null : (string) $s;
}

function istGeschuetzt(PDO $pdo, int $id, string $firma = ''): bool
{
    $status = sponsorStatus($pdo, $id);
    if ($status === null) {
        echo "SKIP #{$id} {$firma}: nicht gefunden\n";
        return true;
    }
    if (in_array($status, PROTECTED_STATUS, true)) {
        echo "SKIP #{$id} {$firma}: Schutzstatus '{$status}'\n";
        return true;
    }
    return false;
}

// ---------------------------------------------------------------------------
// Block 1 — Telefon-Normalisierung (verlustfrei, rein mechanisch)
// ---------------------------------------------------------------------------

/**
 * "49810629061" / "+4981062744" → "08106 29061".
 * Nur wenn der Rohwert ausschliesslich aus Ziffern (optional fuehrendes +) besteht
 * und mit der Laendervorwahl 49 beginnt. Alles andere bleibt unberuehrt.
 * Gibt null zurueck, wenn nicht sicher normalisierbar.
 */
function normalisiereTelefon(string $roh, array $vorwahlen): ?string
{
    $t = trim($roh);
    if ($t === '' || !preg_match('/^\+?49[0-9]{6,}$/', $t)) {
        return null;
    }
    $ziffern = ltrim($t, '+');
    $rest = substr($ziffern, 2); // Laendervorwahl 49 abschneiden

    foreach ($vorwahlen as $vw) {
        if (str_starts_with($rest, $vw) && strlen($rest) > strlen($vw)) {
            return '0' . $vw . ' ' . substr($rest, strlen($vw));
        }
    }
    return null; // unbekannte Vorwahl → lieber nicht raten
}

// Regionale Vorwahlen (laengste zuerst pruefen, damit 08106 vor 089 greift)
$vorwahlen = ['8106', '8091', '8092', '8093', '8095', '8102', '8107', '8122', '8662', '89'];

$kandidaten = $pdo->query("
    SELECT ap.id, ap.sponsor_id, s.firma, s.status, ap.telefon
    FROM sponsor_ansprechpartner ap
    JOIN sponsors s ON s.id = ap.sponsor_id
    WHERE ap.telefon REGEXP '^\\\\+?49[0-9]{6,}$'
")->fetchAll();

$updTel = $pdo->prepare('UPDATE sponsor_ansprechpartner SET telefon = :t WHERE id = :id');

foreach ($kandidaten as $k) {
    if (in_array((string) $k['status'], PROTECTED_STATUS, true)) {
        echo "SKIP AP#{$k['id']} ({$k['firma']}): Schutzstatus '{$k['status']}'\n";
        continue;
    }
    $neu = normalisiereTelefon((string) $k['telefon'], $vorwahlen);
    if ($neu === null) {
        echo "OFFEN AP#{$k['id']} ({$k['firma']}): '{$k['telefon']}' nicht sicher normalisierbar\n";
        continue;
    }
    $updTel->execute(['t' => $neu, 'id' => $k['id']]);
    echo "TEL AP#{$k['id']} ({$k['firma']}): '{$k['telefon']}' → '{$neu}'\n";
}

// EBERwerk: Stray-Pipe aus dem Rohwert entfernen (keine Ziffer veraendert).
$st = $pdo->prepare("
    UPDATE sponsor_ansprechpartner SET telefon = '08092 33090-60'
    WHERE sponsor_id = 3 AND telefon = '08092 | 330 90 -60'
");
$st->execute();
echo $st->rowCount() > 0 ? "TEL #3 EBERwerk: Pipe-Artefakt bereinigt\n" : "OK  #3 EBERwerk: Telefon bereits sauber\n";

// ---------------------------------------------------------------------------
// Block 2 — Ort + Website aus dem eigenen Datensatz ableiten (nur wenn leer)
// ---------------------------------------------------------------------------

// Ort: steht jeweils als Adresse in den eigenen notizen/kontaktweg (Quelle: Recherche-v2-Import)
$orte = [
    77 => 'München',      // Versicherungskammer Bayern (Konzernsitz, Recherche v2)
    78 => 'München',      // VK Stiftung — Maximilianstr. 53, 80530 München
    79 => 'München',      // die Bayerische — "Sitz München"
    80 => 'Kirchseeon',   // Allianz: relevanter Weg = Generalvertretung Wasserburger Str. 5
    85 => 'München',      // TK — LV Bayern in München
    86 => 'München',      // Barmer — Landsberger Str. 187, 80687 München
    87 => 'München',      // DAK — LV Bayern in München
    88 => 'München',      // AOK Bayern — Pressestelle München
    89 => 'München',      // KKH — Landshuter Allee 4-6, 80637 München
    91 => 'München',      // SBK — Heimeranstr. 31, 80339 München
    92 => 'Ingolstadt',   // Audi BKK — Ferdinand-Braun-Str. 6, 85053 Ingolstadt
    93 => 'Ludwigsburg',  // mhplus — Franckstr. 8, 71636 Ludwigsburg
    94 => 'Bremen',       // hkk — Sitz Bremen
    95 => 'Dortmund',     // BIG — Rheinische Str. 1, 44137 Dortmund
    96 => 'Essen',        // Knappschaft — Zentrale 45095 Essen
    97 => 'München',      // BKK LV Bayern — Züricher Str. 25, 81476 München
    104 => 'Ebersberg',   // KSK-Stiftungen — fuer den Marktlauf: Stiftung Lkr Ebersberg
];

$updOrt = $pdo->prepare("UPDATE sponsors SET ort = :o WHERE id = :id AND (ort IS NULL OR ort = '')");
foreach ($orte as $id => $ort) {
    if (istGeschuetzt($pdo, $id)) {
        continue;
    }
    $updOrt->execute(['o' => $ort, 'id' => $id]);
    echo $updOrt->rowCount() > 0 ? "ORT #{$id}: {$ort}\n" : "OK  #{$id}: Ort bereits gesetzt\n";
}

// Website: Domain der hinterlegten Firmen-Mail. Nur nicht-Freemailer, und nur
// Domains, die am 12.08.2026 per HTTP-Check erreichbar waren (hagebaumarkt-ebersberg.de
// war es nicht → bewusst nicht gesetzt).
$websites = [
    4  => 'ea-ebe-m.de',
    9  => 'radlarzt.de',
    12 => 'energie-spezialisten.de',
    13 => 'glonntaler.de',
    15 => 'hoenninger.de',
    20 => 'schweiger-bier.de',
    22 => 'emberger-bau.de',
    26 => 'hoermann-automotive.com',
    27 => 'hoermann-ws.de',
    28 => 'hoermann-kn.de',
    36 => 'machtsinn.bayern',
];

$updWeb = $pdo->prepare("UPDATE sponsors SET website = :w WHERE id = :id AND (website IS NULL OR website = '')");
foreach ($websites as $id => $web) {
    if (istGeschuetzt($pdo, $id)) {
        continue;
    }
    $updWeb->execute(['w' => $web, 'id' => $id]);
    echo $updWeb->rowCount() > 0 ? "WEB #{$id}: {$web}\n" : "OK  #{$id}: Website bereits gesetzt\n";
}

// ---------------------------------------------------------------------------
// Block 3 — Verifizierte Telefonnummern (Quelle je Eintrag)
// ---------------------------------------------------------------------------

/** Telefon am (ersten) Ansprechpartner setzen; AP anlegen, wenn keiner existiert. */
function setzeTelefon(PDO $pdo, int $sponsorId, string $telefon, string $quelle): void
{
    if (istGeschuetzt($pdo, $sponsorId)) {
        return;
    }
    $st = $pdo->prepare('SELECT id, telefon FROM sponsor_ansprechpartner WHERE sponsor_id = :sid ORDER BY id LIMIT 1');
    $st->execute(['sid' => $sponsorId]);
    $ap = $st->fetch();

    if ($ap === false) {
        $pdo->prepare('INSERT INTO sponsor_ansprechpartner (sponsor_id, telefon) VALUES (:sid, :t)')
            ->execute(['sid' => $sponsorId, 't' => $telefon]);
        echo "TEL #{$sponsorId}: {$telefon} (neuer AP, Quelle: {$quelle})\n";
        return;
    }

    $ist = trim((string) $ap['telefon']);
    if ($ist === $telefon) {
        echo "OK  #{$sponsorId}: Telefon bereits {$telefon}\n";
        return;
    }
    // Nur setzen, wenn leer ODER der Ist-Wert nachweislich kaputt ist (Exponentialschreibweise).
    $kaputt = $ist !== '' && preg_match('/E\+|,/', $ist) === 1;
    if ($ist !== '' && !$kaputt) {
        echo "OFFEN #{$sponsorId}: Telefon '{$ist}' vorhanden — nicht ueberschrieben\n";
        return;
    }
    $pdo->prepare('UPDATE sponsor_ansprechpartner SET telefon = :t WHERE id = :id')
        ->execute(['t' => $telefon, 'id' => $ap['id']]);
    echo "TEL #{$sponsorId}: " . ($kaputt ? "'{$ist}' → " : '') . "{$telefon} (Quelle: {$quelle})\n";
}

// Aus dem jeweiligen Impressum verifiziert (12.08.2026)
setzeTelefon($pdo, 105, '08122 409-0', 'erdinger.de/impressum');
setzeTelefon($pdo, 108, '08662 62-0', 'adelholzener.de/impressum');

/**
 * Recherchierte Nummern ersetzen die durch Excel zerstoerten Exponential-Werte.
 * Regel bei widerspruechlichen Quellen: das eigene Impressum der Firma gewinnt,
 * die abweichende Variante wird als Notiz am Sponsor dokumentiert (nicht still
 * verworfen). quellenurl wird nur gesetzt, wenn noch leer.
 * Websites nur, wenn die Domain am 12.08.2026 per HTTP erreichbar war
 * (sabrina-baudrexl.de antwortete nicht → bewusst leer).
 */
$recherche = [
    37 => ['tel' => '08106 994360',  'web' => 'heigenmoser.de',
           'quelle' => 'https://heigenmoser.de/kontakt/'],
    40 => ['tel' => '08106 998072',  'web' => 'functiomed.de',
           'quelle' => 'https://functiomed.de/kontakt'],
    41 => ['tel' => '08106 9978001', 'web' => null,
           'quelle' => 'https://adresse.dastelefonbuch.de/Zorneding/1-Physiotherapie-Baudrexl-Zorneding-Georg-Wimmer-Ring.html',
           'notiz'  => 'Telefon aus dem Telefonbuch übernommen — die eigene Website sabrina-baudrexl.de war am 12.08.2026 nicht erreichbar (TLS-Fehler).'],
    42 => ['tel' => '0160 6323128',  'web' => 'physio-holistic.de',
           'quelle' => 'https://physio-holistic.de/impressum/',
           'notiz'  => 'Nur Mobilnummer verfügbar (so im Impressum). Achtung Ortsbezug: Das Impressum nennt Vaterstetten (Anschrift) und Grasbrunn (Praxis); die Zorneding-Adresse (Herzogplatz 9) steht nur in der Datenschutzerklärung.'],
    46 => ['tel' => '089 68919840',  'web' => 'rauschhuber-haustechnik.de',
           'quelle' => 'https://rauschhuber-haustechnik.de/impressum',
           'notiz'  => 'Quellen widersprechen sich: Das eigene Impressum führt nur München (Thierseestr. 14, Tel. 089 68919840) — diese Nummer ist hier hinterlegt. Zwei Branchenverzeichnisse führen dagegen weiterhin Zorneding (Ingelsberg 16, Tel. 08106 3070140). Beim Anruf klären, welcher Standort gilt.'],
    54 => ['tel' => '08106 247763',  'web' => 'kuechenwelt-becker.de',
           'quelle' => 'https://www.dasoertliche.de/Themen/Küchenwelt-Becker-Zorneding-Am-Ziegelland',
           'notiz'  => 'Telefon aus zwei übereinstimmenden Verzeichnissen (eigene Kontaktseite lädt nur per JavaScript).'],
    56 => ['tel' => '08091 567833',  'web' => 'fahrschule-aschmann.de',
           'quelle' => 'https://fahrschule-aschmann.de/impressum',
           'notiz'  => 'Hinterlegt ist der Firmensitz Kirchseeon (Hauptstr. 41a, 08091 567833) — dort fallen die Entscheidungen, und der Ortsbezug zum Marktlauf ist der bessere. Zweigstelle Zorneding (Bahnwiesenstr. 2): 08106 303062.'],
    61 => ['tel' => '08106 241280',  'web' => 'glasls-landhotel.de',
           'quelle' => 'https://www.glasls-landhotel.de/en/contact/'],
    38 => ['tel' => '08106 8925255', 'web' => 'deine-alternative.com',
           'quelle' => 'https://www.deine-alternative.com/impressum'],
    55 => ['tel' => '08106 219883',  'web' => 'steffis-schreibwaren.de',
           'quelle' => 'https://adresse.dastelefonbuch.de/Zorneding/1-Spielwaren-Stefanie-Berndlmeier-Zorneding-Obere-Bahnhofstr.html',
           'notiz'  => 'Inhaberin Stefanie Berndlmeier. Telefon aus dem Telefonbuch — die eigene Website hat keine abrufbare Kontaktseite.'],
    63 => ['tel' => '01525 4691255', 'web' => 'skeide-coaching.de',
           'quelle' => 'https://skeide-coaching.de/impressum/',
           'notiz'  => 'Nur Mobilnummer verfügbar (so im Impressum).'],
];

$updQuelle = $pdo->prepare("UPDATE sponsors SET quellenurl = :q WHERE id = :id AND (quellenurl IS NULL OR quellenurl = '')");

foreach ($recherche as $id => $r) {
    if (istGeschuetzt($pdo, $id)) {
        continue;
    }
    setzeTelefon($pdo, $id, $r['tel'], $r['quelle']);

    if ($r['web'] !== null) {
        $updWeb->execute(['w' => $r['web'], 'id' => $id]);
        if ($updWeb->rowCount() > 0) {
            echo "WEB #{$id}: {$r['web']}\n";
        }
    }

    $updQuelle->execute(['q' => $r['quelle'], 'id' => $id]);
    if ($updQuelle->rowCount() > 0) {
        echo "QUE #{$id}: Quelle hinterlegt\n";
    }

    if (isset($r['notiz'])) {
        $st = $pdo->prepare('SELECT notizen FROM sponsors WHERE id = :id');
        $st->execute(['id' => $id]);
        $ist = (string) ($st->fetchColumn() ?: '');
        // Marker: erste 40 Zeichen der Notiz — verhindert Doppel-Anhaenge bei Re-Runs
        $marker = mb_substr($r['notiz'], 0, 40);
        if (mb_stripos($ist, $marker) === false) {
            $neu = ($ist === '' ? '' : rtrim($ist) . "\n\n") . 'Datenpflege 12.08.2026: ' . $r['notiz'];
            $pdo->prepare('UPDATE sponsors SET notizen = :n WHERE id = :id')
                ->execute(['n' => $neu, 'id' => $id]);
            echo "NOT #{$id}: Hinweis ergänzt\n";
        }
    }
}

echo "Fertig.\n";
