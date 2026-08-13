<?php
/**
 * Dashboard audit log for file actions (intern/gdrive-storage-spec.md §2.3).
 * In Drive every app action shows as info@; the real person is the logged-in
 * dashboard user, so we record who did what here. Best-effort: an audit failure
 * must never break the actual file operation.
 */

declare(strict_types=1);

require_once __DIR__ . '/logger.php';

/**
 * @param 'upload'|'replace'|'download'|'delete'|'sync' $aktion
 * @param array{datei_id?:?int,drive_file_id?:?string,originalname?:?string,kategorie?:?string,benutzer_id?:?int} $ctx
 */
function dateiAudit(PDO $pdo, string $aktion, array $ctx = []): void
{
    try {
        $pdo->prepare('
            INSERT INTO datei_audit (datei_id, drive_file_id, originalname, kategorie, aktion, benutzer_id)
            VALUES (:datei_id, :drive_file_id, :originalname, :kategorie, :aktion, :benutzer_id)
        ')->execute([
            'datei_id'      => $ctx['datei_id']      ?? null,
            'drive_file_id' => $ctx['drive_file_id'] ?? null,
            'originalname'  => $ctx['originalname']  ?? null,
            'kategorie'     => $ctx['kategorie']     ?? null,
            'aktion'        => $aktion,
            'benutzer_id'   => $ctx['benutzer_id']   ?? null,
        ]);
    } catch (\Throwable $e) {
        logError('dateiAudit (' . $aktion . '): ' . $e->getMessage());
    }
}
