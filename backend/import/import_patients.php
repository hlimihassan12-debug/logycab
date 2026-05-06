<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('max_execution_time', 300);

require_once '../db.php';
$db = getDB();

$fichier = 'C:/xampp/htdocs/logycab/exports-access/patients.csv';

if (!file_exists($fichier)) {
    die("❌ Fichier introuvable : " . $fichier);
}

$handle = fopen($fichier, 'r');

// Lire les entêtes
$ligneEntete = fgets($handle);
$ligneEntete = mb_convert_encoding($ligneEntete, 'UTF-8', 'Windows-1252');
$entetes = str_getcsv($ligneEntete, ';', '"');
$entetes = array_map(fn($e) => trim($e, '"'), $entetes);

// Corriger N°PAT
$entetes = array_map(fn($e) => str_replace(['N°PAT', 'N?PAT', 'N°PAT'], 'N°PAT', $e), $entetes);

$nb = 0;
$erreurs = 0;

// Vider les tables
$db->exec("DELETE FROM PROD");
$db->exec("DELETE FROM ORD");
$db->exec("DELETE FROM ID");

echo "<pre>";
echo "Entêtes détectées : " . implode(' | ', $entetes) . "\n\n";
echo "Importation en cours...\n\n";

while (($ligneRaw = fgets($handle)) !== false) {
    $ligneRaw = mb_convert_encoding($ligneRaw, 'UTF-8', 'Windows-1252');
    $ligne = str_getcsv($ligneRaw, ';', '"');
    $ligne = array_map(fn($v) => trim($v, '"'), $ligne);
    if (count($ligne) != count($entetes)) continue;
    $row = array_combine($entetes, $ligne);

    // Trouver N°PAT
    $npat = 0;
    foreach ($row as $k => $v) {
        if (strpos($k, 'PAT') !== false) {
            $npat = (int)$v;
            break;
        }
    }

    $nom      = $row['NOMPRENOM'] ?? '';
    $ddn      = !empty($row['DDN']) ? date('Y-m-d', strtotime($row['DDN'])) : null;
    $age      = !empty($row['AGE']) ? (int)$row['AGE'] : null;
    $sexe     = $row['SXE'] ?? '';
    $telD     = $row['TEL D'] ?? '';
    $telB     = $row['TEL B'] ?? '';
    $mutuelle = $row['MUTUELLE'] ?? '';

    if ($npat == 0 || empty($nom)) continue;

    try {
        $db->exec("SET IDENTITY_INSERT ID ON");
        $stmt = $db->prepare("
            INSERT INTO ID ([N°PAT], NOMPRENOM, DDN, AGE, SXE, [TEL D], [TEL B], MUTUELLE)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$npat, $nom, $ddn, $age, $sexe, $telD, $telB, $mutuelle]);
        $db->exec("SET IDENTITY_INSERT ID OFF");
        $nb++;
    } catch (Exception $e) {
        $db->exec("SET IDENTITY_INSERT ID OFF");
        $erreurs++;
        if ($erreurs <= 3) echo "⚠️ Erreur N°$npat $nom : " . $e->getMessage() . "\n";
    }
}

fclose($handle);
echo "✅ Import terminé !\n";
echo "✅ Patients importés : $nb\n";
echo "⚠️ Erreurs : $erreurs\n";
echo "</pre>";