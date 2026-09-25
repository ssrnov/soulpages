<?php
require_once 'includes/functions.php';
$user = is_logged_in() ? get_user_profile($_SESSION['user_id']) : null;

// Auto-create support_messages table
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

$sent = false;
$cerr = '';
$myTickets = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $cerr = 'Security token expired. Please try again.';
    } else {
        $sname = trim($_POST['name'] ?? ($user['name'] ?? ''));
        $semail = trim($_POST['email'] ?? ($user['email'] ?? ''));
        $ssubject = trim($_POST['subject'] ?? 'General');
        $smsg = trim($_POST['message'] ?? '');

        if ($sname === '' || $semail === '' || $smsg === '') {
            $cerr = 'Please fill all required fields.';
        } elseif (mb_strlen($smsg) < 10) {
            $cerr = 'Message is too short. Please describe your issue.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO support_messages (user_id, name, email, subject, message, ip) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $user['id'] ?? null,
                mb_substr($sname, 0, 100),
                mb_substr($semail, 0, 150),
                mb_substr($ssubject, 0, 200),
                mb_substr($smsg, 0, 5000),
                $_SERVER['REMOTE_ADDR'] ?? ''
            ]);
            $sent = true;
        }
    }
}

// Show user's past tickets if logged in
if ($user) {
    try {
        $myTickets = $pdo->prepare("SELECT id, subject, status, message, admin_reply, replied_at, created_at FROM support_messages WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
        $myTickets->execute([$user['id']]);
        $myTickets = $myTickets->fetchAll();
    } catch (\Throwable $e) { $myTickets = []; }
}

$csrf = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Help & Support - <?= h(SITE_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{--bg:#0f0a1a;--card:#1a1528;--card2:#221a35;--line:#2e2545;--text:#e8e0f0;--mut:#8b7fa0;--accent:#a855f7;--pink:#ec4899;--green:#22c55e;--red:#ef4444}
body{background:var(--bg);color:var(--text);font-family:'Inter',system-ui,sans-serif;min-height:100vh}
.wrap{max-width:720px;margin:0 auto;padding:30px 20px}
.back{display:inline-flex;align-items:center;gap:6px;color:var(--mut);text-decoration:none;font-size:.85rem;margin-bottom:20px}
.back:hover{color:var(--accent)}
h1{font-size:1.8rem;font-weight:800;margin-bottom:6px;background:linear-gradient(135deg,#a855f7,#ec4899);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.subtitle{color:var(--mut);font-size:.9rem;margin-bottom:30px}
.card{background:var(--card);border:1px solid var(--line);border-radius:18px;padding:28px;margin-bottom:20px}
.field{margin-bottom:18px}
.field label{display:block;font-size:.72rem;color:var(--mut);text-transform:uppercase;letter-spacing:.5px;font-weight:700;margin-bottom:6px}
.field input,.field textarea,.field select{width:100%;background:var(--bg);border:1px solid var(--line);color:var(--text);padding:12px 16px;border-radius:12px;font-size:.9rem;font-family:inherit;outline:none;transition:border-color .2s}
.field input:focus,.field textarea:focus,.field select:focus{border-color:var(--accent)}
.field textarea{resize:vertical;min-height:120px}
.field select option{background:var(--bg);color:var(--text)}
.btn{width:100%;padding:14px;border:none;border-radius:14px;background:linear-gradient(135deg,#a855f7,#ec4899);color:#fff;font-weight:700;font-size:.95rem;cursor:pointer;transition:opacity .2s}
.btn:hover{opacity:.9}
.error{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);color:#f87171;padding:14px;border-radius:12px;font-size:.85rem;margin-bottom:18px;text-align:center}
.success{background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.25);color:#4ade80;padding:20px;border-radius:14px;text-align:center}
.success h3{font-size:1.2rem;margin-bottom:6px;color:#4ade80}
.success p{color:var(--mut);font-size:.85rem}
.faq{margin-top:30px}
.faq h2{font-size:1.1rem;font-weight:700;margin-bottom:14px}
.faq-item{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:16px 18px;margin-bottom:10px;cursor:pointer;transition:border-color .2s}
.faq-item:hover{border-color:var(--accent)}
.faq-q{font-weight:600;font-size:.9rem;display:flex;justify-content:space-between;align-items:center}
.faq-a{color:var(--mut);font-size:.83rem;margin-top:10px;display:none;line-height:1.5}
.faq-item.open .faq-a{display:block}
.faq-item.open .arrow{transform:rotate(180deg)}
.arrow{transition:transform .2s;color:var(--mut)}
.tickets{margin-top:30px}
.tickets h2{font-size:1.1rem;font-weight:700;margin-bottom:14px}
.ticket{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:16px 18px;margin-bottom:10px}
.ticket-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px}
.ticket-subject{font-weight:700;font-size:.9rem}
.pill{display:inline-block;padding:3px 10px;border-radius:50px;font-size:.68rem;font-weight:700}
.pill-open{background:rgba(249,115,22,.15);color:#fb923c}
.pill-replied{background:rgba(34,197,94,.15);color:#4ade80}
.pill-closed{background:rgba(100,116,139,.15);color:#94a3b8}
.ticket-msg{color:var(--mut);font-size:.82rem;line-height:1.5}
.ticket-reply{margin-top:10px;padding:12px;background:rgba(168,85,247,.08);border:1px solid rgba(168,85,247,.2);border-radius:10px}
.ticket-reply-label{font-size:.7rem;color:var(--accent);text-transform:uppercase;font-weight:700;margin-bottom:4px}
.ticket-reply-text{font-size:.83rem;line-height:1.5}
.ticket-date{font-size:.72rem;color:var(--mut);margin-top:6px}
</style>
</head>
<body>
<div class="wrap">
    <a href="index.php" class="back">← Back to <?= h(SITE_NAME) ?></a>
    <h1>Help & Support</h1>
    <p class="subtitle">Have a question, issue, or feedback? Send us a message and we'll get back to you.</p>

    <?php if ($sent): ?>
    <div class="success">
        <h3>Message Sent!</h3>
        <p>We've received your message. Our team will review it and respond soon.</p>
    </div>
    <?php else: ?>

    <?php if ($cerr): ?>
    <div class="error"><?= h($cerr) ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                <div class="field">
                    <label>Your Name *</label>
                    <input type="text" name="name" required maxlength="100" value="<?= h($user['name'] ?? '') ?>" placeholder="Your name" <?= $user ? 'readonly' : '' ?>>
                </div>
                <div class="field">
                    <label>Email *</label>
                    <input type="email" name="email" required maxlength="150" value="<?= h($user['email'] ?? '') ?>" placeholder="you@example.com" <?= $user ? 'readonly' : '' ?>>
                </div>
            </div>

            <div class="field">
                <label>Topic</label>
                <select name="subject">
                    <option value="General">General Question</option>
                    <option value="Bug Report">Bug Report</option>
                    <option value="Feature Request">Feature Request</option>
                    <option value="Account Issue">Account Issue</option>
                    <option value="Payment Issue">Payment Issue</option>
                    <option value="Privacy Concern">Privacy Concern</option>
                    <option value="Other">Other</option>
                </select>
            </div>

            <div class="field">
                <label>Message *</label>
                <textarea name="message" required minlength="10" maxlength="5000" placeholder="Describe your issue or question in detail..."></textarea>
            </div>

            <button type="submit" class="btn">Send Message</button>
        </form>
    </div>
    <?php endif; ?>

    <?php if (!empty($myTickets)): ?>
    <div class="tickets">
        <h2>Your Past Messages</h2>
        <?php foreach ($myTickets as $t):
            $statusClass = match($t['status']){'replied'=>'pill-replied','closed'=>'pill-closed',default=>'pill-open'};
            $statusLabel = match($t['status']){'replied'=>'Replied','closed'=>'Closed',default=>'Open'};
        ?>
        <div class="ticket">
            <div class="ticket-head">
                <span class="ticket-subject"><?= h($t['subject']) ?></span>
                <span class="pill <?= $statusClass ?>"><?= $statusLabel ?></span>
            </div>
            <div class="ticket-msg"><?= nl2br(h(mb_substr($t['message'], 0, 300))) ?><?= mb_strlen($t['message']) > 300 ? '...' : '' ?></div>
            <?php if (!empty($t['admin_reply'])): ?>
            <div class="ticket-reply">
                <div class="ticket-reply-label">Admin Reply</div>
                <div class="ticket-reply-text"><?= nl2br(h($t['admin_reply'])) ?></div>
            </div>
            <?php endif; ?>
            <div class="ticket-date">Sent: <?= date('d M Y, H:i', strtotime($t['created_at'])) ?><?= $t['replied_at'] ? ' · Replied: ' . date('d M Y, H:i', strtotime($t['replied_at'])) : '' ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="faq">
        <h2>Frequently Asked Questions</h2>

        <div class="faq-item" onclick="this.classList.toggle('open')">
            <div class="faq-q">How long does it take to get a reply? <span class="arrow">▼</span></div>
            <div class="faq-a">We usually respond within 24 hours. For urgent issues, we try to get back to you even sooner.</div>
        </div>

        <div class="faq-item" onclick="this.classList.toggle('open')">
            <div class="faq-q">Can I track my support request? <span class="arrow">▼</span></div>
            <div class="faq-a">Yes! If you're logged in, you can see all your past messages and our replies right on this page.</div>
        </div>

        <div class="faq-item" onclick="this.classList.toggle('open')">
            <div class="faq-q">Is my data safe? <span class="arrow">▼</span></div>
            <div class="faq-a">Absolutely. We take privacy seriously. Your data is encrypted and never shared with third parties.</div>
        </div>

        <div class="faq-item" onclick="this.classList.toggle('open')">
            <div class="faq-q">How do I delete my account? <span class="arrow">▼</span></div>
            <div class="faq-a">Send us a message with the subject "Account Issue" and request account deletion. We'll process it within 48 hours.</div>
        </div>
    </div>
</div>
</body>
</html>
