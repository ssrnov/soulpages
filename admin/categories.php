<?php
require_once '../includes/functions.php';

if (!is_logged_in() || !is_admin()) {
    redirect('login.php');
}

$action = $_GET['action'] ?? '';
$error = '';
$success = '';

// Re-index display orders helper
function reindex_display_orders($pdo) {
    try {
        $stmt = $pdo->query("SELECT id FROM categories ORDER BY featured DESC, display_order ASC, id ASC");
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $upd = $pdo->prepare("UPDATE categories SET display_order = ? WHERE id = ?");
        $idx = 1;
        foreach ($ids as $id) {
            $upd->execute([$idx, $id]);
            $idx++;
        }
    } catch (PDOException $e) {}
}

// --------------------------------------------------
// ACTIONS HANDLER
// --------------------------------------------------
if ($action === 'save_order') {
    $input = json_decode(file_get_contents('php://input'), true);
    $ids = $input['ids'] ?? [];
    if (!empty($ids)) {
        try {
            $pdo->beginTransaction();
            $upd = $pdo->prepare("UPDATE categories SET display_order = ? WHERE id = ?");
            $order = 1;
            foreach ($ids as $id) {
                $upd->execute([$order, (int)$id]);
                $order++;
            }
            $pdo->commit();
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No IDs provided']);
    exit;
}

if ($action === 'reorder') {
    $id = (int)($_GET['id'] ?? 0);
    $dir = $_GET['dir'] ?? '';
    
    reindex_display_orders($pdo);
    
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    $cat = $stmt->fetch();
    if ($cat) {
        $curr_order = (int)$cat['display_order'];
        if ($dir === 'up') {
            $stmt_adj = $pdo->prepare("SELECT * FROM categories WHERE display_order < ? ORDER BY display_order DESC LIMIT 1");
        } else {
            $stmt_adj = $pdo->prepare("SELECT * FROM categories WHERE display_order > ? ORDER BY display_order ASC LIMIT 1");
        }
        $stmt_adj->execute([$curr_order]);
        $adj = $stmt_adj->fetch();
        if ($adj) {
            $pdo->prepare("UPDATE categories SET display_order = ? WHERE id = ?")->execute([$adj['display_order'], $id]);
            $pdo->prepare("UPDATE categories SET display_order = ? WHERE id = ?")->execute([$curr_order, $adj['id']]);
            $success = "Categories reordered successfully.";
        }
    }
    reindex_display_orders($pdo);
    redirect('categories.php');
}

if ($action === 'toggle_status') {
    $id = (int)($_GET['id'] ?? 0);
    $status = $_GET['status'] ?? 'enabled';
    if (in_array($status, ['enabled', 'disabled', 'hidden', 'maintenance'])) {
        $pdo->prepare("UPDATE categories SET status = ? WHERE id = ?")->execute([$status, $id]);
        $success = "Category status updated successfully.";
    }
    redirect('categories.php');
}

if ($action === 'toggle_featured') {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT featured FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    $curr = $stmt->fetchColumn();
    $new_f = $curr ? 0 : 1;
    $pdo->prepare("UPDATE categories SET featured = ? WHERE id = ?")->execute([$new_f, $id]);
    $success = "Category featured status updated.";
    reindex_display_orders($pdo);
    redirect('categories.php');
}

if ($action === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    // Delete safety check: don't delete if pages reference it, unless forced? 
    // We will delete normally or warn the user.
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM pages WHERE category = (SELECT slug FROM categories WHERE id = ?)");
    $stmt->execute([$id]);
    $pages_count = $stmt->fetchColumn();
    
    if ($pages_count > 0) {
        $error = "Cannot delete category because $pages_count page(s) currently use it. Disable or hide it instead.";
    } else {
        $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
        $success = "Category deleted successfully.";
        reindex_display_orders($pdo);
        redirect('categories.php');
    }
}

// --------------------------------------------------
// SAVE CATEGORY (ADD / EDIT)
// --------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'add' || $action === 'edit')) {
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $icon = trim($_POST['icon'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $theme = trim($_POST['theme'] ?? 'romantic');
    $status = $_POST['status'] ?? 'enabled';
    $featured = isset($_POST['featured']) ? 1 : 0;
    
    $default_title = trim($_POST['default_title'] ?? '');
    $default_letter = trim($_POST['default_letter'] ?? '');
    $default_question = trim($_POST['default_question'] ?? '');
    $font = trim($_POST['font'] ?? 'Outfit');
    $accent_hex = trim($_POST['accent_hex'] ?? '#ec4899');
    $music_url = trim($_POST['music_url'] ?? '');
    $slides_json = trim($_POST['slides'] ?? '[]');

    if (empty($slug)) {
        // Simple slugify
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '_', $name)));
    }
    
    if (empty($name) || empty($slug) || empty($icon)) {
        $error = "Name, Slug, and Icon are required.";
    } else {
        if ($action === 'add') {
            // Check uniqueness
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE slug = ?");
            $stmt->execute([$slug]);
            if ($stmt->fetchColumn() > 0) {
                $error = "A category with slug '$slug' already exists.";
            } else {
                // Find next display order
                $max_order = $pdo->query("SELECT COALESCE(MAX(display_order), 0) FROM categories")->fetchColumn();
                $display_order = $max_order + 1;
                
                $stmt = $pdo->prepare("INSERT INTO categories 
                    (name, slug, icon, description, status, featured, display_order, theme, default_title, default_letter, default_question, font, accent_hex, music_url, slides) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $slug, $icon, $description, $status, $featured, $display_order, $theme, $default_title, $default_letter, $default_question, $font, $accent_hex, $music_url, $slides_json]);
                $success = "Category created successfully.";
                reindex_display_orders($pdo);
                redirect('categories.php');
            }
        } else {
            // Edit check unique except self
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE slug = ? AND id != ?");
            $stmt->execute([$slug, $id]);
            if ($stmt->fetchColumn() > 0) {
                $error = "A category with slug '$slug' already exists.";
            } else {
                $stmt = $pdo->prepare("UPDATE categories SET 
                    name = ?, slug = ?, icon = ?, description = ?, status = ?, featured = ?, theme = ?, default_title = ?, default_letter = ?, default_question = ?, font = ?, accent_hex = ?, music_url = ?, slides = ? 
                    WHERE id = ?");
                $stmt->execute([$name, $slug, $icon, $description, $status, $featured, $theme, $default_title, $default_letter, $default_question, $font, $accent_hex, $music_url, $slides_json, $id]);
                $success = "Category updated successfully.";
                reindex_display_orders($pdo);
                redirect('categories.php');
            }
        }
    }
}

// --------------------------------------------------
// METRICS & REVENUE PRECALCULATION
// --------------------------------------------------
// 1. Pro-rated user payment revenue
$user_payments = [];
try {
    $stmt = $pdo->query("SELECT user_id, SUM(amount) as total_paid FROM payments WHERE status = 'captured' GROUP BY user_id");
    $user_payments = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // user_id => total_paid in paise
} catch (PDOException $e) {
    // payments table might not exist yet or be empty
}

$stmt = $pdo->query("SELECT user_id, category, COUNT(*) as page_count FROM pages GROUP BY user_id, category");
$user_pages = [];
$user_total_pages = [];
while ($row = $stmt->fetch()) {
    $uid = $row['user_id'];
    if (!$uid) continue;
    $cat = $row['category'];
    $count = (int)$row['page_count'];
    $user_pages[$uid][$cat] = $count;
    $user_total_pages[$uid] = ($user_total_pages[$uid] ?? 0) + $count;
}

$category_revenue = [];
foreach ($user_payments as $uid => $total_paid) {
    $total_p = $user_total_pages[$uid] ?? 0;
    if ($total_p === 0) continue;
    foreach ($user_pages[$uid] as $cat => $count) {
        $share = ($count / $total_p) * $total_paid;
        $category_revenue[$cat] = ($category_revenue[$cat] ?? 0) + $share;
    }
}

// 2. Direct page extensions revenue
$stmt = $pdo->query("SELECT p.category, SUM(ee.amount_paise) as ext_rev FROM expiry_extensions ee JOIN pages p ON ee.page_id = p.id GROUP BY p.category");
while ($row = $stmt->fetch()) {
    $cat = $row['category'];
    $ext_rev = (float)$row['ext_rev'];
    $category_revenue[$cat] = ($category_revenue[$cat] ?? 0) + $ext_rev;
}

// --------------------------------------------------
// DATA RETRIEVAL FOR LIST OR EDIT FORM
// --------------------------------------------------
$edit_cat = null;
if (($action === 'edit' || $action === 'analytics') && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    $edit_cat = $stmt->fetch();
}

// Fetch all categories for list
$query_list = "SELECT c.*,
    (SELECT COUNT(*) FROM pages WHERE category = c.slug) as total_pages,
    (SELECT COUNT(*) FROM page_views pv JOIN pages p ON pv.page_id = p.id WHERE p.category = c.slug) as total_views,
    (SELECT COUNT(*) FROM page_replies pr JOIN pages p ON pr.page_id = p.id WHERE p.category = c.slug) as total_replies,
    (SELECT COUNT(*) FROM reactions r JOIN pages p ON r.page_id = p.id WHERE p.category = c.slug) as total_reactions,
    (SELECT COUNT(*) FROM page_views pv JOIN pages p ON pv.page_id = p.id WHERE p.category = c.slug AND pv.completed = 1) as completed_views
FROM categories c
ORDER BY c.featured DESC, c.display_order ASC";

$categories = $pdo->query($query_list)->fetchAll();

// Fetch music options for dropdown
$music_list = $pdo->query("SELECT title, file_path, category FROM music_library ORDER BY category, title")->fetchAll();

$admin_nav = 'categories';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Category Management - SoulSync Admin</title>
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
    <?php $ADMIN_TITLE='Categories'; include '_nav.php'; ?>

    <main class="flex-grow max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-10">

        <!-- Alert Notifications -->
        <?php if (!empty($error)): ?>
            <div class="bg-red-500/10 border border-red-500/20 text-red-400 p-4 rounded-2xl mb-6 text-sm flex items-center gap-3">
                <span>⚠️</span> <span><?= h($error) ?></span>
            </div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="bg-green-500/10 border border-green-500/20 text-green-400 p-4 rounded-2xl mb-6 text-sm flex items-center gap-3">
                <span>✅</span> <span><?= h($success) ?></span>
            </div>
        <?php endif; ?>

        <!-- ========================================================================= -->
        <!-- ACTION: ANALYTICS DRAWER/VIEW -->
        <!-- ========================================================================= -->
        <?php if ($action === 'analytics' && $edit_cat): 
            $slug = $edit_cat['slug'];
            $total_p = (int)$pdo->prepare("SELECT COUNT(*) FROM pages WHERE category = ?")->execute([$slug]) ? $pdo->prepare("SELECT COUNT(*) FROM pages WHERE category = ?")->fetchColumn() : 0;
            
            // Subquery results for current slug
            $stmt_metrics = $pdo->prepare("SELECT 
                (SELECT COUNT(*) FROM page_views pv JOIN pages p ON pv.page_id = p.id WHERE p.category = ?) as v_count,
                (SELECT COUNT(*) FROM page_replies pr JOIN pages p ON pr.page_id = p.id WHERE p.category = ?) as rep_count,
                (SELECT COUNT(*) FROM reactions r JOIN pages p ON r.page_id = p.id WHERE p.category = ?) as reac_count,
                (SELECT COUNT(*) FROM page_views pv JOIN pages p ON pv.page_id = p.id WHERE p.category = ? AND pv.completed = 1) as comp_count");
            $stmt_metrics->execute([$slug, $slug, $slug, $slug]);
            $metrics = $stmt_metrics->fetch();
            
            $views = (int)$metrics['v_count'];
            $replies = (int)$metrics['rep_count'];
            $reactions = (int)$metrics['reac_count'];
            $comp = (int)$metrics['comp_count'];
            
            $comp_rate = $views > 0 ? round(($comp / $views) * 100, 1) : 0;
            $rev = ($category_revenue[$slug] ?? 0) / 100;
            
            // Most Popular Pages (top 5 by views)
            $stmt_pop = $pdo->prepare("SELECT p.title, p.slug, p.sender_name, p.receiver_name, COUNT(pv.id) as view_count 
                FROM pages p 
                LEFT JOIN page_views pv ON pv.page_id = p.id 
                WHERE p.category = ? 
                GROUP BY p.id 
                ORDER BY view_count DESC 
                LIMIT 5");
            $stmt_pop->execute([$slug]);
            $popular_pages = $stmt_pop->fetchAll();
        ?>
            <div class="mb-6 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="text-3xl"><?= $edit_cat['icon'] ?></span>
                    <div>
                        <h2 class="text-xl font-extrabold font-heading text-white"><?= h($edit_cat['name']) ?> Analytics</h2>
                        <p class="text-xs text-slate-500 font-mono">slug: <?= h($slug) ?></p>
                    </div>
                </div>
                <a href="categories.php" class="text-xs font-semibold bg-slate-900 border border-slate-800 text-slate-300 px-4 py-2 rounded-xl hover:bg-slate-800 transition">Back to List</a>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
                <div class="glass-card rounded-2xl p-5 text-center">
                    <span class="text-[10px] text-slate-500 uppercase font-semibold tracking-wider block mb-1">Total Stories</span>
                    <p class="text-3xl font-extrabold text-pink-400 font-heading"><?= number_format($total_p) ?></p>
                </div>
                <div class="glass-card rounded-2xl p-5 text-center">
                    <span class="text-[10px] text-slate-500 uppercase font-semibold tracking-wider block mb-1">Total Views</span>
                    <p class="text-3xl font-extrabold text-blue-400 font-heading"><?= number_format($views) ?></p>
                </div>
                <div class="glass-card rounded-2xl p-5 text-center">
                    <span class="text-[10px] text-slate-500 uppercase font-semibold tracking-wider block mb-1">Total Replies</span>
                    <p class="text-3xl font-extrabold text-green-400 font-heading"><?= number_format($replies) ?></p>
                </div>
                <div class="glass-card rounded-2xl p-5 text-center">
                    <span class="text-[10px] text-slate-500 uppercase font-semibold tracking-wider block mb-1">Total Reactions</span>
                    <p class="text-3xl font-extrabold text-red-400 font-heading"><?= number_format($reactions) ?></p>
                </div>
                <div class="glass-card rounded-2xl p-5 text-center">
                    <span class="text-[10px] text-slate-500 uppercase font-semibold tracking-wider block mb-1">Revenue Generated</span>
                    <p class="text-3xl font-extrabold text-emerald-400 font-heading">₹<?= number_format($rev) ?></p>
                </div>
                <div class="glass-card rounded-2xl p-5 text-center">
                    <span class="text-[10px] text-slate-500 uppercase font-semibold tracking-wider block mb-1">Completion Rate</span>
                    <p class="text-3xl font-extrabold text-purple-400 font-heading"><?= $comp_rate ?>%</p>
                </div>
            </div>

            <!-- Deep Analytics Detail Panels -->
            <div class="grid lg:grid-cols-2 gap-6">
                <!-- Popular pages -->
                <div class="glass-card rounded-3xl p-6">
                    <h3 class="text-lg font-bold font-heading text-white mb-4">⭐ Most Popular Stories</h3>
                    <div class="space-y-4">
                        <?php if (empty($popular_pages)): ?>
                            <p class="text-sm text-slate-600">No views registered yet.</p>
                        <?php else: ?>
                            <?php foreach ($popular_pages as $idx => $pop): ?>
                                <div class="flex items-center justify-between border-b border-slate-900 pb-3 last:border-0 last:pb-0">
                                    <div class="flex items-center gap-3">
                                        <span class="w-6 h-6 rounded-lg bg-slate-900 flex items-center justify-center text-xs font-bold text-slate-500"><?= $idx + 1 ?></span>
                                        <div>
                                            <?php if (is_super_admin()): ?><a href="../p.php?s=<?= h($pop['slug']) ?>" target="_blank" class="text-sm font-semibold text-white hover:text-pink-400 transition"><?= h($pop['title']) ?></a><?php else: ?><span class="text-sm font-semibold text-white"><?= h($pop['title']) ?></span><?php endif; ?>
                                            <span class="block text-[10px] text-slate-500"><?= h($pop['sender_name']) ?> &rarr; <?= h($pop['receiver_name']) ?></span>
                                        </div>
                                    </div>
                                    <span class="text-xs font-semibold bg-slate-900 border border-slate-800 text-slate-300 px-3 py-1 rounded-xl"><?= number_format($pop['view_count']) ?> Views</span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Category properties summary -->
                <div class="glass-card rounded-3xl p-6 flex flex-col justify-between">
                    <div>
                        <h3 class="text-lg font-bold font-heading text-white mb-4">⚙️ Category Configuration</h3>
                        <div class="grid grid-cols-2 gap-4 text-xs">
                            <div class="bg-slate-950/40 p-3 rounded-xl border border-slate-900">
                                <span class="text-slate-500 uppercase tracking-wider font-semibold block mb-1">Theme</span>
                                <span class="text-white font-semibold capitalize"><?= h($edit_cat['theme']) ?></span>
                            </div>
                            <div class="bg-slate-950/40 p-3 rounded-xl border border-slate-900">
                                <span class="text-slate-500 uppercase tracking-wider font-semibold block mb-1">Font</span>
                                <span class="text-white font-semibold"><?= h($edit_cat['font'] ?: 'Outfit') ?></span>
                            </div>
                            <div class="bg-slate-950/40 p-3 rounded-xl border border-slate-900">
                                <span class="text-slate-500 uppercase tracking-wider font-semibold block mb-1">Accent Hex</span>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="w-3.5 h-3.5 rounded border border-white/10" style="background-color: <?= h($edit_cat['accent_hex']) ?>"></span>
                                    <span class="text-white font-mono"><?= h($edit_cat['accent_hex'] ?: '#ec4899') ?></span>
                                </div>
                            </div>
                            <div class="bg-slate-950/40 p-3 rounded-xl border border-slate-900">
                                <span class="text-slate-500 uppercase tracking-wider font-semibold block mb-1">Background Music</span>
                                <span class="text-white font-semibold truncate block" title="<?= h($edit_cat['music_url']) ?>"><?= $edit_cat['music_url'] ? basename($edit_cat['music_url']) : 'None' ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="mt-6 pt-4 border-t border-slate-900 flex justify-end gap-2">
                        <a href="categories.php?action=edit&id=<?= $edit_cat['id'] ?>" class="bg-pink-500 hover:bg-pink-600 text-white font-bold text-xs px-4 py-2.5 rounded-xl transition">Edit Category Details</a>
                    </div>
                </div>
            </div>

        <!-- ========================================================================= -->
        <!-- ACTION: ADD OR EDIT FORM -->
        <!-- ========================================================================= -->
        <?php elseif ($action === 'add' || ($action === 'edit' && $edit_cat)): ?>
            <div class="mb-6 flex items-center justify-between">
                <h2 class="text-xl font-extrabold font-heading text-white"><?= $action === 'add' ? 'Create Category' : 'Edit Category: ' . h($edit_cat['name']) ?></h2>
                <a href="categories.php" class="text-xs font-semibold bg-slate-900 border border-slate-800 text-slate-300 px-4 py-2 rounded-xl hover:bg-slate-800 transition">Cancel</a>
            </div>

            <form action="categories.php?action=<?= $action ?>" method="POST" class="grid lg:grid-cols-3 gap-6">
                <input type="hidden" name="id" value="<?= $edit_cat ? (int)$edit_cat['id'] : '' ?>">
                
                <!-- Left column: Main Metadata Fields -->
                <div class="lg:col-span-1 space-y-6">
                    <div class="glass-card rounded-3xl p-6 space-y-4">
                        <h3 class="text-sm font-extrabold uppercase tracking-wider text-slate-400 mb-2">Basic Info</h3>
                        
                        <div>
                            <label class="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-2">Category Name</label>
                            <input type="text" name="name" value="<?= $edit_cat ? h($edit_cat['name']) : '' ?>" placeholder="e.g. Crush Confession" required class="w-full bg-slate-900 border border-slate-800 focus:border-pink-500 focus:ring-1 focus:ring-pink-500 rounded-xl px-3.5 py-2.5 text-sm outline-none text-white transition">
                        </div>

                        <div>
                            <label class="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-2">Slug (URL identifier)</label>
                            <input type="text" name="slug" value="<?= $edit_cat ? h($edit_cat['slug']) : '' ?>" placeholder="e.g. crush" class="w-full bg-slate-900 border border-slate-800 focus:border-pink-500 focus:ring-1 focus:ring-pink-500 rounded-xl px-3.5 py-2.5 text-sm outline-none text-white transition" <?= $action === 'edit' ? 'readonly bg-slate-950/40 text-slate-500 border-slate-950/60' : '' ?>>
                            <?php if ($action === 'edit'): ?>
                                <span class="text-[9px] text-slate-600 mt-1 block">Category slug is used for linking stories and cannot be changed.</span>
                            <?php endif; ?>
                        </div>

                        <div>
                            <label class="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-2">Emoji Icon</label>
                            <input type="text" name="icon" value="<?= $edit_cat ? h($edit_cat['icon']) : '✨' ?>" placeholder="e.g. 🤫" required class="w-full bg-slate-900 border border-slate-800 focus:border-pink-500 focus:ring-1 focus:ring-pink-500 rounded-xl px-3.5 py-2.5 text-sm outline-none text-white text-lg transition">
                        </div>

                        <div>
                            <label class="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-2">Category Description</label>
                            <textarea name="description" placeholder="A short description of this category..." class="w-full h-24 bg-slate-900 border border-slate-800 focus:border-pink-500 focus:ring-1 focus:ring-pink-500 rounded-xl px-3.5 py-2.5 text-xs outline-none text-white transition"><?= $edit_cat ? h($edit_cat['description']) : '' ?></textarea>
                        </div>
                    </div>

                    <div class="glass-card rounded-3xl p-6 space-y-4">
                        <h3 class="text-sm font-extrabold uppercase tracking-wider text-slate-400 mb-2">Status & Controls</h3>
                        
                        <div>
                            <label class="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-2">Status Visibility</label>
                            <select name="status" class="w-full bg-slate-900 border border-slate-800 focus:border-pink-500 focus:ring-1 focus:ring-pink-500 rounded-xl px-3 py-2 text-xs outline-none text-slate-300 transition">
                                <option value="enabled" <?= ($edit_cat && $edit_cat['status'] === 'enabled') ? 'selected' : '' ?>>Enabled (Visible & Creatable)</option>
                                <option value="disabled" <?= ($edit_cat && $edit_cat['status'] === 'disabled') ? 'selected' : '' ?>>Disabled (Viewable only, creation blocked)</option>
                                <option value="hidden" <?= ($edit_cat && $edit_cat['status'] === 'hidden') ? 'selected' : '' ?>>Hidden (Admin only)</option>
                                <option value="maintenance" <?= ($edit_cat && $edit_cat['status'] === 'maintenance') ? 'selected' : '' ?>>Maintenance Mode (Shows maintenance message)</option>
                            </select>
                        </div>

                        <div class="flex items-center justify-between bg-slate-900/60 p-3 rounded-xl border border-slate-800">
                            <div>
                                <label class="block text-xs font-bold text-white mb-0.5">⭐ Featured Category</label>
                                <span class="text-[9px] text-slate-500 block leading-tight">Featured categories appear first on the homepage in larger cards.</span>
                            </div>
                            <input type="checkbox" name="featured" value="1" <?= ($edit_cat && $edit_cat['featured']) ? 'checked' : '' ?> class="w-4 h-4 text-pink-500 rounded bg-slate-900 border-slate-800 focus:ring-pink-500 focus:ring-offset-slate-950">
                        </div>
                    </div>
                </div>

                <!-- Middle column: Styles, Music & Fallbacks -->
                <div class="lg:col-span-1 space-y-6">
                    <div class="glass-card rounded-3xl p-6 space-y-4">
                        <h3 class="text-sm font-extrabold uppercase tracking-wider text-slate-400 mb-2">Design & Styles</h3>
                        
                        <div>
                            <label class="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-2">Default Theme Template</label>
                            <select name="theme" class="w-full bg-slate-900 border border-slate-800 focus:border-pink-500 focus:ring-1 focus:ring-pink-500 rounded-xl px-3 py-2 text-xs outline-none text-slate-300 transition">
                                <?php foreach (get_themes() as $t_key => $t_info): ?>
                                    <option value="<?= h($t_key) ?>" <?= ($edit_cat && $edit_cat['theme'] === $t_key) ? 'selected' : '' ?>><?= $t_info['icon'] ?> <?= h($t_info['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-2">Default Font</label>
                            <select name="font" class="w-full bg-slate-900 border border-slate-800 focus:border-pink-500 focus:ring-1 focus:ring-pink-500 rounded-xl px-3 py-2 text-xs outline-none text-slate-300 transition">
                                <option value="Outfit" <?= ($edit_cat && $edit_cat['font'] === 'Outfit') ? 'selected' : '' ?>>Outfit (Sans Serif)</option>
                                <option value="Playfair Display" <?= ($edit_cat && $edit_cat['font'] === 'Playfair Display') ? 'selected' : '' ?>>Playfair Display (Elegant Serif)</option>
                                <option value="Inter" <?= ($edit_cat && $edit_cat['font'] === 'Inter') ? 'selected' : '' ?>>Inter (Modern Sans)</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-2">Default Color Scheme (Hex)</label>
                            <div class="flex gap-2">
                                <input type="color" id="accent_color_picker" class="w-10 h-10 border border-slate-800 bg-slate-900 rounded-xl cursor-pointer" value="<?= $edit_cat ? h($edit_cat['accent_hex']) : '#ec4899' ?>" onchange="document.getElementById('accent_hex').value = this.value">
                                <input type="text" id="accent_hex" name="accent_hex" value="<?= $edit_cat ? h($edit_cat['accent_hex']) : '#ec4899' ?>" placeholder="#ec4899" required class="flex-grow bg-slate-900 border border-slate-800 focus:border-pink-500 focus:ring-1 focus:ring-pink-500 rounded-xl px-3.5 py-2 text-sm outline-none text-white text-center transition" onchange="document.getElementById('accent_color_picker').value = this.value">
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-2">Default Background Music</label>
                            <select name="music_url" class="w-full bg-slate-900 border border-slate-800 focus:border-pink-500 focus:ring-1 focus:ring-pink-500 rounded-xl px-3 py-2 text-xs outline-none text-slate-300 transition">
                                <option value="">-- No Default Music --</option>
                                <?php foreach ($music_list as $m): ?>
                                    <option value="<?= h($m['file_path']) ?>" <?= ($edit_cat && $edit_cat['music_url'] === $m['file_path']) ? 'selected' : '' ?>>
                                        [<?= h($m['category']) ?>] <?= h($m['title']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="glass-card rounded-3xl p-6 space-y-4">
                        <h3 class="text-sm font-extrabold uppercase tracking-wider text-slate-400 mb-2">Default Field Fallbacks</h3>
                        
                        <div>
                            <label class="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-2">Default Story Title</label>
                            <input type="text" name="default_title" value="<?= $edit_cat ? h($edit_cat['default_title']) : '' ?>" placeholder="e.g. My Secret Confession for You" class="w-full bg-slate-900 border border-slate-800 focus:border-pink-500 focus:ring-1 focus:ring-pink-500 rounded-xl px-3.5 py-2.5 text-xs outline-none text-white transition">
                        </div>

                        <div>
                            <label class="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-2">Default Letter Text</label>
                            <textarea name="default_letter" placeholder="The fallback content for the main letter slide..." class="w-full h-24 bg-slate-900 border border-slate-800 focus:border-pink-500 focus:ring-1 focus:ring-pink-500 rounded-xl px-3.5 py-2.5 text-xs outline-none text-white transition"><?= $edit_cat ? h($edit_cat['default_letter']) : '' ?></textarea>
                        </div>

                        <div>
                            <label class="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider mb-2">Default Decision Question</label>
                            <input type="text" name="default_question" value="<?= $edit_cat ? h($edit_cat['default_question']) : '' ?>" placeholder="e.g. Will you be mine? 💖" class="w-full bg-slate-900 border border-slate-800 focus:border-pink-500 focus:ring-1 focus:ring-pink-500 rounded-xl px-3.5 py-2.5 text-xs outline-none text-white transition">
                        </div>
                    </div>
                </div>

                <!-- Right column: Dynamic Slides List Builder -->
                <div class="lg:col-span-1 space-y-6">
                    <div class="glass-card rounded-3xl p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-sm font-extrabold uppercase tracking-wider text-slate-400">Slides & Animations</h3>
                            <button type="button" onclick="addSlideRow()" class="bg-pink-500 hover:bg-pink-600 text-white font-bold text-[10px] px-3 py-1.5 rounded-lg transition">+ Add Slide</button>
                        </div>
                        
                        <textarea id="slides_textarea" name="slides" class="hidden"><?= $edit_cat ? h($edit_cat['slides']) : '[]' ?></textarea>
                        
                        <div id="slides_list_container" class="space-y-4 max-h-[500px] overflow-y-auto pr-1">
                            <!-- Rows injected via Javascript -->
                        </div>
                    </div>
                    
                    <div class="flex justify-end gap-3">
                        <button type="submit" class="w-full bg-gradient-to-r from-pink-500 to-purple-500 hover:opacity-95 text-white font-bold text-sm py-3.5 rounded-xl transition shadow-lg shadow-pink-500/25">Save Category</button>
                    </div>
                </div>
            </form>

            <!-- Dynamic slide script -->
            <script>
                // List of all valid slide types for selection dropdown
                const slideTypes = [
                    { value: 'welcome', label: 'Welcome Page' },
                    { value: 'gallery', label: 'Photo Gallery' },
                    { value: 'letter', label: 'Letter / Text Message' },
                    { value: 'voice_message', label: 'Voice Message' },
                    { value: 'video_message', label: 'Video Message' },
                    { value: 'text_story', label: 'Story Description Section' },
                    { value: 'interactive_choice', label: 'Interactive Decision Option' },
                    { value: 'celebration', label: 'Celebration Confetti Screen' },
                    
                    // Cinematic slide models
                    { value: 'proposal_rose_cinematic', label: '[Proposal] Rose cinematic' },
                    { value: 'proposal_constellation', label: '[Proposal] Stars constellation' },
                    { value: 'proposal_heart_formation', label: '[Proposal] Heart particles' },
                    { value: 'proposal_love_letter', label: '[Proposal] Love letter sheet' },
                    { value: 'proposal_future_portal', label: '[Proposal] Future portal' },
                    { value: 'proposal_countdown', label: '[Proposal] Countdown ticker' },
                    { value: 'proposal_ring_cinematic', label: '[Proposal] Ring Cinematic box' },
                    { value: 'proposal_final_cinematic', label: '[Proposal] Ultimate Question' },
                    { value: 'proposal_celebration', label: '[Proposal] Final Celebration' },
                    { value: 'sorry_broken_heart_repair', label: '[Apology] Broken Heart repair' },
                    { value: 'sorry_rain_to_sunshine', label: '[Apology] Rain to sunshine wiper' },
                    { value: 'sorry_memory_lantern', label: '[Apology] Memory Lantern lift' },
                    { value: 'birthday_pop_balloons', label: '[Birthday] Pop Balloons' },
                    { value: 'birthday_blow_candles', label: '[Birthday] Blow Candles' },
                    { value: 'birthday_cake_cutting', label: '[Birthday] Cut the cake' },
                    { value: 'love_letter_envelope_open', label: '[Love Letter] Wax sealed envelope' },
                    { value: 'love_letter_handwrite', label: '[Love Letter] Handwrite Ink' },
                    { value: 'love_letter_scrapbook', label: '[Love Letter] Scrapbook flip' },
                    { value: 'love_letter_timeline', label: '[Love Letter] Love timeline' },
                    { value: 'love_letter_folding', label: '[Love Letter] Kiss envelope folding' },
                    { value: 'love_letter_reactions_finale', label: '[Love Letter] Hearts reaction' },
                    { value: 'mana_lo_angry_meter', label: '[Maan Jao] Angry Meter scale' },
                    { value: 'mana_lo_emergency', label: '[Maan Jao] Emergency alert' },
                    { value: 'mana_lo_rescue', label: '[Maan Jao] Memory rescue restore' },
                    { value: 'mana_lo_miss_things', label: '[Maan Jao] Things I Miss list' },
                    { value: 'mana_lo_bribe', label: '[Maan Jao] Gift boxes bribe' },
                    { value: 'mana_lo_quiz', label: '[Maan Jao] Playful truth quiz' },
                    { value: 'mana_lo_unlock', label: '[Maan Jao] Drag key lock' },
                    { value: 'mana_lo_smile', label: '[Maan Jao] Smile please request' },
                    { value: 'friendship_wheel', label: '[Friendship] Wheel spin' },
                    { value: 'friendship_moment_cards', label: '[Friendship] Flip cards highlight' },
                    { value: 'friendship_badge', label: '[Friendship] Stamp badge award' },
                    { value: 'anniversary_together_counter', label: '[Anniversary] Time counter ticker' },
                    { value: 'anniversary_timeline_unlock', label: '[Anniversary] Year milestones list' },
                    { value: 'anniversary_memory_book', label: '[Anniversary] Book flip journal' },
                    { value: 'invite_date_wheel', label: '[Invite] Date idea spinner' },
                    { value: 'invite_map_reveal', label: '[Invite] Zoom map pinpoint' },
                    { value: 'patchup_rebuild_bridge', label: '[Patchup] Rebuild Bridge planks' },
                    { value: 'patchup_lock_key', label: '[Patchup] Unlock lock key' },
                    { value: 'miss_you_star_collection', label: '[Miss You] Catch stars reasons' },
                    { value: 'miss_you_moon_message', label: '[Miss You] Scroll under moon' },
                    { value: 'congrats_trophy_reveal', label: '[Congrats] Trophy reveal card' },
                    { value: 'congrats_fireworks', label: '[Congrats] Fireworks click' },
                    { value: 'crush_secret_envelope', label: '[Crush] Slide envelope text' },
                    { value: 'crush_hidden_message', label: '[Crush] Wipe screen fog' },
                    { value: 'crush_heartbeat', label: '[Crush] Hold heartbeat scale' },
                    { value: 'surprise_mystery_box', label: '[Surprise] Mystery box unwrap' },
                    { value: 'surprise_scratch_card', label: '[Surprise] Scratch card coin' },
                    { value: 'surprise_countdown_reveal', label: '[Surprise] Timer pulse count' },
                    { value: 'counter', label: '[Long Distance] Meet Countdown counter' },
                    
                    // Premium Options
                    { value: 'premium_memory_reveal', label: '[Premium] photo memory reveal' },
                    { value: 'premium_heart_formation', label: '[Premium] Heart particles' },
                    { value: 'premium_collage_explosion', label: '[Premium] Collage explosion' },
                    { value: 'premium_star_sky', label: '[Premium] Star sky' },
                    { value: 'premium_memory_book', label: '[Premium] Scrapbook page' },
                    { value: 'premium_reasons_special', label: '[Premium] Why you are special' },
                    { value: 'premium_puzzle_reveal', label: '[Premium] Photo puzzle unlock' },
                    { value: 'premium_memory_timeline', label: '[Premium] Milestones timeline' },
                    { value: 'premium_mosaic_heart', label: '[Premium] Mosaic photo heart' }
                ];

                const animations = ['fadeIn', 'typewriter', 'zoomIn', 'slideUp', 'bounceIn', 'heartbeat', 'confetti', 'countPulse', 'scaleUp', 'explosionReveal'];

                let slides = [];
                try {
                    slides = JSON.parse(document.getElementById('slides_textarea').value);
                } catch(e) {
                    slides = [];
                }
                if (!Array.isArray(slides)) slides = [];

                function serializeSlides() {
                    const container = document.getElementById('slides_list_container');
                    const rows = container.querySelectorAll('.slide-row');
                    const result = [];
                    
                    rows.forEach(row => {
                        const type = row.querySelector('.slide-type').value;
                        const key = row.querySelector('.slide-key').value;
                        const title = row.querySelector('.slide-title').value;
                        const subtitle = row.querySelector('.slide-subtitle').value;
                        const btn = row.querySelector('.slide-btn').value;
                        const animation = row.querySelector('.slide-animation').value;
                        const is_optional = row.querySelector('.slide-optional').checked;
                        
                        const item = { type, title, animation };
                        if (key) item.key = key;
                        if (subtitle) item.subtitle = subtitle;
                        if (btn) item.btn = btn;
                        if (is_optional) item.is_optional = true;
                        
                        result.push(item);
                    });
                    
                    document.getElementById('slides_textarea').value = JSON.stringify(result);
                }

                function renderSlideRows() {
                    const container = document.getElementById('slides_list_container');
                    container.innerHTML = '';
                    
                    if (slides.length === 0) {
                        container.innerHTML = `<p class="text-xs text-slate-600 text-center py-4">No slides configured. Add a slide to begin.</p>`;
                        return;
                    }
                    
                    slides.forEach((slide, idx) => {
                        const row = document.createElement('div');
                        row.className = 'slide-row glass-card rounded-2xl p-4 border border-slate-900 relative space-y-3';
                        
                        // Header info
                        let typeOptions = '';
                        slideTypes.forEach(t => {
                            typeOptions += `<option value="${t.value}" ${slide.type === t.value ? 'selected' : ''}>${t.label}</option>`;
                        });

                        let animOptions = '';
                        animations.forEach(a => {
                            animOptions += `<option value="${a}" ${slide.animation === a ? 'selected' : ''}>${a}</option>`;
                        });

                        row.innerHTML = `
                            <div class="flex items-center justify-between border-b border-slate-900/60 pb-2">
                                <span class="text-xs font-bold text-pink-400">Slide #${idx + 1}</span>
                                <div class="flex gap-1.5">
                                    <button type="button" onclick="moveSlide(${idx}, 'up')" class="text-[10px] bg-slate-900 border border-slate-800 hover:bg-slate-800 text-slate-400 w-6 h-6 rounded flex items-center justify-center font-bold">▲</button>
                                    <button type="button" onclick="moveSlide(${idx}, 'down')" class="text-[10px] bg-slate-900 border border-slate-800 hover:bg-slate-800 text-slate-400 w-6 h-6 rounded flex items-center justify-center font-bold">▼</button>
                                    <button type="button" onclick="removeSlide(${idx})" class="text-[10px] bg-red-500/10 hover:bg-red-500/20 text-red-400 w-6 h-6 rounded flex items-center justify-center font-bold">✕</button>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-2 text-xs">
                                <div>
                                    <label class="block text-[9px] text-slate-500 uppercase font-semibold mb-1">Slide Page Type</label>
                                    <select class="slide-type w-full bg-slate-900 border border-slate-800 rounded-lg p-1.5 text-xs text-white" onchange="serializeSlides()">
                                        ${typeOptions}
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[9px] text-slate-500 uppercase font-semibold mb-1">Unique Key (optional)</label>
                                    <input type="text" class="slide-key w-full bg-slate-900 border border-slate-800 rounded-lg p-1.5 text-xs text-white" value="${slide.key ?? ''}" placeholder="e.g. first_impression" oninput="serializeSlides()">
                                </div>
                            </div>

                            <div class="text-xs space-y-1.5">
                                <div>
                                    <label class="block text-[9px] text-slate-500 uppercase font-semibold">Title Template</label>
                                    <input type="text" class="slide-title w-full bg-slate-900 border border-slate-800 rounded-lg p-1.5 text-xs text-white" value="${slide.title ?? ''}" placeholder="e.g. Hey [receiver] ❤️" oninput="serializeSlides()">
                                </div>
                                <div>
                                    <label class="block text-[9px] text-slate-500 uppercase font-semibold">Subtitle / Guide Message</label>
                                    <input type="text" class="slide-subtitle w-full bg-slate-900 border border-slate-800 rounded-lg p-1.5 text-xs text-white" value="${slide.subtitle ?? ''}" placeholder="e.g. Turn the page to see reasons..." oninput="serializeSlides()">
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-2 text-xs">
                                <div>
                                    <label class="block text-[9px] text-slate-500 uppercase font-semibold mb-1">Button Text</label>
                                    <input type="text" class="slide-btn w-full bg-slate-900 border border-slate-800 rounded-lg p-1.5 text-xs text-white" value="${slide.btn ?? ''}" placeholder="e.g. Continue" oninput="serializeSlides()">
                                </div>
                                <div>
                                    <label class="block text-[9px] text-slate-500 uppercase font-semibold mb-1">Entrance Animation</label>
                                    <select class="slide-animation w-full bg-slate-900 border border-slate-800 rounded-lg p-1.5 text-xs text-white" onchange="serializeSlides()">
                                        ${animOptions}
                                    </select>
                                </div>
                            </div>

                            <div class="flex items-center justify-between text-xs pt-1">
                                <label class="text-[10px] font-semibold text-slate-400">Premium Optional Page (Togglable by user)</label>
                                <input type="checkbox" class="slide-optional w-3.5 h-3.5 text-pink-500 rounded bg-slate-900 border-slate-800 focus:ring-pink-500" ${slide.is_optional ? 'checked' : ''} onchange="serializeSlides()">
                            </div>
                        `;
                        container.appendChild(row);
                    });
                }

                function addSlideRow() {
                    serializeSlides(); // Capture current state
                    slides.push({
                        type: 'welcome',
                        key: '',
                        title: 'A Special Message',
                        subtitle: '',
                        btn: 'Continue',
                        animation: 'fadeIn',
                        is_optional: false
                    });
                    renderSlideRows();
                    serializeSlides();
                }

                function removeSlide(idx) {
                    serializeSlides();
                    slides.splice(idx, 1);
                    renderSlideRows();
                    serializeSlides();
                }

                function moveSlide(idx, dir) {
                    serializeSlides();
                    if (dir === 'up' && idx > 0) {
                        const temp = slides[idx];
                        slides[idx] = slides[idx - 1];
                        slides[idx - 1] = temp;
                    } else if (dir === 'down' && idx < slides.length - 1) {
                        const temp = slides[idx];
                        slides[idx] = slides[idx + 1];
                        slides[idx + 1] = temp;
                    }
                    renderSlideRows();
                    serializeSlides();
                }

                // Initial render
                renderSlideRows();
            </script>

        <!-- ========================================================================= -->
        <!-- VIEW: MAIN LIST -->
        <!-- ========================================================================= -->
        <?php else: ?>
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
                <h2 class="text-xl font-extrabold font-heading text-white">Categories Management (<?= count($categories) ?>)</h2>
                <a href="categories.php?action=add" class="bg-pink-500 hover:bg-pink-600 text-white font-bold text-xs px-5 py-3 rounded-xl transition shadow-lg shadow-pink-500/25">+ Add New Category</a>
            </div>

            <div class="glass-card rounded-3xl p-6 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-400">
                        <thead class="bg-slate-900/80 text-xs text-slate-500 uppercase tracking-wider">
                            <tr>
                                <th class="p-4 rounded-l-xl w-16 text-center">Order</th>
                                <th class="p-4">Category Name</th>
                                <th class="p-4 text-center">Icon</th>
                                <th class="p-4">Status</th>
                                <th class="p-4 text-center">Total Pages</th>
                                <th class="p-4 text-center">Total Stories Created</th>
                                <th class="p-4">Theme</th>
                                <th class="p-4 text-center text-emerald-400 font-bold">Revenue</th>
                                <th class="p-4 rounded-r-xl text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-900">
                            <?php if (count($categories) === 0): ?>
                                <tr><td colspan="9" class="p-8 text-center text-slate-600">No categories found. Click Add Category to start.</td></tr>
                            <?php else: ?>
                                <?php foreach ($categories as $idx => $c): 
                                    $slug = $c['slug'];
                                    $v_count = (int)$c['total_views'];
                                    $comp_count = (int)$c['completed_views'];
                                    $comp_rate = $v_count > 0 ? round(($comp_count / $v_count) * 100, 1) : 0;
                                    $rev_paise = $category_revenue[$slug] ?? 0;
                                    $rev = $rev_paise / 100;
                                ?>
                                    <tr class="hover:bg-slate-900/20 transition drag-row cursor-move" draggable="true" data-id="<?= $c['id'] ?>">
                                        <!-- Reorder order / display -->
                                        <td class="p-4 text-center">
                                            <div class="flex flex-col items-center gap-0.5 select-none">
                                                <span class="text-xs text-slate-500 cursor-grab" title="Drag to reorder">☰</span>
                                                <span class="text-[10px] text-slate-500 font-mono order-num"><?= $c['display_order'] ?></span>
                                                <div class="flex gap-1">
                                                    <a href="categories.php?action=reorder&dir=up&id=<?= $c['id'] ?>" class="text-[9px] hover:text-white transition block" title="Move Up">▲</a>
                                                    <a href="categories.php?action=reorder&dir=down&id=<?= $c['id'] ?>" class="text-[9px] hover:text-white transition block" title="Move Down">▼</a>
                                                </div>
                                            </div>
                                        </td>
                                        <!-- Category name -->
                                        <td class="p-4">
                                            <div class="flex items-center gap-3">
                                                <div>
                                                    <div class="flex items-center gap-1.5">
                                                        <span class="font-bold text-white"><?= h($c['name']) ?></span>
                                                        <?php if ($c['featured']): ?>
                                                            <span class="bg-pink-500/10 border border-pink-500/30 text-pink-400 text-[9px] font-extrabold px-1.5 py-0.5 rounded uppercase">⭐ Featured</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <span class="block text-[10px] text-slate-500 font-mono">/create-page.php?category=<?= h($slug) ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <!-- Category Icon -->
                                        <td class="p-4 text-2xl text-center"><?= $c['icon'] ?></td>
                                        <!-- Status Badge -->
                                        <td class="p-4">
                                            <?php 
                                            $st = $c['status'] ?? 'enabled';
                                            if ($st === 'enabled') {
                                                $badge_style = 'bg-green-500/10 text-green-400 border border-green-500/20';
                                            } elseif ($st === 'disabled') {
                                                $badge_style = 'bg-slate-500/10 text-slate-400 border border-slate-500/20';
                                            } elseif ($st === 'hidden') {
                                                $badge_style = 'bg-purple-500/10 text-purple-400 border border-purple-500/20';
                                            } else { // maintenance
                                                $badge_style = 'bg-amber-500/10 text-amber-400 border border-amber-500/20 animate-pulse';
                                            }
                                            ?>
                                            <span class="px-2.5 py-1 rounded-full text-[9px] font-extrabold uppercase tracking-wide <?= $badge_style ?>"><?= h($st) ?></span>
                                        </td>
                                        <!-- Stats columns -->
                                        <td class="p-4 font-bold text-pink-400 text-xs text-center"><?= number_format($c['total_pages']) ?></td>
                                        <td class="p-4 font-bold text-pink-400 text-xs text-center"><?= number_format($c['total_pages']) ?></td>
                                        <td class="p-4 text-xs">
                                            <div class="space-y-0.5">
                                                <span class="text-slate-300 font-semibold capitalize"><?= h($c['theme']) ?></span>
                                                <span class="block text-[9px] text-slate-500"><?= h($c['font'] ?: 'Outfit') ?></span>
                                            </div>
                                        </td>
                                        <td class="p-4 text-xs text-center text-emerald-400 font-bold">₹<?= number_format($rev) ?></td>
                                        <!-- Actions -->
                                        <td class="p-4 text-right">
                                            <div class="flex items-center justify-end gap-1.5">
                                                <a href="categories.php?action=analytics&id=<?= $c['id'] ?>" class="text-[10px] bg-blue-500/10 hover:bg-blue-500/20 text-blue-400 px-2 py-1.5 rounded-lg transition" title="Analytics">📊 Analytics</a>
                                                
                                                <?php if ($st !== 'enabled'): ?>
                                                    <a href="categories.php?action=toggle_status&id=<?= $c['id'] ?>&status=enabled" class="text-[10px] bg-green-500/10 hover:bg-green-500/20 text-green-400 px-2.5 py-1.5 rounded-lg transition font-bold" title="Enable">Enable</a>
                                                <?php else: ?>
                                                    <a href="categories.php?action=toggle_status&id=<?= $c['id'] ?>&status=disabled" class="text-[10px] bg-slate-500/10 hover:bg-slate-500/20 text-slate-400 px-2.5 py-1.5 rounded-lg transition font-bold" title="Disable">Disable</a>
                                                <?php endif; ?>

                                                <a href="categories.php?action=edit&id=<?= $c['id'] ?>" class="text-[10px] bg-pink-500/10 hover:bg-pink-500/20 text-pink-400 px-2 py-1.5 rounded-lg transition font-bold" title="Edit">✏️ Edit</a>
                                                
                                                <!-- Dropdown or quick toggles for status -->
                                                <select onchange="window.location.href='categories.php?action=toggle_status&id=<?= $c['id'] ?>&status=' + this.value" class="bg-slate-900 border border-slate-800 text-[10px] text-slate-300 rounded px-1.5 py-1 outline-none cursor-pointer">
                                                    <option value="" disabled selected>More...</option>
                                                    <option value="enabled" <?= $st === 'enabled' ? 'selected' : '' ?>>Enable</option>
                                                    <option value="disabled" <?= $st === 'disabled' ? 'selected' : '' ?>>Disable</option>
                                                    <option value="hidden" <?= $st === 'hidden' ? 'selected' : '' ?>>Hide</option>
                                                    <option value="maintenance" <?= $st === 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
                                                </select>
 
                                                <button onclick="if(confirm('Are you sure you want to delete this category? All configuration will be lost.')) window.location.href='categories.php?action=delete&id=<?= $c['id'] ?>'" class="text-[10px] bg-red-500/10 hover:bg-red-500/20 text-red-400 px-2 py-1.5 rounded-lg transition font-bold" title="Delete">🗑️ Delete</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Drag & Drop Dragrow Script -->
            <script>
                let dragSrcEl = null;

                function handleDragStart(e) {
                    this.style.opacity = '0.4';
                    dragSrcEl = this;
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('text/html', this.innerHTML);
                }

                function handleDragOver(e) {
                    if (e.preventDefault) {
                        e.preventDefault();
                    }
                    e.dataTransfer.dropEffect = 'move';
                    return false;
                }

                function handleDragEnter(e) {
                    this.classList.add('bg-slate-900/60');
                }

                function handleDragLeave(e) {
                    this.classList.remove('bg-slate-900/60');
                }

                function handleDrop(e) {
                    if (e.stopPropagation) {
                        e.stopPropagation();
                    }
                    
                    if (dragSrcEl !== this) {
                        // Reorder rows in DOM
                        const rect = this.getBoundingClientRect();
                        const next = (e.clientY - rect.top) / (rect.bottom - rect.top) > 0.5;
                        this.parentNode.insertBefore(dragSrcEl, next ? this.nextSibling : this);
                        
                        // Save order to database
                        saveNewOrder();
                    }
                    return false;
                }

                function handleDragEnd(e) {
                    this.style.opacity = '1.0';
                    let items = document.querySelectorAll('.drag-row');
                    items.forEach(function (item) {
                        item.classList.remove('bg-slate-900/60');
                    });
                }

                function saveNewOrder() {
                    let items = document.querySelectorAll('.drag-row');
                    let ids = [];
                    items.forEach(function(item) {
                        ids.push(item.getAttribute('data-id'));
                    });
                    
                    fetch('categories.php?action=save_order', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ ids: ids })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update display order numbers
                            items.forEach((item, index) => {
                                let orderSpan = item.querySelector('.order-num');
                                if (orderSpan) {
                                    orderSpan.textContent = index + 1;
                                }
                            });
                        } else {
                            console.error('Order save failed:', data.error);
                        }
                    });
                }

                function addDragAndDropHandlers() {
                    let items = document.querySelectorAll('.drag-row');
                    items.forEach(function(item) {
                        item.addEventListener('dragstart', handleDragStart, false);
                        item.addEventListener('dragenter', handleDragEnter, false);
                        item.addEventListener('dragover', handleDragOver, false);
                        item.addEventListener('dragleave', handleDragLeave, false);
                        item.addEventListener('drop', handleDrop, false);
                        item.addEventListener('dragend', handleDragEnd, false);
                    });
                }

                document.addEventListener('DOMContentLoaded', addDragAndDropHandlers);
            </script>
        <?php endif; ?>

    </main>

    <!-- Footer -->
    <footer class="bg-slate-950 border-t border-slate-900 py-6 text-center text-slate-600 text-xs">
        &copy; <?= date('Y') ?> SoulSync Admin.
    </footer>

</body>
</html>
