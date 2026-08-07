<?php
/**
 * Sponsoren-Logo-Rotation — Materialisierung & Feed.
 * Spec: intern/sponsoren-logo-rotation-spec.md
 *
 * Logos werden als web-optimierte, statische Assets in ein deploy-ausgeschlossenes
 * öffentliches Verzeichnis geschrieben (assets/sponsoren-live/), der Rotations-Feed
 * nach data/sponsoren.json. Beide Pfade stehen in deploy.yml EXCLUDE, damit
 * `rsync --delete` sie nicht bei jedem Deploy löscht.
 */

declare(strict_types=1);

require_once __DIR__ . '/google_drive.php';

const SPONSOR_LOGO_DIR  = __DIR__ . '/../assets/sponsoren-live';
const SPONSOR_FEED_PATH = __DIR__ . '/../data/sponsoren.json';
const SPONSOR_LOGO_MAX_EDGE = 800; // Rotation zeigt ~231px; 800 gibt Retina-Reserve bei kleiner Datei

/**
 * Firmennamen zu einem URL-tauglichen Slug (nur Lesbarkeit; Eindeutigkeit kommt aus der Sponsor-ID).
 */
function sponsorSlug(string $firma): string
{
    $s = mb_strtolower(trim($firma));
    $s = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $s);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    $s = trim($s, '-');
    return $s !== '' ? $s : 'sponsor';
}

/**
 * Freitext-Website (z. B. "beispiel.de") zu einer verlinkbaren URL normalisieren.
 */
function sponsorPublicUrl(?string $raw): ?string
{
    $u = trim((string) $raw);
    if ($u === '') {
        return null;
    }
    if (!preg_match('#^https?://#i', $u)) {
        $u = 'https://' . $u;
    }
    return $u;
}

/**
 * Hochgeladenes Logo validieren, web-optimieren und als statisches Asset ablegen.
 * Gibt den Dateinamen (relativ zu assets/sponsoren-live/) zurück oder null (keine Datei).
 * Wirft RuntimeException bei ungültigem Typ/zu groß/Schreibfehler.
 */
function materializeSponsorLogo(int $sponsorId, string $firma, array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null; // kein Upload in diesem Request
    }
    if (($file['error'] ?? 1) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Logo-Upload fehlgeschlagen.');
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('Ungültige Logo-Datei.');
    }
    if ((int) ($file['size'] ?? 0) > 5 * 1024 * 1024) {
        throw new RuntimeException('Logo ist zu groß (max. 5 MB).');
    }
    return storeSponsorLogo($sponsorId, $firma, $tmp, true, (string) ($file['name'] ?? ''));
}

/**
 * Wie materializeSponsorLogo, aber aus einer vorhandenen Datei (z. B. Bestands-Asset beim Seed).
 */
function importSponsorLogoFromPath(int $sponsorId, string $firma, string $srcPath): ?string
{
    if (!is_file($srcPath)) {
        throw new RuntimeException('Quell-Logo nicht gefunden: ' . $srcPath);
    }
    return storeSponsorLogo($sponsorId, $firma, $srcPath, false, $srcPath);
}

/**
 * Gemeinsamer Kern: Logo validieren, ablegen (Move bei Upload, sonst Copy), web-optimieren.
 * $nameHint dient nur der SVG-Erkennung, wenn finfo den MIME nicht eindeutig als image/svg+xml meldet.
 */
function storeSponsorLogo(int $sponsorId, string $firma, string $srcPath, bool $wasUploaded, string $nameHint = ''): ?string
{
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($srcPath);
    $extByMime = [
        'image/png'     => 'png',
        'image/jpeg'    => 'jpg',
        'image/svg+xml' => 'svg',
    ];
    $ext = $extByMime[$mime] ?? null;
    if ($ext === null) {
        // finfo meldet SVG je nach System als text/xml o. ä. — per Namens-Hint + Inhalt absichern.
        $hint = $nameHint !== '' ? $nameHint : $srcPath;
        if (preg_match('/\.svg$/i', $hint) && strpos((string) @file_get_contents($srcPath), '<svg') !== false) {
            $mime = 'image/svg+xml';
            $ext  = 'svg';
        } else {
            throw new RuntimeException('Logo-Typ nicht erlaubt. Erlaubt: PNG, JPG, SVG.');
        }
    }

    if (!is_dir(SPONSOR_LOGO_DIR) && !@mkdir(SPONSOR_LOGO_DIR, 0775, true) && !is_dir(SPONSOR_LOGO_DIR)) {
        throw new RuntimeException('Logo-Verzeichnis konnte nicht angelegt werden.');
    }

    // Alte Assets dieses Sponsors (evtl. andere Endung/Slug) entfernen.
    deleteSponsorLogo($sponsorId);

    $name = 'sponsor-' . $sponsorId . '-' . sponsorSlug($firma) . '.' . $ext;
    $dest = SPONSOR_LOGO_DIR . '/' . $name;
    $ok = $wasUploaded ? @move_uploaded_file($srcPath, $dest) : @copy($srcPath, $dest);
    if (!$ok) {
        throw new RuntimeException('Logo konnte nicht gespeichert werden.');
    }
    @chmod($dest, 0644);

    if ($mime === 'image/png' || $mime === 'image/jpeg') {
        sponsorLogoDownscale($dest, $mime, SPONSOR_LOGO_MAX_EDGE);
    }

    return $name;
}

/**
 * Alle Logo-Assets eines Sponsors löschen (per ID-Präfix, endungs-/slug-unabhängig).
 */
function deleteSponsorLogo(int $sponsorId): void
{
    foreach (glob(SPONSOR_LOGO_DIR . '/sponsor-' . $sponsorId . '-*') ?: [] as $old) {
        @unlink($old);
    }
}

/**
 * Rotations-Feed neu schreiben: alle aktiven Sponsoren mit Logo, nach Priorität.
 * Atomar (temp + rename). Fehler werfen — Aufrufer fängt und loggt.
 */
function writeSponsorenFeed(PDO $pdo): void
{
    $sql = "SELECT firma, website, logo_web_asset, prioritaet
              FROM sponsors
             WHERE in_rotation = 1
               AND logo_web_asset IS NOT NULL AND logo_web_asset <> ''
               AND kein_kontakt = 0
          ORDER BY (prioritaet IS NULL), prioritaet ASC, firma ASC";
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'name' => (string) $r['firma'],
            'logo' => 'assets/sponsoren-live/' . $r['logo_web_asset'],
            'url'  => sponsorPublicUrl($r['website']),
        ];
    }

    $dir = dirname(SPONSOR_FEED_PATH);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Feed-Verzeichnis fehlt.');
    }
    $json = json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) {
        throw new RuntimeException('Feed-JSON konnte nicht erzeugt werden.');
    }
    $tmp = SPONSOR_FEED_PATH . '.tmp';
    if (file_put_contents($tmp, $json) === false || !@rename($tmp, SPONSOR_FEED_PATH)) {
        @unlink($tmp);
        throw new RuntimeException('Feed konnte nicht geschrieben werden.');
    }
    @chmod(SPONSOR_FEED_PATH, 0644);
}

/**
 * Bild in-place auf maximale Kantenlänge verkleinern (PNG-Alpha bleibt erhalten).
 * Ohne GD oder bei Fehlern bleibt die Datei unverändert. Eigenständig gehalten,
 * weil das Pendant in orga/api/file_upload.php dort lokal (nicht includebar) lebt.
 */
function sponsorLogoDownscale(string $path, string $mime, int $maxEdge): void
{
    if (!function_exists('imagecreatetruecolor')) {
        return;
    }
    $info = @getimagesize($path);
    if ($info === false) {
        return;
    }
    [$w, $h] = $info;
    if ($w <= $maxEdge && $h <= $maxEdge) {
        return;
    }
    $ratio = min($maxEdge / $w, $maxEdge / $h);
    $nw = max(1, (int) round($w * $ratio));
    $nh = max(1, (int) round($h * $ratio));

    $src = $mime === 'image/png' ? @imagecreatefrompng($path) : @imagecreatefromjpeg($path);
    if (!$src) {
        return;
    }
    $dst = imagecreatetruecolor($nw, $nh);
    if ($mime === 'image/png') {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
    }
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    if ($mime === 'image/png') {
        imagepng($dst, $path, 6);
    } else {
        imagejpeg($dst, $path, 85);
    }
}

/* ---------------------------------------------------------------------------
 * WP-3: Drive-Ordner je Sponsor (Ablagebecken) + Logo-Auswahl aus dem Ordner
 * ------------------------------------------------------------------------- */

const SPONSOR_DRIVE_PARENT = 'Sponsoren'; // Ordner unter der Orga-Wurzel

/**
 * Eltern-Ordner „Sponsoren" unter der Orga-Wurzel finden/anlegen. Gibt Drive-ID zurück.
 */
function sponsorDriveRootId(PDO $pdo): string
{
    $orgaRoot = driveRootFolderId($pdo, 'orga');
    return driveFindFolder(SPONSOR_DRIVE_PARENT, $orgaRoot)
        ?? driveCreateFolder(SPONSOR_DRIVE_PARENT, $orgaRoot);
}

/**
 * Sponsor-Ordner sicherstellen (find-or-create) und drive_folder_id persistieren.
 * Gibt die Drive-Ordner-ID zurück. Wirft bei Drive-Fehlern (Aufrufer fängt).
 */
function sponsorEnsureDriveFolder(PDO $pdo, int $sponsorId, string $firma): string
{
    if (!driveConfigured()) {
        throw new RuntimeException('Google Drive ist nicht konfiguriert.');
    }
    // Bereits verknüpft und noch gültig? Dann wiederverwenden.
    $cur = $pdo->prepare('SELECT drive_folder_id FROM sponsors WHERE id = :id');
    $cur->execute(['id' => $sponsorId]);
    $existing = (string) ($cur->fetchColumn() ?: '');
    if ($existing !== '' && driveInSharedDrive($existing)) {
        return $existing;
    }

    $name = trim($firma) !== '' ? trim($firma) : ('Sponsor ' . $sponsorId);
    $root = sponsorDriveRootId($pdo);
    $folderId = driveFindFolder($name, $root) ?? driveCreateFolder($name, $root);

    $pdo->prepare('UPDATE sponsors SET drive_folder_id = :f WHERE id = :id')
        ->execute(['f' => $folderId, 'id' => $sponsorId]);

    return $folderId;
}

/**
 * Bild-Dateien (PNG/JPG/SVG) im Sponsor-Drive-Ordner auflisten — für die Auswahl-UI.
 * @return array<int,array{id:string,name:string,mimeType:string}>
 */
function sponsorDriveFolderImages(string $folderId): array
{
    $allowed = ['image/png', 'image/jpeg', 'image/svg+xml'];
    $out = [];
    foreach (driveListChildren($folderId) as $f) {
        if ($f['isFolder']) {
            continue;
        }
        $mime = $f['mimeType'];
        $isImg = in_array($mime, $allowed, true) || preg_match('/\.(png|jpe?g|svg)$/i', $f['name']);
        if ($isImg) {
            $out[] = ['id' => $f['id'], 'name' => $f['name'], 'mimeType' => $mime];
        }
    }
    return $out;
}

/**
 * Eine im Drive-Ordner gewählte Datei als web-optimiertes Logo materialisieren.
 * Gibt den Asset-Dateinamen zurück. Wirft bei Fehlern (Aufrufer fängt).
 */
function materializeSponsorLogoFromDrive(int $sponsorId, string $firma, string $driveFileId): ?string
{
    if ($driveFileId === '') {
        return null;
    }
    $meta = driveFileMeta($driveFileId);
    if ($meta === null || (string) ($meta['driveId'] ?? '') !== driveSharedDriveId()) {
        throw new RuntimeException('Gewählte Drive-Datei ist ungültig.');
    }
    $bytes = driveDownload($driveFileId);
    $tmp = tempnam(sys_get_temp_dir(), 'splogo_');
    if ($tmp === false || file_put_contents($tmp, $bytes) === false) {
        throw new RuntimeException('Drive-Logo konnte nicht zwischengespeichert werden.');
    }
    try {
        return storeSponsorLogo($sponsorId, $firma, $tmp, false, (string) ($meta['name'] ?? ''));
    } finally {
        @unlink($tmp);
    }
}
