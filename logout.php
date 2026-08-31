<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/backend/db.php';

// Supprimer le jeton "se souvenir de moi" (base + cookie), sinon la
// déconnexion ne servirait à rien : l'utilisateur serait reconnecté
// automatiquement au rechargement suivant.
if (!empty($_COOKIE['remember_token']) && strpos($_COOKIE['remember_token'], ':') !== false) {
    [$selector, ] = explode(':', $_COOKIE['remember_token'], 2);
    try {
        $db = getDB();
        $db->prepare("DELETE FROM T_Remember_Tokens WHERE selector = ?")->execute([$selector]);
    } catch (Exception $e) {
        // Pas grave si la suppression échoue : le jeton expirera de toute façon
        // au bout de 90 jours, et le cookie est supprimé ci-dessous dans tous les cas.
    }
}

setcookie('remember_token', '', [
    'expires'  => time() - 3600,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
]);

$_SESSION = [];
session_destroy();
header('Location: login.php');
exit;
