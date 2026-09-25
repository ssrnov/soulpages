<?php
header('Content-Type: text/plain');
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "SoulSync Require Validation Tool\n";
echo "=============================\n\n";

$files = [
    'includes/db.php' => '../includes/db.php',
    'includes/functions.php' => '../includes/functions.php',
    'admin/categories.php' => './categories.php',
    'index.php' => '../index.php'
];

foreach ($files as $name => $path) {
    echo "Testing require of '$name' ($path)...\n";
    try {
        // We run these in isolation if possible, or just sequentially
        require_once $path;
        echo "  - SUCCESS: Loaded successfully!\n";
    } catch (Throwable $t) {
        echo "  - FAILED: " . $t->getMessage() . "\n";
        echo "    File: " . $t->getFile() . " on line " . $t->getLine() . "\n";
    }
    echo "-------------------------------------------\n\n";
}
