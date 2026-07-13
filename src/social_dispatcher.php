<?php
/**
 * SocialDispatcher — Andockpunkt für Auto-Posting (Phase 2).
 *
 * Phase 1: Stub. Gibt Download-Info zurück, postet nichts.
 *
 * Phase 2 (nach Renntag, nach Meta Business-Verifizierung + App-Review):
 *   - Instagram Business Graph API (instagram_content_publish)
 *   - Facebook Page Graph API
 *   - PNG muss vorher auf öffentliche HTTPS-URL hochgeladen werden
 *     (Instagram akzeptiert keinen Base64-Blob, nur eine URL)
 *   - Langlebige Tokens in storage/config.php: meta_page_access_token, meta_ig_user_id
 *
 * Interface (bleibt stabil zwischen Phase 1 und 2):
 *   dispatch(string $text, string $imageBase64): array
 *   Rückgabe: ['ok' => bool, 'message' => string, 'channels' => string[]]
 */

declare(strict_types=1);

function socialDispatch(string $text, string $imageBase64): array
{
    // Phase 1: kein Auto-Posting implementiert.
    // Text + Bild stehen zur manuellen Veröffentlichung bereit (Copy/Download auf der Seite).
    return [
        'ok'       => true,
        'message'  => 'Phase 1: manueller Dispatch. Text kopieren, PNG herunterladen, manuell posten.',
        'channels' => [],
    ];
}
