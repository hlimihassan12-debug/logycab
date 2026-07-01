<?php
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';

header('Content-Type: application/json');

$data    = json_decode(file_get_contents('php://input'), true);
$n_ordon = (int)($data['n_ordon'] ?? 0);
$heure   = trim($data['heure'] ?? '');

// Validation : format HH:MM obligatoire
if (!$n_ordon || !preg_match('/^\d{2}:\d{2}$/', $heure)) {
    echo json_encode(['success' => false, 'error' => 'Paramètres invalides']);
    exit;
}

try {
    $db   = getDB();
    $stmt = $db->prepare("UPDATE ORD SET HeureRDV = ? WHERE n_ordon = ?");
    $stmt->execute([$heure, $n_ordon]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
