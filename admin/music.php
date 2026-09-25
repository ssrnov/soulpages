<?php
require_once '../includes/functions.php';

// Verify admin role
if (!is_logged_in() || !is_admin()) {
    redirect('login.php');
}

$error = '';
$success = '';

// Handle file addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_music') {
    if (isset($_FILES['music_files']) && is_array($_FILES['music_files']['name'])) {
        $file_count = count($_FILES['music_files']['name']);
        $uploaded_count = 0;
        $errors = [];
        
        for ($i = 0; $i < $file_count; $i++) {
            if ($_FILES['music_files']['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue; // Skip empty slots
            }
            
            $title = trim($_POST['titles'][$i] ?? '');
            $category = $_POST['categories'][$i] ?? 'all';
            
            if (empty($title)) {
                $orig_name = $_FILES['music_files']['name'][$i];
                $title = pathinfo($orig_name, PATHINFO_FILENAME);
                $title = ucwords(str_replace(['_', '-'], ' ', $title));
            }
            
            if ($_FILES['music_files']['error'][$i] !== UPLOAD_ERR_OK) {
                $errors[] = "File #" . ($i + 1) . " ('" . h($_FILES['music_files']['name'][$i]) . "') upload failed with error code " . $_FILES['music_files']['error'][$i] . ".";
                continue;
            }
            
            $file_size = $_FILES['music_files']['size'][$i];
            $tmp_name = $_FILES['music_files']['tmp_name'][$i];
            $name = $_FILES['music_files']['name'][$i];
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $allowed_audio_exts = ['mp3', 'aac', 'acc', 'm4a', 'wav', 'ogg', 'webm'];
            
            if (!in_array($ext, $allowed_audio_exts)) {
                $errors[] = "'" . h($name) . "': Only MP3, AAC, M4A, WAV, OGG, WEBM files are allowed.";
                continue;
            }
            
            if ($file_size > 10 * 1024 * 1024) {
                $errors[] = "'" . h($name) . "': Audio file exceeds 10MB limit.";
                continue;
            }
            
            $music_dir = '../assets/music/';
            if (!is_dir($music_dir)) {
                mkdir($music_dir, 0755, true);
            }
            
            // Unique file name
            $filename = md5(uniqid(rand(), true)) . '.' . $ext;
            $target = $music_dir . $filename;
            
            if (move_uploaded_file($tmp_name, $target)) {
                $db_path = 'assets/music/' . $filename;
                
                $stmt = $pdo->prepare("INSERT INTO music_library (title, file_path, category) VALUES (?, ?, ?)");
                if ($stmt->execute([$title, $db_path, $category])) {
                    $uploaded_count++;
                } else {
                    $errors[] = "'" . h($name) . "': Failed to save to database.";
                    if (file_exists($target)) {
                        unlink($target);
                    }
                }
            } else {
                $errors[] = "'" . h($name) . "': Failed to move uploaded file. Check folder permissions.";
            }
        }
        
        if ($uploaded_count > 0) {
            $success = "Successfully uploaded {$uploaded_count} music track(s).";
        }
        if (!empty($errors)) {
            $error = implode("<br>", $errors);
        }
    } elseif (isset($_FILES['music_file']) && $_FILES['music_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $title = trim($_POST['title'] ?? '');
        $category = $_POST['category'] ?? 'all';
        
        if (empty($title)) {
            $error = 'Music title is required.';
        } elseif (!isset($_FILES['music_file']) || $_FILES['music_file']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Please select a valid audio file to upload.';
        } else {
            $file = $_FILES['music_file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed_audio_exts = ['mp3', 'aac', 'acc', 'm4a', 'wav', 'ogg', 'webm'];
            
            if (!in_array($ext, $allowed_audio_exts)) {
                $error = 'Only MP3, AAC, M4A, WAV, OGG, WEBM files are allowed.';
            } elseif ($file['size'] > 10 * 1024 * 1024) {
                $error = 'Audio file exceeds 10MB limit.';
            } else {
                $music_dir = '../assets/music/';
                if (!is_dir($music_dir)) {
                    mkdir($music_dir, 0755, true);
                }
                
                // Unique file name
                $filename = md5(uniqid(rand(), true)) . '.' . $ext;
                $target = $music_dir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $target)) {
                    // Save path relative to root directory
                    $db_path = 'assets/music/' . $filename;
                    
                    $stmt = $pdo->prepare("INSERT INTO music_library (title, file_path, category) VALUES (?, ?, ?)");
                    if ($stmt->execute([$title, $db_path, $category])) {
                        $success = 'Music track added successfully.';
                    } else {
                        $error = 'Failed to save to database.';
                    }
                } else {
                    $error = 'Failed to move uploaded file. Check folder permissions.';
                }
            }
        }
    } else {
        $error = 'Please select one or more audio files to upload.';
    }
}

// Handle deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    
    // Fetch file path
    $stmt = $pdo->prepare("SELECT file_path FROM music_library WHERE id = ?");
    $stmt->execute([$delete_id]);
    $filepath = $stmt->fetchColumn();
    
    if ($filepath) {
        // Delete local file if it exists and is under uploads/assets
        $local_path = '../' . $filepath;
        if (file_exists($local_path) && strpos($filepath, 'assets/music/') === 0) {
            unlink($local_path);
        }
        
        $stmt_del = $pdo->prepare("DELETE FROM music_library WHERE id = ?");
        $stmt_del->execute([$delete_id]);
        $success = 'Music track removed successfully.';
    }
}

// Handle Approve Action
if (isset($_GET['approve']) && is_numeric($_GET['approve'])) {
    $approve_id = (int)$_GET['approve'];
    $stmt = $pdo->prepare("UPDATE music_library SET status = 'approved' WHERE id = ?");
    if ($stmt->execute([$approve_id])) {
        $success = 'Music track approved and added to public library successfully.';
    } else {
        $error = 'Failed to approve music track.';
    }
}

// Handle Reject Action
if (isset($_GET['reject']) && is_numeric($_GET['reject'])) {
    $reject_id = (int)$_GET['reject'];
    $stmt = $pdo->prepare("UPDATE music_library SET status = 'rejected' WHERE id = ?");
    if ($stmt->execute([$reject_id])) {
        $success = 'Music track request rejected successfully.';
    } else {
        $error = 'Failed to reject music track.';
    }
}

// Fetch all music library (approved or admin uploads)
$stmt = $pdo->query("SELECT * FROM music_library WHERE status = 'approved' OR status IS NULL OR uploaded_by IS NULL ORDER BY category, title");
$music_list = $stmt->fetchAll();

// Fetch pending library music submissions
$stmt_pending = $pdo->query("SELECT ml.*, u.name as user_name FROM music_library ml LEFT JOIN users u ON ml.uploaded_by = u.id WHERE ml.status = 'pending' AND ml.is_private = 0 ORDER BY ml.id DESC");
$pending_list = $stmt_pending->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Music Library - SoulSync Admin</title>
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
        .hero-bg {
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
<body class="bg-slate-950 text-slate-100 font-sans min-h-screen flex flex-col justify-between hero-bg antialiased">

    <!-- Header -->
    <?php $ADMIN_TITLE='Music Library'; include '_nav.php'; ?>

    <main class="flex-grow max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-10">
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left panel: Add track -->
            <div class="glass-card rounded-3xl p-6 h-fit relative">
                <h3 class="text-lg font-bold font-heading text-white mb-4">Add Background Tracks</h3>
                
                <?php if (!empty($error)): ?>
                    <div class="bg-red-500/15 border border-red-500/30 text-red-400 p-4 rounded-xl mb-4 text-xs">
                        ⚠️ <?= $error ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($success)): ?>
                    <div class="bg-green-500/15 border border-green-500/30 text-green-400 p-4 rounded-xl mb-4 text-xs">
                        ✅ <?= h($success) ?>
                    </div>
                <?php endif; ?>

                <form id="upload-form" action="music.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="action" value="add_music">
                    
                    <!-- Drag & Drop Area -->
                    <div id="drop-zone" class="border-2 border-dashed border-slate-800 hover:border-pink-500/50 rounded-2xl p-6 text-center cursor-pointer transition bg-slate-900/20 hover:bg-slate-900/40 relative">
                        <input type="file" id="file-input" name="music_files[]" accept=".mp3,.aac,.acc,.m4a,.wav,.ogg,.webm" multiple class="hidden">
                        <div class="space-y-2 pointer-events-none">
                            <span class="text-3xl block">🎵</span>
                            <p class="text-xs font-semibold text-slate-300">Drag & Drop audio files here</p>
                            <p class="text-[10px] text-slate-500">or click to browse from device</p>
                        </div>
                    </div>

                    <!-- Staging Area (hidden by default) -->
                    <div id="staging-container" class="space-y-3 hidden">
                        <div class="flex justify-between items-center pb-2 border-b border-slate-900">
                            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Staged Tracks (<span id="staged-count">0</span>)</span>
                            <button type="button" id="clear-all-btn" class="text-[10px] text-red-400 hover:text-red-300 font-semibold uppercase tracking-wider">Clear All</button>
                        </div>
                        
                        <!-- Scrollable list of staged cards -->
                        <div id="staged-list" class="space-y-3 max-h-[350px] overflow-y-auto pr-1">
                            <!-- Populated via Javascript -->
                        </div>
                        
                        <button type="submit" id="upload-submit-btn" class="w-full py-3 bg-gradient-to-r from-pink-500 to-purple-500 text-white font-bold rounded-xl text-xs uppercase tracking-widest transition shadow hover:opacity-95 flex items-center justify-center gap-2">
                            <span>🚀</span> Upload Tracks
                        </button>
                    </div>
                    
                    <!-- Progress / Spinner Overlay during upload -->
                    <div id="upload-loading-overlay" class="hidden text-center py-4 bg-slate-950/80 rounded-2xl absolute inset-0 flex flex-col justify-center items-center backdrop-blur-sm z-50">
                        <div class="w-8 h-8 border-2 border-pink-500 border-t-transparent rounded-full animate-spin mb-2"></div>
                        <p class="text-xs text-pink-400 font-semibold">Uploading tracks, please wait...</p>
                    </div>

                    <p class="text-[9px] text-slate-600 text-center">Allowed formats: MP3, AAC, M4A, WAV, OGG, WEBM up to 10MB per track.</p>
                </form>
            </div>

            <!-- Right panel: Music List -->
            <div class="glass-card rounded-3xl p-6 lg:col-span-2 space-y-8">
                <!-- Pending Submissions -->
                <div>
                    <h3 class="text-lg font-bold font-heading text-white mb-4">Pending Public Submissions</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-slate-400">
                            <thead class="bg-slate-900/80 text-xs text-slate-500 uppercase tracking-wider">
                                <tr>
                                    <th class="p-4 rounded-l-xl">Song Title</th>
                                    <th class="p-4">Category</th>
                                    <th class="p-4">Uploaded By</th>
                                    <th class="p-4 rounded-r-xl">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-900">
                                <?php if (count($pending_list) === 0): ?>
                                    <tr>
                                        <td colspan="4" class="p-4 text-center text-slate-600 text-xs">No pending public submissions.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($pending_list as $track): ?>
                                        <tr class="hover:bg-slate-900/20 transition">
                                            <td class="p-4 font-semibold text-white">
                                                <div class="flex items-center space-x-2">
                                                    <audio id="audio-preview-<?= $track['id'] ?>" src="../<?= h($track['file_path']) ?>" preload="none"></audio>
                                                    <button onclick="const a = document.getElementById('audio-preview-<?= $track['id'] ?>'); if(a.paused){a.play(); this.textContent='⏸';}else{a.pause(); this.textContent='▶';}" class="w-6 h-6 rounded-full bg-slate-800 flex items-center justify-center text-xs">▶</button>
                                                    <span><?= h($track['title']) ?></span>
                                                </div>
                                            </td>
                                            <td class="p-4">
                                                <span class="text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded bg-slate-900 border border-slate-800"><?= h($track['category']) ?></span>
                                            </td>
                                            <td class="p-4 text-xs font-medium">
                                                <?= h($track['user_name'] ?? 'Guest') ?>
                                            </td>
                                            <td class="p-4 text-xs flex space-x-3">
                                                <a href="music.php?approve=<?= $track['id'] ?>" class="text-green-500 hover:text-green-400 font-semibold transition">Approve</a>
                                                <a href="music.php?reject=<?= $track['id'] ?>" class="text-yellow-500 hover:text-yellow-400 font-semibold transition">Reject</a>
                                                <a href="music.php?delete=<?= $track['id'] ?>" onclick="return confirm('Are you sure you want to completely delete this track file?')" class="text-red-500 hover:text-red-400 font-semibold transition">Delete File</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div>
                    <h3 class="text-lg font-bold font-heading text-white mb-4 font-heading">Background Music Library</h3>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-400">
                        <thead class="bg-slate-900/80 text-xs text-slate-500 uppercase tracking-wider">
                            <tr>
                                <th class="p-4 rounded-l-xl">Song Title</th>
                                <th class="p-4">Category</th>
                                <th class="p-4">Path</th>
                                <th class="p-4 rounded-r-xl">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-900">
                            <?php if (count($music_list) === 0): ?>
                                <tr>
                                    <td colspan="4" class="p-8 text-center text-slate-600">No tracks uploaded.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($music_list as $track): ?>
                                    <tr class="hover:bg-slate-900/20 transition">
                                        <td class="p-4 font-semibold text-white">
                                            <?= h($track['title']) ?>
                                        </td>
                                        <td class="p-4">
                                            <span class="text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded bg-slate-900 border border-slate-800"><?= h($track['category']) ?></span>
                                        </td>
                                        <td class="p-4 font-mono text-[10px] truncate max-w-[150px]">
                                            <?= h($track['file_path']) ?>
                                        </td>
                                        <td class="p-4 text-xs">
                                            <a href="music.php?delete=<?= $track['id'] ?>" onclick="return confirm('Are you sure you want to delete this track?')" class="text-red-500 hover:text-red-400 font-semibold transition">Remove</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    </main>

    <!-- Footer -->
    <footer class="bg-slate-950 border-t border-slate-900 py-6 text-center text-slate-600 text-xs">
        &copy; <?= date('Y') ?> SoulSync Admin.
    </footer>

    <!-- Local preview audio player -->
    <audio id="local-preview-audio" class="hidden"></audio>

    <!-- Row template for dynamic track creation -->
    <template id="track-row-template">
        <div class="track-row bg-slate-950/40 border border-slate-800/80 rounded-2xl p-4 space-y-3 relative transition hover:border-slate-700">
            <div class="flex justify-between items-start gap-2">
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-xs text-white truncate file-name" title=""></p>
                    <p class="text-[9px] text-slate-500 font-mono file-size"></p>
                </div>
                <button type="button" class="remove-btn text-slate-500 hover:text-red-400 transition text-sm p-1" title="Remove track">&times;</button>
            </div>
            
            <div class="space-y-2 pt-1">
                <div>
                    <label class="block text-[9px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Track Title</label>
                    <input type="text" name="titles[]" required placeholder="Enter track title" class="w-full bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1.5 text-xs outline-none text-white focus:border-pink-500/50 transition title-input">
                </div>
                <div>
                    <label class="block text-[9px] font-semibold text-slate-500 uppercase tracking-wider mb-1">Topic / Category</label>
                    <select name="categories[]" class="w-full bg-slate-900 border border-slate-800 text-white rounded-lg px-2 py-1.5 text-xs outline-none focus:border-pink-500/50 transition category-select">
                        <option value="premium">✨ Premium Pages</option>
                        <optgroup label="🎉 Festivals (fixed track per festival)">
                            <?php foreach (get_festivals() as $fst): ?>
                                <option value="<?= h($fst['slug']) ?>"><?= h($fst['emoji']) ?> <?= h($fst['name']) ?></option>
                            <?php endforeach; ?>
                            <option value="festival">🎊 All Festivals (fallback)</option>
                        </optgroup>
                        <optgroup label="Categories">
                            <?php foreach (get_categories(true) as $key => $cat): ?>
                                <option value="<?= h($key) ?>"><?= h($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <option value="all">Universal (All themes)</option>
                    </select>
                </div>
            </div>
            
            <div class="flex justify-between items-center pt-2 border-t border-slate-800/50">
                <button type="button" class="preview-btn text-[10px] text-purple-400 hover:text-purple-300 font-bold uppercase tracking-wider flex items-center gap-1.5 transition select-none">
                    <span class="preview-icon">▶</span> <span class="preview-text">Preview</span>
                </button>
            </div>
        </div>
    </template>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const dropZone = document.getElementById('drop-zone');
            const fileInput = document.getElementById('file-input');
            const stagingContainer = document.getElementById('staging-container');
            const stagedList = document.getElementById('staged-list');
            const stagedCount = document.getElementById('staged-count');
            const clearAllBtn = document.getElementById('clear-all-btn');
            const uploadForm = document.getElementById('upload-form');
            const uploadOverlay = document.getElementById('upload-loading-overlay');
            const template = document.getElementById('track-row-template');
            
            const previewAudio = document.getElementById('local-preview-audio');
            let activePreviewBtn = null;
            let activeObjectURL = null;

            // Global array of staged files
            let stagedFiles = [];

            // Open file selector when clicking drop zone
            dropZone.addEventListener('click', () => fileInput.click());

            // Drag and Drop behaviors
            ['dragenter', 'dragover'].forEach(eventName => {
                dropZone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    dropZone.classList.add('border-pink-500', 'bg-slate-900/50');
                }, false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    dropZone.classList.remove('border-pink-500', 'bg-slate-900/50');
                }, false);
            });

            dropZone.addEventListener('drop', (e) => {
                const dt = e.dataTransfer;
                const files = dt.files;
                if (files.length > 0) {
                    addFiles(files);
                }
            });

            fileInput.addEventListener('change', () => {
                if (fileInput.files.length > 0) {
                    addFiles(fileInput.files);
                }
            });

            // Clear All button
            clearAllBtn.addEventListener('click', () => {
                stopPreview();
                stagedFiles = [];
                updateUI();
            });

            // Form Submit Show Loading Spinner
            uploadForm.addEventListener('submit', () => {
                stopPreview();
                uploadOverlay.classList.remove('hidden');
                uploadOverlay.classList.add('flex');
                document.getElementById('upload-submit-btn').disabled = true;
            });

            // Process added files
            function addFiles(filesList) {
                const allowedExts = ['mp3', 'aac', 'acc', 'm4a', 'wav', 'ogg', 'webm'];
                
                for (let i = 0; i < filesList.length; i++) {
                    const file = filesList[i];
                    const ext = file.name.split('.').pop().toLowerCase();
                    
                    if (!allowedExts.includes(ext)) {
                        alert(`File "${file.name}" is not supported. Only audio files are allowed.`);
                        continue;
                    }
                    
                    if (file.size > 10 * 1024 * 1024) {
                        alert(`File "${file.name}" exceeds the 10MB limit.`);
                        continue;
                    }

                    // Check if file is already added
                    const alreadyAdded = stagedFiles.some(f => f.name === file.name && f.size === file.size);
                    if (!alreadyAdded) {
                        stagedFiles.push(file);
                    }
                }
                
                updateUI();
            }

            // Sync HTML File Input with stagedFiles array using DataTransfer API
            function syncFileInput() {
                const dataTransfer = new DataTransfer();
                stagedFiles.forEach(file => {
                    dataTransfer.items.add(file);
                });
                fileInput.files = dataTransfer.files;
            }

            // Helper to clean file names and generate titles
            function cleanFileNameToTitle(fileName) {
                const withoutExt = fileName.substring(0, fileName.lastIndexOf('.')) || fileName;
                // Replace hyphens/underscores with spaces
                let cleaned = withoutExt.replace(/[_-]+/g, ' ');
                // Camelcase capitalize
                return cleaned.split(' ').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
            }

            // Update UI list
            function updateUI() {
                stagedList.innerHTML = '';
                
                if (stagedFiles.length === 0) {
                    stagingContainer.classList.add('hidden');
                    syncFileInput();
                    return;
                }
                
                stagingContainer.classList.remove('hidden');
                stagedCount.textContent = stagedFiles.length;
                
                stagedFiles.forEach((file, index) => {
                    const clone = template.content.cloneNode(true);
                    
                    // Card references
                    const card = clone.querySelector('.track-row');
                    const fileNameEl = clone.querySelector('.file-name');
                    const fileSizeEl = clone.querySelector('.file-size');
                    const titleInput = clone.querySelector('.title-input');
                    const removeBtn = clone.querySelector('.remove-btn');
                    const previewBtn = clone.querySelector('.preview-btn');
                    
                    // Populate info
                    fileNameEl.textContent = file.name;
                    fileNameEl.title = file.name;
                    fileSizeEl.textContent = (file.size / (1024 * 1024)).toFixed(2) + ' MB';
                    
                    // Suggested title
                    titleInput.value = cleanFileNameToTitle(file.name);
                    
                    // Remove button event
                    removeBtn.addEventListener('click', () => {
                        // If this row was playing preview, stop it
                        if (activePreviewBtn === previewBtn) {
                            stopPreview();
                        }
                        stagedFiles.splice(index, 1);
                        updateUI();
                    });
                    
                    // Preview event
                    previewBtn.addEventListener('click', () => {
                        togglePreview(file, previewBtn);
                    });
                    
                    stagedList.appendChild(clone);
                });
                
                syncFileInput();
            }

            // Preview player controls
            function togglePreview(file, button) {
                const iconSpan = button.querySelector('.preview-icon');
                const textSpan = button.querySelector('.preview-text');

                if (activePreviewBtn === button) {
                    // Pause
                    if (!previewAudio.paused) {
                        previewAudio.pause();
                        iconSpan.textContent = '▶';
                        textSpan.textContent = 'Preview';
                    } else {
                        previewAudio.play();
                        iconSpan.textContent = '⏸';
                        textSpan.textContent = 'Pause';
                    }
                } else {
                    // Start new preview
                    stopPreview();
                    
                    activePreviewBtn = button;
                    activeObjectURL = URL.createObjectURL(file);
                    
                    previewAudio.src = activeObjectURL;
                    previewAudio.play()
                        .then(() => {
                            iconSpan.textContent = '⏸';
                            textSpan.textContent = 'Pause';
                            button.classList.add('text-pink-400');
                        })
                        .catch(err => {
                            console.error('Audio playback error:', err);
                            alert('Could not preview this audio file.');
                            stopPreview();
                        });
                }
            }

            function stopPreview() {
                if (previewAudio) {
                    previewAudio.pause();
                    previewAudio.removeAttribute('src');
                }
                
                if (activeObjectURL) {
                    URL.revokeObjectURL(activeObjectURL);
                    activeObjectURL = null;
                }
                
                if (activePreviewBtn) {
                    const iconSpan = activePreviewBtn.querySelector('.preview-icon');
                    const textSpan = activePreviewBtn.querySelector('.preview-text');
                    if (iconSpan) iconSpan.textContent = '▶';
                    if (textSpan) textSpan.textContent = 'Preview';
                    activePreviewBtn.classList.remove('text-pink-400');
                    activePreviewBtn = null;
                }
            }

            // Listen for end of audio preview
            previewAudio.addEventListener('ended', () => {
                stopPreview();
            });
        });
    </script>
<script src="../assets/js/aac-playback-fix.js"></script>
</body>
</html>

