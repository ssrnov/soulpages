<?php
require_once 'includes/functions.php';

// If logged in, show change password form
$user = null;
$isLoggedIn = is_logged_in();
if ($isLoggedIn) {
    $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
}

$error = '';
$success = '';

// Handle password change (logged-in user)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isLoggedIn && $user) {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($csrf)) {
        $error = 'Invalid token. Please try again.';
    } else {
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';
        if (strlen($newPass) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($newPass !== $confirmPass) {
            $error = 'Passwords do not match.';
        } else {
            $hashed = password_hash($newPass, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashed, $user['id']]);
            $success = 'Password changed successfully! You can now login with your new password.';
        }
    }
}

$csrf_token = generate_csrf_token();
$g_client_id = get_setting('google_client_id', GOOGLE_CLIENT_ID);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password - <?= h(SITE_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Outfit:wght@600;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<style>
:root{--bg-darkest:#ffffff;--pink-hot:#db2777;--pink-rose:#e11d48}
body{background:linear-gradient(135deg,#fff 0%,#fdf2f8 50%,#f1f5f9 100%)!important;background-attachment:fixed;min-height:100vh;font-family:'Inter',sans-serif}
.sp-glass{background:rgba(255,255,255,.8)!important;backdrop-filter:blur(20px);border:1px solid rgba(236,72,153,.12)!important;border-radius:24px;transition:all .3s}
.sp-gradient-text{background:linear-gradient(135deg,#be185d,#db2777 40%,#e11d48 70%,#d97706);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.sp-btn-primary{background:linear-gradient(135deg,#db2777,#e11d48)!important;color:#fff!important;font-weight:700;border-radius:16px;padding:14px 32px;border:none;box-shadow:0 8px 25px rgba(219,39,119,.2)!important;cursor:pointer;transition:all .3s}
.sp-btn-primary:hover{transform:translateY(-2px);box-shadow:0 12px 35px rgba(219,39,119,.3)!important}
input[type="password"]{background:#fff!important;border:1px solid #cbd5e1!important;color:#0f172a!important;border-radius:16px;padding:12px 16px;width:100%;outline:none;font-size:.9rem}
input:focus{border-color:#db2777!important;box-shadow:0 0 0 3px rgba(219,39,119,.15)!important}
</style>
</head>
<body class="flex items-center justify-center">
<div class="w-full max-w-md mx-4 py-8 relative z-10">
<div class="sp-glass p-8 sm:p-10">
    <div class="text-center mb-8">
        <a href="index.php" class="text-3xl font-extrabold sp-gradient-text"><?= h(SITE_NAME) ?></a>
        <h1 class="text-lg font-bold mt-3" style="color:#0f172a">Forgot Password?</h1>
    </div>

    <?php if (!empty($error)): ?>
    <div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);color:#dc2626;border-radius:14px;padding:12px;font-size:.85rem;margin-bottom:16px;text-align:center"><?= h($error) ?></div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
    <div style="background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.2);color:#16a34a;border-radius:14px;padding:16px;font-size:.85rem;margin-bottom:16px;text-align:center">
        <div style="font-size:1.5rem;margin-bottom:6px">✅</div>
        <?= h($success) ?>
    </div>
    <div class="text-center mt-4">
        <a href="login.php" class="sp-btn-primary inline-block text-sm" style="padding:10px 28px">Go to Login →</a>
    </div>

    <?php elseif ($isLoggedIn && $user): ?>
    <!-- Logged in via Google — show change password form -->
    <div style="text-align:center;margin-bottom:20px">
        <div style="background:rgba(168,85,247,.08);border:1px solid rgba(168,85,247,.15);border-radius:14px;padding:14px;font-size:.85rem;color:#7c3aed">
            Logged in as <b><?= h($user['email']) ?></b>
        </div>
    </div>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">

        <div style="margin-bottom:16px">
            <label style="display:block;font-size:.7rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">New Password</label>
            <input type="password" name="new_password" required minlength="8" placeholder="Min 8 characters">
        </div>

        <div style="margin-bottom:20px">
            <label style="display:block;font-size:.7rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">Confirm Password</label>
            <input type="password" name="confirm_password" required minlength="8" placeholder="Re-enter password">
        </div>

        <button type="submit" class="sp-btn-primary" style="width:100%;font-size:.95rem">Change Password 🔒</button>
    </form>

    <div class="text-center mt-4">
        <a href="index.php" style="color:#db2777;font-size:.85rem;text-decoration:none">← Back to Home</a>
    </div>

    <?php else: ?>
    <!-- Not logged in — show instructions + Google sign-in -->
    <div style="text-align:center;margin-bottom:24px">
        <p style="color:#475569;font-size:.9rem;line-height:1.6">
            To reset your password, first <b>sign in with Google</b> to verify your identity. Then you can set a new password.
        </p>
    </div>

    <?php if ($g_client_id && $g_client_id !== 'YOUR_GOOGLE_CLIENT_ID'): ?>
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <div id="google-btn-wrap" style="display:flex;justify-content:center;margin:20px 0"></div>
    <script>
    function handleGoogleSignIn(response) {
        fetch('auth/google-onetap.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'credential=' + encodeURIComponent(response.credential)
        })
        .then(r => r.text())
        .then(text => {
            try { var data = JSON.parse(text); } catch(e) { alert('Server error: ' + text.substring(0,200)); return; }
            if (data.success) window.location.href = 'forgot-password.php';
            else alert(data.error || 'Google sign-in failed');
        })
        .catch(e => alert('Network error: ' + e.message));
    }
    window.addEventListener('load', function() {
        google.accounts.id.initialize({
            client_id: '<?= h($g_client_id) ?>',
            callback: handleGoogleSignIn
        });
        google.accounts.id.renderButton(document.getElementById('google-btn-wrap'), {
            theme: 'outline', size: 'large', width: 360, text: 'continue_with', shape: 'pill'
        });
    });
    </script>
    <?php endif; ?>

    <div style="text-align:center;margin-top:20px;padding-top:16px;border-top:1px solid rgba(236,72,153,.1)">
        <p style="color:#94a3b8;font-size:.8rem">Remember your password? <a href="login.php" style="color:#db2777;font-weight:600;text-decoration:none">Login here</a></p>
    </div>
    <?php endif; ?>
</div>
</div>
</body>
</html>
