<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once '../db.php';
$db = getDB();

$fichier = 'C:/xampp/htdocs/logycab/exports-access/produits.csv';
if (!file_exists($fichier)) { die("❌ Fichier introuvable !"); }

$handle = fopen($fichier, 'r');
$ligneEntete = fgets($handle);
$ligneEntete = mb_convert_encoding($ligneEntete, 'UTF-8', 'Windows-1252');
$entetes = str_getcsv($ligneEntete, ';', '"');
$entetes = array_map(fn($e) => trim($e, '"'), $entetes);

$nb = 0;
$db->exec("DELETE FROM PRODUITS");

echo "<pre>Importation produits en cours...\n\n";

while (($ligneRaw = fgets($handle)) !== false) {
    $ligneRaw = mb_convert_encoding($ligneRaw, 'UTF-8', 'Windows-1252');
    $ligne = str_getcsv($ligneRaw, ';', '"');
    $ligne = array_map(fn($v) => trim($v ?? '', '"'), $ligne);
    if (count($ligne) != count($entetes)) continue;
    $row = array_combine($entetes, $ligne);

    $num     = (int)($row['NuméroPRODUIT'] ?? 0);
    $produit = $row['PRODUIT'] ?? '';
    $prop    = $row['PROPRIETE'] ?? '';

    if ($num == 0 || empty($produit)) continue;

    try {
        $db->exec("SET IDENTITY_INSERT PRODUITS ON");
        $stmt = $db->prepare("INSERT INTO PRODUITS (NuméroPRODUIT, PRODUIT, PROPRIETE) VALUES (?, ?, ?)");
        $stmt->execute([$num, $produit, $prop]);
        $db->exec("SET IDENTITY_INSERT PRODUITS OFF");
        $nb++;
    } catch (Exception $e) {
        $db->exec("SET IDENTITY_INSERT PRODUITS OFF");
    }
}

fclose($handle);
echo "✅ Import terminé !\n";
echo "✅ Produits importés : $nb\n";
echo "</pre>";