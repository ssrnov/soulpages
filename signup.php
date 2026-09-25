<?php
require_once 'includes/functions.php';

// If already logged in, go to dashboard
if (is_logged_in()) {
    redirect('dashboard.php');
}

// Handle referral code from URL
$ref = trim($_GET['ref'] ?? '');
if (!empty($ref)) {
    setcookie('sp_ref', $ref, time() + (30 * 24 * 60 * 60), '/');
    $_SESSION['referral_code'] = $ref;
}

$error = '';
$old_name = '';
$old_username = '';
$old_email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($csrf_token)) {
        $error = 'Invalid CSRF token. Please refresh and try again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $agree = isset($_POST['agree_terms']) ? 1 : 0;

        $old_name = $name;
        $old_username = $username;
        $old_email = $email;

        // Validation
        if (empty($name) || empty($email) || empty($password) || empty($confirm)) {
            $error = 'Please fill in all required fields.';
        } elseif (!$agree) {
            $error = 'You must agree to the Terms and Privacy Policy.';
        } elseif (strlen($name) < 2) {
            $error = 'Name must be at least 2 characters.';
        } elseif (!empty($username) && strlen($username) < 3) {
            $error = 'Username must be at least 3 characters.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'This email is already registered. Please login instead.';
            } else {
                // Check if username already exists
                if (!empty($username)) {
                    $stmt_u = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                    $stmt_u->execute([$username]);
                    if ($stmt_u->fetch()) {
                        $error = 'Username is already taken. Please try another one.';
                    }
                }
                
                if (empty($error)) {
                    // Create new user
                    $hashed = password_hash($password, PASSWORD_BCRYPT);
                    $referral_code = generate_referral_code();

                    // Ensure unique referral code
                    $attempts = 0;
                    while ($attempts < 10) {
                        $check = $pdo->prepare("SELECT id FROM users WHERE referral_code = ?");
                        $check->execute([$referral_code]);
                        if (!$check->fetch()) break;
                        $referral_code = generate_referral_code();
                        $attempts++;
                    }

                    $stmt = $pdo->prepare("INSERT INTO users (name, username, email, password, role, status, referral_code) VALUES (?, ?, ?, ?, 'user', 'active', ?)");
                    $stmt->execute([$name, !empty($username) ? $username : null, $email, $hashed, $referral_code]);
                    $user_id = $pdo->lastInsertId();

                    // ── Same login on the SoulSync app: mirror this account into
                    //    the SoulSync database (best-effort; never breaks signup). ──
                    try {
                        $ssdb = new PDO("mysql:host=localhost;dbname=looprsi1_SoulSync;charset=utf8mb4",
                            'looprsi1_ssrnov', 'Jayshreeram@12345',
                            [PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT, PDO::ATTR_TIMEOUT => 3]);
                        // Pick a valid, unique SoulSync username.
                        $uname = strtolower((string)(!empty($username) ? $username : strtok($email, '@')));
                        $uname = preg_replace('/[^a-z0-9_.]/', '', $uname);
                        if (strlen($uname) < 3) $uname = 'user' . (int)$user_id;
                        $uname = substr($uname, 0, 28);
                        $exists = $ssdb->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
                        $exists->execute([$uname, $email]);
                        if (!$exists->fetch()) {
                            $ins = $ssdb->prepare("INSERT INTO users (username, email, password, name) VALUES (?, ?, ?, ?)");
                            $ins->execute([$uname, $email, $hashed, $name ?: $uname]);
                            $ssUid = (int)$ssdb->lastInsertId();
                            try { $ssdb->prepare("INSERT INTO tracking_settings (user_id) VALUES (?)")->execute([$ssUid]); } catch (\Throwable $e) {}
                        }
                    } catch (\Throwable $e) { /* ignore */ }

                    // Grant 1 free starter credit (enough for one normal page)
                    $pdo->prepare("UPDATE users SET credits = credits + 1 WHERE id = ?")->execute([$user_id]);

                    // Process referral if cookie/session exists
                    $ref_code = $_COOKIE['sp_ref'] ?? ($_SESSION['referral_code'] ?? '');
                    if (!empty($ref_code)) {
                        process_referral($user_id, $ref_code);
                        setcookie('sp_ref', '', time() - 3600, '/');
                        unset($_SESSION['referral_code']);
                    }

                    // Secure session regeneration
                    regenerate_user_session();

                    // Set session
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['user_name'] = $name;
                    $_SESSION['user_email'] = $email;
                    $_SESSION['user_role'] = 'user';
                    $_SESSION['user_photo'] = '';

                    // Claim guest-created pages from this browser session
                    $session_id = session_id();
                    $stmt_claim = $pdo->prepare("UPDATE pages SET user_id = ? WHERE user_id IS NULL AND guest_session_id = ?");
                    $stmt_claim->execute([$user_id, $session_id]);
                    $claimed_count = $stmt_claim->rowCount();
                    
                    if ($claimed_count > 0) {
                        // Increment free_pages_used since guest page is now claimed as the user's free page
                        $pdo->prepare("UPDATE users SET free_pages_used = free_pages_used + ? WHERE id = ?")
                            ->execute([$claimed_count, $user_id]);
                    }

                    redirect('index.php');
                }
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

    <title>Sign Up - SoulSync</title>
    <meta name="description" content="Create your free SoulSync account to start making emotional webpages for your loved ones.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@600;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        heading: ['Outfit', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        /* ═══ SOULPAGES LIGHT PINK GLOBAL THEME ═══ */
        :root {
            --bg-darkest:    #ffffff;
            --bg-dark:       #fcfcfc;
            --bg-mid:        #f8fafc;
            --bg-card:       rgba(255, 20, 120, 0.01);
            --border-glow:   rgba(236, 72, 153, 0.1);
            --pink-hot:      #db2777;
            --pink-rose:     #e11d48;
            --pink-soft:     #f43f5e;
            --pink-pale:     #fdf2f8;
            --white-pure:    #0f172a;
            --white-soft:    #f8fafc;
            --text-primary:  #0f172a;
            --text-secondary:#475569;
            --text-muted:    #64748b;
        }

        /* Force light background and high contrast text colors */
        body {
            background: linear-gradient(135deg, #ffffff 0%, #fdf2f8 50%, #f1f5f9 100%) !important;
            background-attachment: fixed;
            color: #0f172a !important;
            min-height: 100vh;
        }

        /* Global Typography Contrast Fix */
        body, p, label, li, span:not(.sp-gradient-text):not(.text-transparent) {
            color: #334155 !important; /* slate-700 */
        }
        h1, h2, h3, h4, h5, h6 {
            color: #0f172a !important; /* slate-900 */
        }

        /* Buttons & Badges (White Text) */
        .sp-btn-primary, .sp-btn-primary *, button[type="submit"], button[type="submit"] *, .sp-btn-primary span {
            color: #ffffff !important;
        }

        /* Gradient & Transparent text preservation */
        .sp-gradient-text, .bg-clip-text, .text-transparent {
            color: transparent !important;
            -webkit-text-fill-color: transparent !important;
        }

        /* Interactive & Alert Text Color Overrides */
        .text-pink-500, .text-pink-650, .text-pink-600, .text-pink-400, .text-pink-300, .hover\:text-pink-500:hover, .hover\:text-pink-400:hover {
            color: #db2777 !important;
        }

        /* Glass card light */
        .sp-glass {
            background: rgba(255, 255, 255, 0.8) !important;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(236, 72, 153, 0.12) !important;
            border-radius: 24px;
            transition: all 0.3s ease;
        }
        .sp-glass:hover {
            border-color: rgba(236, 72, 153, 0.3) !important;
            box-shadow: 0 10px 30px rgba(236, 72, 153, 0.06), 0 20px 40px rgba(0,0,0,0.02) !important;
            transform: translateY(-3px);
        }

        /* Pink gradient text */
        .sp-gradient-text {
            background: linear-gradient(135deg, #be185d 0%, #db2777 40%, #e11d48 70%, #d97706 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Primary button */
        .sp-btn-primary {
            background: linear-gradient(135deg, #db2777 0%, #e11d48 100%) !important;
            color: white !important;
            font-weight: 700;
            border-radius: 16px;
            padding: 14px 32px;
            border: none;
            box-shadow: 0 8px 25px rgba(219,39,119,0.2) !important;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .sp-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 35px rgba(219,39,119,0.3) !important;
            opacity: 0.95;
        }

        /* Form elements */
        input[type="text"], input[type="number"], input[type="email"], input[type="password"], textarea, select {
            background-color: #ffffff !important;
            border: 1px solid #cbd5e1 !important;
            color: #0f172a !important;
        }
        input::placeholder, textarea::placeholder {
            color: #94a3b8 !important;
        }
        input:focus, textarea:focus, select:focus {
            border-color: #db2777 !important;
            box-shadow: 0 0 0 3px rgba(219, 39, 119, 0.15) !important;
        }
        .bg-slate-900\/40, .bg-slate-900, .bg-slate-950, .bg-slate-950\/40 {
            background-color: #ffffff !important;
        }
        .border-slate-800, .border-slate-850 {
            border-color: #cbd5e1 !important;
        }
    </style>
</head>
<body style="background: linear-gradient(135deg, #ffffff 0%, #fdf2f8 50%, #f1f5f9 100%); color: #0f172a; min-height: 100vh;" class="flex items-center justify-center font-sans">

    <!-- Emoji Rain -->
    <?php render_emoji_rain(); ?>

    <!-- Ambient glow -->
    <div class="fixed inset-0 pointer-events-none">
        <div class="absolute top-1/4 left-1/3 w-72 h-72 bg-pink-500/8 rounded-full blur-[80px]"></div>
        <div class="absolute bottom-1/4 right-1/4 w-64 h-64 bg-purple-500/6 rounded-full blur-[70px]"></div>
    </div>

    <div class="w-full max-w-md mx-4 py-8 relative z-10">
        <!-- Signup Card -->
        <div class="sp-glass p-8 sm:p-10">
            <div class="text-center mb-8">
                <a href="index.php" class="text-3xl font-extrabold font-heading sp-gradient-text"><?= h(SITE_NAME) ?></a>
                <h1 class="text-white text-lg font-bold mt-2">Create Account 🚀</h1>
                <p class="text-pink-200/40 text-xs mt-1">Sign up free and start sharing beautiful stories</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="bg-red-500/10 border border-red-500/30 text-red-300 rounded-2xl p-4 mb-6 text-sm">
                    ⚠️ <?= h($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="signup.php" class="space-y-4" id="signup-form">
                <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">

                <!-- Name -->
                <div>
                    <label for="name" class="block text-[10px] font-bold text-pink-200/40 mb-1.5 uppercase tracking-wider">Full Name</label>
                    <input type="text" id="name" name="name" required minlength="2" maxlength="100" value="<?= h($old_name) ?>" placeholder="Enter your name"
                           class="w-full bg-white/5 border border-pink-500/20 focus:border-pink-500/60 text-white placeholder-pink-200/30 rounded-2xl px-4 py-3 outline-none transition text-sm">
                </div>

                <!-- Username -->
                <div>
                    <label for="username" class="block text-[10px] font-bold text-pink-200/40 mb-1.5 uppercase tracking-wider">Username <span class="text-[9px] text-pink-200/30 lowercase font-normal">(optional)</span></label>
                    <input type="text" id="username" name="username" minlength="3" maxlength="100" value="<?= h($old_username) ?>" placeholder="Choose username"
                           class="w-full bg-white/5 border border-pink-500/20 focus:border-pink-500/60 text-white placeholder-pink-200/30 rounded-2xl px-4 py-3 outline-none transition text-sm">
                </div>

                <!-- Email -->
                <div>
                    <label for="email" class="block text-[10px] font-bold text-pink-200/40 mb-1.5 uppercase tracking-wider">Email Address</label>
                    <input type="email" id="email" name="email" required maxlength="150" value="<?= h($old_email) ?>" placeholder="you@example.com"
                           class="w-full bg-white/5 border border-pink-500/20 focus:border-pink-500/60 text-white placeholder-pink-200/30 rounded-2xl px-4 py-3 outline-none transition text-sm">
                </div>

                <!-- Password -->
                <div>
                    <label for="password" class="block text-[10px] font-bold text-pink-200/40 mb-1.5 uppercase tracking-wider">Password</label>
                    <div class="relative">
                        <input type="password" id="password" name="password" required minlength="8" placeholder="Min 8 characters"
                               class="w-full bg-white/5 border border-pink-500/20 focus:border-pink-500/60 text-white placeholder-pink-200/30 rounded-2xl px-4 py-3 outline-none transition text-sm pr-12">
                        <button type="button" onclick="togglePassword('password', this)" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-350 transition text-sm">
                            👁️
                        </button>
                    </div>
                </div>

                <!-- Confirm Password -->
                <div>
                    <label for="confirm_password" class="block text-[10px] font-bold text-pink-200/40 mb-1.5 uppercase tracking-wider">Confirm Password</label>
                    <div class="relative">
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="8" placeholder="Re-enter password"
                               class="w-full bg-white/5 border border-pink-500/20 focus:border-pink-500/60 text-white placeholder-pink-200/30 rounded-2xl px-4 py-3 outline-none transition text-sm pr-12">
                        <button type="button" onclick="togglePassword('confirm_password', this)" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-350 transition text-sm">
                            👁️
                        </button>
                    </div>
                </div>

                <!-- Terms & Conditions Checkbox -->
                <div class="flex items-start gap-2 pt-1">
                    <input type="checkbox" id="agree_terms" name="agree_terms" required class="mt-0.5 rounded border-pink-500/20 bg-slate-900 text-pink-500 focus:ring-0 focus:ring-offset-0">
                    <label for="agree_terms" class="text-[10px] text-pink-200/40 leading-tight select-none">
                        I agree to the <a href="terms.php" target="_blank" class="text-pink-400 hover:underline">Terms of Service</a> and <a href="privacy.php" target="_blank" class="text-pink-400 hover:underline">Privacy Policy</a>
                    </label>
                </div>

                <!-- Submit Button -->
                <button type="submit" id="signup-btn" class="sp-btn-primary w-full py-4 text-base mt-2">
                    Create Account ✨
                </button>
            </form>

            <?php
            $g_client_id = get_setting('google_client_id', GOOGLE_CLIENT_ID);
            if ($g_client_id && $g_client_id !== 'YOUR_GOOGLE_CLIENT_ID'):
            ?>
            <div class="flex items-center gap-3 my-5">
                <div class="flex-1 h-px bg-pink-500/10"></div>
                <span class="text-xs" style="color:#94a3b8 !important">or</span>
                <div class="flex-1 h-px bg-pink-500/10"></div>
            </div>
            <script src="https://accounts.google.com/gsi/client" async defer></script>
            <div id="google-btn-wrap" style="display:flex;justify-content:center;margin-top:4px"></div>
            <script>
            function handleGoogleSignIn(response) {
                fetch('auth/google-onetap.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'credential=' + encodeURIComponent(response.credential)
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) window.location.href = data.redirect || 'dashboard.php';
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
                    theme: 'outline', size: 'large', width: 360, text: 'signup_with', shape: 'pill'
                });
            });
            </script>
            <?php endif; ?>

            <!-- Divider -->
            <div class="flex items-center my-6">
                <div class="flex-grow border-t border-pink-500/10"></div>
                <span class="px-3 text-[10px] text-pink-200/30 font-semibold">Already have an account?</span>
                <div class="flex-grow border-t border-pink-500/10"></div>
            </div>

            <!-- Login Link -->
            <a href="login.php" class="flex items-center justify-center gap-2 w-full bg-white/5 hover:bg-white/10 text-white font-semibold text-xs py-3.5 px-4 rounded-2xl transition border border-pink-500/10">
                <span>🔑</span>
                <span>Login Here</span>
            </a>
        </div>
    </div>

    <!-- Emoji Rain Script -->
    <script>


        function togglePassword(fieldId, btn) {
            const field = document.getElementById(fieldId);
            if (field.type === 'password') {
                field.type = 'text';
                btn.textContent = '🙈';
            } else {
                field.type = 'password';
                btn.textContent = '👁️';
            }
        }

        // Client-side validation
        document.getElementById('signup-form').addEventListener('submit', function(e) {
            const pw = document.getElementById('password').value;
            const cpw = document.getElementById('confirm_password').value;
            if (pw.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long!');
                return;
            }
            if (pw !== cpw) {
                e.preventDefault();
                alert('Passwords do not match!');
            }
        });
    </script>
</body>
</html>
