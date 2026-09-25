<?php
require_once '../includes/functions.php';

if (!is_logged_in() || !is_admin()) {
    redirect('login.php');
}

// Promote / demote — Super Admin only
$role_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_role'])) {
    if (!is_super_admin()) {
        $role_msg = 'Only a Super Admin can change roles.';
    } elseif (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $role_msg = 'Session expired. Please try again.';
    } else {
        $target = (int)$_POST['user_id'];
        $new_role = $_POST['set_role'];
        // Managers can be created/removed; never touch a Super Admin from here.
        if (in_array($new_role, ['user', 'manager'], true) && $target !== (int)$_SESSION['user_id']) {
            $cur = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $cur->execute([$target]);
            if ($cur->fetchColumn() !== 'admin') {
                $pdo->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$new_role, $target]);
                $role_msg = $new_role === 'manager' ? 'User promoted to Manager ✅' : 'Manager changed back to User.';
            } else {
                $role_msg = 'Cannot change another Super Admin here.';
            }
        }
    }
}
$csrf = generate_csrf_token();

$search = trim($_GET['search'] ?? '');
$params = [];

// Check if payments table exists
$payments_table_exists = false;
try {
    $pdo->query("SELECT 1 FROM `payments` LIMIT 1");
    $payments_table_exists = true;
} catch (PDOException $e) {}

$total_spent_select = $payments_table_exists 
    ? "COALESCE((SELECT SUM(amount) FROM payments WHERE user_id = u.id AND status = 'captured'), 0) as total_spent"
    : "0 as total_spent";

$query = "SELECT u.*, 
    COUNT(DISTINCT p.id) as page_count,
    u.credits as credit_count,
    $total_spent_select
    FROM users u
    LEFT JOIN pages p ON u.id = p.user_id
    WHERE 1=1";

if (!empty($search)) {
    $query .= " AND (u.name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " GROUP BY u.id ORDER BY u.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$all_users = $stmt->fetchAll();

// Split staff (Managers + Super Admins) from regular users
$staff = array_values(array_filter($all_users, fn($u) => in_array($u['role'] ?? 'user', ['manager', 'admin'], true)));
$users = array_values(array_filter($all_users, fn($u) => ($u['role'] ?? 'user') === 'user'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - SoulSync Admin</title>
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

    <?php $ADMIN_TITLE='Users'; include '_nav.php'; ?>

    <main class="flex-grow max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-10">

        <?php if ($role_msg): ?><div class="mb-5 rounded-2xl p-4 text-sm bg-slate-900/50" style="border:1px solid #eef0f4;"><?= h($role_msg) ?></div><?php endif; ?>

        <!-- STAFF: Managers & Super Admins -->
        <div class="glass-card rounded-3xl p-6 mb-8">
            <h2 class="text-lg font-extrabold font-heading text-white mb-1">👑 Staff — Managers &amp; Admins (<?= count($staff) ?>)</h2>
            <p class="text-xs text-slate-500 mb-4">Managers get <b>unlimited page creation</b> and can <b>see</b> the pages list, but <b>cannot open</b> users' pages. Only a Super Admin can open pages &amp; change roles.</p>
            <div class="space-y-2">
                <?php foreach ($staff as $u): $is_super = ($u['role'] === 'admin'); ?>
                <div class="flex items-center gap-3 py-2.5 border-b border-slate-900/50 last:border-0 flex-wrap">
                    <div class="w-9 h-9 rounded-full flex items-center justify-center text-white font-bold text-sm" style="background:linear-gradient(135deg,<?= $is_super ? '#7c3aed,#4f46e5' : '#f43f5e,#db2777' ?>);"><?= h(mb_strtoupper(mb_substr($u['name'] ?: 'U', 0, 1))) ?></div>
                    <div class="min-w-0">
                        <p class="font-semibold text-white"><?= h($u['name']) ?>
                            <span class="ml-1 text-[10px] font-bold uppercase px-2 py-0.5 rounded-full <?= $is_super ? 'bg-indigo-500/15 text-indigo-300' : 'bg-pink-500/15 text-pink-300' ?>"><?= $is_super ? '👑 Super Admin' : '🛠️ Manager' ?></span>
                        </p>
                        <p class="text-xs text-slate-500 truncate"><?= h($u['email']) ?> · <?= $u['page_count'] ?> pages</p>
                    </div>
                    <?php if (is_super_admin() && !$is_super && (int)$u['id'] !== (int)$_SESSION['user_id']): ?>
                    <form method="POST" class="ml-auto">
                        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>"><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                        <button name="set_role" value="user" onclick="return confirm('Remove Manager role from this user?')" class="text-[11px] font-bold bg-slate-800 text-slate-300 px-3 py-1.5 rounded-lg hover:bg-slate-700">↓ Make User</button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php if (empty($staff)): ?><p class="text-sm text-slate-600 text-center py-3">No staff yet.</p><?php endif; ?>
            </div>
        </div>

        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <h2 class="text-xl font-extrabold font-heading text-white">Registered Users (<?= count($users) ?>)</h2>
            <form action="users.php" method="GET" class="flex max-w-sm w-full">
                <input type="text" name="search" value="<?= h($search) ?>" placeholder="Search by name or email..." class="w-full bg-slate-900 border border-slate-800 focus:border-pink-500 focus:ring-1 focus:ring-pink-500 rounded-l-xl px-4 py-2 text-sm outline-none text-white">
                <button type="submit" class="bg-pink-500 hover:bg-pink-600 text-white font-bold text-xs px-5 py-2.5 rounded-r-xl transition">Search</button>
            </form>
        </div>

        <div class="glass-card rounded-3xl p-6 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-400">
                    <thead class="bg-slate-900/80 text-xs text-slate-500 uppercase tracking-wider">
                        <tr>
                            <th class="p-4 rounded-l-xl">User</th>
                            <th class="p-4">Email</th>
                            <th class="p-4">Pages</th>
                            <th class="p-4">Credits</th>
                            <th class="p-4">Spent</th>
                            <th class="p-4">Status</th>
                            <th class="p-4">Joined</th>
                            <th class="p-4 rounded-r-xl">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-900">
                        <?php if (count($users) === 0): ?>
                            <tr><td colspan="8" class="p-8 text-center text-slate-600">No users found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($users as $u): ?>
                                <tr class="hover:bg-slate-900/20 transition" id="user-row-<?= $u['id'] ?>">
                                    <td class="p-4">
                                        <div class="flex items-center gap-2">
                                            <?php if (!empty($u['profile_photo'])): ?>
                                                <img src="<?= h($u['profile_photo']) ?>" class="w-7 h-7 rounded-full" alt="">
                                            <?php else: ?>
                                                <div class="w-7 h-7 rounded-full bg-slate-800 flex items-center justify-center text-xs">👤</div>
                                            <?php endif; ?>
                                            <span class="font-semibold text-white"><?= h($u['name']) ?></span>
                                        </div>
                                    </td>
                                    <td class="p-4 text-xs"><?= h($u['email']) ?></td>
                                    <td class="p-4 font-bold text-pink-400"><?= $u['page_count'] ?></td>
                                    <td class="p-4 font-bold text-green-400"><?= $u['credit_count'] ?></td>
                                    <td class="p-4 text-xs">₹<?= number_format($u['total_spent'] / 100) ?></td>
                                    <td class="p-4">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase <?= ($u['status'] ?? 'active') === 'active' ? 'bg-green-500/10 text-green-400' : 'bg-red-500/10 text-red-400' ?>"><?= h($u['status'] ?? 'active') ?></span>
                                    </td>
                                    <td class="p-4 text-xs"><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
                                    <td class="p-4">
                                        <div class="flex items-center gap-1">
                                            <?php if (is_super_admin()): ?>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>"><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                                <button name="set_role" value="manager" onclick="return confirm('Promote <?= h(addslashes($u['name'])) ?> to Manager? They will get unlimited page creation.')" class="text-[10px] bg-purple-500/10 hover:bg-purple-500/20 text-purple-300 px-2 py-1 rounded-lg transition" title="Make Manager">🛠️ Manager</button>
                                            </form>
                                            <?php endif; ?>
                                            <button onclick="giveCredits(<?= $u['id'] ?>)" class="text-[10px] bg-green-500/10 hover:bg-green-500/20 text-green-400 px-2 py-1 rounded-lg transition" title="Give Credits">💳</button>
                                            <?php if (($u['status'] ?? 'active') === 'active'): ?>
                                                <button onclick="toggleStatus(<?= $u['id'] ?>, 'suspended')" class="text-[10px] bg-yellow-500/10 hover:bg-yellow-500/20 text-yellow-400 px-2 py-1 rounded-lg transition" title="Suspend">🚫</button>
                                            <?php else: ?>
                                                <button onclick="toggleStatus(<?= $u['id'] ?>, 'active')" class="text-[10px] bg-blue-500/10 hover:bg-blue-500/20 text-blue-400 px-2 py-1 rounded-lg transition" title="Activate">✅</button>
                                            <?php endif; ?>
                                            <button onclick="deleteUser(<?= $u['id'] ?>)" class="text-[10px] bg-red-500/10 hover:bg-red-500/20 text-red-400 px-2 py-1 rounded-lg transition" title="Delete">🗑️</button>
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

    <footer class="bg-slate-950 border-t border-slate-900 py-6 text-center text-slate-600 text-xs">
        &copy; <?= date('Y') ?> SoulSync Admin.
    </footer>

    <script>
    function giveCredits(userId) {
        const credits = prompt('Enter number of credits to give:');
        if (!credits || isNaN(credits) || credits < 1) return;
        fetch('../api.php?action=admin_give_credits', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'user_id=' + userId + '&credits=' + credits
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('Credits added!');
                location.reload();
            } else {
                alert(data.error || 'Failed.');
            }
        });
    }

    function toggleStatus(userId, newStatus) {
        const action = newStatus === 'suspended' ? 'suspend' : 'activate';
        if (!confirm('Are you sure you want to ' + action + ' this user?')) return;
        fetch('../api.php?action=admin_toggle_user_status', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'user_id=' + userId + '&status=' + newStatus
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) location.reload();
            else alert(data.error || 'Failed.');
        });
    }

    function deleteUser(userId) {
        if (!confirm('DELETE this user and ALL their pages? This cannot be undone!')) return;
        if (!confirm('Are you ABSOLUTELY sure?')) return;
        fetch('../api.php?action=admin_delete_user', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'user_id=' + userId
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('user-row-' + userId).remove();
            } else {
                alert(data.error || 'Failed.');
            }
        });
    }
    </script>
</body>
</html>
