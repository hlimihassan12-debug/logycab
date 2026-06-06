<?php
/**
 * ajax_fdr.php — Enregistre les FDR d'un patient dans la table FDR
 * La colonne N est IDENTITY (auto-incrément) → on ne la renseigne pas
 */
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$id   = (int)($data['id']  ?? 0);
$fdrs = $data['fdrs'] ?? [];

if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID patient invalide']);
    exit;
}

try {
    $db = getDB();

    // 1. Supprimer toutes les lignes existantes pour ce patient
    $db->prepare("DELETE FROM FDR WHERE id = ?")->execute([$id]);

    // 2. Réinsérer les FDR cochés — N est IDENTITY, on ne le passe pas
    if (!empty($fdrs)) {
        $stmt = $db->prepare("INSERT INTO FDR (FDR, id) VALUES (?, ?)");
        foreach ($fdrs as $fdr) {
            $stmt->execute([trim($fdr), $id]);
        }
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
