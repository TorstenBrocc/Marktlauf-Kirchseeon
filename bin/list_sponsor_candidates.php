<?php
/** Read-only-Diagnose: Kandidaten-Zeilen für den Rotation-Seed anzeigen. */
declare(strict_types=1);
require_once __DIR__ . '/../src/db.php';
$pdo = getDbConnection();
foreach (['%potheke%', '%parkasse%', '%rmann%', '%ietas%'] as $p) {
    echo "== LIKE $p ==\n";
    $st = $pdo->prepare('SELECT id, firma, status, in_rotation FROM sponsors WHERE firma LIKE :p ORDER BY id');
    $st->execute(['p' => $p]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        echo "  #{$r['id']}  [{$r['status']}] rot={$r['in_rotation']}  {$r['firma']}\n";
    }
}
