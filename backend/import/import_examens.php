<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('max_execution_time', 600);

require_once '../db.php';
$db = getDB();

$fichier = 'C:/xampp/htdocs/logycab/exports-access/examens.csv';

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

$db->exec("DELETE FROM t_examen");

echo "<pre>Importation examens en cours...\n\n";

while (($ligneRaw = fgets($handle)) !== false) {
    $ligneRaw = mb_convert_encoding($ligneRaw, 'UTF-8', 'Windows-1252');
    $ligne = str_getcsv($ligneRaw, ';', '"');
    $ligne = array_map(fn($v) => trim($v ?? '', '"'), $ligne);
    if (count($ligne) != count($entetes)) continue;
    $row = array_combine($entetes, $ligne);

    $n1    = (int)($row['N1'] ?? 0);
    $npat  = (int)($row['NPAT'] ?? 0);
    $date  = !empty($row['DateExam']) ? date('Y-m-d', strtotime($row['DateExam'])) : null;
    $tas   = !empty($row['TAS']) ? (int)$row['TAS'] : null;
    $tad   = !empty($row['TAD']) ? (int)$row['TAD'] : null;
    $fc    = !empty($row['FC']) ? (int)$row['FC'] : null;
    $poids = !empty($row['POIDS']) ? (float)$row['POIDS'] : null;
    $taille = !empty($row['TAILLE']) ? (int)$row['TAILLE'] : null;
    $imc   = !empty($row['IMC']) ? (float)$row['IMC'] : null;
    $sf    = $row['S_Fonctionnels'] ?? '';
    $ac    = $row['Auscult_Cardiaque'] ?? '';
    $ap    = $row['Auscult_Pulmonaire'] ?? '';
    $concl = $row['Conclusion'] ?? '';
    $rem   = $row['REMARQUE'] ?? '';

    if ($n1 == 0 || $npat == 0) continue;

    // Vérifier patient existe
    $check = $db->prepare("SELECT COUNT(*) FROM ID WHERE [N°PAT] = ?");
    $check->execute([$npat]);
    if ($check->fetchColumn() == 0) continue;

    try {
        $db->exec("SET IDENTITY_INSERT t_examen ON");
        $stmt = $db->prepare("
            INSERT INTO t_examen (N1, NPAT, DateExam, TAS, TAD, FC, POIDS, TAILLE, IMC,
                S_Fonctionnels, Auscult_Cardiaque, Auscult_Pulmonaire, Conclusion, REMARQUE,
                FDR_Age, FDR_HTA, FDR_Diabete, FDR_Tabac, FDR_Obesite, FDR_Surpoids,
                FDR_LDL_Oui, FDR_TG_Oui, FDR_ATCD_IDM_Fam, FDR_ATCD_AVC_Fam,
                FDR_Sedentarite, FDR_Synd_Metabolique, FDR_Stress_Depression,
                FDR_Sommeil, FDR_Drogues,
                Diag_Ischemique, Diag_Hypertensive, Diag_Valvulaire, Diag_Rythmique,
                Diag_CMD_Hypokin, Diag_CMH_NonObstr, Diag_CMH_Obstr)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        $stmt->execute([
            $n1, $npat, $date, $tas, $tad, $fc, $poids, $taille, $imc,
            $sf, $ac, $ap, $concl, $rem,
            (int)$row['FDR_Age'], (int)$row['FDR_HTA'],
            (int)$row['FDR_Diabete'], (int)$row['FDR_Tabac'],
            (int)$row['FDR_Obesite'], (int)$row['FDR_Surpoids'],
            (int)$row['FDR_LDL_Oui'], (int)$row['FDR_TG_Oui'],
            (int)$row['FDR_ATCD_IDM_Fam'], (int)$row['FDR_ATCD_AVC_Fam'],
            (int)$row['FDR_Sedentarite'], (int)$row['FDR_Synd_Metabolique'],
            (int)$row['FDR_Stress_Depression'], (int)$row['FDR_Sommeil'],
            (int)$row['FDR_Drogues'],
            (int)$row['Diag_Ischemique'], (int)$row['Diag_Hypertensive'],
            (int)$row['Diag_Valvulaire'], (int)$row['Diag_Rythmique'],
            (int)$row['Diag_CMD_Hypokin'], (int)$row['Diag_CMH_NonObstr'],
            (int)$row['Diag_CMH_Obstr']
        ]);
        $db->exec("SET IDENTITY_INSERT t_examen OFF");
        $nb++;
        if ($nb % 1000 == 0) echo "✅ $nb examens importés...\n";

    } catch (Exception $e) {
        $db->exec("SET IDENTITY_INSERT t_examen OFF");
        $erreurs++;
        if ($erreurs <= 3) echo "⚠️ Erreur N1=$n1 : " . $e->getMessage() . "\n";
    }
}

fclose($handle);
echo "\n✅ Import terminé !\n";
echo "✅ Examens importés : $nb\n";
echo "⚠️ Erreurs : $erreurs\n";
echo "</pre>";