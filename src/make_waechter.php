<?php
/**
 * make.com-Waechter: erkennt, wenn eines der beiden make-Szenarien still steht.
 *
 * Anlass: Am 17.09.2026 hat make das Insights-Szenario (7094793) nach einem einzigen
 * Konfigurationsfehler dauerhaft abgeschaltet — elf Tage lang fiel das nur als „ausstehend“
 * in der Auswertung auf (intern/make-com-optimierung-spec.md §7.6).
 *
 * Prinzip Lebenszeichen statt make-API: die Website merkt sich, wann make zuletzt nachgefragt
 * bzw. zurueckgemeldet hat. Das faengt jeden Stillstand, egal warum (abgeschaltet, Credits leer,
 * Verbindung abgelaufen), und braucht keinen make-Token.
 *  - Insights: posts_pending_insights.php stempelt jeden autorisierten Abruf in `einstellungen`.
 *    Das Szenario laeuft taeglich 17:56 -> ueber MAKE_INSIGHTS_MAX_STUNDEN ohne Abruf = Alarm.
 *  - Posting: ein an make uebergebener Post ohne Rueckmeldung (versand_bestaetigt_am) nach
 *    MAKE_POSTING_MAX_MINUTEN = Alarm.
 *
 * Anzeige: Hinweis im Cockpit (orga/index.php, nur Admins) + Tagesmail (bin/make_waechter.php
 * ueber .github/workflows/taegliche_erinnerung.yml), nur wenn etwas nicht stimmt.
 */

declare(strict_types=1);

const MAKE_WAECHTER_EMPFAENGER     = 't.tyras@atsv-kirchseeon-marktlauf.de';
const MAKE_INSIGHTS_HEARTBEAT_KEY  = 'make_insights_letzter_abruf';
const MAKE_INSIGHTS_MAX_STUNDEN    = 26;
const MAKE_POSTING_MAX_MINUTEN     = 30;

/**
 * Records that the insights scenario just called in. Failures are logged, never fatal —
 * the collector must keep working even if the heartbeat cannot be written.
 */
function makeInsightsHeartbeat(PDO $pdo): void
{
    try {
        $pdo->prepare(
            'INSERT INTO einstellungen (`key`, `value`) VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE `value` = :v2'
        )->execute([
            'k'  => MAKE_INSIGHTS_HEARTBEAT_KEY,
            'v'  => date('Y-m-d H:i:s'),
            'v2' => date('Y-m-d H:i:s'),
        ]);
    } catch (PDOException $e) {
        logError('makeInsightsHeartbeat: ' . $e->getMessage());
    }
}

/**
 * Returns human-readable findings; empty array = all good.
 *
 * @return string[]
 */
function makeWaechterBefunde(PDO $pdo): array
{
    $befunde = [];

    // Insights scenario: last authorised call must be recent.
    $stmt = $pdo->prepare('SELECT `value` FROM einstellungen WHERE `key` = :k');
    $stmt->execute(['k' => MAKE_INSIGHTS_HEARTBEAT_KEY]);
    $letzter = $stmt->fetchColumn();
    $ts      = is_string($letzter) ? strtotime($letzter) : false;
    if ($ts === false) {
        $befunde[] = 'Insights-Szenario (make 7094793): noch nie ein Abruf verzeichnet.';
    } elseif (time() - $ts > MAKE_INSIGHTS_MAX_STUNDEN * 3600) {
        $befunde[] = sprintf(
            'Insights-Szenario (make 7094793): letzter Abruf %s — seit über %d Stunden still. '
            . 'Vermutlich hat make das Szenario abgeschaltet.',
            date('d.m.Y H:i', $ts),
            MAKE_INSIGHTS_MAX_STUNDEN
        );
    }

    // Posting scenario: posts handed to make without any callback.
    $stmt = $pdo->prepare(
        "SELECT id, gesendet_am
           FROM post_race_contents
          WHERE status = 'gesendet'
            AND COALESCE(gesendet_kanaele, '') <> ''
            AND versand_bestaetigt_am IS NULL
            AND gesendet_am >= (NOW() - INTERVAL 7 DAY)
            AND gesendet_am <  (NOW() - INTERVAL :min MINUTE)
          ORDER BY gesendet_am"
    );
    $stmt->execute(['min' => MAKE_POSTING_MAX_MINUTEN]);
    $ohneRueckmeldung = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($ohneRueckmeldung) {
        $liste = array_map(
            static fn(array $r): string => '#' . (int) $r['id'] . ' (' . date('d.m. H:i', strtotime((string) $r['gesendet_am'])) . ')',
            $ohneRueckmeldung
        );
        $befunde[] = sprintf(
            'Posting-Szenario (make 6642115): %d Post(s) ohne Rückmeldung nach über %d Minuten: %s. '
            . 'Vermutlich wurde nicht gepostet.',
            count($ohneRueckmeldung),
            MAKE_POSTING_MAX_MINUTEN,
            implode(', ', $liste)
        );
    }

    return $befunde;
}
