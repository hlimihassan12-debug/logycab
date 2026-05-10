<?php
/**
 * ajax_prochain_jour.php
 * À partir d'une date cible (ex: aujourd'hui + 3 mois),
 * cherche le premier jour disponible :
 *   - Pas lundi
 *   - Pas férié (T_JoursFeries)
 *   - Total patients < MaxJour (T_Config)
 * Et retourne aussi les créneaux de ce jour.
 *
 * Appel POST JSON : { "date_cible": "2026-08-10" }
 * Réponse : même format que ajax_creneaux.php + "date_trouvee"
 */

require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$dateCible = $input['date_cible'] ?? '';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateCible)) {
    echo json_encode(['error' => 'Date invalide']);
    exit;
}

$db = getDB();

// Charger les jours fériés en mémoire (optimisation)
$stmtFeries = $db->query("SELECT CAST(DateFerie AS DATE) AS d FROM T_JoursFeries");
$feriesSet  = array_flip($stmtFeries->fetchAll(PDO::FETCH_COLUMN));

// MaxPatientsJour par défaut
$stmtMaxDef = $db->prepare("SELECT valeur FROM T_Config WHERE cle = 'MaxPatientsJour'");
$stmtMaxDef->execute();
$maxDefaut = (int)($stmtMaxDef->fetchColumn() ?: 21);

// Fonction : obtenir le max pour un jour précis
function getMaxJour($db, $date, $maxDefaut) {
    $stmt = $db->prepare("SELECT valeur FROM T_Config WHERE cle = ?");
    $stmt->execute(['MaxPatients_' . $date]);
    $v = $stmt->fetchColumn();
    return (int)($v !== false ? $v : $maxDefaut);
}

// Fonction : compter les patients inscrits un jour donné
function getTotalJour($db, $date) {
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM ORD
        WHERE (
            ([DATE REDEZ VOUS] IS NOT NULL AND CAST([DATE REDEZ VOUS] AS DATE) = ?)
            OR
            (Date_Rdv IS NOT NULL AND CAST(Date_Rdv AS DATE) = ?)
        )
    ");
    $stmt->execute([$date, $date]);
    return (int)$stmt->fetchColumn();
}

// Recherche du premier jour disponible (max 90 jours de recherche)
$dt      = new DateTime($dateCible);
$trouve  = null;
$tentatives = 0;

while ($tentatives < 90) {
    $dateTest = $dt->format('Y-m-d');
    $jourSem  = (int)$dt->format('N'); // 1=lundi ... 7=dimanche

    $estLundi  = ($jourSem === 1);
    $estFerie  = isset($feriesSet[$dateTest]);
    $maxJ      = getMaxJour($db, $dateTest, $maxDefaut);
    $totalJ    = getTotalJour($db, $dateTest);
    $estComplet = ($totalJ >= $maxJ);

    if (!$estLundi && !$estFerie && !$estComplet) {
        $trouve = $dateTest;
        break;
    }

    $dt->modify('+1 day');
    $tentatives++;
}

if (!$trouve) {
    echo json_encode(['error' => 'Aucun jour disponible dans les 90 prochains jours.']);
    exit;
}

// Maintenant charger les créneaux du jour trouvé
$date = $trouve;
$maxJour   = getMaxJour($db, $date, $maxDefaut);
$totalJour = getTotalJour($db, $date);

$stmtC = $db->prepare("
    SELECT HeureRDV, COUNT(*) AS nb
    FROM ORD
    WHERE (
        ([DATE REDEZ VOUS] IS NOT NULL AND CAST([DATE REDEZ VOUS] AS DATE) = ?)
        OR
        (Date_Rdv IS NOT NULL AND CAST(Date_Rdv AS DATE) = ?)
    )
      AND HeureRDV IS NOT NULL AND HeureRDV != ''
    GROUP BY HeureRDV
");
$stmtC->execute([$date, $date]);
$occup = [];
while ($row = $stmtC->fetch(PDO::FETCH_ASSOC)) {
    $h = substr(trim($row['HeureRDV']), 0, 5);
    $occup[$h] = (int)$row['nb'];
}

$creneaux     = [];
$premierLibre = null;

for ($t = strtotime('09:00'); $t <= strtotime('16:00'); $t += 1800) {
    $h  = date('H:i', $t);
    $nb = $occup[$h] ?? 0;

    if      ($nb === 0) $statut = 'libre';
    elseif  ($nb === 1) $statut = 'moyen';
    else                $statut = 'plein';

    if ($premierLibre === null && $nb < 2) $premierLibre = $h;

    $creneaux[] = ['heure' => $h, 'nb' => $nb, 'statut' => $statut];
}

echo json_encode([
    'date_trouvee'  => $trouve,
    'date_ok'       => true,
    'raison'        => '',
    'max_jour'      => $maxJour,
    'total_jour'    => $totalJour,
    'jour_complet'  => false,
    'creneaux'      => $creneaux,
    'premier_libre' => $premierLibre,
], JSON_UNESCAPED_UNICODE);