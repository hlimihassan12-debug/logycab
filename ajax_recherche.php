<?php
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';
header('Content-Type: application/json; charset=utf-8');

$db = getDB();
$q  = trim($_GET['q'] ?? '');
$resultats = [];

if (strlen($q) >= 2) {
    if (is_numeric($q)) {
        $stmt = $db->prepare("SELECT TOP 20 [N°PAT], NOMPRENOM FROM ID WHERE [N°PAT] = ? ORDER BY NOMPRENOM");
        $stmt->execute([(int)$q]);
    } else {
        $stmt = $db->prepare("SELECT TOP 20 [N°PAT], NOMPRENOM FROM ID WHERE NOMPRENOM LIKE ? ORDER BY NOMPRENOM");
        $stmt->execute(['%' . $q . '%']);
    }
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $resultats[] = ['id' => $r['N°PAT'], 'nom' => $r['NOMPRENOM']];
    }
}

echo json_encode($resultats);
