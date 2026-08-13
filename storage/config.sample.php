<?php
/**
 * Konfiguration für ATSV Kirchseeon Marktlauf Dashboard
 *
 * ANLEITUNG:
 * 1. Kopiere diese Datei zu config.php
 * 2. Fülle die echten Werte ein
 * 3. config.php niemals committen!
 */

return [
    'db' => [
        'host'     => 'localhost',
        'port'     => 3306,
        'name'     => 'marktlauf_db',
        'user'     => 'marktlauf_user',
        'password' => '',
        'charset'  => 'utf8mb4',
    ],

    'app' => [
        'url'         => 'https://atsv-kirchseeon-marktlauf.de',
        'environment' => 'production',
    ],

    'mail' => [
        'from_address' => 'info@atsv-kirchseeon-marktlauf.de',
        'from_name'    => 'ATSV Kirchseeon Marktlauf',
    ],

    // SMTP-Versand (Strato)
    'smtp_host'      => 'smtp.strato.de',
    'smtp_port'      => 587,
    'smtp_user'      => '',  // volle E-Mail-Adresse
    'smtp_password'  => '',  // Postfach-Passwort
    'smtp_from'      => '',  // Absender-Adresse (oder leer = smtp_user)
    'smtp_from_name' => 'ATSV Kirchseeon Marktlauf',
    'smtp_bcc'       => '',  // Blindkopie bei JEDEM Versand (leer = mail.from_address, also info@)

    // Sponsor-Versand: Pause (Sekunden) zwischen zwei Mails im CLI-Queue-Lauf
    // (bin/sponsor_versand.php), damit Strato nicht drosselt.
    'sponsor_versand_delay' => 15,

    // Signatur der Sponsor-Anschreiben (Name/Telefon bewusst NICHT im Repo).
    'sponsor_mail' => [
        'sender_name'  => '',  // z.B. 'Vorname Nachname (ATSV Orga-Team Marktlauf)'
        'sender_role'  => 'Sponsoring · Marktlauf Kirchseeon, ATSV Kirchseeon e.V.',
        'sender_phone' => '',  // z.B. '+49 172 1234567' (leer = nicht anzeigen)
    ],

    'security' => [
        'login_max_attempts'        => 5,
        'login_max_attempts_per_ip' => 20,
        'login_lockout_minutes'     => 15,
        'register_max_per_hour'     => 10,

        // Nur setzen, wenn hinter einem Reverse-Proxy (z.B. Cloudflare, nginx).
        // Dann X-Forwarded-For vom Proxy auswerten. Sonst REMOTE_ADDR nutzen.
        // 'trusted_proxy' => '127.0.0.1',
    ],

    // Orga-Kontaktdaten (angezeigt auf der Helfer-Zugangsseite)
    'orga' => [
        'email'         => 'info@atsv-kirchseeon-marktlauf.de',
        'phone'         => '',        // z.B. '08091 123456'
        'notfall_phone' => '',        // Nur am Veranstaltungstag
    ],

    // Trello-Board für Orga-Aufgaben (optional)
    'trello_board_url' => '',  // z.B. 'https://trello.com/b/BOARD_ID/marktlauf'

    // LLM-Provider für Social-Media-Content-Erstellung (Gemini oder Mistral)
    'gemini_api_key'  => '',  // Google AI Studio → https://aistudio.google.com/app/apikey
    'mistral_api_key' => '',  // Mistral → https://console.mistral.ai/api-keys

    // Auto-Posting Social Media über Make.com (Instagram/Facebook).
    // Leer lassen = kein Auto-Posting, das Dashboard fällt automatisch auf
    // "manuell" zurück (Text kopieren + PNG herunterladen).
    // Einrichtung: in Make.com ein Szenario "Custom Webhook → Instagram/Facebook"
    // anlegen, IG-Business-/Creator-Konto + FB-Seite verbinden, die vom Webhook
    // erzeugte URL hier eintragen. Der Webhook bekommt JSON {text, image_url, channels, secret}.
    'make_webhook_url'    => '',  // z.B. 'https://hook.eu2.make.com/xxxxxxxxxxxx'
    'make_webhook_secret' => '',  // frei wählbares Shared Secret; im Make-Szenario gegenprüfen (optional, aber empfohlen)

    // Brevo (Newsletter). REST-API v3, Header 'api-key'. Key im Brevo-Backend unter
    // "SMTP & API" → "API Keys" generieren — NICHT der "MCP server API key".
    // Leer lassen = Newsletter-Push aus; das Dashboard zeigt dann den Copy-Fallback.
    'brevo_api_key'      => '',
    'brevo_sender_name'  => 'ATSV Kirchseeon Marktlauf',
    'brevo_sender_email' => 'info@atsv-kirchseeon-marktlauf.de',
    'brevo_list_id'      => 0,    // ID der Newsletter-Empfängerliste in Brevo (für den Kampagnen-Entwurf)

    // Google Drive (geteiltes Laufwerk "Marktlauf Orga") als Dateiablage-Backend.
    // Auth = OAuth als info@ (keyless, KEIN Service-Account-Schlüssel; siehe
    // intern/gdrive-storage-spec.md §2.1). Einrichtung einmalig:
    //   1) Google Cloud → APIs & Dienste → Anmeldedaten → OAuth-Client "Desktop-App"
    //      anlegen, JSON herunterladen.
    //   2) Lokal `php bin/gdrive_auth.php <oauth-client.json>` ausführen, als info@
    //      anmelden, Drive-Zugriff erlauben → das Skript druckt Refresh-Token +
    //      Laufwerk-ID und den fertigen Config-Block.
    // Leer lassen = Drive-Backend aus; die Dateiablage nutzt weiter lokalen Storage
    // (storage/files/), plakateAnhang() liest lokal.
    'google_oauth_client_id'     => '',
    'google_oauth_client_secret' => '',
    'google_oauth_refresh_token' => '',  // aus bin/gdrive_auth.php
    'google_shared_drive_id'     => '',  // ID des geteilten Laufwerks (druckt bin/gdrive_auth.php mit aus)
];
