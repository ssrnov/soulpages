<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/plain');

echo "Testing require of includes/functions.php...\n";

try {
    require_once '../includes/functions.php';
    echo "SUCCESS: includes/functions.php required successfully!\n";
    if (isset($pdo)) {
        echo "PDO database connection variable is set!\n";
    } else {
        echo "PDO database connection variable is NOT set!\n";
    }
} catch (Throwable $t) {
    echo "ERROR caught during require: " . $t->getMessage() . "\n";
    echo "File: " . $t->getFile() . " on line " . $t->getLine() . "\n";
}
