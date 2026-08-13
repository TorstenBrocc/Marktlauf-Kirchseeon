<?php
/**
 * Orga Logout
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';

logout();

header('Location: login.php');
exit;
