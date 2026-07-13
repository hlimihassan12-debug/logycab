<?php
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';
$db = getDB();

header('Content-Type: application/json; charset=utf-8');

// ── Paramètres reçus ────────────────────────────────────────────
$vue  = $_GET['vue']  ?? 'patient';                 // patient | total | ECG | EDC | DTSA | DVMI
$gran = $_GET['granularite'] ?? 'mois';             // jour | mois | trimestre | annee

$actesValides = ['ECG', 'EDC', 'DTSA', 'DVMI', 'DAMI', 'DAR', 'EDC_P'];
$vuesValides  = array_merge(['patient', 'total', 'acte'], $actesValides);
if (!in_array($vue, $vuesValides, true)) $vue = 'patient';

// ── Codes exacts (table t_acte_simplifiée) par vue ──────────────
// ECG=65, EDC(ECHO-CŒUR)=66, DTSA(vaisseaux du cou)=69, DVMI(écho doppler des VMI)=68
// DAMI(doppler des AMI)=67, DAR(doppler art rénales)=74, EDC_P(écho-cœur pédiatrique)=76
$codesParVue = [
    'ECG'   => [65],
    'EDC'   => [66],
    'DTSA'  => [69],
    'DVMI'  => [68],
    'DAMI'  => [67],
    'DAR'   => [74],
    'EDC_P' => [76],
];

$granValides = ['jour', 'mois', 'trimestre', 'annee', 'tout'];
if (!in_array($gran, $granValides, true)) $gran = 'mois';

// ── Dates Du / Au : si fournies et valides, on les utilise ──────
$dateDebut = $_GET['date_debut'] ?? '';
$dateFin   = $_GET['date_fin']   ?? '';
$dateOk = (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateDebut)
       && (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFin);

if ($dateOk && $dateDebut > $dateFin) {
    // échange si inversées par erreur
    [$dateDebut, $dateFin] = [$dateFin, $dateDebut];
}

// ── Sinon, période par défaut selon la vue et la granularité ────
if (!$dateOk) {
    $today = new DateTime('today');
    if ($gran === 'tout') {
        // Tout l'historique : depuis une date très ancienne jusqu'à aujourd'hui
        $debut = new DateTime('2000-01-01');
    } elseif ($vue === 'patient') {
        // Fenêtre "en cours" : aujourd'hui / ce mois / ce trimestre / cette année
        switch ($gran) {
            case 'jour':
                $debut = clone $today;
                break;
            case 'trimestre':
                $moisDebutTrim = intdiv(((int)$today->format('n')) - 1, 3) * 3 + 1;
                $debut = new DateTime($today->format('Y') . '-' . sprintf('%02d', $moisDebutTrim) . '-01');
                break;
            case 'annee':
                $debut = new DateTime($today->format('Y') . '-01-01');
                break;
            default: // mois
                $debut = new DateTime($today->format('Y-m') . '-01');
        }
    } else {
        // Historique sur plusieurs périodes : 30 jours / 12 mois / 8 trimestres / 5 années
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
    }
    $dateDebut = $debut->format('Y-m-d');
    $dateFin   = $today->format('Y-m-d');
}

$moisFr = ['', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
                'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];

$lignes = [];
$totalNb = 0;
$totalMontant = 0.0;
$colonnePrincipale = 'Période';
$colonneCompte = 'Actes';

if ($vue === 'patient') {

    $colonnePrincipale = 'Patient';
    $colonneCompte = 'Factures';

    $sql = "
        SELECT f.id AS n_pat,
               ISNULL(p.NOMPRENOM, '(patient inconnu)') AS nom,
               COUNT(DISTINCT f.n_facture) AS nb_factures,
               ISNULL(SUM(d.Versé), 0) AS total
        FROM facture f
        LEFT JOIN detail_acte d ON d.N_fact = f.n_facture
        LEFT JOIN ID p ON p.[N°PAT] = f.id
        WHERE CONVERT(date, f.date_facture) BETWEEN ? AND ?
        GROUP BY f.id, p.NOMPRENOM
        ORDER BY total DESC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([$dateDebut, $dateFin]);

    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $montant = (float)$r['total'];
        $lignes[] = [
            'label'   => $r['nom'],
            'n_pat'   => (int)$r['n_pat'],
            'nb'      => (int)$r['nb_factures'],
            'montant' => $montant,
        ];
        $totalNb += (int)$r['nb_factures'];
        $totalMontant += $montant;
    }

} elseif ($vue === 'acte') {

    $colonnePrincipale = 'Acte';
    $colonneCompte = 'Actes';

    // Libellés d'actes raccourcis pour l'affichage (le nom en base reste inchangé)
    $renommageActes = [
        'CERTIFICAT MEDICAL'                   => 'CM',
        'CONSULTATION'                         => 'C2',
        'ECG'                                  => 'ECG',
        'ECHO-CŒUR'                            => 'EDC',
        'DOPPLER DES AMI'                      => 'DAMI',
        'ECHO DOPPLER DES VMI'                 => 'DVMI',
        'DOPPLER DES VAISSEAUX DU COU'         => 'DTSA',
        'DOPPLER DES ART RENALES'              => 'DAR',
        'HOLTER TENSIONEL'                     => 'MAPA',
        'ECHO-CŒUR PEDIATRIQUE'                => 'EDC_P',
        'INJECTION INTRAVEINEUSE MEDICAMENTS'  => 'ACT_iv',
    ];

    $sql = "
        SELECT ISNULL(a.ACTE, '(acte inconnu)') AS nom_acte,
               COUNT(*) AS nb,
               ISNULL(SUM(d.Versé), 0) AS total
        FROM detail_acte d
        INNER JOIN facture f ON d.N_fact = f.n_facture
        LEFT JOIN t_acte_simplifiée a ON d.ACTE = a.n_acte
        WHERE CONVERT(date, f.date_facture) BETWEEN ? AND ?
        GROUP BY a.ACTE
        ORDER BY total DESC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([$dateDebut, $dateFin]);

    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $montant = (float)$r['total'];
        $lignes[] = [
            'label'   => $renommageActes[$r['nom_acte']] ?? $r['nom_acte'],
            'n_pat'   => null,
            'nb'      => (int)$r['nb'],
            'montant' => $montant,
        ];
        $totalNb += (int)$r['nb'];
        $totalMontant += $montant;
    }

} else {

    $colonneCompte = 'Actes';

    switch ($gran) {
        case 'jour':
            $bucketExpr = "CONVERT(date, f.date_facture)";
            break;
        case 'trimestre':
            $bucketExpr = "DATEFROMPARTS(YEAR(f.date_facture), (DATEPART(quarter, f.date_facture)-1)*3+1, 1)";
            break;
        case 'annee':
        case 'tout': // pas de "bucket" propre à "tout" : on regroupe par année
            $bucketExpr = "DATEFROMPARTS(YEAR(f.date_facture), 1, 1)";
            break;
        default: // mois
            $bucketExpr = "DATEFROMPARTS(YEAR(f.date_facture), MONTH(f.date_facture), 1)";
    }

    if ($vue === 'total') {
        // Toutes recettes confondues : aucun filtre sur le type d'acte
        $sql = "
            SELECT $bucketExpr AS periode,
                   COUNT(*) AS nb_actes,
                   ISNULL(SUM(d.Versé), 0) AS total
            FROM detail_acte d
            INNER JOIN facture f ON d.N_fact = f.n_facture
            WHERE CONVERT(date, f.date_facture) BETWEEN ? AND ?
            GROUP BY $bucketExpr
            ORDER BY periode ASC
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([$dateDebut, $dateFin]);
    } else {
        // Par type d'acte : ECG / EDC / DTSA / DVMI, filtré sur le(s) code(s) exact(s) d'acte
        $codes = $codesParVue[$vue];
        $placeholders = implode(',', array_fill(0, count($codes), '?'));
        $sql = "
            SELECT $bucketExpr AS periode,
                   COUNT(*) AS nb_actes,
                   ISNULL(SUM(d.Versé), 0) AS total
            FROM detail_acte d
            INNER JOIN facture f ON d.N_fact = f.n_facture
            WHERE d.ACTE IN ($placeholders)
              AND CONVERT(date, f.date_facture) BETWEEN ? AND ?
            GROUP BY $bucketExpr
            ORDER BY periode ASC
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute(array_merge($codes, [$dateDebut, $dateFin]));
    }

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
            case 'tout':
                $label = $dt->format('Y');
                break;
            default: // mois
                $label = $moisFr[(int)$dt->format('n')] . ' ' . $dt->format('Y');
        }
        $montant = (float)$r['total'];
        $lignes[] = [
            'label'   => $label,
            'n_pat'   => null,
            'nb'      => (int)$r['nb_actes'],
            'montant' => $montant,
        ];
        $totalNb += (int)$r['nb_actes'];
        $totalMontant += $montant;
    }
}

echo json_encode([
    'vue'                 => $vue,
    'granularite'         => $gran,
    'date_debut'          => $dateDebut,
    'date_fin'            => $dateFin,
    'colonne_principale'  => $colonnePrincipale,
    'colonne_compte'      => $colonneCompte,
    'lignes'               => $lignes,
    'total_nb'             => $totalNb,
    'total_montant'        => $totalMontant,
], JSON_UNESCAPED_UNICODE);
