<?php
// Google OAuth 2.0 Callback Handler
require_once __DIR__ . '/../includes/functions.php';

// Get authorization code from Google
$code = $_GET['code'] ?? '';
$error = $_GET['error'] ?? '';

if ($error) {
    $_SESSION['login_error'] = 'Google login was cancelled or denied.';
    redirect('../login.php');
}

if (empty($code)) {
    $_SESSION['login_error'] = 'Invalid Google login response.';
    redirect('../login.php');
}

$google_client_id = get_setting('google_client_id', GOOGLE_CLIENT_ID);
$google_client_secret = get_setting('google_client_secret', GOOGLE_CLIENT_SECRET);

// Determine dynamic redirect URI if placeholder is set
$google_redirect_uri = GOOGLE_REDIRECT_URI;
if (strpos($google_redirect_uri, 'yourdomain.com') !== false) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $google_redirect_uri = $protocol . $host . $_SERVER['SCRIPT_NAME'];
}

// Exchange authorization code for tokens
$token_url = 'https://oauth2.googleapis.com/token';
$token_data = [
    'code' => $code,
    'client_id' => $google_client_id,
    'client_secret' => $google_client_secret,
    'redirect_uri' => $google_redirect_uri,
    'grant_type' => 'authorization_code',
];

$ch = curl_init($token_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($token_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
$token_response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200) {
    $_SESSION['login_error'] = 'Failed to authenticate with Google. Please try again.';
    redirect('../login.php');
}

$token_json = json_decode($token_response, true);
$access_token = $token_json['access_token'] ?? '';

if (empty($access_token)) {
    $_SESSION['login_error'] = 'Invalid token received from Google.';
    redirect('../login.php');
}

// Fetch user profile from Google
$profile_url = 'https://www.googleapis.com/oauth2/v2/userinfo';
$ch = curl_init($profile_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $access_token"]);
$profile_response = curl_exec($ch);
curl_close($ch);

$profile = json_decode($profile_response, true);
$google_id = $profile['id'] ?? '';
$email = $profile['email'] ?? '';
$name = $profile['name'] ?? '';
$photo = $profile['picture'] ?? '';

if (empty($google_id) || empty($email)) {
    $_SESSION['login_error'] = 'Could not retrieve your Google profile. Please try again.';
    redirect('../login.php');
}

// Check if user exists by google_id
$stmt = $pdo->prepare("SELECT * FROM users WHERE google_id = ?");
$stmt->execute([$google_id]);
$user = $stmt->fetch();

if (!$user) {
    // Check if email matches existing user (merge accounts)
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'user'");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user) {
        // Update existing user with Google ID and photo
        $pdo->prepare("UPDATE users SET google_id = ?, profile_photo = ?, name = ?, email_verified = 1 WHERE id = ?")
            ->execute([$google_id, $photo, $name, $user['id']]);
        $user['google_id'] = $google_id;
        $user['profile_photo'] = $photo;
    } else {
        // Create new user
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
        
        $stmt = $pdo->prepare("INSERT INTO users (name, email, google_id, profile_photo, role, status, referral_code, email_verified) VALUES (?, ?, ?, ?, 'user', 'active', ?, 1)");
        $stmt->execute([$name, $email, $google_id, $photo, $referral_code]);
        $user_id = $pdo->lastInsertId();
        
        // Initialize credits (0 credits — first page is free by policy, not by credit)
        $pdo->prepare("INSERT INTO user_credits (user_id, credits) VALUES (?, 0)")->execute([$user_id]);
        
        // Process referral if cookie exists
        $ref_code = $_COOKIE['sp_ref'] ?? ($_SESSION['referral_code'] ?? '');
        if (!empty($ref_code)) {
            process_referral($user_id, $ref_code);
            // Clear referral cookie
            setcookie('sp_ref', '', time() - 3600, '/');
            unset($_SESSION['referral_code']);
        }
        
        // Fetch the newly created user
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
    }
}

// Check if user is suspended
if (($user['status'] ?? 'active') === 'suspended') {
    $_SESSION['login_error'] = 'Your account has been suspended. Please contact support.';
    redirect('../login.php');
}

// Update profile photo on each login
if ($user['profile_photo'] !== $photo) {
    $pdo->prepare("UPDATE users SET profile_photo = ? WHERE id = ?")
        ->execute([$photo, $user['id']]);
}

// Set session
$_SESSION['user_id'] = $user['id'];
$_SESSION['user_name'] = $user['name'];
$_SESSION['user_email'] = $user['email'];
$_SESSION['user_role'] = $user['role'];
$_SESSION['user_photo'] = $photo;

// Redirect to dashboard
redirect('../dashboard.php');
