<?php
/**
 * Orga Login
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';

initSession();

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrfToken)) {
        $error = 'Ungültige Anfrage. Bitte erneut versuchen.';
    } elseif (empty($email) || empty($password)) {
        $error = 'Bitte E-Mail und Passwort eingeben.';
    } elseif (isLoginRateLimited($email)) {
        $error = 'Zu viele Anmeldeversuche. Bitte später erneut versuchen.';
    } else {
        $user = attemptLogin($email, $password);

        if ($user) {
            cleanupOldLoginAttempts();
            header('Location: index.php');
            exit;
        } else {
            $error = 'E-Mail oder Passwort falsch.';
        }
    }
}

$csrfToken = generateCsrfToken();
$basePath = '../';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Orga Login | ATSV Kirchseeon Marktlauf</title>
    <?php require_once __DIR__ . '/../src/layout/head.php'; ?>
    <style>
        .login-section {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: calc(100vh - 200px);
            padding: var(--space-xl) var(--space-md);
            background: var(--gray-100);
        }
        .login-card {
            background: var(--white);
            padding: var(--space-xl);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            width: 100%;
            max-width: 400px;
        }
        .login-card h1 {
            color: var(--primary);
            text-align: center;
            margin-bottom: var(--space-lg);
            font-size: var(--text-2xl);
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
        .form-group input {
            width: 100%;
            padding: var(--space-sm);
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: var(--text-base);
            font-family: inherit;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(0, 150, 64, 0.15);
        }
        .alert {
            padding: var(--space-md);
            border-radius: var(--radius-md);
            margin-bottom: var(--space-md);
            background: #fdecea;
            color: #d32f2f;
        }
        .pw-wrapper {
            position: relative;
        }
        .pw-toggle {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            padding: 0.25rem;
            color: var(--gray-500);
        }
        .pw-toggle:hover {
            color: var(--gray-700);
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../src/layout/header-minimal.php'; ?>

    <main>
        <section class="login-section">
            <div class="login-card">
                <h1>Orga Login</h1>

                <?php if ($error): ?>
                    <div class="alert"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="post" action="login.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                    <div class="form-group">
                        <label for="email">E-Mail</label>
                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required autofocus>
                    </div>

                    <div class="form-group">
                        <label for="password">Passwort</label>
                        <div class="pw-wrapper">
                            <input type="password" id="password" name="password" required style="padding-right:2.5rem">
                            <button type="button" id="pw-toggle" class="pw-toggle" aria-label="Passwort anzeigen">
                                <svg id="pw-icon-show" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg id="pw-icon-hide" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">Anmelden</button>
                </form>
            </div>
        </section>
    </main>

    <?php require_once __DIR__ . '/../src/layout/footer.php'; ?>

    <script>
    document.getElementById('pw-toggle').addEventListener('click', function() {
        var pw = document.getElementById('password');
        var show = document.getElementById('pw-icon-show');
        var hide = document.getElementById('pw-icon-hide');
        if (pw.type === 'password') {
            pw.type = 'text';
            show.style.display = 'none';
            hide.style.display = 'block';
            this.setAttribute('aria-label', 'Passwort verbergen');
        } else {
            pw.type = 'password';
            show.style.display = 'block';
            hide.style.display = 'none';
            this.setAttribute('aria-label', 'Passwort anzeigen');
        }
    });
    </script>
</body>
</html>
