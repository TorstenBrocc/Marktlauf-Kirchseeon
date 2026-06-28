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
        .helfer-form h1 {
            color: var(--color-primary);
            margin-bottom: var(--space-lg);
            font-size: clamp(2rem, 5vw, 3.5rem);
            font-weight: 700;
            line-height: 1.2;
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
        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
        }
        .alert-error {
            background: #fdecea;
            color: #d32f2f;
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
        <section class="helfer-section">
            <div class="container">
                <div class="helfer-form">
                    <h1>Helfer-Anmeldung</h1>

                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <strong>Vielen Dank!</strong> Deine Anmeldung ist eingegangen. Du erhältst in Kürze eine Bestätigungs-E-Mail.
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <?php if (!$tokenValid && !$success): ?>
                        <p>Die Helfer-Anmeldung ist derzeit nicht verfügbar.</p>
                    <?php elseif (!$success): ?>
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
                                <label>
                                    <input type="checkbox" name="beitrag[]" value="kuchen">
                                    Kuchen / Gebäck
                                </label>
                                <label>
                                    <input type="checkbox" name="beitrag[]" value="getraenke">
                                    Getränke
                                </label>
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
    </main>

    <?php require_once __DIR__ . '/src/layout/footer.php'; ?>
</body>
</html>
