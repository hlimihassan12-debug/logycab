<?php
/**
 * ajax_creneaux.php
 * Retourne les créneaux 09:00→16:00 (par 30min) pour une date donnée.
 *
 * Appel GET : ajax_creneaux.php?date=2026-05-15
 *
 * Réponse JSON :
 * {
 *   "date_ok": true/false,
 *   "raison": "...",          // si date_ok = false
 *   "max_jour": 21,
 *   "total_jour": 8,
 *   "jour_complet": false,
 *   "creneaux": [
 *     { "heure":"09:00", "nb":0, "statut":"libre"  },
 *     { "heure":"09:30", "nb":1, "statut":"moyen"  },
 *     { "heure":"10:00", "nb":2, "statut":"plein"  }, ...
 *   ],
 *   "premier_libre": "14:00"
 * }
 */

require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';

header('Content-Type: application/json');

$date = $_GET['date'] ?? '';

// ── Validation format ──────────────────────────────────────────────────────
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['error' => 'Date invalide']);
    exit;
}

$db = getDB();
$dt = new DateTime($date);

// ── 1. Lundi = fermé ──────────────────────────────────────────────────────
if ((int)$dt->format('N') === 1) {
    echo json_encode(['date_ok' => false, 'raison' => 'Le cabinet est fermé le lundi.']);
    exit;
}

// ── 2. Jour férié ─────────────────────────────────────────────────────────
$stmtF = $db->prepare("SELECT COUNT(*) FROM T_JoursFeries WHERE CAST(DateFerie AS DATE) = ?");
$stmtF->execute([$date]);
if ((int)$stmtF->fetchColumn() > 0) {
    echo json_encode(['date_ok' => false, 'raison' => 'Ce jour est férié — cabinet fermé.']);
    exit;
}

// ── 3. Max patients ce jour ───────────────────────────────────────────────
$stmtM = $db->prepare("SELECT valeur FROM T_Config WHERE cle = ?");
$stmtM->execute(['MaxPatients_' . $date]);
$maxVal = $stmtM->fetchColumn();
if ($maxVal === false) {
    $stmtM2 = $db->prepare("SELECT valeur FROM T_Config WHERE cle = 'MaxPatientsJour'");
    $stmtM2->execute();
    $maxVal = $stmtM2->fetchColumn();
}
$maxJour = (int)($maxVal ?: 21);

// ── 4. Total patients inscrits ce jour ───────────────────────────────────
$stmtT = $db->prepare("
    SELECT COUNT(*) FROM ORD
    WHERE (
        ([DATE REDEZ VOUS] IS NOT NULL AND CAST([DATE REDEZ VOUS] AS DATE) = ?)
        OR
        (Date_Rdv IS NOT NULL AND CAST(Date_Rdv AS DATE) = ?)
    )
");
$stmtT->execute([$date, $date]);
$totalJour = (int)$stmtT->fetchColumn();

// ── 5. Occupation par créneau (HeureRDV stocké en texte 'HH:MM') ─────────
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
    $h = substr(trim($row['HeureRDV']), 0, 5); // normalise à HH:MM
    $occup[$h] = (int)$row['nb'];
}

// ── 6. Construire créneaux 09:00 → 16:00 par 30min ───────────────────────
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

// ── 7. Réponse ────────────────────────────────────────────────────────────
echo json_encode([
    'date_ok'       => true,
    'raison'        => '',
    'max_jour'      => $maxJour,
    'total_jour'    => $totalJour,
    'jour_complet'  => ($totalJour >= $maxJour),
    'creneaux'      => $creneaux,
    'premier_libre' => $premierLibre,
], JSON_UNESCAPED_UNICODE);