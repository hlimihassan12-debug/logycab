<?php
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
if ($id == 0) { header('Location: recherche.php'); exit; }

$stmt = $db->prepare("INSERT INTO ecg ([N-PAT], [Date ECG], [trouble de rythme], [RYTHME SUPRA VENTRICULAIRE], [FREQUENCE], [SEGMENT ST], [LA REPOLARISATION], [IDM], [C/C]) VALUES (?, GETDATE(), NULL, NULL, NULL, NULL, NULL, NULL, NULL)");
$stmt->execute([$id]);

$newEcg = $db->lastInsertId();

header("Location: dossier.php?id=$id&ecg=$newEcg");
exit;
?>