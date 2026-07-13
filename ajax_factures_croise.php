<?php
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';
$db = getDB();

header('Content-Type: application/json; charset=utf-8');

// ── Paramètres reçus ────────────────────────────────────────────
// axe    : lignes du tableau => 'mois' (Janvier..Décembre) ou 'acte' (liste des actes)
// valeur : ce qui remplit les cellules => 'montant' (Somme de Versé) ou 'nombre' (compte d'actes)
// Les colonnes sont toujours les années trouvées dans les factures.
$axe    = $_GET['axe']    ?? 'mois';
$valeur = $_GET['valeur'] ?? 'montant';

if (!in_array($axe, ['mois', 'acte'], true))    $axe    = 'mois';
if (!in_array($valeur, ['montant', 'nombre'], true)) $valeur = 'montant';

// ── Période optionnelle (Du/Au) ; par défaut : tout l'historique ─
$dateDebut = $_GET['date_debut'] ?? '';
$dateFin   = $_GET['date_fin']   ?? '';
$dateOk = (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateDebut)
       && (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFin);
if (!$dateOk) {
    $dateDebut = '2000-01-01';
    $dateFin   = date('Y-m-d');
}

$moisFr = ['', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
                'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];

// ── Requête : une ligne par (étiquette de ligne, année) ─────────
if ($axe === 'mois') {
    $sql = "
        SELECT MONTH(f.date_facture) AS cle,
               YEAR(f.date_facture)  AS annee,
               COUNT(*) AS nb,
               ISNULL(SUM(d.Versé), 0) AS montant
        FROM detail_acte d
        INNER JOIN facture f ON d.N_fact = f.n_facture
        WHERE CONVERT(date, f.date_facture) BETWEEN ? AND ?
        GROUP BY MONTH(f.date_facture), YEAR(f.date_facture)
    ";
} else {
    $sql = "
        SELECT ISNULL(a.ACTE, '(acte inconnu)') AS cle,
               YEAR(f.date_facture) AS annee,
               COUNT(*) AS nb,
               ISNULL(SUM(d.Versé), 0) AS montant
        FROM detail_acte d
        INNER JOIN facture f ON d.N_fact = f.n_facture
        LEFT JOIN t_acte_simplifiée a ON d.ACTE = a.n_acte
        WHERE CONVERT(date, f.date_facture) BETWEEN ? AND ?
        GROUP BY ISNULL(a.ACTE, '(acte inconnu)'), YEAR(f.date_facture)
    ";
}
$stmt = $db->prepare($sql);
$stmt->execute([$dateDebut, $dateFin]);

// ── Construction de la matrice en mémoire ───────────────────────
$donnees = [];      // [cle][annee] = ['nb'=>.., 'montant'=>..]
$totalParCle = [];  // [cle] = ['nb'=>.., 'montant'=>..]
$totalParAnnee = []; // [annee] = ['nb'=>.., 'montant'=>..]
$totalGeneral = ['nb' => 0, 'montant' => 0.0];
$anneesTrouvees = [];

while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $cle    = $axe === 'mois' ? (int)$r['cle'] : $r['cle'];
    $annee  = (int)$r['annee'];
    $nb     = (int)$r['nb'];
    $montant = (float)$r['montant'];

    $anneesTrouvees[$annee] = true;

    if (!isset($donnees[$cle])) $donnees[$cle] = [];
    $donnees[$cle][$annee] = ['nb' => $nb, 'montant' => $montant];

    if (!isset($totalParCle[$cle])) $totalParCle[$cle] = ['nb' => 0, 'montant' => 0.0];
    $totalParCle[$cle]['nb']      += $nb;
    $totalParCle[$cle]['montant'] += $montant;

    if (!isset($totalParAnnee[$annee])) $totalParAnnee[$annee] = ['nb' => 0, 'montant' => 0.0];
    $totalParAnnee[$annee]['nb']      += $nb;
    $totalParAnnee[$annee]['montant'] += $montant;

    $totalGeneral['nb']      += $nb;
    $totalGeneral['montant'] += $montant;
}

$annees = array_keys($anneesTrouvees);
sort($annees);

// ── Ordre et libellé des lignes ──────────────────────────────────
if ($axe === 'mois') {
    // Toujours les 12 mois dans l'ordre, même sans données
    $clesOrdonnees = range(1, 12);
    $libelle = function($cle) use ($moisFr) { return $moisFr[$cle]; };
} else {
    // Actes triés par montant total décroissant
    $clesOrdonnees = array_keys($totalParCle);
    usort($clesOrdonnees, function($a, $b) use ($totalParCle) {
        return $totalParCle[$b]['montant'] <=> $totalParCle[$a]['montant'];
    });
    $libelle = function($cle) { return $cle; };
}

$lignes = [];
foreach ($clesOrdonnees as $cle) {
    $valeurs = [];
    foreach ($annees as $annee) {
        $v = $donnees[$cle][$annee] ?? ['nb' => 0, 'montant' => 0.0];
        $valeurs[$annee] = $v;
    }
    $lignes[] = [
        'label'   => $libelle($cle),
        'valeurs' => $valeurs,
        'total'   => $totalParCle[$cle] ?? ['nb' => 0, 'montant' => 0.0],
    ];
}

echo json_encode([
    'axe'             => $axe,
    'valeur'          => $valeur,
    'annees'          => $annees,
    'lignes'          => $lignes,
    'total_par_annee' => $totalParAnnee,
    'total_general'   => $totalGeneral,
], JSON_UNESCAPED_UNICODE);
