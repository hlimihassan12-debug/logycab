<?php
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
if ($id == 0) { header('Location: recherche.php'); exit; }

$stmt = $db->prepare("SELECT * FROM ID WHERE [N°PAT] = ?");
$stmt->execute([$id]);
$patient = $stmt->fetch();
if (!$patient) { die("❌ Patient introuvable !"); }

$stmtDiag = $db->prepare("SELECT N_dic, diagnostic FROM t_diagnostic WHERE id = ? ORDER BY N_dic");
$stmtDiag->execute([$id]);
$diagnostics = $stmtDiag->fetchAll();

$stmtDiag2 = $db->prepare("SELECT N_DIC_II, DicII FROM T_dianstcII WHERE id = ? ORDER BY N_DIC_II");
$stmtDiag2->execute([$id]);
$diagnosticsII = $stmtDiag2->fetchAll();

$stmtDiagNC = $db->prepare("SELECT N_dic_non_cardio, dic_non_cardio FROM T_id_dic_non_cardio WHERE id = ? ORDER BY N_dic_non_cardio");
$stmtDiagNC->execute([$id]);
$diagnosticsNC = $stmtDiagNC->fetchAll();

$stmtFDR = $db->prepare("SELECT FDR FROM patient_fdr WHERE id = ? ORDER BY N");
$stmtFDR->execute([$id]);
$fdrPatient = $stmtFDR->fetchAll(PDO::FETCH_COLUMN);

$first_id = $db->query("SELECT TOP 1 [N°PAT] FROM ID WHERE [N°PAT] IN (SELECT DISTINCT id FROM ORD) ORDER BY [N°PAT] ASC")->fetchColumn();
$last_id  = $db->query("SELECT TOP 1 [N°PAT] FROM ID WHERE [N°PAT] IN (SELECT DISTINCT id FROM ORD) ORDER BY [N°PAT] DESC")->fetchColumn();

$prev_id  = $db->prepare("SELECT TOP 1 [N°PAT] FROM ID WHERE [N°PAT] < ? AND [N°PAT] IN (SELECT DISTINCT id FROM ORD) ORDER BY [N°PAT] DESC");
$prev_id->execute([$id]); $prev_id = $prev_id->fetchColumn() ?: $id;

$next_id  = $db->prepare("SELECT TOP 1 [N°PAT] FROM ID WHERE [N°PAT] > ? AND [N°PAT] IN (SELECT DISTINCT id FROM ORD) ORDER BY [N°PAT] ASC");
$next_id->execute([$id]); $next_id = $next_id->fetchColumn() ?: $id;

$total_patients = $db->query("SELECT COUNT(DISTINCT id) FROM ORD")->fetchColumn();
$pos_patient    = $db->prepare("SELECT COUNT(DISTINCT id) FROM ORD WHERE id <= ?");
$pos_patient->execute([$id]); $pos_patient = $pos_patient->fetchColumn();

$age = '';
if ($patient['DDN']) {
    $naissance = new DateTime($patient['DDN']);
    $age = $naissance->diff(new DateTime())->y;
}

$stmtOrd = $db->prepare("SELECT * FROM ORD WHERE id=? ORDER BY date_ordon DESC");
$stmtOrd->execute([$id]);
$ordonnances = $stmtOrd->fetchAll();
$nOrd = (int)($_GET['ord'] ?? ($ordonnances ? $ordonnances[0]['n_ordon'] : 0));

$ordCourante = null;
$idxOrdCourante = 0;
foreach ($ordonnances as $i => $o) {
    if ($o['n_ordon'] == $nOrd) { $ordCourante = $o; $idxOrdCourante = $i; break; }
}

$ordPrecedente = isset($ordonnances[$idxOrdCourante + 1]) ? $ordonnances[$idxOrdCourante + 1] : null;

$acteNouveauRDV = '';
if ($ordPrecedente) {
    $acteNouveauRDV = $ordPrecedente['acte1'] ?? '';
}

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

// ── HISTORIQUE ACTES ──
$stmtHistECG = $db->prepare("
    SELECT da.[date-H] AS dt FROM detail_acte da
    JOIN facture f ON da.N_fact = f.n_facture
    JOIN t_acte_simplifiée a ON da.ACTE = a.n_acte
    WHERE f.id = ? AND a.ACTE LIKE '%ECG%' AND da.[date-H] IS NOT NULL
    ORDER BY da.[date-H] DESC");
$stmtHistECG->execute([$id]); $histECG = $stmtHistECG->fetchAll();

$stmtHistEDC = $db->prepare("
    SELECT da.[date-H] AS dt FROM detail_acte da
    JOIN facture f ON da.N_fact = f.n_facture
    JOIN t_acte_simplifiée a ON da.ACTE = a.n_acte
    WHERE f.id = ? AND a.ACTE LIKE '%EDC%' AND da.[date-H] IS NOT NULL
    ORDER BY da.[date-H] DESC");
$stmtHistEDC->execute([$id]); $histEDC = $stmtHistEDC->fetchAll();

$stmtHistDTSA = $db->prepare("
    SELECT da.[date-H] AS dt FROM detail_acte da
    JOIN facture f ON da.N_fact = f.n_facture
    JOIN t_acte_simplifiée a ON da.ACTE = a.n_acte
    WHERE f.id = ? AND a.ACTE LIKE '%DTSA%' AND da.[date-H] IS NOT NULL
    ORDER BY da.[date-H] DESC");
$stmtHistDTSA->execute([$id]); $histDTSA = $stmtHistDTSA->fetchAll();

function dateActe($row) {
    $d = $row['dt'] ?? null;
    if (!$d) return '—';
    $ts = strtotime($d);
    return ($ts && $ts > 86400) ? date('d/m/y', $ts) : '—';
}

// ── DATE RECRUTEMENT — calculée ICI, avant tout usage ──
$datePremVisite = null;
if (!empty($patient['DateRecrt'])) {
    $ts = strtotime($patient['DateRecrt']);
    if ($ts && $ts > 86400) {
        $datePremVisite = date('Y-m-d', $ts);
    }
}
$tsPV = $datePremVisite ? strtotime($datePremVisite) : false;
$datePVAff = ($tsPV && $tsPV > 86400) ? date('d/m/Y', $tsPV) : '—';

// Navigation ordonnances
$idxOrd = 0;
foreach ($ordonnances as $i => $o) { if ($o['n_ordon'] == $nOrd) { $idxOrd = $i; break; } }
$ordPremiere = $ordonnances ? $ordonnances[count($ordonnances)-1]['n_ordon'] : 0;
$ordDerniere = $ordonnances ? $ordonnances[0]['n_ordon'] : 0;
$ordPrev = ($idxOrd < count($ordonnances)-1) ? $ordonnances[$idxOrd+1]['n_ordon'] : $nOrd;
$ordNext = ($idxOrd > 0) ? $ordonnances[$idxOrd-1]['n_ordon'] : $nOrd;

$medicaments = [];
if ($nOrd) {
    $stmtMed = $db->prepare("SELECT p.*, pr.PRODUIT FROM PROD p LEFT JOIN PRODUITS pr ON p.produit = pr.NuméroPRODUIT WHERE p.N_ord = ? ORDER BY p.Ordre");
    $stmtMed->execute([$nOrd]);
    $medicaments = $stmtMed->fetchAll();
}

$stmtEx = $db->prepare("SELECT TOP 1 * FROM t_examen WHERE NPAT=? ORDER BY DateExam DESC");
$stmtEx->execute([$id]);
$examen = $stmtEx->fetch();

$stmtECGs = $db->prepare("SELECT * FROM ecg WHERE [N-PAT]=? ORDER BY [Date ECG] DESC");
$stmtECGs->execute([$id]);
$ecgs = $stmtECGs->fetchAll();
$nECG = (int)($_GET['ecg'] ?? ($ecgs ? $ecgs[0]['N°'] : 0));
$ecgCourant = null; $idxECG = 0;
foreach ($ecgs as $i => $e) { if ($e['N°'] == $nECG) { $ecgCourant = $e; $idxECG = $i; break; } }

$stmtEchos = $db->prepare("SELECT * FROM echo WHERE [N-PAT]=? ORDER BY DATEchog DESC");
$stmtEchos->execute([$id]);
$echos = $stmtEchos->fetchAll();
$nEcho = (int)($_GET['echo'] ?? ($echos ? $echos[0]['N°'] : 0));
$echoCourant = null; $idxEcho = 0;
foreach ($echos as $i => $e) { if ($e['N°'] == $nEcho) { $echoCourant = $e; $idxEcho = $i; break; } }

$stmtFact = $db->prepare("
    SELECT f.n_facture, f.id, f.date_facture, f.montant,
           ISNULL(SUM(d.prixU),0) AS total,
           ISNULL(SUM(d.Versé),0) AS verse_total,
           ISNULL(SUM(d.dette),0) AS dette_total
    FROM facture f
    LEFT JOIN detail_acte d ON f.n_facture = d.N_fact
    WHERE f.id = ?
    GROUP BY f.n_facture, f.id, f.date_facture, f.montant
    ORDER BY f.date_facture DESC");
$stmtFact->execute([$id]);
$factures = $stmtFact->fetchAll();
$nFact = (int)($_GET['fact'] ?? ($factures ? $factures[0]['n_facture'] : 0));
$factCourante = null; $idxFact = 0;
foreach ($factures as $i => $f) { if ($f['n_facture'] == $nFact) { $factCourante = $f; $idxFact = $i; break; } }
$factPremiere = $factures ? $factures[count($factures)-1]['n_facture'] : 0;
$factDerniere = $factures ? $factures[0]['n_facture'] : 0;
$factPrev = ($idxFact < count($factures)-1) ? $factures[$idxFact+1]['n_facture'] : $nFact;
$factNext = ($idxFact > 0) ? $factures[$idxFact-1]['n_facture'] : $nFact;

$detailActes = [];
if ($nFact) {
    $stmtDA = $db->prepare("SELECT d.*, a.ACTE AS nom_acte FROM detail_acte d LEFT JOIN t_acte_simplifiée a ON d.ACTE = a.n_acte WHERE d.N_fact = ?");
    $stmtDA->execute([$nFact]);
    $detailActes = $stmtDA->fetchAll();
}

$listeActes = $db->query("SELECT n_acte, ACTE, cout FROM t_acte_simplifiée ORDER BY ACTE")->fetchAll();
$listeMeds  = $db->query("SELECT NuméroPRODUIT, PRODUIT FROM PRODUITS ORDER BY PRODUIT")->fetchAll();

$listeDiag1 = $db->query("SELECT DISTINCT diagnostic FROM t_diagnostic WHERE diagnostic IS NOT NULL AND diagnostic != '' ORDER BY diagnostic")->fetchAll(PDO::FETCH_COLUMN);
$listeDiag2 = $db->query("SELECT DISTINCT DicII FROM T_dianstcII WHERE DicII IS NOT NULL AND DicII != '' ORDER BY DicII")->fetchAll(PDO::FETCH_COLUMN);
$listeDiag3 = $db->query("SELECT DISTINCT dic_non_cardio FROM T_id_dic_non_cardio WHERE dic_non_cardio IS NOT NULL AND dic_non_cardio != '' ORDER BY dic_non_cardio")->fetchAll(PDO::FETCH_COLUMN);

$posologies = [
    '1 cp 1 fois par jour','1 cp 1 jour sur deux','1 cp 2 fois par jour',
    '1 cp 3 fois par jour','1 cp 4 fois par jour','1 cp alterné avec 1cp + 1/4 cp',
    '1 gel 1 fois par jour','1 gel 2 fois par jour','1 gel 3 fois par jour','1 gel 4 fois par jour',
    '1 sachet 1 x par jour','1 sachet 3 x par jour',
    '1/2 cp 1 fois par jour','1/2 cp 1 jour sur deux','1/2 cp 2 fois par jour',
    '1/2 cp 3 fois par jour','1/2 cp 4 fois par jour','1/2 cp par jour',
    '1/4 cp 1 fois par jour','1/4 cp 1 jour sur deux','1/4 cp 2 fois par jour',
    '1/4 cp 3 fois par jour','1/4 cp 4 fois par jour',
    '1/4 cp alterné avec 1/2 cp','1/4 cp alterné avec rien',
    '2 cp 1 fois par jour','2 cp 2 fois par jour','2 cp 3 fois par jour',
    '3 cp 1 fois par jour','3/4 cp 1 fois par jour','3/4 cp alterné avec 1 cp','4 gel 1 fois par jour',
];
$durees = ['1 semaine','2 semaines','1 mois','2 mois','3 mois','6 mois'];

$stmtActes = $db->prepare("SELECT n_acte, ACTE FROM t_acte_simplifiée ORDER BY n_acte");
$stmtActes->execute();
$actesCat = $stmtActes->fetchAll();

$dernVisite = null;
$dernActesFact = [];
if ($ordPrecedente) {
    $dernVisite = $ordPrecedente;
   $stmtDF = $db->prepare("
        SELECT TOP 1 f.n_facture, f.date_facture
        FROM facture f
        WHERE f.id = ?
        AND f.date_facture >= CONVERT(datetime, ?, 120)
        AND f.date_facture <= DATEADD(day, 7, CONVERT(datetime, ?, 120))
        ORDER BY f.date_facture ASC
    ");
    $dateOrdPrec = $ordPrecedente['date_ordon'] ?? null;
    if ($dateOrdPrec) {
        $stmtDF->execute([$id, $dateOrdPrec, $dateOrdPrec]);
        $factPrec = $stmtDF->fetch();
        if ($factPrec) {
            $stmtActesPrec = $db->prepare("SELECT a.ACTE FROM detail_acte d LEFT JOIN t_acte_simplifiée a ON d.ACTE = a.n_acte WHERE d.N_fact = ?");
            $stmtActesPrec->execute([$factPrec['n_facture']]);
            $dernActesFact = $stmtActesPrec->fetchAll(PDO::FETCH_COLUMN);
        }
    }
}

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
        $rdvPrevu = !empty($ordPrecedente['DATE REDEZ VOUS']) ? strtotime($ordPrecedente['DATE REDEZ VOUS']) : null;
        if ($rdvPrevu) {
            $ecartJours = (int)(($tsPrec + $totalJours * 86400 - $rdvPrevu) / 86400);
            if ($ecartJours <= 14) $delaiCouleur = '#27ae60';
            elseif ($ecartJours <= 30) $delaiCouleur = '#f39c12';
            else $delaiCouleur = '#e74c3c';
        }
    }
}

$acteSugActuel = [];
foreach ($actesSuggeres as $a) { $acteSugActuel[] = $a['acte']; }
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
.header { background: #1a4a7a; color: white; padding: 5px 12px; display: flex; align-items: center; gap: 7px; flex-wrap: nowrap; }
.header h1 { font-size: 14px; font-weight: 700; white-space: nowrap; }
.btn-h { color: white; text-decoration: none; border: none; cursor: pointer;
         padding: 3px 9px; border-radius: 4px; font-size: 11px; font-weight: bold;
         display: inline-flex; align-items: center; height: 24px; white-space: nowrap; }
.btn-h.green  { background: #27ae60; }
.btn-h.navy   { background: #1a4a7a; border: 1px solid rgba(255,255,255,0.3); }
.btn-h.blue   { background: #2e6da4; }
.btn-h.orange { background: #e67e22; }
.btn-h.purple { background: #8e44ad; }
.btn-h.red    { background: #e74c3c; }
.btn-h.grey   { background: #888; pointer-events: none; opacity: 0.7; cursor: default; }
.btn-h:not(.grey):hover { opacity: 0.85; }
/* Recherche avec suggestions */
.search-hdr-wrap { position: relative; flex-shrink: 0; }
.search-hdr {
    padding: 2px 8px; border-radius: 4px; font-size: 11px; height: 24px;
    border: 1px solid rgba(255,255,255,0.35); background: rgba(255,255,255,0.12);
    color: white; outline: none; width: 190px;
}
.search-hdr::placeholder { color: rgba(255,255,255,0.5); }
.search-hdr:focus { border-color: rgba(255,255,255,0.7); background: rgba(255,255,255,0.2); }
.header-clock { background: rgba(255,255,255,0.12); border-radius: 6px;
                padding: 3px 10px; text-align: center; min-width: 130px; flex-shrink: 0; }
.header-clock .ct { font-size: 15px; font-weight: bold; letter-spacing: 1px; color: #f0f4f8; }
.header-clock .cd { font-size: 9px; opacity: 0.75; }
.patient-bar { background: #000000; color: #FFD700; padding: 6px 16px; display: flex; gap: 20px; flex-wrap: wrap; font-size: 12px; }
.patient-bar .info label { font-size: 10px; opacity: 0.8; text-transform: uppercase; display: block; color: #FFD700; }
.patient-bar .info span { font-weight: bold; color: #FFD700; }
.main { display: grid; grid-template-columns: 200px 1fr 320px; gap: 8px; padding: 8px; align-items: start; }
.col-left { display: flex; flex-direction: column; gap: 8px; }
.col-mid  { display: flex; flex-direction: column; gap: 8px; }
.col-right{ display: flex; flex-direction: column; gap: 8px; }
.card { background: white; border-radius: 6px; padding: 10px; box-shadow: 0 1px 4px rgba(0,0,0,0.1); }
.card-title { color: #1a4a7a; font-size: 12px; font-weight: bold; margin-bottom: 8px; padding-bottom: 4px; border-bottom: 2px solid #e0e0e0; display: flex; justify-content: space-between; align-items: center; }
.nav-btns { display: flex; gap: 3px; }
.nav-btn { background: #1a4a7a; color: white; border: none; padding: 3px 7px; border-radius: 3px; cursor: pointer; font-size: 11px; text-decoration: none; }
.nav-btn:hover { background: #2e6da4; }
.nav-ord-barre { display: flex; justify-content: center; align-items: center; gap: 3px; margin-top: 14px; padding-top: 10px; border-top: 2px solid #e0e0e0; }
.champ { margin-bottom: 6px; }
.champ label { font-size: 10px; color: #888; text-transform: uppercase; font-weight: bold; display: block; margin-bottom: 2px; }
.champ input, .champ select, .champ textarea { width: 100%; padding: 4px 6px; border: 1px solid #ddd; border-radius: 3px; font-size: 12px; }
.champ textarea { resize: vertical; height: auto; overflow: hidden; field-sizing: content; }
.diag-bloc { display:flex; flex-direction:column; gap:3px; margin-bottom:4px; }
.diag-ligne { display:flex; gap:4px; align-items:center; }
.creneaux-wrap { margin-top: 6px; }
.creneaux-grille { display: flex; flex-wrap: wrap; gap: 3px; }
.creneau-btn { padding: 3px 7px; border-radius: 3px; border: 2px solid transparent; cursor: pointer; font-size: 11px; font-weight: bold; min-width: 48px; text-align: center; transition: transform 0.1s; }
.creneau-btn:hover { transform: scale(1.08); }
.creneau-btn.libre  { background: #27ae60; color: white; border-color: #1e8449; }
.creneau-btn.moyen  { background: #f39c12; color: white; border-color: #d68910; }
.creneau-btn.plein  { background: #e74c3c; color: #fdd; border-color: #c0392b; cursor: not-allowed; opacity: 0.7; }
.creneau-btn.selectionne { border-color: #1a4a7a !important; box-shadow: 0 0 0 3px rgba(26,74,122,0.35); transform: scale(1.1); }
.creneaux-msg     { font-size: 11px; color: #e74c3c; margin-top: 4px; font-weight: bold; }
.creneaux-loading { font-size: 11px; color: #888; font-style: italic; margin-top: 4px; }
.jauge-jour { display: flex; align-items: center; gap: 6px; margin-bottom: 6px; font-size: 11px; }
.jauge-bar  { flex: 1; height: 8px; background: #e0e0e0; border-radius: 4px; overflow: hidden; }
.jauge-fill { height: 100%; border-radius: 4px; transition: width 0.3s; }
.jauge-fill.ok   { background: #27ae60; }
.jauge-fill.warn { background: #f39c12; }
.jauge-fill.full { background: #e74c3c; }
.row-bottom { padding: 0 8px 8px; display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.ta-val { font-size: 16px; font-weight: bold; }
.fdr-badge { background: #ffe0e0; color: #c0392b; padding: 1px 6px; border-radius: 8px; font-size: 11px; margin: 1px; display: inline-block; }
.delai-btn-rdv { padding: 3px 8px; border: 1px solid #8e44ad; border-radius: 3px; cursor: pointer; font-size: 11px; background: white; color: #8e44ad; }
.delai-btn-rdv:hover, .delai-btn-rdv.actif { background: #8e44ad; color: white; }
.tableau-rdv { width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 10px; border-radius: 6px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,0.15); }
.tableau-rdv th { padding: 7px 6px; text-align: center; font-size: 11px; }
.tableau-rdv td { padding: 5px 6px; border-bottom: 1px solid #e8e8e8; }
.tableau-rdv td:first-child { background: #f0f4f8; font-size: 11px; font-weight: bold; color: #1a4a7a; text-align: right; white-space: nowrap; }
.tableau-rdv tr:last-child td { border-bottom: none; }
.col-visite   { background: #e8f8ee; }
.col-rdv-fixe { background: #e8f0fb; }
.col-rdv-futur{ background: #f3eafb; }
@media (max-width: 900px) { .main { grid-template-columns: 1fr; } .row-bottom { grid-template-columns: 1fr; } }
</style>
</head>
<body>

<!-- HEADER -->
<div class="header">
    <!-- GAUCHE : recherche globale avec suggestions -->
    <div class="search-hdr-wrap">
        <input class="search-hdr" type="text" id="rech-patient" placeholder="🔍 Rechercher patient...">
        <div id="rech-suggestions" style="position:absolute;top:100%;left:0;width:300px;background:white;
             border:1px solid #ccc;border-radius:4px;max-height:200px;overflow-y:auto;
             z-index:1000;display:none;box-shadow:0 4px 12px rgba(0,0,0,0.2);"></div>
    </div>
    <!-- MILIEU : boutons fixes (dossier = gris car page courante) -->
    <span                               class="btn-h grey"  >🏠 Dossier</span>
    <a href="agenda.php"                class="btn-h navy"  >📅 Agenda</a>
    <a href="planning.php"              class="btn-h blue"  >📊 Planning</a>
    <a href="grille_semaine.php"        class="btn-h blue"  >📋 Grille</a>
    <a href="biologie.php?id=<?= $id ?>" class="btn-h orange">🧪 Biologie</a>
    <a href="jours_feries.php"          class="btn-h purple">📅 Fériés</a>
    <!-- Séparateur -->
    <div style="width:1px;height:22px;background:rgba(255,255,255,0.2);flex-shrink:0;"></div>
    <!-- Navigation patient (spécifique dossier) -->
    <div style="display:inline-flex;align-items:center;gap:2px;background:rgba(255,255,255,0.1);border-radius:5px;padding:2px 6px;">
        <a href="dossier.php?id=<?= $first_id ?>" title="Premier" style="color:white;text-decoration:none;font-size:15px;padding:0 3px;">⏮</a>
        <a href="dossier.php?id=<?= $prev_id ?>"  title="Précédent" style="color:white;text-decoration:none;font-size:15px;padding:0 3px;">◀</a>
        <span style="color:white;font-size:11px;min-width:60px;text-align:center;"><?= $pos_patient ?> / <?= $total_patients ?></span>
        <a href="dossier.php?id=<?= $next_id ?>"  title="Suivant" style="color:white;text-decoration:none;font-size:15px;padding:0 3px;">▶</a>
        <a href="dossier.php?id=<?= $last_id ?>"  title="Dernier" style="color:white;text-decoration:none;font-size:15px;padding:0 3px;">⏭</a>
    </div>
    <!-- Bilans + Déco (spécifiques dossier) -->
    <a href="bilan.php?id=<?= $id ?>" class="btn-h blue">🧪 Bilans</a>
    <a href="logout.php"              class="btn-h red" >🚪 Déco</a>
    <!-- TITRE -->
    <h1 style="margin-left:4px;">🩺 Dossier médical</h1>
    <!-- DROITE : horloge -->
    <div class="header-clock" style="margin-left:auto;">
        <div id="clockTime" class="ct">--:--:--</div>
        <div id="clockDate" class="cd">---</div>
    </div>
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

<!-- ══ COLONNE GAUCHE ══ -->
<div class="col-left">
    <div class="card">
        <div class="card-title">👤 Dossier patient
            <span id="dossier_status" style="font-size:10px;color:#27ae60;font-weight:normal;"></span>
        </div>
        <div class="champ">
            <label>Motif de consultation</label>
            <textarea id="champ_motif" onblur="sauvegarderChamp('MOTIF CONSULTATION', this.value)"
                style="border:1px solid #ddd;border-radius:3px;padding:4px 6px;width:100%;font-size:12px;resize:vertical;min-height:50px;field-sizing:content;"
            ><?= htmlspecialchars($patient['MOTIF CONSULTATION'] ?? '') ?></textarea>
        </div>
        <div class="champ">
            <label>Antécédents</label>
            <textarea id="champ_atcd" onblur="sauvegarderChamp('ATCD', this.value)"
                style="border:1px solid #ddd;border-radius:3px;padding:4px 6px;width:100%;font-size:12px;resize:vertical;min-height:50px;field-sizing:content;"
            ><?= htmlspecialchars($patient['ATCD'] ?? '') ?></textarea>
        </div>

        <?php
        $diagConfigs = [
            1 => ['label' => 'Diagnostic principal',        'items' => $diagnostics,   'pk' => 'N_dic',            'champ' => 'diagnostic',     'liste' => $listeDiag1],
            2 => ['label' => 'Diagnostic II',               'items' => $diagnosticsII, 'pk' => 'N_DIC_II',         'champ' => 'DicII',          'liste' => $listeDiag2],
            3 => ['label' => 'Diagnostic non cardiologique','items' => $diagnosticsNC, 'pk' => 'N_dic_non_cardio', 'champ' => 'dic_non_cardio', 'liste' => $listeDiag3],
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
            <datalist id="datalist_diag_<?= $type ?>">
                <?php foreach ($cfg['liste'] as $opt): ?>
                <option value="<?= htmlspecialchars($opt) ?>">
                <?php endforeach; ?>
            </datalist>
            <div style="display:flex;gap:4px;margin-top:4px;">
                <input type="text" id="new_diag_<?= $type ?>" list="datalist_diag_<?= $type ?>"
                    placeholder="Choisir ou saisir..."
                    style="flex:1;border:1px solid #27ae60;border-radius:3px;padding:3px 6px;font-size:12px;">
                <button type="button" onclick="diagAjouter(<?= $type ?>, <?= $id ?>, <?= htmlspecialchars(json_encode($cfg['liste']), ENT_QUOTES) ?>)"
                    style="background:#27ae60;color:white;border:none;border-radius:3px;padding:2px 10px;cursor:pointer;font-size:11px;">➕</button>
            </div>
        </div>
        <?php endforeach; ?>

        <?php
        $fdrs = [];
        $nomsfdrs = [
            'FDR_Age'=>"L'âge",'FDR_ATCD_IDM_Fam'=>'ATCD IDM famille','FDR_ATCD_AVC_Fam'=>'ATCD AVC',
            'FDR_Tabac'=>'Tabagisme','FDR_Diabete'=>'Diabète','FDR_HTA'=>'HTA',
            'FDR_LDL_Oui'=>'LDL cholestérol','FDR_TG_Oui'=>'Triglycérides',
            'FDR_Obesite'=>'Obésité','FDR_Surpoids'=>'Surpoids','FDR_Tour_Taille'=>'Tour de taille',
            'FDR_Sedentarite'=>'Sédentarité','FDR_Synd_Metabolique'=>'Synd. métabolique',
            'FDR_Stress_Depression'=>'Stress/Dépression','FDR_Sommeil'=>'Troubles du sommeil','FDR_Drogues'=>'Drogues',
        ];
        if ($examen) { foreach ($nomsfdrs as $champFDR => $nomFDR) { if (!empty($examen[$champFDR])) $fdrs[] = $nomFDR; } }
        ?>
        <?php if (!empty($fdrs)): ?>
        <div class="champ">
            <label>Facteurs de risque (examen)</label>
            <?php foreach ($fdrs as $f): ?><span class="fdr-badge"><?= $f ?></span><?php endforeach; ?>
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
            <div style="margin-top:6px;"><a href="fdr_edit.php?id=<?= $id ?>" style="font-size:11px;color:#2e6da4;">✏️ Modifier</a></div>
        </div>

        <div class="champ">
            <label>Remarque</label>
            <textarea id="champ_remarque" onblur="sauvegarderChamp('REMARQUE', this.value)"
                style="border:1px solid #ddd;border-radius:3px;padding:4px 6px;width:100%;font-size:12px;resize:vertical;min-height:40px;field-sizing:content;"
            ><?= htmlspecialchars($patient['REMARQUE'] ?? '') ?></textarea>
        </div>
    </div>
</div><!-- FIN col-left -->

<!-- ══ COLONNE MILIEU ══ -->
<div class="col-mid">
    <div class="card">
        <div class="card-title" style="display:flex;justify-content:space-between;align-items:center;padding-bottom:6px;">
            <span style="font-size:13px;">📋 Ordonnance
                <?php if ($ordCourante && !empty($ordCourante['date_ordon'])): ?>
                <?php
                    $tsOrd = strtotime($ordCourante['date_ordon']);
                    $dateOrdAff = ($tsOrd && $tsOrd > 0) ? date('d/m/Y', $tsOrd) : '—';
                    $estAujourdHui = ($tsOrd && date('Y-m-d', $tsOrd) === date('Y-m-d'));
                    $coulOrd = $estAujourdHui ? '#e74c3c' : '#1a4a7a';
                    $bgOrd   = $estAujourdHui ? '#fdecea' : '#e8f0fb';
                    $bordOrd = $estAujourdHui ? '#e74c3c' : '#2e6da4';
                ?>
                <span style="font-family:Arial,sans-serif;font-weight:bold;font-size:12px;
                             color:<?= $coulOrd ?>;background:<?= $bgOrd ?>;
                             padding:2px 8px;border-radius:4px;
                             border:1px solid <?= $bordOrd ?>;margin-left:8px;">
                    <?= $dateOrdAff ?>
                </span>
                <?php endif; ?>
            </span>
        </div>

        <?php if ($ordCourante): ?>
        <div id="vue-ordonnance">
        <div id="ord-affichage" style="display:grid;grid-template-columns:1fr 380px;gap:8px;align-items:start;margin-bottom:8px;">

        <!-- COL GAUCHE : TABLEAU RDV + MÉDICAMENTS -->
        <div>
        <?php
        $dv_dateOrd = '—'; $dv_heure = '—'; $dv_actes = '—';
        if ($ordPrecedente) {
            $ts = strtotime($ordPrecedente['date_ordon'] ?? '');
            $dv_dateOrd = ($ts && $ts > 86400) ? date('d/m/Y', $ts) : '—';
            $dv_heure   = htmlspecialchars($ordPrecedente['HeureRDV'] ?? '—');
            $dv_actes   = !empty($dernActesFact) ? implode(', ', $dernActesFact) : htmlspecialchars($ordPrecedente['acte1'] ?? '—');
        }
        $rdvp_date = '—'; $rdvp_heure = '—'; $rdvp_acte = '—';
        if ($ordPrecedente) {
            $ts = !empty($ordPrecedente['DATE REDEZ VOUS']) ? strtotime($ordPrecedente['DATE REDEZ VOUS']) : false;
            $rdvp_date  = ($ts && $ts > 86400) ? date('d/m/Y', $ts) : '—';
            $rdvp_heure = htmlspecialchars($ordPrecedente['HeureRDV'] ?? '—');
            $rdvp_acte  = htmlspecialchars($ordPrecedente['acte1'] ?? '—');
        }
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
                <tr>
                    <td>📅 Date<br>⏰ Heure</td>
                    <td class="col-rdv-fixe" style="text-align:center;">
                        <strong style="color:#2e6da4;font-size:13px;"><?= $dv_dateOrd ?></strong><br>
                        <strong style="color:#2e6da4;font-size:12px;"><?= $dv_heure ?></strong>
                    </td>
                    <td style="background:#dce8f7;text-align:center;">
                        <strong style="color:#5b7fa6;font-size:13px;"><?= $rdvp_date ?></strong><br>
                        <strong style="color:#5b7fa6;font-size:12px;"><?= $rdvp_heure ?></strong>
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
                        <div style="display:flex;gap:2px;flex-wrap:wrap;margin-bottom:4px;align-items:center;">
                            <button type="button" onclick="rdvSetDelai(1,0,'rdv')"  class="delai-btn-rdv">1M</button>
                            <button type="button" onclick="rdvSetDelai(3,0,'rdv')"  class="delai-btn-rdv actif">3M</button>
                            <button type="button" onclick="rdvSetDelai(6,0,'rdv')"  class="delai-btn-rdv">6M</button>
                            <button type="button" onclick="rdvSetDelai(0,7,'rdv')"  class="delai-btn-rdv">7J</button>
                            <button type="button" onclick="rdvSetDelai(0,10,'rdv')" class="delai-btn-rdv">10J</button>
                            <button type="button" onclick="rdvSetDelai(0,15,'rdv')" class="delai-btn-rdv">15J</button>
                            <span style="width:1px;height:14px;background:#ccc;display:inline-block;margin:0 2px;"></span>
                            <button type="button" onclick="reportTraitement(3,<?= $id ?>)" style="background:#e67e22;color:white;border:none;padding:2px 5px;border-radius:3px;cursor:pointer;font-size:10px;font-weight:bold;">↺3M</button>
                            <button type="button" onclick="reportTraitement(6,<?= $id ?>)" style="background:#c0392b;color:white;border:none;padding:2px 5px;border-radius:3px;cursor:pointer;font-size:10px;font-weight:bold;">↺6M</button>
                    <button type="button" onclick="confirmerRdv(<?= $ordCourante['n_ordon'] ?>)"
                            title="Enregistrer le RDV"
                            style="background:#27ae60;color:white;border:none;padding:2px 6px;border-radius:3px;cursor:pointer;font-size:10px;font-weight:bold;display:inline-flex;align-items:center;gap:3px;">
                        <svg viewBox="0 0 24 24" width="14" height="14" fill="white" xmlns="http://www.w3.org/2000/svg">
                            <rect x="2" y="3" width="20" height="18" rx="2" ry="2" fill="none" stroke="white" stroke-width="1.5"/>
                            <rect x="5" y="3" width="14" height="8" rx="1" fill="white" opacity="0.3"/>
                            <rect x="8" y="4" width="2" height="6" rx="0.5" fill="white"/>
                            <rect x="14" y="4" width="2" height="6" rx="0.5" fill="white"/>
                            <circle cx="8"  cy="15" r="2.2" fill="none" stroke="white" stroke-width="1.2"/>
                            <circle cx="16" cy="15" r="2.2" fill="none" stroke="white" stroke-width="1.2"/>
                            <line x1="10.2" y1="15" x2="13.8" y2="15" stroke="white" stroke-width="1.2"/>
                        </svg>
                        RDV
                    </button>
					   </div>
                        <div style="display:flex;gap:4px;margin-bottom:4px;">
                            <input type="date" id="rdv_futur_visible" value="<?= $rdvFuturVal ?>"
                                   onchange="rdvDateChange(this.value,'rdv')"
                                   ondblclick="if(this.value) window.location.href='agenda.php?date='+this.value"
                                   title="Double-clic → ouvrir l'agenda ce jour"
                                   style="flex:1;padding:3px 4px;border:1px solid #8e44ad;border-radius:3px;font-size:11px;cursor:pointer;">
                            <div id="rdv_heure_affichage" style="background:#e8d5f5;color:#8e44ad;padding:3px 8px;border-radius:3px;font-size:12px;font-weight:bold;white-space:nowrap;">
                                <?= !empty($ordCourante['HeureRDV']) ? htmlspecialchars($ordCourante['HeureRDV']) : '—:——' ?>
                            </div>
                        </div>
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
                <tr>
                    <td>🏥 Acte</td>
                    <td class="col-rdv-fixe" style="text-align:center;">
                        <span style="background:#dce8f7;color:#1a4a7a;padding:2px 6px;border-radius:8px;font-size:11px;font-weight:bold;"><?= $dv_actes ?></span>
                    </td>
                    <td style="background:#dce8f7;text-align:center;">
                        <span style="background:#b8d0ec;color:#1a4a7a;padding:2px 6px;border-radius:8px;font-size:11px;font-weight:bold;"><?= $rdvp_acte ?></span>
                    </td>
                    <td class="col-visite" style="text-align:center;padding:4px;">
                        <?php foreach ($actesSuggeres as $as): ?>
                        <div style="border:2px solid #555;color:#333;padding:2px 6px;border-radius:4px;font-size:11px;font-weight:bold;margin-bottom:2px;display:inline-block;background:#f9f9f9;">
                            <?= $as['acte'] ?> <span style="font-size:9px;color:#888;"><?= $as['derniere'] ? date('d/m/y', strtotime($as['derniere'])) : 'jamais' ?></span>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($actesSuggeres)): ?><span style="color:#27ae60;font-size:11px;">✅ À jour</span><?php endif; ?>
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

        <!-- MÉDICAMENTS -->
        <div class="champ" style="margin-top:4px;">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                <label style="font-size:11px;font-weight:bold;color:#1a4a7a;margin:0;">💊 Médicaments (<?= count($medicaments) ?>)</label>
                <button type="button" onclick="reportTraitement(3,<?= $id ?>)" style="background:#e67e22;color:white;border:none;padding:2px 8px;border-radius:3px;cursor:pointer;font-size:10px;font-weight:bold;">↺ 3M</button>
                <button type="button" onclick="reportTraitement(6,<?= $id ?>)" style="background:#c0392b;color:white;border:none;padding:2px 8px;border-radius:3px;cursor:pointer;font-size:10px;font-weight:bold;">↺ 6M</button>
                <?php if ($ordCourante && !empty($ordCourante['date_ordon'])): ?>
                <?php
                    $tsOrd2 = strtotime($ordCourante['date_ordon']);
                    $dateOrd2 = ($tsOrd2 && $tsOrd2 > 0) ? date('d/m/Y', $tsOrd2) : '—';
                    $estAuj2  = ($tsOrd2 && date('Y-m-d', $tsOrd2) === date('Y-m-d'));
                    $coul2 = $estAuj2 ? '#e74c3c' : '#1a4a7a';
                    $bg2   = $estAuj2 ? '#fdecea' : '#e8f0fb';
                    $bord2 = $estAuj2 ? '#e74c3c' : '#2e6da4';
                ?>
                <span style="font-family:Arial,sans-serif;font-weight:bold;font-size:12px;
                             color:<?= $coul2 ?>;background:<?= $bg2 ?>;
                             padding:2px 8px;border-radius:4px;border:1px solid <?= $bord2 ?>;">
                    📋 <?= $dateOrd2 ?>
                </span>
                <?php endif; ?>
            </div>
            <?php if (!empty($medicaments)): ?>
            <div style="display:grid;grid-template-columns:2fr 2fr 1fr;gap:4px;margin-bottom:4px;margin-top:4px;">
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
            <?php else: ?><p style="color:#999;font-size:12px;">Aucun médicament</p><?php endif; ?>
        </div>
        </div><!-- FIN COL GAUCHE -->

        <!-- COL DROITE : FACTURATION -->
        <div>
            <div class="card-title" style="display:flex;justify-content:space-between;align-items:center;padding-bottom:6px;">
                <span style="font-size:13px;">💰 Facturation</span>
                <?php if ($factCourante): ?>
                <?php
                    $tsFactTitre  = strtotime($factCourante['date_facture'] ?? '');
                    $dateFactTitre = ($tsFactTitre && $tsFactTitre > 86400) ? date('d/m/Y', $tsFactTitre) : '—';
                    $estAujFact   = ($tsFactTitre && $tsFactTitre > 86400 && date('Y-m-d', $tsFactTitre) === date('Y-m-d'));
                    $coulFact = $estAujFact ? '#e74c3c' : '#1a4a7a';
                    $bgFact   = $estAujFact ? '#fdecea' : '#e8f0fb';
                    $bordFact = $estAujFact ? '#e74c3c' : '#2e6da4';
                ?>
                <span style="font-family:Arial,sans-serif;font-weight:bold;font-size:12px;
                             color:<?= $coulFact ?>;background:<?= $bgFact ?>;
                             padding:2px 8px;border-radius:4px;
                             border:1px solid <?= $bordFact ?>;margin-left:8px;">
                    <?= $dateFactTitre ?>
                </span>
                <?php endif; ?>
            </div>
            <div id="fact-affichage">
            <?php if ($factCourante): ?>
            <?php $tsF = strtotime($factCourante['date_facture'] ?? ''); $dateFactVal = ($tsF && $tsF > 86400) ? date('Y-m-d', $tsF) : ''; ?>
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;padding:4px 6px;background:#f0f4f8;border-radius:4px;">
                <span style="color:#888;font-size:11px;text-transform:uppercase;white-space:nowrap;">N°</span>
                <strong style="font-size:15px;color:#1a4a7a;"><?= $factCourante['n_facture'] ?></strong>
                <input type="date" value="<?= $dateFactVal ?>" onchange="majDateFacture(<?= $nFact ?>, this.value)"
                    style="margin-left:auto;padding:3px 6px;border:1px solid #ddd;border-radius:3px;font-size:11px;">
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
                        <td style="padding:4px 6px;">Total</td><td></td>
                        <td style="padding:4px 6px;text-align:right;"><?= number_format($factCourante['total'], 0, ',', ' ') ?> DH</td>
                        <td style="padding:4px 6px;text-align:right;"><?= number_format($factCourante['verse_total'], 0, ',', ' ') ?> DH</td>
                        <td style="padding:4px 6px;text-align:right;"><?= number_format($factCourante['dette_total'], 0, ',', ' ') ?> DH</td>
                    </tr>
                </tfoot>
            </table>
            <div style="display:flex;justify-content:center;gap:2px;margin-top:6px;">
                <a href="?id=<?= $id ?>&fact=<?= $factPremiere ?>" class="nav-btn" style="padding:2px 5px;font-size:10px;">|◀</a>
                <a href="?id=<?= $id ?>&fact=<?= $factPrev ?>"     class="nav-btn" style="padding:2px 5px;font-size:10px;">◀</a>
                <span style="font-size:10px;color:#1a4a7a;font-weight:bold;padding:2px 5px;white-space:nowrap;"><?= ($idxFact+1) ?> / <?= count($factures) ?></span>
                <a href="?id=<?= $id ?>&fact=<?= $factNext ?>"     class="nav-btn" style="padding:2px 5px;font-size:10px;">▶</a>
                <a href="?id=<?= $id ?>&fact=<?= $factDerniere ?>" class="nav-btn" style="padding:2px 5px;font-size:10px;">▶|</a>
                <button type="button" onclick="toggleNouvelleFacture()" class="nav-btn" style="background:#27ae60;padding:2px 5px;font-size:10px;">✚</button>
				<a href="factures.php?id=<?= $id ?>" class="nav-btn" style="background:#2e6da4;padding:2px 5px;font-size:10px;" title="Toutes les factures">💰 Liste</a>
            </div>
            <?php else: ?>
                <p style="color:#999;font-size:12px;">Aucune facture</p>
                <div style="display:flex;justify-content:center;margin-top:8px;">
                    <button type="button" onclick="toggleNouvelleFacture()" class="nav-btn" style="background:#27ae60;">✚ Nouvelle facture</button>
                </div>
            <?php endif; ?>
            </div>

            <!-- FORMULAIRE NOUVELLE FACTURE -->
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

            <!-- ══ CERTIFICAT MÉDICAL + RECRUTEMENT + TABLEAU ACTES ══ -->
            <div style="margin-top:12px;border-top:2px solid #e0e0e0;padding-top:10px;">

                <!-- BOUTON CERTIFICAT -->
                <button type="button"
                    onclick="var z=document.getElementById('cert-zone');z.style.display=z.style.display==='none'?'block':'none'"
                    style="background:white;color:#333;border:1px solid #ccc;border-radius:4px;padding:4px 12px;cursor:pointer;font-size:12px;font-weight:normal;margin-bottom:10px;">
                    Certificat médical
                </button>

                <!-- ZONE CERTIFICAT (cachée par défaut) -->
                <div id="cert-zone" style="display:none;background:#f0f4f8;border-radius:6px;padding:8px;margin-bottom:10px;border:1px solid #dde3ea;">
                    <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;font-size:12px;">
                        <span>du</span>
                        <input type="date" id="cert_debut" style="border:1px solid #ddd;border-radius:3px;padding:3px 6px;font-size:12px;" onchange="calcNbrJ()">
                        <span>au</span>
                        <input type="date" id="cert_fin"   style="border:1px solid #ddd;border-radius:3px;padding:3px 6px;font-size:12px;" onchange="calcNbrJ()">
                        <span>Nbr J</span>
                        <input type="number" id="cert_nbrj" style="width:55px;border:1px solid #ddd;border-radius:3px;padding:3px 6px;font-size:12px;text-align:center;" readonly>
                        <button type="button" onclick="imprimerCertificat()" style="background:#1a4a7a;color:white;border:none;border-radius:3px;padding:4px 10px;cursor:pointer;font-size:11px;">🖨️ Imprimer</button>
                    </div>
                </div>

                <!-- DATE RECRUTEMENT -->
                <div style="font-size:11px;color:#555;margin-bottom:8px;">
                    🏥 <strong>Recrutement :</strong> <?= $datePVAff ?>
                </div>

                <!-- TABLEAU HISTORIQUE + ACTES SUGGÉRÉS (4 colonnes) -->
                <table style="width:100%;border-collapse:collapse;font-size:11px;">
                    <thead>
                        <tr style="background:#1a4a7a;color:white;">
                            <th style="padding:5px 6px;text-align:left;">Acte</th>
                            <th style="padding:5px 6px;text-align:center;">Historique</th>
                            <th style="padding:5px 6px;text-align:center;">Dernière réal.</th>
                            <th style="padding:5px 6px;text-align:center;">Acte suggéré</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $actesTableau = [
                        'ECG'  => ['hist' => $histECG,  'delai' => 30],
                        'EDC'  => ['hist' => $histEDC,  'delai' => 335],
                        'DTSA' => ['hist' => $histDTSA, 'delai' => 335],
                    ];
                    foreach ($actesTableau as $nomA => $cfg):
                        $nb      = count($cfg['hist']);
                        $datesJS = [];
                        $dernDate = null;
                        foreach ($cfg['hist'] as $row) {
                            $d = dateActe($row);
                            if ($d !== '—') { $datesJS[] = $d; if (!$dernDate) $dernDate = $row['dt']; }
                        }
                        $datesAttr = htmlspecialchars(json_encode($datesJS), ENT_QUOTES);
                        $dernAff   = '—';
                        if ($dernDate) { $ts = strtotime($dernDate); $dernAff = ($ts && $ts > 86400) ? date('d/m/Y', $ts) : '—'; }
                        $suggAff   = '—';
                        $suggStyle = 'color:#27ae60;font-weight:bold;';
                        if (!$dernDate) {
                            $suggAff = 'jamais fait'; $suggStyle = 'color:#e74c3c;font-weight:bold;';
                        } else {
                            $ts = strtotime($dernDate);
                            if ($ts && $ts > 86400) {
                                $dtSugg = new DateTime(date('Y-m-d', $ts));
                                $dtSugg->modify('+' . $cfg['delai'] . ' days');
                                $suggAff   = $dtSugg->format('d/m/Y');
                                $suggStyle = ($dtSugg < new DateTime()) ? 'color:#e74c3c;font-weight:bold;' : 'color:#27ae60;font-weight:bold;';
                            }
                        }
                    ?>
                    <tr style="border-bottom:1px solid #eee;">
                        <td style="padding:5px 6px;font-weight:bold;color:#1a4a7a;"><?= $nomA ?></td>
                        <td style="padding:5px 6px;text-align:center;">
                            <?php if ($nb > 0): ?>
                            <span class="hist-acte-btn" data-acte="<?= $nomA ?>" data-dates="<?= $datesAttr ?>"
                                  style="background:#e8f0fb;color:#1a4a7a;padding:1px 10px;border-radius:10px;font-size:12px;font-weight:bold;cursor:pointer;border:1px solid #2e6da4;"
                                  title="Cliquer pour voir les dates"><?= $nb ?></span>
                            <?php else: ?><span style="color:#aaa;">—</span><?php endif; ?>
                        </td>
                        <td style="padding:5px 6px;text-align:center;font-size:11px;color:#555;"><?= $dernAff ?></td>
                        <td style="padding:5px 6px;text-align:center;font-size:11px;<?= $suggStyle ?>"><?= $suggAff ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- POPUP DATES ACTES -->
                <div id="popup-dates-acte" style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:white;border-radius:8px;padding:20px;box-shadow:0 8px 32px rgba(0,0,0,0.3);z-index:9998;min-width:200px;max-width:300px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                        <strong id="popup-dates-titre" style="color:#1a4a7a;font-size:14px;"></strong>
                        <button onclick="document.getElementById('popup-dates-acte').style.display='none'" style="background:none;border:none;cursor:pointer;font-size:18px;color:#888;">✕</button>
                    </div>
                    <div id="popup-dates-liste" style="font-size:12px;line-height:1.8;color:#333;"></div>
                </div>

            </div><!-- FIN section certificat + tableau actes -->

        </div><!-- FIN COL DROITE -->

        </div><!-- FIN GRID -->

        <!-- NAVIGATION ORDONNANCE -->
        <div class="nav-ord-barre">
            <a href="?id=<?= $id ?>&ord=<?= $ordPremiere ?>" class="nav-btn" title="Première ordonnance">|◀</a>
            <a href="?id=<?= $id ?>&ord=<?= $ordPrev ?>"     class="nav-btn" title="Précédente">◀</a>
            <span style="font-size:12px;color:#1a4a7a;font-weight:bold;padding:3px 10px;white-space:nowrap;background:#f0f4f8;border-radius:4px;border:1px solid #dde3ea;"><?= (count($ordonnances) - $idxOrd) ?> / <?= count($ordonnances) ?></span>
            <a href="?id=<?= $id ?>&ord=<?= $ordNext ?>"     class="nav-btn" title="Suivante">▶</a>
            <a href="?id=<?= $id ?>&ord=<?= $ordDerniere ?>" class="nav-btn" title="Dernière">▶|</a>
            <button type="button" onclick="afficherNouvelleOrdonnance()" class="nav-btn" style="background:#27ae60;" title="Nouvelle ordonnance">✚</button>
			<a href="ordonnances.php?id=<?= $id ?>" class="nav-btn" style="background:#2e6da4;" title="Toutes les ordonnances">📋 Liste</a>
        <button type="button" onclick="afficherModifierOrdonnance()" class="nav-btn" style="background:#e67e22;" title="Modifier ordonnance">✏️</button>
		</div>

        </div><!-- FIN vue-ordonnance -->

        <?php else: ?>
            <p style="color:#999;font-size:12px;">Aucune ordonnance</p>
            <div class="nav-ord-barre">
                <a href="?id=<?= $id ?>&ord=<?= $ordPremiere ?>" class="nav-btn">|◀</a>
                <a href="?id=<?= $id ?>&ord=<?= $ordPrev ?>"     class="nav-btn">◀</a>
                <span style="font-size:12px;color:#1a4a7a;font-weight:bold;padding:3px 10px;white-space:nowrap;background:#f0f4f8;border-radius:4px;border:1px solid #dde3ea;">0 / 0</span>
                <a href="?id=<?= $id ?>&ord=<?= $ordNext ?>"     class="nav-btn">▶</a>
                <a href="?id=<?= $id ?>&ord=<?= $ordDerniere ?>" class="nav-btn">▶|</a>
                <button type="button" onclick="afficherNouvelleOrdonnance()" class="nav-btn" style="background:#27ae60;">✚</button>
            </div>
        <?php endif; ?>

    </div><!-- FIN card ordonnance -->
</div><!-- FIN col-mid -->

<!-- ══ POPUP NOUVELLE ORDONNANCE ══ -->
<div id="modal-nouvelle-ordonnance" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;overflow-y:auto;">
    <div style="background:white;border-radius:8px;padding:20px;margin:40px auto;max-width:700px;box-shadow:0 8px 32px rgba(0,0,0,0.3);position:relative;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;padding-bottom:10px;border-bottom:2px solid #27ae60;">
            <strong style="color:#27ae60;font-size:15px;">✚ Nouvelle ordonnance</strong>
            <button type="button" onclick="masquerNouvelleOrdonnance()" style="background:#e74c3c;color:white;border:none;border-radius:4px;padding:4px 12px;cursor:pointer;font-size:13px;">✕ Annuler</button>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
            <div>
                <label style="font-size:10px;color:#555;font-weight:bold;display:block;margin-bottom:4px;">DATE ORDONNANCE</label>
                <input type="date" id="no_date" value="<?= date('Y-m-d') ?>" style="width:100%;border:1px solid #cdd5de;border-radius:4px;padding:6px 8px;font-size:13px;">
            </div>
            <div>
                <label style="font-size:10px;color:#555;font-weight:bold;display:block;margin-bottom:4px;">ACTE</label>
                <input type="text" id="no_acte" placeholder="ECG, EDC..." oninput="syncActe(this.value,'no')"
                       style="width:100%;border:1px solid #cdd5de;border-radius:4px;padding:6px 8px;font-size:13px;margin-bottom:6px;">
                <div style="display:flex;gap:3px;flex-wrap:wrap;">
                    <?php foreach (['ECG','EDC','ECG+EDC','DTSA','ECG+DTSA','CONTROL','DVMI','BILAN'] as $ba): ?>
                    <button type="button" onclick="setActeRdv('<?= $ba ?>','no');" style="background:#8e44ad;color:white;border:none;padding:3px 8px;border-radius:3px;cursor:pointer;font-size:11px;"><?= $ba ?></button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div style="margin-bottom:12px;background:#f8f0ff;border-radius:6px;padding:10px;border:1px solid #c9a0f0;">
            <label style="font-size:10px;color:#8e44ad;font-weight:bold;display:block;margin-bottom:6px;">📅 DATE &amp; HEURE RDV</label>
            <input type="hidden" id="no_rdv"   value="">
            <input type="hidden" id="no_heure" value="">
            <div style="display:flex;gap:4px;flex-wrap:wrap;margin-bottom:6px;">
                <button type="button" onclick="rdvSetDelai(1,0,'no')"  style="background:#2e6da4;color:white;border:none;padding:3px 8px;border-radius:3px;cursor:pointer;font-size:11px;">1M</button>
                <button type="button" onclick="rdvSetDelai(3,0,'no')"  style="background:#1a4a7a;color:white;border:none;padding:3px 8px;border-radius:3px;cursor:pointer;font-size:11px;">3M</button>
                <button type="button" onclick="rdvSetDelai(6,0,'no')"  style="background:#1a4a7a;color:white;border:none;padding:3px 8px;border-radius:3px;cursor:pointer;font-size:11px;">6M</button>
                <button type="button" onclick="rdvSetDelai(0,7,'no')"  style="background:#27ae60;color:white;border:none;padding:3px 8px;border-radius:3px;cursor:pointer;font-size:11px;">7J</button>
                <button type="button" onclick="rdvSetDelai(0,15,'no')" style="background:#27ae60;color:white;border:none;padding:3px 8px;border-radius:3px;cursor:pointer;font-size:11px;">15J</button>
                <button type="button" onclick="rdvSetDelai(0,21,'no')" style="background:#27ae60;color:white;border:none;padding:3px 8px;border-radius:3px;cursor:pointer;font-size:11px;">21J</button>
            </div>
            <div style="display:flex;gap:8px;align-items:center;margin-bottom:6px;">
                <input type="date" id="no_rdv_visible" onchange="rdvDateChange(this.value,'no')"
                       style="flex:1;border:1px solid #8e44ad;border-radius:4px;padding:5px 8px;font-size:12px;">
                <div id="no_heure_affichage" style="background:#e8d5f5;color:#8e44ad;padding:5px 12px;border-radius:4px;font-size:13px;font-weight:bold;white-space:nowrap;">—:——</div>
            </div>
            <div class="jauge-jour" id="no_jauge" style="display:none;">
                <span id="no_jauge_txt" style="white-space:nowrap;color:#555;font-size:11px;"></span>
                <div class="jauge-bar"><div class="jauge-fill ok" id="no_jauge_fill" style="width:0%"></div></div>
            </div>
            <div class="creneaux-wrap">
                <div class="creneaux-loading" id="no_loading"  style="display:none;">⏳ Chargement…</div>
                <div class="creneaux-msg"     id="no_msg_rdv"  style="display:none;"></div>
                <div class="creneaux-grille"  id="no_grille"></div>
            </div>
        </div>
        <div style="font-size:12px;font-weight:bold;color:#1a4a7a;margin-bottom:8px;">💊 Médicaments :</div>
        <table style="width:100%;border-collapse:collapse;font-size:12px;">
            <thead style="background:#1a4a7a;color:white;">
                <tr>
                    <th style="padding:6px 8px;text-align:left;">Médicament</th>
                    <th style="padding:6px 8px;text-align:left;">Posologie</th>
                    <th style="padding:6px 8px;text-align:left;">Durée</th>
                    <th style="padding:6px 8px;width:30px;"></th>
                </tr>
            </thead>
            <tbody id="no_lignes"></tbody>
        </table>
        <div style="display:flex;gap:10px;margin-top:14px;align-items:center;">
            <button type="button" onclick="noAjouterLigne()" style="background:#2ecc71;color:white;border:none;border-radius:4px;padding:7px 14px;cursor:pointer;font-size:13px;">✚ Médicament</button>
            <button type="button" onclick="noEnregistrer(<?= $id ?>)" style="background:#1a4a7a;color:white;border:none;border-radius:4px;padding:7px 18px;cursor:pointer;font-size:13px;font-weight:600;">💾 Enregistrer</button>
            <span id="no_msg" style="font-size:12px;color:#27ae60;"></span>
        </div>
    </div>
</div>

<!-- ══ COLONNE DROITE : EXAMEN CLINIQUE ══ -->
<div class="col-right">
    <div class="card">
        <div class="card-title">🩺 Examen clinique</div>
        <?php if ($examen): ?>
        <?php
            $dateExamRaw = $examen['DateExam'] ?? null;
            $dateExamAff = '—';
            $dateExamStyle = 'font-size:11px;font-weight:bold;color:#1a4a7a;';
            if ($dateExamRaw) {
                $tsExam = strtotime($dateExamRaw);
                if ($tsExam && $tsExam > 86400) {
                    $dateExamAff = date('d/m/Y', $tsExam);
                    // Rouge si c'est aujourd'hui
                    if (date('Y-m-d', $tsExam) === date('Y-m-d')) {
                        $dateExamStyle = 'font-size:11px;font-weight:bold;color:#e74c3c;';
                    }
                }
            }
        ?>
        <div style="text-align:center;margin-bottom:8px;">
            <span style="font-size:9px;color:#888;text-transform:uppercase;display:block;">Date examen</span>
            <span style="<?= $dateExamStyle ?>"><?= $dateExamAff ?></span>
        </div>
        <?php
        $tas = (int)($examen['TAS'] ?? 0); $tad = (int)($examen['TAD'] ?? 0);
        $coulTA = '#333';
        if ($tas >= 140 || $tad >= 90) $coulTA = '#e74c3c';
        elseif ($tas >= 130 || $tad >= 80) $coulTA = '#f39c12';
        elseif ($tas > 0) $coulTA = '#27ae60';
        ?>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;">
            <div class="champ"><label>TAS/TAD</label><span class="ta-val" style="color:<?= $coulTA ?>"><?= ($tas && $tad) ? $tas.'/'.$tad : '—' ?></span></div>
            <div class="champ"><label>FC</label><span><?= htmlspecialchars($examen['FC'] ?? '—') ?></span></div>
            <div class="champ"><label>Poids</label><span><?= htmlspecialchars($examen['POIDS'] ?? '—') ?> kg</span></div>
            <div class="champ"><label>Taille</label><span><?= htmlspecialchars($examen['TAILLE'] ?? '—') ?> cm</span></div>
        </div>
        <?php else: ?>
            <p style="color:#999;font-size:12px;">Aucun examen enregistré</p>
        <?php endif; ?>
		<!-- ══ ECG COMPACT ══ -->
    <div class="card" style="padding:6px;">
        <div class="card-title" style="font-size:11px;margin-bottom:4px;">
            ⚡ ECG
            <div class="nav-btns" style="gap:2px;">
                <a href="?id=<?= $id ?>&ecg=<?= $ecgs ? $ecgs[count($ecgs)-1]['N°'] : 0 ?>" class="nav-btn" style="padding:1px 4px;font-size:10px;">|◀</a>
                <a href="?id=<?= $id ?>&ecg=<?= $ecgs && $idxECG < count($ecgs)-1 ? $ecgs[$idxECG+1]['N°'] : $nECG ?>" class="nav-btn" style="padding:1px 4px;font-size:10px;">◀</a>
                <span style="font-size:10px;color:#1a4a7a;font-weight:bold;padding:0 3px;white-space:nowrap;"><?= count($ecgs) ? ($idxECG+1).' / '.count($ecgs) : '0' ?></span>
                <a href="?id=<?= $id ?>&ecg=<?= $ecgs && $idxECG > 0 ? $ecgs[$idxECG-1]['N°'] : $nECG ?>" class="nav-btn" style="padding:1px 4px;font-size:10px;">▶</a>
                <a href="?id=<?= $id ?>&ecg=<?= $ecgs ? $ecgs[0]['N°'] : 0 ?>" class="nav-btn" style="padding:1px 4px;font-size:10px;">▶|</a>
                <a href="nouveau_ecg.php?id=<?= $id ?>" class="nav-btn" style="background:#27ae60;padding:1px 4px;font-size:10px;">✚</a>
            </div>
        </div>
        <?php if ($ecgCourant): ?>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:3px;">
            <div><span style="font-size:9px;color:#888;text-transform:uppercase;display:block;">Date ECG</span>
                <span style="font-size:11px;font-weight:bold;color:#1a4a7a;"><?= $ecgCourant['Date ECG'] ? date('d/m/Y', strtotime($ecgCourant['Date ECG'])) : '—' ?></span></div>
            <div><span style="font-size:9px;color:#888;text-transform:uppercase;display:block;">Fréquence</span>
                <span style="font-size:11px;"><?= htmlspecialchars($ecgCourant['FREQUENCE'] ?? '—') ?></span></div>
            <div><span style="font-size:9px;color:#888;text-transform:uppercase;display:block;">Rythme</span>
                <span style="font-size:11px;"><?= htmlspecialchars($ecgCourant['trouble de rythme'] ?? '—') ?></span></div>
            <div><span style="font-size:9px;color:#888;text-transform:uppercase;display:block;">Supra vent.</span>
                <span style="font-size:11px;"><?= htmlspecialchars($ecgCourant['RYTHME SUPRA VENTRICULAIRE'] ?? '—') ?></span></div>
            <div><span style="font-size:9px;color:#888;text-transform:uppercase;display:block;">Seg. ST</span>
                <span style="font-size:11px;"><?= htmlspecialchars($ecgCourant['SEGMENT ST'] ?? '—') ?></span></div>
            <div><span style="font-size:9px;color:#888;text-transform:uppercase;display:block;">Repolarisation</span>
                <span style="font-size:11px;"><?= htmlspecialchars($ecgCourant['LA REPOLARISATION'] ?? '—') ?></span></div>
            <div><span style="font-size:9px;color:#888;text-transform:uppercase;display:block;">IDM</span>
                <span style="font-size:11px;"><?= htmlspecialchars($ecgCourant['IDM'] ?? '—') ?></span></div>
            <div><span style="font-size:9px;color:#888;text-transform:uppercase;display:block;">C/C</span>
                <span style="font-size:11px;"><?= htmlspecialchars($ecgCourant['C/C'] ?? '—') ?></span></div>
        </div>
        <?php else: ?>
            <p style="color:#999;font-size:11px;">Aucun ECG enregistré</p>
        <?php endif; ?>
    </div>

    <!-- ══ ECHO-DOPPLER COMPACT ══ -->
    <div class="card" style="padding:6px;">
        <div class="card-title" style="font-size:11px;margin-bottom:4px;">
            🫀 Echo-Doppler
            <div class="nav-btns" style="gap:2px;">
                <a href="?id=<?= $id ?>&echo=<?= $echos ? $echos[count($echos)-1]['N°'] : 0 ?>" class="nav-btn" style="padding:1px 4px;font-size:10px;">|◀</a>
                <a href="?id=<?= $id ?>&echo=<?= $echos && $idxEcho < count($echos)-1 ? $echos[$idxEcho+1]['N°'] : $nEcho ?>" class="nav-btn" style="padding:1px 4px;font-size:10px;">◀</a>
                <span style="font-size:10px;color:#1a4a7a;font-weight:bold;padding:0 3px;white-space:nowrap;"><?= count($echos) ? ($idxEcho+1).' / '.count($echos) : '0' ?></span>
                <a href="?id=<?= $id ?>&echo=<?= $echos && $idxEcho > 0 ? $echos[$idxEcho-1]['N°'] : $nEcho ?>" class="nav-btn" style="padding:1px 4px;font-size:10px;">▶</a>
                <a href="?id=<?= $id ?>&echo=<?= $echos ? $echos[0]['N°'] : 0 ?>" class="nav-btn" style="padding:1px 4px;font-size:10px;">▶|</a>
                <a href="nouveau_echo.php?id=<?= $id ?>" class="nav-btn" style="background:#27ae60;padding:1px 4px;font-size:10px;">✚</a>
            </div>
        </div>
        <?php if ($echoCourant): ?>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:3px;">
            <div><span style="font-size:9px;color:#888;text-transform:uppercase;display:block;">Date Echo</span>
                <span style="font-size:11px;font-weight:bold;color:#1a4a7a;"><?= $echoCourant['DATEchog'] ? date('d/m/Y', strtotime($echoCourant['DATEchog'])) : '—' ?></span></div>
            <div><span style="font-size:9px;color:#888;text-transform:uppercase;display:block;">FEVG</span>
                <span style="font-size:11px;"><?= htmlspecialchars($echoCourant['FEVG'] ?? '—') ?></span></div>
            <div><span style="font-size:9px;color:#888;text-transform:uppercase;display:block;">DTD-VG</span>
                <span style="font-size:11px;"><?= htmlspecialchars($echoCourant['DTD-VG'] ?? '—') ?></span></div>
            <div><span style="font-size:9px;color:#888;text-transform:uppercase;display:block;">S,OG</span>
                <span style="font-size:11px;"><?= htmlspecialchars($echoCourant['S,OG'] ?? '—') ?></span></div>
            <div><span style="font-size:9px;color:#888;text-transform:uppercase;display:block;">Cinétique</span>
                <span style="font-size:11px;"><?= htmlspecialchars($echoCourant['CINETIQUE'] ?? '—') ?></span></div>
            <div><span style="font-size:9px;color:#888;text-transform:uppercase;display:block;">AO ASC</span>
                <span style="font-size:11px;"><?= htmlspecialchars($echoCourant['AO ASC,'] ?? '—') ?></span></div>
            <div style="grid-column:1/-1;"><span style="font-size:9px;color:#888;text-transform:uppercase;display:block;">Doppler</span>
                <span style="font-size:11px;"><?= htmlspecialchars($echoCourant['DOPPLER'] ?? '—') ?></span></div>
            <div style="grid-column:1/-1;"><span style="font-size:9px;color:#888;text-transform:uppercase;display:block;">DTSA</span>
                <span style="font-size:11px;"><?= htmlspecialchars($echoCourant['DOPPLER DES TRONCS SUPRA AORTIQUES'] ?? '—') ?></span></div>
        </div>
        <?php else: ?>
            <p style="color:#999;font-size:11px;">Aucun Echo enregistré</p>
        <?php endif; ?>
    </div>
    </div>
	
</div>

</div><!-- FIN .main -->

<!-- BAS DE PAGE : ECG + ECHO -->


<script>
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
document.getElementById('rech-patient').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        const val = this.value.trim();
        if (/^\d+$/.test(val)) window.location.href = 'dossier.php?id=' + val;
    }
});
document.addEventListener('click', e => {
    if (!e.target.closest('#rech-patient') && !e.target.closest('#rech-suggestions'))
        document.getElementById('rech-suggestions').style.display = 'none';
});

function diagUpdate(type, nDic, valeur) {
    fetch('ajax_maj_diagnostic.php', { method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ action:'update', type, n_dic:nDic, valeur }) });
}
function diagDelete(type, nDic, patId, btn) {
    if (!confirm('Supprimer ce diagnostic ?')) return;
    fetch('ajax_maj_diagnostic.php', { method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ action:'delete', type, n_dic:nDic, id:patId }) })
    .then(r => r.json()).then(data => { if (data.success) btn.closest('.diag-ligne').remove(); else alert('❌ '+data.error); });
}
function diagAjouter(type, patId, liste) {
    const input = document.getElementById('new_diag_' + type);
    const valeur = input.value.trim();
    if (!valeur) return;
    const bloc = document.getElementById('diag_' + type);
    const dejaDans = Array.from(bloc.querySelectorAll('input[type=text]')).some(inp => inp.value.trim().toLowerCase() === valeur.toLowerCase());
    if (dejaDans) { alert('⚠️ Ce diagnostic est déjà dans la liste de ce patient.'); input.value = ''; return; }
    const existe = liste.some(d => d.toLowerCase() === valeur.toLowerCase());
    if (!existe && !confirm(`"${valeur}" n'existe pas dans la liste.\nVoulez-vous l'ajouter comme nouveau diagnostic ?`)) return;
    fetch('ajax_maj_diagnostic.php', { method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ action:'add', type, id:patId, valeur }) })
    .then(r => r.json()).then(data => {
        if (data.success) {
            const vide = bloc.querySelector('.diag-vide'); if (vide) vide.remove();
            const div = document.createElement('div');
            div.className = 'diag-ligne'; div.dataset.pk = data.n_dic;
            div.innerHTML = `<input type="text" value="${valeur.replace(/"/g,'&quot;')}" list="datalist_diag_${type}"
                onblur="diagUpdate(${type},${data.n_dic},this.value)"
                style="flex:1;border:1px solid #ddd;border-radius:3px;padding:3px 5px;font-size:12px;">
                <button type="button" onclick="diagDelete(${type},${data.n_dic},${patId},this)"
                style="background:#e74c3c;color:white;border:none;border-radius:3px;padding:2px 6px;cursor:pointer;font-size:11px;flex-shrink:0;">✕</button>`;
            bloc.appendChild(div); input.value = '';
            if (!existe) { const dl=document.getElementById('datalist_diag_'+type); if(dl){const o=document.createElement('option');o.value=valeur;dl.appendChild(o);} }
        } else alert('❌ '+data.error);
    });
}

function sauvegarderChamp(champ, valeur) {
    const s = document.getElementById('dossier_status');
    if (s) { s.textContent='⏳ Enregistrement…'; s.style.color='#888'; }
    fetch('ajax_maj_dossier.php', { method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ id:<?= $id ?>, champ, valeur }) })
    .then(r=>r.json()).then(data => {
        if (s) { s.textContent=data.success?'✅ Enregistré':'❌ Erreur'; s.style.color=data.success?'#27ae60':'#e74c3c';
            if(data.success) setTimeout(()=>{s.textContent='';},2000); }
    }).catch(()=>{ if(s){s.textContent='❌ Erreur réseau';s.style.color='#e74c3c';} });
}

function afficherDatesActe(nomActe, dates) {
    document.getElementById('popup-dates-titre').textContent = nomActe + ' — ' + dates.length + ' réalisation(s)';
    const liste = document.getElementById('popup-dates-liste');
    liste.innerHTML = dates.length ? dates.map(d=>`<div>• ${d}</div>`).join('') : '<div style="color:#aaa;">Aucune date enregistrée</div>';
    document.getElementById('popup-dates-acte').style.display = 'block';
}
document.addEventListener('click', function(e) {
    const btn = e.target.closest('.hist-acte-btn');
    if (btn) { afficherDatesActe(btn.dataset.acte, JSON.parse(btn.dataset.dates||'[]')); }
    const popup = document.getElementById('popup-dates-acte');
    if (popup && popup.style.display!=='none' && !popup.contains(e.target) && !e.target.closest('.hist-acte-btn'))
        popup.style.display='none';
});

function majDateFacture(nFact, val) {
    fetch('ajax_maj_facture.php', { method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({n_facture:nFact, date_facture:val}) });
}
function majDateActe(nAacte, val) {
    fetch('ajax_maj_acte.php', { method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({n_aacte:nAacte, date_H:val}) });
}
function calcNbrJ() {
    const d1=document.getElementById('cert_debut').value, d2=document.getElementById('cert_fin').value;
    if (d1&&d2) { const diff=Math.round((new Date(d2)-new Date(d1))/86400000); document.getElementById('cert_nbrj').value=diff>=0?diff:0; }
}
function imprimerCertificat() {
    const debut = document.getElementById('cert_debut').value;
    const fin   = document.getElementById('cert_fin').value;
    const nbrj  = document.getElementById('cert_nbrj').value;

    const nom    = <?= json_encode($patient['NOMPRENOM'] ?? '') ?>;
    const age    = <?= json_encode($age) ?>;
    const dateAuj = new Date().toLocaleDateString('fr-FR');

    // Formatage des dates
    function fmtDate(d) {
        if (!d) return '___________';
        const p = d.split('-');
        return p[2]+'/'+p[1]+'/'+p[0];
    }

    let texteArret = '';
    if (debut && fin) {
        texteArret = `du ${fmtDate(debut)} au ${fmtDate(fin)} inclus (${nbrj} jour(s))`;
    } else if (debut) {
        texteArret = `à partir du ${fmtDate(debut)}`;
    }

    const contenu = `<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Certificat médical</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: Arial, sans-serif;
    font-size: 13px;
    color: #000;
    background: #fff;
    padding: 60px 80px;
    font-weight: normal;
  }
  h1 {
    font-size: 16px;
    font-weight: normal;
    text-align: center;
    text-decoration: underline;
    margin-bottom: 40px;
    letter-spacing: 1px;
  }
  p { line-height: 2; margin-bottom: 12px; }
  .signature {
    margin-top: 60px;
    text-align: right;
    font-size: 12px;
  }
  @media print {
    body { padding: 40px 60px; }
    button { display: none; }
  }
</style>
</head>
<body>
<h1>Certificat médical</h1>

<p>Je soussigné, médecin, certifie avoir examiné ce jour M./Mme <strong style="font-weight:normal;text-decoration:underline;">${nom}</strong>, âgé(e) de ${age} ans.</p>

<p>À l'issue de cet examen, il/elle est en repos médical ${texteArret}.</p>

<p>Ce certificat est établi sur sa demande et remis en main propre à l'intéressé(e) pour faire valoir ce que de droit.</p>

<div class="signature">
    <p>Tétouan, le ${dateAuj}</p>
    <br><br>
    <p>Signature et cachet du médecin</p>
</div>

<div style="margin-top:30px;text-align:center;">
    <button onclick="window.print()" style="padding:6px 20px;font-size:12px;cursor:pointer;border:1px solid #000;background:white;">🖨️ Imprimer</button>
    <button onclick="window.close()" style="padding:6px 20px;font-size:12px;cursor:pointer;border:1px solid #ccc;background:white;margin-left:10px;">✕ Fermer</button>
</div>
</body>
</html>`;

    const w = window.open('', '_blank', 'width=750,height=600');
    w.document.write(contenu);
    w.document.close();
}

function reportTraitement(mois, patientId) {
    if (!confirm(`Confirmer le report du traitement dans ${mois} mois ?`)) return;
    fetch('ajax_report_traitement.php', { method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ id:patientId, mois }) })
    .then(r=>r.json()).then(data => {
        if (data.success) window.location.href=`dossier.php?id=${patientId}&ord=${data.n_ordon}`;
        else alert('❌ '+data.error);
    }).catch(()=>alert('❌ Erreur réseau'));
}
// ════════════════════════════════════════════════════════════
// UTILITAIRE date
// ════════════════════════════════════════════════════════════
function dateEnFr(d) {
    if (!d) return '';
    const [a,m,j] = d.split('-');
    return j+'/'+m+'/'+a;
}

// ════════════════════════════════════════════════════════════
// MODALE JOUR FERMÉ / SPÉCIAL
// ════════════════════════════════════════════════════════════
function jfFermer() {
    const m = document.getElementById('modal-jour-ferme');
    if (m) m.remove();
}
function jfAfficher(data, onChoix) {
    jfFermer();
    const estSamedi = data.est_samedi || false;
    const estLundi  = data.est_lundi  || false;
    const raison    = data.raison     || 'Jour fermé';
    let titre, sousTitre;
    if (estLundi) {
        titre     = '⚠️ Lundi — Habituellement non travaillé';
        sousTitre = 'Le lundi est généralement réservé. Que souhaitez-vous faire ?';
    } else if (estSamedi) {
        titre     = '⚠️ Samedi — Demi-journée habituelle';
        sousTitre = 'Le samedi est particulier. Que souhaitez-vous faire ?';
    } else {
        titre     = '⛔ ' + raison + ' — Cabinet fermé';
        sousTitre = 'Ce jour est fermé. Choisissez une alternative :';
    }
    const base = 'border:none;border-radius:6px;padding:8px 14px;cursor:pointer;font-size:12px;font-weight:bold;';
    let btns = '';
    if (data.date_avant)
        btns += `<button style="${base}background:#2e6da4;color:white;" onclick="jfChoisir('${data.date_avant}')">◀ ${data.label_avant}</button>`;
    if ((estLundi || estSamedi) && data.date_cible) {
        const lbl = estLundi ? 'Garder lundi' : 'Garder samedi';
        btns += `<button style="${base}background:#e67e22;color:white;" onclick="jfChoisir('${data.date_cible}')">${lbl}</button>`;
    }
    if (data.date_apres)
        btns += `<button style="${base}background:#1a4a7a;color:white;" onclick="jfChoisir('${data.date_apres}')">${data.label_apres} ▶</button>`;
    btns += `<button style="${base}background:#555;color:white;" onclick="jfChoisirDate()">📅 Choisir date</button>`;
    btns += `<button style="${base}background:#ddd;color:#444;" onclick="jfFermer()">✕ Annuler</button>`;

    document.body.insertAdjacentHTML('beforeend', `
    <div id="modal-jour-ferme" style="position:fixed;top:0;left:0;width:100%;height:100%;
         background:rgba(0,0,0,0.5);z-index:9999;display:flex;align-items:center;justify-content:center;">
        <div style="background:white;border-radius:10px;padding:24px 28px;
                    max-width:500px;width:92%;box-shadow:0 10px 40px rgba(0,0,0,0.3);">
            <div style="font-size:14px;font-weight:bold;color:#1a4a7a;margin-bottom:6px;">${titre}</div>
            <div style="font-size:12px;color:#666;margin-bottom:18px;">${sousTitre}</div>
            <div id="jf-btns" style="display:flex;flex-wrap:wrap;gap:8px;">${btns}</div>
            <div id="jf-datepicker" style="display:none;margin-top:14px;">
                <input type="date" id="jf-input-date"
                       style="padding:5px 8px;border:1px solid #2e6da4;border-radius:4px;font-size:12px;">
                <button style="${base}background:#1a4a7a;color:white;margin-left:8px;" onclick="jfConfirmerDate()">✔ Confirmer</button>
            </div>
        </div>
    </div>`);
    window._jfCallback = onChoix;
}
function jfChoisir(date) {
    jfFermer();
    if (window._jfCallback) { window._jfCallback(date); window._jfCallback = null; }
}
function jfChoisirDate() {
    document.getElementById('jf-datepicker').style.display = 'block';
    document.getElementById('jf-btns').style.display = 'none';
}
function jfConfirmerDate() {
    const d = document.getElementById('jf-input-date').value;
    if (!d) return;
    jfFermer();
    if (window._jfCallback) { window._jfCallback(d); window._jfCallback = null; }
}

// ════════════════════════════════════════════════════════════
// VÉRIFIER UNE DATE via ajax_prochain_jour.php
// ════════════════════════════════════════════════════════════
function verifierEtAppliquerDate(dateCible, prefixe, callback) {
    fetch('ajax_prochain_jour.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ date_cible: dateCible })
    })
    .then(r => r.json())
    .then(data => {
        if (data.error) { alert('❌ ' + data.error); return; }
        if (data.ok) {
            // Jour libre → appliquer directement
            callback(data.date_trouvee);
        } else {
            // Fermé / samedi / lundi → modale
            jfAfficher(
                { ...data, date_cible: dateCible },
                (dateChoisie) => verifierEtAppliquerDate(dateChoisie, prefixe, callback)
            );
        }
    })
    .catch(() => alert('❌ Erreur réseau'));
}

// ════════════════════════════════════════════════════════════
// APPLIQUER une date validée dans les champs RDV
// ════════════════════════════════════════════════════════════
function appliquerDateRdv(date, prefixe) {
    ['rdv_futur','no_rdv'].forEach(id => { const el=document.getElementById(id); if(el) el.value=date; });
    ['rdv_futur_visible','no_rdv_visible'].forEach(id => { const el=document.getElementById(id); if(el) el.value=date; });
    ['heure_rdv_futur','no_heure'].forEach(id => { const el=document.getElementById(id); if(el) el.value=''; });
    rdvChargerCreneaux(date, prefixe, true);
}

// ════════════════════════════════════════════════════════════
// FONCTIONS PUBLIQUES
// ════════════════════════════════════════════════════════════
function reportTraitement(mois, patientId) {
    if (!confirm(`Confirmer le report du traitement dans ${mois} mois ?`)) return;
    fetch('ajax_report_traitement.php', { method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ id:patientId, mois }) })
    .then(r=>r.json()).then(data => {
        if (data.success) window.location.href=`dossier.php?id=${patientId}&ord=${data.n_ordon}`;
        else alert('❌ '+data.error);
    }).catch(()=>alert('❌ Erreur réseau'));
}

function confirmerRdv(nOrdon) {
    const dateRdv  = document.getElementById('rdv_futur')?.value;
    const heureRdv = document.getElementById('heure_rdv_futur')?.value || '';
    if (!dateRdv) { alert('Veuillez choisir une date de RDV'); return; }
    verifierEtAppliquerDate(dateRdv, 'rdv', (dateFin) => {
        const dateFr = dateEnFr(dateFin);
        fetch('ajax_maj_rdv.php', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({ n_ordon:nOrdon, date_rdv:dateFin, heure_rdv:heureRdv })
        })
        .then(r=>r.json())
        .then(data => {
            if (data.success) {
                alert('✅ RDV enregistré : ' + dateFr + (heureRdv ? ' à ' + heureRdv : ''));
                location.reload();
            } else alert('❌ Erreur : ' + data.error);
        });
    });
}
function rdvIds(p) {
    if (p==='rdv') return { dateH:'rdv_futur', dateV:'rdv_futur_visible', heureH:'heure_rdv_futur',
        grille:'rdv_grille', loading:'rdv_loading', msg:'rdv_msg',
        jauge:'rdv_jauge', jaugeTxt:'rdv_jauge_txt', jaugeFill:'rdv_jauge_fill', acte:'acte_rdv_futur' };
    return { dateH:'no_rdv', dateV:'no_rdv_visible', heureH:'no_heure',
        grille:'no_grille', loading:'no_loading', msg:'no_msg_rdv',
        jauge:'no_jauge', jaugeTxt:'no_jauge_txt', jaugeFill:'no_jauge_fill', acte:'no_acte' };
}
function syncActe(val, source) { const el=document.getElementById(source==='rdv'?'no_acte':'acte_rdv_futur'); if(el) el.value=val; }
function setActeRdv(val, prefixe) { const ids=rdvIds(prefixe); document.getElementById(ids.acte).value=val; syncActe(val,prefixe); }

function rdvChargerCreneaux(date, prefixe, heureAuto) {
    const ids = rdvIds(prefixe);
    const grille  = document.getElementById(ids.grille);
    const loading = document.getElementById(ids.loading);
    const msgEl   = document.getElementById(ids.msg);
    const jaugeEl = document.getElementById(ids.jauge);

    grille.innerHTML = '';
    msgEl.style.display   = 'none';
    loading.style.display = 'block';
    jaugeEl.style.display = 'none';

    if (heureAuto) {
        document.getElementById(ids.heureH).value = '';
        const ha  = document.getElementById('rdv_heure_affichage');
        const han = document.getElementById('no_heure_affichage');
        if (ha)  ha.textContent  = '—:——';
        if (han) han.textContent = '—:——';
    }

    fetch('ajax_creneaux.php?date=' + date).then(r => r.json()).then(data => {
        loading.style.display = 'none';

        if (!data.date_ok) {
            msgEl.textContent = '⛔ ' + data.raison;
            msgEl.style.display = 'block';
            document.getElementById(ids.dateH).value  = '';
            document.getElementById(ids.dateV).value  = '';
            document.getElementById(ids.heureH).value = '';
            return;
        }

        if (data.jour_complet) {
            msgEl.textContent = '⛔ Journée complète (' + data.total_jour + '/' + data.max_jour + ' patients).';
            msgEl.style.display = 'block';
        }

        const pct = Math.min(100, Math.round(data.total_jour / data.max_jour * 100));
        const cl  = pct < 60 ? 'ok' : pct < 90 ? 'warn' : 'full';
        document.getElementById(ids.jaugeTxt).textContent = data.total_jour + ' / ' + data.max_jour + ' patients';
        const fill = document.getElementById(ids.jaugeFill);
        fill.style.width = pct + '%';
        fill.className   = 'jauge-fill ' + cl;
        jaugeEl.style.display = 'flex';

        const heureActuelle = document.getElementById(ids.heureH).value;
        data.creneaux.forEach(c => {
            const btn = document.createElement('button');
            btn.type        = 'button';
            btn.textContent = c.heure;
            btn.className   = 'creneau-btn ' + c.statut;
            btn.title       = c.nb + ' patient(s)';
            if (c.statut === 'plein') {
                btn.disabled = true;
            } else {
                btn.onclick = () => rdvSelectionnerCreneau(c.heure, prefixe);
            }
            if (c.heure === heureActuelle) btn.classList.add('selectionne');
            grille.appendChild(btn);
        });

        if (heureAuto && data.premier_libre) {
    setTimeout(() => {
        
        rdvSelectionnerCreneau(data.premier_libre, prefixe);
        
        
    }, 100);
}

        const actesSugg = <?= json_encode($acteSugActuel) ?>;
        let divActes = document.getElementById('rdv_actes_sugg_' + prefixe);
        if (!divActes) {
            divActes = document.createElement('div');
            divActes.id = 'rdv_actes_sugg_' + prefixe;
            divActes.style.cssText = 'margin-top:6px;';
            grille.parentNode.appendChild(divActes);
        }
        divActes.innerHTML = '';

        if (actesSugg.length > 0) {
            const lbl = document.createElement('div');
            lbl.style.cssText = 'font-size:10px;color:#e74c3c;font-weight:bold;margin-bottom:3px;';
            lbl.textContent   = '⚠ Actes suggérés :';
            divActes.appendChild(lbl);
            const wrap = document.createElement('div');
            wrap.style.cssText = 'display:flex;flex-wrap:wrap;gap:4px;';
            actesSugg.forEach(acte => {
                const badge = document.createElement('button');
                badge.type          = 'button';
                badge.textContent   = acte;
                badge.title         = 'Cliquer pour sélectionner cet acte';
                badge.style.cssText = 'background:#e74c3c;color:white;border:none;padding:2px 10px;border-radius:10px;font-size:11px;font-weight:bold;cursor:pointer;';
                badge.onclick = () => setActeRdv(acte, prefixe);
                wrap.appendChild(badge);
            });
            divActes.appendChild(wrap);
        } else {
            const ok = document.createElement('div');
            ok.style.cssText = 'font-size:11px;color:#27ae60;font-weight:bold;margin-top:4px;';
            ok.textContent   = '✅ Actes à jour';
            divActes.appendChild(ok);
        }

    }).catch(() => {
        loading.style.display = 'none';
        msgEl.textContent     = '❌ Erreur de connexion';
        msgEl.style.display   = 'block';
    });
}
function rdvSelectionnerCreneau(heure, prefixe) {
    const ids=rdvIds(prefixe);
    document.getElementById(ids.heureH).value=heure;
    const ha=document.getElementById('rdv_heure_affichage'); if(ha) { ha.textContent=heure;   }
    const han=document.getElementById('no_heure_affichage'); if(han) han.textContent=heure;
    const autreHEl=document.getElementById(prefixe==='rdv'?'no_heure':'heure_rdv_futur'); if(autreHEl) autreHEl.value=heure;
    const grille=document.getElementById(ids.grille);
    grille.querySelectorAll('.creneau-btn').forEach(b=>b.classList.remove('selectionne'));
    grille.querySelectorAll('.creneau-btn').forEach(b=>{ if(b.textContent===heure) b.classList.add('selectionne'); });
    const autreGrille=document.getElementById(prefixe==='rdv'?'no_grille':'rdv_grille');
    if(autreGrille) { autreGrille.querySelectorAll('.creneau-btn').forEach(b=>{b.classList.remove('selectionne');if(b.textContent===heure&&!b.disabled)b.classList.add('selectionne');}); }
}

function rdvDateChange(date, prefixe) {
    if (!date || !/^\d{4}-\d{2}-\d{2}$/.test(date) || date==='1970-01-01') return;
    const ids = rdvIds(prefixe);
    document.getElementById(ids.dateH).value = date;
    verifierEtAppliquerDate(date, prefixe, (dateFin) => appliquerDateRdv(dateFin, prefixe));
}

function rdvSetDelai(mois, jours, prefixe) {
    const d = new Date();
    if (mois)  d.setMonth(d.getMonth() + mois);
    if (jours) d.setDate(d.getDate() + jours);
    const dateCible = d.toISOString().split('T')[0];
    const ids = rdvIds(prefixe);
    const loading = document.getElementById(ids.loading);
    const grille  = document.getElementById(ids.grille);
    const msgEl   = document.getElementById(ids.msg);
    grille.innerHTML = ''; msgEl.style.display = 'none';
    loading.style.display = 'block'; loading.textContent = '⏳ Vérification…';
    verifierEtAppliquerDate(dateCible, prefixe, (dateFin) => {
        loading.style.display = 'none'; loading.textContent = '⏳ Chargement…';
        appliquerDateRdv(dateFin, prefixe);
    });
}

function afficherNouvelleOrdonnance() {
    document.getElementById('modal-nouvelle-ordonnance').style.display='block';
    document.body.style.overflow='hidden';
    if(document.getElementById('no_lignes').children.length===0) noAjouterLigne();
    const de=document.getElementById('no_rdv').value;
    if(de&&document.getElementById('no_grille').children.length===0) rdvChargerCreneaux(de,'no',false);
}
function masquerNouvelleOrdonnance() {
    document.getElementById('modal-nouvelle-ordonnance').style.display='none';
    document.body.style.overflow='';
}
document.addEventListener('DOMContentLoaded', ()=>{
    const modal=document.getElementById('modal-nouvelle-ordonnance');
    if(modal) modal.addEventListener('click',e=>{if(e.target===modal)masquerNouvelleOrdonnance();});
});

const noMeds = <?= json_encode(array_map(fn($m)=>['id'=>$m['NuméroPRODUIT'],'nom'=>$m['PRODUIT']],$listeMeds)) ?>;
const noPosologies = <?= json_encode($posologies) ?>;
const noDurees     = <?= json_encode($durees) ?>;
let noIdx=0;
function noAjouterLigne() {
    const i=noIdx++;
    let optsMed='<option value="">— Médicament —</option>';
    noMeds.forEach(m=>{optsMed+=`<option value="${m.id}">${m.nom}</option>`;});
    let optsPoso='<option value="">— Posologie —</option>';
    noPosologies.forEach(p=>{optsPoso+=`<option value="${p}">${p}</option>`;});
    let optsDuree='<option value="">— Durée —</option>';
    noDurees.forEach(d=>{optsDuree+=`<option value="${d}">${d}</option>`;});
    const tr=document.createElement('tr'); tr.style.borderBottom='1px solid #eee';
    tr.innerHTML=`<td style="padding:3px 4px;"><select id="no_med_${i}" style="width:100%;border:1px solid #ddd;border-radius:3px;padding:3px;font-size:11px;">${optsMed}</select></td>
        <td style="padding:3px 4px;"><select id="no_poso_${i}" style="width:100%;border:1px solid #ddd;border-radius:3px;padding:3px;font-size:11px;">${optsPoso}</select></td>
        <td style="padding:3px 4px;"><select id="no_duree_${i}" style="width:100%;border:1px solid #ddd;border-radius:3px;padding:3px;font-size:11px;">${optsDuree}</select></td>
        <td style="padding:3px 4px;"><button type="button" onclick="this.closest('tr').remove()" style="background:#e74c3c;color:white;border:none;border-radius:3px;padding:2px 6px;cursor:pointer;font-size:10px;">✕</button></td>`;
    document.getElementById('no_lignes').appendChild(tr);
}

function noEnregistrer(patientId) {
    const date_ordon=document.getElementById('no_date').value;
    const acte=document.getElementById('no_acte').value;
    const date_rdv=document.getElementById('no_rdv').value;
    const heure_rdv=document.getElementById('no_heure').value;
    const msgEl=document.getElementById('no_msg');
    const lignes=[];
    if (!date_ordon) { msgEl.textContent='⛔ La date d\'ordonnance est obligatoire.'; msgEl.style.color='#e74c3c'; document.getElementById('no_date').style.border='2px solid #e74c3c'; document.getElementById('no_date').focus(); return; }
    document.getElementById('no_date').style.border='';
    if (!date_rdv) { msgEl.textContent='⛔ La date de RDV est obligatoire.'; msgEl.style.color='#e74c3c'; document.getElementById('no_rdv_visible').style.border='2px solid #e74c3c'; return; }
    document.getElementById('no_rdv_visible').style.border='';
    if (!heure_rdv) { msgEl.textContent='⛔ L\'heure de RDV est obligatoire.'; msgEl.style.color='#e74c3c'; return; }
    document.querySelectorAll('#no_lignes tr').forEach(tr=>{
        const idx=tr.querySelector('select')?.id?.replace('no_med_',''); if(!idx) return;
        const med=document.getElementById(`no_med_${idx}`)?.value;
        const poso=document.getElementById(`no_poso_${idx}`)?.value;
        const duree=document.getElementById(`no_duree_${idx}`)?.value;
        if(med) lignes.push({med,poso,duree});
    });
    msgEl.textContent='Enregistrement…'; msgEl.style.color='#999';
    fetch('ajax_nouvelle_ordonnance.php',{method:'POST',headers:{'Content-Type':'application/json'},
        body:JSON.stringify({id:patientId,date_ordon,acte,date_rdv,heure_rdv,lignes})})
    .then(r=>r.json()).then(data=>{
        if(data.success) window.location.href=`dossier.php?id=${patientId}&ord=${data.n_ordon}`;
        else { document.getElementById('no_msg').textContent='❌ '+data.error; document.getElementById('no_msg').style.color='#e74c3c'; }
    }).catch(()=>{document.getElementById('no_msg').textContent='❌ Erreur réseau';document.getElementById('no_msg').style.color='#e74c3c';});
}

const nfActes = <?= json_encode(array_map(fn($a)=>['n_acte'=>$a['n_acte'],'ACTE'=>$a['ACTE'],'cout'=>(float)$a['cout']],$listeActes)) ?>;
let nfIdx=0;
function toggleNouvelleFacture() {
    const form=document.getElementById('formNouvelleFacture'), aff=document.getElementById('fact-affichage');
    const visible=form.style.display!=='none';
    form.style.display=visible?'none':'block';
    if(aff) aff.style.display=visible?'block':'none';
    if(!visible&&document.getElementById('nf_lignes').children.length===0) nfAjouterLigne();
}
function nfAjouterLigne() {
    const i=nfIdx++; const today=document.getElementById('nf_date').value;
    let opts='<option value="">— Acte —</option>';
    nfActes.forEach(a=>{opts+=`<option value="${a.n_acte}" data-cout="${a.cout}">${a.ACTE}</option>`;});
    const tr=document.createElement('tr'); tr.style.borderBottom='1px solid #eee';
    tr.innerHTML=`<td style="padding:3px 4px;"><input type="date" id="nf_dateacte_${i}" value="${today}" style="border:1px solid #ddd;border-radius:3px;padding:2px;font-size:11px;width:105px;"></td>
        <td style="padding:3px 4px;"><select id="nf_acte_${i}" onchange="nfRemplirPrix(${i})" style="width:100%;border:1px solid #ddd;border-radius:3px;padding:2px;font-size:11px;">${opts}</select></td>
        <td style="padding:3px 4px;"><input type="number" id="nf_prix_${i}" min="0" step="0.01" placeholder="0" oninput="nfRecalculer(${i})" style="width:70px;border:1px solid #ddd;border-radius:3px;padding:2px;font-size:11px;text-align:right;"></td>
        <td style="padding:3px 4px;"><input type="number" id="nf_verse_${i}" min="0" step="0.01" value="0" oninput="nfRecalculer(${i})" style="width:70px;border:1px solid #ddd;border-radius:3px;padding:2px;font-size:11px;text-align:right;"></td>
        <td style="padding:3px 4px;text-align:right;font-weight:600;color:#c0392b;" id="nf_dette_${i}">0</td>
        <td style="padding:3px 4px;"><button type="button" onclick="this.closest('tr').remove();nfMajTotaux()" style="background:#e74c3c;color:white;border:none;border-radius:3px;padding:2px 6px;cursor:pointer;font-size:10px;">✕</button></td>`;
    document.getElementById('nf_lignes').appendChild(tr);
}
function nfRemplirPrix(i) {
    const sel=document.getElementById(`nf_acte_${i}`);
    document.getElementById(`nf_prix_${i}`).value=sel.options[sel.selectedIndex]?.getAttribute('data-cout')||'';
    nfRecalculer(i);
}
function nfRecalculer(i) {
    const prix=parseFloat(document.getElementById(`nf_prix_${i}`)?.value)||0;
    const verse=parseFloat(document.getElementById(`nf_verse_${i}`)?.value)||0;
    const el=document.getElementById(`nf_dette_${i}`); if(el) el.textContent=(prix-verse).toLocaleString('fr-FR')+' DH';
    nfMajTotaux();
}
function nfMajTotaux() {
    let tp=0,tv=0,td=0;
    document.querySelectorAll('#nf_lignes tr').forEach(tr=>{
        const idx=tr.querySelector('select')?.id?.replace('nf_acte_',''); if(!idx) return;
        const p=parseFloat(document.getElementById(`nf_prix_${idx}`)?.value)||0;
        const v=parseFloat(document.getElementById(`nf_verse_${idx}`)?.value)||0;
        tp+=p;tv+=v;td+=(p-v);
    });
    document.getElementById('nf_totalPrix').textContent=tp.toLocaleString('fr-FR')+' DH';
    document.getElementById('nf_totalVerse').textContent=tv.toLocaleString('fr-FR')+' DH';
    document.getElementById('nf_totalDette').textContent=td.toLocaleString('fr-FR')+' DH';
}
function nfEnregistrer(patientId) {
    const date_facture=document.getElementById('nf_date').value; const lignes=[];
    document.querySelectorAll('#nf_lignes tr').forEach(tr=>{
        const idx=tr.querySelector('select')?.id?.replace('nf_acte_',''); if(!idx) return;
        const acte=document.getElementById(`nf_acte_${idx}`)?.value;
        const prix=parseFloat(document.getElementById(`nf_prix_${idx}`)?.value)||0;
        const verse=parseFloat(document.getElementById(`nf_verse_${idx}`)?.value)||0;
        const dateA=document.getElementById(`nf_dateacte_${idx}`)?.value;
        if(acte) lignes.push({acte,prix,verse,date_acte:dateA});
    });
    if(lignes.length===0){document.getElementById('nf_msg').textContent='⚠ Ajoutez au moins un acte.';document.getElementById('nf_msg').style.color='#e74c3c';return;}
    document.getElementById('nf_msg').textContent='Enregistrement…';document.getElementById('nf_msg').style.color='#999';
    fetch('ajax_nouvelle_facture.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:patientId,date_facture,lignes})})
    .then(r=>r.json()).then(data=>{
        if(data.success) window.location.href=`dossier.php?id=${patientId}&fact=${data.n_facture}`;
        else{document.getElementById('nf_msg').textContent='❌ '+data.error;document.getElementById('nf_msg').style.color='#e74c3c';}
    }).catch(()=>{document.getElementById('nf_msg').textContent='❌ Erreur réseau';document.getElementById('nf_msg').style.color='#e74c3c';});
}

document.addEventListener('DOMContentLoaded', ()=>{
    const dateInit=document.getElementById('rdv_futur')?.value;
    if(dateInit && /^\d{4}-\d{2}-\d{2}$/.test(dateInit) && dateInit!=='1970-01-01')
        rdvChargerCreneaux(dateInit,'rdv',false);
});
function afficherModifierOrdonnance() {
    window.location.href = 'modifier_ordonnance.php?id=<?= $id ?>&ord=<?= $nOrd ?>';
}

// ── Horloge temps réel ────────────────────────────────
(function miseAJourHorloge() {
    const jours = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
    const mois  = ['Jan','Fév','Mar','Avr','Mai','Juin','Juil','Aoû','Sep','Oct','Nov','Déc'];
    function tick() {
        const n = new Date();
        const h = String(n.getHours()).padStart(2,'0');
        const m = String(n.getMinutes()).padStart(2,'0');
        const s = String(n.getSeconds()).padStart(2,'0');
        const ct = document.getElementById('clockTime');
        const cd = document.getElementById('clockDate');
        if (ct) ct.textContent = h+':'+m+':'+s;
        if (cd) cd.textContent = jours[n.getDay()]+' '+n.getDate()+' '+mois[n.getMonth()]+' '+n.getFullYear();
    }
    tick();
    setInterval(tick, 1000);
})();

// ── Mémoriser le dernier patient consulté (cookie 30 jours) ──
(function() {
    const id = <?= (int)$id ?>;
    if (id > 0) {
        const expire = new Date();
        expire.setDate(expire.getDate() + 30);
        document.cookie = 'dernier_patient=' + id +
            '; expires=' + expire.toUTCString() + '; path=/';
    }
})();
</script>
</body>
</html>