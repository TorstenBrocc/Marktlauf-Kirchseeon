<?php
/**
 * Ansprechpartner Autosave (POST, JSON).
 * Actions:
 *   save   – Upsert einer Kontakt-Zeile per id (id=0 => Neuanlage, gibt neue id zurück)
 *   delete – Löschen einer Zeile per id
 * Jede Aktion prüft, dass die Zeile zum übergebenen sponsor_id gehört.
 */

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/logger.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'message' => 'Nur POST.']);
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['ok' => false, 'message' => 'Ungültige Anfrage.']);
    exit;
}

$action    = (string) ($_POST['action'] ?? '');
$sponsorId = (int) ($_POST['sponsor_id'] ?? 0);
$apId      = (int) ($_POST['ap_id'] ?? 0);

if ($sponsorId <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Ungültige Sponsor-ID.']);
    exit;
}

try {
    $pdo = getDbConnection();

    // Sponsor muss existieren (FK-Schutz + klare Meldung).
    $chk = $pdo->prepare('SELECT id FROM sponsors WHERE id = :id');
    $chk->execute(['id' => $sponsorId]);
    if ($chk->fetchColumn() === false) {
        echo json_encode(['ok' => false, 'message' => 'Sponsor nicht gefunden.']);
        exit;
    }

    if ($action === 'save') {
        $anrede  = in_array($_POST['anrede'] ?? '', ['Herr', 'Frau', 'Divers', ''], true)
            ? (string) $_POST['anrede'] : '';
        $vorname  = trim((string) ($_POST['vorname'] ?? ''));
        $nachname = trim((string) ($_POST['nachname'] ?? ''));
        $funktion = trim((string) ($_POST['funktion'] ?? ''));
        $telefon  = trim((string) ($_POST['telefon'] ?? ''));
        $email    = trim((string) ($_POST['email'] ?? ''));
        $imAnschreiben = ((string) ($_POST['im_anschreiben'] ?? '1')) === '0' ? 0 : 1;

        $params = [
            'anrede'         => $anrede,
            'vorname'        => $vorname,
            'nachname'       => $nachname,
            'funktion'       => $funktion,
            'telefon'        => $telefon,
            'email'          => $email,
            'im_anschreiben' => $imAnschreiben,
        ];

        if ($apId > 0) {
            // Update – nur die eigene Zeile dieses Sponsors.
            $own = $pdo->prepare('SELECT id FROM sponsor_ansprechpartner WHERE id = :id AND sponsor_id = :sid');
            $own->execute(['id' => $apId, 'sid' => $sponsorId]);
            if ($own->fetchColumn() === false) {
                echo json_encode(['ok' => false, 'message' => 'Ansprechpartner nicht gefunden.']);
                exit;
            }
            $params['id']  = $apId;
            $params['sid'] = $sponsorId;
            $pdo->prepare('
                UPDATE sponsor_ansprechpartner
                SET anrede = :anrede, vorname = :vorname, nachname = :nachname, funktion = :funktion,
                    telefon = :telefon, email = :email, im_anschreiben = :im_anschreiben
                WHERE id = :id AND sponsor_id = :sid
            ')->execute($params);
            echo json_encode(['ok' => true, 'id' => $apId]);
            exit;
        }

        // Neuanlage – ans Ende der Sortier-Reihenfolge dieses Sponsors haengen.
        $posStmt = $pdo->prepare('SELECT COALESCE(MAX(sortierung), 0) + 1 FROM sponsor_ansprechpartner WHERE sponsor_id = :sid');
        $posStmt->execute(['sid' => $sponsorId]);
        $params['sortierung'] = (int) $posStmt->fetchColumn();
        $params['sponsor_id'] = $sponsorId;
        $pdo->prepare('
            INSERT INTO sponsor_ansprechpartner
                (sponsor_id, anrede, vorname, nachname, funktion, telefon, email, im_anschreiben, sortierung)
            VALUES
                (:sponsor_id, :anrede, :vorname, :nachname, :funktion, :telefon, :email, :im_anschreiben, :sortierung)
        ')->execute($params);
        echo json_encode(['ok' => true, 'id' => (int) $pdo->lastInsertId()]);
        exit;
    }

    if ($action === 'reorder') {
        // Neue Reihenfolge: Liste von ap_ids in Ziel-Reihenfolge. Es werden nur
        // Zeilen geschrieben, die dem uebergebenen Sponsor gehoeren (Ownership per
        // WHERE sponsor_id). Positionen werden auf 1..n normalisiert.
        $order = $_POST['order'] ?? [];
        if (!is_array($order)) {
            $order = [];
        }
        $upd = $pdo->prepare('UPDATE sponsor_ansprechpartner SET sortierung = :pos WHERE id = :id AND sponsor_id = :sid');
        $pdo->beginTransaction();
        try {
            $pos = 1;
            foreach ($order as $rawId) {
                $id = (int) $rawId;
                if ($id <= 0) {
                    continue;
                }
                $upd->execute(['pos' => $pos, 'id' => $id, 'sid' => $sponsorId]);
                $pos++;
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'delete') {
        if ($apId > 0) {
            $pdo->prepare('DELETE FROM sponsor_ansprechpartner WHERE id = :id AND sponsor_id = :sid')
                ->execute(['id' => $apId, 'sid' => $sponsorId]);
        }
        echo json_encode(['ok' => true]);
        exit;
    }

    echo json_encode(['ok' => false, 'message' => 'Unbekannte Aktion.']);
    exit;
} catch (Throwable $e) {
    logError('ansprechpartner_save: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'message' => 'Speichern fehlgeschlagen.']);
    exit;
}
