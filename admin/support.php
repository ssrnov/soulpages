<?php
require_once __DIR__ . '/../includes/functions.php';
if (!is_admin()) { header('Location: login.php'); exit; }

// Auto-create table
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS support_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(150) NOT NULL,
        subject VARCHAR(200) NOT NULL DEFAULT 'General',
        message TEXT NOT NULL,
        status ENUM('open','replied','closed') DEFAULT 'open',
        admin_reply TEXT NULL,
        replied_by INT NULL,
        replied_at DATETIME NULL,
        ip VARCHAR(45),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX(user_id), INDEX(status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (\Throwable $e) {}

$flash = '';

// Handle reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $flash = 'Invalid CSRF token.';
    } elseif ($_POST['action'] === 'reply') {
        $mid = (int)($_POST['msg_id'] ?? 0);
        $reply = trim($_POST['reply'] ?? '');
        if ($mid > 0 && $reply !== '') {
            $pdo->prepare("UPDATE support_messages SET admin_reply=?, status='replied', replied_by=?, replied_at=NOW() WHERE id=?")
                ->execute([$reply, $_SESSION['user_id'], $mid]);
            $flash = 'Reply sent!';
        }
    } elseif ($_POST['action'] === 'close') {
        $mid = (int)($_POST['msg_id'] ?? 0);
        if ($mid > 0) {
            $pdo->prepare("UPDATE support_messages SET status='closed' WHERE id=?")->execute([$mid]);
            $flash = 'Ticket closed.';
        }
    } elseif ($_POST['action'] === 'delete') {
        $mid = (int)($_POST['msg_id'] ?? 0);
        if ($mid > 0) {
            $pdo->prepare("DELETE FROM support_messages WHERE id=?")->execute([$mid]);
            $flash = 'Message deleted.';
        }
    }
}

$filter = $_GET['status'] ?? 'all';
$where = $filter !== 'all' ? "WHERE status=" . $pdo->quote($filter) : "";
$messages = $pdo->query("SELECT * FROM support_messages $where ORDER BY FIELD(status,'open','replied','closed'), created_at DESC LIMIT 100")->fetchAll();
$counts = $pdo->query("SELECT status, COUNT(*) c FROM support_messages GROUP BY status")->fetchAll();
$cMap = [];
foreach ($counts as $c) $cMap[$c['status']] = (int)$c['c'];
$total = array_sum($cMap);

$csrf = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Support Messages - Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<style>
body{font-family:'Inter',sans-serif;background:#f8fafc;color:#1e293b;min-height:100vh}
.wrap{max-width:1000px;margin:0 auto;padding:30px 20px}
</style>
</head>
<body>
<div class="wrap">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-extrabold">💬 Support Messages</h1>
            <p class="text-sm text-slate-500 mt-1">Total: <?= $total ?> · Open: <?= $cMap['open'] ?? 0 ?> · Replied: <?= $cMap['replied'] ?? 0 ?> · Closed: <?= $cMap['closed'] ?? 0 ?></p>
        </div>
        <a href="../admin/index.php" class="text-sm text-pink-600 hover:underline">← Back to Admin</a>
    </div>

    <?php if ($flash): ?>
    <div class="bg-green-50 border border-green-200 text-green-700 rounded-xl p-3 mb-4 text-sm font-medium"><?= h($flash) ?></div>
    <?php endif; ?>

    <div class="flex gap-2 mb-5">
        <?php foreach (['all'=>'All','open'=>'Open','replied'=>'Replied','closed'=>'Closed'] as $k => $v): ?>
        <a href="?status=<?= $k ?>" class="px-4 py-2 rounded-xl text-sm font-semibold <?= $filter === $k ? 'bg-pink-600 text-white' : 'bg-white border border-slate-200 text-slate-600 hover:border-pink-300' ?>"><?= $v ?><?= $k !== 'all' ? ' (' . ($cMap[$k] ?? 0) . ')' : '' ?></a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($messages)): ?>
    <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center text-slate-400">No messages found.</div>
    <?php endif; ?>

    <?php foreach ($messages as $m):
        $statusColor = match($m['status']){'open'=>'bg-orange-100 text-orange-600','replied'=>'bg-green-100 text-green-600','closed'=>'bg-slate-100 text-slate-500',default=>'bg-slate-100 text-slate-500'};
    ?>
    <div class="bg-white rounded-2xl border border-slate-200 p-5 mb-4 shadow-sm">
        <div class="flex items-start justify-between mb-3">
            <div>
                <span class="font-bold text-base"><?= h($m['name']) ?></span>
                <span class="text-slate-400 text-sm ml-2"><?= h($m['email']) ?></span>
                <?php if ($m['user_id']): ?><span class="text-xs text-purple-500 ml-1">(User #<?= (int)$m['user_id'] ?>)</span><?php endif; ?>
            </div>
            <div class="flex items-center gap-2">
                <span class="px-3 py-1 rounded-full text-xs font-bold <?= $statusColor ?>"><?= ucfirst($m['status']) ?></span>
                <span class="text-xs text-slate-400"><?= date('d M Y H:i', strtotime($m['created_at'])) ?></span>
            </div>
        </div>

        <div class="text-xs text-slate-400 font-semibold uppercase mb-1"><?= h($m['subject']) ?></div>
        <div class="text-sm text-slate-700 leading-relaxed mb-4 whitespace-pre-line"><?= h($m['message']) ?></div>

        <?php if (!empty($m['admin_reply'])): ?>
        <div class="bg-purple-50 border border-purple-100 rounded-xl p-4 mb-3">
            <div class="text-xs text-purple-500 font-bold uppercase mb-1">Your Reply</div>
            <div class="text-sm text-purple-900 whitespace-pre-line"><?= h($m['admin_reply']) ?></div>
            <div class="text-xs text-purple-400 mt-2"><?= $m['replied_at'] ? date('d M Y H:i', strtotime($m['replied_at'])) : '' ?></div>
        </div>
        <?php endif; ?>

        <div class="flex gap-2 items-end flex-wrap">
            <?php if ($m['status'] !== 'closed'): ?>
            <form method="POST" class="flex-1 min-w-[250px]">
                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                <input type="hidden" name="action" value="reply">
                <input type="hidden" name="msg_id" value="<?= (int)$m['id'] ?>">
                <textarea name="reply" required placeholder="Type your reply..." class="w-full border border-slate-200 rounded-xl p-3 text-sm resize-none h-20 focus:border-pink-400 focus:outline-none mb-2"><?= h($m['admin_reply'] ?? '') ?></textarea>
                <button type="submit" class="bg-pink-600 text-white px-5 py-2 rounded-xl text-sm font-bold hover:bg-pink-700 transition">Reply</button>
            </form>
            <?php endif; ?>

            <div class="flex gap-2">
                <?php if ($m['status'] !== 'closed'): ?>
                <form method="POST" class="inline">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                    <input type="hidden" name="action" value="close">
                    <input type="hidden" name="msg_id" value="<?= (int)$m['id'] ?>">
                    <button type="submit" class="border border-slate-200 text-slate-500 px-4 py-2 rounded-xl text-sm hover:bg-slate-50">Close</button>
                </form>
                <?php endif; ?>
                <form method="POST" class="inline" onsubmit="return confirm('Delete this message?')">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="msg_id" value="<?= (int)$m['id'] ?>">
                    <button type="submit" class="border border-red-200 text-red-400 px-4 py-2 rounded-xl text-sm hover:bg-red-50">Delete</button>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
</body>
</html>
