<?php
// SoulSync admin no longer has its own login — it uses the soulpages admin login.
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$role = $_SESSION['user_role'] ?? '';
if (in_array($role, ['admin', 'manager'], true)) {
    header('Location: index.php');              // already an admin → SoulSync dashboard
} else {
    header('Location: ../../admin/login.php');   // log in on the soulpages admin
}
exit;
