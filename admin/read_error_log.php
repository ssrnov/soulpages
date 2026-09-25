<?php
header('Content-Type: text/plain');
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "SoulSync Admin Server Error Log Reader\n";
echo "==================================\n\n";

$log_paths = [
    __DIR__ . '/error_log',
    __DIR__ . '/../error_log',
    __DIR__ . '/../../error_log',
    ini_get('error_log')
];

foreach ($log_paths as $path) {
    if (empty($path)) continue;
    echo "Checking log path: $path\n";
    if (file_exists($path)) {
        echo "FOUND! Size: " . filesize($path) . " bytes. Last 50 lines:\n";
        echo "--------------------------------------------------------\n";
        $lines = file($path);
        $last_lines = array_slice($lines, -50);
        echo implode("", $last_lines);
        echo "--------------------------------------------------------\n\n";
    } else {
        echo "Not found.\n\n";
    }
}
