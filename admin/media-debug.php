<?php
require_once '../includes/functions.php';

// Verify admin role
if (!is_logged_in() || !is_admin()) {
    redirect('login.php');
}

$success_msg = '';
$error_msg = '';

// Folders to check write permissions
$folders = [
    'uploads' => '../uploads',
    'replies' => '../uploads/replies',
    'music'   => '../assets/music'
];

$folder_status = [];
foreach ($folders as $name => $path) {
    if (!is_dir($path)) {
        // Try to create it
        @mkdir($path, 0755, true);
    }
    $folder_status[$name] = [
        'path' => $path,
        'exists' => is_dir($path),
        'writable' => is_writable($path)
    ];
}

// Fetch all pages
$stmt_pages = $pdo->query("SELECT id, title, slug, sender_name, receiver_name, music_url, video_url, voice_url, letter_voice_url, created_at FROM pages ORDER BY created_at DESC");
$pages = $stmt_pages->fetchAll();

// Fetch all images
$stmt_images = $pdo->query("SELECT id, page_id, image_path FROM page_images");
$all_images = $stmt_images->fetchAll();

// Fetch replies media
$stmt_replies = $pdo->query("SELECT id, page_id, visitor_name, reply_type, voice_path, image_path, video_path, created_at FROM page_replies ORDER BY created_at DESC");
$all_replies = $stmt_replies->fetchAll();

// Diagnostics summaries
$total_assets_scanned = 0;
$missing_assets = [];
$healthy_assets = [];
$total_size_bytes = 0;

// Helper to audit a file path
function audit_file($relative_path, $type, $owner_type, $owner_id, $owner_desc) {
    global $total_assets_scanned, $missing_assets, $healthy_assets, $total_size_bytes;
    if (empty($relative_path)) return null;

    $total_assets_scanned++;
    $local_path = '../' . $relative_path;
    $exists = file_exists($local_path);
    $size = 0;
    $mime = 'unknown';

    if ($exists) {
        $size = filesize($local_path);
        $total_size_bytes += $size;
        
        // Retrieve MIME type safely
        if (function_exists('mime_content_type')) {
            $mime = @mime_content_type($local_path);
        } else {
            $ext = strtolower(pathinfo($local_path, PATHINFO_EXTENSION));
            $mimes = [
                'mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'wav' => 'audio/wav', 'aac' => 'audio/aac', 'ogg' => 'audio/ogg',
                'mp4' => 'video/mp4', 'webm' => 'video/webm', 'mov' => 'video/quicktime',
                'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'
            ];
            $mime = $mimes[$ext] ?? 'application/octet-stream';
        }

        $asset = [
            'path' => $relative_path,
            'type' => $type,
            'size' => $size,
            'mime' => $mime,
            'owner_type' => $owner_type,
            'owner_id' => $owner_id,
            'owner_desc' => $owner_desc
        ];
        $healthy_assets[] = $asset;
        return $asset;
    } else {
        $asset = [
            'path' => $relative_path,
            'type' => $type,
            'size' => 0,
            'mime' => 'missing',
            'owner_type' => $owner_type,
            'owner_id' => $owner_id,
            'owner_desc' => $owner_desc
        ];
        $missing_assets[] = $asset;
        return $asset;
    }
}

// Audit all page assets
$pages_media_status = [];
foreach ($pages as $p) {
    $page_media = [
        'info' => $p,
        'music' => audit_file($p['music_url'], 'Background Music', 'Page', $p['id'], $p['title']),
        'video' => audit_file($p['video_url'], 'Video Message', 'Page', $p['id'], $p['title']),
        'voice' => audit_file($p['voice_url'], 'Voice Message', 'Page', $p['id'], $p['title']),
        'letter_voice' => audit_file($p['letter_voice_url'], 'Letter Voice Note', 'Page', $p['id'], $p['title']),
        'images' => []
    ];
    
    // Filter images belonging to this page
    foreach ($all_images as $img) {
        if ($img['page_id'] == $p['id']) {
            $page_media['images'][] = audit_file($img['image_path'], 'Page Image', 'Page', $p['id'], $p['title']);
        }
    }
    
    $pages_media_status[$p['id']] = $page_media;
}

// Audit reply assets
$replies_media_status = [];
foreach ($all_replies as $r) {
    if (!empty($r['voice_path']) || !empty($r['image_path']) || !empty($r['video_path'])) {
        $replies_media_status[] = [
            'info' => $r,
            'voice' => audit_file($r['voice_path'], 'Reply Voice', 'Reply', $r['id'], $r['visitor_name']),
            'image' => audit_file($r['image_path'], 'Reply Image', 'Reply', $r['id'], $r['visitor_name']),
            'video' => audit_file($r['video_path'], 'Reply Video', 'Reply', $r['id'], $r['visitor_name'])
        ];
    }
}

// Storage space calculation
$free_disk_bytes = @disk_free_space('../uploads') ?: 0;
$total_disk_bytes = @disk_total_space('../uploads') ?: 0;

function format_bytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Media Debugger - SoulSync Admin</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@600;800&display=swap" rel="stylesheet">
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        heading: ['Outfit', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        body { 
            background-color: #0f172a; 
            background-image: radial-gradient(at 0% 0%, rgba(236,72,153,0.05) 0px, transparent 50%), radial-gradient(at 100% 100%, rgba(59,130,246,0.05) 0px, transparent 50%); 
        }
        .glass-card { 
            background: rgba(255,255,255,0.03); 
            backdrop-filter: blur(12px); 
            border: 1px solid rgba(255,255,255,0.08); 
        }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 font-sans min-h-screen flex flex-col antialiased">

    <!-- Header -->
    <header class="bg-slate-950 border-b border-slate-900 h-16 flex items-center">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <span class="text-2xl font-bold font-heading tracking-wider bg-gradient-to-r from-pink-500 to-purple-500 bg-clip-text text-transparent">SoulSync</span>
                <span class="bg-pink-500/10 border border-pink-500/30 text-pink-400 text-[10px] font-bold px-2 py-0.5 rounded-md uppercase tracking-wider">Media Debugger</span>
            </div>
            <div class="flex items-center gap-2">
                <a href="../dashboard.php" class="text-xs font-semibold bg-slate-900 border border-slate-800 text-slate-300 px-3.5 py-2 rounded-xl hover:bg-slate-800 hover:text-white transition">← Go to Site</a>
                <a href="../logout.php" class="text-xs font-semibold bg-slate-900 border border-slate-800 text-slate-300 px-3.5 py-2 rounded-xl hover:bg-slate-800 hover:text-white transition">Logout</a>
            </div>
        </div>
    </header>

    <!-- Admin Nav -->
    <div class="bg-slate-900/40 border-b border-slate-900">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 flex space-x-5 text-sm overflow-x-auto">
            <a href="index.php" class="text-slate-400 hover:text-white transition whitespace-nowrap">Dashboard</a>
            <a href="users.php" class="text-slate-400 hover:text-white transition whitespace-nowrap">Users</a>
            <a href="pages.php" class="text-slate-400 hover:text-white transition whitespace-nowrap">Pages</a>
            <a href="payments.php" class="text-slate-400 hover:text-white transition whitespace-nowrap">Payments</a>
            <a href="replies.php" class="text-slate-400 hover:text-white transition whitespace-nowrap">Replies</a>
            <a href="music.php" class="text-slate-400 hover:text-white transition whitespace-nowrap">Music</a>
            <a href="categories.php" class="text-slate-400 hover:text-white transition whitespace-nowrap">Category Management</a>
            <a href="settings.php" class="text-slate-400 hover:text-white transition whitespace-nowrap">Settings</a>
            <a href="debug.php" class="text-slate-400 hover:text-white transition whitespace-nowrap">Debug</a>
            <a href="media-debug.php" class="text-pink-400 font-bold whitespace-nowrap">Media Debug</a>
        </div>
    </div>

    <!-- Main Content -->
    <main class="flex-grow max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div>
                <h1 class="text-2xl font-extrabold font-heading text-white">Media Asset Debugger</h1>
                <p class="text-sm text-slate-400 font-medium">Scan upload directory integrity, broken page paths, permissions, and health status.</p>
            </div>
            <button onclick="window.location.reload()" class="px-4 py-2.5 bg-pink-500 hover:bg-pink-600 rounded-2xl text-xs font-bold text-white shadow-lg transition">
                🔄 Re-run Scan
            </button>
        </div>

        <!-- Folder Permissions & Health Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- Directory Permissions Card -->
            <div class="glass-card rounded-2xl p-5 flex flex-col justify-between">
                <div>
                    <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-400 mb-4">Directory Status</h3>
                    <div class="space-y-3.5 text-xs">
                        <?php foreach ($folder_status as $name => $status): ?>
                            <div class="flex items-center justify-between">
                                <span class="font-mono text-slate-300 font-semibold uppercase"><?= $name ?>/</span>
                                <div class="flex items-center space-x-2">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase <?= $status['exists'] ? 'bg-green-500/10 text-green-400' : 'bg-red-500/10 text-red-400' ?>">
                                        <?= $status['exists'] ? 'Exists' : 'Missing' ?>
                                    </span>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase <?= $status['writable'] ? 'bg-green-500/10 text-green-400' : 'bg-red-500/10 text-red-400' ?>">
                                        <?= $status['writable'] ? 'Writable' : 'Read-Only' ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="mt-4 border-t border-slate-900 pt-3 flex items-center justify-between text-[11px] text-slate-500 font-semibold">
                    <span>Base Dir: /uploads</span>
                    <span>755 Permission</span>
                </div>
            </div>

            <!-- Health Summary Card -->
            <div class="glass-card rounded-2xl p-5 flex flex-col justify-between">
                <div>
                    <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-400 mb-4">Asset Health</h3>
                    <div class="space-y-3.5 text-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-300 font-semibold">Total Scanned Paths</span>
                            <span class="font-mono font-bold text-white"><?= $total_assets_scanned ?></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-300 font-semibold">Healthy Assets</span>
                            <span class="font-mono font-bold text-green-400"><?= count($healthy_assets) ?></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-300 font-semibold">Missing Assets</span>
                            <span class="font-mono font-bold <?= count($missing_assets) > 0 ? 'text-red-400 animate-pulse' : 'text-slate-500' ?>"><?= count($missing_assets) ?></span>
                        </div>
                    </div>
                </div>
                <div class="mt-4 border-t border-slate-900 pt-3 flex items-center justify-between text-[11px] text-slate-500 font-semibold">
                    <span>Integrity Score:</span>
                    <span class="font-bold <?= count($missing_assets) === 0 ? 'text-green-400' : 'text-yellow-400' ?>">
                        <?= $total_assets_scanned > 0 ? round((count($healthy_assets) / $total_assets_scanned) * 100, 1) : 100 ?>%
                    </span>
                </div>
            </div>

            <!-- Storage Health Card -->
            <div class="glass-card rounded-2xl p-5 flex flex-col justify-between">
                <div>
                    <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-400 mb-4">Disk Footprint</h3>
                    <div class="space-y-3.5 text-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-300 font-semibold">Total SoulSync Uploads</span>
                            <span class="font-mono font-bold text-pink-400"><?= format_bytes($total_size_bytes) ?></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-300 font-semibold">Server Free Storage</span>
                            <span class="font-mono font-bold text-slate-400"><?= format_bytes($free_disk_bytes) ?></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-300 font-semibold">Total Server Disk</span>
                            <span class="font-mono font-bold text-slate-500"><?= format_bytes($total_disk_bytes) ?></span>
                        </div>
                    </div>
                </div>
                <div class="mt-4 border-t border-slate-900 pt-3 flex items-center justify-between text-[11px] text-slate-500 font-semibold">
                    <span>Path: SoulSync Local Disk</span>
                    <span>Storage Safe</span>
                </div>
            </div>
        </div>

        <!-- Diagnostics Detail Section -->
        <div class="space-y-8">
            <!-- 1. Broken / Missing Paths -->
            <div class="glass-card rounded-2xl p-6">
                <h2 class="text-lg font-bold font-heading text-white mb-4 flex items-center gap-2">
                    <span class="text-red-400">⚠️</span> Broken Paths / Missing Uploads
                </h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-slate-800 text-slate-400 font-semibold uppercase text-[10px] tracking-wider">
                                <th class="py-3 px-4">Type</th>
                                <th class="py-3 px-4">Expected Path</th>
                                <th class="py-3 px-4">Belongs To</th>
                                <th class="py-3 px-4 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-900 text-slate-300">
                            <?php foreach ($missing_assets as $ma): ?>
                                <tr class="hover:bg-white/5 transition-colors">
                                    <td class="py-3.5 px-4 font-semibold text-rose-400 flex items-center gap-1.5">
                                        <span>📂</span> <?= h($ma['type']) ?>
                                    </td>
                                    <td class="py-3.5 px-4 font-mono text-slate-400 break-all"><?= h($ma['path']) ?></td>
                                    <td class="py-3.5 px-4">
                                        <span class="font-semibold text-white"><?= h($ma['owner_desc']) ?></span> 
                                        <span class="text-slate-500">(<?= h($ma['owner_type']) ?> ID: <?= $ma['owner_id'] ?>)</span>
                                    </td>
                                    <td class="py-3.5 px-4 text-right">
                                        <?php if ($ma['owner_type'] === 'Page'): ?>
                                            <a href="pages.php?search=<?= $ma['owner_id'] ?>" class="text-[10px] font-bold bg-pink-500/10 hover:bg-pink-500/20 text-pink-400 px-3 py-1.5 rounded-xl transition">
                                                Manage Page
                                            </a>
                                        <?php else: ?>
                                            <a href="replies.php?search=<?= $ma['owner_id'] ?>" class="text-[10px] font-bold bg-blue-500/10 hover:bg-blue-500/20 text-blue-400 px-3 py-1.5 rounded-xl transition">
                                                Manage Reply
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($missing_assets)): ?>
                                <tr>
                                    <td colspan="4" class="py-6 text-center text-slate-500 font-medium">
                                        🎉 Awesome! No broken media paths or missing uploads found.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 2. Full Media Audit by Page -->
            <div class="glass-card rounded-2xl p-6">
                <h2 class="text-lg font-bold font-heading text-white mb-4 flex items-center gap-2">
                    <span class="text-pink-400">📊</span> Full Page Media Audit
                </h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-slate-800 text-slate-400 font-semibold uppercase text-[10px] tracking-wider">
                                <th class="py-3 px-4">Page Description</th>
                                <th class="py-3 px-4">BGM Status</th>
                                <th class="py-3 px-4">Video Status</th>
                                <th class="py-3 px-4">Voice Status</th>
                                <th class="py-3 px-4">Letter Voice</th>
                                <th class="py-3 px-4">Images Uploaded</th>
                                <th class="py-3 px-4 text-right">Audit</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-900 text-slate-300">
                            <?php foreach ($pages_media_status as $p_id => $pm): ?>
                                <tr class="hover:bg-white/5 transition-colors">
                                    <td class="py-3.5 px-4">
                                        <p class="font-bold text-white"><?= h($pm['info']['title']) ?></p>
                                        <p class="text-[10px] text-slate-500">to <?= h($pm['info']['receiver_name']) ?> • slug: p/<?= h($pm['info']['slug']) ?></p>
                                    </td>
                                    <!-- Music -->
                                    <td class="py-3.5 px-4 font-mono">
                                        <?php if ($pm['music']): ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase <?= $pm['music']['mime'] !== 'missing' ? 'bg-green-500/10 text-green-400' : 'bg-red-500/10 text-red-400' ?>">
                                                <?= $pm['music']['mime'] !== 'missing' ? 'OK (' . format_bytes($pm['music']['size']) . ')' : 'MISSING' ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-slate-600">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <!-- Video -->
                                    <td class="py-3.5 px-4 font-mono">
                                        <?php if ($pm['video']): ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase <?= $pm['video']['mime'] !== 'missing' ? 'bg-green-500/10 text-green-400' : 'bg-red-500/10 text-red-400' ?>">
                                                <?= $pm['video']['mime'] !== 'missing' ? 'OK (' . format_bytes($pm['video']['size']) . ')' : 'MISSING' ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-slate-600">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <!-- Voice -->
                                    <td class="py-3.5 px-4 font-mono">
                                        <?php if ($pm['voice']): ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase <?= $pm['voice']['mime'] !== 'missing' ? 'bg-green-500/10 text-green-400' : 'bg-red-500/10 text-red-400' ?>">
                                                <?= $pm['voice']['mime'] !== 'missing' ? 'OK (' . format_bytes($pm['voice']['size']) . ')' : 'MISSING' ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-slate-600">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <!-- Letter Voice -->
                                    <td class="py-3.5 px-4 font-mono">
                                        <?php if ($pm['letter_voice']): ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase <?= $pm['letter_voice']['mime'] !== 'missing' ? 'bg-green-500/10 text-green-400' : 'bg-red-500/10 text-red-400' ?>">
                                                <?= $pm['letter_voice']['mime'] !== 'missing' ? 'OK (' . format_bytes($pm['letter_voice']['size']) . ')' : 'MISSING' ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-slate-600">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <!-- Images -->
                                    <td class="py-3.5 px-4">
                                        <?php 
                                        $img_count = count($pm['images']);
                                        $broken_count = 0;
                                        $total_img_size = 0;
                                        foreach ($pm['images'] as $img) {
                                            if ($img['mime'] === 'missing') $broken_count++;
                                            $total_img_size += $img['size'];
                                        }
                                        if ($img_count > 0): ?>
                                            <span class="font-bold text-slate-200"><?= $img_count ?> Photos</span>
                                            <?php if ($broken_count > 0): ?>
                                                <span class="text-red-400 font-bold ml-1 animate-pulse">(<?= $broken_count ?> Broken)</span>
                                            <?php else: ?>
                                                <span class="text-slate-500 text-[10px] font-semibold ml-1">(<?= format_bytes($total_img_size) ?>)</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-slate-600">No Photos</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3.5 px-4 text-right">
                                        <a href="../p.php?s=<?= h($pm['info']['slug']) ?>" target="_blank" class="text-[10px] font-bold bg-slate-900 border border-slate-800 text-slate-300 px-3 py-1.5 rounded-xl hover:text-white transition">
                                            View Page 🔗
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-slate-950 border-t border-slate-900 py-6 text-center text-slate-600 text-xs mt-12">
        &copy; <?= date('Y') ?> SoulSync Admin.
    </footer>
</body>
</html>
