<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('max_execution_time', 600);

require_once '../db.php';
$db = getDB();

$fichier = 'C:/xampp/htdocs/logycab/exports-access/medicaments.csv';

if (!file_exists($fichier)) {
    die("❌ Fichier introuvable !");
}

$handle = fopen($fichier, 'r');
$ligneEntete = fgets($handle);
$ligneEntete = mb_convert_encoding($ligneEntete, 'UTF-8', 'Windows-1252');
$entetes = str_getcsv($ligneEntete, ';', '"');
$entetes = array_map(fn($e) => trim($e, '"'), $entetes);

$nb = 0;
$erreurs = 0;

$db->exec("DELETE FROM PROD");

echo "<pre>Importation medicaments en cours...\n\n";

while (($ligneRaw = fgets($handle)) !== false) {
    $ligneRaw = mb_convert_encoding($ligneRaw, 'UTF-8', 'Windows-1252');
    $ligne = str_getcsv($ligneRaw, ';', '"');
    $ligne = array_map(fn($v) => trim($v ?? '', '"'), $ligne);
    if (count($ligne) != count($entetes)) continue;
    $row = array_combine($entetes, $ligne);

    $n_produit = (int)($row['N_produit'] ?? 0);
    $n_ord     = (int)($row['N_ord'] ?? 0);
    $produit   = (int)($row['produit'] ?? 0);
    $posologie = $row['posologie'] ?? '';
    $duree     = $row['DUREE'] ?? '';
    $ordre     = !empty($row['Ordre']) ? (int)$row['Ordre'] : null;

    if ($n_produit == 0 || $n_ord == 0) continue;

    // Vérifier ordonnance existe
    $check = $db->prepare("SELECT COUNT(*) FROM ORD WHERE n_ordon = ?");
    $check->execute([$n_ord]);
    if ($check->fetchColumn() == 0) continue;

    try {
        $db->exec("SET IDENTITY_INSERT PROD ON");
        $stmt = $db->prepare("
            INSERT INTO PROD (N_produit, N_ord, produit, posologie, DUREE, Ordre)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$n_produit, $n_ord, $produit, $posologie, $duree, $ordre]);
        $db->exec("SET IDENTITY_INSERT PROD OFF");
        $nb++;
        if ($nb % 5000 == 0) echo "✅ $nb médicaments importés...\n";

    } catch (Exception $e) {
        $db->exec("SET IDENTITY_INSERT PROD OFF");
        $erreurs++;
        if ($erreurs <= 3) echo "⚠️ Erreur : " . $e->getMessage() . "\n";
    }
}

fclose($handle);
echo "\n✅ Import terminé !\n";
echo "✅ Médicaments importés : $nb\n";
echo "⚠️ Erreurs : $erreurs\n";
echo "</pre>";