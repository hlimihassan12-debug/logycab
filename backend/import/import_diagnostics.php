<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once '../db.php';
$db = getDB();

echo "<pre>";

function importerCSV($db, $fichier, $table, $champs, $entete) {
    if (!file_exists($fichier)) { echo "❌ Fichier introuvable : $fichier\n"; return; }
    $handle = fopen($fichier, 'r');
    $ligneEntete = fgets($handle);
    $ligneEntete = mb_convert_encoding($ligneEntete, 'UTF-8', 'Windows-1252');
    $entetes = str_getcsv($ligneEntete, ';', '"');
    $entetes = array_map(fn($e) => trim($e, '"'), $entetes);
    
    $db->exec("DELETE FROM $table");
    $nb = 0;
    
    while (($ligneRaw = fgets($handle)) !== false) {
        $ligneRaw = mb_convert_encoding($ligneRaw, 'UTF-8', 'Windows-1252');
        $ligne = str_getcsv($ligneRaw, ';', '"');
        $ligne = array_map(fn($v) => trim($v ?? '', '"'), $ligne);
        if (count($ligne) != count($entetes)) continue;
        $row = array_combine($entetes, $ligne);
        
        $vals = array_map(fn($c) => $row[$c] ?? '', $champs);
        if (empty($vals[0])) continue;
        
        $check = $db->prepare("SELECT COUNT(*) FROM ID WHERE [N°PAT] = ?");
        $check->execute([(int)$vals[1]]);
        if ($check->fetchColumn() == 0) continue;
        
        try {
            $db->exec("SET IDENTITY_INSERT $table ON");
            $placeholders = implode(',', array_fill(0, count($champs), '?'));
            $cols = implode(',', array_map(fn($c) => "[$c]", $champs));
            $db->prepare("INSERT INTO $table ($cols) VALUES ($placeholders)")
               ->execute($vals);
            $db->exec("SET IDENTITY_INSERT $table OFF");
            $nb++;
        } catch (Exception $e) {
            $db->exec("SET IDENTITY_INSERT $table OFF");
        }
    }
    fclose($handle);
    echo "✅ $table : $nb lignes importées\n";
}

$base = 'C:/xampp/htdocs/logycab/exports-access/';

importerCSV($db, $base.'t_diagnostic.csv',    't_diagnostic',
    ['N_dic', 'id', 'diagnostic'], '');

importerCSV($db, $base.'t_dianstcII.csv',     'T_dianstcII',
    ['N_DIC_II', 'id', 'DicII'], '');

importerCSV($db, $base.'t_dic_non_cardio.csv','T_id_dic_non_cardio',
    ['N_dic_non_cardio', 'id', 'dic_non_cardio'], '');

echo "</pre>";