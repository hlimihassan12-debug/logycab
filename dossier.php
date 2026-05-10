<?php
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
if ($id == 0) { header('Location: recherche.php'); exit; }

// Patient
$stmt = $db->prepare("SELECT * FROM ID WHERE [N°PAT] = ?");
$stmt->execute([$id]);
$patient = $stmt->fetch();
if (!$patient) { die("❌ Patient introuvable !"); }

// Diagnostics
$stmtDiag = $db->prepare("SELECT N_dic, diagnostic FROM t_diagnostic WHERE id = ? ORDER BY N_dic");
$stmtDiag->execute([$id]);
$diagnostics = $stmtDiag->fetchAll();

$stmtDiag2 = $db->prepare("SELECT N_DIC_II, DicII FROM T_dianstcII WHERE id = ? ORDER BY N_DIC_II");
$stmtDiag2->execute([$id]);
$diagnosticsII = $stmtDiag2->fetchAll();

$stmtDiagNC = $db->prepare("SELECT N_dic_non_cardio, dic_non_cardio FROM T_id_dic_non_cardio WHERE id = ? ORDER BY N_dic_non_cardio");
$stmtDiagNC->execute([$id]);
$diagnosticsNC = $stmtDiagNC->fetchAll();

// FDR permanents du patient
$stmtFDR = $db->prepare("SELECT FDR FROM patient_fdr WHERE id = ? ORDER BY N");
$stmtFDR->execute([$id]);
$fdrPatient = $stmtFDR->fetchAll(PDO::FETCH_COLUMN);

// Navigation entre patients qui ont des ordonnances
$first_id = $db->query("SELECT TOP 1 [N°PAT] FROM ID WHERE [N°PAT] IN (SELECT DISTINCT id FROM ORD) ORDER BY [N°PAT] ASC")->fetchColumn();
$last_id  = $db->query("SELECT TOP 1 [N°PAT] FROM ID WHERE [N°PAT] IN (SELECT DISTINCT id FROM ORD) ORDER BY [N°PAT] DESC")->fetchColumn();

$prev_id  = $db->prepare("SELECT TOP 1 [N°PAT] FROM ID WHERE [N°PAT] < ? AND [N°PAT] IN (SELECT DISTINCT id FROM ORD) ORDER BY [N°PAT] DESC");
$prev_id->execute([$id]); $prev_id = $prev_id->fetchColumn() ?: $id;

$next_id  = $db->prepare("SELECT TOP 1 [N°PAT] FROM ID WHERE [N°PAT] > ? AND [N°PAT] IN (SELECT DISTINCT id FROM ORD) ORDER BY [N°PAT] ASC");
$next_id->execute([$id]); $next_id = $next_id->fetchColumn() ?: $id;

$total_patients = $db->query("SELECT COUNT(DISTINCT id) FROM ORD")->fetchColumn();
$pos_patient    = $db->prepare("SELECT COUNT(DISTINCT id) FROM ORD WHERE id <= ?");
$pos_patient->execute([$id]); $pos_patient = $pos_patient->fetchColumn();

// Calcul âge
$age = '';
if ($patient['DDN']) {
    $naissance = new DateTime($patient['DDN']);
    $age = $naissance->diff(new DateTime())->y;
}

// Ordonnances
$stmtOrd = $db->prepare("SELECT * FROM ORD WHERE id=? ORDER BY date_ordon DESC");
$stmtOrd->execute([$id]);
$ordonnances = $stmtOrd->fetchAll();
$nOrd = (int)($_GET['ord'] ?? ($ordonnances ? $ordonnances[0]['n_ordon'] : 0));

$ordCourante = null;
$idxOrdCourante = 0;
foreach ($ordonnances as $i => $o) {
    if ($o['n_ordon'] == $nOrd) { $ordCourante = $o; $idxOrdCourante = $i; break; }
}

// Ordonnance précédente
$ordPrecedente = isset($ordonnances[$idxOrdCourante + 1]) ? $ordonnances[$idxOrdCourante + 1] : null;

// Acte suggéré pour le nouveau RDV
$acteNouveauRDV = '';
if ($ordPrecedente) {
    $acteNouveauRDV = $ordPrecedente['acte1'] ?? '';
}

// Actes automatiques basés sur les ordonnances
$actesSuggeres = [];
$stmtLastECG = $db->prepare("SELECT TOP 1 date_ordon FROM ORD WHERE id=? AND acte1 LIKE '%ECG%' ORDER BY date_ordon DESC");
$stmtLastECG->execute([$id]); $lastECG = $stmtLastECG->fetchColumn();
if (!$lastECG || (new DateTime())->diff(new DateTime($lastECG))->days > 30) {
    $actesSuggeres[] = ['acte' => 'ECG', 'derniere' => $lastECG];
}
$stmtLastEDC = $db->prepare("SELECT TOP 1 date_ordon FROM ORD WHERE id=? AND acte1 LIKE '%EDC%' ORDER BY date_ordon DESC");
$stmtLastEDC->execute([$id]); $lastEDC = $stmtLastEDC->fetchColumn();
if (!$lastEDC || (new DateTime())->diff(new DateTime($lastEDC))->days > 335) {
    $actesSuggeres[] = ['acte' => 'EDC', 'derniere' => $lastEDC];
}
$stmtLastDTSA = $db->prepare("SELECT TOP 1 date_ordon FROM ORD WHERE id=? AND acte1 LIKE '%DTSA%' ORDER BY date_ordon DESC");
$stmtLastDTSA->execute([$id]); $lastDTSA = $stmtLastDTSA->fetchColumn();
if (!$lastDTSA || (new DateTime())->diff(new DateTime($lastDTSA))->days > 335) {
    $actesSuggeres[] = ['acte' => 'DTSA', 'derniere' => $lastDTSA];
}

// Navigation ordonnances
// Les ordonnances sont triées DESC (plus récente = index 0)
// |◀ = première (plus ancienne) = dernier index
// ▶| = dernière (plus récente)  = index 0
// ◀  = précédente (plus ancienne) = index + 1
// ▶  = suivante (plus récente)    = index - 1
$idxOrd = 0;
foreach ($ordonnances as $i => $o) { if ($o['n_ordon'] == $nOrd) { $idxOrd = $i; break; } }
$ordPremiere = $ordonnances ? $ordonnances[count($ordonnances)-1]['n_ordon'] : 0; // plus ancienne
$ordDerniere = $ordonnances ? $ordonnances[0]['n_ordon'] : 0;                     // plus récente
$ordPrev = ($idxOrd < count($ordonnances)-1) ? $ordonnances[$idxOrd+1]['n_ordon'] : $nOrd; // plus ancienne
$ordNext = ($idxOrd > 0) ? $ordonnances[$idxOrd-1]['n_ordon'] : $nOrd;                     // plus récente

// Médicaments de l'ordonnance courante
$medicaments = [];
if ($nOrd) {
    $stmtMed = $db->prepare("SELECT p.*, pr.PRODUIT FROM PROD p LEFT JOIN PRODUITS pr ON p.produit = pr.NuméroPRODUIT WHERE p.N_ord = ? ORDER BY p.Ordre");
    $stmtMed->execute([$nOrd]);
    $medicaments = $stmtMed->fetchAll();
}

// Dernier examen
$stmtEx = $db->prepare("SELECT TOP 1 * FROM t_examen WHERE NPAT=? ORDER BY DateExam DESC");
$stmtEx->execute([$id]);
$examen = $stmtEx->fetch();

// Examens ECG
$stmtECGs = $db->prepare("SELECT * FROM ecg WHERE [N-PAT]=? ORDER BY [Date ECG] DESC");
$stmtECGs->execute([$id]);
$ecgs = $stmtECGs->fetchAll();
$nECG = (int)($_GET['ecg'] ?? ($ecgs ? $ecgs[0]['N°'] : 0));
$ecgCourant = null; $idxECG = 0;
foreach ($ecgs as $i => $e) { if ($e['N°'] == $nECG) { $ecgCourant = $e; $idxECG = $i; break; } }

// Examens Echo
$stmtEchos = $db->prepare("SELECT * FROM echo WHERE [N-PAT]=? ORDER BY DATEchog DESC");
$stmtEchos->execute([$id]);
$echos = $stmtEchos->fetchAll();
$nEcho = (int)($_GET['echo'] ?? ($echos ? $echos[0]['N°'] : 0));
$echoCourant = null; $idxEcho = 0;
foreach ($echos as $i => $e) { if ($e['N°'] == $nEcho) { $echoCourant = $e; $idxEcho = $i; break; } }

// Factures
$stmtFact = $db->prepare("
    SELECT f.n_facture, f.id, f.date_facture, f.montant,
           ISNULL(SUM(d.prixU),0) AS total,
           ISNULL(SUM(d.Versé),0) AS verse_total,
           ISNULL(SUM(d.dette),0) AS dette_total
    FROM facture f
    LEFT JOIN detail_acte d ON f.n_facture = d.N_fact
    WHERE f.id = ?
    GROUP BY f.n_facture, f.id, f.date_facture, f.montant
    ORDER BY f.date_facture DESC
");
$stmtFact->execute([$id]);
$factures = $stmtFact->fetchAll();
$nFact = (int)($_GET['fact'] ?? ($factures ? $factures[0]['n_facture'] : 0));
$factCourante = null; $idxFact = 0;
foreach ($factures as $i => $f) { if ($f['n_facture'] == $nFact) { $factCourante = $f; $idxFact = $i; break; } }
$factPremiere = $factures ? $factures[count($factures)-1]['n_facture'] : 0;
$factDerniere = $factures ? $factures[0]['n_facture'] : 0;
$factPrev = ($idxFact < count($factures)-1) ? $factures[$idxFact+1]['n_facture'] : $nFact;
$factNext = ($idxFact > 0) ? $factures[$idxFact-1]['n_facture'] : $nFact;

// Détail actes facture courante
$detailActes = [];
if ($nFact) {
    $stmtDA = $db->prepare("SELECT d.*, a.ACTE AS nom_acte FROM detail_acte d LEFT JOIN t_acte_simplifiée a ON d.ACTE = a.n_acte WHERE d.N_fact = ?");
    $stmtDA->execute([$nFact]);
    $detailActes = $stmtDA->fetchAll();
}

// Liste actes pour formulaire inline
$listeActes = $db->query("SELECT n_acte, ACTE, cout FROM t_acte_simplifiée ORDER BY ACTE")->fetchAll();

// Catalogue médicaments
$listeMeds = $db->query("SELECT NuméroPRODUIT, PRODUIT FROM PRODUITS ORDER BY PRODUIT")->fetchAll();

// Listes de référence diagnostics (valeurs distinctes existantes)
$listeDiag1 = $db->query("SELECT DISTINCT diagnostic FROM t_diagnostic WHERE diagnostic IS NOT NULL AND diagnostic != '' ORDER BY diagnostic")->fetchAll(PDO::FETCH_COLUMN);
$listeDiag2 = $db->query("SELECT DISTINCT DicII FROM T_dianstcII WHERE DicII IS NOT NULL AND DicII != '' ORDER BY DicII")->fetchAll(PDO::FETCH_COLUMN);
$listeDiag3 = $db->query("SELECT DISTINCT dic_non_cardio FROM T_id_dic_non_cardio WHERE dic_non_cardio IS NOT NULL AND dic_non_cardio != '' ORDER BY dic_non_cardio")->fetchAll(PDO::FETCH_COLUMN);

// Posologies prédéfinies
$posologies = [
    '1 cp 1 fois par jour','1 cp 1 jour sur deux','1 cp 2 fois par jour',
    '1 cp 3 fois par jour','1 cp 4 fois par jour',
    '1 cp alterné avec 1cp + 1/4 cp',
    '1 gel 1 fois par jour','1 gel 2 fois par jour',
    '1 gel 3 fois par jour','1 gel 4 fois par jour',
    '1 sachet 1 x par jour','1 sachet 3 x par jour',
    '1/2 cp 1 fois par jour','1/2 cp 1 jour sur deux',
    '1/2 cp 2 fois par jour','1/2 cp 3 fois par jour',
    '1/2 cp 4 fois par jour','1/2 cp par jour',
    '1/4 cp 1 fois par jour','1/4 cp 1 jour sur deux',
    '1/4 cp 2 fois par jour','1/4 cp 3 fois par jour',
    '1/4 cp 4 fois par jour',
    '1/4 cp alterné avec 1/2 cp','1/4 cp alterné avec rien',
    '2 cp 1 fois par jour','2 cp 2 fois par jour','2 cp 3 fois par jour',
    '3 cp 1 fois par jour','3/4 cp 1 fois par jour',
    '3/4 cp alterné avec 1 cp','4 gel 1 fois par jour',
];
$durees = ['1 semaine','2 semaines','1 mois','2 mois','3 mois','6 mois'];

// Catalogue actes
$stmtActes = $db->prepare("SELECT n_acte, ACTE FROM t_acte_simplifiée ORDER BY n_acte");
$stmtActes->execute();
$actesCat = $stmtActes->fetchAll();

// ── DONNÉES COLONNE "DERNIÈRE VISITE" ─────────────────────────────────────
// = ordonnance précédente (idxOrdCourante + 1)
$dernVisite = null;
$dernActesFact = [];
if ($ordPrecedente) {
    $dernVisite = $ordPrecedente;
    // Acte(s) réalisé(s) = dernière facture proche de cette date
    $stmtDF = $db->prepare("
        SELECT TOP 1 f.n_facture, f.date_facture
        FROM facture f
        WHERE f.id = ?
        AND f.date_facture >= ?
        AND f.date_facture <= DATEADD(day, 7, ?)
        ORDER BY f.date_facture ASC
    ");
    $dateOrdPrec = $ordPrecedente['date_ordon'] ?? null;
    if ($dateOrdPrec) {
        $stmtDF->execute([$id, $dateOrdPrec, $dateOrdPrec]);
        $factPrec = $stmtDF->fetch();
        if ($factPrec) {
            $stmtActesPrec = $db->prepare("
                SELECT a.ACTE FROM detail_acte d
                LEFT JOIN t_acte_simplifiée a ON d.ACTE = a.n_acte
                WHERE d.N_fact = ?
            ");
            $stmtActesPrec->execute([$factPrec['n_facture']]);
            $dernActesFact = $stmtActesPrec->fetchAll(PDO::FETCH_COLUMN);
        }
    }
}

// ── CALCUL DÉLAI DEPUIS DERNIÈRE VISITE ──────────────────────────────────
$delaiVisite = null;
$delaiCouleur = '#27ae60';
if ($ordPrecedente && !empty($ordPrecedente['date_ordon'])) {
    $tsPrec = strtotime($ordPrecedente['date_ordon']);
    if ($tsPrec && $tsPrec > 86400) {
        $dtPrec = new DateTime(date('Y-m-d', $tsPrec));
        $dtAuj  = new DateTime();
        $diff   = $dtPrec->diff($dtAuj);
        $totalJours = $diff->days;
        $mois = $diff->m + ($diff->y * 12);
        $jours = $diff->d;

        if ($mois > 0) {
            $delaiVisite = $mois . ' mois' . ($jours > 0 ? ' ' . $jours . 'j' : '');
        } else {
            $delaiVisite = $totalJours . ' jours';
        }

        // Couleur selon écart avec RDV prévu
        $rdvPrevu = !empty($ordPrecedente['DATE REDEZ VOUS']) ? strtotime($ordPrecedente['DATE REDEZ VOUS']) : null;
        if ($rdvPrevu) {
            $ecartJours = (int)(($tsPrec + $totalJours * 86400 - $rdvPrevu) / 86400);
            if ($ecartJours <= 14) $delaiCouleur = '#27ae60';       // vert
            elseif ($ecartJours <= 30) $delaiCouleur = '#f39c12';   // orange
            else $delaiCouleur = '#e74c3c';                          // rouge
        }
    }
}

// ── ACTE SUGGÉRÉ POUR COLONNE "ACTUEL VISITE" ────────────────────────────
$acteSugActuel = [];
foreach ($actesSuggeres as $a) {
    $acteSugActuel[] = $a['acte'];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dossier — <?= htmlspecialchars($patient['NOMPRENOM']) ?></title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Arial, sans-serif; background: #f0f4f8; font-size: 13px; }

/* HEADER */
.header { background: #1a4a7a; color: white; padding: 8px 16px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.header a { color: white; text-decoration: none; background: #2e6da4; padding: 5px 12px; border-radius: 4px; font-size: 12px; }
.header h1 { font-size: 15px; flex: 1; }

/* BANDEAU PATIENT */
.patient-bar { background: #000000; color: #FFD700; padding: 6px 16px; display: flex; gap: 20px; flex-wrap: wrap; font-size: 12px; }
.patient-bar .info label { font-size: 10px; opacity: 0.8; text-transform: uppercase; display: block; color: #FFD700; }
.patient-bar .info span { font-weight: bold; color: #FFD700; }

/* LAYOUT PRINCIPAL */
.main { display: grid; grid-template-columns: 200px 1fr 320px; gap: 8px; padding: 8px; }
.col-left { display: flex; flex-direction: column; gap: 8px; }
.col-mid { display: flex; flex-direction: column; gap: 8px; }
.col-right { display: flex; flex-direction: column; gap: 8px; }

/* CARTES */
.card { background: white; border-radius: 6px; padding: 10px; box-shadow: 0 1px 4px rgba(0,0,0,0.1); }
.card-title { color: #1a4a7a; font-size: 12px; font-weight: bold; margin-bottom: 8px; padding-bottom: 4px; border-bottom: 2px solid #e0e0e0; display: flex; justify-content: space-between; align-items: center; }

/* NAVIGATION */
.nav-btns { display: flex; gap: 3px; }
.nav-btn { background: #1a4a7a; color: white; border: none; padding: 3px 7px; border-radius: 3px; cursor: pointer; font-size: 11px; text-decoration: none; }
.nav-btn:hover { background: #2e6da4; }
.nav-btn.danger { background: #e74c3c; }

/* BARRE NAVIGATION ORDONNANCE - en bas, centrée */
.nav-ord-barre {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 3px;
    margin-top: 14px;
    padding-top: 10px;
    border-top: 2px solid #e0e0e0;
}

/* CHAMPS */
.champ { margin-bottom: 6px; }
.champ label { font-size: 10px; color: #888; text-transform: uppercase; font-weight: bold; display: block; margin-bottom: 2px; }
.champ input, .champ select, .champ textarea { width: 100%; padding: 4px 6px; border: 1px solid #ddd; border-radius: 3px; font-size: 12px; }
.champ textarea { resize: vertical; height: auto; overflow: hidden; field-sizing: content; }

/* ACTES BOUTONS */
.actes-btns { display: flex; gap: 4px; flex-wrap: wrap; margin-bottom: 8px; }
.acte-btn { background: #27ae60; color: white; border: none; padding: 4px 8px; border-radius: 3px; cursor: pointer; font-size: 11px; }
.acte-btn:hover { background: #2ecc71; }

/* MEDICAMENTS */
.med-ligne { display: grid; grid-template-columns: 2fr 2fr 1fr 24px; gap: 4px; margin-bottom: 4px; align-items: center; }
.med-ligne select, .med-ligne input { padding: 3px 5px; border: 1px solid #ddd; border-radius: 3px; font-size: 11px; width: 100%; }
.btn-del { background: #e74c3c; color: white; border: none; border-radius: 3px; padding: 3px 6px; cursor: pointer; font-size: 11px; }

/* DELAI RDV */
.delai-btns { display: flex; gap: 4px; flex-wrap: wrap; }
.delai-btn { padding: 4px 10px; border: 1px solid #2e6da4; border-radius: 3px; cursor: pointer; font-size: 11px; background: white; color: #2e6da4; text-decoration: none; display: inline-block; }
.delai-btn.actif { background: #1a4a7a; color: white; border-color: #1a4a7a; }

/* DIAGNOSTICS EDITABLES */
.diag-bloc { display:flex; flex-direction:column; gap:3px; margin-bottom:4px; }
.diag-ligne { display:flex; gap:4px; align-items:center; }
.creneaux-wrap { margin-top: 6px; }
.creneaux-titre { font-size: 10px; font-weight: bold; color: #555; text-transform: uppercase; margin-bottom: 4px; }
.creneaux-grille { display: flex; flex-wrap: wrap; gap: 3px; }
.creneau-btn {
    padding: 3px 7px; border-radius: 3px; border: 2px solid transparent;
    cursor: pointer; font-size: 11px; font-weight: bold; min-width: 48px;
    text-align: center; transition: transform 0.1s;
}
.creneau-btn:hover { transform: scale(1.08); }
.creneau-btn.libre  { background: #27ae60; color: white; border-color: #1e8449; }
.creneau-btn.moyen  { background: #f39c12; color: white; border-color: #d68910; }
.creneau-btn.plein  { background: #e74c3c; color: #fdd; border-color: #c0392b; cursor: not-allowed; opacity: 0.7; }
.creneau-btn.selectionne { border-color: #1a4a7a !important; box-shadow: 0 0 0 3px rgba(26,74,122,0.35); transform: scale(1.1); }
.creneaux-msg { font-size: 11px; color: #e74c3c; margin-top: 4px; font-weight: bold; }
.creneaux-loading { font-size: 11px; color: #888; font-style: italic; margin-top: 4px; }
.jauge-jour { display: flex; align-items: center; gap: 6px; margin-bottom: 6px; font-size: 11px; }
.jauge-bar { flex: 1; height: 8px; background: #e0e0e0; border-radius: 4px; overflow: hidden; }
.jauge-fill { height: 100%; border-radius: 4px; transition: width 0.3s; }
.jauge-fill.ok   { background: #27ae60; }
.jauge-fill.warn { background: #f39c12; }
.jauge-fill.full { background: #e74c3c; }

/* FULL WIDTH */
.row-full { padding: 0 8px 8px; display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px; }
.row-bottom { padding: 0 8px 8px; display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }

/* TENSION */
.ta-val { font-size: 16px; font-weight: bold; }

/* FDR badges */
.fdr-badge { background: #ffe0e0; color: #c0392b; padding: 1px 6px; border-radius: 8px; font-size: 11px; margin: 1px; display: inline-block; }

/* Acte badge */
.acte-badge { background: #e8f5e9; color: #2e7d32; padding: 2px 8px; border-radius: 8px; font-size: 11px; }

/* Boutons délai dans tableau RDV */
.delai-btn-rdv { padding: 3px 8px; border: 1px solid #8e44ad; border-radius: 3px; cursor: pointer; font-size: 11px; background: white; color: #8e44ad; }
.delai-btn-rdv:hover, .delai-btn-rdv.actif { background: #8e44ad; color: white; }

/* TABLEAU RDV 3 colonnes */
.tableau-rdv { width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 10px; border-radius: 6px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,0.15); }
.tableau-rdv th { padding: 7px 6px; text-align: center; font-size: 11px; }
.tableau-rdv td { padding: 5px 6px; border-bottom: 1px solid #e8e8e8; }
.tableau-rdv td:first-child { background: #f0f4f8; font-size: 11px; font-weight: bold; color: #1a4a7a; text-align: right; white-space: nowrap; }
.tableau-rdv tr:last-child td { border-bottom: none; }
.col-visite { background: #e8f8ee; }
.col-rdv-fixe { background: #e8f0fb; }
.col-rdv-futur { background: #f3eafb; }

/* NOUVELLE ORDONNANCE inline */
.no-zone { background: #f0f8ff; border: 2px solid #27ae60; border-radius: 6px; padding: 10px; margin-top: 10px; }
.no-zone-title { color: #27ae60; font-size: 12px; font-weight: bold; margin-bottom: 8px; }

@media (max-width: 900px) {
    .main { grid-template-columns: 1fr; }
    .row-full, .row-bottom { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<!-- HEADER -->
<div class="header">
    <a href="agenda.php">◀ Agenda</a>
    <a href="recherche.php" style="background:#27ae60;">🏠 Home</a>
    <div style="position:relative;display:inline-block;">
        <input type="text" id="rech-patient" placeholder="🔍 Rechercher patient..."
               style="padding:5px 10px;border-radius:4px;border:none;font-size:12px;width:200px;">
        <div id="rech-suggestions" style="position:absolute;top:100%;left:0;width:300px;background:white;border:1px solid #ccc;border-radius:4px;max-height:200px;overflow-y:auto;z-index:1000;display:none;box-shadow:0 4px 12px rgba(0,0,0,0.2);"></div>
    </div>
    <h1>🩺 Dossier médical</h1>
    <div style="display:inline-flex;align-items:center;gap:4px;background:rgba(255,255,255,0.15);border-radius:6px;padding:3px 8px;">
        <a href="dossier.php?id=<?= $first_id ?>" title="Premier patient" style="background:none;padding:2px 5px;font-size:16px;color:white;text-decoration:none;">⏮</a>
        <a href="dossier.php?id=<?= $prev_id ?>" title="Précédent" style="background:none;padding:2px 5px;font-size:16px;color:white;text-decoration:none;">◀</a>
        <span style="color:white;font-size:12px;min-width:70px;text-align:center;"><?= $pos_patient ?> / <?= $total_patients ?></span>
        <a href="dossier.php?id=<?= $next_id ?>" title="Suivant" style="background:none;padding:2px 5px;font-size:16px;color:white;text-decoration:none;">▶</a>
        <a href="dossier.php?id=<?= $last_id ?>" title="Dernier patient" style="background:none;padding:2px 5px;font-size:16px;color:white;text-decoration:none;">⏭</a>
    </div>
    <a href="bilan.php?id=<?= $id ?>">🧪 Bilans</a>
    <a href="logout.php" style="background:#e74c3c;">🚪 Déco</a>
</div>

<!-- BANDEAU PATIENT -->
<div class="patient-bar">
    <div class="info"><label>N°</label><span><?= $id ?></span></div>
    <div class="info"><label>Nom</label><span><?= htmlspecialchars($patient['NOMPRENOM']) ?></span></div>
    <div class="info"><label>Âge</label><span><?= $age ?> ans</span></div>
    <div class="info"><label>DDN</label><span><?= $patient['DDN'] ? date('d/m/Y', strtotime($patient['DDN'])) : '—' ?></span></div>
    <div class="info"><label>CIN</label><span><?= htmlspecialchars($patient['CIN'] ?? '—') ?></span></div>
    <div class="info"><label>Mutuelle</label><span><?= htmlspecialchars($patient['MUTUELLE'] ?? '—') ?></span></div>
</div>

<!-- LAYOUT 3 COLONNES -->
<div class="main">

<!-- ══════════════════════════════════════════════════════
     COLONNE GAUCHE : DOSSIER PATIENT
     ══════════════════════════════════════════════════════ -->
<div class="col-left">
    <div class="card">
        <div class="card-title">👤 Dossier patient
            <span id="dossier_status" style="font-size:10px;color:#27ae60;font-weight:normal;"></span>
        </div>

        <!-- MOTIF DE CONSULTATION — éditable -->
        <div class="champ">
            <label>Motif de consultation</label>
            <textarea id="champ_motif"
                onblur="sauvegarderChamp('MOTIF CONSULTATION', this.value)"
                style="border:1px solid #ddd;border-radius:3px;padding:4px 6px;width:100%;font-size:12px;resize:vertical;min-height:50px;field-sizing:content;"
            ><?= htmlspecialchars($patient['MOTIF CONSULTATION'] ?? '') ?></textarea>
        </div>

        <!-- ANTÉCÉDENTS — éditable -->
        <div class="champ">
            <label>Antécédents</label>
            <textarea id="champ_atcd"
                onblur="sauvegarderChamp('ATCD', this.value)"
                style="border:1px solid #ddd;border-radius:3px;padding:4px 6px;width:100%;font-size:12px;resize:vertical;min-height:50px;field-sizing:content;"
            ><?= htmlspecialchars($patient['ATCD'] ?? '') ?></textarea>
        </div>

        <!-- DIAGNOSTICS — éditables inline avec autocomplétion -->
        <?php
        $diagConfigs = [
            1 => ['label' => 'Diagnostic principal',        'items' => $diagnostics,   'pk' => 'N_dic',            'champ' => 'diagnostic',      'liste' => $listeDiag1],
            2 => ['label' => 'Diagnostic II',               'items' => $diagnosticsII, 'pk' => 'N_DIC_II',         'champ' => 'DicII',           'liste' => $listeDiag2],
            3 => ['label' => 'Diagnostic non cardiologique','items' => $diagnosticsNC, 'pk' => 'N_dic_non_cardio', 'champ' => 'dic_non_cardio',  'liste' => $listeDiag3],
        ];
        foreach ($diagConfigs as $type => $cfg):
        ?>
        <div class="champ">
            <label><?= $cfg['label'] ?></label>
            <div id="diag_<?= $type ?>" class="diag-bloc">
                <?php foreach ($cfg['items'] as $d): ?>
                <div class="diag-ligne" data-pk="<?= $d[$cfg['pk']] ?>">
                    <input type="text" value="<?= htmlspecialchars($d[$cfg['champ']]) ?>"
                        list="datalist_diag_<?= $type ?>"
                        onblur="diagUpdate(<?= $type ?>, <?= $d[$cfg['pk']] ?>, this.value)"
                        style="flex:1;border:1px solid #ddd;border-radius:3px;padding:3px 5px;font-size:12px;">
                    <button type="button" onclick="diagDelete(<?= $type ?>, <?= $d[$cfg['pk']] ?>, <?= $id ?>, this)"
                        style="background:#e74c3c;color:white;border:none;border-radius:3px;padding:2px 6px;cursor:pointer;font-size:11px;flex-shrink:0;">✕</button>
                </div>
                <?php endforeach; ?>
                <?php if (empty($cfg['items'])): ?>
                <div class="diag-vide" style="color:#999;font-size:11px;padding:2px 0;">—</div>
                <?php endif; ?>
            </div>
            <!-- Datalist autocomplétion -->
            <datalist id="datalist_diag_<?= $type ?>">
                <?php foreach ($cfg['liste'] as $opt): ?>
                <option value="<?= htmlspecialchars($opt) ?>">
                <?php endforeach; ?>
            </datalist>
            <!-- Champ ajout nouveau -->
            <div style="display:flex;gap:4px;margin-top:4px;">
                <input type="text" id="new_diag_<?= $type ?>"
                    list="datalist_diag_<?= $type ?>"
                    placeholder="Choisir ou saisir..."
                    style="flex:1;border:1px solid #27ae60;border-radius:3px;padding:3px 6px;font-size:12px;">
                <button type="button" onclick="diagAjouter(<?= $type ?>, <?= $id ?>, <?= htmlspecialchars(json_encode($cfg['liste']), ENT_QUOTES) ?>)"
                    style="background:#27ae60;color:white;border:none;border-radius:3px;padding:2px 10px;cursor:pointer;font-size:11px;">➕</button>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- FACTEURS DE RISQUE -->
        <?php
        $fdrs = [];
        $nomsfdrs = [
            'FDR_Age' => "L'âge", 'FDR_ATCD_IDM_Fam' => 'ATCD IDM famille',
            'FDR_ATCD_AVC_Fam' => 'ATCD AVC', 'FDR_Tabac' => 'Tabagisme',
            'FDR_Diabete' => 'Diabète', 'FDR_HTA' => 'HTA',
            'FDR_LDL_Oui' => 'LDL cholestérol', 'FDR_TG_Oui' => 'Triglycérides',
            'FDR_Obesite' => 'Obésité', 'FDR_Surpoids' => 'Surpoids',
            'FDR_Tour_Taille' => 'Tour de taille', 'FDR_Sedentarite' => 'Sédentarité',
            'FDR_Synd_Metabolique' => 'Synd. métabolique',
            'FDR_Stress_Depression' => 'Stress/Dépression',
            'FDR_Sommeil' => 'Troubles du sommeil', 'FDR_Drogues' => 'Drogues',
        ];
        if ($examen) {
            foreach ($nomsfdrs as $champFDR => $nomFDR) {  // ← renommé pour éviter conflit
                if (!empty($examen[$champFDR])) $fdrs[] = $nomFDR;
            }
        }
        ?>
        <?php if (!empty($fdrs)): ?>
        <div class="champ">
            <label>Facteurs de risque (examen)</label>
            <?php foreach ($fdrs as $f): ?>
                <span class="fdr-badge"><?= $f ?></span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="champ">
            <label>Facteurs de risque</label>
            <?php if (empty($fdrPatient)): ?>
                <span style="color:#999;font-size:11px;">Pas de facteur de risque</span>
            <?php else: ?>
                <div style="display:flex;flex-wrap:wrap;gap:4px;margin-top:4px;">
                <?php foreach ($fdrPatient as $f): ?>
                    <span style="background:#e74c3c;color:white;padding:3px 8px;border-radius:12px;font-size:12px;font-weight:bold;"><?= htmlspecialchars($f) ?></span>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div style="margin-top:6px;">
                <a href="fdr_edit.php?id=<?= $id ?>" style="font-size:11px;color:#2e6da4;">✏️ Modifier</a>
            </div>
        </div>

        <!-- REMARQUE — éditable -->
        <div class="champ">
            <label>Remarque</label>
            <textarea id="champ_remarque"
                onblur="sauvegarderChamp('REMARQUE', this.value)"
                style="border:1px solid #ddd;border-radius:3px;padding:4px 6px;width:100%;font-size:12px;resize:vertical;min-height:40px;field-sizing:content;"
            ><?= htmlspecialchars($patient['REMARQUE'] ?? '') ?></textarea>
        </div>

    </div>
</div><!-- FIN col-left -->

<!-- ══════════════════════════════════════════════════════
     COLONNE MILIEU : ORDONNANCE
     ══════════════════════════════════════════════════════ -->
<div class="col-mid">
    <div class="card">

        <!-- TITRE CARTE ORDONNANCE avec date ordonnance -->
        <div class="card-title">
            📋 Ordonnance
            <?php if ($ordCourante && !empty($ordCourante['date_ordon'])): ?>
            <?php
                $tsOrd = strtotime($ordCourante['date_ordon']);
                $dateOrdAff = ($tsOrd && $tsOrd > 0) ? date('d/m/Y', $tsOrd) : '—';
            ?>
            <span style="font-family:Arial,sans-serif;font-weight:bold;font-size:14px;color:#27ae60;background:#e8f8ee;padding:3px 12px;border-radius:5px;border:1px solid #27ae60;">
                📅 <?= $dateOrdAff ?>
            </span>
            <?php endif; ?>
        </div>

        <?php if ($ordCourante): ?>

        <!-- ═══ VUE ORDONNANCE — TABLEAU 4 COLONNES ═══ -->
        <div id="vue-ordonnance">
        <div id="ord-affichage" style="display:grid;grid-template-columns:1fr 380px;gap:8px;align-items:start;margin-bottom:8px;">

        <!-- ══ COL GAUCHE : TABLEAU 4 COLONNES ══ -->
        <div>
        <?php
        // COL 1 — Dernière visite (ordonnance précédente)
        $dv_dateOrd = '—'; $dv_heure = '—'; $dv_actes = '—';
        if ($ordPrecedente) {
            $ts = strtotime($ordPrecedente['date_ordon'] ?? '');
            $dv_dateOrd = ($ts && $ts > 86400) ? date('d/m/Y', $ts) : '—';
            $dv_heure   = htmlspecialchars($ordPrecedente['HeureRDV'] ?? '—');
            $dv_actes   = !empty($dernActesFact) ? implode(', ', $dernActesFact) : htmlspecialchars($ordPrecedente['acte1'] ?? '—');
        }
        // COL 2 — RDV prévu (données de l'ordonnance précédente)
        $rdvp_date = '—'; $rdvp_heure = '—'; $rdvp_acte = '—';
        if ($ordPrecedente) {
            $ts = !empty($ordPrecedente['DATE REDEZ VOUS']) ? strtotime($ordPrecedente['DATE REDEZ VOUS']) : false;
            $rdvp_date  = ($ts && $ts > 86400) ? date('d/m/Y', $ts) : '—';
            $rdvp_heure = htmlspecialchars($ordPrecedente['HeureRDV'] ?? '—');
            $rdvp_acte  = htmlspecialchars($ordPrecedente['acte1'] ?? '—');
        }
        // COL 4 — RDV prochain
        $rdvFuturVal = '';
        if (!empty($ordCourante['DATE REDEZ VOUS'])) {
            $ts = strtotime($ordCourante['DATE REDEZ VOUS']);
            if ($ts && $ts > 86400) $rdvFuturVal = date('Y-m-d', $ts);
        }
        $acteNouveauRDV = $ordCourante['acte1'] ?? '';
        ?>
        <table class="tableau-rdv">
            <thead>
                <tr>
                    <th style="background:#1a4a7a;color:white;width:70px;"></th>
                    <th style="background:#2e6da4;color:white;font-size:11px;">🏥 Dernière visite</th>
                    <th style="background:#5b7fa6;color:white;font-size:11px;">📅 RDV prévu</th>
                    <th style="background:#27ae60;color:white;font-size:11px;">🩺 Actuel visite<br><span style="font-size:10px;font-weight:normal;"><?= date('d/m/Y') ?></span></th>
                    <th style="background:#8e44ad;color:white;font-size:11px;">📆 RDV prochain</th>
                </tr>
            </thead>
            <tbody>
                <!-- LIGNE DATE -->
                <tr>
                    <td>📅 Date</td>
                    <td class="col-rdv-fixe" style="text-align:center;">
                        <strong style="color:#2e6da4;font-size:13px;"><?= $dv_dateOrd ?></strong>
                    </td>
                    <td style="background:#dce8f7;text-align:center;">
                        <strong style="color:#5b7fa6;font-size:13px;"><?= $rdvp_date ?></strong>
                    </td>
                    <td class="col-visite" style="text-align:center;">
                        <strong style="color:#27ae60;font-size:13px;"><?= date('d/m/Y') ?></strong>
                        <?php if ($delaiVisite): ?>
                        <br><span style="font-size:11px;font-weight:bold;color:<?= $delaiCouleur ?>;background:<?= $delaiCouleur ?>22;padding:1px 6px;border-radius:8px;">⏱ <?= $delaiVisite ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="col-rdv-futur" style="padding:4px;">
                        <input type="hidden" id="rdv_futur"       value="<?= $rdvFuturVal ?>">
                        <input type="hidden" id="heure_rdv_futur" value="<?= htmlspecialchars($ordCourante['HeureRDV'] ?? '') ?>">
                        <!-- Délai rapide -->
                        <div style="display:flex;gap:2px;flex-wrap:wrap;margin-bottom:3px;">
                            <button type="button" onclick="rdvSetDelai(1,0,'rdv')"  class="delai-btn-rdv">1M</button>
                            <button type="button" onclick="rdvSetDelai(3,0,'rdv')"  class="delai-btn-rdv actif">3M</button>
                            <button type="button" onclick="rdvSetDelai(6,0,'rdv')"  class="delai-btn-rdv">6M</button>
                            <button type="button" onclick="rdvSetDelai(0,7,'rdv')"  class="delai-btn-rdv">7J</button>
                            <button type="button" onclick="rdvSetDelai(0,10,'rdv')" class="delai-btn-rdv">10J</button>
                            <button type="button" onclick="rdvSetDelai(0,15,'rdv')" class="delai-btn-rdv">15J</button>
                        </div>
                        <!-- Boutons Report -->
                        <div style="display:flex;gap:2px;margin-bottom:3px;">
                            <button type="button" onclick="reportTraitement(3,<?= $id ?>)"
                                style="background:#e67e22;color:white;border:none;padding:2px 6px;border-radius:3px;cursor:pointer;font-size:10px;font-weight:bold;">📋 Report 3M</button>
                            <button type="button" onclick="reportTraitement(6,<?= $id ?>)"
                                style="background:#c0392b;color:white;border:none;padding:2px 6px;border-radius:3px;cursor:pointer;font-size:10px;font-weight:bold;">📋 Report 6M</button>
                        </div>
                        <input type="date" id="rdv_futur_visible" value="<?= $rdvFuturVal ?>"
                               onchange="rdvDateChange(this.value,'rdv')"
                               style="width:100%;padding:3px 4px;border:1px solid #8e44ad;border-radius:3px;font-size:11px;margin-bottom:4px;">
                        <div class="jauge-jour" id="rdv_jauge" style="display:none;">
                            <span id="rdv_jauge_txt" style="white-space:nowrap;color:#555;font-size:10px;"></span>
                            <div class="jauge-bar"><div class="jauge-fill ok" id="rdv_jauge_fill" style="width:0%"></div></div>
                        </div>
                        <div class="creneaux-wrap">
                            <div class="creneaux-loading" id="rdv_loading" style="display:none;">⏳ Chargement…</div>
                            <div class="creneaux-msg"     id="rdv_msg"     style="display:none;"></div>
                            <div class="creneaux-grille"  id="rdv_grille"></div>
                        </div>
                    </td>
                </tr>
                <!-- LIGNE HEURE -->
                <tr>
                    <td>⏰ Heure</td>
                    <td class="col-rdv-fixe" style="text-align:center;"><strong style="color:#2e6da4;"><?= $dv_heure ?></strong></td>
                    <td style="background:#dce8f7;text-align:center;"><strong style="color:#5b7fa6;"><?= $rdvp_heure ?></strong></td>
                    <td class="col-visite" style="text-align:center;color:#888;font-size:11px;">—</td>
                    <td class="col-rdv-futur" style="text-align:center;padding:4px;">
                        <?php if (!empty($ordCourante['HeureRDV'])): ?>
                        <span style="background:#e8d5f5;color:#8e44ad;padding:2px 8px;border-radius:8px;font-size:12px;font-weight:bold;"><?= htmlspecialchars($ordCourante['HeureRDV']) ?></span>
                        <?php else: ?><span style="color:#aaa;font-size:11px;">à choisir ↑</span><?php endif; ?>
                    </td>
                </tr>
                <!-- LIGNE ACTE -->
                <tr>
                    <td>🏥 Acte</td>
                    <td class="col-rdv-fixe" style="text-align:center;">
                        <span style="background:#dce8f7;color:#1a4a7a;padding:2px 6px;border-radius:8px;font-size:11px;font-weight:bold;"><?= $dv_actes ?></span>
                    </td>
                    <td style="background:#dce8f7;text-align:center;">
                        <span style="background:#b8d0ec;color:#1a4a7a;padding:2px 6px;border-radius:8px;font-size:11px;font-weight:bold;"><?= $rdvp_acte ?></span>
                    </td>
                    <td class="col-visite" style="text-align:center;">
                        <?php foreach ($actesSuggeres as $as): ?>
                        <span style="background:#f39c12;color:white;padding:2px 6px;border-radius:8px;font-size:11px;font-weight:bold;display:inline-block;margin:1px;"><?= $as['acte'] ?></span>
                        <?php endforeach; ?>
                        <?php if (empty($actesSuggeres)): ?><span style="color:#aaa;font-size:11px;">—</span><?php endif; ?>
                    </td>
                    <td class="col-rdv-futur" style="padding:4px;">
                        <input type="text" id="acte_rdv_futur" value="<?= htmlspecialchars($acteNouveauRDV) ?>"
                               oninput="syncActe(this.value,'rdv')"
                               style="width:100%;padding:3px 4px;border:1px solid #8e44ad;border-radius:3px;font-size:11px;text-align:center;margin-bottom:3px;">
                        <div style="display:flex;gap:2px;flex-wrap:wrap;">
                            <?php foreach (['ECG','ECG+EDC','ECG+EDC+DTSA','DTSA','EDC','DVMI','BILAN','CONTROL','DAMI'] as $ba): ?>
                            <button type="button" onclick="setActeRdv('<?= $ba ?>','rdv');"
                                style="background:#8e44ad;color:white;border:none;padding:2px 5px;border-radius:3px;cursor:pointer;font-size:10px;margin-bottom:2px;"><?= $ba ?></button>
                            <?php endforeach; ?>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- DATE ORDONNANCE -->
        <?php
        $tsOrdGauche   = !empty($ordCourante['date_ordon']) ? strtotime($ordCourante['date_ordon']) : false;
        $dateOrdGauche = ($tsOrdGauche && $tsOrdGauche > 0) ? date('d/m/Y', $tsOrdGauche) : '—';
        ?>
        <div style="background:#e8f8ee;border:2px solid #27ae60;border-radius:6px;padding:8px 14px;margin-bottom:8px;display:flex;align-items:center;gap:12px;">
            <span style="font-size:11px;color:#555;text-transform:uppercase;font-weight:bold;">Date ordonnance</span>
            <span style="font-family:Arial,sans-serif;font-weight:bold;font-size:18px;color:#1a4a7a;letter-spacing:1px;"><?= $dateOrdGauche ?></span>
        </div>

        <!-- MÉDICAMENTS -->
        <div class="champ" style="margin-top:4px;">
            <label>💊 Médicaments (<?= count($medicaments) ?>)</label>
            <div style="display:grid;grid-template-columns:2fr 2fr 1fr;gap:4px;margin-bottom:4px;">
                <span style="font-size:10px;color:#888;text-transform:uppercase;">Médicament</span>
                <span style="font-size:10px;color:#888;text-transform:uppercase;">Posologie</span>
                <span style="font-size:10px;color:#888;text-transform:uppercase;">Durée</span>
            </div>
            <?php foreach ($medicaments as $m): ?>
            <div style="display:grid;grid-template-columns:2fr 2fr 1fr;gap:4px;margin-bottom:3px;">
                <input type="text" value="<?= htmlspecialchars($m['PRODUIT'] ?? '') ?>" readonly style="padding:3px 5px;border:1px solid #ddd;border-radius:3px;font-size:11px;background:#f9f9f9;">
                <input type="text" value="<?= htmlspecialchars($m['posologie'] ?? '') ?>" readonly style="padding:3px 5px;border:1px solid #ddd;border-radius:3px;font-size:11px;background:#f9f9f9;">
                <input type="text" value="<?= htmlspecialchars($m['DUREE'] ?? '') ?>" readonly style="padding:3px 5px;border:1px solid #ddd;border-radius:3px;font-size:11px;background:#f9f9f9;">
            </div>
            <?php endforeach; ?>
            <?php if (empty($medicaments)): ?><p style="color:#999;font-size:12px;">Aucun médicament</p><?php endif; ?>
        </div>
        </div><!-- FIN COL GAUCHE -->

        <!-- COL DROITE : FACTURATION -->
        <div>
            <div class="card-title">💰 Facturation</div>
            <div id="fact-affichage">
            <?php if ($factCourante): ?>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-bottom:8px;">
                <div class="champ">
                    <label>N° Facture</label>
                    <input type="text" value="<?= $factCourante['n_facture'] ?>" readonly>
                </div>
                <div class="champ">
                    <label>Date facture</label>
                    <?php
                    $tsF = strtotime($factCourante['date_facture'] ?? '');
                    $dateFactVal = ($tsF && $tsF > 86400) ? date('Y-m-d', $tsF) : '';
                    ?>
                    <input type="date" value="<?= $dateFactVal ?>"
                        onchange="majDateFacture(<?= $nFact ?>, this.value)"
                        style="padding:4px 6px;border:1px solid #ddd;border-radius:3px;font-size:12px;">
                </div>
            </div>
            <table style="width:100%;border-collapse:collapse;font-size:11px;">
                <thead style="background:#1a4a7a;color:white;">
                    <tr>
                        <th style="padding:4px 6px;text-align:left;">Date acte</th>
                        <th style="padding:4px 6px;text-align:left;">Acte</th>
                        <th style="padding:4px 6px;text-align:right;">Prix</th>
                        <th style="padding:4px 6px;text-align:right;">Versé</th>
                        <th style="padding:4px 6px;text-align:right;">Reste</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($detailActes as $da): ?>
                <tr style="border-bottom:1px solid #eee;">
                    <td style="padding:4px 6px;">
                        <input type="date" value="<?= $da['date-H'] ? date('Y-m-d', strtotime($da['date-H'])) : '' ?>"
                        onchange="majDateActe(<?= $da['N_aacte'] ?>, this.value)"
                        style="border:1px solid #ddd;border-radius:3px;padding:2px;font-size:11px;width:110px;">
                    </td>
                    <td style="padding:4px 6px;"><?= htmlspecialchars($da['nom_acte'] ?? 'Acte '.$da['ACTE']) ?></td>
                    <td style="padding:4px 6px;text-align:right;"><?= number_format($da['prixU'], 0, ',', ' ') ?></td>
                    <td style="padding:4px 6px;text-align:right;"><?= number_format($da['Versé'], 0, ',', ' ') ?></td>
                    <td style="padding:4px 6px;text-align:right;"><?= number_format($da['dette'], 0, ',', ' ') ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot style="background:#f0f4f8;font-weight:bold;">
                    <tr>
                        <td style="padding:4px 6px;">Total</td>
                        <td></td>
                        <td style="padding:4px 6px;text-align:right;"><?= number_format($factCourante['total'], 0, ',', ' ') ?> DH</td>
                        <td style="padding:4px 6px;text-align:right;"><?= number_format($factCourante['verse_total'], 0, ',', ' ') ?> DH</td>
                        <td style="padding:4px 6px;text-align:right;"><?= number_format($factCourante['dette_total'], 0, ',', ' ') ?> DH</td>
                    </tr>
                </tfoot>
            </table>

            <!-- ═══ NAVIGATION FACTURE — ordre : |◀ ◀ X/N ▶ ▶| ✚ ═══ -->
            <div style="display:flex;justify-content:center;gap:4px;margin-top:8px;">
                <a href="?id=<?= $id ?>&fact=<?= $factPremiere ?>" class="nav-btn" title="Première">|◀</a>
                <a href="?id=<?= $id ?>&fact=<?= $factPrev ?>" class="nav-btn" title="Précédente">◀</a>
                <span style="font-size:11px;color:#1a4a7a;font-weight:bold;padding:3px 6px;white-space:nowrap;"><?= ($idxFact+1) ?> / <?= count($factures) ?></span>
                <a href="?id=<?= $id ?>&fact=<?= $factNext ?>" class="nav-btn" title="Suivante">▶</a>
                <a href="?id=<?= $id ?>&fact=<?= $factDerniere ?>" class="nav-btn" title="Dernière">▶|</a>
                <button type="button" onclick="toggleNouvelleFacture()" class="nav-btn" style="background:#27ae60;" title="Nouvelle facture">✚</button>
            </div>
            <?php else: ?>
                <p style="color:#999;font-size:12px;">Aucune facture</p>
                <div style="display:flex;justify-content:center;margin-top:8px;">
                    <button type="button" onclick="toggleNouvelleFacture()" class="nav-btn" style="background:#27ae60;">✚ Nouvelle facture</button>
                </div>
            <?php endif; ?>
            </div>

            <!-- FORMULAIRE NOUVELLE FACTURE INLINE -->
            <div id="formNouvelleFacture" style="display:none;margin-top:10px;border-top:2px solid #1a4a7a;padding-top:10px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                    <strong style="color:#1a4a7a;font-size:12px;">Nouvelle facture</strong>
                    <button type="button" onclick="toggleNouvelleFacture()" style="background:none;border:none;cursor:pointer;color:#999;font-size:14px;">✕</button>
                </div>
                <div style="margin-bottom:8px;">
                    <label style="font-size:11px;font-weight:600;">Date facture :</label>
                    <input type="date" id="nf_date" value="<?= date('Y-m-d') ?>" style="margin-left:8px;border:1px solid #cdd5de;border-radius:3px;padding:3px 6px;font-size:12px;">
                </div>
                <table style="width:100%;border-collapse:collapse;font-size:11px;">
                    <thead style="background:#1a4a7a;color:white;">
                        <tr>
                            <th style="padding:4px 6px;text-align:left;">Date acte</th>
                            <th style="padding:4px 6px;text-align:left;">Acte</th>
                            <th style="padding:4px 6px;text-align:right;">Prix</th>
                            <th style="padding:4px 6px;text-align:right;">Versé</th>
                            <th style="padding:4px 6px;text-align:right;">Reste</th>
                            <th style="padding:4px 6px;"></th>
                        </tr>
                    </thead>
                    <tbody id="nf_lignes"></tbody>
                    <tfoot>
                        <tr style="background:#f0f4f8;font-weight:bold;font-size:11px;">
                            <td colspan="2" style="padding:4px 6px;">Total</td>
                            <td style="padding:4px 6px;text-align:right;" id="nf_totalPrix">0 DH</td>
                            <td style="padding:4px 6px;text-align:right;" id="nf_totalVerse">0 DH</td>
                            <td style="padding:4px 6px;text-align:right;color:#c0392b;" id="nf_totalDette">0 DH</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
                <div style="display:flex;gap:8px;margin-top:8px;">
                    <button type="button" onclick="nfAjouterLigne()" style="background:#2ecc71;color:white;border:none;border-radius:3px;padding:4px 10px;cursor:pointer;font-size:11px;">✚ Acte</button>
                    <button type="button" onclick="nfEnregistrer(<?= $id ?>)" style="background:#1a4a7a;color:white;border:none;border-radius:3px;padding:4px 12px;cursor:pointer;font-size:11px;font-weight:600;">💾 Enregistrer</button>
                    <span id="nf_msg" style="font-size:11px;color:#27ae60;align-self:center;"></span>
                </div>
            </div>

            <!-- CERTIFICAT MÉDICAL -->
            <div style="margin-top:12px;border-top:2px solid #e0e0e0;padding-top:10px;">
                <div style="font-size:12px;font-weight:bold;color:#1a4a7a;margin-bottom:8px;">📄 Certificat médical</div>
                <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;font-size:12px;">
                    <span>du</span>
                    <input type="date" id="cert_debut" style="border:1px solid #ddd;border-radius:3px;padding:3px 6px;font-size:12px;" onchange="calcNbrJ()">
                    <span>au</span>
                    <input type="date" id="cert_fin" style="border:1px solid #ddd;border-radius:3px;padding:3px 6px;font-size:12px;" onchange="calcNbrJ()">
                    <span>Nbr J</span>
                    <input type="number" id="cert_nbrj" style="width:55px;border:1px solid #ddd;border-radius:3px;padding:3px 6px;font-size:12px;text-align:center;" readonly>
                    <button type="button" onclick="imprimerCertificat()" style="background:#1a4a7a;color:white;border:none;border-radius:3px;padding:4px 10px;cursor:pointer;font-size:11px;">🖨️ Imprimer</button>
                </div>
            </div>

            <!-- DATE FACTURE VISIBLE EN GRAS -->
            <?php if ($factCourante): ?>
            <?php
                $tsFactAff = strtotime($factCourante['date_facture'] ?? '');
                $dateFactAff = ($tsFactAff && $tsFactAff > 86400) ? date('d/m/Y', $tsFactAff) : '—';
            ?>
            <div style="margin-top:10px;border-top:2px solid #1a4a7a;padding-top:10px;display:flex;align-items:center;gap:10px;">
                <span style="font-size:11px;color:#555;text-transform:uppercase;font-weight:bold;">Date facture</span>
                <span style="font-family:Arial,sans-serif;font-weight:bold;font-size:18px;color:#1a4a7a;letter-spacing:1px;"><?= $dateFactAff ?></span>
            </div>
            <?php endif; ?>

            <!-- ACTES SUGGERES (déplacés ici) -->
            <?php if (!empty($actesSuggeres)): ?>
            <div style="background:#fff3cd;border-left:4px solid #f39c12;padding:8px;border-radius:4px;margin-top:10px;">
                <div style="font-size:11px;font-weight:bold;color:#856404;margin-bottom:6px;">⚠️ Actes suggérés</div>
                <div style="display:flex;gap:4px;flex-wrap:wrap;">
                    <?php foreach ($actesSuggeres as $a): ?>
                    <span style="background:#f39c12;color:white;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:bold;">
                        <?= $a['acte'] ?>
                        <?php if ($a['derniere']): ?>
                            <span style="font-size:10px;opacity:0.85;">(<?= date('d/m/Y', strtotime($a['derniere'])) ?>)</span>
                        <?php else: ?>
                            <span style="font-size:10px;opacity:0.85;">(jamais)</span>
                        <?php endif; ?>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

        </div><!-- FIN COL DROITE -->

        </div><!-- FIN GRID RDV + FACTURATION -->

        <!-- ═══════════════════════════════════════════════
             NAVIGATION ORDONNANCE — en bas, centrée
             Ordre : |◀  ◀  X/N  ▶  ▶|  ✚
             ═══════════════════════════════════════════════ -->
        <div class="nav-ord-barre">
            <a href="?id=<?= $id ?>&ord=<?= $ordPremiere ?>" class="nav-btn" title="Première ordonnance (plus ancienne)">|◀</a>
            <a href="?id=<?= $id ?>&ord=<?= $ordPrev ?>" class="nav-btn" title="Ordonnance précédente">◀</a>
            <span style="font-size:12px;color:#1a4a7a;font-weight:bold;padding:3px 10px;white-space:nowrap;background:#f0f4f8;border-radius:4px;border:1px solid #dde3ea;"><?= (count($ordonnances) - $idxOrd) ?> / <?= count($ordonnances) ?></span>
            <a href="?id=<?= $id ?>&ord=<?= $ordNext ?>" class="nav-btn" title="Ordonnance suivante">▶</a>
            <a href="?id=<?= $id ?>&ord=<?= $ordDerniere ?>" class="nav-btn" title="Dernière ordonnance (plus récente)">▶|</a>
            <button type="button" onclick="afficherNouvelleOrdonnance()" class="nav-btn" style="background:#27ae60;" title="Nouvelle ordonnance">✚</button>
        </div>

        </div><!-- FIN vue-ordonnance -->

        <?php else: ?>
            <p style="color:#999;font-size:12px;">Aucune ordonnance</p>
            <!-- Navigation même si aucune ordonnance, pour pouvoir en créer une -->
            <div class="nav-ord-barre">
                <a href="?id=<?= $id ?>&ord=<?= $ordPremiere ?>" class="nav-btn">|◀</a>
                <a href="?id=<?= $id ?>&ord=<?= $ordPrev ?>" class="nav-btn">◀</a>
                <span style="font-size:12px;color:#1a4a7a;font-weight:bold;padding:3px 10px;white-space:nowrap;background:#f0f4f8;border-radius:4px;border:1px solid #dde3ea;">0 / 0</span>
                <a href="?id=<?= $id ?>&ord=<?= $ordNext ?>" class="nav-btn">▶</a>
                <a href="?id=<?= $id ?>&ord=<?= $ordDerniere ?>" class="nav-btn">▶|</a>
                <button type="button" onclick="afficherNouvelleOrdonnance()" class="nav-btn" style="background:#27ae60;">✚</button>
            </div>
        <?php endif; ?>

        <!-- ═══════════════════════════════════════════════════════
             FORMULAIRE NOUVELLE ORDONNANCE
             Caché par défaut — affiché quand on clique ✚
             ═══════════════════════════════════════════════════════ -->
        <div id="form-nouvelle-ordonnance" style="display:none;">

            <div style="background:#e8f8ee;border:2px solid #27ae60;border-radius:6px;padding:10px;margin-bottom:8px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                    <strong style="color:#27ae60;font-size:13px;">✚ Nouvelle ordonnance</strong>
                    <button type="button" onclick="masquerNouvelleOrdonnance()" style="background:#e74c3c;color:white;border:none;border-radius:3px;padding:3px 8px;cursor:pointer;font-size:12px;">✕ Annuler</button>
                </div>

                <!-- LIGNE 1 : DATE ORDONNANCE + ACTE -->
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px;">
                    <div>
                        <label style="font-size:10px;color:#555;font-weight:bold;display:block;margin-bottom:2px;">DATE ORDONNANCE</label>
                        <input type="date" id="no_date" value="<?= date('Y-m-d') ?>" style="width:100%;border:1px solid #cdd5de;border-radius:3px;padding:4px 6px;font-size:12px;">
                    </div>
                    <div>
                        <label style="font-size:10px;color:#555;font-weight:bold;display:block;margin-bottom:2px;">ACTE</label>
                        <input type="text" id="no_acte" placeholder="ECG, EDC..."
                               oninput="syncActe(this.value,'no')"
                               style="width:100%;border:1px solid #cdd5de;border-radius:3px;padding:4px 6px;font-size:12px;">
                        <div style="display:flex;gap:3px;flex-wrap:wrap;margin-top:4px;">
                            <?php foreach (['ECG','EDC','ECG+EDC','DTSA','ECG+DTSA','CONTROL','DVMI','BILAN'] as $ba): ?>
                            <button type="button" onclick="setActeRdv('<?= $ba ?>','no');" style="background:#8e44ad;color:white;border:none;padding:2px 6px;border-radius:3px;cursor:pointer;font-size:10px;"><?= $ba ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- LIGNE 2 : DATE RDV + CRÉNEAUX (pleine largeur) -->
                <div style="margin-bottom:8px;">
                    <label style="font-size:10px;color:#555;font-weight:bold;display:block;margin-bottom:4px;">📅 DATE &amp; HEURE RDV</label>

                    <!-- Champs cachés pour la soumission -->
                    <input type="hidden" id="no_rdv"   value="">
                    <input type="hidden" id="no_heure" value="">

                    <!-- Délai rapide -->
                    <div style="display:flex;gap:3px;flex-wrap:wrap;margin-bottom:5px;">
                        <button type="button" onclick="rdvSetDelai(1,0,'no')"  style="background:#2e6da4;color:white;border:none;padding:2px 7px;border-radius:3px;cursor:pointer;font-size:11px;">1M</button>
                        <button type="button" onclick="rdvSetDelai(3,0,'no')"  style="background:#1a4a7a;color:white;border:none;padding:2px 7px;border-radius:3px;cursor:pointer;font-size:11px;">3M</button>
                        <button type="button" onclick="rdvSetDelai(6,0,'no')"  style="background:#1a4a7a;color:white;border:none;padding:2px 7px;border-radius:3px;cursor:pointer;font-size:11px;">6M</button>
                        <button type="button" onclick="rdvSetDelai(0,7,'no')"  style="background:#27ae60;color:white;border:none;padding:2px 7px;border-radius:3px;cursor:pointer;font-size:11px;">7J</button>
                        <button type="button" onclick="rdvSetDelai(0,15,'no')" style="background:#27ae60;color:white;border:none;padding:2px 7px;border-radius:3px;cursor:pointer;font-size:11px;">15J</button>
                        <button type="button" onclick="rdvSetDelai(0,21,'no')" style="background:#27ae60;color:white;border:none;padding:2px 7px;border-radius:3px;cursor:pointer;font-size:11px;">21J</button>
                    </div>

                    <!-- Saisie manuelle date -->
                    <input type="date" id="no_rdv_visible"
                           onchange="rdvDateChange(this.value,'no')"
                           style="width:100%;border:1px solid #cdd5de;border-radius:3px;padding:4px 6px;font-size:12px;margin-bottom:5px;">

                    <!-- Jauge jour -->
                    <div class="jauge-jour" id="no_jauge" style="display:none;">
                        <span id="no_jauge_txt" style="white-space:nowrap;color:#555;"></span>
                        <div class="jauge-bar"><div class="jauge-fill ok" id="no_jauge_fill" style="width:0%"></div></div>
                    </div>

                    <!-- Grille créneaux -->
                    <div class="creneaux-wrap">
                        <div class="creneaux-loading" id="no_loading" style="display:none;">⏳ Chargement…</div>
                        <div class="creneaux-msg"     id="no_msg_rdv" style="display:none;"></div>
                        <div class="creneaux-grille"  id="no_grille"></div>
                    </div>
                </div>

                <!-- TABLEAU MEDICAMENTS -->
                <div style="font-size:11px;font-weight:bold;color:#1a4a7a;margin-bottom:6px;">💊 Médicaments :</div>
                <table style="width:100%;border-collapse:collapse;font-size:11px;">
                    <thead style="background:#1a4a7a;color:white;">
                        <tr>
                            <th style="padding:5px 6px;text-align:left;">Médicament</th>
                            <th style="padding:5px 6px;text-align:left;">Posologie</th>
                            <th style="padding:5px 6px;text-align:left;">Durée</th>
                            <th style="padding:5px 6px;width:30px;"></th>
                        </tr>
                    </thead>
                    <tbody id="no_lignes"></tbody>
                </table>

                <!-- BOUTONS -->
                <div style="display:flex;gap:8px;margin-top:10px;align-items:center;">
                    <button type="button" onclick="noAjouterLigne()" style="background:#2ecc71;color:white;border:none;border-radius:3px;padding:5px 12px;cursor:pointer;font-size:12px;">✚ Médicament</button>
                    <button type="button" onclick="noEnregistrer(<?= $id ?>)" style="background:#1a4a7a;color:white;border:none;border-radius:3px;padding:5px 14px;cursor:pointer;font-size:12px;font-weight:600;">💾 Enregistrer</button>
                    <span id="no_msg" style="font-size:11px;color:#27ae60;"></span>
                </div>
            </div>

        </div><!-- FIN form-nouvelle-ordonnance -->

    </div><!-- FIN card ordonnance -->
</div><!-- FIN col-mid -->

<!-- ══════════════════════════════════════════════════════
     COLONNE DROITE : EXAMEN CLINIQUE
     ══════════════════════════════════════════════════════ -->
<div class="col-right">
    <div class="card">
        <div class="card-title">🩺 Examen clinique</div>
        <?php if ($examen): ?>
        <div style="text-align:center;margin-bottom:8px;font-size:11px;color:#888;">
            <?= $examen['DateExam'] ? date('d/m/Y', strtotime($examen['DateExam'])) : '—' ?>
        </div>
        <?php
        $tas = (int)($examen['TAS'] ?? 0);
        $tad = (int)($examen['TAD'] ?? 0);
        $coulTA = '#333';
        if ($tas >= 140 || $tad >= 90) $coulTA = '#e74c3c';
        elseif ($tas >= 130 || $tad >= 80) $coulTA = '#f39c12';
        elseif ($tas > 0) $coulTA = '#27ae60';
        ?>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;">
            <div class="champ">
                <label>TAS/TAD</label>
                <span class="ta-val" style="color:<?= $coulTA ?>"><?= ($tas && $tad) ? $tas.'/'.$tad : '—' ?></span>
            </div>
            <div class="champ"><label>FC</label><span><?= htmlspecialchars($examen['FC'] ?? '—') ?></span></div>
            <div class="champ"><label>Poids</label><span><?= htmlspecialchars($examen['POIDS'] ?? '—') ?> kg</span></div>
            <div class="champ"><label>Taille</label><span><?= htmlspecialchars($examen['TAILLE'] ?? '—') ?> cm</span></div>
        </div>
        <?php else: ?>
            <p style="color:#999;font-size:12px;">Aucun examen enregistré</p>
        <?php endif; ?>
    </div>
</div><!-- FIN col-right -->

</div><!-- FIN .main -->

<!-- ══════════════════════════════════════════════════════
     BAS DE PAGE : ECG + ECHO
     ══════════════════════════════════════════════════════ -->
<div class="row-bottom">

    <!-- ECG -->
    <div class="card">
        <div class="card-title">
            ⚡ ECG
            <div class="nav-btns">
                <a href="?id=<?= $id ?>&ecg=<?= $ecgs ? $ecgs[count($ecgs)-1]['N°'] : 0 ?>" class="nav-btn">|◀</a>
                <a href="?id=<?= $id ?>&ecg=<?= $ecgs && $idxECG < count($ecgs)-1 ? $ecgs[$idxECG+1]['N°'] : $nECG ?>" class="nav-btn">◀</a>
                <span style="font-size:11px;color:#1a4a7a;font-weight:bold;padding:0 4px;white-space:nowrap;"><?= count($ecgs) ? ($idxECG+1).' / '.count($ecgs) : '0' ?></span>
                <a href="?id=<?= $id ?>&ecg=<?= $ecgs && $idxECG > 0 ? $ecgs[$idxECG-1]['N°'] : $nECG ?>" class="nav-btn">▶</a>
                <a href="?id=<?= $id ?>&ecg=<?= $ecgs ? $ecgs[0]['N°'] : 0 ?>" class="nav-btn">▶|</a>
                <a href="nouveau_ecg.php?id=<?= $id ?>" class="nav-btn" style="background:#27ae60;" title="Nouvel ECG">✚</a>
            </div>
        </div>
        <?php if ($ecgCourant): ?>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;">
            <div class="champ"><label>Date ECG</label><input type="text" value="<?= $ecgCourant['Date ECG'] ? date('d/m/Y', strtotime($ecgCourant['Date ECG'])) : '—' ?>" readonly></div>
            <div class="champ"><label>Fréquence</label><input type="text" value="<?= htmlspecialchars($ecgCourant['FREQUENCE'] ?? '') ?>" readonly></div>
            <div class="champ"><label>Trouble de rythme</label><input type="text" value="<?= htmlspecialchars($ecgCourant['trouble de rythme'] ?? '') ?>" readonly></div>
            <div class="champ"><label>Rythme supra vent.</label><input type="text" value="<?= htmlspecialchars($ecgCourant['RYTHME SUPRA VENTRICULAIRE'] ?? '') ?>" readonly></div>
            <div class="champ"><label>Segment ST</label><input type="text" value="<?= htmlspecialchars($ecgCourant['SEGMENT ST'] ?? '') ?>" readonly></div>
            <div class="champ"><label>Repolarisation</label><input type="text" value="<?= htmlspecialchars($ecgCourant['LA REPOLARISATION'] ?? '') ?>" readonly></div>
            <div class="champ"><label>IDM</label><input type="text" value="<?= htmlspecialchars($ecgCourant['IDM'] ?? '') ?>" readonly></div>
            <div class="champ"><label>C/C</label><input type="text" value="<?= htmlspecialchars($ecgCourant['C/C'] ?? '') ?>" readonly></div>
        </div>
        <?php else: ?>
            <p style="color:#999;font-size:12px;">Aucun ECG enregistré</p>
        <?php endif; ?>
    </div>

    <!-- ECHO DOPPLER -->
    <div class="card">
        <div class="card-title">
            🫀 Echo-Doppler
            <div class="nav-btns">
                <a href="?id=<?= $id ?>&echo=<?= $echos ? $echos[count($echos)-1]['N°'] : 0 ?>" class="nav-btn">|◀</a>
                <a href="?id=<?= $id ?>&echo=<?= $echos && $idxEcho < count($echos)-1 ? $echos[$idxEcho+1]['N°'] : $nEcho ?>" class="nav-btn">◀</a>
                <span style="font-size:11px;color:#1a4a7a;font-weight:bold;padding:0 4px;white-space:nowrap;"><?= count($echos) ? ($idxEcho+1).' / '.count($echos) : '0' ?></span>
                <a href="?id=<?= $id ?>&echo=<?= $echos && $idxEcho > 0 ? $echos[$idxEcho-1]['N°'] : $nEcho ?>" class="nav-btn">▶</a>
                <a href="?id=<?= $id ?>&echo=<?= $echos ? $echos[0]['N°'] : 0 ?>" class="nav-btn">▶|</a>
                <a href="nouveau_echo.php?id=<?= $id ?>" class="nav-btn" style="background:#27ae60;" title="Nouvel Echo">✚</a>
            </div>
        </div>
        <?php if ($echoCourant): ?>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;">
            <div class="champ"><label>Date Echo</label><input type="text" value="<?= $echoCourant['DATEchog'] ? date('d/m/Y', strtotime($echoCourant['DATEchog'])) : '—' ?>" readonly></div>
            <div class="champ"><label>FEVG</label><input type="text" value="<?= htmlspecialchars($echoCourant['FEVG'] ?? '') ?>" readonly></div>
            <div class="champ"><label>DTD-VG</label><input type="text" value="<?= htmlspecialchars($echoCourant['DTD-VG'] ?? '') ?>" readonly></div>
            <div class="champ"><label>S,OG</label><input type="text" value="<?= htmlspecialchars($echoCourant['S,OG'] ?? '') ?>" readonly></div>
            <div class="champ"><label>Cinétique</label><input type="text" value="<?= htmlspecialchars($echoCourant['CINETIQUE'] ?? '') ?>" readonly></div>
            <div class="champ"><label>AO ASC</label><input type="text" value="<?= htmlspecialchars($echoCourant['AO ASC,'] ?? '') ?>" readonly></div>
            <div class="champ" style="grid-column:1/-1;"><label>Doppler</label><textarea readonly style="min-height:30px;field-sizing:content;"><?= htmlspecialchars($echoCourant['DOPPLER'] ?? '') ?></textarea></div>
            <div class="champ" style="grid-column:1/-1;"><label>DTSA</label><textarea readonly style="min-height:30px;field-sizing:content;"><?= htmlspecialchars($echoCourant['DOPPLER DES TRONCS SUPRA AORTIQUES'] ?? '') ?></textarea></div>
        </div>
        <?php else: ?>
            <p style="color:#999;font-size:12px;">Aucun Echo enregistré</p>
        <?php endif; ?>
    </div>

</div><!-- FIN row-bottom -->

<script>
// ── Recherche patient ──
document.getElementById('rech-patient').addEventListener('input', function() {
    const val = this.value.trim();
    const sugg = document.getElementById('rech-suggestions');
    if (val.length < 2) { sugg.style.display = 'none'; return; }
    fetch('ajax_recherche.php?q=' + encodeURIComponent(val))
        .then(r => r.json())
        .then(data => {
            sugg.innerHTML = '';
            if (!data.length) { sugg.style.display = 'none'; return; }
            data.forEach(p => {
                const d = document.createElement('div');
                d.style.cssText = 'padding:8px 12px;cursor:pointer;border-bottom:1px solid #eee;font-size:12px;';
                d.textContent = p.nom + ' — N°' + p.id;
                d.onmouseenter = () => d.style.background = '#f0f4f8';
                d.onmouseleave = () => d.style.background = '';
                d.onclick = () => window.location.href = 'dossier.php?id=' + p.id;
                sugg.appendChild(d);
            });
            sugg.style.display = 'block';
        });
});
document.addEventListener('click', e => {
    if (!e.target.closest('#rech-patient') && !e.target.closest('#rech-suggestions')) {
        document.getElementById('rech-suggestions').style.display = 'none';
    }
});

// ── Diagnostics éditables inline ─────────────────────────────────────────
function diagUpdate(type, nDic, valeur) {
    fetch('ajax_maj_diagnostic.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'update', type, n_dic: nDic, valeur })
    });
}

function diagDelete(type, nDic, patId, btn) {
    if (!confirm('Supprimer ce diagnostic ?')) return;
    fetch('ajax_maj_diagnostic.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'delete', type, n_dic: nDic, id: patId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) btn.closest('.diag-ligne').remove();
        else alert('❌ ' + data.error);
    });
}

function diagAjouter(type, patId, liste) {
    const input = document.getElementById('new_diag_' + type);
    const valeur = input.value.trim();
    if (!valeur) return;

    // Vérifier si déjà assigné à ce patient
    const bloc = document.getElementById('diag_' + type);
    const dejaDans = Array.from(bloc.querySelectorAll('input[type=text]'))
        .some(inp => inp.value.trim().toLowerCase() === valeur.toLowerCase());
    if (dejaDans) {
        alert('⚠️ Ce diagnostic est déjà dans la liste de ce patient.');
        input.value = '';
        return;
    }

    // Vérifier si le diagnostic existe dans le référentiel
    const existe = liste.some(d => d.toLowerCase() === valeur.toLowerCase());
    if (!existe) {
        if (!confirm(`"${valeur}" n'existe pas dans la liste.\nVoulez-vous l'ajouter comme nouveau diagnostic ?`)) {
            return;
        }
    }

    fetch('ajax_maj_diagnostic.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'add', type, id: patId, valeur })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const bloc = document.getElementById('diag_' + type);
            // Supprimer le "—" s'il existe
            const vide = bloc.querySelector('.diag-vide');
            if (vide) vide.remove();
            // Ajouter la nouvelle ligne
            const div = document.createElement('div');
            div.className = 'diag-ligne';
            div.dataset.pk = data.n_dic;
            div.innerHTML = `
                <input type="text" value="${valeur.replace(/"/g, '&quot;')}"
                    list="datalist_diag_${type}"
                    onblur="diagUpdate(${type}, ${data.n_dic}, this.value)"
                    style="flex:1;border:1px solid #ddd;border-radius:3px;padding:3px 5px;font-size:12px;">
                <button type="button" onclick="diagDelete(${type}, ${data.n_dic}, ${patId}, this)"
                    style="background:#e74c3c;color:white;border:none;border-radius:3px;padding:2px 6px;cursor:pointer;font-size:11px;flex-shrink:0;">✕</button>`;
            bloc.appendChild(div);
            input.value = '';
            // Si nouveau diagnostic, l'ajouter au datalist
            if (!existe) {
                const dl = document.getElementById('datalist_diag_' + type);
                if (dl) {
                    const opt = document.createElement('option');
                    opt.value = valeur;
                    dl.appendChild(opt);
                }
            }
        } else {
            alert('❌ ' + data.error);
        }
    });
}

// ── Sauvegarde automatique dossier patient ────────────────────────────────
function sauvegarderChamp(champ, valeur) {
    const statusEl = document.getElementById('dossier_status');
    if (statusEl) { statusEl.textContent = '⏳ Enregistrement…'; statusEl.style.color = '#888'; }

    fetch('ajax_maj_dossier.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ id: <?= $id ?>, champ: champ, valeur: valeur })
    })
    .then(r => r.json())
    .then(data => {
        if (statusEl) {
            if (data.success) {
                statusEl.textContent = '✅ Enregistré';
                statusEl.style.color = '#27ae60';
                setTimeout(() => { statusEl.textContent = ''; }, 2000);
            } else {
                statusEl.textContent = '❌ Erreur';
                statusEl.style.color = '#e74c3c';
            }
        }
    })
    .catch(() => {
        if (statusEl) { statusEl.textContent = '❌ Erreur réseau'; statusEl.style.color = '#e74c3c'; }
    });
}
function majDateFacture(nFact, val) {
    fetch('ajax_maj_facture.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({n_facture: nFact, date_facture: val})
    });
}

// ── Mise à jour date acte ──
function majDateActe(nAacte, val) {
    fetch('ajax_maj_acte.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({n_aacte: nAacte, date_H: val})
    });
}

// ── Certificat médical ──
function calcNbrJ() {
    const d1 = document.getElementById('cert_debut').value;
    const d2 = document.getElementById('cert_fin').value;
    if (d1 && d2) {
        const diff = Math.round((new Date(d2) - new Date(d1)) / 86400000);
        document.getElementById('cert_nbrj').value = diff >= 0 ? diff : 0;
    }
}
function imprimerCertificat() {
    alert('Impression certificat — à implémenter');
}

// ── Report de traitement (même ordonnance + nouvelle facture ECG) ──────────
function reportTraitement(mois, patientId) {
    if (!confirm(`Confirmer le report du traitement dans ${mois} mois ?\nCela créera une nouvelle ordonnance identique + une facture ECG (300 DH) à la date du jour.`)) return;

    const msgEl = document.getElementById('no_msg') || document.createElement('span');
    msgEl.textContent = '⏳ Report en cours…';

    fetch('ajax_report_traitement.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ id: patientId, mois: mois })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.href = `dossier.php?id=${patientId}&ord=${data.n_ordon}`;
        } else {
            alert('❌ ' + data.error);
        }
    })
    .catch(() => alert('❌ Erreur réseau'));
}

// ══════════════════════════════════════════════════════════════════
// SYSTÈME CRÉNEAUX — commun aux deux formulaires RDV
//
// prefixe = 'rdv'  → formulaire tableau violet (RDV existant)
// prefixe = 'no'   → formulaire ✚ nouvelle ordonnance (vert)
//
// Champs utilisés :
//   rdv : #rdv_futur (hidden date), #rdv_futur_visible (input date)
//         #heure_rdv_futur (hidden heure), #rdv_grille, #rdv_loading, #rdv_msg
//   no  : #no_rdv (hidden date), #no_rdv_visible (input date)
//         #no_heure (hidden heure), #no_grille, #no_loading, #no_msg_rdv
//
// Acte  : #acte_rdv_futur (violet) ↔ #no_acte (vert) — synchronisés
// ══════════════════════════════════════════════════════════════════

// IDs des éléments selon le préfixe
function rdvIds(p) {
    if (p === 'rdv') return {
        dateH:     'rdv_futur',
        dateV:     'rdv_futur_visible',
        heureH:    'heure_rdv_futur',
        grille:    'rdv_grille',
        loading:   'rdv_loading',
        msg:       'rdv_msg',
        jauge:     'rdv_jauge',
        jaugeTxt:  'rdv_jauge_txt',
        jaugeFill: 'rdv_jauge_fill',
        acte:      'acte_rdv_futur',
    };
    return {
        dateH:     'no_rdv',
        dateV:     'no_rdv_visible',
        heureH:    'no_heure',
        grille:    'no_grille',
        loading:   'no_loading',
        msg:       'no_msg_rdv',
        jauge:     'no_jauge',
        jaugeTxt:  'no_jauge_txt',
        jaugeFill: 'no_jauge_fill',
        acte:      'no_acte',
    };
}

// Synchronise l'acte entre les deux formulaires
function syncActe(val, source) {
    const autreId = (source === 'rdv') ? 'no_acte' : 'acte_rdv_futur';
    const el = document.getElementById(autreId);
    if (el) el.value = val;
}

function setActeRdv(val, prefixe) {
    const ids = rdvIds(prefixe);
    document.getElementById(ids.acte).value = val;
    syncActe(val, prefixe);
}

// Chargement et affichage des créneaux pour une date
function rdvChargerCreneaux(date, prefixe, heureAuto) {
    const ids = rdvIds(prefixe);
    const grille  = document.getElementById(ids.grille);
    const loading = document.getElementById(ids.loading);
    const msgEl   = document.getElementById(ids.msg);
    const jaugeEl = document.getElementById(ids.jauge);

    grille.innerHTML  = '';
    msgEl.style.display   = 'none';
    loading.style.display = 'block';
    jaugeEl.style.display = 'none';

    fetch('ajax_creneaux.php?date=' + date)
        .then(r => r.json())
        .then(data => {
            loading.style.display = 'none';

            if (!data.date_ok) {
                msgEl.textContent    = '⛔ ' + data.raison;
                msgEl.style.display  = 'block';
                // Effacer la date invalide et chercher le prochain jour
                document.getElementById(ids.dateH).value = '';
                document.getElementById(ids.dateV).value = '';
                document.getElementById(ids.heureH).value = '';
                return;
            }

            if (data.jour_complet) {
                msgEl.textContent   = '⛔ Journée complète (' + data.total_jour + '/' + data.max_jour + ' patients). Choisissez un autre jour.';
                msgEl.style.display = 'block';
            }

            // Jauge du jour
            const pct   = Math.min(100, Math.round(data.total_jour / data.max_jour * 100));
            const cl    = pct < 60 ? 'ok' : pct < 90 ? 'warn' : 'full';
            document.getElementById(ids.jaugeTxt).textContent = data.total_jour + ' / ' + data.max_jour + ' patients';
            const fill = document.getElementById(ids.jaugeFill);
            fill.style.width = pct + '%';
            fill.className   = 'jauge-fill ' + cl;
            jaugeEl.style.display = 'flex';

            // Grille créneaux
            const heureActuelle = document.getElementById(ids.heureH).value;
            data.creneaux.forEach(c => {
                const btn = document.createElement('button');
                btn.type      = 'button';
                btn.textContent = c.heure;
                btn.className = 'creneau-btn ' + c.statut;
                btn.title     = c.nb + ' patient(s) sur ce créneau';

                if (c.statut === 'plein') {
                    btn.disabled = true;
                } else {
                    btn.onclick = () => rdvSelectionnerCreneau(c.heure, prefixe);
                }

                // Marquer le créneau actuellement sélectionné
                if (c.heure === heureActuelle) btn.classList.add('selectionne');

                grille.appendChild(btn);
            });

            // Sélection automatique du premier créneau libre si demandé
            if (heureAuto && data.premier_libre && !heureActuelle) {
                rdvSelectionnerCreneau(data.premier_libre, prefixe);
            }
        })
        .catch(() => {
            loading.style.display = 'none';
            msgEl.textContent     = '❌ Erreur de connexion';
            msgEl.style.display   = 'block';
        });
}

// Sélectionner un créneau (met à jour les champs cachés + visuel)
function rdvSelectionnerCreneau(heure, prefixe) {
    const ids = rdvIds(prefixe);

    // Mettre à jour le champ heure caché
    document.getElementById(ids.heureH).value = heure;

    // Synchroniser les deux formulaires
    const autreHId = (prefixe === 'rdv') ? 'no_heure' : 'heure_rdv_futur';
    const autreEl  = document.getElementById(autreHId);
    if (autreEl) autreEl.value = heure;

    // Visuel : retirer selectionne de tous, ajouter au cliqué
    const grille = document.getElementById(ids.grille);
    grille.querySelectorAll('.creneau-btn').forEach(b => b.classList.remove('selectionne'));
    grille.querySelectorAll('.creneau-btn').forEach(b => {
        if (b.textContent === heure) b.classList.add('selectionne');
    });

    // Synchroniser aussi la grille de l'autre formulaire
    const autreGrilleId = (prefixe === 'rdv') ? 'no_grille' : 'rdv_grille';
    const autreGrille   = document.getElementById(autreGrilleId);
    if (autreGrille) {
        autreGrille.querySelectorAll('.creneau-btn').forEach(b => {
            b.classList.remove('selectionne');
            if (b.textContent === heure && !b.disabled) b.classList.add('selectionne');
        });
    }
}

// Appel quand l'utilisateur change la date manuellement
function rdvDateChange(date, prefixe) {
    if (!date || !/^\d{4}-\d{2}-\d{2}$/.test(date) || date === '1970-01-01') return;
    const ids = rdvIds(prefixe);

    // Synchroniser la date dans le champ caché
    document.getElementById(ids.dateH).value = date;

    // Synchroniser l'autre formulaire (date visible + caché)
    const autreV = (prefixe === 'rdv') ? 'no_rdv_visible'     : 'rdv_futur_visible';
    const autreH = (prefixe === 'rdv') ? 'no_rdv'             : 'rdv_futur';
    const evEl   = document.getElementById(autreV);
    const ehEl   = document.getElementById(autreH);
    if (evEl) evEl.value = date;
    if (ehEl) ehEl.value = date;

    // Effacer l'heure (on va recharger les créneaux)
    document.getElementById(ids.heureH).value = '';
    const autreHId = (prefixe === 'rdv') ? 'no_heure' : 'heure_rdv_futur';
    const autreHEl = document.getElementById(autreHId);
    if (autreHEl) autreHEl.value = '';

    // Charger les créneaux avec sélection automatique
    rdvChargerCreneaux(date, prefixe, true);

    // Recharger aussi la grille de l'autre formulaire
    const autrePrefixe = (prefixe === 'rdv') ? 'no' : 'rdv';
    const autreGrille  = document.getElementById(rdvIds(autrePrefixe).grille);
    if (autreGrille && autreGrille.closest('#form-nouvelle-ordonnance')?.style.display !== 'none'
        || autreGrille && prefixe === 'no') {
        rdvChargerCreneaux(date, autrePrefixe, false);
    }
}

// Appel quand on clique 1M / 3M / 6M / 7J / 15J / 21J
// Pour les délais en mois (1M, 3M, 6M) → cherche le prochain jour disponible
// Pour les délais en jours (7J, 15J, 21J) → affiche directement
function rdvSetDelai(mois, jours, prefixe) {
    const ids = rdvIds(prefixe);

    const d = new Date();
    if (mois)  d.setMonth(d.getMonth() + mois);
    if (jours) d.setDate(d.getDate() + jours);
    const dateCible = d.toISOString().split('T')[0];

    if (mois > 0) {
        // Pour 1M / 3M / 6M → chercher le prochain jour disponible
        const loading = document.getElementById(ids.loading);
        const grille  = document.getElementById(ids.grille);
        const msgEl   = document.getElementById(ids.msg);
        grille.innerHTML  = '';
        msgEl.style.display   = 'none';
        loading.style.display = 'block';
        loading.textContent   = '⏳ Recherche du prochain jour disponible…';

        fetch('ajax_prochain_jour.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ date_cible: dateCible })
        })
        .then(r => r.json())
        .then(data => {
            loading.style.display = 'none';
            loading.textContent   = '⏳ Chargement…';

            if (data.error) {
                msgEl.textContent   = '⛔ ' + data.error;
                msgEl.style.display = 'block';
                return;
            }

            const dateTrouvee = data.date_trouvee;

            // Mettre à jour les deux formulaires
            ['rdv_futur', 'no_rdv'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.value = dateTrouvee;
            });
            ['rdv_futur_visible', 'no_rdv_visible'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.value = dateTrouvee;
            });

            // Effacer les heures
            ['heure_rdv_futur','no_heure'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.value = '';
            });

            // Afficher les créneaux dans les deux grilles
            rdvChargerCreneaux(dateTrouvee, 'rdv', true);
            rdvChargerCreneaux(dateTrouvee, 'no',  false);
        })
        .catch(() => {
            loading.style.display = 'none';
            loading.textContent   = '⏳ Chargement…';
        });

    } else {
        // Pour 7J / 15J / 21J → afficher directement
        ['rdv_futur','no_rdv'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = dateCible;
        });
        ['rdv_futur_visible','no_rdv_visible'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = dateCible;
        });
        ['heure_rdv_futur','no_heure'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });

        rdvChargerCreneaux(dateCible, 'rdv', true);
        rdvChargerCreneaux(dateCible, 'no',  false);
    }
}

// ══════════════════════════════════════════════════════
// NOUVELLE ORDONNANCE
// ══════════════════════════════════════════════════════
function afficherNouvelleOrdonnance() {
    document.getElementById('form-nouvelle-ordonnance').style.display = 'block';
    if (document.getElementById('no_lignes').children.length === 0) {
        noAjouterLigne();
    }
    // Si une date RDV est déjà définie (depuis le formulaire violet), charger ses créneaux
    const dateExistante = document.getElementById('no_rdv').value;
    if (dateExistante && document.getElementById('no_grille').children.length === 0) {
        rdvChargerCreneaux(dateExistante, 'no', false);
    }
}

function masquerNouvelleOrdonnance() {
    document.getElementById('form-nouvelle-ordonnance').style.display = 'none';
}

const noMeds = <?= json_encode(array_map(fn($m) => [
    'id'  => $m['NuméroPRODUIT'],
    'nom' => $m['PRODUIT']
], $listeMeds)) ?>;

const noPosologies = <?= json_encode($posologies) ?>;
const noDurees     = <?= json_encode($durees) ?>;
let noIdx = 0;
function noAjouterLigne() {
    const i = noIdx++;
    let optsMed = '<option value="">— Médicament —</option>';
    noMeds.forEach(m => { optsMed += `<option value="${m.id}">${m.nom}</option>`; });
    let optsPoso = '<option value="">— Posologie —</option>';
    noPosologies.forEach(p => { optsPoso += `<option value="${p}">${p}</option>`; });
    let optsDuree = '<option value="">— Durée —</option>';
    noDurees.forEach(d => { optsDuree += `<option value="${d}">${d}</option>`; });

    const tr = document.createElement('tr');
    tr.style.borderBottom = '1px solid #eee';
    tr.innerHTML = `
        <td style="padding:3px 4px;"><select id="no_med_${i}" style="width:100%;border:1px solid #ddd;border-radius:3px;padding:3px;font-size:11px;">${optsMed}</select></td>
        <td style="padding:3px 4px;"><select id="no_poso_${i}" style="width:100%;border:1px solid #ddd;border-radius:3px;padding:3px;font-size:11px;">${optsPoso}</select></td>
        <td style="padding:3px 4px;"><select id="no_duree_${i}" style="width:100%;border:1px solid #ddd;border-radius:3px;padding:3px;font-size:11px;">${optsDuree}</select></td>
        <td style="padding:3px 4px;"><button type="button" onclick="this.closest('tr').remove()" style="background:#e74c3c;color:white;border:none;border-radius:3px;padding:2px 6px;cursor:pointer;font-size:10px;">✕</button></td>`;
    document.getElementById('no_lignes').appendChild(tr);
}

function noEnregistrer(patientId) {
    const date_ordon = document.getElementById('no_date').value;
    const acte       = document.getElementById('no_acte').value;
    const date_rdv   = document.getElementById('no_rdv').value;
    const heure_rdv  = document.getElementById('no_heure').value;
    const msgEl      = document.getElementById('no_msg');
    const lignes     = [];

    // ── Validations obligatoires ──────────────────────────────────────
    if (!date_ordon) {
        msgEl.textContent = '⛔ La date d\'ordonnance est obligatoire.';
        msgEl.style.color = '#e74c3c';
        document.getElementById('no_date').style.border = '2px solid #e74c3c';
        document.getElementById('no_date').focus();
        return;
    }
    document.getElementById('no_date').style.border = '';

    if (!date_rdv) {
        msgEl.textContent = '⛔ La date de RDV est obligatoire — choisissez un créneau.';
        msgEl.style.color = '#e74c3c';
        document.getElementById('no_rdv_visible').style.border = '2px solid #e74c3c';
        document.getElementById('no_rdv_visible').focus();
        return;
    }
    document.getElementById('no_rdv_visible').style.border = '';

    if (!heure_rdv) {
        msgEl.textContent = '⛔ L\'heure de RDV est obligatoire — cliquez sur un créneau vert ou jaune.';
        msgEl.style.color = '#e74c3c';
        return;
    }
    // ─────────────────────────────────────────────────────────────────

    document.querySelectorAll('#no_lignes tr').forEach(tr => {
        const idx = tr.querySelector('select')?.id?.replace('no_med_', '');
        if (!idx) return;
        const med   = document.getElementById(`no_med_${idx}`)?.value;
        const poso  = document.getElementById(`no_poso_${idx}`)?.value;
        const duree = document.getElementById(`no_duree_${idx}`)?.value;
        if (med) lignes.push({ med, poso, duree });
    });

    msgEl.textContent = 'Enregistrement…';
    msgEl.style.color = '#999';

    fetch('ajax_nouvelle_ordonnance.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ id: patientId, date_ordon, acte, date_rdv, heure_rdv, lignes })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.href = `dossier.php?id=${patientId}&ord=${data.n_ordon}`;
        } else {
            document.getElementById('no_msg').textContent = '❌ ' + data.error;
            document.getElementById('no_msg').style.color = '#e74c3c';
        }
    })
    .catch(() => {
        document.getElementById('no_msg').textContent = '❌ Erreur réseau';
        document.getElementById('no_msg').style.color = '#e74c3c';
    });
}

// ══════════════════════════════════════════════════════
// NOUVELLE FACTURE
// ══════════════════════════════════════════════════════
const nfActes = <?= json_encode(array_map(fn($a) => [
    'n_acte' => $a['n_acte'],
    'ACTE'   => $a['ACTE'],
    'cout'   => (float)$a['cout']
], $listeActes)) ?>;
let nfIdx = 0;

function toggleNouvelleFacture() {
    const form = document.getElementById('formNouvelleFacture');
    const aff  = document.getElementById('fact-affichage');
    const visible = form.style.display !== 'none';
    form.style.display = visible ? 'none' : 'block';
    if (aff) aff.style.display = visible ? 'block' : 'none';
    if (!visible && document.getElementById('nf_lignes').children.length === 0) {
        nfAjouterLigne();
    }
}

function nfAjouterLigne() {
    const i = nfIdx++;
    const today = document.getElementById('nf_date').value;
    let opts = '<option value="">— Acte —</option>';
    nfActes.forEach(a => {
        opts += `<option value="${a.n_acte}" data-cout="${a.cout}">${a.ACTE}</option>`;
    });
    const tr = document.createElement('tr');
    tr.style.borderBottom = '1px solid #eee';
    tr.innerHTML = `
        <td style="padding:3px 4px;"><input type="date" id="nf_dateacte_${i}" value="${today}" style="border:1px solid #ddd;border-radius:3px;padding:2px;font-size:11px;width:105px;"></td>
        <td style="padding:3px 4px;"><select id="nf_acte_${i}" onchange="nfRemplirPrix(${i})" style="width:100%;border:1px solid #ddd;border-radius:3px;padding:2px;font-size:11px;">${opts}</select></td>
        <td style="padding:3px 4px;"><input type="number" id="nf_prix_${i}" min="0" step="0.01" placeholder="0" oninput="nfRecalculer(${i})" style="width:70px;border:1px solid #ddd;border-radius:3px;padding:2px;font-size:11px;text-align:right;"></td>
        <td style="padding:3px 4px;"><input type="number" id="nf_verse_${i}" min="0" step="0.01" value="0" oninput="nfRecalculer(${i})" style="width:70px;border:1px solid #ddd;border-radius:3px;padding:2px;font-size:11px;text-align:right;"></td>
        <td style="padding:3px 4px;text-align:right;font-weight:600;color:#c0392b;" id="nf_dette_${i}">0</td>
        <td style="padding:3px 4px;"><button type="button" onclick="this.closest('tr').remove();nfMajTotaux()" style="background:#e74c3c;color:white;border:none;border-radius:3px;padding:2px 6px;cursor:pointer;font-size:10px;">✕</button></td>`;
    document.getElementById('nf_lignes').appendChild(tr);
}

function nfRemplirPrix(i) {
    const sel = document.getElementById(`nf_acte_${i}`);
    const cout = sel.options[sel.selectedIndex]?.getAttribute('data-cout') || '';
    document.getElementById(`nf_prix_${i}`).value = cout;
    nfRecalculer(i);
}

function nfRecalculer(i) {
    const prix  = parseFloat(document.getElementById(`nf_prix_${i}`)?.value) || 0;
    const verse = parseFloat(document.getElementById(`nf_verse_${i}`)?.value) || 0;
    const el = document.getElementById(`nf_dette_${i}`);
    if (el) el.textContent = (prix - verse).toLocaleString('fr-FR') + ' DH';
    nfMajTotaux();
}

function nfMajTotaux() {
    let tp = 0, tv = 0, td = 0;
    document.querySelectorAll('#nf_lignes tr').forEach(tr => {
        const idx = tr.querySelector('select')?.id?.replace('nf_acte_', '');
        if (!idx) return;
        const p = parseFloat(document.getElementById(`nf_prix_${idx}`)?.value) || 0;
        const v = parseFloat(document.getElementById(`nf_verse_${idx}`)?.value) || 0;
        tp += p; tv += v; td += (p - v);
    });
    document.getElementById('nf_totalPrix').textContent  = tp.toLocaleString('fr-FR') + ' DH';
    document.getElementById('nf_totalVerse').textContent = tv.toLocaleString('fr-FR') + ' DH';
    document.getElementById('nf_totalDette').textContent = td.toLocaleString('fr-FR') + ' DH';
}

function nfEnregistrer(patientId) {
    const date_facture = document.getElementById('nf_date').value;
    const lignes = [];
    document.querySelectorAll('#nf_lignes tr').forEach(tr => {
        const idx = tr.querySelector('select')?.id?.replace('nf_acte_', '');
        if (!idx) return;
        const acte  = document.getElementById(`nf_acte_${idx}`)?.value;
        const prix  = parseFloat(document.getElementById(`nf_prix_${idx}`)?.value) || 0;
        const verse = parseFloat(document.getElementById(`nf_verse_${idx}`)?.value) || 0;
        const dateA = document.getElementById(`nf_dateacte_${idx}`)?.value;
        if (acte) lignes.push({ acte, prix, verse, date_acte: dateA });
    });
    if (lignes.length === 0) {
        document.getElementById('nf_msg').textContent = '⚠ Ajoutez au moins un acte.';
        document.getElementById('nf_msg').style.color = '#e74c3c';
        return;
    }
    document.getElementById('nf_msg').textContent = 'Enregistrement…';
    document.getElementById('nf_msg').style.color = '#999';
    fetch('ajax_nouvelle_facture.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ id: patientId, date_facture, lignes })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.href = `dossier.php?id=${patientId}&fact=${data.n_facture}`;
        } else {
            document.getElementById('nf_msg').textContent = '❌ ' + data.error;
            document.getElementById('nf_msg').style.color = '#e74c3c';
        }
    })
    .catch(() => {
        document.getElementById('nf_msg').textContent = '❌ Erreur réseau';
        document.getElementById('nf_msg').style.color = '#e74c3c';
    });
}

// ── Initialisation au chargement ──────────────────────────────────────────
// Si l'ordonnance courante a déjà une date RDV → afficher ses créneaux
document.addEventListener('DOMContentLoaded', () => {
    // Valider que la date est bien au format yyyy-mm-dd avant de charger
    const dateInit = document.getElementById('rdv_futur')?.value;
    const reDate = /^\d{4}-\d{2}-\d{2}$/;
    if (dateInit && reDate.test(dateInit) && dateInit !== '1970-01-01') {
        rdvChargerCreneaux(dateInit, 'rdv', false);
    }
});

// Validation format date utilisée dans rdvDateChange et rdvSetDelai
function dateValide(d) {
    return d && /^\d{4}-\d{2}-\d{2}$/.test(d) && d !== '1970-01-01';
}
</script>
</body>
</html>