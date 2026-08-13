<?php
/**
 * Social-Fahrplan CRUD (POST + CSRF) — nur Admin/Orga.
 * Aktionen: create, update, erledigt, loeschen.
 * Wiederkehr: beim Erledigen rueckt ein Eintrag mit frequenz_tage aufs naechste
 * Datum vor (bis einschliesslich ende), statt auf erledigt zu wechseln.
 * Response: {"ok":true} oder {"ok":false,"message":"..."}
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/social_anlaesse.php';

header('Content-Type: application/json; charset=utf-8');

function fahrplanJson(bool $ok, string $message = ''): void {
    echo json_encode(['ok' => $ok, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    fahrplanJson(false, 'Methode nicht erlaubt.');
}
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    fahrplanJson(false, 'Ungültige Anfrage.');
}

$action = $_POST['action'] ?? '';
$pdo    = getDbConnection();

/** Datum validieren (Y-m-d) oder null. */
function fahrplanDatum(?string $raw): ?string {
    $raw = trim((string) $raw);
    if ($raw === '') { return null; }
    $d = DateTime::createFromFormat('Y-m-d', $raw);
    return ($d && $d->format('Y-m-d') === $raw) ? $raw : null;
}

try {
    if ($action === 'create' || $action === 'update') {
        $anlass = $_POST['anlass_key'] ?? '';
        if (!isset(socialAnlaesse()[$anlass])) {
            http_response_code(422);
            fahrplanJson(false, 'Unbekannter Anlass.');
        }
        $zieldatum = fahrplanDatum($_POST['zieldatum'] ?? '');
        $ende      = fahrplanDatum($_POST['ende'] ?? '');
        $frequenz  = (int) ($_POST['frequenz_tage'] ?? 0);
        $frequenz  = ($frequenz > 0 && $frequenz <= 365) ? $frequenz : null;

        $zustaendig = (int) ($_POST['zustaendig_user_id'] ?? 0);
        if ($zustaendig > 0) {
            $gueltig = array_column(orgaUserListe($pdo), 'id');
            if (!in_array($zustaendig, array_map('intval', $gueltig), true)) {
                http_response_code(422);
                fahrplanJson(false, 'Unbekannter Zuständiger.');
            }
        } else {
            $zustaendig = null;
        }

        if ($action === 'create') {
            $stmt = $pdo->prepare(
                'INSERT INTO social_fahrplan (anlass_key, zieldatum, zustaendig_user_id, frequenz_tage, ende)
                 VALUES (:anlass, :zieldatum, :zustaendig, :frequenz, :ende)'
            );
            $stmt->execute([
                'anlass' => $anlass, 'zieldatum' => $zieldatum,
                'zustaendig' => $zustaendig, 'frequenz' => $frequenz, 'ende' => $ende,
            ]);
        } else {
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = $pdo->prepare(
                'UPDATE social_fahrplan
                    SET anlass_key = :anlass, zieldatum = :zieldatum,
                        zustaendig_user_id = :zustaendig, frequenz_tage = :frequenz, ende = :ende
                  WHERE id = :id'
            );
            $stmt->execute([
                'anlass' => $anlass, 'zieldatum' => $zieldatum,
                'zustaendig' => $zustaendig, 'frequenz' => $frequenz, 'ende' => $ende,
                'id' => $id,
            ]);
        }
        fahrplanJson(true);
    }

    if ($action === 'erledigt') {
        $id   = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT zieldatum, frequenz_tage, ende FROM social_fahrplan WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            http_response_code(404);
            fahrplanJson(false, 'Eintrag nicht gefunden.');
        }

        // Wiederkehr: aufs naechste Datum vorruecken, solange es das Ende nicht ueberschreitet
        if ($row['frequenz_tage'] && $row['zieldatum']) {
            $naechstes = (new DateTime($row['zieldatum']))
                ->modify('+' . (int) $row['frequenz_tage'] . ' days')
                ->format('Y-m-d');
            if ($row['ende'] === null || $naechstes <= $row['ende']) {
                $pdo->prepare('UPDATE social_fahrplan SET zieldatum = :d, post_id = NULL WHERE id = :id')
                    ->execute(['d' => $naechstes, 'id' => $id]);
                fahrplanJson(true, 'Erledigt — nächster Termin ' . date('d.m.Y', strtotime($naechstes)) . '.');
            }
        }
        $pdo->prepare("UPDATE social_fahrplan SET status = 'erledigt' WHERE id = :id")->execute(['id' => $id]);
        fahrplanJson(true);
    }

    if ($action === 'loeschen') {
        $id = (int) ($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM social_fahrplan WHERE id = :id')->execute(['id' => $id]);
        fahrplanJson(true);
    }

    http_response_code(422);
    fahrplanJson(false, 'Unbekannte Aktion.');
} catch (PDOException $e) {
    logError('fahrplan_crud: ' . $e->getMessage());
    http_response_code(500);
    fahrplanJson(false, 'Datenbankfehler.');
}
