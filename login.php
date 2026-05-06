<?php
session_start();
require_once 'backend/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';

    // Utilisateurs définis (à sécuriser plus tard)
    $users = [
        'medecin'   => ['pass' => 'hlimi2026', 'role' => 'medecin',   'nom' => 'Dr Hassan Hlimi'],
        'secretaire'=> ['pass' => 'cab2026',   'role' => 'secretaire','nom' => 'Secrétaire'],
    ];

    if (isset($users[$user]) && $users[$user]['pass'] === $pass) {
        $_SESSION['user']  = $user;
        $_SESSION['role']  = $users[$user]['role'];
        $_SESSION['nom']   = $users[$user]['nom'];
        header('Location: agenda.php');
        exit;
    } else {
        $erreur = "Identifiant ou mot de passe incorrect !";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Logycab — Connexion</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Arial, sans-serif; background: #1a4a7a; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
.login-box { background: white; border-radius: 12px; padding: 40px; width: 380px; box-shadow: 0 8px 32px rgba(0,0,0,0.3); }
.logo { text-align: center; margin-bottom: 24px; }
.logo h1 { color: #1a4a7a; font-size: 32px; font-weight: bold; }
.logo p { color: #666; font-size: 13px; margin-top: 4px; }
label { display: block; font-size: 12px; font-weight: bold; color: #555; text-transform: uppercase; margin-bottom: 4px; }
input { width: 100%; padding: 10px 14px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; margin-bottom: 16px; }
input:focus { border-color: #2e6da4; outline: none; }
.btn-login { width: 100%; background: #1a4a7a; color: white; border: none; padding: 12px; border-radius: 6px; font-size: 15px; font-weight: bold; cursor: pointer; }
.btn-login:hover { background: #2e6da4; }
.erreur { background: #ffe0e0; color: #c0392b; padding: 10px; border-radius: 6px; font-size: 13px; margin-bottom: 16px; text-align: center; }
.cabinet { text-align: center; color: #999; font-size: 12px; margin-top: 20px; }
</style>
</head>
<body>
<div class="login-box">
    <div class="logo">
        <h1>🏥 Logycab</h1>
        <p>Cabinet Dr Hlimi — Cardiologue Tétouan</p>
    </div>

    <?php if (isset($erreur)): ?>
    <div class="erreur">❌ <?= htmlspecialchars($erreur) ?></div>
    <?php endif; ?>

    <form method="POST">
        <label>Identifiant</label>
        <input type="text" name="username" placeholder="medecin ou secretaire" autofocus>
        <label>Mot de passe</label>
        <input type="password" name="password" placeholder="••••••••">
        <button type="submit" class="btn-login">🔐 Se connecter</button>
    </form>

    <div class="cabinet">Cabinet Dr Hassan Hlimi — Tétouan, Maroc</div>
</div>
</body>
</html>