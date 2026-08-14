<?php
/**
 * Sponsor CRUD Handler (POST)
 * Actions: create, update, delete, kein_kontakt_set, kein_kontakt_remove
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';
require_once __DIR__ . '/../../src/sponsor_status.php';
require_once __DIR__ . '/../../src/sponsor_rotation.php';
require_once __DIR__ . '/../../src/sponsor_beleg.php';
require_once __DIR__ . '/../../src/sponsor_leitfaden.php';
require_once __DIR__ . '/../../src/rechnung.php';

/**
 * Optionalen Leitfaden-Upload persistieren. Braucht die (frisch vergebene) Sponsor-id,
 * läuft daher nach dem INSERT/UPDATE. Fehler landen als Flash, brechen das Speichern nicht ab.
 */
function sponsorApplyLeitfaden(PDO $pdo, int $sponsorId): void {
    if (!isset($_FILES['leitfaden'])) {
        return;
    }
    try {
        $datei = materializeSponsorLeitfaden($sponsorId, $_FILES['leitfaden']);
    } catch (RuntimeException $e) {
        $_SESSION['flash_error'] = $e->getMessage();
        return;
    }
    if ($datei === null) {
        return; // kein Upload in diesem Request
    }
    $pdo->prepare('UPDATE sponsors SET leitfaden_datei = :d WHERE id = :id')
        ->execute(['d' => $datei, 'id' => $sponsorId]);
}

/**
 * Rotations-Felder (Aktiv-Haken + optionaler Logo-Upload) persistieren und den
 * öffentlichen Feed neu schreiben. Kapselt die Fehlerbehandlung an einer Stelle.
 */
function sponsorApplyRotation(PDO $pdo, int $sponsorId, string $firma): void {
    $inRotation = isset($_POST['in_rotation']) ? 1 : 0;
    $logoAsset = null;
    $driveFileId = null;

    // Vorrang: frisch hochgeladenes Logo.
    try {
        if (isset($_FILES['logo'])) {
            $logoAsset = materializeSponsorLogo($sponsorId, $firma, $_FILES['logo']);
        }
    } catch (RuntimeException $e) {
        $_SESSION['flash_error'] = $e->getMessage();
    }
    // Sonst: aus dem Drive-Ordner gewähltes Logo materialisieren.
    if ($logoAsset === null) {
        $pick = trim((string) ($_POST['logo_drive_pick'] ?? ''));
        if ($pick !== '') {
            try {
                $logoAsset = materializeSponsorLogoFromDrive($sponsorId, $firma, $pick);
                $driveFileId = $pick;
            } catch (Throwable $e) {
                $_SESSION['flash_error'] = $e->getMessage();
            }
        }
    }

    // Drive-Ordner automatisch anlegen, sobald der Sponsor auf „zugesagt" steht (und noch keinen hat).
    try {
        if (sponsorStatusFromPost($_POST['status'] ?? '') === 'zugesagt' && driveConfigured()) {
            $chk = $pdo->prepare('SELECT drive_folder_id FROM sponsors WHERE id = :id');
            $chk->execute(['id' => $sponsorId]);
            if ((string) ($chk->fetchColumn() ?: '') === '') {
                sponsorEnsureDriveFolder($pdo, $sponsorId, $firma);
            }
        }
    } catch (Throwable $e) {
        logError('Sponsor Drive-Ordner-Anlage: ' . $e->getMessage());
    }

    $sets = ['in_rotation = :r'];
    $params = ['r' => $inRotation, 'id' => $sponsorId];
    if ($logoAsset !== null) {
        $sets[] = 'logo_web_asset = :l';
        $params['l'] = $logoAsset;
    }
    if ($driveFileId !== null) {
        $sets[] = 'logo_drive_file_id = :d';
        $params['d'] = $driveFileId;
    }
    $pdo->prepare('UPDATE sponsors SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);

    try {
        writeSponsorenFeed($pdo);
    } catch (Throwable $e) {
        logError('Sponsor-Feed schreiben: ' . $e->getMessage());
    }
}

/**
 * prioritaet aus dem Formular (leer|1|2|3) validieren.
 */
function sponsorPrioritaetFromPost(mixed $raw): ?int {
    $v = trim((string) $raw);
    if ($v === '' || !ctype_digit($v)) {
        return null;
    }
    $n = (int) $v;
    return ($n >= 1 && $n <= 3) ? $n : null;
}

/**
 * status aus dem Formular gegen die erlaubten Werte prüfen.
 */
function sponsorStatusFromPost(mixed $raw): string {
    $v = (string) $raw;
    return sponsorStatusValid($v) ? $v : 'neu';
}

/**
 * Konzern/Gruppe per Freitext-Autocomplete: bestehende Gruppe wiederverwenden
 * (exakter Namensvergleich) statt bei jeder Eingabe eine neue anzulegen.
 * Leere Eingabe = keine Gruppe (gruppe_id NULL).
 */
function sponsorGruppeIdFromPost(PDO $pdo, string $rawName): ?int {
    $name = trim($rawName);
    if ($name === '') {
        return null;
    }
    $select = $pdo->prepare('SELECT id FROM sponsor_gruppen WHERE name = :name');
    $select->execute(['name' => $name]);
    $id = $select->fetchColumn();
    if ($id !== false) {
        return (int) $id;
    }
    $insert = $pdo->prepare('INSERT INTO sponsor_gruppen (name) VALUES (:name)');
    $insert->execute(['name' => $name]);
    return (int) $pdo->lastInsertId();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../sponsoren.php');
    exit;
}

/**
 * Inline-Schnellbearbeitung aus der Sponsoren-Übersicht (Paket/Status per Dropdown).
 * Antwortet immer als JSON und wird per fetch() aufgerufen.
 */
if (($_POST['action'] ?? '') === 'inline_update') {
    header('Content-Type: application/json');

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['ok' => false, 'message' => 'Ungültige Anfrage.']);
        exit;
    }

    $sponsorId = (int) ($_POST['sponsor_id'] ?? 0);
    $field = (string) ($_POST['field'] ?? '');
    $value = (string) ($_POST['value'] ?? '');

    if ($sponsorId <= 0) {
        echo json_encode(['ok' => false, 'message' => 'Ungültige Sponsor-ID.']);
        exit;
    }

    try {
        $pdo = getDbConnection();

        if ($field === 'status') {
            if (!sponsorStatusValid($value)) {
                echo json_encode(['ok' => false, 'message' => 'Ungültiger Status.']);
                exit;
            }
            $pdo->prepare('UPDATE sponsors SET status = :v WHERE id = :id')
                ->execute(['v' => $value, 'id' => $sponsorId]);
            echo json_encode([
                'ok'    => true,
                'ampel' => sponsorStatusAmpel($value),
                'label' => sponsorStatusLabel($value),
            ]);
            exit;
        }

        if ($field === 'paket') {
            $paket = in_array($value, ['hauptsponsor', 'gold', 'silber', 'bronze', 'sachsponsor'], true) ? $value : null;
            $neueSumme = null; // null = summe unangetastet lassen
            if (in_array($paket, ['gold', 'silber', 'bronze'], true)) {
                // Tier ⇒ fester Betrag aus dem Paket; summe mitziehen, damit Übersicht/Summe/Abrechnung stimmen.
                $pakete = sponsoringPakete($pdo);
                $neueSumme = paketBetrag($pakete[$paket]['investition'] ?? null);
                $pdo->prepare('UPDATE sponsors SET paket = :v, summe = :s WHERE id = :id')
                    ->execute(['v' => $paket, 's' => $neueSumme, 'id' => $sponsorId]);
            } elseif ($paket === 'sachsponsor') {
                // Sachsponsor ⇒ kein Geld.
                $neueSumme = 0.0;
                $pdo->prepare('UPDATE sponsors SET paket = :v, summe = NULL WHERE id = :id')
                    ->execute(['v' => $paket, 'id' => $sponsorId]);
            } else {
                // Hauptsponsor (individuell) oder kein Typ: summe nicht überschreiben.
                $pdo->prepare('UPDATE sponsors SET paket = :v WHERE id = :id')
                    ->execute(['v' => $paket, 'id' => $sponsorId]);
            }
            echo json_encode(['ok' => true, 'paket' => $paket, 'summe' => $neueSumme]);
            exit;
        }

        if ($field === 'branche') {
            $branche = $value !== '' ? json_encode([mb_substr(trim($value), 0, 100)]) : null;
            $pdo->prepare('UPDATE sponsors SET branche = :v WHERE id = :id')
                ->execute(['v' => $branche, 'id' => $sponsorId]);
            echo json_encode(['ok' => true]);
            exit;
        }

        if ($field === 'zustaendig') {
            // Leer = Zuordnung entfernen; sonst muss es ein aktiver Nutzer sein.
            $uid = ($value !== '' && ctype_digit($value)) ? (int) $value : 0;
            if ($uid > 0) {
                $chk = $pdo->prepare('SELECT 1 FROM users WHERE id = :id AND active = 1');
                $chk->execute(['id' => $uid]);
                if (!$chk->fetchColumn()) {
                    echo json_encode(['ok' => false, 'message' => 'Unbekannte oder inaktive Person.']);
                    exit;
                }
            }
            $pdo->prepare('UPDATE sponsors SET zustaendig_user_id = :v WHERE id = :id')
                ->execute(['v' => $uid > 0 ? $uid : null, 'id' => $sponsorId]);
            echo json_encode(['ok' => true]);
            exit;
        }

        echo json_encode(['ok' => false, 'message' => 'Ungültiges Feld.']);
        exit;
    } catch (PDOException $e) {
        logError('Sponsor inline_update error: ' . $e->getMessage());
        echo json_encode(['ok' => false, 'message' => 'Datenbankfehler.']);
        exit;
    }
}

/**
 * Feldweiser Autosave der Sponsor-Einzelmaske (sponsor_form.php im Bearbeiten-Modus).
 * Jeder Request schreibt genau EIN Feld — damit kann ein fehlendes Formularfeld nie
 * stumm Bestandsdaten leeren. Antwortet als JSON, wird per fetch() aufgerufen.
 */
if (($_POST['action'] ?? '') === 'field_update') {
    header('Content-Type: application/json');

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['ok' => false, 'message' => 'Ungültige Anfrage.']);
        exit;
    }

    $sponsorId = (int) ($_POST['sponsor_id'] ?? 0);
    $field = (string) ($_POST['field'] ?? '');
    $rawValue = $_POST['value'] ?? '';

    if ($sponsorId <= 0) {
        echo json_encode(['ok' => false, 'message' => 'Ungültige Sponsor-ID.']);
        exit;
    }

    // Einfache Textfelder (leer -> NULL), Datumsfelder und 0/1-Checkboxen.
    // Die Spaltennamen stammen ausschließlich aus diesen Whitelists — nie aus Nutzer-Input in die SQL.
    $plainText = [
        'ort', 'notizen', 'rechnung_firma', 'rechnung_email', 'rechnung_strasse',
        'rechnung_plz', 'rechnung_ort', 'foerderprogramm', 'kontaktweg',
        'quellenurl', 'weitere_links', 'website',
    ];
    $dateFields = ['wiedervorlage', 'bedingungen_bestaetigt_am'];
    $checkboxFields = ['rechnung_betrag_brutto', 'bedingungen_beleg'];

    try {
        $pdo = getDbConnection();

        // Autosave gibt es nur zu einem bestehenden Sponsor (Neuanlage läuft weiter über 'create').
        $chk = $pdo->prepare('SELECT 1 FROM sponsors WHERE id = :id');
        $chk->execute(['id' => $sponsorId]);
        if (!$chk->fetchColumn()) {
            echo json_encode(['ok' => false, 'message' => 'Sponsor nicht gefunden.']);
            exit;
        }

        // firma: Pflichtfeld, darf per Autosave nicht geleert werden.
        if ($field === 'firma') {
            $firma = trim((string) $rawValue);
            if ($firma === '') {
                echo json_encode(['ok' => false, 'message' => 'Firma darf nicht leer sein.']);
                exit;
            }
            $pdo->prepare('UPDATE sponsors SET firma = :v WHERE id = :id')
                ->execute(['v' => $firma, 'id' => $sponsorId]);
            echo json_encode(['ok' => true]);
            exit;
        }

        // status: gegen erlaubte Werte prüfen.
        if ($field === 'status') {
            if (!sponsorStatusValid((string) $rawValue)) {
                echo json_encode(['ok' => false, 'message' => 'Ungültiger Status.']);
                exit;
            }
            $pdo->prepare('UPDATE sponsors SET status = :v WHERE id = :id')
                ->execute(['v' => (string) $rawValue, 'id' => $sponsorId]);
            echo json_encode(['ok' => true]);
            exit;
        }

        // paket: nur der Typ selbst; der Betrag speichert sich als eigenes Feld (summe).
        if ($field === 'paket') {
            $paket = in_array($rawValue, ['hauptsponsor', 'gold', 'silber', 'bronze', 'sachsponsor'], true) ? (string) $rawValue : null;
            $pdo->prepare('UPDATE sponsors SET paket = :v WHERE id = :id')
                ->execute(['v' => $paket, 'id' => $sponsorId]);
            echo json_encode(['ok' => true]);
            exit;
        }

        // foerdergruppe: gegen erlaubte Werte prüfen (Spalte ist NOT NULL, Default 'sponsoring').
        if ($field === 'foerdergruppe') {
            if (!isset(SPONSOR_FOERDERGRUPPE[(string) $rawValue])) {
                echo json_encode(['ok' => false, 'message' => 'Ungültige Fördergruppe.']);
                exit;
            }
            $pdo->prepare('UPDATE sponsors SET foerdergruppe = :v WHERE id = :id')
                ->execute(['v' => (string) $rawValue, 'id' => $sponsorId]);
            echo json_encode(['ok' => true]);
            exit;
        }

        // summe: freie Zahl, 0/leer -> NULL.
        if ($field === 'summe') {
            $summe = (float) $rawValue ?: null;
            $pdo->prepare('UPDATE sponsors SET summe = :v WHERE id = :id')
                ->execute(['v' => $summe, 'id' => $sponsorId]);
            echo json_encode(['ok' => true]);
            exit;
        }

        // prioritaet: leer|1|2|3.
        if ($field === 'prioritaet') {
            $pdo->prepare('UPDATE sponsors SET prioritaet = :v WHERE id = :id')
                ->execute(['v' => sponsorPrioritaetFromPost($rawValue), 'id' => $sponsorId]);
            echo json_encode(['ok' => true]);
            exit;
        }

        // gruppe_name (Freitext) -> gruppe_id (Gruppe wiederverwenden/anlegen).
        if ($field === 'gruppe_name') {
            $gruppeId = sponsorGruppeIdFromPost($pdo, (string) $rawValue);
            $pdo->prepare('UPDATE sponsors SET gruppe_id = :v WHERE id = :id')
                ->execute(['v' => $gruppeId, 'id' => $sponsorId]);
            echo json_encode(['ok' => true]);
            exit;
        }

        // ansprache: du|sie.
        if ($field === 'ansprache') {
            $ansprache = ((string) $rawValue === 'du') ? 'du' : 'sie';
            $pdo->prepare('UPDATE sponsors SET ansprache = :v WHERE id = :id')
                ->execute(['v' => $ansprache, 'id' => $sponsorId]);
            echo json_encode(['ok' => true]);
            exit;
        }

        // bedingungen_weg: gegen erlaubte Wege prüfen, sonst NULL.
        if ($field === 'bedingungen_weg') {
            $weg = in_array((string) $rawValue, sponsorBedingungenWegKeys(), true) ? (string) $rawValue : null;
            $pdo->prepare('UPDATE sponsors SET bedingungen_weg = :v WHERE id = :id')
                ->execute(['v' => $weg, 'id' => $sponsorId]);
            echo json_encode(['ok' => true]);
            exit;
        }

        // branche[]: Mehrfachauswahl -> JSON-Array (oder NULL). Werte kommen als value[].
        if ($field === 'branche') {
            $arr = array_values(array_filter(array_map('trim', (array) ($_POST['value'] ?? []))));
            $branche = !empty($arr) ? json_encode($arr) : null;
            $pdo->prepare('UPDATE sponsors SET branche = :v WHERE id = :id')
                ->execute(['v' => $branche, 'id' => $sponsorId]);
            echo json_encode(['ok' => true]);
            exit;
        }

        // Datumsfelder (leer -> NULL). $field ist per Whitelist geprüft.
        if (in_array($field, $dateFields, true)) {
            $val = trim((string) $rawValue) ?: null;
            $pdo->prepare("UPDATE sponsors SET {$field} = :v WHERE id = :id")
                ->execute(['v' => $val, 'id' => $sponsorId]);
            echo json_encode(['ok' => true]);
            exit;
        }

        // 0/1-Checkboxen. $field ist per Whitelist geprüft.
        if (in_array($field, $checkboxFields, true)) {
            $val = ((string) $rawValue === '1') ? 1 : 0;
            $pdo->prepare("UPDATE sponsors SET {$field} = :v WHERE id = :id")
                ->execute(['v' => $val, 'id' => $sponsorId]);
            echo json_encode(['ok' => true]);
            exit;
        }

        // Einfache Textfelder (leer -> NULL). $field ist per Whitelist geprüft.
        if (in_array($field, $plainText, true)) {
            $val = trim((string) $rawValue) ?: null;
            $pdo->prepare("UPDATE sponsors SET {$field} = :v WHERE id = :id")
                ->execute(['v' => $val, 'id' => $sponsorId]);
            echo json_encode(['ok' => true]);
            exit;
        }

        echo json_encode(['ok' => false, 'message' => 'Ungültiges Feld.']);
        exit;
    } catch (PDOException $e) {
        logError('Sponsor field_update error: ' . $e->getMessage());
        echo json_encode(['ok' => false, 'message' => 'Datenbankfehler.']);
        exit;
    }
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    $_SESSION['flash_error'] = 'Ungültige Anfrage.';
    header('Location: ../sponsoren.php');
    exit;
}

$action = $_POST['action'] ?? '';
$sponsorId = (int) ($_POST['sponsor_id'] ?? 0);
$isAdmin = isAdminFromGuard();

$validActions = ['create', 'update', 'delete', 'kein_kontakt_set', 'kein_kontakt_remove', 'archive_bestaetigung'];
if (!in_array($action, $validActions, true)) {
    $_SESSION['flash_error'] = 'Ungültige Aktion.';
    header('Location: ../sponsoren.php');
    exit;
}

if ($action === 'delete' && !$isAdmin) {
    $_SESSION['flash_error'] = 'Nur Admins können Sponsoren löschen.';
    header('Location: ../sponsoren.php');
    exit;
}

if ($action === 'kein_kontakt_remove' && !$isAdmin) {
    $_SESSION['flash_error'] = 'Nur Admins können Kein-Kontakt zurücknehmen.';
    header('Location: ../sponsoren.php');
    exit;
}

try {
    $pdo = getDbConnection();

    switch ($action) {
        case 'create':
            $firma = trim($_POST['firma'] ?? '');
            if ($firma === '') {
                $_SESSION['flash_error'] = 'Firma ist ein Pflichtfeld.';
                header('Location: ../sponsor_form.php');
                exit;
            }

            $keinKontakt = isset($_POST['kein_kontakt']) ? 1 : 0;
            $keinKontaktDatum = $_POST['kein_kontakt_datum'] ?? '';
            if ($keinKontakt && empty($keinKontaktDatum)) {
                $keinKontaktDatum = date('Y-m-d');
            }

            $gruppeId = sponsorGruppeIdFromPost($pdo, $_POST['gruppe_name'] ?? '');

            $stmt = $pdo->prepare('
                INSERT INTO sponsors (firma, paket, foerdergruppe, prioritaet, ort, summe, status, kein_kontakt, kein_kontakt_grund, kein_kontakt_wer, kein_kontakt_datum, notizen, wiedervorlage, gruppe_id, rechnung_firma, rechnung_strasse, rechnung_plz, rechnung_ort, rechnung_email, rechnung_leistung, leistung_zeitraum, rechnung_betrag_brutto, branche, foerderprogramm, kontaktweg, website, quellenurl, weitere_links, ansprache, bedingungen_bestaetigt_am, bedingungen_weg, bedingungen_beleg)
                VALUES (:firma, :paket, :foerdergruppe, :prioritaet, :ort, :summe, :status, :kein_kontakt, :kein_kontakt_grund, :kein_kontakt_wer, :kein_kontakt_datum, :notizen, :wiedervorlage, :gruppe_id, :rechnung_firma, :rechnung_strasse, :rechnung_plz, :rechnung_ort, :rechnung_email, :rechnung_leistung, :leistung_zeitraum, :rechnung_betrag_brutto, :branche, :foerderprogramm, :kontaktweg, :website, :quellenurl, :weitere_links, :ansprache, :bedingungen_bestaetigt_am, :bedingungen_weg, :bedingungen_beleg)
            ');
            $stmt->execute([
                'firma'              => $firma,
                'paket'              => $_POST['paket'] ?: null,
                'foerdergruppe'      => isset(SPONSOR_FOERDERGRUPPE[(string) ($_POST['foerdergruppe'] ?? '')]) ? (string) $_POST['foerdergruppe'] : 'sponsoring',
                'prioritaet'         => sponsorPrioritaetFromPost($_POST['prioritaet'] ?? ''),
                'ort'                => trim($_POST['ort'] ?? '') ?: null,
                'summe'              => (float) ($_POST['summe'] ?? 0) ?: null,
                'status'             => sponsorStatusFromPost($_POST['status'] ?? 'neu'),
                'kein_kontakt'       => $keinKontakt,
                'kein_kontakt_grund' => $keinKontakt ? (trim($_POST['kein_kontakt_grund'] ?? '') ?: null) : null,
                'kein_kontakt_wer'   => $keinKontakt ? (trim($_POST['kein_kontakt_wer'] ?? '') ?: null) : null,
                'kein_kontakt_datum' => $keinKontakt ? ($keinKontaktDatum ?: null) : null,
                'notizen'            => trim($_POST['notizen'] ?? '') ?: null,
                'wiedervorlage'      => $_POST['wiedervorlage'] ?: null,
                'gruppe_id'          => $gruppeId,
                'rechnung_firma'     => trim($_POST['rechnung_firma'] ?? '') ?: null,
                'rechnung_strasse'   => trim($_POST['rechnung_strasse'] ?? '') ?: null,
                'rechnung_plz'       => trim($_POST['rechnung_plz'] ?? '') ?: null,
                'rechnung_ort'       => trim($_POST['rechnung_ort'] ?? '') ?: null,
                'rechnung_email'     => trim($_POST['rechnung_email'] ?? '') ?: null,
                'rechnung_leistung'  => trim($_POST['rechnung_leistung'] ?? '') ?: null,
                'leistung_zeitraum'  => trim($_POST['leistung_zeitraum'] ?? '') ?: null,
                'rechnung_betrag_brutto' => isset($_POST['rechnung_betrag_brutto']) ? 1 : 0,
                'branche'            => !empty($_POST['branche']) ? json_encode(array_values(array_filter(array_map('trim', (array) $_POST['branche'])))) : null,
                'foerderprogramm'    => trim($_POST['foerderprogramm'] ?? '') ?: null,
                'kontaktweg'         => trim($_POST['kontaktweg'] ?? '') ?: null,
                'ansprache'          => ($_POST['ansprache'] ?? 'sie') === 'du' ? 'du' : 'sie',
                'website'            => trim($_POST['website'] ?? '') ?: null,
                'quellenurl'         => trim($_POST['quellenurl'] ?? '') ?: null,
                'weitere_links'      => trim($_POST['weitere_links'] ?? '') ?: null,
                'bedingungen_bestaetigt_am' => $_POST['bedingungen_bestaetigt_am'] ?: null,
                'bedingungen_weg'    => in_array($_POST['bedingungen_weg'] ?? '', sponsorBedingungenWegKeys(), true) ? $_POST['bedingungen_weg'] : null,
                'bedingungen_beleg'  => isset($_POST['bedingungen_beleg']) ? 1 : 0,
            ]);

            $newSponsorId = (int) $pdo->lastInsertId();

            sponsorApplyRotation($pdo, $newSponsorId, $firma);
            sponsorApplyLeitfaden($pdo, $newSponsorId);

            // Weiter in die Bearbeiten-Maske: Ansprechpartner werden dort per Autosave
            // gepflegt (brauchen die frisch vergebene Sponsor-id).
            $_SESSION['flash_success'] = 'Sponsor angelegt. Ansprechpartner kannst du jetzt hinzufügen.';
            header('Location: ../sponsor_form.php?id=' . $newSponsorId);
            exit;

        case 'update':
            if ($sponsorId <= 0) {
                $_SESSION['flash_error'] = 'Ungültige Sponsor-ID.';
                header('Location: ../sponsoren.php');
                exit;
            }

            $firma = trim($_POST['firma'] ?? '');
            if ($firma === '') {
                $_SESSION['flash_error'] = 'Firma ist ein Pflichtfeld.';
                header('Location: ../sponsor_form.php?id=' . $sponsorId);
                exit;
            }

            $existing = $pdo->prepare('SELECT kein_kontakt FROM sponsors WHERE id = :id');
            $existing->execute(['id' => $sponsorId]);
            $sponsor = $existing->fetch();

            if (!$sponsor) {
                $_SESSION['flash_error'] = 'Sponsor nicht gefunden.';
                header('Location: ../sponsoren.php');
                exit;
            }

            $keinKontakt = $sponsor['kein_kontakt'];
            $keinKontaktGrund = null;
            $keinKontaktWer = null;
            $keinKontaktDatum = null;

            if (isset($_POST['kein_kontakt'])) {
                $keinKontakt = 1;
                $keinKontaktGrund = trim($_POST['kein_kontakt_grund'] ?? '') ?: null;
                $keinKontaktWer = trim($_POST['kein_kontakt_wer'] ?? '') ?: null;
                $keinKontaktDatum = $_POST['kein_kontakt_datum'] ?? '';
                if (empty($keinKontaktDatum)) {
                    $keinKontaktDatum = date('Y-m-d');
                }
            } elseif ($isAdmin) {
                $keinKontakt = 0;
            } else {
                $existingFull = $pdo->prepare('SELECT kein_kontakt_grund, kein_kontakt_wer, kein_kontakt_datum FROM sponsors WHERE id = :id');
                $existingFull->execute(['id' => $sponsorId]);
                $full = $existingFull->fetch();
                $keinKontaktGrund = $full['kein_kontakt_grund'];
                $keinKontaktWer = $full['kein_kontakt_wer'];
                $keinKontaktDatum = $full['kein_kontakt_datum'];
            }

            $gruppeId = sponsorGruppeIdFromPost($pdo, $_POST['gruppe_name'] ?? '');

            $stmt = $pdo->prepare('
                UPDATE sponsors SET
                    firma = :firma,
                    paket = :paket,
                    foerdergruppe = :foerdergruppe,
                    prioritaet = :prioritaet,
                    ort = :ort,
                    summe = :summe,
                    status = :status,
                    kein_kontakt = :kein_kontakt,
                    kein_kontakt_grund = :kein_kontakt_grund,
                    kein_kontakt_wer = :kein_kontakt_wer,
                    kein_kontakt_datum = :kein_kontakt_datum,
                    notizen = :notizen,
                    wiedervorlage = :wiedervorlage,
                    gruppe_id = :gruppe_id,
                    rechnung_firma = :rechnung_firma,
                    rechnung_strasse = :rechnung_strasse,
                    rechnung_plz = :rechnung_plz,
                    rechnung_ort = :rechnung_ort,
                    rechnung_email = :rechnung_email,
                    rechnung_leistung = :rechnung_leistung,
                    leistung_zeitraum = :leistung_zeitraum,
                    rechnung_betrag_brutto = :rechnung_betrag_brutto,
                    branche = :branche,
                    foerderprogramm = :foerderprogramm,
                    kontaktweg = :kontaktweg,
                    website = :website,
                    quellenurl = :quellenurl,
                    weitere_links = :weitere_links,
                    ansprache = :ansprache,
                    bedingungen_bestaetigt_am = :bedingungen_bestaetigt_am,
                    bedingungen_weg = :bedingungen_weg,
                    bedingungen_beleg = :bedingungen_beleg
                WHERE id = :id
            ');
            $stmt->execute([
                'firma'              => $firma,
                'paket'              => $_POST['paket'] ?: null,
                'foerdergruppe'      => isset(SPONSOR_FOERDERGRUPPE[(string) ($_POST['foerdergruppe'] ?? '')]) ? (string) $_POST['foerdergruppe'] : 'sponsoring',
                'prioritaet'         => sponsorPrioritaetFromPost($_POST['prioritaet'] ?? ''),
                'ort'                => trim($_POST['ort'] ?? '') ?: null,
                'summe'              => (float) ($_POST['summe'] ?? 0) ?: null,
                'status'             => sponsorStatusFromPost($_POST['status'] ?? 'neu'),
                'kein_kontakt'       => $keinKontakt,
                'kein_kontakt_grund' => $keinKontaktGrund,
                'kein_kontakt_wer'   => $keinKontaktWer,
                'kein_kontakt_datum' => $keinKontaktDatum ?: null,
                'notizen'            => trim($_POST['notizen'] ?? '') ?: null,
                'wiedervorlage'      => $_POST['wiedervorlage'] ?: null,
                'gruppe_id'          => $gruppeId,
                'rechnung_firma'     => trim($_POST['rechnung_firma'] ?? '') ?: null,
                'rechnung_strasse'   => trim($_POST['rechnung_strasse'] ?? '') ?: null,
                'rechnung_plz'       => trim($_POST['rechnung_plz'] ?? '') ?: null,
                'rechnung_ort'       => trim($_POST['rechnung_ort'] ?? '') ?: null,
                'rechnung_email'     => trim($_POST['rechnung_email'] ?? '') ?: null,
                'rechnung_leistung'  => trim($_POST['rechnung_leistung'] ?? '') ?: null,
                'leistung_zeitraum'  => trim($_POST['leistung_zeitraum'] ?? '') ?: null,
                'rechnung_betrag_brutto' => isset($_POST['rechnung_betrag_brutto']) ? 1 : 0,
                'branche'            => !empty($_POST['branche']) ? json_encode(array_values(array_filter(array_map('trim', (array) $_POST['branche'])))) : null,
                'foerderprogramm'    => trim($_POST['foerderprogramm'] ?? '') ?: null,
                'kontaktweg'         => trim($_POST['kontaktweg'] ?? '') ?: null,
                'ansprache'          => ($_POST['ansprache'] ?? 'sie') === 'du' ? 'du' : 'sie',
                'website'            => trim($_POST['website'] ?? '') ?: null,
                'quellenurl'         => trim($_POST['quellenurl'] ?? '') ?: null,
                'weitere_links'      => trim($_POST['weitere_links'] ?? '') ?: null,
                'bedingungen_bestaetigt_am' => $_POST['bedingungen_bestaetigt_am'] ?: null,
                'bedingungen_weg'    => in_array($_POST['bedingungen_weg'] ?? '', sponsorBedingungenWegKeys(), true) ? $_POST['bedingungen_weg'] : null,
                'bedingungen_beleg'  => isset($_POST['bedingungen_beleg']) ? 1 : 0,
                'id'                 => $sponsorId,
            ]);

            sponsorApplyRotation($pdo, $sponsorId, $firma);
            sponsorApplyLeitfaden($pdo, $sponsorId);

            $_SESSION['flash_success'] = 'Sponsor aktualisiert.';
            header('Location: ../sponsor_form.php?id=' . $sponsorId);
            exit;

        case 'delete':
            if ($sponsorId <= 0) {
                $_SESSION['flash_error'] = 'Ungültige Sponsor-ID.';
                header('Location: ../sponsoren.php');
                exit;
            }

            $stmt = $pdo->prepare('DELETE FROM sponsors WHERE id = :id');
            $stmt->execute(['id' => $sponsorId]);
            deleteSponsorLogo($sponsorId);
            deleteSponsorLeitfaden($sponsorId);
            try {
                writeSponsorenFeed($pdo);
            } catch (Throwable $e) {
                logError('Sponsor-Feed schreiben (delete): ' . $e->getMessage());
            }
            $_SESSION['flash_success'] = 'Sponsor gelöscht.';
            header('Location: ../sponsoren.php');
            exit;

        case 'kein_kontakt_set':
            if ($sponsorId <= 0) {
                $_SESSION['flash_error'] = 'Ungültige Sponsor-ID.';
                header('Location: ../sponsoren.php');
                exit;
            }

            $stmt = $pdo->prepare('UPDATE sponsors SET kein_kontakt = 1, kein_kontakt_datum = COALESCE(kein_kontakt_datum, CURDATE()) WHERE id = :id');
            $stmt->execute(['id' => $sponsorId]);
            $_SESSION['flash_success'] = 'Kein-Kontakt gesetzt.';
            header('Location: ../sponsoren.php');
            exit;

        case 'kein_kontakt_remove':
            if ($sponsorId <= 0) {
                $_SESSION['flash_error'] = 'Ungültige Sponsor-ID.';
                header('Location: ../sponsoren.php');
                exit;
            }

            $stmt = $pdo->prepare('UPDATE sponsors SET kein_kontakt = 0, kein_kontakt_grund = NULL, kein_kontakt_wer = NULL, kein_kontakt_datum = NULL WHERE id = :id');
            $stmt->execute(['id' => $sponsorId]);
            $_SESSION['flash_success'] = 'Kein-Kontakt aufgehoben.';
            header('Location: ../sponsoren.php');
            exit;

        case 'archive_bestaetigung':
            if ($sponsorId <= 0) {
                $_SESSION['flash_error'] = 'Ungültige Sponsor-ID.';
                header('Location: ../sponsoren.php');
                exit;
            }
            try {
                archiveSponsorBestaetigung($pdo, $sponsorId, (int) ($_SESSION['user_id'] ?? 0));
                $_SESSION['flash_success'] = 'Bestätigungs-Beleg im Sponsor-Ordner abgelegt.';
            } catch (Throwable $e) {
                logError('archive_bestaetigung Sponsor ' . $sponsorId . ': ' . $e->getMessage());
                $_SESSION['flash_error'] = 'Beleg-Ablage fehlgeschlagen: ' . $e->getMessage();
            }
            header('Location: ../sponsor_form.php?id=' . $sponsorId);
            exit;
    }

} catch (PDOException $e) {
    logError('Sponsor CRUD error: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Datenbankfehler.';
    header('Location: ../sponsoren.php');
    exit;
}
