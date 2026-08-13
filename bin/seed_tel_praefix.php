<?php
/**
 * Einmaliger Seed: Präfix „Tel." aus einer Telefonnummer entfernen (13.08.2026).
 * Gefunden beim Trockenlauf des ToDo-Digests: „Tel. Tel. 08091 2038".
 * Betrifft genau einen Datensatz; die Ziffern bleiben unverändert.
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli' && getenv('MARKTLAUF_CLI') !== '1') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../src/db.php';
$pdo = getDbConnection();

$st = $pdo->query("SELECT id, telefon FROM sponsor_ansprechpartner WHERE telefon REGEXP '[A-Za-z]'");
$n = 0;
foreach ($st->fetchAll() as $row) {
    // Nur führende Wort-Präfixe entfernen (Tel./Telefon/Fon/Mobil), Ziffern nie anfassen.
    $neu = trim((string) preg_replace('/^\s*(telefon|tel\.?|fon|mobil|handy)\s*:?\s*/iu', '', (string) $row['telefon']));
    if ($neu === '' || $neu === trim((string) $row['telefon'])) {
        echo "OFFEN AP#{$row['id']}: '{$row['telefon']}' — kein bekanntes Präfix, nicht angefasst\n";
        continue;
    }
    $pdo->prepare('UPDATE sponsor_ansprechpartner SET telefon = :t WHERE id = :id')
        ->execute(['t' => $neu, 'id' => $row['id']]);
    echo "TEL AP#{$row['id']}: '{$row['telefon']}' → '{$neu}'\n";
    $n++;
}
echo "Fertig. {$n} bereinigt.\n";
