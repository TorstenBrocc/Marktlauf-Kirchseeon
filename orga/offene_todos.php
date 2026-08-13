<?php
/**
 * Offene ToDos Sponsoring — was gerade Aufmerksamkeit braucht, auf einer Seite.
 *
 * Datenquelle ist ausschließlich src/offene_todos.php — dieselbe, aus der die
 * Erinnerungsmail (bin/offene_todos_digest.php) ihre Inhalte zieht. Die Seite bringt
 * bewusst KEINE eigenen Abfragen mit, sonst gäbe es zwei Wahrheiten.
 *
 * Reihenfolge der Gruppen = Bearbeitungsreihenfolge: erst was zugesagt/terminiert ist,
 * dann was noch nie angefasst wurde, zuletzt das Nachfassen.
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

/**
 * Kontakt-Buttons einer Zeile (auf dem Handy antippbar).
 * Muster aus orga/sponsoren.php: im href werden Leerzeichen entfernt, die Anzeige
 * bleibt formatiert.
 */
$kontakt = static function (?string $telefon, ?string $email): string {
    $out = '';
    $tel = trim((string) $telefon);
    if ($tel !== '') {
        $out .= '<a href="tel:' . htmlspecialchars(preg_replace('/\s+/', '', $tel)) . '">📞 '
              . htmlspecialchars($tel) . '</a>';
    }
    $mail = trim((string) $email);
    if ($mail !== '') {
        $out .= '<a href="mailto:' . htmlspecialchars($mail) . '">✉️ Mail</a>';
    }
    return $out === '' ? '' : '<span class="todo-kontakt">' . $out . '</span>';
};

/** Alters-/Fristangabe. `dringend` hebt hervor, ohne die Seite rot einzufärben. */
$alter = static function (int $tage, string $heuteText, string $mehrText, bool $dringendAbTag1 = true): string {
    $klasse = ($dringendAbTag1 && $tage > 0) ? 'todo-alter dringend' : 'todo-alter';
    $text = $tage <= 0 ? $heuteText : sprintf($mehrText, $tage);
    return '<span class="' . $klasse . '">' . htmlspecialchars($text) . '</span>';
};

/** Priorität nur zeigen, wenn gesetzt — Prio 1 ist die einzige, die hervorsticht. */
$prio = static function ($p): string {
    $p = $p === null ? 0 : (int) $p;
    if ($p !== 1) {
        return '';
    }
    return '<span class="todo-prio">Prio 1</span>';
};

/** Letzter Notiz-Stand als eigene Zeile unter dem Eintrag. */
$notiz = static function (?string $notizen): string {
    $stand = todoNotizStand($notizen);
    return $stand === '' ? '' : '<span class="todo-notiz">' . htmlspecialchars($stand) . '</span>';
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
    <title>Offene ToDos Sponsoring | ATSV Kirchseeon Marktlauf</title>
    <link rel="stylesheet" href="css/orga.css?v=<?= @filemtime(__DIR__ . '/css/orga.css') ?>">
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo-final.svg">
    <style>
        /* Durchgehend CI-Grün (TT, 2026-08-13): Dringlichkeit wird über Gewicht und
           einen kräftigeren Grünton gezeigt, nicht über Rot. Rot bleibt echten
           Fehlerzuständen vorbehalten (Versand-Queue). */
        .todo-kopf {
            display: flex;
            align-items: baseline;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-bottom: 1.25rem;
        }
        .todo-summe {
            background: var(--primary);
            color: #fff;
            border-radius: 999px;
            padding: 0.15rem 0.7rem;
            font-size: 0.85rem;
            font-weight: 700;
        }
        .todo-intro { color: var(--text-light); font-size: 0.85rem; margin: 0; }

        .todo-gruppe {
            background: var(--white);
            border-radius: 8px;
            box-shadow: var(--shadow-card);
            border-left: 4px solid var(--primary);
            padding: 1.1rem 1.35rem;
            margin-bottom: 1.1rem;
        }
        .todo-gruppe.ist-fehler { border-left-color: var(--error); }
        .todo-gruppe.ist-nachrichtlich { border-left-color: var(--border); }
        .todo-gruppe > h2 {
            font-size: 0.95rem;
            margin: 0 0 0.2rem 0;
            display: flex;
            align-items: baseline;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .todo-anzahl {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--primary);
            background: var(--success-bg);
            border-radius: 999px;
            padding: 0.05rem 0.5rem;
        }
        .todo-gruppe.ist-fehler .todo-anzahl { color: var(--error); background: var(--error-bg); }
        .todo-was { font-size: 0.78rem; color: var(--text-light); margin: 0 0 0.7rem 0; }

        .todo-liste { list-style: none; margin: 0; padding: 0; }
        .todo-liste > li {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 0.2rem 0.9rem;
            padding: 0.55rem 0;
            border-bottom: 1px solid var(--border);
        }
        .todo-liste > li:last-child { border-bottom: none; }
        .todo-zeile { display: flex; align-items: baseline; gap: 0.5rem; flex-wrap: wrap; min-width: 0; }
        .todo-name { font-weight: 600; overflow-wrap: anywhere; }
        .todo-name a { color: inherit; text-decoration: none; }
        .todo-name a:hover { text-decoration: underline; color: var(--primary); }
        .todo-alter { font-size: 0.75rem; color: var(--text-light); white-space: nowrap; }
        .todo-alter.dringend { color: var(--primary-dark); font-weight: 700; }
        .todo-prio {
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--primary);
            border: 1px solid var(--primary);
            border-radius: 999px;
            padding: 0 0.4rem;
        }
        .todo-notiz {
            grid-column: 1 / -1;
            font-size: 0.78rem;
            color: var(--text-light);
            font-style: italic;
            overflow-wrap: anywhere;
        }
        .todo-kontakt { display: flex; gap: 0.4rem; flex-wrap: wrap; justify-self: end; }
        .todo-kontakt a {
            font-size: 0.75rem;
            padding: 0.15rem 0.55rem;
            border: 1px solid var(--primary);
            border-radius: 999px;
            color: var(--primary);
            text-decoration: none;
            white-space: nowrap;
        }
        .todo-kontakt a:hover { background: var(--primary); color: #fff; }
        .todo-mehr { font-size: 0.78rem; color: var(--text-light); margin: 0.6rem 0 0 0; }
        .todo-leer {
            background: var(--success-bg);
            border-left: 4px solid var(--primary);
            border-radius: 8px;
            padding: 1.25rem 1.5rem;
        }

        /* Handy: alles untereinander, Kontakt-Buttons unter den Namen — kein
           horizontales Scrollen (vertikal ist die Leserichtung). */
        @media (max-width: 700px) {
            .todo-gruppe { padding: 1rem; }
            .todo-liste > li { grid-template-columns: 1fr; }
            .todo-kontakt { justify-self: start; }
            .todo-kontakt a { padding: 0.3rem 0.7rem; }
        }
    </style>
</head>
<body>
<?php $activeNav = 'offene_todos'; require __DIR__ . '/_sidebar.php'; ?>

        <main class="main-content">
            <header class="content-header">
                <h1>Offene ToDos Sponsoring</h1>
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
                    <p class="todo-intro">Keine fälligen Wiedervorlagen, keine offenen Bestätigungen, nichts Unangeschriebenes und kein unbeantwortetes Anschreiben.</p>
                </div>
            <?php endif; ?>

            <?php if (!empty($todos['bestaetigung'])): ?>
            <section class="todo-gruppe">
                <h2>Bestätigung offen <span class="todo-anzahl"><?= count($todos['bestaetigung']) ?></span></h2>
                <p class="todo-was">Hat zugesagt — die Bestätigung mit den Sponsoring-Bedingungen ist noch nicht raus. Läuft über <a href="bestaetigungen.php">Bestätigungen</a>.</p>
                <ul class="todo-liste">
                    <?php foreach (array_slice($todos['bestaetigung'], 0, TODO_LISTE_MAX) as $t): ?>
                    <li>
                        <span class="todo-zeile">
                            <span class="todo-name"><a href="sponsor_form.php?id=<?= (int) $t['id'] ?>"><?= htmlspecialchars($t['firma']) ?></a></span>
                            <?= $prio($t['prioritaet']) ?>
                            <?php if (!empty($t['paket'])): ?>
                                <span class="todo-alter"><?= htmlspecialchars(ucfirst((string) $t['paket'])) ?></span>
                            <?php endif; ?>
                            <?= $alter((int) $t['tage'], 'heute zugesagt', 'seit %d Tagen zugesagt') ?>
                        </span>
                        <?= $kontakt($t['telefon'], $t['email']) ?>
                        <?= $notiz($t['notizen']) ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($rest($todos['bestaetigung']) > 0): ?>
                    <p class="todo-mehr">… und <?= $rest($todos['bestaetigung']) ?> weitere — <a href="bestaetigungen.php">alle öffnen</a></p>
                <?php endif; ?>
            </section>
            <?php endif; ?>

            <?php if (!empty($todos['wiedervorlagen'])): ?>
            <section class="todo-gruppe">
                <h2>Wiedervorlage fällig <span class="todo-anzahl"><?= count($todos['wiedervorlagen']) ?></span></h2>
                <p class="todo-was">Termin gesetzt und erreicht — hier war jemand schon dran und wollte nachfassen.</p>
                <ul class="todo-liste">
                    <?php foreach (array_slice($todos['wiedervorlagen'], 0, TODO_LISTE_MAX) as $t): ?>
                    <li>
                        <span class="todo-zeile">
                            <span class="todo-name"><a href="sponsor_form.php?id=<?= (int) $t['id'] ?>"><?= htmlspecialchars($t['firma']) ?></a></span>
                            <?= $prio($t['prioritaet']) ?>
                            <span class="todo-alter"><?= htmlspecialchars(sponsorStatusLabel((string) $t['status'])) ?></span>
                            <?= $alter((int) $t['tage'], 'heute fällig', 'seit %d Tagen überfällig') ?>
                        </span>
                        <?= $kontakt($t['telefon'], $t['email']) ?>
                        <?= $notiz($t['notizen']) ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($rest($todos['wiedervorlagen']) > 0): ?>
                    <p class="todo-mehr">… und <?= $rest($todos['wiedervorlagen']) ?> weitere — <a href="sponsoren.php">alle Sponsoren öffnen</a></p>
                <?php endif; ?>
            </section>
            <?php endif; ?>

            <?php if (!empty($todos['versand_fehler'])): ?>
            <section class="todo-gruppe ist-fehler">
                <h2>Versand-Queue: Fehler <span class="todo-anzahl"><?= count($todos['versand_fehler']) ?></span></h2>
                <p class="todo-was">Ein Anschreiben ist nicht rausgegangen. Betrifft den Job, der automatisch läuft — bleibt sonst unbemerkt liegen.</p>
                <ul class="todo-liste">
                    <?php foreach (array_slice($todos['versand_fehler'], 0, TODO_LISTE_MAX) as $t): ?>
                    <li>
                        <span class="todo-zeile">
                            <span class="todo-name"><?= htmlspecialchars($t['firma']) ?></span>
                            <span class="todo-alter"><?= htmlspecialchars($t['fehler'] !== '' ? $t['fehler'] : 'Versand fehlgeschlagen') ?></span>
                        </span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <?php endif; ?>

            <?php if (!empty($todos['nie_angeschrieben'])): ?>
            <section class="todo-gruppe">
                <h2>Noch nie angeschrieben <span class="todo-anzahl"><?= count($todos['nie_angeschrieben']) ?></span></h2>
                <p class="todo-was">Steht im Bestand, wurde aber nie angesprochen. Anschreiben läuft über <a href="erstanschreiben.php">Erstanschreiben</a>.</p>
                <ul class="todo-liste">
                    <?php foreach (array_slice($todos['nie_angeschrieben'], 0, TODO_LISTE_MAX) as $t): ?>
                    <li>
                        <span class="todo-zeile">
                            <span class="todo-name"><a href="sponsor_form.php?id=<?= (int) $t['id'] ?>"><?= htmlspecialchars($t['firma']) ?></a></span>
                            <?= $prio($t['prioritaet']) ?>
                            <?= $alter((int) $t['tage'], 'heute angelegt', 'liegt seit %d Tagen', false) ?>
                        </span>
                        <?= $kontakt($t['telefon'], $t['email']) ?>
                        <?= $notiz($t['notizen']) ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($rest($todos['nie_angeschrieben']) > 0): ?>
                    <p class="todo-mehr">… und <?= $rest($todos['nie_angeschrieben']) ?> weitere — <a href="sponsoren.php?status=neu">alle neuen öffnen</a></p>
                <?php endif; ?>
            </section>
            <?php endif; ?>

            <?php if (!empty($todos['ohne_reaktion'])): ?>
            <section class="todo-gruppe">
                <h2>Angeschrieben ohne Reaktion <span class="todo-anzahl"><?= count($todos['ohne_reaktion']) ?></span></h2>
                <p class="todo-was">Seit mindestens <?= TODO_KEINE_REAKTION_TAGE ?> Tagen keine Rückmeldung und kein Termin gesetzt. Sobald der Status weitergedreht oder eine Wiedervorlage eingetragen wird, fällt der Eintrag von selbst heraus.</p>
                <ul class="todo-liste">
                    <?php foreach (array_slice($todos['ohne_reaktion'], 0, TODO_LISTE_MAX) as $t): ?>
                    <li>
                        <span class="todo-zeile">
                            <span class="todo-name"><a href="sponsor_form.php?id=<?= (int) $t['id'] ?>"><?= htmlspecialchars($t['firma']) ?></a></span>
                            <?= $prio($t['prioritaet']) ?>
                            <?= $alter((int) $t['tage'], 'heute', 'seit %d Tagen keine Antwort') ?>
                        </span>
                        <?= $kontakt($t['telefon'], $t['email']) ?>
                        <?= $notiz($t['notizen']) ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($rest($todos['ohne_reaktion']) > 0): ?>
                    <p class="todo-mehr">… und <?= $rest($todos['ohne_reaktion']) ?> weitere, nach Priorität und Wartezeit sortiert — <a href="sponsoren.php?status=angefragt">alle Angeschriebenen öffnen</a></p>
                <?php endif; ?>
            </section>
            <?php endif; ?>

            <?php if (!empty($todos['sponsor_aufgaben'])): ?>
            <section class="todo-gruppe ist-nachrichtlich">
                <h2>Aufgaben am Sponsor <span class="todo-anzahl"><?= count($todos['sponsor_aufgaben']) ?></span></h2>
                <p class="todo-was">Ohne Termin — diese Aufgaben haben kein Fälligkeitsdatum, deshalb nur nachrichtlich und nicht in der Gesamtzahl.</p>
                <ul class="todo-liste">
                    <?php foreach (array_slice($todos['sponsor_aufgaben'], 0, TODO_LISTE_MAX) as $t): ?>
                    <li>
                        <span class="todo-zeile">
                            <span class="todo-name"><a href="sponsor_form.php?id=<?= (int) $t['sponsor_id'] ?>"><?= htmlspecialchars($t['firma']) ?></a></span>
                            <span class="todo-alter"><?= htmlspecialchars($t['titel']) ?></span>
                        </span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($rest($todos['sponsor_aufgaben']) > 0): ?>
                    <p class="todo-mehr">… und <?= $rest($todos['sponsor_aufgaben']) ?> weitere</p>
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
