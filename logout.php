<?php
session_start();
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
// Clear Remember Me cookie
if (isset($_COOKIE['loopr_remember'])) {
    setcookie('loopr_remember', '', time() - 3600, '/');
}
session_destroy();
header("Location: index.php");
exit;
