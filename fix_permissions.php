<?php
header('Content-Type: text/plain');
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "=== FIXING UPLOADS DIRECTORY PERMISSIONS ===\n\n";

function fix_perms_recursive($dir) {
    if (!is_dir($dir)) {
        echo "Directory does not exist: $dir\n";
        return;
    }
    
    // Fix current directory permissions
    echo "Directory: $dir -> chmod 0755\n";
    @chmod($dir, 0755);
    
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            fix_perms_recursive($path);
        } else {
            // Fix file permissions
            $old_perms = sprintf('%o', fileperms($path));
            if (@chmod($path, 0644)) {
                $new_perms = sprintf('%o', fileperms($path));
                echo "  File: $file (perms was $old_perms -> now $new_perms) - OK\n";
            } else {
                echo "  File: $file (perms was $old_perms) - FAILED to chmod\n";
            }
        }
    }
}

$uploads_dir = __DIR__ . '/uploads';
fix_perms_recursive($uploads_dir);

echo "\nDone!\n";
?>
