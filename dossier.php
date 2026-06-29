<?php
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';
$db = getDB();

// Compteur RDV du jour / NbrMax (pour le bloc logo)
$nbRdvAujourd = $db->query("SELECT COUNT(*) FROM ORD WHERE CONVERT(date,[DATE REDEZ VOUS])=CONVERT(date,GETDATE()) OR CONVERT(date,Date_Rdv)=CONVERT(date,GETDATE())")->fetchColumn();
$nbrMax = 20;
try {
    $stmtMax = $db->prepare("SELECT Valeur FROM T_Config WHERE Cle='NbrMax'");
    $stmtMax->execute();
    $rowMax = $stmtMax->fetch(PDO::FETCH_ASSOC);
    if ($rowMax) $nbrMax = (int)$rowMax['Valeur'];
} catch (Exception $e) {}

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

$stmtEx = $db->prepare("SELECT * FROM t_examen WHERE NPAT=? ORDER BY DateExam DESC");
$stmtEx->execute([$id]);
$examens = $stmtEx->fetchAll();
$nExam = (int)($_GET['exam'] ?? ($examens ? $examens[0]['N1'] : 0));
$examen = null; $idxExam = 0;
foreach ($examens as $i => $e) { if ($e['N1'] == $nExam) { $examen = $e; $idxExam = $i; break; } }
if (!$examen && $examens) { $examen = $examens[0]; }

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
// ── BILANS BIOLOGIE — liste pour navigation dossier ───────────────────
$stmtBioListe = $db->prepare("
    SELECT b.n_bilan,
           CONVERT(varchar(10), b.date_bilan, 103) AS date_fr,
           b.date_bilan,
           ISNULL(b.observation,'') AS observation,
           COUNT(a.N_analyse) AS nb_total,
           SUM(CASE WHEN ISNULL(a.résultat,'') <> '' AND a.résultat <> 'N' THEN 1 ELSE 0 END) AS nb_anormal
    FROM LE_BILAN b
    LEFT JOIN analyses a ON a.N_bilan = b.n_bilan
    WHERE b.id = ?
    GROUP BY b.n_bilan, b.date_bilan, b.observation
    ORDER BY b.date_bilan DESC
");
$stmtBioListe->execute([$id]);
$bilansListe = $stmtBioListe->fetchAll();
$bilanCourantData = $bilansListe ? $bilansListe[0] : null;
 
// Charger le détail du premier bilan (le plus récent)
$lignesBioActuel = [];
if ($bilanCourantData) {
    $stmtBioDetail = $db->prepare("
        SELECT a.N_analyse,
               c.analyse AS nom,
               c.rubrique,
               ISNULL(a.résultat,'') AS resultat
        FROM analyses a
        LEFT JOIN C_ANALYSE c ON c.[N°TypeAnalyse] = a.bilan
        WHERE a.N_bilan = ?
        ORDER BY c.rubrique, c.analyse
    ");
    $stmtBioDetail->execute([$bilanCourantData['n_bilan']]);
    $lignesBioActuel = $stmtBioDetail->fetchAll();
}
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
$factPremiere = $factures ? $factures[0]['n_facture'] : 0;
$factDerniere = $factures ? $factures[count($factures)-1]['n_facture'] : 0;
$factPrev = ($idxFact > 0) ? $factures[$idxFact-1]['n_facture'] : $nFact;
$factNext = ($idxFact < count($factures)-1) ? $factures[$idxFact+1]['n_facture'] : $nFact;

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

// Thème
$themes_valides = ['theme-0','theme-a','theme-b','theme-c'];
$theme = $_COOKIE['logycab_theme'] ?? 'theme-0';
if (!in_array($theme, $themes_valides)) $theme = 'theme-0';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dossier — <?= htmlspecialchars($patient['NOMPRENOM']) ?></title>
<link rel="stylesheet" href="themes.css">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: var(--th-font-body); background: var(--th-bg-page); font-size: 13px; color: var(--th-color-text); }
.header { background: var(--th-bg-header-s); color: white; padding: 5px 12px; display: flex; align-items: center; gap: 7px; flex-wrap: nowrap; }
.header h1 { font-size: 14px; font-weight: 700; white-space: nowrap; }
.btn-h { color: white; text-decoration: none; border: none; cursor: pointer;
         padding: 3px 9px; border-radius: 4px; font-size: 11px; font-weight: bold;
         display: inline-flex; align-items: center; height: 24px; white-space: nowrap; }
.btn-h.green  { background: #27ae60; }
.btn-h.navy   { background: var(--th-btn-navy); border: 1px solid rgba(255,255,255,0.3); }
.btn-h.blue   { background: var(--th-btn-blue); }
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
@keyframes heartbeat {
    0%,100% { transform: scale(1); }
    14%     { transform: scale(1.2); }
    28%     { transform: scale(1); }
    42%     { transform: scale(1.15); }
    56%     { transform: scale(1); }
}
.heart { display: inline-block; animation: heartbeat 1.6s infinite; color: #e74c3c; font-size: 20px; }
.logo-block { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }
.logo-block .nom { font-size: 16px; font-weight: 900; letter-spacing: 1px; color: #fff; line-height: 1.1; }
.logo-block .sub { font-size: 9px; opacity: 0.85; color: #fff; white-space: nowrap; }
.header-clock { background: rgba(255,255,255,0.12); border-radius: 6px;
                padding: 3px 10px; text-align: center; min-width: 130px; flex-shrink: 0; }
.header-clock .ct { font-size: 15px; font-weight: bold; letter-spacing: 1px; color: white; }
.header-clock .cd { font-size: 9px; opacity: 0.75; }
.patient-bar { background: #000000; color: var(--th-col-header-accent); padding: 6px 16px; display: flex; align-items: center; gap: 20px; flex-wrap: wrap; font-size: 12px; }
.patient-bar .info label { font-size: 10px; opacity: 0.8; text-transform: uppercase; display: block; color: var(--th-col-header-accent); }
.patient-bar .info span { font-weight: bold; color: var(--th-col-header-accent); }
.main { display: grid; grid-template-columns: 200px 1fr 320px; gap: 8px; padding: 8px; align-items: start; }
body.vue-accueil .main { grid-template-columns: 200px 1fr 320px; }
.col-left { display: flex; flex-direction: column; gap: 8px; }
.col-mid  { display: flex; flex-direction: column; gap: 8px; }
.col-right{ display: flex; flex-direction: column; gap: 8px; }
.card { background: var(--th-bg-card); border-radius: 6px; padding: 10px; box-shadow: 0 1px 4px var(--th-border-card); }
.card-title { color: var(--th-color-primary); font-size: 12px; font-weight: bold; margin-bottom: 8px; padding-bottom: 4px; border-bottom: 2px solid var(--th-border-statsbar); display: flex; justify-content: space-between; align-items: center; }
.nav-btns { display: flex; gap: 3px; }
.nav-btn { background: var(--th-btn-navy); color: white; border: none; padding: 3px 7px; border-radius: 3px; cursor: pointer; font-size: 11px; text-decoration: none; }
.nav-btn:hover { background: var(--th-color-secondary); }
.nav-ord-barre { display: flex; justify-content: center; align-items: center; gap: 3px; margin-top: 14px; padding-top: 10px; border-top: 2px solid var(--th-border-statsbar); }
.champ { margin-bottom: 6px; }
.champ label { font-size: 10px; color: var(--th-color-text-muted); text-transform: uppercase; font-weight: bold; display: block; margin-bottom: 2px; }
.champ input, .champ select, .champ textarea { width: 100%; padding: 4px 6px; border: 1px solid #ddd; border-radius: 3px; font-size: 12px; color: #222; background:var(--th-bg-card); }
.champ textarea { resize: vertical; height: auto; overflow: hidden; field-sizing: content; }
.diag-bloc { display:flex; flex-direction:column; gap:3px; margin-bottom:4px; }
.diag-ligne { display:flex; gap:4px; align-items:center; }
.creneaux-wrap { margin-top: 6px; }
.creneaux-grille { display: flex; flex-wrap: wrap; gap: 3px; }
.creneau-btn { padding: 3px 7px; border-radius: 3px; border: 2px solid transparent; cursor: pointer; font-size: 11px; font-weight: bold; min-width: 48px; text-align: center; transition: transform 0.1s; }
.creneau-btn:hover { transform: scale(1.08); }
.creneau-btn.libre  { background: #27ae60; color: white; border-color: #1e8449; }
.creneau-btn.moyen  { background: var(--th-col-warn); color: white; border-color: #d68910; }
.creneau-btn.plein  { background: #e74c3c; color: #fdd; border-color: #c0392b; cursor: not-allowed; opacity: 0.7; }
.creneau-btn.selectionne { border-color: var(--th-btn-navy) !important; box-shadow: 0 0 0 3px rgba(26,74,122,0.35); transform: scale(1.1); }
.creneaux-msg     { font-size: 11px; color: #e74c3c; margin-top: 4px; font-weight: bold; }
.creneaux-loading { font-size: 11px; color: var(--th-color-text-muted); font-style: italic; margin-top: 4px; }
.jauge-jour { display: flex; align-items: center; gap: 6px; margin-bottom: 6px; font-size: 11px; }
.jauge-bar  { flex: 1; height: 8px; background: #e0e0e0; border-radius: 4px; overflow: hidden; }
.jauge-fill { height: 100%; border-radius: 4px; transition: width 0.3s; }
.jauge-fill.ok   { background: #27ae60; }
.jauge-fill.warn { background: var(--th-col-warn); }
.jauge-fill.full { background: #e74c3c; }
.row-bottom { padding: 0 8px 8px; display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.ta-val { font-size: 16px; font-weight: bold; }
.fdr-badge { background: #ffe0e0; color: #c0392b; padding: 1px 6px; border-radius: 8px; font-size: 11px; margin: 1px; display: inline-block; }
.delai-btn-rdv { padding: 3px 8px; border: 1px solid var(--th-col-rdvn); border-radius: 3px; cursor: pointer; font-size: 11px; background:var(--th-bg-card); color: var(--th-col-rdvn); }
.delai-btn-rdv:hover, .delai-btn-rdv.actif { background: var(--th-col-rdvn); color: white; }
.tableau-rdv { width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 10px; border-radius: 6px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,0.15); }
.tableau-rdv th { padding: 7px 6px; text-align: center; font-size: 11px; }
.tableau-rdv td { padding: 5px 6px; border-bottom: 1px solid var(--th-sep-color); color: var(--th-color-text); }
.tableau-rdv td:first-child { background: var(--th-bg-link-hover); font-size: 11px; font-weight: bold; color: var(--th-color-primary); text-align: right; white-space: nowrap; }
.tableau-rdv tr:last-child td { border-bottom: none; }
.col-visite   { background: #e8f8ee; }
.col-rdv-fixe { background: var(--th-col-visite-bg); }
.col-rdv-futur{ background: var(--th-col-rdvn-bg); }
@media (max-width: 900px) { .main { grid-template-columns: 1fr; } .row-bottom { grid-template-columns: 1fr; } }

/* ── Vue bascule ── */
.btn-vue { padding:3px 10px; border-radius:4px; border:none; cursor:pointer; font-size:11px; font-weight:bold; color:white; height:24px; }
.btn-vue.actif  { background:var(--th-btn-navy); opacity:1; }
.btn-vue.inactif{ background:rgba(255,255,255,0.25); opacity:0.7; }
.btn-vue.inactif:hover { opacity:1; }
#btn-vue-consultation { display: none; }

/* ── Tableau accueil ── */
.tbl-acc { width:100%; border-collapse:collapse; font-size:12px; margin-bottom:8px; border-radius:6px; overflow:hidden; box-shadow:0 1px 4px rgba(0,0,0,0.12); }
.tbl-acc th { padding:6px 8px; text-align:center; font-size:11px; }
.tbl-acc td { padding:5px 7px; border-bottom:1px solid var(--th-sep-color); text-align:center; font-size:12px; color: var(--th-color-text); }
.tbl-acc td:first-child { background:var(--th-bg-link-hover); font-size:11px; font-weight:bold; color:var(--th-color-primary); text-align:right; white-space:nowrap; }
.tbl-acc tr:last-child td { border-bottom:none; }
.cell-rdv-prochain { background:var(--th-col-rdvn-bg); cursor:pointer; transition:background 0.2s; color:#333; }
.cell-rdv-prochain:hover { background:var(--th-col-rdvn-bg-hover); }
.cell-rdv-vide { color:#ccc; font-size:18px; }

/* ── Popup RDV prochain ── */
.popup-rdv-ov { display:none; position:fixed; top:0; left:0; width:100%; height:100%;
    background:rgba(0,0,0,0.5); z-index:10000; align-items:center; justify-content:center; }
.popup-rdv-ov.ouvert { display:flex !important; }
.popup-rdv-box { background:var(--th-bg-card); border-radius:10px; padding:0; max-width:420px; width:96%;
    box-shadow:0 10px 40px rgba(0,0,0,0.3); overflow:hidden; }
.popup-rdv-header { background:var(--th-col-rdvn); color:white; padding:12px 16px;
    display:flex; justify-content:space-between; align-items:center; }
.popup-rdv-body { padding:14px 16px; color:#222; }
</style>
</head>
<body class="<?= htmlspecialchars($theme) ?>">

<!-- HEADER -->
<div class="header">
    <!-- GAUCHE : logo + cœur animé + compteur RDV du jour -->
    <div class="logo-block">
        <span class="heart">❤</span>
        <div>
            <div class="nom">LOGYCAB</div>
            <div class="sub"><?= $nbRdvAujourd ?> RDV aujourd'hui / <?= $nbrMax ?> prévus</div>
        </div>
    </div>
    <!-- Recherche globale avec suggestions -->
    <div class="search-hdr-wrap">
        <input class="search-hdr" type="text" id="rech-patient" placeholder="🔍 Rechercher patient...">
        <div id="rech-suggestions" style="position:absolute;top:100%;left:0;width:300px;background:var(--th-bg-card);
             border:1px solid #ccc;border-radius:4px;max-height:200px;overflow-y:auto;
             z-index:1000;display:none;box-shadow:0 4px 12px rgba(0,0,0,0.2);"></div>
    </div>
    <!-- Espace flexible : pousse Rapports/boutons/horloge/déconnexion à droite -->
    <div style="flex:1;"></div>
    <!-- Rapports, juste à gauche de Accueil -->
    <button type="button" onclick="ouvrirMenuRapports()" class="btn-h" style="background:#c0392b;border:none;cursor:pointer;">📑 Rapports</button>
    <!-- MILIEU : boutons fixes (dossier = gris car page courante) -->
    <a href="index.php" class="btn-h" style="background:#c0392b;font-size:11px;">🏠 Accueil</a>
    <span                               class="btn-h grey"  >🏠 Dossier</span>
    <a href="nouveau_bilan_clinique.php?id=<?= $id ?>" class="btn-h" style="background:#27ae60;font-size:13px;padding:5px 14px;font-weight:bold;">📋 Aperçu</a>
    <a href="agenda.php"                class="btn-h navy"  >📅 Agenda</a>
    <a href="planning.php"              class="btn-h blue"  >📊 Planning</a>
    <a href="grille_semaine.php"        class="btn-h blue"  >📋 Grille</a>
    <a href="biologie.php?id=<?= $id ?>" class="btn-h orange">🧪 Biologie</a>
    <a href="jours_feries.php"          class="btn-h purple">📅 Fériés</a>
    <!-- DROITE : horloge puis déconnexion tout au bord -->
    <div class="header-clock">
        <div id="clockTime" class="ct">--:--:--</div>
        <div id="clockDate" class="cd">---</div>
    </div>
    <a href="logout.php" class="btn-h red" title="Déconnexion">⏻</a>
</div>

<!-- BANDEAU PATIENT -->
<div class="patient-bar">
    <div class="info"><label>N°</label><span><?= $id ?></span></div>
    <div class="info"><label>Nom</label><span><?= htmlspecialchars($patient['NOMPRENOM']) ?></span></div>
    <div class="info"><label>Âge</label><span><?= $age ?> ans</span></div>
    <div class="info"><label>DDN</label><span><?= $patient['DDN'] ? date('d/m/Y', strtotime($patient['DDN'])) : '—' ?></span></div>
    <div class="info"><label>CIN</label><span><?= htmlspecialchars($patient['CIN'] ?? '—') ?></span></div>
    <div class="info"><label>Mutuelle</label><span><?= htmlspecialchars($patient['MUTUELLE'] ?? '—') ?></span></div>
    <!-- Navigation patient -->
    <div style="display:inline-flex;align-items:center;gap:2px;background:rgba(255,255,255,0.1);border-radius:5px;padding:2px 6px;">
        <a href="dossier.php?id=<?= $first_id ?>" title="Premier" style="color:var(--th-col-header-accent);text-decoration:none;font-size:15px;padding:0 3px;">⏮</a>
        <a href="dossier.php?id=<?= $prev_id ?>"  title="Précédent" style="color:var(--th-col-header-accent);text-decoration:none;font-size:15px;padding:0 3px;">◀</a>
        <span style="color:var(--th-col-header-accent);font-size:11px;min-width:60px;text-align:center;"><?= $pos_patient ?> / <?= $total_patients ?></span>
        <a href="dossier.php?id=<?= $next_id ?>"  title="Suivant" style="color:var(--th-col-header-accent);text-decoration:none;font-size:15px;padding:0 3px;">▶</a>
        <a href="dossier.php?id=<?= $last_id ?>"  title="Dernier" style="color:var(--th-col-header-accent);text-decoration:none;font-size:15px;padding:0 3px;">⏭</a>
    </div>
</div>

<!-- LAYOUT 3 COLONNES -->
<div class="main">

<!-- ══ COLONNE GAUCHE ══ -->
<div class="col-left">
    <div class="card">
        <div class="card-title">👤 Dossier patient
            <span id="dossier_status" style="font-size:10px;color:#27ae60;font-weight:normal;"></span>
        </div>
        <div style="text-align:center;margin-bottom:8px;">
            <button type="button" onclick="ouvrirPopupMAD('motif')"
                style="background:#1a4a7a;color:white;border:none;border-radius:5px;padding:4px 14px;font-size:11px;font-weight:bold;cursor:pointer;letter-spacing:0.5px;">
                MDC | ATCD | DIC | FDR
            </button>
        </div>
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
    </div>

    <!-- Remarque patient -->
    <div class="card" style="flex:1;">
        <div style="font-size:11px;font-weight:bold;color:var(--th-color-primary);margin-bottom:4px;">📝 Remarque</div>
        <textarea id="champ_remarque2" onblur="sauvegarderChamp('REMARQUE', this.value)"
            style="border:1px solid #ddd;border-radius:3px;padding:3px 5px;width:100%;font-size:11px;resize:vertical;min-height:80px;field-sizing:content;box-sizing:border-box;"
        ><?= htmlspecialchars($patient['REMARQUE'] ?? '') ?></textarea>
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
                    $coulOrd = $estAujourdHui ? '#e74c3c' : 'var(--th-col-visite)';
                    $bgOrd   = $estAujourdHui ? '#fdecea' : 'var(--th-col-visite-bg)';
                    $bordOrd = $estAujourdHui ? '#e74c3c' : 'var(--th-col-visite)';
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
        <?php
        // ══ CALCULS COMMUNS AUX DEUX VUES ══════════════════════
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

        <!-- ══════════════════════════════════════════════════
             VUE ACCUEIL
        ══════════════════════════════════════════════════ -->
        <div id="section-accueil">
        <div style="display:grid;grid-template-columns:1fr 320px;gap:10px;align-items:start;">
        <div><!-- COL GAUCHE : tableau + médicaments -->
        <?php
        // ── Calculs spécifiques vue accueil ──────────────────
        $rdvp_delai = '—';
        if ($ordPrecedente && !empty($ordPrecedente['DATE REDEZ VOUS']) && !empty($ordPrecedente['date_ordon'])) {
            $tsOrd = strtotime($ordPrecedente['date_ordon']);
            $tsRdv = strtotime($ordPrecedente['DATE REDEZ VOUS']);
            if ($tsOrd && $tsRdv && $tsRdv > 86400) {
                $diff = (new DateTime(date('Y-m-d',$tsOrd)))->diff(new DateTime(date('Y-m-d',$tsRdv)));
                $m = $diff->m + $diff->y*12; $j = $diff->d;
                $rdvp_delai = $m > 0 ? $m.'M'.($j>0?' '.$j.'j':'') : $j.'j';
            }
        }
        // Actes Dernière visite
        $dv_acte_ecg  = in_array('ECG',  $dernActesFact) ? 'ECG'  : '—';
        $dv_acte_edc  = in_array('EDC',  $dernActesFact) ? 'EDC'  : '—';
        $dv_acte_dtsa = in_array('DTSA', $dernActesFact) ? 'DTSA' : '—';
        // Actes RDV prévu
        $rdvp_acte_str = $ordPrecedente['acte1'] ?? '';
        $rdvp_ecg  = (strpos($rdvp_acte_str,'ECG')!==false)  ? 'ECG'  : '—';
        $rdvp_edc  = (strpos($rdvp_acte_str,'EDC')!==false)  ? 'EDC'  : '—';
        $rdvp_dtsa = (strpos($rdvp_acte_str,'DTSA')!==false) ? 'DTSA' : '—';
        // Actes Actuel (suggérés)
        $act_ecg  = in_array('ECG',  $acteSugActuel) ? '<span style="color:#e74c3c;font-weight:bold;">ECG</span>'  : '<span style="color:#27ae60;">✓</span>';
        $act_edc  = in_array('EDC',  $acteSugActuel) ? '<span style="color:#e74c3c;font-weight:bold;">EDC</span>'  : '<span style="color:#27ae60;">✓</span>';
        $act_dtsa = in_array('DTSA', $acteSugActuel) ? '<span style="color:#e74c3c;font-weight:bold;">DTSA</span>' : '<span style="color:#27ae60;">✓</span>';
        // Totaux historique
        $tot_ecg  = count($histECG);
        $tot_edc  = count($histEDC);
        $tot_dtsa = count($histDTSA);
        // RDV prochain existant
        $rdvf_date  = $rdvFuturVal ? date('d/m/Y', strtotime($rdvFuturVal)) : '';
        $rdvf_heure = !empty($ordCourante['HeureRDV']) ? htmlspecialchars($ordCourante['HeureRDV']) : '';
        $rdvf_acte  = htmlspecialchars($ordCourante['acte1'] ?? '');
        // Heure visite enregistrée
        $heureVisite = htmlspecialchars($ordCourante['HeureVisite'] ?? '');
        ?>

        <!-- Recrutement accueil -->
        <div style="font-size:11px;color:var(--th-color-text-muted);margin-bottom:6px;">
            <span>🏥 <strong>Recrutement :</strong> <?= $datePVAff ?></span>
        </div>

        <!-- Tableau principal accueil -->
        <table class="tbl-acc">
            <thead>
                <tr>
                    <th style="background:#1a4a7a;color:white;width:80px;"></th>
                    <th style="background:var(--th-col-visite);color:white;">🏥 Dernière visite</th>
                    <th style="background:var(--th-col-rdvp);color:white;">📅 RDV prévu</th>
                    <th style="background:#27ae60;color:white;">🩺 Actuel<br><small><?= date('d/m/Y') ?></small></th>
                    <th class="cell-rdv-prochain" onclick="ouvrirPopupRdv()" title="Cliquer pour donner un RDV"
                        style="background:var(--th-col-rdvn);color:white;">
                        📆 RDV prochain<br><small style="font-weight:normal;opacity:0.8;">▶ Cliquer</small></th>
                </tr>
            </thead>
            <tbody>
                <!-- Ligne Date / Heure -->
                <tr>
                    <td>📅 Date<br>⏰ Heure</td>
                    <td class="col-rdv-fixe">
                        <strong style="color:var(--th-col-visite);"><?= $dv_dateOrd ?></strong><br>
                        <span style="color:var(--th-col-visite);font-size:11px;"><?= $dv_heure ?></span>
                    </td>
                    <td style="background:var(--th-col-rdvp-bg);">
                        <strong style="color:var(--th-col-rdvp);"><?= $rdvp_date ?></strong><br>
                        <span style="color:var(--th-col-rdvp);font-size:11px;"><?= $rdvp_heure ?></span>
                    </td>
                    <td class="col-visite">
                        <strong style="color:#27ae60;"><?= date('d/m/Y') ?></strong><br>
                        <div style="display:flex;align-items:center;gap:3px;">
                            <input type="time" id="heure_consultation_acc"
                                   value="<?= $heureVisite ?>"
                                   style="border:1px solid #b2dfb2;border-radius:3px;padding:1px 4px;font-size:11px;color:#27ae60;font-weight:bold;width:70px;background:#f0fff0;">
                            <button type="button" onclick="enregistrerHeureVisite()"
                                    title="Enregistrer l'heure"
                                    style="background:#27ae60;color:white;border:none;border-radius:3px;padding:1px 5px;cursor:pointer;font-size:10px;line-height:1.4;">💾</button>
                        </div>
                    </td>
                    <td class="cell-rdv-prochain" onclick="ouvrirPopupRdv()" id="acc-rdvp-date">
                        <?php if ($rdvf_date): ?>
                            <strong style="color:var(--th-col-rdvn);"><?= $rdvf_date ?></strong><br>
                            <span style="color:var(--th-col-rdvn);font-size:11px;"><?= $rdvf_heure ?></span>
                        <?php else: ?>
                            <span class="cell-rdv-vide">＋</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <!-- Ligne Délai -->
                <tr>
                    <td>⏱ Délai</td>
                    <td class="col-rdv-fixe"><span style="color:#aaa;">—</span></td>
                    <td style="background:var(--th-col-rdvp-bg);color:var(--th-col-rdvp);font-weight:bold;"><?= $rdvp_delai ?></td>
                    <td class="col-visite;color:#27ae60;font-weight:bold;"><?= $delaiVisite ?: '—' ?></td>
                    <td class="cell-rdv-prochain" onclick="ouvrirPopupRdv()" id="acc-rdvp-delai">
                        <span style="color:var(--th-col-rdvn);font-weight:bold;" id="acc-rdvp-delai-txt">—</span>
                    </td>
                </tr>
                <!-- Ligne ECG -->
                <tr>
                    <td>⚡ ECG (<?= $tot_ecg ?>)</td>
                    <td class="col-rdv-fixe"><span style="color:<?= $dv_acte_ecg!=='—'?'var(--th-col-visite)':'#ccc' ?>;font-weight:bold;"><?= $dv_acte_ecg ?></span></td>
                    <td style="background:var(--th-col-rdvp-bg);"><span style="color:<?= $rdvp_ecg!=='—'?'var(--th-col-rdvp)':'#ccc' ?>;font-weight:bold;"><?= $rdvp_ecg ?></span></td>
                    <td class="col-visite"><?= $act_ecg ?></td>
                    <td class="cell-rdv-prochain" onclick="ouvrirPopupRdv()" id="acc-rdvp-ecg"><span style="color:#ccc;">—</span></td>
                </tr>
                <!-- Ligne EDC -->
                <tr>
                    <td>🫀 EDC (<?= $tot_edc ?>)</td>
                    <td class="col-rdv-fixe"><span style="color:<?= $dv_acte_edc!=='—'?'var(--th-col-visite)':'#ccc' ?>;font-weight:bold;"><?= $dv_acte_edc ?></span></td>
                    <td style="background:var(--th-col-rdvp-bg);"><span style="color:<?= $rdvp_edc!=='—'?'var(--th-col-rdvp)':'#ccc' ?>;font-weight:bold;"><?= $rdvp_edc ?></span></td>
                    <td class="col-visite"><?= $act_edc ?></td>
                    <td class="cell-rdv-prochain" onclick="ouvrirPopupRdv()" id="acc-rdvp-edc"><span style="color:#ccc;">—</span></td>
                </tr>
                <!-- Ligne DTSA -->
                <tr>
                    <td>🔬 DTSA (<?= $tot_dtsa ?>)</td>
                    <td class="col-rdv-fixe"><span style="color:<?= $dv_acte_dtsa!=='—'?'var(--th-col-visite)':'#ccc' ?>;font-weight:bold;"><?= $dv_acte_dtsa ?></span></td>
                    <td style="background:var(--th-col-rdvp-bg);"><span style="color:<?= $rdvp_dtsa!=='—'?'var(--th-col-rdvp)':'#ccc' ?>;font-weight:bold;"><?= $rdvp_dtsa ?></span></td>
                    <td class="col-visite"><?= $act_dtsa ?></td>
                    <td class="cell-rdv-prochain" onclick="ouvrirPopupRdv()" id="acc-rdvp-dtsa"><span style="color:#ccc;">—</span></td>
                </tr>
            </tbody>
        </table>

        <!-- MÉDICAMENTS (identique) -->
        <div class="champ" style="margin-top:4px;">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                <label style="font-size:11px;font-weight:bold;color:var(--th-color-primary);margin:0;">💊 Médicaments (<?= count($medicaments) ?>)</label>
                <button type="button" onclick="reportTraitement(3,<?= $id ?>)" style="background:#e67e22;color:white;border:none;padding:2px 8px;border-radius:3px;cursor:pointer;font-size:10px;font-weight:bold;">↺ 3M</button>
                <button type="button" onclick="reportTraitement(6,<?= $id ?>)" style="background:#c0392b;color:white;border:none;padding:2px 8px;border-radius:3px;cursor:pointer;font-size:10px;font-weight:bold;">↺ 6M</button>
                <a href="print_ordonnance.php?id=<?= $id ?>&ord=<?= $nOrd ?>" target="_blank" style="background:#1a4a7a;color:white;border:none;padding:2px 8px;border-radius:3px;cursor:pointer;font-size:10px;font-weight:bold;text-decoration:none;" title="Imprimer">🖨️</a>
            </div>
            <?php if (!empty($medicaments)): ?>
            <div style="display:grid;grid-template-columns:2fr 2fr 1fr;gap:4px;margin-bottom:4px;">
                <span style="font-size:10px;color:var(--th-color-text-muted);text-transform:uppercase;">Médicament</span>
                <span style="font-size:10px;color:var(--th-color-text-muted);text-transform:uppercase;">Posologie</span>
                <span style="font-size:10px;color:var(--th-color-text-muted);text-transform:uppercase;">Durée</span>
            </div>
            <?php foreach ($medicaments as $m): ?>
            <div style="display:grid;grid-template-columns:2fr 2fr 1fr;gap:4px;margin-bottom:3px;">
                <input type="text" value="<?= htmlspecialchars($m['PRODUIT'] ?? '') ?>" readonly style="padding:3px 5px;border:1px solid #ddd;border-radius:3px;font-size:11px;background:#f9f9f9;">
                <input type="text" value="<?= htmlspecialchars($m['posologie'] ?? '') ?>" readonly style="padding:3px 5px;border:1px solid #ddd;border-radius:3px;font-size:11px;background:#f9f9f9;">
                <input type="text" value="<?= htmlspecialchars($m['DUREE'] ?? '') ?>" readonly style="padding:3px 5px;border:1px solid #ddd;border-radius:3px;font-size:11px;background:#f9f9f9;">
            </div>
            <?php endforeach; ?>
            <?php else: ?><p style="color:var(--th-color-text-muted);font-size:12px;">Aucun médicament</p><?php endif; ?>
        </div>

        </div><!-- FIN COL GAUCHE ACCUEIL -->
        <div><!-- COL DROITE : facturation + certificat -->

        <!-- FACTURATION ACCUEIL (sans colonne Prix) -->
        <div style="margin-top:8px;border-top:1px solid #eee;padding-top:8px;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:6px;">
                <span style="font-size:12px;font-weight:bold;color:var(--th-color-primary);">💰 Facturation</span>
                <?php if ($factCourante): ?>
                <?php $tsFA=strtotime($factCourante['date_facture']??''); $dfA=($tsFA&&$tsFA>86400)?date('d/m/Y',$tsFA):'—'; $estAujFA=($tsFA&&date('Y-m-d',$tsFA)===date('Y-m-d')); ?>
                <div style="text-align:right;">
                    <div style="font-size:12px;font-weight:bold;color:<?=$estAujFA?'#e74c3c':'var(--th-col-visite)'?>;background:<?=$estAujFA?'#fdecea':'var(--th-col-visite-bg)'?>;padding:2px 8px;border-radius:4px;border:1px solid <?=$estAujFA?'#e74c3c':'var(--th-col-visite)'?>;"><?= $dfA ?></div>
                    <div style="font-size:11px;color:#aaa;margin-top:2px;padding-right:2px;">N° <?= $factCourante['n_facture'] ?></div>
                </div>
                <?php endif; ?>
            </div>
            <?php if ($factCourante): ?>
            <table style="width:100%;border-collapse:collapse;font-size:11px;">
                <thead style="background:#1a4a7a;color:white;">
                    <tr>
                        <th style="padding:4px 6px;text-align:left;">Date acte</th>
                        <th style="padding:4px 6px;text-align:left;">Acte</th>
                        <th style="padding:4px 6px;text-align:right;">Versé</th>
                        <th style="padding:4px 6px;text-align:right;">Reste</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($detailActes as $da): ?>
                <tr style="border-bottom:1px solid #eee;">
                    <td style="padding:4px 6px;color:var(--th-color-text-muted);font-size:11px;"><?= $da['date-H'] ? date('d/m/Y',strtotime($da['date-H'])) : '—' ?></td>
                    <td style="padding:4px 6px;"><?= htmlspecialchars($da['nom_acte'] ?? 'Acte '.$da['ACTE']) ?></td>
                    <td style="padding:4px 6px;text-align:right;"><?= number_format($da['Versé'],0,',',' ') ?></td>
                    <td style="padding:4px 6px;text-align:right;color:<?=$da['dette']>0?'#e74c3c':'#27ae60'?>;"><?= number_format($da['dette'],0,',',' ') ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot style="background:#f0f4f8;font-weight:bold;color:#333;">
                    <tr>
                        <td colspan="2" style="padding:4px 6px;">Total</td>
                        <td style="padding:4px 6px;text-align:right;"><?= number_format($factCourante['verse_total'],0,',',' ') ?> DH</td>
                        <td style="padding:4px 6px;text-align:right;color:<?=$factCourante['dette_total']>0?'#e74c3c':'#27ae60'?>;"><?= number_format($factCourante['dette_total'],0,',',' ') ?> DH</td>
                    </tr>
                </tfoot>
            </table>
            <div style="display:flex;justify-content:center;gap:2px;margin-top:4px;">
                <a href="?id=<?= $id ?>&fact=<?= $factPremiere ?>" class="nav-btn" style="padding:2px 5px;font-size:10px;">|◀</a>
                <a href="?id=<?= $id ?>&fact=<?= $factPrev ?>"     class="nav-btn" style="padding:2px 5px;font-size:10px;">◀</a>
                <span style="font-size:10px;color:var(--th-color-primary);font-weight:bold;padding:2px 5px;"><?= ($idxFact+1) ?> / <?= count($factures) ?></span>
                <a href="?id=<?= $id ?>&fact=<?= $factNext ?>"     class="nav-btn" style="padding:2px 5px;font-size:10px;">▶</a>
                <a href="?id=<?= $id ?>&fact=<?= $factDerniere ?>" class="nav-btn" style="padding:2px 5px;font-size:10px;">▶|</a>
                <button type="button" onclick="toggleNouvelleFacture('acc')" class="nav-btn" style="background:#27ae60;padding:2px 5px;font-size:10px;">✚</button>
            </div>
            <?php else: ?>
            <p style="color:var(--th-color-text-muted);font-size:12px;">Aucune facture</p>
            <div style="display:flex;justify-content:center;margin-top:8px;">
                <button type="button" onclick="toggleNouvelleFacture('acc')" class="nav-btn" style="background:#27ae60;">✚ Nouvelle facture</button>
            </div>
            <?php endif; ?>

            <!-- FORMULAIRE NOUVELLE FACTURE — vue Accueil -->
            <div id="formNouvelleFacture_acc" style="display:none;margin-top:10px;border-top:2px solid #1a4a7a;padding-top:10px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                    <strong style="color:var(--th-color-primary);font-size:12px;">Nouvelle facture</strong>
                    <button type="button" onclick="toggleNouvelleFacture('acc')" style="background:none;border:none;cursor:pointer;color:var(--th-color-text-muted);font-size:14px;">✕</button>
                </div>
                <div style="margin-bottom:8px;">
                    <label style="font-size:11px;font-weight:600;">Date facture :</label>
                    <input type="date" id="nf_date_acc" value="<?= date('Y-m-d') ?>" style="margin-left:8px;border:1px solid #cdd5de;border-radius:3px;padding:3px 6px;font-size:12px;">
                </div>
                <table style="width:100%;border-collapse:collapse;font-size:11px;">
                    <thead style="background:#1a4a7a;color:white;">
                        <tr>
                            <th style="padding:4px 6px;text-align:left;">Date acte</th>
                            <th style="padding:4px 6px;text-align:left;">Acte</th>
                            <th style="padding:4px 6px;text-align:right;">Versé</th>
                            <th style="padding:4px 6px;text-align:right;">Reste</th>
                            <th style="padding:4px 6px;"></th>
                        </tr>
                    </thead>
                    <tbody id="nf_lignes_acc"></tbody>
                    <tfoot>
                        <tr style="background:#f0f4f8;font-weight:bold;font-size:11px;">
                            <td colspan="2" style="padding:4px 6px;">Total</td>
                            <td style="padding:4px 6px;text-align:right;" id="nf_totalPrix_acc">0 DH</td>
                            <td style="padding:4px 6px;text-align:right;" id="nf_totalVerse_acc">0 DH</td>
                            <td style="padding:4px 6px;text-align:right;color:#c0392b;" id="nf_totalDette_acc">0 DH</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
                <div style="display:flex;gap:8px;margin-top:8px;">
                    <button type="button" onclick="nfAjouterLigne('acc')" style="background:#2ecc71;color:white;border:none;border-radius:3px;padding:4px 10px;cursor:pointer;font-size:11px;">✚ Acte</button>
                    <button type="button" onclick="nfEnregistrer(<?= $id ?>,'acc')" style="background:#1a4a7a;color:white;border:none;border-radius:3px;padding:4px 12px;cursor:pointer;font-size:11px;font-weight:600;">💾 Enregistrer</button>
                    <span id="nf_msg_acc" style="font-size:11px;color:#27ae60;align-self:center;"></span>
                </div>
            </div>
        </div>

        <!-- CERTIFICAT (bouton seulement, zone cachée) -->
        <div style="margin-top:8px;border-top:1px solid #eee;padding-top:8px;">
            <button type="button"
                onclick="var z=document.getElementById('cert-zone-acc');z.style.display=z.style.display==='none'?'block':'none'"
                style="background:var(--th-bg-card);color:var(--th-color-text);border:1px solid #ccc;border-radius:4px;padding:4px 12px;cursor:pointer;font-size:12px;">
                Certificat médical
            </button>
            <div id="cert-zone-acc" style="display:none;background:#f0f4f8;border-radius:6px;padding:8px;margin-top:8px;border:1px solid #dde3ea;">
                <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;font-size:12px;">
                    <span>du</span>
                    <input type="date" id="cert_debut_acc" style="border:1px solid #ddd;border-radius:3px;padding:3px 6px;font-size:12px;" onchange="calcNbrJAcc()">
                    <span>au</span>
                    <input type="date" id="cert_fin_acc"   style="border:1px solid #ddd;border-radius:3px;padding:3px 6px;font-size:12px;" onchange="calcNbrJAcc()">
                    <span>Nbr J</span>
                    <input type="number" id="cert_nbrj_acc" style="width:55px;border:1px solid #ddd;border-radius:3px;padding:3px 6px;font-size:12px;text-align:center;" readonly>
                    <button type="button" onclick="ouvrirCertificat('M','acc')" style="background:#1a4a7a;color:white;border:none;border-radius:3px;padding:4px 10px;cursor:pointer;font-size:11px;" title="Imprimer — l'intéressé">🖨️ ♂</button>
                    <button type="button" onclick="ouvrirCertificat('F','acc')" style="background:#8e44ad;color:white;border:none;border-radius:3px;padding:4px 10px;cursor:pointer;font-size:11px;" title="Imprimer — l'intéressée">🖨️ ♀</button>
                </div>
            </div>
        </div>

        </div><!-- FIN COL DROITE ACCUEIL -->
        </div><!-- FIN GRILLE ACCUEIL -->

        <!-- NAVIGATION ORDONNANCE (vue accueil) -->
        <div class="nav-ord-barre">
            <a href="?id=<?= $id ?>&ord=<?= $ordPremiere ?>" class="nav-btn" title="Première ordonnance">|◀</a>
            <a href="?id=<?= $id ?>&ord=<?= $ordPrev ?>"     class="nav-btn" title="Précédente">◀</a>
            <span style="font-size:12px;color:var(--th-color-primary);font-weight:bold;padding:3px 10px;white-space:nowrap;background:var(--th-bg-link-hover);border-radius:4px;border:1px solid var(--th-border-statsbar);"><?= (count($ordonnances) - $idxOrd) ?> / <?= count($ordonnances) ?></span>
            <a href="?id=<?= $id ?>&ord=<?= $ordNext ?>"     class="nav-btn" title="Suivante">▶</a>
            <a href="?id=<?= $id ?>&ord=<?= $ordDerniere ?>" class="nav-btn" title="Dernière">▶|</a>
            <button type="button" onclick="afficherNouvelleOrdonnance()" class="nav-btn" style="background:#27ae60;" title="Nouvelle ordonnance">✚</button>
            <a href="ordonnances.php?id=<?= $id ?>" class="nav-btn" style="background:#2e6da4;" title="Toutes les ordonnances">📋 Liste</a>
            <button type="button" onclick="afficherModifierOrdonnance()" class="nav-btn" style="background:#e67e22;" title="Modifier ordonnance">✏️</button>
        </div>

        </div><!-- FIN section-accueil -->

        <!-- ══════════════════════════════════════════════════
             VUE CONSULTATION (identique à l'original)
        ══════════════════════════════════════════════════ -->
        <div id="section-consultation" style="display:none;">

        <!-- Recrutement consultation -->
        <div style="font-size:11px;color:var(--th-color-text-muted);margin-bottom:6px;">
            <span>🏥 <strong>Recrutement :</strong> <?= $datePVAff ?></span>
        </div>

        <div id="ord-affichage" style="display:grid;grid-template-columns:1fr 380px;gap:8px;align-items:start;margin-bottom:8px;">

        <!-- COL GAUCHE : TABLEAU RDV + MÉDICAMENTS -->
        <div>
        <table class="tableau-rdv">
            <thead>
                <tr>
                    <th style="background:#1a4a7a;color:white;width:70px;"></th>
                    <th style="background:var(--th-col-visite);color:white;font-size:11px;">🏥 Dernière visite</th>
                    <th style="background:var(--th-col-rdvp);color:white;font-size:11px;">📅 RDV prévu</th>
                    <th style="background:#27ae60;color:white;font-size:11px;">🩺 Actuel visite<br><span style="font-size:10px;font-weight:normal;"><?= date('d/m/Y') ?></span></th>
                    <th style="background:var(--th-col-rdvn);color:white;font-size:11px;">📆 RDV prochain</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>📅 Date<br>⏰ Heure</td>
                    <td class="col-rdv-fixe" style="text-align:center;">
                        <strong style="color:var(--th-col-visite);font-size:13px;"><?= $dv_dateOrd ?></strong><br>
                        <strong style="color:var(--th-col-visite);font-size:12px;"><?= $dv_heure ?></strong>
                    </td>
                    <td style="background:var(--th-col-rdvp-bg);text-align:center;">
                        <strong style="color:var(--th-col-rdvp);font-size:13px;"><?= $rdvp_date ?></strong><br>
                        <strong style="color:var(--th-col-rdvp);font-size:12px;"><?= $rdvp_heure ?></strong>
                    </td>
                    <td class="col-visite" style="text-align:center;">
                        <strong style="color:#27ae60;font-size:13px;"><?= date('d/m/Y') ?></strong><br>
                        <div style="display:inline-flex;align-items:center;gap:3px;">
                            <input type="time" id="heure_consultation_cons"
                                   value="<?= $heureVisite ?>"
                                   style="border:1px solid #b2dfb2;border-radius:3px;padding:1px 4px;font-size:11px;color:#27ae60;font-weight:bold;width:70px;background:#f0fff0;">
                            <button type="button" onclick="enregistrerHeureVisite()"
                                    title="Enregistrer l'heure"
                                    style="background:#27ae60;color:white;border:none;border-radius:3px;padding:1px 5px;cursor:pointer;font-size:10px;line-height:1.4;">💾</button>
                        </div>
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
                            <input type="date" id="rdv_futur_visible_cons" value="<?= $rdvFuturVal ?>"
                                   onchange="rdvDateChange(this.value,'rdv')"
                                   ondblclick="if(this.value) window.location.href='agenda.php?date='+this.value"
                                   title="Double-clic → ouvrir l'agenda ce jour"
                                   style="flex:1;padding:3px 4px;border:1px solid var(--th-col-rdvn);border-radius:3px;font-size:11px;cursor:pointer;">
                            <div id="rdv_heure_affichage_cons" style="background:var(--th-col-rdvn-bg-hover);color:var(--th-col-rdvn);padding:3px 8px;border-radius:3px;font-size:12px;font-weight:bold;white-space:nowrap;">
                                <?= !empty($ordCourante['HeureRDV']) ? htmlspecialchars($ordCourante['HeureRDV']) : '—:——' ?>
                            </div>
                        </div>
                        <div class="jauge-jour" id="rdv_jauge_cons" style="display:none;">
                            <span id="rdv_jauge_txt_cons" style="white-space:nowrap;color:var(--th-color-text-muted);font-size:10px;"></span>
                            <div class="jauge-bar"><div class="jauge-fill ok" id="rdv_jauge_fill_cons" style="width:0%"></div></div>
                        </div>
                        <div class="creneaux-wrap">
                            <div class="creneaux-loading" id="rdv_loading_cons" style="display:none;">⏳ Chargement…</div>
                            <div class="creneaux-msg"     id="rdv_msg_cons"     style="display:none;"></div>
                            <div class="creneaux-grille"  id="rdv_grille_cons"></div>
                        </div>
                    </td>
                </tr>
                <!-- Ligne Délai -->
                <tr>
                    <td>⏱ Délai</td>
                    <td class="col-rdv-fixe"><span style="color:#aaa;">—</span></td>
                    <td style="background:var(--th-col-rdvp-bg);color:var(--th-col-rdvp);font-weight:bold;"><?= $rdvp_delai ?? '—' ?></td>
                    <td class="col-visite;color:#27ae60;font-weight:bold;"><?= $delaiVisite ?: '—' ?></td>
                    <td class="col-rdv-futur" style="padding:4px;"></td>
                </tr>
                <!-- Ligne ECG -->
                <tr>
                    <td>⚡ ECG (<?= $tot_ecg ?>)</td>
                    <td class="col-rdv-fixe"><span style="color:<?= $dv_acte_ecg!=='—'?'var(--th-col-visite)':'#ccc' ?>;font-weight:bold;"><?= $dv_acte_ecg ?></span></td>
                    <td style="background:var(--th-col-rdvp-bg);"><span style="color:<?= $rdvp_ecg!=='—'?'var(--th-col-rdvp)':'#ccc' ?>;font-weight:bold;"><?= $rdvp_ecg ?></span></td>
                    <td class="col-visite"><?= $act_ecg ?></td>
                    <td class="col-rdv-futur" style="padding:4px;"></td>
                </tr>
                <!-- Ligne EDC -->
                <tr>
                    <td>🫀 EDC (<?= $tot_edc ?>)</td>
                    <td class="col-rdv-fixe"><span style="color:<?= $dv_acte_edc!=='—'?'var(--th-col-visite)':'#ccc' ?>;font-weight:bold;"><?= $dv_acte_edc ?></span></td>
                    <td style="background:var(--th-col-rdvp-bg);"><span style="color:<?= $rdvp_edc!=='—'?'var(--th-col-rdvp)':'#ccc' ?>;font-weight:bold;"><?= $rdvp_edc ?></span></td>
                    <td class="col-visite"><?= $act_edc ?></td>
                    <td class="col-rdv-futur" style="padding:4px;"></td>
                </tr>
                <!-- Ligne DTSA -->
                <tr>
                    <td>🔬 DTSA (<?= $tot_dtsa ?>)</td>
                    <td class="col-rdv-fixe"><span style="color:<?= $dv_acte_dtsa!=='—'?'var(--th-col-visite)':'#ccc' ?>;font-weight:bold;"><?= $dv_acte_dtsa ?></span></td>
                    <td style="background:var(--th-col-rdvp-bg);"><span style="color:<?= $rdvp_dtsa!=='—'?'var(--th-col-rdvp)':'#ccc' ?>;font-weight:bold;"><?= $rdvp_dtsa ?></span></td>
                    <td class="col-visite"><?= $act_dtsa ?></td>
                    <td class="col-rdv-futur" style="padding:4px;"></td>
                </tr>
            </tbody>
        </table>

        <!-- MÉDICAMENTS -->
        <div class="champ" style="margin-top:4px;">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                <label style="font-size:11px;font-weight:bold;color:var(--th-color-primary);margin:0;">💊 Médicaments (<?= count($medicaments) ?>)</label>
                <button type="button" onclick="reportTraitement(3,<?= $id ?>)" style="background:#e67e22;color:white;border:none;padding:2px 8px;border-radius:3px;cursor:pointer;font-size:10px;font-weight:bold;">↺ 3M</button>
                <button type="button" onclick="reportTraitement(6,<?= $id ?>)" style="background:#c0392b;color:white;border:none;padding:2px 8px;border-radius:3px;cursor:pointer;font-size:10px;font-weight:bold;">↺ 6M</button>
                <a href="print_ordonnance.php?id=<?= $id ?>&ord=<?= $nOrd ?>" target="_blank" style="background:#1a4a7a;color:white;border:none;padding:2px 8px;border-radius:3px;cursor:pointer;font-size:10px;font-weight:bold;text-decoration:none;" title="Imprimer">🖨️</a>
                <?php if ($ordCourante && !empty($ordCourante['date_ordon'])): ?>
                <?php
                    $tsOrd2 = strtotime($ordCourante['date_ordon']);
                    $dateOrd2 = ($tsOrd2 && $tsOrd2 > 0) ? date('d/m/Y', $tsOrd2) : '—';
                    $estAuj2  = ($tsOrd2 && date('Y-m-d', $tsOrd2) === date('Y-m-d'));
                    $coul2 = $estAuj2 ? '#e74c3c' : 'var(--th-col-visite)';
                    $bg2   = $estAuj2 ? '#fdecea' : 'var(--th-col-visite-bg)';
                    $bord2 = $estAuj2 ? '#e74c3c' : 'var(--th-col-visite)';
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
                <span style="font-size:10px;color:var(--th-color-text-muted);text-transform:uppercase;">Médicament</span>
                <span style="font-size:10px;color:var(--th-color-text-muted);text-transform:uppercase;">Posologie</span>
                <span style="font-size:10px;color:var(--th-color-text-muted);text-transform:uppercase;">Durée</span>
            </div>
            <?php foreach ($medicaments as $m): ?>
            <div style="display:grid;grid-template-columns:2fr 2fr 1fr;gap:4px;margin-bottom:3px;">
                <input type="text" value="<?= htmlspecialchars($m['PRODUIT'] ?? '') ?>" readonly style="padding:3px 5px;border:1px solid #ddd;border-radius:3px;font-size:11px;background:#f9f9f9;">
                <input type="text" value="<?= htmlspecialchars($m['posologie'] ?? '') ?>" readonly style="padding:3px 5px;border:1px solid #ddd;border-radius:3px;font-size:11px;background:#f9f9f9;">
                <input type="text" value="<?= htmlspecialchars($m['DUREE'] ?? '') ?>" readonly style="padding:3px 5px;border:1px solid #ddd;border-radius:3px;font-size:11px;background:#f9f9f9;">
            </div>
            <?php endforeach; ?>
            <?php else: ?><p style="color:var(--th-color-text-muted);font-size:12px;">Aucun médicament</p><?php endif; ?>
        </div>
        </div><!-- FIN COL GAUCHE -->

        <!-- COL DROITE : FACTURATION -->
        <div>
            <div class="card-title" style="display:flex;justify-content:space-between;align-items:flex-start;padding-bottom:6px;">
                <span style="font-size:13px;">💰 Facturation</span>
                <?php if ($factCourante): ?>
                <?php
                    $tsFactTitre  = strtotime($factCourante['date_facture'] ?? '');
                    $dateFactTitre = ($tsFactTitre && $tsFactTitre > 86400) ? date('d/m/Y', $tsFactTitre) : '—';
                    $estAujFact   = ($tsFactTitre && $tsFactTitre > 86400 && date('Y-m-d', $tsFactTitre) === date('Y-m-d'));
                    $coulFact = $estAujFact ? '#e74c3c' : 'var(--th-col-visite)';
                    $bgFact   = $estAujFact ? '#fdecea' : 'var(--th-col-visite-bg)';
                    $bordFact = $estAujFact ? '#e74c3c' : 'var(--th-col-visite)';
                ?>
                <div style="text-align:right;">
                    <span style="font-family:Arial,sans-serif;font-weight:bold;font-size:12px;
                                 color:<?= $coulFact ?>;background:<?= $bgFact ?>;
                                 padding:2px 8px;border-radius:4px;
                                 border:1px solid <?= $bordFact ?>;">
                        <?= $dateFactTitre ?>
                    </span>
                    <div style="font-size:11px;color:#aaa;margin-top:2px;padding-right:2px;">N° <?= $factCourante['n_facture'] ?></div>
                </div>
                <?php endif; ?>
            </div>
            <div id="fact-affichage">
            <?php if ($factCourante): ?>
            <?php $tsF = strtotime($factCourante['date_facture'] ?? ''); $dateFactVal = ($tsF && $tsF > 86400) ? date('Y-m-d', $tsF) : ''; ?>
            <table style="width:100%;border-collapse:collapse;font-size:11px;">
                <thead style="background:#1a4a7a;color:white;">
                    <tr>
                        <th style="padding:4px 6px;text-align:left;">Date acte</th>
                        <th style="padding:4px 6px;text-align:left;">Acte</th>
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
                    <td style="padding:4px 6px;text-align:right;"><?= number_format($da['Versé'], 0, ',', ' ') ?></td>
                    <td style="padding:4px 6px;text-align:right;color:<?= $da['dette']>0?'#e74c3c':'#27ae60' ?>;"><?= number_format($da['dette'], 0, ',', ' ') ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot style="background:#f0f4f8;font-weight:bold;color:#333;">
                    <tr>
                        <td colspan="2" style="padding:4px 6px;">Total</td>
                        <td style="padding:4px 6px;text-align:right;"><?= number_format($factCourante['verse_total'], 0, ',', ' ') ?> DH</td>
                        <td style="padding:4px 6px;text-align:right;color:<?= $factCourante['dette_total']>0?'#e74c3c':'#27ae60' ?>;"><?= number_format($factCourante['dette_total'], 0, ',', ' ') ?> DH</td>
                    </tr>
                </tfoot>
            </table>
            <div style="display:flex;justify-content:center;gap:2px;margin-top:6px;">
                <a href="?id=<?= $id ?>&fact=<?= $factPremiere ?>" class="nav-btn" style="padding:2px 5px;font-size:10px;">|◀</a>
                <a href="?id=<?= $id ?>&fact=<?= $factPrev ?>"     class="nav-btn" style="padding:2px 5px;font-size:10px;">◀</a>
                <span style="font-size:10px;color:var(--th-color-primary);font-weight:bold;padding:2px 5px;white-space:nowrap;"><?= ($idxFact+1) ?> / <?= count($factures) ?></span>
                <a href="?id=<?= $id ?>&fact=<?= $factNext ?>"     class="nav-btn" style="padding:2px 5px;font-size:10px;">▶</a>
                <a href="?id=<?= $id ?>&fact=<?= $factDerniere ?>" class="nav-btn" style="padding:2px 5px;font-size:10px;">▶|</a>
                <button type="button" onclick="toggleNouvelleFacture('cons')" class="nav-btn" style="background:#27ae60;padding:2px 5px;font-size:10px;">✚</button>
				<a href="factures.php?id=<?= $id ?>" class="nav-btn" style="background:#2e6da4;padding:2px 5px;font-size:10px;" title="Toutes les factures">💰 Liste</a>
            </div>
            <?php else: ?>
                <p style="color:var(--th-color-text-muted);font-size:12px;">Aucune facture</p>
                <div style="display:flex;justify-content:center;margin-top:8px;">
                    <button type="button" onclick="toggleNouvelleFacture('cons')" class="nav-btn" style="background:#27ae60;">✚ Nouvelle facture</button>
                </div>
            <?php endif; ?>
            </div>

            <!-- FORMULAIRE NOUVELLE FACTURE -->
            <div id="formNouvelleFacture_cons" style="display:none;margin-top:10px;border-top:2px solid #1a4a7a;padding-top:10px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                    <strong style="color:var(--th-color-primary);font-size:12px;">Nouvelle facture</strong>
                    <button type="button" onclick="toggleNouvelleFacture('cons')" style="background:none;border:none;cursor:pointer;color:var(--th-color-text-muted);font-size:14px;">✕</button>
                </div>
                <div style="margin-bottom:8px;">
                    <label style="font-size:11px;font-weight:600;">Date facture :</label>
                    <input type="date" id="nf_date_cons" value="<?= date('Y-m-d') ?>" style="margin-left:8px;border:1px solid #cdd5de;border-radius:3px;padding:3px 6px;font-size:12px;">
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
                    <tbody id="nf_lignes_cons"></tbody>
                    <tfoot>
                        <tr style="background:#f0f4f8;font-weight:bold;font-size:11px;">
                            <td colspan="2" style="padding:4px 6px;">Total</td>
                            <td style="padding:4px 6px;text-align:right;" id="nf_totalPrix_cons">0 DH</td>
                            <td style="padding:4px 6px;text-align:right;" id="nf_totalVerse_cons">0 DH</td>
                            <td style="padding:4px 6px;text-align:right;color:#c0392b;" id="nf_totalDette_cons">0 DH</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
                <div style="display:flex;gap:8px;margin-top:8px;">
                    <button type="button" onclick="nfAjouterLigne('cons')" style="background:#2ecc71;color:white;border:none;border-radius:3px;padding:4px 10px;cursor:pointer;font-size:11px;">✚ Acte</button>
                    <button type="button" onclick="nfEnregistrer(<?= $id ?>,'cons')" style="background:#1a4a7a;color:white;border:none;border-radius:3px;padding:4px 12px;cursor:pointer;font-size:11px;font-weight:600;">💾 Enregistrer</button>
                    <span id="nf_msg_cons" style="font-size:11px;color:#27ae60;align-self:center;"></span>
                </div>
            </div>

            <!-- ══ CERTIFICAT MÉDICAL + RECRUTEMENT + TABLEAU ACTES ══ -->
            <div style="margin-top:12px;border-top:2px solid #e0e0e0;padding-top:10px;">

                <!-- BOUTON CERTIFICAT -->
                <button type="button"
                    onclick="var z=document.getElementById('cert-zone');z.style.display=z.style.display==='none'?'block':'none'"
                    style="background:var(--th-bg-card);color:var(--th-color-text);border:1px solid #ccc;border-radius:4px;padding:4px 12px;cursor:pointer;font-size:12px;font-weight:normal;margin-bottom:10px;">
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
                        <button type="button" onclick="ouvrirCertificat('M','cons')" style="background:#1a4a7a;color:white;border:none;border-radius:3px;padding:4px 10px;cursor:pointer;font-size:11px;" title="Imprimer — l'intéressé">🖨️ ♂</button>
                        <button type="button" onclick="ouvrirCertificat('F','cons')" style="background:#8e44ad;color:white;border:none;border-radius:3px;padding:4px 10px;cursor:pointer;font-size:11px;" title="Imprimer — l'intéressée">🖨️ ♀</button>
                    </div>
                </div>

            </div><!-- FIN section certificat -->

        </div><!-- FIN COL DROITE -->

        </div><!-- FIN GRID -->

        <!-- NAVIGATION ORDONNANCE -->
        <div class="nav-ord-barre">
            <a href="?id=<?= $id ?>&ord=<?= $ordPremiere ?>" class="nav-btn" title="Première ordonnance">|◀</a>
            <a href="?id=<?= $id ?>&ord=<?= $ordPrev ?>"     class="nav-btn" title="Précédente">◀</a>
            <span style="font-size:12px;color:var(--th-color-primary);font-weight:bold;padding:3px 10px;white-space:nowrap;background:var(--th-bg-link-hover);border-radius:4px;border:1px solid var(--th-border-statsbar);"><?= (count($ordonnances) - $idxOrd) ?> / <?= count($ordonnances) ?></span>
            <a href="?id=<?= $id ?>&ord=<?= $ordNext ?>"     class="nav-btn" title="Suivante">▶</a>
            <a href="?id=<?= $id ?>&ord=<?= $ordDerniere ?>" class="nav-btn" title="Dernière">▶|</a>
            <button type="button" onclick="afficherNouvelleOrdonnance()" class="nav-btn" style="background:#27ae60;" title="Nouvelle ordonnance">✚</button>
			<a href="ordonnances.php?id=<?= $id ?>" class="nav-btn" style="background:#2e6da4;" title="Toutes les ordonnances">📋 Liste</a>
        <button type="button" onclick="afficherModifierOrdonnance()" class="nav-btn" style="background:#e67e22;" title="Modifier ordonnance">✏️</button>
		</div>

        </div><!-- FIN vue-ordonnance -->

        <!-- ── Grille Motif / Antécédents / FDR / Diagnostic ── -->
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:5px;margin-top:8px;border-top:2px solid #e0e8f0;padding-top:6px;">

            <!-- Motif de consultation -->
            <div style="background:#f5f9ff;border:1px solid #c5d8ed;border-radius:6px;padding:5px 6px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
                    <span style="font-size:11px;font-weight:bold;color:var(--th-color-primary);">📋 Motif</span>
                    <div style="display:flex;gap:3px;">
                        <button type="button" onclick="ouvrirPopupMAD('motif')" style="background:#2e6da4;color:white;border:none;border-radius:3px;padding:1px 6px;font-size:10px;cursor:pointer;" title="Ouvrir liste Motif">📋</button>
                        <button type="button" onclick="viderChamp('champ_motif','MOTIF CONSULTATION')" style="background:#e74c3c;color:white;border:none;border-radius:3px;padding:1px 5px;font-size:10px;cursor:pointer;">✕</button>
                    </div>
                </div>
                <textarea id="champ_motif" onblur="sauvegarderChamp('MOTIF CONSULTATION', this.value)"
                    style="border:1px solid #ddd;border-radius:3px;padding:2px 4px;width:100%;font-size:11px;font-family:Arial,sans-serif;line-height:1.3;resize:vertical;min-height:55px;field-sizing:content;"
                ><?= htmlspecialchars($patient['MOTIF CONSULTATION'] ?? '') ?></textarea>
            </div>

            <!-- Antécédents -->
            <div style="background:#f5f9ff;border:1px solid #c5d8ed;border-radius:6px;padding:5px 6px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
                    <span style="font-size:11px;font-weight:bold;color:var(--th-color-primary);">📂 Antécédents</span>
                    <div style="display:flex;gap:3px;">
                        <button type="button" onclick="ouvrirPopupMAD('atcd')" style="background:#2e6da4;color:white;border:none;border-radius:3px;padding:1px 6px;font-size:10px;cursor:pointer;" title="Ouvrir liste Antécédents">📋</button>
                        <button type="button" onclick="viderChamp('champ_atcd','ATCD')" style="background:#e74c3c;color:white;border:none;border-radius:3px;padding:1px 5px;font-size:10px;cursor:pointer;">✕</button>
                    </div>
                </div>
                <textarea id="champ_atcd" onblur="sauvegarderChamp('ATCD', this.value)"
                    style="border:1px solid #ddd;border-radius:3px;padding:2px 4px;width:100%;font-size:11px;font-family:Arial,sans-serif;line-height:1.3;resize:vertical;min-height:55px;field-sizing:content;"
                ><?= htmlspecialchars($patient['ATCD'] ?? '') ?></textarea>
            </div>

            <!-- Facteurs de risque -->
            <div style="background:#f5f9ff;border:1px solid #c5d8ed;border-radius:6px;padding:5px 6px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
                    <span style="font-size:11px;font-weight:bold;color:var(--th-color-primary);">⚠️ Facteurs de risque</span>
                    <div style="display:flex;gap:3px;">
                        <button type="button" onclick="ouvrirPopupMAD('fdr')" style="background:#2e6da4;color:white;border:none;border-radius:3px;padding:1px 6px;font-size:10px;cursor:pointer;" title="Ouvrir liste Facteurs de risque">📋</button>
                        <button type="button" onclick="viderChamp('champ_fdr','CHAMP_FDR')" style="background:#e74c3c;color:white;border:none;border-radius:3px;padding:1px 5px;font-size:10px;cursor:pointer;">✕</button>
                    </div>
                </div>
                <textarea id="champ_fdr" onblur="sauvegarderChamp('CHAMP_FDR', this.value)"
                    style="border:1px solid #ddd;border-radius:3px;padding:2px 4px;width:100%;font-size:11px;font-family:Arial,sans-serif;line-height:1.3;resize:vertical;min-height:55px;field-sizing:content;"
                ><?= htmlspecialchars($patient['CHAMP_FDR'] ?? '') ?></textarea>
            </div>

            <!-- Diagnostic -->
            <div style="background:#f5f9ff;border:1px solid #c5d8ed;border-radius:6px;padding:5px 6px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
                    <span style="font-size:11px;font-weight:bold;color:var(--th-color-primary);">🩺 Diagnostic</span>
                    <div style="display:flex;gap:3px;">
                        <button type="button" onclick="ouvrirPopupMAD('diag')" style="background:#2e6da4;color:white;border:none;border-radius:3px;padding:1px 6px;font-size:10px;cursor:pointer;" title="Ouvrir liste Diagnostic">📋</button>
                        <button type="button" onclick="viderChamp('champ_diagnostic','diagnostic')" style="background:#e74c3c;color:white;border:none;border-radius:3px;padding:1px 5px;font-size:10px;cursor:pointer;">✕</button>
                    </div>
                </div>
                <textarea id="champ_diagnostic" onblur="sauvegarderChamp('diagnostic', this.value)"
                    style="border:1px solid #ddd;border-radius:3px;padding:2px 4px;width:100%;font-size:11px;font-family:Arial,sans-serif;line-height:1.3;resize:vertical;min-height:55px;field-sizing:content;"
                ><?= htmlspecialchars($patient['diagnostic'] ?? '') ?></textarea>
            </div>

        </div><!-- fin grille MAD -->

        </div><!-- FIN section-consultation -->

        <?php else: ?>
            <p style="color:var(--th-color-text-muted);font-size:12px;">Aucune ordonnance</p>
            <div class="nav-ord-barre">
                <a href="?id=<?= $id ?>&ord=<?= $ordPremiere ?>" class="nav-btn">|◀</a>
                <a href="?id=<?= $id ?>&ord=<?= $ordPrev ?>"     class="nav-btn">◀</a>
                <span style="font-size:12px;color:var(--th-color-primary);font-weight:bold;padding:3px 10px;white-space:nowrap;background:var(--th-bg-link-hover);border-radius:4px;border:1px solid var(--th-border-statsbar);">0 / 0</span>
                <a href="?id=<?= $id ?>&ord=<?= $ordNext ?>"     class="nav-btn">▶</a>
                <a href="?id=<?= $id ?>&ord=<?= $ordDerniere ?>" class="nav-btn">▶|</a>
                <button type="button" onclick="afficherNouvelleOrdonnance()" class="nav-btn" style="background:#27ae60;">✚</button>
            </div>
        <?php endif; ?>

    </div><!-- FIN card ordonnance -->
</div><!-- FIN col-mid -->
<?php
/* ── Navigation globale colonne droite ── */
$eFirst = $examens ? $examens[0]['N1'] : 0;
$eLast  = $examens ? $examens[count($examens)-1]['N1'] : 0;
$ePrev  = ($examens && $idxExam > 0) ? $examens[$idxExam-1]['N1'] : $nExam;
$eNext  = ($examens && $idxExam < count($examens)-1) ? $examens[$idxExam+1]['N1'] : $nExam;
$gFirst = $ecgs ? $ecgs[0]['N°'] : 0;
$gLast  = $ecgs ? $ecgs[count($ecgs)-1]['N°'] : 0;
$gPrev  = ($ecgs && $idxECG > 0) ? $ecgs[$idxECG-1]['N°'] : $nECG;
$gNext  = ($ecgs && $idxECG < count($ecgs)-1) ? $ecgs[$idxECG+1]['N°'] : $nECG;
$oFirst = $echos ? $echos[0]['N°'] : 0;
$oLast  = $echos ? $echos[count($echos)-1]['N°'] : 0;
$oPrev  = ($echos && $idxEcho > 0) ? $echos[$idxEcho-1]['N°'] : $nEcho;
$oNext  = ($echos && $idxEcho < count($echos)-1) ? $echos[$idxEcho+1]['N°'] : $nEcho;
$urlBase  = "?id=$id";
$urlFirst = "$urlBase&exam=$eFirst&ecg=$gFirst&echo=$oFirst";
$urlPrev  = "$urlBase&exam=$ePrev&ecg=$gPrev&echo=$oPrev";
$urlNext  = "$urlBase&exam=$eNext&ecg=$gNext&echo=$oNext";
$urlLast  = "$urlBase&exam=$eLast&ecg=$gLast&echo=$oLast";
$labelNav = $examen ? date('d/m/Y', strtotime($examen['DateExam'])) : '—';
$posExam  = count($examens) ? ($idxExam+1).'/'.count($examens) : '—';
?>
<!-- ══ COLONNE DROITE ══ -->
<div class="col-right" id="col-right-exam">

    <!-- Barre navigation globale — en tête de col-right -->
    <div style="background:#e8f0fa;border:1px solid #c5d8ed;border-radius:5px;padding:4px 8px;display:flex;align-items:center;gap:3px;">
        <span style="font-size:10px;font-weight:bold;color:var(--th-color-primary);white-space:nowrap;margin-right:4px;">🔀</span>
        <a href="<?= $urlFirst ?>" class="nav-btn" style="padding:1px 5px;font-size:11px;" title="Plus récent (tous)">|◀</a>
        <a href="<?= $urlPrev ?>"  class="nav-btn" style="padding:1px 5px;font-size:11px;" title="Précédent (tous)">◀</a>
        <span style="font-size:10px;color:var(--th-color-primary);font-weight:bold;padding:0 4px;white-space:nowrap;flex:1;text-align:center;"><?= $labelNav ?> <span style="color:var(--th-color-text-muted);font-weight:normal;">(<?= $posExam ?>)</span></span>
        <a href="<?= $urlNext ?>"  class="nav-btn" style="padding:1px 5px;font-size:11px;" title="Suivant (tous)">▶</a>
        <a href="<?= $urlLast ?>"  class="nav-btn" style="padding:1px 5px;font-size:11px;" title="Plus ancien (tous)">▶|</a>
        <a href="nouveau_bilan_clinique.php?id=<?= $id ?>" class="nav-btn" style="background:#27ae60;padding:1px 5px;font-size:11px;margin-left:2px;" title="Nouveau bilan">✚</a>
        <button type="button" onclick="bioNav('first')" class="nav-btn" style="padding:1px 4px;font-size:10px;margin-left:4px;" title="Biologie → plus récent">🧪</button>
    </div>
    <div class="card">
        <?php if ($examen): ?>
        <?php
            $dateExamRaw = $examen['DateExam'] ?? null;
            $dateExamAff = '—';
            $dateExamStyle = 'font-size:11px;font-weight:bold;color:var(--th-color-primary);';
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
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;padding-bottom:4px;border-bottom:2px solid #e0e0e0;">
            <span style="color:var(--th-color-primary);font-size:12px;font-weight:bold;">🩺 Examen</span>
            <div style="display:flex;align-items:center;gap:6px;">
                <span style="<?= $dateExamStyle ?>"><?= $dateExamAff ?></span>
            </div>
        </div>
        <?php if ($examen && !empty($examen['CMLM_EXAMEN'])): ?>
        <div style="background:#ffffff;border:1px solid #2e6da4;border-radius:3px;padding:5px 7px;font-size:11px;color:#0d2b4e;font-weight:500;line-height:1.6;">
            <?= nl2br(htmlspecialchars($examen['CMLM_EXAMEN'])) ?>
        </div>
        <?php elseif ($examen): ?>
            <p style="color:var(--th-color-text-muted);font-size:11px;font-style:italic;">Aperçu non généré — ouvrir le bilan clinique</p>
        <?php else: ?>
            <p style="color:var(--th-color-text-muted);font-size:12px;">Aucun examen enregistré</p>
        <?php endif; ?>
        <!-- Navigation Examen en bas -->
        <div style="display:flex;justify-content:center;gap:2px;margin-top:4px;padding-top:4px;border-top:1px solid #eee;">
            <a href="?id=<?= $id ?>&exam=<?= $examens ? $examens[0]['N1'] : 0 ?>" class="nav-btn" style="padding:1px 4px;font-size:10px;" title="Plus récent">|◀</a>
            <a href="?id=<?= $id ?>&exam=<?= $examens && $idxExam > 0 ? $examens[$idxExam-1]['N1'] : $nExam ?>" class="nav-btn" style="padding:1px 4px;font-size:10px;" title="Précédent (plus récent)">◀</a>
            <span style="font-size:10px;color:var(--th-color-primary);font-weight:bold;padding:0 4px;white-space:nowrap;"><?= count($examens) ? ($idxExam+1).' / '.count($examens) : '0' ?></span>
            <a href="?id=<?= $id ?>&exam=<?= $examens && $idxExam < count($examens)-1 ? $examens[$idxExam+1]['N1'] : $nExam ?>" class="nav-btn" style="padding:1px 4px;font-size:10px;" title="Suivant (plus ancien)">▶</a>
            <a href="?id=<?= $id ?>&exam=<?= $examens ? $examens[count($examens)-1]['N1'] : 0 ?>" class="nav-btn" style="padding:1px 4px;font-size:10px;" title="Plus ancien">▶|</a>
            <a href="nouveau_bilan_clinique.php?id=<?= $id ?>&onglet=examen" class="nav-btn" style="background:#27ae60;padding:1px 4px;font-size:10px;" title="Nouvel examen">✚</a>
        </div>
        <?php endif; ?>
<!-- ══ ECG COMPACT ══ -->
    <div class="card" style="padding:6px;">
        <div class="card-title" style="font-size:11px;margin-bottom:4px;">
            <span>⚡ ECG</span>
            <div style="display:flex;align-items:center;gap:6px;">
                <?php if ($ecgCourant && $ecgCourant['Date ECG']): ?>
                <span style="font-size:10px;font-weight:bold;color:var(--th-color-primary);"><?= date('d/m/Y', strtotime($ecgCourant['Date ECG'])) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($ecgCourant && !empty($ecgCourant['CMLM_ECG'])): ?>
        <div style="background:#ffffff;border:1px solid #2e6da4;border-radius:3px;padding:5px 7px;font-size:11px;color:#0d2b4e;font-weight:500;line-height:1.6;">
            <?= nl2br(htmlspecialchars($ecgCourant['CMLM_ECG'])) ?>
        </div>
        <?php elseif ($ecgCourant): ?>
            <p style="color:var(--th-color-text-muted);font-size:11px;font-style:italic;">Aperçu non généré — ouvrir le bilan clinique</p>
        <?php else: ?>
            <p style="color:var(--th-color-text-muted);font-size:11px;">Aucun ECG enregistré</p>
        <?php endif; ?>
        <!-- Navigation ECG en bas -->
        <div style="display:flex;justify-content:center;gap:2px;margin-top:4px;padding-top:4px;border-top:1px solid #eee;">
            <a href="?id=<?= $id ?>&ecg=<?= $ecgs ? $ecgs[0]['N°'] : 0 ?>" class="nav-btn" style="padding:1px 4px;font-size:10px;" title="Plus récent">|◀</a>
            <a href="?id=<?= $id ?>&ecg=<?= $ecgs && $idxECG > 0 ? $ecgs[$idxECG-1]['N°'] : $nECG ?>" class="nav-btn" style="padding:1px 4px;font-size:10px;" title="Précédent (plus récent)">◀</a>
            <span style="font-size:10px;color:var(--th-color-primary);font-weight:bold;padding:0 4px;white-space:nowrap;"><?= count($ecgs) ? ($idxECG+1).' / '.count($ecgs) : '0' ?></span>
            <a href="?id=<?= $id ?>&ecg=<?= $ecgs && $idxECG < count($ecgs)-1 ? $ecgs[$idxECG+1]['N°'] : $nECG ?>" class="nav-btn" style="padding:1px 4px;font-size:10px;" title="Suivant (plus ancien)">▶</a>
            <a href="?id=<?= $id ?>&ecg=<?= $ecgs ? $ecgs[count($ecgs)-1]['N°'] : 0 ?>" class="nav-btn" style="padding:1px 4px;font-size:10px;" title="Plus ancien">▶|</a>
            <a href="nouveau_bilan_clinique.php?id=<?= $id ?>&onglet=ecg" class="nav-btn" style="background:#27ae60;padding:1px 4px;font-size:10px;" title="Nouvel ECG">✚</a>
        </div>
    </div>

    <!-- ══ ECHO-DOPPLER COMPACT ══ -->
    <div class="card" style="padding:6px;">
        <div class="card-title" style="font-size:11px;margin-bottom:4px;">
            <span>🫀 Echo-Doppler</span>
            <div style="display:flex;align-items:center;gap:6px;">
                <?php if ($echoCourant && $echoCourant['DATEchog']): ?>
                <span style="font-size:10px;font-weight:bold;color:var(--th-color-primary);"><?= date('d/m/Y', strtotime($echoCourant['DATEchog'])) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($echoCourant && !empty($echoCourant['CMLM_ECHO'])): ?>
        <div style="background:#ffffff;border:1px solid #2e6da4;border-radius:3px;padding:5px 7px;font-size:11px;color:#0d2b4e;font-weight:500;line-height:1.6;">
            <?= nl2br(htmlspecialchars($echoCourant['CMLM_ECHO'])) ?>
        </div>
        <?php elseif ($echoCourant): ?>
            <p style="color:var(--th-color-text-muted);font-size:11px;font-style:italic;">Aperçu non généré — ouvrir le bilan clinique</p>
        <?php else: ?>
            <p style="color:var(--th-color-text-muted);font-size:11px;">Aucun Echo enregistré</p>
        <?php endif; ?>
        <!-- Navigation Echo en bas -->
        <div style="display:flex;justify-content:center;gap:2px;margin-top:4px;padding-top:4px;border-top:1px solid #eee;">
            <a href="?id=<?= $id ?>&echo=<?= $echos ? $echos[0]['N°'] : 0 ?>" class="nav-btn" style="padding:1px 4px;font-size:10px;" title="Plus récent">|◀</a>
            <a href="?id=<?= $id ?>&echo=<?= $echos && $idxEcho > 0 ? $echos[$idxEcho-1]['N°'] : $nEcho ?>" class="nav-btn" style="padding:1px 4px;font-size:10px;" title="Précédent (plus récent)">◀</a>
            <span style="font-size:10px;color:var(--th-color-primary);font-weight:bold;padding:0 4px;white-space:nowrap;"><?= count($echos) ? ($idxEcho+1).' / '.count($echos) : '0' ?></span>
            <a href="?id=<?= $id ?>&echo=<?= $echos && $idxEcho < count($echos)-1 ? $echos[$idxEcho+1]['N°'] : $nEcho ?>" class="nav-btn" style="padding:1px 4px;font-size:10px;" title="Suivant (plus ancien)">▶</a>
            <a href="?id=<?= $id ?>&echo=<?= $echos ? $echos[count($echos)-1]['N°'] : 0 ?>" class="nav-btn" style="padding:1px 4px;font-size:10px;" title="Plus ancien">▶|</a>
            <a href="nouveau_bilan_clinique.php?id=<?= $id ?>&onglet=echo" class="nav-btn" style="background:#27ae60;padding:1px 4px;font-size:10px;" title="Nouvel Echo">✚</a>
        </div>
    </div>

<!-- ══ BIOLOGIE COMPACT ══ -->
        <div class="card" style="padding:6px;margin-top:6px;" id="card-bio-dossier">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;padding-bottom:3px;border-bottom:2px solid #e0e0e0;">
                <span style="color:var(--th-color-primary);font-size:12px;font-weight:bold;">🧪 Biologie</span>
                <div style="display:flex;align-items:center;gap:5px;">
                    <span id="bio-nb-anormal" style="font-size:10px;font-weight:bold;color:#e74c3c;display:none;"></span>
                    <span id="bio-date-affich" style="font-size:10px;font-weight:bold;color:var(--th-color-primary);">—</span>
                    <button type="button" onclick="toggleApercu('apercu-bio-dossier',this)"
                        id="bio-btn-apercu"
                        style="display:none;background:none;border:1px solid #2e6da4;border-radius:3px;color:#2e6da4;font-size:10px;padding:1px 5px;cursor:pointer;"
                        title="Aperçu rapport">👁</button>
                </div>
            </div>
 
            <!-- Aperçu biologie anormale — visible directement -->
            <div id="apercu-bio-dossier" style="margin-top:4px;">
                <div style="background:#ffffff;border:1px solid #2e6da4;border-radius:3px;padding:5px 7px;font-size:11px;color:#0d2b4e;font-weight:500;line-height:1.6;" id="apercu-bio-texte">
                    <?php
                    $apercuBioLignes = [];
                    foreach ($lignesBioActuel as $bl) {
                        $v = trim($bl['resultat']);
                        if ($v !== '' && strtoupper($v) !== 'N') {
                            $apercuBioLignes[] = htmlspecialchars($bl['nom'])
                                . ' : <strong style="color:#e74c3c;">'
                                . htmlspecialchars($v) . '</strong>';
                        }
                    }
                    echo $apercuBioLignes
                        ? implode('<br>', $apercuBioLignes)
                        : '<span style="color:var(--th-color-text-muted);font-weight:600;">Aucun résultat anormal</span>';
                    ?>
                </div>
            </div>
 
            <!-- Navigation entre bilans -->
            <div style="display:flex;justify-content:center;gap:2px;margin-top:5px;padding-top:4px;border-top:1px solid #eee;">
                <button onclick="bioNav('first')" class="nav-btn" style="padding:1px 4px;font-size:10px;" title="Plus récent">|◀</button>
                <button onclick="bioNav('prev')"  class="nav-btn" style="padding:1px 4px;font-size:10px;" title="Précédent">◀</button>
                <span id="bio-nav-pos" style="font-size:10px;color:var(--th-color-primary);font-weight:bold;padding:0 4px;white-space:nowrap;">
                    <?= $bilansListe ? '1 / '.count($bilansListe) : '0' ?>
                </span>
                <button onclick="bioNav('next')"  class="nav-btn" style="padding:1px 4px;font-size:10px;" title="Suivant">▶</button>
                <button onclick="bioNav('last')"  class="nav-btn" style="padding:1px 4px;font-size:10px;" title="Plus ancien">▶|</button>
                <a href="biologie.php?id=<?= $id ?>" class="nav-btn" style="background:#e67e22;padding:1px 4px;font-size:10px;" title="Ouvrir module Biologie">✚</a>
            </div>
        </div><!-- FIN card biologie -->

    </div>
	
</div>

</div><!-- FIN .main -->

<!-- ══ POPUP NOUVELLE ORDONNANCE ══ -->
<div id="modal-nouvelle-ordonnance" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;overflow-y:auto;">
    <div style="background:var(--th-bg-card);border-radius:8px;padding:20px;margin:40px auto;max-width:700px;box-shadow:0 8px 32px rgba(0,0,0,0.3);position:relative;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;padding-bottom:10px;border-bottom:2px solid #27ae60;">
            <strong style="color:#27ae60;font-size:15px;">✚ Nouvelle ordonnance</strong>
            <button type="button" onclick="masquerNouvelleOrdonnance()" style="background:#e74c3c;color:white;border:none;border-radius:4px;padding:4px 12px;cursor:pointer;font-size:13px;">✕ Annuler</button>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
            <div>
                <label style="font-size:10px;color:var(--th-color-text-muted);font-weight:bold;display:block;margin-bottom:4px;">DATE ORDONNANCE</label>
                <input type="date" id="no_date" value="<?= date('Y-m-d') ?>" style="width:100%;border:1px solid #cdd5de;border-radius:4px;padding:6px 8px;font-size:13px;">
            </div>
            <div>
                <label style="font-size:10px;color:var(--th-color-text-muted);font-weight:bold;display:block;margin-bottom:4px;">ACTE</label>
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
            <label style="font-size:10px;color:var(--th-col-rdvn);font-weight:bold;display:block;margin-bottom:6px;">📅 DATE &amp; HEURE RDV</label>
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
                       style="flex:1;border:1px solid var(--th-col-rdvn);border-radius:4px;padding:5px 8px;font-size:12px;">
                <div id="no_heure_affichage" style="background:var(--th-col-rdvn-bg-hover);color:var(--th-col-rdvn);padding:5px 12px;border-radius:4px;font-size:13px;font-weight:bold;white-space:nowrap;">—:——</div>
            </div>
            <div class="jauge-jour" id="no_jauge" style="display:none;">
                <span id="no_jauge_txt" style="white-space:nowrap;color:var(--th-color-text-muted);font-size:11px;"></span>
                <div class="jauge-bar"><div class="jauge-fill ok" id="no_jauge_fill" style="width:0%"></div></div>
            </div>
            <div class="creneaux-wrap">
                <div class="creneaux-loading" id="no_loading"  style="display:none;">⏳ Chargement…</div>
                <div class="creneaux-msg"     id="no_msg_rdv"  style="display:none;"></div>
                <div class="creneaux-grille"  id="no_grille"></div>
            </div>
        </div>
        <div style="font-size:12px;font-weight:bold;color:var(--th-color-primary);margin-bottom:8px;">💊 Médicaments :</div>
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
// Vérifie la date de début dès qu'elle est saisie, sans attendre la date de fin
function verifierDebutCertificat(debut) {
    if (!debut) return;
    const dDebut = new Date(debut);
    const dAuj   = new Date(new Date().toISOString().slice(0,10));
    if (dDebut < dAuj) {
        confirm('⚠️ La date de début ("du") est antérieure à aujourd\'hui.\nConfirmer quand même ?');
    }
}
// Vérifie la cohérence entre les deux dates (nécessite que les deux soient remplies)
function verifierDatesCertificat(debut, fin, nbrJ) {
    const dDebut = new Date(debut);
    const dFin   = new Date(fin);

    if (dFin < dDebut) {
        alert('⚠️ La date de fin ("au") est antérieure à la date de début ("du").\nMerci de corriger les dates.');
        return;
    }
    if (nbrJ > 30) {
        confirm(`⚠️ L'arrêt dépasse 30 jours (${nbrJ} jours).\nConfirmer quand même ?`);
    }
}
function calcNbrJ() {
    const d1=document.getElementById('cert_debut').value, d2=document.getElementById('cert_fin').value;
    verifierDebutCertificat(d1);
    if (d1&&d2) { const diff=Math.round((new Date(d2)-new Date(d1))/86400000); document.getElementById('cert_nbrj').value=diff>=0?diff:0; verifierDatesCertificat(d1,d2,diff); }
}
function ouvrirCertificat(sexe, vue) {
    const suffix = (vue === 'acc') ? '_acc' : '';
    const debut  = document.getElementById('cert_debut' + suffix).value;
    const fin    = document.getElementById('cert_fin'   + suffix).value;
    if (!debut || !fin) { alert('Veuillez saisir les dates de début et de fin.'); return; }
    const url = `print_certificat.php?id=<?= $id ?>&debut=${debut}&fin=${fin}&sexe=${sexe}`;
    window.open(url, '_blank', 'width=700,height=620');
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
function toggleApercu(id, btn) {
    var el = document.getElementById(id);
    if (!el) return;
    var visible = el.style.display !== 'none';
    el.style.display = visible ? 'none' : 'block';
    btn.style.background = visible ? 'none' : '#2e6da4';
    btn.style.color = visible ? '#2e6da4' : 'white';
}

function dateEnFr(d) {
    if (!d) return '';
    const [a,m,j] = d.split('-');
    const jours = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
    const dateObj = new Date(a, m-1, j);
    return jours[dateObj.getDay()] + ' ' + j+'/'+m+'/'+a;
}

// ════════════════════════════════════════════════════════════
// MODALE JOUR FERMÉ / SPÉCIAL
// ════════════════════════════════════════════════════════════
function jfFermer() {
    const m = document.getElementById('modal-jour-ferme');
    if (m) m.remove();
}
function jfAfficher(data, onChoix, onGarder) {
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
        btns += `<button style="${base}background:#e67e22;color:white;" onclick="jfGarder('${data.date_cible}')">${lbl}</button>`;
    }
    if (data.date_apres)
        btns += `<button style="${base}background:#1a4a7a;color:white;" onclick="jfChoisir('${data.date_apres}')">${data.label_apres} ▶</button>`;
    btns += `<button style="${base}background:#555;color:white;" onclick="jfChoisirDate()">📅 Choisir date</button>`;
    btns += `<button style="${base}background:#ddd;color:#444;" onclick="jfFermer()">✕ Annuler</button>`;

    document.body.insertAdjacentHTML('beforeend', `
    <div id="modal-jour-ferme" style="position:fixed;top:0;left:0;width:100%;height:100%;
         background:rgba(0,0,0,0.5);z-index:9999;display:flex;align-items:center;justify-content:center;">
        <div style="background:var(--th-bg-card);border-radius:10px;padding:24px 28px;
                    max-width:500px;width:92%;box-shadow:0 10px 40px rgba(0,0,0,0.3);">
            <div style="font-size:14px;font-weight:bold;color:var(--th-color-primary);margin-bottom:6px;">${titre}</div>
            <div style="font-size:12px;color:#666;margin-bottom:18px;">${sousTitre}</div>
            <div id="jf-btns" style="display:flex;flex-wrap:wrap;gap:8px;">${btns}</div>
            <div id="jf-datepicker" style="display:none;margin-top:14px;">
                <input type="date" id="jf-input-date"
                       style="padding:5px 8px;border:1px solid #2e6da4;border-radius:4px;font-size:12px;">
                <button style="${base}background:#1a4a7a;color:white;margin-left:8px;" onclick="jfConfirmerDate()">✔ Confirmer</button>
            </div>
        </div>
    </div>`);
    window._jfCallback      = onChoix;
    window._jfCallbackForce = onGarder || onChoix;
}
function jfChoisir(date) {
    jfFermer();
    if (window._jfCallback) { window._jfCallback(date); window._jfCallback = null; }
    window._jfCallbackForce = null;
}
function jfGarder(date) {
    jfFermer();
    if (window._jfCallbackForce) { window._jfCallbackForce(date); window._jfCallbackForce = null; }
    window._jfCallback = null;
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
                (dateChoisie) => verifierEtAppliquerDate(dateChoisie, prefixe, callback),
                (dateGardee) => callback(dateGardee)
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
    const dateVal = document.getElementById('no_date').value;
    if (!dateVal) {
        alert('⛔ Renseignez d\'abord la date de l\'ordonnance avant d\'ajouter un médicament.');
        document.getElementById('no_date').style.border = '2px solid #e74c3c';
        document.getElementById('no_date').focus();
        return;
    }
    document.getElementById('no_date').style.border = '';
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
/* ── Facture : suffixe 'acc' pour vue Accueil ── */
function toggleNouvelleFacture(sfx) {
    sfx = sfx || 'acc';
    const formId = 'formNouvelleFacture_' + sfx;
    const form = document.getElementById(formId);
    if (!form) return;
    const visible = form.style.display !== 'none';
    form.style.display = visible ? 'none' : 'block';
    if (!visible && document.getElementById('nf_lignes_' + sfx).children.length === 0)
        nfAjouterLigne(sfx);
}
function nfAjouterLigne(sfx) {
    sfx = sfx || 'acc';
    const i = nfIdx++;
    const today = document.getElementById('nf_date_' + sfx).value;
    let opts = '<option value="">— Acte —</option>';
    nfActes.forEach(a => { opts += `<option value="${a.n_acte}" data-cout="${a.cout}">${a.ACTE}</option>`; });
    const tr = document.createElement('tr'); tr.style.borderBottom = '1px solid #eee';
    tr.dataset.sfx = sfx;
    tr.innerHTML = `<td style="padding:3px 4px;"><input type="date" id="nf_dateacte_${sfx}_${i}" value="${today}" style="border:1px solid #ddd;border-radius:3px;padding:2px;font-size:11px;width:105px;"></td>
        <td style="padding:3px 4px;"><select id="nf_acte_${sfx}_${i}" onchange="nfRemplirPrix('${sfx}',${i})" style="width:100%;border:1px solid #ddd;border-radius:3px;padding:2px;font-size:11px;">${opts}</select></td>
        <input type="hidden" id="nf_prix_${sfx}_${i}" value="">
        <td style="padding:3px 4px;"><input type="number" id="nf_verse_${sfx}_${i}" min="0" step="0.01" value="0" oninput="nfRecalculer('${sfx}',${i})" style="width:70px;border:1px solid #ddd;border-radius:3px;padding:2px;font-size:11px;text-align:right;"></td>
        <td style="padding:3px 4px;text-align:right;font-weight:600;color:#c0392b;" id="nf_dette_${sfx}_${i}">0</td>
        <td style="padding:3px 4px;"><button type="button" onclick="this.closest('tr').remove();nfMajTotaux('${sfx}')" style="background:#e74c3c;color:white;border:none;border-radius:3px;padding:2px 6px;cursor:pointer;font-size:10px;">✕</button></td>`;
    document.getElementById('nf_lignes_' + sfx).appendChild(tr);
}
function nfRemplirPrix(sfx, i) {
    sfx = sfx || 'acc';
    const sel = document.getElementById(`nf_acte_${sfx}_${i}`);
    const cout = sel.options[sel.selectedIndex]?.getAttribute('data-cout') || '';
    document.getElementById(`nf_prix_${sfx}_${i}`).value = cout;
    // Auto-remplir Versé = Prix (le médecin corrige si paiement partiel)
    const verseEl = document.getElementById(`nf_verse_${sfx}_${i}`);
    if (verseEl) verseEl.value = cout ? parseFloat(cout) : 0;
    nfRecalculer(sfx, i);
}
function nfRecalculer(sfx, i) {
    sfx = sfx || 'acc';
    const prix  = parseFloat(document.getElementById(`nf_prix_${sfx}_${i}`)?.value) || 0;
    const verse = parseFloat(document.getElementById(`nf_verse_${sfx}_${i}`)?.value) || 0;
    const el = document.getElementById(`nf_dette_${sfx}_${i}`);
    if (el) el.textContent = (prix - verse).toLocaleString('fr-FR') + ' DH';
    nfMajTotaux(sfx);
}
function nfMajTotaux(sfx) {
    sfx = sfx || 'acc';
    let tp = 0, tv = 0, td = 0;
    document.querySelectorAll(`#nf_lignes_${sfx} tr`).forEach(tr => {
        const sel = tr.querySelector('select');
        if (!sel) return;
        const idx = sel.id.replace(`nf_acte_${sfx}_`, '');
        const p = parseFloat(document.getElementById(`nf_prix_${sfx}_${idx}`)?.value) || 0;
        const v = parseFloat(document.getElementById(`nf_verse_${sfx}_${idx}`)?.value) || 0;
        tp += p; tv += v; td += (p - v);
    });
    document.getElementById(`nf_totalPrix_${sfx}`).textContent  = tp.toLocaleString('fr-FR') + ' DH';
    document.getElementById(`nf_totalVerse_${sfx}`).textContent = tv.toLocaleString('fr-FR') + ' DH';
    document.getElementById(`nf_totalDette_${sfx}`).textContent = td.toLocaleString('fr-FR') + ' DH';
}
function nfEnregistrer(patientId, sfx) {
    sfx = sfx || 'acc';
    const date_facture = document.getElementById(`nf_date_${sfx}`).value;
    const lignes = [];
    document.querySelectorAll(`#nf_lignes_${sfx} tr`).forEach(tr => {
        const sel = tr.querySelector('select');
        if (!sel) return;
        const idx   = sel.id.replace(`nf_acte_${sfx}_`, '');
        const acte  = document.getElementById(`nf_acte_${sfx}_${idx}`)?.value;
        const prix  = parseFloat(document.getElementById(`nf_prix_${sfx}_${idx}`)?.value) || 0;
        const verse = parseFloat(document.getElementById(`nf_verse_${sfx}_${idx}`)?.value) || 0;
        const dateA = document.getElementById(`nf_dateacte_${sfx}_${idx}`)?.value;
        if (acte) lignes.push({acte, prix, verse, date_acte: dateA});
    });
    const msgEl = document.getElementById(`nf_msg_${sfx}`);
    if (lignes.length === 0) { msgEl.textContent = '⚠ Ajoutez au moins un acte.'; msgEl.style.color = '#e74c3c'; return; }
    msgEl.textContent = 'Enregistrement…'; msgEl.style.color = '#999';
    fetch('ajax_nouvelle_facture.php', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({id: patientId, date_facture, lignes})})
    .then(r => r.json()).then(data => {
        if (data.success) window.location.href = `dossier.php?id=${patientId}&fact=${data.n_facture}`;
        else { msgEl.textContent = '❌ ' + data.error; msgEl.style.color = '#e74c3c'; }
    }).catch(() => { msgEl.textContent = '❌ Erreur réseau'; msgEl.style.color = '#e74c3c'; });
}

// DOMContentLoaded : pas de chargement auto des créneaux (ils se chargent à l'ouverture de la popup)
document.addEventListener('DOMContentLoaded', ()=>{
    // La popup charge les créneaux quand elle s'ouvre via ouvrirPopupRdv()

    // Pré-remplir l'heure de consultation avec l'heure actuelle si pas encore enregistrée
    const heureBase = '<?= $heureVisite ?>';
    if (!heureBase) {
        const now = new Date();
        const hh  = String(now.getHours()).padStart(2,'0');
        const mm  = String(now.getMinutes()).padStart(2,'0');
        const heureNow = hh + ':' + mm;
        ['heure_consultation_acc','heure_consultation_cons'].forEach(id => {
            const el = document.getElementById(id);
            if (el && !el.value) el.value = heureNow;
        });
    }
});

// Enregistrer HeureVisite en base
function enregistrerHeureVisite() {
    // Lire depuis le champ visible (accueil ou consultation)
    const elAcc  = document.getElementById('heure_consultation_acc');
    const elCons = document.getElementById('heure_consultation_cons');
    const heure  = (elAcc && elAcc.value) ? elAcc.value : (elCons ? elCons.value : '');
    if (!heure) { alert('Veuillez saisir une heure.'); return; }

    // Synchroniser les deux champs
    if (elAcc)  elAcc.value  = heure;
    if (elCons) elCons.value = heure;

    fetch('ajax_heure_visite.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ n_ordon: <?= (int)$nOrd ?>, heure: heure })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Feedback visuel discret : le bouton vire brièvement au vert foncé
            document.querySelectorAll('button[onclick="enregistrerHeureVisite()"]').forEach(btn => {
                btn.style.background = '#1a7a3a';
                setTimeout(() => btn.style.background = '#27ae60', 1200);
            });
        } else {
            alert('❌ Erreur : ' + (data.error || 'inconnue'));
        }
    })
    .catch(() => alert('❌ Erreur réseau'));
}
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

// ════════════════════════════════════════════════════════════
// BASCULE VUE ACCUEIL / CONSULTATION
// ════════════════════════════════════════════════════════════
function setVue(vue) {
    document.cookie = 'vue_dossier=' + vue + ';path=/;max-age=31536000';
    const isAccueil = (vue === 'accueil');
    // Classe sur body — CSS fait le reste
    document.body.classList.toggle('vue-accueil', isAccueil);
    document.getElementById('section-accueil').style.display      = isAccueil ? 'block' : 'none';
    document.getElementById('section-consultation').style.display = isAccueil ? 'none'  : 'block';
    var btnAccueil      = document.getElementById('btn-vue-accueil');
    var btnConsultation = document.getElementById('btn-vue-consultation');
    if (btnAccueil)      btnAccueil.className      = 'btn-vue ' + (isAccueil ? 'actif' : 'inactif');
    if (btnConsultation) btnConsultation.className = 'btn-vue ' + (isAccueil ? 'inactif' : 'actif');
}
document.addEventListener('DOMContentLoaded', function() {
    var m = document.cookie.match(/vue_dossier=([^;]+)/);
    setVue(m ? m[1] : 'accueil');
});

// ════════════════════════════════════════════════════════════
// POPUP RDV PROCHAIN (vue accueil)
// ════════════════════════════════════════════════════════════
function ouvrirPopupRdv() {
    document.getElementById('popup-rdv-acc').classList.add('ouvert');
    // Charger les créneaux si date déjà connue
    const dateActuelle = document.getElementById('rdv_futur')?.value;
    if (dateActuelle && /^\d{4}-\d{2}-\d{2}$/.test(dateActuelle) && dateActuelle !== '1970-01-01') {
        rdvChargerCreneaux(dateActuelle, 'rdv', false);
    }
}
function fermerPopupRdv() {
    document.getElementById('popup-rdv-acc').classList.remove('ouvert');
}

// Mettre à jour la colonne RDV prochain dans le tableau accueil après confirmation
function mettreAJourAccueilRdv(date, heure, acte) {
    // Date
    const cellDate = document.getElementById('acc-rdvp-date');
    if (cellDate) {
        const dateFr = date ? date.split('-').reverse().join('/') : '—';
        cellDate.innerHTML = `<strong style="color:var(--th-col-rdvn);">${dateFr}</strong>${heure ? '<br><span style="color:var(--th-col-rdvn);font-size:11px;">'+heure+'</span>' : ''}`;
    }
    // Délai (calculé depuis aujourd'hui)
    if (date) {
        const now = new Date();
        const rdv = new Date(date);
        const diffMs = rdv - now;
        const diffJ  = Math.round(diffMs / 86400000);
        const mois   = Math.floor(diffJ / 30);
        const jours  = diffJ % 30;
        const delaiTxt = mois > 0 ? mois + 'M' + (jours > 0 ? ' ' + jours + 'j' : '') : diffJ + 'j';
        const el = document.getElementById('acc-rdvp-delai-txt');
        if (el) el.textContent = delaiTxt;
    }
    // Actes
    ['ecg','edc','dtsa'].forEach(a => {
        const el = document.getElementById('acc-rdvp-' + a);
        if (!el) return;
        const trouve = acte && acte.toUpperCase().indexOf(a.toUpperCase()) !== -1;
        el.innerHTML = trouve
            ? `<span style="color:var(--th-col-rdvn);font-weight:bold;">${a.toUpperCase()}</span>`
            : `<span style="color:#ccc;">—</span>`;
    });
}

// Surcharge confirmerRdv pour mettre à jour la vue accueil aussi
const _confirmerRdvOrig = confirmerRdv;
function confirmerRdv(nOrdon) {
    const dateRdv  = document.getElementById('rdv_futur')?.value;
    const heureRdv = document.getElementById('heure_rdv_futur')?.value || '';
    const acte     = document.getElementById('acte_rdv_futur')?.value || '';
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
                // Mettre à jour vue accueil instantanément
                mettreAJourAccueilRdv(dateFin, heureRdv, acte);
                fermerPopupRdv();
                alert('✅ RDV enregistré : ' + dateFr + (heureRdv ? ' à ' + heureRdv : ''));
            } else alert('❌ Erreur : ' + data.error);
        });
    });
}
// ════════════════════════════════════════════════════════════
// NAVIGATION BIOLOGIE (dossier)
// ════════════════════════════════════════════════════════════
const bioBilans = <?= json_encode(array_map(fn($b) => [
    'n_bilan'    => $b['n_bilan'],
    'date_fr'    => $b['date_fr'],
    'nb_anormal' => (int)$b['nb_anormal'],
    'nb_total'   => (int)$b['nb_total'],
], $bilansListe)) ?>;
let bioIdx = 0; // 0 = bilan le plus récent
 
function bioNav(dir) {
    if (!bioBilans.length) return;
    if      (dir === 'first') bioIdx = 0;
    else if (dir === 'last')  bioIdx = bioBilans.length - 1;
    else if (dir === 'prev')  bioIdx = Math.max(0, bioIdx - 1);
    else if (dir === 'next')  bioIdx = Math.min(bioBilans.length - 1, bioIdx + 1);
    bioCharger(bioBilans[bioIdx].n_bilan, bioIdx);
}
 
async function bioCharger(n_bilan, idx) {
    const res = await fetch('ajax_bio_dossier.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'get_detail', n_bilan})
    }).then(r => r.json());
 
    if (!res.ok) return;
 
    // Mettre à jour compteur position
    document.getElementById('bio-nav-pos').textContent = (idx + 1) + ' / ' + bioBilans.length;
 
    // Mettre à jour la date affichée
    document.getElementById('bio-date-affich').textContent = res.bilan?.date_fr || '—';
 
    // Compter et afficher le nombre de résultats anormaux
    const anormaux = res.lignes.filter(l => l.resultat !== '' && l.resultat.toUpperCase() !== 'N');
    const elNb = document.getElementById('bio-nb-anormal');
    if (anormaux.length > 0) {
        elNb.textContent = anormaux.length + ' ⚠️';
        elNb.style.display = '';
    } else {
        elNb.style.display = 'none';
    }
 
    // Afficher toutes les lignes (anormaux en rouge, normaux en gris)
    const zone = document.getElementById('bio-resultats');
    if (!res.lignes.length) {
        zone.innerHTML = '<span style="color:var(--th-color-text-muted);font-size:11px;">Bilan vide</span>';
    } else {
        zone.innerHTML = res.lignes.map(l => {
            const v  = l.resultat || '';
            const an = (v !== '' && v.toUpperCase() !== 'N');
            const col = an ? '#e74c3c' : '#aaa';
            const fw  = an ? 'bold'   : 'normal';
            const fs  = an ? '11px'   : '10px';
            return `<div style="display:flex;justify-content:space-between;align-items:center;padding:1px 0;border-bottom:1px solid #f5f5f5;">
                <span style="color:${col};font-weight:${fw};font-size:${fs};">${l.nom}</span>
                <span style="color:${col};font-weight:${fw};font-size:${fs};margin-left:6px;white-space:nowrap;">${v || '—'}</span>
            </div>`;
        }).join('');
    }
 
    // Mettre à jour l'aperçu rapport (anormaux seulement)
    const apercuTexte = document.getElementById('apercu-bio-texte');
    if (apercuTexte) {
        const lignesAn = res.lignes.filter(l => l.resultat && l.resultat.toUpperCase() !== 'N');
        apercuTexte.innerHTML = lignesAn.length
            ? lignesAn.map(l => `${l.nom} : <strong style="color:#e74c3c;">${l.resultat}</strong>`).join('<br>')
            : '<span style="color:var(--th-color-text-muted);font-weight:600;">Aucun résultat anormal</span>';
    }
 
    // Afficher bouton aperçu 👁 si le bilan a des lignes
    document.getElementById('bio-btn-apercu').style.display = res.lignes.length ? '' : 'none';
}
 
// Initialiser l'affichage au chargement de la page
document.addEventListener('DOMContentLoaded', function() {
    if (!bioBilans.length) return;
    const elNb = document.getElementById('bio-nb-anormal');
    const nbAn = bioBilans[0]?.nb_anormal || 0;
    if (nbAn > 0) {
        elNb.textContent = nbAn + ' ⚠️';
        elNb.style.display = '';
    }
    document.getElementById('bio-date-affich').textContent = bioBilans[0]?.date_fr || '—';
    if (bioBilans[0]?.nb_total > 0) {
        document.getElementById('bio-btn-apercu').style.display = '';
    }
});
 
// Certificat vue accueil
function calcNbrJAcc() {
    const d1=document.getElementById('cert_debut_acc').value, d2=document.getElementById('cert_fin_acc').value;
    verifierDebutCertificat(d1);
    if (d1&&d2) { const diff=Math.round((new Date(d2)-new Date(d1))/86400000); document.getElementById('cert_nbrj_acc').value=diff>=0?diff:0; verifierDatesCertificat(d1,d2,diff); }
}
</script>

<!-- POPUP RDV PROCHAIN (vue accueil) -->
<div class="popup-rdv-ov" id="popup-rdv-acc">
    <div class="popup-rdv-box">
        <div class="popup-rdv-header">
            <strong style="font-size:14px;">📆 RDV prochain</strong>
            <button onclick="fermerPopupRdv()" style="background:rgba(255,255,255,0.2);color:white;border:none;border-radius:4px;padding:3px 10px;cursor:pointer;font-size:13px;">✕</button>
        </div>
        <div class="popup-rdv-body">
            <!-- Boutons délai -->
            <div style="display:flex;gap:4px;flex-wrap:wrap;margin-bottom:8px;">
                <button type="button" onclick="rdvSetDelai(1,0,'rdv')"  class="delai-btn-rdv">1M</button>
                <button type="button" onclick="rdvSetDelai(3,0,'rdv')"  class="delai-btn-rdv actif">3M</button>
                <button type="button" onclick="rdvSetDelai(6,0,'rdv')"  class="delai-btn-rdv">6M</button>
                <button type="button" onclick="rdvSetDelai(0,7,'rdv')"  class="delai-btn-rdv">7J</button>
                <button type="button" onclick="rdvSetDelai(0,10,'rdv')" class="delai-btn-rdv">10J</button>
                <button type="button" onclick="rdvSetDelai(0,15,'rdv')" class="delai-btn-rdv">15J</button>
                <span style="width:1px;height:14px;background:#ccc;display:inline-block;margin:0 2px;"></span>
                <button type="button" onclick="reportTraitement(3,<?= $id ?>)" style="background:#e67e22;color:white;border:none;padding:2px 6px;border-radius:3px;cursor:pointer;font-size:10px;font-weight:bold;">↺3M</button>
                <button type="button" onclick="reportTraitement(6,<?= $id ?>)" style="background:#c0392b;color:white;border:none;padding:2px 6px;border-radius:3px;cursor:pointer;font-size:10px;font-weight:bold;">↺6M</button>
                <button type="button" onclick="confirmerRdv(<?= $ordCourante['n_ordon'] ?? 0 ?>)"
                        style="background:#27ae60;color:white;border:none;padding:3px 8px;border-radius:3px;cursor:pointer;font-size:11px;font-weight:bold;margin-left:auto;">
                    📅 RDV
                </button>
            </div>
            <!-- Date + heure -->
            <div style="display:flex;gap:6px;margin-bottom:8px;align-items:center;">
                <input type="date" id="rdv_futur_visible" value="<?= $rdvFuturVal ?>"
                       onchange="rdvDateChange(this.value,'rdv')"
                       ondblclick="if(this.value) window.location.href='agenda.php?date='+this.value"
                       title="Double-clic → ouvrir l'agenda ce jour"
                       style="flex:1;padding:5px 8px;border:1px solid var(--th-col-rdvn);border-radius:4px;font-size:12px;cursor:pointer;">
                <div id="rdv_heure_affichage" style="background:var(--th-col-rdvn-bg-hover);color:var(--th-col-rdvn);padding:5px 10px;border-radius:4px;font-size:13px;font-weight:bold;white-space:nowrap;">
                    <?= !empty($ordCourante['HeureRDV']) ? htmlspecialchars($ordCourante['HeureRDV']) : '—:——' ?>
                </div>
            </div>
            <!-- Jauge -->
            <div class="jauge-jour" id="rdv_jauge" style="display:none;">
                <span id="rdv_jauge_txt" style="white-space:nowrap;color:var(--th-color-text-muted);font-size:10px;"></span>
                <div class="jauge-bar"><div class="jauge-fill ok" id="rdv_jauge_fill" style="width:0%"></div></div>
            </div>
            <!-- Grille créneaux -->
            <div class="creneaux-wrap">
                <div class="creneaux-loading" id="rdv_loading" style="display:none;">⏳ Chargement…</div>
                <div class="creneaux-msg"     id="rdv_msg"     style="display:none;"></div>
                <div class="creneaux-grille"  id="rdv_grille"></div>
            </div>
            <!-- Acte -->
            <div style="margin-top:8px;">
                <input type="text" id="acte_rdv_futur" value="<?= htmlspecialchars($ordCourante['acte1'] ?? '') ?>"
                       oninput="syncActe(this.value,'rdv')"
                       placeholder="Acte…"
                       style="width:100%;padding:4px 8px;border:1px solid var(--th-col-rdvn);border-radius:4px;font-size:12px;text-align:center;margin-bottom:6px;">
                <div style="display:flex;gap:3px;flex-wrap:wrap;">
                    <?php foreach (['ECG','ECG+EDC','ECG+EDC+DTSA','DTSA','EDC','DVMI','BILAN','CONTROL','DAMI'] as $ba): ?>
                    <button type="button" onclick="setActeRdv('<?= $ba ?>','rdv');"
                        style="background:var(--th-col-rdvn);color:white;border:none;padding:3px 8px;border-radius:3px;cursor:pointer;font-size:11px;"><?= $ba ?></button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- ══════════════════════════════════════════════════════════════════
     MODALE — Sélection dates rapport cardio-vasculaire
══════════════════════════════════════════════════════════════════ -->
<!-- ══ MODALE MENU RAPPORTS ══════════════════════════════════════════════ -->
<div id="modal-rapports-menu" style="display:none;position:fixed;top:42px;right:18px;
     z-index:99999;">
    <div style="background:var(--th-bg-card);border-radius:10px;width:320px;
                box-shadow:0 8px 32px rgba(0,0,0,0.3);overflow:hidden;">
        <div style="background:#c0392b;color:white;padding:8px 14px;
                    display:flex;align-items:center;justify-content:space-between;">
            <span style="font-weight:bold;font-size:13px;">📑 Rapports</span>
        </div>
        <div style="padding:10px 14px;display:flex;flex-direction:column;gap:6px;">
            <a href="print_cmlm.php?id=<?= $id ?>" target="_blank"
               style="display:block;background:#8e44ad;color:white;text-decoration:none;
                      border-radius:5px;padding:7px 14px;font-size:12px;font-weight:bold;">
                📋 Attestation de maladie de longue durée
            </a>
            <a href="print_aptitude.php?id=<?= $id ?>" target="_blank"
               style="display:block;background:#27ae60;color:white;text-decoration:none;
                      border-radius:5px;padding:7px 14px;font-size:12px;font-weight:bold;">
                🏅 Certificat médical d'aptitude physique
            </a>
            <button onclick="ouvrirModalRapport();"
               style="display:block;width:100%;background:#c0392b;color:white;border:none;
                      border-radius:5px;padding:7px 14px;font-size:12px;font-weight:bold;
                      cursor:pointer;text-align:left;">
                📄 Compte rendu de l'examen cardio-vasculaire
            </button>
            <a href="print_lettre.php?id=<?= $id ?>" target="_blank"
               style="display:block;background:#16a085;color:white;text-decoration:none;
                      border-radius:5px;padding:7px 14px;font-size:12px;font-weight:bold;">
                ✉️ Lettre de correspondance
            </a>
            <a href="print_ordonnance.php?id=<?= $id ?>&ord=<?= $nOrd ?>" target="_blank"
               style="display:block;background:#2e6da4;color:white;text-decoration:none;
                      border-radius:5px;padding:7px 14px;font-size:12px;font-weight:bold;">
                💊 Ordonnance
            </a>
            <button onclick="fermerMenuRapports()"
               style="display:block;width:100%;background:#7f8c8d;color:white;border:none;
                      border-radius:5px;padding:7px 14px;font-size:12px;font-weight:bold;
                      cursor:pointer;text-align:center;margin-top:2px;">
                ✕ Fermer
            </button>
        </div>
    </div>
</div>

<div id="modal-rapport" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;
     background:rgba(0,0,0,0.55);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:var(--th-bg-card);border-radius:10px;width:380px;max-width:96%;
                box-shadow:0 8px 32px rgba(0,0,0,0.3);overflow:hidden;">
        <!-- Header -->
        <div style="background:#c0392b;color:white;padding:10px 16px;
                    display:flex;align-items:center;justify-content:space-between;">
            <span style="font-weight:bold;font-size:13px;">🖨️ Rapport cardio-vasculaire</span>
            <button onclick="fermerModalRapport()" style="background:none;border:none;color:white;
                    font-size:18px;cursor:pointer;line-height:1;">✕</button>
        </div>
        <!-- Corps -->
        <div style="padding:16px;">
            <div id="rapport-loading" style="text-align:center;color:var(--th-color-text-muted);font-size:12px;padding:20px 0;">
                Chargement des dates…
            </div>
            <div id="rapport-contenu" style="display:none;">
                <!-- Ligne Examen -->
                <div id="ligne-examen" style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
                    <span style="width:130px;font-size:12px;font-weight:bold;color:var(--th-color-primary);">🩺 Examen clinique :</span>
                    <select id="sel-examen" style="flex:1;font-size:12px;padding:3px 6px;border:1px solid #ccc;border-radius:4px;"></select>
                    <button onclick="toggleExclusionRubrique('examen')" id="btn-excl-examen"
                        title="Exclure cette rubrique du rapport"
                        style="width:28px;height:28px;border:1px solid #e74c3c;border-radius:4px;
                               background:#fff;color:#e74c3c;font-size:14px;cursor:pointer;flex-shrink:0;">✕</button>
                </div>
                <!-- Ligne ECG -->
                <div id="ligne-ecg" style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
                    <span style="width:130px;font-size:12px;font-weight:bold;color:var(--th-color-primary);">📈 ECG :</span>
                    <select id="sel-ecg" style="flex:1;font-size:12px;padding:3px 6px;border:1px solid #ccc;border-radius:4px;"></select>
                    <button onclick="toggleExclusionRubrique('ecg')" id="btn-excl-ecg"
                        title="Exclure cette rubrique du rapport"
                        style="width:28px;height:28px;border:1px solid #e74c3c;border-radius:4px;
                               background:#fff;color:#e74c3c;font-size:14px;cursor:pointer;flex-shrink:0;">✕</button>
                </div>
                <!-- Ligne Echo -->
                <div id="ligne-echo" style="display:flex;align-items:center;gap:8px;margin-bottom:16px;">
                    <span style="width:130px;font-size:12px;font-weight:bold;color:var(--th-color-primary);">🫀 Echo-Doppler :</span>
                    <select id="sel-echo" style="flex:1;font-size:12px;padding:3px 6px;border:1px solid #ccc;border-radius:4px;"></select>
                    <button onclick="toggleExclusionRubrique('echo')" id="btn-excl-echo"
                        title="Exclure cette rubrique du rapport"
                        style="width:28px;height:28px;border:1px solid #e74c3c;border-radius:4px;
                               background:#fff;color:#e74c3c;font-size:14px;cursor:pointer;flex-shrink:0;">✕</button>
                </div>
                <!-- Boutons action -->
                <div style="display:flex;justify-content:flex-end;gap:8px;">
                    <button onclick="fermerModalRapport()"
                        style="background:#95a5a6;color:white;border:none;border-radius:5px;
                               padding:6px 16px;font-size:12px;cursor:pointer;">Annuler</button>
                    <button onclick="imprimerRapport()"
                        style="background:#c0392b;color:white;border:none;border-radius:5px;
                               padding:6px 16px;font-size:12px;font-weight:bold;cursor:pointer;">🖨️ Imprimer</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
/* ── Exclusions rubriques rapport ── */
var rapportExclusions = { examen: false, ecg: false, echo: false };

function ouvrirMenuRapports() {
    document.getElementById('modal-rapports-menu').style.display = 'flex';
}
function fermerMenuRapports() {
    document.getElementById('modal-rapports-menu').style.display = 'none';
}

function ouvrirModalRapport() {
    rapportExclusions = { examen: false, ecg: false, echo: false };
    // Réinitialiser l'affichage des boutons exclusion
    ['examen','ecg','echo'].forEach(function(r) {
        var btn = document.getElementById('btn-excl-' + r);
        var ligne = document.getElementById('ligne-' + r);
        if (btn) { btn.textContent = '✕'; btn.style.background = '#fff'; btn.style.color = '#e74c3c'; }
        if (ligne) ligne.style.opacity = '1';
    });

    document.getElementById('modal-rapport').style.display = 'flex';
    document.getElementById('rapport-loading').style.display = 'block';
    document.getElementById('rapport-contenu').style.display = 'none';

    fetch('ajax_dates_rapport.php?id=<?= $id ?>')
        .then(function(r){ return r.json(); })
        .then(function(d) {
            remplirSelectRapport('sel-examen', d.examen || []);
            remplirSelectRapport('sel-ecg',    d.ecg    || []);
            remplirSelectRapport('sel-echo',   d.echo   || []);
            document.getElementById('rapport-loading').style.display = 'none';
            document.getElementById('rapport-contenu').style.display = 'block';
        })
        .catch(function(e) {
            document.getElementById('rapport-loading').textContent = 'Erreur de chargement.';
        });
}

function remplirSelectRapport(selectId, dates) {
    var sel = document.getElementById(selectId);
    sel.innerHTML = '';
    if (!dates || dates.length === 0) {
        var opt = document.createElement('option');
        opt.value = '';
        opt.textContent = '— aucun —';
        sel.appendChild(opt);
        sel.disabled = true;
        // Exclure automatiquement si aucune donnée
        var rubrique = selectId.replace('sel-','');
        rapportExclusions[rubrique] = true;
        var btn = document.getElementById('btn-excl-' + rubrique);
        var ligne = document.getElementById('ligne-' + rubrique);
        if (btn) { btn.textContent = '↩'; btn.style.background = '#e74c3c'; btn.style.color = '#fff'; }
        if (ligne) ligne.style.opacity = '0.4';
    } else {
        sel.disabled = false;
        dates.forEach(function(d, i) {
            var opt = document.createElement('option');
            opt.value = d.date_tri;   // format YYYYMMDD pour la requête SQL
            opt.textContent = d.date_fr; // format JJ/MM/AAAA pour l'affichage
            if (i === 0) opt.selected = true; // dernière date par défaut
            sel.appendChild(opt);
        });
    }
}

function toggleExclusionRubrique(rubrique) {
    rapportExclusions[rubrique] = !rapportExclusions[rubrique];
    var btn   = document.getElementById('btn-excl-' + rubrique);
    var ligne = document.getElementById('ligne-' + rubrique);
    var sel   = document.getElementById('sel-' + rubrique);
    if (rapportExclusions[rubrique]) {
        // Exclure : griser, barrer, bouton ↩
        if (btn)   { btn.textContent = '↩'; btn.style.background = '#e74c3c'; btn.style.color = '#fff'; }
        if (ligne) ligne.style.opacity = '0.4';
        if (sel)   sel.disabled = true;
    } else {
        // Réintégrer
        if (btn)   { btn.textContent = '✕'; btn.style.background = '#fff'; btn.style.color = '#e74c3c'; }
        if (ligne) ligne.style.opacity = '1';
        if (sel)   sel.disabled = false;
    }
}

function fermerModalRapport() {
    document.getElementById('modal-rapport').style.display = 'none';
}

function imprimerRapport() {
    var url = 'print_rapport.php?id=<?= $id ?>';
    if (!rapportExclusions.examen) {
        var v = document.getElementById('sel-examen').value;
        if (v) url += '&date_ex=' + encodeURIComponent(v);
    } else {
        url += '&excl_examen=1';
    }
    if (!rapportExclusions.ecg) {
        var v = document.getElementById('sel-ecg').value;
        if (v) url += '&date_ecg=' + encodeURIComponent(v);
    } else {
        url += '&excl_ecg=1';
    }
    if (!rapportExclusions.echo) {
        var v = document.getElementById('sel-echo').value;
        if (v) url += '&date_echo=' + encodeURIComponent(v);
    } else {
        url += '&excl_echo=1';
    }
    window.open(url, '_blank');
    fermerModalRapport();
}

// Fermer si clic sur le fond
document.getElementById('modal-rapport').addEventListener('click', function(e) {
    if (e.target === this) fermerModalRapport();
});

/* ══ Panneaux Motif / Antécédents / Diagnostic ══ */
function togglePanel(id) {
    var p = document.getElementById(id);
    if (p) p.style.display = (p.style.display === 'none' || p.style.display === '') ? 'block' : 'none';
}
function toggleAtcd(cb) {
    var t = document.getElementById(cb.dataset.target);
    if (t) t.style.display = cb.checked ? 'block' : 'none';
}

function validerMotif() {
    var items = [];
    document.querySelectorAll('.motif-cb:checked').forEach(function(cb) {
        if (cb.value) items.push(cb.value);
    });
    var ta = document.getElementById('champ_motif');
    if (ta && items.length > 0) {
        ta.value = items.join(' — ');
        sauvegarderChamp('MOTIF CONSULTATION', ta.value);
    }
    document.getElementById('panel_motif').style.display = 'none';
}

function validerAtcd() {
    var items = [];
    document.querySelectorAll('.atcd-cb:checked').forEach(function(cb) {
        if (cb.value) items.push(cb.value);
    });
    // Néoplasie avec précision
    var neo = document.getElementById('atcd_neo_cb');
    var neoD = document.getElementById('atcd_neo_detail');
    if (neo && neo.checked && neoD && neoD.value.trim()) {
        // Remplacer l'item générique par l'item avec précision
        items = items.filter(function(i){ return i !== 'Néoplasie'; });
        items.push('Néoplasie (' + neoD.value.trim() + ')');
    }
    // Autre chirurgie avec précision
    var autreChir = document.getElementById('atcd_autrechir_cb');
    var autreChirD = document.getElementById('atcd_autrechir_detail');
    if (autreChir && autreChir.checked && autreChirD && autreChirD.value.trim()) {
        items = items.filter(function(i){ return i !== 'ATCD chir. : Autre chirurgie'; });
        items.push('ATCD chir. : Autre chirurgie (' + autreChirD.value.trim() + ')');
    }
    var ta = document.getElementById('champ_atcd');
    if (ta && items.length > 0) {
        ta.value = items.join('\n');
        sauvegarderChamp('ATCD', ta.value);
    }
    document.getElementById('panel_atcd').style.display = 'none';
}

function validerDiag() {
    var items = [];
    document.querySelectorAll('.diag-cb:checked').forEach(function(cb) {
        if (cb.value) items.push(cb.value);
    });
    // Bradycardie sinusale + FC
    var fcMoy = document.getElementById('diag_fc_moy');
    var fcMin = document.getElementById('diag_fc_min');
    if (fcMoy && fcMoy.value) items.push('Bradycardie sinusale — FC moy: ' + fcMoy.value + '/min');
    if (fcMin && fcMin.value) items.push('Bradycardie sinusale — FC min: ' + fcMin.value + '/min');

    if (items.length === 0) {
        document.getElementById('panel_diag').style.display = 'none';
        return;
    }
    // Ajouter chaque item comme nouvelle entrée de diagnostic
    var id = <?= $id ?>;
    var liste = <?= json_encode($listeDiag1) ?>;
    items.forEach(function(val) {
        diagAjouter(1, id, liste, val);
    });
    document.getElementById('panel_diag').style.display = 'none';
}

/* ══ Popup MAD (Motif / Antécédents / Diagnostic) ══ */
function ouvrirPopupMAD(ong) {
    document.getElementById('popup-mad').style.display = 'block';
    switchOnglet(ong || 'motif');
}
function fermerPopupMAD() {
    document.getElementById('popup-mad').style.display = 'none';
}
function switchOnglet(ong) {
    ['motif','atcd','diag','fdr'].forEach(function(o) {
        document.getElementById('tab_' + o).style.display = (o === ong) ? 'block' : 'none';
        var btn = document.getElementById('ong_' + o);
        if (btn) {
            btn.style.background = (o === ong) ? '#e8f0fa' : '#f5f5f5';
            btn.style.fontWeight = (o === ong) ? 'bold' : 'normal';
            btn.style.color = (o === ong) ? '#1a4a7a' : '#555';
            btn.style.borderBottom = (o === ong) ? '2px solid #1a4a7a' : '2px solid transparent';
        }
    });
}
function viderChamp(champId, champDB) {
    var ta = document.getElementById(champId);
    if (ta) {
        ta.value = '';
        sauvegarderChamp(champDB, '');
    }
}
function viderDiagnostic() {
    if (!confirm('Supprimer tous les diagnostics ?')) return;
    var lignes = document.querySelectorAll('#diag_1 .diag-ligne');
    lignes.forEach(function(ligne) {
        var pk = ligne.dataset.pk;
        var btn = ligne.querySelector('button');
        if (btn) btn.click();
    });
}
function madToggle(cb) {
    var t = document.getElementById(cb.dataset.target);
    if (t) t.style.display = cb.checked ? 'block' : 'none';
}
function madToutDecocher() {
    document.querySelectorAll('#popup-mad input[type="checkbox"]').forEach(function(cb) {
        cb.checked = false;
    });
    document.querySelectorAll('#popup-mad div[id^="md_"],#popup-mad div[id^="ma_"]').forEach(function(d) {
        d.style.display = 'none';
    });
}
function madValiderTout() {
    var id = <?= $id ?>;
    var liste = <?= json_encode($listeDiag1) ?>;

    // ── Motif ──
    var motifs = [];
    document.querySelectorAll('.mad-motif:checked').forEach(function(cb) {
        if (cb.value) motifs.push('- ' + cb.value);
    });
    if (motifs.length > 0) {
        var ta = document.getElementById('champ_motif');
        if (ta) {
            ta.value = motifs.join('\n');
            sauvegarderChamp('MOTIF CONSULTATION', ta.value);
        }
    }

    // ── Antécédents ──
    var atcds = [];
    document.querySelectorAll('.mad-atcd:checked').forEach(function(cb) {
        if (cb.value) atcds.push('- ' + cb.value);
    });
    var neo = document.getElementById('mad_neo_cb');
    var neoD = document.getElementById('mad_neo_detail');
    if (neo && neo.checked && neoD && neoD.value.trim()) {
        atcds = atcds.filter(function(i){ return i !== '- Néoplasie'; });
        atcds.push('- ATCD : Néoplasie (' + neoD.value.trim() + ')');
    }
    var autreChir = document.getElementById('mad_autrechir_cb');
    var autreChirD = document.getElementById('mad_autrechir_detail');
    if (autreChir && autreChir.checked && autreChirD && autreChirD.value.trim()) {
        atcds = atcds.filter(function(i){ return i !== '- Autre'; });
        atcds.push('- ATCD chir. : Autre (' + autreChirD.value.trim() + ')');
    }
    if (atcds.length > 0) {
        var ta2 = document.getElementById('champ_atcd');
        if (ta2) {
            ta2.value = atcds.join('\n');
            sauvegarderChamp('ATCD', ta2.value);
        }
    }

    // ── Diagnostic → champ_diagnostic ──
    var diags = [];
    document.querySelectorAll('.mad-diag:checked').forEach(function(cb) {
        if (cb.value) diags.push('- ' + cb.value);
    });
    var fcMoy = document.getElementById('md_fc_moy');
    var fcMin = document.getElementById('md_fc_min');
    if (fcMoy && fcMoy.value) diags.push('- Bradycardie sinusale — FC moy: ' + fcMoy.value + '/min');
    if (fcMin && fcMin.value) diags.push('- Bradycardie sinusale — FC min: ' + fcMin.value + '/min');
    if (diags.length > 0) {
        var champDiag = document.getElementById('champ_diagnostic');
        if (champDiag) {
            champDiag.value = diags.join('\n');
            sauvegarderChamp('diagnostic', champDiag.value);
        }
    }

    // ── Facteurs de risque → champ_fdr ──
    var fdrs = [];
    document.querySelectorAll('.mad-fdr:checked').forEach(function(cb) {
        if (cb.value) fdrs.push('- ' + cb.value);
    });
    if (fdrs.length > 0) {
        var champFdr = document.getElementById('champ_fdr');
        if (champFdr) {
            champFdr.value = fdrs.join('\n');
            sauvegarderChamp('CHAMP_FDR', champFdr.value);
        }
    }

    fermerPopupMAD();
}
// Fermer en cliquant sur l'overlay
document.getElementById('popup-mad').addEventListener('click', function(e) {
    if (e.target === this) fermerPopupMAD();
});
</script>


<!-- ══ POPUP Motif / Antécédents / Diagnostic ══ -->
<div id="popup-mad" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;overflow:hidden;">
    <div style="background:var(--th-bg-card);border-radius:8px;width:820px;max-width:98vw;max-height:92vh;margin:2vh auto;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.3);">

        <!-- Header -->
        <div style="background:#1a4a7a;color:white;padding:10px 14px;display:flex;align-items:center;justify-content:space-between;flex-shrink:0;">
            <span style="font-weight:bold;font-size:13px;">📋 Motif — Antécédents — Facteurs de risque</span>
            <button onclick="fermerPopupMAD()" style="background:none;border:none;color:white;font-size:16px;cursor:pointer;">✕</button>
        </div>

        <!-- Onglets -->
        <div style="display:flex;border-bottom:2px solid #e0e0e0;flex-shrink:0;">
            <button id="ong_motif" onclick="switchOnglet('motif')" style="flex:1;padding:7px 4px;border:none;background:#e8f0fa;font-size:11px;font-weight:bold;cursor:pointer;color:var(--th-color-primary);border-bottom:2px solid #1a4a7a;">Motif</button>
            <button id="ong_atcd" onclick="switchOnglet('atcd')" style="flex:1;padding:7px 4px;border:none;background:#f5f5f5;font-size:11px;cursor:pointer;color:var(--th-color-text-muted);">Antécédents</button>
            <button id="ong_diag" onclick="switchOnglet('diag')" style="flex:1;padding:7px 4px;border:none;background:#f5f5f5;font-size:11px;cursor:pointer;color:var(--th-color-text-muted);">Diagnostic</button>
            <button id="ong_fdr" onclick="switchOnglet('fdr')" style="flex:1;padding:7px 4px;border:none;background:#f5f5f5;font-size:11px;cursor:pointer;color:var(--th-color-text-muted);">Fact. risque</button>
        </div>

        <!-- Contenu scrollable -->
        <div style="overflow-y:auto;flex:1;padding:8px 12px;">

            <!-- ── Onglet Motif ── -->
            <div id="tab_motif">
                <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-motif" value="Évaluation d'un risque cardiovasculaire (bilan, facteurs de risque)"> Évaluation d'un risque cardiovasculaire</label>
                <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-motif" value="Bilan pré-chimiothérapie"> Bilan pré-chimiothérapie</label>
                <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-motif" value="Bilan étiologique d'un AVC"> Bilan étiologique d'un AVC</label>
                <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-motif" value="Bilan préopératoire"> Bilan préopératoire</label>
                <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-motif" value="Concours Aptitude"> Concours Aptitude</label>
                <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-motif" value="Cardiopathie ischémique"> Cardiopathie ischémique</label>
                <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-motif" value="Palpitations"> Palpitations</label>
                <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-motif" value="HTA"> HTA</label>
                <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-motif" value="Bilan d'insuffisance cardiaque"> Bilan d'insuffisance cardiaque</label>
                <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-motif" value="Pathologie vasculaire"> Pathologie vasculaire</label>
            </div>

            <!-- ── Onglet Antécédents ── -->
            <div id="tab_atcd" style="display:none;">
                <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-atcd" value="Antécédents absents"> Absents</label>
                <div style="font-size:9px;font-weight:bold;color:var(--th-color-primary);margin:4px 0 1px;">🏥 Antécédents médicaux</div>

                <label style="font-size:10px;cursor:pointer;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-parent" data-target="ma_cardio" onchange="madToggle(this)"> ▶ Cardiovasculaires</label>
                <div id="ma_cardio" style="display:none;margin-left:10px;">
                    <label style="font-size:10px;cursor:pointer;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-parent" data-target="ma_coro" onchange="madToggle(this)"> ▶ Cardiopathies ischémiques</label>
                    <div id="ma_coro" style="display:none;margin-left:10px;">
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-atcd" value="Angine de poitrine"> Angine de poitrine</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-atcd" value="Infarctus du myocarde"> Infarctus du myocarde</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-atcd" value="Syndrome coronarien aigu"> Syndrome coronarien aigu</label>
                    </div>
                    <label style="font-size:10px;cursor:pointer;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-parent" data-target="ma_ic" onchange="madToggle(this)"> ▶ Insuffisance cardiaque</label>
                    <div id="ma_ic" style="display:none;margin-left:10px;">
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-atcd" value="IC — FE réduite"> FE réduite</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-atcd" value="IC — FE préservée"> FE préservée</label>
                    </div>
                    <label style="font-size:10px;cursor:pointer;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-parent" data-target="ma_valv" onchange="madToggle(this)"> ▶ Valvulopathies</label>
                    <div id="ma_valv" style="display:none;margin-left:10px;">
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-atcd" value="Valvulopathie aortique"> Aortique</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-atcd" value="Valvulopathie mitrale"> Mitrale</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-atcd" value="Valvulopathie tricuspide"> Tricuspide</label>
                    </div>
                    <label style="font-size:10px;cursor:pointer;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-parent" data-target="ma_rythme" onchange="madToggle(this)"> ▶ Troubles du rythme</label>
                    <div id="ma_rythme" style="display:none;margin-left:10px;">
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-atcd" value="Fibrillation atriale"> Fibrillation atriale</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-atcd" value="Flutter atrial"> Flutter atrial</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-atcd" value="Tachycardie supraventriculaire"> Tachycardie supraventriculaire</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-atcd" value="BAV"> BAV</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-atcd" value="Bloc de branche"> Bloc de branche</label>
                    </div>
                    <label style="font-size:10px;cursor:pointer;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-parent" data-target="ma_hta" onchange="madToggle(this)"> ▶ HTA</label>
                    <div id="ma_hta" style="display:none;margin-left:10px;">
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-atcd" value="HTA systémique"> Systémique</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-atcd" value="HTAP"> Pulmonaire</label>
                    </div>
                    <label style="font-size:10px;cursor:pointer;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-parent" data-target="ma_vasc" onchange="madToggle(this)"> ▶ Pathologies vasculaires</label>
                    <div id="ma_vasc" style="display:none;margin-left:10px;">
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-atcd" value="Artériopathie des MI"> Artériopathie des MI</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-atcd" value="Anévrisme aortique ou périphérique"> Anévrisme aortique/périphérique</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-atcd" value="TVP"> TVP</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-atcd" value="Insuffisance veineuse chronique"> Insuffisance veineuse chronique</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-atcd" value="Embolie pulmonaire"> Embolie pulmonaire</label>
                    </div>
                    <label style="font-size:10px;cursor:pointer;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-parent" data-target="ma_cong" onchange="madToggle(this)"> ▶ Maladies congénitales</label>
                    <div id="ma_cong" style="display:none;margin-left:10px;">
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-atcd" value="CIA"> CIA</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-atcd" value="CIV"> CIV</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-atcd" value="Canal artériel persistant"> Canal artériel persistant</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-atcd" value="Tétralogie de Fallot"> Tétralogie de Fallot</label>
                    </div>
                    <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-atcd" value="Endocardite infectieuse"> Endocardite infectieuse</label>
                </div>

                <label style="font-size:10px;cursor:pointer;display:block;margin-bottom:1px;margin-top:3px;"><input type="checkbox" class="mad-parent" data-target="ma_meta" onchange="madToggle(this)"> ▶ Métaboliques et autres</label>
                <div id="ma_meta" style="display:none;margin-left:10px;">
                    <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-atcd" value="Diabète"> Diabète</label>
                    <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-atcd" value="Dyslipidémie"> Dyslipidémie</label>
                    <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-atcd" value="Insuffisance rénale chronique"> Insuffisance rénale chronique</label>
                    <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-atcd" value="BPCO / insuffisance respiratoire"> BPCO / insuffisance respiratoire</label>
                    <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-atcd" value="Maladie thyroïdienne"> Maladie thyroïdienne</label>
                    <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-atcd" value="AVC / AIT"> AVC / AIT</label>
                    <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-atcd" value="Démence / troubles cognitifs"> Démence / troubles cognitifs</label>
                    <label style="font-size:10px;display:flex;align-items:center;gap:3px;margin-bottom:1px;">
                        <input type="checkbox" class="mad-atcd" id="mad_neo_cb" value="Néoplasie"> Néoplasie —
                        <input type="text" id="mad_neo_detail" placeholder="préciser..." style="flex:1;border:1px solid #ccc;border-radius:2px;padding:1px 3px;font-size:10px;">
                    </label>
                </div>

                <div style="font-size:9px;font-weight:bold;color:var(--th-color-primary);margin:4px 0 1px;">🔪 Antécédents chirurgicaux</div>
                <label style="font-size:10px;cursor:pointer;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-parent" data-target="ma_chir_card" onchange="madToggle(this)"> ▶ Chirurgie cardiaque</label>
                <div id="ma_chir_card" style="display:none;margin-left:10px;">
                    <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-atcd" value="ATCD chir. : Pontage coronarien (CABG)"> Pontage coronarien (CABG)</label>
                    <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-atcd" value="ATCD chir. : Angioplastie avec stent"> Angioplastie avec stent</label>
                    <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-atcd" value="ATCD chir. : RVA / RVM / RV tricuspide"> RVA / RVM / RV tricuspide</label>
                    <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-atcd" value="ATCD chir. : Réparation valvulaire"> Réparation valvulaire</label>
                    <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-atcd" value="ATCD chir. : Chirurgie de l'aorte"> Chirurgie de l'aorte</label>
                    <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-atcd" value="ATCD chir. : Correction congénitale (CIA, CIV, Fallot)"> Correction congénitale</label>
                </div>
                <label style="font-size:10px;cursor:pointer;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-parent" data-target="ma_chir_vasc" onchange="madToggle(this)"> ▶ Chirurgie vasculaire</label>
                <div id="ma_chir_vasc" style="display:none;margin-left:10px;">
                    <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-atcd" value="ATCD chir. : Endartériectomie carotidienne"> Endartériectomie carotidienne</label>
                    <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-atcd" value="ATCD chir. : Pontage périphérique"> Pontage périphérique</label>
                    <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-atcd" value="ATCD chir. : Réparation d'anévrisme périphérique"> Réparation d'anévrisme</label>
                </div>
                <label style="font-size:10px;display:flex;align-items:center;gap:3px;margin-top:2px;">
                    <input type="checkbox" class="mad-atcd" id="mad_autrechir_cb" value="ATCD chir. : Autre"> Autre chirurgie —
                    <input type="text" id="mad_autrechir_detail" placeholder="préciser..." style="flex:1;border:1px solid #ccc;border-radius:2px;padding:1px 3px;font-size:10px;">
                </label>
            </div>


            <!-- ── Onglet Diagnostic ── -->
            <div id="tab_diag" style="display:none;">

                <label style="font-size:10px;cursor:pointer;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-parent" data-target="md_coro" onchange="madToggle(this)"> ▶ Maladies coronaires</label>
                <div id="md_coro" style="display:none;margin-left:10px;">
                    <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="Angine de poitrine stable"> Angine de poitrine stable</label>
                    <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="Angine de poitrine instable (suspectée)"> Angine de poitrine instable</label>
                    <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="Syndrome coronarien aigu (suspecté)"> Syndrome coronarien aigu</label>
                    <label style="font-size:10px;cursor:pointer;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-parent" data-target="md_idm" onchange="madToggle(this)"> ▶ IDM ancien</label>
                    <div id="md_idm" style="display:none;margin-left:10px;">
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="IDM ancien antérieur"> Antérieur</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="IDM ancien inférieur"> Inférieur</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="IDM ancien latéral"> Latéral</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="IDM ancien — sans séquelles"> Sans séquelles</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="IDM ancien — avec séquelles antérieures"> Séquelles antérieures</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="IDM ancien — avec séquelles inférieures"> Séquelles inférieures</label>
                    </div>
                </div>

                <label style="font-size:10px;cursor:pointer;display:block;margin-bottom:1px;margin-top:2px;"><input type="checkbox" class="mad-parent" data-target="md_ic" onchange="madToggle(this)"> ▶ Insuffisance cardiaque</label>
                <div id="md_ic" style="display:none;margin-left:10px;">
                    <label style="font-size:10px;cursor:pointer;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-parent" data-target="md_icfer" onchange="madToggle(this)"> ▶ IC-FEr</label>
                    <div id="md_icfer" style="display:none;margin-left:10px;">
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="IC-FEr — FE < 40%"> FE &lt; 40%</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="IC-FEr — FE 40-49%"> FE 40–49%</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="IC-FEr — NYHA I"> NYHA I</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="IC-FEr — NYHA II"> NYHA II</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="IC-FEr — NYHA III"> NYHA III</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="IC-FEr — NYHA IV"> NYHA IV</label>
                    </div>
                    <label style="font-size:10px;cursor:pointer;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-parent" data-target="md_icfep" onchange="madToggle(this)"> ▶ IC-FEp</label>
                    <div id="md_icfep" style="display:none;margin-left:10px;">
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="IC-FEp — FE ≥ 50%"> FE ≥ 50%</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="IC-FEp — NYHA I"> NYHA I</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="IC-FEp — NYHA II"> NYHA II</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="IC-FEp — NYHA III"> NYHA III</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="IC-FEp — NYHA IV"> NYHA IV</label>
                    </div>
                    <label style="font-size:10px;cursor:pointer;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-parent" data-target="md_icd" onchange="madToggle(this)"> ▶ IC droite / gauche</label>
                    <div id="md_icd" style="display:none;margin-left:10px;">
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="IC droite légère"> IC droite légère</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="IC droite modérée"> IC droite modérée</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="IC droite sévère"> IC droite sévère</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="IC gauche légère"> IC gauche légère</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="IC gauche modérée"> IC gauche modérée</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="IC gauche sévère"> IC gauche sévère</label>
                    </div>
                    <label style="font-size:10px;cursor:pointer;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-parent" data-target="md_acc" onchange="madToggle(this)"> ▶ Stades ACC/AHA</label>
                    <div id="md_acc" style="display:none;margin-left:10px;">
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="IC stade A (à risque)"> Stade A : à risque</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="IC stade B (préclinique)"> Stade B : préclinique</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="IC stade C (symptomatique)"> Stade C : symptomatique</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="IC stade D (avancée)"> Stade D : avancée</label>
                    </div>
                </div>

                <label style="font-size:10px;cursor:pointer;display:block;margin-bottom:1px;margin-top:2px;"><input type="checkbox" class="mad-parent" data-target="md_valv" onchange="madToggle(this)"> ▶ Maladies valvulaires</label>
                <div id="md_valv" style="display:none;margin-left:10px;">
                    <label style="font-size:10px;cursor:pointer;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-parent" data-target="md_sa" onchange="madToggle(this)"> ▶ Sténose aortique</label>
                    <div id="md_sa" style="display:none;margin-left:10px;">
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="Sténose aortique légère (grad. < 20 mmHg)"> Légère</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="Sténose aortique modérée (grad. 20–40 mmHg)"> Modérée</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="Sténose aortique sévère (grad. > 40 mmHg)"> Sévère</label>
                    </div>
                    <label style="font-size:10px;cursor:pointer;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-parent" data-target="md_ia" onchange="madToggle(this)"> ▶ Insuffisance aortique</label>
                    <div id="md_ia" style="display:none;margin-left:10px;">
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="IA grade I"> Grade I</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="IA grade II"> Grade II</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="IA grade III"> Grade III</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="IA grade IV"> Grade IV</label>
                    </div>
                    <label style="font-size:10px;cursor:pointer;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-parent" data-target="md_sm" onchange="madToggle(this)"> ▶ Sténose mitrale</label>
                    <div id="md_sm" style="display:none;margin-left:10px;">
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="Sténose mitrale légère"> Légère</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="Sténose mitrale modérée"> Modérée</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="Sténose mitrale sévère"> Sévère</label>
                    </div>
                    <label style="font-size:10px;cursor:pointer;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-parent" data-target="md_im" onchange="madToggle(this)"> ▶ Insuffisance mitrale</label>
                    <div id="md_im" style="display:none;margin-left:10px;">
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="IM légère"> Légère</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="IM modérée"> Modérée</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="IM sévère"> Sévère</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="IM organique — prolapsus"> Organique — prolapsus</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="IM organique — rhumatismale"> Organique — rhumatismale</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="IM fonctionnelle (secondaire)"> Fonctionnelle</label>
                    </div>
                    <label style="font-size:10px;cursor:pointer;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-parent" data-target="md_it" onchange="madToggle(this)"> ▶ Insuffisance tricuspide</label>
                    <div id="md_it" style="display:none;margin-left:10px;">
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="IT légère"> Légère</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="IT modérée"> Modérée</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="IT sévère"> Sévère</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="IT primitive"> Primitive</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="IT secondaire (post-HTP)"> Secondaire</label>
                    </div>
                </div>

                <label style="font-size:10px;cursor:pointer;display:block;margin-bottom:1px;margin-top:2px;"><input type="checkbox" class="mad-parent" data-target="md_myoc" onchange="madToggle(this)"> ▶ Myocardiopathies</label>
                <div id="md_myoc" style="display:none;margin-left:10px;">
                    <label style="font-size:10px;cursor:pointer;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-parent" data-target="md_mcd" onchange="madToggle(this)"> ▶ Dilatée</label>
                    <div id="md_mcd" style="display:none;margin-left:10px;">
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="MCd — FE < 40%"> FE &lt; 40%</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="MCd — FE 40-49%"> FE 40–49%</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="MCd — génétique"> Génétique</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="MCd — toxique"> Toxique</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="MCd — idiopathique"> Idiopathique</label>
                    </div>
                    <label style="font-size:10px;cursor:pointer;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-parent" data-target="md_mch" onchange="madToggle(this)"> ▶ Hypertrophique</label>
                    <div id="md_mch" style="display:none;margin-left:10px;">
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="MCH asymétrique (septale)"> Asymétrique septale</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="MCH concentrique"> Concentrique</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="MCH apicale"> Apicale</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="MCH avec obstruction"> Avec obstruction</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="MCH sans obstruction"> Sans obstruction</label>
                    </div>
                    <label style="font-size:10px;cursor:pointer;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-parent" data-target="md_mcr" onchange="madToggle(this)"> ▶ Restrictive</label>
                    <div id="md_mcr" style="display:none;margin-left:10px;">
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="MCR infiltrative (amylose)"> Infiltrative</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="MCR non infiltrative"> Non infiltrative</label>
                    </div>
                    <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="Myocardiopathie arythmogène (suspectée)"> Arythmogène</label>
                </div>

                <label style="font-size:10px;cursor:pointer;display:block;margin-bottom:1px;margin-top:2px;"><input type="checkbox" class="mad-parent" data-target="md_rythme" onchange="madToggle(this)"> ▶ Troubles du rythme</label>
                <div id="md_rythme" style="display:none;margin-left:10px;">
                    <label style="font-size:10px;cursor:pointer;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-parent" data-target="md_fa" onchange="madToggle(this)"> ▶ Fibrillation atriale</label>
                    <div id="md_fa" style="display:none;margin-left:10px;">
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="FA paroxystique (< 7 j)"> Paroxystique</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="FA persistante (> 7 j)"> Persistante</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="FA permanente"> Permanente</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="FA valvulaire"> Valvulaire</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="FA non valvulaire"> Non valvulaire</label>
                    </div>
                    <label style="font-size:10px;cursor:pointer;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-parent" data-target="md_flutter" onchange="madToggle(this)"> ▶ Flutter atrial</label>
                    <div id="md_flutter" style="display:none;margin-left:10px;">
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="Flutter typique (cavotricuspide)"> Typique</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="Flutter atypique"> Atypique</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="Flutter gauche"> Gauche</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="Flutter droit"> Droit</label>
                    </div>
                    <label style="font-size:10px;cursor:pointer;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-parent" data-target="md_tsv" onchange="madToggle(this)"> ▶ TSV</label>
                    <div id="md_tsv" style="display:none;margin-left:10px;">
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="TSV paroxystique"> Paroxystique</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="TSV nodale"> Nodale</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="TSV atriale"> Atriale</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="TSV par WPW"> Par WPW</label>
                    </div>
                    <label style="font-size:10px;cursor:pointer;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-parent" data-target="md_esv" onchange="madToggle(this)"> ▶ ESV / ESA</label>
                    <div id="md_esv" style="display:none;margin-left:10px;">
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="ESV isolées"> ESV isolées</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="ESV bigéminisme"> Bigéminisme</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="ESV trigéminisme"> Trigéminisme</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="ESV couplets"> Couplets</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="ESA isolées"> ESA isolées</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="ESA en salves"> ESA en salves</label>
                    </div>
                </div>

                <label style="font-size:10px;cursor:pointer;display:block;margin-bottom:1px;margin-top:2px;"><input type="checkbox" class="mad-parent" data-target="md_brady" onchange="madToggle(this)"> ▶ Bradyarythmies / conduction</label>
                <div id="md_brady" style="display:none;margin-left:10px;">
                    <label style="font-size:10px;display:flex;align-items:center;gap:3px;margin-bottom:1px;">
                        <input type="checkbox" class="mad-diag" value="Bradycardie sinusale"> Bradycardie sinusale —
                        FC moy: <input type="text" id="md_fc_moy" placeholder="/min" style="width:40px;border:1px solid #ccc;border-radius:2px;padding:1px 3px;font-size:10px;">
                        min: <input type="text" id="md_fc_min" placeholder="/min" style="width:40px;border:1px solid #ccc;border-radius:2px;padding:1px 3px;font-size:10px;">
                    </label>
                    <label style="font-size:10px;cursor:pointer;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-parent" data-target="md_bav" onchange="madToggle(this)"> ▶ BAV</label>
                    <div id="md_bav" style="display:none;margin-left:10px;">
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="BAV 1er degré (PR > 200 ms)"> 1er degré</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="BAV 2e degré Mobitz I (Wenckebach)"> 2e Mobitz I</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="BAV 2e degré Mobitz II"> 2e Mobitz II</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="BAV 3e degré (complet)"> 3e degré (complet)</label>
                    </div>
                    <label style="font-size:10px;cursor:pointer;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-parent" data-target="md_bb" onchange="madToggle(this)"> ▶ Bloc de branche</label>
                    <div id="md_bb" style="display:none;margin-left:10px;">
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="BBBD"> BBBD</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="BBBG"> BBBG</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="HBAG"> HBAG</label>
                        <label style="font-size:10px;display:block;margin-bottom:1px;"><input type="checkbox" class="mad-diag" value="HBAPG"> HBAPG</label>
                    </div>
                </div>

            </div><!-- fin tab_diag -->

            <!-- ── Onglet Facteurs de risque ── -->
            <div id="tab_fdr" style="display:none;">
                <!-- Pas de FDR -->
                <label style="font-size:10px;display:block;margin-bottom:3px;"><input type="checkbox" class="mad-fdr" value="Pas de facteurs de risque"> <strong>Pas de facteurs de risque</strong></label>
                <hr style="margin:4px 0;border:none;border-top:1px solid #ddd;">

                <!-- Non modifiables -->
                <div style="font-size:10px;font-weight:bold;color:var(--th-color-primary);margin:4px 0 2px;">Non modifiables</div>
                <label style="font-size:10px;display:block;margin-bottom:1px;padding-left:10px;"><input type="checkbox" class="mad-fdr" value="Âge > 50 ans (H) ou > 65 ans (F)"> Âge : &gt;50 ans chez H, 65 ans chez F</label>
                <label style="font-size:10px;display:block;margin-bottom:1px;padding-left:10px;"><input type="checkbox" class="mad-fdr" value="Sexe masculin"> Sexe masculin</label>
                <label style="font-size:10px;display:block;margin-bottom:1px;padding-left:10px;"><input type="checkbox" class="mad-fdr" value="ATCD familiaux MCV précoce (H < 55 ans / F < 65 ans)"> ATCD familiaux (MCV précoce : H &lt; 55 ans / F &lt; 65 ans)</label>
                <label style="font-size:10px;display:block;margin-bottom:1px;padding-left:10px;"><input type="checkbox" class="mad-fdr" value="ATCD AVC < 45 ans"> ATCD AVC &lt; 45 ans</label>
                <hr style="margin:4px 0;border:none;border-top:1px solid #ddd;">

                <!-- Modifiables -->
                <div style="font-size:10px;font-weight:bold;color:var(--th-color-primary);margin:4px 0 2px;">Modifiables</div>
                <label style="font-size:10px;display:block;margin-bottom:1px;padding-left:10px;"><input type="checkbox" class="mad-fdr" value="HTA (> 140/90 mmHg ou traitement)"> HTA (&gt; 140/90 mmHg ou traitement)</label>
                <label style="font-size:10px;display:block;margin-bottom:1px;padding-left:10px;"><input type="checkbox" class="mad-fdr" value="Tabac (actif ou passif)"> Tabac (actif ou passif)</label>
                <label style="font-size:10px;display:block;margin-bottom:1px;padding-left:10px;"><input type="checkbox" class="mad-fdr" value="LDL-cholestérol élevé"> LDL-cholestérol</label>
                <label style="font-size:10px;display:block;margin-bottom:1px;padding-left:10px;"><input type="checkbox" class="mad-fdr" value="Syndrome métabolique (Ob abd - TG - HDL - HTA - Diabète)"> Syndrome métabolique (Ob abd - TG - HDL - HTA - Diabète)</label>
                <label style="font-size:10px;display:block;margin-bottom:1px;padding-left:10px;"><input type="checkbox" class="mad-fdr" value="Diabète type 2 (insulinorésistance)"> Diabète : surtout type 2, lié à l'insulinorésistance</label>
                <label style="font-size:10px;display:block;margin-bottom:1px;padding-left:10px;"><input type="checkbox" class="mad-fdr" value="Triglycérides élevés"> Triglycérides</label>
                <label style="font-size:10px;display:block;margin-bottom:1px;padding-left:10px;"><input type="checkbox" class="mad-fdr" value="Obésité abdominale (H > 94 cm / F > 80 cm)"> Obésité abdominale (tour de taille : H &gt; 94 cm / F &gt; 80 cm)</label>
                <label style="font-size:10px;display:block;margin-bottom:1px;padding-left:10px;"><input type="checkbox" class="mad-fdr" value="Obésité (IMC > 30)"> Obésité IMC &gt; 30</label>
                <label style="font-size:10px;display:block;margin-bottom:1px;padding-left:10px;"><input type="checkbox" class="mad-fdr" value="Surpoids (IMC > 25)"> Surpoids IMC &gt; 25</label>
                <label style="font-size:10px;display:block;margin-bottom:1px;padding-left:10px;"><input type="checkbox" class="mad-fdr" value="Tour de taille > 80 cm (F) ou > 94 cm (H)"> Tour de taille &gt; 80 F / 94 H</label>
                <hr style="margin:4px 0;border:none;border-top:1px solid #ddd;">

                <!-- Émergents -->
                <div style="font-size:10px;font-weight:bold;color:var(--th-color-primary);margin:4px 0 2px;">Émergents (à évoquer si risque résiduel)</div>
                <label style="font-size:10px;display:block;margin-bottom:1px;padding-left:10px;"><input type="checkbox" class="mad-fdr" value="Pollution (PM2.5)"> Pollution PM2.5</label>
                <label style="font-size:10px;display:block;margin-bottom:1px;padding-left:10px;"><input type="checkbox" class="mad-fdr" value="Score calcique élevé"> Score calcique élevé</label>
                <label style="font-size:10px;display:block;margin-bottom:1px;padding-left:10px;"><input type="checkbox" class="mad-fdr" value="Inflammatoire (CRP us > 3 mg/L ou maladie auto-immune)"> Inflammatoire (CRP us &gt; 3 mg/L, ou maladie auto-immune)</label>
                <hr style="margin:4px 0;border:none;border-top:1px solid #ddd;">

                <!-- Additionnels -->
                <div style="font-size:10px;font-weight:bold;color:var(--th-color-primary);margin:4px 0 2px;">Facteurs de risque additionnels</div>
                <label style="font-size:10px;display:block;margin-bottom:1px;padding-left:10px;"><input type="checkbox" class="mad-fdr" value="Sédentarité"> Sédentarité</label>
                <label style="font-size:10px;display:block;margin-bottom:1px;padding-left:10px;"><input type="checkbox" class="mad-fdr" value="Alimentation pro-inflammatoire (gras trans, sucres rapides, sel)"> Alimentation pro-inflammatoire (gras trans, sucres rapides, sel)</label>
                <label style="font-size:10px;display:block;margin-bottom:1px;padding-left:10px;"><input type="checkbox" class="mad-fdr" value="Alcool (excès chronique)"> Alcool (excès chronique)</label>
                <label style="font-size:10px;display:block;margin-bottom:1px;padding-left:10px;"><input type="checkbox" class="mad-fdr" value="Stress / anxiété / troubles du sommeil"> Stress / anxiété / troubles du sommeil</label>
                <label style="font-size:10px;display:block;margin-bottom:1px;padding-left:10px;"><input type="checkbox" class="mad-fdr" value="Insuffisance rénale (DFG < 60 ml/min)"> Insuffisance rénale (DFG &lt; 60 ml/min)</label>
                <label style="font-size:10px;display:block;margin-bottom:1px;padding-left:10px;"><input type="checkbox" class="mad-fdr" value="AOMI ou plaque carotidienne asymptomatique"> AOMI ou plaque carotidienne asymptomatique</label>
                <label style="font-size:10px;display:block;margin-bottom:1px;padding-left:10px;"><input type="checkbox" class="mad-fdr" value="Antécédent de prééclampsie (F)"> Antécédent de prééclampsie (F)</label>
                <label style="font-size:10px;display:block;margin-bottom:1px;padding-left:10px;"><input type="checkbox" class="mad-fdr" value="Apnée du sommeil non traitée"> Apnée du sommeil non traitée</label>
            </div>

        </div><!-- fin scroll -->

        <!-- Footer boutons -->
        <div style="padding:8px 12px;border-top:1px solid #e0e0e0;display:flex;justify-content:space-between;align-items:center;flex-shrink:0;background:#f9f9f9;">
            <button type="button" onclick="madToutDecocher()"
                style="background:#e74c3c;color:white;border:none;border-radius:3px;padding:4px 12px;font-size:11px;cursor:pointer;">✕ Tout décocher</button>
            <button type="button" onclick="madValiderTout()"
                style="background:#27ae60;color:white;border:none;border-radius:3px;padding:4px 16px;font-size:12px;font-weight:bold;cursor:pointer;">✓ Valider et insérer</button>
        </div>
    </div>
</div>
</body>
</html>