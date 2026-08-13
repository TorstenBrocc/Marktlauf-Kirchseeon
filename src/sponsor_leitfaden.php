<?php
/**
 * Sponsoren-Leitfaden — Upload & Ablage je Sponsor.
 *
 * Anders als das Logo (öffentliches Asset) enthält ein Leitfaden interne Kontaktdaten
 * und darf NICHT im Web-Root liegen. Ablage daher unter storage/files/leitfaeden/
 * (dieses Verzeichnis ist per storage/.htaccess "Require all denied" gesperrt und
 * steht in der deploy EXCLUDE-Liste, überlebt also rsync --delete). Ausgeliefert wird
 * ausschließlich über orga/api/leitfaden_download.php mit Login-Guard.
 */

declare(strict_types=1);

const SPONSOR_LEITFADEN_DIR = __DIR__ . '/../storage/files/leitfaeden';
const SPONSOR_LEITFADEN_MAX_BYTES = 10 * 1024 * 1024; // 10 MB

/** Erlaubte Endungen für einen Leitfaden (Dokumentformate, kein ausführbarer Inhalt). */
function sponsorLeitfadenAllowedExts(): array
{
    return ['pdf', 'doc', 'docx', 'odt', 'rtf', 'md', 'txt'];
}

/** Content-Type für die Auslieferung anhand der Endung (Fallback: octet-stream). */
function sponsorLeitfadenContentType(string $ext): string
{
    return [
        'pdf'  => 'application/pdf',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'odt'  => 'application/vnd.oasis.opendocument.text',
        'rtf'  => 'application/rtf',
        'md'   => 'text/markdown; charset=utf-8',
        'txt'  => 'text/plain; charset=utf-8',
    ][$ext] ?? 'application/octet-stream';
}

/** Absoluter Pfad zu einer gespeicherten Leitfaden-Datei (nur Basename, kein Traversal). */
function sponsorLeitfadenPath(string $name): string
{
    return SPONSOR_LEITFADEN_DIR . '/' . basename($name);
}

/**
 * Anzeige-/Download-Name aus dem gespeicherten Dateinamen.
 * Gespeichert wird "<sponsorId>__<originalname>"; hier fällt das ID-Präfix wieder weg,
 * damit der echte hochgeladene Dateiname sichtbar ist.
 */
function sponsorLeitfadenDisplayName(string $stored): string
{
    $base = basename($stored);
    $pos = strpos($base, '__');
    return $pos === false ? $base : substr($base, $pos + 2);
}

/**
 * Hochgeladenen Leitfaden validieren und ablegen.
 * Gibt den Dateinamen (relativ zu storage/files/leitfaeden/) zurück oder null (keine Datei).
 * Wirft RuntimeException bei ungültigem Typ/zu groß/Schreibfehler.
 */
function materializeSponsorLeitfaden(int $sponsorId, array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null; // kein Upload in diesem Request
    }
    if (($file['error'] ?? 1) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Leitfaden-Upload fehlgeschlagen.');
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('Ungültige Leitfaden-Datei.');
    }
    if ((int) ($file['size'] ?? 0) > SPONSOR_LEITFADEN_MAX_BYTES) {
        throw new RuntimeException('Leitfaden ist zu groß (max. 10 MB).');
    }

    $origName = basename((string) ($file['name'] ?? ''));
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if (!in_array($ext, sponsorLeitfadenAllowedExts(), true)) {
        throw new RuntimeException('Leitfaden-Typ nicht erlaubt. Erlaubt: PDF, DOC(X), ODT, RTF, MD, TXT.');
    }

    if (!is_dir(SPONSOR_LEITFADEN_DIR) && !@mkdir(SPONSOR_LEITFADEN_DIR, 0775, true) && !is_dir(SPONSOR_LEITFADEN_DIR)) {
        throw new RuntimeException('Leitfaden-Verzeichnis konnte nicht angelegt werden.');
    }

    // Alten Leitfaden dieses Sponsors (evtl. anderer Name/Endung) entfernen.
    deleteSponsorLeitfaden($sponsorId);

    // Originalnamen sicher machen (nur harmlose Zeichen) und mit ID-Präfix eindeutig ablegen.
    $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', $origName) ?? '';
    $safe = trim($safe, '-.');
    if ($safe === '' || strtolower(pathinfo($safe, PATHINFO_EXTENSION)) !== $ext) {
        $safe = 'leitfaden.' . $ext;
    }
    $name = $sponsorId . '__' . $safe;
    $dest = SPONSOR_LEITFADEN_DIR . '/' . $name;
    if (!@move_uploaded_file($tmp, $dest)) {
        throw new RuntimeException('Leitfaden konnte nicht gespeichert werden.');
    }
    @chmod($dest, 0640);

    return $name;
}

/** Alle Leitfaden-Dateien eines Sponsors löschen (per ID-Präfix "<id>__"). */
function deleteSponsorLeitfaden(int $sponsorId): void
{
    foreach (glob(SPONSOR_LEITFADEN_DIR . '/' . $sponsorId . '__*') ?: [] as $old) {
        @unlink($old);
    }
}
