<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';

try {

$credential = $_POST['credential'] ?? '';
if (empty($credential)) {
    echo json_encode(['success' => false, 'error' => 'No credential provided.']);
    exit;
}

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

$google_client_id = '619239393964-epm3b0dbfm0b2c3lgdbg0bu432qfa2nk.apps.googleusercontent.com';
if (($payload['aud'] ?? '') !== $google_client_id) {
    echo json_encode(['success' => false, 'error' => 'Token audience mismatch.']);
    exit;
}

if (($payload['exp'] ?? 0) < time()) {
    echo json_encode(['success' => false, 'error' => 'Token expired.']);
    exit;
}

if (!in_array($payload['iss'] ?? '', ['accounts.google.com', 'https://accounts.google.com'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid token issuer.']);
    exit;
}

$email = $payload['email'] ?? '';
if (empty($email)) {
    echo json_encode(['success' => false, 'error' => 'No email in token.']);
    exit;
}

$stmt = $pdo->prepare("SELECT id, username, name, email FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    echo json_encode(['success' => false, 'error' => 'No account found with this email.']);
    exit;
}

$chk = $pdo->prepare("SELECT 1 FROM tracking_viewers WHERE viewer_id = ? LIMIT 1");
$chk->execute([$user['id']]);
if (!$chk->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Access denied. You are not authorized to view tracking data.']);
    exit;
}

$_SESSION['viewer_id'] = $user['id'];
echo json_encode(['success' => true]);

} catch (\Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
