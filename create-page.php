<?php
require_once 'includes/functions.php';

if (is_logged_in()) {
    $user = get_user_profile($_SESSION['user_id']);
    if ($user && isset($user['email_verified']) && (int)$user['email_verified'] === 0) {
        redirect('verification-pending.php');
    }
    if (!can_create_page($_SESSION['user_id'])) {
        redirect('payment.php');
    }
} else {
    $_SESSION['login_error'] = 'Registration or login is required to create a page.';
    redirect('login.php');
}

// Fetch music library
$user_id = is_logged_in() ? $_SESSION['user_id'] : null;
$user = null;
$credits = 0;
$free_pages_left = 0;
if ($user_id) {
    $user = get_user_profile($user_id);
    $credits = get_user_credits($user_id);
    $free_limit = (int)get_setting('free_pages_per_user', DEFAULT_FREE_PAGES);
    $free_pages_left = max(0, $free_limit - (int)$user['free_pages_used']);
}

$stmt = $pdo->prepare("SELECT * FROM music_library WHERE (is_private = 0 AND status = 'approved') OR (uploaded_by = ? AND uploaded_by IS NOT NULL) ORDER BY category, title");
$stmt->execute([$user_id]);
$music_list = $stmt->fetchAll();

$categories = get_categories();
$themes = get_themes();
$destinations = get_destinations();
$first_cat = !empty($categories) ? array_key_first($categories) : 'proposal';
$selected_category = $_GET['category'] ?? $first_cat;
if (!array_key_exists($selected_category, $categories)) {
    $selected_category = $first_cat;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    @set_time_limit(300); @ignore_user_abort(true);
    if (empty($_POST) && isset($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > 0) {
        $post_max_size = ini_get('post_max_size');
        $error = "The uploaded files are too large. The total size exceeds the server limit of {$post_max_size}. Please try uploading smaller photos or videos.";
    } else {
        $category = $_POST['category'] ?? 'proposal';
    $sender = trim($_POST['sender_name'] ?? '');
    $receiver = trim($_POST['receiver_name'] ?? '');
    $nickname = trim($_POST['nickname'] ?? '');
    $relationship_date = trim($_POST['relationship_date'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $letter = trim($_POST['letter_text'] ?? '');
    $music_url = $_POST['music_url'] ?? '';
    $theme = $_POST['theme'] ?? 'romantic';
    $accent_color = $_POST['accent_color'] ?? '#ec4899';
    $font_style = $_POST['font_style'] ?? 'Playfair Display';
    $photo_fit_mode = $_POST['photo_fit_mode'] ?? 'cover';
    $proposal_question = trim($_POST['proposal_question'] ?? '');
    $destination = $_POST['destination'] ?? '';
    $page_password = trim($_POST['page_password'] ?? '');
    
    // Universal Interactive Reply fields
    $interactive_ending = isset($_POST['interactive_ending']) && $_POST['interactive_ending'] === '1' ? 1 : 0;
    $interactive_question = trim($_POST['interactive_question'] ?? '');
    $interactive_yes_text = trim($_POST['interactive_yes_text'] ?? '');
    $interactive_no_text = trim($_POST['interactive_no_text'] ?? '');
    $interactive_funny_no = isset($_POST['interactive_funny_no']) && $_POST['interactive_funny_no'] === '1' ? 1 : 0;
    $interactive_ask_name = isset($_POST['interactive_ask_name']) && $_POST['interactive_ask_name'] === '1' ? 1 : 0;
    
    // Validations
    if (!in_array($photo_fit_mode, ['cover', 'contain'])) {
        $photo_fit_mode = 'cover';
    }
    if (!array_key_exists($category, $categories)) {
        $category = $first_cat;
    }
    if (empty($sender) || empty($receiver)) {
        $error = 'Sender and Receiver names are required.';
    } else {
        $cat_config = ($categories[$category] ?? reset($categories)) ?: [];
        if (empty($title)) {
            $title = str_replace('[Receiver]', $receiver, $cat_config['default_title'] ?? 'My Special Page for You');
        }
        if (empty($letter)) {
            $letter = $cat_config['default_letter'] ?? '';
        }
        if (empty($interactive_question)) {
            $interactive_question = $cat_config['interactive_question'] ?? $cat_config['default_question'] ?? 'Will you be mine?';
        }
        if (empty($proposal_question)) {
            $proposal_question = $interactive_question;
        }
        if (empty($interactive_yes_text)) {
            $interactive_yes_text = $cat_config['interactive_yes_text'] ?? 'Yes! ❤️';
        }
        if (empty($interactive_no_text)) {
            $interactive_no_text = $cat_config['interactive_no_text'] ?? 'No';
        }
        if (empty($theme) || !array_key_exists($theme, $themes)) {
            $theme = $cat_config['default_theme'] ?? 'romantic';
        }
        
        // Build slide_data JSON from category-specific form fields
        $slide_data = [];
        $cat_slides = $cat_config['slides'] ?? [];
        foreach ($cat_slides as $slide) {
            if (isset($slide['key']) && isset($_POST['slide_' . $slide['key']])) {
                $val = trim($_POST['slide_' . $slide['key']]);
                if (!empty($val)) {
                    $slide_data[$slide['key']] = $val;
                }
            }
            // Handle cards arrays
            if ($slide['type'] === 'cards' && isset($slide['key'])) {
                $cards_input = $_POST['cards_' . $slide['key']] ?? [];
                if (is_array($cards_input)) {
                    $filtered = array_filter(array_map('trim', $cards_input));
                    if (!empty($filtered)) {
                        $slide_data[$slide['key']] = array_values($filtered);
                    }
                }
            }
        }
        
        // Save slide enabled toggles
        $standard_slides = ['gallery', 'letter', 'voice_message', 'video_message'];
        foreach ($standard_slides as $std_type) {
            $post_key = $std_type . '_enabled';
            $slide_data[$std_type . '_enabled'] = isset($_POST[$post_key]) && (int)$_POST[$post_key] === 1 ? 1 : 0;
        }
        $slide_data['special_date_enabled'] = isset($_POST['special_date_enabled']) && (int)$_POST['special_date_enabled'] === 1 ? 1 : 0;

        // Save optional premium slides toggles and their contents
        foreach ($cat_slides as $slide) {
            if (isset($slide['is_optional']) && $slide['is_optional']) {
                $post_key = 'slide_' . $slide['key'] . '_enabled';
                $slide_data[$slide['key'] . '_enabled'] = isset($_POST[$post_key]) && (int)$_POST[$post_key] === 1 ? '1' : '0';
                
                if (isset($_POST['slide_' . $slide['key'] . '_title'])) {
                    $slide_data[$slide['key'] . '_title'] = trim($_POST['slide_' . $slide['key'] . '_title']);
                }
                if (isset($_POST['slide_' . $slide['key'] . '_subtitle'])) {
                    $slide_data[$slide['key'] . '_subtitle'] = trim($_POST['slide_' . $slide['key'] . '_subtitle']);
                }
                if (isset($_POST['slide_' . $slide['key'] . '_message'])) {
                    $slide_data[$slide['key'] . '_message'] = trim($_POST['slide_' . $slide['key'] . '_message']);
                }
                if (isset($_POST['slide_' . $slide['key'] . '_bg'])) {
                    $slide_data[$slide['key'] . '_bg'] = $_POST['slide_' . $slide['key'] . '_bg'];
                }
                // Chat moments for premium_our_chats
                if ($slide['type'] === 'premium_our_chats') {
                    for ($m = 1; $m <= 5; $m++) {
                        foreach (['label', 'title', 'dialogue'] as $mfield) {
                            $mkey = 'slide_' . $slide['key'] . '_moment_' . $m . '_' . $mfield;
                            if (isset($_POST[$mkey])) {
                                $slide_data[$slide['key'] . '_moment_' . $m . '_' . $mfield] = trim($_POST[$mkey]);
                            }
                        }
                    }
                }
            }
        }
        
        $slide_data_json = !empty($slide_data) ? json_encode($slide_data) : null;
        
        // Hash password if set
        $hashed_password = !empty($page_password) ? password_hash($page_password, PASSWORD_DEFAULT) : null;
        
        // Handle custom background music upload
        $custom_music_path = null;
        if (isset($_FILES['custom_music_file']) && $_FILES['custom_music_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['custom_music_file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($ext === 'mp3') {
                $music_dir = 'assets/music/';
                if (!is_dir($music_dir)) {
                    mkdir($music_dir, 0755, true);
                }
                $filename = md5(uniqid(rand(), true)) . '.mp3';
                $target = $music_dir . $filename;
                if (move_uploaded_file($file['tmp_name'], $target)) {
                    $custom_music_path = $target;
                    
                    // Insert into music_library table
                    $make_public = isset($_POST['make_public']) && $_POST['make_public'] === '1' ? 0 : 1; // is_private = 0 if checked, 1 if private
                    $status = $make_public === 0 ? 'pending' : 'approved'; // status = pending if public requested
                    $title_clean = pathinfo($file['name'], PATHINFO_FILENAME);
                    
                    $stmt_mus_ins = $pdo->prepare("INSERT INTO music_library (title, file_path, category, status, is_private, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt_mus_ins->execute([$title_clean, $custom_music_path, $category, $status, $make_public, $user_id]);
                    
                    $music_url = $custom_music_path;
                }
            } else {
                $error = "Custom music file must be an MP3.";
            }
        }

        // Generate Slug
        $slug = generate_slug($title . '-' . rand(100, 999), $pdo);
        
        // Save to Database
        $user_id = is_logged_in() ? $_SESSION['user_id'] : null;
        $guest_session_id = session_id();
        
        // Calculate expiry date based on selected duration
        $days = 10;
        if (isset($_POST['expiry_duration'])) {
            if ($_POST['expiry_duration'] === 'custom') {
                $days = (int)($_POST['custom_days'] ?? 10);
            } else {
                $days = (int)$_POST['expiry_duration'];
            }
        }
        if ($days < 10) $days = 10;
        $expiry_date = date('Y-m-d H:i:s', strtotime("+{$days} days"));

        // Normal page = 1 credit (every user gets 1 free credit on signup)
        $credit_cost = 1;
        if ($user_id) {
            if (get_user_credits($user_id) < $credit_cost) {
                $error = "You need 1 credit to create a page. Please buy credits (₹10 each).";
            }
        }

        if (empty($error)) {
            $stmt = $pdo->prepare("INSERT INTO pages 
                (user_id, category, title, slug, sender_name, receiver_name, nickname, relationship_date, letter_text, slide_data, music_url, theme, accent_color, font_style, photo_fit_mode, proposal_question, destination, password, status, guest_session_id, expiry_date, interactive_ending, interactive_question, interactive_yes_text, interactive_no_text, interactive_funny_no, interactive_ask_name) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?, ?, ?, ?, ?, ?, ?, ?)");
            
            if ($stmt->execute([$user_id, $category, $title, $slug, $sender, $receiver, $nickname, $relationship_date, $letter, $slide_data_json, $music_url, $theme, $accent_color, $font_style, $photo_fit_mode, $proposal_question, $destination, $hashed_password, $guest_session_id, $expiry_date, $interactive_ending, $interactive_question, $interactive_yes_text, $interactive_no_text, $interactive_funny_no, $interactive_ask_name])) {
                $page_id = $pdo->lastInsertId();
                
                // Deduct 1 credit for the normal page
                if ($user_id) {
                    $pdo->prepare("UPDATE users SET credits = credits - 1 WHERE id = ? AND credits > 0")->execute([$user_id]);
                } else {
                    $_SESSION['guest_page_created'] = true;
                }
            
            $warnings = [];
            // Handle Image Uploads
            if (!empty($_FILES['images']['name'][0])) {
                $images_count = count($_FILES['images']['name']);
                $limit = min($images_count, 10); // Allow up to 10 images
                $pos_idx = 0;
                for ($i = 0; $i < $limit; $i++) {
                    $file_error = $_FILES['images']['error'][$i];
                    if ($file_error === UPLOAD_ERR_OK) {
                        $single_file = [
                            'name' => $_FILES['images']['name'][$i],
                            'type' => $_FILES['images']['type'][$i],
                            'tmp_name' => $_FILES['images']['tmp_name'][$i],
                            'error' => $_FILES['images']['error'][$i],
                            'size' => $_FILES['images']['size'][$i]
                        ];
                        
                        $upload_res = upload_image($single_file);
                        if ($upload_res['success']) {
                            $stmt_img = $pdo->prepare("INSERT INTO page_images (page_id, image_path, thumb_path, medium_path, position) VALUES (?, ?, ?, ?, ?)");
                            $stmt_img->execute([$page_id, $upload_res['path'], $upload_res['thumb'] ?? null, $upload_res['medium'] ?? null, $pos_idx++]);
                        } else {
                            $warnings[] = "Gallery photo \"" . $_FILES['images']['name'][$i] . "\" failed to upload: " . $upload_res['message'];
                        }
                    } elseif ($file_error !== UPLOAD_ERR_NO_FILE) {
                        $warnings[] = "Gallery photo \"" . $_FILES['images']['name'][$i] . "\" transfer error (code: {$file_error}). Check server file upload limits.";
                    }
                }
            }
            // Handle Video Upload
            if (!empty($_FILES['video']['name'])) {
                if ($_FILES['video']['error'] === UPLOAD_ERR_OK) {
                    $video_res = upload_video($_FILES['video']);
                    if ($video_res['success']) {
                        $pdo->prepare("UPDATE pages SET video_url = ? WHERE id = ?")->execute([$video_res['path'], $page_id]);
                    } else {
                        $warnings[] = "Video upload failed: " . $video_res['message'];
                    }
                } else {
                    $warnings[] = "Video file transfer error code: " . $_FILES['video']['error'] . " (file might exceed server's upload_max_filesize limit).";
                }
            }
            
            // Handle Voice Upload
            if (!empty($_FILES['voice']['name'])) {
                if ($_FILES['voice']['error'] === UPLOAD_ERR_OK) {
                    $voice_res = upload_voice($_FILES['voice']);
                    if ($voice_res['success']) {
                        $voice_duration = (int)($_POST['voice_duration'] ?? 0);
                        $voice_size = (int)($_FILES['voice']['size'] ?? 0);
                        $pdo->prepare("UPDATE pages SET voice_url = ?, voice_duration = ?, voice_size = ?, voice_created_at = NOW() WHERE id = ?")
                            ->execute([$voice_res['path'], $voice_duration, $voice_size, $page_id]);
                    } else {
                        $warnings[] = "Voice note upload failed: " . $voice_res['message'];
                    }
                } else {
                    $warnings[] = "Voice file transfer error code: " . $_FILES['voice']['error'] . " (file might exceed server's upload_max_filesize limit).";
                }
            }

            // Handle premium slide media uploads
            $cat_slides_upload = $cat_config['slides'] ?? [];
            foreach ($cat_slides_upload as $s_upload) {
                if (!isset($s_upload['is_optional']) || !$s_upload['is_optional'] || !isset($s_upload['key'])) continue;
                $sk = $s_upload['key'];
                if (empty($slide_data[$sk . '_enabled']) || $slide_data[$sk . '_enabled'] !== '1') continue;
                
                // Slide photos
                $pk = 'slide_' . $sk . '_photos';
                if (!empty($_FILES[$pk]['name'][0])) {
                    $s_images = [];
                    $s_count = min(count($_FILES[$pk]['name']), 10);
                    for ($si = 0; $si < $s_count; $si++) {
                        $ferr = $_FILES[$pk]['error'][$si];
                        if ($ferr === UPLOAD_ERR_OK) {
                            $sf = ['name' => $_FILES[$pk]['name'][$si], 'type' => $_FILES[$pk]['type'][$si], 'tmp_name' => $_FILES[$pk]['tmp_name'][$si], 'error' => $_FILES[$pk]['error'][$si], 'size' => $_FILES[$pk]['size'][$si]];
                            $sres = upload_image($sf);
                            if ($sres['success']) {
                                $s_images[] = [
                                    'original' => $sres['path'], 'medium' => $sres['medium'] ?? $sres['path'], 'thumb' => $sres['thumb'] ?? $sres['path'],
                                    'caption' => trim($_POST['slide_' . $sk . '_photo_captions'][$si] ?? ''),
                                    'date' => trim($_POST['slide_' . $sk . '_photo_dates'][$si] ?? ''),
                                    'memory' => trim($_POST['slide_' . $sk . '_photo_memories'][$si] ?? '')
                                ];
                            } else {
                                $warnings[] = "Slide {$s_upload['title']} photo \"" . $_FILES[$pk]['name'][$si] . "\" upload failed: " . $sres['message'];
                            }
                        } elseif ($ferr !== UPLOAD_ERR_NO_FILE) {
                            $warnings[] = "Slide {$s_upload['title']} photo \"" . $_FILES[$pk]['name'][$si] . "\" upload error code {$ferr} (check server file upload limits).";
                        }
                    }
                    if (!empty($s_images)) $slide_data[$sk . '_images'] = $s_images;
                }
                
                // Slide video
                $vk = 'slide_' . $sk . '_video';
                if (!empty($_FILES[$vk]['name']) && $_FILES[$vk]['error'] === UPLOAD_ERR_OK) {
                    $vres = upload_video($_FILES[$vk]);
                    if ($vres['success']) $slide_data[$sk . '_video'] = $vres['path'];
                    else $warnings[] = "Slide {$s_upload['title']} video: " . $vres['message'];
                }
                
                // Slide voice
                $svk = 'slide_' . $sk . '_voice';
                if (!empty($_FILES[$svk]['name']) && $_FILES[$svk]['error'] === UPLOAD_ERR_OK) {
                    $svres = upload_voice($_FILES[$svk]);
                    if ($svres['success']) {
                        $slide_data[$sk . '_voice'] = $svres['path'];
                        $svdur = (int)($_POST['slide_' . $sk . '_voice_duration'] ?? 0);
                        if ($svdur > 0) $slide_data[$sk . '_voice_duration'] = $svdur;
                    } else $warnings[] = "Slide {$s_upload['title']} voice: " . $svres['message'];
                }
                
                // Slide music
                $smk = 'slide_' . $sk . '_music';
                if (!empty($_FILES[$smk]['name']) && $_FILES[$smk]['error'] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES[$smk]['name'], PATHINFO_EXTENSION));
                    if ($ext === 'mp3') {
                        $mdir = 'uploads/music/';
                        if (!is_dir($mdir)) mkdir($mdir, 0755, true);
                        $mfn = md5(uniqid(rand(), true)) . '.mp3';
                        if (move_uploaded_file($_FILES[$smk]['tmp_name'], $mdir . $mfn)) {
                            $slide_data[$sk . '_music'] = $mdir . $mfn;
                        }
                    }
                }
            }
            
            // Update slide_data with premium slide media paths
            if (!empty($slide_data)) {
                $pdo->prepare("UPDATE pages SET slide_data = ? WHERE id = ?")->execute([json_encode($slide_data), $page_id]);
            }

            if (!empty($warnings)) {
                $_SESSION['upload_warnings'] = $warnings;
            }
            
            redirect("edit-preview.php?slug=$slug");
        } else {
            $error = 'Failed to generate page. Please try again.';
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

    <title>Create SoulSync Page - 2-Minute Page Generator</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@600;800&family=Playfair+Display:ital,wght@0,600;1,700&display=swap" rel="stylesheet">
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
        /* â•â•â• SOULPAGES LIGHT PINK GLOBAL THEME â•â•â• */
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

        .hero-bg {
            background-color: #f8fafc;
            background-image: 
                radial-gradient(at 0% 0%, rgba(236, 72, 153, 0.08) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(59, 130, 246, 0.08) 0px, transparent 50%);
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(236, 72, 153, 0.12);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.03);
        }
        .step-panel { display: none; }
        .step-panel.active { display: block; animation: fadeUp 0.4s ease-out; }
        @keyframes fadeUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: rgba(241, 245, 249, 0.8);
            border-radius: 9999px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(236, 72, 153, 0.2);
            border-radius: 9999px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: rgba(236, 72, 153, 0.4);
        }
        .custom-scrollbar {
            scrollbar-width: thin;
            scrollbar-color: rgba(236, 72, 153, 0.2) rgba(241, 245, 249, 0.8);
        }

        /* Global Typography Contrast Fix */
        body, p, label, li, span:not(.sp-gradient-text):not(.text-transparent):not(.relative) {
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
        .text-purple-400, .text-purple-500, .hover\:text-purple-400:hover {
            color: #7c3aed !important;
        }
        .text-emerald-400, .text-green-400, .text-emerald-500 {
            color: #059669 !important;
        }
        .text-red-400, .text-red-500 {
            color: #dc2626 !important;
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

        /* Cards and list elements */
        .bg-slate-900\/40, .bg-slate-950\/40, .bg-slate-950\/60, .bg-slate-900\/60, .bg-slate-900, .bg-slate-950, .bg-slate-850 {
            background-color: #ffffff !important;
        }
        .border-slate-800, .border-slate-850, .border-slate-900 {
            border-color: #cbd5e1 !important;
        }

        /* Peer-checked checked state for Category cards and Slide switches */
        .peer:checked + div {
            border-color: #db2777 !important;
            background-color: #fdf2f8 !important;
        }
        .peer:checked + div span {
            color: #0f172a !important;
        }
        .peer-checked\:bg-pink-500\/5:checked + div {
            border-color: #db2777 !important;
            background-color: #fdf2f8 !important;
        }

        /* Custom widgets like voice player, music player */
        .bg-slate-800 {
            background-color: #f1f5f9 !important;
            color: #334155 !important;
        }
        .peer:not(:checked) + .bg-slate-800 {
            background-color: #cbd5e1 !important;
        }
        .peer:checked + .bg-slate-800 {
            background-color: #db2777 !important;
        }
        .text-slate-200 {
            color: #334155 !important;
        }
        .text-slate-400 {
            color: #475569 !important;
        }
        
        /* Drag-and-drop dropzone overrides */
        #dropzone {
            background-color: rgba(236, 72, 153, 0.02) !important;
            border-color: rgba(236, 72, 153, 0.2) !important;
        }

        /* â”€â”€ Category cards (Step 1) â”€â”€ */
        .cat-card { border: 2px solid rgba(236,72,153,0.10) !important; transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease; position: relative; }
        .cat-card:hover { transform: translateY(-3px); box-shadow: 0 12px 26px rgba(219,39,119,0.14); border-color: rgba(219,39,119,0.35) !important; }
        .cat-emoji { display:inline-flex; width:52px; height:52px; align-items:center; justify-content:center; border-radius:16px; font-size:1.7rem; margin-bottom:8px; background:rgba(255,255,255,0.8); box-shadow:0 4px 12px rgba(219,39,119,0.08); }
        .peer:checked + .cat-card { border-color:#db2777 !important; background:#fdf2f8 !important; box-shadow:0 12px 30px rgba(219,39,119,0.18); transform: translateY(-3px); }
        .cat-check { position:absolute; top:8px; right:8px; width:20px; height:20px; border-radius:9999px; background:#db2777; font-size:11px; line-height:20px; text-align:center; display:none; font-weight:800; }
        .peer:checked + .cat-card .cat-check { display:block; }
        .peer:checked + .cat-card span.cat-check { color:#ffffff !important; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 font-sans min-h-screen flex flex-col justify-between hero-bg antialiased">

    <!-- Emoji Rain -->
    <?php render_emoji_rain(); ?>

    <!-- Header -->
    <header class="bg-white/80 backdrop-blur-md border-b border-slate-200 h-16 flex items-center">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full flex items-center justify-between">
            <a href="index.php" class="flex items-center space-x-2">
                <span class="text-2xl font-bold font-heading tracking-wider bg-gradient-to-r from-pink-500 to-purple-500 bg-clip-text text-transparent">SoulSync</span>
            </a>
            <div class="flex items-center gap-4">
                <?php if (is_logged_in()): ?>
                    <a href="dashboard.php" class="text-xs font-semibold text-slate-600 hover:text-pink-600 transition">Dashboard</a>
                    <?php if (is_admin()): ?>
                        <a href="admin/index.php" class="text-xs font-semibold text-purple-500 hover:text-purple-400 transition">Admin Portal 🛠️</a>
                    <?php endif; ?>
                    <a href="logout.php" class="text-xs font-semibold text-slate-500 hover:text-pink-600 transition">Logout</a>
                <?php else: ?>
                    <a href="index.php" class="text-sm text-slate-500 hover:text-pink-600 transition">&larr; Cancel</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Main -->
    <main class="flex-grow flex items-start justify-center p-4 py-8">
        <div class="w-full max-w-2xl glass-card rounded-3xl p-6 md:p-8 shadow-2xl relative">
            
            <!-- Progress Bar -->
            <div class="mb-8">
                <div class="flex justify-between items-center text-xs font-semibold text-slate-400 uppercase tracking-widest mb-2">
                    <span id="step-indicator">Step 1 of 6</span>
                    <span id="step-name">Choose Category</span>
                </div>
                <div class="w-full h-1.5 rounded-full overflow-hidden" style="background:#fce7f3;">
                    <div id="progress-bar" class="h-full bg-gradient-to-r from-pink-500 to-purple-500 transition-all duration-300 w-[16.6%]"></div>
                </div>
            </div>

            <?php if (!empty($error)): ?>
                <div class="bg-red-500/15 border border-red-500/30 text-red-400 p-4 rounded-2xl mb-6 text-sm flex items-center">
                    <span>âš ï¸ <?= h($error) ?></span>
                </div>
            <?php endif; ?>

            <form action="create-page.php" method="POST" enctype="multipart/form-data" id="wizard-form" class="space-y-6">
                
                <!-- STEP 1: SELECT CATEGORY -->
                <div class="step-panel active" data-step="1" data-name="Choose Category">
                    <h2 class="text-2xl font-extrabold font-heading mb-2">Select Page Category</h2>
                    <p class="text-slate-400 text-sm mb-6">What occasion are you creating this for?</p>
                    
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 max-h-[24rem] overflow-y-auto custom-scrollbar pr-1 py-1">
                        <?php
                        $cat_palette = [
                            'linear-gradient(150deg,#fdf2f8,#fce7f3)', // pink
                            'linear-gradient(150deg,#eff6ff,#dbeafe)', // blue
                            'linear-gradient(150deg,#fefce8,#fef9c3)', // yellow
                            'linear-gradient(150deg,#f0fdf4,#dcfce7)', // green
                            'linear-gradient(150deg,#faf5ff,#f3e8ff)', // purple
                            'linear-gradient(150deg,#fff7ed,#ffedd5)', // orange
                        ];
                        $ci = 0;
                        foreach ($categories as $key => $cat): $pal = $cat_palette[$ci++ % count($cat_palette)]; ?>
                            <label class="cursor-pointer">
                                <input type="radio" name="category" value="<?= $key ?>" class="sr-only peer" <?= $key === $selected_category ? 'checked' : '' ?>>
                                <div class="cat-card p-4 rounded-2xl flex flex-col items-center text-center h-full" style="background:<?= $pal ?>;">
                                    <span class="cat-check">✓</span>
                                    <span class="cat-emoji"><?= $cat['icon'] ?></span>
                                    <span class="font-bold text-xs" style="color:#0f172a;"><?= h($cat['name']) ?></span>
                                    <span class="text-[9px] mt-1 leading-tight" style="color:#64748b;"><?= h(mb_substr($cat['description'], 0, 50)) ?>…</span>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- STEP 2: CONFIGURE OPTIONS -->
                <div class="step-panel" data-step="2" data-name="Configure Story">
                    <h2 class="text-2xl font-extrabold font-heading mb-2">Configure Story Options</h2>
                    <p class="text-slate-400 text-sm mb-6">Choose which sections and interactive features to enable in your story.</p>
                    
                    <div class="space-y-4">
                        <!-- Toggle for Letter -->
                        <div class="flex items-center justify-between p-4 bg-slate-900/40 border border-slate-800 rounded-2xl">
                            <div>
                                <span class="text-sm font-semibold text-white block">Text Letter Slide</span>
                                <span class="text-[10px] text-slate-500 font-medium">Show a dedicated aesthetic typewriter text letter.</span>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="hidden" name="letter_enabled" value="0">
                                <input type="checkbox" name="letter_enabled" id="step2_letter_enabled" value="1" checked class="sr-only peer" onchange="updateStepsLayoutFromToggles()">
                                <div class="w-9 h-5 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-305 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-pink-500"></div>
                            </label>
                        </div>
                        
                        <!-- Toggle for Gallery -->
                        <div class="flex items-center justify-between p-4 bg-slate-900/40 border border-slate-800 rounded-2xl">
                            <div>
                                <span class="text-sm font-semibold text-white block">Photos Gallery Slide</span>
                                <span class="text-[10px] text-slate-500 font-medium">Show a customized photos carousel/gallery page.</span>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="hidden" name="gallery_enabled" value="0">
                                <input type="checkbox" name="gallery_enabled" id="step2_gallery_enabled" value="1" checked class="sr-only peer" onchange="updateStepsLayoutFromToggles()">
                                <div class="w-9 h-5 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-305 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-pink-500"></div>
                            </label>
                        </div>
                        
                        <!-- Toggle for Voice Message -->
                        <div class="flex items-center justify-between p-4 bg-slate-900/40 border border-slate-800 rounded-2xl">
                            <div>
                                <span class="text-sm font-semibold text-white block">Voice Note Slide</span>
                                <span class="text-[10px] text-slate-500 font-medium">Show a slide with an audio voice message player.</span>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="hidden" name="voice_message_enabled" value="0">
                                <input type="checkbox" name="voice_message_enabled" id="step2_voice_message_enabled" value="1" checked class="sr-only peer" onchange="updateStepsLayoutFromToggles()">
                                <div class="w-9 h-5 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-305 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-pink-500"></div>
                            </label>
                        </div>
                        
                        <!-- Toggle for Video Message -->
                        <div class="flex items-center justify-between p-4 bg-slate-900/40 border border-slate-800 rounded-2xl">
                            <div>
                                <span class="text-sm font-semibold text-white block">Video Message Slide</span>
                                <span class="text-[10px] text-slate-500 font-medium">Show a slide with a video player for message clips.</span>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="hidden" name="video_message_enabled" value="0">
                                <input type="checkbox" name="video_message_enabled" id="step2_video_message_enabled" value="1" checked class="sr-only peer" onchange="updateStepsLayoutFromToggles()">
                                <div class="w-9 h-5 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-305 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-pink-500"></div>
                            </label>
                        </div>
                        
                        <input type="checkbox" name="special_date_enabled" id="step2_special_date_enabled" value="1" style="display: none;">

                        <!-- Toggle for Interactive Ending -->
                        <div class="p-4 bg-slate-900/40 border border-slate-800 rounded-2xl space-y-3">
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="text-sm font-semibold text-white block font-heading">Interactive Ending Reply</span>
                                    <span class="text-[10px] text-slate-500 font-medium">Ask a final yes/no question with replies and pranks.</span>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="hidden" name="interactive_ending" value="0">
                                    <input type="checkbox" name="interactive_ending" id="step2_interactive_ending" value="1" checked class="sr-only peer" onchange="updateStepsLayoutFromToggles()">
                                    <div class="w-9 h-5 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-350 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-pink-500"></div>
                                </label>
                            </div>
                            
                            <div id="step2-ending-subsettings" class="space-y-3 pt-2 border-t border-slate-800/60">
                                <div class="flex flex-col space-y-2">
                                    <label class="flex items-center space-x-2 cursor-pointer">
                                        <input type="hidden" name="interactive_funny_no" value="0">
                                        <input type="checkbox" name="interactive_funny_no" id="step2_funny_no" value="1" checked class="w-3.5 h-3.5 rounded bg-slate-950 border-slate-800 text-pink-500 focus:ring-pink-500">
                                        <span class="text-[10px] font-medium text-slate-300">Enable Funny NO Button Pranks</span>
                                    </label>
                                    <label class="flex items-center space-x-2 cursor-pointer">
                                        <input type="hidden" name="interactive_ask_name" value="0">
                                        <input type="checkbox" name="interactive_ask_name" id="step2_ask_name" value="1" checked class="w-3.5 h-3.5 rounded bg-slate-950 border-slate-800 text-pink-500 focus:ring-pink-500">
                                        <span class="text-[10px] font-medium text-slate-300">Ask Recipient's Name at Startup</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Dynamic Premium Optional Slides Container -->
                        <div id="step2-dynamic-premium-slides" class="space-y-4 pt-4 border-t border-slate-800/60 hidden">
                        </div>
                    </div>
                </div>

                <!-- STEP 3: DETAILS & MESSAGE -->
                <div class="step-panel" data-step="3" data-name="Details & Message">
                    <h2 class="text-2xl font-extrabold font-heading mb-2">Fill Page Details</h2>
                    <p class="text-slate-400 text-sm mb-6">Enter names and create your emotional message.</p>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Your Name (Sender)</label>
                            <input type="text" name="sender_name" id="sender_name" required
                                   class="w-full bg-slate-900/60 border border-slate-800 focus:border-pink-500 focus:ring-1 focus:ring-pink-500 rounded-xl px-4 py-3 text-white placeholder-slate-500 outline-none"
                                   placeholder="e.g. <?= h(get_example('sender')) ?>">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Their Name (Recipient)</label>
                            <input type="text" name="receiver_name" id="receiver_name" required
                                   class="w-full bg-slate-900/60 border border-slate-800 focus:border-pink-500 focus:ring-1 focus:ring-pink-500 rounded-xl px-4 py-3 text-white placeholder-slate-500 outline-none"
                                   placeholder="e.g. <?= h(get_example('receiver')) ?>">
                        </div>
                    </div>
 
                    <div class="mb-4">
                        <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Nickname (Optional)</label>
                        <input type="text" name="nickname" id="nickname"
                               class="w-full bg-slate-900/60 border border-slate-800 focus:border-pink-500 focus:ring-1 focus:ring-pink-500 rounded-xl px-4 py-3 text-white placeholder-slate-500 outline-none"
                               placeholder="e.g. <?= h(get_example('nickname')) ?>">
                    </div>
                    <input type="hidden" name="relationship_date" id="relationship_date" value="">
                    <div id="special-date-section-container" class="hidden"></div>
 
                    <div class="mb-4">
                        <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Custom Title (Optional)</label>
                        <input type="text" name="title" id="page_title"
                               class="w-full bg-slate-900/60 border border-slate-800 focus:border-pink-500 focus:ring-1 focus:ring-pink-500 rounded-xl px-4 py-3 text-white placeholder-slate-500 outline-none"
                               placeholder="Leave blank for automatic title">
                    </div>
 
                    <div class="mb-4" id="letter-section-container">
                        <div id="letter-section" class="transition-all duration-300">
                            <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Your Emotional Letter</label>
                            <textarea name="letter_text" id="letter_text" rows="5"
                                      class="w-full bg-slate-900/60 border border-slate-800 focus:border-pink-500 focus:ring-1 focus:ring-pink-500 rounded-xl px-4 py-3 text-white placeholder-slate-500 outline-none resize-none" placeholder="Write your heart out..."></textarea>
                        </div>
                    </div>

                    <!-- Category-specific fields (shown/hidden by JS) -->
                    <div id="category-specific-fields" class="space-y-4">
                        <!-- Populated by JavaScript based on selected category -->
                    </div>

                    <!-- Destination selector (only for invite_out) -->
                    <div id="destination-field" class="hidden mb-4">
                        <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Where do you want to go?</label>
                        <div class="grid grid-cols-3 sm:grid-cols-5 gap-2">
                            <?php foreach ($destinations as $dkey => $dest): ?>
                                <label class="cursor-pointer">
                                    <input type="radio" name="destination" value="<?= $dkey ?>" class="sr-only peer" <?= $dkey === 'cafe' ? 'checked' : '' ?>>
                                    <div class="p-3 rounded-xl border border-slate-800 bg-slate-900/40 peer-checked:border-pink-500 peer-checked:bg-pink-500/5 hover:border-slate-700 transition flex flex-col items-center text-center">
                                        <span class="text-2xl mb-1"><?= $dest['emoji'] ?></span>
                                        <span class="text-[10px] font-bold text-white"><?= explode(' ', $dest['name'])[0] ?></span>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                             <!-- STEP 4: PHOTOS, VIDEO & MUSIC -->
                <div class="step-panel" data-step="4" data-name="Media & Music">
                    <h2 class="text-2xl font-extrabold font-heading mb-2">Add Photos, Video & Music</h2>
                    <p class="text-slate-400 text-sm mb-6">Upload media and select background music.</p>
                    
                    <!-- Photos upload -->
                    <div class="mb-6" id="gallery-section-container">
                        <div id="gallery-section" class="transition-all duration-300">
                            <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Upload Photos (Max 10)</label>
                            <label id="dropzone" class="flex flex-col items-center justify-center border-2 border-dashed border-slate-800 hover:border-slate-700 bg-slate-900/20 py-6 rounded-2xl cursor-pointer transition">
                                <span class="text-2xl mb-1">🖼️</span>
                                <span class="text-sm font-semibold text-slate-300">Click or Drag & Drop to choose files</span>
                                <span class="text-xs text-slate-500 mt-1">JPG, PNG, WebP up to 5MB each</span>
                                <input type="file" name="images[]" id="image_input" multiple accept="image/*" class="hidden">
                            </label>
                            <div class="flex justify-end mt-2">
                                <button type="button" onclick="clearSelectedImages()" id="clear-images-btn" class="hidden px-3 py-1 bg-slate-880 hover:bg-slate-700 text-slate-300 hover:text-white rounded-lg text-[10px] font-bold transition flex items-center gap-1">
                                    <span>🗑️</span> <span>Deselect All Photos</span>
                                </button>
                            </div>
                            <div id="image_preview_container" class="grid grid-cols-3 gap-3 mt-3"></div>
                        </div>
                    </div>

                    <!-- Video upload (optional) -->
                    <div class="mb-6" id="video-section-container">
                        <div id="video-section" class="transition-all duration-300">
                            <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Video Message (Optional)</label>
                            <div class="flex items-center gap-2">
                                <input type="file" name="video" id="video_file_input" accept="video/*" onchange="document.getElementById('clear-video-btn').classList.remove('hidden')" class="w-full text-xs text-slate-400 file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-slate-800 file:text-white hover:file:bg-slate-700 cursor-pointer">
                                <button type="button" onclick="clearSelectedVideo()" id="clear-video-btn" class="hidden px-3 py-2 bg-slate-850 hover:bg-slate-800 text-slate-300 hover:text-white rounded-xl text-xs font-bold transition">
                                    Deselect
                                </button>
                            </div>
                            <p class="text-[10px] text-slate-600 mt-1">MP4, WEBM, MOV up to 20MB</p>
                        </div>
                    </div>

                    <!-- Voice note upload (optional) -->
                    <div class="mb-6 bg-slate-900/40 border border-slate-800/80 rounded-2xl p-4" id="voice-section-container">
                        <div id="voice-section" class="transition-all duration-300 space-y-3">
                            <label class="block text-xs font-semibold text-slate-300 uppercase flex items-center gap-1.5">
                                <span>🎙️</span> <span>Voice Note</span>
                            </label>
                            <input type="hidden" name="voice_duration" id="voice_duration_input" value="0">
                            
                            <div class="flex items-center gap-2">
                                <input type="file" name="voice" id="voice_file_input" accept=".mp3,.aac,.m4a,.wav,.ogg,audio/mpeg,audio/aac,audio/mp4,audio/wav,audio/ogg" class="w-full text-xs text-slate-400 file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-pink-600 file:text-white hover:file:bg-pink-500 cursor-pointer">
                                <button type="button" onclick="clearSelectedVoice()" id="clear-voice-btn" class="hidden px-3 py-2 bg-slate-850 hover:bg-slate-800 text-slate-300 hover:text-white rounded-xl text-xs font-bold transition">
                                    Deselect
                                </button>
                            </div>
                            <p class="text-[10px] text-slate-600">MP3, AAC, M4A, WAV, OGG — up to 10MB</p>
                            
                            <!-- Preview after selecting file -->
                            <div id="voice-upload-preview" class="mt-3 hidden bg-slate-950/40 p-3 rounded-xl border border-slate-800/80">
                                <div class="flex items-center gap-2">
                                    <span class="text-xl">🎵</span>
                                    <audio id="voice-upload-player" controls class="h-8 w-full max-w-[260px] rounded-lg"></audio>
                                </div>
                                <p id="voice-upload-info" class="text-[10px] text-slate-500 mt-1.5"></p>
                            </div>
                        </div>
                    </div>

                    <!-- Music selector -->
                    <div class="mb-6">
                        <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Select Background Music</label>
                        <div class="space-y-2 max-h-48 overflow-y-auto pr-2 custom-scrollbar mb-4" id="music-selector-container">
                        </div>
                    </div>

                    <!-- Custom Music Upload -->
                    <div class="mb-2 border-t border-slate-800/60 pt-4">
                        <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Or Upload Custom Music (MP3 only)</label>
                        <input type="file" name="custom_music_file" id="custom_music_file" accept=".mp3" class="w-full text-xs text-slate-400 file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-slate-800 file:text-white hover:file:bg-slate-700 cursor-pointer">
                        <div class="flex items-center space-x-2 mt-3">
                            <input type="checkbox" name="make_public" id="make_public" value="1" class="w-4 h-4 rounded bg-slate-950 border-slate-800 text-pink-500 focus:ring-pink-500">
                            <span class="text-xs text-slate-300">Make this track public in SoulSync library</span>
                        </div>
                    </div>
                </div>

                <!-- STEP 5: THEME & DESIGN -->
                <div class="step-panel" data-step="5" data-name="Theme & Design">
                    <h2 class="text-2xl font-extrabold font-heading mb-2">Choose Theme & Design</h2>
                    <p class="text-slate-400 text-sm mb-6">Pick the visual style for your story page.</p>
                    
                    <div class="mb-6">
                        <label class="block text-xs font-semibold text-slate-400 uppercase mb-3">Visual Theme</label>
                        <div class="grid grid-cols-3 sm:grid-cols-4 gap-3 max-h-[20rem] overflow-y-auto custom-scrollbar pr-1">
                            <?php foreach ($themes as $tkey => $tval): ?>
                                <label class="cursor-pointer">
                                    <input type="radio" name="theme" value="<?= $tkey ?>" class="sr-only peer" <?= $tkey === 'romantic' ? 'checked' : '' ?>>
                                    <div class="p-3 rounded-2xl border border-slate-800 bg-slate-900/40 peer-checked:border-pink-500 peer-checked:bg-pink-500/5 hover:border-slate-700 transition flex flex-col items-center text-center">
                                        <span class="text-2xl mb-1"><?= $tval['icon'] ?></span>
                                        <span class="text-[10px] font-bold text-white"><?= h($tval['name']) ?></span>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Accent Color</label>
                            <div class="grid grid-cols-4 gap-2">
                                <label class="cursor-pointer"><input type="radio" name="accent_color" value="#e11d48" checked class="sr-only peer"><div class="h-10 rounded-xl border border-slate-800 peer-checked:border-white bg-rose-600"></div></label>
                                <label class="cursor-pointer"><input type="radio" name="accent_color" value="#a855f7" class="sr-only peer"><div class="h-10 rounded-xl border border-slate-800 peer-checked:border-white bg-purple-500"></div></label>
                                <label class="cursor-pointer"><input type="radio" name="accent_color" value="#d97706" class="sr-only peer"><div class="h-10 rounded-xl border border-slate-800 peer-checked:border-white bg-amber-600"></div></label>
                                <label class="cursor-pointer"><input type="radio" name="accent_color" value="#3b82f6" class="sr-only peer"><div class="h-10 rounded-xl border border-slate-800 peer-checked:border-white bg-blue-500"></div></label>
                                <label class="cursor-pointer"><input type="radio" name="accent_color" value="#ec4899" class="sr-only peer"><div class="h-10 rounded-xl border border-slate-800 peer-checked:border-white bg-pink-500"></div></label>
                                <label class="cursor-pointer"><input type="radio" name="accent_color" value="#0d9488" class="sr-only peer"><div class="h-10 rounded-xl border border-slate-800 peer-checked:border-white bg-teal-600"></div></label>
                                <label class="cursor-pointer"><input type="radio" name="accent_color" value="#6366f1" class="sr-only peer"><div class="h-10 rounded-xl border border-slate-800 peer-checked:border-white bg-indigo-500"></div></label>
                                <label class="cursor-pointer"><input type="radio" name="accent_color" value="#ea580c" class="sr-only peer"><div class="h-10 rounded-xl border border-slate-800 peer-checked:border-white bg-orange-600"></div></label>
                            </div>
                        </div>
                        <div class="flex flex-col space-y-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Font Typography</label>
                                <select name="font_style"
                                        class="w-full bg-slate-900 border border-slate-800 text-white rounded-xl px-4 py-3 outline-none focus:border-pink-500 text-sm">
                                    <option value="Playfair Display">Elegant Serif (Playfair)</option>
                                    <option value="Outfit">Modern Clean (Outfit)</option>
                                    <option value="Inter">Classic Sans (Inter)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Photo Display Mode</label>
                                <select name="photo_fit_mode"
                                        class="w-full bg-slate-900 border border-slate-800 text-white rounded-xl px-4 py-3 outline-none focus:border-pink-500 text-sm">
                                    <option value="cover">Crop to Fill / Cover (Modern & Full)</option>
                                    <option value="contain">Fit Entire Photo / Contain (No Cropping)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 6: FINAL QUESTION & SECURITY -->
                <div class="step-panel" data-step="6" data-name="Final Touch">
                    <h2 class="text-2xl font-extrabold font-heading mb-2">Final Touch</h2>
                    <p class="text-slate-400 text-sm mb-6">Set the interactive question and optional security.</p>
                    
                    <!-- Universal Interactive Ending Panel -->
                    <div class="mb-6 bg-slate-900/40 border border-slate-850 rounded-2xl p-5 space-y-4" id="ending-section-container">
                        <div id="interactive-ending-settings" class="space-y-4">
                            <div>
                                <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Interactive Prompt Question</label>
                                <input type="text" name="interactive_question" id="proposal_question"
                                       class="w-full bg-slate-900/60 border border-slate-800 focus:border-pink-500 focus:ring-1 focus:ring-pink-500 rounded-xl px-4 py-3 text-white placeholder-slate-500 outline-none text-sm"
                                       placeholder="e.g. <?= h(get_example('question')) ?>">
                                <p class="text-[10px] text-slate-500 mt-1">This question appears on the final decision screen.</p>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">YES Button Text</label>
                                    <input type="text" name="interactive_yes_text" id="interactive_yes_text"
                                           class="w-full bg-slate-900/60 border border-slate-800 focus:border-pink-500 focus:ring-1 focus:ring-pink-500 rounded-xl px-4 py-2.5 text-white outline-none text-sm"
                                           placeholder="e.g. <?= h(get_example('yes_text')) ?>">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">NO Button Text</label>
                                    <input type="text" name="interactive_no_text" id="interactive_no_text"
                                           class="w-full bg-slate-900/60 border border-slate-800 focus:border-pink-500 focus:ring-1 focus:ring-pink-500 rounded-xl px-4 py-2.5 text-white outline-none text-sm"
                                           placeholder="e.g. <?= h(get_example('no_text')) ?>">
                                </div>
                            </div>
                            
                        </div>
                    </div>

                    <div class="mb-6">
                        <label class="flex items-center space-x-3 cursor-pointer mb-3">
                            <input type="checkbox" id="enable_password" class="w-5 h-5 rounded bg-slate-900 border-slate-800 text-pink-500 focus:ring-pink-500">
                            <span class="text-sm font-semibold text-slate-300">🔒 Password Protect This Page</span>
                        </label>
                        <div id="password-field" class="hidden">
                            <input type="text" name="page_password" id="page_password"
                                   class="w-full bg-slate-900/60 border border-slate-800 focus:border-pink-500 focus:ring-1 focus:ring-pink-500 rounded-xl px-4 py-3 text-white placeholder-slate-500 outline-none"
                                   placeholder="Set a password for this page">
                            <p class="text-[10px] text-slate-600 mt-1">Share the password separately with the recipient</p>
                        </div>
                    </div>

                    <!-- Expiry Duration selection -->
                    <div class="mb-6 border-t border-slate-800/60 pt-4">
                        <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">Page Expiry Duration</label>
                        <select name="expiry_duration" id="expiry_duration" onchange="updateValidityCost()" class="w-full bg-slate-900 border border-slate-800 text-white rounded-xl px-4 py-3 outline-none focus:border-pink-500 text-sm mb-3">
                            <option value="10">10 Days (Free / Default)</option>
                            <option value="15">15 Days (₹5 / 5 paid days)</option>
                            <option value="30">30 Days (₹20 / 20 paid days)</option>
                            <option value="60">60 Days (₹50 / 50 paid days)</option>
                            <option value="90">90 Days (₹80 / 80 paid days)</option>
                            <option value="custom">Custom Days (₹1 / extra day)</option>
                        </select>
                        <div id="custom-days-container" class="hidden mb-3">
                            <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Enter Custom Days</label>
                            <input type="number" id="custom_days" name="custom_days" min="10" max="365" value="10" oninput="updateValidityCost()" class="w-full bg-slate-900/60 border border-slate-800 focus:border-pink-500 rounded-xl px-4 py-2.5 text-white outline-none text-sm">
                        </div>
                        <div class="bg-slate-900/40 border border-slate-850 rounded-2xl p-4 text-xs space-y-2" id="validity-cost-card">
                            <div class="flex justify-between">
                                <span class="text-slate-400">Selected Duration:</span>
                                <span id="display-duration" class="font-bold text-white">10 Days</span>
                            </div>
                            <?php if ($user_id): ?>
                            <div class="flex justify-between">
                                <span class="text-slate-400">Your Current Credits:</span>
                                <span id="user-current-credits" class="font-bold text-white"><?= $credits ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-400">Credits Required:</span>
                                <span id="credits-required" class="font-bold text-emerald-400">0 Credits</span>
                            </div>
                            <div class="border-t border-slate-800 pt-2 flex justify-between">
                                <span class="text-slate-400 font-semibold">Remaining Credits Balance:</span>
                                <span id="user-remaining-credits" class="font-bold text-white"><?= $credits ?></span>
                            </div>
                            <?php else: ?>
                            <div class="flex justify-between">
                                <span class="text-slate-400">Price:</span>
                                <span id="duration-price" class="font-bold text-emerald-400">Free</span>
                            </div>
                            <div class="text-[10px] text-slate-500 mt-1">
                                Note: Guest pages default to 10 days. Register an account to extend or customize page validity.
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="glass-card rounded-2xl p-5 border border-slate-800">
                        <div class="flex items-center space-x-3 mb-3">
                            <span class="text-2xl">✨</span>
                            <div>
                                <h4 class="text-sm font-bold text-white">Ready to Generate!</h4>
                                <p class="text-[10px] text-slate-500">Your page will be created as a draft. You can preview and edit before publishing.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer Navigation Buttons -->
                <div class="sticky bottom-0 bg-slate-950/90 backdrop-blur-md py-4 border-t border-slate-900 flex justify-between items-center z-20 mt-6 -mx-6 md:-mx-8 px-6 md:px-8 rounded-b-3xl">
                    <button type="button" id="prev-btn" class="px-6 py-3 bg-slate-900 hover:bg-slate-800 border border-slate-800 text-slate-300 font-semibold rounded-xl transition invisible">
                        &larr; Back
                    </button>
                    <button type="button" id="next-btn" class="px-8 py-3.5 bg-gradient-to-r from-pink-500 to-purple-500 text-white font-bold rounded-xl hover:opacity-95 transition shadow-md shadow-pink-500/20">
                        Continue &rarr;
                    </button>
                </div>
            </form>
        </div>
        

    </main>

    <!-- Footer -->
    <footer class="bg-slate-950 border-t border-slate-900 py-4 text-center text-slate-600 text-xs">
        &copy; <?= date('Y') ?> SoulSync. Safe, secure & private shared cards.
    </footer>

    <!-- Audio element for play preview -->
    <audio id="preview-audio" class="hidden"></audio>

    <script>
        // Pass config from PHP to JS
        const musicLibrary = (<?= json_encode($music_list) ?>) || [];
        const categoryDefaults = (<?= json_encode($categories) ?>) || {};
        
        const userIsLoggedIn = <?= $user_id ? 'true' : 'false' ?>;
        const userCredits = parseInt("<?= $credits ?>") || 0;
        const freePagesLeft = parseInt("<?= $free_pages_left ?>") || 0;
        
        const POST_MAX_SIZE = <?= parse_ini_bytes(ini_get('post_max_size')) ?>;
        const MAX_IMAGE_SIZE = 10 * 1024 * 1024;
        const MAX_VIDEO_SIZE = 50 * 1024 * 1024;
        const MAX_VOICE_SIZE = 10 * 1024 * 1024;
        const MAX_MUSIC_SIZE = 10 * 1024 * 1024;

        function updateValidityCost() {
            const durationSelect = document.getElementById('expiry_duration');
            const customDaysContainer = document.getElementById('custom-days-container');
            const customDaysInput = document.getElementById('custom_days');
            const displayDuration = document.getElementById('display-duration');
            
            let days = 10;
            if (durationSelect.value === 'custom') {
                customDaysContainer.classList.remove('hidden');
                days = parseInt(customDaysInput.value) || 10;
                if (days < 10) {
                    days = 10;
                    customDaysInput.value = 10;
                }
            } else {
                customDaysContainer.classList.add('hidden');
                days = parseInt(durationSelect.value);
            }
            
            displayDuration.textContent = days + " Days";
            
            // Calculate Rupee Price
            let price = 0;
            if (days === 10) price = 0;
            else if (days === 15) price = 5;
            else if (days === 30) price = 20;
            else if (days === 60) price = 50;
            else if (days === 90) price = 80;
            else price = (days - 10) * 1;
            
            if (userIsLoggedIn) {
                let creditCost = 0;
                if (freePagesLeft > 0) {
                    if (days <= 10) {
                        creditCost = 0;
                    } else {
                        creditCost = Math.ceil((days - 10) / 10);
                    }
                } else {
                    creditCost = Math.ceil(days / 10);
                }
                
                const creditsReqEl = document.getElementById('credits-required');
                if (creditsReqEl) {
                    creditsReqEl.textContent = creditCost + (creditCost === 1 ? ' Credit' : ' Credits');
                }
                const remaining = userCredits - creditCost;
                const remEl = document.getElementById('user-remaining-credits');
                if (remEl) {
                    remEl.textContent = remaining;
                    if (remaining < 0) {
                        remEl.classList.add('text-red-400');
                        remEl.classList.remove('text-white');
                    } else {
                        remEl.classList.remove('text-red-400');
                        remEl.classList.add('text-white');
                    }
                }
            } else {
                const priceEl = document.getElementById('duration-price');
                if (priceEl) {
                    priceEl.textContent = price === 0 ? 'Free' : '₹' + price;
                }
            }
        }

        let currentStep = 1;
        let totalSteps = 6;
        
        const prevBtn = document.getElementById('prev-btn');
        const nextBtn = document.getElementById('next-btn');
        const wizardForm = document.getElementById('wizard-form');
        const progressBar = document.getElementById('progress-bar');
        const stepIndicator = document.getElementById('step-indicator');
        const stepName = document.getElementById('step-name');
        
        const letterTextarea = document.getElementById('letter_text');
        const pageTitleInput = document.getElementById('page_title');
        const proposalQuestionInput = document.getElementById('proposal_question');

        window.togglePremiumEditUI = function(key, isChecked) {
            const container = document.getElementById('premium_fields_container_' + key);
            if (container) {
                container.classList.toggle('hidden', !isChecked);
                container.querySelectorAll('input, textarea, select, button').forEach(el => {
                    el.disabled = !isChecked;
                });
            }
        };

        function updateStepsLayoutFromToggles() {
            try {
                const cbLetter = document.getElementById('step2_letter_enabled');
                const cbGallery = document.getElementById('step2_gallery_enabled');
                const cbVoice = document.getElementById('step2_voice_message_enabled');
                const cbVideo = document.getElementById('step2_video_message_enabled');
                const cbSpecialDate = document.getElementById('step2_special_date_enabled');
                const cbEnding = document.getElementById('step2_interactive_ending');

                const letter = cbLetter ? cbLetter.checked : true;
                const gallery = cbGallery ? cbGallery.checked : true;
                const voice = cbVoice ? cbVoice.checked : true;
                const video = cbVideo ? cbVideo.checked : true;
                const specialDate = cbSpecialDate ? cbSpecialDate.checked : true;
                const ending = cbEnding ? cbEnding.checked : true;

                // Apply to later steps elements
                toggleSection('letter-section-container', letter);
                toggleSection('special-date-section-container', specialDate);
                toggleSection('gallery-section-container', gallery);
                toggleSection('video-section-container', video);
                toggleSection('voice-section-container', voice);
                toggleSection('ending-section-container', ending);

                // Toggle premium optional slides sections
                const checkedRadio = document.querySelector('input[name="category"]:checked');
                if (checkedRadio) {
                    const category = checkedRadio.value;
                    const defaults = categoryDefaults[category];
                    if (defaults && defaults.slides) {
                        defaults.slides.forEach(slide => {
                            if (slide.is_optional) {
                                const cbOpt = document.getElementById('step2_slide_' + slide.key + '_enabled');
                                const optEnabled = cbOpt ? cbOpt.checked : true;
                                toggleSection('creator_slide_' + slide.key + '_container', optEnabled);
                            }
                        });
                    }
                }
                
                // Subsettings for ending in Step 2 itself
                const endingSubsettings = document.getElementById('step2-ending-subsettings');
                if (endingSubsettings) {
                    if (ending) {
                        endingSubsettings.style.opacity = '1';
                        endingSubsettings.style.pointerEvents = 'auto';
                        endingSubsettings.querySelectorAll('input').forEach(i => i.disabled = false);
                    } else {
                        endingSubsettings.style.opacity = '0.3';
                        endingSubsettings.style.pointerEvents = 'none';
                        endingSubsettings.querySelectorAll('input').forEach(i => i.disabled = true);
                    }
                }
            } catch (e) {
                console.error("Error in updateStepsLayoutFromToggles:", e);
            }
        }

        // Step panels transition
        function showStep(step) {
            updateStepsLayoutFromToggles();
            
            // Remove active classes
            document.querySelectorAll('.step-panel').forEach(p => p.classList.remove('active'));
            
            const activePanel = document.querySelector(`.step-panel[data-step="${step}"]`);
            if (activePanel) {
                activePanel.classList.add('active');
                stepName.textContent = activePanel.getAttribute('data-name');
            }
            
            stepIndicator.textContent = `Step ${step} of ${totalSteps}`;
            progressBar.style.width = `${(step / totalSteps) * 100}%`;
            
            if (step === totalSteps) {
                updateValidityCost();
            }
            
            prevBtn.classList.toggle('invisible', step === 1);
            nextBtn.textContent = step === totalSteps ? 'Generate Page 🚀' : 'Continue →';
        }

        function validateFileSizes() {
            let totalSize = 0;
            const errors = [];

            // 1. Validate images
            const imageInput = document.getElementById('image_input');
            if (imageInput && imageInput.files) {
                for (let i = 0; i < imageInput.files.length; i++) {
                    const file = imageInput.files[i];
                    totalSize += file.size;
                    if (file.size > MAX_IMAGE_SIZE) {
                        errors.push(`Photo "${file.name}" is too large (${(file.size / 1024 / 1024).toFixed(2)}MB). Max allowed is 10MB.`);
                    }
                }
            }

            // 2. Validate video
            const videoInput = document.querySelector('input[name="video"]');
            if (videoInput && videoInput.files && videoInput.files.length > 0) {
                const file = videoInput.files[0];
                totalSize += file.size;
                if (file.size > MAX_VIDEO_SIZE) {
                    errors.push(`Video "${file.name}" is too large (${(file.size / 1024 / 1024).toFixed(2)}MB). Max allowed is 50MB.`);
                }
            }

            // 3. Validate voice (voice note)
            const voiceInput = document.getElementById('voice_file_input');
            if (voiceInput && voiceInput.files && voiceInput.files.length > 0) {
                const file = voiceInput.files[0];
                totalSize += file.size;
                if (file.size > MAX_VOICE_SIZE) {
                    errors.push(`Voice note "${file.name}" is too large (${(file.size / 1024 / 1024).toFixed(2)}MB). Max allowed is 10MB.`);
                }
            }

            // 4. Validate custom music
            const customMusicInput = document.getElementById('custom_music_file');
            if (customMusicInput && customMusicInput.files && customMusicInput.files.length > 0) {
                const file = customMusicInput.files[0];
                totalSize += file.size;
                if (file.size > MAX_MUSIC_SIZE) {
                    errors.push(`Custom background music "${file.name}" is too large (${(file.size / 1024 / 1024).toFixed(2)}MB). Max allowed is 10MB.`);
                }
            }

            // Premium slides media validation
            const checkedRadio = document.querySelector('input[name="category"]:checked');
            if (checkedRadio) {
                const category = checkedRadio.value;
                const defaults = categoryDefaults[category];
                if (defaults && defaults.slides) {
                    defaults.slides.forEach(slide => {
                        if (slide.is_optional) {
                            const cbOpt = document.getElementById('step2_slide_' + slide.key + '_enabled');
                            if (cbOpt && cbOpt.checked) {
                                // Validate images
                                const slideImagesInput = document.querySelector(`input[name="slide_${slide.key}_photos[]"]`);
                                if (slideImagesInput && slideImagesInput.files) {
                                    for (let i = 0; i < slideImagesInput.files.length; i++) {
                                        const file = slideImagesInput.files[i];
                                        totalSize += file.size;
                                        if (file.size > MAX_IMAGE_SIZE) {
                                            errors.push(`Slide "${slide.title}" photo "${file.name}" is too large (${(file.size / 1024 / 1024).toFixed(2)}MB). Max allowed is 10MB.`);
                                        }
                                    }
                                }
                                // Validate video
                                const slideVideoInput = document.querySelector(`input[name="slide_${slide.key}_video"]`);
                                if (slideVideoInput && slideVideoInput.files && slideVideoInput.files.length > 0) {
                                    const file = slideVideoInput.files[0];
                                    totalSize += file.size;
                                    if (file.size > MAX_VIDEO_SIZE) {
                                        errors.push(`Slide "${slide.title}" video "${file.name}" is too large (${(file.size / 1024 / 1024).toFixed(2)}MB). Max allowed is 50MB.`);
                                    }
                                }
                                // Validate voice
                                const slideVoiceInput = document.querySelector(`input[name="slide_${slide.key}_voice"]`);
                                if (slideVoiceInput && slideVoiceInput.files && slideVoiceInput.files.length > 0) {
                                    const file = slideVoiceInput.files[0];
                                    totalSize += file.size;
                                    if (file.size > MAX_VOICE_SIZE) {
                                        errors.push(`Slide "${slide.title}" voice note "${file.name}" is too large (${(file.size / 1024 / 1024).toFixed(2)}MB). Max allowed is 10MB.`);
                                    }
                                }
                                // Validate music
                                const slideMusicInput = document.querySelector(`input[name="slide_${slide.key}_music"]`);
                                if (slideMusicInput && slideMusicInput.files && slideMusicInput.files.length > 0) {
                                    const file = slideMusicInput.files[0];
                                    totalSize += file.size;
                                    if (file.size > MAX_MUSIC_SIZE) {
                                        errors.push(`Slide "${slide.title}" background music "${file.name}" is too large (${(file.size / 1024 / 1024).toFixed(2)}MB). Max allowed is 10MB.`);
                                    }
                                }
                            }
                        }
                    });
                }
            }

            // 6. Validate total size against POST_MAX_SIZE
            if (totalSize > POST_MAX_SIZE) {
                errors.push(`Total size of all selected files is ${(totalSize / 1024 / 1024).toFixed(2)}MB, which exceeds the server limit of ${(POST_MAX_SIZE / 1024 / 1024).toFixed(2)}MB. Please remove some photos or upload a smaller video.`);
            }

            if (errors.length > 0) {
                alert("Upload Size Validation Error:\n\n" + errors.join("\n"));
                return false;
            }
            return true;
        }

        // Add submit event listener fallback to prevent oversized form submission
        if (wizardForm) {
            wizardForm.addEventListener('submit', (e) => {
                if (!validateFileSizes()) {
                    e.preventDefault();
                    return false;
                }
                showGeneratingOverlay();
            });
        }
        
        // Next button logic
        nextBtn.addEventListener('click', () => {
            try {
                if (currentStep === 1) {
                    const checkedRadio = document.querySelector('input[name="category"]:checked');
                    if (!checkedRadio) {
                        alert('Please select a category.');
                        return;
                    }
                    const category = checkedRadio.value;
                    const defaults = categoryDefaults[category];
                    if (!defaults) {
                        alert('Selected category configuration could not be loaded.');
                        return;
                    }
                    
                    if (pageTitleInput && !pageTitleInput.value) {
                        pageTitleInput.placeholder = defaults.default_title || '';
                    }
                    if (letterTextarea && !letterTextarea.value) {
                        letterTextarea.value = defaults.default_letter || '';
                    }
                    
                    const interactiveQuestionInput = document.getElementById('proposal_question');
                    const interactiveYesInput = document.getElementById('interactive_yes_text');
                    const interactiveNoInput = document.getElementById('interactive_no_text');
                    
                    if (interactiveQuestionInput) {
                        interactiveQuestionInput.value = defaults.interactive_question || defaults.default_question || 'Will you be mine?';
                    }
                    if (interactiveYesInput) {
                        interactiveYesInput.value = defaults.interactive_yes_text || 'Yes! ❤️';
                    }
                    if (interactiveNoInput) {
                        interactiveNoInput.value = defaults.interactive_no_text || 'No';
                    }
                    
                    // Show/hide destination field
                    const destField = document.getElementById('destination-field');
                    if (destField) {
                        destField.classList.toggle('hidden', category !== 'invite_out');
                    }
                    
                    // Build category-specific slide fields
                    buildCategoryFields(category, defaults);
                    
                    renderMusicLibrary(category);
                    
                    // Auto-toggle checkboxes based on default slides of selected category
                    if (defaults && defaults.slides) {
                        const hasLetter = defaults.slides.some(s => s.type === 'letter');
                        const hasGallery = defaults.slides.some(s => s.type === 'gallery');
                        const hasVoice = defaults.slides.some(s => s.type === 'voice_message');
                        const hasVideo = defaults.slides.some(s => s.type === 'video_message');
                        const hasSpecialDate = defaults.slides.some(s => s.type === 'premium_love_counter' || s.type.includes('countdown') || s.type.includes('counter') || s.type.includes('relationship'));

                        const cbLetter = document.getElementById('step2_letter_enabled');
                        const cbGallery = document.getElementById('step2_gallery_enabled');
                        const cbVoice = document.getElementById('step2_voice_message_enabled');
                        const cbVideo = document.getElementById('step2_video_message_enabled');
                        const cbSpecialDate = document.getElementById('step2_special_date_enabled');
                        const cbEnding = document.getElementById('step2_interactive_ending');
                        const cbFunnyNo = document.getElementById('step2_funny_no');
                        const cbAskName = document.getElementById('step2_ask_name');

                        if (cbLetter) cbLetter.checked = hasLetter;
                        if (cbGallery) cbGallery.checked = hasGallery;
                        if (cbVoice) cbVoice.checked = hasVoice;
                        if (cbVideo) cbVideo.checked = hasVideo;
                        if (cbSpecialDate) cbSpecialDate.checked = hasSpecialDate;
                        if (cbEnding) cbEnding.checked = defaults.interactive_ending !== 0;
                        if (cbFunnyNo) cbFunnyNo.checked = defaults.interactive_funny_no !== 0;
                        if (cbAskName) cbAskName.checked = defaults.interactive_ask_name !== 0;

                        // Dynamically render optional premium slides toggles and forms in Step 2
                        const premiumContainer = document.getElementById('step2-dynamic-premium-slides');
                        if (premiumContainer) {
                            premiumContainer.innerHTML = '';
                            const optionalSlides = defaults.slides.filter(s => s.is_optional && s.key !== 'premium_floating_memories' && s.key !== 'premium_love_counter' && s.key !== 'premium_photo_spotlight');
                            if (optionalSlides.length > 0) {
                                premiumContainer.classList.remove('hidden');
                                
                                const heading = document.createElement('h4');
                                heading.className = 'text-xs font-bold text-slate-300 uppercase tracking-wider font-heading mb-3 mt-2';
                                heading.textContent = 'Premium Interactive Features';
                                premiumContainer.appendChild(heading);
                                
                                optionalSlides.forEach(slide => {
                                    const isStorySlide = ['premium_memory_book', 'premium_memory_timeline'].includes(slide.type);
                                    const isChatsSlide = slide.type === 'premium_our_chats';
                                    
                                    const div = document.createElement('div');
                                    div.className = 'p-5 bg-slate-900/40 border border-slate-800 rounded-3xl space-y-4 mb-4';
                                    
                                    let fieldsHTML = `
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <span class="text-sm font-semibold text-white block">✨ ${slide.title}</span>
                                                <span class="text-[10px] text-slate-500 font-medium">${slide.subtitle || 'Premium interactive slide block.'}</span>
                                            </div>
                                            <label class="relative inline-flex items-center cursor-pointer">
                                                <input type="hidden" name="slide_${slide.key}_enabled" value="0">
                                                <input type="checkbox" name="slide_${slide.key}_enabled" id="step2_slide_${slide.key}_enabled" value="1" class="sr-only peer" onchange="togglePremiumEditUI('${slide.key}', this.checked)">
                                                <div class="w-9 h-5 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-305 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-pink-500"></div>
                                            </label>
                                        </div>
                                        
                                        <!-- Customization Fields Container -->
                                        <div id="premium_fields_container_${slide.key}" class="space-y-4 pt-4 border-t border-slate-800/50">
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                <div>
                                                    <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1">Custom Heading</label>
                                                    <input type="text" name="slide_${slide.key}_title" value="${slide.title || ''}" class="w-full bg-slate-950 border border-slate-800 focus:border-pink-500 rounded-xl px-3 py-2 text-white outline-none text-xs">
                                                </div>
                                                <div>
                                                    <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1">Custom Subtitle</label>
                                                    <input type="text" name="slide_${slide.key}_subtitle" value="${slide.subtitle || ''}" class="w-full bg-slate-950 border border-slate-800 focus:border-pink-500 rounded-xl px-3 py-2 text-white outline-none text-xs">
                                                </div>
                                            </div>
                                    `;
                                    
                                    if (isChatsSlide) {
                                        fieldsHTML += `<div class="space-y-4 pt-2">
                                            <p class="text-[10px] text-pink-400 font-bold uppercase tracking-wider">Customize Chat Moments (Up to 5)</p>`;
                                        for (let m = 1; m <= 5; m++) {
                                            const defaultLabel = m === 1 ? 'Attachment 😍' : '';
                                            const defaultTitle = m === 1 ? 'Attachment Moment 📦' : '';
                                            const defaultDialogue = m === 1 ? "Her: You save everything about me 😂\nYou: Everything? 😜\nHer: What if someday I accidentally lose you?\nHer: My soul would leave my body 🥺\nYou: Drama queen 😂" : '';
                                            
                                            fieldsHTML += `
                                                <div class="border border-slate-800 bg-slate-950/40 p-4 rounded-2xl space-y-3">
                                                    <span class="text-xs font-bold text-pink-500">Moment ${m}</span>
                                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                        <div>
                                                            <label class="block text-[9px] text-slate-400 mb-1">Tab Label</label>
                                                            <input type="text" name="slide_${slide.key}_moment_${m}_label" value="${defaultLabel}" class="w-full bg-slate-900 border border-slate-800 focus:border-pink-500 rounded-xl px-2.5 py-1 text-white outline-none text-xs">
                                                        </div>
                                                        <div>
                                                            <label class="block text-[9px] text-slate-400 mb-1">Moment Title</label>
                                                            <input type="text" name="slide_${slide.key}_moment_${m}_title" value="${defaultTitle}" class="w-full bg-slate-900 border border-slate-800 focus:border-pink-500 rounded-xl px-2.5 py-1 text-white outline-none text-xs">
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <label class="block text-[9px] text-slate-400 mb-1">Dialogue (Dialogue should use "Her:" and "You:" prefixes)</label>
                                                        <textarea name="slide_${slide.key}_moment_${m}_dialogue" rows="3" class="w-full bg-slate-900 border border-slate-800 focus:border-pink-500 rounded-xl px-2.5 py-1 text-white outline-none text-xs resize-none" placeholder="Her: message&#10;You: message">${defaultDialogue}</textarea>
                                                    </div>
                                                </div>
                                            `;
                                        }
                                        fieldsHTML += `</div>`;
                                    } else {
                                        fieldsHTML += `
                                            <div>
                                                <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1">Custom Message / Story</label>
                                                <textarea name="slide_${slide.key}_message" rows="3" class="w-full bg-slate-950 border border-slate-800 focus:border-pink-500 rounded-xl px-3 py-2 text-white outline-none text-xs resize-none" placeholder="Enter custom message or story text..."></textarea>
                                            </div>
                                        `;
                                    }
                                    
                                    fieldsHTML += `
                                            <div>
                                                <label class="block text-[10px] font-semibold text-slate-400 uppercase mb-1">Custom Background Gradient (Optional)</label>
                                                <select name="slide_${slide.key}_bg" class="w-full bg-slate-950 border border-slate-800 text-white rounded-xl px-3 py-2 text-xs outline-none focus:border-pink-500">
                                                    <option value="">-- Use Category default theme --</option>
                                                    <option value="bg-gradient-to-b from-rose-950 via-rose-900 to-red-950">Rose Midnight Gradient</option>
                                                    <option value="bg-gradient-to-b from-slate-950 via-gray-900 to-zinc-950">Dark Onyx Gradient</option>
                                                    <option value="bg-gradient-to-b from-violet-950 via-purple-950 to-orange-950">Sunset Amethyst Gradient</option>
                                                    <option value="bg-gradient-to-b from-emerald-950 via-green-950 to-rose-950">Emerald Rosewood Gradient</option>
                                                    <option value="bg-gradient-to-b from-indigo-950 via-purple-950 to-violet-950">Cosmic Galaxy Gradient</option>
                                                </select>
                                            </div>
                                        </div>
                                    `;
                                    
                                    div.innerHTML = fieldsHTML;
                                    premiumContainer.appendChild(div);
                                    togglePremiumEditUI(slide.key, false);
                                });
                            } else {
                                premiumContainer.classList.add('hidden');
                            }
                        }
                    }
                    
                    currentStep++;
                    showStep(currentStep);
                } else if (currentStep === 2) {
                    currentStep++;
                    showStep(currentStep);
                } else if (currentStep === 3) {
                    const sender = document.getElementById('sender_name').value.trim();
                    const receiver = document.getElementById('receiver_name').value.trim();
                    if (!sender || !receiver) {
                        alert('Please enter both Sender and Recipient names.');
                        return;
                    }
                    currentStep++;
                    showStep(currentStep);
                } else if (currentStep < totalSteps) {
                    currentStep++;
                    showStep(currentStep);
                } else {
                    // Step 5: Verify credit sufficiency
                    if (userIsLoggedIn) {
                        const durationSelect = document.getElementById('expiry_duration');
                        const customDaysInput = document.getElementById('custom_days');
                        let days = 10;
                        if (durationSelect.value === 'custom') {
                            days = parseInt(customDaysInput.value) || 10;
                        } else {
                            days = parseInt(durationSelect.value);
                        }
                        
                        let creditCost = 0;
                        if (freePagesLeft > 0) {
                            if (days <= 10) creditCost = 0;
                            else creditCost = Math.ceil((days - 10) / 10);
                        } else {
                            creditCost = Math.ceil(days / 10);
                        }
                        
                        if (userCredits < creditCost) {
                            alert(`Insufficient credits! You need ${creditCost} credits for a ${days}-day duration, but you only have ${userCredits} credits. Please select a shorter duration or buy more credits.`);
                            return;
                        }
                    }

                    // Validate file sizes before submitting
                    if (!validateFileSizes()) {
                        return;
                    }
                    
                    showGeneratingOverlay();
                    setTimeout(() => {
                        wizardForm.submit();
                    }, 1200);
                }
            } catch (err) {
                console.error("Error in Next button handler:", err);
                alert("An error occurred during step navigation: " + err.message);
            }
        });
        
        prevBtn.addEventListener('click', () => {
            try {
                if (currentStep > 1) {
                    currentStep--;
                    showStep(currentStep);
                }
            } catch (err) {
                console.error("Error in Prev button handler:", err);
            }
        });
        
        // Build category-specific slide content fields
        function buildCategoryFields(category, config) {
            const container = document.getElementById('category-specific-fields');
            container.innerHTML = '';
            
            const slides = config.slides || [];
            slides.forEach(slide => {
                if (slide.type === 'text_story' && slide.key) {
                    const div = document.createElement('div');
                    div.innerHTML = `
                        <label class="block text-xs font-semibold text-slate-400 uppercase mb-2">${slide.title} (Optional)</label>
                        <textarea name="slide_${slide.key}" rows="3" 
                                  class="w-full bg-slate-900/60 border border-slate-800 focus:border-pink-500 focus:ring-1 focus:ring-pink-500 rounded-xl px-4 py-3 text-white placeholder-slate-500 outline-none resize-none text-sm"
                                  placeholder="${(slide.default || '').substring(0, 100)}...">${slide.default || ''}</textarea>
                    `;
                    container.appendChild(div);
                }
                if (slide.type === 'cards' && slide.key) {
                    const defaults = slide.defaults || [];
                    const div = document.createElement('div');
                    let cardsHTML = `<label class="block text-xs font-semibold text-slate-400 uppercase mb-2">${slide.title} Cards</label>`;
                    defaults.forEach((card, i) => {
                        cardsHTML += `<input type="text" name="cards_${slide.key}[]" value="${card}" 
                                      class="w-full bg-slate-900/60 border border-slate-800 focus:border-pink-500 rounded-xl px-4 py-2.5 text-white placeholder-slate-500 outline-none text-sm mb-2">`;
                    });
                    div.innerHTML = cardsHTML;
                    container.appendChild(div);
                }
            });
        }



        window.previewSlidePhotos = function(input, slideKey, isStorySlide) {
            const previewContainer = document.getElementById(`slide_photos_preview_${slideKey}`);
            if (!previewContainer) return;
            previewContainer.innerHTML = '';
            
            if (input.files && input.files.length > 0) {
                const count = Math.min(input.files.length, 10);
                for (let i = 0; i < count; i++) {
                    const file = input.files[i];
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        const row = document.createElement('div');
                        row.className = 'flex items-start space-x-3 bg-slate-950/40 p-3 rounded-xl border border-slate-850';
                        
                        let photoHTML = `
                            <div class="w-14 h-14 bg-slate-900 rounded-lg overflow-hidden border border-slate-800 flex-shrink-0">
                                <img src="${e.target.result}" class="w-full h-full object-cover">
                            </div>
                            <div class="flex-grow space-y-1.5">
                                <input type="text" name="slide_${slideKey}_photo_captions[]" placeholder="Photo Caption" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1 text-white outline-none focus:border-pink-500 text-xs">
                                <input type="text" name="slide_${slideKey}_photo_dates[]" placeholder="Memory Date (e.g. 2026-06-23)" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1 text-white outline-none focus:border-pink-500 text-xs">
                        `;
                        
                        if (isStorySlide) {
                            photoHTML += `
                                <textarea name="slide_${slideKey}_photo_memories[]" placeholder="Story / Memory description" rows="2" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1 text-white outline-none focus:border-pink-500 text-xs resize-none"></textarea>
                            `;
                        }
                        
                        photoHTML += `</div>`;
                        row.innerHTML = photoHTML;
                        previewContainer.appendChild(row);
                    };
                    reader.readAsDataURL(file);
                }
            }
        };
        
        // Photo preview, drag and drop, delete and reordering
        const imageInput = document.getElementById('image_input');
        const previewContainer = document.getElementById('image_preview_container');
        const dropzone = document.getElementById('dropzone');
        let selectedFiles = [];

        function updateInputFiles() {
            const dt = new DataTransfer();
            selectedFiles.forEach(file => dt.items.add(file));
            imageInput.files = dt.files;
        }

        async function handleFiles(files) {
            const validTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            const array = Array.from(files).filter(f => {
                const mime = f.type.toLowerCase();
                const ext = f.name.split('.').pop().toLowerCase();
                return validTypes.includes(mime) || ['jpg', 'jpeg', 'png', 'webp', 'gif'].includes(ext);
            });
            if (selectedFiles.length + array.length > 10) {
                alert('You can only upload up to 10 photos total.');
            }
            const cloned = await Promise.all(array.map(async f => {
                const buf = await f.arrayBuffer();
                return new File([buf], f.name, { type: f.type, lastModified: f.lastModified });
            }));
            selectedFiles = [...selectedFiles, ...cloned].slice(0, 10);
            renderPreviews();
            updateInputFiles();
        }

        function renderPreviews() {
            previewContainer.innerHTML = '';
            selectedFiles.forEach((file, index) => {
                const reader = new FileReader();
                reader.onload = (e) => {
                    const div = document.createElement('div');
                    div.className = 'relative aspect-square rounded-xl overflow-hidden bg-slate-900 border border-slate-800 cursor-grab active:cursor-grabbing thumbnail-item';
                    div.setAttribute('draggable', 'true');
                    div.setAttribute('data-index', index);
                    div.innerHTML = `
                        <img src="${e.target.result}" class="w-full h-full object-cover pointer-events-none">
                        <span class="absolute bottom-1 right-1 bg-slate-950/80 px-1.5 py-0.5 rounded text-[10px] text-slate-400 font-bold">${index + 1}</span>
                        <button type="button" onclick="deleteSelectedFile(${index})" class="absolute top-1 right-1 bg-red-500/80 hover:bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs font-bold transition shadow-md">&times;</button>
                    `;

                    // HTML5 Drag and Drop reordering
                    div.addEventListener('dragstart', (evt) => {
                        evt.dataTransfer.setData('text/plain', index);
                        div.classList.add('opacity-50');
                    });
                    div.addEventListener('dragend', () => {
                        div.classList.remove('opacity-50');
                    });
                    div.addEventListener('dragover', (evt) => {
                        evt.preventDefault();
                    });
                    div.addEventListener('drop', (evt) => {
                        evt.preventDefault();
                        const fromIndex = parseInt(evt.dataTransfer.getData('text/plain'));
                        const toIndex = index;
                        if (fromIndex !== toIndex && !isNaN(fromIndex)) {
                            const movedItem = selectedFiles.splice(fromIndex, 1)[0];
                            selectedFiles.splice(toIndex, 0, movedItem);
                            renderPreviews();
                            updateInputFiles();
                        }
                    });

                    previewContainer.appendChild(div);
                };
                reader.readAsDataURL(file);
            });
            
            const clearBtn = document.getElementById('clear-images-btn');
            if (clearBtn) {
                if (selectedFiles.length > 0) {
                    clearBtn.classList.remove('hidden');
                } else {
                    clearBtn.classList.add('hidden');
                }
            }
        }

        window.deleteSelectedFile = function(index) {
            selectedFiles.splice(index, 1);
            renderPreviews();
            updateInputFiles();
        };

        imageInput.addEventListener('change', () => {
            handleFiles(imageInput.files);
        });

        if (dropzone) {
            ['dragenter', 'dragover'].forEach(eventName => {
                dropzone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dropzone.classList.add('border-pink-500', 'bg-pink-500/10');
                }, false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                dropzone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    dropzone.classList.remove('border-pink-500', 'bg-pink-500/10');
                }, false);
            });

            dropzone.addEventListener('drop', (e) => {
                const dt = e.dataTransfer;
                if (dt && dt.files && dt.files.length > 0) {
                    handleFiles(dt.files);
                }
            });
        }
        
        // Music library renderer
        const previewAudio = document.getElementById('preview-audio');
        let playingTrackUrl = null;
        
        function renderMusicLibrary(category) {
            const container = document.getElementById('music-selector-container');
            container.innerHTML = '';
            
            const filtered = musicLibrary.filter(track => track.category === category || track.category === 'all');
            
            if (filtered.length === 0) {
                container.innerHTML = `<div class='text-xs text-slate-500 py-2'>No music tracks found for this category.</div>`;
                return;
            }
            
            filtered.forEach((track, index) => {
                const label = document.createElement('label');
                label.className = 'block cursor-pointer';
                label.innerHTML = `
                    <input type="radio" name="music_url" value="${track.file_path}" class="sr-only peer" ${index === 0 ? 'checked' : ''}>
                    <div class="flex items-center justify-between p-3.5 rounded-xl border border-slate-800 bg-slate-900/30 peer-checked:border-pink-500/50 peer-checked:bg-pink-500/5 hover:bg-slate-900/50 transition">
                        <div class="flex items-center space-x-3">
                            <button type="button" class="w-8 h-8 rounded-full bg-slate-800 hover:bg-pink-500 text-white flex items-center justify-center play-btn text-xs" data-url="${track.file_path}">â–¶</button>
                            <span class="text-sm font-semibold text-white">${track.title}</span>
                        </div>
                        <span class="text-slate-500 text-[10px] uppercase tracking-wider bg-slate-900 px-2 py-0.5 rounded-full">${track.category}</span>
                    </div>
                `;
                container.appendChild(label);
            });
            
            // Audio preview play logic
            container.querySelectorAll('.play-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    const trackUrl = btn.getAttribute('data-url');
                    if (playingTrackUrl === trackUrl) {
                        previewAudio.pause();
                        btn.textContent = 'â–¶';
                        playingTrackUrl = null;
                    } else {
                        container.querySelectorAll('.play-btn').forEach(b => b.textContent = 'â–¶');
                        previewAudio.src = trackUrl;
                        previewAudio.play().then(() => {
                            btn.textContent = 'â¸';
                            playingTrackUrl = trackUrl;
                        }).catch(() => {
                            btn.textContent = 'â¸';
                            playingTrackUrl = trackUrl;
                        });
                    }
                });
            });
        }
        
        // Password toggle
        document.getElementById('enable_password').addEventListener('change', function() {
            document.getElementById('password-field').classList.toggle('hidden', !this.checked);
            if (!this.checked) document.getElementById('page_password').value = '';
        });

        // Interactive ending toggle
        const interactiveEndingCheckbox = document.getElementById('interactive_ending');
        if (interactiveEndingCheckbox) {
            interactiveEndingCheckbox.addEventListener('change', function() {
                const settingsPanel = document.getElementById('interactive-ending-settings');
                if (settingsPanel) {
                    settingsPanel.classList.toggle('hidden', !this.checked);
                }
            });
        }
        
        // Stop audio when changing steps
        document.querySelectorAll('#next-btn, #prev-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                previewAudio.pause();
                playingTrackUrl = null;
            });
        });



        // Voice Note Upload Preview JS
        const voiceFileInput = document.getElementById('voice_file_input');
        const voiceUploadPreview = document.getElementById('voice-upload-preview');
        const voiceUploadPlayer = document.getElementById('voice-upload-player');
        const voiceUploadInfo = document.getElementById('voice-upload-info');

        if (voiceFileInput) {
            voiceFileInput.addEventListener('change', () => {
                if (voiceFileInput.files && voiceFileInput.files.length > 0) {
                    const file = voiceFileInput.files[0];
                    const sizeMB = (file.size / 1024 / 1024).toFixed(2);
                    const url = URL.createObjectURL(file);
                    
                    voiceUploadPlayer.src = url;
                    voiceUploadInfo.textContent = `${file.name} — ${sizeMB} MB`;
                    voiceUploadPreview.classList.remove('hidden');
                    
                    // Try to detect duration
                    const tempAudio = new Audio(url);
                    tempAudio.addEventListener('loadedmetadata', () => {
                        if (isFinite(tempAudio.duration)) {
                            document.getElementById('voice_duration_input').value = Math.round(tempAudio.duration);
                        }
                    });
                } else {
                    voiceUploadPreview.classList.add('hidden');
                    voiceUploadPlayer.src = '';
                    voiceUploadInfo.textContent = '';
                }
            });
        }

        function showGeneratingOverlay() {
            const overlay = document.getElementById('generating-overlay');
            if (overlay) {
                overlay.classList.remove('pointer-events-none');
                overlay.classList.add('opacity-100');
            }
            
            const statuses = [
                'Brewing the love potions... 💖',
                'Designing your premium responsive theme... 🎨',
                'Optimizing your photo layouts... 📸',
                'Preparing background music tracks... 🎵',
                'Polishing micro-animations... ✨',
                'Sealing with a digital kiss... 💋'
            ];
            
            let currentStatusIdx = 0;
            const statusText = document.getElementById('generating-status');
            const progressBar = document.getElementById('generating-progress');
            
            let progress = 0;
            const progressInterval = setInterval(() => {
                progress += Math.random() * 8 + 2;
                if (progress >= 95) {
                    progress = 95;
                    clearInterval(progressInterval);
                }
                if (progressBar) progressBar.style.width = `${progress}%`;
            }, 250);
            
            const statusInterval = setInterval(() => {
                currentStatusIdx = (currentStatusIdx + 1) % statuses.length;
                if (statusText) statusText.textContent = statuses[currentStatusIdx];
            }, 1200);
        }

        // Section Toggles
        function toggleSection(sectionId, isChecked) {
            const section = document.getElementById(sectionId);
            if (!section) return;
            if (isChecked) {
                section.classList.remove('opacity-30', 'pointer-events-none');
            } else {
                section.classList.add('opacity-30', 'pointer-events-none');
            }
        }

        // Deselect Selected Images
        function clearSelectedImages() {
            selectedFiles = [];
            updateInputFiles();
            renderPreviews();
        }

        // Deselect Selected Video
        function clearSelectedVideo() {
            const videoInput = document.getElementById('video_file_input');
            if (videoInput) videoInput.value = '';
            const clearBtn = document.getElementById('clear-video-btn');
            if (clearBtn) clearBtn.classList.add('hidden');
        }

        // Deselect Selected Voice
        function clearSelectedVoice() {
            const voiceInput = document.getElementById('voice_file_input');
            if (voiceInput) voiceInput.value = '';
            
            const preview = document.getElementById('voice-upload-preview');
            if (preview) preview.classList.add('hidden');
            
            const player = document.getElementById('voice-upload-player');
            if (player) {
                player.src = '';
                player.load();
            }
            
            const durationInput = document.getElementById('voice_duration_input');
            if (durationInput) durationInput.value = '0';

            const clearBtn = document.getElementById('clear-voice-btn');
            if (clearBtn) clearBtn.classList.add('hidden');
        }

        // Hook up Voice Note Deselect show/hide on change
        if (voiceFileInput) {
            voiceFileInput.addEventListener('change', () => {
                const clearBtn = document.getElementById('clear-voice-btn');
                if (clearBtn) {
                    if (voiceFileInput.files && voiceFileInput.files.length > 0) {
                        clearBtn.classList.remove('hidden');
                    } else {
                        clearBtn.classList.add('hidden');
                    }
                }
            });
        }

        // Slide Voice Recording Variables
        let activeSlideRecordKey = null;
        let slideMediaRecorder = null;
        let slideAudioChunks = [];
        let slideRecordSeconds = 0;
        let isSlideRecordPaused = false;
        let slideRecordTimerInterval = null;
        let slideAudioBlob = null;
        let slideAudioCtx = null;
        let slideAnalyser = null;
        let slideVisualSource = null;
        let slideDrawVisualId = null;

        const slideModal = document.getElementById('slide-record-modal');
        const slideModalTitle = document.getElementById('slide-record-title');
        const slideCloseBtn = document.getElementById('btn-close-slide-record');
        const slideStatusText = document.getElementById('slide-record-status-text');
        const slideTimerSpan = document.getElementById('slide-record-timer');
        const slideVisualizerCanvas = document.getElementById('slide-record-visualizer');
        const slideStartBtn = document.getElementById('btn-start-slide-rec');
        const slidePauseBtn = document.getElementById('btn-pause-slide-rec');
        const slideResumeBtn = document.getElementById('btn-resume-slide-rec');
        const slideStopBtn = document.getElementById('btn-stop-slide-rec');
        const slidePreviewContainer = document.getElementById('slide-record-preview-container');
        const slidePreviewPlayer = document.getElementById('slide-record-preview-player');
        const slideClearBtn = document.getElementById('btn-clear-slide-rec');
        const slideSaveBtn = document.getElementById('btn-save-slide-rec');

        function drawSlideWaveform() {
            if (!slideAnalyser || !slideVisualizerCanvas) return;
            const ctx = slideVisualizerCanvas.getContext('2d');
            const bufferLength = slideAnalyser.frequencyBinCount;
            const dataArray = new Uint8Array(bufferLength);
            
            slideVisualizerCanvas.style.display = 'block';

            function draw() {
                if (!slideMediaRecorder || slideMediaRecorder.state === 'inactive') {
                    slideVisualizerCanvas.style.display = 'none';
                    return;
                }
                slideDrawVisualId = requestAnimationFrame(draw);
                
                slideAnalyser.getByteFrequencyData(dataArray);
                ctx.fillStyle = 'rgba(15, 23, 42, 0.7)';
                ctx.fillRect(0, 0, slideVisualizerCanvas.width, slideVisualizerCanvas.height);

                const barWidth = (slideVisualizerCanvas.width / bufferLength) * 2.5;
                let barHeight;
                let x = 0;

                for (let i = 0; i < bufferLength; i++) {
                    barHeight = dataArray[i] / 2.5;
                    ctx.fillStyle = isSlideRecordPaused ? '#64748b' : '#ec4899';
                    ctx.fillRect(x, slideVisualizerCanvas.height - barHeight, barWidth - 1, barHeight);
                    x += barWidth;
                }
            }
            draw();
        }

        window.openSlideVoiceRecorder = function(slideKey) {
            activeSlideRecordKey = slideKey;
            slideModalTitle.textContent = slideKey;
            slideModal.classList.remove('hidden');
            slideModal.classList.add('flex');
            resetSlideRecorderUI();
        }

        function resetSlideRecorderUI() {
            slideStatusText.textContent = 'Ready to record';
            slideTimerSpan.textContent = '0:00';
            slideRecordSeconds = 0;
            isSlideRecordPaused = false;
            slideAudioChunks = [];
            slideAudioBlob = null;
            
            slideStartBtn.classList.remove('hidden');
            slidePauseBtn.classList.add('hidden');
            slideResumeBtn.classList.add('hidden');
            slideStopBtn.classList.add('hidden');
            slidePreviewContainer.classList.add('hidden');
            slideVisualizerCanvas.style.display = 'none';
            slidePreviewPlayer.src = '';

            if (slideRecordTimerInterval) clearInterval(slideRecordTimerInterval);
            if (slideDrawVisualId) cancelAnimationFrame(slideDrawVisualId);
            if (slideAudioCtx && slideAudioCtx.state !== 'closed') {
                slideAudioCtx.close();
            }
        }

        if (slideCloseBtn) {
            slideCloseBtn.addEventListener('click', () => {
                slideModal.classList.add('hidden');
                slideModal.classList.remove('flex');
                resetSlideRecorderUI();
            });
        }

        if (slideStartBtn) {
            slideStartBtn.addEventListener('click', async () => {
                try {
                    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                    slideAudioChunks = [];
                    isSlideRecordPaused = false;
                    
                    let mimeType = 'audio/webm';
                    if (MediaRecorder.isTypeSupported('audio/mp4')) mimeType = 'audio/mp4';
                    else if (MediaRecorder.isTypeSupported('audio/ogg')) mimeType = 'audio/ogg';
                    else if (MediaRecorder.isTypeSupported('audio/wav')) mimeType = 'audio/wav';

                    slideMediaRecorder = new MediaRecorder(stream, { mimeType });
                    
                    try {
                        slideAudioCtx = new (window.AudioContext || window.webkitAudioContext)();
                        slideAnalyser = slideAudioCtx.createAnalyser();
                        slideVisualSource = slideAudioCtx.createMediaStreamSource(stream);
                        slideVisualSource.connect(slideAnalyser);
                        slideAnalyser.fftSize = 64;
                        drawSlideWaveform();
                    } catch (ae) {
                        console.warn("Slide visualizer failed to init", ae);
                    }

                    slideMediaRecorder.ondataavailable = (event) => {
                        if (event.data.size > 0) {
                            slideAudioChunks.push(event.data);
                        }
                    };

                    slideMediaRecorder.onstop = () => {
                        slideAudioBlob = new Blob(slideAudioChunks, { type: mimeType });
                        const audioUrl = URL.createObjectURL(slideAudioBlob);
                        slidePreviewPlayer.src = audioUrl;
                        slidePreviewContainer.classList.remove('hidden');
                        
                        stream.getTracks().forEach(track => track.stop());
                        if (slideAudioCtx && slideAudioCtx.state !== 'closed') {
                            slideAudioCtx.close();
                        }
                        if (slideDrawVisualId) cancelAnimationFrame(slideDrawVisualId);
                        slideVisualizerCanvas.style.display = 'none';
                    };

                    slideMediaRecorder.start();
                    
                    slideStartBtn.classList.add('hidden');
                    slidePauseBtn.classList.remove('hidden');
                    slideStopBtn.classList.remove('hidden');
                    slideResumeBtn.classList.add('hidden');
                    slideStatusText.textContent = 'Recording';

                    slideRecordSeconds = 0;
                    slideTimerSpan.textContent = '0:00';
                    slideRecordTimerInterval = setInterval(() => {
                        if (!isSlideRecordPaused) {
                            slideRecordSeconds++;
                            const mins = Math.floor(slideRecordSeconds / 60);
                            const secs = slideRecordSeconds % 60;
                            slideTimerSpan.textContent = `${mins}:${secs < 10 ? '0' : ''}${secs}`;
                            
                            if (slideRecordSeconds >= 300) {
                                slideStopBtn.click();
                            }
                        }
                    }, 1000);

                } catch (err) {
                    console.error('Microphone error:', err);
                    if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError' || err.message.includes('permission')) {
                        showMicPermissionModal(() => slideStartBtn.click());
                    } else {
                        alert('Recording failed: ' + err.message);
                    }
                }
            });
        }

        if (slidePauseBtn) {
            slidePauseBtn.addEventListener('click', () => {
                if (slideMediaRecorder && slideMediaRecorder.state === 'recording') {
                    slideMediaRecorder.pause();
                    isSlideRecordPaused = true;
                    slidePauseBtn.classList.add('hidden');
                    slideResumeBtn.classList.remove('hidden');
                    slideStatusText.textContent = 'Paused';
                }
            });
        }

        if (slideResumeBtn) {
            slideResumeBtn.addEventListener('click', () => {
                if (slideMediaRecorder && slideMediaRecorder.state === 'paused') {
                    slideMediaRecorder.resume();
                    isSlideRecordPaused = false;
                    slideResumeBtn.classList.add('hidden');
                    slidePauseBtn.classList.remove('hidden');
                    slideStatusText.textContent = 'Recording';
                }
            });
        }

        if (slideStopBtn) {
            slideStopBtn.addEventListener('click', () => {
                if (slideMediaRecorder && slideMediaRecorder.state !== 'inactive') {
                    slideMediaRecorder.stop();
                    clearInterval(slideRecordTimerInterval);
                    slideStartBtn.classList.remove('hidden');
                    slidePauseBtn.classList.add('hidden');
                    slideResumeBtn.classList.add('hidden');
                    slideStopBtn.classList.add('hidden');
                    slideStatusText.textContent = 'Stopped';
                }
            });
        }

        if (slideClearBtn) {
            slideClearBtn.addEventListener('click', () => {
                resetSlideRecorderUI();
            });
        }

        if (slideSaveBtn) {
            slideSaveBtn.addEventListener('click', () => {
                if (!slideAudioBlob || !activeSlideRecordKey) return;
                
                const mimeType = slideAudioBlob.type;
                const extension = mimeType.split('/')[1].split(';')[0];
                const file = new File([slideAudioBlob], `recorded_slide_voice_${activeSlideRecordKey}.${extension}`, { type: mimeType });

                const dt = new DataTransfer();
                dt.items.add(file);

                const fileInput = document.getElementById(`slide_${activeSlideRecordKey}_voice_file_input`);
                if (fileInput) {
                    fileInput.files = dt.files;
                }

                const durationInput = document.getElementById(`slide_${activeSlideRecordKey}_voice_duration`);
                if (durationInput) {
                    durationInput.value = slideRecordSeconds;
                }

                const statusDiv = document.getElementById(`slide_voice_status_${activeSlideRecordKey}`);
                if (statusDiv) {
                    statusDiv.innerHTML = `<span class="text-emerald-400 truncate">Recorded: recorded_slide_voice_${activeSlideRecordKey}.${extension} (${slideRecordSeconds}s)</span>`;
                }

                slideModal.classList.add('hidden');
                slideModal.classList.remove('flex');
                resetSlideRecorderUI();
            });
        }
    </script>

    <!-- Premium fullscreen loading overlay -->
    <div id="generating-overlay" class="fixed inset-0 bg-slate-950/90 z-50 flex flex-col items-center justify-center backdrop-blur-md opacity-0 pointer-events-none transition-opacity duration-500">
        <div class="relative w-64 h-64 flex items-center justify-center mb-8">
            <!-- Glowing rings -->
            <div class="absolute inset-0 rounded-full border-4 border-t-pink-500 border-r-transparent border-b-violet-500 border-l-transparent animate-spin duration-1000"></div>
            <div class="absolute inset-4 rounded-full border-4 border-t-transparent border-r-purple-500 border-b-transparent border-l-cyan-400 animate-spin duration-700 reverse"></div>
            <div class="absolute inset-8 rounded-full border-2 border-dashed border-pink-400/30 animate-pulse"></div>
            <!-- Center icon -->
            <div class="text-4xl animate-bounce">💖</div>
        </div>
        <!-- Generating status and progress -->
        <h3 class="text-xl font-bold text-white mb-2 tracking-wide text-center">Creating Your Magic Page...</h3>
        <p id="generating-status" class="text-sm text-pink-400 font-medium h-6 animate-pulse text-center">Injecting romance and memories...</p>
        
        <!-- Progress bar -->
        <div class="w-64 h-1.5 bg-slate-800 rounded-full overflow-hidden mt-6 shadow-inner">
            <div id="generating-progress" class="h-full bg-gradient-to-r from-pink-500 via-purple-500 to-cyan-400 w-0 transition-all duration-300 ease-out"></div>
        </div>
        <p class="text-[10px] text-slate-400 font-bold mt-4 text-center tracking-wider uppercase animate-pulse">âš ï¸ Please do not press back or refresh. Be patient...</p>
    </div>

    <!-- Firebase JS SDK & Analytics -->
    <script type="importmap">
      {
        "imports": {
          "firebase/app": "https://www.gstatic.com/firebasejs/12.15.0/firebase-app.js",
          "firebase/analytics": "https://www.gstatic.com/firebasejs/12.15.0/firebase-analytics.js"
        }
      }
    </script>
    <script type="module">
      // Import the functions you need from the SDKs you need
      import { initializeApp } from "firebase/app";
      import { getAnalytics } from "firebase/analytics";

      // Your web app's Firebase configuration
      const firebaseConfig = {
        apiKey: "AIzaSyB-UcbJripzj5BYfXNZzGVGNRvp6fdpzdk",
        authDomain: "loopr-5afff.firebaseapp.com",
        projectId: "loopr-5afff",
        storageBucket: "loopr-5afff.firebasestorage.app",
        messagingSenderId: "461317839365",
        appId: "1:461317839365:web:5a7e62412a085120edda6c",
        measurementId: "G-E64N7NW2HW"
      };

      // Initialize Firebase
      const app = initializeApp(firebaseConfig);
      const analytics = getAnalytics(app);
    </script>

    <!-- Slide Voice Record Modal -->
    <div id="slide-record-modal" class="fixed inset-0 bg-slate-950/85 backdrop-blur-sm z-[999] hidden items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 max-w-sm w-full text-center space-y-4 shadow-2xl relative">
            <button type="button" id="btn-close-slide-record" class="absolute top-4 right-4 text-slate-500 hover:text-slate-300 text-lg font-bold">&times;</button>
            <div class="w-12 h-12 rounded-full bg-pink-500/10 text-pink-500 flex items-center justify-center mx-auto text-xl">🎙️</div>
            <h3 class="text-white font-bold text-sm">Record Voice for <span id="slide-record-title">Slide</span></h3>
            
            <div class="flex items-center justify-between mb-3 text-xs bg-slate-950/20 p-2.5 rounded-xl border border-slate-800">
                <span id="slide-record-status-text" class="text-slate-400 font-semibold">Ready to record</span>
                <span id="slide-record-timer" class="font-mono text-pink-400 font-bold">0:00</span>
            </div>

            <!-- Waveform Canvas -->
            <canvas id="slide-record-visualizer" class="w-full h-12 bg-slate-950/60 rounded-xl border border-slate-800/40 hidden" width="300" height="48"></canvas>

            <div class="flex items-center justify-center gap-2">
                <button type="button" id="btn-start-slide-rec" class="px-4 py-2 bg-pink-600 hover:bg-pink-500 text-white rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-lg shadow-pink-600/20">
                    <span>âºï¸</span> <span>Record</span>
                </button>
                <button type="button" id="btn-pause-slide-rec" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-xs font-bold transition flex items-center gap-1.5 hidden">
                    <span>â¸ï¸</span> <span>Pause</span>
                </button>
                <button type="button" id="btn-resume-slide-rec" class="px-4 py-2 bg-pink-600 hover:bg-pink-500 text-white rounded-xl text-xs font-bold transition flex items-center gap-1.5 hidden">
                    <span>â–¶ï¸</span> <span>Resume</span>
                </button>
                <button type="button" id="btn-stop-slide-rec" class="px-4 py-2 bg-red-600 hover:bg-red-500 text-white rounded-xl text-xs font-bold transition flex items-center gap-1.5 hidden">
                    <span>â¹ï¸</span> <span>Stop</span>
                </button>
            </div>

            <!-- Preview & Save -->
            <div id="slide-record-preview-container" class="space-y-3 hidden pt-3 border-t border-slate-800/50">
                <audio id="slide-record-preview-player" controls class="h-8 w-full max-w-[240px] mx-auto rounded-lg"></audio>
                <div class="flex items-center gap-3">
                    <button type="button" id="btn-clear-slide-rec" class="flex-grow py-2 bg-slate-800 hover:bg-slate-750 text-red-400 font-semibold rounded-xl text-xs transition">Delete</button>
                    <button type="button" id="btn-save-slide-rec" class="flex-grow py-2 bg-gradient-to-r from-pink-500 to-purple-500 text-white font-bold rounded-xl text-xs uppercase tracking-wider transition hover:opacity-95">Save Recording</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Microphone permission alert modal -->
    <div id="mic-permission-modal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-[999] hidden items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 max-w-sm w-full text-center space-y-4 shadow-2xl">
            <div class="w-12 h-12 rounded-full bg-red-500/10 text-red-400 flex items-center justify-center mx-auto text-xl font-sans">âš ï¸</div>
            <h3 class="text-white font-bold text-sm">Microphone Access Required</h3>
            <p class="text-xs text-slate-400 leading-relaxed">Microphone access is required to record a voice message. Please enable it in your browser settings and try again.</p>
            <div class="flex items-center gap-3 pt-2">
                <button type="button" id="btn-mic-retry" class="flex-1 py-2 bg-gradient-to-r from-pink-500 to-purple-500 text-white font-bold rounded-xl text-xs uppercase tracking-wider transition hover:opacity-95">Allow Again</button>
                <button type="button" id="btn-mic-cancel" class="flex-1 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold rounded-xl text-xs transition">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        function showMicPermissionModal(retryCallback) {
            const modal = document.getElementById('mic-permission-modal');
            const retryBtn = document.getElementById('btn-mic-retry');
            const cancelBtn = document.getElementById('btn-mic-cancel');
            if (!modal) return;
            
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            
            // Clean up old events
            const newRetryBtn = retryBtn.cloneNode(true);
            const newCancelBtn = cancelBtn.cloneNode(true);
            retryBtn.parentNode.replaceChild(newRetryBtn, retryBtn);
            cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
            
            newRetryBtn.addEventListener('click', () => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                if (retryCallback) retryCallback();
            });
            
            newCancelBtn.addEventListener('click', () => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            });
        }
    </script>
<script src="assets/js/img-compress.js"></script>
<script src="assets/js/audio-fix.js"></script>
<script src="assets/js/aac-playback-fix.js"></script>
<?php render_tutorial_button('normal'); ?>
</body>
</html>
