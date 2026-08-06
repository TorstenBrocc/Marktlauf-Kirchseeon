<?php
/**
 * One-time OAuth helper: obtain a Google Drive refresh token for the shared-drive
 * storage backend (intern/gdrive-storage-spec.md, Paket 1). Run LOCALLY, not on the
 * server:
 *
 *   php bin/gdrive_auth.php /pfad/zur/oauth-client.json
 *
 * Loopback (Desktop app) flow: prints an auth URL; you sign in as info@ and grant
 * Drive access; Google redirects back to a local port; the script exchanges the code
 * for a refresh token and prints the ready-to-paste config block. It also lists the
 * shared drives so you can copy the "Marktlauf Orga" drive id.
 *
 * No Composer / SDK: raw sockets + cURL only.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Nur über die Kommandozeile ausführen.\n");
    exit(1);
}

$clientJsonPath = $argv[1] ?? '';
if ($clientJsonPath === '' || !is_file($clientJsonPath)) {
    fwrite(STDERR, "Aufruf: php bin/gdrive_auth.php <pfad-zur-oauth-client.json>\n");
    exit(1);
}

$raw = json_decode((string) file_get_contents($clientJsonPath), true);
$cfg = $raw['installed'] ?? $raw['web'] ?? null;
if (!is_array($cfg) || empty($cfg['client_id']) || empty($cfg['client_secret'])) {
    fwrite(STDERR, "Konnte client_id/client_secret nicht aus der JSON lesen (erwartet Block 'installed' oder 'web').\n");
    exit(1);
}
$clientId     = (string) $cfg['client_id'];
$clientSecret = (string) $cfg['client_secret'];

$scope = 'https://www.googleapis.com/auth/drive';

// Open a loopback listener on a random free port. Desktop OAuth clients allow any
// http://127.0.0.1:<port> redirect, so no fixed redirect URI needs registering.
$server = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
if (!$server) {
    fwrite(STDERR, "Konnte lokalen Port nicht öffnen: $errstr ($errno)\n");
    exit(1);
}
$sockName    = stream_socket_get_name($server, false); // "127.0.0.1:PORT"
$port        = (int) substr($sockName, strrpos($sockName, ':') + 1);
$redirectUri = 'http://127.0.0.1:' . $port;

$authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
    'client_id'     => $clientId,
    'redirect_uri'  => $redirectUri,
    'response_type' => 'code',
    'scope'         => $scope,
    'access_type'   => 'offline',
    'prompt'        => 'consent', // force a refresh_token even on repeat runs
]);

echo "\n1) Öffne diese URL im Browser (als info@ anmelden, Drive-Zugriff erlauben):\n\n";
echo $authUrl . "\n\n";
echo "2) Danach kehrt der Browser automatisch hierher zurück. Warte auf die Anmeldung ...\n";

$conn = @stream_socket_accept($server, 300); // wait up to 5 minutes
if (!$conn) {
    fwrite(STDERR, "Zeitüberschreitung beim Warten auf die Anmeldung.\n");
    exit(1);
}
$requestLine = fgets($conn);
$code = '';
if ($requestLine && preg_match('#GET\s+/\?([^ ]+)#', $requestLine, $m)) {
    parse_str($m[1], $q);
    $code = (string) ($q['code'] ?? '');
    if (!empty($q['error'])) {
        fwrite(STDERR, "Google meldete einen Fehler: " . $q['error'] . "\n");
    }
}
$html = "<html><body style='font-family:sans-serif'>Fertig. Fenster schließen und zurück zum Terminal.</body></html>";
fwrite($conn, "HTTP/1.1 200 OK\r\nContent-Type: text/html; charset=utf-8\r\nContent-Length: " . strlen($html) . "\r\nConnection: close\r\n\r\n" . $html);
fclose($conn);
fclose($server);

if ($code === '') {
    fwrite(STDERR, "Kein Autorisierungscode empfangen. Abbruch.\n");
    exit(1);
}

// Exchange the authorization code for tokens.
$tokenResp = gdrivePostForm('https://oauth2.googleapis.com/token', [
    'code'          => $code,
    'client_id'     => $clientId,
    'client_secret' => $clientSecret,
    'redirect_uri'  => $redirectUri,
    'grant_type'    => 'authorization_code',
]);
$token        = json_decode($tokenResp, true) ?: [];
$refreshToken = (string) ($token['refresh_token'] ?? '');
$accessToken  = (string) ($token['access_token'] ?? '');

if ($refreshToken === '') {
    fwrite(STDERR, "Kein refresh_token erhalten. Antwort:\n$tokenResp\n");
    fwrite(STDERR, "Tipp: Zugriff der App unter myaccount.google.com/permissions entfernen und erneut ausführen.\n");
    exit(1);
}

echo "\n=== Refresh-Token erhalten ===\n\n";

// List shared drives so the operator can grab the correct drive id.
$drivesJson = gdriveGet('https://www.googleapis.com/drive/v3/drives?pageSize=100', $accessToken);
$drives     = json_decode($drivesJson, true)['drives'] ?? [];
echo "Geteilte Laufwerke (für google_shared_drive_id):\n";
if ($drives) {
    foreach ($drives as $d) {
        echo "  - " . ($d['name'] ?? '?') . "  =>  " . ($d['id'] ?? '?') . "\n";
    }
} else {
    echo "  (keine gefunden — ist info@ Mitglied eines geteilten Laufwerks?)\n";
}

echo "\nTrage in storage/config.php ein:\n\n";
echo "    'google_oauth_client_id'     => '" . $clientId . "',\n";
echo "    'google_oauth_client_secret' => '" . $clientSecret . "',\n";
echo "    'google_oauth_refresh_token' => '" . $refreshToken . "',\n";
echo "    'google_shared_drive_id'     => '<ID des Laufwerks \"Marktlauf Orga\" von oben>',\n\n";

exit(0);

/** POST application/x-www-form-urlencoded; return raw response body. */
function gdrivePostForm(string $url, array $fields): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($fields),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $resp = curl_exec($ch);
    if ($resp === false) {
        fwrite(STDERR, "cURL-Fehler: " . curl_error($ch) . "\n");
        exit(1);
    }
    curl_close($ch);
    return (string) $resp;
}

/** GET with a Bearer token; return raw response body ('' on failure). */
function gdriveGet(string $url, string $accessToken): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $accessToken],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    return (string) ($resp ?: '');
}
