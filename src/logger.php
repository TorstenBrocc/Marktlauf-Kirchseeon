<?php
declare(strict_types=1);

function logError(string $message): void {
    $logPath = __DIR__ . '/../storage/logs/php_errors.log';
    error_log('[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, 3, $logPath);
}
