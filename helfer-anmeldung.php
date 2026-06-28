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
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Helfer-Anmeldung | ATSV Kirchseeon Marktlauf</title>
    <meta name="description" content="Melde dich als Helfer beim ATSV Kirchseeon Marktlauf an und unterstütze unser Team.">
    <link rel="stylesheet" href="css/main.css">
    <style>
        .helfer-form {
            max-width: 600px;
            margin: 2rem auto;
            padding: 2rem;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .helfer-form h1 {
            color: #009640;
            margin-bottom: 1rem;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="tel"],
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #009640;
            box-shadow: 0 0 0 2px rgba(0, 150, 64, 0.2);
        }
        .form-hint {
            font-size: 0.875rem;
            color: #666;
            margin-top: 0.25rem;
        }
        .timetable {
            display: grid;
            grid-template-columns: auto repeat(2, 1fr);
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        .timetable-header {
            font-weight: 500;
            text-align: center;
            padding: 0.5rem;
            background: #f5f5f5;
        }
        .timetable-day {
            padding: 0.5rem;
            background: #f5f5f5;
        }
        .timetable-cell {
            text-align: center;
            padding: 0.5rem;
        }
        .timetable-cell input {
            width: 20px;
            height: 20px;
        }
        .checkbox-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        .checkbox-group label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: normal;
            cursor: pointer;
        }
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
        }
        .btn-submit {
            display: inline-block;
            padding: 1rem 2rem;
            background: #009640;
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            width: 100%;
        }
        .btn-submit:hover {
            background: #007a34;
        }
        .alert {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
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
            gap: 1rem;
        }
        @media (min-width: 600px) {
            .name-row {
                grid-template-columns: 1fr 1fr;
            }
        }
    </style>
</head>
<body>
    <header>
        <nav>
            <a href="index.html">Zurück zur Startseite</a>
        </nav>
    </header>

    <main>
        <div class="helfer-form">
            <h1>Helfer-Anmeldung</h1>
            <p>Werde Teil unseres Teams beim ATSV Kirchseeon Marktlauf! Fülle das Formular aus und wir melden uns bei dir.</p>

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

                <button type="submit" class="btn-submit">Anmeldung absenden</button>
            </form>
            <?php endif; ?>
        </div>
    </main>

    <footer style="text-align:center">
        <p><a href="impressum.html">Impressum & Datenschutz</a></p>
    </footer>
</body>
</html>
