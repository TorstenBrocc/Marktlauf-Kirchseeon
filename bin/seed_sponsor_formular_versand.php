<?php
/**
 * Einmaliger Seed: Kontaktformular-Anschreiben vom 11.08.2026 nachtragen
 * (BIG, ERDINGER, Ayinger, Adelholzener — KSK-Stiftung nur Entwurf, nicht gesendet).
 *
 * Je Sponsor: status neu → angefragt + gesendet_am (nur die vier Gesendeten),
 * Formular-URL sauber in kontaktweg, Versand-Notiz anhaengen, Drive-Ordner
 * (Orga/Sponsoren/<Firma>) sicherstellen und den gesendeten Text als .txt ablegen.
 * Idempotent: vorhandene Werte/Dateien werden erkannt und uebersprungen.
 *
 * Aufruf: ssh strato-marktlauf "cd ~/marktlauf/Homepage && MARKTLAUF_CLI=1 php bin/seed_sponsor_formular_versand.php"
 */

declare(strict_types=1);

// Nur per CLI/SSH ausführbar (Strato-SSH meldet cgi statt cli → Bypass via MARKTLAUF_CLI=1).
if (php_sapi_name() !== 'cli' && getenv('MARKTLAUF_CLI') !== '1') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/logger.php';
require_once __DIR__ . '/../src/google_drive.php';
require_once __DIR__ . '/../src/sponsor_rotation.php';

$pdo = getDbConnection();

const PROTECTED_STATUS = ['zugesagt', 'bestaetigt', 'abgerechnet', 'bezahlt'];

$signatur = "Torsten Tyras · Organisationsleiter Marktlauf, ATSV Kirchseeon e.V.\n"
          . "t.tyras@atsv-kirchseeon-marktlauf.de · 08091 – 9313";

// id => [firmaPrefix, gesendet(bool), kontaktweg_neu, notiz, dateiname, text]
$eintraege = [
    95 => [
        'prefix'     => 'BIG direkt gesund',
        'gesendet'   => true,
        'kontaktweg' => 'BIG Family Games: Bewerbung über https://bigfamilygames.de/ (Fallback-Formular: https://www.big-direkt.de/de/kontakt/nachricht-schreiben); Zentrale: Rheinische Str. 1, 44137 Dortmund',
        'notiz'      => 'Am 11.08.2026 über das Kontaktformular angeschrieben (BIG Family Games / Vereins-Paket, Fristen erfragt). Gesendeter Text liegt im Drive-Sponsorenordner.',
        'marker'     => 'Am 11.08.2026 über das Kontaktformular',
        'datei'      => '2026-08-11-anschreiben-kontaktformular.txt',
        'text'       => <<<TXT
Betreff: BIG Family Games — Bewerbung/Anfrage Marktlauf Kirchseeon (20.09.2026)

Guten Tag,

der ATSV Kirchseeon e.V. (1.800 Mitglieder) richtet am 20.09.2026 den Marktlauf
Kirchseeon aus — ein Familien-Laufevent mit Bambini-Lauf (500 m), Schülerläufen
und Hauptläufen über 5 und 10 km. Wir erwarten rund 250 Teilnehmende und 500
Zuschauer; der Eintritt ist frei, die Veranstaltung findet gemeinsam mit dem
Energie- und Umwelttag der Gemeinde Kirchseeon statt.

Familien und Breitensport sind der Kern unseres Events — genau die Zielgruppe
der BIG Family Games. Wir möchten uns daher für die Aktion bewerben bzw.
erfahren: Ist für 2026 noch eine Teilnahme möglich, und ab wann läuft die
nächste Ausschreibungsrunde? Gerne binden wir das Vereins-Paket (Werbemittel,
Preise, Material) sichtbar in den Zielbereich und das Rahmenprogramm ein.

Über eine kurze Rückmeldung zu Fristen und Voraussetzungen freuen wir uns.

Mit freundlichen Grüßen
SIGNATUR
TXT,
    ],
    105 => [
        'prefix'     => 'Privatbrauerei ERDINGER',
        'gesendet'   => true,
        'kontaktweg' => "Kontaktformular https://www.erdinger.de/kontakt → Thema 'Marketing'; operativ über Eventagentur kiecom (kiecom.de, Lager Aschheim b. München)",
        'notiz'      => 'Am 11.08.2026 über das Kontaktformular (Thema Marketing) angeschrieben: Anfrage Aktiv-Tour-Zielausschank. Gesendeter Text liegt im Drive-Sponsorenordner.',
        'marker'     => 'Am 11.08.2026 über das Kontaktformular',
        'datei'      => '2026-08-11-anschreiben-kontaktformular.txt',
        'text'       => <<<TXT
Betreff: ERDINGER Alkoholfrei Aktiv-Tour — Zielausschank beim Marktlauf Kirchseeon (20.09.2026)

Guten Tag,

der ATSV Kirchseeon e.V. veranstaltet am Sonntag, 20.09.2026 (Start 10:00 Uhr)
den Marktlauf Kirchseeon: Läufe vom Bambini (500 m) über Schülerläufe bis 5 km
und 10 km, rund 250 Läuferinnen und Läufer und ca. 500 Zuschauer im Zielbereich
mitten im Ort — bei freiem Eintritt und gekoppelt an den Energie- und Umwelttag
der Gemeinde Kirchseeon.

Wir würden den Marktlauf sehr gerne als Station der ERDINGER Alkoholfrei
Aktiv-Tour gewinnen: Ein Zielausschank für alle Finisher passt perfekt zu
unserem Lauf und Ihrem Auftritt bei Ausdauersport-Events im Münchner Umland.
Standfläche im Zielbereich, Strom und helfende Hände stellen wir; zusätzlich
bieten wir Logo-Präsenz an Start/Ziel, auf der Website und in Social Media.

Ist eine Aufnahme in die Tour-Planung 2026 noch möglich? Für Planung und
Werbemittel wäre eine Rückmeldung bis Ende August ideal — gerne auch direkt
über Ihre Eventagentur.

Mit freundlichen Grüßen
SIGNATUR
TXT,
    ],
    107 => [
        'prefix'     => 'Privatbrauerei Ayinger',
        'gesendet'   => true,
        'kontaktweg' => 'Kontaktformular https://www.brauerei-ayinger.de/kontakt/',
        'notiz'      => 'Am 11.08.2026 über das Kontaktformular angeschrieben: Getränkepartnerschaft (Zielbereich/Festbetrieb, Kategorie-Exklusivität nur für den Festbetrieb angeboten — Abgrenzung zu ERDINGER-Zielausschank). Gesendeter Text liegt im Drive-Sponsorenordner.',
        'marker'     => 'Am 11.08.2026 über das Kontaktformular',
        'datei'      => '2026-08-11-anschreiben-kontaktformular.txt',
        'text'       => <<<TXT
Betreff: Getränkepartnerschaft Marktlauf Kirchseeon (20.09.2026) — Anfrage aus der Nachbarschaft

Guten Tag,

der ATSV Kirchseeon e.V. (1.800 Mitglieder) veranstaltet am 20.09.2026 den
Marktlauf Kirchseeon, nur gut 12 km von Aying entfernt: Läufe für alle
Generationen (Bambini 500 m bis Hauptlauf 10 km), rund 250 Teilnehmende und
500 Zuschauer, Ziel und Festbetrieb mitten im Ort — bei freiem Eintritt und
gemeinsam mit dem Energie- und Umwelttag der Gemeinde.

Heimatverbundenheit und bayerische Bierkultur — genau das soll auch unser
Lauffest ausstrahlen. Deshalb fragen wir an, ob die Privatbrauerei Ayinger den
Marktlauf unterstützen mag: etwa mit alkoholfreiem Weißbier für den Zielbereich
oder Getränken für den Festbetrieb, als Sachleistung oder Sponsoring. Im
Gegenzug bieten wir Bannerflächen an Start/Ziel, Logo auf Website und Social
Media, einen Stand am Veranstaltungsgelände — auf Wunsch auch eine exklusive
Kategorie-Partnerschaft im Festbetrieb.

Wen dürfen wir dazu konkret ansprechen? Für die Planung wäre eine Rückmeldung
bis Ende August ideal. Vielen Dank und beste Grüße nach Aying!

SIGNATUR
TXT,
    ],
    108 => [
        'prefix'     => 'Adelholzener',
        'gesendet'   => true,
        'kontaktweg' => 'Kontaktformular https://www.adelholzener.de/kontakt/ (max. 800 Zeichen, Kontaktdaten werden separat abgefragt)',
        'notiz'      => 'Am 11.08.2026 über das Kontaktformular angeschrieben: Wasser/Active O2 für Verpflegungs- und Zielstation (Kurzfassung wg. 800-Zeichen-Limit). Gesendeter Text liegt im Drive-Sponsorenordner.',
        'marker'     => 'Am 11.08.2026 über das Kontaktformular',
        'datei'      => '2026-08-11-anschreiben-kontaktformular.txt',
        'text'       => <<<TXT
(Gesendet über das Kontaktformular, 800-Zeichen-Limit; Kontaktdaten separat abgefragt.)

Guten Tag,
am 20.09.2026 richtet der ATSV Kirchseeon e.V. den Marktlauf Kirchseeon aus — ein Familien-Laufevent im Landkreis Ebersberg mit Läufen von 500 m (Bambini) bis 10 km, rund 250 Teilnehmenden und ca. 500 Zuschauern, bei freiem Eintritt und gemeinsam mit dem Energie- und Umwelttag der Gemeinde.
Als bayerische Quelle mit gelebtem Sport-Engagement (BR-Radltour, FC Bayern) wären Sie unser Wunschpartner: Können Sie Mineralwasser (ggf. Active O2) für Verpflegungs- und Zielstation als Sachspende oder zu Sponsoring-Konditionen bereitstellen?
Im Gegenzug bieten wir Logo-Präsenz an den Ständen und im Zielbereich, auf Website und Social Media sowie Produktproben im Starterpaket. Für die Planung wäre eine Rückmeldung bis 30.08. ideal. Vielen Dank!
TXT,
    ],
    104 => [
        'prefix'     => 'Stiftungen der Kreissparkasse',
        'gesendet'   => false, // bewusst NICHT angeschrieben (KSK ist bereits Gold-Sponsor)
        'kontaktweg' => 'Antrag über https://www.kskmse.de/engagement (Antragslinks je Region — Stiftung für Lkr Ebersberg wählen)',
        'notiz'      => 'Antragstext-Entwurf vom 11.08.2026 liegt im Drive-Sponsorenordner (Förderbetrag noch offen). Noch nicht eingereicht.',
        'marker'     => 'Antragstext-Entwurf vom 11.08.2026',
        'datei'      => '2026-08-11-entwurf-foerderantrag.txt',
        'text'       => <<<TXT
(ENTWURF — noch nicht eingereicht. Vor dem Absenden Förderbetrag [Betrag] festlegen
und Freistellungsbescheid bereitlegen. Portal: https://www.kskmse.de/engagement,
Stiftung für Lkr Ebersberg wählen.)

Förderantrag: Marktlauf Kirchseeon 2026 — Breitensport-Lauffest für den ganzen Landkreis

Der ATSV Kirchseeon e.V. (1.800 Mitglieder, VR 30003, AG München) veranstaltet am
20.09.2026 den Marktlauf Kirchseeon: ein Lauffest mitten im Ort für alle
Generationen — vom Bambini-Lauf (500 m) über die Schülerläufe bis zu den
Hauptläufen über 5 und 10 km. Nach dem erfolgreichen Auftakt 2025 mit 130
Läuferinnen und Läufern und rund 250 Zuschauern erwarten wir 2026 etwa 250
Teilnehmende und 500 Gäste. Die Veranstaltung ist öffentlich, der Zutritt
kostenlos, und sie findet gemeinsam mit dem Energie- und Umwelttag der Gemeinde
Kirchseeon statt — getragen komplett von Ehrenamtlichen.

Der Gesamtbedarf für die Durchführung liegt bei rund 6.000 €. Wir bitten die
Stiftung um eine Förderung von [Betrag], insbesondere für die Kinder- und
Schülerläufe sowie die Strecken- und Zielinfrastruktur. Der Marktlauf senkt die
Einstiegshürde zum Laufsport bewusst: kurze Distanzen für Kinder, ein
familienfreundliches Rahmenprogramm und Startgebühren, die niemanden ausschließen.

Satzung, Freistellungsbescheid und eine detaillierte Kostenaufstellung reichen
wir gerne nach.

SIGNATUR
TXT,
    ],
];

foreach ($eintraege as $id => $e) {
    $st = $pdo->prepare('SELECT * FROM sponsors WHERE id = :id');
    $st->execute(['id' => $id]);
    $row = $st->fetch();
    if ($row === false) {
        echo "SKIP #{$id}: nicht gefunden\n";
        continue;
    }
    if (mb_stripos((string) $row['firma'], $e['prefix']) !== 0) {
        echo "SKIP #{$id}: Firma-Guard verletzt (ist '{$row['firma']}')\n";
        continue;
    }
    if (in_array((string) $row['status'], PROTECTED_STATUS, true)) {
        echo "SKIP #{$id} {$row['firma']}: Schutzstatus '{$row['status']}'\n";
        continue;
    }

    // 1) Stammdaten: Status/gesendet_am (nur Gesendete), kontaktweg, Notiz
    $set = [];
    $params = [];
    if ($e['gesendet'] && (string) $row['status'] === 'neu') {
        $set[] = "status = 'angefragt'";
        if (empty($row['gesendet_am'])) {
            $set[] = 'gesendet_am = NOW()';
        }
    }
    if (trim((string) ($row['kontaktweg'] ?? '')) !== trim($e['kontaktweg'])) {
        $set[] = 'kontaktweg = :kontaktweg';
        $params['kontaktweg'] = $e['kontaktweg'];
    }
    if (mb_stripos((string) ($row['notizen'] ?? ''), $e['marker']) === false) {
        $set[] = 'notizen = :notizen';
        $params['notizen'] = (trim((string) ($row['notizen'] ?? '')) === ''
            ? '' : rtrim((string) $row['notizen']) . "\n\n") . $e['notiz'];
    }
    if ($set !== []) {
        $params['id'] = $id;
        $pdo->prepare('UPDATE sponsors SET ' . implode(', ', $set) . ' WHERE id = :id')->execute($params);
        echo "UPD #{$id} {$row['firma']}: " . implode(', ', array_map(static fn ($s) => explode(' ', $s)[0], $set)) . "\n";
    } else {
        echo "OK  #{$id} {$row['firma']}: Stammdaten bereits auf Stand\n";
    }

    // 2) Drive: Sponsor-Ordner sicherstellen, Text ablegen (skip, wenn Datei schon existiert)
    try {
        $folderId = sponsorEnsureDriveFolder($pdo, $id, (string) $row['firma']);
        $vorhanden = array_column(driveListChildren($folderId), 'name');
        if (in_array($e['datei'], $vorhanden, true)) {
            echo "OK  #{$id}: Drive-Datei {$e['datei']} liegt bereits\n";
        } else {
            $tmp = tempnam(sys_get_temp_dir(), 'sptxt');
            file_put_contents($tmp, str_replace('SIGNATUR', $signatur, $e['text']) . "\n");
            driveUploadToFolder($folderId, $tmp, $e['datei'], 'text/plain');
            unlink($tmp);
            echo "DRV #{$id}: {$e['datei']} hochgeladen\n";
        }
    } catch (Throwable $ex) {
        logError("seed_sponsor_formular_versand #{$id} Drive: " . $ex->getMessage());
        echo "FEHLER #{$id} Drive: " . $ex->getMessage() . "\n";
    }
}

echo "Fertig.\n";
