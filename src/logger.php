<?php
declare(strict_types=1);

function logError(string $message): void {
    $logPath = __DIR__ . '/../storage/logs/php_errors.log';
    error_log('[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, 3, $logPath);
}

/**
 * Ausgabe der CLI-Skripte in bin/. Was sie auf stdout schreiben, steht bei den
 * Läufen über GitHub Actions im Actions-Log — und das ist im öffentlichen Repo für
 * jeden angemeldeten GitHub-Nutzer lesbar (Befund 2026-10-07, Sponsor- und
 * Mitgliederadressen im Log). Deshalb geben die Skripte nur IDs und Zähler aus;
 * Adressen, Namen, Firmen und Aufgabentitel gehören höchstens in logError()
 * (Server-Datei, nicht öffentlich).
 *
 * Sicherheitsnetz dahinter: jede E-Mail-Adresse wird zu „[adresse]" — auch in
 * Exception-Texten, deren Inhalt niemand vorhersagen kann.
 */
function cliAusgabe(string $text): void {
    echo ohneEmailAdressen($text);
}

function ohneEmailAdressen(string $text): string {
    $ohne = preg_replace('/[\p{L}\p{N}._%+-]+@[\p{L}\p{N}.-]+\.\p{L}{2,}/u', '[adresse]', $text);
    if ($ohne === null) {
        // Ungültiges UTF-8 (z. B. roher Server-Text in einer Exception): ASCII-Muster ohne /u.
        $ohne = (string) preg_replace('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', '[adresse]', $text);
    }
    return $ohne;
}
