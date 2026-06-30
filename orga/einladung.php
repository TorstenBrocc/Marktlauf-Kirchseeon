<?php
/**
 * Einladung annehmen — Passwort setzen (öffentlich, kein Login)
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';

initSession();

$token = trim($_GET['token'] ?? '');
$error = '';
$success = false;
$invite = null;
$user = null;

if ($token === '' || strlen($token) > 64) {
    $error = 'Ungültiger Einladungslink.';
} else {
    try {
        $pdo = getDbConnection();

        $stmt = $pdo->prepare('
            SELECT it.*, u.name, u.email, u.role
            FROM invite_tokens it
            JOIN users u ON it.user_id = u.id
            WHERE it.token = :token
        ');
        $stmt->execute(['token' => $token]);
        $invite = $stmt->fetch();

        if (!$invite) {
            $error = 'Einladungslink nicht gefunden.';
        } elseif ($invite['used_at']) {
            $error = 'Diese Einladung wurde bereits verwendet.';
        } elseif (strtotime($invite['expires_at']) < time()) {
            $error = 'Diese Einladung ist abgelaufen. Bitte fordere eine neue an.';
        } else {
            $user = $invite;
        }
    } catch (PDOException $e) {
        $error = 'Datenbankfehler.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user && !$error) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

    if (!verifyCsrfToken($csrfToken)) {
        $error = 'Ungültige Anfrage. Bitte erneut versuchen.';
    } elseif (strlen($password) < 12) {
        $error = 'Das Passwort muss mindestens 12 Zeichen lang sein.';
    } elseif ($password !== $passwordConfirm) {
        $error = 'Die Passwörter stimmen nicht überein.';
    } else {
        try {
            $pdo = getDbConnection();
            $pdo->beginTransaction();

            $hash = password_hash($password, PASSWORD_ARGON2ID);

            $updateUser = $pdo->prepare('UPDATE users SET pass_hash = :hash, active = 1 WHERE id = :id');
            $updateUser->execute(['hash' => $hash, 'id' => $invite['user_id']]);

            $updateToken = $pdo->prepare('UPDATE invite_tokens SET used_at = NOW() WHERE id = :id');
            $updateToken->execute(['id' => $invite['id']]);

            $pdo->commit();

            $_SESSION['flash_success'] = 'Dein Account ist aktiviert. Du kannst dich jetzt anmelden.';
            header('Location: login.php');
            exit;

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Fehler beim Speichern. Bitte erneut versuchen.';
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
    <title>Einladung annehmen | ATSV Kirchseeon Marktlauf</title>
    <?php require_once __DIR__ . '/../src/layout/head.php'; ?>
    <style>
        .invite-section {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: calc(100vh - 200px);
            padding: var(--space-xl) var(--space-md);
            background: var(--gray-100);
        }
        .invite-card {
            background: var(--white);
            padding: var(--space-xl);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            width: 100%;
            max-width: 450px;
        }
        .invite-card h1 {
            color: var(--color-primary);
            text-align: center;
            margin-bottom: var(--space-md);
            font-size: var(--text-2xl);
        }
        .invite-info {
            background: var(--gray-50);
            padding: var(--space-md);
            border-radius: var(--radius-md);
            margin-bottom: var(--space-lg);
            text-align: center;
        }
        .invite-info p {
            margin: 0;
            color: var(--gray-600);
        }
        .invite-info strong {
            color: var(--gray-800);
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
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(0, 150, 64, 0.15);
        }
        .form-hint {
            font-size: var(--text-sm);
            color: var(--gray-500);
            margin-top: var(--space-xs);
        }
        .alert {
            padding: var(--space-md);
            border-radius: var(--radius-md);
            margin-bottom: var(--space-md);
        }
        .alert-error {
            background: #fdecea;
            color: #d32f2f;
        }
        .error-card {
            text-align: center;
        }
        .error-icon {
            font-size: 3rem;
            margin-bottom: var(--space-md);
        }
        .error-card h1 {
            margin-bottom: var(--space-sm);
        }
        .error-card p {
            color: var(--gray-600);
            margin-bottom: var(--space-lg);
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../src/layout/header-minimal.php'; ?>

    <main>
        <section class="invite-section">
            <?php if ($error && !$user): ?>
            <div class="invite-card error-card">
                <div class="error-icon">⚠️</div>
                <h1>Einladung ungültig</h1>
                <p><?= htmlspecialchars($error) ?></p>
                <a href="login.php" class="btn btn-primary">Zum Login</a>
            </div>
            <?php elseif ($user): ?>
            <div class="invite-card">
                <h1>Willkommen!</h1>

                <div class="invite-info">
                    <p>Du wurdest eingeladen als</p>
                    <p><strong><?= htmlspecialchars($user['name']) ?></strong></p>
                    <p><?= htmlspecialchars($user['email']) ?></p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                    <div class="form-group">
                        <label for="password">Passwort</label>
                        <input type="password" id="password" name="password" required minlength="12" autofocus>
                        <p class="form-hint">Mindestens 12 Zeichen</p>
                    </div>

                    <div class="form-group">
                        <label for="password_confirm">Passwort bestätigen</label>
                        <input type="password" id="password_confirm" name="password_confirm" required minlength="12">
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">Account aktivieren</button>
                </form>
            </div>
            <?php endif; ?>
        </section>
    </main>

    <?php require_once __DIR__ . '/../src/layout/footer.php'; ?>
</body>
</html>
