<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/backend/db.php';

// Si déjà connecté, on ne repasse pas par ici
if (!empty($_SESSION['role'])) {
    header('Location: index.php');
    exit;
}

$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login       = trim($_POST['login'] ?? '');
    $motPasse    = $_POST['mot_passe'] ?? '';
    $seSouvenir  = !empty($_POST['se_souvenir']);

    if ($login === '' || $motPasse === '') {
        $erreur = 'Veuillez remplir les deux champs.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM T_Utilisateurs WHERE login = ? AND actif = 1");
        $stmt->execute([$login]);
        $utilisateur = $stmt->fetch();

        if ($utilisateur && password_verify($motPasse, $utilisateur['mot_passe'])) {
            $_SESSION['user'] = $utilisateur['login'];
            $_SESSION['role'] = $utilisateur['role'];
            $_SESSION['nom_affiche'] = $utilisateur['nom_affiche'];

            if ($seSouvenir) {
                // --- Créer un jeton "se souvenir de moi", valable 90 jours ---
                $selector  = bin2hex(random_bytes(12));   // sert à retrouver la ligne en base
                $validator = bin2hex(random_bytes(32));   // jamais stocké en clair
                $validatorHash = hash('sha256', $validator);
                $expireAt = date('Ymd H:i:s', time() + 90 * 24 * 3600);

                $stmtIns = $db->prepare("INSERT INTO T_Remember_Tokens (login, selector, validator_hash, expire_at) VALUES (?, ?, ?, ?)");
                $stmtIns->execute([$utilisateur['login'], $selector, $validatorHash, $expireAt]);

                setcookie(
                    'remember_token',
                    $selector . ':' . $validator,
                    [
                        'expires'  => time() + 90 * 24 * 3600,
                        'path'     => '/',
                        'httponly' => true,
                        'samesite' => 'Lax',
                    ]
                );
            }

            header('Location: index.php');
            exit;
        } else {
            $erreur = 'Login ou mot de passe incorrect.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Logycab — Connexion</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: Arial, sans-serif;
    background: #1a4a7a;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
}
.boite {
    background: white;
    border-radius: 8px;
    padding: 32px 36px;
    width: 320px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.25);
}
.logo { text-align: center; margin-bottom: 20px; }
.logo .coeur { font-size: 32px; color: #c0392b; }
.logo .titre { font-size: 20px; font-weight: bold; color: #1a4a7a; letter-spacing: 1px; }
label { display: block; font-size: 12px; color: #444; margin-bottom: 4px; margin-top: 12px; }
input[type=text], input[type=password] {
    width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px;
}
.ligne-souvenir { display: flex; align-items: center; gap: 6px; margin-top: 14px; }
.ligne-souvenir input[type=checkbox] { width: auto; }
.ligne-souvenir label { margin: 0; font-size: 13px; color: #333; }
button {
    width: 100%; margin-top: 20px; padding: 10px; border: none; border-radius: 4px;
    background: #2e6da4; color: white; font-size: 14px; font-weight: bold; cursor: pointer;
}
button:hover { background: #1a4a7a; }
.erreur {
    background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;
    padding: 8px; border-radius: 4px; font-size: 12px; margin-top: 12px; text-align: center;
}
</style>
</head>
<body>
<div class="boite">
    <div class="logo">
        <div class="coeur">❤</div>
        <div class="titre">LOGYCAB</div>
    </div>
    <form method="POST">
        <label for="login">Identifiant</label>
        <input type="text" id="login" name="login" autofocus required>
        <label for="mot_passe">Mot de passe</label>
        <input type="password" id="mot_passe" name="mot_passe" required>
        <div class="ligne-souvenir">
            <input type="checkbox" id="se_souvenir" name="se_souvenir" value="1">
            <label for="se_souvenir">Se souvenir de moi (90 jours)</label>
        </div>
        <button type="submit">Se connecter</button>
    </form>
    <?php if ($erreur): ?>
        <div class="erreur">❌ <?= htmlspecialchars($erreur) ?></div>
    <?php endif; ?>
</div>
</body>
</html>
