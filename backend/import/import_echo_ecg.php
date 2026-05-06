<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('max_execution_time', 300);
require_once '../db.php';
$db = getDB();

echo "<pre>";

// ============================================================
// IMPORT ECHO
// ============================================================
$fichier = 'C:/xampp/htdocs/logycab/exports-access/echo.csv';
$handle = fopen($fichier, 'r');
$ligneEntete = fgets($handle);
$ligneEntete = mb_convert_encoding($ligneEntete, 'UTF-8', 'Windows-1252');
$entetes = str_getcsv($ligneEntete, ';', '"');
$entetes = array_map(fn($e) => trim($e, '"'), $entetes);

$db->exec("DELETE FROM echo");
$nb = 0;
echo "Importation echo...\n";

while (($ligneRaw = fgets($handle)) !== false) {
    $ligneRaw = mb_convert_encoding($ligneRaw, 'UTF-8', 'Windows-1252');
    $ligne = str_getcsv($ligneRaw, ';', '"');
    $ligne = array_map(fn($v) => trim($v ?? '', '"'), $ligne);
    if (count($ligne) != count($entetes)) continue;
    $row = array_combine($entetes, $ligne);

    $n       = (int)($row['N°'] ?? 0);
    $npat    = (int)($row['N-PAT'] ?? 0);
    $date    = !empty($row['DATEchog']) ? date('Y-m-d', strtotime($row['DATEchog'])) : null;
    $echog   = $row['ECHOGENICITE'] ?? '';
    $racine  = !empty($row['RACINE-AO']) ? (float)$row['RACINE-AO'] : null;
    $dtdvg   = !empty($row['DTD-VG']) ? (float)$row['DTD-VG'] : null;
    $dtsvg   = !empty($row['DTS-VG']) ? (float)$row['DTS-VG'] : null;
    $siv     = !empty($row['SIV']) ? (float)$row['SIV'] : null;
    $pp      = !empty($row['PP']) ? (float)$row['PP'] : null;
    $fevg    = !empty($row['FEVG']) ? (int)$row['FEVG'] : null;
    $cinet   = $row['CINETIQUE'] ?? '';
    $htap    = !empty($row['HTAP']) ? (float)$row['HTAP'] : null;
    $doppler = $row['DOPPLER'] ?? '';
    $concl   = $row['CONCLUSION1'] ?? '';
    $dtsa    = $row['DOPPLER DES TRONCS SUPRA AORTIQUES'] ?? '';

    if ($n == 0 || $npat == 0) continue;

    $check = $db->prepare("SELECT COUNT(*) FROM ID WHERE [N°PAT] = ?");
    $check->execute([$npat]);
    if ($check->fetchColumn() == 0) continue;

    try {
        $db->exec("SET IDENTITY_INSERT echo ON");
        $stmt = $db->prepare("
            INSERT INTO echo ([N°], [N-PAT], DATEchog, ECHOGENICITE, [RACINE-AO],
                [DTD-VG], [DTS-VG], SIV, PP, FEVG, CINETIQUE, HTAP,
                DOPPLER, CONCLUSION1, [DOPPLER DES TRONCS SUPRA AORTIQUES])
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        $stmt->execute([$n, $npat, $date, $echog, $racine, $dtdvg, $dtsvg,
                        $siv, $pp, $fevg, $cinet, $htap, $doppler, $concl, $dtsa]);
        $db->exec("SET IDENTITY_INSERT echo OFF");
        $nb++;
    } catch (Exception $e) {
        $db->exec("SET IDENTITY_INSERT echo OFF");
    }
}
fclose($handle);
echo "✅ Echo importés : $nb\n\n";

// ============================================================
// IMPORT ECG
// ============================================================
$fichier2 = 'C:/xampp/htdocs/logycab/exports-access/ecg.csv';
$handle2 = fopen($fichier2, 'r');
$ligneEntete2 = fgets($handle2);
$ligneEntete2 = mb_convert_encoding($ligneEntete2, 'UTF-8', 'Windows-1252');
$entetes2 = str_getcsv($ligneEntete2, ';', '"');
$entetes2 = array_map(fn($e) => trim($e, '"'), $entetes2);

$db->exec("DELETE FROM ecg");
$nb2 = 0;
echo "Importation ECG...\n";

while (($ligneRaw = fgets($handle2)) !== false) {
    $ligneRaw = mb_convert_encoding($ligneRaw, 'UTF-8', 'Windows-1252');
    $ligne = str_getcsv($ligneRaw, ';', '"');
    $ligne = array_map(fn($v) => trim($v ?? '', '"'), $ligne);
    if (count($ligne) != count($entetes2)) continue;
    $row = array_combine($entetes2, $ligne);

    $n     = (int)($row['N°'] ?? 0);
    $npat  = (int)($row['N-PAT'] ?? 0);
    $date  = !empty($row['Date ECG']) ? date('Y-m-d', strtotime($row['Date ECG'])) : null;
    $trbl  = $row['trouble de rythme'] ?? '';
    $ryth  = $row['RYTHME SUPRA VENTRICULAIRE'] ?? '';
    $freq  = !empty($row['FREQUENCE']) ? (int)$row['FREQUENCE'] : null;
    $st    = $row['SEGMENT ST'] ?? '';
    $repol = $row['LA REPOLARISATION'] ?? '';
    $idm   = $row['IDM'] ?? '';
    $cc    = $row['C/C'] ?? '';

    if ($n == 0 || $npat == 0) continue;

    $check = $db->prepare("SELECT COUNT(*) FROM ID WHERE [N°PAT] = ?");
    $check->execute([$npat]);
    if ($check->fetchColumn() == 0) continue;

    try {
        $db->exec("SET IDENTITY_INSERT ecg ON");
        $stmt = $db->prepare("
            INSERT INTO ecg ([N°], [N-PAT], [Date ECG], [trouble de rythme],
                [RYTHME SUPRA VENTRICULAIRE], FREQUENCE, [SEGMENT ST],
                [LA REPOLARISATION], IDM, [C/C])
            VALUES (?,?,?,?,?,?,?,?,?,?)
        ");
        $stmt->execute([$n, $npat, $date, $trbl, $ryth, $freq, $st, $repol, $idm, $cc]);
        $db->exec("SET IDENTITY_INSERT ecg OFF");
        $nb2++;
    } catch (Exception $e) {
        $db->exec("SET IDENTITY_INSERT ecg OFF");
    }
}
fclose($handle2);
echo "✅ ECG importés : $nb2\n";
echo "</pre>";