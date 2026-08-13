<?php
/**
 * Einmaliger Seed: Sponsor 19 auf die korrekte Firmierung umbenennen (13.08.2026).
 * Freigabe TT. Recherche: ein „Bauzentrum Honold" ist in keiner Quelle auffindbar;
 * der Markt firmiert als hagebaumarkt Ebersberg GmbH & Co. KG.
 *
 * Der alte Name bleibt in den Notizen erhalten — sonst wäre der Bezug zur bisherigen
 * Korrespondenz weg.
 *
 * Aufruf: ssh strato-marktlauf "cd ~/marktlauf/Homepage && MARKTLAUF_CLI=1 php bin/seed_sponsor_honold_umbenennen.php"
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli' && getenv('MARKTLAUF_CLI') !== '1') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../src/db.php';

$pdo = getDbConnection();

$st = $pdo->prepare('SELECT firma, status, notizen, quellenurl FROM sponsors WHERE id = 19');
$st->execute();
$row = $st->fetch();

if ($row === false) {
    exit("SKIP #19: nicht gefunden\n");
}
if (in_array((string) $row['status'], ['zugesagt', 'bestaetigt', 'abgerechnet', 'bezahlt'], true)) {
    exit("SKIP #19: Schutzstatus '{$row['status']}'\n");
}
if (trim((string) $row['firma']) !== 'Bauzentrum Honold (Hagebau)') {
    exit("SKIP #19: Firma ist bereits '{$row['firma']}' — nichts zu tun\n");
}

$notiz = 'Umbenannt 13.08.2026 (Freigabe TT): vormals „Bauzentrum Honold (Hagebau)" im CRM. '
       . 'Korrekte Firmierung laut Impressum/Registerangaben: hagebaumarkt Ebersberg GmbH & Co. KG, '
       . 'Langwied 2, 85560 Ebersberg (GF Joachim Ricker, Mathias Lehmann; Gesellschafter Bauzentrum '
       . 'Mayer, Gural Baustoffvertrieb, Josef Schwarz & Sohn). Der Name „Honold" ließ sich in keiner '
       . 'Quelle belegen. Die frühere Korrespondenz lief unter dem alten Namen.';

$set = ['firma = :firma'];
$params = ['firma' => 'hagebaumarkt Ebersberg GmbH & Co. KG'];

if (mb_stripos((string) ($row['notizen'] ?? ''), 'Umbenannt 13.08.2026') === false) {
    $set[] = 'notizen = :notizen';
    $params['notizen'] = (trim((string) ($row['notizen'] ?? '')) === ''
        ? '' : rtrim((string) $row['notizen']) . "\n\n") . $notiz;
}
if (trim((string) ($row['quellenurl'] ?? '')) === '') {
    $set[] = 'quellenurl = :q';
    $params['q'] = 'https://www.hagebau.de/baumarkt/hagebaumarkt-ebersberg-gmbh-co-kg-ebersberg-sn174522/';
}

$pdo->prepare('UPDATE sponsors SET ' . implode(', ', $set) . ' WHERE id = 19')->execute($params);
echo "UPD #19: umbenannt in „hagebaumarkt Ebersberg GmbH & Co. KG\"\n";
echo "Fertig.\n";
