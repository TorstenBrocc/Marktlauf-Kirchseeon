<?php
/**
 * Öffentliches Helfer-Anmeldeformular (Token-gegatet)
 */

declare(strict_types=1);

require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/src/db.php';
require_once __DIR__ . '/src/helfer_aufgaben.php';

function isValidAccessToken(string $token): bool {
    if ($token === '' || strlen($token) > 64) {
        return false;
    }

    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('
            SELECT id FROM access_tokens
            WHERE token = :token AND active = 1 AND expires_at > NOW()
        ');
        $stmt->execute(['token' => $token]);
        return $stmt->fetch() !== false;
    } catch (PDOException $e) {
        return false;
    }
}

$token = trim($_GET['token'] ?? '');
$tokenValid = isValidAccessToken($token);

initSession();

$success = isset($_GET['success']);
$error = $_SESSION['helfer_error'] ?? '';
unset($_SESSION['helfer_error']);

$csrfToken = generateCsrfToken();
$basePath = '';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Helfer-Anmeldung | ATSV Marktlauf Kirchseeon</title>
    <meta name="description" content="Melde dich als Helfer beim ATSV Marktlauf Kirchseeon an und unterstütze unser Team.">
    <meta name="robots" content="noindex, nofollow">
    <?php require_once __DIR__ . '/src/layout/head.php'; ?>
    <style>
        .helfer-section {
            padding: var(--space-xl) 0;
            background: var(--gray-100);
            min-height: calc(100vh - 200px);
        }
        .helfer-form {
            max-width: 600px;
            margin: 0 auto;
            padding: var(--space-lg);
            background: var(--white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
        }
        .helfer-form h2 {
            margin-bottom: var(--space-lg);
        }
        .form-group {
            margin-bottom: var(--space-md);
        }
        .form-group label {
            display: block;
            margin-bottom: var(--space-xs);
            font-weight: 600;
            color: var(--gray-700);
        }
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="tel"],
        .form-group textarea {
            width: 100%;
            padding: var(--space-sm);
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: var(--text-base);
            font-family: inherit;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0, 150, 64, 0.15);
        }
        .form-hint {
            font-size: var(--text-sm);
            color: var(--gray-500);
            margin-top: var(--space-xs);
        }
        .anmeldung-hinweis {
            background: #fffbea;
            border: 1px solid #f59e0b;
            border-radius: var(--radius-md);
            padding: var(--space-sm) var(--space-md);
            font-size: var(--text-sm);
            line-height: 1.5;
            margin-bottom: var(--space-lg);
        }
        .aufgaben-tag {
            margin-top: var(--space-md);
        }
        .aufgaben-tag-titel {
            font-size: var(--text-base);
            font-weight: 700;
            color: var(--gray-800);
            padding: var(--space-sm) 0 var(--space-xs);
            border-bottom: 2px solid var(--primary);
            margin-bottom: var(--space-xs);
        }
        .aufgaben-table {
            width: 100%;
            border-collapse: collapse;
            font-size: var(--text-sm);
        }
        .aufgaben-table th,
        .aufgaben-table td {
            text-align: left;
            padding: var(--space-sm);
            border-bottom: 1px solid var(--gray-200);
            vertical-align: middle;
        }
        .aufgaben-table th {
            font-size: var(--text-sm);
            color: var(--gray-500);
            font-weight: 600;
        }
        .aufgaben-table td label {
            font-weight: normal;
            margin: 0;
            cursor: pointer;
            color: var(--gray-800);
        }
        .aufgaben-zeit {
            white-space: nowrap;
            color: var(--gray-600);
        }
        .aufgaben-check-col {
            text-align: center;
            width: 64px;
        }
        .aufgaben-check-col input {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        .foto-fieldset {
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            padding: var(--space-md);
            margin: var(--space-lg) 0;
        }
        .foto-fieldset legend {
            font-weight: 700;
            padding: 0 var(--space-xs);
            color: var(--gray-800);
        }
        .foto-step {
            font-size: var(--text-sm);
            margin-bottom: var(--space-xs);
            color: var(--gray-700);
        }
        .foto-consent-text {
            font-size: var(--text-sm);
            line-height: 1.55;
            color: var(--gray-700);
            background: var(--gray-100);
            border-radius: var(--radius-md);
            padding: var(--space-sm) var(--space-md);
            margin-bottom: var(--space-md);
        }
        .foto-consent-text a {
            color: var(--primary);
        }
        .foto-options {
            display: flex;
            flex-direction: column;
            gap: var(--space-sm);
            margin-top: var(--space-xs);
        }
        /* Auswahlzeile: eckiges Feld + Text nebeneinander, linksbündig */
        .foto-fieldset .foto-option {
            display: flex;
            align-items: flex-start;
            gap: 0.6rem;
            margin: 0;
            font-weight: normal;
            color: var(--gray-700);
            text-align: left;
            cursor: pointer;
        }
        /* Eckige Auswahlfelder (wie die Checkboxen sonst) – überschreibt .form-group input { width:100% } */
        .foto-fieldset .foto-option input[type="radio"] {
            appearance: none;
            -webkit-appearance: none;
            width: 20px;
            height: 20px;
            flex: 0 0 auto;
            margin: 0;
            padding: 0;
            border: 1px solid var(--gray-400);
            border-radius: 4px;
            background: var(--white);
            cursor: pointer;
            position: relative;
            top: 1px;
        }
        .foto-fieldset .foto-option input[type="radio"]:checked {
            background: var(--primary);
            border-color: var(--primary);
        }
        .foto-fieldset .foto-option input[type="radio"]:checked::after {
            content: "";
            position: absolute;
            left: 6px;
            top: 2px;
            width: 5px;
            height: 10px;
            border: solid #fff;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }
        .foto-fieldset .foto-option input[type="radio"]:focus-visible {
            outline: 2px solid var(--primary);
            outline-offset: 2px;
        }
        .checkbox-group {
            display: flex;
            flex-direction: column;
            gap: var(--space-sm);
            margin-top: var(--space-sm);
        }
        .checkbox-group label {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            font-weight: normal;
            cursor: pointer;
        }
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        .alert {
            padding: var(--space-md);
            border-radius: var(--radius-md);
            margin-bottom: var(--space-lg);
        }
        .alert-error {
            background: #fdecea;
            color: #d32f2f;
        }
        .success-section {
            padding: 12vh 0;
            text-align: center;
            background: #f0faf0;
        }
        .success-checkmark {
            width: 80px;
            height: 80px;
            margin: 0 auto var(--space-lg);
        }
        .success-checkmark circle {
            fill: none;
            stroke: var(--color-primary);
            stroke-width: 3;
            stroke-dasharray: 166;
            stroke-dashoffset: 166;
            animation: stroke-circle 0.6s ease-out forwards;
        }
        .success-checkmark path {
            fill: none;
            stroke: var(--color-primary);
            stroke-width: 3;
            stroke-linecap: round;
            stroke-linejoin: round;
            stroke-dasharray: 48;
            stroke-dashoffset: 48;
            animation: stroke-check 0.3s ease-out 0.4s forwards;
        }
        @keyframes stroke-circle {
            to { stroke-dashoffset: 0; }
        }
        @keyframes stroke-check {
            to { stroke-dashoffset: 0; }
        }
        .success-headline {
            font-size: 2.8rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: var(--space-md);
            line-height: 1.2;
        }
        .success-subline {
            color: var(--gray-600);
            max-width: 480px;
            margin: 0 auto;
            line-height: 1.6;
        }
        .success-backlink {
            display: inline-block;
            margin-top: 2rem;
            color: var(--gray-500);
            text-decoration: none;
            transition: color 0.2s;
        }
        .success-backlink:hover {
            color: var(--color-primary);
        }
        @media (max-width: 480px) {
            .success-headline {
                font-size: 2rem;
            }
            .success-section {
                padding: 8vh var(--space-md);
            }
        }
        .hp-field {
            position: absolute;
            left: -9999px;
        }
        .required::after {
            content: " *";
            color: #d32f2f;
        }
        .name-row {
            display: grid;
            grid-template-columns: 1fr;
            gap: var(--space-md);
        }
        .kuchen-row {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .kuchen-nuesse-label {
            display: flex;
            align-items: center;
            gap: var(--space-xs);
            font-weight: normal;
        }
        .kuchen-details {
            margin-left: calc(18px + var(--space-sm));
            margin-top: var(--space-sm);
            display: flex;
            flex-direction: column;
            gap: var(--space-sm);
        }
        .kuchen-art-input {
            width: 100%;
            padding: var(--space-sm);
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: var(--text-base);
            font-family: inherit;
        }
        .kuchen-warning {
            background: #fffbea;
            border: 1px solid #f59e0b;
            border-radius: var(--radius-md);
            padding: var(--space-sm) var(--space-md);
            font-size: var(--text-sm);
            line-height: 1.5;
        }
        @media (min-width: 600px) {
            .name-row {
                grid-template-columns: 1fr 1fr;
            }
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/src/layout/header.php'; ?>

    <main>
        <?php if ($success): ?>
        <section class="success-section">
            <div class="container">
                <svg class="success-checkmark" viewBox="0 0 52 52">
                    <circle cx="26" cy="26" r="24"/>
                    <path d="M14 27l7 7 16-16"/>
                </svg>
                <h1 class="success-headline">Du bist dabei!</h1>
                <p class="success-subline">Vielen Dank! Wir melden uns in Kürze mit allen Details per E-Mail.</p>
                <a href="index.html" class="success-backlink">← Zurück zur Startseite</a>
            </div>
        </section>
        <?php else: ?>
        <section class="helfer-section">
            <div class="container">
                <div class="helfer-form">
                    <h2 class="text-center">Helfer-Anmeldung</h2>
                    <p class="anmeldung-hinweis"><strong>HINWEIS:</strong> Für eindeutige Einteilungen braucht es bitte eine Anmeldung pro Helfer.</p>

                    <?php if ($error): ?>
                        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <?php if (!$tokenValid): ?>
                        <p>Die Helfer-Anmeldung ist derzeit nicht verfügbar.</p>
                    <?php else: ?>
                    <form method="post" action="orga/api/helfer_register.php">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="access_token" value="<?= htmlspecialchars($token) ?>">

                        <div class="hp-field" aria-hidden="true">
                            <label for="website">Website</label>
                            <input type="text" name="website" id="website" tabindex="-1" autocomplete="off">
                        </div>

                        <div class="name-row">
                            <div class="form-group">
                                <label for="vorname" class="required">Vorname</label>
                                <input type="text" id="vorname" name="vorname" required>
                            </div>
                            <div class="form-group">
                                <label for="nachname" class="required">Nachname</label>
                                <input type="text" id="nachname" name="nachname" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="email" class="required">E-Mail</label>
                            <input type="email" id="email" name="email" required>
                        </div>

                        <div class="form-group">
                            <label for="phone" class="required">Handynummer</label>
                            <input type="tel" id="phone" name="phone" required>
                            <p class="form-hint">Für schnelle Kommunikation am Veranstaltungstag.</p>
                        </div>

                        <div class="form-group">
                            <label>Wann und wobei kannst du helfen?</label>
                            <p class="form-hint">Wähle die Aufgaben aus, bei denen du dabei sein kannst – gern mehrere.</p>
                            <?php foreach (helferAufgabenKatalog() as $tag => $day): ?>
                                <div class="aufgaben-tag">
                                    <h3 class="aufgaben-tag-titel"><?= htmlspecialchars($day['label']) ?></h3>
                                    <table class="aufgaben-table">
                                        <thead>
                                            <tr>
                                                <th>Aufgabe</th>
                                                <th>Zeitfenster</th>
                                                <th class="aufgaben-check-col">Dabei?</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($day['aufgaben'] as $a): ?>
                                                <tr>
                                                    <td>
                                                        <label for="slot_<?= htmlspecialchars($a['key']) ?>"><?= htmlspecialchars($a['beschreibung']) ?></label>
                                                    </td>
                                                    <td class="aufgaben-zeit"><?= htmlspecialchars($a['zeitfenster']) ?></td>
                                                    <td class="aufgaben-check-col">
                                                        <input type="checkbox" name="slots[]" value="<?= htmlspecialchars($a['key']) ?>" id="slot_<?= htmlspecialchars($a['key']) ?>">
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="form-group">
                            <label>Was kannst du mitbringen oder beitragen?</label>
                            <p class="form-hint">Optional, aber sehr willkommen!</p>
                            <div class="checkbox-group">
                                <div class="kuchen-row">
                                    <label>
                                        <input type="checkbox" name="beitrag[]" value="kuchen" id="kuchen-checkbox">
                                        Kuchen / Gebäck
                                    </label>
                                    <label class="kuchen-nuesse-label">
                                        <input type="checkbox" name="kuchen_nuesse" id="kuchen_nuesse" value="ja">
                                        enthält Nüsse
                                    </label>
                                </div>
                                <div class="kuchen-details">
                                    <input type="text" id="kuchen_art" name="kuchen_art" placeholder="Art des Kuchens, z.B. Apfelkuchen, Muffins" class="kuchen-art-input">
                                    <div class="kuchen-warning">
                                        ⚠️ Bitte nur durchgebackene Produkte ohne rohe Eier oder ungekühlte Sahne. Kuchen mit Sahne bitte gekühlt transportieren. Allergene werden am Stand ausgehängt.
                                    </div>
                                </div>
                                <label>
                                    <input type="checkbox" name="beitrag[]" value="equipment">
                                    Equipment (Tische, Zelte, etc.)
                                </label>
                                <label style="align-items:flex-start">
                                    <input type="checkbox" name="beitrag[]" value="sonstiges" style="margin-top:0.2rem;flex-shrink:0">
                                    <span>Sonstige Unterstützung (wir suchen auch über den kompletten Zeitraum hinweg) – bitte gern näher beschreiben:</span>
                                </label>
                                <textarea id="beitrag_freitext" name="beitrag_freitext" rows="3" style="margin-left:calc(18px + 0.5rem);width:calc(100% - 18px - 0.5rem)"></textarea>
                            </div>
                        </div>

                        <fieldset class="foto-fieldset">
                            <legend>Fotoeinwilligung</legend>

                            <div class="form-group">
                                <p class="foto-step"><strong>Schritt 1:</strong> Für wen erfolgt die Anmeldung?</p>
                                <div class="foto-options">
                                    <label class="foto-option">
                                        <input type="radio" name="is_minor" value="0" id="minor_no" required>
                                        <span>Ich bin volljährig und melde mich selbst an.</span>
                                    </label>
                                    <label class="foto-option">
                                        <input type="radio" name="is_minor" value="1" id="minor_yes">
                                        <span>Ich bin erziehungsberechtigt und melde eine minderjährige Person an.</span>
                                    </label>
                                </div>
                            </div>

                            <div class="foto-consent-text" id="consent-text-adult">
                                Ich willige ein, dass der ATSV Kirchseeon e.V. Foto- und Videoaufnahmen von mir beim Marktlauf für die Öffentlichkeitsarbeit des Vereins (Website, Social Media, Presse und Vereinsarchiv) einschließlich der Bewerbung künftiger Veranstaltungen verwendet (Art. 6 Abs. 1 lit. a DSGVO). Die Einwilligung ist freiwillig, hat keinen Einfluss auf die Teilnahme und ist jederzeit mit Wirkung für die Zukunft widerrufbar an <a href="mailto:atsv@atsv-kirchseeon.de">atsv@atsv-kirchseeon.de</a>. Die <a href="https://atsv-kirchseeon-marktlauf.de/datenschutz.html" target="_blank" rel="noopener noreferrer">Datenschutzhinweise</a> habe ich gelesen.
                            </div>

                            <div class="foto-consent-text" id="consent-text-minor" hidden>
                                Ich willige als erziehungsberechtigte Person ein, dass der ATSV Kirchseeon e.V. Foto- und Videoaufnahmen des von mir angemeldeten Kindes beim Marktlauf für die Öffentlichkeitsarbeit des Vereins (Website, Social Media, Presse und Vereinsarchiv) einschließlich der Bewerbung künftiger Veranstaltungen verwendet (Art. 6 Abs. 1 lit. a DSGVO). Die Einwilligung ist freiwillig, hat keinen Einfluss auf die Teilnahme und ist jederzeit mit Wirkung für die Zukunft widerrufbar an <a href="mailto:atsv@atsv-kirchseeon.de">atsv@atsv-kirchseeon.de</a>. Die <a href="https://atsv-kirchseeon-marktlauf.de/datenschutz.html" target="_blank" rel="noopener noreferrer">Datenschutzhinweise</a> habe ich gelesen.
                            </div>

                            <div class="form-group">
                                <p class="foto-step"><strong>Schritt 2:</strong> Willigst du in die Nutzung der Aufnahmen ein?</p>
                                <div class="foto-options">
                                    <label class="foto-option">
                                        <input type="radio" name="consent_photo" value="yes" id="consent_yes" required>
                                        <span>Ja, ich willige ein.</span>
                                    </label>
                                    <label class="foto-option">
                                        <input type="radio" name="consent_photo" value="no" id="consent_no">
                                        <span>Nein, ich willige nicht ein.</span>
                                    </label>
                                </div>
                            </div>

                            <div class="form-group" id="guardian-name-group" hidden>
                                <label for="guardian_name" class="required">Vollständiger Name der erziehungsberechtigten Person</label>
                                <input type="text" id="guardian_name" name="guardian_name" maxlength="255">
                            </div>
                        </fieldset>

                        <button type="submit" class="btn btn-primary btn-block">Anmeldung absenden</button>
                    </form>

                    <script>
                    (function () {
                        const minorYes = document.getElementById('minor_yes');
                        const minorNo = document.getElementById('minor_no');
                        const adultText = document.getElementById('consent-text-adult');
                        const minorText = document.getElementById('consent-text-minor');
                        const guardianGroup = document.getElementById('guardian-name-group');
                        const guardianInput = document.getElementById('guardian_name');

                        function sync() {
                            const isMinor = minorYes.checked;
                            adultText.hidden = isMinor;
                            minorText.hidden = !isMinor;
                            guardianGroup.hidden = !isMinor;
                            guardianInput.required = isMinor;
                            if (!isMinor) { guardianInput.value = ''; }
                        }
                        minorYes.addEventListener('change', sync);
                        minorNo.addEventListener('change', sync);
                        sync();
                    })();
                    </script>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>
    </main>

    <?php require_once __DIR__ . '/src/layout/footer.php'; ?>
</body>
</html>
