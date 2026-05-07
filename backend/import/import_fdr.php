<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once '../db.php';
$db = getDB();

$fichier = 'C:/xampp/htdocs/logycab/exports-access/fdr.csv';
if (!file_exists($fichier)) { die("❌ Fichier introuvable !"); }

$handle = fopen($fichier, 'r');
$ligneEntete = fgets($handle);
$ligneEntete = mb_convert_encoding($ligneEntete, 'UTF-8', 'Windows-1252');
$entetes = str_getcsv($ligneEntete, ';', '"');
$entetes = array_map(fn($e) => trim($e, '"'), $entetes);

$db->exec("DELETE FROM FDR");
$nb = 0;

echo "<pre>Importation FDR...\n\n";

while (($ligneRaw = fgets($handle)) !== false) {
    $ligneRaw = mb_convert_encoding($ligneRaw, 'UTF-8', 'Windows-1252');
    $ligne = str_getcsv($ligneRaw, ';', '"');
    $ligne = array_map(fn($v) => trim($v ?? '', '"'), $ligne);
    if (count($ligne) != count($entetes)) continue;
    $row = array_combine($entetes, $ligne);

    $n   = (int)($row['N'] ?? 0);
    $id  = (int)($row['id'] ?? 0);
    $fdr = $row['FDR'] ?? '';

    if ($n == 0 || $id == 0) continue;

    $check = $db->prepare("SELECT COUNT(*) FROM ID WHERE [N°PAT] = ?");
    $check->execute([$id]);
    if ($check->fetchColumn() == 0) continue;

    try {
        $db->exec("SET IDENTITY_INSERT FDR ON");
        $db->prepare("INSERT INTO FDR (N, id, FDR) VALUES (?,?,?)")
           ->execute([$n, $id, $fdr]);
        $db->exec("SET IDENTITY_INSERT FDR OFF");
        $nb++;
    } catch (Exception $e) {
        $db->exec("SET IDENTITY_INSERT FDR OFF");
    }
}

fclose($handle);
echo "✅ FDR importés : $nb\n";
echo "</pre>";