<?php
require_once '../includes/functions.php';

if (!is_logged_in() || !is_admin()) {
    redirect('login.php');
}

// Stats
$total_revenue = 0;
$rev_today = 0;
$rev_month = 0;
$success_count = 0;
$failed_count = 0;
$payments = [];

try {
    $total_revenue = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'captured'")->fetchColumn();
    $rev_today = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE DATE(created_at) = CURDATE() AND status = 'captured'")->fetchColumn();
    $rev_month = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) AND status = 'captured'")->fetchColumn();
    $success_count = $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'captured'")->fetchColumn();
    $failed_count = $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'failed'")->fetchColumn();

    // Fetch payments
    $search = trim($_GET['search'] ?? '');
    $status_filter = $_GET['status'] ?? '';
    $params = [];

    $query = "SELECT py.*, u.name as user_name, u.email as user_email 
        FROM payments py 
        LEFT JOIN users u ON py.user_id = u.id 
        WHERE 1=1";

    if (!empty($search)) {
        $query .= " AND (u.name LIKE ? OR u.email LIKE ? OR py.razorpay_payment_id LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    if (!empty($status_filter)) {
        $query .= " AND py.status = ?";
        $params[] = $status_filter;
    }

    $query .= " ORDER BY py.created_at DESC LIMIT 100";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $payments = $stmt->fetchAll();
} catch (PDOException $e) {
    // payments table doesn't exist yet
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments - SoulSync Admin</title>
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

    <?php $ADMIN_TITLE='Payments'; include '_nav.php'; ?>

    <main class="flex-grow max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <h2 class="text-xl font-extrabold font-heading text-white mb-6">Payment Management</h2>

        <!-- Revenue Stats -->
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-6">
            <div class="glass-card rounded-2xl p-4">
                <p class="text-[10px] text-slate-500 uppercase tracking-wider font-semibold mb-1">Total Revenue</p>
                <p class="text-xl font-extrabold font-heading text-green-400">₹<?= number_format($total_revenue / 100) ?></p>
            </div>
            <div class="glass-card rounded-2xl p-4">
                <p class="text-[10px] text-slate-500 uppercase tracking-wider font-semibold mb-1">Today</p>
                <p class="text-xl font-extrabold font-heading text-emerald-400">₹<?= number_format($rev_today / 100) ?></p>
            </div>
            <div class="glass-card rounded-2xl p-4">
                <p class="text-[10px] text-slate-500 uppercase tracking-wider font-semibold mb-1">This Month</p>
                <p class="text-xl font-extrabold font-heading text-blue-400">₹<?= number_format($rev_month / 100) ?></p>
            </div>
            <div class="glass-card rounded-2xl p-4">
                <p class="text-[10px] text-slate-500 uppercase tracking-wider font-semibold mb-1">Successful</p>
                <p class="text-xl font-extrabold font-heading text-green-400"><?= number_format($success_count) ?></p>
            </div>
            <div class="glass-card rounded-2xl p-4">
                <p class="text-[10px] text-slate-500 uppercase tracking-wider font-semibold mb-1">Failed</p>
                <p class="text-xl font-extrabold font-heading text-red-400"><?= number_format($failed_count) ?></p>
            </div>
        </div>

        <!-- Filters -->
        <form class="flex flex-wrap gap-3 mb-6" method="GET">
            <input type="text" name="search" value="<?= h($search) ?>" placeholder="Search user or payment ID..." class="bg-slate-900 border border-slate-800 focus:border-pink-500 rounded-xl px-4 py-2 text-sm outline-none text-white flex-grow max-w-xs">
            <select name="status" class="bg-slate-900 border border-slate-800 rounded-xl px-4 py-2 text-sm outline-none text-white">
                <option value="">All Status</option>
                <option value="captured" <?= $status_filter === 'captured' ? 'selected' : '' ?>>Captured</option>
                <option value="created" <?= $status_filter === 'created' ? 'selected' : '' ?>>Created</option>
                <option value="failed" <?= $status_filter === 'failed' ? 'selected' : '' ?>>Failed</option>
            </select>
            <button type="submit" class="bg-pink-500 hover:bg-pink-600 text-white font-bold text-xs px-5 py-2.5 rounded-xl transition">Filter</button>
        </form>

        <!-- Transactions Table -->
        <div class="glass-card rounded-3xl p-6 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-400">
                    <thead class="bg-slate-900/80 text-xs text-slate-500 uppercase tracking-wider">
                        <tr>
                            <th class="p-4 rounded-l-xl">User</th>
                            <th class="p-4">Order ID</th>
                            <th class="p-4">Payment ID</th>
                            <th class="p-4">Amount</th>
                            <th class="p-4">Status</th>
                            <th class="p-4 rounded-r-xl">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-900">
                        <?php if (empty($payments)): ?>
                            <tr><td colspan="6" class="p-8 text-center text-slate-600">No payments found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($payments as $pay): ?>
                                <tr class="hover:bg-slate-900/20 transition">
                                    <td class="p-4 font-semibold text-white text-xs"><?= h($pay['user_name'] ?? 'Unknown') ?></td>
                                    <td class="p-4 text-xs font-mono"><?= h($pay['razorpay_order_id'] ?? '-') ?></td>
                                    <td class="p-4 text-xs font-mono"><?= h($pay['razorpay_payment_id'] ?? '-') ?></td>
                                    <td class="p-4 font-bold text-green-400">₹<?= number_format($pay['amount'] / 100) ?></td>
                                    <td class="p-4">
                                        <?php
                                        $status_classes = [
                                            'captured' => 'bg-green-500/10 text-green-400',
                                            'created' => 'bg-yellow-500/10 text-yellow-400',
                                            'failed' => 'bg-red-500/10 text-red-400',
                                        ];
                                        $sc = $status_classes[$pay['status']] ?? 'bg-slate-500/10 text-slate-400';
                                        ?>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase <?= $sc ?>"><?= h($pay['status']) ?></span>
                                    </td>
                                    <td class="p-4 text-xs"><?= date('M d, Y h:i A', strtotime($pay['created_at'])) ?></td>
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
</body>
</html>
