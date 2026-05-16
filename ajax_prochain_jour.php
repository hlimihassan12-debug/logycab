<?php
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';
header('Content-Type: application/json');

$db   = getDB();
$body = json_decode(file_get_contents('php://input'), true);
$dateCible = trim($body['date_cible'] ?? '');

if (!$dateCible || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateCible)) {
    echo json_encode(['error' => 'Date invalide']);
    exit;
}

// ── Charger les jours fériés depuis T_JourFeries ──────────────
// La colonne DateFerie peut être stockée en date SQL ou texte DD/MM/YYYY
// On normalise tout en YYYY-MM-DD pour comparaison
$stmtF = $db->query("SELECT DateFerie FROM T_JourFeries");
$feriesRaw = $stmtF->fetchAll(PDO::FETCH_COLUMN);

$feries = [];
foreach ($feriesRaw as $f) {
    $f = trim($f);
    // Si format DD/MM/YYYY → convertir
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $f, $m)) {
        $feries[] = $m[3] . '-' . $m[2] . '-' . $m[1];
    } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f)) {
        $feries[] = $f;
    } else {
        // Essai via strtotime
        $ts = strtotime($f);
        if ($ts) $feries[] = date('Y-m-d', $ts);
    }
}
$feries = array_flip($feries); // hashmap pour recherche O(1)

// ── Fonctions utilitaires ─────────────────────────────────────

function formatFr($date) {
    $jours = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
    $mois  = ['Jan','Fév','Mar','Avr','Mai','Juin','Juil','Aoû','Sep','Oct','Nov','Déc'];
    $ts = strtotime($date . ' 12:00:00');
    $j  = (int)date('j', $ts);
    $m  = (int)date('n', $ts);
    $a  = date('Y', $ts);
    $dw = (int)date('w', $ts);
    return $jours[$dw] . ' ' . $j . ' ' . $mois[$m-1] . ' ' . $a;
}

// Retourne true si le jour est "fermé" (dimanche, samedi, férié)
// Le lundi est "spécial" (à confirmer) mais PAS fermé ici
function estFerme($date, $feries) {
    $ts  = strtotime($date . ' 12:00:00');
    $dow = (int)date('w', $ts); // 0=dim, 6=sam
    return ($dow === 0 || $dow === 6 || isset($feries[$date]));
}

function estLundi($date) {
    return (int)date('w', strtotime($date . ' 12:00:00')) === 1;
}

// Cherche le prochain jour ouvert (non fermé) à partir d'une date
// direction : +1 = futur, -1 = passé
function prochainJourOuvert($dateDepart, $direction, $feries, $maxIter = 60) {
    $d = new DateTime($dateDepart . ' 12:00:00');
    $d->modify(($direction > 0 ? '+' : '-') . '1 day');
    for ($i = 0; $i < $maxIter; $i++) {
        $ds = $d->format('Y-m-d');
        if (!estFerme($ds, $feries)) return $ds;
        $d->modify(($direction > 0 ? '+' : '-') . '1 day');
    }
    return null;
}

// ── Analyse de la date cible ──────────────────────────────────

$ts  = strtotime($dateCible . ' 12:00:00');
$dow = (int)date('w', $ts); // 0=dim,1=lun,...,6=sam

$estF      = estFerme($dateCible, $feries);
$estL      = estLundi($dateCible);
$estS      = ($dow === 6); // samedi
$estFerie  = isset($feries[$dateCible]);

// Cas 1 : Jour ouvert normal (mardi→vendredi, ni férié)
if (!$estF && !$estL && !$estS) {
    echo json_encode([
        'ok'           => true,
        'date_trouvee' => $dateCible,
        'est_lundi'    => false,
        'est_samedi'   => false,
        'label'        => formatFr($dateCible),
    ]);
    exit;
}

// Cas 2 : Lundi (ouvert mais à confirmer, sauf si férié)
if ($estL && !$estFerie) {
    $avant = prochainJourOuvert($dateCible, -1, $feries);
    $apres = prochainJourOuvert($dateCible, +1, $feries);
    echo json_encode([
        'ok'          => false,
        'est_lundi'   => true,
        'est_samedi'  => false,
        'date_cible'  => $dateCible,
        'label_cible' => formatFr($dateCible),
        'date_avant'  => $avant,
        'label_avant' => $avant ? formatFr($avant) : null,
        'date_apres'  => $apres,
        'label_apres' => $apres ? formatFr($apres) : null,
        'raison'      => 'Lundi',
    ]);
    exit;
}

// Cas 3 : Samedi (à confirmer, sauf si férié)
if ($estS && !$estFerie) {
    $avant = prochainJourOuvert($dateCible, -1, $feries);
    $apres = prochainJourOuvert($dateCible, +1, $feries);
    echo json_encode([
        'ok'          => false,
        'est_lundi'   => false,
        'est_samedi'  => true,
        'date_cible'  => $dateCible,
        'label_cible' => formatFr($dateCible),
        'date_avant'  => $avant,
        'label_avant' => $avant ? formatFr($avant) : null,
        'date_apres'  => $apres,
        'label_apres' => $apres ? formatFr($apres) : null,
        'raison'      => 'Samedi',
    ]);
    exit;
}

// Cas 4 : Fermé (dimanche, ou lundi/samedi férié, ou autre férié)
$raison = $estFerie ? 'Jour férié' : ($dow === 0 ? 'Dimanche' : 'Jour fermé');
$avant  = prochainJourOuvert($dateCible, -1, $feries);
$apres  = prochainJourOuvert($dateCible, +1, $feries);

echo json_encode([
    'ok'          => false,
    'est_lundi'   => false,
    'est_samedi'  => false,
    'date_avant'  => $avant,
    'label_avant' => $avant ? formatFr($avant) : null,
    'date_apres'  => $apres,
    'label_apres' => $apres ? formatFr($apres) : null,
    'raison'      => $raison,
]);
