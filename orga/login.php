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
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Orga Login | ATSV Kirchseeon Marktlauf</title>
    <link rel="stylesheet" href="css/orga.css">
</head>
<body class="login-page">
    <main class="login-container">
        <h1>Orga Login</h1>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" action="login.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <div class="form-group">
                <label for="email">E-Mail</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required autofocus>
            </div>

            <div class="form-group">
                <label for="password">Passwort</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" class="btn btn-primary">Anmelden</button>
        </form>

        <p class="login-footer">
            <a href="../index.html">Zurück zur Startseite</a>
        </p>
    </main>
</body>
</html>
