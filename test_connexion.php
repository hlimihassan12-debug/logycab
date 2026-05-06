<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'backend/db.php';

try {
    $db = getDB();
    $stmt = $db->query("SELECT COUNT(*) AS nb FROM [t_acte_simplifiée]");
    $row = $stmt->fetch();
    echo "✅ Connexion réussie ! Actes en base : " . $row['nb'];
} catch (Exception $e) {
    echo "❌ Erreur : " . $e->getMessage();
}