<?php
/**
 * Dateien-Verwaltung (Admin + Orga)
 */

declare(strict_types=1);

require_once __DIR__ . '/api/_auth.php';
require_once __DIR__ . '/../src/db.php';

$user = getCurrentUserFromGuard();
$isAdmin = isAdminFromGuard();
$csrfToken = generateCsrfToken();

$flashSuccess = $_SESSION['flash_success'] ?? '';
$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$activeTab = $_GET['tab'] ?? 'orga';
if (!in_array($activeTab, ['orga', 'helfer'], true)) {
    $activeTab = 'orga';
}

$pdo = getDbConnection();

$orgaDateien = [];
$helferDateien = [];

try {
    $stmt = $pdo->query('
        SELECT d.*, u.name as uploader_name
        FROM dateien d
        JOIN users u ON d.hochgeladen_von = u.id
        ORDER BY d.created_at DESC
    ');
    while ($row = $stmt->fetch()) {
        if ($row['bereich'] === 'orga') {
            $orgaDateien[] = $row;
        } else {
            $helferDateien[] = $row;
        }
    }
} catch (PDOException $e) {
    // Table may not exist yet
}

function formatFileSize(int $bytes): string {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 0, ',', '.') . ' KB';
    }
    return $bytes . ' B';
}

function getFileIcon(string $mimetype): string {
    return match (true) {
        str_contains($mimetype, 'pdf') => '📄',
        str_contains($mimetype, 'word') => '📝',
        str_contains($mimetype, 'sheet') => '📊',
        str_contains($mimetype, 'image') => '🖼️',
        default => '📁',
    };
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Dateien | ATSV Kirchseeon Marktlauf</title>
    <link rel="stylesheet" href="css/orga.css">
    <style>
        .tabs {
            display: flex;
            gap: 0;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid var(--border);
        }
        .tab {
            padding: 0.75rem 1.5rem;
            background: none;
            border: none;
            font-size: 1rem;
            cursor: pointer;
            color: var(--text-light);
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            text-decoration: none;
        }
        .tab:hover {
            color: var(--text);
        }
        .tab.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
            font-weight: 500;
        }
        .tab-count {
            background: var(--bg);
            padding: 0.125rem 0.5rem;
            border-radius: 10px;
            font-size: 0.75rem;
            margin-left: 0.5rem;
        }
        .tab.active .tab-count {
            background: rgba(0, 150, 64, 0.1);
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .upload-form {
            background: var(--white);
            padding: 1rem 1.5rem;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }
        .upload-form input[type="file"] {
            flex: 1;
            min-width: 200px;
        }
        .upload-hint {
            width: 100%;
            font-size: 0.75rem;
            color: var(--text-light);
            margin-top: 0.25rem;
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
        .file-icon {
            font-size: 1.25rem;
            margin-right: 0.5rem;
        }
        .file-name {
            font-weight: 500;
        }
        .file-meta {
            font-size: 0.75rem;
            color: var(--text-light);
        }
        .btn-download {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background: var(--primary);
            color: white;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.75rem;
        }
        .btn-download:hover {
            background: var(--primary-dark);
        }
        .btn-action {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            background: var(--border);
            color: var(--text);
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-action:hover {
            background: #ccc;
        }
        .btn-danger {
            background: var(--error-bg);
            color: var(--error);
        }
        .btn-danger:hover {
            background: var(--error);
            color: white;
        }
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-light);
        }
        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        .table-wrap {
            overflow-x: auto;
        }
        .inline-form {
            display: inline;
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <nav class="sidebar">
            <div class="sidebar-header">
                <h2>Marktlauf Orga</h2>
            </div>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="index.php">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a href="helfer.php">Helfer</a>
                </li>
                <li class="nav-item">
                    <a href="sponsoren.php">Sponsoren</a>
                </li>
                <li class="nav-item active">
                    <a href="dateien.php">Dateien</a>
                </li>
                <li class="nav-item">
                    <a href="ticker.php">Live-Ticker</a>
                    <span class="badge">Phase 3</span>
                </li>
                <?php if ($isAdmin): ?>
                <li class="nav-section">Admin</li>
                <li class="nav-item">
                    <a href="benutzer.php">Benutzerverwaltung</a>
                    
                </li>
                <li class="nav-item">
                    <a href="einstellungen.php">Einstellungen</a>
                </li>
                <?php endif; ?>
            </ul>
            <div class="sidebar-footer">
                <div class="user-info">
                    <span class="user-name"><?= htmlspecialchars($user['name']) ?></span>
                    <span class="user-role"><?= htmlspecialchars(ucfirst($user['role'])) ?></span>
                </div>
                <a href="logout.php" class="btn btn-small btn-secondary">Abmelden</a>
            </div>
        </nav>

        <main class="main-content">
            <header class="content-header">
                <h1>Dateien</h1>
            </header>

            <?php if ($flashSuccess): ?>
                <div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div>
            <?php endif; ?>

            <?php if ($flashError): ?>
                <div class="alert alert-error"><?= htmlspecialchars($flashError) ?></div>
            <?php endif; ?>

            <div class="tabs">
                <a href="?tab=orga" class="tab <?= $activeTab === 'orga' ? 'active' : '' ?>">
                    Orga-Dateien
                    <span class="tab-count"><?= count($orgaDateien) ?></span>
                </a>
                <a href="?tab=helfer" class="tab <?= $activeTab === 'helfer' ? 'active' : '' ?>">
                    Helfer-Dateien
                    <span class="tab-count"><?= count($helferDateien) ?></span>
                </a>
            </div>

            <div id="tab-orga" class="tab-content <?= $activeTab === 'orga' ? 'active' : '' ?>">
                <form method="post" action="api/file_upload.php" enctype="multipart/form-data" class="upload-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="bereich" value="orga">
                    <input type="file" name="datei" required accept=".pdf,.docx,.xlsx,.png,.jpg,.jpeg">
                    <button type="submit" class="btn btn-primary btn-small">Hochladen</button>
                    <span class="upload-hint">Erlaubt: PDF, DOCX, XLSX, PNG, JPG — max. 10 MB. Nur für Orga-Team sichtbar.</span>
                </form>

                <?php if (empty($orgaDateien)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📁</div>
                        <p>Noch keine Orga-Dateien hochgeladen.</p>
                    </div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Datei</th>
                                    <th>Größe</th>
                                    <th>Hochgeladen von</th>
                                    <th>Datum</th>
                                    <th>Aktionen</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orgaDateien as $d): ?>
                                    <tr>
                                        <td>
                                            <span class="file-icon"><?= getFileIcon($d['mimetype']) ?></span>
                                            <span class="file-name"><?= htmlspecialchars($d['originalname']) ?></span>
                                        </td>
                                        <td class="file-meta"><?= formatFileSize((int)$d['groesse']) ?></td>
                                        <td class="file-meta"><?= htmlspecialchars($d['uploader_name']) ?></td>
                                        <td class="file-meta"><?= date('d.m.Y H:i', strtotime($d['created_at'])) ?></td>
                                        <td>
                                            <a href="api/file_download.php?id=<?= $d['id'] ?>" class="btn-download">Download</a>
                                            <?php if ($isAdmin || $d['hochgeladen_von'] == $user['id']): ?>
                                                <form method="post" action="api/file_delete.php" class="inline-form" onsubmit="return confirm('Datei wirklich löschen?');">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                    <input type="hidden" name="file_id" value="<?= $d['id'] ?>">
                                                    <button type="submit" class="btn-action btn-danger">Löschen</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div id="tab-helfer" class="tab-content <?= $activeTab === 'helfer' ? 'active' : '' ?>">
                <form method="post" action="api/file_upload.php" enctype="multipart/form-data" class="upload-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="bereich" value="helfer">
                    <input type="file" name="datei" required accept=".pdf,.docx,.xlsx,.png,.jpg,.jpeg">
                    <button type="submit" class="btn btn-primary btn-small">Hochladen</button>
                    <span class="upload-hint">Erlaubt: PDF, DOCX, XLSX, PNG, JPG — max. 10 MB. Für alle bestätigten Helfer sichtbar.</span>
                </form>

                <?php if (empty($helferDateien)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📁</div>
                        <p>Noch keine Helfer-Dateien hochgeladen.</p>
                    </div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Datei</th>
                                    <th>Größe</th>
                                    <th>Hochgeladen von</th>
                                    <th>Datum</th>
                                    <th>Aktionen</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($helferDateien as $d): ?>
                                    <tr>
                                        <td>
                                            <span class="file-icon"><?= getFileIcon($d['mimetype']) ?></span>
                                            <span class="file-name"><?= htmlspecialchars($d['originalname']) ?></span>
                                        </td>
                                        <td class="file-meta"><?= formatFileSize((int)$d['groesse']) ?></td>
                                        <td class="file-meta"><?= htmlspecialchars($d['uploader_name']) ?></td>
                                        <td class="file-meta"><?= date('d.m.Y H:i', strtotime($d['created_at'])) ?></td>
                                        <td>
                                            <a href="api/file_download.php?id=<?= $d['id'] ?>" class="btn-download">Download</a>
                                            <?php if ($isAdmin || $d['hochgeladen_von'] == $user['id']): ?>
                                                <form method="post" action="api/file_delete.php" class="inline-form" onsubmit="return confirm('Datei wirklich löschen?');">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                    <input type="hidden" name="file_id" value="<?= $d['id'] ?>">
                                                    <button type="submit" class="btn-action btn-danger">Löschen</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
