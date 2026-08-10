<?php
/**
 * Sponsor-Anschreiben versenden (POST + CSRF)
 * Grundlage: intern/sponsor-crm-ausbau.md §5
 *
 * KEIN Autoversand: Orga wählt Empfänger, bestätigt im Dialog, dann Versand.
 *   - 1 Empfänger  → sofortiger Web-Versand
 *   - >1 Empfänger → Eintrag in Sende-Queue, Abarbeitung per bin/sponsor_versand.php
 *                    (15-Sek-Delay pro Mail, kein 8-Minuten-Web-Request)
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';
require_once __DIR__ . '/../../src/channels/mail.php';
require_once __DIR__ . '/../../src/sponsor_status.php';
require_once __DIR__ . '/../../src/sponsor_beleg.php';

// Rücksprungziel: die Bestätigungs-Seite schickt ihre Nutzer zu sich zurück statt in die
// Sponsoren-Liste. Feste Whitelist — kein offener Redirect aus dem Request. Muss vor der
// ersten Weiterleitung stehen (Methoden- und CSRF-Guard nutzen es bereits).
$redirectTo = in_array($_POST['redirect_to'] ?? '', ['bestaetigungen.php'], true)
    ? '../' . $_POST['redirect_to']
    : '../sponsoren.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirectTo);
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    $_SESSION['flash_error'] = 'Ungültige Anfrage.';
    header('Location: ' . $redirectTo);
    exit;
}

$user = getCurrentUserFromGuard();

$typ = $_POST['anschreiben_typ'] ?? 'erstanschreiben';
if (!in_array($typ, ['erstanschreiben', 'folgejahr', 'frei', 'bestaetigung', 'bedingungen'], true)) {
    $typ = 'erstanschreiben';
}

// Bestätigung: vom Versender abgewählte Anhang-Dateien (Opt-out). Die Abwahl lebt
// browser-seitig (localStorage) und gilt bis zum nächsten Versand; sie wird als
// exclude_*_fids[] mitgeschickt. Greift nur im Einzelversand (immer 1 Sponsor bei der Bestätigung).
$readExcludeFids = static function (string $key): array {
    if (!isset($_POST[$key]) || !is_array($_POST[$key])) {
        return [];
    }
    return array_values(array_filter(array_map(
        static fn ($v) => trim((string) $v),
        $_POST[$key]
    ), static fn ($v) => $v !== ''));
};
$excludeAssetFids  = $typ === 'bestaetigung' ? $readExcludeFids('exclude_asset_fids')  : [];
$excludePlakatFids = $typ === 'bestaetigung' ? $readExcludeFids('exclude_plakat_fids') : [];

// IDs einsammeln (Einzel-Button: sponsor_id, Mehrfach-Auswahl: sponsor_ids[])
$ids = [];
if (!empty($_POST['sponsor_id'])) {
    $ids[] = (int) $_POST['sponsor_id'];
}
if (isset($_POST['sponsor_ids']) && is_array($_POST['sponsor_ids'])) {
    foreach ($_POST['sponsor_ids'] as $id) {
        $ids[] = (int) $id;
    }
}
$ids = array_values(array_unique(array_filter($ids, static fn ($i) => $i > 0)));

if (empty($ids)) {
    $_SESSION['flash_error'] = 'Keine Sponsoren ausgewählt.';
    header('Location: ' . $redirectTo);
    exit;
}

try {
    $pdo = getDbConnection();

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT id, firma, paket, kein_kontakt FROM sponsors WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    $sponsoren = [];
    foreach ($stmt->fetchAll() as $row) {
        $sponsoren[(int) $row['id']] = $row;
    }

    // Alle für das Anschreiben markierten Ansprechpartner mit E-Mail je Sponsor holen.
    // Jeder bekommt eine eigene, individuell personalisierte Mail (kein CC/Sammel-Mail) —
    // "im_anschreiben" lässt sich pro Kontakt im Formular abwählen (Default: an).
    $apStmt = $pdo->prepare("
        SELECT sponsor_id, anrede, vorname, nachname, email
        FROM sponsor_ansprechpartner
        WHERE sponsor_id IN ($placeholders) AND email <> '' AND im_anschreiben = 1
        ORDER BY sponsor_id, id
    ");
    $apStmt->execute($ids);
    $apBySponsor = [];
    foreach ($apStmt->fetchAll() as $row) {
        $apBySponsor[$row['sponsor_id']][] = $row;
    }

    $recipients = [];
    $skippedKeinKontakt = 0;
    $skippedNoEmail = 0;

    foreach ($ids as $id) {
        $sponsor = $sponsoren[$id] ?? null;
        if ($sponsor === null) {
            continue;
        }
        if ((int) $sponsor['kein_kontakt'] === 1) {
            $skippedKeinKontakt++;
            continue;
        }
        $aps = $apBySponsor[$id] ?? [];
        if (empty($aps)) {
            $skippedNoEmail++;
            continue;
        }
        foreach ($aps as $ap) {
            $recipients[] = [
                'sponsor_id' => $id,
                'email'      => trim((string) $ap['email']),
                'anrede'     => (string) $ap['anrede'],
                'vorname'    => (string) $ap['vorname'],
                'nachname'   => (string) $ap['nachname'],
                'firma'      => (string) $sponsor['firma'],
                // Typ inkl. 'sachsponsor' steckt jetzt direkt im paket-Feld;
                // sponsorLevelText() mappt 'sachsponsor' → 'Sachsponsor'.
                'paket'      => (string) ($sponsor['paket'] ?? ''),
            ];
        }
    }

    $hinweis = '';
    if ($skippedKeinKontakt > 0) {
        $hinweis .= " {$skippedKeinKontakt} mit „Kein Kontakt“ übersprungen.";
    }
    if ($skippedNoEmail > 0) {
        $hinweis .= " {$skippedNoEmail} ohne E-Mail übersprungen.";
    }

    if (empty($recipients)) {
        $_SESSION['flash_error'] = 'Kein versandfähiger Empfänger.' . $hinweis;
        header('Location: ' . $redirectTo);
        exit;
    }

    // --- Einzelversand: sofort über Web-Request ---
    if (count($recipients) === 1) {
        $r = $recipients[0];
        try {
            $ok = sendSponsorAnschreiben($r['email'], $r['anrede'], $r['vorname'], $r['nachname'], $r['firma'], $typ, $r['paket'], (int)($user['id'] ?? 0), $excludeAssetFids, $excludePlakatFids, (int) $r['sponsor_id']);
        } catch (Throwable $e) {
            $ok = false;
            logError('Sponsor-Versand (einzeln) Exception: ' . $e->getMessage());
        }

        if ($ok) {
            sponsorMarkGesendet($pdo, $r['sponsor_id'], $typ);
            $_SESSION['flash_success'] = 'Anschreiben gesendet an ' . htmlspecialchars($r['firma']) . '.' . $hinweis;
            // Reset-Signal: eine Bestätigung ging raus → Browser setzt die Anhang-Abwahl zurück (alle wieder dran).
            if ($typ === 'bestaetigung') {
                $_SESSION['bestaetigung_versand_done'] = true;
                // Lebenszyklus: zugesagt → bestätigt (stuft abgerechnet/bezahlt nicht zurück).
                sponsorMarkBestaetigt($pdo, (int) $r['sponsor_id']);
            }

            // Bestätigung: Beleg-PDF im Sponsor-Ordner ablegen (Mail bleibt einmalig).
            if ($typ === 'bestaetigung') {
                try {
                    archiveSponsorBestaetigung($pdo, (int) $r['sponsor_id'], (int) ($user['id'] ?? 0));
                } catch (Throwable $e) {
                    logError('Beleg-Ablage (einzeln): ' . $e->getMessage());
                    $_SESSION['flash_error'] = 'Mail versendet, aber der Bestätigungs-Beleg wurde NICHT im Drive abgelegt: '
                        . htmlspecialchars($e->getMessage())
                        . ' — Ursache beheben und in der Sponsoren-Maske über „Bestätigungs-Beleg im Drive ablegen" erneut versuchen.';
                }
            }
        } else {
            $_SESSION['flash_error'] = 'Versand fehlgeschlagen (siehe Log).' . $hinweis;
        }
        header('Location: ' . $redirectTo);
        exit;
    }

    // --- Mehrfachauswahl: in Sende-Queue stellen ---
    $insert = $pdo->prepare('
        INSERT INTO sponsor_versand_queue (sponsor_id, email, anrede, nachname, vorname, firma, paket, anschreiben_typ, angefordert_von)
        VALUES (:sponsor_id, :email, :anrede, :nachname, :vorname, :firma, :paket, :typ, :von)
    ');
    $queued = 0;
    foreach ($recipients as $r) {
        $insert->execute([
            'sponsor_id' => $r['sponsor_id'],
            'email'      => $r['email'],
            'anrede'     => $r['anrede'],
            'nachname'   => $r['nachname'],
            'vorname'    => $r['vorname'],
            'firma'      => $r['firma'],
            'paket'      => $r['paket'] ?: null,
            'typ'        => $typ,
            'von'        => $user['id'] ?? null,
        ]);
        $queued++;
    }

    // Reset-Signal auch im Queue-Fall (Bestätigung mit mehreren Kontakten): ein Versand wurde ausgelöst.
    if ($typ === 'bestaetigung' && $queued > 0) {
        $_SESSION['bestaetigung_versand_done'] = true;
    }

    $scriptPath = realpath(__DIR__ . '/../../bin/sponsor_versand.php');
    $launched = false;
    if ($scriptPath && function_exists('exec') && !in_array('exec', array_map('trim', explode(',', ini_get('disable_functions'))))) {
        exec('MARKTLAUF_CLI=1 php ' . escapeshellarg($scriptPath) . ' > /dev/null 2>&1 &');
        $launched = true;
    }

    if ($launched) {
        $_SESSION['flash_success'] = $queued . ' Anschreiben werden jetzt gesendet (läuft im Hintergrund).' . $hinweis;
    } else {
        $_SESSION['flash_success'] = $queued . ' Anschreiben in die Sende-Queue gestellt. '
            . 'Versand starten: <code>MARKTLAUF_CLI=1 php bin/sponsor_versand.php</code> per SSH.' . $hinweis;
    }
    header('Location: ' . $redirectTo);
    exit;
} catch (PDOException $e) {
    logError('Sponsor-Versand DB error: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Datenbankfehler beim Versand.';
    header('Location: ' . $redirectTo);
    exit;
}
