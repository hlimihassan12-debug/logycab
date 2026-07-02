<?php
/**
 * ajax_get_ordonnance.php
 * Lecture seule : renvoie les données d'une ordonnance existante (JSON)
 * pour préremplir la modale "Nouvelle ordonnance" en mode modification.
 *
 * GET : ?id=56&ord=1234
 */

require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';

header('Content-Type: application/json');

$id   = (int)($_GET['id']  ?? 0);
$nOrd = (int)($_GET['ord'] ?? 0);

if ($id == 0 || $nOrd == 0) {
    echo json_encode(['success' => false, 'error' => 'Paramètres invalides']);
    exit;
}

$db = getDB();

$stmtOrd = $db->prepare("SELECT * FROM ORD WHERE n_ordon = ? AND id = ?");
$stmtOrd->execute([$nOrd, $id]);
$ord = $stmtOrd->fetch();

if (!$ord) {
    echo json_encode(['success' => false, 'error' => 'Ordonnance introuvable']);
    exit;
}

$stmtMeds = $db->prepare("SELECT produit, posologie, DUREE FROM PROD WHERE N_ord = ? ORDER BY Ordre");
$stmtMeds->execute([$nOrd]);
$lignes = array_map(fn($m) => [
    'produit' => (int)$m['produit'],
    'poso'    => $m['posologie'],
    'duree'   => $m['DUREE'],
], $stmtMeds->fetchAll());

$dateOrdVal = '';
if (!empty($ord['date_ordon'])) {
    $ts = strtotime($ord['date_ordon']);
    if ($ts && $ts > 86400) $dateOrdVal = date('Y-m-d', $ts);
}
$dateRdvVal = '';
if (!empty($ord['DATE REDEZ VOUS'])) {
    $ts = strtotime($ord['DATE REDEZ VOUS']);
    if ($ts && $ts > 86400) $dateRdvVal = date('Y-m-d', $ts);
}

echo json_encode([
    'success'    => true,
    'date_ordon' => $dateOrdVal,
    'acte'       => trim($ord['acte1'] ?? ''),
    'date_rdv'   => $dateRdvVal,
    'heure_rdv'  => trim($ord['HeureRDV'] ?? ''),
    'lignes'     => $lignes,
]);
