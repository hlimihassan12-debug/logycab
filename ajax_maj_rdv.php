<?php
/**
 * ajax_maj_rdv.php
 * Met à jour DATE REDEZ VOUS et HeureRDV sur l'ordonnance courante
 *
 * POST JSON : { "n_ordon": 31767, "date_rdv": "2026-05-22", "heure_rdv": "09:00", "acte1": "ECG" }
 */
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';
header('Content-Type: application/json');

$data     = json_decode(file_get_contents('php://input'), true);
$nOrdon   = (int)($data['n_ordon']  ?? 0);
$dateRdv  = trim($data['date_rdv']  ?? '');
$heureRdv = trim($data['heure_rdv'] ?? '');
$acte1    = trim($data['acte1']     ?? '');

if ($nOrdon == 0 || empty($dateRdv)) {
    echo json_encode(['success' => false, 'error' => 'Données manquantes']);
    exit;
}

// Calcul jour_rdv et mois_rdv
$dtRdv   = new DateTime($dateRdv);
$jourRdv = (int)$dtRdv->format('d');
$moisRdv = (int)$dtRdv->format('m');
$dateRdvDt = $dateRdv . ' 00:00:00';

$db = getDB();
try {
    $stmt = $db->prepare("
        UPDATE ORD SET
            [DATE REDEZ VOUS] = CONVERT(datetime, ?, 120),
            Date_Rdv          = CONVERT(datetime, ?, 120),
            HeureRDV          = ?,
            acte1             = ?,
            jour_rdv          = ?,
            mois_rdv          = ?,
            JourRDV           = CONVERT(date, ?, 23)
        WHERE n_ordon = ?
    ");
    $stmt->execute([
        $dateRdvDt,   // DATE REDEZ VOUS
        $dateRdvDt,   // Date_Rdv
        $heureRdv ?: null,
        $acte1 ?: null,
        $jourRdv,
        $moisRdv,
        $dateRdv,
        $nOrdon
    ]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>