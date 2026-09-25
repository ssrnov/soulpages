<?php
// Tiny JSON endpoint the installed admin app polls for new verification requests.
require_once '../includes/functions.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');

if (!is_logged_in() || !is_admin()) {
    echo json_encode(['ok' => false]);
    exit;
}

$pending = count_verify_requests($pdo);
$latest = '';
try {
    $latest = (string)$pdo->query("SELECT COALESCE(MAX(verify_requested_at),'') FROM users WHERE email_verified = 0 AND verify_requested_at IS NOT NULL")->fetchColumn();
} catch (\Throwable $e) {}

echo json_encode(['ok' => true, 'count' => $pending, 'latest' => $latest]);
