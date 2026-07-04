<?php
/**
 * ajax_maj_cat.php
 * Sauvegarde du champ "Au total — Conduite à tenir" (CAT)
 * sur une ligne t_examen déjà existante (jamais de création).
 *
 * POST JSON : { "n1": 1234, "valeur": "..." }
 */

require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';

header('Content-Type: application/json');

$data  = json_decode(file_get_contents('php://input'), true);
$n1    = (int)($data['n1']    ?? 0);
$val   = trim($data['valeur'] ?? '');

if ($n1 == 0) {
    echo json_encode(['success' => false, 'error' => 'Examen invalide']);
    exit;
}

$db = getDB();

try {
    $stmt = $db->prepare("UPDATE t_examen SET Conduite_ATenir = ? WHERE N1 = ?");
    $stmt->execute([$val, $n1]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
