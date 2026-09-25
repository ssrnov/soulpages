<?php
require_once '../includes/functions.php';

if (!is_logged_in() || !is_admin()) {
    redirect('login.php');
}

$type_filter = $_GET['type'] ?? '';
$params = [];

$query = "SELECT pr.*, p.title as page_title, p.slug as page_slug 
    FROM page_replies pr 
    JOIN pages p ON pr.page_id = p.id 
    WHERE 1=1";

if (!empty($type_filter)) {
    $query .= " AND pr.reply_type = ?";
    $params[] = $type_filter;
}

$query .= " ORDER BY pr.created_at DESC LIMIT 100";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$replies = $stmt->fetchAll();

$total_replies = $pdo->query("SELECT COUNT(*) FROM page_replies")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reply Moderation - SoulSync Admin</title>
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

    <?php $ADMIN_TITLE='Replies'; include '_nav.php'; ?>

    <main class="flex-grow max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <h2 class="text-xl font-extrabold font-heading text-white">Reply Moderation (<?= $total_replies ?> total)</h2>
            <form class="flex gap-2" method="GET">
                <select name="type" class="bg-slate-900 border border-slate-800 rounded-xl px-4 py-2 text-sm outline-none text-white">
                    <option value="">All Types</option>
                    <option value="text" <?= $type_filter === 'text' ? 'selected' : '' ?>>📝 Text</option>
                    <option value="emoji" <?= $type_filter === 'emoji' ? 'selected' : '' ?>>😊 Emoji</option>
                    <option value="voice" <?= $type_filter === 'voice' ? 'selected' : '' ?>>🎤 Voice</option>
                    <option value="image" <?= $type_filter === 'image' ? 'selected' : '' ?>>🖼️ Image</option>
                    <option value="video" <?= $type_filter === 'video' ? 'selected' : '' ?>>📹 Video</option>
                </select>
                <button type="submit" class="bg-pink-500 hover:bg-pink-600 text-white font-bold text-xs px-5 py-2.5 rounded-xl transition">Filter</button>
            </form>
        </div>

        <div class="space-y-3">
            <?php if (empty($replies)): ?>
                <div class="glass-card rounded-2xl p-12 text-center">
                    <p class="text-slate-600">No replies found.</p>
                </div>
            <?php else: ?>
                <?php foreach ($replies as $r): ?>
                    <?php
                    $typeIcons = ['text' => '📝', 'emoji' => '😊', 'voice' => '🎤', 'image' => '🖼️', 'video' => '📹'];
                    $icon = $typeIcons[$r['reply_type']] ?? '💬';
                    ?>
                    <div class="glass-card rounded-2xl p-4 flex items-start gap-4" id="reply-<?= $r['id'] ?>">
                        <span class="text-xl flex-shrink-0"><?= $icon ?></span>
                        <div class="flex-grow min-w-0">
                            <div class="flex items-center gap-3 mb-1">
                                <span class="text-sm font-semibold text-white"><?= h($r['visitor_name'] ?? 'Anonymous') ?></span>
                                <span class="text-[10px] text-slate-600">on</span>
                                <?php if (is_super_admin()): ?><a href="../p.php?s=<?= h($r['page_slug']) ?>" target="_blank" class="text-xs text-pink-400 hover:text-pink-300 transition truncate"><?= h($r['page_title']) ?></a><?php else: ?><span class="text-xs text-slate-400 truncate"><?= h($r['page_title']) ?></span><?php endif; ?>
                                <span class="text-[10px] text-slate-600 ml-auto flex-shrink-0"><?= date('M d, Y h:i A', strtotime($r['created_at'])) ?></span>
                            </div>
                            <?php if (!empty($r['message'])): ?>
                                <p class="text-sm text-slate-300 mb-2"><?= h($r['message']) ?></p>
                            <?php endif; ?>
                            <?php if ($r['reply_type'] === 'voice' && !empty($r['voice_path'])): ?>
                                <audio controls class="h-8 w-full max-w-xs" src="../<?= h($r['voice_path']) ?>"></audio>
                            <?php elseif ($r['reply_type'] === 'image' && !empty($r['image_path'])): ?>
                                <a href="../<?= h($r['image_path']) ?>" target="_blank">
                                    <img src="../<?= h($r['image_path']) ?>" class="w-20 h-20 object-cover rounded-lg">
                                </a>
                            <?php elseif ($r['reply_type'] === 'video' && !empty($r['video_path'])): ?>
                                <video controls class="w-full max-w-xs rounded-lg h-32" src="../<?= h($r['video_path']) ?>"></video>
                            <?php endif; ?>
                        </div>
                        <button onclick="deleteReply(<?= $r['id'] ?>)" class="text-red-500/50 hover:text-red-400 text-sm flex-shrink-0 transition" title="Delete">🗑️</button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <footer class="bg-slate-950 border-t border-slate-900 py-6 text-center text-slate-600 text-xs">
        &copy; <?= date('Y') ?> SoulSync Admin.
    </footer>

    <script>
    function deleteReply(replyId) {
        if (!confirm('Delete this reply?')) return;
        fetch('../api.php?action=delete_reply', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'reply_id=' + replyId
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('reply-' + replyId).remove();
            } else {
                alert(data.error || 'Failed.');
            }
        });
    }
    </script>
<script src="../assets/js/aac-playback-fix.js"></script>
</body>
</html>

