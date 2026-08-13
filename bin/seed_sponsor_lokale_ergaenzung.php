<?php
/**
 * Einmaliger Seed: drei lokale Einträge vervollständigen + überfällige Wiedervorlagen
 * nachziehen (13.08.2026). Grundlage: intern/sponsoren-abarbeitung-2026-08-12.md Teil D.
 *
 * 66 Elektro Naumann · 69 link-protect · 70 Urgibl — waren bis auf den Namen leer.
 * Alle Angaben aus dem jeweiligen Impressum bzw. belegten Verzeichnissen, Quelle steht
 * je Eintrag in quellenurl.
 *
 * Guards wie gehabt: zugesagt/bestaetigt/abgerechnet/bezahlt bleiben unangetastet;
 * vorhandene Werte werden nur gefuellt, wenn leer; Notizen werden angehaengt.
 *
 * Aufruf: ssh strato-marktlauf "cd ~/marktlauf/Homepage && MARKTLAUF_CLI=1 php bin/seed_sponsor_lokale_ergaenzung.php"
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli' && getenv('MARKTLAUF_CLI') !== '1') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../src/db.php';

$pdo = getDbConnection();

const PROTECTED_STATUS = ['zugesagt', 'bestaetigt', 'abgerechnet', 'bezahlt'];

/**
 * Stammdaten vervollstaendigen. firma wird nur ersetzt, wenn der Ist-Wert exakt dem
 * erwarteten Platzhalter entspricht (sonst hat jemand anders schon gepflegt).
 */
function vervollstaendige(PDO $pdo, int $id, string $erwarteterName, array $d): void
{
    $st = $pdo->prepare('SELECT * FROM sponsors WHERE id = :id');
    $st->execute(['id' => $id]);
    $row = $st->fetch();
    if ($row === false) {
        echo "SKIP #{$id}: nicht gefunden\n";
        return;
    }
    if (trim((string) $row['firma']) !== $erwarteterName) {
        echo "SKIP #{$id}: Firma ist '{$row['firma']}', erwartet '{$erwarteterName}' — jemand hat schon gepflegt\n";
        return;
    }
    if (in_array((string) $row['status'], PROTECTED_STATUS, true)) {
        echo "SKIP #{$id}: Schutzstatus '{$row['status']}'\n";
        return;
    }

    $set = [];
    $params = [];

    if (isset($d['firma']) && $d['firma'] !== $erwarteterName) {
        $set[] = 'firma = :firma';
        $params['firma'] = $d['firma'];
    }
    foreach (['ort', 'website', 'quellenurl', 'branche'] as $feld) {
        if (isset($d[$feld]) && trim((string) ($row[$feld] ?? '')) === '') {
            $set[] = "{$feld} = :{$feld}";
            $params[$feld] = $feld === 'branche' ? json_encode($d[$feld]) : $d[$feld];
        }
    }
    if (isset($d['notiz']) && mb_stripos((string) ($row['notizen'] ?? ''), $d['marker']) === false) {
        $set[] = 'notizen = :notizen';
        $params['notizen'] = (trim((string) ($row['notizen'] ?? '')) === ''
            ? '' : rtrim((string) $row['notizen']) . "\n\n") . $d['notiz'];
    }

    if ($set !== []) {
        $params['id'] = $id;
        $pdo->prepare('UPDATE sponsors SET ' . implode(', ', $set) . ' WHERE id = :id')->execute($params);
        echo "UPD #{$id} " . ($d['firma'] ?? $row['firma']) . ': '
            . implode(', ', array_map(static fn ($s) => explode(' ', $s)[0], $set)) . "\n";
    } else {
        echo "OK  #{$id}: bereits auf Stand\n";
    }

    // Ansprechpartner: anlegen, wenn die E-Mail (bzw. bei leerer Mail die Nummer) fehlt
    if (isset($d['ap'])) {
        $ap = $d['ap'] + ['anrede' => '', 'vorname' => '', 'nachname' => '', 'funktion' => '', 'telefon' => '', 'email' => ''];
        $vorhanden = $pdo->prepare('SELECT id, telefon, email FROM sponsor_ansprechpartner WHERE sponsor_id = :sid');
        $vorhanden->execute(['sid' => $id]);
        $treffer = false;
        foreach ($vorhanden->fetchAll() as $e) {
            $gleicheMail = $ap['email'] !== '' && mb_strtolower(trim((string) $e['email'])) === mb_strtolower($ap['email']);
            $gleicheNr   = $ap['email'] === '' && $ap['telefon'] !== '' && trim((string) $e['telefon']) === $ap['telefon'];
            if ($gleicheMail || $gleicheNr) {
                $treffer = true;
                break;
            }
            // Bestehende Kontaktzeile ohne Mail (z. B. nur Mobilnummer) anreichern
            if (trim((string) $e['email']) === '' && $ap['email'] !== '') {
                $pdo->prepare('UPDATE sponsor_ansprechpartner SET email = :m WHERE id = :apid')
                    ->execute(['m' => $ap['email'], 'apid' => $e['id']]);
                echo "AP  #{$id}: E-Mail am vorhandenen Kontakt ergänzt\n";
                $treffer = true;
                break;
            }
        }
        if (!$treffer) {
            $ap['sid'] = $id;
            $pdo->prepare('
                INSERT INTO sponsor_ansprechpartner (sponsor_id, anrede, vorname, nachname, funktion, telefon, email)
                VALUES (:sid, :anrede, :vorname, :nachname, :funktion, :telefon, :email)
            ')->execute([
                'sid' => $id, 'anrede' => $ap['anrede'], 'vorname' => $ap['vorname'],
                'nachname' => $ap['nachname'], 'funktion' => $ap['funktion'],
                'telefon' => $ap['telefon'], 'email' => $ap['email'],
            ]);
            echo "AP  #{$id}: Ansprechpartner angelegt\n";
        }
    }
}

// --- 66 · Elektro Naumann --------------------------------------------------
vervollstaendige($pdo, 66, 'Elektro Naumann', [
    'ort'        => 'Kirchseeon',
    'branche'    => ['Handwerk & Bau'],
    'website'    => 'elektro-naumann-muenchen.de',
    'quellenurl' => 'https://elektro-naumann-muenchen.de/',
    'notiz'      => 'Recherche 13.08.2026: Elektro Naumann (Inhaber Gerhard Naumann), Münchner Str. 98, 85614 Kirchseeon, Tel. 08091 1884. Fachbetrieb für Elektrotechnik (Installation, Smart Home, Wallbox, Sicherheitstechnik), über 30 Jahre am Ort. WARMER KONTAKT: läuft laut Notiz über „Daniel vom Tennis" — vor dem Anschreiben Daniel fragen, wer der richtige Ansprechpartner ist. Die hinterlegte Mobilnummer 0171 2374559 stammt aus diesem Kontakt.',
    'marker'     => 'Münchner Str. 98',
    'ap'         => ['nachname' => 'Naumann', 'funktion' => 'Inhaber', 'telefon' => '08091 1884'],
]);

// --- 69 · link protect GmbH ------------------------------------------------
vervollstaendige($pdo, 69, 'link-protect', [
    'firma'      => 'link protect GmbH',
    'ort'        => 'Kirchseeon',
    'branche'    => ['IT & Digitales'],
    'website'    => 'linkprotect.de',
    'quellenurl' => 'https://www.linkprotect.de/impressum',
    'notiz'      => 'Recherche 13.08.2026: IT-Security, IT-Consulting, Infrastruktur, Managed Services, Cloud. Münchner Str. 92, 85614 Kirchseeon (zweiter Standort Nürnberg, Hauptsitz ist Kirchseeon). Geschäftsführer Frank Mann, HRB 156657 AG München. Ortsansässiger Arbeitgeber — guter lokaler Kandidat.',
    'marker'     => 'Münchner Str. 92',
    'ap'         => ['anrede' => 'Herr', 'vorname' => 'Frank', 'nachname' => 'Mann', 'funktion' => 'Geschäftsführer', 'telefon' => '08091 5384400', 'email' => 'info@linkprotect.de'],
]);

// --- 70 · Urgibl grün erleben (Gartencenter) -------------------------------
vervollstaendige($pdo, 70, 'Urgibl', [
    'firma'      => 'Urgibl grün erleben (Gartencenter Urgibl)',
    'ort'        => 'Kirchseeon',
    'branche'    => ['Handel & Gastronomie'],
    'website'    => 'urgibl.de',
    'quellenurl' => 'https://www.urgibl.de/impressum',
    'notiz'      => 'Recherche 13.08.2026: Gartencenter/Gärtnerei mit Floristik (Pflanzen, Sträuße, Hochzeits- und Trauerfloristik, Gartengestaltung). Riederinger Str. 2, 85614 Eglharting — Ortsteil von Kirchseeon, also direkt vor Ort. Familienbetrieb in 2. Generation, gegründet 1953, geführt von Willi Urgibl jun. und Regina Urgibl. Firmierung uneinheitlich (Website: „Urgibl grün erleben", Verzeichnisse: „Gartencenter Urgibl"); Rechtsform nicht belegt, im Impressum kein Handelsregistereintrag — im Anschreiben daher „Familie Urgibl" statt einer Rechtsform verwenden. Naheliegende Sachleistung: Blumen/Pflanzen für die Siegerehrung.',
    'marker'     => 'Riederinger Str. 2',
    'ap'         => ['nachname' => 'Urgibl', 'funktion' => 'Inhaber', 'telefon' => '08091 53901-0', 'email' => 'buero1@urgibl.de'],
]);

// --- Überfällige Wiedervorlagen nachziehen ---------------------------------
// Ein Datum in der Vergangenheit verschwindet aus jeder sinnvollen Sicht. Nur dort
// setzen, wo die Wiedervorlage tatsaechlich abgelaufen ist.
$wv = [
    3  => ['2026-08-18', 'EBERwerk'],           // AB besprochen 12.08.
    33 => ['2026-08-14', 'Die Handwerker R & M'], // seit 17.07. ueberfaellig
];
foreach ($wv as $id => [$datum, $name]) {
    $st = $pdo->prepare("
        UPDATE sponsors SET wiedervorlage = :d
        WHERE id = :id AND wiedervorlage IS NOT NULL AND wiedervorlage < CURDATE()
          AND status NOT IN ('zugesagt','bestaetigt','abgerechnet','bezahlt')
    ");
    $st->execute(['d' => $datum, 'id' => $id]);
    echo $st->rowCount() > 0
        ? "WV  #{$id} {$name}: überfällige Wiedervorlage auf {$datum} gesetzt\n"
        : "OK  #{$id} {$name}: Wiedervorlage aktuell\n";
}

echo "Fertig.\n";
