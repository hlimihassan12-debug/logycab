<?php
require_once __DIR__ . '/../db.php';
$data = json_decode(file_get_contents('php://input'), true);
$id = (int)$data['id'];
$date = $data['date'];
try {
    $db = getDB();
    $db->prepare("UPDATE facture SET date_facture = ? WHERE n_facture = ?")
       ->execute([$date, $id]);
    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}