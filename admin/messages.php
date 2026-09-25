<?php
require_once '../includes/functions.php';

if (!is_logged_in() || !is_admin()) {
    redirect('login.php');
}

// Contact-form messages live in site_settings under 'contact_inbox' (JSON, newest first).
if (isset($_GET['clear']) && $_GET['clear'] === '1') {
    set_setting('contact_inbox', '[]');
    redirect('messages.php');
}
if (isset($_GET['del'])) {
    $inbox = json_decode(get_setting('contact_inbox', '[]'), true);
    if (is_array($inbox)) {
        $di = (int)$_GET['del'];
        if (isset($inbox[$di])) { unset($inbox[$di]); $inbox = array_values($inbox); set_setting('contact_inbox', json_encode($inbox, JSON_UNESCAPED_UNICODE)); }
    }
    redirect('messages.php');
}

$inbox = json_decode(get_setting('contact_inbox', '[]'), true);
if (!is_array($inbox)) $inbox = [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Contact Inbox — Admin</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@600;800&display=swap" rel="stylesheet">
<style>
  body { font-family:'Inter',sans-serif; background:#0b0b0f; color:#e5e7eb; }
  .heading { font-family:'Outfit',sans-serif; }
</style>
</head>
<body class="min-h-screen">

<?php $ADMIN_TITLE = 'Contact Inbox'; include '_nav.php'; ?>

<main class="max-w-4xl mx-auto px-4 py-8">
  <div class="flex items-center justify-between mb-6">
    <div>
      <h1 class="heading text-2xl font-extrabold text-white">📮 Contact Inbox</h1>
      <p class="text-sm text-slate-400 mt-1">Messages from the public contact page (<?= count($inbox) ?> total, newest first).</p>
    </div>
    <?php if ($inbox): ?><a href="messages.php?clear=1" onclick="return confirm('Delete ALL contact messages?')" class="text-xs font-bold bg-red-500/10 border border-red-500/30 text-red-300 px-4 py-2.5 rounded-xl hover:bg-red-500/20 transition">🗑 Clear All</a><?php endif; ?>
  </div>

  <?php if (!$inbox): ?>
  <div class="rounded-2xl border border-slate-800 bg-slate-900/50 p-12 text-center">
    <div class="text-4xl mb-3">📭</div>
    <p class="text-slate-400 font-semibold">No messages yet</p>
    <p class="text-xs text-slate-500 mt-1">Messages sent from contact.php will appear here.</p>
  </div>
  <?php else: ?>
  <div class="space-y-4">
    <?php foreach ($inbox as $mi => $m): ?>
    <div class="rounded-2xl border border-slate-800 bg-slate-900/50 p-5">
      <div class="flex items-start justify-between gap-3 flex-wrap">
        <div>
          <span class="font-bold text-white"><?= h($m['name'] ?? 'Anonymous') ?></span>
          <?php if (!empty($m['email'])): ?><a href="mailto:<?= h($m['email']) ?>" class="ml-2 text-xs text-pink-400 hover:underline"><?= h($m['email']) ?></a><?php endif; ?>
        </div>
        <div class="flex items-center gap-3">
          <span class="text-[11px] text-slate-500"><?= h($m['at'] ?? '') ?></span>
          <a href="messages.php?del=<?= $mi ?>" onclick="return confirm('Delete this message?')" class="text-xs text-red-400 hover:text-red-300">✕</a>
        </div>
      </div>
      <p class="text-sm text-slate-300 mt-3 leading-relaxed whitespace-pre-line"><?= h($m['msg'] ?? '') ?></p>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</main>

</body>
</html>
