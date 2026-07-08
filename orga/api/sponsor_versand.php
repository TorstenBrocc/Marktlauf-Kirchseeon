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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../sponsoren.php');
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    $_SESSION['flash_error'] = 'Ungültige Anfrage.';
    header('Location: ../sponsoren.php');
    exit;
}

$user = getCurrentUserFromGuard();

$typ = $_POST['anschreiben_typ'] ?? 'erstanschreiben';
if (!in_array($typ, ['erstanschreiben', 'folgejahr'], true)) {
    $typ = 'erstanschreiben';
}

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
    header('Location: ../sponsoren.php');
    exit;
}

try {
    $pdo = getDbConnection();

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT id, firma, kein_kontakt FROM sponsors WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    $sponsoren = [];
    foreach ($stmt->fetchAll() as $row) {
        $sponsoren[(int) $row['id']] = $row;
    }

    // Ersten Ansprechpartner mit E-Mail je Sponsor holen
    $apStmt = $pdo->prepare("
        SELECT sponsor_id, anrede, nachname, email
        FROM sponsor_ansprechpartner
        WHERE sponsor_id IN ($placeholders) AND email <> ''
        ORDER BY sponsor_id, id
    ");
    $apStmt->execute($ids);
    $apBySponsor = [];
    foreach ($apStmt->fetchAll() as $row) {
        if (!isset($apBySponsor[$row['sponsor_id']])) {
            $apBySponsor[$row['sponsor_id']] = $row;
        }
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
        $ap = $apBySponsor[$id] ?? null;
        if ($ap === null || trim((string) $ap['email']) === '') {
            $skippedNoEmail++;
            continue;
        }
        $recipients[] = [
            'sponsor_id' => $id,
            'email'      => trim((string) $ap['email']),
            'anrede'     => (string) $ap['anrede'],
            'nachname'   => (string) $ap['nachname'],
            'firma'      => (string) $sponsor['firma'],
        ];
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
        header('Location: ../sponsoren.php');
        exit;
    }

    // --- Einzelversand: sofort über Web-Request ---
    if (count($recipients) === 1) {
        $r = $recipients[0];
        try {
            $ok = sendSponsorAnschreiben($r['email'], $r['anrede'], $r['nachname'], $r['firma'], $typ);
        } catch (Throwable $e) {
            $ok = false;
            logError('Sponsor-Versand (einzeln) Exception: ' . $e->getMessage());
        }

        if ($ok) {
            sponsorMarkGesendet($pdo, $r['sponsor_id'], $typ);
            $_SESSION['flash_success'] = 'Anschreiben gesendet an ' . htmlspecialchars($r['firma']) . '.' . $hinweis;
        } else {
            $_SESSION['flash_error'] = 'Versand fehlgeschlagen (siehe Log).' . $hinweis;
        }
        header('Location: ../sponsoren.php');
        exit;
    }

    // --- Mehrfachauswahl: in Sende-Queue stellen ---
    $insert = $pdo->prepare('
        INSERT INTO sponsor_versand_queue (sponsor_id, email, anrede, nachname, firma, anschreiben_typ, angefordert_von)
        VALUES (:sponsor_id, :email, :anrede, :nachname, :firma, :typ, :von)
    ');
    $queued = 0;
    foreach ($recipients as $r) {
        $insert->execute([
            'sponsor_id' => $r['sponsor_id'],
            'email'      => $r['email'],
            'anrede'     => $r['anrede'],
            'nachname'   => $r['nachname'],
            'firma'      => $r['firma'],
            'typ'        => $typ,
            'von'        => $user['id'] ?? null,
        ]);
        $queued++;
    }

    $_SESSION['flash_success'] = "{$queued} Anschreiben in die Sende-Queue gestellt. "
        . 'Der Versand läuft über das CLI-Script (bin/sponsor_versand.php).' . $hinweis;
    header('Location: ../sponsoren.php');
    exit;
} catch (PDOException $e) {
    logError('Sponsor-Versand DB error: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Datenbankfehler beim Versand.';
    header('Location: ../sponsoren.php');
    exit;
}
