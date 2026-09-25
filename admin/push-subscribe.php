<?php
// Stores an admin device's push subscription; optionally sends a test push.
require_once '../includes/functions.php';
header('Content-Type: application/json');

if (!is_logged_in() || !is_admin()) { echo json_encode(['ok' => false]); exit; }

$raw = file_get_contents('php://input');
$sub = json_decode($raw, true);
if (!is_array($sub) || empty($sub['endpoint'])) { echo json_encode(['ok' => false, 'error' => 'bad subscription']); exit; }

add_push_sub($sub);

// ?test=1 → send an immediate push to confirm the full path works
if (isset($_GET['test'])) {
    $code = webpush_send(['endpoint' => $sub['endpoint']]);
    echo json_encode(['ok' => true, 'test' => true, 'code' => $code, 'sent' => in_array($code, [200, 201, 202], true)]);
    exit;
}

echo json_encode(['ok' => true]);
