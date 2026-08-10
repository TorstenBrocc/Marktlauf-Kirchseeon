<?php
/**
 * Brevo-Client — Newsletter-Kampagnen über die Brevo REST-API v3.
 *
 * Kein Composer/SDK: roher PHP-cURL (Muster wie src/google_drive.php, src/llm_client.php).
 * Auth = Header 'api-key' (NICHT Bearer). Shape verifiziert 2026-08-07 gegen
 * developers.brevo.com/reference/createemailcampaign:
 *   POST https://api.brevo.com/v3/emailCampaigns
 *   Header: api-key: <key>, Content-Type: application/json
 *   Body:   {name, subject, sender:{name,email}, htmlContent, recipients:{listIds:[...]}}
 *   scheduledAt WEGLASSEN => Kampagne wird als ENTWURF angelegt (Default).
 *   Antwort: 201 {"id": <int>}
 *
 * Versendet NICHTS: legt nur den Entwurf an. Prüfen + Senden macht die Orga in Brevo
 * (dort greift auch das 300-Mails/Tag-Limit; SPF/DKIM sind DNS-seitig gesetzt). Fehlt die
 * Config oder scheitert der Call, kommt fallback=true zurück — das Dashboard zeigt dann
 * den Copy-Weg (Fallback-Muster wie socialDispatch()). Kein Silent Fail: alles via logError().
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/logger.php';

/** True nur, wenn API-Key, Absender-Mail und eine gültige Listen-ID gesetzt sind. */
function brevoConfigured(): bool
{
    $c = getConfig();
    return trim((string) ($c['brevo_api_key'] ?? '')) !== ''
        && trim((string) ($c['brevo_sender_email'] ?? '')) !== ''
        && (int) ($c['brevo_list_id'] ?? 0) > 0;
}

/**
 * Legt eine E-Mail-Kampagne als ENTWURF an (kein Versand).
 *
 * @param string $name    interner Kampagnenname (Brevo-Backend)
 * @param string $subject Betreffzeile (Posteingang)
 * @param string $html    vollständiges E-Mail-HTML
 * @return array{ok:bool,id?:int,message:string,fallback?:bool}
 */
function brevoCreateCampaignDraft(string $name, string $subject, string $html): array
{
    if (!brevoConfigured()) {
        return [
            'ok'       => false,
            'fallback' => true,
            'message'  => 'Brevo ist nicht konfiguriert — bitte HTML kopieren und in Brevo manuell als Kampagne anlegen.',
        ];
    }

    $c       = getConfig();
    $payload = json_encode([
        'name'        => $name,
        'subject'     => $subject,
        'sender'      => [
            'name'  => (string) ($c['brevo_sender_name'] ?? 'ATSV Kirchseeon Marktlauf'),
            'email' => (string) $c['brevo_sender_email'],
        ],
        'htmlContent' => $html,
        'recipients'  => ['listIds' => [(int) $c['brevo_list_id']]],
        // KEIN scheduledAt => Brevo legt die Kampagne als Entwurf an.
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    try {
        $ch = curl_init('https://api.brevo.com/v3/emailCampaigns');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                'api-key: ' . trim((string) $c['brevo_api_key']),
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 8,
        ]);
        $raw      = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $curlErr !== '') {
            logError('brevoCreateCampaignDraft: cURL-Fehler: ' . $curlErr);
            return ['ok' => false, 'fallback' => true, 'message' => 'Brevo nicht erreichbar — bitte HTML kopieren und manuell in Brevo anlegen.'];
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            logError('brevoCreateCampaignDraft: HTTP ' . $httpCode . ' — ' . substr((string) $raw, 0, 300));
            return ['ok' => false, 'fallback' => true, 'message' => 'Brevo meldete einen Fehler (HTTP ' . $httpCode . ') — bitte HTML kopieren und manuell in Brevo anlegen.'];
        }

        $data = json_decode((string) $raw, true);
        $id   = (int) (is_array($data) ? ($data['id'] ?? 0) : 0);
        if ($id <= 0) {
            logError('brevoCreateCampaignDraft: keine Kampagnen-ID in Antwort — ' . substr((string) $raw, 0, 300));
            return ['ok' => false, 'fallback' => true, 'message' => 'Brevo-Antwort ohne Kampagnen-ID — bitte in Brevo prüfen.'];
        }

        return [
            'ok'      => true,
            'id'      => $id,
            'message' => 'Entwurf in Brevo angelegt (Kampagne #' . $id . '). Jetzt in Brevo prüfen und senden.',
        ];
    } catch (\Throwable $e) {
        logError('brevoCreateCampaignDraft: Ausnahme: ' . $e->getMessage());
        return ['ok' => false, 'fallback' => true, 'message' => 'Brevo-Aufruf fehlgeschlagen — bitte HTML kopieren und manuell in Brevo anlegen.'];
    }
}
