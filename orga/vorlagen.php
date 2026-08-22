<?php
/**
 * Grafik-Vorlagen-Tool (dashboard-native, Option 2 aus grafik-vorlagen-tool-spec.md).
 * Nicht-Techniker-Weg: Vorlage befuellen -> Text tippen + Bild waehlen -> PNG-Export.
 *
 * Erste Vorlage: "Anmeldung geoeffnet" (IG Portrait 1080x1350). Das Layout ist ein
 * MASSGETREUER Port des abgenommenen Proofs:
 *   intern/design-referenzen/proof-anmeldung-portrait.html  (+ proof-anmeldung.png)
 * Koordinaten/Groessen/Farben 1:1 uebernommen; nur die Text-/Bild-Slots sind befuellbar.
 * Render-Pipeline = snapDOM (dpr:1, embedFonts). Fonts self-hosted (assets/fonts/, kein CDN).
 */

declare(strict_types=1);

require_once __DIR__ . '/api/_auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/social_anlaesse.php';
require_once __DIR__ . '/../src/social_grafik_defaults.php';
require_once __DIR__ . '/../src/social_sponsoren.php';
require_once __DIR__ . '/../src/raceresult_client.php';

$user    = getCurrentUserFromGuard();
$isAdmin = isAdminFromGuard();
$csrfToken = generateCsrfToken();
$pdo     = getDbConnection();

// Assets (deploybar, ueber Prod-URL geladen). Wortmarke + Wappen sind gruen -> weisse Leiste.
$logoWortmarke = '../assets/images/marktlauf-wordmark.png';
$logoAtsv      = '../assets/images/ATSV_Logo-750x968.png';
$logoGemeinde  = '../assets/images/Wort-u-Bildmarke-Gemeinde.png';
$runner        = '../assets/images/laeufer.png';

// Post-Kontext (?post=&fahrplan=): Grafik wird nach dem Rendern am Post gespeichert
$postKontext = null;
$postId      = (int) ($_GET['post'] ?? 0);
$fahrplanId  = (int) ($_GET['fahrplan'] ?? 0);
// Embed-Modus (?embed=1): ohne Sidebar/Header/Intro, eingebettet im Grafik-Schritt des
// Post-Details. "Fuer Post uebernehmen" meldet per postMessage an die Elternseite (kein
// Seitenwechsel). Nur sinnvoll mit Post-Kontext.
$embed = ((int) ($_GET['embed'] ?? 0)) === 1 && $postId > 0;
if ($postId > 0) {
    $stmt = $pdo->prepare('SELECT id, anlass_key FROM post_race_contents WHERE id = :id');
    $stmt->execute(['id' => $postId]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $def = socialAnlaesse()[$row['anlass_key']] ?? null;
        $postKontext = [
            'id'         => (int) $row['id'],
            'anlass_key' => (string) $row['anlass_key'],
            'ui'         => $def ? $def['ui'] : (string) $row['anlass_key'],
        ];
    }
}

// Renntag-Vorlage: Vorbefuellung aus RaceResult (Fallback Mock, wie Orchestrator)
$rr = raceResultData($pdo);
$rennen10 = null;
foreach ($rr['rennen'] ?? [] as $r) {
    if (isset($r['kategorie']) && str_contains((string) $r['kategorie'], '10')) { $rennen10 = $r; break; }
}
// Vorlagen-Vorwahl je Thema: Renntag -> Ergebnis-Card, Anmeldung -> Anmeldungs-Poster,
// alles andere -> universelle Themen-Vorlage
$vorlageDefault = $postKontext ? socialLayoutKey($postKontext['anlass_key']) : 'anmeldung';

// Eckdaten fuer die Themen-Vorlage (aus den Einstellungen, wie socialEckdaten)
$veranstaltung = 'Marktlauf Kirchseeon';
$eyebrowDatum  = '';
$themaDatumDefault = '';
try {
    $stmt = $pdo->query("SELECT `key`, `value` FROM einstellungen WHERE `key` IN ('renntag_datum', 'veranstaltungsname')");
    $werte = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    if (trim((string) ($werte['veranstaltungsname'] ?? '')) !== '') {
        $veranstaltung = trim((string) $werte['veranstaltungsname']);
    }
    $datum = trim((string) ($werte['renntag_datum'] ?? ''));
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) {
        $wochentage = [1 => 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];
        $ts = strtotime($datum);
        $eyebrowDatum = date('d.m.Y', $ts);
        $themaDatumDefault = $wochentage[(int) date('N', $ts)] . ', ' . date('d.m.Y', $ts) . ' · Start ab 10:00 Uhr';
    }
} catch (PDOException $e) {
    // Einstellungen evtl. leer
}

// Themen-Vorlage aus dem Anlass-Katalog vorbefuellen (Fakten-Zeilen ohne Klammer-Hinweise)
$themaHeadline = $veranstaltung;
$themaSub      = '';
$themaZeilen   = ['', '', ''];
$themaCta      = 'Jetzt anmelden!';
if ($postKontext && isset(socialAnlaesse()[$postKontext['anlass_key']])) {
    $def = socialAnlaesse()[$postKontext['anlass_key']];
    $themaHeadline = trim((string) preg_replace('/\s*\(.*\)\s*$/', '', $def['ui']));
    $zeilen = array_values(array_filter(
        array_map('trim', explode("\n", (string) ($def['fakten'] ?? ''))),
        static fn (string $z): bool => $z !== '' && !str_starts_with($z, '(')
    ));
    $themaSub    = $zeilen[0] ?? '';
    $themaZeilen = [$zeilen[1] ?? '', $zeilen[2] ?? '', $zeilen[3] ?? ''];
    $themaCta    = socialCtaDefault($postKontext['anlass_key']);
}

// QR-Ziele: feststehende Links als Auswahl (Inhaber 2026-08-14). Helfer-Link kommt zur
// LAUFZEIT aus access_tokens (aktiv + nicht abgelaufen) — kein Token im Code/Repo.
$appUrl = rtrim((string) (getConfig()['app']['url'] ?? 'https://atsv-kirchseeon-marktlauf.de'), '/');
$qrZiele = [
    'anmeldung'     => ['label' => 'Anmeldung (Website)', 'url' => $appUrl . '/#anmeldung'],
    'registrierung' => ['label' => 'RaceResult-Registrierung', 'url' => 'https://my.raceresult.com/412617/registration'],
    'website'       => ['label' => 'Website-Startseite', 'url' => $appUrl],
];
try {
    $tok = $pdo->query("SELECT token FROM access_tokens WHERE active = 1 AND expires_at > NOW() ORDER BY id DESC LIMIT 1")->fetchColumn();
    if ($tok) {
        $qrZiele['helfer'] = ['label' => 'Helfer-Anmeldung (Token-Link)', 'url' => $appUrl . '/helfer-anmeldung.php?token=' . rawurlencode((string) $tok)];
    }
} catch (PDOException $e) {
    // Tabelle fehlt/leer -> Preset entfaellt
}
$qrZiele['eigen'] = ['label' => 'Eigener Link …', 'url' => ''];

// Vorwahl passend zum Post-Thema
$qrDefault = $postKontext ? socialQrKey($postKontext['anlass_key'], isset($qrZiele['helfer'])) : 'anmeldung';

// Grafik-Regeln je Thema (Post-Wirkung-Spec S4): Format-Vorwahl, Foto/Grafik-Default,
// Logo-Fuehrung. Ohne Post-Kontext neutrale Defaults (Portrait, Grafik, ATSV-Fuehrung).
$formatDefault = $postKontext ? socialFormatDefault($postKontext['anlass_key']) : 'portrait';
$bildDefault   = $postKontext ? socialBildDefault($postKontext['anlass_key'])   : 'grafik';
$logoFuehrung  = $postKontext ? socialLogoFuehrung($postKontext['anlass_key'])  : ['marktlauf', 'atsv'];
// Feste Logos fuer die Fuehrung -> {url,label} (Sponsor-Logos folgen in S5)
$logoKatalog = [
    'marktlauf' => ['url' => $logoWortmarke, 'label' => 'Marktlauf'],
    'atsv'      => ['url' => $logoAtsv,      'label' => 'ATSV-Logo'],
    'gemeinde'  => ['url' => $logoGemeinde,  'label' => 'Gemeinde'],
];
$logoFuehrungAssets = array_values(array_filter(array_map(
    static fn (string $k): ?array => $logoKatalog[$k] ?? null,
    $logoFuehrung
)));
// Logo-Quelle „Sponsoren" (S5): bestaetigte Sponsoren mit Logo -> waehlbar im Logo-Picker.
// SSOT = sponsors.logo_web_asset (dieselbe Datei wie die Website-Rotation).
$sponsorLogos = [];
foreach (socialSponsoren($pdo) as $s) {
    if (!empty($s['logo'])) {
        $sponsorLogos[] = ['url' => '../' . $s['logo'], 'label' => $s['firma']];
    }
}

// Repo-Logos fuer tauschbare Logo-Slots der Renntag-Vorlage (Scan wie Orchestrator)
$repoAssets = [];
$assetsRoot = realpath(__DIR__ . '/../assets/images');
if ($assetsRoot !== false && is_dir($assetsRoot)) {
    $rii = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($assetsRoot, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($rii as $file) {
        if (!$file->isFile()) { continue; }
        if (!in_array(strtolower($file->getExtension()), ['png', 'jpg', 'jpeg', 'webp'], true)) { continue; }
        $rel = ltrim(str_replace($assetsRoot, '', $file->getPathname()), '/\\');
        $rel = str_replace('\\', '/', $rel);
        $url = '../assets/images/' . implode('/', array_map('rawurlencode', explode('/', $rel)));
        $repoAssets[] = ['label' => $rel, 'url' => $url];
    }
    usort($repoAssets, static fn ($a, $b) => strcasecmp($a['label'], $b['label']));
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Grafik-Vorlagen | ATSV Kirchseeon Marktlauf</title>
    <link rel="stylesheet" href="css/orga.css?v=<?= @filemtime(__DIR__ . '/css/orga.css') ?>">
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo-final.svg">
    <link rel="stylesheet" href="../css/fonts.css?v=<?= @filemtime(__DIR__ . '/../css/fonts.css') ?>">
    <style>
        /* --- Werkzeug-Layout: Steuerung links, Vorschau rechts --- */
        .vt-split { display: grid; grid-template-columns: minmax(320px, 430px) 1fr; gap: 1.25rem; align-items: start; }
        @media (max-width: 1000px) { .vt-split { grid-template-columns: 1fr; } }
        .vt-panel { background: var(--white); border-radius: 8px; box-shadow: var(--shadow-card); padding: 1.25rem; }
        .vt-panel h2 { font-size: 1rem; margin: 0 0 0.9rem; }
        .vt-panel h3 { font-size: 0.82rem; color: var(--text-light); text-transform: uppercase; letter-spacing: 0.5px; margin: 1.2rem 0 0.6rem; }
        .vt-field { margin-bottom: 0.85rem; }
        .vt-field label { display: block; font-size: 0.82rem; color: var(--text-light); margin-bottom: 0.25rem; }
        .vt-field input[type="text"], .vt-field select {
            width: 100%; padding: var(--control-pad-y) var(--control-pad-x); border: 1px solid var(--border);
            border-radius: var(--radius); font-size: 0.9rem; box-sizing: border-box; font-family: inherit;
        }
        /* Beide Spalten muessen ihren Inhalt zeigen (Inhaber 2026-08-14: keine abgeschnittenen Felder) */
        .vt-two { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.5fr); gap: 0.5rem; align-items: start; }
        .vt-hint { font-size: 0.78rem; color: var(--text-light); margin: 0.2rem 0 0; line-height: 1.45; }
        .vt-row { display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center; }
        .vt-seg { display: inline-flex; border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; }
        .vt-seg label { margin: 0; padding: 0.4rem 0.75rem; font-size: 0.85rem; cursor: pointer; color: var(--text); background: var(--white); }
        .vt-seg input { display: none; }
        .vt-seg input:checked + label { background: var(--primary); color: #fff; }
        .vt-photo-picker { display: none; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.5rem; max-height: 220px; overflow-y: auto; }
        .vt-thumb { width: 84px; cursor: pointer; text-align: center; }
        .vt-thumb img { width: 84px; height: 84px; object-fit: cover; border-radius: 6px; border: 1px solid var(--border); display: block; }
        .vt-thumb span { display: block; font-size: 0.68rem; color: var(--text-light); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .vt-actions { display: flex; gap: 0.75rem; flex-wrap: wrap; margin-top: 1rem; }
        .vt-error { display: none; color: var(--error); font-size: 0.85rem; margin-top: 0.5rem; }

        /* --- Vorschau --- */
        .vt-preview-wrap { position: sticky; top: 1rem; }
        #vt-card-img { max-width: 100%; width: auto; max-height: 74vh; border-radius: 8px; box-shadow: var(--shadow-card); display: block; }
        .vt-preview-empty { color: var(--text-light); font-size: 0.9rem; padding: 2rem 1rem; text-align: center; border: 1px dashed var(--border); border-radius: 8px; }
        .vt-caption { font-size: 0.82rem; color: var(--text-light); margin: 0 0 0.5rem; }

        /* --- Off-screen Render-Buehne (echte 1080px, NICHT display:none) --- */
        .vt-stage { position: absolute; left: -9999px; top: 0; width: 1080px; overflow: visible; }
        /* Freie Text-Platzierung (Beta): Live-Vorschau + ziehbare Bloecke */
        #vt-live-vp { width: 100%; max-width: 340px; overflow: hidden; border: 1px solid var(--border); border-radius: 8px; }
        #vt-live-scale { transform-origin: top left; }
        /* position !important: schlaegt die spaeter stehende, gleich-spezifische Regel
           `.sc-card > *:not(.sc-bg):not(.sc-overlay){position:relative}` — sonst bleiben
           die Bloecke im Flow und sind nicht frei ziehbar. */
        .sc-card.freilayout .vt-drag { position: absolute !important; cursor: grab; outline: 2px dashed rgba(255,255,255,0.6); outline-offset: 4px; touch-action: none; }
        .sc-card.freilayout .vt-drag:active { cursor: grabbing; }

        /* ============================================================
           Vorlage "Anmeldung geoeffnet" — 1:1 Port des Proofs
           (intern/design-referenzen/proof-anmeldung-portrait.html)
           ============================================================ */
        .poster {
            width: 1080px; height: 1350px; position: relative; overflow: hidden; color: #fff;
            background: linear-gradient(152deg, #1f7a3a 0%, #2f8f3f 38%, #57bd46 72%, #8ac63f 100%);
            font-family: 'Poppins', sans-serif;
        }
        .poster .bgphoto { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; display: none; }
        .poster .bgphoto-overlay { position: absolute; inset: 0; display: none;
            background: linear-gradient(160deg, rgba(0,0,0,0.28) 0%, rgba(16,51,26,0.80) 100%); }
        .poster.has-photo .bgphoto, .poster.has-photo .bgphoto-overlay { display: block; }
        .poster.has-photo .runner { display: none; }

        .poster .runner { position: absolute; right: 20px; bottom: 20px; width: 713px; height: 1162px;
            object-fit: contain; object-position: right bottom; filter: drop-shadow(0 8px 24px rgba(0,0,0,.18)); }
        .poster .logobar { position: absolute; top: 44px; left: 52px; display: flex; align-items: center; gap: 18px;
            background: #f7f5ee; border-radius: 22px; padding: 16px 24px; box-shadow: 0 10px 30px rgba(0,0,0,.16); z-index: 3; }
        .poster .logobar img.mark { height: 64px; width: auto; display: block; }
        .poster .logobar .sep { width: 2px; height: 56px; background: #e2e6de; }
        .poster .logobar img.atsv { height: 70px; width: auto; display: block; }
        .poster .koop { position: absolute; top: 44px; right: 52px; background: #fff; border-radius: 22px; padding: 14px 22px;
            box-shadow: 0 10px 30px rgba(0,0,0,.16); text-align: left; max-width: 360px; z-index: 3; }
        .poster .koop .kk { font-weight: 700; font-size: 15px; letter-spacing: 2px; color: #2f8f3f; text-transform: uppercase; margin-bottom: 8px; }
        .poster .koop img { height: 58px; width: auto; display: block; }
        /* Fliessende Textspalte: Elemente schieben sich, statt sich zu ueberlappen (robust bei langem Text) */
        .poster .content { position: absolute; left: 56px; top: 172px; width: 648px; bottom: 52px;
            display: flex; flex-direction: column; justify-content: space-between; z-index: 2; }
        .poster .hero { width: 640px; }
        .poster .hero h1 { font-family: 'Fredoka', 'Trebuchet MS', Verdana, sans-serif; font-weight: 700; font-size: 104px;
            line-height: .92; letter-spacing: -1px; text-transform: uppercase; text-shadow: 0 6px 20px rgba(0,0,0,.18); }
        .poster .hero .sub { font-family: 'Fredoka', 'Trebuchet MS', Verdana, sans-serif; font-weight: 600; font-size: 38px;
            line-height: 1.15; margin-top: 22px; color: #eafff0; }
        .poster .feat { width: 600px; display: flex; flex-direction: column; gap: 26px; }
        .poster .frow { display: flex; align-items: center; gap: 22px; }
        .poster .fic { flex: 0 0 auto; width: 74px; height: 74px; border-radius: 50%; border: 3px solid rgba(255,255,255,.85);
            display: flex; align-items: center; justify-content: center; }
        .poster .fic svg { width: 38px; height: 38px; stroke: #fff; fill: none; stroke-width: 2.2; }
        .poster .ft b { font-family: 'Fredoka', 'Trebuchet MS', Verdana, sans-serif; font-weight: 600; font-size: 30px; color: #f4b81e; display: block; line-height: 1.1; }
        .poster .ft span { font-weight: 400; font-size: 23px; color: #eafff0; line-height: 1.2; }
        .poster .cta { width: 520px; background: #f4b81e; border-radius: 18px;
            padding: 24px 0; text-align: center; box-shadow: 0 12px 30px rgba(0,0,0,.20); }
        .poster .cta span { font-family: 'Fredoka', 'Trebuchet MS', Verdana, sans-serif; font-weight: 700; font-size: 40px; color: #1f7a3a; letter-spacing: .5px; text-transform: uppercase; }
        .poster .info { display: flex; gap: 22px; align-items: stretch; }
        .poster .card { background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.28); border-radius: 20px; padding: 22px 26px; max-width: 250px; }
        .poster .card .ic { font-size: 26px; margin-bottom: 10px; display: block; }
        .poster .card .big { font-family: 'Fredoka', 'Trebuchet MS', Verdana, sans-serif; font-weight: 700; font-size: 34px; line-height: 1; }
        .poster .card .lbl { font-weight: 500; font-size: 20px; color: #eafff0; line-height: 1.25; margin-top: 4px; }
        .poster .qr { position: absolute; right: 56px; bottom: 56px; background: #fff; border-radius: 22px; padding: 22px; text-align: center;
            box-shadow: 0 12px 30px rgba(0,0,0,.20); width: 300px; z-index: 3; }
        .poster .qr .qh { font-family: 'Fredoka', 'Trebuchet MS', Verdana, sans-serif; font-weight: 700; font-size: 26px; color: #1f7a3a; line-height: 1.05; margin-bottom: 14px; }
        .poster .qr img { width: 220px; height: 220px; display: block; margin: 0 auto; }

        /* ============================================================
           Vorlage "Renntag-Ergebnis" — CI-Port der Share-Card (Poppins/
           Fredoka, Gold, Hero-Verlauf; Farben aus den DS-Tokens)
           ============================================================ */
        .sc-card {
            width: 1080px; height: 1350px;
            background: var(--color-primary-dark, #007230);
            display: flex; flex-direction: column; justify-content: space-between;
            padding: 80px; box-sizing: border-box;
            font-family: 'Poppins', -apple-system, sans-serif;
            color: #ffffff; position: relative; overflow: hidden;
        }
        .sc-card .sc-bg { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; z-index: 0; }
        .sc-card .sc-overlay { position: absolute; inset: 0; z-index: 1; }
        .sc-card > *:not(.sc-bg):not(.sc-overlay) { position: relative; z-index: 2; }
        /* Helle Logo-Plakette wie auf der Homepage (.logo-plakette, --color-cream): IMMER
           eine cremefarbene Pille hinter den Logos — pipeline-weit über alle Post-Layouts. */
        /* width:fit-content, damit die Pille die Logos umschliesst statt volle Breite zu ziehen
           (der Eltern-Block ist kein Flex-Container, align-self allein greift daher nicht). */
        .sc-card .sc-logos { display: flex; flex-wrap: wrap; gap: 30px; align-items: center; margin-bottom: 24px;
            width: fit-content; max-width: 100%; align-self: flex-start; background: #f7f5ee;
            border-radius: 20px; padding: 18px 26px; box-shadow: 0 6px 18px rgba(0,0,0,0.18); }
        .sc-card .sc-logos img { height: 96px; width: auto; max-width: 340px; object-fit: contain; }
        /* Auf dunklem Foto: halbtransparentes Weiß statt Creme, damit es sich einfügt. */
        .sc-card .sc-logos.on-photo { background: rgba(255,255,255,0.92); }
        /* Kein Logo gewählt -> keine leere Pille (sonst durchgehender weißer Streifen). */
        .sc-card .sc-logos:empty { display: none; }
        .sc-card .sc-event { display: flex; align-items: center; gap: 14px; font-size: 24px; font-weight: 600;
            letter-spacing: 0.16em; text-transform: uppercase; color: #fff8dd; margin-bottom: 20px; }
        .sc-card .sc-event::before { content: ''; width: 44px; height: 4px; border-radius: 2px;
            background: var(--color-accent-yellow, #f4b81e); flex-shrink: 0; }
        .sc-card .sc-headline { font-family: 'Fredoka', 'Trebuchet MS', sans-serif; font-size: 84px;
            font-weight: 700; line-height: 0.95; letter-spacing: -0.01em;
            text-shadow: 0 8px 28px rgba(20,60,30,0.3); }
        .sc-card .sc-metrics { display: flex; flex-direction: column; gap: 36px; }
        .sc-card .sc-metric-row { display: flex; gap: 60px; }
        .sc-card .sc-metric { display: flex; flex-direction: column; }
        .sc-card .sc-metric-label { font-size: 21px; font-weight: 600; letter-spacing: 0.12em;
            text-transform: uppercase; opacity: 0.8; margin-bottom: 8px; }
        .sc-card .sc-metric-value { font-family: 'Fredoka', 'Trebuchet MS', sans-serif; font-size: 52px;
            font-weight: 700; line-height: 1; color: var(--color-accent-yellow, #f4b81e); }
        .sc-card .sc-metric-sub { font-size: 26px; font-weight: 500; opacity: 0.9; margin-top: 6px; }
        .sc-card .sc-highlight { font-size: 26px; font-weight: 500; background: rgba(255,255,255,0.18);
            border: 1.5px solid rgba(255,255,255,0.55); border-radius: 16px; padding: 24px 32px; line-height: 1.4; }
        .sc-card .sc-footer { display: flex; justify-content: space-between; align-items: flex-end; gap: 40px; }
        .sc-card .sc-footer-text { display: flex; flex-direction: column; gap: 6px; }
        .sc-card .sc-url { font-size: 22px; font-weight: 500; opacity: 0.75; }
        .sc-card .sc-wordmark { font-family: 'Fredoka', 'Trebuchet MS', sans-serif; font-size: 34px;
            font-weight: 600; letter-spacing: 0.5px; }
        .sc-card .sc-qr { display: flex; flex-direction: column; align-items: center; gap: 10px; flex: 0 0 auto; }
        .sc-card .sc-qr img { width: 200px; height: 200px; background: #fff; padding: 14px; border-radius: 16px; box-sizing: border-box; display: block; }
        .sc-card .sc-qr-label { font-family: 'Fredoka', 'Trebuchet MS', sans-serif; font-size: 24px; font-weight: 600; text-align: center; }
        /* Themen-Vorlage: Unterzeile, Gold-Bullets, CTA-Pille, Meta-Zeilen */
        .sc-card .sc-sub { font-size: 34px; font-weight: 500; color: #fff8dd; margin-top: 18px; line-height: 1.35; }
        .sc-card .sc-bullets { display: flex; flex-direction: column; gap: 26px; }
        .sc-card .sc-bullet { display: flex; align-items: flex-start; gap: 18px; font-size: 30px; font-weight: 500; line-height: 1.3; }
        .sc-card .sc-bullet::before { content: ''; width: 14px; height: 14px; border-radius: 50%;
            background: var(--color-accent-yellow, #f4b81e); flex-shrink: 0; margin-top: 13px; }
        .sc-card .sc-cta { align-self: flex-start; background: var(--color-accent-yellow, #f4b81e);
            border-radius: 18px; padding: 20px 44px; box-shadow: 0 12px 30px rgba(0,0,0,.2); }
        .sc-card .sc-cta span { font-family: 'Fredoka', 'Trebuchet MS', sans-serif; font-weight: 700;
            font-size: 36px; color: #1f7a3a; text-transform: uppercase; letter-spacing: .5px; }
        .sc-card .sc-meta { font-size: 26px; font-weight: 500; opacity: 0.92; margin-top: 8px; }
        .vt-chips { display: flex; gap: 0.4rem; flex-wrap: wrap; margin: 0.5rem 0; }
        .vt-chip { display: inline-flex; align-items: center; gap: 0.35rem; background: var(--bg);
            border: 1px solid var(--border); border-radius: 12px; padding: 0.15rem 0.2rem 0.15rem 0.6rem; font-size: 0.78rem; }
        .vt-chip button { border: none; background: none; cursor: pointer; color: #b91c1c; font-size: 0.9rem; line-height: 1; padding: 0 0.3rem; }
        .vt-kontext { background: #eef7f0; border: 1px solid #bfe3c8; border-radius: 8px;
            padding: 0.6rem 0.9rem; font-size: 0.88rem; margin-bottom: 1rem; }
        .vt-kontext a { color: var(--primary-dark); }

        /* --- Embed-Modus (?embed=1): im Grafik-Schritt des Post-Details eingebettet --- */
        body.vt-embed { background: var(--white); }
        body.vt-embed .main-content { margin: 0; padding: 0.25rem 0.25rem 0.75rem; max-width: none; }
        body.vt-embed .vt-preview-wrap { position: static; }
        body.vt-embed #vt-card-img { max-height: none; }
        /* Layout-Wechsler als kompakter Nebenweg statt prominenter erster Schritt */
        .vt-layout-row { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;
            margin-bottom: 0.85rem; padding-bottom: 0.75rem; border-bottom: 1px solid var(--border); }
        .vt-layout-row label { font-size: 0.82rem; color: var(--text-light); margin: 0; }
        .vt-layout-row select { width: auto; min-width: 220px; padding: var(--control-pad-y) var(--control-pad-x);
            border: 1px solid var(--border); border-radius: var(--radius); font-size: 0.88rem; font-family: inherit; background: var(--white); }
        .vt-layout-auto { font-size: 0.76rem; color: var(--text-light); }
    </style>
    <link rel="stylesheet" href="../design-system/tokens/colors.css?v=<?= @filemtime(__DIR__ . '/../design-system/tokens/colors.css') ?>">
</head>
<body class="<?= $embed ? 'vt-embed' : '' ?>">
<?php if (!$embed): ?>
<?php $activeNav = 'vorlagen'; require __DIR__ . '/_sidebar.php'; ?>
<?php endif; ?>

        <main class="main-content">
            <?php if (!$embed): ?>
            <header class="content-header">
                <h1>Grafik-Vorlagen</h1>
            </header>

            <?php if ($postKontext): ?>
            <div class="vt-kontext">
                Grafik für Post: <strong><?= htmlspecialchars($postKontext['ui']) ?></strong> —
                nach dem Erzeugen unten „Für Post übernehmen" klicken.
                <a href="social_post.php?fahrplan=<?= (int) $fahrplanId ?>">← zurück zum Post</a>
            </div>
            <?php endif; ?>
            <p class="vt-hint" style="margin-bottom:1rem;max-width:760px;">
                Fertiges Layout befüllen &amp; als Bild exportieren &mdash; ohne Design-Kenntnisse.
                Layouts: <strong>&bdquo;Themen-Post&ldquo;</strong> (universell, Formate wählbar),
                <strong>&bdquo;Anmeldung geöffnet&ldquo;</strong> (Portrait 1080&times;1350) und
                <strong>&bdquo;Renntag-Ergebnis&ldquo;</strong> (Formate wählbar).
                Für freie Plakate bleibt der <a href="poster_generator.php">Plakat-Generator</a>.
            </p>
            <?php endif; ?>

            <div class="vt-split">
                <!-- ============ Steuerung ============ -->
                <div class="vt-panel">
                    <div class="vt-layout-row">
                        <label for="vt-vorlage">Layout</label>
                        <select id="vt-vorlage">
                            <option value="thema" <?= $vorlageDefault === 'thema' ? 'selected' : '' ?>>Themen-Post (universell, Formate wählbar)</option>
                            <option value="anmeldung" <?= $vorlageDefault === 'anmeldung' ? 'selected' : '' ?>>Anmeldung geöffnet (Portrait)</option>
                            <option value="renntag" <?= $vorlageDefault === 'renntag' ? 'selected' : '' ?>>Renntag-Ergebnis (Formate wählbar)</option>
                        </select>
                        <?php if ($postKontext): ?>
                        <span class="vt-layout-auto">automatisch zum Thema gewählt — bei Bedarf wechseln</span>
                        <?php endif; ?>
                    </div>

                    <div class="vt-layout-row" style="border:0;padding-top:0;">
                        <button type="button" class="btn btn-secondary btn-small" id="vt-reset-vorlage">Auf Vorlage zurücksetzen</button>
                        <span class="vt-layout-auto" id="vt-autosave-hint">Änderungen werden automatisch zwischengespeichert</span>
                    </div>

                    <h2>1 &middot; Inhalt befuellen</h2>

                    <div class="vt-field">
                        <label>Hintergrund</label>
                        <div class="vt-seg" id="vt-bg-mode">
                            <input type="radio" name="bgmode" id="bg-gradient" value="gradient" <?= $bildDefault === 'grafik' ? 'checked' : '' ?>>
                            <label for="bg-gradient">Grafik (Verlauf + L&auml;ufer)</label>
                            <input type="radio" name="bgmode" id="bg-photo" value="photo" <?= $bildDefault === 'foto' ? 'checked' : '' ?>>
                            <label for="bg-photo">Foto</label>
                        </div>
                        <?php if ($postKontext && $bildDefault === 'foto'): ?><span class="vt-hint">Dieses Thema lebt vom echten Foto — bitte ein Foto aus der Ablage wählen.</span><?php endif; ?>
                        <div id="vt-photo-block" style="display:none;margin-top:0.5rem;">
                            <div class="vt-row">
                                <button type="button" class="btn btn-secondary" id="vt-pick-photo">Foto aus Ablage wählen</button>
                                <button type="button" class="btn btn-secondary" id="vt-clear-photo" style="display:none;">Foto entfernen</button>
                            </div>
                            <span class="vt-hint" id="vt-photo-name"></span>
                            <div class="vt-photo-picker" id="vt-photo-picker"></div>
                            <div class="vt-row" style="margin-top:0.6rem;">
                                <label for="vt-photo-file" class="vt-hint" style="margin:0">Oder eigenes Foto vom Rechner:</label>
                                <input type="file" id="vt-photo-file" accept="image/png,image/jpeg,image/webp" style="font-size:0.85rem;">
                            </div>
                            <span class="vt-hint">Bei Foto tritt der Läufer zurück.</span>
                        </div>
                    </div>

                    <div id="vt-felder-anmeldung">
                    <h3>Kopf</h3>
                    <div class="vt-field">
                        <label for="vt-headline">Schlagzeile</label>
                        <input type="text" id="vt-headline" maxlength="40" value="Anmeldung geöffnet!">
                    </div>
                    <div class="vt-field">
                        <label for="vt-sub">Unterzeile</label>
                        <input type="text" id="vt-sub" maxlength="70" value="Sichert euch jetzt euren Startplatz!">
                    </div>

                    <h3>Drei Punkte</h3>
                    <div class="vt-field vt-two">
                        <input type="text" id="vt-f1t" maxlength="40" value="Für alle Altersklassen" aria-label="Punkt 1 Titel">
                        <input type="text" id="vt-f1s" maxlength="60" value="Bambini, Schüler, Jugend, Erwachsene" aria-label="Punkt 1 Text">
                    </div>
                    <div class="vt-field vt-two">
                        <input type="text" id="vt-f2t" maxlength="40" value="Verschiedene Distanzen" aria-label="Punkt 2 Titel">
                        <input type="text" id="vt-f2s" maxlength="60" value="500 m bis 10 km" aria-label="Punkt 2 Text">
                    </div>
                    <div class="vt-field vt-two">
                        <input type="text" id="vt-f3t" maxlength="40" value="Gemeinsam für Umwelt &amp; Energie" aria-label="Punkt 3 Titel">
                        <input type="text" id="vt-f3s" maxlength="60" value="Jeder Schritt zählt!" aria-label="Punkt 3 Text">
                    </div>
                    <span class="vt-hint">Links der goldene Titel, rechts der Untertext. Icons sind fest.</span>

                    <h3>Aktion &amp; Infos</h3>
                    <div class="vt-field">
                        <label for="vt-cta">Aktions-Button</label>
                        <input type="text" id="vt-cta" maxlength="30" value="Jetzt anmelden!">
                    </div>
                    <div class="vt-field vt-two">
                        <input type="text" id="vt-datum" maxlength="20" value="20.09.2026" aria-label="Datum gross">
                        <input type="text" id="vt-datum-zusatz" maxlength="40" value="Sonntag, Start 10:00 Uhr" aria-label="Datum Zusatz">
                    </div>
                    <div class="vt-field">
                        <label for="vt-ort">Ort</label>
                        <input type="text" id="vt-ort" maxlength="60" value="JEK, Westring 6, Kirchseeon">
                    </div>
                    </div><!-- /vt-felder-anmeldung -->

                    <div id="vt-felder-thema" style="display:none">
                    <h3>Kopf</h3>
                    <div class="vt-field">
                        <label for="vt-th-headline">Schlagzeile</label>
                        <input type="text" id="vt-th-headline" maxlength="40" value="<?= htmlspecialchars($themaHeadline) ?>">
                    </div>
                    <div class="vt-field">
                        <label for="vt-th-sub">Unterzeile</label>
                        <input type="text" id="vt-th-sub" maxlength="90" value="<?= htmlspecialchars($themaSub) ?>">
                    </div>
                    <h3>Bis zu drei Zeilen</h3>
                    <?php foreach ($themaZeilen as $i => $zeile): ?>
                    <div class="vt-field">
                        <input type="text" id="vt-th-z<?= $i + 1 ?>" maxlength="80" value="<?= htmlspecialchars($zeile) ?>" aria-label="Zeile <?= $i + 1 ?>">
                    </div>
                    <?php endforeach; ?>
                    <span class="vt-hint">Leere Zeilen erscheinen nicht auf der Grafik.</span>
                    <h3>Aktion &amp; Termin</h3>
                    <div class="vt-field">
                        <label for="vt-th-cta">Aktions-Button (leer = kein Button)</label>
                        <input type="text" id="vt-th-cta" maxlength="30" value="<?= htmlspecialchars($themaCta) ?>">
                    </div>
                    <div class="vt-field vt-two">
                        <input type="text" id="vt-th-datum" maxlength="60" value="<?= htmlspecialchars($themaDatumDefault) ?>" aria-label="Datum-Zeile">
                        <input type="text" id="vt-th-ort" maxlength="60" value="JEK, Westring 6, Kirchseeon" aria-label="Ort-Zeile">
                    </div>
                    </div><!-- /vt-felder-thema -->

                    <div id="vt-felder-renntag" style="display:none">
                    <h3>Kopf</h3>
                    <div class="vt-field">
                        <label for="vt-rt-event">Kopfzeile (Event · Datum)</label>
                        <input type="text" id="vt-rt-event" maxlength="70" value="<?= htmlspecialchars(($rr['event']['name'] ?? 'Marktlauf Kirchseeon') . ' · ' . ($rr['event']['datum'] ?? '')) ?>">
                    </div>
                    <div class="vt-field">
                        <label for="vt-rt-headline">Schlagzeile</label>
                        <input type="text" id="vt-rt-headline" maxlength="40" value="Danke &amp; Glückwunsch!">
                    </div>
                    <h3>Ergebnisse (aus RaceResult vorbefuellt)</h3>
                    <div class="vt-field vt-two">
                        <input type="text" id="vt-rt-s10" maxlength="40" value="<?= htmlspecialchars($rennen10['sieger']['name'] ?? '') ?>" aria-label="Sieger 10 km">
                        <input type="text" id="vt-rt-s10z" maxlength="20" value="<?= htmlspecialchars($rennen10['sieger']['zeit'] ?? '') ?>" aria-label="Zeit Sieger">
                    </div>
                    <div class="vt-field vt-two">
                        <input type="text" id="vt-rt-si10" maxlength="40" value="<?= htmlspecialchars($rennen10['siegerin']['name'] ?? '') ?>" aria-label="Siegerin 10 km">
                        <input type="text" id="vt-rt-si10z" maxlength="20" value="<?= htmlspecialchars($rennen10['siegerin']['zeit'] ?? '') ?>" aria-label="Zeit Siegerin">
                    </div>
                    <div class="vt-field vt-two">
                        <input type="text" id="vt-rt-tn" maxlength="10" value="<?= htmlspecialchars((string) ($rr['gesamt']['teilnehmer'] ?? '')) ?>" aria-label="Teilnehmer">
                        <input type="text" id="vt-rt-finisher" maxlength="10" value="<?= htmlspecialchars((string) ($rr['gesamt']['finisher'] ?? '')) ?>" aria-label="Finisher">
                    </div>
                    <span class="vt-hint">Links Sieger/Teilnehmer, rechts Zeit/Finisher.</span>
                    <div class="vt-field" style="margin-top:0.85rem">
                        <label for="vt-rt-highlight">Highlight (optional)</label>
                        <input type="text" id="vt-rt-highlight" maxlength="120" value="<?= htmlspecialchars((string) ($rr['highlight'] ?? '')) ?>">
                    </div>
                    </div><!-- /vt-felder-renntag -->

                    <!-- Format + Logos: gemeinsam fuer Themen-Post + Renntag-Ergebnis -->
                    <div id="vt-felder-scard" style="display:none">
                    <h3>Format</h3>
                    <div class="vt-field">
                        <select id="vt-rt-format">
                            <option value="portrait" <?= $formatDefault === 'portrait' ? 'selected' : '' ?>>Portrait 1080×1350 (Feed)</option>
                            <option value="grid34" <?= $formatDefault === 'grid34' ? 'selected' : '' ?>>Instagram-Grid 1080×1440 (3:4)</option>
                            <option value="square" <?= $formatDefault === 'square' ? 'selected' : '' ?>>Quadratisch 1080×1080</option>
                            <option value="story" <?= $formatDefault === 'story' ? 'selected' : '' ?>>Story 1080×1920</option>
                        </select>
                        <?php if ($postKontext): ?><span class="vt-hint">zum Thema vorgewählt — bei Bedarf wechseln</span><?php endif; ?>
                    </div>
                    <h3>Logos (tauschbar)</h3>
                    <div class="vt-row">
                        <button type="button" class="btn btn-secondary" id="vt-logo-add">Logo hinzufügen</button>
                        <button type="button" class="btn btn-secondary" id="vt-logo-reset">nur ATSV</button>
                        <button type="button" class="btn btn-secondary" id="vt-logo-nur-marktlauf">nur Marktlauf</button>
                    </div>
                    <div class="vt-field" style="margin-top:0.6rem;">
                        <label style="display:flex;align-items:center;gap:0.5rem;font-weight:400;cursor:pointer;">
                            <input type="checkbox" id="vt-hide-event" style="width:auto;"> Datum-Kopfzeile (mit dem Strich) ausblenden
                        </label>
                        <label style="display:flex;align-items:center;gap:0.5rem;font-weight:400;cursor:pointer;margin-top:0.4rem;">
                            <input type="checkbox" id="vt-freilayout" style="width:auto;"> Text frei positionieren (Beta · nur Themen-Post)
                        </label>
                    </div>
                    <div class="vt-chips" id="vt-logo-chips"></div>
                    <div class="vt-photo-picker" id="vt-logo-picker"></div>
                    </div><!-- /vt-felder-scard -->

                    <h3>QR-Code (optional)</h3>
                    <div class="vt-field">
                        <label for="vt-qr-ziel">QR-Ziel</label>
                        <select id="vt-qr-ziel">
                            <option value="">— kein QR-Code —</option>
                            <?php foreach ($qrZiele as $zielKey => $ziel): ?>
                            <option value="<?= htmlspecialchars($zielKey) ?>" data-url="<?= htmlspecialchars($ziel['url']) ?>" <?= $zielKey === $qrDefault ? 'selected' : '' ?>><?= htmlspecialchars($ziel['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="vt-hint">Feststehende Ziele sind hinterlegt; der Helfer-Link kommt immer aktuell aus der Token-Verwaltung.</span>
                    </div>
                    <div class="vt-field" id="vt-qr-eigen-feld" style="display:none">
                        <label for="vt-qr-url">Eigener Ziel-Link</label>
                        <input type="text" id="vt-qr-url" placeholder="https://…">
                    </div>
                    <div class="vt-field">
                        <label for="vt-qr-label">QR-Beschriftung</label>
                        <input type="text" id="vt-qr-label" maxlength="30" value="Jetzt scannen &amp; anmelden!">
                    </div>

                    <div class="vt-actions">
                        <button type="button" class="btn btn-primary" id="vt-render">Grafik erzeugen</button>
                        <button type="button" class="btn btn-secondary" id="vt-download" style="display:none;">PNG herunterladen</button>
                        <?php if ($postKontext): ?>
                        <button type="button" class="btn btn-primary" id="vt-uebernehmen" style="display:none;">Für Post übernehmen</button>
                        <?php endif; ?>
                    </div>
                    <div class="vt-error" id="vt-error"></div>
                </div>

                <!-- ============ Vorschau ============ -->
                <div class="vt-preview-wrap">
                    <p class="vt-caption" id="vt-caption">Vorschau erscheint nach &bdquo;Grafik erzeugen&ldquo;.</p>
                    <div class="vt-preview-empty" id="vt-preview-empty">Noch keine Vorschau &mdash; links befuellen und &bdquo;Grafik erzeugen&ldquo; klicken.</div>
                    <img id="vt-card-img" alt="Vorschau der erzeugten Grafik" style="display:none;">
                    <div id="vt-live-wrap" style="display:none;">
                        <p class="vt-hint" style="margin:0 0 0.4rem">Frei-Modus: Textblöcke direkt ziehen. „Grafik erzeugen" exportiert die Anordnung.</p>
                        <div id="vt-live-vp"><div id="vt-live-scale"></div></div>
                    </div>
                </div>
            </div>

            <!-- Off-screen Render-Buehne (echte Pixel; darf nicht display:none sein) -->
            <div class="vt-stage" aria-hidden="true">
                <div class="poster" id="vt-card">
                    <img class="bgphoto" id="vt-bg" alt="">
                    <div class="bgphoto-overlay"></div>
                    <img class="runner" id="vt-runner" src="<?= htmlspecialchars($runner) ?>" alt="">
                    <div class="logobar">
                        <img class="mark" src="<?= htmlspecialchars($logoWortmarke) ?>" alt="Marktlauf Kirchseeon" id="vt-mark">
                        <div class="sep"></div>
                        <img class="atsv" src="<?= htmlspecialchars($logoAtsv) ?>" alt="ATSV Kirchseeon 1906" id="vt-atsv">
                    </div>
                    <div class="koop">
                        <div class="kk">In Kooperation mit</div>
                        <img src="<?= htmlspecialchars($logoGemeinde) ?>" alt="Markt Kirchseeon" id="vt-gemeinde">
                    </div>
                    <div class="content">
                    <div class="hero">
                        <h1 id="c-headline"></h1>
                        <div class="sub" id="c-sub"></div>
                    </div>
                    <div class="feat">
                        <div class="frow">
                            <div class="fic"><svg viewBox="0 0 24 24"><path d="M2 17h16l3-3-2-2-4 1-3-4-4 2v6z"/><path d="M2 17v2h19"/></svg></div>
                            <div class="ft"><b id="c-f1t"></b><span id="c-f1s"></span></div>
                        </div>
                        <div class="frow">
                            <div class="fic"><svg viewBox="0 0 24 24"><circle cx="12" cy="13" r="8"/><path d="M12 13V9M9 2h6"/></svg></div>
                            <div class="ft"><b id="c-f2t"></b><span id="c-f2s"></span></div>
                        </div>
                        <div class="frow">
                            <div class="fic"><svg viewBox="0 0 24 24"><path d="M11 20A7 7 0 0 1 4 13c0-5 7-9 7-9s7 4 7 9a7 7 0 0 1-7 7z"/><path d="M11 20v-9"/></svg></div>
                            <div class="ft"><b id="c-f3t"></b><span id="c-f3s"></span></div>
                        </div>
                    </div>
                    <div class="cta"><span id="c-cta"></span></div>
                    <div class="info">
                        <div class="card"><span class="ic">📅</span><div class="big" id="c-datum"></div><div class="lbl" id="c-datum-zusatz"></div></div>
                        <div class="card" id="c-ort-card"><span class="ic">📍</span><div class="lbl" id="c-ort"></div></div>
                    </div>
                    </div><!-- /content -->
                    <div class="qr" id="c-qr" style="display:none;">
                        <div class="qh" id="c-qr-label"></div>
                        <img id="c-qr-img" alt="">
                    </div>
                </div>
            </div>

            <!-- Off-screen Render-Buehne: Vorlage "Themen-Post" (universell) -->
            <div class="vt-stage" aria-hidden="true">
                <div class="sc-card" id="vt-card3">
                    <img class="sc-bg" id="th-bg" alt="" style="display:none">
                    <div class="sc-overlay" id="th-overlay"></div>
                    <div class="vt-drag" data-drag="top">
                        <div class="sc-logos" id="th-logos"></div>
                        <div class="sc-event"><?= htmlspecialchars($veranstaltung . ($eyebrowDatum !== '' ? ' · ' . $eyebrowDatum : '')) ?></div>
                        <div class="sc-headline" id="th-headline"></div>
                        <div class="sc-sub" id="th-sub"></div>
                    </div>
                    <div class="sc-bullets vt-drag" data-drag="bullets" id="th-bullets">
                        <div class="sc-bullet" id="th-z1"></div>
                        <div class="sc-bullet" id="th-z2"></div>
                        <div class="sc-bullet" id="th-z3"></div>
                    </div>
                    <div class="sc-cta vt-drag" data-drag="cta" id="th-cta-wrap"><span id="th-cta"></span></div>
                    <div class="vt-drag" data-drag="meta">
                        <div class="sc-meta" id="th-datum"></div>
                        <div class="sc-meta" id="th-ort"></div>
                    </div>
                    <div class="sc-footer">
                        <div class="sc-footer-text">
                            <span class="sc-wordmark">ATSV Kirchseeon</span>
                            <span class="sc-url">atsv-kirchseeon-marktlauf.de</span>
                        </div>
                        <div class="sc-qr" id="th-qr" style="display:none">
                            <img id="th-qr-img" alt="">
                            <span class="sc-qr-label" id="th-qr-label"></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Off-screen Render-Buehne: Vorlage "Renntag-Ergebnis" -->
            <div class="vt-stage" aria-hidden="true">
                <div class="sc-card" id="vt-card2">
                    <img class="sc-bg" id="rt-bg" alt="" style="display:none">
                    <div class="sc-overlay" id="rt-overlay"></div>
                    <div>
                        <div class="sc-logos" id="rt-logos"></div>
                        <div class="sc-event" id="rt-event"></div>
                        <div class="sc-headline" id="rt-headline"></div>
                    </div>
                    <div class="sc-metrics">
                        <div class="sc-metric-row">
                            <div class="sc-metric">
                                <span class="sc-metric-label">Sieger 10 km</span>
                                <span class="sc-metric-value" id="rt-s10">–</span>
                                <span class="sc-metric-sub" id="rt-s10z"></span>
                            </div>
                            <div class="sc-metric">
                                <span class="sc-metric-label">Siegerin 10 km</span>
                                <span class="sc-metric-value" id="rt-si10">–</span>
                                <span class="sc-metric-sub" id="rt-si10z"></span>
                            </div>
                        </div>
                        <div class="sc-metric-row">
                            <div class="sc-metric">
                                <span class="sc-metric-label">Teilnehmer</span>
                                <span class="sc-metric-value" id="rt-tn">–</span>
                            </div>
                            <div class="sc-metric">
                                <span class="sc-metric-label">Finisher</span>
                                <span class="sc-metric-value" id="rt-finisher">–</span>
                            </div>
                        </div>
                    </div>
                    <div class="sc-highlight" id="rt-highlight"></div>
                    <div class="sc-footer">
                        <div class="sc-footer-text">
                            <span class="sc-wordmark">ATSV Kirchseeon</span>
                            <span class="sc-url">atsv-kirchseeon-marktlauf.de</span>
                        </div>
                        <div class="sc-qr" id="rt-qr" style="display:none">
                            <img id="rt-qr-img" alt="">
                            <span class="sc-qr-label" id="rt-qr-label"></span>
                        </div>
                    </div>
                </div>
            </div>
        </main>
<?php if (!$embed): ?>
    </div>
<?php endif; ?>
    <script src="../assets/js/snapdom.js"></script>
    <script src="../assets/js/qrcode.js"></script>
    <script>
    (function() {
        const $ = id => document.getElementById(id);
        const card  = $('vt-card');
        const card2 = $('vt-card2');
        let lastDataUrl = null;
        let selectedPhotoUrl = '';
        let lokalesFotoUrl = ''; // Blob-URL eines lokal gewaehlten Fotos (zum Freigeben)
        let thPositions = {};    // Freie Text-Platzierung Themen-Post: {blockKey: {l,t}} in %

        const csrf        = <?= json_encode($csrfToken) ?>;
        const postKontext = <?= json_encode($postKontext) ?>;
        const fahrplanId  = <?= (int) $fahrplanId ?>;
        const embed       = <?= $embed ? 'true' : 'false' ?>;
        const repoAssets  = <?= json_encode($repoAssets, JSON_UNESCAPED_UNICODE) ?>;
        const sponsorLogos = <?= json_encode($sponsorLogos, JSON_UNESCAPED_UNICODE) ?>;
        const DEFAULT_LOGO   = '<?= htmlspecialchars($logoAtsv) ?>';
        const MARKTLAUF_LOGO = '<?= htmlspecialchars($logoWortmarke) ?>';
        const RT_FORMATS = {
            portrait: { w: 1080, h: 1350, label: 'Portrait 1080×1350' },
            grid34:   { w: 1080, h: 1440, label: 'Instagram-Grid 1080×1440' },
            square:   { w: 1080, h: 1080, label: 'Quadratisch 1080×1080' },
            story:    { w: 1080, h: 1920, label: 'Story 1080×1920' },
        };
        // Logo-Fuehrung je Thema (S4): serverseitig vorgewaehlt aus den festen Logos.
        const LOGO_FUEHRUNG = <?= json_encode($logoFuehrungAssets, JSON_UNESCAPED_UNICODE) ?>;
        const LOGO_DECKEL = 3;  // max Logos in der Leiste (Spec 5.C.12: Marktlauf + ATSV + 1 Partner)
        let selectedLogos = (LOGO_FUEHRUNG.length ? LOGO_FUEHRUNG : [{ url: DEFAULT_LOGO, label: 'ATSV-Logo' }]).slice(0, LOGO_DECKEL);

        function aktiveVorlage() { return $('vt-vorlage').value; }
        function vorlageWechsel() {
            const v = aktiveVorlage();
            $('vt-felder-anmeldung').style.display = v === 'anmeldung' ? 'block' : 'none';
            $('vt-felder-renntag').style.display   = v === 'renntag'   ? 'block' : 'none';
            $('vt-felder-thema').style.display     = v === 'thema'     ? 'block' : 'none';
            $('vt-felder-scard').style.display     = v === 'anmeldung' ? 'none'  : 'block';
        }
        $('vt-vorlage').addEventListener('change', vorlageWechsel);
        vorlageWechsel();

        // Farben aus den DS-Tokens (colors.css) — eine Quelle, kein Drift
        function cssVar(name, fallback) {
            const v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
            return v || fallback;
        }
        function hexToRgba(hex, a) {
            const m = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex.trim());
            if (!m) return 'rgba(0,86,42,' + a + ')';
            return 'rgba(' + parseInt(m[1],16) + ',' + parseInt(m[2],16) + ',' + parseInt(m[3],16) + ',' + a + ')';
        }
        function heroOverlay() {
            const gold = cssVar('--color-accent-yellow', '#f4b81e');
            const teal = cssVar('--color-teal', '#0e6f88');
            return 'radial-gradient(620px 620px at 108% -8%, ' + hexToRgba(gold, 0.55) + ', ' + hexToRgba(gold, 0) + ' 68%), '
                 + 'radial-gradient(640px 640px at -10% 112%, ' + hexToRgba(teal, 0.55) + ', ' + hexToRgba(teal, 0) + ' 66%), '
                 + cssVar('--gradient-hero', 'linear-gradient(128deg, #12a877 0%, #5cbd45 50%, #bcd531 100%)');
        }

        document.querySelectorAll('input[name="bgmode"]').forEach(r => {
            r.addEventListener('change', () => { $('vt-photo-block').style.display = $('bg-photo').checked ? 'block' : 'none'; });
        });
        // Initialzustand: bei Foto-Default (S4) den Foto-Block gleich aufklappen
        $('vt-photo-block').style.display = $('bg-photo').checked ? 'block' : 'none';

        // --- Foto-Picker aus der Datei-Ablage (same-origin -> snapDOM-tauglich) ---
        const picker = $('vt-photo-picker');
        $('vt-pick-photo').addEventListener('click', async () => {
            if (picker.style.display === 'flex') { picker.style.display = 'none'; return; }
            picker.textContent = 'laedt …'; picker.style.display = 'flex';
            try {
                const r = await fetch('api/dateien_images.php');
                const d = await r.json();
                picker.innerHTML = '';
                const imgs = (d.images || []);
                if (!imgs.length) { picker.textContent = 'Keine Bilder in der Ablage.'; return; }
                imgs.forEach(img => {
                    const t = document.createElement('div'); t.className = 'vt-thumb';
                    const im = document.createElement('img'); im.src = img.url; im.alt = '';
                    const nm = document.createElement('span'); nm.textContent = img.name;
                    t.appendChild(im); t.appendChild(nm);
                    t.addEventListener('click', () => {
                        selectedPhotoUrl = img.url;
                        $('vt-photo-name').textContent = 'Gewählt: ' + img.name;
                        $('vt-clear-photo').style.display = 'inline-flex';
                        picker.style.display = 'none';
                    });
                    picker.appendChild(t);
                });
            } catch (e) { picker.textContent = 'Fehler beim Laden.'; }
        });
        $('vt-clear-photo').addEventListener('click', () => {
            if (lokalesFotoUrl) { URL.revokeObjectURL(lokalesFotoUrl); lokalesFotoUrl = ''; }
            selectedPhotoUrl = '';
            $('vt-photo-name').textContent = '';
            $('vt-clear-photo').style.display = 'none';
            $('vt-photo-file').value = '';
        });

        // Eigenes Foto vom Rechner: direkt als Hintergrund (client-seitig, snapDOM-tauglich).
        // Kein Upload/keine Ablage noetig — das fertige PNG wird ohnehin lokal gerendert.
        $('vt-photo-file').addEventListener('change', () => {
            const file = $('vt-photo-file').files && $('vt-photo-file').files[0];
            if (!file) { return; }
            if (lokalesFotoUrl) { URL.revokeObjectURL(lokalesFotoUrl); }
            lokalesFotoUrl = URL.createObjectURL(file);
            selectedPhotoUrl = lokalesFotoUrl;
            if (!$('bg-photo').checked) { $('bg-photo').checked = true; $('vt-photo-block').style.display = 'block'; }
            $('vt-photo-name').textContent = 'Gewählt: ' + file.name;
            $('vt-clear-photo').style.display = 'inline-flex';
            $('vt-photo-picker').style.display = 'none';
        });

        // --- Auto-Zwischenspeicher der Editor-Felder pro Post (localStorage) ---
        // "Bearbeitungsstaende immer direkt zwischenspeichern": jede Aenderung landet sofort
        // im Browser-Cache und wird beim erneuten Oeffnen wiederhergestellt. Der Button
        // "Auf Vorlage zuruecksetzen" stellt den Ursprung (Themen-Defaults) wieder her.
        // Lokale Blob-Fotos lassen sich nicht persistieren (Blob stirbt beim Reload) -> ausgelassen.
        const VT_CACHE_KEY = 'vt-draft-' + (<?= (int) ($_GET['post'] ?? 0) ?> || 'standalone');
        function vtSammleFelder() {
            const felder = {}, checks = {};
            document.querySelectorAll('[id^="vt-"]').forEach(el => {
                if (el.id === 'vt-photo-file') { return; }
                const t = el.tagName;
                if (t === 'INPUT' && el.type === 'checkbox') { checks[el.id] = el.checked; return; }
                if (t === 'SELECT' || t === 'TEXTAREA' || (t === 'INPUT' && ['text', 'number'].includes(el.type))) {
                    felder[el.id] = el.value;
                }
            });
            const bg = document.querySelector('input[name="bgmode"]:checked');
            const photo = (selectedPhotoUrl && !selectedPhotoUrl.startsWith('blob:')) ? selectedPhotoUrl : '';
            return { felder, checks, bgmode: bg ? bg.value : '', photo, positions: thPositions };
        }
        let vtSaveT = null;
        function vtSpeichereDraft() { try { localStorage.setItem(VT_CACHE_KEY, JSON.stringify(vtSammleFelder())); } catch (e) {} }
        function vtSpeichereDebounced() { clearTimeout(vtSaveT); vtSaveT = setTimeout(vtSpeichereDraft, 400); }
        function vtStelleWieder() {
            let raw; try { raw = localStorage.getItem(VT_CACHE_KEY); } catch (e) { return; }
            if (!raw) { return; }
            let d; try { d = JSON.parse(raw); } catch (e) { return; }
            Object.entries(d.felder || {}).forEach(([id, val]) => { const el = document.getElementById(id); if (el) { el.value = val; } });
            Object.entries(d.checks || {}).forEach(([id, val]) => { const el = document.getElementById(id); if (el) { el.checked = !!val; } });
            if (d.bgmode) { const r = document.querySelector('input[name="bgmode"][value="' + d.bgmode + '"]'); if (r) { r.checked = true; } }
            if (d.photo) { selectedPhotoUrl = d.photo; $('vt-photo-name').textContent = 'Gewählt: gespeichertes Foto'; $('vt-clear-photo').style.display = 'inline-flex'; }
            if (d.positions && typeof d.positions === 'object') { thPositions = d.positions; }
            vorlageWechsel();
            $('vt-photo-block').style.display = $('bg-photo').checked ? 'block' : 'none';
        }
        document.addEventListener('input', (e) => { const id = e.target.id || ''; if (id.startsWith('vt-') && id !== 'vt-photo-file') { vtSpeichereDebounced(); } });
        document.addEventListener('change', (e) => { const id = e.target.id || ''; if (e.target.name === 'bgmode' || (id.startsWith('vt-') && id !== 'vt-photo-file')) { vtSpeichereDebounced(); } });
        $('vt-reset-vorlage').addEventListener('click', () => {
            if (!confirm('Diesen Entwurf verwerfen und die Vorlage von vorne beginnen?')) { return; }
            try { localStorage.removeItem(VT_CACHE_KEY); } catch (e) {}
            location.reload();
        });
        vtStelleWieder();

        // --- Slots aus den Eingaben in die Karte schreiben ---
        function fillCard() {
            $('c-headline').textContent = $('vt-headline').value.trim();
            $('c-sub').textContent      = $('vt-sub').value.trim();
            $('c-f1t').textContent = $('vt-f1t').value.trim(); $('c-f1s').textContent = $('vt-f1s').value.trim();
            $('c-f2t').textContent = $('vt-f2t').value.trim(); $('c-f2s').textContent = $('vt-f2s').value.trim();
            $('c-f3t').textContent = $('vt-f3t').value.trim(); $('c-f3s').textContent = $('vt-f3s').value.trim();
            $('c-cta').textContent = $('vt-cta').value.trim();
            $('c-datum').textContent = $('vt-datum').value.trim();
            $('c-datum-zusatz').textContent = $('vt-datum-zusatz').value.trim();
            $('c-ort').textContent = $('vt-ort').value.trim();
            $('c-ort-card').style.display = $('vt-ort').value.trim() ? 'block' : 'none';

            const usePhoto = $('bg-photo').checked && selectedPhotoUrl;
            card.classList.toggle('has-photo', !!usePhoto);
            if (usePhoto) { $('vt-bg').src = selectedPhotoUrl; } else { $('vt-bg').removeAttribute('src'); }
        }

        // --- QR-Ziel aus der Auswahl (feststehende Links) oder dem Eigener-Link-Feld ---
        function qrZielUrl() {
            const sel = $('vt-qr-ziel');
            if (!sel.value) { return ''; }
            if (sel.value === 'eigen') { return $('vt-qr-url').value.trim(); }
            return (sel.options[sel.selectedIndex].dataset.url || '').trim();
        }
        $('vt-qr-ziel').addEventListener('change', () => {
            $('vt-qr-eigen-feld').style.display = $('vt-qr-ziel').value === 'eigen' ? 'block' : 'none';
        });

        // --- QR erzeugen (self-hosted qrcode.js) — je Vorlage eigenes Ziel ---
        function applyQr(wrapId, imgId, labelId, anzeige) {
            const url = qrZielUrl();
            const wrap = $(wrapId), img = $(imgId);
            if (!url || typeof qrcode !== 'function') { wrap.style.display = 'none'; return; }
            try {
                const qr = qrcode(0, 'M'); qr.addData(url); qr.make();
                const count = qr.getModuleCount(), cell = 10, quiet = 2, size = (count + quiet * 2) * cell;
                const cv = document.createElement('canvas'); cv.width = cv.height = size;
                const ctx = cv.getContext('2d');
                ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, size, size);
                ctx.fillStyle = '#000';
                for (let r = 0; r < count; r++) for (let c = 0; c < count; c++) if (qr.isDark(r, c)) ctx.fillRect((c + quiet) * cell, (r + quiet) * cell, cell, cell);
                img.src = cv.toDataURL('image/png');
                $(labelId).textContent = $('vt-qr-label').value.trim() || 'Jetzt scannen & anmelden!';
                wrap.style.display = anzeige;
            } catch (e) { wrap.style.display = 'none'; }
        }

        // --- Logo-Slots der Renntag-Vorlage (tauschbar, Repo-Assets) ---
        function renderLogoChips() {
            const box = $('vt-logo-chips');
            box.innerHTML = '';
            selectedLogos.forEach((l, i) => {
                const c = document.createElement('span'); c.className = 'vt-chip';
                const t = document.createElement('span'); t.textContent = l.label; c.appendChild(t);
                const x = document.createElement('button'); x.type = 'button'; x.textContent = '✕';
                x.addEventListener('click', () => { selectedLogos.splice(i, 1); renderLogoChips(); });
                c.appendChild(x);
                box.appendChild(c);
            });
        }
        $('vt-logo-add').addEventListener('click', () => {
            const panel = $('vt-logo-picker');
            if (panel.style.display === 'flex') { panel.style.display = 'none'; return; }
            panel.innerHTML = ''; panel.style.display = 'flex';
            // Logo-Quellen: Repo-Assets + bestaetigte Sponsoren (S5). Deckel 3 greift beim Klick.
            repoAssets.concat(sponsorLogos).forEach(asset => {
                const t = document.createElement('div'); t.className = 'vt-thumb';
                const im = document.createElement('img'); im.src = asset.url; im.alt = '';
                const nm = document.createElement('span'); nm.textContent = asset.label;
                t.appendChild(im); t.appendChild(nm);
                t.addEventListener('click', () => {
                    if (selectedLogos.length >= LOGO_DECKEL) {
                        alert('Höchstens ' + LOGO_DECKEL + ' Logos in der Leiste (weniger ist mehr). Mehr Sponsoren → eigenes Thema/Carousel.');
                        return;
                    }
                    if (!selectedLogos.some(l => l.url === asset.url)) {
                        selectedLogos.push({ url: asset.url, label: asset.label });
                        renderLogoChips();
                    }
                });
                panel.appendChild(t);
            });
        });
        $('vt-logo-reset').addEventListener('click', () => {
            selectedLogos = [{ url: DEFAULT_LOGO, label: 'ATSV-Logo' }];
            renderLogoChips();
        });
        $('vt-logo-nur-marktlauf').addEventListener('click', () => {
            selectedLogos = [{ url: MARKTLAUF_LOGO, label: 'Marktlauf' }];
            renderLogoChips();
        });
        renderLogoChips();

        // --- Renntag-Karte aus den Eingaben befuellen ---
        function fillCard2(fmt) {
            card2.style.width  = fmt.w + 'px';
            card2.style.height = fmt.h + 'px';
            $('rt-event').textContent    = $('vt-rt-event').value.trim();
            $('rt-event').style.display  = $('vt-hide-event').checked ? 'none' : '';
            $('rt-headline').textContent = $('vt-rt-headline').value.trim();
            $('rt-s10').textContent      = $('vt-rt-s10').value.trim() || '–';
            $('rt-s10z').textContent     = $('vt-rt-s10z').value.trim();
            $('rt-si10').textContent     = $('vt-rt-si10').value.trim() || '–';
            $('rt-si10z').textContent    = $('vt-rt-si10z').value.trim();
            $('rt-tn').textContent       = $('vt-rt-tn').value.trim() || '–';
            $('rt-finisher').textContent = $('vt-rt-finisher').value.trim() || '–';
            const hl = $('vt-rt-highlight').value.trim();
            $('rt-highlight').textContent = hl;
            $('rt-highlight').style.display = hl ? 'block' : 'none';

            const box = $('rt-logos');
            box.innerHTML = '';
            selectedLogos.forEach(l => {
                const im = document.createElement('img'); im.src = l.url; im.alt = '';
                box.appendChild(im);
            });

            const usePhoto = $('bg-photo').checked && selectedPhotoUrl;
            $('rt-logos').classList.toggle('on-photo', !!usePhoto);
            const dark = cssVar('--color-primary-dark', '#007230');
            if (usePhoto) {
                $('rt-bg').src = selectedPhotoUrl;
                $('rt-bg').style.display = 'block';
                $('rt-overlay').style.background = 'linear-gradient(160deg, rgba(0,0,0,0.28) 0%, ' + hexToRgba(dark, 0.78) + ' 100%)';
            } else {
                $('rt-bg').removeAttribute('src');
                $('rt-bg').style.display = 'none';
                $('rt-overlay').style.background = heroOverlay();
            }
        }

        function waitImg(img) {
            return new Promise(resolve => {
                if (!img.getAttribute('src') || (img.complete && img.naturalWidth)) { resolve(); return; }
                img.onload = resolve; img.onerror = resolve;
            });
        }

        // --- Themen-Post-Karte aus den Eingaben befuellen ---
        const card3 = $('vt-card3');
        function fillCard3(fmt) {
            card3.style.width  = fmt.w + 'px';
            card3.style.height = fmt.h + 'px';
            $('th-headline').textContent = $('vt-th-headline').value.trim();
            const sub = $('vt-th-sub').value.trim();
            $('th-sub').textContent = sub;
            $('th-sub').style.display = sub ? 'block' : 'none';
            let zeilen = 0;
            for (let i = 1; i <= 3; i++) {
                const wert = $('vt-th-z' + i).value.trim();
                $('th-z' + i).textContent = wert;
                $('th-z' + i).style.display = wert ? 'flex' : 'none';
                if (wert) { zeilen++; }
            }
            $('th-bullets').style.display = zeilen ? 'flex' : 'none';
            const cta = $('vt-th-cta').value.trim();
            $('th-cta').textContent = cta;
            $('th-cta-wrap').style.display = cta ? 'block' : 'none';
            const datum = $('vt-th-datum').value.trim();
            const ort   = $('vt-th-ort').value.trim();
            $('th-datum').textContent = datum ? '📅 ' + datum : '';
            $('th-datum').style.display = datum ? 'block' : 'none';
            $('th-ort').textContent = ort ? '📍 ' + ort : '';
            $('th-ort').style.display = ort ? 'block' : 'none';

            const thEvent = document.querySelector('#vt-card3 .sc-event');
            if (thEvent) { thEvent.style.display = $('vt-hide-event').checked ? 'none' : ''; }

            const box = $('th-logos');
            box.innerHTML = '';
            selectedLogos.forEach(l => {
                const im = document.createElement('img'); im.src = l.url; im.alt = '';
                box.appendChild(im);
            });

            const usePhoto = $('bg-photo').checked && selectedPhotoUrl;
            $('th-logos').classList.toggle('on-photo', !!usePhoto);
            const dark = cssVar('--color-primary-dark', '#007230');
            if (usePhoto) {
                $('th-bg').src = selectedPhotoUrl;
                $('th-bg').style.display = 'block';
                $('th-overlay').style.background = 'linear-gradient(160deg, rgba(0,0,0,0.28) 0%, ' + hexToRgba(dark, 0.78) + ' 100%)';
            } else {
                $('th-bg').removeAttribute('src');
                $('th-bg').style.display = 'none';
                $('th-overlay').style.background = heroOverlay();
            }
        }

        // --- Rendern (snapDOM, dpr:1 zwingend; Fonts eingebettet) ---
        $('vt-render').addEventListener('click', async () => {
            const btn = $('vt-render'), err = $('vt-error');
            btn.disabled = true; btn.textContent = '⏳ Rendert …'; err.style.display = 'none';

            const vorlage = aktiveVorlage();
            const fmt = vorlage !== 'anmeldung'
                ? (RT_FORMATS[$('vt-rt-format').value] || RT_FORMATS.portrait)
                : { w: 1080, h: 1350, label: 'Portrait 1080×1350' };

            try {
                await Promise.all([
                    document.fonts.load('700 100px Fredoka'),
                    document.fonts.load('600 40px Poppins'),
                    document.fonts.load('400 40px Poppins'),
                ]);
                await document.fonts.ready;

                let ziel;
                if (vorlage === 'renntag') {
                    fillCard2(fmt);
                    applyQr('rt-qr', 'rt-qr-img', 'rt-qr-label', 'flex');
                    const logoImgs = Array.from(document.querySelectorAll('#rt-logos img'));
                    await Promise.all([
                        ...logoImgs.map(waitImg),
                        card2.querySelector('.sc-bg').style.display === 'block' ? waitImg($('rt-bg')) : Promise.resolve(),
                        waitImg($('rt-qr-img')),
                    ]);
                    ziel = card2;
                } else if (vorlage === 'thema') {
                    fillCard3(fmt);
                    applyQr('th-qr', 'th-qr-img', 'th-qr-label', 'flex');
                    const logoImgs = Array.from(document.querySelectorAll('#th-logos img'));
                    await Promise.all([
                        ...logoImgs.map(waitImg),
                        card3.querySelector('.sc-bg').style.display === 'block' ? waitImg($('th-bg')) : Promise.resolve(),
                        waitImg($('th-qr-img')),
                    ]);
                    ziel = card3;
                } else {
                    fillCard();
                    applyQr('c-qr', 'c-qr-img', 'c-qr-label', 'block');
                    await Promise.all([
                        waitImg($('vt-mark')), waitImg($('vt-atsv')), waitImg($('vt-gemeinde')),
                        card.classList.contains('has-photo') ? waitImg($('vt-bg')) : waitImg($('vt-runner')),
                        waitImg($('c-qr-img')),
                    ]);
                    ziel = card;
                }

                const canvas = await snapdom.toCanvas(ziel, {
                    width: fmt.w, height: fmt.h, scale: 1, dpr: 1,
                    backgroundColor: vorlage !== 'anmeldung' ? cssVar('--color-primary-dark', '#007230') : '#1f7a3a',
                    embedFonts: true,
                });
                lastDataUrl = canvas.toDataURL('image/png');
                $('vt-card-img').src = lastDataUrl;
                $('vt-card-img').style.display = 'block';
                $('vt-preview-empty').style.display = 'none';
                $('vt-caption').textContent = 'Vorschau (' + fmt.label + '):';
                $('vt-download').style.display = 'inline-block';
                if (postKontext) { $('vt-uebernehmen').style.display = 'inline-block'; }
            } catch (e) {
                err.textContent = 'Render-Fehler: ' + (e && e.message ? e.message : e);
                err.style.display = 'block';
            } finally {
                btn.disabled = false; btn.textContent = 'Grafik erzeugen';
            }
        });

        // ================= B: Freie Text-Platzierung (Themen-Post, Beta) =================
        // Verifiziert im Mockup: skalierte Live-Buehne + absolut positionierte, ziehbare
        // Bloecke; snapDOM exportiert die Anordnung trotz transform:scale korrekt bei 1080.
        const liveWrap = $('vt-live-wrap'), liveVp = $('vt-live-vp'), liveScale = $('vt-live-scale');
        const thStage = document.querySelector('.vt-stage'); // Ruecksprung-Ziel fuer #vt-card3
        function thDragEls() { return Array.from(card3.querySelectorAll('.vt-drag')); }
        function keyOf(el) { return el.dataset.drag || el.id; }
        function freiAktiv() { return $('vt-freilayout').checked && aktiveVorlage() === 'thema'; }
        function freiScale() {
            const fmt = RT_FORMATS[$('vt-rt-format').value] || RT_FORMATS.portrait;
            const vw = liveVp.clientWidth || 340;
            return { s: vw / fmt.w, w: fmt.w, h: fmt.h };
        }
        function freilayoutEin() {
            // ERST sichtbar machen: in einem display:none-Teilbaum hat #vt-card3 keine
            // Layout-Groesse (offsetWidth=0) -> Messung ergaebe NaN-Positionen -> die Bloecke
            // kollabieren und lassen sich nicht ziehen. Deshalb vor jeder Messung anzeigen.
            $('vt-card-img').style.display = 'none';
            $('vt-preview-empty').style.display = 'none';
            liveWrap.style.display = 'block';
            const sw = freiScale(), w = sw.w, h = sw.h;
            card3.style.width = w + 'px'; card3.style.height = h + 'px';
            liveScale.appendChild(card3);
            liveScale.style.transform = 'none';   // bei natuerlicher Groesse messen
            fillCard3({ w: w, h: h });
            // Default-Positionen aus dem Flow-Layout messen (border-box-relativ; Karte hat keinen
            // Border -> Padding-Box == Border-Box, %-Basis = offsetWidth passt zu left/top in %).
            const cr = card3.getBoundingClientRect(), bw = card3.offsetWidth, bh = card3.offsetHeight;
            thDragEls().forEach(el => {
                const k = keyOf(el);
                if (!thPositions[k] && bw > 0) {
                    const r = el.getBoundingClientRect();
                    thPositions[k] = { l: +((r.left - cr.left) / bw * 100).toFixed(1), t: +((r.top - cr.top) / bh * 100).toFixed(1) };
                }
            });
            card3.classList.add('freilayout');
            thDragEls().forEach(el => { const p = thPositions[keyOf(el)]; if (p) { el.style.left = p.l + '%'; el.style.top = p.t + '%'; } });
            liveScale.style.transform = 'scale(' + sw.s + ')';
            liveVp.style.height = (h * sw.s) + 'px';
        }
        function freilayoutAus() {
            card3.classList.remove('freilayout');
            thDragEls().forEach(el => { el.style.left = ''; el.style.top = ''; });
            if (card3.parentElement !== thStage) { thStage.appendChild(card3); }
            liveScale.style.transform = '';
            liveWrap.style.display = 'none';
        }
        $('vt-freilayout').addEventListener('change', () => {
            if (freiAktiv()) { freilayoutEin(); } else { freilayoutAus(); }
        });
        // Layoutwechsel weg vom Themen-Post beendet den Frei-Modus sauber
        $('vt-vorlage').addEventListener('change', () => {
            if (!freiAktiv() && card3.classList.contains('freilayout')) { freilayoutAus(); }
        });

        let thDrag = null;
        card3.addEventListener('pointerdown', (e) => {
            if (!card3.classList.contains('freilayout')) { return; }
            const el = e.target.closest('.vt-drag');
            if (!el || !card3.contains(el)) { return; }
            e.preventDefault();
            thDrag = { el: el, sx: e.clientX, sy: e.clientY, ol: el.offsetLeft, ot: el.offsetTop, s: freiScale().s };
            el.setPointerCapture(e.pointerId);
        });
        card3.addEventListener('pointermove', (e) => {
            if (!thDrag) { return; }
            const cw = card3.offsetWidth, ch = card3.offsetHeight;
            const dx = (e.clientX - thDrag.sx) / thDrag.s, dy = (e.clientY - thDrag.sy) / thDrag.s;
            const l = Math.max(0, Math.min(92, (thDrag.ol + dx) / cw * 100));
            const t = Math.max(0, Math.min(94, (thDrag.ot + dy) / ch * 100));
            thDrag.el.style.left = l.toFixed(1) + '%';
            thDrag.el.style.top  = t.toFixed(1) + '%';
            thPositions[keyOf(thDrag.el)] = { l: +l.toFixed(1), t: +t.toFixed(1) };
        });
        card3.addEventListener('pointerup', () => { if (thDrag) { thDrag = null; vtSpeichereDebounced(); } });
        // Aus dem Cache wiederhergestellter Frei-Modus aktivieren (nach vollem Setup)
        if (freiAktiv()) { freilayoutEin(); }

        $('vt-download').addEventListener('click', () => {
            if (!lastDataUrl) return;
            const a = document.createElement('a');
            a.href = lastDataUrl;
            a.download = 'marktlauf2026-' + (aktiveVorlage() === 'anmeldung'
                ? 'anmeldung-portrait'
                : aktiveVorlage() + '-' + $('vt-rt-format').value) + '.png';
            a.click();
        });

        // --- Grafik am Post speichern und zurueck zum Post-Detail ---
        if (postKontext) {
            $('vt-uebernehmen').addEventListener('click', async () => {
                if (!lastDataUrl) return;
                const btn = $('vt-uebernehmen'), err = $('vt-error');
                btn.disabled = true; btn.textContent = '⏳ Speichert …'; err.style.display = 'none';
                try {
                    const body = new URLSearchParams();
                    body.set('csrf_token', csrf);
                    body.set('post_id', postKontext.id);
                    body.set('image_base64', lastDataUrl);
                    const r = await fetch('api/post_bild.php', { method: 'POST', body });
                    const d = await r.json();
                    if (d.ok) {
                        if (embed && window.parent !== window) {
                            // Kein Seitenwechsel: Elternseite (Post-Detail) neu laden lassen
                            window.parent.postMessage({ type: 'vt-uebernommen', fahrplanId: fahrplanId }, location.origin);
                        } else {
                            window.location.href = 'social_post.php?fahrplan=' + fahrplanId;
                        }
                        return;
                    }
                    err.textContent = '⚠️ ' + (d.message || 'Speichern fehlgeschlagen.');
                    err.style.display = 'block';
                } catch (e) {
                    err.textContent = '⚠️ Netzwerkfehler.';
                    err.style.display = 'block';
                } finally {
                    btn.disabled = false; btn.textContent = 'Für Post übernehmen';
                }
            });
        }

        // --- Embed: eigene Hoehe an die Elternseite melden (kein innerer Scrollbalken) ---
        if (embed && window.parent !== window) {
            const meldeHoehe = () => {
                const h = Math.ceil(document.body.scrollHeight);
                window.parent.postMessage({ type: 'vt-height', height: h }, location.origin);
            };
            window.addEventListener('load', meldeHoehe);
            if (window.ResizeObserver) { new ResizeObserver(meldeHoehe).observe(document.body); }
            meldeHoehe();
        }

        // --- Auto-Erzeugung beim Oeffnen (Inhaber-Wunsch 2026-08-22): sofort mit den
        // aktuellen Einstellungen eine Grafik rendern statt auf den Button zu warten.
        // Vorher fragen, ob ein Foto als Hintergrund verwendet werden soll. Nur mit
        // Post-Kontext (der eingebettete Editor im Post-Detail). ?noauto=1 schaltet es ab.
        if (postKontext && !/[?&]noauto=1/.test(location.search)) {
            const autoErzeugen = () => {
                const wollenFoto = window.confirm('Soll für die Grafik ein Foto als Hintergrund verwendet werden?\n\nOK = Foto   ·   Abbrechen = Farbverlauf');
                if (wollenFoto) {
                    $('bg-photo').checked = true;
                    $('vt-photo-block').style.display = 'block';
                } else {
                    $('bg-gradient').checked = true;
                    $('vt-photo-block').style.display = 'none';
                }
                $('vt-render').click();
            };
            if (document.readyState === 'complete') { autoErzeugen(); }
            else { window.addEventListener('load', autoErzeugen); }
        }
    })();

    (function() {
        const burger = document.getElementById('burger-btn');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        if (!burger || !sidebar || !overlay) { return; }  // Embed-Modus: keine Sidebar
        function closeSidebar() { sidebar.classList.remove('open'); overlay.classList.remove('open'); document.body.style.overflow = ''; }
        burger.addEventListener('click', function() { sidebar.classList.add('open'); overlay.classList.add('open'); document.body.style.overflow = 'hidden'; });
        overlay.addEventListener('click', closeSidebar);
        sidebar.querySelectorAll('.nav-item a').forEach(function(link) { link.addEventListener('click', closeSidebar); });
    })();
    </script>
</body>
</html>
