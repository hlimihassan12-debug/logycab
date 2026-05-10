<?php
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';

header('Content-Type: application/json');

$data      = json_decode(file_get_contents('php://input'), true);
$id        = (int)($data['id'] ?? 0);
$date_ordon = date('Y-m-d H:i:s');
if (!empty($data['date_ordon']) && strlen(trim($data['date_ordon'])) === 10) {
    $date_ordon = trim($data['date_ordon']) . ' 00:00:00';
}
$acte      = $data['acte'] ?? '';
$date_rdv = null;
if (!empty($data['date_rdv']) && strlen(trim($data['date_rdv'])) === 10) {
    $date_rdv = trim($data['date_rdv']) . ' 00:00:00';
}
$heure_rdv = null;
$lignes    = $data['lignes'] ?? [];

if ($id == 0) {
    echo json_encode(['success' => false, 'error' => 'Patient invalide']);
    exit;
}
error_log("date_ordon reçue: " . $date_ordon);
error_log("date_rdv reçue: " . $date_rdv);
if (empty($id)) {
    echo json_encode(['success' => false, 'error' => 'Patient invalide']);
    exit;
}

$db = getDB();

try {
    $db->beginTransaction();

    $stmt = $db->prepare("
     INSERT INTO ORD (id, date_ordon, acte1, [DATE REDEZ VOUS], HeureRDV)
VALUES (?, ?, ?, ?, ?)
    ");
  
    $stmt->execute([$id, $date_ordon, $acte, null, null]);

    $nOrd = $db->query("SELECT MAX(n_ordon) FROM ORD WHERE id = $id")->fetchColumn();

    if (!empty($lignes)) {
    $stmtMed = $db->prepare("
        INSERT INTO PROD (N_ord, produit, posologie, DUREE, Ordre)
        VALUES (?, ?, ?, ?, ?)
    ");
    foreach ($lignes as $idx => $l) {
        $stmtMed->execute([$nOrd, (int)$l['med'], $l['poso'], $l['duree'], $idx + 1]);
    }
}

    $db->commit();
    echo json_encode(['success' => true, 'n_ordon' => $nOrd]);

} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>