<?php
header('Content-Type: text/plain');
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "SoulSync Database Repair & Integrity Tool\n";
echo "====================================\n\n";

$db_host = 'localhost';
$db_name = 'looprsi1_nothing';
$db_user = 'looprsi1_ssrnov';
$db_pass = 'Jayshreeram@12345';

try {
    echo "Connecting to database...\n";
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
    echo "Connected.\n\n";

    $tables = ['categories', 'pages', 'users', 'site_settings', 'page_images', 'expiry_extensions'];
    
    foreach ($tables as $table) {
        echo "Checking table '$table'...\n";
        try {
            $stmt = $pdo->query("CHECK TABLE `$table`");
            $result = $stmt->fetchAll();
            foreach ($result as $row) {
                echo "  - " . $row['Op'] . " | " . $row['Msg_type'] . " | " . $row['Msg_text'] . "\n";
            }
        } catch (PDOException $e) {
            echo "  - CHECK FAILED: " . $e->getMessage() . "\n";
        }
        
        echo "Attempting to query table '$table' status...\n";
        try {
            $stmt = $pdo->query("SHOW TABLE STATUS LIKE '$table'");
            $status = $stmt->fetch();
            if ($status) {
                echo "  - Engine: " . $status['Engine'] . " | Rows: " . $status['Rows'] . "\n";
            } else {
                echo "  - No status returned (table may not exist).\n";
            }
        } catch (PDOException $e) {
            echo "  - STATUS QUERY FAILED: " . $e->getMessage() . "\n";
        }
        echo "\n";
    }

} catch (Exception $e) {
    echo "CRITICAL CONNECTION ERROR: " . $e->getMessage() . "\n";
}
