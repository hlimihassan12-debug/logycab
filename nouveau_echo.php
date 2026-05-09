<?php
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
if ($id == 0) { header('Location: recherche.php'); exit; }

$stmt = $db->prepare("INSERT INTO echo ([N-PAT], [DATEchog], [ECHOGENICITE], [RACINE-AO], [DTD-VG], [DTS-VG], [SIV], [PP], [FEVG], [CINETIQUE], [HTAP], [DOPPLER], [CONCLUSION1], [DOPPLER DES TRONCS SUPRA AORTIQUES]) VALUES (?, GETDATE(), NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL)");
$stmt->execute([$id]);

$newEcho = $db->lastInsertId();

header("Location: dossier.php?id=$id&echo=$newEcho");
exit;
?>