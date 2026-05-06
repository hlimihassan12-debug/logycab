<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('max_execution_time', 600);
require_once '../db.php';
$db = getDB();

echo "<pre>";

// --- Import factures ---
$fichier = 'C:/xampp/htdocs/logycab/exports-access/factures.csv';
$handle = fopen($fichier, 'r');
$ligneEntete = fgets($handle);
$ligneEntete = mb_convert_encoding($ligneEntete, 'UTF-8', 'Windows-1252');
$entetes = str_getcsv($ligneEntete, ';', '"');
$entetes = array_map(fn($e) => trim($e, '"'), $entetes);

$db->exec("DELETE FROM detail_acte");
$db->exec("DELETE FROM facture");

$nb = 0;
echo "Importation factures...\n";

while (($ligneRaw = fgets($handle)) !== false) {
    $ligneRaw = mb_convert_encoding($ligneRaw, 'UTF-8', 'Windows-1252');
    $ligne = str_getcsv($ligneRaw, ';', '"');
    $ligne = array_map(fn($v) => trim($v ?? '', '"'), $ligne);
    if (count($ligne) != count($entetes)) continue;
    $row = array_combine($entetes, $ligne);

    $nfact   = (int)($row['n_facture'] ?? 0);
    $id      = (int)($row['id'] ?? 0);
    $date    = !empty($row['date_facture']) ? date('Y-m-d', strtotime($row['date_facture'])) : null;
    $montant = !empty($row['montant']) ? (float)$row['montant'] : 0;

    if ($nfact == 0 || $id == 0) continue;

    // Vérifier patient existe
    $check = $db->prepare("SELECT COUNT(*) FROM ID WHERE [N°PAT] = ?");
    $check->execute([$id]);
    if ($check->fetchColumn() == 0) continue;

    try {
        $db->exec("SET IDENTITY_INSERT facture ON");
        $stmt = $db->prepare("INSERT INTO facture (n_facture, id, date_facture, montant) VALUES (?,?,?,?)");
        $stmt->execute([$nfact, $id, $date, $montant]);
        $db->exec("SET IDENTITY_INSERT facture OFF");
        $nb++;
        if ($nb % 5000 == 0) echo "✅ $nb factures...\n";
    } catch (Exception $e) {
        $db->exec("SET IDENTITY_INSERT facture OFF");
    }
}
fclose($handle);
echo "✅ Factures importées : $nb\n\n";

// --- Import detail_actes ---
$fichier2 = 'C:/xampp/htdocs/logycab/exports-access/detail_actes.csv';
$handle2 = fopen($fichier2, 'r');
$ligneEntete2 = fgets($handle2);
$ligneEntete2 = mb_convert_encoding($ligneEntete2, 'UTF-8', 'Windows-1252');
$entetes2 = str_getcsv($ligneEntete2, ';', '"');
$entetes2 = array_map(fn($e) => trim($e, '"'), $entetes2);

$nb2 = 0;
$err2 = 0;
echo "Importation detail actes...\n";

while (($ligneRaw = fgets($handle2)) !== false) {
    $ligneRaw = mb_convert_encoding($ligneRaw, 'UTF-8', 'Windows-1252');
    $ligne = str_getcsv($ligneRaw, ';', '"');
    $ligne = array_map(fn($v) => trim($v ?? '', '"'), $ligne);
    if (count($ligne) != count($entetes2)) continue;
    $row = array_combine($entetes2, $ligne);

    $naacte = (int)($row['N_aacte'] ?? 0);
    $nfact  = (int)($row['N_fact'] ?? 0);
    $acte   = (int)($row['ACTE'] ?? 0);
    $prixU  = !empty($row['prixU']) ? (float)$row['prixU'] : 0;
    $verse  = !empty($row['Versé']) ? (float)$row['Versé'] : 0;
    $dette  = !empty($row['dette']) ? (float)$row['dette'] : 0;

    if ($naacte == 0 || $nfact == 0) continue;

    // Vérifier facture existe
    $check = $db->prepare("SELECT COUNT(*) FROM facture WHERE n_facture = ?");
    $check->execute([$nfact]);
    if ($check->fetchColumn() == 0) continue;

    try {
        $db->exec("SET IDENTITY_INSERT detail_acte ON");
        $stmt = $db->prepare("
            INSERT INTO detail_acte (N_aacte, N_fact, ACTE, prixU, Versé, dette)
            VALUES (?,?,?,?,?,?)
        ");
        $stmt->execute([$naacte, $nfact, $acte, $prixU, $verse, $dette]);
        $db->exec("SET IDENTITY_INSERT detail_acte OFF");
        $nb2++;
        if ($nb2 % 5000 == 0) echo "✅ $nb2 actes...\n";
    } catch (Exception $e) {
        $db->exec("SET IDENTITY_INSERT detail_acte OFF");
        $err2++;
        if ($err2 <= 3) echo "⚠️ " . $e->getMessage() . "\n";
    }
}
fclose($handle2);
echo "✅ Detail actes importés : $nb2\n";
echo "⚠️ Erreurs : $err2\n";
echo "</pre>";