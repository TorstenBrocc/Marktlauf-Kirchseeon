<?php
/**
 * Benutzerverwaltung (Admin only)
 */

declare(strict_types=1);

require_once __DIR__ . '/api/_auth.php';
require_once __DIR__ . '/../src/db.php';

$user = getCurrentUserFromGuard();
$isAdmin = isAdminFromGuard();

if (!$isAdmin) {
    $_SESSION['flash_error'] = 'Nur Admins haben Zugriff auf die Benutzerverwaltung.';
    header('Location: index.php');
    exit;
}

$csrfToken = generateCsrfToken();

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
$flashResetLink = $_SESSION['flash_reset_link'] ?? '';
$flashResetName = $_SESSION['flash_reset_name'] ?? '';
$flashResetMailOk = $_SESSION['flash_reset_mail_ok'] ?? false;
unset($_SESSION['flash_success'], $_SESSION['flash_error'], $_SESSION['flash_reset_link'], $_SESSION['flash_reset_name'], $_SESSION['flash_reset_mail_ok']);

$pdo = getDbConnection();
$stmt = $pdo->query('SELECT * FROM users ORDER BY created_at DESC');
$users = $stmt->fetchAll();

$pendingInvites = [];
try {
    $inviteStmt = $pdo->query('
        SELECT it.*, u.email, u.name
        FROM invite_tokens it
        JOIN users u ON it.user_id = u.id
        WHERE it.used_at IS NULL AND it.expires_at > NOW()
        ORDER BY it.created_at DESC
    ');
    $pendingInvites = $inviteStmt->fetchAll();
} catch (PDOException $e) {
    // Table may not exist yet
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Benutzerverwaltung | ATSV Kirchseeon Marktlauf</title>
    <link rel="stylesheet" href="css/orga.css?v=<?= @filemtime(__DIR__ . '/css/orga.css') ?>">
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo-final.svg">
    <style>
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--white);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .data-table th,
        .data-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        .data-table th {
            background: var(--bg);
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-light);
        }
        .data-table tr:hover {
            background: #fafafa;
        }
        .data-table td {
            font-size: 0.875rem;
            vertical-align: middle;
        }
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .status-active { background: var(--success-bg); color: var(--success); }
        .status-inactive { background: var(--error-bg); color: var(--error); }
        .status-pending { background: #fff3cd; color: #856404; }
        .role-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .role-admin { background: #e3f2fd; color: #1565c0; }
        .role-orga { background: var(--bg); color: var(--text-light); }
        .table-wrap {
            overflow-x: auto;
        }
        .inline-form {
            display: inline;
        }
        .invite-form {
            background: var(--white);
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }
        .invite-form h2 {
            font-size: 1.1rem;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--border);
        }
        .invite-row {
            display: grid;
            grid-template-columns: 1fr 1fr 150px auto;
            gap: 1rem;
            align-items: end;
        }
        .invite-row input,
        .invite-row select {
            padding: 0.5rem;
            border: 1px solid var(--border);
            border-radius: 4px;
        }
        .invite-row label {
            font-size: 0.75rem;
            color: var(--text-light);
            margin-bottom: 0.25rem;
            display: block;
        }
        .section-title {
            font-size: 1rem;
            margin: 2rem 0 1rem;
            color: var(--text-light);
        }
        .you-badge {
            font-size: 0.625rem;
            background: var(--primary);
            color: white;
            padding: 0.125rem 0.375rem;
            border-radius: 3px;
            margin-left: 0.5rem;
        }
        .reset-link-box {
            background: #e8f5e9;
            border: 1px solid var(--primary);
            border-radius: 8px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
        }
        .reset-link-box strong { display: block; margin-bottom: 0.25rem; }
        .reset-link-box p {
            margin: 0 0 0.75rem 0;
            font-size: 0.85rem;
            color: var(--text-light);
        }
        .reset-link-row {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        .reset-link-row input {
            flex: 1;
            min-width: 0;
            padding: 0.5rem;
            border: 1px solid var(--border);
            border-radius: 4px;
            font-family: monospace;
            font-size: 0.8rem;
            background: var(--white);
        }
        .reset-link-row .btn { white-space: nowrap; }
        @media (max-width: 800px) {
            .invite-row {
                grid-template-columns: 1fr;
            }
            .reset-link-row { flex-direction: column; align-items: stretch; }
        }
    </style>
</head>
<body>
<?php $activeNav = 'benutzer'; require __DIR__ . '/_sidebar.php'; ?>

        <main class="main-content">
            <div class="page-header">
                <h1>Benutzerverwaltung</h1>
            </div>

            <?php if ($flashSuccess): ?>
                <div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div>
            <?php endif; ?>

            <?php if ($flashError): ?>
                <div class="alert alert-error"><?= htmlspecialchars($flashError) ?></div>
            <?php endif; ?>

            <?php if ($flashResetLink): ?>
                <div class="reset-link-box">
                    <strong>Passwort-Reset-Link für <?= htmlspecialchars($flashResetName) ?></strong>
                    <p><?= $flashResetMailOk ? 'Wurde per E-Mail an den Benutzer gesendet.' : 'E-Mail-Versand fehlgeschlagen — bitte den Link manuell weitergeben.' ?> 48 Stunden gültig.</p>
                    <div class="reset-link-row">
                        <input type="text" id="reset-link-input" readonly value="<?= htmlspecialchars($flashResetLink) ?>" onclick="this.select()">
                        <button type="button" class="btn btn-primary" id="reset-copy-btn" onclick="copyResetLink()">Kopieren</button>
                    </div>
                </div>
            <?php endif; ?>

            <div class="invite-form">
                <h2>Neuen Benutzer einladen</h2>
                <form method="post" action="api/user_invite.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <div class="invite-row">
                        <div>
                            <label for="name">Name</label>
                            <input type="text" id="name" name="name" required placeholder="Max Mustermann">
                        </div>
                        <div>
                            <label for="email">E-Mail</label>
                            <input type="email" id="email" name="email" required placeholder="max@example.com">
                        </div>
                        <div>
                            <label for="role">Rolle</label>
                            <select id="role" name="role">
                                <option value="orga">Orga</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Einladen</button>
                    </div>
                </form>
            </div>

            <?php if (!empty($pendingInvites)): ?>
            <h3 class="section-title">Offene Einladungen</h3>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>E-Mail</th>
                            <th>Gültig bis</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingInvites as $inv): ?>
                            <tr>
                                <td><?= htmlspecialchars($inv['name']) ?></td>
                                <td><?= htmlspecialchars($inv['email']) ?></td>
                                <td><?= date('d.m.Y H:i', strtotime($inv['expires_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <h3 class="section-title">Alle Benutzer</h3>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>E-Mail</th>
                            <th>Rolle</th>
                            <th>Status</th>
                            <th>Erstellt am</th>
                            <th>Aktion</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars($u['name']) ?>
                                    <?php if ($u['id'] == $user['id']): ?>
                                        <span class="you-badge">Du</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td>
                                    <span class="role-badge role-<?= $u['role'] ?>"><?= ucfirst($u['role']) ?></span>
                                </td>
                                <td>
                                    <?php if ($u['active'] && $u['pass_hash']): ?>
                                        <span class="status-badge status-active">Aktiv</span>
                                    <?php elseif (!$u['pass_hash']): ?>
                                        <span class="status-badge status-pending">Eingeladen</span>
                                    <?php else: ?>
                                        <span class="status-badge status-inactive">Inaktiv</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('d.m.Y', strtotime($u['created_at'])) ?></td>
                                <td>
                                    <a href="benutzer_edit.php?id=<?= $u['id'] ?>" class="btn-action">Bearbeiten</a>
                                    <?php if ($u['id'] != $user['id']): ?>
                                        <form method="post" action="api/user_reset_link.php" class="inline-form">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <button type="submit" class="btn-action" onclick="return confirm('Passwort-Reset-Link für <?= htmlspecialchars($u['name'], ENT_QUOTES) ?> erzeugen?')">Reset-Link</button>
                                        </form>
                                        <?php if ($u['active']): ?>
                                            <form method="post" action="api/user_deactivate.php" class="inline-form">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <input type="hidden" name="action" value="deactivate">
                                                <button type="submit" class="btn-action btn-danger" onclick="return confirm('Benutzer deaktivieren?')">Deaktivieren</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="post" action="api/user_deactivate.php" class="inline-form">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <input type="hidden" name="action" value="activate">
                                                <button type="submit" class="btn-action btn-success">Aktivieren</button>
                                            </form>
                                            <form method="post" action="api/user_delete.php" class="inline-form">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <button type="submit" class="btn-action btn-danger" onclick="return confirm('Benutzer „<?= htmlspecialchars($u['name'], ENT_QUOTES) ?>“ endgültig löschen? Das kann nicht rückgängig gemacht werden.')">Löschen</button>
                                            </form>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    <script>
    (function() {
        const burger = document.getElementById('burger-btn');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');

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

    function copyResetLink() {
        var input = document.getElementById('reset-link-input');
        var btn = document.getElementById('reset-copy-btn');
        if (!input) return;
        input.select();
        var done = function () {
            var label = btn.textContent;
            btn.textContent = 'Kopiert ✓';
            setTimeout(function () { btn.textContent = label; }, 1500);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(input.value).then(done).catch(function () { document.execCommand('copy'); done(); });
        } else {
            document.execCommand('copy');
            done();
        }
    }
    </script>
</body>
</html>
