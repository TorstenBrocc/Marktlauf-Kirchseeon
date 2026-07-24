<?php
/**
 * Verein / Laufevent anlegen oder bearbeiten.
 * Speichert über api/verein_crud.php (action=create|update).
 */

declare(strict_types=1);

require_once __DIR__ . '/api/_auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/verein_status.php';

$user = getCurrentUserFromGuard();
$isAdmin = isAdminFromGuard();
$csrfToken = generateCsrfToken();

$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_error']);

$id = (int) ($_GET['id'] ?? 0);
$isEdit = $id > 0;
$eintrag = null;

if ($isEdit) {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare('SELECT * FROM vereine WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $eintrag = $stmt->fetch();
    if (!$eintrag) {
        $_SESSION['flash_error'] = 'Eintrag nicht gefunden.';
        header('Location: vereine.php');
        exit;
    }
}

$v = static function (string $key, $default = '') use ($eintrag) {
    return htmlspecialchars((string) ($eintrag[$key] ?? $default));
};
$sel = static function (string $key, string $val) use ($eintrag): string {
    return (($eintrag[$key] ?? '') === $val) ? 'selected' : '';
};

$pageTitle = $isEdit ? 'Eintrag bearbeiten' : 'Neuer Eintrag';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $pageTitle ?> | ATSV Kirchseeon Marktlauf</title>
    <link rel="stylesheet" href="css/orga.css?v=<?= @filemtime(__DIR__ . '/css/orga.css') ?>">
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo-final.svg">
    <style>
        .form-container { max-width: 820px; }
        .form-card { background: var(--white); padding: 1.5rem; border-radius: 8px; box-shadow: var(--shadow-card); margin-bottom: 1.5rem; }
        .form-card h2 { font-size: 1.05rem; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid var(--border); }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; }
        .form-actions { display: flex; gap: 1rem; margin: 1.5rem 0; }
        .form-hint { font-size: 0.8rem; color: var(--text-light); margin-top: 0.25rem; }
        .field-laufevent { display: none; }
        body.kat-laufevent .field-laufevent { display: block; }
    </style>
</head>
<body class="kat-<?= htmlspecialchars((string) ($eintrag['kategorie'] ?? 'verein')) ?>">
<?php $activeNav = 'vereine'; require __DIR__ . '/_sidebar.php'; ?>

        <main class="main-content">
            <div class="content-header">
                <h1><?= $pageTitle ?></h1>
            </div>

            <?php if ($flashError): ?>
                <div class="alert alert-error"><?= htmlspecialchars($flashError) ?></div>
            <?php endif; ?>

            <div class="form-container">
                <form method="post" action="api/verein_crud.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="action" value="<?= $isEdit ? 'update' : 'create' ?>">
                    <?php if ($isEdit): ?><input type="hidden" name="verein_id" value="<?= $id ?>"><?php endif; ?>

                    <div class="form-card">
                        <h2>Grunddaten</h2>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="kategorie">Kategorie</label>
                                <select id="kategorie" name="kategorie">
                                    <option value="verein" <?= $sel('kategorie', 'verein') ?>>Sportverein</option>
                                    <option value="laufevent" <?= $sel('kategorie', 'laufevent') ?>>Laufevent</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select id="status" name="status">
                                    <?php foreach (VEREIN_STATUS as $key => $meta): ?>
                                        <option value="<?= $key ?>" <?= $sel('status', $key) ?>><?= htmlspecialchars($meta['label']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="name">Name (Verein bzw. Event) *</label>
                            <input type="text" id="name" name="name" maxlength="255" required value="<?= $v('name') ?>">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="ort">Ort</label>
                                <input type="text" id="ort" name="ort" maxlength="120" value="<?= $v('ort') ?>">
                            </div>
                            <div class="form-group">
                                <label for="entfernung">Entfernung (ca.)</label>
                                <input type="text" id="entfernung" name="entfernung" maxlength="32" placeholder="~6 km" value="<?= $v('entfernung') ?>">
                            </div>
                        </div>
                        <div class="form-row field-laufevent">
                            <div class="form-group">
                                <label for="veranstalter">Veranstalter (nur Laufevent)</label>
                                <input type="text" id="veranstalter" name="veranstalter" maxlength="255" value="<?= $v('veranstalter') ?>">
                            </div>
                            <div class="form-group">
                                <label for="termin">Termin (nur Laufevent)</label>
                                <input type="text" id="termin" name="termin" maxlength="120" placeholder="z. B. Mai 2026" value="<?= $v('termin') ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="relevanz">Laufsport-Relevanz / Distanzen</label>
                            <textarea id="relevanz" name="relevanz" rows="2" maxlength="255"><?= $v('relevanz') ?></textarea>
                        </div>
                    </div>

                    <div class="form-card">
                        <h2>Ansprechpartner &amp; Kontakt</h2>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="anrede">Anrede</label>
                                <select id="anrede" name="anrede">
                                    <option value="" <?= $sel('anrede', '') ?>>–</option>
                                    <option value="Frau" <?= $sel('anrede', 'Frau') ?>>Frau</option>
                                    <option value="Herr" <?= $sel('anrede', 'Herr') ?>>Herr</option>
                                    <option value="Divers" <?= $sel('anrede', 'Divers') ?>>Divers</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="vorname">Vorname</label>
                                <input type="text" id="vorname" name="vorname" maxlength="128" value="<?= $v('vorname') ?>">
                            </div>
                            <div class="form-group">
                                <label for="nachname">Nachname</label>
                                <input type="text" id="nachname" name="nachname" maxlength="128" value="<?= $v('nachname') ?>">
                            </div>
                            <div class="form-group">
                                <label for="funktion">Funktion</label>
                                <input type="text" id="funktion" name="funktion" maxlength="120" placeholder="1. Vorstand" value="<?= $v('funktion') ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="email">E-Mail</label>
                                <input type="email" id="email" name="email" maxlength="255" value="<?= $v('email') ?>">
                                <p class="form-hint">Ohne E-Mail kein Versand möglich.</p>
                            </div>
                            <div class="form-group">
                                <label for="telefon">Telefon</label>
                                <input type="text" id="telefon" name="telefon" maxlength="64" value="<?= $v('telefon') ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="anschrift">Anschrift</label>
                            <input type="text" id="anschrift" name="anschrift" maxlength="255" value="<?= $v('anschrift') ?>">
                        </div>
                    </div>

                    <div class="form-card">
                        <h2>Online &amp; Quellen</h2>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="website">Website</label>
                                <input type="text" id="website" name="website" maxlength="255" value="<?= $v('website') ?>">
                            </div>
                            <div class="form-group">
                                <label for="social">Social Media</label>
                                <input type="text" id="social" name="social" maxlength="255" placeholder="Instagram/Facebook/Strava" value="<?= $v('social') ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="quelle">Impressum / Quelle</label>
                            <input type="text" id="quelle" name="quelle" maxlength="255" value="<?= $v('quelle') ?>">
                        </div>
                        <div class="form-group">
                            <label for="hinweis">Hinweis</label>
                            <textarea id="hinweis" name="hinweis" rows="2" maxlength="500"><?= $v('hinweis') ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="notizen">Interne Notizen</label>
                            <textarea id="notizen" name="notizen" rows="3"><?= $v('notizen') ?></textarea>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Speichern' : 'Anlegen' ?></button>
                        <a href="vereine.php" class="btn btn-secondary">Abbrechen</a>
                        <?php if ($isEdit && $isAdmin): ?>
                            <button type="submit" formaction="api/verein_crud.php" name="action" value="delete" class="btn btn-secondary"
                                    style="margin-left:auto;color:var(--error)"
                                    onclick="return confirm('Diesen Eintrag wirklich löschen?');">Löschen</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </main>
    </div>
    <script>
    (function() {
        const kat = document.getElementById('kategorie');
        function sync() { document.body.className = 'kat-' + kat.value; }
        kat.addEventListener('change', sync);
        sync();
    })();
    (function() {
        const burger = document.getElementById('burger-btn');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        function closeSidebar() { sidebar.classList.remove('open'); overlay.classList.remove('open'); document.body.style.overflow = ''; }
        burger.addEventListener('click', function() { sidebar.classList.add('open'); overlay.classList.add('open'); document.body.style.overflow = 'hidden'; });
        overlay.addEventListener('click', closeSidebar);
        sidebar.querySelectorAll('.nav-item a').forEach(function(link) { link.addEventListener('click', closeSidebar); });
    })();
    </script>
</body>
</html>
