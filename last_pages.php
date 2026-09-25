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
    
    echo "Last 5 Created Pages:\n";
    echo "====================\n\n";
    
    $stmt = $pdo->query("SELECT id, slug, sender_name, receiver_name, created_at, slide_data FROM pages ORDER BY id DESC LIMIT 5");
    while ($row = $stmt->fetch()) {
        echo "Page ID: {$row['id']}\n";
        echo "Slug: {$row['slug']}\n";
        echo "Sender: {$row['sender_name']} -> Recipient: {$row['receiver_name']}\n";
        echo "Created At: {$row['created_at']}\n";
        echo "Slide Data JSON:\n";
        
        $sd = json_decode($row['slide_data'], true);
        if (is_array($sd)) {
            // Print images keys
            foreach ($sd as $k => $v) {
                if (strpos($k, '_images') !== false) {
                    echo "  Key '$k': " . count($v) . " images found.\n";
                    foreach ($v as $img) {
                        echo "    - Path: {$img['original']}\n";
                        echo "      Caption: {$img['caption']}\n";
                    }
                }
            }
        } else {
            echo "  (No slide data or invalid JSON)\n";
        }
        
        // Print page_images
        $stmt_img = $pdo->prepare("SELECT image_path, position FROM page_images WHERE page_id = ? ORDER BY position");
        $stmt_img->execute([$row['id']]);
        $imgs = $stmt_img->fetchAll();
        echo "Gallery Images: " . count($imgs) . " images found.\n";
        foreach ($imgs as $img) {
            echo "  Position {$img['position']}: {$img['image_path']}\n";
        }
        
        echo "\n-------------------------------------\n\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
