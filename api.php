<?php
// SoulSync API (Phase 6)
require_once 'includes/functions.php';

header('Content-Type: application/json');

// Check for truncated POST request in API (exceeds post_max_size)
// Note: JSON POST requests have empty $_POST by default, so we ensure the request is not application/json.
$is_json_request = isset($_SERVER['CONTENT_TYPE']) && stripos($_SERVER['CONTENT_TYPE'], 'application/json') !== false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_json_request && empty($_POST) && empty($_FILES) && isset($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > 0) {
    $post_max_size = ini_get('post_max_size');
    echo json_encode([
        'success' => false,
        'error' => "The uploaded files are too large. The total request size exceeds the server limit of {$post_max_size}. Please try uploading smaller files."
    ]);
    exit;
}

// Global error/exception handler to always return JSON and log errors
function log_api_error($message) {
    $log_dir = __DIR__ . '/logs';
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }
    $log_file = $log_dir . '/api-errors.log';
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

set_exception_handler(function ($exception) {
    log_api_error('Exception: ' . $exception->getMessage() . ' in ' . $exception->getFile() . ' on line ' . $exception->getLine() . "\n" . $exception->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server Exception: ' . $exception->getMessage()
    ]);
    exit;
});

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    log_api_error("Error [$errno]: $errstr in $errfile on line $errline");
    if ($errno === E_USER_ERROR || $errno === E_RECOVERABLE_ERROR || $errno === E_ERROR || $errno === E_PARSE) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Server Fatal Error: ' . $errstr
        ]);
        exit;
    }
    return false;
});

$action = $_GET['action'] ?? '';

switch ($action) {

    // ──────────────────────────────────────────────────────
    // EXISTING: React to a page
    // ──────────────────────────────────────────────────────
    case 'react':
        $page_id = (int)($_POST['page_id'] ?? 0);
        $type = $_POST['type'] ?? '';
        if ($page_id > 0 && !empty($type)) {
            add_reaction($page_id, $type, $pdo);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid parameters.']);
        }
        break;

    // ──────────────────────────────────────────────────────
    // EXISTING: Publish a page
    // ──────────────────────────────────────────────────────
    case 'publish':
        $slug = $_POST['slug'] ?? '';
        if (empty($slug)) {
            echo json_encode(['success' => false, 'error' => 'No slug provided.']);
            break;
        }
        $stmt = $pdo->prepare("SELECT * FROM pages WHERE slug = ?");
        $stmt->execute([$slug]);
        $page = $stmt->fetch();
        if (!$page) {
            echo json_encode(['success' => false, 'error' => 'Page not found.']);
            break;
        }
        // Authorization
        $authorized = false;
        if (is_logged_in() && $page['user_id'] == $_SESSION['user_id']) $authorized = true;
        if (!$page['user_id'] && $page['guest_session_id'] === session_id()) $authorized = true;
        if (is_admin()) $authorized = true;

        if (!$authorized) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized.']);
            break;
        }
        // Get default expiry days from settings
        $expiry_days = (int)get_setting('default_expiry_days', '10');
        $expiry_date = date('Y-m-d H:i:s', strtotime("+{$expiry_days} days"));
        $storage_bytes = calculate_page_storage($page['id'], $pdo);

        $pdo->prepare("UPDATE pages SET status = 'published', expiry_date = ?, storage_bytes = ?, is_expired = 0 WHERE slug = ?")->execute([$expiry_date, $storage_bytes, $slug]);
        echo json_encode(['success' => true]);
        break;

    // ──────────────────────────────────────────────────────
    // EXISTING: Delete a page
    // ──────────────────────────────────────────────────────
    case 'delete_page':
        if (!is_logged_in()) {
            echo json_encode(['success' => false, 'error' => 'Login required.']);
            break;
        }
        $page_id = (int)($_POST['page_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM pages WHERE id = ?");
        $stmt->execute([$page_id]);
        $page = $stmt->fetch();
        if (!$page || ($page['user_id'] != $_SESSION['user_id'] && !is_admin())) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized.']);
            break;
        }
        // Delete associated images
        $imgs = $pdo->prepare("SELECT image_path, thumb_path, medium_path FROM page_images WHERE page_id = ?");
        $imgs->execute([$page_id]);
        foreach ($imgs->fetchAll() as $img) {
            if (file_exists($img['image_path'])) @unlink($img['image_path']);
            if (!empty($img['thumb_path']) && file_exists($img['thumb_path'])) @unlink($img['thumb_path']);
            if (!empty($img['medium_path']) && file_exists($img['medium_path'])) @unlink($img['medium_path']);
        }
        // Delete associated videos
        $vids = $pdo->prepare("SELECT video_path FROM page_videos WHERE page_id = ?");
        $vids->execute([$page_id]);
        foreach ($vids->fetchAll() as $vid) {
            if (file_exists($vid['video_path'])) @unlink($vid['video_path']);
        }
        // Delete main video/voice
        if (!empty($page['video_url']) && file_exists($page['video_url'])) @unlink($page['video_url']);
        if (!empty($page['voice_url']) && file_exists($page['voice_url'])) @unlink($page['voice_url']);
        if (!empty($page['letter_voice_url']) && file_exists($page['letter_voice_url'])) @unlink($page['letter_voice_url']);
        // Delete reply files
        $replies = $pdo->prepare("SELECT voice_path, image_path, video_path FROM page_replies WHERE page_id = ?");
        $replies->execute([$page_id]);
        foreach ($replies->fetchAll() as $r) {
            if (!empty($r['voice_path']) && file_exists($r['voice_path'])) @unlink($r['voice_path']);
            if (!empty($r['image_path']) && file_exists($r['image_path'])) @unlink($r['image_path']);
            if (!empty($r['video_path']) && file_exists($r['video_path'])) @unlink($r['video_path']);
        }
        $pdo->prepare("DELETE FROM pages WHERE id = ?")->execute([$page_id]);
        echo json_encode(['success' => true]);
        break;

    // ──────────────────────────────────────────────────────
    // EXISTING: Unlock password-protected page
    // ──────────────────────────────────────────────────────
    case 'unlock_page':
        $slug = $_POST['slug'] ?? '';
        $password = $_POST['password'] ?? '';
        if (empty($slug) || empty($password)) {
            echo json_encode(['success' => false, 'error' => 'Missing parameters.']);
            break;
        }
        $stmt = $pdo->prepare("SELECT id, password FROM pages WHERE slug = ?");
        $stmt->execute([$slug]);
        $page = $stmt->fetch();
        if (!$page || empty($page['password'])) {
            echo json_encode(['success' => false, 'error' => 'Page not found.']);
            break;
        }
        if (password_verify($password, $page['password'])) {
            $_SESSION['unlocked_pages'][$slug] = true;
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Wrong password.']);
        }
        break;

    // ──────────────────────────────────────────────────────
    // NEW: Create Razorpay Order
    // ──────────────────────────────────────────────────────
    case 'validate_coupon':
        if (!is_logged_in()) { echo json_encode(['success' => false, 'error' => 'Login required.']); break; }
        $ci = json_decode(file_get_contents('php://input'), true) ?? [];
        $c_plan = get_credit_plan(trim($ci['plan_id'] ?? ''));
        $c_base = $c_plan ? plan_effective_paise($c_plan) : get_price_per_page();
        $cp = validate_coupon($ci['coupon'] ?? '', $c_base);
        if (is_array($cp)) {
            $disc = coupon_discount_paise($cp, $c_base);
            echo json_encode(['success' => true, 'discount_paise' => $disc, 'final_paise' => $c_base - $disc, 'code' => $cp['code']]);
        } else {
            echo json_encode(['success' => false, 'error' => $cp]);
        }
        break;

    case 'create_razorpay_order':
        if (!is_logged_in()) {
            echo json_encode(['success' => false, 'error' => 'Login required.']);
            break;
        }
        // Plan-based pricing (admin-managed plans). Falls back to the single-credit price.
        $order_input = json_decode(file_get_contents('php://input'), true) ?? [];
        $plan_id = trim($order_input['plan_id'] ?? ($_POST['plan_id'] ?? ''));
        $plan = $plan_id !== '' ? get_credit_plan($plan_id) : null;
        $price_paise = $plan ? plan_effective_paise($plan) : get_price_per_page();
        $order_credits = $plan ? $plan['credits'] : 1;

        // Optional coupon (validated server-side)
        $coupon_code = strtoupper(trim($order_input['coupon'] ?? ''));
        if ($coupon_code !== '') {
            $cp = validate_coupon($coupon_code, $price_paise);
            if (is_array($cp)) {
                $price_paise -= coupon_discount_paise($cp, $price_paise);
            } else {
                echo json_encode(['success' => false, 'error' => $cp]);
                break;
            }
        }

        $razorpay_key = get_setting('razorpay_key_id', RAZORPAY_KEY_ID);
        $razorpay_secret = get_setting('razorpay_key_secret', RAZORPAY_KEY_SECRET);

        if (empty($razorpay_key) || empty($razorpay_secret)) {
            echo json_encode(['success' => false, 'error' => 'Payment gateway not configured.']);
            break;
        }

        $order_data = [
            'amount' => $price_paise,
            'currency' => 'INR',
            'receipt' => 'sp_' . $_SESSION['user_id'] . '_' . time(),
        ];

        $ch = curl_init('https://api.razorpay.com/v1/orders');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($order_data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, "$razorpay_key:$razorpay_secret");
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);

        if ($http_code === 200 && !empty($result['id'])) {
            // Insert payment record; credits_added holds the plan's credits until verified
            $pdo->prepare("INSERT INTO payments (user_id, razorpay_order_id, amount, currency, status, credits_added) VALUES (?, ?, ?, 'INR', 'created', ?)")
                ->execute([$_SESSION['user_id'], $result['id'], $price_paise, $order_credits]);
            if ($coupon_code !== '') $_SESSION['coupon_for_' . $result['id']] = $coupon_code;
            
            echo json_encode([
                'success' => true,
                'order_id' => $result['id'],
                'amount' => $price_paise,
                'key' => $razorpay_key,
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to create payment order.']);
        }
        break;

    // ──────────────────────────────────────────────────────
    // NEW: Verify Razorpay Payment
    // ──────────────────────────────────────────────────────
    case 'verify_payment':
        if (!is_logged_in()) {
            echo json_encode(['success' => false, 'error' => 'Login required.']);
            break;
        }
        $input = json_decode(file_get_contents('php://input'), true);
        $order_id = $input['razorpay_order_id'] ?? '';
        $payment_id = $input['razorpay_payment_id'] ?? '';
        $signature = $input['razorpay_signature'] ?? '';

        if (empty($order_id) || empty($payment_id) || empty($signature)) {
            echo json_encode(['success' => false, 'error' => 'Missing payment details.']);
            break;
        }

        $razorpay_secret = get_setting('razorpay_key_secret', RAZORPAY_KEY_SECRET);

        // Verify signature
        $expected_signature = hash_hmac('sha256', $order_id . '|' . $payment_id, $razorpay_secret);

        if ($expected_signature === $signature) {
            // How many credits was this order for? (set at order creation; min 1)
            $stmt_cr = $pdo->prepare("SELECT credits_added, status FROM payments WHERE razorpay_order_id = ? AND user_id = ?");
            $stmt_cr->execute([$order_id, $_SESSION['user_id']]);
            $pay_row = $stmt_cr->fetch();
            $credits_to_add = max(1, (int)($pay_row['credits_added'] ?? 1));

            // Guard against double-crediting on repeated verify calls
            if (($pay_row['status'] ?? '') === 'captured') {
                echo json_encode(['success' => true]);
                break;
            }

            $pdo->prepare("UPDATE payments SET razorpay_payment_id = ?, razorpay_signature = ?, status = 'captured' WHERE razorpay_order_id = ? AND user_id = ?")
                ->execute([$payment_id, $signature, $order_id, $_SESSION['user_id']]);

            add_credits($_SESSION['user_id'], $credits_to_add);

            // Count coupon usage (if one was applied to this order)
            if (!empty($_SESSION['coupon_for_' . $order_id])) {
                increment_coupon_usage($_SESSION['coupon_for_' . $order_id]);
                unset($_SESSION['coupon_for_' . $order_id]);
            }

            echo json_encode(['success' => true, 'credits_added' => $credits_to_add]);
        } else {
            // Mark as failed
            $pdo->prepare("UPDATE payments SET razorpay_payment_id = ?, status = 'failed' WHERE razorpay_order_id = ? AND user_id = ?")
                ->execute([$payment_id, $order_id, $_SESSION['user_id']]);
            echo json_encode(['success' => false, 'error' => 'Payment verification failed.']);
        }
        break;

    // ──────────────────────────────────────────────────────
    // NEW: Extend Page Expiry
    // ──────────────────────────────────────────────────────
    case 'extend_expiry':
        if (!is_logged_in()) {
            echo json_encode(['success' => false, 'error' => 'Login required.']);
            break;
        }
        $page_id = (int)($_POST['page_id'] ?? 0);
        $days = (int)($_POST['days'] ?? 10);
        
        if ($days <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid number of days.']);
            break;
        }

        $stmt = $pdo->prepare("SELECT * FROM pages WHERE id = ?");
        $stmt->execute([$page_id]);
        $page = $stmt->fetch();
        if (!$page) {
            echo json_encode(['success' => false, 'error' => 'Page not found.']);
            break;
        }

        // Authorization (only page owner or admin can extend)
        if ($page['user_id'] != $_SESSION['user_id'] && !is_admin()) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized.']);
            break;
        }

        // Calculate credits needed: 1 credit per 10 days
        $credits_needed = ceil($days / 10);
        $user_credits = get_user_credits($_SESSION['user_id']);

        if ($user_credits < $credits_needed) {
            echo json_encode(['success' => false, 'error' => 'Insufficient credits. Please purchase more credits first.']);
            break;
        }

        // Deduct credits
        $stmt_deduct = $pdo->prepare("UPDATE users SET credits = credits - ? WHERE id = ? AND credits >= ?");
        if ($stmt_deduct->execute([$credits_needed, $_SESSION['user_id'], $credits_needed])) {
            $current_expiry = $page['expiry_date'];
            if (!$current_expiry || strtotime($current_expiry) < time()) {
                $new_expiry = date('Y-m-d H:i:s', strtotime("+{$days} days"));
            } else {
                $new_expiry = date('Y-m-d H:i:s', strtotime($current_expiry . " +{$days} days"));
            }

            // Update page
            $pdo->prepare("UPDATE pages SET expiry_date = ?, is_expired = 0 WHERE id = ?")->execute([$new_expiry, $page_id]);

            // Log extension
            $amount_paise = $credits_needed * 1000;
            $pdo->prepare("INSERT INTO expiry_extensions (page_id, user_id, days_added, amount_paise, payment_id) VALUES (?, ?, ?, ?, 'CREDIT_DEDUCTION')")
                ->execute([$page_id, $_SESSION['user_id'], $days, $amount_paise]);

            echo json_encode([
                'success' => true,
                'new_expiry' => date('d M Y, h:i A', strtotime($new_expiry)),
                'credits_left' => ($user_credits - $credits_needed)
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to deduct credits.']);
        }
        break;

    // ──────────────────────────────────────────────────────
    // NEW: Track View/Session Watch Time & Completion
    // ──────────────────────────────────────────────────────
    case 'track_session':
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $page_id = (int)($input['page_id'] ?? 0);
        $watch_time = (int)($input['watch_time'] ?? 0);
        $completed = (int)($input['completed'] ?? 0);
        $ip = get_client_ip();
        
        if ($page_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid page ID.']);
            break;
        }
        
        // Find the latest page_view ID for this page and IP in the last 12 hours
        $stmt_find = $pdo->prepare("SELECT id FROM page_views WHERE page_id = ? AND ip = ? ORDER BY viewed_at DESC LIMIT 1");
        $stmt_find->execute([$page_id, $ip]);
        $view_id = $stmt_find->fetchColumn();
        
        if ($view_id) {
            // Update the view row
            $pdo->prepare("UPDATE page_views SET watch_time_seconds = ?, completed = ? WHERE id = ?")
                ->execute([$watch_time, $completed, $view_id]);
            echo json_encode(['success' => true]);
        } else {
            // Fallback: insert a new row if none found
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $pdo->prepare("INSERT INTO page_views (page_id, ip, user_agent, watch_time_seconds, completed) VALUES (?, ?, ?, ?, ?)")
                ->execute([$page_id, $ip, $user_agent, $watch_time, $completed]);
            echo json_encode(['success' => true]);
        }
        break;

    // ──────────────────────────────────────────────────────
    // NEW: Submit Reply (text, emoji, or file upload)
    // ──────────────────────────────────────────────────────
    case 'submit_reply':
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $params = array_merge($_POST, $input);

        $page_id = (int)($params['page_id'] ?? 0);
        $reply_type = $params['reply_type'] ?? 'text';
        $message = trim($params['message'] ?? $params['reply_text'] ?? '');
        $visitor_name = trim($params['visitor_name'] ?? $params['sender_name'] ?? 'Anonymous');
        $ip = get_client_ip();

        if ($page_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid page.']);
            break;
        }

        // Rate limit: max 5 replies per IP per page per hour
        $rate = $pdo->prepare("SELECT COUNT(*) FROM page_replies WHERE page_id = ? AND ip = ? AND created_at > NOW() - INTERVAL 1 HOUR");
        $rate->execute([$page_id, $ip]);
        if ($rate->fetchColumn() >= 5) {
            echo json_encode(['success' => false, 'error' => 'Too many replies. Please wait a while.']);
            break;
        }

        $voice_path = null;
        $image_path = null;
        $video_path = null;

        if ($reply_type === 'text' || $reply_type === 'emoji') {
            if (empty($message)) {
                echo json_encode(['success' => false, 'error' => 'Reply message is required.']);
                break;
            }
            if (strlen($message) > 1000) {
                $message = substr($message, 0, 1000);
            }
        } elseif (in_array($reply_type, ['voice', 'image', 'video'])) {
            if (!empty($_FILES['reply_file']) && $_FILES['reply_file']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['reply_file'];
                $file_ext = strtolower(pathinfo(basename($file['name']), PATHINFO_EXTENSION));
                
                // Specific validations
                if ($reply_type === 'voice') {
                    $allowed_exts = ['mp3', 'm4a', 'aac', 'acc', 'wav'];
                } elseif ($reply_type === 'video') {
                    $allowed_exts = ['mp4', 'mov', 'webm'];
                } else { // image
                    $allowed_exts = ['jpg', 'jpeg', 'png', 'webp'];
                }
                
                if (!in_array($file_ext, $allowed_exts)) {
                    echo json_encode(['success' => false, 'error' => 'Invalid file extension. Expected: ' . implode(', ', $allowed_exts)]);
                    break;
                }
                
                // Perform upload
                $upload_res = upload_reply_file($file);
                if ($upload_res['success']) {
                    if ($reply_type === 'voice') {
                        $voice_path = $upload_res['path'];
                    } elseif ($reply_type === 'video') {
                        $video_path = $upload_res['path'];
                    } else {
                        $image_path = $upload_res['path'];
                    }
                } else {
                    echo json_encode(['success' => false, 'error' => $upload_res['message']]);
                    break;
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'File upload required for this reply type.']);
                break;
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid reply type.']);
            break;
        }

        $stmt = $pdo->prepare("INSERT INTO page_replies (page_id, visitor_name, reply_type, message, voice_path, image_path, video_path, ip, is_read) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)");
        $stmt->execute([$page_id, $visitor_name, $reply_type, $message !== '' ? $message : null, $voice_path, $image_path, $video_path, $ip]);

        // Recalculate and update storage
        $storage_bytes = calculate_page_storage($page_id, $pdo);
        $pdo->prepare("UPDATE pages SET storage_bytes = ? WHERE id = ?")->execute([$storage_bytes, $page_id]);

        echo json_encode(['success' => true, 'message' => 'Reply sent!']);
        break;

    // ──────────────────────────────────────────────────────
    // UNIVERSAL INTERACTIVE REPLY SYSTEM: Save / Get Replies & Notifications
    // ──────────────────────────────────────────────────────
    case 'submit_interactive_reply':
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $params = array_merge($_POST, $input);

        $page_id = (int)($params['page_id'] ?? 0);
        $visitor_name = trim($params['visitor_name'] ?? 'Anonymous');
        if (empty($visitor_name)) $visitor_name = 'Anonymous';
        $question = trim($params['question'] ?? '');
        $selected_answer = trim(strtolower($params['selected_answer'] ?? ''));
        $positive_button_text = trim($params['positive_button_text'] ?? '');
        $negative_button_text = trim($params['negative_button_text'] ?? '');
        $no_click_count = (int)($params['no_click_count'] ?? 0);

        if ($page_id <= 0 || empty($selected_answer)) {
            echo json_encode(['success' => false, 'error' => 'Invalid parameters.']);
            break;
        }

        // Fetch page details
        $stmt = $pdo->prepare("SELECT * FROM pages WHERE id = ?");
        $stmt->execute([$page_id]);
        $page = $stmt->fetch();
        if (!$page) {
            echo json_encode(['success' => false, 'error' => 'Page not found.']);
            break;
        }

        // Detect browser & device
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $browser = "Unknown Browser";
        $device = "Desktop";
        if (preg_match('/mobile/i', $ua)) {
            $device = "Mobile";
        } elseif (preg_match('/tablet/i', $ua) || preg_match('/ipad/i', $ua)) {
            $device = "Tablet";
        }
        if (preg_match('/chrome/i', $ua)) {
            $browser = "Chrome";
        } elseif (preg_match('/safari/i', $ua) && !preg_match('/chrome/i', $ua)) {
            $browser = "Safari";
        } elseif (preg_match('/firefox/i', $ua)) {
            $browser = "Firefox";
        } elseif (preg_match('/edge/i', $ua)) {
            $browser = "Edge";
        }

        $ip = get_client_ip();
        $country = $_SERVER["HTTP_CF_IPCOUNTRY"] ?? 'Unknown';
        $city = 'Unknown';

        // Insert interactive reply
        $stmt_ins = $pdo->prepare("INSERT INTO interactive_replies (page_id, category, visitor_name, question, selected_answer, positive_button_text, negative_button_text, no_click_count, browser, device, country, city, visitor_ip) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_ins->execute([$page_id, $page['category'], $visitor_name, $question, $selected_answer, $positive_button_text, $negative_button_text, $no_click_count, $browser, $device, $country, $city, $ip]);

        // Insert notification if page owner exists
        if (!empty($page['user_id'])) {
            $notif_msg = "";
            $page_title = $page['title'];
            if ($selected_answer === 'yes') {
                $notif_msg = "💖 {$visitor_name} answered YES to your question on page \"{$page_title}\"!";
            } else {
                $notif_msg = "😢 {$visitor_name} answered NO to your question on page \"{$page_title}\".";
            }
            
            // Insert notification
            $stmt_notif = $pdo->prepare("INSERT INTO notifications (user_id, page_id, type, message, is_read) VALUES (?, ?, 'interactive_reply', ?, 0)");
            $stmt_notif->execute([$page['user_id'], $page_id, $notif_msg]);
        }

        echo json_encode(['success' => true]);
        break;

    case 'get_interactive_replies':
        if (!is_logged_in()) {
            echo json_encode(['success' => false, 'error' => 'Login required.']);
            break;
        }
        $page_id = (int)($_GET['page_id'] ?? 0);
        if ($page_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid page ID.']);
            break;
        }
        // Verify ownership
        $stmt = $pdo->prepare("SELECT user_id FROM pages WHERE id = ?");
        $stmt->execute([$page_id]);
        $page = $stmt->fetch();
        if (!$page || ($page['user_id'] != $_SESSION['user_id'] && !is_admin())) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized.']);
            break;
        }

        $stmt_replies = $pdo->prepare("SELECT * FROM interactive_replies WHERE page_id = ? ORDER BY created_at DESC");
        $stmt_replies->execute([$page_id]);
        $replies = $stmt_replies->fetchAll();
        echo json_encode(['success' => true, 'replies' => $replies]);
        break;

    case 'delete_interactive_reply':
        if (!is_logged_in()) {
            echo json_encode(['success' => false, 'error' => 'Login required.']);
            break;
        }
        $reply_id = (int)($_POST['reply_id'] ?? $_GET['reply_id'] ?? 0);
        if ($reply_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid reply ID.']);
            break;
        }
        // Verify ownership
        $stmt = $pdo->prepare("SELECT ir.*, p.user_id FROM interactive_replies ir JOIN pages p ON ir.page_id = p.id WHERE ir.id = ?");
        $stmt->execute([$reply_id]);
        $reply = $stmt->fetch();
        if (!$reply || ($reply['user_id'] != $_SESSION['user_id'] && !is_admin())) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized.']);
            break;
        }

        $pdo->prepare("DELETE FROM interactive_replies WHERE id = ?")->execute([$reply_id]);
        echo json_encode(['success' => true]);
        break;

    case 'get_notifications':
        if (!is_logged_in()) {
            echo json_encode(['success' => false, 'error' => 'Login required.']);
            break;
        }
        $stmt = $pdo->prepare("SELECT n.*, p.slug as page_slug FROM notifications n LEFT JOIN pages p ON n.page_id = p.id WHERE n.user_id = ? ORDER BY n.created_at DESC LIMIT 50");
        $stmt->execute([$_SESSION['user_id']]);
        $notifications = $stmt->fetchAll();

        // Get unread count
        $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt_count->execute([$_SESSION['user_id']]);
        $unread_count = (int)$stmt_count->fetchColumn();

        echo json_encode(['success' => true, 'notifications' => $notifications, 'unread_count' => $unread_count]);
        break;

    case 'mark_notification_read':
        if (!is_logged_in()) {
            echo json_encode(['success' => false, 'error' => 'Login required.']);
            break;
        }
        $notif_id = (int)($_POST['notification_id'] ?? $_GET['notification_id'] ?? 0);
        if ($notif_id > 0) {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
            $stmt->execute([$notif_id, $_SESSION['user_id']]);
        }
        echo json_encode(['success' => true]);
        break;

    case 'mark_all_notifications_read':
        if (!is_logged_in()) {
            echo json_encode(['success' => false, 'error' => 'Login required.']);
            break;
        }
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        echo json_encode(['success' => true]);
        break;

    case 'get_replies':
        if (!is_logged_in()) {
            echo json_encode(['success' => false, 'login_required' => true, 'error' => 'Login required.']);
            break;
        }
        $page_id = (int)($_GET['page_id'] ?? 0);
        if ($page_id > 0) {
            // Verify ownership
            $stmt = $pdo->prepare("SELECT user_id FROM pages WHERE id = ?");
            $stmt->execute([$page_id]);
            $page = $stmt->fetch();
            if (!$page || ($page['user_id'] != $_SESSION['user_id'] && !is_admin())) {
                echo json_encode(['success' => false, 'login_required' => true, 'error' => 'Unauthorized.']);
                break;
            }
            $stmt_replies = $pdo->prepare("SELECT pr.*, p.title as page_title, p.slug as page_slug FROM page_replies pr JOIN pages p ON pr.page_id = p.id WHERE pr.page_id = ? ORDER BY pr.created_at DESC");
            $stmt_replies->execute([$page_id]);
            $replies = $stmt_replies->fetchAll();
        } else {
            // Get all replies for all pages owned by current user
            if (is_admin()) {
                $stmt_replies = $pdo->prepare("SELECT pr.*, p.title as page_title, p.slug as page_slug FROM page_replies pr JOIN pages p ON pr.page_id = p.id ORDER BY pr.created_at DESC");
                $stmt_replies->execute();
            } else {
                $stmt_replies = $pdo->prepare("SELECT pr.*, p.title as page_title, p.slug as page_slug FROM page_replies pr JOIN pages p ON pr.page_id = p.id WHERE p.user_id = ? ORDER BY pr.created_at DESC");
                $stmt_replies->execute([$_SESSION['user_id']]);
            }
            $replies = $stmt_replies->fetchAll();
        }
        echo json_encode(['success' => true, 'replies' => $replies]);
        break;

    // ──────────────────────────────────────────────────────
    // NEW: Delete a reply (creator or admin)
    // ──────────────────────────────────────────────────────
    case 'delete_reply':
        if (!is_logged_in()) {
            echo json_encode(['success' => false, 'error' => 'Login required.']);
            break;
        }
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $params = array_merge($_POST, $input);
        $reply_id = (int)($params['reply_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT pr.*, p.user_id as page_owner FROM page_replies pr JOIN pages p ON pr.page_id = p.id WHERE pr.id = ?");
        $stmt->execute([$reply_id]);
        $reply = $stmt->fetch();
        if (!$reply || ($reply['page_owner'] != $_SESSION['user_id'] && !is_admin())) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized.']);
            break;
        }
        if (!empty($reply['voice_path']) && file_exists($reply['voice_path'])) @unlink($reply['voice_path']);
        if (!empty($reply['image_path']) && file_exists($reply['image_path'])) @unlink($reply['image_path']);
        if (!empty($reply['video_path']) && file_exists($reply['video_path'])) @unlink($reply['video_path']);
        
        $pdo->prepare("DELETE FROM page_replies WHERE id = ?")->execute([$reply_id]);
        
        // Recalculate and update storage
        $page_id = (int)$reply['page_id'];
        $storage_bytes = calculate_page_storage($page_id, $pdo);
        $pdo->prepare("UPDATE pages SET storage_bytes = ? WHERE id = ?")->execute([$storage_bytes, $page_id]);

        echo json_encode(['success' => true]);
        break;

    // ──────────────────────────────────────────────────────
    // NEW: Mark reply as read
    // ──────────────────────────────────────────────────────
    case 'mark_reply_read':
        if (!is_logged_in()) {
            echo json_encode(['success' => false, 'error' => 'Login required.']);
            break;
        }
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $params = array_merge($_POST, $input);
        $reply_id = (int)($params['reply_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT pr.id, p.user_id as page_owner FROM page_replies pr JOIN pages p ON pr.page_id = p.id WHERE pr.id = ?");
        $stmt->execute([$reply_id]);
        $reply = $stmt->fetch();
        if (!$reply || ($reply['page_owner'] != $_SESSION['user_id'] && !is_admin())) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized.']);
            break;
        }
        $pdo->prepare("UPDATE page_replies SET is_read = 1 WHERE id = ?")->execute([$reply_id]);
        echo json_encode(['success' => true]);
        break;

    // ──────────────────────────────────────────────────────
    // NEW: Mark all replies as read
    // ──────────────────────────────────────────────────────
    case 'mark_all_read':
        if (!is_logged_in()) {
            echo json_encode(['success' => false, 'error' => 'Login required.']);
            break;
        }
        if (is_admin()) {
            $pdo->prepare("UPDATE page_replies SET is_read = 1")->execute();
        } else {
            $pdo->prepare("UPDATE page_replies pr JOIN pages p ON pr.page_id = p.id SET pr.is_read = 1 WHERE p.user_id = ?")->execute([$_SESSION['user_id']]);
        }
        echo json_encode(['success' => true]);
        break;

    // ──────────────────────────────────────────────────────
    // NEW: Get unread count
    // ──────────────────────────────────────────────────────
    case 'get_unread_count':
        if (!is_logged_in()) {
            echo json_encode(['success' => false, 'error' => 'Login required.']);
            break;
        }
        if (is_admin()) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM page_replies WHERE is_read = 0");
            $stmt->execute();
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM page_replies pr JOIN pages p ON pr.page_id = p.id WHERE p.user_id = ? AND pr.is_read = 0");
            $stmt->execute([$_SESSION['user_id']]);
        }
        $count = (int)$stmt->fetchColumn();
        echo json_encode(['success' => true, 'count' => $count]);
        break;

    // ──────────────────────────────────────────────────────
    // ADMIN: Update Site Setting
    // ──────────────────────────────────────────────────────
    case 'admin_update_setting':
        if (!is_logged_in() || !is_admin()) {
            echo json_encode(['success' => false, 'error' => 'Admin access required.']);
            break;
        }
        $key = $_POST['setting_key'] ?? '';
        $value = $_POST['setting_value'] ?? '';
        if (empty($key)) {
            echo json_encode(['success' => false, 'error' => 'Missing setting key.']);
            break;
        }
        set_setting($key, $value);
        echo json_encode(['success' => true]);
        break;

    // ──────────────────────────────────────────────────────
    // ADMIN: Give Credits to User
    // ──────────────────────────────────────────────────────
    case 'admin_give_credits':
        if (!is_logged_in() || !is_admin()) {
            echo json_encode(['success' => false, 'error' => 'Admin access required.']);
            break;
        }
        $user_id = (int)($_POST['user_id'] ?? 0);
        $credits = (int)($_POST['credits'] ?? 0);
        if ($user_id <= 0 || $credits <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid parameters.']);
            break;
        }
        add_credits($user_id, $credits);
        echo json_encode(['success' => true, 'message' => "Added $credits credits."]);
        break;

    // ──────────────────────────────────────────────────────
    // ADMIN: Suspend/Activate User
    // ──────────────────────────────────────────────────────
    case 'admin_toggle_user_status':
        if (!is_logged_in() || !is_admin()) {
            echo json_encode(['success' => false, 'error' => 'Admin access required.']);
            break;
        }
        $user_id = (int)($_POST['user_id'] ?? 0);
        $new_status = $_POST['status'] ?? '';
        if ($user_id <= 0 || !in_array($new_status, ['active', 'suspended'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid parameters.']);
            break;
        }
        $pdo->prepare("UPDATE users SET status = ? WHERE id = ? AND role = 'user'")->execute([$new_status, $user_id]);
        echo json_encode(['success' => true]);
        break;

    // ──────────────────────────────────────────────────────
    // ADMIN: Delete User
    // ──────────────────────────────────────────────────────
    case 'admin_delete_user':
        if (!is_logged_in() || !is_admin()) {
            echo json_encode(['success' => false, 'error' => 'Admin access required.']);
            break;
        }
        $user_id = (int)($_POST['user_id'] ?? 0);
        if ($user_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid user ID.']);
            break;
        }
        // Delete user's page files first
        $pages = $pdo->prepare("SELECT id, video_url, voice_url, letter_voice_url FROM pages WHERE user_id = ?");
        $pages->execute([$user_id]);
        foreach ($pages->fetchAll() as $p) {
            // Delete images
            $imgs = $pdo->prepare("SELECT image_path, thumb_path, medium_path FROM page_images WHERE page_id = ?");
            $imgs->execute([$p['id']]);
            foreach ($imgs->fetchAll() as $img) {
                if (file_exists($img['image_path'])) @unlink($img['image_path']);
                if (!empty($img['thumb_path']) && file_exists($img['thumb_path'])) @unlink($img['thumb_path']);
                if (!empty($img['medium_path']) && file_exists($img['medium_path'])) @unlink($img['medium_path']);
            }
            if (!empty($p['video_url']) && file_exists($p['video_url'])) @unlink($p['video_url']);
            if (!empty($p['voice_url']) && file_exists($p['voice_url'])) @unlink($p['voice_url']);
            if (!empty($p['letter_voice_url']) && file_exists($p['letter_voice_url'])) @unlink($p['letter_voice_url']);
        }
        $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'user'")->execute([$user_id]);
        echo json_encode(['success' => true]);
        break;

    // ──────────────────────────────────────────────────────
    // ADMIN: Upload Music
    // ──────────────────────────────────────────────────────
    case 'upload_music':
        if (!is_logged_in() || !is_admin()) {
            echo json_encode(['success' => false, 'error' => 'Admin access required.']);
            break;
        }
        $title = trim($_POST['title'] ?? '');
        $category = trim($_POST['category'] ?? 'all');
        if (empty($title) || empty($_FILES['music_file'])) {
            echo json_encode(['success' => false, 'error' => 'Title and file are required.']);
            break;
        }
        $result = upload_voice($_FILES['music_file'], 'assets/music/');
        if ($result['success']) {
            $pdo->prepare("INSERT INTO music_library (title, file_path, category) VALUES (?, ?, ?)")
                ->execute([$title, $result['path'], $category]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $result['message']]);
        }
        break;

    // ──────────────────────────────────────────────────────
    // ADMIN: Delete Music
    // ──────────────────────────────────────────────────────
    case 'delete_music':
        if (!is_logged_in() || !is_admin()) {
            echo json_encode(['success' => false, 'error' => 'Admin access required.']);
            break;
        }
        $music_id = (int)($_POST['music_id'] ?? 0);
        if ($music_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid music ID.']);
            break;
        }
        $stmt = $pdo->prepare("SELECT file_path FROM music_library WHERE id = ?");
        $stmt->execute([$music_id]);
        $music = $stmt->fetch();
        if ($music && file_exists($music['file_path'])) {
            @unlink($music['file_path']);
        }
        $pdo->prepare("DELETE FROM music_library WHERE id = ?")->execute([$music_id]);
        echo json_encode(['success' => true]);
        break;

    // ──────────────────────────────────────────────────────
    // EDITOR: Edit Page Details
    // ──────────────────────────────────────────────────────
    case 'edit_details':
        $input = json_decode(file_get_contents('php://input'), true);
        $page_id = (int)($input['page_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM pages WHERE id = ?");
        $stmt->execute([$page_id]);
        $page = $stmt->fetch();
        if (!$page) {
            echo json_encode(['success' => false, 'message' => 'Page not found.']);
            break;
        }
        $authorized = false;
        if (is_logged_in() && $page['user_id'] == $_SESSION['user_id']) $authorized = true;
        if (empty($page['user_id']) && $page['guest_session_id'] === session_id()) $authorized = true;
        if (is_admin()) $authorized = true;

        if (!$authorized) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
            break;
        }

        $sender_name = trim($input['sender_name'] ?? '');
        $receiver_name = trim($input['receiver_name'] ?? '');
        $title = trim($input['title'] ?? '');
        $letter_text = trim($input['letter_text'] ?? '');
        $proposal_question = trim($input['proposal_question'] ?? '');
        $accent_color = trim($input['accent_color'] ?? '');
        $font_style = trim($input['font_style'] ?? '');
        $photo_fit_mode = trim($input['photo_fit_mode'] ?? 'cover');
        $nickname = trim($input['nickname'] ?? '');
        $relationship_date = trim($input['relationship_date'] ?? '');
        $theme = trim($input['theme'] ?? '');

        // Universal Interactive Ending fields
        $interactive_ending = isset($input['interactive_ending']) && (int)$input['interactive_ending'] === 1 ? 1 : 0;
        $interactive_question = trim($input['interactive_question'] ?? '');
        $interactive_yes_text = trim($input['interactive_yes_text'] ?? '');
        $interactive_no_text = trim($input['interactive_no_text'] ?? '');
        $interactive_funny_no = isset($input['interactive_funny_no']) && (int)$input['interactive_funny_no'] === 1 ? 1 : 0;
        $interactive_ask_name = isset($input['interactive_ask_name']) && (int)$input['interactive_ask_name'] === 1 ? 1 : 0;

        if (empty($interactive_question)) {
            $interactive_question = $proposal_question;
        }
        if (empty($proposal_question)) {
            $proposal_question = $interactive_question;
        }

        if (!in_array($photo_fit_mode, ['cover', 'contain'])) {
            $photo_fit_mode = 'cover';
        }

        if (empty($sender_name) || empty($receiver_name)) {
            echo json_encode(['success' => false, 'message' => 'Sender and Recipient names are required.']);
            break;
        }

        $slide_data = $input['slide_data'] ?? [];
        $existing_slide_data = decode_slide_data($page['slide_data'] ?? '');
        $merged_slide_data = array_merge($existing_slide_data, $slide_data);
        $slide_data_json = !empty($merged_slide_data) ? json_encode($merged_slide_data) : null;

        $days = 10;
        if (isset($input['expiry_duration'])) {
            if ($input['expiry_duration'] === 'custom') {
                $days = (int)($input['custom_days'] ?? 10);
            } else {
                $days = (int)$input['expiry_duration'];
            }
        }
        if ($days < 10) $days = 10;

        // Verify credit sufficiency and deduct difference if logged in
        $expiry_date = $page['expiry_date'];
        if ($page['user_id']) {
            $user_id = $page['user_id'];
            $user_prof = get_user_profile($user_id);
            $user_credits = get_user_credits($user_id);
            $free_limit = (int)get_setting('free_pages_per_user', DEFAULT_FREE_PAGES);
            $free_pages_used = (int)$user_prof['free_pages_used'];

            // Calculate old days duration
            $old_days = 10;
            if (!empty($page['expiry_date']) && !empty($page['created_at'])) {
                $diff = strtotime($page['expiry_date']) - strtotime($page['created_at']);
                if ($diff > 0) {
                    $old_days = round($diff / 86400);
                }
            }

            // Calculate costs
            if ($free_pages_used <= $free_limit) {
                $old_cost = ($old_days <= 10) ? 0 : ceil(($old_days - 10) / 10);
                $new_cost = ($days <= 10) ? 0 : ceil(($days - 10) / 10);
            } else {
                $old_cost = ceil($old_days / 10);
                $new_cost = ceil($days / 10);
            }

            $credit_diff = $new_cost - $old_cost;
            if ($credit_diff > 0) {
                if ($user_credits < $credit_diff) {
                    echo json_encode(['success' => false, 'message' => "Insufficient credits. You need $credit_diff more credits for this duration."]);
                    break;
                }
                // Deduct credits difference
                $pdo->prepare("UPDATE users SET credits = credits - ? WHERE id = ?")->execute([$credit_diff, $user_id]);
            }
            
            // Calculate expiry_date relative to created_at
            $created_time = strtotime($page['created_at']);
            $expiry_date = date('Y-m-d H:i:s', $created_time + ($days * 86400));
        } else {
            // Guest draft: just calculate relative to created_at
            $created_time = strtotime($page['created_at']);
            $expiry_date = date('Y-m-d H:i:s', $created_time + ($days * 86400));
        }

        $stmt_upd = $pdo->prepare("UPDATE pages SET 
            sender_name = ?, receiver_name = ?, title = ?, letter_text = ?, 
            proposal_question = ?, accent_color = ?, font_style = ?, 
            photo_fit_mode = ?, nickname = ?, relationship_date = ?, theme = ?,
            slide_data = ?, expiry_date = ?,
            interactive_ending = ?, interactive_question = ?, interactive_yes_text = ?, interactive_no_text = ?, interactive_funny_no = ?, interactive_ask_name = ?
            WHERE id = ?");
        
        $stmt_upd->execute([
            $sender_name, $receiver_name, $title, $letter_text, 
            $proposal_question, $accent_color, $font_style, 
            $photo_fit_mode, $nickname, !empty($relationship_date) ? $relationship_date : null, $theme, 
            $slide_data_json, $expiry_date,
            $interactive_ending, $interactive_question, $interactive_yes_text, $interactive_no_text, $interactive_funny_no, $interactive_ask_name,
            $page_id
        ]);
        echo json_encode(['success' => true]);
        break;

    // ──────────────────────────────────────────────────────
    // EDITOR: Change Music Track
    // ──────────────────────────────────────────────────────
    case 'change_music':
        $input = json_decode(file_get_contents('php://input'), true);
        $page_id = (int)($input['page_id'] ?? 0);
        $music_url = trim($input['music_url'] ?? '');

        $stmt = $pdo->prepare("SELECT * FROM pages WHERE id = ?");
        $stmt->execute([$page_id]);
        $page = $stmt->fetch();
        if (!$page) {
            echo json_encode(['success' => false, 'message' => 'Page not found.']);
            break;
        }
        $authorized = false;
        if (is_logged_in() && $page['user_id'] == $_SESSION['user_id']) $authorized = true;
        if (empty($page['user_id']) && $page['guest_session_id'] === session_id()) $authorized = true;
        if (is_admin()) $authorized = true;

        if (!$authorized) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
            break;
        }

        $stmt_upd = $pdo->prepare("UPDATE pages SET music_url = ? WHERE id = ?");
        $stmt_upd->execute([$music_url, $page_id]);
        echo json_encode(['success' => true]);
        break;

    // ──────────────────────────────────────────────────────
    // EDITOR: Upload/Replace Voice or Video Message
    // ──────────────────────────────────────────────────────
    case 'upload_voice_video':
        $page_id = (int)($_POST['page_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM pages WHERE id = ?");
        $stmt->execute([$page_id]);
        $page = $stmt->fetch();
        if (!$page) {
            echo json_encode(['success' => false, 'error' => 'Page not found.']);
            break;
        }
        $authorized = false;
        if (is_logged_in() && $page['user_id'] == $_SESSION['user_id']) $authorized = true;
        if (empty($page['user_id']) && $page['guest_session_id'] === session_id()) $authorized = true;
        if (is_admin()) $authorized = true;

        if (!$authorized) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized.']);
            break;
        }

        $response = ['success' => true, 'messages' => []];

        // Handle Voice note
        if (isset($_FILES['voice']) && $_FILES['voice']['error'] === UPLOAD_ERR_OK) {
            // Delete old voice note if exists
            if (!empty($page['voice_url']) && file_exists($page['voice_url'])) {
                @unlink($page['voice_url']);
            }
            $voice_res = upload_voice($_FILES['voice']);
            if ($voice_res['success']) {
                $voice_duration = (int)($_POST['voice_duration'] ?? 0);
                $voice_size = (int)($_FILES['voice']['size'] ?? 0);
                $pdo->prepare("UPDATE pages SET voice_url = ?, voice_duration = ?, voice_size = ?, voice_created_at = NOW() WHERE id = ?")
                    ->execute([$voice_res['path'], $voice_duration, $voice_size, $page_id]);
                $response['messages'][] = 'Voice note uploaded successfully.';
            } else {
                $response['success'] = false;
                $response['error'] = $voice_res['message'];
            }
        } elseif (isset($_FILES['voice']) && $_FILES['voice']['error'] !== UPLOAD_ERR_NO_FILE) {
            $response['success'] = false;
            $response['error'] = 'Voice file upload error code: ' . $_FILES['voice']['error'];
        }

        // Handle Letter Voice note
        if ($response['success'] && isset($_FILES['letter_voice']) && $_FILES['letter_voice']['error'] === UPLOAD_ERR_OK) {
            // Delete old letter voice note if exists
            if (!empty($page['letter_voice_url']) && file_exists($page['letter_voice_url'])) {
                @unlink($page['letter_voice_url']);
            }
            $voice_res = upload_voice($_FILES['letter_voice']);
            if ($voice_res['success']) {
                $letter_voice_duration = (int)($_POST['letter_voice_duration'] ?? 0);
                $letter_voice_size = (int)($_FILES['letter_voice']['size'] ?? 0);
                $pdo->prepare("UPDATE pages SET letter_voice_url = ?, letter_voice_duration = ?, letter_voice_size = ?, letter_voice_created_at = NOW() WHERE id = ?")
                    ->execute([$voice_res['path'], $letter_voice_duration, $letter_voice_size, $page_id]);
                $response['messages'][] = 'Letter voice note uploaded successfully.';
            } else {
                $response['success'] = false;
                $response['error'] = $voice_res['message'];
            }
        } elseif (isset($_FILES['letter_voice']) && $_FILES['letter_voice']['error'] !== UPLOAD_ERR_NO_FILE) {
            $response['success'] = false;
            $response['error'] = 'Letter voice file upload error code: ' . $_FILES['letter_voice']['error'];
        }

        // Handle Video
        if ($response['success'] && isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
            // Delete old video if exists
            if (!empty($page['video_url']) && file_exists($page['video_url'])) {
                @unlink($page['video_url']);
            }
            $video_res = upload_video($_FILES['video']);
            if ($video_res['success']) {
                $pdo->prepare("UPDATE pages SET video_url = ? WHERE id = ?")->execute([$video_res['path'], $page_id]);
                $response['messages'][] = 'Video message uploaded successfully.';
            } else {
                $response['success'] = false;
                $response['error'] = $video_res['message'];
            }
        } elseif (isset($_FILES['video']) && $_FILES['video']['error'] !== UPLOAD_ERR_NO_FILE) {
            $response['success'] = false;
            $response['error'] = 'Video file upload error code: ' . $_FILES['video']['error'];
        }

        echo json_encode($response);
        break;

    // ──────────────────────────────────────────────────────
    // EDITOR: Delete Voice Note
    // ──────────────────────────────────────────────────────
    case 'delete_voice':
        $input = json_decode(file_get_contents('php://input'), true);
        $page_id = (int)($input['page_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM pages WHERE id = ?");
        $stmt->execute([$page_id]);
        $page = $stmt->fetch();
        if (!$page) {
            echo json_encode(['success' => false, 'message' => 'Page not found.']);
            break;
        }
        $authorized = false;
        if (is_logged_in() && $page['user_id'] == $_SESSION['user_id']) $authorized = true;
        if (empty($page['user_id']) && $page['guest_session_id'] === session_id()) $authorized = true;
        if (is_admin()) $authorized = true;

        if (!$authorized) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
            break;
        }

        if (!empty($page['voice_url']) && file_exists($page['voice_url'])) {
            @unlink($page['voice_url']);
        }
        $pdo->prepare("UPDATE pages SET voice_url = NULL WHERE id = ?")->execute([$page_id]);
        echo json_encode(['success' => true]);
        break;

    // ──────────────────────────────────────────────────────
    // EDITOR: Delete Letter Voice Note
    // ──────────────────────────────────────────────────────
    case 'delete_letter_voice':
        $input = json_decode(file_get_contents('php://input'), true);
        $page_id = (int)($input['page_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM pages WHERE id = ?");
        $stmt->execute([$page_id]);
        $page = $stmt->fetch();
        if (!$page) {
            echo json_encode(['success' => false, 'message' => 'Page not found.']);
            break;
        }
        $authorized = false;
        if (is_logged_in() && $page['user_id'] == $_SESSION['user_id']) $authorized = true;
        if (empty($page['user_id']) && $page['guest_session_id'] === session_id()) $authorized = true;
        if (is_admin()) $authorized = true;

        if (!$authorized) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
            break;
        }

        if (!empty($page['letter_voice_url']) && file_exists($page['letter_voice_url'])) {
            @unlink($page['letter_voice_url']);
        }
        $pdo->prepare("UPDATE pages SET letter_voice_url = NULL WHERE id = ?")->execute([$page_id]);
        echo json_encode(['success' => true]);
        break;

    // ──────────────────────────────────────────────────────
    // EDITOR: Delete Video Message
    // ──────────────────────────────────────────────────────
    case 'delete_video':
        $input = json_decode(file_get_contents('php://input'), true);
        $page_id = (int)($input['page_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM pages WHERE id = ?");
        $stmt->execute([$page_id]);
        $page = $stmt->fetch();
        if (!$page) {
            echo json_encode(['success' => false, 'message' => 'Page not found.']);
            break;
        }
        $authorized = false;
        if (is_logged_in() && $page['user_id'] == $_SESSION['user_id']) $authorized = true;
        if (empty($page['user_id']) && $page['guest_session_id'] === session_id()) $authorized = true;
        if (is_admin()) $authorized = true;

        if (!$authorized) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
            break;
        }

        if (!empty($page['video_url']) && file_exists($page['video_url'])) {
            @unlink($page['video_url']);
        }
        $pdo->prepare("UPDATE pages SET video_url = NULL WHERE id = ?")->execute([$page_id]);
        echo json_encode(['success' => true]);
        break;

    // ──────────────────────────────────────────────────────
    // EDITOR: Upload Photo
    // ──────────────────────────────────────────────────────
    case 'upload_photo':
        $page_id = (int)($_POST['page_id'] ?? 0);
        if ($page_id <= 0 || empty($_FILES['photo'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
            break;
        }

        $stmt = $pdo->prepare("SELECT * FROM pages WHERE id = ?");
        $stmt->execute([$page_id]);
        $page = $stmt->fetch();
        if (!$page) {
            echo json_encode(['success' => false, 'message' => 'Page not found.']);
            break;
        }
        $authorized = false;
        if (is_logged_in() && $page['user_id'] == $_SESSION['user_id']) $authorized = true;
        if (empty($page['user_id']) && $page['guest_session_id'] === session_id()) $authorized = true;
        if (is_admin()) $authorized = true;

        if (!$authorized) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
            break;
        }

        // Check photo limit (max 10)
        $count = $pdo->prepare("SELECT COUNT(*) FROM page_images WHERE page_id = ?");
        $count->execute([$page_id]);
        if ($count->fetchColumn() >= 10) {
            echo json_encode(['success' => false, 'message' => 'Limit reached. You can only upload up to 10 photos.']);
            break;
        }

        $upload_res = upload_image($_FILES['photo']);
        if ($upload_res['success']) {
            $pos_stmt = $pdo->prepare("SELECT COALESCE(MAX(position), -1) + 1 FROM page_images WHERE page_id = ?");
            $pos_stmt->execute([$page_id]);
            $next_pos = $pos_stmt->fetchColumn();

            $stmt_ins = $pdo->prepare("INSERT INTO page_images (page_id, image_path, thumb_path, medium_path, position) VALUES (?, ?, ?, ?, ?)");
            $stmt_ins->execute([$page_id, $upload_res['path'], $upload_res['thumb'] ?? null, $upload_res['medium'] ?? null, $next_pos]);
            $photo_id = $pdo->lastInsertId();

            echo json_encode([
                'success' => true,
                'photo' => [
                    'id' => $photo_id,
                    'path' => $upload_res['path'],
                    'thumb' => $upload_res['thumb'] ?? null,
                    'medium' => $upload_res['medium'] ?? null
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => $upload_res['message']]);
        }
        break;

    // ──────────────────────────────────────────────────────
    // EDITOR: Delete Photo
    // ──────────────────────────────────────────────────────
    case 'delete_photo':
        $input = json_decode(file_get_contents('php://input'), true);
        $page_id = (int)($input['page_id'] ?? 0);
        $photo_id = (int)($input['photo_id'] ?? 0);

        $stmt = $pdo->prepare("SELECT * FROM pages WHERE id = ?");
        $stmt->execute([$page_id]);
        $page = $stmt->fetch();
        if (!$page) {
            echo json_encode(['success' => false, 'message' => 'Page not found.']);
            break;
        }
        $authorized = false;
        if (is_logged_in() && $page['user_id'] == $_SESSION['user_id']) $authorized = true;
        if (empty($page['user_id']) && $page['guest_session_id'] === session_id()) $authorized = true;
        if (is_admin()) $authorized = true;

        if (!$authorized) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
            break;
        }

        $img_stmt = $pdo->prepare("SELECT image_path, thumb_path, medium_path FROM page_images WHERE id = ? AND page_id = ?");
        $img_stmt->execute([$photo_id, $page_id]);
        $img = $img_stmt->fetch();
        if ($img) {
            if (file_exists($img['image_path'])) @unlink($img['image_path']);
            if (!empty($img['thumb_path']) && file_exists($img['thumb_path'])) @unlink($img['thumb_path']);
            if (!empty($img['medium_path']) && file_exists($img['medium_path'])) @unlink($img['medium_path']);
            $pdo->prepare("DELETE FROM page_images WHERE id = ?")->execute([$photo_id]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Photo not found.']);
        }
        break;

    // ──────────────────────────────────────────────────────
    // EDITOR: Reorder Photos
    // ──────────────────────────────────────────────────────
    case 'reorder_photos':
        $input = json_decode(file_get_contents('php://input'), true);
        $page_id = (int)($input['page_id'] ?? 0);
        $photo_ids = $input['photo_ids'] ?? [];

        if (!is_array($photo_ids)) {
            echo json_encode(['success' => false, 'message' => 'Invalid list of photo IDs.']);
            break;
        }

        $stmt = $pdo->prepare("SELECT * FROM pages WHERE id = ?");
        $stmt->execute([$page_id]);
        $page = $stmt->fetch();
        if (!$page) {
            echo json_encode(['success' => false, 'message' => 'Page not found.']);
            break;
        }
        $authorized = false;
        if (is_logged_in() && $page['user_id'] == $_SESSION['user_id']) $authorized = true;
        if (empty($page['user_id']) && $page['guest_session_id'] === session_id()) $authorized = true;
        if (is_admin()) $authorized = true;

        if (!$authorized) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
            break;
        }

        $upd_stmt = $pdo->prepare("UPDATE page_images SET position = ? WHERE id = ? AND page_id = ?");
        foreach ($photo_ids as $position => $photo_id) {
            $upd_stmt->execute([(int)$position, (int)$photo_id, $page_id]);
        }

        echo json_encode(['success' => true]);
        break;

    // ──────────────────────────────────────────────────────
    // EDITOR: Upload/Replace Slide-Specific Media (AJAX)
    // ──────────────────────────────────────────────────────
    case 'upload_slide_media':
        $page_id = (int)($_POST['page_id'] ?? 0);
        $slide_key = trim($_POST['slide_key'] ?? '');
        $media_type = trim($_POST['media_type'] ?? '');

        if ($page_id <= 0 || empty($slide_key) || empty($media_type)) {
            echo json_encode(['success' => false, 'error' => 'Invalid parameters.']);
            break;
        }

        $stmt = $pdo->prepare("SELECT * FROM pages WHERE id = ?");
        $stmt->execute([$page_id]);
        $page = $stmt->fetch();
        if (!$page) {
            echo json_encode(['success' => false, 'error' => 'Page not found.']);
            break;
        }
        $authorized = false;
        if (is_logged_in() && $page['user_id'] == $_SESSION['user_id']) $authorized = true;
        if (empty($page['user_id']) && $page['guest_session_id'] === session_id()) $authorized = true;
        if (is_admin()) $authorized = true;

        if (!$authorized) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized.']);
            break;
        }

        $slide_data = decode_slide_data($page['slide_data'] ?? '');
        $response = ['success' => false];

        if ($media_type === 'video') {
            if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
                // Delete old video if exists
                $old_path = $slide_data[$slide_key . '_video'] ?? '';
                if (!empty($old_path) && file_exists($old_path)) @unlink($old_path);
                
                $res = upload_video($_FILES['file']);
                if ($res['success']) {
                    $slide_data[$slide_key . '_video'] = $res['path'];
                    $pdo->prepare("UPDATE pages SET slide_data = ? WHERE id = ?")->execute([json_encode($slide_data), $page_id]);
                    $response = ['success' => true, 'path' => $res['path']];
                } else {
                    $response['error'] = $res['message'];
                }
            } else {
                $response['error'] = 'No file uploaded or file transfer error.';
            }
        } elseif ($media_type === 'voice') {
            if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
                $old_path = $slide_data[$slide_key . '_voice'] ?? '';
                if (!empty($old_path) && file_exists($old_path)) @unlink($old_path);
                
                $res = upload_voice($_FILES['file']);
                if ($res['success']) {
                    $slide_data[$slide_key . '_voice'] = $res['path'];
                    $slide_data[$slide_key . '_voice_duration'] = (int)($_POST['voice_duration'] ?? 0);
                    $slide_data[$slide_key . '_voice_size'] = (int)($_FILES['file']['size'] ?? 0);
                    $slide_data[$slide_key . '_voice_created_at'] = date('Y-m-d H:i:s');
                    
                    $pdo->prepare("UPDATE pages SET slide_data = ? WHERE id = ?")->execute([json_encode($slide_data), $page_id]);
                    $response = ['success' => true, 'path' => $res['path']];
                } else {
                    $response['error'] = $res['message'];
                }
            } else {
                $response['error'] = 'No file uploaded or file transfer error.';
            }
        } elseif ($media_type === 'music') {
            if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
                $old_path = $slide_data[$slide_key . '_music'] ?? '';
                if (!empty($old_path) && file_exists($old_path)) @unlink($old_path);
                
                $res = upload_voice($_FILES['file'], 'assets/music/');
                if ($res['success']) {
                    $slide_data[$slide_key . '_music'] = $res['path'];
                    $pdo->prepare("UPDATE pages SET slide_data = ? WHERE id = ?")->execute([json_encode($slide_data), $page_id]);
                    $response = ['success' => true, 'path' => $res['path']];
                } else {
                    $response['error'] = $res['message'];
                }
            } else {
                $response['error'] = 'No file uploaded or file transfer error.';
            }
        } elseif ($media_type === 'images') {
            if (!empty($_FILES['files']['name'][0])) {
                $files_count = count($_FILES['files']['name']);
                $limit = min($files_count, 10);
                $uploaded_paths = [];
                for ($i = 0; $i < $limit; $i++) {
                    if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
                        $single_file = [
                            'name' => $_FILES['files']['name'][$i],
                            'type' => $_FILES['files']['type'][$i],
                            'tmp_name' => $_FILES['files']['tmp_name'][$i],
                            'error' => $_FILES['files']['error'][$i],
                            'size' => $_FILES['files']['size'][$i]
                        ];
                        $res = upload_image($single_file);
                        if ($res['success']) {
                            $uploaded_paths[] = [
                                'original' => $res['path'],
                                'medium' => $res['medium'] ?? $res['path'],
                                'thumb' => $res['thumb'] ?? $res['path']
                            ];
                        }
                    }
                }
                if (!empty($uploaded_paths)) {
                    // Delete old photos
                    $old_photos = $slide_data[$slide_key . '_images'] ?? [];
                    if (is_array($old_photos)) {
                        foreach ($old_photos as $pinfo) {
                            if (is_array($pinfo)) {
                                if (!empty($pinfo['original']) && file_exists($pinfo['original'])) @unlink($pinfo['original']);
                                if (!empty($pinfo['medium']) && file_exists($pinfo['medium'])) @unlink($pinfo['medium']);
                                if (!empty($pinfo['thumb']) && file_exists($pinfo['thumb'])) @unlink($pinfo['thumb']);
                            }
                        }
                    }
                    $slide_data[$slide_key . '_images'] = $uploaded_paths;
                    $pdo->prepare("UPDATE pages SET slide_data = ? WHERE id = ?")->execute([json_encode($slide_data), $page_id]);
                    $response = ['success' => true, 'paths' => $uploaded_paths];
                } else {
                    $response['error'] = 'No valid images could be uploaded.';
                }
            } else {
                $response['error'] = 'No files found.';
            }
        } else {
            $response['error'] = 'Invalid media type.';
        }

        // Recalculate and update storage
        $storage_bytes = calculate_page_storage($page_id, $pdo);
        $pdo->prepare("UPDATE pages SET storage_bytes = ? WHERE id = ?")->execute([$storage_bytes, $page_id]);

        echo json_encode($response);
        break;

    // ──────────────────────────────────────────────────────
    // EDITOR: Delete Slide-Specific Media (AJAX)
    // ──────────────────────────────────────────────────────
    case 'delete_slide_media':
        $input = json_decode(file_get_contents('php://input'), true);
        $page_id = (int)($input['page_id'] ?? 0);
        $slide_key = trim($input['slide_key'] ?? '');
        $media_type = trim($input['media_type'] ?? '');

        if ($page_id <= 0 || empty($slide_key) || empty($media_type)) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
            break;
        }

        $stmt = $pdo->prepare("SELECT * FROM pages WHERE id = ?");
        $stmt->execute([$page_id]);
        $page = $stmt->fetch();
        if (!$page) {
            echo json_encode(['success' => false, 'message' => 'Page not found.']);
            break;
        }
        $authorized = false;
        if (is_logged_in() && $page['user_id'] == $_SESSION['user_id']) $authorized = true;
        if (empty($page['user_id']) && $page['guest_session_id'] === session_id()) $authorized = true;
        if (is_admin()) $authorized = true;

        if (!$authorized) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
            break;
        }

        $slide_data = decode_slide_data($page['slide_data'] ?? '');

        if ($media_type === 'video') {
            $path = $slide_data[$slide_key . '_video'] ?? '';
            if (!empty($path) && file_exists($path)) @unlink($path);
            unset($slide_data[$slide_key . '_video']);
        } elseif ($media_type === 'voice') {
            $path = $slide_data[$slide_key . '_voice'] ?? '';
            if (!empty($path) && file_exists($path)) @unlink($path);
            unset($slide_data[$slide_key . '_voice']);
        } elseif ($media_type === 'music') {
            $path = $slide_data[$slide_key . '_music'] ?? '';
            if (!empty($path) && file_exists($path)) @unlink($path);
            unset($slide_data[$slide_key . '_music']);
        } elseif ($media_type === 'images') {
            $photos = $slide_data[$slide_key . '_images'] ?? [];
            if (is_array($photos)) {
                foreach ($photos as $pinfo) {
                    if (is_array($pinfo)) {
                        if (!empty($pinfo['original']) && file_exists($pinfo['original'])) @unlink($pinfo['original']);
                        if (!empty($pinfo['medium']) && file_exists($pinfo['medium'])) @unlink($pinfo['medium']);
                        if (!empty($pinfo['thumb']) && file_exists($pinfo['thumb'])) @unlink($pinfo['thumb']);
                    }
                }
            }
            unset($slide_data[$slide_key . '_images']);
        }

        $pdo->prepare("UPDATE pages SET slide_data = ? WHERE id = ?")->execute([json_encode($slide_data), $page_id]);

        // Recalculate and update storage
        $storage_bytes = calculate_page_storage($page_id, $pdo);
        $pdo->prepare("UPDATE pages SET storage_bytes = ? WHERE id = ?")->execute([$storage_bytes, $page_id]);

        echo json_encode(['success' => true]);
        break;

    // ──────────────────────────────────────────────────────
    // NEW: Create Razorpay order for publishing a paid draft
    // ──────────────────────────────────────────────────────
    case 'create_publish_order':
        $page_id = (int)($_POST['page_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM pages WHERE id = ?");
        $stmt->execute([$page_id]);
        $page = $stmt->fetch();
        if (!$page) {
            echo json_encode(['success' => false, 'error' => 'Page not found.']);
            break;
        }

        // Verify page ownership/authorization
        $authorized = false;
        if (is_logged_in() && $page['user_id'] == $_SESSION['user_id']) $authorized = true;
        if (empty($page['user_id']) && $page['guest_session_id'] === session_id()) $authorized = true;
        if (is_admin()) $authorized = true;

        if (!$authorized) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized.']);
            break;
        }

        // Calculate days from expiry_date & created_at
        $days = 10;
        if (!empty($page['expiry_date']) && !empty($page['created_at'])) {
            $diff = strtotime($page['expiry_date']) - strtotime($page['created_at']);
            if ($diff > 0) {
                $days = round($diff / 86400);
            }
        }
        if ($days < 10) $days = 10;

        if ($days <= 10) {
            echo json_encode(['success' => false, 'error' => 'No payment required for 10-day duration.']);
            break;
        }

        // Calculate price in rupees and paise
        $price = 0;
        if ($days === 15) $price = 5;
        elseif ($days === 30) $price = 20;
        elseif ($days === 60) $price = 50;
        elseif ($days === 90) $price = 80;
        else $price = ($days - 10) * 1;

        $amount_paise = $price * 100;

        $razorpay_key = get_setting('razorpay_key_id', RAZORPAY_KEY_ID);
        $razorpay_secret = get_setting('razorpay_key_secret', RAZORPAY_KEY_SECRET);

        if (empty($razorpay_key) || empty($razorpay_secret)) {
            echo json_encode(['success' => false, 'error' => 'Payment gateway not configured.']);
            break;
        }

        $order_data = [
            'amount' => $amount_paise,
            'currency' => 'INR',
            'receipt' => 'page_pub_' . $page_id . '_' . time(),
        ];

        $ch = curl_init('https://api.razorpay.com/v1/orders');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($order_data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, "$razorpay_key:$razorpay_secret");
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);

        if ($http_code === 200 && !empty($result['id'])) {
            // Insert payment record
            $user_id = is_logged_in() ? $_SESSION['user_id'] : null;
            
            // Alter payments table user_id column to allow NULL
            try {
                $pdo->exec("ALTER TABLE payments MODIFY user_id INT NULL");
            } catch (PDOException $e) {}

            $pdo->prepare("INSERT INTO payments (user_id, razorpay_order_id, amount, currency, status) VALUES (?, ?, ?, 'INR', 'created')")
                ->execute([$user_id, $result['id'], $amount_paise]);
            
            echo json_encode([
                'success' => true,
                'order_id' => $result['id'],
                'amount' => $amount_paise,
                'key' => $razorpay_key,
                'days' => $days
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to create payment order.']);
        }
        break;

    // ──────────────────────────────────────────────────────
    // NEW: Verify Razorpay payment and publish page
    // ──────────────────────────────────────────────────────
    case 'verify_publish_payment':
        $input = json_decode(file_get_contents('php://input'), true);
        $page_id = (int)($input['page_id'] ?? 0);
        $order_id = $input['razorpay_order_id'] ?? '';
        $payment_id = $input['razorpay_payment_id'] ?? '';
        $signature = $input['razorpay_signature'] ?? '';

        if (empty($page_id) || empty($order_id) || empty($payment_id) || empty($signature)) {
            echo json_encode(['success' => false, 'error' => 'Missing payment details.']);
            break;
        }

        // Fetch page
        $stmt = $pdo->prepare("SELECT * FROM pages WHERE id = ?");
        $stmt->execute([$page_id]);
        $page = $stmt->fetch();
        if (!$page) {
            echo json_encode(['success' => false, 'error' => 'Page not found.']);
            break;
        }

        $razorpay_secret = get_setting('razorpay_key_secret', RAZORPAY_KEY_SECRET);

        // Verify signature
        $expected_signature = hash_hmac('sha256', $order_id . '|' . $payment_id, $razorpay_secret);

        if ($expected_signature === $signature) {
            // Update payment record
            $user_id = is_logged_in() ? $_SESSION['user_id'] : null;
            $pdo->prepare("UPDATE payments SET razorpay_payment_id = ?, razorpay_signature = ?, status = 'captured' WHERE razorpay_order_id = ?")
                ->execute([$payment_id, $signature, $order_id]);
            
            // Calculate days from expiry_date & created_at
            $days = 10;
            if (!empty($page['expiry_date']) && !empty($page['created_at'])) {
                $diff = strtotime($page['expiry_date']) - strtotime($page['created_at']);
                if ($diff > 0) {
                    $days = round($diff / 86400);
                }
            }
            if ($days < 10) $days = 10;

            $expiry_date = date('Y-m-d H:i:s', strtotime("+{$days} days"));
            $storage_bytes = calculate_page_storage($page['id'], $pdo);

            // Publish page!
            $pdo->prepare("UPDATE pages SET status = 'published', expiry_date = ?, storage_bytes = ?, is_expired = 0 WHERE id = ?")
                ->execute([$expiry_date, $storage_bytes, $page_id]);

            echo json_encode(['success' => true, 'slug' => $page['slug']]);
        } else {
            // Mark as failed
            $pdo->prepare("UPDATE payments SET razorpay_payment_id = ?, status = 'failed' WHERE razorpay_order_id = ?")
                ->execute([$payment_id, $order_id]);
            echo json_encode(['success' => false, 'error' => 'Payment verification failed.']);
        }
        break;

    // ──────────────────────────────────────────────────────
    // NEW: Extend Expiry via Credits (Dashboard)
    // ──────────────────────────────────────────────────────
    case 'extend_expiry':
        if (!is_logged_in()) {
            echo json_encode(['success' => false, 'error' => 'Login required.']);
            break;
        }
        $page_id = (int)($_POST['page_id'] ?? 0);
        $days = (int)($_POST['days'] ?? 0);
        
        if ($page_id <= 0 || $days <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid parameters.']);
            break;
        }

        // Verify page ownership
        $stmt = $pdo->prepare("SELECT * FROM pages WHERE id = ?");
        $stmt->execute([$page_id]);
        $page = $stmt->fetch();
        if (!$page || ($page['user_id'] != $_SESSION['user_id'] && !is_admin())) {
            echo json_encode(['success' => false, 'error' => 'Unauthorized.']);
            break;
        }

        // Calculate credit cost
        $cost = ceil($days / 10);

        // Fetch user profile to check credits
        $user_id = $_SESSION['user_id'];
        $user_credits = get_user_credits($user_id);
        if ($user_credits < $cost) {
            echo json_encode(['success' => false, 'error' => "Insufficient credits. You need $cost credits to extend by $days days."]);
            break;
        }

        // Deduct credits
        $stmt_deduct = $pdo->prepare("UPDATE users SET credits = credits - ? WHERE id = ? AND credits >= ?");
        if (!$stmt_deduct->execute([$cost, $user_id, $cost]) || $stmt_deduct->rowCount() === 0) {
            echo json_encode(['success' => false, 'error' => 'Failed to deduct credits.']);
            break;
        }

        // Calculate new expiry date
        $current_expiry = !empty($page['expiry_date']) ? strtotime($page['expiry_date']) : time();
        if ($current_expiry < time()) {
            $current_expiry = time();
        }
        $new_expiry_time = $current_expiry + ($days * 86400);
        $new_expiry = date('Y-m-d H:i:s', $new_expiry_time);

        // Update pages table
        $stmt_upd = $pdo->prepare("UPDATE pages SET expiry_date = ?, is_expired = 0 WHERE id = ?");
        $stmt_upd->execute([$new_expiry, $page_id]);

        // Insert into expiry_extensions billing log table
        $amount_paise = $cost * get_price_per_page();
        $stmt_log = $pdo->prepare("INSERT INTO expiry_extensions (page_id, user_id, days_added, amount_paise, payment_id) VALUES (?, ?, ?, ?, 'credit_deduction')");
        $stmt_log->execute([$page_id, $user_id, $days, $amount_paise]);

        echo json_encode([
            'success' => true,
            'new_expiry' => date('M d, Y H:i', $new_expiry_time)
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action.']);
        break;
}
