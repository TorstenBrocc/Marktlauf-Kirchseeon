<?php
/**
 * SocialDispatcher — Auto-Posting an Instagram/Facebook über Make.com.
 *
 * Datenfluss: Dashboard rendert die PNG-Grafik (Modul 3) → legt sie über
 * orga/api/post_dispatch.php unter einer öffentlichen HTTPS-URL ab →
 * socialDispatch() schickt {text, image_url, channels, secret} per Webhook an
 * ein Make.com-Szenario, das an IG/FB postet.
 *
 * Fallback (HARTE ANFORDERUNG): Ist kein Webhook konfiguriert ODER schlägt der
 * Aufruf fehl, gibt die Funktion 'fallback' => true zurück. Das Dashboard zeigt
 * dann den manuellen Weg (Text kopieren, PNG herunterladen) — kein Silent Fail,
 * jeder Fehler wird über logError() protokolliert.
 *
 * Instagram akzeptiert kein Base64 — deshalb wird eine öffentliche Bild-URL
 * übergeben, nicht der Blob (Ablage: assets/social/, siehe post_dispatch.php).
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/logger.php';

/**
 * @param string   $text     Post-Text (Caption)
 * @param string   $imageUrl Öffentliche HTTPS-URL des PNG (leer erlaubt = reiner Text)
 * @param string[] $channels z.B. ['instagram','facebook']
 * @param int      $postId   Post-ID — Make.com reicht sie im Erfolgs-Callback zurueck
 *                           (orga/api/post_status_callback.php), damit der Permalink dem
 *                           richtigen Post zugeordnet wird.
 * @param string   $firstComment Erster Kommentar (Link+Hashtags). Make setzt ihn nach dem Post
 *                           als ersten Kommentar; leer = Make ueberspringt den Schritt.
 * @param ?int     $scheduledTime Unix-Zeit (Epoch, Sekunden) fuer die geplante Veroeffentlichung
 *                           (Spec §4a/S3): gesetzt -> Make terminiert FB nativ via
 *                           scheduled_publish_time und ueberspringt IG (Handoff). null = sofort
 *                           (manueller Klick / Catch-up).
 * @return array{ok:bool,message:string,channels:string[],fallback?:bool}
 */
function socialDispatch(string $text, string $imageUrl, array $channels, int $postId = 0, string $firstComment = '', ?int $scheduledTime = null): array
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

    $daten = [
        'post_id'   => $postId,
        'text'      => $text,
        'image_url' => $imageUrl,
        'channels'  => array_values($channels),
        'secret'    => $secret,
    ];
    // Ersten Kommentar nur mitschicken, wenn vorhanden. Der make-Filter vor dem Kommentar-Modul
    // prueft `first_comment` mit "Exists" — ein leerer String koennte dort als vorhanden gelten und
    // den Kommentar-Schritt auf einen (bei terminierten Posts) noch UNveroeffentlichten Beitrag
    // laufen lassen -> Meta-Fehler. Feld ganz weglassen => "Exists" ist garantiert false => der
    // Kommentar-Schritt wird sauber uebersprungen (Spec §4c). Beim terminierten Versand wandert der
    // CTA+Link ohnehin in die Caption (versendePost).
    if ($firstComment !== '') {
        $daten['first_comment'] = $firstComment;
    }
    // Terminierter Versand (Spec §4a): Zielzeit als ISO 8601 mit Berlin-Offset mitgeben. Make parst
    // das nativ in sein FB-Pages-Feld "Publish date" (Zeitzone Europe/Berlin) -> Meta terminiert
    // exakt; im Webhook-Log ist es menschenlesbar. Nur anhaengen, wenn gesetzt — sonst bleibt der
    // Payload sofort-kompatibel (leer/fehlend = Make published sofort).
    if ($scheduledTime !== null) {
        $daten['scheduled_time'] = (new DateTimeImmutable('@' . $scheduledTime))
            ->setTimezone(new DateTimeZone('Europe/Berlin'))
            ->format('c');
    }
    $payload = json_encode($daten, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // make.com-Optimierung #4 (Haertung): HMAC-Signatur ueber den exakten Body mitschicken
    // (Header `X-Signature: sha256=…`). Das `secret` im Body bleibt zusaetzlich erhalten, damit
    // ein bestehendes Make-Szenario nicht bricht — Make kann die Signatur pruefen, sobald gewuenscht.
    $headers = ['Content-Type: application/json'];
    if ($secret !== '') {
        $headers[] = 'X-Signature: sha256=' . hash_hmac('sha256', (string) $payload, $secret);
    }

    try {
        $ch = curl_init($webhookUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => $headers,
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

        // WP-M10: name the channels that were actually requested (was a fixed "Instagram/Facebook").
        $kanalNamen = array_map(
            static fn (string $c): string => $c === 'instagram' ? 'Instagram' : ($c === 'facebook' ? 'Facebook' : ucfirst($c)),
            array_values($channels)
        );
        return [
            'ok'       => true,
            'message'  => 'An Make.com übergeben. ' . implode(' + ', $kanalNamen) . ' postet in Kürze.',
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
