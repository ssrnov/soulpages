<?php
header('Content-Type: text/plain');
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "SoulSync Table Diagnostic Tool\n";
echo "============================\n\n";

$db_host = 'localhost';
$db_name = 'looprsi1_nothing';
$db_user = 'looprsi1_ssrnov';
$db_pass = 'Jayshreeram@12345';

function check_table($db_host, $db_name, $db_user, $db_pass, $table) {
    echo "Testing table '$table'...\n";
    try {
        $pdo = new PDO(
            "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
            $db_user,
            $db_pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        
        echo "  - Connection: OK\n";
        
        $stmt = $pdo->query("SELECT 1");
        echo "  - SELECT 1: OK\n";
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
        $count = $stmt->fetchColumn();
        echo "  - SELECT COUNT(*): SUCCESS ($count rows)\n";
        
    } catch (Throwable $e) {
        echo "  - FAILED: " . $e->getMessage() . " (File: " . $e->getFile() . " on line " . $e->getLine() . ")\n";
    }
    echo "-------------------------------------------\n\n";
}

$tables = ['site_settings', 'users', 'pages', 'categories', 'page_images', 'expiry_extensions', 'payments'];

foreach ($tables as $t) {
    check_table($db_host, $db_name, $db_user, $db_pass, $t);
}
