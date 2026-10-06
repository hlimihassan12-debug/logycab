<?php
// Suppression FORCEE d'un produit du catalogue :
// 1) efface ses lignes dans les ordonnances (table PROD)
// 2) efface le produit du catalogue (table PRODUITS)
// Le tout dans une transaction : si une etape echoue, rien n'est modifie.
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/backend/db.php';
$db = getDB();

$in = json_decode(file_get_contents('php://input'), true);
$id = (int)($in['id'] ?? 0);
if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Identifiant invalide']);
    exit;
}

try {
    $db->beginTransaction();

    $s = $db->prepare("DELETE FROM PROD WHERE produit = ?");
    $s->execute([$id]);
    $nbLignes = $s->rowCount();

    $s = $db->prepare("DELETE FROM PRODUITS WHERE [NuméroPRODUIT] = ?");
    $s->execute([$id]);
    if ($s->rowCount() !== 1) {
        $db->rollBack();
        echo json_encode(['success' => false, 'error' => 'Produit introuvable dans le catalogue']);
        exit;
    }

    $db->commit();
    echo json_encode(['success' => true, 'nb_lignes' => $nbLignes]);

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
