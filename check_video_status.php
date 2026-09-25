<?php
require_once 'includes/functions.php';

header('Content-Type: text/plain');

echo "=== LIVE VIDEO DIAGNOSTICS ===\n\n";

try {
    $stmt = $pdo->query("SELECT id, slug, title, video_url, created_at FROM pages WHERE video_url IS NOT NULL AND video_url != '' ORDER BY id DESC LIMIT 5");
    $pages = $stmt->fetchAll();
    
    if (empty($pages)) {
        echo "No pages found with a non-empty video_url in the database.\n";
    } else {
        foreach ($pages as $p) {
            echo "Page ID: {$p['id']}\n";
            echo "Slug: {$p['slug']}\n";
            echo "Title: {$p['title']}\n";
            echo "Video URL (DB): '{$p['video_url']}'\n";
            
            // Check file existence
            $local_file = __DIR__ . '/' . $p['video_url'];
            echo "Local path: '$local_file'\n";
            if (file_exists($local_file)) {
                echo "File exists on server: YES\n";
                echo "File size: " . filesize($local_file) . " bytes\n";
            } else {
                echo "File exists on server: NO\n";
            }
            
            // Directory check
            $dir = dirname($local_file);
            echo "Directory: '$dir'\n";
            echo "Directory exists: " . (is_dir($dir) ? "YES" : "NO") . "\n";
            echo "Directory writable: " . (is_writable($dir) ? "YES" : "NO") . "\n";
            
            echo "----------------------------------------\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
