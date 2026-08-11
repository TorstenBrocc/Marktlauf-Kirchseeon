<?php
/**
 * Einmaliger Seed: Sponsoren-Stammdaten-Abgleich v2 (2026-08-11).
 * Grundlage: intern/sponsor-stammdaten-abgleich-2026-08.md (von TT freigegeben).
 *
 * Idempotent, auf EXAKTE Sponsor-IDs gepinnt (Snapshot 2026-08-11), mit Guards:
 *   - Updates nur bei Status ausserhalb (zugesagt, bestaetigt, abgerechnet, bezahlt);
 *     einzige freigegebene Ausnahme: ID 8 quellenurl (nur wenn leer).
 *   - Firma-Praefix muss zur ID passen, sonst SKIP.
 *   - Felder werden nur gefuellt, wenn leer; kontaktweg/notizen der unveraenderten
 *     v1-Recherche-Eintraege werden ersetzt, aber nur wenn der Ist-Wert noch dem
 *     v1-Stand (oder schon dem Zielwert) entspricht — sonst MANUELL-Meldung.
 *   - Ansprechpartner nur anlegen, wenn E-Mail/Telefon noch nicht vorhanden.
 *   - Neuanlagen nur, wenn die Firma (normalisiert) noch nicht existiert.
 *
 * Aufruf: ssh strato-marktlauf "cd ~/marktlauf/Homepage && MARKTLAUF_CLI=1 php bin/seed_sponsor_stammdaten_v2.php"
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

// ---------------------------------------------------------------------------
// Helfer
// ---------------------------------------------------------------------------

/** Sponsor-Zeile laden und Guards pruefen (Existenz, Firma-Praefix, Schutzstatus). */
function loadGuarded(PDO $pdo, int $id, string $firmaPrefix, bool $allowProtected = false): ?array
{
    $st = $pdo->prepare('SELECT * FROM sponsors WHERE id = :id');
    $st->execute(['id' => $id]);
    $row = $st->fetch();
    if ($row === false) {
        echo "SKIP #{$id}: nicht gefunden\n";
        return null;
    }
    if (mb_stripos((string) $row['firma'], $firmaPrefix) !== 0) {
        echo "SKIP #{$id}: Firma-Guard verletzt (ist '{$row['firma']}', erwartet '{$firmaPrefix}…')\n";
        return null;
    }
    if (!$allowProtected && in_array((string) $row['status'], PROTECTED_STATUS, true)) {
        echo "SKIP #{$id} {$row['firma']}: Schutzstatus '{$row['status']}'\n";
        return null;
    }
    return $row;
}

/** Feld setzen, nur wenn bislang leer. Liefert SQL-Fragmente + Parameter. */
function fillIfEmpty(array $row, array $fields, array &$set, array &$params): void
{
    foreach ($fields as $col => $val) {
        if (trim((string) ($row[$col] ?? '')) === '') {
            $set[] = "{$col} = :{$col}";
            $params[$col] = $val;
        }
    }
}

/**
 * kontaktweg/notizen vom v1- auf den v2-Stand heben. Ersetzt nur, wenn der
 * Ist-Wert noch exakt dem erwarteten v1-Text entspricht (oder schon Ziel ist).
 */
function replaceIfUntouched(array $row, array $transitions, array &$set, array &$params): void
{
    foreach ($transitions as $col => [$expected, $new]) {
        $current = trim((string) ($row[$col] ?? ''));
        if ($current === trim($new)) {
            continue; // schon auf Zielstand
        }
        if ($current === trim($expected) || $current === '') {
            $set[] = "{$col} = :{$col}";
            $params[$col] = $new;
        } else {
            echo "  MANUELL #{$row['id']} {$row['firma']}: {$col} wurde zwischenzeitlich editiert, v2-Text nicht angewendet\n";
        }
    }
}

/** Notiz anhaengen (mit Marker-Substring als Idempotenz-Check). */
function appendNote(array $row, string $note, string $marker, array &$set, array &$params): void
{
    $current = (string) ($row['notizen'] ?? '');
    if (mb_stripos($current, $marker) !== false) {
        return;
    }
    $set[] = 'notizen = :notizen';
    $params['notizen'] = ($current === '' ? '' : rtrim($current) . "\n\n") . $note;
}

function applyUpdate(PDO $pdo, int $id, array $set, array $params): void
{
    if ($set === []) {
        echo "OK  #{$id}: bereits auf Stand\n";
        return;
    }
    $params['id'] = $id;
    $pdo->prepare('UPDATE sponsors SET ' . implode(', ', $set) . ' WHERE id = :id')->execute($params);
    echo "UPD #{$id}: " . implode(', ', array_map(static fn ($s) => explode(' ', $s)[0], $set)) . "\n";
}

/** Ansprechpartner anlegen, wenn weder E-Mail noch (bei leerer Mail) Telefon vorhanden. */
function ensureAp(PDO $pdo, int $sponsorId, array $ap): void
{
    $st = $pdo->prepare('SELECT id, email, telefon FROM sponsor_ansprechpartner WHERE sponsor_id = :sid');
    $st->execute(['sid' => $sponsorId]);
    foreach ($st->fetchAll() as $e) {
        if ($ap['email'] !== '' && mb_strtolower(trim((string) $e['email'])) === mb_strtolower($ap['email'])) {
            // E-Mail schon da — fehlendes Telefon nachtragen, vorhandenes nie ueberschreiben
            if (($ap['telefon'] ?? '') !== '' && trim((string) $e['telefon']) === '') {
                $pdo->prepare('UPDATE sponsor_ansprechpartner SET telefon = :t WHERE id = :id')
                    ->execute(['t' => $ap['telefon'], 'id' => $e['id']]);
                echo "AP  #{$sponsorId}: Telefon nachgetragen ({$ap['telefon']})\n";
            }
            return;
        }
        if ($ap['email'] === '' && $ap['telefon'] !== '' && trim((string) $e['telefon']) === $ap['telefon']) {
            return; // reine Telefon-Zeile schon da
        }
    }
    $pdo->prepare('
        INSERT INTO sponsor_ansprechpartner (sponsor_id, anrede, vorname, nachname, funktion, telefon, email)
        VALUES (:sid, :anrede, :vorname, :nachname, :funktion, :telefon, :email)
    ')->execute([
        'sid'      => $sponsorId,
        'anrede'   => $ap['anrede'] ?? '',
        'vorname'  => $ap['vorname'] ?? '',
        'nachname' => $ap['nachname'] ?? '',
        'funktion' => $ap['funktion'] ?? '',
        'telefon'  => $ap['telefon'] ?? '',
        'email'    => $ap['email'] ?? '',
    ]);
    echo "AP  #{$sponsorId}: +{$ap['email']}{$ap['telefon']}\n";
}

// ---------------------------------------------------------------------------
// 1) Freigegebene Ausnahme: KSK MSE (ID 8, bestaetigt) — NUR quellenurl, nur wenn leer
// ---------------------------------------------------------------------------

if (($row = loadGuarded($pdo, 8, 'Kreissparkasse', true)) !== null) {
    $set = [];
    $params = [];
    fillIfEmpty($row, ['quellenurl' => 'https://www.kskmse.de/engagement'], $set, $params);
    applyUpdate($pdo, 8, $set, $params);
}

// ---------------------------------------------------------------------------
// 2) RVB Ebersberg: v2-Ergaenzungen auf ID 7 mergen, Dublette ID 72 loeschen
// ---------------------------------------------------------------------------

if (($row = loadGuarded($pdo, 7, 'Raiffeisen-Volksbank Ebersberg')) !== null) {
    $set = [];
    $params = [];
    fillIfEmpty($row, [
        'foerderprogramm' => 'Spenden & Sponsoring für Vereine im Geschäftsgebiet',
        'kontaktweg'      => 'über Website / Filiale (kein Online-Formular verifiziert)',
        'website'         => 'rv-ebe.de',
        'quellenurl'      => 'https://www.rv-ebe.de/meine-bank/news/bank/spenden.html',
        'branche'         => json_encode(['Finanzdienstleistungen']),
    ], $set, $params);
    appendNote($row,
        'Recherche v2 (2026-08): Lokal am relevantesten (Filiale Kirchseeon). Spendenvergabe erfolgt in Abstimmung mit den Ersten Bürgermeistern (Spenden-Tour, Vorstand Bernhard Failer) → Rathaus Kirchseeon einbinden. Sitz: Marktplatz 1, 85567 Grafing.',
        'Spenden-Tour', $set, $params);
    applyUpdate($pdo, 7, $set, $params);
    ensureAp($pdo, 7, ['email' => 'info@rv-ebe.de', 'telefon' => '08092 701-0']);

    // Dublette aus v1-Import entfernen (Freigabe TT 2026-08-11) — nur den unbearbeiteten Zwilling.
    $del = $pdo->prepare("DELETE FROM sponsors WHERE id = 72 AND firma = 'Raiffeisen-Volksbank Ebersberg eG' AND status = 'neu'");
    $del->execute();
    echo $del->rowCount() > 0 ? "DEL #72: RVB-Dublette geloescht\n" : "OK  #72: Dublette bereits weg\n";
}

// ---------------------------------------------------------------------------
// 3) Auto Eder (ID 10, abgelehnt): nur Wissens-Notiz fuer 2027 anhaengen
// ---------------------------------------------------------------------------

if (($row = loadGuarded($pdo, 10, 'AUTOHAUS KIRCHSEEON')) !== null) {
    $set = [];
    $params = [];
    appendNote($row,
        'Recherche v2 (2026-08): Auto Eder Gruppe unterstützt bereits ATSV Kirchseeon (= Veranstalter des Marktlaufs), Perchten Kirchseeon, EHC Klostersee, TSV Grafing; Fokus Sport-Nachwuchsförderung. GF Christian Kraft. Für 2027 ggf. über die bestehende ATSV-Partnerschaft ansprechen statt Neuakquise.',
        'Auto Eder Gruppe unterstützt bereits', $set, $params);
    applyUpdate($pdo, 10, $set, $params);
}

// ---------------------------------------------------------------------------
// 4) EBERwerk (ID 3, in_klaerung): leere Recherche-Felder fuellen, Notiz anhaengen
// ---------------------------------------------------------------------------

if (($row = loadGuarded($pdo, 3, 'EBERwerk')) !== null) {
    $set = [];
    $params = [];
    fillIfEmpty($row, [
        'foerderprogramm' => 'Als regionaler Veranstaltungs-Sponsor belegt (z. B. ABSI-Jahrestagung Ebersberg 2023)',
        'kontaktweg'      => 'Anfrage über eberwerk.de (kein Antragsformular)',
        'website'         => 'eberwerk.de',
        'quellenurl'      => 'https://eberwerk.de/ueber-uns/',
    ], $set, $params);
    appendNote($row,
        'Recherche v2 (2026-08): GF Dr. Markus Henle. Kommunale Trägerschaft (19 von 21 Kommunen des Lkr Ebersberg) = inhaltlich guter Fit für ein Gemeinde-Event.',
        'Kommunale Trägerschaft', $set, $params);
    applyUpdate($pdo, 3, $set, $params);
}

// ---------------------------------------------------------------------------
// 5) v1-Recherche-Eintraege auf v2-Stand (kontaktweg/notizen ersetzen, APs ergaenzen)
//    Format: id => [firmaPrefix, kontaktweg [v1, v2]|null, notizen [v1, v2]|null, ap|null]
// ---------------------------------------------------------------------------

$v2 = [
    73 => ['VR-Bank München Land', null,
        ['Prüfen, ob Kirchseeon im Geschäftsgebiet',
         'Geprüft: Geschäftsgebiet endet im Westen des Lkr EBE (östlichste Filiale Baldham); Kirchseeon = Gebiet der RVB Ebersberg → nachrangig'],
        ['email' => 'kundenservice@vrbml.de', 'telefon' => '089 444565-0']],
    75 => ['VR-Förderpreis', null,
        ['Bewerbungsfristen/Preishöhe prüfen',
         'Geprüft: keine zentrale Frist; Auslobung + Preishöhe legt jede Bank individuell fest. Nur relevant, wenn RVB Ebersberg auslobt (aktuelle Auslobungen: neu.vr-foerderpreis.de/aktuelle/). Auch Fremdvorschläge möglich'],
        null],
    77 => ['Versicherungskammer Bayern', null,
        ['Schwerpunkt Prävention/Sicherheit',
         'Schwerpunkt Prävention/Sicherheit. Kein direkter Sponsoring-Kontakt öffentlich → konkreter Weg über die Versicherungskammer Stiftung (eigener Eintrag)'],
        null],
    78 => ['Versicherungskammer Stiftung',
        ['über Stiftungs-Website',
         'Formlose Erstanfrage per E-Mail (max. 4 Seiten: Projekt + Finanzierung), jederzeit'],
        ['Ehrenamts-Aspekt des Laufs betonen',
         "Ehrenamts-Aspekt des Laufs betonen — Handlungsfeld 'bürgerschaftliches Engagement/Ehrenamt'. Ø-Förderhöhe ca. 10.000 €; Prüfung bis 8 Wochen; Maximilianstr. 53, 80530 München"],
        ['email' => 'info@versicherungskammer-stiftung.de']],
    80 => ['Allianz', null,
        ['Kein Breitensport-Förderprogramm für kleine Vereine',
         'Kein Breitensport-Programm der Zentrale. Lokaler Weg: Generalvertretungen Wasserburger Str. 5, 85614 Kirchseeon – Gillhuber e.K. (Tel. 08091 5383836) und Waldhör & Schrödinger OHG (Tel. 08091 9400)'],
        null],
    84 => ['Barmenia',
        ['über Sponsoring-Seite',
         'Pressereferentin Spenden & Sponsoring: Verena Wanner'],
        ['Programm bestätigt; Regional-Relevanz für Ebersberg unklar',
         'Programm bestätigt (BarmeniaGothaer); Regional-Relevanz für Ebersberg unklar'],
        ['anrede' => 'Frau', 'vorname' => 'Verena', 'nachname' => 'Wanner',
         'funktion' => 'Pressereferentin Spenden & Sponsoring',
         'email' => 'verena.wanner@barmenia.de', 'telefon' => '0202 438-2010']],
    86 => ['Barmer',
        ['Presse / LV Bayern über barmer.de',
         'Landesvertretung Bayern, Landsberger Str. 187, 80687 München'],
        ['Fokus Bonus/Prävention; regionale Stelle nicht öffentlich benannt',
         'Fokus Bonus/Prävention. Presse Bayern: presse.by@barmer.de'],
        ['email' => 'bayern@barmer.de', 'telefon' => '0800 333004 251-102']],
    89 => ['KKH',
        ['über BFV-Kooperation / KKH Service',
         'Landesverwaltung Bayern, Landshuter Allee 4-6, 80637 München, Tel. 089 53298-0'],
        ['BFV-Bezug für Bayern relevant',
         'BFV-Bezug für Bayern relevant (Gesundheitspartner BFV)'],
        null], // E-Mail wird unten am bestehenden AP ergaenzt (Telefon dort nicht ueberschreiben)
    91 => ['SBK',
        ['über sbk.org',
         'Zentrale München (Heimeranstr. 31, 80339 München)'],
        null,
        ['email' => 'info@sbk.org', 'telefon' => '0800 072572572-50']],
    92 => ['Audi BKK',
        ['über audibkk.de',
         'Service-Center Ingolstadt (Ferdinand-Braun-Str. 6, 85053 Ingolstadt); offiziell Kontaktformular audibkk.de bevorzugt'],
        null,
        ['email' => 'info@audibkk.de', 'telefon' => '0841 887-0']],
    93 => ['mhplus',
        ['über mhplus-krankenkasse.de',
         'Hauptverwaltung: Franckstr. 8, 71636 Ludwigsburg'],
        ['Kein spezif. Event-Sponsoring belegt',
         'Korrigiert: operativer Sitz ist Ludwigsburg, nicht Nürnberg (Nürnberg = nur Rechtssitz; bayernweit versicherbar). Kein spezif. Event-Sponsoring belegt'],
        ['email' => 'info@mhplus.de', 'telefon' => '07141 9790-0']],
    95 => ['BIG direkt gesund',
        ['über big-direkt.de/engagement',
         'Zentrale: Rheinische Str. 1, 44137 Dortmund; Anmeldung Family Games über big-direkt.de'],
        ['Explizit vereinsorientiert – prüfenswert',
         'Explizit vereinsorientiert – prüfenswert. BIG Family Games: Verein erhält Konzept, Werbemittel, Preise + Material kostenlos; Anmeldung fristgebunden (jahrgangsweise Ausschreibung)'],
        ['email' => '', 'telefon' => '0800 54565456']],
    96 => ['Knappschaft',
        ['über knappschaft.de',
         'Zentrale: 45095 Essen; Kontaktformular knappschaft.de'],
        null,
        ['email' => '', 'telefon' => '08000 200-501']],
    97 => ['BKK Landesverband Bayern',
        ['über bkk-bayern.de',
         'Züricher Str. 25, 81476 München'],
        ['Kein eigener Sponsor, aber Türöffner zu regionalen BKKn',
         'Kein eigener Sponsor, aber Türöffner zu regionalen BKKn. Presse: presse@bkk-lv-bayern.de'],
        ['email' => 'info@bkk-lv-bayern.de', 'telefon' => '089 74579-0']],
];

foreach ($v2 as $id => [$prefix, $kontaktweg, $notizen, $ap]) {
    if (($row = loadGuarded($pdo, $id, $prefix)) === null) {
        continue;
    }
    $set = [];
    $params = [];
    $transitions = [];
    if ($kontaktweg !== null) {
        $transitions['kontaktweg'] = $kontaktweg;
    }
    if ($notizen !== null) {
        $transitions['notizen'] = $notizen;
    }
    replaceIfUntouched($row, $transitions, $set, $params);
    applyUpdate($pdo, $id, $set, $params);
    if ($ap !== null) {
        $ap += ['email' => '', 'telefon' => ''];
        ensureAp($pdo, $id, $ap);
    }
}

// KKH (ID 89): E-Mail am bestehenden Ansprechpartner nachtragen, Telefon bleibt.
$st = $pdo->prepare("
    UPDATE sponsor_ansprechpartner SET email = 'serviceteam.LK3@kkh.de'
    WHERE sponsor_id = 89 AND (email IS NULL OR email = '')
    ORDER BY id LIMIT 1
");
$st->execute();
echo $st->rowCount() > 0 ? "AP  #89: E-Mail nachgetragen\n" : "OK  #89: AP-E-Mail bereits vorhanden\n";

// ---------------------------------------------------------------------------
// 6) Neuanlagen (Status neu) — nur wenn Firma noch nicht existiert
// ---------------------------------------------------------------------------

$neue = [
    ['firma' => 'Stiftungen der Kreissparkasse (4 Stiftungen, u.a. für Lkr Ebersberg)',
     'ort' => null, 'branche' => ['Finanzdienstleistungen'],
     'foerderprogramm' => 'Stiftungsförderung Kultur/Bildung/Sport/Soziales, ergänzend zu Spenden/Sponsoring der Bank',
     'kontaktweg' => 'Antrag über kskmse.de/engagement (Antragslinks je Region)',
     'website' => 'kskmse.de', 'quellenurl' => 'https://www.kskmse.de/engagement',
     'notizen' => 'Für den Marktlauf: Stiftung für Lkr Ebersberg wählen. Typ: Stiftung (Sparkasse), je Landkreis (München, Starnberg, Ebersberg)',
     'ap' => null],
    ['firma' => 'Privatbrauerei ERDINGER Weißbräu (ERDINGER Alkoholfrei)',
     'ort' => 'Erding', 'branche' => ['Handel & Gastronomie'],
     'foerderprogramm' => 'ERDINGER Alkoholfrei Aktiv-Tour: Zielausschank alkoholfreies Weißbier bei >180 Ausdauersport-Events/Jahr (auch lokale Läufe)',
     'kontaktweg' => "Kontaktformular erdinger.de → Thema 'Marketing'; operativ über Eventagentur kiecom (kiecom.de, Lager Aschheim b. München)",
     'website' => 'erdinger.de', 'quellenurl' => 'https://www.kiecom.de/cases/erdinger-alkoholfrei-aktiv-tour/',
     'notizen' => 'Leiter Sponsoring & Events: Philipp Herold. I.d.R. Sachleistung (Zielausschank) statt Geld. Stand identisch in Finanz- und Getränkeliste — ein Eintrag.',
     'ap' => null],
    ['firma' => 'Wildbräu Grafing GmbH',
     'ort' => 'Grafing', 'branche' => ['Handel & Gastronomie'],
     'foerderprogramm' => 'Kein formales Programm; als lokale Traditionsbrauerei (gegr. 1060) stark regional engagiert',
     'kontaktweg' => 'Direktansprache Inhaberfamilie Schlederer',
     'website' => 'wildbraeu.de', 'quellenurl' => 'https://werbering-grafing.de/wildbraeu-grafing/',
     'notizen' => 'Rotter Str. 15, 85567 Grafing (~5 km). Eigene Liefertour bedient mittwochs auch Kirchseeon. Maximale lokale Nähe – naheliegendster Getränkepartner.',
     'ap' => ['nachname' => 'Schlederer', 'email' => 'g.schlederer@wildbraeu.de', 'telefon' => '08092 700 90']],
    ['firma' => 'Privatbrauerei Ayinger',
     'ort' => 'Aying', 'branche' => ['Handel & Gastronomie'],
     'foerderprogramm' => "Kein Antragsportal; Positionierung 'Heimatverbundenheit/bayerische Bierkultur'",
     'kontaktweg' => 'Kontaktformular brauerei-ayinger.de/kontakt',
     'website' => 'brauerei-ayinger.de', 'quellenurl' => 'https://www.brauerei-ayinger.de/kontakt/',
     'notizen' => 'Münchener Str. 21, 85653 Aying (~12 km); Familienunternehmen Inselkammer (6. Generation)',
     'ap' => ['email' => '', 'telefon' => '08095 90650']],
    ['firma' => 'Adelholzener Alpenquellen GmbH (inkl. Active O2)',
     'ort' => 'Siegsdorf', 'branche' => ['Handel & Gastronomie'],
     'foerderprogramm' => 'Sport-Sponsoring belegt: offizieller Sponsor FC Bayern, Partner BR-Radltour (stellt Wasser für alle Teilnehmer)',
     'kontaktweg' => 'Kontaktformular adelholzener.de/kontakt',
     'website' => 'adelholzener.de', 'quellenurl' => 'https://www.adelholzener.de/kontakt/',
     'notizen' => 'St.-Primus-Str. 1-5, 83313 Siegsdorf. Ideal für Wasser an Verpflegungs-/Zielstationen; Erlöse gehen an soziale Zwecke der Ordensschwestern.',
     'ap' => null],
    ['firma' => 'Rothmoser GmbH & Co. KG',
     'ort' => 'Grafing', 'branche' => ['Sonstige'],
     'foerderprogramm' => 'Öffentlich dokumentiert: fördert jedes Jahr Vereine, Einrichtungen und Veranstaltungen mit Geldspenden, Sponsoring, Sachspenden',
     'kontaktweg' => 'Direktanfrage',
     'website' => 'rothmoser.de', 'quellenurl' => 'https://rothmoser.de/fragen-antworten/',
     'notizen' => 'Am Urtelbach 4, 85567 Grafing. Typ: Energieversorger (regional, familiengeführt). Einziger Kandidat der Getränke-/Regionalliste mit explizit dokumentierter jährlicher Förderpraxis.',
     'ap' => ['email' => 'strom@rothmoser.de', 'telefon' => '08092 7004-0']],
    ['firma' => 'Bayernwerk AG',
     'ort' => null, 'branche' => ['Sonstige'],
     'foerderprogramm' => 'Regionales Sponsoring belegt (u.a. Partner des BFV/Amateurfußball); kein zentrales Antragsportal verifiziert',
     'kontaktweg' => "Über regionale Kommunalbetreuung ('Sprechen Sie uns an')",
     'website' => 'bayernwerk.de', 'quellenurl' => 'https://www.bfv.de/der-bfv/sponsoring/partner/bayernwerk',
     'notizen' => 'Typ: Energienetzbetreiber/Versorger; Netzgebiet umfasst die Region.',
     'ap' => null],
    ['firma' => 'Energie Südbayern (ESB)',
     'ort' => null, 'branche' => ['Sonstige'],
     'foerderprogramm' => 'Kein öffentliches Sponsoring-/Antragsportal für Vereine verifiziert',
     'kontaktweg' => 'ggf. Direktanfrage',
     'website' => 'esb.de', 'quellenurl' => 'https://www.esb.de/kommunen',
     'notizen' => 'Typ: Energieversorger (Ober-/Niederbayern). Nachrangig; nur ansprechen, wenn lokaler Bezug (Konzession/Netz) besteht.',
     'ap' => null],
];

$chk = $pdo->prepare('SELECT id FROM sponsors WHERE LOWER(TRIM(firma)) = :f LIMIT 1');
$ins = $pdo->prepare('
    INSERT INTO sponsors (firma, ort, branche, foerderprogramm, kontaktweg, website, quellenurl, notizen, status)
    VALUES (:firma, :ort, :branche, :foerderprogramm, :kontaktweg, :website, :quellenurl, :notizen, :status)
');

foreach ($neue as $n) {
    $chk->execute(['f' => mb_strtolower(trim($n['firma']))]);
    $existingId = $chk->fetchColumn();
    if ($existingId !== false) {
        echo "OK  neu '{$n['firma']}': existiert bereits (#{$existingId})\n";
        continue;
    }
    $ins->execute([
        'firma'           => $n['firma'],
        'ort'             => $n['ort'],
        'branche'         => json_encode($n['branche']),
        'foerderprogramm' => $n['foerderprogramm'],
        'kontaktweg'      => $n['kontaktweg'],
        'website'         => $n['website'],
        'quellenurl'      => $n['quellenurl'],
        'notizen'         => $n['notizen'],
        'status'          => 'neu',
    ]);
    $newId = (int) $pdo->lastInsertId();
    echo "NEU #{$newId}: {$n['firma']}\n";
    if ($n['ap'] !== null) {
        $ap = $n['ap'] + ['email' => '', 'telefon' => ''];
        ensureAp($pdo, $newId, $ap);
    }
}

echo "Fertig.\n";
