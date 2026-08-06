<?php
/**
 * Gemeinsame Head-Elemente (Fonts, CSS)
 *
 * Erwartet: $basePath (z.B. '' für root, '../' für orga/)
 */

$basePath = $basePath ?? '';
?>
    <link rel="icon" type="image/svg+xml" href="<?= $basePath ?>assets/images/logo-final.svg">

    <link rel="stylesheet" href="<?= $basePath ?>css/fonts.css?v=<?= @filemtime(__DIR__ . '/../../css/fonts.css') ?>">

    <link rel="stylesheet" href="<?= $basePath ?>css/base.css?v=<?= @filemtime(__DIR__ . '/../../css/base.css') ?>">
    <link rel="stylesheet" href="<?= $basePath ?>css/layout.css?v=<?= @filemtime(__DIR__ . '/../../css/layout.css') ?>">
    <link rel="stylesheet" href="<?= $basePath ?>css/components.css?v=<?= @filemtime(__DIR__ . '/../../css/components.css') ?>">
