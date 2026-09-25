<?php
// SoulSync Temporary Admin Creator
require_once 'includes/functions.php';

try {
    $done = [];

    // Super admins.
    $admins = [
        'ssrnov@gmail.com' => 'Qwerty@7303',
    ];
    foreach ($admins as $email => $password) {
        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $userId = $stmt->fetchColumn();
        if ($userId) {
            $pdo->prepare("UPDATE users SET password = ?, role = 'admin' WHERE id = ?")->execute([$hashed, $userId]);
            $done[] = "Updated '$email' → Admin (new password set)";
        } else {
            $pdo->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, 'admin', 'active')")
                ->execute(['Admin', $email, $hashed]);
            $done[] = "Created new admin '$email'";
        }
    }

    // Demote back to a normal user (was mistakenly made admin).
    foreach (['sgamertech7303@gmail.com'] as $email) {
        $st = $pdo->prepare("UPDATE users SET role = 'user' WHERE email = ?");
        $st->execute([$email]);
        if ($st->rowCount() > 0) $done[] = "'$email' → set back to normal user";
    }

    $msg = implode('<br>', $done);

    // Self-destruct for security
    @unlink(__FILE__);
    
    echo "<div style='font-family: sans-serif; padding: 20px; max-width: 500px; margin: 50px auto; border: 1px solid #d1fae5; background: #ecfdf5; border-radius: 8px; color: #065f46;'>
        <h3>Success</h3>
        <p>$msg</p>
        <p style='font-size: 12px; color: #047857;'>Security Action: This script (<code>update-admin.php</code>) has automatically deleted itself from the server.</p>
        <a href='admin-login.php' style='display: inline-block; margin-top: 15px; padding: 8px 16px; background: #059669; color: white; text-decoration: none; border-radius: 6px; font-weight: bold;'>Go to Admin Login &rarr;</a>
    </div>";
} catch (Exception $e) {
    echo "<div style='font-family: sans-serif; padding: 20px; max-width: 500px; margin: 50px auto; border: 1px solid #fee2e2; background: #fef2f2; border-radius: 8px; color: #991b1b;'>
        <h3>Error</h3>
        <p>" . htmlspecialchars($e->getMessage()) . "</p>
    </div>";
}
