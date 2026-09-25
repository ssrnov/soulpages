<?php
header('Content-Type: text/plain');
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "SoulSync Payments Table Creator & Diagnostic Tool\n";
echo "===============================================\n\n";

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
    echo "Connected successfully!\n\n";

    // 1. Check if table 'payments' exists
    echo "Checking if 'payments' table exists...\n";
    $table_exists = false;
    try {
        $pdo->query("SELECT 1 FROM `payments` LIMIT 1");
        $table_exists = true;
        echo "  - Table 'payments' ALREADY EXISTS.\n";
    } catch (PDOException $e) {
        echo "  - Table 'payments' does NOT exist (or error: " . $e->getMessage() . ").\n";
    }

    // 2. If it doesn't exist, try to create it
    if (!$table_exists) {
        echo "\nAttempting to create 'payments' table...\n";
        $create_sql = "CREATE TABLE IF NOT EXISTS `payments` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `razorpay_order_id` VARCHAR(100) NULL,
            `razorpay_payment_id` VARCHAR(100) NULL,
            `razorpay_signature` VARCHAR(255) NULL,
            `amount` INT NOT NULL DEFAULT 0,
            `currency` VARCHAR(10) DEFAULT 'INR',
            `status` VARCHAR(20) DEFAULT 'created',
            `credits_added` INT DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        try {
            $pdo->exec($create_sql);
            echo "  - SUCCESS: 'payments' table created successfully!\n";
        } catch (PDOException $e) {
            echo "  - FAILED to create table: " . $e->getMessage() . "\n";
        }
    }

    // 3. Let's check if we can query it now
    echo "\nVerifying table structure/query...\n";
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM `payments`");
        $count = $stmt->fetchColumn();
        echo "  - Table 'payments' query OK. Count: $count rows.\n";
    } catch (PDOException $e) {
        echo "  - Table 'payments' query FAILED: " . $e->getMessage() . "\n";
    }

} catch (Throwable $e) {
    echo "\nCRITICAL ERROR: " . $e->getMessage() . " (File: " . $e->getFile() . " on line " . $e->getLine() . ")\n";
}
