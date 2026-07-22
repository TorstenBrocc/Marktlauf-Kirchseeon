<?php
/**
 * Gemeinsame Head-Elemente (Fonts, CSS)
 *
 * Erwartet: $basePath (z.B. '' für root, '../' für orga/)
 */

$basePath = $basePath ?? '';
?>
    <link rel="icon" type="image/svg+xml" href="<?= $basePath ?>assets/images/logo-final.svg">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="<?= $basePath ?>css/base.css?v=<?= @filemtime(__DIR__ . '/../../css/base.css') ?>">
    <link rel="stylesheet" href="<?= $basePath ?>css/layout.css?v=<?= @filemtime(__DIR__ . '/../../css/layout.css') ?>">
    <link rel="stylesheet" href="<?= $basePath ?>css/components.css?v=<?= @filemtime(__DIR__ . '/../../css/components.css') ?>">
