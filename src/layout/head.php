<?php
/**
 * Gemeinsame Head-Elemente (Fonts, CSS)
 *
 * Erwartet: $basePath (z.B. '' für root, '../' für orga/)
 */

$basePath = $basePath ?? '';
?>
    <link rel="icon" type="image/png" href="<?= $basePath ?>assets/images/ATSV_Logo-750x968.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="<?= $basePath ?>css/base.css">
    <link rel="stylesheet" href="<?= $basePath ?>css/layout.css">
    <link rel="stylesheet" href="<?= $basePath ?>css/components.css">
