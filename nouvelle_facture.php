<?php
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
if ($id == 0) { header('Location: recherche.php'); exit; }

$stmt = $db->prepare("INSERT INTO facture (id, date_facture, mode_paiement, montant, remarque) VALUES (?, GETDATE(), NULL, 0, NULL)");
$stmt->execute([$id]);

$maxFact = $db->lastInsertId();

header("Location: dossier.php?id=$id&fact=$maxFact");
exit;
?>