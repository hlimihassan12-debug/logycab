<?php
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';
header('Content-Type: application/json');

$data   = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';
$db     = getDB();

if ($action === 'ajouter') {
    $libelle = trim($data['libelle'] ?? '');
    if (!$libelle) {
        echo json_encode(['success' => false, 'error' => 'Libellé vide']);
        exit;
    }
    // Vérifier doublon
    $stmtCheck = $db->prepare("SELECT id_spec FROM T_Specialites WHERE LOWER(libelle) = LOWER(?)");
    $stmtCheck->execute([$libelle]);
    if ($stmtCheck->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Spécialité déjà existante']);
        exit;
    }
    $stmtMax = $db->query("SELECT ISNULL(MAX(ordre),0) FROM T_Specialites");
    $maxOrdre = (int)$stmtMax->fetchColumn();
    $stmt = $db->prepare("INSERT INTO T_Specialites (libelle, ordre) VALUES (?, ?)");
    $stmt->execute([$libelle, $maxOrdre + 1]);
    $newId = $db->query("SELECT MAX(id_spec) FROM T_Specialites")->fetchColumn();
    echo json_encode(['success' => true, 'id_spec' => $newId, 'libelle' => $libelle]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Action inconnue']);
