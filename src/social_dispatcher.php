<?php
/**
 * SocialDispatcher — Auto-Posting an Instagram/Facebook über Make.com.
 *
 * Datenfluss: Dashboard rendert die PNG-Grafik (Modul 3) → legt sie über
 * orga/api/social_dispatch.php unter einer öffentlichen HTTPS-URL ab →
 * socialDispatch() schickt {text, image_url, channels, secret} per Webhook an
 * ein Make.com-Szenario, das an IG/FB postet.
 *
 * Fallback (HARTE ANFORDERUNG): Ist kein Webhook konfiguriert ODER schlägt der
 * Aufruf fehl, gibt die Funktion 'fallback' => true zurück. Das Dashboard zeigt
 * dann den manuellen Weg (Text kopieren, PNG herunterladen) — kein Silent Fail,
 * jeder Fehler wird über logError() protokolliert.
 *
 * Instagram akzeptiert kein Base64 — deshalb wird eine öffentliche Bild-URL
 * übergeben, nicht der Blob (Ablage: assets/social/, siehe social_dispatch.php).
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/logger.php';

/**
 * @param string   $text     Post-Text (Caption)
 * @param string   $imageUrl Öffentliche HTTPS-URL des PNG (leer erlaubt = reiner Text)
 * @param string[] $channels z.B. ['instagram','facebook']
 * @return array{ok:bool,message:string,channels:string[],fallback?:bool}
 */
function socialDispatch(string $text, string $imageUrl, array $channels): array
{
    $config     = getConfig();
    $webhookUrl = trim((string) ($config['make_webhook_url'] ?? ''));
    $secret     = (string) ($config['make_webhook_secret'] ?? '');

    if ($webhookUrl === '') {
        // Kein Auto-Posting eingerichtet → manueller Weg.
        return [
            'ok'       => false,
            'fallback' => true,
            'message'  => 'Kein Make.com-Webhook konfiguriert — bitte manuell posten: Text kopieren, PNG herunterladen.',
            'channels' => [],
        ];
    }

    $payload = json_encode([
        'text'      => $text,
        'image_url' => $imageUrl,
        'channels'  => array_values($channels),
        'secret'    => $secret,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    try {
        $ch = curl_init($webhookUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
        ]);
        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($response === false || $curlErr !== '') {
            logError('socialDispatch: cURL-Fehler: ' . $curlErr);
            return [
                'ok'       => false,
                'fallback' => true,
                'message'  => 'Auto-Posting nicht erreichbar — bitte manuell posten (Text kopieren, PNG herunterladen).',
                'channels' => [],
            ];
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            logError('socialDispatch: Webhook antwortete HTTP ' . $httpCode . ' — ' . substr((string) $response, 0, 300));
            return [
                'ok'       => false,
                'fallback' => true,
                'message'  => 'Make.com meldete einen Fehler (HTTP ' . $httpCode . ') — bitte manuell posten.',
                'channels' => [],
            ];
        }

        return [
            'ok'       => true,
            'message'  => 'An Make.com übergeben. Instagram/Facebook posten in Kürze.',
            'channels' => array_values($channels),
        ];
    } catch (\Throwable $e) {
        logError('socialDispatch: Ausnahme: ' . $e->getMessage());
        return [
            'ok'       => false,
            'fallback' => true,
            'message'  => 'Auto-Posting fehlgeschlagen — bitte manuell posten (Text kopieren, PNG herunterladen).',
            'channels' => [],
        ];
    }
}
