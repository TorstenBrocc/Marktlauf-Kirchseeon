<?php
/**
 * Öffentliches Helfer-Anmeldeformular (Token-gegatet)
 */

declare(strict_types=1);

require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/src/db.php';

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
        .timetable {
            display: grid;
            grid-template-columns: auto repeat(2, 1fr);
            gap: var(--space-xs);
            margin-top: var(--space-sm);
        }
        .timetable-header {
            font-weight: 600;
            text-align: center;
            padding: var(--space-sm);
            background: var(--gray-100);
            border-radius: var(--radius-sm);
        }
        .timetable-day {
            padding: var(--space-sm);
            background: var(--gray-100);
            border-radius: var(--radius-sm);
        }
        .timetable-cell {
            text-align: center;
            padding: var(--space-sm);
        }
        .timetable-cell input {
            width: 20px;
            height: 20px;
            cursor: pointer;
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
                            <label class="required">Wann hast du Zeit?</label>
                            <p class="form-hint">Markiere die Zeitfenster, in denen du helfen kannst.</p>
                            <div class="timetable">
                                <div></div>
                                <div class="timetable-header">Vormittag</div>
                                <div class="timetable-header">Nachmittag</div>

                                <div class="timetable-day">Freitag (Aufbau)</div>
                                <div class="timetable-cell">
                                    <input type="checkbox" name="slots[]" value="2026-10-09_vormittag" id="slot_fr_vm">
                                </div>
                                <div class="timetable-cell">
                                    <input type="checkbox" name="slots[]" value="2026-10-09_nachmittag" id="slot_fr_nm">
                                </div>

                                <div class="timetable-day">Samstag (Aufbau)</div>
                                <div class="timetable-cell">
                                    <input type="checkbox" name="slots[]" value="2026-10-10_vormittag" id="slot_sa_vm">
                                </div>
                                <div class="timetable-cell">
                                    <input type="checkbox" name="slots[]" value="2026-10-10_nachmittag" id="slot_sa_nm">
                                </div>

                                <div class="timetable-day">Sonntag (Renntag)</div>
                                <div class="timetable-cell">
                                    <input type="checkbox" name="slots[]" value="2026-10-11_vormittag" id="slot_so_vm">
                                </div>
                                <div class="timetable-cell">
                                    <input type="checkbox" name="slots[]" value="2026-10-11_nachmittag" id="slot_so_nm">
                                </div>
                            </div>
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

                        <button type="submit" class="btn btn-primary btn-block">Anmeldung absenden</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>
    </main>

    <?php require_once __DIR__ . '/src/layout/footer.php'; ?>
</body>
</html>
