<?php
// admin_change_credentials.php
// Small local-only utility to change the admin username and password.

// Security: allow only from localhost or CLI
if (php_sapi_name() !== 'cli') {
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($remote !== '127.0.0.1' && $remote !== '::1') {
        http_response_code(403);
        echo "Forbidden: tool accessible only from the server (localhost).";
        exit;
    }
}

require_once __DIR__ . '/db.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_user = trim($_POST['username'] ?? '');
    $new_pass = trim($_POST['password'] ?? '');
    $confirm = trim($_POST['password_confirm'] ?? '');

    if ($new_user === '' || $new_pass === '') {
        $error = 'Veuillez renseigner le nom d\'utilisateur et le mot de passe.';
    } elseif ($new_pass !== $confirm) {
        $error = 'Les mots de passe ne correspondent pas.';
    } else {
        try {
            $hash = password_hash($new_pass, PASSWORD_BCRYPT);

            // If any admin exists, update all rows to the new credentials.
            $stmt = $pdo->query("SELECT COUNT(*) FROM admins");
            $count = (int)$stmt->fetchColumn();

            if ($count > 0) {
                $upd = $pdo->prepare("UPDATE admins SET username = ?, password = ?");
                $upd->execute([$new_user, $hash]);
            } else {
                $ins = $pdo->prepare("INSERT INTO admins (username, password) VALUES (?, ?)");
                $ins->execute([$new_user, $hash]);
            }

            // Clear any existing remember-me tokens so old tokens can't auto-login
            try {
                $pdo->exec("UPDATE admins SET remember_token = NULL, remember_expires = NULL");
            } catch (Exception $e) {
                // ignore
            }

            $message = 'Identifiants mis à jour avec succès.';
        } catch (PDOException $e) {
            $error = 'Erreur base de données : ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Changer identifiants admin - Carthage Chic</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; background:#f6f7fb; color:#222; }
        .wrap { max-width:480px; margin:48px auto; background:#fff; padding:20px; border-radius:8px; box-shadow:0 6px 24px rgba(20,24,40,0.08);} 
        h1{font-size:18px;margin:0 0 12px}
        label{display:block;margin-top:12px;font-size:13px}
        input{width:100%;padding:10px;margin-top:6px;border:1px solid #ddd;border-radius:6px}
        button{margin-top:16px;padding:10px 14px;border:0;background:#1a73e8;color:#fff;border-radius:6px;cursor:pointer}
        .msg{padding:10px;border-radius:6px;margin-top:12px}
        .msg.success{background:#e6ffed;color:#085c2a}
        .msg.error{background:#ffecec;color:#7a1b12}
        .note{margin-top:10px;font-size:13px;color:#666}
    </style>
</head>
<body>
    <div class="wrap">
        <h1>Changer le nom d'utilisateur et le mot de passe admin</h1>

        <?php if ($message): ?>
            <div class="msg success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="msg error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="admin_change_credentials.php">
            <label for="username">Nouvel identifiant</label>
            <input type="text" id="username" name="username" required placeholder="ex: admin">

            <label for="password">Nouveau mot de passe</label>
            <input type="password" id="password" name="password" required placeholder="Mot de passe sécurisé">

            <label for="password_confirm">Confirmer le mot de passe</label>
            <input type="password" id="password_confirm" name="password_confirm" required placeholder="Repeter le mot de passe">

            <button type="submit">Mettre à jour</button>
        </form>

        <div class="note">Ce script est accessible uniquement depuis le serveur (localhost). Après modification, retournez à la page de connexion <a href="admin_login.php">ici</a>.</div>
    </div>
</body>
</html>
