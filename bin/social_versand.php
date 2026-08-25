#!/usr/bin/env php
<?php
/**
 * CLI-Tool: Auto-Versand faelliger Social-Posts am Stichtag.
 * Grundlage: intern/social-auto-versand-stichtag-spec.md (Inhaber-Entscheide 2026-08-25).
 *
 * Sendet NUR Posts, die der Mensch dafuer freigegeben hat (Opt-in je Post):
 *   status='approved'  UND  auto_versand=1  UND  faellig
 * Faellig = Fahrplan-Stichtag heute bis 2 Tage alt (Catch-up-Fenster gegen GitHub-Actions-
 * Verzoegerung/Wochenende). Nutzt die gemeinsame Versandlogik (src/social_versand.php) —
 * identisch zum manuellen Klick (Bild->JPEG, Make.com, Log, Fahrplan-Fortschritt, Live-Mail).
 * Die feste Sendezeit "mittags" kommt aus dem Cron-Zeitpunkt (social_versand.yml), nicht hier.
 *
 * Aufruf (SSH):  MARKTLAUF_CLI=1 php bin/social_versand.php
 *
 * Concurrency: MySQL GET_LOCK, damit Cron + ein manueller Lauf sich nicht ueberholen und
 * denselben Post doppelt senden. Niemals als Web-Request laufen lassen.
 */

// Strato: SSH-Shell liefert cgi-fcgi statt cli → Bypass via MARKTLAUF_CLI=1
if (php_sapi_name() !== 'cli' && getenv('MARKTLAUF_CLI') !== '1') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/logger.php';
require_once __DIR__ . '/../src/social_versand.php';

const SOCIAL_VERSAND_LOCK = 'social_versand_stichtag';

function svLog(string $m): void
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $m . "\n";
}

try {
    $pdo = getDbConnection();

    // Nur ein Versandlauf gleichzeitig (Cron + manuell duerfen sich nicht ueberholen).
    $lock = $pdo->query("SELECT GET_LOCK('" . SOCIAL_VERSAND_LOCK . "', 5)")->fetchColumn();
    if ((int) $lock !== 1) {
        svLog('Lock belegt — anderer Lauf aktiv, Abbruch.');
        exit(0);
    }

    // Faellige Auto-Posts: freigegeben + Opt-in + Stichtag im Catch-up-Fenster (heute .. -2 Tage).
    $posts = $pdo->query(
        "SELECT p.*
           FROM post_race_contents p
           JOIN social_fahrplan f ON f.post_id = p.id AND f.status = 'offen'
          WHERE p.status = 'approved'
            AND p.auto_versand = 1
            AND f.zieldatum IS NOT NULL
            AND f.zieldatum <= CURDATE()
            AND f.zieldatum >= (CURDATE() - INTERVAL 2 DAY)
          ORDER BY f.zieldatum ASC, p.id ASC"
    )->fetchAll(PDO::FETCH_ASSOC);

    if ($posts === []) {
        svLog('Keine faelligen Auto-Posts.');
        $pdo->query("SELECT RELEASE_LOCK('" . SOCIAL_VERSAND_LOCK . "')");
        exit(0);
    }

    svLog(count($posts) . ' faellige(r) Auto-Post(s).');
    foreach ($posts as $post) {
        $postId   = (int) $post['id'];
        $channels = array_values(array_intersect(
            array_map('trim', explode(',', (string) ($post['auto_versand_channels'] ?? ''))),
            ['instagram', 'facebook']
        ));
        if ($channels === []) {
            $channels = ['instagram', 'facebook'];
        }
        // Erster Kommentar: gespeicherter Wert am Post (im CLI gibt es keinen Screen).
        $ersterKommentar = trim((string) ($post['erster_kommentar'] ?? ''));

        try {
            $r = versendePost($pdo, $post, $channels, $ersterKommentar);
            if (!empty($r['ok'])) {
                svLog("Post $postId gesendet an " . implode('+', $channels) . '.');
            } else {
                // Fallback/Fehler: Post bleibt 'approved' fuer den naechsten Lauf (bis Catch-up-Grenze).
                logError("social_versand: Post $postId nicht gesendet: " . (string) ($r['message'] ?? '?'));
                svLog("Post $postId NICHT gesendet: " . (string) ($r['message'] ?? '?'));
            }
        } catch (Throwable $e) {
            logError("social_versand: Post $postId Ausnahme: " . $e->getMessage());
            svLog("Post $postId Ausnahme: " . $e->getMessage());
        }
    }

    $pdo->query("SELECT RELEASE_LOCK('" . SOCIAL_VERSAND_LOCK . "')");
} catch (Throwable $e) {
    logError('social_versand: Lauf-Fehler: ' . $e->getMessage());
    fwrite(STDERR, 'Fehler: ' . $e->getMessage() . "\n");
    exit(1);
}
