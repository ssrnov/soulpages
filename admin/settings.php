<?php
require_once '../includes/functions.php';

// Verify admin role
if (!is_logged_in() || !is_admin()) {
    redirect('login.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_settings') {
        $settings = $_POST['settings'] ?? [];
        
        // Handle OG Image file upload
        if (isset($_FILES['og_image_file']) && $_FILES['og_image_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['og_image_file'];
            $name = $file['name'];
            $tmp_name = $file['tmp_name'];
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                $upload_dir = '../uploads/og/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $filename = 'og_default_' . time() . '.' . $ext;
                $target = $upload_dir . $filename;
                
                if (move_uploaded_file($tmp_name, $target)) {
                    // Update setting with public URL path
                    $db_path = 'uploads/og/' . $filename;
                    $settings['og_image_url'] = SITE_URL . '/' . $db_path;
                } else {
                    $error = 'Failed to save uploaded image on server.';
                }
            } else {
                $error = 'Invalid image format. Allowed formats: JPG, JPEG, PNG, WEBP, GIF.';
            }
        }

        $updated = true;
        foreach ($settings as $key => $value) {
            if (!set_setting($key, trim($value))) {
                $updated = false;
            }
        }
        
        if ($updated) {
            $success = 'Settings updated successfully.';
        } else {
            $error = 'Failed to update some settings.';
        }
    } elseif ($action === 'change_password') {
        $old_pwd = $_POST['old_password'] ?? '';
        $new_pwd = $_POST['new_password'] ?? '';
        $confirm_pwd = $_POST['confirm_password'] ?? '';
        
        if (empty($old_pwd) || empty($new_pwd) || empty($confirm_pwd)) {
            $error = 'Please fill in all password fields.';
        } elseif ($new_pwd !== $confirm_pwd) {
            $error = 'New passwords do not match.';
        } elseif (strlen($new_pwd) < 6) {
            $error = 'New password must be at least 6 characters.';
        } else {
            // Fetch admin details
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $admin = $stmt->fetch();
            
            if ($admin && password_verify($old_pwd, $admin['password'])) {
                $hashed = password_hash($new_pwd, PASSWORD_BCRYPT);
                $stmt_upd = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                if ($stmt_upd->execute([$hashed, $_SESSION['user_id']])) {
                    $success = 'Password updated successfully.';
                } else {
                    $error = 'Database update failed.';
                }
            } else {
                $error = 'Incorrect current password.';
            }
        }
    }
}

// Fetch current values
$price_per_page = get_setting('price_per_page', '1000');
$free_pages_per_user = get_setting('free_pages_per_user', '1');
$razorpay_key_id = get_setting('razorpay_key_id', '');
$razorpay_key_secret = get_setting('razorpay_key_secret', '');
$google_client_id = get_setting('google_client_id', '');
$google_client_secret = get_setting('google_client_secret', '');
$site_title = get_setting('site_title', 'SoulSync - Express Your Feelings Beautifully');
$site_description = get_setting('site_description', 'Create emotional interactive webpages for proposals, birthdays, apologies and more.');
$homepage_h1 = get_setting('homepage_h1', 'Create a Beautiful, Interactive Emotional Story Page');
$og_image_url = get_setting('og_image_url', '');
$enable_ads = get_setting('enable_ads', '1');
$adsense_test_mode = get_setting('adsense_test_mode', '0');
$adsense_publisher_id = get_setting('adsense_publisher_id', 'ca-pub-5211469243507012');
$adsense_slot_homepage_1 = get_setting('adsense_slot_homepage_1', '9749842987');
$adsense_slot_homepage_2 = get_setting('adsense_slot_homepage_2', '9749842987');
$adsense_slot_login = get_setting('adsense_slot_login', '9749842987');
$adsense_slot_published_top = get_setting('adsense_slot_published_top', '9749842987');
$adsense_slot_published_bottom = get_setting('adsense_slot_published_bottom', '9749842987');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Global Settings - SoulSync Admin</title>
    <!-- Google Fonts -->
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
        body {
            background-color: #0f172a;
            background-image: 
                radial-gradient(at 0% 0%, rgba(236, 72, 153, 0.05) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(59, 130, 246, 0.05) 0px, transparent 50%);
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 font-sans min-h-screen flex flex-col justify-between antialiased">

    <!-- Header -->
    <?php $ADMIN_TITLE='Settings'; include '_nav.php'; ?>

    <main class="flex-grow max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <h1 class="text-2xl font-extrabold font-heading text-white mb-6">Global Application Settings</h1>

        <?php if (!empty($error)): ?>
            <div class="bg-red-500/15 border border-red-500/30 text-red-400 p-4 rounded-2xl mb-6 text-sm">
                ⚠️ <?= h($error) ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="bg-green-500/15 border border-green-500/30 text-green-400 p-4 rounded-2xl mb-6 text-sm">
                ✅ <?= h($success) ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left 2 Cols: Main Settings Form -->
            <div class="lg:col-span-2 space-y-6">
                <form action="settings.php" method="POST" enctype="multipart/form-data" class="space-y-6">
                    <input type="hidden" name="action" value="update_settings">

                    <!-- Pricing & Credits -->
                    <div class="glass-card rounded-3xl p-6">
                        <h2 class="text-lg font-bold font-heading text-white mb-4 flex items-center gap-2">
                            <span>💰</span> Pricing & Credit Model
                        </h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase">Price Per Page</label>
                                <select name="settings[price_per_page]" class="w-full bg-slate-900 border border-slate-800 text-white rounded-xl px-4 py-2.5 text-sm outline-none focus:border-pink-500">
                                    <option value="500" <?= $price_per_page === '500' ? 'selected' : '' ?>>₹5 per page</option>
                                    <option value="1000" <?= $price_per_page === '1000' ? 'selected' : '' ?>>₹10 per page</option>
                                    <option value="2000" <?= $price_per_page === '2000' ? 'selected' : '' ?>>₹20 per page</option>
                                    <option value="5000" <?= $price_per_page === '5000' ? 'selected' : '' ?>>₹50 per page</option>
                                    <option value="10000" <?= $price_per_page === '10000' ? 'selected' : '' ?>>₹100 per page</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase">Free Pages Limit per User</label>
                                <input type="number" name="settings[free_pages_per_user]" required min="0" value="<?= h($free_pages_per_user) ?>" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-2.5 text-sm outline-none text-white focus:border-pink-500">
                            </div>
                        </div>
                    </div>

                    <!-- API Keys -->
                    <div class="glass-card rounded-3xl p-6 space-y-4">
                        <h2 class="text-lg font-bold font-heading text-white mb-2 flex items-center gap-2">
                            <span>🔑</span> API Credentials
                        </h2>
                        
                        <div class="border-t border-slate-800/60 pt-4">
                            <h3 class="text-sm font-semibold text-pink-400 mb-3">Google OAuth & One Tap</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase">Client ID</label>
                                    <input type="text" name="settings[google_client_id]" placeholder="Enter Google Client ID" value="<?= h($google_client_id) ?>" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-2.5 text-sm outline-none text-white focus:border-pink-500 font-mono text-xs">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase">Client Secret</label>
                                    <input type="password" name="settings[google_client_secret]" placeholder="Enter Google Client Secret" value="<?= h($google_client_secret) ?>" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-2.5 text-sm outline-none text-white focus:border-pink-500 font-mono text-xs">
                                </div>
                            </div>
                        </div>

                        <div class="border-t border-slate-800/60 pt-4">
                            <h3 class="text-sm font-semibold text-purple-400 mb-3">Razorpay Gateway</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase">Razorpay Key ID</label>
                                    <input type="text" name="settings[razorpay_key_id]" placeholder="rzp_test_..." value="<?= h($razorpay_key_id) ?>" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-2.5 text-sm outline-none text-white focus:border-pink-500 font-mono text-xs">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase">Razorpay Key Secret</label>
                                    <input type="password" name="settings[razorpay_key_secret]" placeholder="••••••••" value="<?= h($razorpay_key_secret) ?>" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-2.5 text-sm outline-none text-white focus:border-pink-500 font-mono text-xs">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SEO Config -->
                    <div class="glass-card rounded-3xl p-6 space-y-4">
                        <h2 class="text-lg font-bold font-heading text-white mb-2 flex items-center gap-2">
                            <span>🔍</span> Search Engine Optimization (SEO)
                        </h2>
                        
                        <div class="grid grid-cols-1 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase">Site Meta Title</label>
                                <input type="text" name="settings[site_title]" required value="<?= h($site_title) ?>" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-2.5 text-sm outline-none text-white focus:border-pink-500">
                            </div>
                            
                            <div>
                                <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase">Homepage H1 Heading</label>
                                <input type="text" name="settings[homepage_h1]" required value="<?= h($homepage_h1) ?>" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-2.5 text-sm outline-none text-white focus:border-pink-500">
                            </div>
                            
                            <div>
                                <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase">Meta Description</label>
                                <textarea name="settings[site_description]" required rows="3" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-2.5 text-sm outline-none text-white focus:border-pink-500 leading-relaxed"><?= h($site_description) ?></textarea>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase">OpenGraph Image (OG Image)</label>
                                <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4 border border-slate-800 bg-slate-900/30 p-4 rounded-2xl">
                                    <?php if (!empty($og_image_url)): ?>
                                        <div class="w-20 h-20 rounded-xl overflow-hidden border border-slate-700 bg-slate-950 flex-shrink-0 flex items-center justify-center">
                                            <img src="<?= h($og_image_url) ?>" class="w-full h-full object-cover">
                                        </div>
                                    <?php else: ?>
                                        <div class="w-20 h-20 rounded-xl overflow-hidden border border-dashed border-slate-750 bg-slate-950/50 flex-shrink-0 flex items-center justify-center text-slate-600 text-[10px] text-center p-1">
                                            No Image
                                        </div>
                                    <?php endif; ?>
                                    <div class="flex-grow space-y-3 w-full">
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Upload New File</label>
                                            <input type="file" name="og_image_file" accept="image/*" class="w-full text-xs text-slate-400 file:mr-3 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-pink-500/10 file:text-pink-400 hover:file:bg-pink-500/20 cursor-pointer">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Or Enter URL Manually</label>
                                            <input type="text" name="settings[og_image_url]" placeholder="https://example.com/assets/og-image.jpg" value="<?= h($og_image_url) ?>" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-xs outline-none text-white focus:border-pink-500 font-mono">
                                        </div>
                                    </div>
                                </div>
                                <p class="text-[10px] text-slate-600 mt-1.5 leading-relaxed">Recommended size: 1200×630 pixels. This default image appears when users share their surprise links on social media.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Google AdSense -->
                    <div class="glass-card rounded-3xl p-6 space-y-4">
                        <h2 class="text-lg font-bold font-heading text-white mb-2 flex items-center gap-2">
                            <span>📢</span> Google AdSense
                        </h2>

                        <div class="flex items-center justify-between p-3 rounded-xl bg-slate-900/60 border border-slate-800">
                            <div>
                                <span class="text-sm font-semibold text-white">Enable Ads</span>
                                <p class="text-[10px] text-slate-500">Show Google AdSense banners across the site</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="hidden" name="settings[enable_ads]" value="0">
                                <input type="checkbox" name="settings[enable_ads]" value="1" <?= $enable_ads === '1' ? 'checked' : '' ?> class="sr-only peer">
                                <div class="w-9 h-5 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-green-500"></div>
                            </label>
                        </div>

                        <div class="flex items-center justify-between p-3 rounded-xl bg-slate-900/60 border border-slate-800">
                            <div>
                                <span class="text-sm font-semibold text-white">Enable Test Ads Mode</span>
                                <p class="text-[10px] text-slate-500">Use AdSense sandbox test mode (safe for localhost testing/verification)</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="hidden" name="settings[adsense_test_mode]" value="0">
                                <input type="checkbox" name="settings[adsense_test_mode]" value="1" <?= $adsense_test_mode === '1' ? 'checked' : '' ?> class="sr-only peer">
                                <div class="w-9 h-5 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-green-500"></div>
                            </label>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase">Publisher ID</label>
                            <input type="text" name="settings[adsense_publisher_id]" placeholder="ca-pub-1234567890" value="<?= h($adsense_publisher_id) ?>" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-2.5 text-sm outline-none text-white focus:border-pink-500 font-mono text-xs">
                            <p class="text-[10px] text-slate-600 mt-1">Your AdSense publisher ID (e.g. ca-pub-xxxxxxxxxxxxx)</p>
                        </div>

                        <div class="border-t border-slate-800/60 pt-4">
                            <h3 class="text-sm font-semibold text-green-400 mb-3">Ad Slot IDs (320×50 Banners)</h3>
                            <div class="grid grid-cols-1 gap-3">
                                <div>
                                    <label class="block text-[10px] font-semibold text-slate-400 mb-1 uppercase">Homepage — Between Stats & Categories</label>
                                    <input type="text" name="settings[adsense_slot_homepage_1]" placeholder="1234567890" value="<?= h($adsense_slot_homepage_1) ?>" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-2 text-sm outline-none text-white focus:border-pink-500 font-mono text-xs">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-semibold text-slate-400 mb-1 uppercase">Homepage — Between Categories & How It Works</label>
                                    <input type="text" name="settings[adsense_slot_homepage_2]" placeholder="1234567890" value="<?= h($adsense_slot_homepage_2) ?>" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-2 text-sm outline-none text-white focus:border-pink-500 font-mono text-xs">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-semibold text-slate-400 mb-1 uppercase">Login Page — Top Banner</label>
                                    <input type="text" name="settings[adsense_slot_login]" placeholder="1234567890" value="<?= h($adsense_slot_login) ?>" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-2 text-sm outline-none text-white focus:border-pink-500 font-mono text-xs">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-semibold text-slate-400 mb-1 uppercase">Published Page — Top (Loading Screen)</label>
                                    <input type="text" name="settings[adsense_slot_published_top]" placeholder="1234567890" value="<?= h($adsense_slot_published_top) ?>" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-2 text-sm outline-none text-white focus:border-pink-500 font-mono text-xs">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-semibold text-slate-400 mb-1 uppercase">Published Page — Below Visitor Replies</label>
                                    <input type="text" name="settings[adsense_slot_published_bottom]" placeholder="1234567890" value="<?= h($adsense_slot_published_bottom) ?>" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-2 text-sm outline-none text-white focus:border-pink-500 font-mono text-xs">
                                </div>
                            </div>
                        </div>
                    </div>


                    <!-- Submit Button -->
                    <div class="flex justify-end pt-4">
                        <button type="submit" class="w-full sm:w-auto px-10 py-4 bg-gradient-to-r from-pink-500 to-purple-500 text-white font-bold rounded-2xl text-xs uppercase tracking-widest transition shadow-lg hover:opacity-95 active:scale-95">
                            Save Configuration 💾
                        </button>
                    </div>
                </form>
            </div>

            <!-- Right Column: Admin Password & Meta -->
            <div class="space-y-6">
                <!-- Change password -->
                <div class="glass-card rounded-3xl p-6">
                    <h3 class="text-lg font-bold font-heading text-white mb-4">Change Admin Password</h3>
                    <form action="settings.php" method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase">Current Password</label>
                            <input type="password" name="old_password" required placeholder="••••••••" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-2.5 text-sm outline-none text-white focus:border-pink-500">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase">New Password</label>
                            <input type="password" name="new_password" required placeholder="••••••••" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-2.5 text-sm outline-none text-white focus:border-pink-500">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-400 mb-2 uppercase">Confirm New Password</label>
                            <input type="password" name="confirm_password" required placeholder="••••••••" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-2.5 text-sm outline-none text-white focus:border-pink-500">
                        </div>

                        <button type="submit" class="w-full py-3 bg-slate-900 hover:bg-slate-800 border border-slate-800 text-white font-bold rounded-xl text-xs uppercase tracking-widest transition shadow">
                            Change Password
                        </button>
                    </form>
                </div>

                <!-- Server Info Info Card -->
                <div class="glass-card rounded-3xl p-6 text-xs space-y-4">
                    <h3 class="text-sm font-bold font-heading text-white">System Information</h3>
                    
                    <div class="space-y-2 text-slate-400">
                        <div class="flex justify-between border-b border-slate-900 pb-1.5">
                            <span>PHP Version</span>
                            <span class="text-white font-mono"><?= phpversion() ?></span>
                        </div>
                        <div class="flex justify-between border-b border-slate-900 pb-1.5">
                            <span>Server API</span>
                            <span class="text-white"><?= php_sapi_name() ?></span>
                        </div>
                        <div class="flex justify-between border-b border-slate-900 pb-1.5">
                            <span>Database Driver</span>
                            <span class="text-white font-mono">PDO MySQL</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Upload Max Size</span>
                            <span class="text-white"><?= ini_get('upload_max_filesize') ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-slate-950 border-t border-slate-900 py-6 text-center text-slate-600 text-xs">
        &copy; <?= date('Y') ?> SoulSync Admin.
    </footer>

</body>
</html>
