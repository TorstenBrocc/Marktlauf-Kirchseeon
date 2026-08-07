<?php
/**
 * Öffentliche Token-CSS — generiert aus der kanonischen Quelle (css/base.css +
 * orga/css/orga.css) über src/design_tokens.php. Speist die statischen DS-Paket-
 * Seiten (guidelines/*.html), damit sie NICHT das Paket-`styles.css` (Derivat)
 * einbinden müssen. Nur Token-Werte, keine sensiblen Daten.
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/design_tokens.php';

header('Content-Type: text/css; charset=utf-8');
header('Cache-Control: public, max-age=300');

echo "/* Generiert aus css/base.css + orga/css/orga.css — nicht von Hand editieren. */\n";
echo ds_render_root_css();
