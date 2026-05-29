<?php
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';
header('Content-Type: application/json');

$id = (int)($_GET['id'] ?? 0);
if ($id == 0) { echo json_encode(['erreur' => 'id manquant']); exit; }

$db = getDB();

// ── Dates examen clinique ──────────────────────────────────────────────────
$stmtEx = $db->prepare("
    SELECT DISTINCT CONVERT(varchar(10), DateExam, 103) AS date_fr,
                    CONVERT(varchar(10), DateExam, 112) AS date_tri
    FROM t_examen
    WHERE NPAT = ?
      AND DateExam IS NOT NULL
    ORDER BY date_tri DESC
");
$stmtEx->execute([$id]);
$dates_examen = $stmtEx->fetchAll(PDO::FETCH_ASSOC);

// ── Dates ECG ────────────────────────────────────────────────────────────
$stmtECG = $db->prepare("
    SELECT DISTINCT CONVERT(varchar(10), [Date ECG], 103) AS date_fr,
                    CONVERT(varchar(10), [Date ECG], 112) AS date_tri
    FROM ecg
    WHERE CAST([N-PAT] AS INT) = ?
      AND [Date ECG] IS NOT NULL
    ORDER BY date_tri DESC
");
$stmtECG->execute([$id]);
$dates_ecg = $stmtECG->fetchAll(PDO::FETCH_ASSOC);

// ── Dates Echo ────────────────────────────────────────────────────────────
$stmtEcho = $db->prepare("
    SELECT DISTINCT CONVERT(varchar(10), DATEchog, 103) AS date_fr,
                    CONVERT(varchar(10), DATEchog, 112) AS date_tri
    FROM echo
    WHERE [N-PAT] = ?
      AND DATEchog IS NOT NULL
    ORDER BY date_tri DESC
");
$stmtEcho->execute([$id]);
$dates_echo = $stmtEcho->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'examen' => $dates_examen,
    'ecg'    => $dates_ecg,
    'echo'   => $dates_echo,
]);
