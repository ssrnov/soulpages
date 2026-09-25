<?php
header('Content-Type: text/plain');
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "SoulSync Database Diagnostic Tool\n";
echo "=============================\n\n";

$db_host = 'localhost';
$db_name = 'looprsi1_nothing';
$db_user = 'looprsi1_ssrnov';
$db_pass = 'Jayshreeram@12345';

echo "Testing PDO Connection to: $db_host / $db_name (User: $db_user)\n";

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
    echo "SUCCESS: Connected to database successfully!\n\n";
    
    // Check tables
    echo "Testing queries...\n";
    $tables = ['users', 'categories', 'pages', 'site_settings'];
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
            $count = $stmt->fetchColumn();
            echo "  - Table '$table': OK ($count rows)\n";
        } catch (PDOException $ex) {
            echo "  - Table '$table': FAILED (Error: " . $ex->getMessage() . ")\n";
        }
    }
} catch (PDOException $e) {
    echo "CONNECTION FAILED: " . $e->getMessage() . "\n";
}
