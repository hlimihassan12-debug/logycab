<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('max_execution_time', 600);

require_once '../db.php';
$db = getDB();

$fichier = 'C:/xampp/htdocs/logycab/exports-access/rdv.csv';

if (!file_exists($fichier)) {
    die("❌ Fichier introuvable : " . $fichier);
}

$handle = fopen($fichier, 'r');
$ligneEntete = fgets($handle);
$ligneEntete = mb_convert_encoding($ligneEntete, 'UTF-8', 'Windows-1252');
$entetes = str_getcsv($ligneEntete, ';', '"');
$entetes = array_map(fn($e) => trim($e, '"'), $entetes);

$nb = 0;
$erreurs = 0;

// Vider la table
$db->exec("DELETE FROM PROD");
$db->exec("DELETE FROM ORD");

echo "<pre>";
echo "Importation RDV en cours...\n\n";

while (($ligneRaw = fgets($handle)) !== false) {
    $ligneRaw = mb_convert_encoding($ligneRaw, 'UTF-8', 'Windows-1252');
    $ligne = str_getcsv($ligneRaw, ';', '"');
    $ligne = array_map(fn($v) => trim($v ?? '', '"'), $ligne);
    if (count($ligne) != count($entetes)) continue;
    $row = array_combine($entetes, $ligne);

    $n_ordon    = (int)($row['n_ordon'] ?? 0);
    $id         = (int)($row['id'] ?? 0);
    $date_ordon = !empty($row['date_ordon']) ? date('Y-m-d H:i:s', strtotime($row['date_ordon'])) : null;
    $dateRdv    = !empty($row['DATE REDEZ VOUS']) ? date('Y-m-d', strtotime($row['DATE REDEZ VOUS'])) : null;
    $date_Rdv   = !empty($row['Date_Rdv']) ? date('Y-m-d', strtotime($row['Date_Rdv'])) : null;
    $heureRDV   = $row['HeureRDV'] ?? '';
    $acte1      = $row['acte1'] ?? '';
    $urgence    = ($row['Urgence'] ?? '') == '-1' ? 1 : 0;
    $vu         = ($row['vu'] ?? '') == '-1' ? 1 : 0;
    $sansRep    = ($row['SansReponse'] ?? '') == '-1' ? 1 : 0;
    $obs        = $row['Observation'] ?? '';

    if ($n_ordon == 0 || $id == 0) continue;

    // Vérifier que le patient existe
    $check = $db->prepare("SELECT COUNT(*) FROM ID WHERE [N°PAT] = ?");
    $check->execute([$id]);
    if ($check->fetchColumn() == 0) continue;

    try {
        $db->exec("SET IDENTITY_INSERT ORD ON");
        $stmt = $db->prepare("
            INSERT INTO ORD (n_ordon, id, date_ordon, [DATE REDEZ VOUS], Date_Rdv,
                            HeureRDV, acte1, Urgence, vu, SansReponse, Observation)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $n_ordon, $id, $date_ordon, $dateRdv, $date_Rdv,
            $heureRDV, $acte1, $urgence, $vu, $sansRep, $obs
        ]);
        $db->exec("SET IDENTITY_INSERT ORD OFF");
        $nb++;

        if ($nb % 1000 == 0) echo "✅ $nb RDV importés...\n";

    } catch (Exception $e) {
        $db->exec("SET IDENTITY_INSERT ORD OFF");
        $erreurs++;
        if ($erreurs <= 3) echo "⚠️ Erreur N°$n_ordon : " . $e->getMessage() . "\n";
    }
}

fclose($handle);
echo "\n✅ Import terminé !\n";
echo "✅ RDV importés : $nb\n";
echo "⚠️ Erreurs : $erreurs\n";
echo "</pre>";