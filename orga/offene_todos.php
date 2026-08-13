<?php
/**
 * ToDos Sponsoring — was gerade Aufmerksamkeit braucht, auf einer Seite.
 *
 * Datenquelle ist ausschließlich src/offene_todos.php — dieselbe, aus der die
 * Erinnerungsmail (bin/offene_todos_digest.php) ihre Inhalte zieht. Die Seite bringt
 * bewusst KEINE eigenen Abfragen mit, sonst gäbe es zwei Wahrheiten.
 *
 * Layout: Helfer-Draht-Look — je Kachel eine echte hd-table (Kopfzeile + Zeilen),
 * Spalten Firma | Info | Status/Frist | Kontakt. Auf dem Handy stapeln die Zeilen.
 *
 * Spec: intern/offene-todos-spec.md
 */

declare(strict_types=1);

require_once __DIR__ . '/api/_auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/logger.php';
require_once __DIR__ . '/../src/offene_todos.php';
require_once __DIR__ . '/../src/sponsor_status.php';

$user = getCurrentUserFromGuard();
$isAdmin = isAdminFromGuard();
$csrfToken = generateCsrfToken();

$pdo = getDbConnection();
$todos = offeneTodosAlle($pdo);

// Auswahllisten fürs Schnellanlegen in „Aufgaben am Sponsor".
//
// Die Vorschlagsliste enthält zwei Sorten Einträge: Firmen und Ansprechpartner (TT-Wunsch
// 2026-08-13 — „dass auch im Namen gesucht wird"). Ein Ansprechpartner-Eintrag trägt den
// Personennamen als Wert und die Firma als sichtbaren Zusatz, damit erkennbar bleibt, wohin
// die Aufgabe läuft. Aufgelöst wird beides serverseitig in aufgabe_orga_crud.php.
$sponsorAuswahl = [];
$orgaUsers = [];
try {
    foreach ($pdo->query('SELECT firma FROM sponsors ORDER BY firma') as $s) {
        $sponsorAuswahl[] = ['wert' => (string) $s['firma'], 'zusatz' => ''];
    }
    $apStmt = $pdo->query("
        SELECT TRIM(CONCAT(ap.vorname, ' ', ap.nachname)) AS person, s.firma
        FROM sponsor_ansprechpartner ap
        JOIN sponsors s ON s.id = ap.sponsor_id
        WHERE TRIM(CONCAT(ap.vorname, ' ', ap.nachname)) <> ''
        ORDER BY ap.nachname, ap.vorname
    ");
    foreach ($apStmt as $ap) {
        $sponsorAuswahl[] = ['wert' => (string) $ap['person'], 'zusatz' => (string) $ap['firma']];
    }
    $orgaUsers = orgaUserListe($pdo);
} catch (PDOException $e) {
    logError('ToDo-Seite: Auswahllisten nicht ladbar: ' . $e->getMessage());
}

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

/** Firma als Link auf die Einzelmaske. */
$firma = static function (int $id, string $name): string {
    return '<a href="sponsor_form.php?id=' . $id . '">' . htmlspecialchars($name) . '</a>';
};

/** Telefon + Mail als schlichte Links (Helfer-Draht-Stil), untereinander. */
$kontakt = static function (?string $telefon, ?string $email): string {
    $out = [];
    $tel = trim((string) $telefon);
    if ($tel !== '') {
        $out[] = '<a href="tel:' . htmlspecialchars(todoTelefonHref($tel)) . '">' . htmlspecialchars($tel) . '</a>';
    }
    $mail = trim((string) $email);
    if ($mail !== '') {
        $out[] = '<a href="mailto:' . htmlspecialchars($mail) . '">' . htmlspecialchars($mail) . '</a>';
    }
    return implode('<br>', $out);
};

/** Prio-1-Badge (sonst leer). */
$prio = static function ($p): string {
    return ((int) ($p ?? 0) === 1) ? '<span class="hd-prio">Prio 1</span> ' : '';
};

/** Alters-/Fristangabe. `dringend` hebt über Grün/Fettung hervor, nicht über Rot. */
$alter = static function (int $tage, string $heuteText, string $mehrText, bool $dringendAbTag1 = true): string {
    $text = $tage <= 0 ? $heuteText : sprintf($mehrText, $tage);
    $cls = ($dringendAbTag1 && $tage > 0) ? ' class="hd-dringend"' : '';
    return '<span' . $cls . '>' . htmlspecialchars($text) . '</span>';
};

/** Eine Tabellenzeile mit den vier Spalten. */
$zeile = static function (string $firma, string $info, string $status, string $kontakt): string {
    return '<tr>'
        . '<td class="hd-firma">' . $firma . '</td>'
        . '<td class="hd-info">' . $info . '</td>'
        . '<td class="hd-status">' . $status . '</td>'
        . '<td class="hd-kontakt">' . $kontakt . '</td>'
        . '</tr>';
};

// Titel und Beschreibung kommen zentral aus src/offene_todos.php (todoGruppenMeta()),
// damit Seite und Erinnerungsmail nicht auseinanderlaufen. Nur der Absprung-Link wird
// hier gerendert — in der Mail wäre er überflüssig.
$meta = todoGruppenMeta();
$kopfZeile = static function (string $key, int $anzahl) use ($meta): string {
    $m = $meta[$key];
    $out = '<h2>' . htmlspecialchars($m['titel'])
         . ' <span class="hd-count">' . $anzahl . '</span></h2>'
         . '<p class="hd-sub">' . htmlspecialchars($m['sub']);
    if (isset($m['link'])) {
        $out .= ' ' . htmlspecialchars($m['link']['vor'])
              . ' <a href="' . htmlspecialchars($m['link']['href']) . '">'
              . htmlspecialchars($m['link']['label']) . '</a>.';
    }
    return $out . '</p>';
};

$rest = static function (array $liste): int {
    return max(0, count($liste) - TODO_LISTE_MAX);
};
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>ToDos Sponsoring | ATSV Kirchseeon Marktlauf</title>
    <link rel="stylesheet" href="css/orga.css?v=<?= @filemtime(__DIR__ . '/css/orga.css') ?>">
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo-final.svg">
    <style>
        /* Basis .hd-card/.hd-table kommt zentral aus css/orga.css (Bauvorlage,
           Links in var(--link)) — hier nur Seiten-Eigenheiten. Rot bleibt echten
           Fehlern vorbehalten (Versand-Queue). */
        .todo-kopf { display: flex; align-items: baseline; gap: 0.75rem; flex-wrap: wrap; margin-bottom: 1.25rem; }
        .todo-summe { background: var(--link); color: #fff; border-radius: var(--radius-pill); padding: 0.15rem 0.7rem; font-size: 0.85rem; font-weight: 700; }
        .todo-intro { color: var(--text-light); font-size: 0.85rem; margin: 0; }

        .hd-card { padding: 1.25rem 1.35rem; }
        .hd-card > h2 { font-size: 2rem; font-weight: 700; margin: 0 0 0.4rem; display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap; color: var(--text); }
        .hd-card.ist-fehler > h2 { color: var(--error); }
        .hd-card.ist-nachrichtlich > h2 { color: var(--text-light); }
        .hd-count { font-size: 0.72rem; font-weight: 700; color: var(--text-light); background: var(--bg); border-radius: var(--radius-pill); padding: 0.05rem 0.55rem; }
        .hd-card.ist-fehler .hd-count { color: var(--error); }
        .hd-sub { font-size: 0.78rem; color: var(--text-light); margin: 0 0 0.8rem; }
        .hd-sub a { color: var(--link); }

        .hd-table tr:last-child td { border-bottom: none; }
        .hd-table a { overflow-wrap: anywhere; }
        .hd-firma { font-weight: 600; overflow-wrap: anywhere; }
        .hd-firma a { color: inherit; }
        .hd-firma a:hover { color: var(--link); }
        .hd-info { color: var(--text-light); font-style: italic; overflow-wrap: anywhere; }
        .hd-status { color: var(--text-light); }
        .hd-kontakt { white-space: nowrap; }
        .hd-aktion { white-space: nowrap; }
        .hd-haken { background: none; border: 1px solid var(--border); border-radius: var(--radius-pill); padding: 0.1rem 0.6rem; font: inherit; font-size: 0.78rem; color: var(--link); cursor: pointer; }
        .hd-haken:hover { border-color: var(--link); background: var(--success-bg); }

        /* Schnellanlegen: eine Zeile, auf dem Handy gestapelt. Sponsor + Aufgabe tragen
           die Zeile, Frist und Wer bleiben schmal — sie sind freiwillig. */
        .todo-neu { display: flex; flex-wrap: wrap; gap: 0.6rem; align-items: flex-end; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border); }
        .todo-neu-feld { display: flex; flex-direction: column; gap: 0.2rem; flex: 1 1 10rem; min-width: 0; }
        .todo-neu-breit { flex: 2 1 16rem; }
        .todo-neu label { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-light); font-weight: 700; }
        .todo-neu-opt { text-transform: none; letter-spacing: 0; font-weight: 400; font-style: italic; }
        .todo-neu input, .todo-neu select { width: 100%; padding: 0.35rem 0.5rem; border: 1px solid var(--border); border-radius: var(--radius-sm); font: inherit; font-size: 0.88rem; background: var(--white); color: var(--text); }
        .hd-haken-add { padding: 0.4rem 0.9rem; font-weight: 700; }
        .hd-dringend { color: var(--link); font-weight: 700; }
        .hd-tag { color: var(--text); }
        .hd-prio { font-size: 0.66rem; font-weight: 700; text-transform: uppercase; color: var(--link); border: 1px solid var(--link); border-radius: var(--radius-pill); padding: 0 0.35rem; white-space: nowrap; }
        .hd-mehr { font-size: 0.78rem; color: var(--text-light); margin: 0.7rem 0 0; }
        .hd-mehr a { color: var(--link); }
        .todo-leer { background: var(--success-bg); border-left: 4px solid var(--link); border-radius: var(--radius-lg); padding: 1.25rem 1.5rem; }

        /* Handy: Tabelle stapeln (kein horizontales Scrollen — vertikal ist die Leserichtung). */
        @media (max-width: 700px) {
            .hd-card { padding: 1.25rem 1rem; }
            .hd-table, .hd-table tbody, .hd-table tr, .hd-table td { display: block; }
            .hd-table thead { display: none; }
            .hd-table tr { border-bottom: 1px solid var(--border); padding: 0.5rem 0; }
            .hd-table tr:last-child { border-bottom: none; }
            .hd-table td { border-bottom: none; padding: 0.1rem 0; }
            .hd-kontakt { white-space: normal; }
        }
    </style>
</head>
<body>
<?php $activeNav = 'offene_todos'; require __DIR__ . '/_sidebar.php'; ?>

        <main class="main-content">
            <header class="content-header">
                <h1>ToDos Sponsoring</h1>
            </header>

            <?php if ($flashSuccess): ?>
                <div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div>
            <?php endif; ?>
            <?php if ($flashError): ?>
                <div class="alert alert-error"><?= htmlspecialchars($flashError) ?></div>
            <?php endif; ?>

            <div class="todo-kopf">
                <?php if ($todos['gesamt'] > 0): ?>
                    <span class="todo-summe"><?= $todos['gesamt'] ?> offen</span>
                <?php endif; ?>
                <p class="todo-intro">Nur Sponsoring — Helfer, Plakate und weitere Bereiche haben eigene Abläufe.</p>
            </div>

            <?php if ($todos['gesamt'] === 0 && empty($todos['sponsor_aufgaben'])): ?>
                <div class="todo-leer">
                    <strong>Nichts offen.</strong>
                    <p class="todo-intro">Keine fälligen Wiedervorlagen, keine offenen Bestätigungen, nichts Unangeschriebenes, keine offenen Bedingungen und kein unbeantwortetes Anschreiben.</p>
                </div>
            <?php endif; ?>

            <?php if (!empty($todos['bestaetigung'])): ?>
            <section class="hd-card">
                <?= $kopfZeile('bestaetigung', count($todos['bestaetigung'])) ?>
                <table class="hd-table">
                    <thead><tr><th>Firma</th><th>Info</th><th>Status / Frist</th><th>Kontakt</th></tr></thead>
                    <tbody>
                    <?php foreach (array_slice($todos['bestaetigung'], 0, TODO_LISTE_MAX) as $t):
                        $status = $prio($t['prioritaet'])
                            . (!empty($t['paket']) ? '<span class="hd-tag">' . htmlspecialchars(ucfirst((string) $t['paket'])) . '</span> ' : '')
                            . $alter((int) $t['tage'], 'heute zugesagt', 'seit %d Tagen zugesagt');
                        echo $zeile($firma((int) $t['id'], (string) $t['firma']), htmlspecialchars(todoNotizStand($t['notizen'])), $status, $kontakt($t['telefon'], $t['email']));
                    endforeach; ?>
                    </tbody>
                </table>
                <?php if ($rest($todos['bestaetigung']) > 0): ?>
                    <p class="hd-mehr">… und <?= $rest($todos['bestaetigung']) ?> weitere — <a href="bestaetigungen.php">alle öffnen</a></p>
                <?php endif; ?>
            </section>
            <?php endif; ?>

            <?php if (!empty($todos['bedingungen'])): ?>
            <section class="hd-card">
                <?= $kopfZeile('bedingungen', count($todos['bedingungen'])) ?>
                <table class="hd-table">
                    <thead><tr><th>Firma</th><th>Info</th><th>Status / Frist</th><th>Kontakt</th></tr></thead>
                    <tbody>
                    <?php foreach (array_slice($todos['bedingungen'], 0, TODO_LISTE_MAX) as $t):
                        $status = $prio($t['prioritaet'])
                            . '<span class="hd-tag">' . htmlspecialchars(sponsorStatusLabel((string) $t['status'])) . '</span> '
                            . $alter((int) $t['tage'], 'seit heute', 'seit %d Tagen');
                        echo $zeile($firma((int) $t['id'], (string) $t['firma']), htmlspecialchars(todoNotizStand($t['notizen'])), $status, $kontakt($t['telefon'], $t['email']));
                    endforeach; ?>
                    </tbody>
                </table>
                <?php if ($rest($todos['bedingungen']) > 0): ?>
                    <p class="hd-mehr">… und <?= $rest($todos['bedingungen']) ?> weitere</p>
                <?php endif; ?>
            </section>
            <?php endif; ?>

            <?php if (!empty($todos['wiedervorlagen'])): ?>
            <section class="hd-card">
                <?= $kopfZeile('wiedervorlagen', count($todos['wiedervorlagen'])) ?>
                <table class="hd-table">
                    <thead><tr><th>Firma</th><th>Info</th><th>Status / Frist</th><th>Kontakt</th></tr></thead>
                    <tbody>
                    <?php foreach (array_slice($todos['wiedervorlagen'], 0, TODO_LISTE_MAX) as $t):
                        $status = $prio($t['prioritaet'])
                            . '<span class="hd-tag">' . htmlspecialchars(sponsorStatusLabel((string) $t['status'])) . '</span> '
                            . $alter((int) $t['tage'], 'heute fällig', 'seit %d Tagen überfällig');
                        echo $zeile($firma((int) $t['id'], (string) $t['firma']), htmlspecialchars(todoNotizStand($t['notizen'])), $status, $kontakt($t['telefon'], $t['email']));
                    endforeach; ?>
                    </tbody>
                </table>
                <?php if ($rest($todos['wiedervorlagen']) > 0): ?>
                    <p class="hd-mehr">… und <?= $rest($todos['wiedervorlagen']) ?> weitere — <a href="sponsoren.php">alle Sponsoren öffnen</a></p>
                <?php endif; ?>
            </section>
            <?php endif; ?>

            <?php if (!empty($todos['versand_fehler'])): ?>
            <section class="hd-card ist-fehler">
                <?= $kopfZeile('versand_fehler', count($todos['versand_fehler'])) ?>
                <table class="hd-table">
                    <thead><tr><th>Firma</th><th>Fehler</th></tr></thead>
                    <tbody>
                    <?php foreach (array_slice($todos['versand_fehler'], 0, TODO_LISTE_MAX) as $t): ?>
                        <tr>
                            <td class="hd-firma"><?= htmlspecialchars($t['firma']) ?></td>
                            <td class="hd-info"><?= htmlspecialchars($t['fehler'] !== '' ? $t['fehler'] : 'Versand fehlgeschlagen') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
            <?php endif; ?>

            <?php if (!empty($todos['nie_angeschrieben'])): ?>
            <section class="hd-card">
                <?= $kopfZeile('nie_angeschrieben', count($todos['nie_angeschrieben'])) ?>
                <table class="hd-table">
                    <thead><tr><th>Firma</th><th>Info</th><th>Status / Frist</th><th>Kontakt</th></tr></thead>
                    <tbody>
                    <?php foreach (array_slice($todos['nie_angeschrieben'], 0, TODO_LISTE_MAX) as $t):
                        $status = $prio($t['prioritaet']) . $alter((int) $t['tage'], 'heute angelegt', 'liegt seit %d Tagen', false);
                        echo $zeile($firma((int) $t['id'], (string) $t['firma']), htmlspecialchars(todoNotizStand($t['notizen'])), $status, $kontakt($t['telefon'], $t['email']));
                    endforeach; ?>
                    </tbody>
                </table>
                <?php if ($rest($todos['nie_angeschrieben']) > 0): ?>
                    <p class="hd-mehr">… und <?= $rest($todos['nie_angeschrieben']) ?> weitere — <a href="sponsoren.php?status=neu">alle neuen öffnen</a></p>
                <?php endif; ?>
            </section>
            <?php endif; ?>

            <?php if (!empty($todos['ohne_reaktion'])): ?>
            <section class="hd-card">
                <?= $kopfZeile('ohne_reaktion', count($todos['ohne_reaktion'])) ?>
                <table class="hd-table">
                    <thead><tr><th>Firma</th><th>Info</th><th>Status / Frist</th><th>Kontakt</th></tr></thead>
                    <tbody>
                    <?php foreach (array_slice($todos['ohne_reaktion'], 0, TODO_LISTE_MAX) as $t):
                        $status = $prio($t['prioritaet']) . $alter((int) $t['tage'], 'seit heute', 'seit %d Tagen ohne Antwort');
                        echo $zeile($firma((int) $t['id'], (string) $t['firma']), htmlspecialchars(todoNotizStand($t['notizen'])), $status, $kontakt($t['telefon'], $t['email']));
                    endforeach; ?>
                    </tbody>
                </table>
                <?php if ($rest($todos['ohne_reaktion']) > 0): ?>
                    <p class="hd-mehr">… und <?= $rest($todos['ohne_reaktion']) ?> weitere, nach Priorität und Wartezeit sortiert — <a href="sponsoren.php?status=angefragt">alle Angeschriebenen öffnen</a></p>
                <?php endif; ?>
            </section>
            <?php endif; ?>

            <?php if (!empty($todos['bedingungen_beleg'])): ?>
            <section class="hd-card ist-nachrichtlich">
                <?= $kopfZeile('bedingungen_beleg', count($todos['bedingungen_beleg'])) ?>
                <table class="hd-table">
                    <thead><tr><th>Firma</th><th>Bestätigt</th></tr></thead>
                    <tbody>
                    <?php foreach (array_slice($todos['bedingungen_beleg'], 0, TODO_LISTE_MAX) as $t):
                        $weg = sponsorBedingungenWegLabel((string) ($t['bedingungen_weg'] ?? ''));
                        $info = 'am ' . htmlspecialchars(date('d.m.Y', strtotime((string) $t['bestaetigt_am']))) . ($weg !== '' ? ' · ' . htmlspecialchars($weg) : ''); ?>
                        <tr>
                            <td class="hd-firma"><?= $firma((int) $t['id'], (string) $t['firma']) ?></td>
                            <td class="hd-info"><?= $info ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
            <?php endif; ?>

            <?php
            /* Immer sichtbar, auch wenn leer — sonst gäbe es keinen Ort zum Anlegen.
               Der graue „nachrichtlich"-Ton gilt nur, solange keine Aufgabe eine Frist hat:
               terminierte zählen seit 13.08.2026 in die Gesamtzahl, und eine mitzählende
               Gruppe darf nicht aussehen wie eine, die man auch übergehen kann. */
            $aufgabenTerminiert = array_filter(
                $todos['sponsor_aufgaben'],
                static fn (array $a): bool => !empty($a['faellig_am'])
            );
            ?>
            <section class="hd-card<?= $aufgabenTerminiert ? '' : ' ist-nachrichtlich' ?>">
                <?= $kopfZeile('sponsor_aufgaben', count($todos['sponsor_aufgaben'])) ?>
                <?php if (!empty($todos['sponsor_aufgaben'])): ?>
                <table class="hd-table">
                    <thead><tr><th>Firma</th><th>Aufgabe</th><th>Frist</th><th>Erledigt</th></tr></thead>
                    <tbody>
                    <?php foreach (array_slice($todos['sponsor_aufgaben'], 0, TODO_LISTE_MAX) as $t): ?>
                        <tr>
                            <td class="hd-firma"><?= $firma((int) $t['sponsor_id'], (string) $t['firma']) ?></td>
                            <td class="hd-info">
                                <?= htmlspecialchars($t['titel']) ?>
                                <?php if (!empty($t['verantwortlich_name'])): ?>
                                    <span class="hd-tag">— <?= htmlspecialchars($t['verantwortlich_name']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="hd-status">
                                <?php if (empty($t['faellig_am'])): ?>
                                    <span>ohne Frist</span>
                                <?php else: ?>
                                    <?= $alter((int) $t['tage_ueberfaellig'], 'heute fällig', 'seit %d Tagen überfällig') ?>
                                <?php endif; ?>
                            </td>
                            <td class="hd-aktion">
                                <form method="post" action="api/aufgabe_orga_crud.php">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <input type="hidden" name="action" value="set_status">
                                    <input type="hidden" name="status" value="erledigt">
                                    <input type="hidden" name="aufgabe_id" value="<?= (int) $t['id'] ?>">
                                    <input type="hidden" name="zurueck" value="todos">
                                    <button type="submit" class="hd-haken" title="Als erledigt markieren">○ abhaken</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if ($rest($todos['sponsor_aufgaben']) > 0): ?>
                    <p class="hd-mehr">… und <?= $rest($todos['sponsor_aufgaben']) ?> weitere</p>
                <?php endif; ?>
                <?php else: ?>
                <p class="hd-sub">Keine offenen Aufgaben.</p>
                <?php endif; ?>

                <form method="post" action="api/aufgabe_orga_crud.php" class="todo-neu">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="kontext_typ" value="sponsor">
                    <input type="hidden" name="zurueck" value="todos">
                    <div class="todo-neu-feld">
                        <label for="neu_sponsor">Sponsor</label>
                        <?php /* Datalist statt <select>: bei 100+ Firmen ist Tippen schneller als Scrollen. */ ?>
                        <input list="sponsoren_liste" id="neu_sponsor" name="sponsor_suche" required
                               placeholder="Firma oder Person tippen…" autocomplete="off">
                        <datalist id="sponsoren_liste">
                            <?php foreach ($sponsorAuswahl as $s): ?>
                                <option value="<?= htmlspecialchars($s['wert']) ?>"><?= htmlspecialchars($s['zusatz']) ?></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="todo-neu-feld todo-neu-breit">
                        <label for="neu_titel">Aufgabe</label>
                        <input type="text" id="neu_titel" name="titel" required placeholder="Was ist zu tun?">
                    </div>
                    <div class="todo-neu-feld">
                        <label for="neu_faellig">Frist <span class="todo-neu-opt">optional</span></label>
                        <input type="date" id="neu_faellig" name="faellig_am">
                    </div>
                    <div class="todo-neu-feld">
                        <label for="neu_wer">Wer <span class="todo-neu-opt">optional</span></label>
                        <select id="neu_wer" name="verantwortlich_user_id">
                            <option value="">– offen –</option>
                            <?php foreach ($orgaUsers as $ou): ?>
                                <option value="<?= (int) $ou['id'] ?>"><?= htmlspecialchars($ou['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="hd-haken hd-haken-add">+ anlegen</button>
                </form>
            </section>
        </main>
        </div>

<script>
    // Burger-Menü — identisch zu orga/sponsoren.php, damit sich die Seiten gleich verhalten.
    (function() {
        const burger = document.getElementById('burger-btn');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        if (!burger || !sidebar || !overlay) { return; }

        function closeSidebar() {
            sidebar.classList.remove('open');
            overlay.classList.remove('open');
            document.body.style.overflow = '';
        }

        burger.addEventListener('click', function() {
            sidebar.classList.add('open');
            overlay.classList.add('open');
            document.body.style.overflow = 'hidden';
        });
        overlay.addEventListener('click', closeSidebar);
        sidebar.querySelectorAll('.nav-item a').forEach(function(link) {
            link.addEventListener('click', closeSidebar);
        });
    })();
</script>
</body>
</html>
