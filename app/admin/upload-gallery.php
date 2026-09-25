<?php
ini_set('upload_max_filesize', '100M');
ini_set('post_max_size', '105M');
ini_set('max_execution_time', '300');
require __DIR__ . '/_boot.php';
ss_require_owner();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$uid = (int)($_POST['user_id'] ?? 0);
if ($uid < 1) {
    echo json_encode(['success' => false, 'error' => 'Missing user_id']);
    exit;
}
if (empty($_FILES['file'])) {
    echo json_encode(['success' => false, 'error' => 'No file']);
    exit;
}

$file = $_FILES['file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'Upload error: ' . $file['error']]);
    exit;
}

$origName = basename($file['name']);
$safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $origName);
$fullDir  = __DIR__ . '/../uploads/gallery/' . $uid . '/full';
if (!is_dir($fullDir)) mkdir($fullDir, 0755, true);
move_uploaded_file($file['tmp_name'], $fullDir . '/' . $safeName);

$relPath = 'uploads/gallery/' . $uid . '/full/' . $safeName;
$mime    = $file['type'] ?: 'application/octet-stream';
$size    = $file['size'];
$ext     = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
$cat     = match(true) {
    in_array($ext, ['jpg','jpeg','png','webp','gif']) => 'image',
    in_array($ext, ['mp4','3gp','mkv','mov','avi'])   => 'video',
    in_array($ext, ['mp3','m4a','aac','ogg','opus','amr','wav']) => 'audio',
    in_array($ext, ['pdf','doc','docx','xls','xlsx','ppt','pptx','txt','csv']) => 'document',
    default => 'image'
};

try {
    $pdo->prepare("INSERT INTO gallery_photos (user_id, filename, mime_type, width, height, file_size, photo_date, file_path, category, is_private, is_sent)
                   VALUES (?, ?, ?, 0, 0, ?, NOW(), ?, ?, 0, 0)
                   ON DUPLICATE KEY UPDATE file_path=VALUES(file_path), file_size=VALUES(file_size), synced_at=NOW()")
        ->execute([$uid, $origName, $mime, $size, $relPath, $cat]);
} catch (\Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'DB error']);
    exit;
}

echo json_encode(['success' => true, 'data' => ['saved' => true, 'filename' => $origName, 'path' => $relPath]]);
