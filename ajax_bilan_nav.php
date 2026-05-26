<?php
require_once __DIR__ . '/backend/db.php';
header('Content-Type: application/json');

$id   = (int)($_GET['id']   ?? 0);
$type = $_GET['type'] ?? '';          // examen | ecg | echo
$dir  = $_GET['dir']  ?? '';          // prev | next
$ref  = $_GET['ref']  ?? '';          // date de référence (Y-m-d)

if (!$id || !in_array($type, ['examen','ecg','echo']) || !in_array($dir, ['prev','next','first','last'])) {
    echo json_encode(['erreur' => 'Paramètres invalides']);
    exit;
}

$db = getDB();

// ── Selon le type, table / colonne ID / colonne date ────────────────────────
if ($type === 'examen') {
    $table   = 't_examen';
    $colId   = 'NPAT';
    $colDate = 'DateExam';

} elseif ($type === 'ecg') {
    $table   = 'ecg';
    $colId   = '[N-PAT]';
    $colDate = '[Date ECG]';

} else { // echo
    $table   = 'echo';
    $colId   = '[N-PAT]';
    $colDate = 'DATEchog';
}

// ── Direction : premier / précédent / suivant / dernier ─────────────────────
if ($dir === 'first') {
    $sql  = "SELECT TOP 1 *, CONVERT(varchar,$colDate,23) AS date_fmt
             FROM $table WHERE $colId = ? ORDER BY $colDate ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute([$id]);

} elseif ($dir === 'last') {
    $sql  = "SELECT TOP 1 *, CONVERT(varchar,$colDate,23) AS date_fmt
             FROM $table WHERE $colId = ? ORDER BY $colDate DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute([$id]);

} elseif ($ref) {
    $op    = ($dir === 'prev') ? '<' : '>';
    $order = ($dir === 'prev') ? 'DESC' : 'ASC';
    $sql   = "SELECT TOP 1 *, CONVERT(varchar,$colDate,23) AS date_fmt
              FROM $table
              WHERE $colId = ? AND $colDate $op CONVERT(datetime,?,120)
              ORDER BY $colDate $order";
    $stmt = $db->prepare($sql);
    $stmt->execute([$id, $ref . ' 00:00:00']);

} else {
    // Pas de référence : dernier enregistrement
    $sql  = "SELECT TOP 1 *, CONVERT(varchar,$colDate,23) AS date_fmt
             FROM $table WHERE $colId = ? ORDER BY $colDate DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute([$id]);
}

$row = $stmt->fetch();

if (!$row) {
    echo json_encode(['vide' => true]);
    exit;
}

// ── Renommer les colonnes avec espaces/tirets pour le JS ────────────────────
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

// ── Formater la date en jj/mm/aaaa pour l'affichage ─────────────────────────
$dateRaw = $row['date_fmt'] ?? '';
$dateAff = '';
if ($dateRaw) {
    $parts = explode('-', $dateRaw);
    if (count($parts) === 3) $dateAff = $parts[2].'/'.$parts[1].'/'.$parts[0];
}
$row['date_affichage'] = $dateAff;

echo json_encode($row);
