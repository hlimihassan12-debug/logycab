<?php
/**
 * ajax_maj_dossier.php
 * Sauvegarde automatique des champs du dossier patient
 * Champs acceptés : MOTIF CONSULTATION, ATCD, REMARQUE
 *
 * POST JSON : { "id": 1234, "champ": "ATCD", "valeur": "..." }
 */

require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';

header('Content-Type: application/json');

$data  = json_decode(file_get_contents('php://input'), true);
$id    = (int)($data['id']    ?? 0);
$champ = trim($data['champ']  ?? '');
$val   = trim($data['valeur'] ?? '');

// Champs autorisés (whitelist pour sécurité)
$champsAutorises = [
    'MOTIF CONSULTATION',
    'ATCD',
    'REMARQUE',
];

if ($id == 0) {
    echo json_encode(['success' => false, 'error' => 'Patient invalide']);
    exit;
}

if (!in_array($champ, $champsAutorises)) {
    echo json_encode(['success' => false, 'error' => 'Champ non autorisé']);
    exit;
}

$db = getDB();

try {
    $stmt = $db->prepare("UPDATE ID SET [$champ] = ? WHERE [N°PAT] = ?");
    $stmt->execute([$val, $id]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>