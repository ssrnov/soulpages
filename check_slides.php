<?php
header('Content-Type: text/plain');
ini_set('display_errors', 1);
error_reporting(E_ALL);

$db_host = 'localhost';
$db_name = 'looprsi1_nothing';
$db_user = 'looprsi1_ssrnov';
$db_pass = 'Jayshreeram@12345';

try {
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    
    $stmt = $pdo->query("SELECT slug, name, slides FROM categories");
    while ($row = $stmt->fetch()) {
        echo "Category: {$row['name']} ({$row['slug']})\n";
        echo "Slides:\n";
        $slides = json_decode($row['slides'], true);
        if (is_array($slides)) {
            foreach ($slides as $slide) {
                $opt = !empty($slide['is_optional']) ? " (OPTIONAL)" : "";
                echo "  - {$slide['title']} [{$slide['key']}] type: {$slide['type']}{$opt}\n";
            }
        } else {
            echo "  (No slides or invalid JSON)\n";
        }
        echo "\n-------------------------------------\n\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
