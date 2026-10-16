<?php
// admin_login.php - Secret Admin Login Form

session_start();
require_once __DIR__ . '/db.php';
// Optional protection layer: IP whitelist or secret key
require_once __DIR__ . '/admin_config.php';

// If IP not allowed and no secret key, deny early
$remote = $_SERVER['REMOTE_ADDR'] ?? '';
// Enforce basic auth (unless IP allowed or secret key present)
admin_require_basic_auth();

// Redirect if already logged in
if (isset($_SESSION['admin_logged']) && $_SESSION['admin_logged'] === true) {
    header('Location: admin.php');
    exit;
}

// If not logged in, check for a persistent "remember me" cookie and auto-login
if (!isset($_SESSION['admin_logged'])) {
    $remember_token = $_COOKIE['cc_admin_remember'] ?? '';
    if (!empty($remember_token)) {
        try {
            $token_hash = hash('sha256', $remember_token);
            $stmt = $pdo->prepare("SELECT * FROM admins WHERE remember_token = ? AND remember_expires > NOW() LIMIT 1");
            $stmt->execute([$token_hash]);
            $admin = $stmt->fetch();
            if ($admin) {
                $_SESSION['admin_logged'] = true;
                $_SESSION['admin_username'] = $admin['username'];
                header('Location: admin.php');
                exit;
            }
        } catch (PDOException $e) {
            // ignore and continue to login form
        }
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
        $remember = isset($_POST['remember']);

    if (empty($username) || empty($password)) {
        $error = 'Veuillez saisir votre identifiant et votre mot de passe.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password'])) {
                $_SESSION['admin_logged'] = true;
                $_SESSION['admin_username'] = $admin['username'];

                // If user asked to be remembered, issue a long-lived secure token
                if ($remember) {
                    try {
                        $token = bin2hex(random_bytes(32));
                        $token_hash = hash('sha256', $token);
                        $expires = date('Y-m-d H:i:s', time() + 60*60*24*30); // 30 days
                        $upd = $pdo->prepare("UPDATE admins SET remember_token = ?, remember_expires = ? WHERE id = ?");
                        $upd->execute([$token_hash, $expires, $admin['id']]);
                        // Set cookie (httponly)
                        setcookie('cc_admin_remember', $token, time() + 60*60*24*30, '/', '', false, true);
                    } catch (Exception $e) {
                        // ignore remember-me failures
                    }
                }
                header('Location: admin.php');
                exit;
            } else {
                $error = 'Identifiant ou mot de passe incorrect.';
            }
        } catch (PDOException $e) {
            $error = 'Erreur serveur : ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration | Carthage Chic</title>
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body class="login-body">

    <div class="login-card">
        <img src="assets/logo.png?v=<?= file_exists(__DIR__ . '/assets/logo.png') ? filemtime(__DIR__ . '/assets/logo.png') : (file_exists(__DIR__ . '/assets/logo.jpg') ? filemtime(__DIR__ . '/assets/logo.jpg') : time()) ?>" alt="Carthage Chic Logo" class="login-logo" onerror="this.style.display='none'">
        <h1 class="login-title">Administration Carthage Chic</h1>
        
        <?php if (!empty($error)): ?>
            <div class="login-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form action="admin_login.php" method="POST">
            <div class="login-form-group">
                <label for="username" class="login-label">Identifiant</label>
                <input type="text" id="username" name="username" class="login-input" autocomplete="username" required value="<?= htmlspecialchars($username ?? '') ?>">
            </div>
            
            <div class="login-form-group">
                <label for="password" class="login-label">Mot de Passe</label>
                <input type="password" id="password" name="password" class="login-input" autocomplete="current-password" required>
            </div>

            <div style="margin-top:10px;">
                <label style="font-size:13px"><input type="checkbox" name="remember" id="remember"> Se souvenir de moi (30 jours)</label>
            </div>

            <button type="submit" class="login-btn">Se connecter</button>
        </form>
    </div>

</body>
</html>
