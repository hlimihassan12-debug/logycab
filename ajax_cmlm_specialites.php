<?php
/**
 * ajax_cmlm_specialites.php
 * Gestion de la table T_Specialites — partagé entre print_cmlm.php et print_lettre.php
 * Actions :
 *   ajouter  → insère un nouveau libellé, retourne {success, id_spec}
 *   lister   → retourne tous les libellés triés
 */
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';

header('Content-Type: application/json; charset=utf-8');

$db   = getDB();
$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

// ── AJOUTER ───────────────────────────────────────────────────────────────
if ($action === 'ajouter') {
    $libelle = trim($data['libelle'] ?? '');
    if ($libelle === '') {
        echo json_encode(['success' => false, 'error' => 'Libellé vide']);
        exit;
    }

    // Vérifier doublons (insensible à la casse)
    $stmtCheck = $db->prepare("SELECT id_spec FROM T_Specialites WHERE LOWER(libelle) = LOWER(?)");
    $stmtCheck->execute([$libelle]);
    $existing = $stmtCheck->fetchColumn();
    if ($existing) {
        echo json_encode(['success' => true, 'id_spec' => $existing, 'doublon' => true]);
        exit;
    }

    // Ordre = max + 1
    $maxOrdre = $db->query("SELECT ISNULL(MAX(ordre), 0) FROM T_Specialites")->fetchColumn();

    $stmt = $db->prepare("INSERT INTO T_Specialites (libelle, ordre) VALUES (?, ?)");
    $stmt->execute([$libelle, (int)$maxOrdre + 1]);
    $newId = $db->lastInsertId();

    echo json_encode(['success' => true, 'id_spec' => $newId]);
    exit;
}

// ── LISTER ────────────────────────────────────────────────────────────────
if ($action === 'lister') {
    $stmt = $db->query("SELECT id_spec, libelle FROM T_Specialites ORDER BY ordre, libelle");
    $rows = $stmt->fetchAll();
    echo json_encode(['success' => true, 'specialites' => $rows]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Action inconnue']);
