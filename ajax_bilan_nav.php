<?php
require_once __DIR__ . '/backend/db.php';
header('Content-Type: application/json');

$id   = (int)($_GET['id']   ?? 0);
$type = $_GET['type'] ?? '';
$dir  = $_GET['dir']  ?? '';
$ref  = (int)($_GET['ref'] ?? 0);

if (!$id || !in_array($type, ['examen','ecg','echo']) || !in_array($dir, ['prev','next','first','last'])) {
    echo json_encode(['erreur' => 'Paramètres invalides']);
    exit;
}

$db = getDB();

if ($type === 'examen') {
    $table = 't_examen'; $colId = 'NPAT'; $colDate = 'DateExam'; $colPK = 'N1';
} elseif ($type === 'ecg') {
    $table = 'ecg'; $colId = '[N-PAT]'; $colDate = '[Date ECG]'; $colPK = '[N°]';
} else {
    $table = 'echo'; $colId = '[N-PAT]'; $colDate = 'DATEchog'; $colPK = '[N°]';
}

// Convention boutons :
// |◀ first → plus récent  (pk DESC)
// ◀  prev  → plus ancien que ref (pk < ref, DESC)
// ▶  next  → plus récent que ref (pk > ref, ASC)
// ▶| last  → plus ancien  (pk ASC)

if ($dir === 'first') {
    $sql = "SELECT TOP 1 *, CONVERT(varchar,$colDate,23) AS date_fmt
            FROM $table WHERE $colId = ? ORDER BY $colPK DESC";
    $stmt = $db->prepare($sql); $stmt->execute([$id]);

} elseif ($dir === 'last') {
    $sql = "SELECT TOP 1 *, CONVERT(varchar,$colDate,23) AS date_fmt
            FROM $table WHERE $colId = ? ORDER BY $colPK ASC";
    $stmt = $db->prepare($sql); $stmt->execute([$id]);

} elseif ($ref) {
    if ($dir === 'prev') {
        // ◀ vers le passé : pk plus petit
        $sql = "SELECT TOP 1 *, CONVERT(varchar,$colDate,23) AS date_fmt
                FROM $table WHERE $colId = ? AND $colPK < ?
                ORDER BY $colPK DESC";
    } else {
        // ▶ vers le récent : pk plus grand
        $sql = "SELECT TOP 1 *, CONVERT(varchar,$colDate,23) AS date_fmt
                FROM $table WHERE $colId = ? AND $colPK > ?
                ORDER BY $colPK ASC";
    }
    $stmt = $db->prepare($sql); $stmt->execute([$id, $ref]);

} else {
    // Sans référence : prev → dernier (ancien), next → premier (récent)
    $order = ($dir === 'prev') ? 'ASC' : 'DESC';
    $sql = "SELECT TOP 1 *, CONVERT(varchar,$colDate,23) AS date_fmt
            FROM $table WHERE $colId = ? ORDER BY $colPK $order";
    $stmt = $db->prepare($sql); $stmt->execute([$id]);
}

$row = $stmt->fetch();
if (!$row) { echo json_encode(['vide' => true]); exit; }

if ($type === 'ecg') {
    $row['rythme_sv']                    = $row['RYTHME SUPRA VENTRICULAIRE'] ?? '';
    $row['trouble_rv']                   = $row['trouble de rythme']          ?? '';
    $row['rythme_v']                     = $row['RYTHME VENTRICULAIRE']       ?? '';
    $row['conduction_nodale']            = $row['LA CONDUCTION NODALE']       ?? '';
    $row['infrastructure_de_conduction'] = $row['LA CONDUCTION INFRANODALE']  ?? '';
    $row['REPOLARISATION']               = $row['LA REPOLARISATION']          ?? '';
    $row['SEGMENT_ST']                   = $row['SEGMENT ST']                 ?? '';
    $row['CC']                           = $row['C/C']                        ?? '';
    $row['AUTRES_SIGNES']                = $row['AUTRES Signes ECG']          ?? '';
}
if ($type === 'echo') {
    $row['RACINE_AO'] = $row['RACINE-AO'] ?? '';
    $row['DTD_VG']    = $row['DTD-VG']    ?? '';
    $row['DTS_VG']    = $row['DTS-VG']    ?? '';
    $row['DTSA']      = $row['DOPPLER DES TRONCS SUPRA AORTIQUES'] ?? '';
}

$dateRaw = $row['date_fmt'] ?? '';
$dateAff = '';
if ($dateRaw) {
    $parts = explode('-', $dateRaw);
    if (count($parts) === 3) $dateAff = $parts[2].'/'.$parts[1].'/'.$parts[0];
}
$row['date_affichage'] = $dateAff;
$pkField = ($type === 'examen') ? 'N1' : 'N°';
$pk = $row[$pkField] ?? 0;
$row['pk'] = $pk;

// Calcul du rang réel : combien d'enregistrements ont un PK >= pk (position depuis le plus récent)
$stmtRang = $db->prepare("SELECT COUNT(*) FROM $table WHERE $colId = ? AND $colPK >= ?");
$stmtRang->execute([$id, $pk]);
$row['rang'] = (int)$stmtRang->fetchColumn();

echo json_encode($row);
