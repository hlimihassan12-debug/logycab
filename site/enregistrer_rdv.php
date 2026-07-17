<?php
require_once __DIR__ . '/../backend/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: rendez-vous.php');
    exit;
}

// Champ piège anti-spam : si rempli, c'est un robot -> on fait comme si tout allait bien
if (!empty($_POST['site_web'])) {
    header('Location: rendez-vous.php?ok=1');
    exit;
}

$nom       = trim($_POST['nom'] ?? '');
$telephone = trim($_POST['telephone'] ?? '');
$email     = trim($_POST['email'] ?? '');
$motif     = trim($_POST['motif'] ?? '');
$dateS     = trim($_POST['date_souhaitee'] ?? '');
$heureS    = trim($_POST['heure_souhaitee'] ?? '');
$message   = trim($_POST['message'] ?? '');

if ($nom === '' || $telephone === '') {
    header('Location: rendez-vous.php?erreur=champs_manquants');
    exit;
}

$dateSouhaitee = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateS) ? $dateS : null;
$heureSouhaitee = preg_match('/^\d{2}:\d{2}/', $heureS) ? substr($heureS, 0, 5) : null;

try {
    $db = getDB();
    $stmt = $db->prepare("
        INSERT INTO T_DemandesRDV (nom, telephone, email, motif, date_souhaitee, heure_souhaitee, message)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $nom,
        $telephone,
        $email !== '' ? $email : null,
        $motif !== '' ? $motif : null,
        $dateSouhaitee,
        $heureSouhaitee,
        $message !== '' ? $message : null,
    ]);
    header('Location: rendez-vous.php?ok=1');
    exit;
} catch (Exception $e) {
    header('Location: rendez-vous.php?erreur=technique');
    exit;
}
