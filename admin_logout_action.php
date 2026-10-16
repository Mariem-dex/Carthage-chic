<?php
// admin_logout_action.php - Action to log out admin and destroy session
session_start();
$_SESSION = array();
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();
// Also clear remember-me cookie and token in database (if present)
$remember_token = $_COOKIE['cc_admin_remember'] ?? '';
if (!empty($remember_token)) {
    try {
        require_once __DIR__ . '/db.php';
        $token_hash = hash('sha256', $remember_token);
        $upd = $pdo->prepare("UPDATE admins SET remember_token = NULL, remember_expires = NULL WHERE remember_token = ?");
        $upd->execute([$token_hash]);
    } catch (Exception $e) {
        // ignore
    }
    setcookie('cc_admin_remember', '', time() - 3600, '/', '', false, true);
}

header('Location: admin_login.php');
exit;
?>
