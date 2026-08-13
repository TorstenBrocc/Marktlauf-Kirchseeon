<?php
/**
 * Einmaliger Seed: Sponsoren ohne Zuständigen auf Torsten Tyras setzen (13.08.2026).
 * Freigabe TT: „Empfänger auf mich einstellen (jetzt aktiv umstellen in den Datensätzen)".
 *
 * Hintergrund: Die Erinnerungslogik soll an die Zuständigen gehen. 64 von 78 aktiven
 * Sponsoren hatten aber keinen — nach dieser Regel hätten 82 % der ToDos nie einen
 * Empfänger gehabt. Bereits vergebene Zuständigkeiten (Anja Jost, Simon Müller,
 * Stefan Reinhart) bleiben unangetastet.
 *
 * Aufruf: ssh strato-marktlauf "cd ~/marktlauf/Homepage && MARKTLAUF_CLI=1 php bin/seed_zustaendig_tt.php"
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli' && getenv('MARKTLAUF_CLI') !== '1') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../src/db.php';

$pdo = getDbConnection();

$tt = $pdo->prepare("SELECT id FROM users WHERE email = :m AND active = 1");
$tt->execute(['m' => 't.tyras@atsv-kirchseeon-marktlauf.de']);
$ttId = $tt->fetchColumn();
if ($ttId === false) {
    exit("ABBRUCH: Nutzer nicht gefunden.\n");
}

// Nur Einträge ohne Zuständigen und nur solche, die überhaupt noch Arbeit machen.
$st = $pdo->prepare("
    UPDATE sponsors SET zustaendig_user_id = :uid
    WHERE zustaendig_user_id IS NULL
      AND status NOT IN ('abgelehnt','bezahlt')
");
$st->execute(['uid' => (int) $ttId]);
echo "UPD: {$st->rowCount()} Sponsoren ohne Zuständigen auf Torsten Tyras gesetzt (User {$ttId}).\n";

$rest = (int) $pdo->query("
    SELECT COUNT(*) FROM sponsors
    WHERE zustaendig_user_id IS NULL AND status NOT IN ('abgelehnt','bezahlt')
")->fetchColumn();
echo "Verbleibend ohne Zuständigen: {$rest}\n";
