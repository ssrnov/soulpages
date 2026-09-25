<?php
require_once '../includes/functions.php';

// Verify admin role
if (!is_logged_in() || !is_admin()) {
    redirect('login.php');
}

// Handle page deletion by admin
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    
    // Fetch page details to delete files from disk
    $stmt_page = $pdo->prepare("SELECT video_url, voice_url, letter_voice_url FROM pages WHERE id = ?");
    $stmt_page->execute([$delete_id]);
    $p = $stmt_page->fetch();
    if ($p) {
        foreach (['video_url', 'voice_url', 'letter_voice_url'] as $field) {
            if (!empty($p[$field])) {
                if (file_exists($p[$field])) {
                    @unlink($p[$field]);
                } elseif (file_exists('../' . $p[$field])) {
                    @unlink('../' . $p[$field]);
                }
            }
        }
    }

    // Fetch associated photos to delete from disk
    $stmt_img = $pdo->prepare("SELECT image_path FROM page_images WHERE page_id = ?");
    $stmt_img->execute([$delete_id]);
    $images = $stmt_img->fetchAll();
    foreach ($images as $img) {
        $path = $img['image_path'];
        if (file_exists($path)) {
            @unlink($path);
        } elseif (file_exists('../' . $path)) {
            @unlink('../' . $path);
        }
    }
    
    // Delete page (cascades database views/reactions automatically)
    $stmt_del = $pdo->prepare("DELETE FROM pages WHERE id = ?");
    $stmt_del->execute([$delete_id]);
    $_SESSION['success_msg'] = 'Page deleted successfully.';
    redirect('pages.php');
}

$search = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');
$theme = trim($_GET['theme'] ?? '');
$security = trim($_GET['security'] ?? '');
$media = trim($_GET['media'] ?? '');
$status = trim($_GET['status'] ?? '');

$params = [];
$conditions = [];

if (!empty($search)) {
    $conditions[] = "(p.title LIKE ? OR p.sender_name LIKE ? OR p.receiver_name LIKE ? OR p.slug LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($category)) {
    $conditions[] = "p.category = ?";
    $params[] = $category;
}

if (!empty($theme)) {
    $conditions[] = "p.theme = ?";
    $params[] = $theme;
}

if ($security === 'password') {
    $conditions[] = "p.password IS NOT NULL AND p.password != ''";
} elseif ($security === 'public') {
    $conditions[] = "(p.password IS NULL OR p.password = '')";
}

if ($media === 'video') {
    $conditions[] = "p.video_url IS NOT NULL AND p.video_url != ''";
} elseif ($media === 'voice') {
    $conditions[] = "p.voice_url IS NOT NULL AND p.voice_url != ''";
} elseif ($media === 'images') {
    $conditions[] = "(SELECT COUNT(*) FROM page_images WHERE page_id = p.id) > 0";
}

if (!empty($status)) {
    $conditions[] = "p.status = ?";
    $params[] = $status;
}

$query = "SELECT p.*, u.name as user_name FROM pages p LEFT JOIN users u ON p.user_id = u.id";
if (!empty($conditions)) {
    $query .= " WHERE " . implode(" AND ", $conditions);
}
$query .= " ORDER BY p.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$pages = $stmt->fetchAll();

$success_msg = $_SESSION['success_msg'] ?? '';
unset($_SESSION['success_msg']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Pages - SoulSync Admin</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@600;800&display=swap" rel="stylesheet">
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
        .hero-bg {
            background-color: #0f172a;
            background-image: 
                radial-gradient(at 0% 0%, rgba(236, 72, 153, 0.05) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(59, 130, 246, 0.05) 0px, transparent 50%);
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 font-sans min-h-screen flex flex-col justify-between hero-bg antialiased">

    <!-- Header -->
    <?php $ADMIN_TITLE='Pages'; include '_nav.php'; ?>

    <main class="flex-grow max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-10">
        
        <!-- Title Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <h2 class="text-2xl font-extrabold font-heading text-white">Generated Pages</h2>
            <div class="text-xs text-slate-400">Total pages matching: <strong class="text-pink-400"><?= count($pages) ?></strong></div>
        </div>

        <!-- Filters glass card -->
        <div class="glass-card rounded-3xl p-6 mb-8 relative overflow-hidden">
            <div class="absolute -top-10 -left-10 w-32 h-32 bg-pink-500/5 rounded-full blur-3xl"></div>
            <div class="absolute -bottom-10 -right-10 w-32 h-32 bg-blue-500/5 rounded-full blur-3xl"></div>
            
            <h3 class="text-xs font-semibold text-slate-400 mb-4 uppercase tracking-wider">Search & Filters</h3>
            <form action="pages.php" method="GET" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-7 gap-4 items-end">
                <!-- Search -->
                <div>
                    <label class="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-2">Keyword Search</label>
                    <input type="text" name="search" value="<?= h($search) ?>" placeholder="Search pages..." class="w-full bg-slate-900/60 border border-slate-800 focus:border-pink-500 focus:ring-1 focus:ring-pink-500 rounded-xl px-3 py-2 text-xs outline-none text-white transition">
                </div>

                <!-- Category -->
                <div>
                    <label class="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-2">Category</label>
                    <select name="category" class="w-full bg-slate-900/60 border border-slate-800 focus:border-pink-500 focus:ring-1 focus:ring-pink-500 rounded-xl px-3 py-2 text-xs outline-none text-slate-300 transition">
                        <option value="">All Categories</option>
                        <?php foreach (get_categories(true) as $cat_key => $cat_info): ?>
                            <option value="<?= h($cat_key) ?>" <?= $category === $cat_key ? 'selected' : '' ?>>
                                <?= $cat_info['icon'] ?> <?= h($cat_info['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Theme -->
                <div>
                    <label class="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-2">Theme</label>
                    <select name="theme" class="w-full bg-slate-900/60 border border-slate-800 focus:border-pink-500 focus:ring-1 focus:ring-pink-500 rounded-xl px-3 py-2 text-xs outline-none text-slate-300 transition">
                        <option value="">All Themes</option>
                        <?php foreach (get_themes() as $theme_key => $theme_info): ?>
                            <option value="<?= h($theme_key) ?>" <?= $theme === $theme_key ? 'selected' : '' ?>>
                                <?= $theme_info['icon'] ?> <?= h($theme_info['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Security -->
                <div>
                    <label class="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-2">Security</label>
                    <select name="security" class="w-full bg-slate-900/60 border border-slate-800 focus:border-pink-500 focus:ring-1 focus:ring-pink-500 rounded-xl px-3 py-2 text-xs outline-none text-slate-300 transition">
                        <option value="">All Security</option>
                        <option value="password" <?= $security === 'password' ? 'selected' : '' ?>>🔒 Password Protected</option>
                        <option value="public" <?= $security === 'public' ? 'selected' : '' ?>>🔓 Public Access</option>
                    </select>
                </div>

                <!-- Media -->
                <div>
                    <label class="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-2">Media Content</label>
                    <select name="media" class="w-full bg-slate-900/60 border border-slate-800 focus:border-pink-500 focus:ring-1 focus:ring-pink-500 rounded-xl px-3 py-2 text-xs outline-none text-slate-300 transition">
                        <option value="">All Media</option>
                        <option value="video" <?= $media === 'video' ? 'selected' : '' ?>>📹 Has Video Message</option>
                        <option value="voice" <?= $media === 'voice' ? 'selected' : '' ?>>🎙️ Has Voice Note</option>
                        <option value="images" <?= $media === 'images' ? 'selected' : '' ?>>🖼️ Has Photo Gallery</option>
                    </select>
                </div>

                <!-- Status -->
                <div>
                    <label class="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-2">Status</label>
                    <select name="status" class="w-full bg-slate-900/60 border border-slate-800 focus:border-pink-500 focus:ring-1 focus:ring-pink-500 rounded-xl px-3 py-2 text-xs outline-none text-slate-300 transition">
                        <option value="">All Statuses</option>
                        <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Published</option>
                    </select>
                </div>

                <!-- Actions -->
                <div class="flex gap-2">
                    <button type="submit" class="flex-grow bg-pink-500 hover:bg-pink-600 text-white font-bold text-xs px-4 py-2.5 rounded-xl transition">
                        Filter
                    </button>
                    <?php if (!empty($search) || !empty($category) || !empty($theme) || !empty($security) || !empty($media) || !empty($status)): ?>
                        <a href="pages.php" class="bg-slate-900 hover:bg-slate-800 border border-slate-800 text-slate-300 font-bold text-xs px-4 py-2.5 rounded-xl transition text-center flex items-center justify-center">
                            Reset
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <?php if (!empty($success_msg)): ?>
            <div class="bg-green-500/15 border border-green-500/30 text-green-400 p-4 rounded-2xl mb-6 text-sm">
                ✅ <?= h($success_msg) ?>
            </div>
        <?php endif; ?>

        <!-- Pages Table -->
        <div class="glass-card rounded-3xl p-6 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-400">
                    <thead class="bg-slate-900/80 text-xs text-slate-500 uppercase tracking-wider">
                        <tr>
                            <th class="p-4 rounded-l-xl">Card Details</th>
                            <th class="p-4">Slug / Link</th>
                            <th class="p-4">Features</th>
                            <th class="p-4">Stats</th>
                            <th class="p-4">Creator</th>
                            <th class="p-4">Status</th>
                            <th class="p-4 rounded-r-xl">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-900">
                        <?php if (count($pages) === 0): ?>
                            <tr>
                                <td colspan="7" class="p-8 text-center text-slate-600">No pages found matching current filters.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pages as $p): 
                                // Fetch Views Count
                                $stmt_v = $pdo->prepare("SELECT COUNT(*) FROM page_views WHERE page_id = ?");
                                $stmt_v->execute([$p['id']]);
                                $views = $stmt_v->fetchColumn();

                                // Fetch Reactions Count
                                $stmt_r = $pdo->prepare("SELECT COUNT(*) FROM reactions WHERE page_id = ?");
                                $stmt_r->execute([$p['id']]);
                                $reactions = $stmt_r->fetchColumn();

                                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
                                $public_url = $protocol . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['PHP_SELF'])) . "/p/" . $p['slug'];
                            ?>
                                <tr class="hover:bg-slate-900/20 transition">
                                    <td class="p-4">
                                        <div class="font-semibold text-white"><?= h($p['title']) ?></div>
                                        <div class="text-xs text-slate-500">From: <?= h($p['sender_name']) ?> | To: <?= h($p['receiver_name']) ?></div>
                                        <div class="mt-1.5 flex flex-wrap gap-1.5 items-center text-[10px]">
                                            <span class="bg-pink-500/10 border border-pink-500/20 text-pink-400 font-semibold px-2 py-0.5 rounded capitalize">
                                                <?= h($p['category']) ?>
                                            </span>
                                            <?php 
                                                $themes = get_themes();
                                                $theme_info = $themes[$p['theme'] ?? 'romantic'] ?? null;
                                                if ($theme_info):
                                            ?>
                                                <span class="bg-slate-800 border border-slate-700 text-slate-300 font-medium px-2 py-0.5 rounded flex items-center space-x-1">
                                                    <span><?= $theme_info['icon'] ?></span>
                                                    <span><?= h($theme_info['name']) ?></span>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="p-4">
                                        <?php if (is_super_admin()): ?>
                                            <a href="<?= h($public_url) ?>" target="_blank" class="text-xs text-pink-400 hover:underline font-mono truncate max-w-[150px] inline-block">/p/<?= h($p['slug']) ?></a>
                                        <?php else: ?>
                                            <span class="text-xs text-slate-400 font-mono truncate max-w-[150px] inline-block" title="Only a Super Admin can open pages">/p/<?= h($p['slug']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-4">
                                        <div class="flex items-center space-x-2">
                                            <!-- Password Security -->
                                            <?php if (!empty($p['password'])): ?>
                                                <span class="w-7 h-7 flex items-center justify-center bg-red-500/10 border border-red-500/20 text-red-400 rounded-lg text-xs" title="Password Protected (🔒)">
                                                    🔒
                                                </span>
                                            <?php else: ?>
                                                <span class="w-7 h-7 flex items-center justify-center bg-slate-800/80 border border-slate-700 text-slate-500 rounded-lg text-xs" title="Public (🔓)">
                                                    🔓
                                                </span>
                                            <?php endif; ?>
                                            
                                            <!-- Video -->
                                            <?php if (!empty($p['video_url'])): ?>
                                                <span class="w-7 h-7 flex items-center justify-center bg-blue-500/10 border border-blue-500/20 text-blue-400 rounded-lg text-xs" title="Has Video (📹)">
                                                    📹
                                                </span>
                                            <?php endif; ?>

                                            <!-- Voice -->
                                            <?php if (!empty($p['voice_url'])): ?>
                                                <span class="w-7 h-7 flex items-center justify-center bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-lg text-xs" title="Has Voice Note (🎙️)">
                                                    🎙️
                                                </span>
                                            <?php endif; ?>

                                            <!-- Images Count -->
                                            <?php 
                                                $stmt_img_count = $pdo->prepare("SELECT COUNT(*) FROM page_images WHERE page_id = ?");
                                                $stmt_img_count->execute([$p['id']]);
                                                $img_count = $stmt_img_count->fetchColumn();
                                                if ($img_count > 0):
                                            ?>
                                                <span class="w-7 h-7 flex items-center justify-center bg-yellow-500/10 border border-yellow-500/20 text-yellow-400 rounded-lg text-xs relative" title="<?= $img_count ?> Photos (🖼️)">
                                                    🖼️
                                                    <span class="absolute -top-1.5 -right-1.5 bg-yellow-500 text-slate-950 font-bold text-[8px] w-3.5 h-3.5 flex items-center justify-center rounded-full border border-slate-950"><?= $img_count ?></span>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="p-4 text-xs">
                                        <div class="whitespace-nowrap">👁️ Views: <strong class="text-slate-200"><?= $views ?></strong></div>
                                        <div class="whitespace-nowrap">❤️ Reacts: <strong class="text-slate-200"><?= $reactions ?></strong></div>
                                    </td>
                                    <td class="p-4">
                                        <?= $p['user_name'] ? h($p['user_name']) : '<span class="text-slate-600 text-xs italic">Guest</span>' ?>
                                    </td>
                                    <td class="p-4">
                                        <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded <?= $p['status'] === 'published' ? 'bg-green-500/10 text-green-400' : 'bg-yellow-500/10 text-yellow-400' ?>">
                                            <?= h($p['status']) ?>
                                        </span>
                                    </td>
                                    <td class="p-4 text-xs">
                                        <div class="flex items-center space-x-2">
                                            <?php if (is_super_admin()): ?>
                                            <a href="../p.php?s=<?= h($p['slug']) ?>" target="_blank" class="text-slate-300 hover:text-white transition">View</a>
                                            <span class="text-slate-800">|</span>
                                            <?php else: ?>
                                            <span class="text-slate-500 cursor-not-allowed" title="Only a Super Admin can open pages">🔒 View</span>
                                            <span class="text-slate-800">|</span>
                                            <?php endif; ?>
                                            <a href="pages.php?delete=<?= $p['id'] ?>" onclick="return confirm('Are you sure you want to delete this page permanently?')" class="text-red-500 hover:text-red-400 font-semibold transition">Delete</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- Footer -->
    <footer class="bg-slate-950 border-t border-slate-900 py-6 text-center text-slate-600 text-xs">
        &copy; <?= date('Y') ?> SoulSync Admin.
    </footer>

</body>
</html>
