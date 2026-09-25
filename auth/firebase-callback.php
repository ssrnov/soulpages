<?php
// Firebase Authentication Callback Handler
// Receives Firebase ID token from frontend, verifies it, and creates/logs in user.
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

// Accept JSON body
$input = json_decode(file_get_contents('php://input'), true);
$id_token = $input['idToken'] ?? '';

if (empty($id_token)) {
    echo json_encode(['success' => false, 'error' => 'No ID token provided.']);
    exit;
}

// =========================================================================
// Verify Firebase ID Token using Google Identity Toolkit API
// This uses your Firebase API key to verify the token server-side.
// =========================================================================
$firebase_api_key = FIREBASE_API_KEY;

$verify_url = "https://identitytoolkit.googleapis.com/v1/accounts:lookup?key=" . urlencode($firebase_api_key);

$ch = curl_init($verify_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['idToken' => $id_token]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($http_code !== 200 || empty($response)) {
    echo json_encode(['success' => false, 'error' => 'Failed to verify token with Firebase. ' . $curl_error]);
    exit;
}

$result = json_decode($response, true);
$users_data = $result['users'] ?? [];

if (empty($users_data) || !isset($users_data[0])) {
    echo json_encode(['success' => false, 'error' => 'Invalid or expired token.']);
    exit;
}

$firebase_user = $users_data[0];
$firebase_uid = $firebase_user['localId'] ?? '';
$email = $firebase_user['email'] ?? '';
$name = $firebase_user['displayName'] ?? '';
$photo = $firebase_user['photoUrl'] ?? '';
$email_verified = $firebase_user['emailVerified'] ?? false;

if (empty($firebase_uid) || empty($email)) {
    echo json_encode(['success' => false, 'error' => 'Could not retrieve your profile from Firebase.']);
    exit;
}

// =========================================================================
// Find or create user in database
// =========================================================================

// Check if user exists by firebase_uid (stored in google_id column for compatibility)
$stmt = $pdo->prepare("SELECT * FROM users WHERE google_id = ?");
$stmt->execute([$firebase_uid]);
$user = $stmt->fetch();

if (!$user) {
    // Check if email matches an existing user (merge accounts)
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'user'");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // Update existing user with Firebase UID and photo
        $pdo->prepare("UPDATE users SET google_id = ?, profile_photo = ?, name = ?, email_verified = 1 WHERE id = ?")
            ->execute([$firebase_uid, $photo, $name, $user['id']]);
        $user['google_id'] = $firebase_uid;
        $user['profile_photo'] = $photo;
    } else {
        // Create new user (Sign Up)
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
        $stmt->execute([$name, $email, $firebase_uid, $photo, $referral_code]);
        $user_id = $pdo->lastInsertId();

        // Initialize credits (0 credits — first page is free by policy)
        $pdo->prepare("INSERT INTO user_credits (user_id, credits) VALUES (?, 0)")->execute([$user_id]);

        // Process referral if cookie exists
        $ref_code = $_COOKIE['sp_ref'] ?? ($_SESSION['referral_code'] ?? '');
        if (!empty($ref_code)) {
            process_referral($user_id, $ref_code);
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
    echo json_encode(['success' => false, 'error' => 'Your account has been suspended. Please contact support.']);
    exit;
}

// Update profile photo on each login
if (($user['profile_photo'] ?? '') !== $photo && !empty($photo)) {
    $pdo->prepare("UPDATE users SET profile_photo = ? WHERE id = ?")
        ->execute([$photo, $user['id']]);
}

// Set session
$_SESSION['user_id'] = $user['id'];
$_SESSION['user_name'] = $user['name'];
$_SESSION['user_email'] = $user['email'];
$_SESSION['user_role'] = $user['role'];
$_SESSION['user_photo'] = $photo ?: ($user['profile_photo'] ?? '');

echo json_encode([
    'success' => true,
    'redirect' => 'dashboard.php',
    'user' => [
        'name' => $user['name'],
        'email' => $user['email'],
    ]
]);
