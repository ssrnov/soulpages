<?php
header('Content-Type: text/plain');
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "SoulSync Admin DB Test\n";
echo "===================\n\n";

$db_host = 'localhost';
$db_name = 'looprsi1_nothing';
$db_user = 'looprsi1_ssrnov';
$db_pass = 'Jayshreeram@12345';

try {
    echo "1. Creating PDO instance...\n";
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
    echo "   PDO connection created.\n\n";

    echo "2. Testing SELECT 1...\n";
    $stmt = $pdo->query("SELECT 1");
    $result = $stmt->fetchColumn();
    echo "   SELECT 1 returned: $result\n\n";

    echo "3. Querying categories count...\n";
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM categories");
        $count = $stmt->fetchColumn();
        echo "   Categories count: $count\n\n";
    } catch (PDOException $ex) {
        echo "   Querying categories failed: " . $ex->getMessage() . "\n\n";
    }

    echo "4. Querying pages count...\n";
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM pages");
        $count = $stmt->fetchColumn();
        echo "   Pages count: $count\n\n";
    } catch (PDOException $ex) {
        echo "   Querying pages failed: " . $ex->getMessage() . "\n\n";
    }

    echo "5. Querying site_settings count...\n";
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM site_settings");
        $count = $stmt->fetchColumn();
        echo "   Site settings count: $count\n\n";
    } catch (PDOException $ex) {
        echo "   Querying site_settings failed: " . $ex->getMessage() . "\n\n";
    }

} catch (Exception $e) {
    echo "CRITICAL ERROR: " . $e->getMessage() . "\n";
}
