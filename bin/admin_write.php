#!/usr/bin/env php
<?php
/**
 * Admin-Write-CLI — kontrolliertes Eintragen von Sponsoren-Daten über die App-Logik.
 *
 * Zweck: Claude/Orga kann per SSH gezielt Felder setzen, ohne die UI und ohne rohes SQL. Jeder
 * Schreibpfad läuft durch dieselben validierten Funktionen wie die Weboberfläche
 * (sponsorSetField / sponsorLeistungSet / Ansprechpartner-Insert) — Whitelist, keine dynamischen
 * Spaltennamen aus Input, kein DELETE. CRM-Daten laufen NIE über GitHub: dieses Werkzeug wird
 * lokal per SSH aufgerufen, nie über einen Actions-Workflow (public-Repo → Logs wären öffentlich).
 *
 * SICHER PER DEFAULT: ohne --apply ist jeder Aufruf ein Trockenlauf (zeigt nur, was passieren
 * würde). Erst --apply schreibt. Zielsatz per --id (eindeutig) oder --firma (exakt; mehrdeutig
 * oder unbekannt => Abbruch).
 *
 * Aufruf auf Strato (SSH-Shell meldet cgi-fcgi statt cli):
 *   MARKTLAUF_CLI=1 /bin/php bin/admin_write.php <befehl> [optionen]
 *
 * Befehle:
 *   resolve  --like "<text>"                      Sponsoren per LIKE suchen (id/firma/paket/status)
 *   show     (--id N | --firma "<exakt>")         Sponsor-Kernfelder + Ansprechpartner + Banner-Leistung
 *   set-field  <ziel> --field <name> --value "<v>"  ein Sponsor-Feld setzen (Whitelist s. sponsorSetFieldKeys)
 *   set-leistung <ziel> [--position banner] [--vereinbart 0|1] [--freitext "<t>"]
 *                [--wm-art banner|hussen] [--wm-anzahl N] [--wm-deadline JJJJ-MM-TT] [--wm-status offen|erhalten|zurueck]
 *   add-ansprechpartner <ziel> --nachname "<n>" [--anrede Herr|Frau|Divers] [--vorname ..]
 *                [--funktion ..] [--email ..] [--telefon ..] [--im-anschreiben 0|1]
 *   rewrite-feed                                  öffentlichen Sponsoren-Feed neu schreiben
 *
 * <ziel> = --id N  ODER  --firma "<exakter Firmenname>". Schreiben immer mit --apply.
 */

declare(strict_types=1);

if (!defined('STDERR')) {
    define('STDERR', fopen('php://stderr', 'w'));
}
if (php_sapi_name() !== 'cli' && getenv('MARKTLAUF_CLI') !== '1') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/logger.php';
require_once __DIR__ . '/../src/sponsor_status.php';
require_once __DIR__ . '/../src/sponsor_write.php';
require_once __DIR__ . '/../src/sponsor_leistungen.php';
require_once __DIR__ . '/../src/sponsor_rotation.php';

function out(string $s): void { fwrite(STDOUT, $s . PHP_EOL); }
function fail(string $s): void { fwrite(STDERR, 'FEHLER: ' . $s . PHP_EOL); exit(1); }

// --- Optionen parsen: --key value / --flag ---
$args = $argv;
array_shift($args);              // Skriptname
$cmd = array_shift($args) ?? '';
$opts = [];
for ($i = 0, $n = count($args); $i < $n; $i++) {
    $a = $args[$i];
    if (strncmp($a, '--', 2) !== 0) {
        fail("Unerwartetes Argument: {$a}");
    }
    $key = substr($a, 2);
    $next = $args[$i + 1] ?? null;
    if ($next === null || strncmp($next, '--', 2) === 0) {
        $opts[$key] = true;                 // Flag
    } else {
        $opts[$key] = $next;                // Wert
        $i++;
    }
}
$opt = static fn (string $k, $default = null) => array_key_exists($k, $opts) && $opts[$k] !== true ? $opts[$k] : $default;
$apply = array_key_exists('apply', $opts);

$pdo = getDbConnection();

/** Sponsor per --id oder exaktem --firma auflösen (mehrdeutig/unbekannt => Abbruch). */
function resolveSponsor(PDO $pdo, callable $opt): array
{
    $id = $opt('id');
    if ($id !== null) {
        $stmt = $pdo->prepare('SELECT * FROM sponsors WHERE id = :id');
        $stmt->execute(['id' => (int) $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { fail("Kein Sponsor mit id={$id}."); }
        return $row;
    }
    $firma = $opt('firma');
    if ($firma !== null) {
        $stmt = $pdo->prepare('SELECT * FROM sponsors WHERE firma = :f');
        $stmt->execute(['f' => $firma]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) === 0) { fail("Kein Sponsor mit exaktem Namen '{$firma}'. Mit 'resolve --like' suchen."); }
        if (count($rows) > 1)  { fail("Mehrdeutig: " . count($rows) . " Sponsoren heißen '{$firma}'. Mit --id arbeiten."); }
        return $rows[0];
    }
    fail('Ziel fehlt: --id N oder --firma "<exakt>" angeben.');
}

/** Banner-Leistungszeile eines Sponsors (für show/read-back). */
function bannerLeistung(PDO $pdo, int $sponsorId): ?array
{
    $stmt = $pdo->prepare(
        "SELECT position, vereinbart, freitext, wm_art, wm_anzahl, wm_deadline, wm_status
           FROM sponsor_leistungen WHERE sponsor_id = :id AND position = 'banner'"
    );
    $stmt->execute(['id' => $sponsorId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

switch ($cmd) {
    case 'resolve': {
        $like = $opt('like');
        if ($like === null) { fail('resolve braucht --like "<text>".'); }
        $stmt = $pdo->prepare("SELECT id, firma, paket, status FROM sponsors WHERE firma LIKE :q ORDER BY firma");
        $stmt->execute(['q' => '%' . $like . '%']);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) { out('(keine Treffer)'); break; }
        foreach ($rows as $r) {
            out(sprintf('#%d  %-40s  paket=%s  status=%s', $r['id'], $r['firma'], $r['paket'] ?? '–', $r['status'] ?? '–'));
        }
        break;
    }

    case 'show': {
        $s = resolveSponsor($pdo, $opt);
        $id = (int) $s['id'];
        out("=== Sponsor #{$id}: {$s['firma']} ===");
        foreach (['paket', 'status', 'summe', 'foerdergruppe', 'website', 'ansprache',
                  'bedingungen_bestaetigt_am', 'bedingungen_weg', 'bedingungen_beleg',
                  'rechnung_firma', 'rechnung_email', 'rechnung_strasse', 'rechnung_plz', 'rechnung_ort'] as $f) {
            out(sprintf('  %-26s %s', $f, $s[$f] ?? '(NULL)'));
        }
        $ap = $pdo->prepare('SELECT anrede, vorname, nachname, funktion, email, telefon, im_anschreiben
            FROM sponsor_ansprechpartner WHERE sponsor_id = :id ORDER BY sortierung');
        $ap->execute(['id' => $id]);
        out('  --- Ansprechpartner ---');
        foreach ($ap->fetchAll(PDO::FETCH_ASSOC) as $a) { out('    ' . json_encode($a, JSON_UNESCAPED_UNICODE)); }
        $b = bannerLeistung($pdo, $id);
        out('  --- Banner-Leistung --- ' . ($b ? json_encode($b, JSON_UNESCAPED_UNICODE) : '(keine Zeile)'));
        break;
    }

    case 'set-field': {
        $s = resolveSponsor($pdo, $opt);
        $id = (int) $s['id'];
        $field = (string) $opt('field', '');
        if ($field === '') { fail('set-field braucht --field <name>.'); }
        if (!in_array($field, sponsorSetFieldKeys(), true)) {
            fail("Feld '{$field}' nicht in der Whitelist. Erlaubt: " . implode(', ', sponsorSetFieldKeys()));
        }
        $value = $opt('value', '');
        out("Sponsor #{$id} ({$s['firma']}) · Feld '{$field}'");
        out("  aktuell: " . ($s[$field] ?? '(NULL)'));
        out("  neu:     " . ($value === '' ? '(leer → NULL)' : $value));
        if (!$apply) { out('DRY-RUN — nichts geschrieben. Mit --apply anwenden.'); break; }
        $res = sponsorSetField($pdo, $id, $field, $value);
        if (!$res['ok']) { fail($res['message'] ?? 'Schreiben abgelehnt.'); }
        $back = $pdo->prepare("SELECT {$field} AS v FROM sponsors WHERE id = :id"); // $field ist whitelist-geprüft
        $back->execute(['id' => $id]);
        out('OK — jetzt: ' . ($back->fetchColumn() ?? '(NULL)'));
        break;
    }

    case 'set-leistung': {
        $s = resolveSponsor($pdo, $opt);
        $id = (int) $s['id'];
        $position = (string) $opt('position', 'banner');
        if (!in_array($position, sponsorLeistungKeys(), true)) {
            fail("Position '{$position}' nicht im Katalog. Erlaubt: " . implode(', ', sponsorLeistungKeys()));
        }
        // Nur mitgeschickte Werbemittel-Felder ins $wm-Array (Leerstring => NULL, fehlend => unverändert).
        $wm = [];
        if (array_key_exists('wm-art', $opts))      { $wm['wm_art'] = $opt('wm-art', ''); }
        if (array_key_exists('wm-anzahl', $opts))   { $wm['wm_anzahl'] = $opt('wm-anzahl', ''); }
        if (array_key_exists('wm-deadline', $opts)) { $wm['wm_deadline'] = $opt('wm-deadline', ''); }
        if (array_key_exists('wm-status', $opts))   { $wm['wm_status'] = $opt('wm-status', ''); }
        $vereinbart = array_key_exists('vereinbart', $opts) ? ((string) $opt('vereinbart', '1') === '1') : null;
        $freitext   = array_key_exists('freitext', $opts) ? (string) $opt('freitext', '') : null;

        out("Sponsor #{$id} ({$s['firma']}) · Leistung '{$position}'");
        out('  aktuell:  ' . (($b = bannerLeistung($pdo, $id)) ? json_encode($b, JSON_UNESCAPED_UNICODE) : '(keine Zeile)'));
        out('  vereinbart=' . ($vereinbart === null ? '(unverändert)' : ($vereinbart ? '1' : '0'))
            . '  freitext=' . ($freitext === null ? '(unverändert)' : "'{$freitext}'")
            . '  wm=' . json_encode($wm, JSON_UNESCAPED_UNICODE));
        // wm_* hart validieren (analog leistung_crud.php), bevor geschrieben wird.
        foreach ($wm as $k => $v) {
            $v = trim((string) $v);
            if ($v === '') { continue; }
            if ($k === 'wm_art' && !in_array($v, ['banner', 'hussen'], true)) { fail('wm-art: banner|hussen'); }
            if ($k === 'wm_anzahl' && !(ctype_digit($v) && (int) $v <= 65535)) { fail('wm-anzahl: 0..65535'); }
            if ($k === 'wm_status' && !in_array($v, ['offen', 'erhalten', 'zurueck'], true)) { fail('wm-status: offen|erhalten|zurueck'); }
            if ($k === 'wm_deadline') { $d = DateTime::createFromFormat('Y-m-d', $v); if (!($d && $d->format('Y-m-d') === $v)) { fail('wm-deadline: JJJJ-MM-TT'); } }
        }
        if (!$apply) { out('DRY-RUN — nichts geschrieben. Mit --apply anwenden.'); break; }
        sponsorLeistungSet($pdo, $id, $position, $vereinbart, $freitext, $wm ?: null);
        out('OK — jetzt: ' . (($b2 = bannerLeistung($pdo, $id)) ? json_encode($b2, JSON_UNESCAPED_UNICODE) : '(keine Zeile)'));
        break;
    }

    case 'add-ansprechpartner': {
        $s = resolveSponsor($pdo, $opt);
        $id = (int) $s['id'];
        $nachname = trim((string) $opt('nachname', ''));
        if ($nachname === '') { fail('add-ansprechpartner braucht --nachname.'); }
        $anrede = (string) $opt('anrede', '');
        if (!in_array($anrede, ['Herr', 'Frau', 'Divers', ''], true)) { fail('anrede: Herr|Frau|Divers'); }
        $data = [
            'anrede'         => $anrede,
            'vorname'        => trim((string) $opt('vorname', '')),
            'nachname'       => $nachname,
            'funktion'       => trim((string) $opt('funktion', '')),
            'telefon'        => trim((string) $opt('telefon', '')),
            'email'          => trim((string) $opt('email', '')),
            'im_anschreiben' => ((string) $opt('im-anschreiben', '1') === '0') ? 0 : 1,
        ];
        out("Sponsor #{$id} ({$s['firma']}) · neuer Ansprechpartner");
        out('  ' . json_encode($data, JSON_UNESCAPED_UNICODE));
        if (!$apply) { out('DRY-RUN — nichts geschrieben. Mit --apply anwenden.'); break; }
        // Insert wie ansprechpartner_save.php (save/Neuanlage), ans Ende der Sortierung.
        $pos = $pdo->prepare('SELECT COALESCE(MAX(sortierung),0)+1 FROM sponsor_ansprechpartner WHERE sponsor_id = :sid');
        $pos->execute(['sid' => $id]);
        $data['sortierung'] = (int) $pos->fetchColumn();
        $data['sponsor_id'] = $id;
        $pdo->prepare('INSERT INTO sponsor_ansprechpartner
                (sponsor_id, anrede, vorname, nachname, funktion, telefon, email, im_anschreiben, sortierung)
            VALUES (:sponsor_id, :anrede, :vorname, :nachname, :funktion, :telefon, :email, :im_anschreiben, :sortierung)')
            ->execute($data);
        out('OK — neuer Ansprechpartner id=' . (int) $pdo->lastInsertId());
        break;
    }

    case 'rewrite-feed': {
        if (!$apply) { out('DRY-RUN — würde den öffentlichen Sponsoren-Feed neu schreiben. Mit --apply anwenden.'); break; }
        writeSponsorenFeed($pdo);
        out('OK — Feed neu geschrieben.');
        break;
    }

    default:
        fail("Unbekannter Befehl '{$cmd}'. Bekannt: resolve, show, set-field, set-leistung, add-ansprechpartner, rewrite-feed.");
}
