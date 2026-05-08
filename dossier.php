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
if (!$patient) { die("❌ Patient introuvable !"); }

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

// Ordonnance précédente = celle d'avant (index 1 = la plus récente avant la courante)
// Elle contient le RDV qui avait été fixé il y a 1/3/6 mois
$ordPrecedente = null;
foreach ($ordonnances as $o) {
    if ($o['n_ordon'] != $nOrd) {
        $ordPrecedente = $o;
        break;
    }
}
// Acte suggéré pour le nouveau RDV basé sur l'algorithme
$acteNouveauRDV = '';
if ($ordPrecedente) {
    $acteNouveauRDV = $ordPrecedente['acte1'] ?? '';
}

// Actes automatiques basés sur les ordonnances
$actesSuggeres = [];

// Dernier ECG prescrit
$stmtLastECG = $db->prepare("
    SELECT TOP 1 date_ordon FROM ORD 
    WHERE id=? AND acte1 LIKE '%ECG%' 
    ORDER BY date_ordon DESC
");
$stmtLastECG->execute([$id]);
$lastECG = $stmtLastECG->fetchColumn();
if (!$lastECG || (new DateTime())->diff(new DateTime($lastECG))->days > 30) {
    $actesSuggeres[] = ['acte' => 'ECG', 'derniere' => $lastECG];
}

// Dernier EDC prescrit
$stmtLastEDC = $db->prepare("
    SELECT TOP 1 date_ordon FROM ORD 
    WHERE id=? AND acte1 LIKE '%EDC%' 
    ORDER BY date_ordon DESC
");
$stmtLastEDC->execute([$id]);
$lastEDC = $stmtLastEDC->fetchColumn();
if (!$lastEDC || (new DateTime())->diff(new DateTime($lastEDC))->days > 335) {
    $actesSuggeres[] = ['acte' => 'EDC', 'derniere' => $lastEDC];
}

// Dernier DTSA prescrit
$stmtLastDTSA = $db->prepare("
    SELECT TOP 1 date_ordon FROM ORD 
    WHERE id=? AND acte1 LIKE '%DTSA%' 
    ORDER BY date_ordon DESC
");
$stmtLastDTSA->execute([$id]);
$lastDTSA = $stmtLastDTSA->fetchColumn();
if (!$lastDTSA || (new DateTime())->diff(new DateTime($lastDTSA))->days > 335) {
    $actesSuggeres[] = ['acte' => 'DTSA', 'derniere' => $lastDTSA];
}

$ordCourante = null;
$idxOrdCourante = 0;
foreach ($ordonnances as $i => $o) {
    if ($o['n_ordon'] == $nOrd) { $ordCourante = $o; $idxOrdCourante = $i; break; }
}

// Ordonnance précédente = celle juste avant dans le temps (index+1 car tri DESC)
// Elle contient le RDV qui avait été fixé lors de la consultation précédente
$ordPrecedente = isset($ordonnances[$idxOrdCourante + 1]) ? $ordonnances[$idxOrdCourante + 1] : null;

// Médicaments de l'ordonnance courante
$medicaments = [];
if ($nOrd) {
    $stmtMed = $db->prepare("
        SELECT p.*, pr.PRODUIT 
        FROM PROD p 
        LEFT JOIN PRODUITS pr ON p.produit = pr.NuméroPRODUIT
        WHERE p.N_ord = ? ORDER BY p.Ordre
    ");
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
$ecgCourant = null;
$idxECG = 0;
foreach ($ecgs as $i => $e) { if ($e['N°'] == $nECG) { $ecgCourant = $e; $idxECG = $i; break; } }

// Examens Echo
$stmtEchos = $db->prepare("SELECT * FROM echo WHERE [N-PAT]=? ORDER BY DATEchog DESC");
$stmtEchos->execute([$id]);
$echos = $stmtEchos->fetchAll();
$nEcho = (int)($_GET['echo'] ?? ($echos ? $echos[0]['N°'] : 0));
$echoCourant = null;
$idxEcho = 0;
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
$factCourante = null;
$idxFact = 0;
foreach ($factures as $i => $f) { if ($f['n_facture'] == $nFact) { $factCourante = $f; $idxFact = $i; break; } }
$factPremiere = $factures ? $factures[count($factures)-1]['n_facture'] : 0;
$factDerniere = $factures ? $factures[0]['n_facture'] : 0;
$factPrev = ($idxFact < count($factures)-1) ? $factures[$idxFact+1]['n_facture'] : $nFact;
$factNext = ($idxFact > 0) ? $factures[$idxFact-1]['n_facture'] : $nFact;

// Détail actes facture courante
$detailActes = [];
if ($nFact) {
    $stmtDA = $db->prepare("
        SELECT d.*, a.ACTE AS nom_acte
        FROM detail_acte d
        LEFT JOIN t_acte_simplifiée a ON d.ACTE = a.n_acte
        WHERE d.N_fact = ?
    ");
    $stmtDA->execute([$nFact]);
    $detailActes = $stmtDA->fetchAll();
}

// Navigation ordonnances
$idxOrd = 0;
foreach ($ordonnances as $i => $o) { if ($o['n_ordon'] == $nOrd) { $idxOrd = $i; break; } }
$ordPremiere = $ordonnances ? $ordonnances[count($ordonnances)-1]['n_ordon'] : 0;
$ordDerniere = $ordonnances ? $ordonnances[0]['n_ordon'] : 0;
$ordPrev = ($idxOrd < count($ordonnances)-1) ? $ordonnances[$idxOrd+1]['n_ordon'] : $nOrd;
$ordNext = ($idxOrd > 0) ? $ordonnances[$idxOrd-1]['n_ordon'] : $nOrd;

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
    '1er jour 1 cp ; 2ème jour 1 cp ; 3ème jour 3/4 cp',
    '1er jour 3/4 cp ; 2ème jour 3/4 cp ; 3ème jour 1/2 cp',
    '1er jour 1/2 cp ; 2ème jour 1/2 cp ; 3ème jour 1/4 cp',
    '1er jour 1/4 cp ; 2ème jour 1/4 cp ; 3ème jour rien',
    '1cp 8h;1cp 12h3j puis 1cp8h-1/2cp12h 3j puis 1cp 8h 3mois',
];

$durees = ['1 semaine','2 semaines','1 mois','2 mois','3 mois','6 mois'];

// Catalogue médicaments
$stmtCat = $db->prepare("SELECT NuméroPRODUIT, PRODUIT FROM PRODUITS ORDER BY PRODUIT");
$stmtCat->execute();
$catalogue = $stmtCat->fetchAll();

// Catalogue actes
$stmtActes = $db->prepare("SELECT n_acte, ACTE FROM t_acte_simplifiée ORDER BY n_acte");
$stmtActes->execute();
$actesCat = $stmtActes->fetchAll();
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

    <!-- COLONNE GAUCHE : ID -->
    <div class="col-left">
        <div class="card">
            <div class="card-title">👤 Dossier patient</div>
            <div class="champ">
                <label>Motif de consultation</label>
                <textarea><?= htmlspecialchars($patient['MOTIF CONSULTATION'] ?? '') ?></textarea>
            </div>
            <div class="champ">
                <label>Antécédents</label>
                <textarea><?= htmlspecialchars($patient['ATCD'] ?? '') ?></textarea>
            </div>
            <div class="champ">
                <label>Diagnostic principal</label>
                <?php foreach ($diagnostics as $d): ?>
                    <div style="padding:2px 0;font-size:12px;">• <?= htmlspecialchars($d['diagnostic']) ?></div>
                <?php endforeach; ?>
                <?php if (empty($diagnostics)): ?>
                    <span style="color:#999;font-size:11px;">—</span>
                <?php endif; ?>
                <a href="diag_edit.php?id=<?= $id ?>&type=1" style="font-size:11px;color:#2e6da4;">✏️ Modifier</a>
            </div>
            <div class="champ">
                <label>Diagnostic II</label>
                <?php foreach ($diagnosticsII as $d): ?>
                    <div style="padding:2px 0;font-size:12px;">• <?= htmlspecialchars($d['DicII']) ?></div>
                <?php endforeach; ?>
                <?php if (empty($diagnosticsII)): ?>
                    <span style="color:#999;font-size:11px;">—</span>
                <?php endif; ?>
                <a href="diag_edit.php?id=<?= $id ?>&type=2" style="font-size:11px;color:#2e6da4;">✏️ Modifier</a>
            </div>
            <div class="champ">
                <label>Diagnostic non cardiologique</label>
                <?php foreach ($diagnosticsNC as $d): ?>
                    <div style="padding:2px 0;font-size:12px;">• <?= htmlspecialchars($d['dic_non_cardio']) ?></div>
                <?php endforeach; ?>
                <?php if (empty($diagnosticsNC)): ?>
                    <span style="color:#999;font-size:11px;">—</span>
                <?php endif; ?>
                <a href="diag_edit.php?id=<?= $id ?>&type=3" style="font-size:11px;color:#2e6da4;">✏️ Modifier</a>
            </div>
            <?php
            $fdrs = [];
            $nomsfdrs = [
                'FDR_Age' => "L'âge",
                'FDR_ATCD_IDM_Fam' => 'ATCD IDM famille',
                'FDR_ATCD_AVC_Fam' => 'ATCD AVC',
                'FDR_Tabac' => 'Tabagisme',
                'FDR_Diabete' => 'Diabète',
                'FDR_HTA' => 'HTA',
                'FDR_LDL_Oui' => 'LDL cholestérol',
                'FDR_TG_Oui' => 'Triglycérides',
                'FDR_Obesite' => 'Obésité',
                'FDR_Surpoids' => 'Surpoids',
                'FDR_Tour_Taille' => 'Tour de taille',
                'FDR_Sedentarite' => 'Sédentarité',
                'FDR_Synd_Metabolique' => 'Synd. métabolique',
                'FDR_Stress_Depression' => 'Stress/Dépression',
                'FDR_Sommeil' => 'Troubles du sommeil',
                'FDR_Drogues' => 'Drogues',
            ];
            if ($examen) {
                foreach ($nomsfdrs as $champ => $nom) {
                    if (!empty($examen[$champ])) $fdrs[] = $nom;
                }
            }
            ?>
            <?php if (!empty($fdrs)): ?>
            <div class="champ">
                <label>Facteurs de risque</label>
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
            <div class="champ">
                <label>Remarque</label>
                <textarea><?= htmlspecialchars($patient['REMARQUE'] ?? '') ?></textarea>
            </div>
        </div>
    </div>

    <!-- COLONNE MILIEU : ORDONNANCE -->
    <div class="col-mid">
        <div class="card">
            <div class="card-title">
                📋 Ordonnance
                <div class="nav-btns">
                    <a href="?id=<?= $id ?>&ord=<?= $ordPremiere ?>" class="nav-btn" title="Première">|◀</a>
                    <a href="?id=<?= $id ?>&ord=<?= $ordPrev ?>" class="nav-btn" title="Précédente">◀</a>
                    <span style="font-size:11px;color:#1a4a7a;font-weight:bold;padding:0 4px;white-space:nowrap;"><?= ($idxOrd+1) ?> / <?= count($ordonnances) ?></span>
                    <a href="?id=<?= $id ?>&ord=<?= $ordNext ?>" class="nav-btn" title="Suivante">▶</a>
                    <a href="?id=<?= $id ?>&ord=<?= $ordDerniere ?>" class="nav-btn" title="Dernière">▶|</a>
                    <a href="nouvelle_ordonnance.php?id=<?= $id ?>" class="nav-btn" title="Nouvelle">✚</a>
                </div>
            </div>

            <?php if ($ordCourante): ?>

            <!-- GRID RDV + FACTURATION -->
            <div style="display:grid;grid-template-columns:1fr 380px;gap:8px;align-items:start;margin-bottom:8px;">
            <div><!-- COL GAUCHE : TABLEAU RDV -->
            <!-- ===== TABLEAU RDV 3 COLONNES ===== -->
            <?php
            $rdvFixeDate  = $ordPrecedente && !empty($ordPrecedente['DATE REDEZ VOUS'])
                            ? date('d/m/Y', strtotime($ordPrecedente['DATE REDEZ VOUS'])) : '—';
            $rdvFixeHeure = $ordPrecedente ? htmlspecialchars($ordPrecedente['HeureRDV'] ?? '—') : '—';
            $rdvFixeActe  = $ordPrecedente ? htmlspecialchars($ordPrecedente['acte1'] ?? '—') : '—';
            $rdvFuturVal  = $ordCourante['DATE REDEZ VOUS'] ? date('Y-m-d', strtotime($ordCourante['DATE REDEZ VOUS'])) : '';
            ?>
            <table class="tableau-rdv">
                <thead>
                    <tr>
                        <th style="background:#1a4a7a;color:white;width:90px;"></th>
                        <th style="background:#2e6da4;color:white;">
                            📅 RDV déjà fixé<br>
                            <span style="font-size:10px;font-weight:normal;opacity:0.9;">il y a 1/3/6 mois</span>
                        </th>
                        <th style="background:#27ae60;color:white;">
                            🩺 Visite aujourd'hui<br>
                            <span style="font-size:10px;font-weight:normal;opacity:0.9;"><?= date('d/m/Y') ?></span>
                        </th>
                        <th style="background:#8e44ad;color:white;">
                            📆 Nouveau RDV<br>
                            <span style="font-size:10px;font-weight:normal;opacity:0.9;">à donner aujourd'hui</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <!-- DATE -->
                    <tr>
                        <td>📅 Date</td>
                        <td class="col-rdv-fixe" style="text-align:center;">
                            <strong style="color:#2e6da4;font-size:13px;"><?= $rdvFixeDate ?></strong>
                        </td>
                        <td class="col-visite" style="text-align:center;">
                            <strong style="color:#27ae60;font-size:13px;"><?= date('d/m/Y') ?></strong>
                        </td>
                        <td class="col-rdv-futur" style="text-align:center;">
                            <input type="date" id="rdv_futur"
                                   value="<?= $rdvFuturVal ?>"
                                   style="width:100%;padding:3px 4px;border:1px solid #8e44ad;border-radius:3px;font-size:11px;">
                        </td>
                    </tr>
                    <!-- HEURE -->
                    <tr>
                        <td>⏰ Heure</td>
                        <td class="col-rdv-fixe" style="text-align:center;">
                            <strong style="color:#2e6da4;font-size:13px;"><?= $rdvFixeHeure ?></strong>
                        </td>
                        <td class="col-visite" style="text-align:center;">
                            <strong style="color:#27ae60;font-size:13px;"><?= $rdvFixeHeure ?></strong>
                        </td>
                        <td class="col-rdv-futur" style="text-align:center;">
                            <input type="time" id="heure_rdv_futur"
                                   value="<?= htmlspecialchars($ordCourante['HeureRDV'] ?? '') ?>"
                                   style="width:95px;padding:3px 4px;border:1px solid #8e44ad;border-radius:3px;font-size:12px;text-align:center;">
                        </td>
                    </tr>
                    <!-- ACTE -->
                    <tr>
                        <td>🏥 Acte</td>
                        <td class="col-rdv-fixe" style="text-align:center;">
                            <span style="background:#dce8f7;color:#1a4a7a;padding:2px 8px;border-radius:8px;font-size:12px;font-weight:bold;"><?= $rdvFixeActe ?></span>
                        </td>
                        <td class="col-visite" style="text-align:center;">
                            <span class="acte-badge" style="font-size:12px;font-weight:bold;"><?= $rdvFixeActe ?></span>
                        </td>
                        <td class="col-rdv-futur" style="padding:4px;">
                            <input type="text" id="acte_rdv_futur"
                                   value="<?= htmlspecialchars($acteNouveauRDV) ?>"
                                   style="width:100%;padding:3px 4px;border:1px solid #8e44ad;border-radius:3px;font-size:11px;text-align:center;margin-bottom:4px;">
                            <!-- BOUTONS ACTES NOUVEAU RDV -->
                            <div style="display:flex;gap:3px;flex-wrap:wrap;margin-top:3px;">
                                <?php
                                $boutonsActes = ['ECG','ECG-EDC','ECG-EDC-DTSA','DTSA','EDC','DVMI','BILAN','CONTROL','DAMI'];
                                foreach ($boutonsActes as $ba):
                                ?>
                                <button onclick="document.getElementById('acte_rdv_futur').value='<?= $ba ?>';"
                                        style="background:#8e44ad;color:white;border:none;padding:2px 6px;border-radius:3px;cursor:pointer;font-size:10px;margin-bottom:2px;">
                                    <?= $ba ?>
                                </button>
                                <?php endforeach; ?>
                            </div>
                        </td>
                    </tr>
                    <!-- DELAI RDV -->
                    <tr>
                        <td>⏳ Délai</td>
                        <td class="col-rdv-fixe" style="text-align:center;color:#888;font-size:11px;">—</td>
                        <td class="col-visite" style="text-align:center;color:#888;font-size:11px;">—</td>
                        <td class="col-rdv-futur" style="padding:4px;">
                            <div style="display:flex;gap:3px;flex-wrap:wrap;">
                                <button onclick="setDelai(1,0)" class="delai-btn-rdv">1M</button>
                                <button onclick="setDelai(3,0)" class="delai-btn-rdv actif">3M</button>
                                <button onclick="setDelai(6,0)" class="delai-btn-rdv">6M</button>
                                <button onclick="setDelai(0,7)" class="delai-btn-rdv">7J</button>
                                <button onclick="setDelai(0,10)" class="delai-btn-rdv">10J</button>
                                <button onclick="setDelai(0,15)" class="delai-btn-rdv">15J</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            <!-- ===== FIN TABLEAU RDV ===== -->

            <!-- ACTES SUGGERES -->
            <?php if (!empty($actesSuggeres)): ?>
            <div style="background:#fff3cd;border-left:4px solid #f39c12;padding:8px;border-radius:4px;margin-bottom:8px;">
                <div style="font-size:11px;font-weight:bold;color:#856404;margin-bottom:6px;">⚠️ Actes suggérés automatiquement</div>
                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                    <?php foreach ($actesSuggeres as $a): ?>
                    <span style="background:#f39c12;color:white;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:bold;">
                        <?= $a['acte'] ?>
                        <?php if ($a['derniere']): ?>
                            <span style="font-size:10px;opacity:0.85;">(dernier : <?= date('d/m/Y', strtotime($a['derniere'])) ?>)</span>
                        <?php else: ?>
                            <span style="font-size:10px;opacity:0.85;">(jamais prescrit)</span>
                        <?php endif; ?>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- BOUTONS ACTES -->
            <div class="champ">
                <label>Actes à programmer</label>
                <div class="actes-btns">
                    <button class="acte-btn">ECG</button>
                    <button class="acte-btn">EDC</button>
                    <button class="acte-btn">DTSA</button>
                    <button class="acte-btn">ECG+DTSA</button>
                    <button class="acte-btn">CONTROLE</button>
                    <button class="acte-btn">DVMI</button>
                    <button class="acte-btn">EDCP</button>
                </div>
            </div>

            <!-- MEDICAMENTS -->
            <div class="champ" style="margin-top:8px;">
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
                <?php if (empty($medicaments)): ?>
                    <p style="color:#999;font-size:12px;">Aucun médicament</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

            </div><!-- FIN COL GAUCHE -->
            <div><!-- COL DROITE : FACTURATION + CERTIFICAT -->
        <!-- FACTURATION -->
        <div class="card" style="margin:0 0 8px 0;">
            <div class="card-title">
                💰 Facturation
                <div class="nav-btns">
                    <a href="?id=<?= $id ?>&fact=<?= $factPremiere ?>" class="nav-btn">|◀</a>
                    <a href="?id=<?= $id ?>&fact=<?= $factPrev ?>" class="nav-btn">◀</a>
                    <span style="font-size:11px;color:#1a4a7a;font-weight:bold;padding:0 4px;white-space:nowrap;"><?= ($idxFact+1) ?> / <?= count($factures) ?></span>
                    <a href="?id=<?= $id ?>&fact=<?= $factNext ?>" class="nav-btn">▶</a>
                    <a href="?id=<?= $id ?>&fact=<?= $factDerniere ?>" class="nav-btn">▶|</a>
                </div>
            </div>
            <?php if ($factCourante): ?>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-bottom:8px;">
                <div class="champ">
                    <label>N° Facture</label>
                    <input type="text" value="<?= $factCourante['n_facture'] ?>" readonly>
                </div>
                <div class="champ">
                    <label>Date facture</label>
                    <input type="date" value="<?= $factCourante['date_facture'] ? date('Y-m-d', strtotime($factCourante['date_facture'])) : '' ?>"
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
            <?php else: ?>
                <p style="color:#999;font-size:12px;">Aucune facture</p>
            <?php endif; ?>
        </div>
            <!-- CERTIFICAT MEDICAL -->
            <div class="card" style="margin:0;background:#fff8e1;">
                <div class="card-title" style="color:#856404;">📄 Certificat médical</div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;">
                    <div class="champ">
                        <label>Du</label>
                        <input type="date" id="cert_debut" onchange="calcJours()">
                    </div>
                    <div class="champ">
                        <label>Au</label>
                        <input type="date" id="cert_fin" onchange="calcJours()">
                    </div>
                </div>
                <div style="margin-top:4px;">
                    <label style="font-size:11px;color:#856404;font-weight:bold;">Nombre de jours : <strong id="cert_jours">—</strong></label>
                </div>
            </div>
            </div><!-- FIN COL DROITE -->
            </div><!-- FIN GRID RDV+FACT -->
    </div>

    <!-- COLONNE DROITE : EXAMEN -->
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
                    <span class="ta-val" style="color:<?= $coulTA ?>">
                        <?= ($tas && $tad) ? $tas.'/'.$tad : '—' ?>
                    </span>
                </div>
                <div class="champ">
                    <label>FC</label>
                    <span><?= $examen['FC'] ? $examen['FC'].' bpm' : '—' ?></span>
                </div>
                <div class="champ">
                    <label>Poids</label>
                    <span><?= $examen['POIDS'] ? $examen['POIDS'].' kg' : '—' ?></span>
                </div>
                <div class="champ">
                    <label>IMC</label>
                    <span><?= $examen['IMC'] ? number_format($examen['IMC'],1) : '—' ?></span>
                </div>
            </div>
            <div class="champ">
                <label>Signes fonctionnels</label>
                <textarea readonly style="min-height:30px;field-sizing:content;"><?= htmlspecialchars($examen['S_Fonctionnels'] ?? '') ?></textarea>
            </div>
            <div class="champ">
                <label>Auscultation cardiaque</label>
                <textarea readonly style="min-height:30px;field-sizing:content;"><?= htmlspecialchars($examen['Auscult_Cardiaque'] ?? '') ?></textarea>
            </div>
            <div class="champ">
                <label>Auscultation pulmonaire</label>
                <textarea readonly style="min-height:30px;field-sizing:content;"><?= htmlspecialchars($examen['Auscult_Pulmonaire'] ?? '') ?></textarea>
            </div>
            <div class="champ">
                <label>Remarque</label>
                <textarea readonly style="min-height:30px;field-sizing:content;"><?= htmlspecialchars($examen['REMARQUE'] ?? '') ?></textarea>
            </div>
            <div class="champ">
                <label>Conduite à tenir</label>
                <textarea readonly style="min-height:30px;field-sizing:content;"><?= htmlspecialchars($examen['Conclusion'] ?? '') ?></textarea>
            </div>
            <?php else: ?>
                <p style="color:#999;font-size:12px;">Aucun examen</p>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- BAS DE PAGE : ECG + ECHO -->
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

    <!-- ECHO -->
    <div class="card">
        <div class="card-title">
            🫀 Echo-Doppler
            <div class="nav-btns">
                <a href="?id=<?= $id ?>&echo=<?= $echos ? $echos[count($echos)-1]['N°'] : 0 ?>" class="nav-btn">|◀</a>
                <a href="?id=<?= $id ?>&echo=<?= $echos && $idxEcho < count($echos)-1 ? $echos[$idxEcho+1]['N°'] : $nEcho ?>" class="nav-btn">◀</a>
                <span style="font-size:11px;color:#1a4a7a;font-weight:bold;padding:0 4px;white-space:nowrap;"><?= count($echos) ? ($idxEcho+1).' / '.count($echos) : '0' ?></span>
                <a href="?id=<?= $id ?>&echo=<?= $echos && $idxEcho > 0 ? $echos[$idxEcho-1]['N°'] : $nEcho ?>" class="nav-btn">▶</a>
                <a href="?id=<?= $id ?>&echo=<?= $echos ? $echos[0]['N°'] : 0 ?>" class="nav-btn">▶|</a>
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

</div>

<script>
// Recherche patient
document.getElementById('rech-patient').addEventListener('input', function() {
    const val = this.value.trim();
    const sugg = document.getElementById('rech-suggestions');
    if (val.length < 2) { sugg.style.display = 'none'; return; }
    fetch('backend/api/recherche_patient.php?q=' + encodeURIComponent(val))
        .then(r => r.json())
        .then(data => {
            sugg.innerHTML = '';
            if (data.length === 0) { sugg.style.display = 'none'; return; }
            data.forEach(p => {
                const div = document.createElement('div');
                div.style = 'padding:6px 10px;cursor:pointer;font-size:11px;border-bottom:1px solid #333;background:#1a1a1a;color:#FFD700;font-weight:bold;';
                div.textContent = p.nom + ' — ' + p.age + ' ans';
                div.onmouseover = () => div.style.background = '#f0f7ff';
                div.onmouseout = () => div.style.background = '#1a1a1a';
                div.onclick = () => window.location.href = 'dossier.php?id=' + p.id;
                sugg.appendChild(div);
            });
            sugg.style.display = 'block';
        });
});
document.getElementById('rech-patient').addEventListener('focus', function() {
    const sugg = document.getElementById('rech-suggestions');
    fetch('backend/api/recherche_patient.php?q=')
        .then(r => r.json())
        .then(data => {
            sugg.innerHTML = '';
            data.forEach(p => {
                const div = document.createElement('div');
                div.style = 'padding:6px 10px;cursor:pointer;font-size:11px;border-bottom:1px solid #333;background:#1a1a1a;color:#FFD700;font-weight:bold;';
                div.innerHTML = p.id + ' — ' + p.nom + ' <span style="color:#888;font-size:10px;">' + p.age + ' ans</span>';
                div.onmouseover = () => div.style.background = '#f0f7ff';
                div.onmouseout = () => div.style.background = '#1a1a1a';
                div.onclick = () => window.location.href = 'dossier.php?id=' + p.id;
                sugg.appendChild(div);
            });
            sugg.style.display = 'block';
        });
});
document.addEventListener('click', function(e) {
    if (!e.target.closest('#rech-patient') && !e.target.closest('#rech-suggestions')) {
        document.getElementById('rech-suggestions').style.display = 'none';
    }
});

function setDelai(mois, jours) {
    const base = new Date();
    if (mois > 0) {
        base.setMonth(base.getMonth() + mois);
    } else {
        base.setDate(base.getDate() + jours);
    }
    const y = base.getFullYear();
    const m = String(base.getMonth() + 1).padStart(2, '0');
    const d = String(base.getDate()).padStart(2, '0');
    document.getElementById('rdv_futur').value = y + '-' + m + '-' + d;
    // Highlight bouton actif
    document.querySelectorAll('.delai-btn-rdv').forEach(b => b.classList.remove('actif'));
    event.target.classList.add('actif');
}

function calcJours() {
    const d1 = document.getElementById('cert_debut').value;
    const d2 = document.getElementById('cert_fin').value;
    if (d1 && d2) {
        const diff = Math.round((new Date(d2) - new Date(d1)) / 86400000) + 1;
        document.getElementById('cert_jours').textContent = diff > 0 ? diff + ' jour(s)' : '—';
    }
}

function majDateActe(id, val) {
    fetch('backend/api/maj_date_acte.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id: id, date: val})
    }).then(r => r.json()).then(d => {
        if (d.ok) console.log('Date acte mise à jour');
    });
}

function majDateFacture(id, val) {
    fetch('backend/api/maj_date_facture.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id: id, date: val})
    }).then(r => r.json()).then(d => {
        if (d.ok) console.log('Date facture mise à jour');
    });
}
</script>
</body>
</html>