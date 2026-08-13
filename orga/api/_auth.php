<?php
/**
 * Auth Guard für geschützte Orga-Seiten
 * Wird von jeder geschützten Seite per require eingebunden.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../src/auth.php';

$currentUser = requireLogin();

function getCurrentUserFromGuard(): array {
    global $currentUser;
    return $currentUser;
}

function isAdminFromGuard(): bool {
    global $currentUser;
    return $currentUser['role'] === 'admin';
}
