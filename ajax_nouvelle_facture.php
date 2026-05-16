<?php
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$id           = (int)($data['id'] ?? 0);
$date_facture = $data['date_facture'] ?? date('Y-m-d');
$lignes       = $data['lignes'] ?? [];

if ($id == 0 || empty($lignes)) {
    echo json_encode(['success' => false, 'error' => 'Données invalides']);
    exit;
}

$db = getDB();

try {
    $db->beginTransaction();

    $stmt = $db->prepare("INSERT INTO facture (id, date_facture, mode_paiement, montant, remarque) VALUES (?, CONVERT(datetime, ?, 120), NULL, 0, NULL)");
    $stmt->execute([$id, $date_facture]);
    $nFact = $db->query("SELECT MAX(n_facture) FROM facture WHERE id = $id")->fetchColumn();

    $stmtDA = $db->prepare("INSERT INTO detail_acte (N_fact, [date-H], ACTE, prixU, QTIT, Versé, dette) VALUES (?, CONVERT(datetime, ?, 120), ?, ?, 1, ?, ?)");
    $total = 0;
    foreach ($lignes as $l) {
        $acteId   = (int)$l['acte'];
        $prixU    = (float)$l['prix'];
        $verse    = (float)($l['verse'] ?? 0);
        $dette    = $prixU - $verse;
        $dateActe = !empty($l['date_acte']) ? $l['date_acte'] : $date_facture;
        $total   += $prixU;
        $stmtDA->execute([$nFact, $dateActe, $acteId, $prixU, $verse, $dette]);
    }

    $db->prepare("UPDATE facture SET montant = ? WHERE n_facture = ?")->execute([$total, $nFact]);

    $db->commit();
    echo json_encode(['success' => true, 'n_facture' => $nFact]);

} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>