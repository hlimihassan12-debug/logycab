<?php
require_once __DIR__ . '/../backend/db.php';
header('Content-Type: application/json; charset=utf-8');

$db = getDB();

$dateCible = trim($_GET['date'] ?? '');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateCible)) {
    echo json_encode(['ok' => false, 'erreur' => 'date_invalide']);
    exit;
}

// ── Jours fériés (même logique que ajax_prochain_jour.php) ────────────
$feriesRaw = $db->query("SELECT DateFerie FROM T_JourFeries")->fetchAll(PDO::FETCH_COLUMN);
$feries = [];
foreach ($feriesRaw as $f) {
    $f = trim($f);
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $f, $m)) {
        $feries[] = $m[3] . '-' . $m[2] . '-' . $m[1];
    } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f)) {
        $feries[] = $f;
    } else {
        $ts = strtotime($f);
        if ($ts) $feries[] = date('Y-m-d', $ts);
    }
}
$feries = array_flip($feries);

$ts  = strtotime($dateCible . ' 12:00:00');
$dow = (int)date('w', $ts); // 0 = dimanche, 6 = samedi

$estFerie    = isset($feries[$dateCible]);
$estDimanche = ($dow === 0);
$estFerme    = $estDimanche || $estFerie;

if ($estFerme) {
    echo json_encode([
        'ok'       => true,
        'ferme'    => true,
        'raison'   => $estFerie ? 'Jour férié' : 'Dimanche — cabinet fermé',
        'creneaux' => [],
    ]);
    exit;
}

// ── Limite globale du jour (T_Config, même source que l'agenda interne) ──
$stmtCfg = $db->prepare("SELECT Valeur FROM T_Config WHERE Cle='NbrMax'");
$stmtCfg->execute();
$nbrMax = (int)($stmtCfg->fetchColumn() ?: 20);

// ── RDV déjà pris ce jour-là ────────────────────────────────────────────
// Règle absolue Logycab : toujours combiner [DATE REDEZ VOUS] ET Date_Rdv,
// jamais un seul des deux champs seul.
$stmtRdv = $db->prepare("
    SELECT HeureRDV FROM ORD WHERE CONVERT(date, [DATE REDEZ VOUS]) = ?
    UNION ALL
    SELECT HeureRDV FROM ORD WHERE CONVERT(date, Date_Rdv) = ?
");
$stmtRdv->execute([$dateCible, $dateCible]);
$heures = $stmtRdv->fetchAll(PDO::FETCH_COLUMN);

$nbRdvJour = count($heures);

$patParCreneau = [];
foreach ($heures as $h) {
    $h = trim($h ?? '');
    if (preg_match('/^(\d{1,2}):(\d{2})/', $h, $m)) {
        $key = sprintf('%02d:%02d', $m[1], $m[2]);
        $patParCreneau[$key] = ($patParCreneau[$key] ?? 0) + 1;
    }
}

// ── Créneaux 9h → 16h par demi-heure (identique à agenda.php) ──────────
$creneauxBase = [];
for ($h = 9; $h <= 16; $h++) {
    $creneauxBase[] = sprintf('%02d:00', $h);
    if ($h < 16) $creneauxBase[] = sprintf('%02d:30', $h);
}

$maxParCreneau = 2; // fixé par le cabinet (2 RDV / créneau de 30 min)

$jourComplet = ($nbRdvJour >= $nbrMax);

$listeCreneaux = [];
foreach ($creneauxBase as $cr) {
    $pris = $patParCreneau[$cr] ?? 0;
    $listeCreneaux[] = [
        'heure'       => $cr,
        'pris'        => $pris,
        'disponible'  => !$jourComplet && ($pris < $maxParCreneau),
    ];
}

echo json_encode([
    'ok'           => true,
    'ferme'        => false,
    'jour_complet' => $jourComplet,
    'nb_rdv_jour'  => $nbRdvJour,
    'nbr_max'      => $nbrMax,
    'creneaux'     => $listeCreneaux,
]);
