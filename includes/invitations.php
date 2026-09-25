<?php
// =========================================================================
// INVITATION STUDIO — ZIP-based animated video invitation templates.
// Registry JSON in site_settings 'invitation_templates'; each installed
// template lives in uploads/invitations/<id>/ with a template.json.
// Rendering + MP4 export happen fully in the user's browser (canvas +
// MediaRecorder), so shared hosting never does video work.
// =========================================================================

/**
 * Filesystem root for installed templates. Absolute, so it works no matter
 * which directory the calling script lives in (admin/ vs site root) —
 * relative paths made admin uploads land in admin/uploads/ by mistake.
 */
function inv_fs_root() {
    return dirname(__DIR__) . '/uploads/invitations/';
}

function inv_registry() {
    $r = json_decode(get_setting('invitation_templates', ''), true);
    return is_array($r) ? $r : [];
}

function inv_save_registry($list) {
    set_setting('invitation_templates', json_encode(array_values($list), JSON_UNESCAPED_UNICODE));
}

/** Resolve a translatable value ({en,hi,...} or string) to a plain string. */
function inv_plain($v) {
    if (is_array($v)) return (string)($v['en'] ?? reset($v) ?? '');
    return (string)$v;
}

/** Apply admin field/event overrides (from registry meta) onto a template. */
function inv_apply_overrides($tpl, $meta) {
    $fo = $meta['fields_override'] ?? [];
    if (is_array($fo) && !empty($tpl['fields'])) {
        foreach ($tpl['fields'] as &$f) {
            if (isset($fo[$f['key']]) && $fo[$f['key']] !== '') $f['default'] = $fo[$f['key']];
        }
        unset($f);
    }
    $eo = $meta['events_override'] ?? [];
    if (is_array($eo) && !empty($tpl['events'])) {
        foreach ($tpl['events'] as &$ev) {
            $o = $eo[$ev['key']] ?? null;
            if (!is_array($o)) continue;
            if (isset($o['on'])) $ev['on'] = (bool)$o['on'];
            if (isset($o['date']) && $o['date'] !== '') $ev['date'] = $o['date'];
            if (isset($o['time']) && $o['time'] !== '') $ev['time'] = $o['time'];
        }
        unset($ev);
    }
    return $tpl;
}

/** All templates (built-in sample + installed), newest first, keyed by id. */
function inv_templates($only_published = true) {
    $out = [];
    $builtin = inv_builtin_template();
    $out[$builtin['id']] = $builtin;
    foreach (inv_registry() as $meta) {
        $tpl = inv_load($meta['id'] ?? '');
        if (!$tpl) continue;
        $tpl = array_replace($tpl, array_intersect_key($meta, array_flip(['status', 'featured', 'trending', 'new_badge', 'credit_cost', 'sort'])));
        $tpl = inv_apply_overrides($tpl, $meta);
        $out[$tpl['id']] = $tpl;
    }
    // built-in overrides (admin can hide/reprice/edit it too, stored with id key)
    foreach (inv_registry() as $meta) {
        if (($meta['id'] ?? '') === $builtin['id']) {
            $out[$builtin['id']] = array_replace($out[$builtin['id']], array_intersect_key($meta, array_flip(['status', 'featured', 'trending', 'new_badge', 'credit_cost', 'sort'])));
            $out[$builtin['id']] = inv_apply_overrides($out[$builtin['id']], $meta);
        }
    }
    if ($only_published) $out = array_filter($out, function ($t) { return ($t['status'] ?? 'published') === 'published'; });
    uasort($out, function ($a, $b) {
        return (($b['featured'] ?? false) <=> ($a['featured'] ?? false)) ?: (($a['sort'] ?? 999) <=> ($b['sort'] ?? 999));
    });
    return $out;
}

function inv_get($id, $only_published = true) {
    $all = inv_templates($only_published);
    return $all[$id] ?? null;
}

/** Load an installed template's template.json + resolve asset paths. */
function inv_load($id) {
    $id = preg_replace('/[^a-z0-9_\-]/i', '', (string)$id);
    if ($id === '') return null;
    $fs = inv_fs_root() . $id . '/';               // filesystem (absolute)
    $web = 'uploads/invitations/' . $id . '/';     // web path from site root
    $jf = $fs . 'template.json';
    if (!file_exists($jf)) return null;
    $t = json_decode(file_get_contents($jf), true);
    if (!is_array($t)) return null;
    $t['id'] = $id;
    $t['_dir'] = $web;
    foreach (['thumbnail', 'preview', 'music', 'background'] as $ak) {
        if (!empty($t[$ak]) && !preg_match('#^https?://#', $t[$ak])) {
            // keep only assets that actually exist on disk
            $t[$ak] = file_exists($fs . ltrim($t[$ak], '/')) ? $web . ltrim($t[$ak], '/') : '';
        }
    }
    return $t;
}

/** Install a template ZIP (must contain template.json). Returns [ok, msg|id]. */
function inv_install_zip($file) {
    if (!class_exists('ZipArchive')) return [false, 'ZipArchive not available on this server.'];
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) return [false, 'Upload error.'];
    if ($file['size'] > 80 * 1024 * 1024) return [false, 'ZIP exceeds 80MB.'];
    $zip = new ZipArchive();
    if ($zip->open($file['tmp_name']) !== true) return [false, 'Could not open ZIP.'];

    // find template.json (root or single top folder)
    $json_index = -1; $prefix = '';
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $n = $zip->getNameIndex($i);
        if (preg_match('#(^|/)template\.json$#', $n)) { $json_index = $i; $prefix = substr($n, 0, strlen($n) - strlen('template.json')); break; }
    }
    if ($json_index < 0) { $zip->close(); return [false, 'template.json not found in ZIP.']; }
    $t = json_decode($zip->getFromIndex($json_index), true);
    if (!is_array($t) || empty($t['name'])) { $zip->close(); return [false, 'template.json is invalid (needs at least "name" and "scenes").']; }

    $id = preg_replace('/[^a-z0-9_\-]/i', '', $t['id'] ?? '');
    if ($id === '') $id = preg_replace('/[^a-z0-9]+/', '_', strtolower($t['name'])) . '_' . substr(md5(uniqid()), 0, 5);
    $dir = inv_fs_root() . $id . '/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    // extract safely (skip traversal + php files)
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $n = $zip->getNameIndex($i);
        if ($prefix !== '' && strpos($n, $prefix) !== 0) continue;
        $rel = $prefix === '' ? $n : substr($n, strlen($prefix));
        if ($rel === '' || substr($rel, -1) === '/') continue;
        if (strpos($rel, '..') !== false) continue;
        if (preg_match('/\.(php|phtml|phar|htaccess)$/i', $rel)) continue;
        $dest = $dir . $rel;
        $dd = dirname($dest);
        if (!is_dir($dd)) mkdir($dd, 0755, true);
        copy('zip://' . $file['tmp_name'] . '#' . $n, $dest);
    }
    $zip->close();
    // normalise: ensure template.json exists at root of dir
    if (!file_exists($dir . 'template.json')) file_put_contents($dir . 'template.json', json_encode($t, JSON_UNESCAPED_UNICODE));

    // register
    $reg = inv_registry();
    $found = false;
    foreach ($reg as &$m) { if (($m['id'] ?? '') === $id) { $m['updated'] = date('Y-m-d H:i'); if (($m['status'] ?? '') === 'draft') $m['status'] = 'published'; $found = true; } }
    unset($m);
    // New templates go live immediately so they show up right after upload;
    // admin can hide/unpublish from the list if needed.
    if (!$found) $reg[] = ['id' => $id, 'status' => 'published', 'featured' => false, 'credit_cost' => (int)($t['credit_cost'] ?? 1), 'created' => date('Y-m-d H:i')];
    inv_save_registry($reg);
    return [true, $id];
}

function inv_update_meta($id, $patch) {
    $reg = inv_registry();
    $found = false;
    foreach ($reg as &$m) { if (($m['id'] ?? '') === $id) { $m = array_replace($m, $patch); $found = true; } }
    unset($m);
    if (!$found) $reg[] = array_replace(['id' => $id], $patch);
    inv_save_registry($reg);
}

function inv_delete($id) {
    $reg = array_values(array_filter(inv_registry(), function ($m) use ($id) { return ($m['id'] ?? '') !== $id; }));
    inv_save_registry($reg);
    $dir = inv_fs_root() . preg_replace('/[^a-z0-9_\-]/i', '', $id) . '/';
    if (is_dir($dir)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) { $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); }
        @rmdir($dir);
    }
}

/**
 * Built-in sample: Royal Golden Wedding Invite (9:16, ~36s).
 * Pure code — no assets needed, works out of the box and doubles as the
 * reference for the template.json format ZIP authors should follow.
 */
function inv_builtin_template() {
    return [
        'id' => 'royal_gold_wedding',
        'name' => 'Royal Golden Wedding Invite',
        'category' => 'Wedding',
        'description' => 'Golden particles, floral bloom, smooth text reveals, cinematic transitions & a confetti ending.',
        'emoji' => '👑',
        'aspect' => '9:16',
        'duration' => 36,
        'credit_cost' => 1,
        'status' => 'published',
        'featured' => true,
        'new_badge' => true,
        'colors' => ['bg1' => '#160f04', 'bg2' => '#3a2a0a', 'accent' => '#eab308', 'accent2' => '#fde68a', 'text' => '#fdf6e3'],
        'font' => 'Cormorant Garamond',
        'particles' => 'golden',
        'fields' => [
            ['key' => 'title',   'label' => 'Event Title',   'default' => 'Wedding Invitation'],
            ['key' => 'bride',   'label' => 'Bride Name',    'default' => 'Shravni'],
            ['key' => 'groom',   'label' => 'Groom Name',    'default' => 'Sny'],
            ['key' => 'family',  'label' => 'Family / Host', 'default' => 'The Sharma Family'],
            ['key' => 'date',    'label' => 'Event Date',    'default' => '14 February 2027'],
            ['key' => 'time',    'label' => 'Event Time',    'default' => '7:00 PM Onwards'],
            ['key' => 'venue',   'label' => 'Venue',         'default' => 'The Grand Palace, Jaipur'],
            ['key' => 'message', 'label' => 'Custom Message','default' => 'With the blessings of our families, we invite you to celebrate our new beginning.'],
            ['key' => 'rsvp',    'label' => 'RSVP',          'default' => 'RSVP: +91 98765 43210'],
        ],
        'photos' => ['label' => 'Couple Photos (up to 3)', 'max' => 3],
        'scenes' => [
            ['d' => 6, 'els' => [
                ['type' => 'text', 'text' => '[family]', 'size' => 40, 'y' => 0.40, 'anim' => 'fade', 'delay' => 0.4, 'color' => 'accent2', 'spacing' => 6, 'caps' => true],
                ['type' => 'text', 'text' => 'cordially invites you to the', 'size' => 34, 'y' => 0.48, 'anim' => 'fade', 'delay' => 1.4, 'italic' => true],
                ['type' => 'text', 'text' => '[title]', 'size' => 76, 'y' => 0.57, 'anim' => 'zoom', 'delay' => 2.2, 'color' => 'accent'],
                ['type' => 'flourish', 'y' => 0.66, 'delay' => 3.0],
            ]],
            ['d' => 7, 'els' => [
                ['type' => 'text', 'text' => '[bride]', 'size' => 92, 'y' => 0.36, 'anim' => 'up', 'delay' => 0.4, 'color' => 'accent'],
                ['type' => 'ring', 'y' => 0.48, 'delay' => 1.6],
                ['type' => 'text', 'text' => 'weds', 'size' => 40, 'y' => 0.485, 'anim' => 'fade', 'delay' => 1.6, 'italic' => true],
                ['type' => 'text', 'text' => '[groom]', 'size' => 92, 'y' => 0.62, 'anim' => 'up', 'delay' => 2.6, 'color' => 'accent'],
            ]],
            ['d' => 7, 'photo' => 0, 'els' => [
                ['type' => 'photo', 'index' => 0, 'y' => 0.42, 'h' => 0.5, 'anim' => 'zoom', 'delay' => 0.3],
                ['type' => 'text', 'text' => 'Two hearts, one story ❤️', 'size' => 40, 'y' => 0.78, 'anim' => 'fade', 'delay' => 1.5, 'italic' => true],
            ]],
            ['d' => 7, 'els' => [
                ['type' => 'text', 'text' => 'Save the Date', 'size' => 44, 'y' => 0.30, 'anim' => 'fade', 'delay' => 0.3, 'caps' => true, 'spacing' => 8, 'color' => 'accent2'],
                ['type' => 'text', 'text' => '[date]', 'size' => 64, 'y' => 0.42, 'anim' => 'up', 'delay' => 1.0, 'color' => 'accent'],
                ['type' => 'text', 'text' => '[time]', 'size' => 42, 'y' => 0.51, 'anim' => 'up', 'delay' => 1.8],
                ['type' => 'flourish', 'y' => 0.58, 'delay' => 2.2],
                ['type' => 'text', 'text' => '[venue]', 'size' => 46, 'y' => 0.66, 'anim' => 'fade', 'delay' => 2.8],
            ]],
            ['d' => 5, 'els' => [
                ['type' => 'text', 'text' => '[message]', 'size' => 40, 'y' => 0.46, 'anim' => 'typein', 'delay' => 0.4, 'italic' => true, 'wrap' => 0.8],
            ]],
            ['d' => 4, 'confetti' => true, 'els' => [
                ['type' => 'text', 'text' => 'See you there! 🎉', 'size' => 58, 'y' => 0.44, 'anim' => 'zoom', 'delay' => 0.3, 'color' => 'accent'],
                ['type' => 'text', 'text' => '[rsvp]', 'size' => 36, 'y' => 0.56, 'anim' => 'fade', 'delay' => 1.2],
            ]],
        ],
    ];
}
