<?php
/**
 * backend/stats_fidelite.php
 *
 * Calcule la fidélité (régularité des consultations) et la ponctualité
 * (écart entre le RDV proposé et la date réelle de la visite suivante)
 * d'un patient, à partir de la liste de ses visites.
 *
 * Base de calcul : ORD.date_ordon = date réelle de chaque consultation
 * (et non [DATE REDEZ VOUS] / Date_Rdv, qui sont la date PROPOSÉE pour
 * la visite suivante, fixée à la fin de la consultation précédente).
 *
 * Utilisé à la fois par dossier.php (un seul patient) et par
 * ajax_statistiques.php (tous les patients, page statistiques.php).
 */

/**
 * Classe un écart moyen entre consultations (en jours) en catégorie de
 * régularité. Seuils convenus avec le Dr Hassan.
 */
function classerRegularite(float $joursMoyens): array {
    if ($joursMoyens <= 45)  return ['mensuel',     'Mensuel'];
    if ($joursMoyens <= 150) return ['trimestriel', 'Trimestriel'];
    if ($joursMoyens <= 270) return ['semestriel',  'Semestriel'];
    if ($joursMoyens <= 400) return ['annuel',      'Annuel'];
    return ['irregulier', 'Irrégulier'];
}

/**
 * Classe un écart de ponctualité (en jours, positif = en retard par
 * rapport au RDV proposé, négatif = en avance) en 3 catégories.
 * Mêmes seuils que ceux déjà utilisés ailleurs dans dossier.php
 * (delaiCouleur : ≤14j vert, ≤30j orange, au-delà rouge).
 */
function classerPonctualite(float $joursEcart): string {
    $abs = abs($joursEcart);
    if ($abs <= 14) return 'ponctuel';
    if ($abs <= 30) return 'retard_modere';
    return 'retard_important';
}

/**
 * Calcule les statistiques de fidélité/ponctualité d'UN patient.
 *
 * @param array $visites Liste triée par date_ordon CROISSANTE (la plus
 *   ancienne en premier), chaque entrée étant :
 *   ['date_ordon' => 'YYYY-MM-DD', 'rdv_propose' => 'YYYY-MM-DD'|null]
 *   où rdv_propose = date du RDV fixé À CETTE visite-là pour la suivante
 *   (colonne [DATE REDEZ VOUS] ou, à défaut, Date_Rdv, de cette même ligne).
 *
 * @return array|null null si aucune visite.
 */
function calculerFidelitePatient(array $visites): ?array {
    $n = count($visites);
    if ($n === 0) return null;

    $premiere = $visites[0]['date_ordon'];
    $derniere = $visites[$n - 1]['date_ordon'];

    $ecarts    = []; // écarts entre visites consécutives (jours)
    $deltasRdv = []; // écarts RDV proposé -> date réelle (jours, signé : + = retard, - = avance)

    for ($i = 1; $i < $n; $i++) {
        $tsPrec = strtotime($visites[$i - 1]['date_ordon'] ?? '');
        $tsAct  = strtotime($visites[$i]['date_ordon'] ?? '');
        if ($tsPrec && $tsAct) {
            $ecarts[] = ($tsAct - $tsPrec) / 86400;
        }

        $rdvPropose = $visites[$i - 1]['rdv_propose'] ?? null;
        if ($rdvPropose && $tsAct) {
            $tsRdv = strtotime($rdvPropose);
            if ($tsRdv) {
                $deltasRdv[] = ($tsAct - $tsRdv) / 86400;
            }
        }
    }

    $ecartMoyen = $ecarts ? array_sum($ecarts) / count($ecarts) : null;
    if ($ecartMoyen === null) {
        $classCode  = 'insuffisant';
        $classLabel = 'Pas assez de recul (1 seule visite)';
    } else {
        [$classCode, $classLabel] = classerRegularite($ecartMoyen);
    }

    $ponctMoyenne = $deltasRdv ? array_sum($deltasRdv) / count($deltasRdv) : null;

    return [
        'nb'                   => $n,
        'premiere'             => $premiere,
        'derniere'             => $derniere,
        'ecart_moyen'          => $ecartMoyen,          // jours, ou null
        'classification'       => $classCode,           // mensuel|trimestriel|semestriel|annuel|irregulier|insuffisant
        'classification_label' => $classLabel,
        'ponctualite_moyenne'  => $ponctMoyenne,         // jours, signé, ou null si aucun RDV proposé exploitable
        'deltas_rdv'           => $deltasRdv,            // tous les écarts individuels (pour agrégation cabinet)
    ];
}

/**
 * Coefficient "étoiles" (1 à 5) résumant un patient en un coup d'œil,
 * à partir de 3 critères à parts égales (1/3 chacun) :
 *   1. Ancienneté  (depuis la première consultation)
 *   2. Fidélité    (régularité du rythme déjà calculée)
 *   3. Ponctualité (écart moyen par rapport aux RDV proposés)
 *
 * Renvoie null si le patient n'a qu'une seule consultation : pas assez
 * de recul pour noter sérieusement (affiché "N*" côté page,
 * voir formaterNoteFidelite()).
 */
function calculerNoteFidelite(?array $statsFidelite): ?int {
    if (!$statsFidelite || $statsFidelite['nb'] < 2) return null;

    // 1. Ancienneté
    $joursAnciennete = (strtotime('today') - strtotime($statsFidelite['premiere'])) / 86400;
    if ($joursAnciennete < 182)        $noteAnciennete = 1;  // < 6 mois
    elseif ($joursAnciennete < 365)    $noteAnciennete = 2;  // 6 mois - 1 an
    elseif ($joursAnciennete < 730)    $noteAnciennete = 3;  // 1 - 2 ans
    elseif ($joursAnciennete < 1460)   $noteAnciennete = 4;  // 2 - 4 ans
    else                               $noteAnciennete = 5;  // 4 ans et plus

    // 2. Fidélité (régularité) — irrégulier pénalise, tout rythme stable est noté au max
    $noteFidelite = ($statsFidelite['classification'] === 'irregulier') ? 1 : 5;

    // 3. Ponctualité
    $pm = $statsFidelite['ponctualite_moyenne'];
    if ($pm === null)           $notePonctualite = 3;  // pas encore de RDV honoré à juger
    elseif (abs($pm) <= 14)     $notePonctualite = 5;
    elseif (abs($pm) <= 30)     $notePonctualite = 3;
    else                        $notePonctualite = 1;

    $moyenne = ($noteAnciennete + $noteFidelite + $notePonctualite) / 3;
    return (int) round($moyenne);
}

/** Texte compact "5*" / "3*", ou "N*" (Nouveau) si pas assez de recul. */
function formaterNoteFidelite(?int $note): string {
    return $note === null ? 'N*' : $note . '*';
}

/** Couleur associée à la note, pour un coup d'œil encore plus rapide. */
function couleurNoteFidelite(?int $note): string {
    if ($note === null)  return '#888';     // gris — nouveau patient
    if ($note >= 4)       return '#27ae60'; // vert — bon patient
    if ($note === 3)      return '#f39c12'; // orange — moyen
    return '#e74c3c';                       // rouge — irrégulier / peu ponctuel
}
