<?php
header('Content-Type: application/json');
require_once '../db.php';

$q = trim($_GET['q'] ?? '');
$db = getDB();

if (strlen($q) < 1) {
    // Retourner les 20 derniers patients
    $stmt = $db->prepare("SELECT TOP 20 [N°PAT] AS id, NOMPRENOM AS nom, AGE AS age FROM ID ORDER BY [N°PAT] DESC");
    $stmt->execute();
} elseif (is_numeric($q)) {
    $stmt = $db->prepare("SELECT TOP 20 [N°PAT] AS id, NOMPRENOM AS nom, AGE AS age FROM ID WHERE [N°PAT] = ?");
    $stmt->execute([(int)$q]);
} else {
    $stmt = $db->prepare("SELECT TOP 20 [N°PAT] AS id, NOMPRENOM AS nom, AGE AS age FROM ID WHERE NOMPRENOM LIKE ? ORDER BY NOMPRENOM");
    $stmt->execute(['%' . $q . '%']);
}

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));