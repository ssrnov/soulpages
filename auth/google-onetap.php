<?php
// Google One Tap Login Handler (receives JWT credential via POST)
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

try {

$credential = $_POST['credential'] ?? '';
if (empty($credential)) {
    echo json_encode(['success' => false, 'error' => 'No credential provided.']);
    exit;
}

// Decode JWT (Google ID Token)
$parts = explode('.', $credential);
if (count($parts) !== 3) {
    echo json_encode(['success' => false, 'error' => 'Invalid token format.']);
    exit;
}

$payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
if (!$payload) {
    echo json_encode(['success' => false, 'error' => 'Failed to decode token.']);
    exit;
}

// Verify audience
$google_client_id = defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : '';
if (function_exists('get_setting')) {
    $google_client_id = get_setting('google_client_id', $google_client_id);
}
if (($payload['aud'] ?? '') !== $google_client_id) {
    echo json_encode(['success' => false, 'error' => 'Token audience mismatch. Expected: ' . substr($google_client_id, 0, 20) . '... Got: ' . substr($payload['aud'] ?? '', 0, 20) . '...']);
    exit;
}

// Verify not expired
if (($payload['exp'] ?? 0) < time()) {
    echo json_encode(['success' => false, 'error' => 'Token expired.']);
    exit;
}

// Verify issuer
if (!in_array($payload['iss'] ?? '', ['accounts.google.com', 'https://accounts.google.com'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid token issuer.']);
    exit;
}

// Extract user info
$email = $payload['email'] ?? '';
$name  = $payload['name'] ?? '';
$photo = $payload['picture'] ?? '';

if (empty($email)) {
    echo json_encode(['success' => false, 'error' => 'No email in token.']);
    exit;
}

// Find or create user
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    // New user — only use columns that exist in schema
    $pdo->prepare("INSERT INTO users (name, email, password, profile_photo, role, status) VALUES (?, ?, '', ?, 'user', 'active')")
        ->execute([$name, $email, $photo]);
    $uid = $pdo->lastInsertId();

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$uid]);
    $user = $stmt->fetch();
} else {
    // Update photo & name
    $pdo->prepare("UPDATE users SET profile_photo = ?, name = ? WHERE id = ?")
        ->execute([$photo, $name, $user['id']]);
}

// Check suspension
if (($user['status'] ?? 'active') === 'suspended') {
    echo json_encode(['success' => false, 'error' => 'Your account has been suspended.']);
    exit;
}

// Set session
if (function_exists('regenerate_user_session')) {
    regenerate_user_session();
}
$_SESSION['user_id']    = $user['id'];
$_SESSION['user_name']  = $user['name'];
$_SESSION['user_email'] = $user['email'];
$_SESSION['user_role']  = $user['role'];
$_SESSION['user_photo'] = $photo;

$base = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');
$redirect = in_array($user['role'], ['admin', 'manager'], true) ? $base . '/admin/index.php' : $base . '/index.php';
echo json_encode(['success' => true, 'redirect' => $redirect]);

} catch (\Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
