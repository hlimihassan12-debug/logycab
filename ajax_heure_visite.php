<?php
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';

header('Content-Type: application/json');

$data    = json_decode(file_get_contents('php://input'), true);
$n_ordon = (int)($data['n_ordon'] ?? 0);
$heure   = trim($data['heure'] ?? '');

// Validation basique : format HH:MM
if ($n_ordon <= 0) {
    echo json_encode(['success' => false, 'error' => 'Ordonnance invalide']);
    exit;
}
if ($heure !== '' && !preg_match('/^\d{2}:\d{2}$/', $heure)) {
    echo json_encode(['success' => false, 'error' => 'Format heure invalide']);
    exit;
}

try {
    $db   = getDB();
    $stmt = $db->prepare("UPDATE ORD SET HeureVisite = ? WHERE n_ordon = ?");
    $stmt->execute([$heure ?: null, $n_ordon]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
