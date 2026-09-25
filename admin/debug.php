<?php
require_once '../includes/functions.php';

if (!is_logged_in() || !is_admin()) {
    redirect('login.php');
}

$admin_nav = 'debug';

// 1. Database Connection check
$db_connected = false;
$db_error = '';
if (isset($pdo)) {
    try {
        $pdo->query("SELECT 1");
        $db_connected = true;
    } catch (PDOException $e) {
        $db_error = $e->getMessage();
    }
} else {
    $db_error = 'Database connection object ($pdo) is not defined.';
}

// 2. Directory write permissions check
$directories = [
    'uploads/pages/' => __DIR__ . '/../uploads/pages/',
    'uploads/videos/' => __DIR__ . '/../uploads/videos/',
    'uploads/audio/' => __DIR__ . '/../uploads/audio/',
    'uploads/voice/' => __DIR__ . '/../uploads/voice/',
    'uploads/replies/' => __DIR__ . '/../uploads/replies/',
];

$dir_status = [];
foreach ($directories as $rel => $abs) {
    if (!file_exists($abs)) {
        @mkdir($abs, 0755, true);
    }
    
    $exists = file_exists($abs);
    $writable = $exists && is_writable($abs);
    
    $dir_status[$rel] = [
        'abs' => $abs,
        'exists' => $exists,
        'writable' => $writable,
        'perms' => $exists ? substr(sprintf('%o', fileperms($abs)), -4) : 'N/A'
    ];
}

// Helper: recursively calculate directory size
function get_dir_info($dir) {
    $size = 0;
    $count = 0;
    if (is_dir($dir)) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            $size += $file->getSize();
            $count++;
        }
    }
    return ['size' => $size, 'count' => $count];
}

// Get size info for each folder
$storage_info = [];
$total_bytes = 0;
$total_files = 0;
foreach ($directories as $rel => $abs) {
    $info = get_dir_info($abs);
    $storage_info[$rel] = $info;
    $total_bytes += $info['size'];
    $total_files += $info['count'];
}

function format_bytes($bytes) {
    if ($bytes > 1073741824) {
        return round($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes > 1048576) {
        return round($bytes / 1048576, 1) . ' MB';
    } elseif ($bytes > 1024) {
        return round($bytes / 1024, 1) . ' KB';
    }
    return $bytes . ' B';
}

// 3. Broken media links detection
$broken_items = [];

if ($db_connected) {
    // A. Page Images
    $stmt = $pdo->query("SELECT pi.id, pi.page_id, pi.image_path, p.title as page_title, p.slug as page_slug 
                         FROM page_images pi 
                         JOIN pages p ON pi.page_id = p.id");
    while ($row = $stmt->fetch()) {
        $path = __DIR__ . '/../' . $row['image_path'];
        if (empty($row['image_path']) || !file_exists($path)) {
            $broken_items[] = [
                'type' => 'Gallery Image',
                'id' => $row['id'],
                'path' => $row['image_path'] ?: '[Empty Path]',
                'page_id' => $row['page_id'],
                'page_title' => $row['page_title'],
                'page_slug' => $row['page_slug']
            ];
        }
    }

    // B. Page Videos
    $stmt = $pdo->query("SELECT id, title, slug, video_url FROM pages WHERE video_url IS NOT NULL AND video_url != ''");
    while ($row = $stmt->fetch()) {
        $path = __DIR__ . '/../' . $row['video_url'];
        if (!file_exists($path)) {
            $broken_items[] = [
                'type' => 'Page Video',
                'id' => $row['id'],
                'path' => $row['video_url'],
                'page_id' => $row['id'],
                'page_title' => $row['title'],
                'page_slug' => $row['slug']
            ];
        }
    }

    // C. Page Voice notes
    $stmt = $pdo->query("SELECT id, title, slug, voice_url FROM pages WHERE voice_url IS NOT NULL AND voice_url != ''");
    while ($row = $stmt->fetch()) {
        $path = __DIR__ . '/../' . $row['voice_url'];
        if (!file_exists($path)) {
            $broken_items[] = [
                'type' => 'Voice Note',
                'id' => $row['id'],
                'path' => $row['voice_url'],
                'page_id' => $row['id'],
                'page_title' => $row['title'],
                'page_slug' => $row['slug']
            ];
        }
    }

    // D. Page Replies
    $stmt = $pdo->query("SELECT pr.id, pr.page_id, pr.reply_type, pr.voice_path, pr.image_path, pr.video_path, p.title as page_title, p.slug as page_slug 
                         FROM page_replies pr 
                         JOIN pages p ON pr.page_id = p.id");
    while ($row = $stmt->fetch()) {
        $file_path = null;
        if ($row['reply_type'] === 'voice') {
            $file_path = $row['voice_path'];
        } elseif ($row['reply_type'] === 'image') {
            $file_path = $row['image_path'];
        } elseif ($row['reply_type'] === 'video') {
            $file_path = $row['video_path'];
        }
        
        if ($file_path !== null && $file_path !== '') {
            $path = __DIR__ . '/../' . $file_path;
            if (!file_exists($path)) {
                $broken_items[] = [
                    'type' => 'Reply File (' . ucfirst($row['reply_type']) . ')',
                    'id' => $row['id'],
                    'path' => $file_path,
                    'page_id' => $row['page_id'],
                    'page_title' => $row['page_title'],
                    'page_slug' => $row['page_slug']
                ];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Diagnostics - SoulSync Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@600;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter', 'sans-serif'], heading: ['Outfit', 'sans-serif'] } } } }
    </script>
    <style>
        body { background-color: #0f172a; background-image: radial-gradient(at 0% 0%, rgba(236,72,153,0.05) 0px, transparent 50%), radial-gradient(at 100% 100%, rgba(59,130,246,0.05) 0px, transparent 50%); }
        .glass-card { background: rgba(255,255,255,0.03); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.08); }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 font-sans min-h-screen flex flex-col antialiased">

    <!-- Header -->
    <header class="bg-slate-950 border-b border-slate-900 h-16 flex items-center">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <span class="text-2xl font-bold font-heading tracking-wider bg-gradient-to-r from-pink-500 to-purple-500 bg-clip-text text-transparent">SoulSync</span>
                <span class="bg-purple-500/10 border border-purple-500/30 text-purple-400 text-[10px] font-bold px-2 py-0.5 rounded-md uppercase tracking-wider">Admin</span>
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
            <a href="debug.php" class="text-pink-400 font-bold whitespace-nowrap">Debug</a>
            <a href="media-debug.php" class="text-slate-400 hover:text-white transition whitespace-nowrap">Media Debug</a>
        </div>
    </div>

    <main class="flex-grow max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div>
                <h1 class="text-2xl font-extrabold font-heading text-white">System Diagnostics</h1>
                <p class="text-sm text-slate-400">Database connections, directory write permissions, and media health status.</p>
            </div>
            <button onclick="window.location.reload()" class="px-4 py-2 bg-slate-900 hover:bg-slate-850 border border-slate-800 rounded-xl text-xs font-semibold flex items-center gap-1.5 transition">
                🔄 Run Diagnostics Again
            </button>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <!-- Col 1: DB & Permissions -->
            <div class="lg:col-span-2 space-y-6">
                <!-- DB check card -->
                <div class="glass-card rounded-3xl p-6">
                    <h3 class="text-lg font-bold font-heading mb-4 text-white flex items-center gap-2">
                        <span>🔌</span> Database Status
                    </h3>
                    <?php if ($db_connected): ?>
                        <div class="flex items-center gap-3 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 p-4 rounded-2xl">
                            <span class="text-2xl">✅</span>
                            <div>
                                <h4 class="font-bold text-sm">Connected successfully</h4>
                                <p class="text-xs text-slate-400 mt-0.5">Database connection is working and responding to queries.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="flex items-start gap-3 bg-red-500/10 border border-red-500/20 text-red-400 p-4 rounded-2xl">
                            <span class="text-2xl">❌</span>
                            <div>
                                <h4 class="font-bold text-sm">Connection Failed</h4>
                                <p class="text-xs text-slate-400 mt-1"><?= h($db_error) ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Directory write permissions check -->
                <div class="glass-card rounded-3xl p-6">
                    <h3 class="text-lg font-bold font-heading mb-4 text-white flex items-center gap-2">
                        <span>📁</span> Write Permissions Check
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-slate-400">
                            <thead>
                                <tr class="border-b border-slate-800 text-slate-500 uppercase tracking-wider font-semibold">
                                    <th class="pb-3">Directory Path</th>
                                    <th class="pb-3">Status</th>
                                    <th class="pb-3 text-right">Permissions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800">
                                <?php foreach ($dir_status as $rel => $status): ?>
                                    <tr>
                                        <td class="py-3.5 font-mono text-slate-300"><?= $rel ?></td>
                                        <td class="py-3.5">
                                            <?php if ($status['writable']): ?>
                                                <span class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider">Writable</span>
                                            <?php elseif ($status['exists']): ?>
                                                <span class="bg-red-500/10 border border-red-500/20 text-red-400 px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider">Not Writable</span>
                                            <?php else: ?>
                                                <span class="bg-amber-500/10 border border-amber-500/20 text-amber-400 px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider">Missing</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3.5 text-right font-mono text-slate-400"><?= $status['perms'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Col 2: Storage Summary -->
            <div class="space-y-6">
                <div class="glass-card rounded-3xl p-6">
                    <h3 class="text-lg font-bold font-heading mb-4 text-white flex items-center gap-2">
                        <span>💾</span> Storage Usage
                    </h3>
                    <div class="text-center py-6 bg-slate-900/50 rounded-2xl border border-slate-800/40 mb-6">
                        <span class="text-3xl font-extrabold text-white tracking-tight"><?= format_bytes($total_bytes) ?></span>
                        <p class="text-xs text-slate-500 mt-1">Total across <?= number_format($total_files) ?> files</p>
                    </div>

                    <div class="space-y-3">
                        <?php foreach ($storage_info as $rel => $info): ?>
                            <div class="flex items-center justify-between text-xs pb-3 border-b border-slate-800/40 last:border-b-0 last:pb-0">
                                <span class="text-slate-400 font-mono"><?= $rel ?></span>
                                <div class="text-right">
                                    <span class="font-bold text-white"><?= format_bytes($info['size']) ?></span>
                                    <span class="text-[10px] text-slate-600 block"><?= $info['count'] ?> files</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Broken media links detection card -->
        <div class="glass-card rounded-3xl p-6">
            <h3 class="text-lg font-bold font-heading mb-2 text-white flex items-center gap-2">
                <span>🔗</span> Broken Media Links Detection
            </h3>
            <p class="text-xs text-slate-400 mb-6">Checks if files referenced in database records are missing from the server's filesystem.</p>

            <?php if (empty($broken_items)): ?>
                <div class="flex items-center gap-3 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 p-4 rounded-2xl">
                    <span class="text-2xl">🎉</span>
                    <div>
                        <h4 class="font-bold text-sm">All media links are healthy!</h4>
                        <p class="text-xs text-slate-400 mt-0.5">Every photo, video, voice note, and reply file referenced in the database exists on disk.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="flex items-center gap-2.5 bg-red-500/10 border border-red-500/20 text-red-400 p-3.5 rounded-2xl mb-6 text-xs">
                    <span>⚠️</span>
                    <span>Found <strong><?= count($broken_items) ?></strong> database records with missing files on the server. See details below.</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-400">
                        <thead>
                            <tr class="border-b border-slate-800 text-slate-500 uppercase tracking-wider font-semibold">
                                <th class="pb-3">Media Type</th>
                                <th class="pb-3">Database Path (Missing File)</th>
                                <th class="pb-3">Associated Page</th>
                                <th class="pb-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            <?php foreach (array_slice($broken_items, 0, 100) as $item): ?>
                                <tr>
                                    <td class="py-3 font-semibold text-white">
                                        <span class="bg-slate-900 border border-slate-800 text-[10px] text-slate-300 px-2 py-0.5 rounded">
                                            <?= h($item['type']) ?>
                                        </span>
                                    </td>
                                    <td class="py-3 font-mono text-[11px] text-red-400 max-w-xs truncate" title="<?= h($item['path']) ?>"><?= h($item['path']) ?></td>
                                    <td class="py-3">
                                        <a href="../p.php?s=<?= h($item['page_slug']) ?>" target="_blank" class="text-pink-400 hover:text-pink-300 font-medium transition max-w-xxs truncate block" title="<?= h($item['page_title']) ?>">
                                            <?= h($item['page_title']) ?>
                                        </a>
                                    </td>
                                    <td class="py-3 text-right">
                                        <a href="pages.php?search=<?= urlencode($item['page_slug']) ?>" class="px-2.5 py-1 bg-slate-900 border border-slate-800 hover:bg-slate-850 hover:text-white rounded-lg transition inline-block">
                                            Manage Page
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (count($broken_items) > 100): ?>
                                <tr>
                                    <td colspan="4" class="py-4 text-center text-slate-500 italic text-[11px]">
                                        Showing first 100 broken links. Please clean up these records to see more.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <footer class="bg-slate-950 border-t border-slate-900 py-6 text-center text-slate-600 text-xs mt-auto">
        &copy; <?= date('Y') ?> SoulSync Admin.
    </footer>

</body>
</html>
