<?php
/**
 * backend/auth.php
 * Vérifie que l'utilisateur est connecté, et que son rôle a le droit
 * d'accéder à la page demandée (liste noire en base : T_Acces_Interdits).
 *
 * Si la session a expiré mais qu'un cookie "se souvenir de moi" valide
 * existe, reconnecte automatiquement l'utilisateur sans repasser par
 * l'écran de connexion.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

// Pages accessibles sans être connecté
$pagesPubliques = ['login.php'];

$scriptActuel = basename($_SERVER['SCRIPT_NAME']);

if (!in_array($scriptActuel, $pagesPubliques)) {

    // 1) Pas de session active -> tenter une reconnexion via le cookie "se souvenir de moi"
    if (empty($_SESSION['role'])) {

        $reconnecte = false;

        if (!empty($_COOKIE['remember_token']) && strpos($_COOKIE['remember_token'], ':') !== false) {
            [$selector, $validator] = explode(':', $_COOKIE['remember_token'], 2);

            try {
                $db = getDB();
                $stmt = $db->prepare("SELECT * FROM T_Remember_Tokens WHERE selector = ? AND expire_at > GETDATE()");
                $stmt->execute([$selector]);
                $jeton = $stmt->fetch();

                if ($jeton && hash_equals($jeton['validator_hash'], hash('sha256', $validator))) {
                    // Jeton valide -> récupérer l'utilisateur
                    $stmtU = $db->prepare("SELECT * FROM T_Utilisateurs WHERE login = ? AND actif = 1");
                    $stmtU->execute([$jeton['login']]);
                    $utilisateur = $stmtU->fetch();

                    if ($utilisateur) {
                        $_SESSION['user'] = $utilisateur['login'];
                        $_SESSION['role'] = $utilisateur['role'];
                        $_SESSION['nom_affiche'] = $utilisateur['nom_affiche'];

                        // Rotation du jeton : on supprime l'ancien et on en émet un nouveau,
                        // pour limiter les risques si le cookie était un jour intercepté.
                        $db->prepare("DELETE FROM T_Remember_Tokens WHERE selector = ?")->execute([$selector]);

                        $nouveauSelector  = bin2hex(random_bytes(12));
                        $nouveauValidator = bin2hex(random_bytes(32));
                        $expireAt = date('Ymd H:i:s', time() + 90 * 24 * 3600);

                        $db->prepare("INSERT INTO T_Remember_Tokens (login, selector, validator_hash, expire_at) VALUES (?, ?, ?, ?)")
                           ->execute([$utilisateur['login'], $nouveauSelector, hash('sha256', $nouveauValidator), $expireAt]);

                        setcookie(
                            'remember_token',
                            $nouveauSelector . ':' . $nouveauValidator,
                            [
                                'expires'  => time() + 90 * 24 * 3600,
                                'path'     => '/',
                                'httponly' => true,
                                'samesite' => 'Lax',
                            ]
                        );

                        $reconnecte = true;
                    }
                }
            } catch (Exception $e) {
                // En cas de souci base de données sur la reconnexion automatique,
                // on abandonne simplement et on renvoie vers l'écran de connexion.
            }
        }

        if (!$reconnecte) {
            header('Location: login.php');
            exit;
        }
    }

    // 2) Connecté (session normale ou reconnexion automatique) -> vérifier si ce fichier est interdit à son rôle
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
