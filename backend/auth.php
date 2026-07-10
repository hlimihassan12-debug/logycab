<?php
/**
 * backend/auth.php
 * Vérifie que l'utilisateur est connecté, et que son rôle a le droit
 * d'accéder à la page demandée (liste noire en base : T_Acces_Interdits).
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

// Pages accessibles sans être connecté
$pagesPubliques = ['login.php'];

$scriptActuel = basename($_SERVER['SCRIPT_NAME']);

if (!in_array($scriptActuel, $pagesPubliques)) {

    // 1) Pas connecté -> renvoi vers l'écran de connexion
    if (empty($_SESSION['role'])) {
        header('Location: login.php');
        exit;
    }

    // 2) Connecté -> vérifier si ce fichier est interdit à son rôle
    $role = $_SESSION['role'];
    if ($role !== 'medecin') {
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT COUNT(*) FROM T_Acces_Interdits WHERE role = ? AND fichier = ?");
            $stmt->execute([$role, $scriptActuel]);
            if ((int)$stmt->fetchColumn() > 0) {
                header('Location: index.php?erreur=acces_refuse');
                exit;
            }
        } catch (Exception $e) {
            // En cas de souci base de données sur la vérification d'accès,
            // on bloque par prudence plutôt que de laisser passer.
            die("Erreur de vérification d'accès. Contactez l'administrateur.");
        }
    }
}
