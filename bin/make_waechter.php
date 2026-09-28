#!/usr/bin/env php
<?php
/**
 * CLI-Tool: make.com-Waechter — Alarm-Mail, wenn ein make-Szenario still steht.
 *
 * Laeuft taeglich ueber .github/workflows/taegliche_erinnerung.yml (Strato hat kein crontab).
 * Schickt NUR eine Mail, wenn src/make_waechter.php etwas findet; sonst bleibt es still.
 */

// Strato: SSH-Shell liefert cgi-fcgi statt cli → Bypass via MARKTLAUF_CLI=1
if (php_sapi_name() !== 'cli' && getenv('MARKTLAUF_CLI') !== '1') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/channels/mail.php';
require_once __DIR__ . '/../src/logger.php';
require_once __DIR__ . '/../src/make_waechter.php';

try {
    $befunde = makeWaechterBefunde(getDbConnection());
} catch (PDOException $e) {
    logError('make_waechter DB error: ' . $e->getMessage());
    echo "Datenbankfehler: {$e->getMessage()}\n";
    exit(1);
}

if (!$befunde) {
    echo "make-Waechter: alles in Ordnung.\n";
    exit(0);
}

$punkte = '• ' . implode("\n• ", $befunde);
$body   = <<<TEXT
Hallo Torsten,

der make-Wächter hat etwas gefunden:

{$punkte}

Prüfen: https://eu1.make.com/organization/8428610/dashboard
Ist ein Szenario abgeschaltet („Inactive“), steht der Grund in dessen History.
Hintergrund und Nachhol-Rezept: intern/make-com-optimierung-spec.md §7.6.

Der Hinweis steht auch oben im Cockpit, bis das Problem behoben ist:
https://atsv-kirchseeon-marktlauf.de/orga/

──────────────────────────
ATSV Kirchseeon Marktlauf
TEXT;

$mail = marktlaufMailBody($body);
$ok   = sendMail(MAKE_WAECHTER_EMPFAENGER, '⚠️ make.com steht still — bitte prüfen', $mail['text'], $mail['html']);

echo $punkte . "\n";
echo $ok ? "Alarm-Mail gesendet an " . MAKE_WAECHTER_EMPFAENGER . "\n" : "Alarm-Mail FEHLGESCHLAGEN\n";
exit($ok ? 0 : 1);
