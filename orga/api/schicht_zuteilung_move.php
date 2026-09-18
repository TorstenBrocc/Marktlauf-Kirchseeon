<?php
/**
 * Helfer per Drag & Drop zuteilen / umhaengen / loesen (POST, JSON-Antwort).
 * Gegenstueck zum Board (orga/einsatzplan.php). Muster wie file_move.php:
 * CSRF im POST, JSON zurueck, kein Redirect — das Board aktualisiert sein DOM
 * selbst und zeigt einen Toast.
 *
 * Aktionen:
 *   add    — Helfer aus dem Pool auf eine Schicht ziehen
 *   move   — Helfer von Schicht A auf Schicht B ziehen (atomar)
 *   remove — Helfer von einer Schicht zurueck in den Pool ziehen
 *
 * Bewusst KEINE Status-Sperre (anders als schicht_zuteilung.php): wer sich ueber
 * das Formular selbst angemeldet hat, ist fachlich dabei. Der Status steuert den
 * persoenlichen Zugang (helfer/zugang.php), nicht die Einsatzplanung.
 * Der Bedarf ist eine Warnung, keine Schranke — real wird ueber Bedarf hinaus
 * eingeteilt (Aufbau/Abbau).
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';

header('Content-Type: application/json; charset=utf-8');

function zuteilungFail(string $msg, int $code = 400): never
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'message' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    zuteilungFail('Ungültige Anfrage.');
}

$action   = (string) ($_POST['action'] ?? '');
$helferId = (int) ($_POST['helfer_id'] ?? 0);
$zielId   = (int) ($_POST['schicht_id'] ?? 0);      // Zielschicht (add/move)
$vonId    = (int) ($_POST['von_schicht_id'] ?? 0);  // Quellschicht (move/remove)

if ($helferId <= 0) {
    zuteilungFail('Ungültiger Helfer.');
}

try {
    $pdo = getDbConnection();

    // Helfer muss existieren — Name wird fuer die Rueckmeldung gebraucht.
    $hStmt = $pdo->prepare('SELECT vorname, nachname, status FROM helfer WHERE id = :id');
    $hStmt->execute(['id' => $helferId]);
    $helfer = $hStmt->fetch();
    if (!$helfer) {
        zuteilungFail('Helfer nicht gefunden.', 404);
    }
    $name = trim($helfer['vorname'] . ' ' . $helfer['nachname']);

    switch ($action) {
        case 'add':
        case 'move':
            if ($zielId <= 0) {
                zuteilungFail('Ungültige Zielschicht.');
            }

            $sStmt = $pdo->prepare('SELECT titel, bedarf FROM schichten WHERE id = :id');
            $sStmt->execute(['id' => $zielId]);
            $schicht = $sStmt->fetch();
            if (!$schicht) {
                zuteilungFail('Schicht nicht gefunden.', 404);
            }

            // Schon drin? Dann ist der Zug ein No-op (z. B. Drop auf dieselbe Karte).
            $exists = $pdo->prepare('SELECT 1 FROM schicht_zuteilung WHERE schicht_id = :s AND helfer_id = :h');
            $exists->execute(['s' => $zielId, 'h' => $helferId]);
            if ($exists->fetchColumn()) {
                echo json_encode([
                    'ok'      => true,
                    'noop'    => true,
                    'message' => $name . ' ist dort bereits eingeteilt.',
                ]);
                exit;
            }

            $pdo->beginTransaction();
            if ($action === 'move' && $vonId > 0) {
                $del = $pdo->prepare('DELETE FROM schicht_zuteilung WHERE schicht_id = :s AND helfer_id = :h');
                $del->execute(['s' => $vonId, 'h' => $helferId]);
            }
            $ins = $pdo->prepare('
                INSERT IGNORE INTO schicht_zuteilung (schicht_id, helfer_id)
                VALUES (:s, :h)
            ');
            $ins->execute(['s' => $zielId, 'h' => $helferId]);
            $pdo->commit();

            // Bedarfs-Warnung nach dem Zug berechnen.
            $cnt = $pdo->prepare('SELECT COUNT(*) FROM schicht_zuteilung WHERE schicht_id = :s');
            $cnt->execute(['s' => $zielId]);
            $anzahl = (int) $cnt->fetchColumn();
            $bedarf = (int) $schicht['bedarf'];

            $msg = $name . ' → ' . $schicht['titel'];
            $warn = null;
            if ($anzahl > $bedarf) {
                $warn = 'Bedarf überschritten (' . $anzahl . ' / ' . $bedarf . ')';
            }
            if ($helfer['status'] !== 'bestaetigt') {
                $hinweis = 'Status „' . $helfer['status'] . '“ — ohne Bestätigung kein persönlicher Zugang';
                $warn = $warn === null ? $hinweis : $warn . ' · ' . $hinweis;
            }

            echo json_encode([
                'ok'      => true,
                'message' => $msg,
                'warn'    => $warn,
                'anzahl'  => $anzahl,
                'bedarf'  => $bedarf,
                'voll'    => $anzahl >= $bedarf,
            ]);
            exit;

        case 'remove':
            if ($vonId <= 0) {
                zuteilungFail('Ungültige Schicht.');
            }
            $del = $pdo->prepare('DELETE FROM schicht_zuteilung WHERE schicht_id = :s AND helfer_id = :h');
            $del->execute(['s' => $vonId, 'h' => $helferId]);

            $cnt = $pdo->prepare('SELECT COUNT(*) FROM schicht_zuteilung WHERE schicht_id = :s');
            $cnt->execute(['s' => $vonId]);
            $anzahl = (int) $cnt->fetchColumn();
            $bStmt = $pdo->prepare('SELECT bedarf FROM schichten WHERE id = :id');
            $bStmt->execute(['id' => $vonId]);
            $bedarf = (int) $bStmt->fetchColumn();

            echo json_encode([
                'ok'      => true,
                'message' => $name . ' aus der Schicht genommen.',
                'anzahl'  => $anzahl,
                'bedarf'  => $bedarf,
                'voll'    => $anzahl >= $bedarf,
            ]);
            exit;

        default:
            zuteilungFail('Unbekannte Aktion.');
    }
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    logError('schicht_zuteilung_move: ' . $e->getMessage());
    zuteilungFail('Datenbankfehler.', 500);
}
