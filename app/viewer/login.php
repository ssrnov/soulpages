<?php
// =========================================================================
// TrackView viewer login — email/password (users table) + Google Sign-In.
// Grants access only if the account has a tracking_viewers row (viewer_id).
// =========================================================================
date_default_timezone_set('Asia/Kolkata');

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', '1');
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', '1');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://accounts.google.com https://apis.google.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; frame-src https://accounts.google.com; frame-ancestors 'none';");
header('Cache-Control: no-store, no-cache, must-revalidate, private');
header('Pragma: no-cache');

require_once __DIR__ . '/../includes/db.php';

if (!defined('GOOGLE_CLIENT_ID')) define('GOOGLE_CLIENT_ID', '619239393964-epm3b0dbfm0b2c3lgdbg0bu432qfa2nk.apps.googleusercontent.com');

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

if (isset($_GET['logout'])) {
    unset($_SESSION['viewer_id']);
    header('Location: login.php');
    exit;
}

// Already logged in → go straight to tracking.
if (!empty($_SESSION['viewer_id'])) {
    $chk = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $chk->execute([$_SESSION['viewer_id']]);
    if ($chk->fetch()) { header('Location: tracking.php'); exit; }
    unset($_SESSION['viewer_id']);
}

// --- Brute force protection (5 attempts per 15 min per IP) ---
function viewer_rate_check($pdo) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (id INT AUTO_INCREMENT PRIMARY KEY, ip VARCHAR(45), attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP, INDEX(ip, attempted_at))");
        $pdo->prepare("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)")->execute();
        $cnt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip=? AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
        $cnt->execute([$ip]);
        return (int)$cnt->fetchColumn() >= 5;
    } catch (\Throwable $e) { return false; }
}
function viewer_rate_log($pdo) {
    try { $pdo->prepare("INSERT INTO login_attempts (ip) VALUES (?)")->execute([$_SERVER['REMOTE_ADDR'] ?? '0.0.0.0']); } catch (\Throwable $e) {}
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (viewer_rate_check($pdo)) {
        $error = 'Too many login attempts. Try again in 15 minutes.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        viewer_rate_log($pdo);
        if (empty($email) || empty($password)) {
            $error = 'Email and password required.';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $u = $stmt->fetch();
            if (!$u || !password_verify($password, $u['password'])) {
                $error = 'Invalid credentials.';
            } else {
                $chk = $pdo->prepare("SELECT 1 FROM tracking_viewers WHERE viewer_id = ? LIMIT 1");
                $chk->execute([$u['id']]);
                if (!$chk->fetch()) {
                    $error = 'Access denied. You are not authorized to view tracking data.';
                } else {
                    $_SESSION['viewer_id'] = $u['id'];
                    $_SESSION['_viewer_last'] = time();
                    header('Location: tracking.php');
                    exit;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TrackView · Login</title>
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#7c3aed">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="TrackView">
<link rel="apple-touch-icon" href="icons/icon-192.svg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{--bg:#0a0e18;--card:#141b2b;--line:#242f49;--text:#e9edf7;--mut:#8b98b6;--accent:#a855f7}
body{background:var(--bg);color:var(--text);font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;min-height:100vh}
.login-wrap{display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px}
.login-card{background:var(--card);border:1px solid var(--line);border-radius:20px;padding:40px;max-width:400px;width:100%;box-shadow:0 8px 26px rgba(0,0,0,.4)}
.login-card h1{font-size:1.6rem;text-align:center;margin-bottom:6px;background:linear-gradient(135deg,#a855f7,#ec4899);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.login-card p{text-align:center;color:var(--mut);font-size:.85rem;margin-bottom:24px}
.field{margin-bottom:16px}
.field label{display:block;font-size:.7rem;color:var(--mut);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px}
.field input{width:100%;background:var(--bg);border:1px solid var(--line);color:var(--text);padding:12px 16px;border-radius:12px;font-size:.9rem;outline:none}
.field input:focus{border-color:var(--accent)}
.btn-primary{width:100%;padding:14px;border:none;border-radius:14px;background:linear-gradient(135deg,#a855f7,#ec4899);color:#fff;font-weight:700;font-size:.95rem;cursor:pointer}
.error{background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.3);color:#f87171;padding:12px;border-radius:12px;font-size:.85rem;margin-bottom:16px;text-align:center}
</style>
</head>
<body>
<div class="login-wrap">
    <div class="login-card">
        <h1>📡 TrackView</h1>
        <p>Login with your SoulSync account</p>
        <?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>
        <form method="POST">
            <div class="field"><label>Email</label><input type="email" name="email" required placeholder="you@example.com"></div>
            <div class="field"><label>Password</label><input type="password" name="password" required placeholder="Your password"></div>
            <button type="submit" class="btn-primary">Login</button>
        </form>
        <div style="display:flex;align-items:center;gap:10px;margin:16px 0">
            <div style="flex:1;height:1px;background:var(--line)"></div>
            <span style="font-size:.75rem;color:var(--mut)">or</span>
            <div style="flex:1;height:1px;background:var(--line)"></div>
        </div>
        <script src="https://accounts.google.com/gsi/client" async defer></script>
        <div id="google-btn-wrap" style="display:flex;justify-content:center"></div>
        <script>
        function handleGoogleViewerSignIn(response) {
            fetch('google-auth.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'credential=' + encodeURIComponent(response.credential)
            })
            .then(r => r.text())
            .then(text => {
                try { var data = JSON.parse(text); } catch(e) { alert('Server error: ' + text.substring(0,200)); return; }
                if (data.success) window.location.href = 'tracking.php';
                else alert(data.error || 'Login failed');
            })
            .catch(e => alert('Network error: ' + e.message));
        }
        window.addEventListener('load', function() {
            if (typeof google !== 'undefined') {
                google.accounts.id.initialize({
                    client_id: '<?php echo GOOGLE_CLIENT_ID; ?>',
                    callback: handleGoogleViewerSignIn
                });
                google.accounts.id.renderButton(document.getElementById('google-btn-wrap'), {
                    theme: 'filled_black', size: 'large', width: 320, text: 'continue_with', shape: 'pill'
                });
            }
        });
        </script>
    </div>
</div>
</body>
</html>
