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

$pdo = getDbConnection();
$todos = offeneTodosAlle($pdo);

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
        /* Helfer-Draht-Look (Bau-Vorlage, intern/CLAUDE.md §UI): weiße Karte + echte
           Tabelle mit grauer Kopfzeile. Grün abgetönt (#007230), nicht das grelle #009640.
           Rot bleibt echten Fehlern vorbehalten (Versand-Queue). */
        .todo-kopf { display: flex; align-items: baseline; gap: 0.75rem; flex-wrap: wrap; margin-bottom: 1.25rem; }
        .todo-summe { background: #007230; color: #fff; border-radius: 999px; padding: 0.15rem 0.7rem; font-size: 0.85rem; font-weight: 700; }
        .todo-intro { color: var(--text-light); font-size: 0.85rem; margin: 0; }

        .hd-card { background: var(--white); border-radius: 8px; box-shadow: var(--shadow-card); padding: 1.25rem 1.35rem; margin-bottom: 1.25rem; }
        .hd-card > h2 { font-size: 2rem; font-weight: 700; margin: 0 0 0.4rem; display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap; color: var(--text); }
        .hd-card.ist-fehler > h2 { color: var(--error); }
        .hd-card.ist-nachrichtlich > h2 { color: var(--text-light); }
        .hd-count { font-size: 0.72rem; font-weight: 700; color: var(--text-light); background: var(--bg); border-radius: 999px; padding: 0.05rem 0.55rem; }
        .hd-card.ist-fehler .hd-count { color: var(--error); }
        .hd-sub { font-size: 0.78rem; color: var(--text-light); margin: 0 0 0.8rem; }
        .hd-sub a { color: #007230; }

        .hd-table { width: 100%; border-collapse: collapse; }
        .hd-table th, .hd-table td { padding: 0.5rem 0.6rem; text-align: left; border-bottom: 1px solid var(--border); font-size: 0.88rem; vertical-align: top; }
        .hd-table th { background: var(--bg); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-light); font-weight: 700; }
        .hd-table tr:last-child td { border-bottom: none; }
        .hd-table a { color: #007230; text-decoration: none; overflow-wrap: anywhere; }
        .hd-table a:hover { text-decoration: underline; }
        .hd-firma { font-weight: 600; overflow-wrap: anywhere; }
        .hd-firma a { color: inherit; }
        .hd-firma a:hover { color: #007230; }
        .hd-info { color: var(--text-light); font-style: italic; overflow-wrap: anywhere; }
        .hd-status { color: var(--text-light); }
        .hd-kontakt { white-space: nowrap; }
        .hd-dringend { color: #007230; font-weight: 700; }
        .hd-tag { color: var(--text); }
        .hd-prio { font-size: 0.66rem; font-weight: 700; text-transform: uppercase; color: #007230; border: 1px solid #007230; border-radius: 999px; padding: 0 0.35rem; white-space: nowrap; }
        .hd-mehr { font-size: 0.78rem; color: var(--text-light); margin: 0.7rem 0 0; }
        .hd-mehr a { color: #007230; }
        .todo-leer { background: var(--success-bg); border-left: 4px solid #007230; border-radius: 8px; padding: 1.25rem 1.5rem; }

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
                <h2>Bestätigungs-Mail offen <span class="hd-count"><?= count($todos['bestaetigung']) ?></span></h2>
                <p class="hd-sub">Hat zugesagt — die Bestätigung mit den Sponsoring-Bedingungen ist noch nicht raus. Läuft über <a href="bestaetigungen.php">Bestätigungen</a>.</p>
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
                <h2>Sponsoring-Bedingungen nicht bestätigt <span class="hd-count"><?= count($todos['bedingungen']) ?></span></h2>
                <p class="hd-sub">Bestätigung ist raus, die Sponsoring-Bedingungen sind aber noch nicht gegengezeichnet. Erfassen in der Einzelmaske (wann, auf welchem Weg, Beleg im Ordner).</p>
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
                <h2>Wiedervorlage fällig <span class="hd-count"><?= count($todos['wiedervorlagen']) ?></span></h2>
                <p class="hd-sub">Termin gesetzt und erreicht — hier war jemand schon dran und wollte nachfassen.</p>
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
                <h2>Versand-Queue: Fehler <span class="hd-count"><?= count($todos['versand_fehler']) ?></span></h2>
                <p class="hd-sub">Ein Anschreiben ist nicht rausgegangen. Betrifft den Job, der automatisch läuft — bleibt sonst unbemerkt liegen.</p>
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
                <h2>Noch nie angeschrieben <span class="hd-count"><?= count($todos['nie_angeschrieben']) ?></span></h2>
                <p class="hd-sub">Steht im Bestand, wurde aber nie angesprochen. Anschreiben läuft über <a href="erstanschreiben.php">Erstanschreiben</a>.</p>
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
                <h2>Angeschrieben ohne Reaktion <span class="hd-count"><?= count($todos['ohne_reaktion']) ?></span></h2>
                <p class="hd-sub">Seit mindestens <?= TODO_KEINE_REAKTION_TAGE ?> Tagen keine Rückmeldung und kein Termin gesetzt. Sobald der Status weitergedreht oder eine Wiedervorlage eingetragen wird, fällt der Eintrag von selbst heraus.</p>
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
                <h2>Sponsoring-Bedingungen bestätigt — Beleg fehlt <span class="hd-count"><?= count($todos['bedingungen_beleg']) ?></span></h2>
                <p class="hd-sub">Inhaltlich erledigt, nur die Rückmeldung liegt nicht im Sponsor-Ordner. Zählt deshalb nicht in die Gesamtzahl.</p>
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

            <?php if (!empty($todos['sponsor_aufgaben'])): ?>
            <section class="hd-card ist-nachrichtlich">
                <h2>Aufgaben am Sponsor <span class="hd-count"><?= count($todos['sponsor_aufgaben']) ?></span></h2>
                <p class="hd-sub">Ohne Termin — diese Aufgaben haben kein Fälligkeitsdatum, deshalb nur nachrichtlich und nicht in der Gesamtzahl.</p>
                <table class="hd-table">
                    <thead><tr><th>Firma</th><th>Aufgabe</th></tr></thead>
                    <tbody>
                    <?php foreach (array_slice($todos['sponsor_aufgaben'], 0, TODO_LISTE_MAX) as $t): ?>
                        <tr>
                            <td class="hd-firma"><?= $firma((int) $t['sponsor_id'], (string) $t['firma']) ?></td>
                            <td class="hd-info"><?= htmlspecialchars($t['titel']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if ($rest($todos['sponsor_aufgaben']) > 0): ?>
                    <p class="hd-mehr">… und <?= $rest($todos['sponsor_aufgaben']) ?> weitere</p>
                <?php endif; ?>
            </section>
            <?php endif; ?>
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
