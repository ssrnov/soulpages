<?php
require_once __DIR__ . '/_boot.php';
ss_require_owner();
$PAGE_TITLE = 'Tracking Viewers';
$PAGE_SUB   = 'Grant users permission to view another user\'s tracking data';
$PAGE_ICON  = '👁️';

$flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $vid = (int)($_POST['viewer_id'] ?? 0);
        $tid = (int)($_POST['target_id'] ?? 0);
        if ($vid > 0 && $tid > 0 && $vid !== $tid) {
            try {
                $pdo->prepare("INSERT IGNORE INTO tracking_viewers (viewer_id, target_id, granted_by) VALUES (?, ?, ?)")
                    ->execute([$vid, $tid, $_SESSION['user_id'] ?? 0]);
                $flash = 'Access granted.';
            } catch (PDOException $e) {
                $flash = 'Error: ' . $e->getMessage();
            }
        } else {
            $flash = 'Invalid selection. Viewer and target must be different users.';
        }
    } elseif ($_POST['action'] === 'remove') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare("DELETE FROM tracking_viewers WHERE id = ?")->execute([$id]);
            $flash = 'Access revoked.';
        }
    }
}

$viewers = $pdo->query("
    SELECT tv.id, tv.created_at,
           v.id AS vid, v.username AS v_user, v.name AS v_name, v.email AS v_email,
           t.id AS tid, t.username AS t_user, t.name AS t_name
    FROM tracking_viewers tv
    JOIN users v ON v.id = tv.viewer_id
    JOIN users t ON t.id = tv.target_id
    ORDER BY tv.created_at DESC
")->fetchAll();

$users = $pdo->query("SELECT id, username, name, email FROM users WHERE role='user' ORDER BY name")->fetchAll();

require __DIR__ . '/_dark_head.php';
?>
<?php if ($flash): ?><div class="flash ok" style="margin-bottom:14px;padding:10px 14px;border-radius:10px;background:rgba(34,197,94,.12);color:#4ade80;font-size:.85rem"><?= ss_admin_h($flash) ?></div><?php endif; ?>

<div class="panel" style="padding:20px;margin-bottom:20px">
    <h3 style="margin-bottom:14px;font-size:.95rem">➕ Grant Tracking Access</h3>
    <form method="POST" style="display:flex;align-items:end;gap:12px;flex-wrap:wrap">
        <input type="hidden" name="action" value="add">
        <div>
            <label style="display:block;font-size:.7rem;color:var(--mut);text-transform:uppercase;margin-bottom:4px">Viewer (who can see)</label>
            <select name="viewer_id" required style="background:var(--bg);color:var(--text);border:1px solid var(--line);padding:8px 12px;border-radius:10px;min-width:200px">
                <option value="">Select user...</option>
                <?php foreach ($users as $u): ?>
                <option value="<?= (int)$u['id'] ?>"><?= ss_admin_h($u['name']) ?> (@<?= ss_admin_h($u['username']) ?>) — <?= ss_admin_h($u['email']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="font-size:1.2rem;padding-bottom:6px">→</div>
        <div>
            <label style="display:block;font-size:.7rem;color:var(--mut);text-transform:uppercase;margin-bottom:4px">Target (whose data to see)</label>
            <select name="target_id" required style="background:var(--bg);color:var(--text);border:1px solid var(--line);padding:8px 12px;border-radius:10px;min-width:200px">
                <option value="">Select user...</option>
                <?php foreach ($users as $u): ?>
                <option value="<?= (int)$u['id'] ?>"><?= ss_admin_h($u['name']) ?> (@<?= ss_admin_h($u['username']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn" style="background:#a855f7;color:#fff;border:none;padding:8px 18px;cursor:pointer">Grant Access</button>
    </form>
</div>

<div class="panel" style="padding:20px">
    <h3 style="margin-bottom:14px;font-size:.95rem">📋 Current Permissions (<?= count($viewers) ?>)</h3>
    <?php if (empty($viewers)): ?>
    <div style="color:var(--mut);text-align:center;padding:30px">No tracking viewer permissions set yet.</div>
    <?php else: ?>
    <table style="width:100%;border-collapse:collapse;font-size:.85rem">
        <thead>
            <tr style="border-bottom:1px solid var(--line)">
                <th style="text-align:left;padding:8px;color:var(--mut);font-size:.72rem;text-transform:uppercase">Viewer</th>
                <th style="text-align:left;padding:8px;color:var(--mut);font-size:.72rem;text-transform:uppercase">Can See</th>
                <th style="text-align:left;padding:8px;color:var(--mut);font-size:.72rem;text-transform:uppercase">Granted</th>
                <th style="text-align:center;padding:8px;color:var(--mut);font-size:.72rem;text-transform:uppercase">Action</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($viewers as $v): ?>
            <tr style="border-bottom:1px solid var(--line)">
                <td style="padding:10px 8px">
                    <div style="font-weight:600"><?= ss_admin_h($v['v_name']) ?></div>
                    <div style="color:var(--mut);font-size:.75rem">@<?= ss_admin_h($v['v_user']) ?> · <?= ss_admin_h($v['v_email']) ?></div>
                </td>
                <td style="padding:10px 8px">
                    <div style="font-weight:600"><?= ss_admin_h($v['t_name']) ?></div>
                    <div style="color:var(--mut);font-size:.75rem">@<?= ss_admin_h($v['t_user']) ?></div>
                </td>
                <td style="padding:10px 8px;color:var(--mut);font-size:.78rem"><?= date('d M Y H:i', strtotime($v['created_at'])) ?></td>
                <td style="padding:10px 8px;text-align:center">
                    <form method="POST" style="display:inline" onsubmit="return confirm('Revoke access?')">
                        <input type="hidden" name="action" value="remove">
                        <input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
                        <button type="submit" class="btn" style="background:rgba(239,68,68,.15);color:#f87171;border:none;padding:5px 12px;cursor:pointer;font-size:.78rem">🗑 Revoke</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<div style="margin-top:16px;padding:14px 18px;background:var(--panel);border:1px solid var(--line);border-radius:12px;font-size:.8rem;color:var(--mut)">
    💡 <b>Viewer URL:</b> <code style="background:var(--bg);padding:2px 8px;border-radius:6px"><?php
        $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        echo $proto . ($_SERVER['HTTP_HOST'] ?? 'soulsyncc.site') . dirname(dirname($_SERVER['SCRIPT_NAME'])) . '/viewer/';
    ?></code>
    <br>Share this URL with the viewer. They login with their SoulSync email/password.
</div>
<?php require __DIR__ . '/_dark_foot.php';
