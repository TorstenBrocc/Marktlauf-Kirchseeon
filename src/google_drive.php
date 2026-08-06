<?php
/**
 * Google Drive storage backend (intern/gdrive-storage-spec.md, Paket 2 + 6).
 *
 * Auth = OAuth as info@ (refresh token in storage/config.php, keyless — no service
 * account key). All files live in ONE shared drive ("Marktlauf Orga"); the folder
 * layout mirrors the dashboard AND the season: <shared drive>/Orga/<Jahr>/<Kategorie>
 * and <shared drive>/Helfer/<Jahr>/<Kategorie>. The (bereich, jahr, kategorie) ->
 * folder-id map is cached in table drive_kategorie_ordner (jahr=0 & kategorie='' =
 * bereich root; kategorie='' = year folder).
 *
 * No Composer / SDK: raw cURL only. Hard failures throw RuntimeException so callers
 * (api/file_*.php, plakateAnhang, dateien.php) can try/catch, logError() and fall
 * back / flash. Shared-drive query params verified against Drive API v3 docs.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/logger.php';

const DRIVE_FOLDER_MIME = 'application/vnd.google-apps.folder';

/** True only if every Google credential + the shared drive id is configured. */
function driveConfigured(): bool
{
    $c = getConfig();
    foreach (['google_oauth_client_id', 'google_oauth_client_secret', 'google_oauth_refresh_token', 'google_shared_drive_id'] as $k) {
        if (empty($c[$k])) {
            return false;
        }
    }
    return true;
}

/** The configured shared drive id. */
function driveSharedDriveId(): string
{
    return (string) (getConfig()['google_shared_drive_id'] ?? '');
}

/** Active season year for file storage (einstellungen 'dateien_jahr'; default = current year). */
function driveAktivesJahr(PDO $pdo): int
{
    try {
        $v = (int) $pdo->query("SELECT `value` FROM einstellungen WHERE `key` = 'dateien_jahr' LIMIT 1")->fetchColumn();
    } catch (PDOException $e) {
        $v = 0;
    }
    return $v >= 2000 ? $v : (int) date('Y');
}

/**
 * Return a valid access token, refreshing via the stored refresh token when the
 * cached one is expired. Cache lives in storage/cache/gdrive_token.json.
 * @throws RuntimeException on any auth failure.
 */
function driveAccessToken(): string
{
    $cacheDir  = __DIR__ . '/../storage/cache';
    $cacheFile = $cacheDir . '/gdrive_token.json';

    if (is_file($cacheFile)) {
        $cached = json_decode((string) @file_get_contents($cacheFile), true);
        if (is_array($cached) && !empty($cached['access_token']) && ($cached['expires_at'] ?? 0) > time() + 60) {
            return (string) $cached['access_token'];
        }
    }

    $c    = getConfig();
    $resp = driveHttp('POST', 'https://oauth2.googleapis.com/token', [
        'headers' => ['Content-Type: application/x-www-form-urlencoded'],
        'body'    => http_build_query([
            'client_id'     => (string) $c['google_oauth_client_id'],
            'client_secret' => (string) $c['google_oauth_client_secret'],
            'refresh_token' => (string) $c['google_oauth_refresh_token'],
            'grant_type'    => 'refresh_token',
        ]),
    ]);
    $data  = json_decode($resp['body'], true) ?: [];
    $token = (string) ($data['access_token'] ?? '');
    if ($resp['status'] < 200 || $resp['status'] >= 300 || $token === '') {
        logError('driveAccessToken: refresh fehlgeschlagen (HTTP ' . $resp['status'] . ') — ' . substr($resp['body'], 0, 300));
        throw new RuntimeException('Google-Drive-Authentifizierung fehlgeschlagen.');
    }

    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0700, true);
    }
    $expiresIn = (int) ($data['expires_in'] ?? 3600);
    @file_put_contents(
        $cacheFile,
        json_encode(['access_token' => $token, 'expires_at' => time() + $expiresIn]),
        LOCK_EX
    );
    @chmod($cacheFile, 0600);

    return $token;
}

/** Human-readable name of the bereich root folder. */
function driveBereichName(string $bereich): string
{
    return $bereich === 'helfer' ? 'Helfer' : 'Orga';
}

/** Ensure the bereich root folder (Orga/Helfer) under the shared drive; cache (bereich,0,''). */
function driveEnsureBereichFolder(PDO $pdo, string $bereich): string
{
    return driveEnsureFolder($pdo, $bereich, 0, '', driveBereichName($bereich), driveSharedDriveId());
}

/** Ensure the year folder (Orga/<Jahr>) under the bereich root; cache (bereich,jahr,''). */
function driveEnsureJahrFolder(PDO $pdo, string $bereich, int $jahr): string
{
    $parentId = driveEnsureBereichFolder($pdo, $bereich);
    return driveEnsureFolder($pdo, $bereich, $jahr, '', (string) $jahr, $parentId);
}

/** Ensure the category folder (Orga/<Jahr>/<Kategorie>) and return its id. */
function driveEnsureCategoryFolder(PDO $pdo, string $bereich, int $jahr, string $kategorie): string
{
    require_once __DIR__ . '/../orga/_dateien_kategorien.php';
    $parentId = driveEnsureJahrFolder($pdo, $bereich, $jahr);
    return driveEnsureFolder($pdo, $bereich, $jahr, $kategorie, dateiKategorieLabel($kategorie), $parentId);
}

/** Cache-backed find-or-create for a single folder; returns its Drive id. */
function driveEnsureFolder(PDO $pdo, string $bereich, int $jahr, string $kategorie, string $name, string $parentId): string
{
    $stmt = $pdo->prepare('SELECT drive_folder_id FROM drive_kategorie_ordner WHERE bereich = ? AND jahr = ? AND kategorie = ?');
    $stmt->execute([$bereich, $jahr, $kategorie]);
    $cached = $stmt->fetchColumn();
    if ($cached !== false && $cached !== null && $cached !== '') {
        return (string) $cached;
    }

    $folderId = driveFindFolder($name, $parentId) ?? driveCreateFolder($name, $parentId);

    $pdo->prepare('
        INSERT INTO drive_kategorie_ordner (bereich, jahr, kategorie, drive_folder_id)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE drive_folder_id = VALUES(drive_folder_id)
    ')->execute([$bereich, $jahr, $kategorie, $folderId]);

    return $folderId;
}

/** Find a non-trashed folder by exact name under a parent; null if none. */
function driveFindFolder(string $name, string $parentId): ?string
{
    $q = sprintf(
        "name = '%s' and mimeType = '%s' and '%s' in parents and trashed = false",
        driveEscapeQ($name),
        DRIVE_FOLDER_MIME,
        driveEscapeQ($parentId)
    );
    $data = driveApiGet('https://www.googleapis.com/drive/v3/files?' . http_build_query([
        'q'                         => $q,
        'corpora'                   => 'drive',
        'driveId'                   => driveSharedDriveId(),
        'includeItemsFromAllDrives' => 'true',
        'supportsAllDrives'         => 'true',
        'fields'                    => 'files(id,name)',
        'pageSize'                  => 1,
    ]));
    return $data['files'][0]['id'] ?? null;
}

/** Create a folder under a parent; returns its Drive id. */
function driveCreateFolder(string $name, string $parentId): string
{
    $data = driveApiSend(
        'POST',
        'https://www.googleapis.com/drive/v3/files?' . http_build_query(['supportsAllDrives' => 'true', 'fields' => 'id']),
        json_encode(['name' => $name, 'mimeType' => DRIVE_FOLDER_MIME, 'parents' => [$parentId]], JSON_UNESCAPED_UNICODE),
        ['Content-Type: application/json']
    );
    $id = (string) ($data['id'] ?? '');
    if ($id === '') {
        throw new RuntimeException('Drive-Ordner konnte nicht angelegt werden: ' . $name);
    }
    return $id;
}

/**
 * List non-trashed files in a (bereich, jahr, kategorie) folder.
 * @return array<int,array{id:string,name:string,mimeType:string,size:int,modifiedTime:string}>
 */
function driveList(PDO $pdo, string $bereich, int $jahr, string $kategorie): array
{
    $folderId  = driveEnsureCategoryFolder($pdo, $bereich, $jahr, $kategorie);
    $out       = [];
    $pageToken = '';
    do {
        $params = [
            'q'                         => sprintf("'%s' in parents and mimeType != '%s' and trashed = false", driveEscapeQ($folderId), DRIVE_FOLDER_MIME),
            'corpora'                   => 'drive',
            'driveId'                   => driveSharedDriveId(),
            'includeItemsFromAllDrives' => 'true',
            'supportsAllDrives'         => 'true',
            'fields'                    => 'nextPageToken,files(id,name,mimeType,size,modifiedTime)',
            'pageSize'                  => 100,
        ];
        if ($pageToken !== '') {
            $params['pageToken'] = $pageToken;
        }
        $data = driveApiGet('https://www.googleapis.com/drive/v3/files?' . http_build_query($params));
        foreach (($data['files'] ?? []) as $f) {
            $out[] = [
                'id'           => (string) ($f['id'] ?? ''),
                'name'         => (string) ($f['name'] ?? ''),
                'mimeType'     => (string) ($f['mimeType'] ?? ''),
                'size'         => (int) ($f['size'] ?? 0),
                'modifiedTime' => (string) ($f['modifiedTime'] ?? ''),
            ];
        }
        $pageToken = (string) ($data['nextPageToken'] ?? '');
    } while ($pageToken !== '');

    return $out;
}

/**
 * Upload a local file into the (bereich, jahr, kategorie) folder via multipart upload.
 * Returns the new Drive file id.
 */
function driveUpload(PDO $pdo, string $bereich, int $jahr, string $kategorie, string $tmpPath, string $name, string $mimeType): string
{
    $folderId = driveEnsureCategoryFolder($pdo, $bereich, $jahr, $kategorie);
    $content  = (string) file_get_contents($tmpPath);

    $boundary = 'mlboundary' . bin2hex(random_bytes(8));
    $meta     = json_encode(['name' => $name, 'parents' => [$folderId]], JSON_UNESCAPED_UNICODE);
    $body     = "--$boundary\r\n"
        . "Content-Type: application/json; charset=UTF-8\r\n\r\n"
        . $meta . "\r\n"
        . "--$boundary\r\n"
        . "Content-Type: " . $mimeType . "\r\n\r\n"
        . $content . "\r\n"
        . "--$boundary--";

    $data = driveApiSend(
        'POST',
        'https://www.googleapis.com/upload/drive/v3/files?' . http_build_query(['uploadType' => 'multipart', 'supportsAllDrives' => 'true', 'fields' => 'id']),
        $body,
        ['Content-Type: multipart/related; boundary=' . $boundary]
    );
    $id = (string) ($data['id'] ?? '');
    if ($id === '') {
        throw new RuntimeException('Drive-Upload fehlgeschlagen: ' . $name);
    }
    return $id;
}

/** Download raw bytes of a Drive file. @throws RuntimeException */
function driveDownload(string $fileId): string
{
    $resp = driveHttp('GET', 'https://www.googleapis.com/drive/v3/files/' . rawurlencode($fileId) . '?' . http_build_query(['alt' => 'media', 'supportsAllDrives' => 'true']), [
        'headers' => ['Authorization: Bearer ' . driveAccessToken()],
    ]);
    if ($resp['status'] < 200 || $resp['status'] >= 300) {
        logError('driveDownload: HTTP ' . $resp['status'] . ' für ' . $fileId . ' — ' . substr($resp['body'], 0, 200));
        throw new RuntimeException('Drive-Download fehlgeschlagen.');
    }
    return $resp['body'];
}

/** Permanently delete a Drive file. @throws RuntimeException */
function driveDelete(string $fileId): void
{
    $resp = driveHttp('DELETE', 'https://www.googleapis.com/drive/v3/files/' . rawurlencode($fileId) . '?' . http_build_query(['supportsAllDrives' => 'true']), [
        'headers' => ['Authorization: Bearer ' . driveAccessToken()],
    ]);
    // 204 No Content on success; 404 = already gone (treat as success).
    if ($resp['status'] !== 204 && $resp['status'] !== 404) {
        logError('driveDelete: HTTP ' . $resp['status'] . ' für ' . $fileId . ' — ' . substr($resp['body'], 0, 200));
        throw new RuntimeException('Drive-Löschung fehlgeschlagen.');
    }
}

/**
 * Reconcile the dashboard index (table dateien) for one bereich + jahr with the shared
 * drive (Modell A): files added or renamed directly in Drive appear in the index,
 * index rows whose Drive file vanished are removed. Only provider='drive' rows are
 * touched — local files are never affected. Throttled to once per 120s per bereich+jahr
 * unless $force. A failed folder listing skips that category (never deletes on error).
 */
function driveReconcile(PDO $pdo, string $bereich, int $jahr, bool $force = false): void
{
    if (!driveConfigured()) {
        return;
    }
    $cacheDir = __DIR__ . '/../storage/cache';
    $marker   = $cacheDir . '/gdrive_reconcile_' . preg_replace('/[^a-z]/', '', $bereich) . '_' . $jahr . '.ts';
    if (!$force && is_file($marker) && (time() - (int) @filemtime($marker)) < 120) {
        return;
    }
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0700, true);
    }
    @touch($marker);

    require_once __DIR__ . '/../orga/_dateien_kategorien.php';
    foreach (array_keys(dateiKategorien()) as $kat) {
        try {
            $driveFiles = driveList($pdo, $bereich, $jahr, $kat);
        } catch (RuntimeException $e) {
            logError('driveReconcile list (' . $bereich . '/' . $jahr . '/' . $kat . '): ' . $e->getMessage());
            continue; // never delete on a failed listing
        }
        $seen = [];
        foreach ($driveFiles as $f) {
            if ($f['id'] === '') {
                continue;
            }
            $seen[] = $f['id'];
            $mime = $f['mimeType'] !== '' ? $f['mimeType'] : 'application/octet-stream';
            $sel  = $pdo->prepare('SELECT id FROM dateien WHERE drive_file_id = ?');
            $sel->execute([$f['id']]);
            $existingId = $sel->fetchColumn();
            if ($existingId === false) {
                // Directly-in-Drive file: index it (no dashboard uploader -> NULL).
                $pdo->prepare('
                    INSERT INTO dateien (bereich, kategorie, jahr, dateiname, drive_file_id, provider, originalname, mimetype, groesse, hochgeladen_von, created_at)
                    VALUES (?, ?, ?, ?, ?, "drive", ?, ?, ?, NULL, NOW())
                ')->execute([$bereich, $kat, $jahr, $f['name'], $f['id'], $f['name'], $mime, $f['size']]);
            } else {
                $pdo->prepare('UPDATE dateien SET kategorie = ?, jahr = ?, originalname = ?, mimetype = ?, groesse = ? WHERE id = ?')
                    ->execute([$kat, $jahr, $f['name'], $mime, $f['size'], (int) $existingId]);
            }
        }
        // Drop drive-index rows for this bereich+jahr+category whose Drive file is gone.
        if ($seen === []) {
            $pdo->prepare("DELETE FROM dateien WHERE bereich = ? AND jahr = ? AND kategorie = ? AND provider = 'drive'")
                ->execute([$bereich, $jahr, $kat]);
        } else {
            $ph = implode(',', array_fill(0, count($seen), '?'));
            $pdo->prepare("DELETE FROM dateien WHERE bereich = ? AND jahr = ? AND kategorie = ? AND provider = 'drive' AND drive_file_id NOT IN ($ph)")
                ->execute(array_merge([$bereich, $jahr, $kat], $seen));
        }
    }
}

// --- Internal helpers -------------------------------------------------------

/** GET a Drive JSON endpoint (Bearer added); returns decoded array. @throws */
function driveApiGet(string $url): array
{
    $resp = driveHttp('GET', $url, ['headers' => ['Authorization: Bearer ' . driveAccessToken()]]);
    return driveDecodeOrThrow($resp, $url);
}

/** Send a JSON/body request with Bearer + extra headers; returns decoded array. @throws */
function driveApiSend(string $method, string $url, string $body, array $headers): array
{
    $headers[] = 'Authorization: Bearer ' . driveAccessToken();
    $resp      = driveHttp($method, $url, ['headers' => $headers, 'body' => $body]);
    return driveDecodeOrThrow($resp, $url);
}

/** @param array{status:int,body:string} $resp */
function driveDecodeOrThrow(array $resp, string $url): array
{
    if ($resp['status'] < 200 || $resp['status'] >= 300) {
        logError('Drive-API-Fehler (HTTP ' . $resp['status'] . ') ' . $url . ' — ' . substr($resp['body'], 0, 300));
        throw new RuntimeException('Google-Drive-Anfrage fehlgeschlagen (HTTP ' . $resp['status'] . ').');
    }
    $data = json_decode($resp['body'], true);
    return is_array($data) ? $data : [];
}

/**
 * Minimal cURL wrapper.
 * @param array{headers?:array<int,string>,body?:string} $opts
 * @return array{status:int,body:string}
 * @throws RuntimeException on transport failure.
 */
function driveHttp(string $method, string $url, array $opts = []): array
{
    $ch = curl_init($url);
    $curlOpts = [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_HTTPHEADER     => $opts['headers'] ?? [],
    ];
    if (isset($opts['body'])) {
        $curlOpts[CURLOPT_POSTFIELDS] = $opts['body'];
    }
    curl_setopt_array($ch, $curlOpts);
    $body   = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    if ($body === false) {
        $err = curl_error($ch);
        curl_close($ch);
        logError('driveHttp: cURL-Transportfehler ' . $method . ' ' . $url . ' — ' . $err);
        throw new RuntimeException('Netzwerkfehler bei Google Drive.');
    }
    curl_close($ch);
    return ['status' => $status, 'body' => (string) $body];
}

/** Escape a value for use inside a Drive q-parameter string literal. */
function driveEscapeQ(string $v): string
{
    return str_replace(['\\', "'"], ['\\\\', "\\'"], $v);
}
