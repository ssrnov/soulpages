<?php
// SoulSync Daily Cleanup Cron Job
// Run once daily (e.g., at 3:00 AM)
// CLI Command: php /path/to/cron/cleanup_expired.php

// Ensure running via CLI (security barrier)
if (php_sapi_name() !== 'cli' && isset($_SERVER['REMOTE_ADDR'])) {
    die("Unauthorized: CLI execution only.\n");
}

require_once __DIR__ . '/../includes/functions.php';

try {
    echo "[" . date('Y-m-d H:i:s') . "] Starting SoulSync cleanup...\n";

    // 1. Fetch all expired pages
    $stmt = $pdo->prepare("SELECT id, slug, voice_url, letter_voice_url, video_url, music_url, storage_bytes FROM pages WHERE expiry_date < NOW()");
    $stmt->execute();
    $expired_pages = $stmt->fetchAll();

    if (empty($expired_pages)) {
        echo "No expired pages found to process.\n";
        exit;
    }

    echo "Found " . count($expired_pages) . " expired pages to clean up.\n";

    $deleted_pages_count = 0;
    $deleted_files_count = 0;
    $total_bytes_recovered = 0;

    foreach ($expired_pages as $p) {
        $page_id = $p['id'];
        echo "Processing Page ID $page_id ({$p['slug']})...\n";

        // A. Delete main voice / video files
        $main_files = ['voice_url', 'letter_voice_url', 'video_url'];
        foreach ($main_files as $field) {
            if (!empty($p[$field])) {
                $file_path = __DIR__ . '/../' . $p[$field];
                if (file_exists($file_path) && is_file($file_path)) {
                    if (@unlink($file_path)) {
                        $deleted_files_count++;
                    }
                }
            }
        }

        // B. Delete custom private music associated with this page
        if (!empty($p['music_url'])) {
            $music_stmt = $pdo->prepare("SELECT id, file_path FROM music_library WHERE file_path = ? AND is_private = 1");
            $music_stmt->execute([$p['music_url']]);
            $private_music = $music_stmt->fetch();
            if ($private_music) {
                $music_file = __DIR__ . '/../' . $private_music['file_path'];
                if (file_exists($music_file) && is_file($music_file)) {
                    if (@unlink($music_file)) {
                        $deleted_files_count++;
                    }
                }
                $pdo->prepare("DELETE FROM music_library WHERE id = ?")->execute([$private_music['id']]);
            }
        }

        // C. Delete associated page images (original, thumb, medium)
        $img_stmt = $pdo->prepare("SELECT image_path, thumb_path, medium_path FROM page_images WHERE page_id = ?");
        $img_stmt->execute([$page_id]);
        $images = $img_stmt->fetchAll();
        foreach ($images as $img) {
            $img_paths = ['image_path', 'thumb_path', 'medium_path'];
            foreach ($img_paths as $path_field) {
                if (!empty($img[$path_field])) {
                    $file_path = __DIR__ . '/../' . $img[$path_field];
                    if (file_exists($file_path) && is_file($file_path)) {
                        if (@unlink($file_path)) {
                            $deleted_files_count++;
                        }
                    }
                }
            }
        }

        // D. Delete associated page videos
        $vid_stmt = $pdo->prepare("SELECT video_path FROM page_videos WHERE page_id = ?");
        $vid_stmt->execute([$page_id]);
        $videos = $vid_stmt->fetchAll();
        foreach ($videos as $vid) {
            if (!empty($vid['video_path'])) {
                $file_path = __DIR__ . '/../' . $vid['video_path'];
                if (file_exists($file_path) && is_file($file_path)) {
                    if (@unlink($file_path)) {
                        $deleted_files_count++;
                    }
                }
            }
        }

        // E. Delete associated reply files
        $rep_stmt = $pdo->prepare("SELECT voice_path, image_path, video_path FROM page_replies WHERE page_id = ?");
        $rep_stmt->execute([$page_id]);
        $replies = $rep_stmt->fetchAll();
        foreach ($replies as $rep) {
            $rep_paths = ['voice_path', 'image_path', 'video_path'];
            foreach ($rep_paths as $path_field) {
                if (!empty($rep[$path_field])) {
                    $file_path = __DIR__ . '/../' . $rep[$path_field];
                    if (file_exists($file_path) && is_file($file_path)) {
                        if (@unlink($file_path)) {
                            $deleted_files_count++;
                        }
                    }
                }
            }
        }

        // F. Delete the page row from DB
        // Cascade triggers will delete related rows in page_images, page_videos, page_replies, page_views, reactions, expiry_extensions
        $pdo->prepare("DELETE FROM pages WHERE id = ?")->execute([$page_id]);

        $total_bytes_recovered += (int)$p['storage_bytes'];
        $deleted_pages_count++;
    }

    // Format recovered storage size
    $formatted_storage = cleanup_format_bytes($total_bytes_recovered);

    echo "[" . date('Y-m-d H:i:s') . "] SoulSync cleanup finished successfully.\n";
    echo "Summary:\n";
    echo " - Pages Purged: $deleted_pages_count\n";
    echo " - Physical Files Deleted: $deleted_files_count\n";
    echo " - Storage Space Recovered: $formatted_storage ($total_bytes_recovered bytes)\n";

} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Error during cleanup: " . $e->getMessage() . "\n";
}

function cleanup_format_bytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}
