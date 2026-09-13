<?php
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';
require_once __DIR__ . '/backend/stats_fidelite.php';
$db = getDB();

header('Content-Type: application/json; charset=utf-8');

// ── Paramètre reçu : granularité de la courbe ────────────────────
$gran = $_GET['granularite'] ?? 'mois';          // jour | mois | trimestre | annee
$granValides = ['jour', 'mois', 'trimestre', 'annee'];
if (!in_array($gran, $granValides, true)) $gran = 'mois';

// ══════════════════════════════════════════════════════════════
// 1. FIDÉLITÉ & PONCTUALITÉ — une ligne par visite réelle
// ══════════════════════════════════════════════════════════════
$sql = "
    SELECT id,
           CONVERT(varchar(10), date_ordon, 23)          AS date_ordon,
           CONVERT(varchar(10), [DATE REDEZ VOUS], 23)    AS rdv_fixe,
           CONVERT(varchar(10), Date_Rdv, 23)             AS rdv_tel
    FROM ORD
    WHERE date_ordon IS NOT NULL
    ORDER BY id, date_ordon
";
$rows = $db->query($sql)->fetchAll();

// Regroupe les visites par patient (comme un tableau de recordsets, un par id)
$parPatient = [];
foreach ($rows as $r) {
    $rdvPropose = $r['rdv_fixe'] ?: ($r['rdv_tel'] ?: null);
    $parPatient[$r['id']][] = [
        'date_ordon'  => $r['date_ordon'],
        'rdv_propose' => $rdvPropose,
    ];
}

$repartition = [
    'mensuel'     => 0,
    'trimestriel' => 0,
    'semestriel'  => 0,
    'annuel'      => 0,
    'irregulier'  => 0,
    'insuffisant' => 0,
];
$tousDeltasRdv = [];

foreach ($parPatient as $visites) {
    $stats = calculerFidelitePatient($visites);
    if (!$stats) continue;
    $repartition[$stats['classification']]++;
    if ($stats['deltas_rdv']) {
        $tousDeltasRdv = array_merge($tousDeltasRdv, $stats['deltas_rdv']);
    }
}

$nbTransitions  = count($tousDeltasRdv);
$moyenneGlobale = $nbTransitions ? array_sum($tousDeltasRdv) / $nbTransitions : null;
$ponctuels = 0; $retardModere = 0; $retardImportant = 0;
foreach ($tousDeltasRdv as $d) {
    switch (classerPonctualite($d)) {
        case 'ponctuel':         $ponctuels++;        break;
        case 'retard_modere':    $retardModere++;     break;
        case 'retard_important': $retardImportant++;  break;
    }
}

// ══════════════════════════════════════════════════════════════
// 2. COURBE — nombre de patients distincts vus par période
// ══════════════════════════════════════════════════════════════
switch ($gran) {
    case 'jour':
        $bucketExpr = "CONVERT(date, date_ordon)";
        break;
    case 'trimestre':
        $bucketExpr = "DATEFROMPARTS(YEAR(date_ordon), (DATEPART(quarter, date_ordon)-1)*3+1, 1)";
        break;
    case 'annee':
        $bucketExpr = "DATEFROMPARTS(YEAR(date_ordon), 1, 1)";
        break;
    default: // mois
        $bucketExpr = "DATEFROMPARTS(YEAR(date_ordon), MONTH(date_ordon), 1)";
}

$today = new DateTime('today');
switch ($gran) {
    case 'jour':
        $debut = (clone $today)->modify('-29 days');
        break;
    case 'trimestre':
        $debut = (clone $today)->modify('-23 months')->modify('first day of this month');
        break;
    case 'annee':
        $debut = (clone $today)->modify('-4 years')->modify('first day of January');
        break;
    default: // mois
        $debut = (clone $today)->modify('-11 months')->modify('first day of this month');
}
$dateDebutChart = $debut->format('Y-m-d');
$dateFinChart   = $today->format('Y-m-d');

$sqlChart = "
    SELECT $bucketExpr AS periode, COUNT(DISTINCT id) AS nb_patients
    FROM ORD
    WHERE date_ordon IS NOT NULL
      AND CONVERT(date, date_ordon) BETWEEN ? AND ?
    GROUP BY $bucketExpr
    ORDER BY periode ASC
";
$stmt = $db->prepare($sqlChart);
$stmt->execute([$dateDebutChart, $dateFinChart]);

$moisFr = ['', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
                'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
$chartLabels = [];
$chartData   = [];
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $dt = new DateTime($r['periode']);
    switch ($gran) {
        case 'jour':
            $label = $dt->format('d/m/Y');
            break;
        case 'trimestre':
            $q = intdiv(((int)$dt->format('n')) - 1, 3) + 1;
            $label = 'T' . $q . ' ' . $dt->format('Y');
            break;
        case 'annee':
            $label = $dt->format('Y');
            break;
        default: // mois
            $label = $moisFr[(int)$dt->format('n')] . ' ' . $dt->format('Y');
    }
    $chartLabels[] = $label;
    $chartData[]   = (int)$r['nb_patients'];
}

echo json_encode([
    'granularite'        => $gran,
    'repartition'        => $repartition,
    'nb_patients_total'  => count($parPatient),
    'ponctualite'        => [
        'moyenne_jours'    => $moyenneGlobale,
        'nb_transitions'   => $nbTransitions,
        'ponctuels'        => $ponctuels,
        'retard_modere'    => $retardModere,
        'retard_important' => $retardImportant,
    ],
    'chart' => [
        'labels' => $chartLabels,
        'data'   => $chartData,
    ],
], JSON_UNESCAPED_UNICODE);
