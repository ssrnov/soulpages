<?php
require_once '../includes/functions.php';

if (is_logged_in() && is_admin()) {
    redirect('index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($csrf_token)) {
        $error = 'Invalid CSRF token.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (is_rate_limited('admin_login_' . md5($email), 5, 60)) {
            $error = 'Too many attempts. Try again in a minute.';
        } elseif (empty($email) || empty($password)) {
            $error = 'Please enter both email and password.';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'admin'");
            $stmt->execute([$email]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password'])) {
                // Regenerate session ID
                regenerate_user_session();

                $_SESSION['user_id'] = $admin['id'];
                $_SESSION['user_name'] = $admin['name'];
                $_SESSION['user_email'] = $admin['email'];
                $_SESSION['user_role'] = $admin['role'];
                $_SESSION['user_photo'] = $admin['profile_photo'] ?? '';
                redirect('index.php');
            } else {
                $error = 'Invalid admin credentials.';
            }
        }
    }
}
$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - SoulSync</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@600;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: { fontFamily: { sans: ['Inter', 'sans-serif'], heading: ['Outfit', 'sans-serif'] } } }
        }
    </script>
    <style>
        body {
            background-color: #0f172a;
            background-image:
                radial-gradient(at 20% 20%, rgba(124, 58, 237, 0.08) 0px, transparent 50%),
                radial-gradient(at 80% 80%, rgba(59, 130, 246, 0.08) 0px, transparent 50%);
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.06);
        }
    </style>
</head>
<body class="text-white font-sans min-h-screen flex items-center justify-center p-4 antialiased">
    <div class="w-full max-w-sm">
        <div class="text-center mb-8">
            <span class="text-3xl font-extrabold font-heading bg-gradient-to-r from-pink-500 to-purple-500 bg-clip-text text-transparent">SoulSync</span>
            <span class="ml-2 bg-purple-500/10 border border-purple-500/30 text-purple-400 text-[10px] font-bold px-2 py-0.5 rounded-md uppercase">Admin</span>
        </div>

        <div class="glass-card rounded-2xl p-8">
            <h1 class="text-xl font-bold font-heading text-white text-center mb-6">Admin Login</h1>

            <?php if (!empty($error)): ?>
                <div class="bg-red-500/10 border border-red-500/20 rounded-xl p-3 mb-4 text-center">
                    <p class="text-red-400 text-sm"><?= h($error) ?></p>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1.5 uppercase tracking-wider">Email</label>
                    <input type="email" name="email" required
                           class="w-full bg-slate-900 border border-slate-800 focus:border-purple-500 focus:ring-1 focus:ring-purple-500 rounded-xl px-4 py-2.5 text-sm outline-none text-white placeholder-slate-600"
                           placeholder="admin@soulsync.com">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1.5 uppercase tracking-wider">Password</label>
                    <input type="password" name="password" required
                           class="w-full bg-slate-900 border border-slate-800 focus:border-purple-500 focus:ring-1 focus:ring-purple-500 rounded-xl px-4 py-2.5 text-sm outline-none text-white placeholder-slate-600"
                           placeholder="••••••••">
                </div>
                <button type="submit"
                        class="w-full bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white font-bold text-sm py-3 rounded-xl shadow-lg shadow-purple-500/20 transition-all duration-300 hover:-translate-y-0.5">
                    Sign In
                </button>
            </form>

            <?php
            $g_client_id = get_setting('google_client_id', GOOGLE_CLIENT_ID);
            if ($g_client_id && $g_client_id !== 'YOUR_GOOGLE_CLIENT_ID'):
            ?>
            <div class="flex items-center gap-3 my-4">
                <div class="flex-1 h-px bg-slate-700"></div>
                <span class="text-xs text-slate-500">or</span>
                <div class="flex-1 h-px bg-slate-700"></div>
            </div>
            <script src="https://accounts.google.com/gsi/client" async defer></script>
            <div id="google-btn-wrap" style="display:flex;justify-content:center;margin-top:4px"></div>
            <script>
            function handleGoogleSignIn(response) {
                fetch('../auth/google-onetap.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'credential=' + encodeURIComponent(response.credential)
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) window.location.href = data.redirect || '../dashboard.php';
                    else alert(data.error || 'Google sign-in failed');
                })
                .catch(() => alert('Google sign-in failed. Please try again.'));
            }
            window.addEventListener('load', function() {
                google.accounts.id.initialize({
                    client_id: '<?= h($g_client_id) ?>',
                    callback: handleGoogleSignIn
                });
                google.accounts.id.renderButton(document.getElementById('google-btn-wrap'), {
                    theme: 'filled_black', size: 'large', width: 300, text: 'continue_with', shape: 'pill'
                });
            });
            </script>
            <?php endif; ?>
        </div>

        <p class="text-center mt-6">
            <a href="../login.php" class="text-slate-500 hover:text-slate-300 text-xs transition">← Back to User Login</a>
        </p>
    </div>
</body>
</html>
