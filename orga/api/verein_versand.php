<?php
/**
 * Vereins-/Laufevent-Anschreiben versenden (POST + CSRF).
 * KEIN Autoversand: Orga wählt Empfänger, bestätigt, dann Versand.
 *   - 1 Empfänger  → sofortiger Web-Versand
 *   - >1 Empfänger → Eintrag in Sende-Queue, Abarbeitung per bin/verein_versand.php
 * Das Anschreiben (Vorlagen-Slug) richtet sich je Empfänger nach der Kategorie.
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';
require_once __DIR__ . '/../../src/channels/mail.php';
require_once __DIR__ . '/../../src/verein_status.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../vereine.php');
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = 'Ungültige Anfrage.';
    header('Location: ../vereine.php');
    exit;
}

$user = getCurrentUserFromGuard();

$ids = [];
if (!empty($_POST['verein_id'])) {
    $ids[] = (int) $_POST['verein_id'];
}
if (isset($_POST['verein_ids']) && is_array($_POST['verein_ids'])) {
    foreach ($_POST['verein_ids'] as $id) {
        $ids[] = (int) $id;
    }
}
$ids = array_values(array_unique(array_filter($ids, static fn ($i) => $i > 0)));

if (empty($ids)) {
    $_SESSION['flash_error'] = 'Keine Einträge ausgewählt.';
    header('Location: ../vereine.php');
    exit;
}

try {
    $pdo = getDbConnection();

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT id, kategorie, name, anrede, vorname, nachname, email FROM vereine WHERE id IN ($placeholders)");
    $stmt->execute($ids);

    $recipients = [];
    $skippedNoEmail = 0;
    foreach ($stmt->fetchAll() as $row) {
        if (trim((string) $row['email']) === '') {
            $skippedNoEmail++;
            continue;
        }
        $kategorie = $row['kategorie'] === 'laufevent' ? 'laufevent' : 'verein';
        $recipients[] = [
            'verein_id' => (int) $row['id'],
            'email'     => trim((string) $row['email']),
            'anrede'    => (string) $row['anrede'],
            'vorname'   => (string) ($row['vorname'] ?? ''),
            'nachname'  => (string) ($row['nachname'] ?? ''),
            'name'      => (string) $row['name'],
            'kategorie' => $kategorie,
        ];
    }

    $hinweis = $skippedNoEmail > 0 ? " {$skippedNoEmail} ohne E-Mail übersprungen." : '';

    if (empty($recipients)) {
        $_SESSION['flash_error'] = 'Kein versandfähiger Empfänger.' . $hinweis;
        header('Location: ../vereine.php');
        exit;
    }

    // --- Einzelversand: sofort ---
    if (count($recipients) === 1) {
        $r = $recipients[0];
        try {
            $ok = sendVereinAnschreiben($r['email'], $r['anrede'], $r['vorname'], $r['nachname'], $r['name'], $r['kategorie'], $r['kategorie'], (int) ($user['id'] ?? 0));
        } catch (Throwable $e) {
            $ok = false;
            logError('Verein-Versand (einzeln) Exception: ' . $e->getMessage());
        }
        if ($ok) {
            vereinMarkGesendet($pdo, $r['verein_id'], $r['kategorie']);
            $_SESSION['flash_success'] = 'Anschreiben gesendet an ' . htmlspecialchars($r['name']) . '.' . $hinweis;
        } else {
            $_SESSION['flash_error'] = 'Versand fehlgeschlagen (siehe Log).' . $hinweis;
        }
        header('Location: ../vereine.php');
        exit;
    }

    // --- Mehrfachauswahl: in Sende-Queue ---
    $insert = $pdo->prepare('
        INSERT INTO verein_versand_queue (verein_id, email, anrede, vorname, nachname, name, kategorie, anschreiben_typ, angefordert_von)
        VALUES (:verein_id, :email, :anrede, :vorname, :nachname, :name, :kategorie, :typ, :von)
    ');
    $queued = 0;
    foreach ($recipients as $r) {
        $insert->execute([
            'verein_id' => $r['verein_id'],
            'email'     => $r['email'],
            'anrede'    => $r['anrede'],
            'vorname'   => $r['vorname'],
            'nachname'  => $r['nachname'],
            'name'      => $r['name'],
            'kategorie' => $r['kategorie'],
            'typ'       => $r['kategorie'],
            'von'       => $user['id'] ?? null,
        ]);
        $queued++;
    }

    $_SESSION['flash_success'] = "{$queued} Anschreiben in die Sende-Queue gestellt (Status „offen“). "
        . 'ACHTUNG: Der Versand startet NICHT automatisch — er muss über das CLI-Script '
        . 'ausgelöst werden (bin/verein_versand.php per SSH).' . $hinweis;
    header('Location: ../vereine.php');
    exit;
} catch (PDOException $e) {
    logError('Verein-Versand DB error: ' . $e->getMessage());
    $_SESSION['flash_error'] = 'Datenbankfehler beim Versand.';
    header('Location: ../vereine.php');
    exit;
}
