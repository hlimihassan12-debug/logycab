<?php
ob_start();
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
if ($id == 0) { header('Location: recherche.php'); exit; }

$stmt = $db->prepare("SELECT * FROM ID WHERE [N°PAT] = ?");
$stmt->execute([$id]);
$patient = $stmt->fetch();
if (!$patient) { die("❌ Patient introuvable !"); }

$nom   = strtoupper(trim($patient['NOMPRENOM'] ?? ''));
$msgs  = [];
$urlMsg = $_GET['msg'] ?? '';
if ($urlMsg === 'examen_ok') $msgs['examen'] = '✅ Examen enregistré';
if ($urlMsg === 'ecg_ok')    $msgs['ecg']    = '✅ ECG enregistré';
if ($urlMsg === 'echo_ok')   $msgs['echo']   = '✅ Echo-Doppler enregistré';

function toSqlDate($d) {
    if (!$d) return null;
    $ts = strtotime($d);
    return ($ts && $ts > 0) ? date('Y-m-d H:i:s', $ts) : null;
}

// ── Détection appel AJAX (header X-Requested-With ou paramètre ajax=1) ──
$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
       || ($_POST['ajax'] ?? '') === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $onglet = $_POST['onglet'] ?? '';

    if ($onglet === 'examen') {
        $dEx = $_POST['DateExam'] ?? date('Y-m-d');
        try {
            $db->prepare("INSERT INTO t_examen 
                (NPAT,DateExam,TAS,TAD,FC,POIDS,TAILLE,
                 S_Fonctionnels,Auscult_Cardiaque,Auscult_Pulmonaire,
                 Examen_Vasculaire,Signes_IVG,Signes_IVD,
                 Autres_Symptomes,Conclusion,REMARQUE,Conduite_ATenir,CMLM_EXAMEN)
                VALUES (?,CONVERT(datetime,?,120),?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$id,
                $dEx.' 00:00:00',$_POST['TAS']?:null,$_POST['TAD']?:null,
                $_POST['FC']?:null,$_POST['POIDS']?:null,$_POST['TAILLE']?:null,
                $_POST['S_Fonctionnels']?:null,$_POST['Auscult_Cardiaque']?:null,
                $_POST['Auscult_Pulmonaire']?:null,$_POST['Examen_Vasculaire']?:null,
                $_POST['Signes_IVG']?:null,$_POST['Signes_IVD']?:null,
                $_POST['Autres_Symptomes']?:null,$_POST['Conclusion']?:null,
                $_POST['REMARQUE']?:null,$_POST['Conduite_ATenir']?:null,
                $_POST['CMLM_EXAMEN']?:null]);
            if ($isAjax) { ob_clean(); header('Content-Type: application/json'); echo json_encode(['ok'=>true,'msg'=>'✅ Examen enregistré']); exit; }
        } catch (Exception $e) {
            if ($isAjax) { ob_clean(); header('Content-Type: application/json'); echo json_encode(['ok'=>false,'msg'=>'❌ Examen : '.$e->getMessage()]); exit; }
        }
        header("Location: ?id=$id&msg=examen_ok"); exit;
    }

    if ($onglet === 'ecg') {
        $dEcg = $_POST['Date_ECG'] ?? date('Y-m-d');
        try {
            $db->prepare("INSERT INTO ecg
                ([N-PAT],[Date ECG],
                 FREQUENCE,
                 [RYTHME SUPRA VENTRICULAIRE],
                 [trouble de rythme],
                 [RYTHME VENTRICULAIRE],
                 [LA CONDUCTION NODALE],
                 QRS,
                 [LA CONDUCTION INFRANODALE],
                 [LA REPOLARISATION],
                 [SEGMENT ST],TOPOGRAPHIE_ST,
                 ONDE_T,TOPOGRAPHIE_T,
                 IDM,TOPOGRAPHIE_Q,
                 [C/C],
                 [AUTRES Signes ECG],
                 CMLM_ECG)
                VALUES (?,CONVERT(datetime,?,120),?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$id,
                $dEcg.' 00:00:00',
                $_POST['FREQUENCE']?:null,
                $_POST['rythme_sv']?:null,
                $_POST['trouble_rv']?:null,
                $_POST['rythme_v']?:null,
                $_POST['conduction_nodale']?:null,
                $_POST['QRS']?:null,
                $_POST['infrastructure_de_conduction']?:null,
                $_POST['REPOLARISATION']?:null,
                $_POST['SEGMENT_ST']?:null,
                $_POST['TOPOGRAPHIE_ST']?:null,
                $_POST['ONDE_T']?:null,
                $_POST['TOPOGRAPHIE_T']?:null,
                $_POST['IDM']?:null,
                $_POST['TOPOGRAPHIE_Q']?:null,
                $_POST['CC']?:null,
                $_POST['AUTRES_SIGNES']?:null,
                $_POST['CMLM_ECG']?:null]);
            if ($isAjax) { ob_clean(); header('Content-Type: application/json'); echo json_encode(['ok'=>true,'msg'=>'✅ ECG enregistré']); exit; }
        } catch (Exception $e) {
            if ($isAjax) { ob_clean(); header('Content-Type: application/json'); echo json_encode(['ok'=>false,'msg'=>'❌ ECG : '.$e->getMessage()]); exit; }
        }
        header("Location: ?id=$id&msg=ecg_ok"); exit;
    }

    if ($onglet === 'echo') {
        $dEcho = $_POST['DATEchog'] ?? date('Y-m-d');
        $fn = fn($v) => (isset($_POST[$v]) && $_POST[$v] !== '') ? (float)$_POST[$v] : null;
        try {
            $db->prepare("INSERT INTO echo
                ([N-PAT],DATEchog,ECHOGENICITE,[RACINE-AO],
                 [DTD-VG],[DTS-VG],SIV,PP,FEVG,
                 CINETIQUE,HTAP,DOPPLER,CONCLUSION1,
                 [DOPPLER DES TRONCS SUPRA AORTIQUES],TYPE_ECHO,CMLM_ECHO,
                 GRAD_MOY_AO,PHT_AO,VMAX_IA,
                 SURF_MI,SOR_MI,HTAP_TR,GRAD_PU,
                 GLOBAL_STRAIN,DIAM_PERIC,
                 OG_SURF,OD_SURF,DIAM_VCI,DIAM_AO_INIT)
                VALUES (?,CONVERT(datetime,?,120),?,?,?,?,?,?,?,?,?,?,?,?,?,?,
                        ?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$id,
                $dEcho.' 00:00:00',$_POST['ECHOGENICITE']?:null,
                $_POST['RACINE_AO']?:null,$fn('DTD_VG'),
                $fn('DTS_VG'),$fn('SIV'),
                null,$fn('FEVG'),
                $_POST['CINETIQUE']?:null,$_POST['HTAP']?:null,
                $_POST['DOPPLER']?:null,$_POST['CONCLUSION1']?:null,
                $_POST['DTSA']?:null,
                $_POST['TYPE_ECHO']?:null,
                $_POST['CMLM_ECHO']?:null,
                $fn('GRAD_MOY_AO'),$fn('PHT_AO'),$fn('VMAX_IA'),
                $fn('SURF_MI'),$fn('SOR_MI'),$fn('HTAP_TR'),$fn('GRAD_PU'),
                $fn('GLOBAL_STRAIN'),$fn('DIAM_PERIC'),
                $fn('OG_SURF'),$fn('OD_SURF'),$fn('DIAM_VCI'),$fn('DIAM_AO_INIT')]);
            if ($isAjax) { ob_clean(); header('Content-Type: application/json'); echo json_encode(['ok'=>true,'msg'=>'✅ Echo enregistré']); exit; }
        } catch (Exception $e) {
            if ($isAjax) { ob_clean(); header('Content-Type: application/json'); echo json_encode(['ok'=>false,'msg'=>'❌ Echo : '.$e->getMessage()]); exit; }
        }
        header("Location: ?id=$id&msg=echo_ok"); exit;
    }
}

$today = date('Y-m-d');

// ── Comptages + date du dernier enregistrement pour navigation ──
$s = $db->prepare("SELECT COUNT(*) FROM t_examen WHERE NPAT = ?"); $s->execute([$id]); $nbExamen = (int)$s->fetchColumn();
$s = $db->prepare("SELECT COUNT(*) FROM ecg WHERE CAST([N-PAT] AS INT) = ?"); $s->execute([$id]); $nbEcg = (int)$s->fetchColumn();
$s = $db->prepare("SELECT COUNT(*) FROM echo WHERE [N-PAT] = ?"); $s->execute([$id]); $nbEcho = (int)$s->fetchColumn();
$s = $db->prepare("SELECT TOP 1 CONVERT(varchar,DateExam,23) FROM t_examen WHERE NPAT = ? ORDER BY DateExam DESC"); $s->execute([$id]); $lastExamen = $s->fetchColumn() ?: '';
$s = $db->prepare("SELECT TOP 1 CONVERT(varchar,[Date ECG],23) FROM ecg WHERE CAST([N-PAT] AS INT) = ? ORDER BY [Date ECG] DESC"); $s->execute([$id]); $lastEcg = $s->fetchColumn() ?: '';
$s = $db->prepare("SELECT TOP 1 CONVERT(varchar,DATEchog,23) FROM echo WHERE [N-PAT] = ? ORDER BY DATEchog DESC"); $s->execute([$id]); $lastEcho = $s->fetchColumn() ?: '';

// ── 3 derniers bilans biologiques avec leurs résultats anormaux ──────────
$stmtBioNBC = $db->prepare("
    SELECT TOP 3 n_bilan,
           CONVERT(varchar(10), date_bilan, 103) AS date_fr
    FROM LE_BILAN
    WHERE id = ?
    ORDER BY date_bilan DESC
");
$stmtBioNBC->execute([$id]);
$bilansList3 = $stmtBioNBC->fetchAll();

// Pour chaque bilan : charger ses anormaux et toutes ses lignes
$bilansNBC = [];
foreach ($bilansList3 as $b) {
    $stmtTout = $db->prepare("
        SELECT c.analyse AS nom,
               c.rubrique,
               ISNULL(a.résultat,'') AS resultat
        FROM analyses a
        LEFT JOIN C_ANALYSE c ON c.[N°TypeAnalyse] = a.bilan
        WHERE a.N_bilan = ?
        ORDER BY c.rubrique, c.analyse
    ");
    $stmtTout->execute([$b['n_bilan']]);
    $lignes = $stmtTout->fetchAll();
    $bilansNBC[] = [
        'n_bilan' => $b['n_bilan'],
        'date_fr' => $b['date_fr'],
        'lignes'  => $lignes,
    ];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Bilan clinique — <?= htmlspecialchars($nom) ?></title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Arial, sans-serif; font-size: 12px; background: #f0f4f8; color: #222; }

.header {
    background: linear-gradient(135deg, #1a4a7a, #2e6da4);
    color: white; padding: 8px 16px;
    display: flex; align-items: center; gap: 12px;
}
.header h1 { font-size: 14px; }
.header .sub { font-size: 11px; opacity: 0.8; }
.btn-retour {
    margin-left: auto;
    background: rgba(255,255,255,0.2); color: white;
    border: none; border-radius: 4px; padding: 5px 12px;
    cursor: pointer; font-size: 11px; text-decoration: none;
}

.cols { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; padding: 10px; align-items: start; }

.col-card { background: white; border-radius: 6px; padding: 12px; box-shadow: 0 1px 4px rgba(0,0,0,0.1); }

.col-title {
    font-size: 12px; font-weight: bold; color: #1a4a7a;
    margin-bottom: 10px; padding-bottom: 6px;
    border-bottom: 2px solid #e0e8f0;
    display: flex; align-items: center; justify-content: space-between;
}

.msg { padding: 6px 10px; border-radius: 4px; margin-bottom: 10px;
       font-size: 11px; background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }

.sec { font-size: 10px; font-weight: bold; color: #888; text-transform: uppercase;
       letter-spacing: 0.5px; margin: 10px 0 6px; }

.champ { margin-bottom: 7px; }
.champ label { font-size: 10px; color: #888; display: block; margin-bottom: 2px; }
.champ input, .champ textarea, .champ select {
    width: 100%; padding: 4px 6px;
    border: 1px solid #ddd; border-radius: 3px;
    font-size: 11px; font-family: Arial, sans-serif;
}
.champ input:focus, .champ textarea:focus, .champ select:focus {
    outline: none; border-color: #2e6da4;
    box-shadow: 0 0 0 2px rgba(46,109,164,0.12);
}
.champ textarea { resize: vertical; min-height: 48px; }

.grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }

.date-enreg {
    display: flex; align-items: center; gap: 6px;
}
.date-enreg input[type=date] {
    border: 1px solid #ddd; border-radius: 3px;
    padding: 2px 5px; font-size: 11px; color: #1a4a7a;
}
/* Boutons icônes ✅ ✏️ 💾 — transparents, taille fixe, centrés */
.btn-preset, .btn-save {
    width: 22px; height: 22px; padding: 0;
    border: none; border-radius: 3px;
    background: transparent;
    font-size: 16px; line-height: 1;
    cursor: pointer;
    display: inline-flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.btn-preset:hover, .btn-save:hover { background: rgba(0,0,0,0.07); }
.btn-normal { color: #27ae60; }
.btn-anormal { color: #e67e22; }
.btn-save { color: #2e6da4; }


/* Champs réduits */
.champ textarea.court { min-height: 30px; height: 30px; }

/* Bouton exclusion concaténation */
.excl-wrap { display: flex; align-items: flex-start; gap: 4px; }
.excl-wrap textarea { flex: 1; }
.btn-excl {
    flex-shrink: 0; width: 20px; height: 20px; margin-top: 1px;
    border: 1px solid #ccc; border-radius: 3px; background: #f5f5f5;
    color: #888; font-size: 12px; font-weight: bold;
    cursor: pointer; line-height: 18px; text-align: center; padding: 0;
    transition: all 0.15s;
}
.btn-excl:hover { background: #e74c3c; color: white; border-color: #e74c3c; }
.btn-excl.exclu { background: #e74c3c; color: white; border-color: #c0392b; }
.champ.exclu-champ textarea { opacity: 0.4; text-decoration: line-through; }
.champ.exclu-champ label { opacity: 0.4; text-decoration: line-through; }
.champ.exclu-champ select,
.champ.exclu-champ input[type=text],
.champ.exclu-champ input[type=number] { opacity: 0.4; }
/* Label avec bouton excl inline */
.label-excl { display:flex; align-items:center; justify-content:space-between; margin-bottom:2px; }
.label-excl label { font-size:10px; color:#888; margin:0; }
</style>
</head>
<body>

<div class="header">
    <div>
        <div class="sub">🩺 Nouveau bilan clinique</div>
        <h1><?= htmlspecialchars($nom) ?> &nbsp;—&nbsp; N° <?= $id ?></h1>
    </div>
    <div style="display:flex;gap:6px;margin-left:auto;align-items:center;">
        <button type="button" onclick="enregistrerTout()"
            style="background:#f39c12;color:white;border:none;border-radius:4px;padding:5px 12px;cursor:pointer;font-size:11px;font-weight:bold;">
            💾 TOUT
        </button>
        <span id="msg_tout" style="font-size:11px;color:#2ecc71;font-weight:bold;display:none;"></span>
        <button type="button" onclick="restaurerTout()" title="Remettre toutes les options visibles et tout décocher"
            style="background:#7f8c8d;color:white;border:none;border-radius:4px;padding:5px 12px;cursor:pointer;font-size:11px;font-weight:bold;">
            ↺ Restaurer
        </button>
        <a href="print_rapport.php?id=<?= $id ?>" target="_blank"
           style="background:#27ae60;color:white;border:none;border-radius:4px;padding:5px 12px;cursor:pointer;font-size:11px;text-decoration:none;font-weight:bold;">
           📄 Rapport
        </a>
        <a href="index.php" class="btn-retour" style="background:#c0392b;margin-left:0;">🏠 Accueil</a>
        <a href="dossier.php?id=<?= $id ?>" class="btn-retour" style="margin-left:0;">← Retour dossier</a>
    </div>
</div>

<!-- ══ BARRE NAVIGATION GLOBALE ══ -->
<div style="background:#e8f0fa;border-bottom:2px solid #c5d8ed;padding:4px 12px;display:flex;align-items:center;gap:6px;">
    <span style="font-size:10px;font-weight:bold;color:#1a4a7a;white-space:nowrap;">🔀 Navigation globale</span>
    <div style="display:flex;align-items:center;gap:3px;margin-left:6px;">
        <button type="button" onclick="naviguerTout('last')"  title="Plus récent (tous)"
            style="background:white;color:#2e6da4;border:1px solid #c5d8ed;border-radius:3px;height:22px;min-width:24px;padding:0 4px;font-size:12px;font-weight:bold;cursor:pointer;">|◀</button>
        <button type="button" onclick="naviguerTout('next')"  title="Précédent (tous)"
            style="background:white;color:#2e6da4;border:1px solid #c5d8ed;border-radius:3px;height:22px;min-width:24px;padding:0 4px;font-size:12px;font-weight:bold;cursor:pointer;">◀</button>
        <span id="nav_global_label" style="font-size:11px;font-weight:bold;color:#1a4a7a;padding:0 8px;white-space:nowrap;">— nouveau —</span>
        <button type="button" onclick="naviguerTout('prev')"  title="Suivant (tous)"
            style="background:white;color:#2e6da4;border:1px solid #c5d8ed;border-radius:3px;height:22px;min-width:24px;padding:0 4px;font-size:12px;font-weight:bold;cursor:pointer;">▶</button>
        <button type="button" onclick="naviguerTout('first')" title="Plus ancien (tous)"
            style="background:white;color:#2e6da4;border:1px solid #c5d8ed;border-radius:3px;height:22px;min-width:24px;padding:0 4px;font-size:12px;font-weight:bold;cursor:pointer;">▶|</button>
        <button type="button" onclick="nouveauTout()"         title="Nouveau bilan (tous)"
            style="background:#27ae60;color:white;border:1px solid #27ae60;border-radius:3px;height:22px;padding:0 8px;font-size:11px;font-weight:bold;cursor:pointer;">▶*</button>
    </div>
    <span style="font-size:10px;color:#888;margin-left:4px;">← agit simultanément sur Examen · ECG · Echo</span>
</div>

<div class="cols">

<!-- ══════════════════════════════════════════════
     COLONNE 1 : EXAMEN CLINIQUE
══════════════════════════════════════════════ -->
<div class="col-card">
    <form id="form-examen">
    <input type="hidden" name="onglet" value="examen">
    <input type="hidden" name="ajax" value="1">
    <div class="col-title">
        <span style="font-size:12px;font-weight:bold;color:#1a4a7a;white-space:nowrap;">🩺 Examen clinique</span>
        <span style="flex:1;"></span>
        <input type="date" name="DateExam" value="<?= $today ?>" id="date_examen" style="border:1px solid #ddd;border-radius:3px;padding:2px 5px;font-size:11px;color:#1a4a7a;">
    </div>

   <div style="min-height:0;"><span id="msg_examen" style="font-size:11px;color:#27ae60;font-weight:bold;display:none;"></span></div>
      <div style="margin-bottom:2px;"><small id="lbl_exclu_examen" style="color:#e74c3c;font-weight:bold;font-size:9px;display:none;"></small></div>
    <div style="display:flex;align-items:center;gap:2px;background:#f0f4f8;border-radius:4px;padding:3px 5px;margin-bottom:8px;">
        <button type="button" onclick="naviguerBilan('examen','last')" title="Premier bilan" style="background:none;color:#2e6da4;border:1px solid #c5d8ed;border-radius:3px;height:20px;min-width:20px;padding:0 3px;font-size:11px;font-weight:bold;cursor:pointer;">|◀</button>
        <button type="button" onclick="naviguerBilan('examen','next')"  title="Précédent"    style="background:none;color:#2e6da4;border:1px solid #c5d8ed;border-radius:3px;height:20px;min-width:20px;padding:0 3px;font-size:11px;font-weight:bold;cursor:pointer;">◀</button>
        <span id="navdate_examen" style="flex:1;text-align:center;font-weight:bold;color:#1a4a7a;font-size:11px;">— nouveau —</span>
        <button type="button" onclick="naviguerBilan('examen','prev')"  title="Suivant"      style="background:none;color:#2e6da4;border:1px solid #c5d8ed;border-radius:3px;height:20px;min-width:20px;padding:0 3px;font-size:11px;font-weight:bold;cursor:pointer;">▶</button>
        <button type="button" onclick="naviguerBilan('examen','first')"  title="Dernier"      style="background:none;color:#2e6da4;border:1px solid #c5d8ed;border-radius:3px;height:20px;min-width:20px;padding:0 3px;font-size:11px;font-weight:bold;cursor:pointer;">▶|</button>
        <button type="button" onclick="nouveauBilan('examen')"          title="Nouveau"      style="background:#27ae60;color:white;border:1px solid #27ae60;border-radius:3px;height:20px;padding:0 6px;font-size:10px;font-weight:bold;cursor:pointer;">▶*</button>
    </div>

    <!-- ── Panel cases Examen ── -->
    <div id="panel_sympto" style="margin-bottom:6px;border:1px solid #b0c8e8;border-radius:5px;padding:6px 8px;background:#f5f9ff;">
        <div style="font-size:11px;font-weight:bold;color:#1a4a7a;margin-bottom:6px;">🩺 Examen — cochez pour générer le rapport Examen</div>

        <!-- Boutons Normal / Anormal -->
        <div style="display:flex;gap:6px;margin-bottom:6px;">
            <button type="button" id="btn_exam_normal"
                onclick="setExamenNormal()"
                style="flex:1;padding:5px 4px;border:2px solid #27ae60;border-radius:4px;background:#27ae60;color:white;font-size:11px;font-weight:bold;cursor:pointer;">
                ✅ Examen normal
            </button>
            <button type="button" id="btn_exam_anormal"
                onclick="setExamenAnormal()"
                style="flex:1;padding:5px 4px;border:2px solid #e67e22;border-radius:4px;background:white;color:#e67e22;font-size:11px;font-weight:bold;cursor:pointer;">
                ⚠️ Examen anormal
            </button>
        </div>
        <div style="margin-top:6px;margin-bottom:4px;">
            <button type="button" id="btn_generer_examen"
                onclick="if(document.getElementById('bloc_normal')&&document.getElementById('bloc_normal').style.display!=='none'){genererConclusionNormal();}else{genererConclusion();} document.getElementById('bloc_normal').style.display='none'; document.getElementById('sympto_cases').style.display='none'; document.getElementById('btn_generer_examen').style.display='none'; document.getElementById('lien_modifier_sympto').style.display='block'; enregistrerAjax('examen');"
                style="background:#1a4a7a;color:white;border:none;border-radius:3px;padding:3px 12px;font-size:11px;cursor:pointer;">▶ Générer &amp; 💾</button>
            <button type="button" id="lien_modifier_sympto" onclick="modifierExamen()" style="display:none;background:#e67e22;color:white;border:none;border-radius:3px;padding:3px 10px;font-size:11px;cursor:pointer;">↺ Modifier</button>
        </div>

        <!-- ══ BLOC NORMAL (affiché par ✅) ══ -->
        <div id="bloc_normal" style="display:none;">
            <div style="font-size:11px;font-weight:bold;color:#27ae60;margin-bottom:3px;">Examen clinique normal</div>
            <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" id="n_sympto" checked value="absence de symptomatologie fonctionnelle orientant sur la sphère cardio-pulmonaire"> Absence de symptomatologie fonctionnelle orientant sur la sphère cardio-pulmonaire</label>
            <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" id="n_auscult" checked value="auscultation cardiaque normale"> Auscultation cardiaque normale</label>
            <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" id="n_oedemes" checked value="absence d'œdèmes des membres inférieurs"> Absence d'œdèmes des membres inférieurs</label>
            <label style="font-size:11px;display:block;margin-bottom:4px;"><input type="checkbox" id="n_vasc" checked value="examen vasculaire normal"> Examen vasculaire normal</label>
        </div>

        <!-- ══ BLOC ANORMAL (affiché par ⚠️) ══ -->
        <div id="sympto_cases" style="display:none;">

            <div style="font-size:11px;font-weight:bold;color:#e67e22;margin-bottom:4px;">Examen clinique anormal</div>

            <!-- ── 🫀 Examen cardiaque ── -->
            <div class="exam-section" onclick="toggleEcgSection('sub_exam_card','arr_exam_card')">
                <span>🫀 Examen cardiaque</span><span id="arr_exam_card">▶</span>
            </div>
            <div id="sub_exam_card" style="display:block;">

            <!-- Angor — EXCLUSIF -->
            <label style="font-size:11px;display:block;margin-bottom:2px;cursor:pointer;"><input type="checkbox" class="sympto-parent" data-target="sub_angor" onchange="toggleSub(this)"> Symptomatologie douloureuse (angor)</label>
            <div id="sub_angor" style="display:none;margin-left:14px;margin-bottom:4px;">
                <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="sx-excl" onchange="exclusifVisible(this,'sub_angor')" value="douleur thoracique (angor)"> Douleur thoracique (angor)</label>
                <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="sx-excl" onchange="exclusifVisible(this,'sub_angor')" value="angor d'effort"> Angor d'effort</label>
                <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="sx-excl" onchange="exclusifVisible(this,'sub_angor')" value="angor crescendo"> Angor crescendo</label>
            </div>

            <!-- Dyspnée — EXCLUSIF -->
            <label style="font-size:11px;display:block;margin-bottom:2px;cursor:pointer;"><input type="checkbox" class="sympto-parent" data-target="sub_dyspnee" onchange="toggleSub(this)"> Symptomatologie dyspnéique</label>
            <div id="sub_dyspnee" style="display:none;margin-left:14px;margin-bottom:4px;">
                <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="sx-excl" onchange="exclusifVisible(this,'sub_dyspnee')" value="dyspnée stade I NYHA"> Dyspnée stade I NYHA</label>
                <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="sx-excl" onchange="exclusifVisible(this,'sub_dyspnee')" value="dyspnée d'effort stade II NYHA"> Dyspnée d'effort stade II NYHA</label>
                <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="sx-excl" onchange="exclusifVisible(this,'sub_dyspnee')" value="dyspnée d'effort stade III NYHA"> Dyspnée d'effort stade III NYHA</label>
                <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="sx-excl" onchange="exclusifVisible(this,'sub_dyspnee')" value="suspicion d'embolie pulmonaire"> Suspicion d'embolie pulmonaire</label>
            </div>

            <!-- Signes IVD — MULTIPLE + bouton OK -->
            <label style="font-size:11px;display:block;margin-bottom:2px;cursor:pointer;"><input type="checkbox" class="sympto-parent" data-target="sub_ivd" onchange="toggleSub(this)"> Signes d'IVD</label>
            <div id="sub_ivd" style="display:none;margin-left:14px;margin-bottom:4px;">
                <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="sx-ivd" value="hépatalgies d'effort"> Hépatalgies d'effort</label>
                <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="sx-ivd" value="hépatomégalie"> Hépatomégalie</label>
                <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="sx-ivd" value="hypochondre droit douloureux à la palpation"> Hypochondre droit douloureux à la palpation</label>
                <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="sx-ivd" value="œdèmes des MI prenant le godet"> Œdèmes des MI prenant le godet</label>
                <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="sx-ivd" value="turgescence des veines jugulaires"> Turgescence des veines jugulaires</label>
                <button type="button" onclick="appliquerMultiple('sub_ivd')" style="margin-top:3px;background:#1a4a7a;color:white;border:none;border-radius:3px;padding:2px 10px;font-size:10px;cursor:pointer;">✓ OK</button>
            </div>

            <!-- Rythmique — EXCLUSIF -->
            <label style="font-size:11px;display:block;margin-bottom:2px;cursor:pointer;"><input type="checkbox" class="sympto-parent" data-target="sub_rythme" onchange="toggleSub(this)"> Symptomatologie rythmique</label>
            <div id="sub_rythme" style="display:none;margin-left:14px;margin-bottom:4px;">
                <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="sx-excl" onchange="exclusifVisible(this,'sub_rythme')" value="palpitations"> Palpitations</label>
                <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="sx-excl" onchange="exclusifVisible(this,'sub_rythme')" value="tachycardie"> Tachycardie</label>
                <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="sx-excl" onchange="exclusifVisible(this,'sub_rythme')" value="bradycardie"> Bradycardie</label>
            </div>

            </div><!-- fin sub_exam_card -->
            <!-- ── 🩸 Examen vasculaire ── -->
            <div class="exam-section" onclick="toggleEcgSection('sub_exam_vasc','arr_exam_vasc')">
                <span>🩸 Examen vasculaire</span><span id="arr_exam_vasc">▶</span>
            </div>
            <div id="sub_exam_vasc" style="display:block;">

            <!-- Artéritique — EXCLUSIF -->
            <label style="font-size:11px;display:block;margin-bottom:2px;cursor:pointer;"><input type="checkbox" class="sympto-parent" data-target="sub_arterite" onchange="toggleSub(this)"> Symptomatologie artéritique des MI</label>
            <div id="sub_arterite" style="display:none;margin-left:14px;margin-bottom:4px;">
                <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="sx-excl" onchange="exclusifVisible(this,'sub_arterite')" value="artérite stade I"> Artérite stade I</label>
                <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="sx-excl" onchange="exclusifVisible(this,'sub_arterite')" value="artérite stade II"> Artérite stade II</label>
                <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="sx-excl" onchange="exclusifVisible(this,'sub_arterite')" value="artérite stade VI"> Artérite stade VI</label>
                <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="sx-excl" onchange="exclusifVisible(this,'sub_arterite')" value="gangrène"> Gangrène</label>
                <div style="display:flex;align-items:center;gap:4px;margin-top:2px;">
                    <span style="font-size:11px;">Autres :</span>
                    <input type="text" id="arterite_autres" placeholder="préciser..." style="flex:1;border:1px solid #ccc;border-radius:3px;padding:2px 5px;font-size:11px;">
                </div>
            </div>

            <!-- Phlébitique — MULTIPLE + bouton OK -->
            <label style="font-size:11px;display:block;margin-bottom:2px;cursor:pointer;"><input type="checkbox" class="sympto-parent" data-target="sub_phlebite" onchange="toggleSub(this)"> Symptomatologie phlébitique</label>
            <div id="sub_phlebite" style="display:none;margin-left:14px;margin-bottom:4px;">
                <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="sx-phleb" value="varices des MI"> Varices des MI</label>
                <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="sx-phleb" value="phlébite des MI"> Phlébite des MI</label>
                <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="sx-phleb" value="trouble trophique des MI"> Trouble trophique des MI</label>
                <div style="display:flex;align-items:center;gap:4px;margin-top:2px;">
                    <span style="font-size:11px;">Autres :</span>
                    <input type="text" id="phlebite_autres" placeholder="préciser..." style="flex:1;border:1px solid #ccc;border-radius:3px;padding:2px 5px;font-size:11px;">
                </div>
                <button type="button" onclick="appliquerMultiple('sub_phlebite')" style="margin-top:3px;background:#1a4a7a;color:white;border:none;border-radius:3px;padding:2px 10px;font-size:10px;cursor:pointer;">✓ OK</button>
            </div>

            </div><!-- fin sub_exam_vasc -->

        </div><!-- fin sympto_cases -->

    </div><!-- fin panel_sympto -->

    <div class="sec">Mesures</div>
    <style>
        /* Supprimer les flèches spinner sur les champs number de la colonne Examen */
        #inp_TAS::-webkit-inner-spin-button, #inp_TAS::-webkit-outer-spin-button,
        #inp_TAD::-webkit-inner-spin-button, #inp_TAD::-webkit-outer-spin-button,
        #inp_FC::-webkit-inner-spin-button,  #inp_FC::-webkit-outer-spin-button,
        #inp_POIDS::-webkit-inner-spin-button,#inp_POIDS::-webkit-outer-spin-button
        { -webkit-appearance:none; margin:0; }
        #inp_TAS,#inp_TAD,#inp_FC,#inp_POIDS { -moz-appearance:textfield; }
    </style>
    <div style="display:flex;align-items:center;gap:5px;flex-wrap:wrap;margin-bottom:8px;">
        <label style="font-size:10px;color:#888;">TAS <span style="color:red;">*</span></label>
        <input type="number" id="inp_TAS" name="TAS" placeholder="TAS" required style="width:50px;padding:3px 5px;border:1px solid #e67e22;border-radius:3px;font-size:11px;" oninput="majApercuExamen()">
        <label style="font-size:10px;color:#888;">TAD <span style="color:red;">*</span></label>
        <input type="number" id="inp_TAD" name="TAD" placeholder="TAD" required style="width:50px;padding:3px 5px;border:1px solid #e67e22;border-radius:3px;font-size:11px;" oninput="majApercuExamen()">
        <label style="font-size:10px;color:#888;">FC <span style="color:red;">*</span></label>
        <input type="number" id="inp_FC"  name="FC"  placeholder="FC"  required style="width:50px;padding:3px 5px;border:1px solid #e67e22;border-radius:3px;font-size:11px;" oninput="majApercuExamen()">
        <label style="font-size:10px;color:#888;">Poids</label>
        <input type="number" id="inp_POIDS" step="0.1" name="POIDS" placeholder="kg" style="width:50px;padding:3px 5px;border:1px solid #ddd;border-radius:3px;font-size:11px;">
        <label style="font-size:10px;color:#888;">Taille</label>
        <input type="text" name="TAILLE" placeholder="cm" readonly tabindex="-1" style="width:50px;padding:3px 5px;border:1px solid #ddd;border-radius:3px;font-size:11px;background:#f5f5f5;color:#aaa;cursor:not-allowed;">
    </div>

    <div class="champ" id="wrap_REMARQUE">
        <div class="label-excl">
            <label>Remarque</label>
            <button type="button" class="btn-excl" onclick="toggleExcl('REMARQUE')" title="Exclure du rapport">−</button>
        </div>
        <textarea name="REMARQUE" class="court" oninput="majApercuExamen()"></textarea>
    </div>
    <div class="champ" id="wrap_Conclusion">
        <div class="label-excl">
            <label>Conclusion</label>
            <button type="button" class="btn-excl" onclick="toggleExcl('Conclusion')" title="Exclure du rapport">−</button>
        </div>
        <textarea name="Conclusion" class="court" oninput="majApercuExamen()"></textarea>
    </div>

    <!-- Champs cachés pour l'exclusion de concaténation -->
    <input type="hidden" id="excl_S_Fonctionnels"     name="excl_S_Fonctionnels">
    <input type="hidden" id="excl_Auscult_Cardiaque"  name="excl_Auscult_Cardiaque">
    <input type="hidden" id="excl_Auscult_Pulmonaire" name="excl_Auscult_Pulmonaire">
    <input type="hidden" id="excl_Examen_Vasculaire"  name="excl_Examen_Vasculaire">
    <input type="hidden" id="excl_Signes_IVG"         name="excl_Signes_IVG">
    <input type="hidden" id="excl_Signes_IVD"         name="excl_Signes_IVD">
    <input type="hidden" id="excl_Autres_Symptomes"   name="excl_Autres_Symptomes">
    <input type="hidden" id="excl_Conclusion"          name="excl_Conclusion">
    <input type="hidden" id="excl_REMARQUE"            name="excl_REMARQUE">

    <!-- Zone prévisualisation concaténation Examen -->
    <div class="champ" style="margin-top:6px;">
        <label style="font-size:10px;color:#2e6da4;font-weight:bold;">👁 Aperçu rapport Examen</label>
        <textarea id="apercu_examen" name="CMLM_EXAMEN"
            style="min-height:45px;background:#f0f7ff;border:1px solid #2e6da4;font-size:11px;color:#1a4a7a;resize:none;overflow:hidden;width:100%;padding:4px 6px;border-radius:3px;font-family:Arial,sans-serif;pointer-events:none;"></textarea>
    </div>

    <div class="sec" style="display:flex;align-items:center;justify-content:space-between;">
        <span>Au total — Conduite à tenir</span>
        <button type="button" onclick="toggleConduitePanel()" title="Choisir dans la liste"
            style="background:#2e6da4;color:white;border:none;border-radius:3px;padding:2px 8px;font-size:11px;cursor:pointer;">📋</button>
    </div>

    <!-- Panneau Conduite à tenir (caché par défaut) -->
    <div id="panel_conduite" style="display:none;border:1px solid #b0c8e8;border-radius:4px;padding:6px 8px;background:#f5f9ff;margin-bottom:4px;max-height:340px;overflow-y:auto;">

        <!-- ══ SECTION 1 : Maladie chronique (ex-CMLM) ══ -->
        <div class="cat-section">
            <div class="cat-header" onclick="toggleCatSection('sec_maladie','arr_maladie')" style="cursor:pointer;font-size:11px;font-weight:bold;color:#1a4a7a;background:#ddeeff;padding:3px 5px;border-radius:3px;margin-bottom:3px;user-select:none;">
                <span id="arr_maladie">▶</span> 📋 Maladie chronique
            </div>
            <div id="sec_maladie" style="display:none;padding-left:10px;">
                <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="cat-item" value="Son état nécessite une prise en charge au long cours"> Son état nécessite</label>

                <div style="font-size:10px;color:#555;font-weight:bold;margin:3px 0 1px;">Traitement médical</div>
                <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="cat-item" value="Un traitement médical au long cours"> Un traitement médical au long cours</label>
                <label style="font-size:11px;display:block;margin-bottom:2px;padding-left:12px;"><input type="checkbox" class="cat-item" value="Actuellement sous traitement"> Actuellement sous :</label>
                <label style="font-size:11px;display:block;margin-bottom:2px;padding-left:12px;"><input type="checkbox" class="cat-item" value="Aucun médicament ajouté"> Aucun médicament ajouté — cliquez sur [ALD]</label>
                <div style="display:flex;align-items:center;gap:4px;margin-bottom:3px;padding-left:12px;">
                    <span style="font-size:10px;color:#555;">Traitement retenu :</span>
                    <input type="text" id="cat_trait_retenu" placeholder="préciser..." style="flex:1;border:1px solid #ccc;border-radius:3px;padding:2px 5px;font-size:10px;" oninput="majConduiteTextarea()">
                </div>

                <div style="font-size:10px;color:#555;font-weight:bold;margin:3px 0 1px;">Une surveillance</div>
                <label style="font-size:11px;display:block;margin-bottom:2px;padding-left:8px;"><input type="checkbox" class="cat-item" value="Surveillance clinique (trimestrielle)"> Clinique (trimestrielle)</label>
                <label style="font-size:11px;display:block;margin-bottom:2px;padding-left:8px;"><input type="checkbox" class="cat-item" value="Surveillance électrocardiographique trimestrielle"> Électrocardiographique trimestrielle</label>
                <label style="font-size:11px;display:block;margin-bottom:2px;padding-left:8px;"><input type="checkbox" class="cat-item" value="Surveillance échographique annuelle"> Échographique annuelle</label>
                <div style="display:flex;align-items:center;gap:4px;margin-bottom:3px;padding-left:8px;">
                    <label style="font-size:11px;display:flex;align-items:center;gap:4px;"><input type="checkbox" class="cat-item" value="Surveillance biologique"> Biologique :</label>
                    <input type="text" id="cat_bio_detail" placeholder="préciser..." style="flex:1;border:1px solid #ccc;border-radius:3px;padding:2px 5px;font-size:10px;" oninput="majConduiteTextarea()">
                </div>

                <div style="font-size:10px;color:#555;font-weight:bold;margin:3px 0 1px;">Avis spécialiste</div>
                <label style="font-size:11px;display:block;margin-bottom:2px;padding-left:8px;"><input type="checkbox" class="cat-item" value="Avis endocrinologue"> Endocrinologue</label>
                <label style="font-size:11px;display:block;margin-bottom:2px;padding-left:8px;"><input type="checkbox" class="cat-item" value="Avis neurologue"> Neurologue</label>
                <label style="font-size:11px;display:block;margin-bottom:2px;padding-left:8px;"><input type="checkbox" class="cat-item" value="Avis néphrologue"> Néphrologue</label>
                <label style="font-size:11px;display:block;margin-bottom:2px;padding-left:8px;"><input type="checkbox" class="cat-item" value="Avis gastro-entérologue"> Gastro-entérologue</label>
                <label style="font-size:11px;display:block;margin-bottom:2px;padding-left:8px;"><input type="checkbox" class="cat-item" value="Avis pneumologue"> Pneumologue</label>
                <label style="font-size:11px;display:block;margin-bottom:2px;padding-left:8px;"><input type="checkbox" class="cat-item" value="Avis chirurgien cardiaque"> Chirurgien cardiaque</label>
                <div style="display:flex;align-items:center;gap:4px;margin-bottom:3px;padding-left:8px;">
                    <span style="font-size:10px;color:#555;">Autre :</span>
                    <input type="text" id="cat_avis_autre" placeholder="préciser..." style="flex:1;border:1px solid #ccc;border-radius:3px;padding:2px 5px;font-size:10px;" oninput="majConduiteTextarea()">
                </div>
            </div>
        </div>

        <!-- ══ SECTION 2 : Correspondance (ex-Lettre) ══ -->
        <div class="cat-section" style="margin-top:3px;">
            <div class="cat-header" onclick="toggleCatSection('sec_corresp','arr_corresp')" style="cursor:pointer;font-size:11px;font-weight:bold;color:#1a4a7a;background:#ddeeff;padding:3px 5px;border-radius:3px;margin-bottom:3px;user-select:none;">
                <span id="arr_corresp">▶</span> ✉️ Correspondance
            </div>
            <div id="sec_corresp" style="display:none;padding-left:10px;">
                <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="cat-item" value="Je vous adresse ce patient pour avis et prise en charge"> Je vous adresse ce patient pour avis et prise en charge</label>
                <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="cat-item" value="Avis chirurgical"> Avis chirurgical</label>
                <div style="display:flex;align-items:center;gap:4px;margin-bottom:3px;">
                    <span style="font-size:10px;color:#555;">Autre :</span>
                    <input type="text" id="cat_corresp_autre" placeholder="préciser..." style="flex:1;border:1px solid #ccc;border-radius:3px;padding:2px 5px;font-size:10px;" oninput="majConduiteTextarea()">
                </div>
            </div>
        </div>

        <!-- ══ SECTION 3 : Compte rendu ══ -->
        <div class="cat-section" style="margin-top:3px;">
            <div class="cat-header" onclick="toggleCatSection('sec_cr','arr_cr')" style="cursor:pointer;font-size:11px;font-weight:bold;color:#1a4a7a;background:#ddeeff;padding:3px 5px;border-radius:3px;margin-bottom:3px;user-select:none;">
                <span id="arr_cr">▶</span> 📋 Compte rendu
            </div>
            <div id="sec_cr" style="display:none;padding-left:10px;">
                <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="cat-item" value="Examen cardio-vasculaire normal"> Examen cardio-vasculaire normal</label>
                <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="cat-item" value="Absence de contre-indication cardiaque à la chirurgie"> Absence de contre-indication cardiaque à la chirurgie</label>

                <div style="font-size:10px;color:#555;font-weight:bold;margin:3px 0 1px;">Adaptation du traitement (voir protocole)</div>
                <label style="font-size:11px;display:block;margin-bottom:2px;padding-left:8px;"><input type="checkbox" class="cat-item" value="Patient sous antiagrégants plaquettaires (voir protocole)"> Patient sous antiagrégants plaquettaires</label>
                <label style="font-size:11px;display:block;margin-bottom:2px;padding-left:8px;"><input type="checkbox" class="cat-item" value="Patient sous AVK (voir protocole)"> Patient sous AVK (voir protocole)</label>
                <label style="font-size:11px;display:block;margin-bottom:2px;padding-left:8px;"><input type="checkbox" class="cat-item" value="Patient sous anticoagulants directs"> Patient sous anticoagulants directs</label>

                <div style="font-size:10px;color:#555;font-weight:bold;margin:3px 0 1px;">Une surveillance</div>
                <label style="font-size:11px;display:block;margin-bottom:2px;padding-left:8px;"><input type="checkbox" class="cat-item" value="Surveillance clinique (trimestrielle) — CR"> Clinique (trimestrielle)</label>
                <label style="font-size:11px;display:block;margin-bottom:2px;padding-left:8px;"><input type="checkbox" class="cat-item" value="Surveillance électrocardiographique trimestrielle — CR"> Électrocardiographique trimestrielle</label>
                <label style="font-size:11px;display:block;margin-bottom:2px;padding-left:8px;"><input type="checkbox" class="cat-item" value="Surveillance échographique annuelle — CR"> Échographique annuelle</label>
                <div style="display:flex;align-items:center;gap:4px;margin-bottom:3px;padding-left:8px;">
                    <label style="font-size:11px;display:flex;align-items:center;gap:4px;"><input type="checkbox" class="cat-item" value="Surveillance biologique — CR"> Biologique :</label>
                    <input type="text" id="cat_cr_bio" placeholder="préciser..." style="flex:1;border:1px solid #ccc;border-radius:3px;padding:2px 5px;font-size:10px;" oninput="majConduiteTextarea()">
                </div>
            </div>
        </div>

        <!-- ══ SECTION 4 : Aptitude physique ══ -->
        <div class="cat-section" style="margin-top:3px;">
            <div class="cat-header" onclick="toggleCatSection('sec_aptitude','arr_aptitude')" style="cursor:pointer;font-size:11px;font-weight:bold;color:#1a4a7a;background:#ddeeff;padding:3px 5px;border-radius:3px;margin-bottom:3px;user-select:none;">
                <span id="arr_aptitude">▶</span> 🏅 Aptitude physique
            </div>
            <div id="sec_aptitude" style="display:none;padding-left:10px;">
                <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="cat-item" value="Examen cardio-vasculaire normal, apte à la pratique sportive"> Examen cardio-vasculaire normal</label>
                <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="cat-item" value="Apte à l'emploi sollicité"> Apte à l'emploi sollicité</label>
                <div style="display:flex;align-items:center;gap:4px;margin-bottom:3px;">
                    <span style="font-size:10px;color:#555;">Autre :</span>
                    <input type="text" id="cat_apt_autre" placeholder="préciser..." style="flex:1;border:1px solid #ccc;border-radius:3px;padding:2px 5px;font-size:10px;" oninput="majConduiteTextarea()">
                </div>
            </div>
        </div>

        <!-- ══ Autres libres ══ -->
        <div style="display:flex;align-items:center;gap:4px;margin-top:5px;">
            <span style="font-size:11px;">Autres :</span>
            <input type="text" id="cat_autres" placeholder="préciser..." style="flex:1;border:1px solid #ccc;border-radius:3px;padding:2px 5px;font-size:11px;" oninput="majConduiteTextarea()">
        </div>
        <div style="text-align:right;margin-top:6px;">
            <button type="button"
                onclick="majConduiteTextarea(); document.getElementById('panel_conduite').style.display='none'; enregistrerAjax('examen');"
                style="background:#27ae60;color:white;border:none;border-radius:4px;padding:3px 10px;font-size:11px;font-weight:bold;cursor:pointer;">&#9654; Générer &amp; 💾</button>
        </div>
    </div>

    <div class="champ">
        <textarea name="Conduite_ATenir" id="conduite_textarea" style="min-height:70px;" placeholder="Cliquez 📋 pour choisir, ou saisie directe…"></textarea>
    </div>
    </form>


    <!-- ══ 🧪 Biologie ══ -->
    <div style="margin-top:8px;padding-top:6px;border-top:2px solid #e0e0e0;">
    <div class="col-title">
        <span style="font-size:12px;font-weight:bold;color:#1a4a7a;white-space:nowrap;">🧪 Biologie</span>
        <a href="biologie.php?id=<?= $id ?>" target="_blank"
           style="background:#e67e22;color:white;border:none;border-radius:3px;padding:2px 8px;font-size:10px;font-weight:bold;text-decoration:none;white-space:nowrap;">✚ Saisir</a>
    </div>

    <?php if (empty($bilansNBC)): ?>
        <p style="color:#999;font-size:11px;">Aucun bilan enregistré</p>
    <?php else: ?>

    <!-- Historique compact : date + anormaux en surbrillance -->
    <div style="margin-bottom:10px;">
        <div class="sec" style="margin-top:0;">Historique (3 derniers)</div>
        <?php foreach ($bilansNBC as $bNBC):
            $anormaux = array_filter($bNBC['lignes'], fn($l) => trim($l['resultat']) !== '' && strtoupper(trim($l['resultat'])) !== 'N');
        ?>
        <div style="padding:4px 0;border-bottom:1px solid #f0f0f0;">
            <span style="font-size:10px;font-weight:bold;color:#1a4a7a;"><?= htmlspecialchars($bNBC['date_fr']) ?></span>
            <?php if (!empty($anormaux)): ?>
            <span style="font-size:10px;color:#555;margin-left:4px;">:</span>
            <?php foreach ($anormaux as $an): ?>
            <span style="display:inline-block;background:#fdecea;color:#c0392b;font-weight:bold;font-size:10px;border-radius:3px;padding:0 4px;margin:1px 2px;">
                <?= htmlspecialchars($an['nom']) ?> <?= htmlspecialchars($an['resultat']) ?>
            </span>
            <?php endforeach; ?>
            <?php else: ?>
            <span style="font-size:10px;color:#27ae60;margin-left:4px;">— Normal</span>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Détail du bilan sélectionné (navigation) -->
    <div class="sec">Détail bilan</div>

    <!-- Barre de navigation bilans -->
    <div style="display:flex;align-items:center;gap:2px;background:#f0f4f8;border-radius:4px;padding:3px 5px;margin-bottom:8px;">
        <button type="button" onclick="bioNavNBC('first')" title="Plus récent"  style="background:none;color:#2e6da4;border:1px solid #c5d8ed;border-radius:3px;height:20px;min-width:20px;padding:0 3px;font-size:11px;font-weight:bold;cursor:pointer;">|◀</button>
        <button type="button" onclick="bioNavNBC('prev')"  title="Précédent"   style="background:none;color:#2e6da4;border:1px solid #c5d8ed;border-radius:3px;height:20px;min-width:20px;padding:0 3px;font-size:11px;font-weight:bold;cursor:pointer;">◀</button>
        <span id="bio-nbc-navdate" style="flex:1;text-align:center;font-weight:bold;color:#1a4a7a;font-size:11px;">
            <?= $bilansNBC ? htmlspecialchars($bilansNBC[0]['date_fr']) : '—' ?>
        </span>
        <button type="button" onclick="bioNavNBC('next')"  title="Suivant"     style="background:none;color:#2e6da4;border:1px solid #c5d8ed;border-radius:3px;height:20px;min-width:20px;padding:0 3px;font-size:11px;font-weight:bold;cursor:pointer;">▶</button>
        <button type="button" onclick="bioNavNBC('last')"  title="Plus ancien" style="background:none;color:#2e6da4;border:1px solid #c5d8ed;border-radius:3px;height:20px;min-width:20px;padding:0 3px;font-size:11px;font-weight:bold;cursor:pointer;">▶|</button>
    </div>

    <!-- Zone résultats du bilan sélectionné -->
    <div id="bio-nbc-resultats" style="font-size:11px;display:block;">
        <?php if ($bilansNBC): ?>
        <?php foreach ($bilansNBC[0]['lignes'] as $lig):
            $v = trim($lig['resultat']);
            $an = ($v !== '' && strtoupper($v) !== 'N');
        ?>
        <div style="display:flex;justify-content:space-between;padding:1px 0;border-bottom:1px solid #f8f8f8;">
            <span style="color:<?= $an ? '#c0392b' : '#aaa' ?>;font-weight:<?= $an ? 'bold' : 'normal' ?>;font-size:<?= $an ? '11px' : '10px' ?>;">
                <?= htmlspecialchars($lig['nom']) ?>
            </span>
            <span style="color:<?= $an ? '#c0392b' : '#bbb' ?>;font-weight:<?= $an ? 'bold' : 'normal' ?>;font-size:<?= $an ? '11px' : '10px' ?>;margin-left:6px;white-space:nowrap;">
                <?= $v !== '' ? htmlspecialchars($v) : '—' ?>
            </span>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php endif; ?>
    </div>
</div>

<!-- ══════════════════════════════════════════════
     COLONNE 2 : ECG
══════════════════════════════════════════════ -->
<div class="col-card">
    <form id="form-ecg">
    <input type="hidden" name="onglet" value="ecg">
    <input type="hidden" name="ajax" value="1">
    <div class="col-title">
        <span style="font-size:12px;font-weight:bold;color:#1a4a7a;white-space:nowrap;">⚡ ECG</span>
        <span style="flex:1;"></span>
        <input type="date" name="Date_ECG" value="<?= $today ?>" id="date_ecg" style="border:1px solid #ddd;border-radius:3px;padding:2px 5px;font-size:11px;color:#1a4a7a;">
    </div>
    <div style="min-height:0;"><span id="msg_ecg" style="font-size:11px;color:#27ae60;font-weight:bold;display:none;"></span></div>
    <div style="margin-bottom:2px;"><small id="lbl_exclu_ecg" style="color:#e74c3c;font-weight:bold;font-size:9px;display:none;"></small></div>
    <div style="display:flex;align-items:center;gap:2px;background:#f0f4f8;border-radius:4px;padding:3px 5px;margin-bottom:8px;">
        <button type="button" onclick="naviguerBilan('ecg','last')" title="Premier bilan" style="background:none;color:#2e6da4;border:1px solid #c5d8ed;border-radius:3px;height:20px;min-width:20px;padding:0 3px;font-size:11px;font-weight:bold;cursor:pointer;">|◀</button>
        <button type="button" onclick="naviguerBilan('ecg','next')"  title="Précédent"    style="background:none;color:#2e6da4;border:1px solid #c5d8ed;border-radius:3px;height:20px;min-width:20px;padding:0 3px;font-size:11px;font-weight:bold;cursor:pointer;">◀</button>
        <span id="navdate_ecg" style="flex:1;text-align:center;font-weight:bold;color:#1a4a7a;font-size:11px;">— nouveau —</span>
        <button type="button" onclick="naviguerBilan('ecg','prev')"  title="Suivant"      style="background:none;color:#2e6da4;border:1px solid #c5d8ed;border-radius:3px;height:20px;min-width:20px;padding:0 3px;font-size:11px;font-weight:bold;cursor:pointer;">▶</button>
        <button type="button" onclick="naviguerBilan('ecg','first')"  title="Dernier"      style="background:none;color:#2e6da4;border:1px solid #c5d8ed;border-radius:3px;height:20px;min-width:20px;padding:0 3px;font-size:11px;font-weight:bold;cursor:pointer;">▶|</button>
        <button type="button" onclick="nouveauBilan('ecg')"          title="Nouveau"      style="background:#27ae60;color:white;border:1px solid #27ae60;border-radius:3px;height:20px;padding:0 6px;font-size:10px;font-weight:bold;cursor:pointer;">▶*</button>
    </div>
    <!-- ── Cases à cocher ECG (Normal / Anormal) ── -->
    <div id="panel_ecg_cases" style="margin-bottom:8px;border:1px solid #b0c8e8;border-radius:5px;padding:6px 8px;background:#f5f9ff;">
        <div style="font-size:11px;font-weight:bold;color:#1a4a7a;margin-bottom:6px;">📈 ECG — cochez pour générer le rapport ECG</div>
        <div style="display:flex;gap:6px;margin-bottom:4px;">
            <button type="button" id="btn_ecg_normal"
                onclick="setEcgGlobal('normal')"
                style="flex:1;padding:5px 4px;border:2px solid #27ae60;border-radius:4px;background:#27ae60;color:white;font-size:11px;font-weight:bold;cursor:pointer;">
                ✅ ECG normal
            </button>
            <button type="button" id="btn_ecg_anormal"
                onclick="setEcgGlobal('anormal')"
                style="flex:1;padding:5px 4px;border:2px solid #e67e22;border-radius:4px;background:white;color:#e67e22;font-size:11px;font-weight:bold;cursor:pointer;">
                ⚠️ ECG anormal
            </button>
        </div>
        <div style="margin-top:6px;margin-bottom:4px;">
            <button type="button" id="btn_generer_ecg"
                onclick="genererRapportECG(); document.getElementById('ecg_normal_detail').style.display='none'; document.getElementById('ecg_detail').style.display='none'; document.getElementById('btn_generer_ecg').style.display='none'; document.getElementById('lien_modifier_ecg').style.display='block'; enregistrerAjax('ecg');"
                style="background:#1a4a7a;color:white;border:none;border-radius:3px;padding:3px 12px;font-size:11px;cursor:pointer;">▶ Générer &amp; 💾</button>
            <button type="button" id="lien_modifier_ecg" onclick="modifierECG()" style="display:none;background:#e67e22;color:white;border:none;border-radius:3px;padding:3px 10px;font-size:11px;cursor:pointer;">↺ Modifier</button>
        </div>
        <!-- champ radio caché pour compatibilité avec le reste du JS -->
        <input type="radio" name="ecg_global" value="normal"  id="ecg_r_normal"  style="display:none;" checked onchange="toggleECGAnormal(false)">
        <input type="radio" name="ecg_global" value="anormal" id="ecg_r_anormal" style="display:none;" onchange="toggleECGAnormal(true)">
        <!-- Cases ECG Normal (visible quand ECG normal coché) -->
        <div id="ecg_normal_detail" style="display:none;margin-top:4px;">
            <label style="font-size:11px;display:block;"><input type="checkbox" class="ecg-normal-cb" id="ecgn_rythme" value="rythme sinusal, absence de trouble de rythme" checked> Rythme sinusal, absence de trouble de rythme</label>
            <label style="font-size:11px;display:block;"><input type="checkbox" class="ecg-normal-cb" id="ecgn_cond_av" value="conduction auriculo-ventriculaire normale" checked> Conduction auriculo-ventriculaire normale</label>
            <label style="font-size:11px;display:block;"><input type="checkbox" class="ecg-normal-cb" id="ecgn_cond_iv" value="conduction intra-ventriculaire normale" checked> Conduction intra-ventriculaire normale</label>
            <label style="font-size:11px;display:block;"><input type="checkbox" class="ecg-normal-cb" id="ecgn_repol" value="repolarisation normale" checked> Repolarisation normale</label>
            <label style="font-size:11px;display:block;"><input type="checkbox" class="ecg-normal-cb" id="ecgn_ondeq" value="absence d'ondes Q de nécrose" checked> Absence d'ondes Q de nécrose</label>
        </div>

        <div id="ecg_detail" style="display:none;">

            <!-- Trouble de rythme -->
            <div class="ecg-section" onclick="toggleEcgSection('sub_ecg_rythme','arr_ecg_rythme')">
                <label style="display:flex;align-items:center;gap:4px;cursor:pointer;margin:0;"><input type="checkbox" class="ecg-parent" data-target="sub_ecg_rythme" onchange="toggleSub(this)"> Trouble de rythme</label>
                <span id="arr_ecg_rythme">▶</span>
            </div>
            <div id="sub_ecg_rythme" style="display:none;margin-left:14px;margin-top:2px;">
                <label style="font-size:11px;cursor:pointer;display:block;"><input type="checkbox" class="ecg-parent" data-target="sub_ecg_rythme_sv" onchange="toggleSub(this)"> Supraventriculaire</label>
                <div id="sub_ecg_rythme_sv" style="display:none;margin-left:12px;margin-top:1px;">
                    <label style="font-size:11px;display:block;"><input type="checkbox" class="ecg-child" onchange="exclusifVisible(this,'sub_ecg_rythme_sv')" value="rythme sinusal, absence de trouble de rythme"> Rythme sinusal, absence de trouble de rythme</label>
                    <label style="font-size:11px;display:block;"><input type="checkbox" class="ecg-child" onchange="exclusifVisible(this,'sub_ecg_rythme_sv')" value="arythmie complète par fibrillation auriculaire"> Arythmie complète par fibrillation auriculaire</label>
                    <label style="font-size:11px;display:block;"><input type="checkbox" class="ecg-child" onchange="exclusifVisible(this,'sub_ecg_rythme_sv')" value="tachyarythmie"> Tachyarythmie</label>
                    <label style="font-size:11px;display:block;"><input type="checkbox" class="ecg-child" onchange="exclusifVisible(this,'sub_ecg_rythme_sv')" value="bradyarythmie"> Bradyarythmie</label>
                    <label style="font-size:11px;display:block;"><input type="checkbox" class="ecg-child" onchange="exclusifVisible(this,'sub_ecg_rythme_sv')" value="flutter auriculaire"> Flutter auriculaire</label>
                    <label style="font-size:11px;display:block;"><input type="checkbox" class="ecg-child" onchange="exclusifVisible(this,'sub_ecg_rythme_sv')" value="hyperexcitabilité supraventriculaire"> Hyperexcitabilité supraventriculaire</label>
                </div>
                <label style="font-size:11px;cursor:pointer;margin-top:3px;display:block;"><input type="checkbox" class="ecg-parent" data-target="sub_ecg_rythme_v" onchange="toggleSub(this)"> Ventriculaire</label>
                <div id="sub_ecg_rythme_v" style="display:none;margin-left:12px;margin-top:1px;">
                    <label style="font-size:11px;display:block;"><input type="checkbox" class="ecg-child" onchange="exclusifVisible(this,'sub_ecg_rythme_v')" value="absence de trouble de rythme ventriculaire"> Absence de trouble de rythme</label>
                    <label style="font-size:11px;display:block;"><input type="checkbox" class="ecg-child" onchange="exclusifVisible(this,'sub_ecg_rythme_v')" value="hyperexcitabilité ventriculaire"> Hyperexcitabilité ventriculaire</label>
                    <label style="font-size:11px;display:block;"><input type="checkbox" class="ecg-child" onchange="exclusifVisible(this,'sub_ecg_rythme_v')" value="salve de TV"> Salve de TV</label>
                </div>
            </div>

            <!-- Trouble de conduction -->
            <div class="ecg-section" onclick="toggleEcgSection('sub_ecg_cond','arr_ecg_cond')">
                <label style="display:flex;align-items:center;gap:4px;cursor:pointer;margin:0;"><input type="checkbox" class="ecg-parent" data-target="sub_ecg_cond" onchange="toggleSub(this)"> Trouble de conduction</label>
                <span id="arr_ecg_cond">▶</span>
            </div>
            <div id="sub_ecg_cond" style="display:none;margin-left:14px;margin-top:2px;">
                <label style="font-size:11px;cursor:pointer;display:block;"><input type="checkbox" class="ecg-parent" data-target="sub_ecg_cond_sv" onchange="toggleSub(this)"> Supraventriculaire</label>
                <div id="sub_ecg_cond_sv" style="display:none;margin-left:12px;margin-top:1px;">
                    <label style="font-size:11px;display:block;"><input type="checkbox" class="ecg-child" onchange="exclusifVisible(this,'sub_ecg_cond_sv')" value="conduction supraventriculaire normale"> Normale</label>
                    <label style="font-size:11px;display:block;"><input type="checkbox" class="ecg-child" onchange="exclusifVisible(this,'sub_ecg_cond_sv')" value="bradycardie sinusale"> Bradycardie sinusale</label>
                    <label style="font-size:11px;display:block;"><input type="checkbox" class="ecg-child" onchange="exclusifVisible(this,'sub_ecg_cond_sv')" value="tachycardie sinusale"> Tachycardie sinusale</label>
                    <label style="font-size:11px;display:block;"><input type="checkbox" class="ecg-child" onchange="exclusifVisible(this,'sub_ecg_cond_sv')" value="bloc sino-auriculaire"> Bloc sino-auriculaire</label>
                    <label style="font-size:11px;display:block;"><input type="checkbox" class="ecg-child" onchange="exclusifVisible(this,'sub_ecg_cond_sv')" value="pauses sinusales"> Pauses sinusales</label>
                </div>
                <label style="font-size:11px;cursor:pointer;margin-top:3px;display:block;"><input type="checkbox" class="ecg-parent" data-target="sub_ecg_cond_av" onchange="toggleSub(this)"> Auriculo-ventriculaire</label>
                <div id="sub_ecg_cond_av" style="display:none;margin-left:12px;margin-top:1px;">
                    <label style="font-size:11px;display:block;"><input type="checkbox" class="ecg-child" onchange="exclusifVisible(this,'sub_ecg_cond_av')" value="conduction auriculo-ventriculaire normale"> Normale</label>
                    <label style="font-size:11px;display:block;"><input type="checkbox" class="ecg-child" onchange="exclusifVisible(this,'sub_ecg_cond_av')" value="BAV I"> BAV I</label>
                    <label style="font-size:11px;display:block;"><input type="checkbox" class="ecg-child" onchange="exclusifVisible(this,'sub_ecg_cond_av')" value="BAV II : Luciani-Wenckebach"> BAV II : Luciani-Wenckebach</label>
                    <label style="font-size:11px;display:block;"><input type="checkbox" class="ecg-child" onchange="exclusifVisible(this,'sub_ecg_cond_av')" value="BAV II : Mobitz II"> BAV II : Mobitz II</label>
                    <label style="font-size:11px;display:block;"><input type="checkbox" class="ecg-child" onchange="exclusifVisible(this,'sub_ecg_cond_av')" value="BAV III"> BAV III</label>
                    <label style="font-size:11px;display:block;"><input type="checkbox" class="ecg-child" onchange="exclusifVisible(this,'sub_ecg_cond_av')" value="pré-excitations (WPW)"> Pré-excitations</label>
                </div>
                <label style="font-size:11px;cursor:pointer;margin-top:3px;display:block;"><input type="checkbox" class="ecg-parent" data-target="sub_ecg_cond_iv" onchange="toggleSub(this)"> Intra-ventriculaire</label>
                <div id="sub_ecg_cond_iv" style="display:none;margin-left:12px;margin-top:1px;">
                    <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="ecg-child" onchange="exclusifVisible(this,'sub_ecg_cond_iv')" value="conduction intra-ventriculaire normale"> Conduction intra-ventriculaire normale</label>

                    <!-- Bloc de branche gauche -->
                    <label style="font-size:11px;cursor:pointer;display:block;margin-bottom:1px;"><input type="checkbox" class="ecg-parent" data-target="sub_ecg_bbg" onchange="toggleSub(this)"> Bloc de branche gauche</label>
                    <div id="sub_ecg_bbg" style="display:none;margin-left:12px;margin-top:1px;margin-bottom:2px;">
                        <label style="font-size:11px;display:block;"><input type="checkbox" class="ecg-child" value="bloc de branche gauche complet"> complet</label>
                        <label style="font-size:11px;display:block;"><input type="checkbox" class="ecg-child" value="bloc de branche gauche incomplet"> incomplet</label>
                        <label style="font-size:11px;display:block;"><input type="checkbox" class="ecg-child" value="hémibloc antérieur gauche"> hémibloc antérieur gauche</label>
                        <label style="font-size:11px;display:block;"><input type="checkbox" class="ecg-child" value="hémibloc postérieur gauche"> hémibloc postérieur</label>
                    </div>

                    <!-- Bloc de branche droit -->
                    <label style="font-size:11px;cursor:pointer;display:block;margin-bottom:1px;"><input type="checkbox" class="ecg-parent" data-target="sub_ecg_bbd" onchange="toggleSub(this)"> Bloc de branche droit</label>
                    <div id="sub_ecg_bbd" style="display:none;margin-left:12px;margin-top:1px;margin-bottom:2px;">
                        <label style="font-size:11px;display:block;"><input type="checkbox" class="ecg-child" value="bloc de branche droit complet"> complet</label>
                        <label style="font-size:11px;display:block;"><input type="checkbox" class="ecg-child" value="bloc de branche droit incomplet"> incomplet</label>
                        <label style="font-size:11px;display:block;"><input type="checkbox" class="ecg-child" value="bloc droit complet"> bloc droit complet</label>
                    </div>

                    <!-- Pacemaker -->
                    <label style="font-size:11px;display:block;cursor:pointer;margin-top:2px;"><input type="checkbox" class="ecg-parent" data-target="sub_ecg_pace" onchange="toggleSub(this);" value="électro-entraîné, pacemaker"> Électro-entraîné, pacemaker — date de pose :
                        <input type="text" id="ecg_pace_date" placeholder="jj/mm/aaaa" style="width:80px;border:1px solid #ccc;border-radius:2px;padding:1px 3px;font-size:10px;">
                    </label>
                    <div id="sub_ecg_pace" style="display:none;margin-left:14px;margin-top:2px;">
                        <label style="font-size:11px;display:block;"><input type="checkbox" class="ecg-child" value="DDD"> DDD</label>
                        <label style="font-size:11px;display:block;"><input type="checkbox" class="ecg-child" value="VVI"> VVI</label>
                        <label style="font-size:11px;display:block;"><input type="checkbox" class="ecg-child" value="AAI (une sonde dans l'oreillette droite)"> AAI — une sonde dans l'oreillette droite</label>
                        <label style="font-size:11px;display:block;"><input type="checkbox" class="ecg-child" value="CRT-P (pacemaker de resynchronisation cardiaque)"> CRT-P — resynchronisation cardiaque</label>
                        <label style="font-size:11px;display:block;"><input type="checkbox" class="ecg-child" value="DAI (défibrillateur automatique implantable)"> DAI — défibrillateur automatique implantable</label>
                    </div>
                </div>
            </div>

            <!-- Ondes Q de nécrose -->
            <div class="ecg-section" onclick="toggleEcgSection('sub_ecg_isch','arr_ecg_isch')">
                <label style="display:flex;align-items:center;gap:4px;cursor:pointer;margin:0;"><input type="checkbox" class="ecg-parent" data-target="sub_ecg_isch" onchange="toggleSub(this)"> Ondes Q de nécrose</label>
                <span id="arr_ecg_isch">▶</span>
            </div>
            <div id="sub_ecg_isch" style="display:none;margin-left:14px;margin-top:2px;">
                <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="ecg-child" onchange="exclusifVisible(this,'sub_ecg_isch')" value="ondes Q de nécrose absentes"> Absents</label>
                <label style="font-size:11px;display:block;cursor:pointer;margin-bottom:2px;"><input type="checkbox" class="ecg-parent ecg-child" data-target="sub_ecg_isch_topo" onchange="exclusifVisible(this,'sub_ecg_isch');toggleSub(this);" value="ondes Q de nécrose présentes"> Présents</label>
                <div id="sub_ecg_isch_topo" style="display:none;margin-left:12px;margin-top:1px;">
                        <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="topo-cb" value="onde Q de nécrose antérieur"> Antérieur</label>
                        <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="topo-cb" value="onde Q de nécrose apical"> Apical</label>
                        <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="topo-cb" value="onde Q de nécrose inférieur"> Inférieur</label>
                        <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="topo-cb" value="onde Q de nécrose latéral"> Latéral</label>
                        <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="topo-cb" value="onde Q de nécrose postérieur"> Postérieur</label>
                        <button type="button" onclick="appliquerMultiple('sub_ecg_isch_topo')" style="margin-top:3px;background:#1a4a7a;color:white;border:none;border-radius:3px;padding:2px 10px;font-size:10px;cursor:pointer;">✓ OK</button>
                </div>
            </div>

            <!-- Segment ST -->
            <div class="ecg-section" onclick="toggleEcgSection('sub_ecg_repol','arr_ecg_repol')">
                <label style="display:flex;align-items:center;gap:4px;cursor:pointer;margin:0;"><input type="checkbox" class="ecg-parent" data-target="sub_ecg_repol" onchange="toggleSub(this)"> Segment ST</label>
                <span id="arr_ecg_repol">▶</span>
            </div>
            <div id="sub_ecg_repol" style="display:none;margin-left:14px;margin-top:2px;">
                <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="ecg-child" onchange="exclusifVisible(this,'sub_ecg_repol')" value="segment ST sans anomalie"> Absents</label>
                <label style="font-size:11px;display:block;cursor:pointer;margin-bottom:2px;"><input type="checkbox" class="ecg-parent ecg-child" data-target="sub_ecg_repol_detail" onchange="exclusifVisible(this,'sub_ecg_repol');toggleSub(this);" value="anomalie segment ST présente"> Présents</label>
                <div id="sub_ecg_repol_detail" style="display:none;margin-left:12px;margin-top:2px;">
                    <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="ecg-child" onchange="exclusifVisible(this,'sub_ecg_repol_st')" value="sous-décalage segment ST"> Sous-décalage segment ST</label>
                    <label style="font-size:11px;display:block;margin-bottom:4px;"><input type="checkbox" class="ecg-child" onchange="exclusifVisible(this,'sub_ecg_repol_st')" value="sus-décalage segment ST"> Sus-décalage segment ST</label>
                    <div id="sub_ecg_repol_st"></div>
                    <div style="font-size:10px;color:#555;margin-bottom:2px;">Dans le territoire :</div>
                        <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="topo-cb" value="anomalie ST antérieur"> Antérieur</label>
                        <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="topo-cb" value="anomalie ST apical"> Apical</label>
                        <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="topo-cb" value="anomalie ST inférieur"> Inférieur</label>
                        <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="topo-cb" value="anomalie ST latéral"> Latéral</label>
                        <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="topo-cb" value="anomalie ST postérieur"> Postérieur</label>
                        <button type="button" onclick="appliquerMultiple('sub_ecg_repol_detail')" style="margin-top:3px;background:#1a4a7a;color:white;border:none;border-radius:3px;padding:2px 10px;font-size:10px;cursor:pointer;">✓ OK</button>
                </div>
            </div>

            <!-- Ondes T -->
            <div class="ecg-section" onclick="toggleEcgSection('sub_ecg_ondeT','arr_ecg_ondeT')">
                <label style="display:flex;align-items:center;gap:4px;cursor:pointer;margin:0;"><input type="checkbox" class="ecg-parent" data-target="sub_ecg_ondeT" onchange="toggleSub(this)"> Ondes T</label>
                <span id="arr_ecg_ondeT">▶</span>
            </div>
            <div id="sub_ecg_ondeT" style="display:none;margin-left:14px;margin-top:2px;">
                <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="ecg-child" onchange="exclusifVisible(this,'sub_ecg_ondeT')" value="ondes T sans anomalie"> Absents</label>
                <label style="font-size:11px;display:block;cursor:pointer;margin-bottom:2px;"><input type="checkbox" class="ecg-parent ecg-child" data-target="sub_ecg_ondeT_topo" onchange="exclusifVisible(this,'sub_ecg_ondeT');toggleSub(this);" value="anomalie ondes T présente"> Présents</label>
                <div id="sub_ecg_ondeT_topo" style="display:none;margin-left:12px;margin-top:1px;">
                        <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="topo-cb" value="anomalie onde T antérieur"> Antérieur</label>
                        <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="topo-cb" value="anomalie onde T apical"> Apical</label>
                        <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="topo-cb" value="anomalie onde T inférieur"> Inférieur</label>
                        <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="topo-cb" value="anomalie onde T latéral"> Latéral</label>
                        <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="topo-cb" value="anomalie onde T postérieur"> Postérieur</label>
                        <button type="button" onclick="appliquerMultiple('sub_ecg_ondeT_topo')" style="margin-top:3px;background:#1a4a7a;color:white;border:none;border-radius:3px;padding:2px 10px;font-size:10px;cursor:pointer;">✓ OK</button>
                </div>
            </div>

        </div><!-- fin ecg_detail -->
    </div><!-- fin panel_ecg_cases -->

    <!-- 12. C/C + Autres signes ECG côte à côte -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;">
        <div class="champ" id="wrap_CC"><div class="label-excl"><label>C/C</label><button type="button" class="btn-excl" onclick="toggleExcl('CC')" title="Exclure du rapport">−</button></div>
            <textarea name="CC" oninput="majApercuECG()" placeholder="ex: ECG normal" style="min-height:36px;resize:vertical;"></textarea>
        </div>
        <div class="champ"><label>Autres signes ECG</label>
            <input type="text" name="AUTRES_SIGNES">
        </div>
    </div>

    <!-- Champs cachés exclusion ECG -->
    <input type="hidden" id="excl_rythme_sv"                   name="excl_rythme_sv">
    <input type="hidden" id="excl_trouble_rv"                  name="excl_trouble_rv">
    <input type="hidden" id="excl_conduction_nodale"           name="excl_conduction_nodale">
    <input type="hidden" id="excl_QRS"                         name="excl_QRS">
    <input type="hidden" id="excl_infrastructure_de_conduction" name="excl_infrastructure_de_conduction">
    <input type="hidden" id="excl_REPOLARISATION"              name="excl_REPOLARISATION">
    <input type="hidden" id="excl_CC"                          name="excl_CC">

    <!-- Zone prévisualisation concaténation ECG -->
    <div class="champ" style="margin-top:6px;">
        <label style="font-size:10px;color:#2e6da4;font-weight:bold;">👁 Aperçu rapport ECG</label>
        <textarea id="apercu_ecg" name="CMLM_ECG"
            style="min-height:65px;background:#f0f7ff;border:1px solid #2e6da4;font-size:11px;color:#1a4a7a;resize:none;overflow:hidden;width:100%;padding:4px 6px;border-radius:3px;font-family:Arial,sans-serif;pointer-events:none;"></textarea>
    </div>
    </form>


    <!-- ══ 🫀 Echo-Doppler ══ -->
    <div style="margin-top:8px;padding-top:6px;border-top:2px solid #e0e0e0;">
    <form id="form-echo">
    <input type="hidden" name="onglet" value="echo">
    <input type="hidden" name="ajax" value="1">
    <div class="col-title">
        <span style="font-size:12px;font-weight:bold;color:#1a4a7a;white-space:nowrap;">🫀 Echo-Doppler</span>
        <span style="flex:1;"></span>
        <input type="date" name="DATEchog" value="<?= $today ?>" id="date_echo" style="border:1px solid #ddd;border-radius:3px;padding:2px 5px;font-size:11px;color:#1a4a7a;">
    </div>
    <div style="min-height:0;"><span id="msg_echo" style="font-size:11px;color:#27ae60;font-weight:bold;display:none;"></span></div>
    <div style="margin-bottom:2px;"><small id="lbl_exclu_echo" style="color:#e74c3c;font-weight:bold;font-size:9px;display:none;"></small></div>
    <div style="display:flex;align-items:center;gap:2px;background:#f0f4f8;border-radius:4px;padding:3px 5px;margin-bottom:8px;">
        <button type="button" onclick="naviguerBilan('echo','last')" title="Premier bilan" style="background:none;color:#2e6da4;border:1px solid #c5d8ed;border-radius:3px;height:20px;min-width:20px;padding:0 3px;font-size:11px;font-weight:bold;cursor:pointer;">|◀</button>
        <button type="button" onclick="naviguerBilan('echo','next')"  title="Précédent"    style="background:none;color:#2e6da4;border:1px solid #c5d8ed;border-radius:3px;height:20px;min-width:20px;padding:0 3px;font-size:11px;font-weight:bold;cursor:pointer;">◀</button>
        <span id="navdate_echo" style="flex:1;text-align:center;font-weight:bold;color:#1a4a7a;font-size:11px;">— nouveau —</span>
        <button type="button" onclick="naviguerBilan('echo','prev')"  title="Suivant"      style="background:none;color:#2e6da4;border:1px solid #c5d8ed;border-radius:3px;height:20px;min-width:20px;padding:0 3px;font-size:11px;font-weight:bold;cursor:pointer;">▶</button>
        <button type="button" onclick="naviguerBilan('echo','first')" title="Dernier"      style="background:none;color:#2e6da4;border:1px solid #c5d8ed;border-radius:3px;height:20px;min-width:20px;padding:0 3px;font-size:11px;font-weight:bold;cursor:pointer;">▶|</button>
        <button type="button" onclick="nouveauBilan('echo')"          title="Nouveau"      style="background:#27ae60;color:white;border:1px solid #27ae60;border-radius:3px;height:20px;padding:0 6px;font-size:10px;font-weight:bold;cursor:pointer;">▶*</button>
    </div>

    <input type="hidden" name="TYPE_ECHO" id="type_echo_val" value="Echoscopie cardiaque">

    <!-- ── Cases à cocher Echo ── -->
    <div id="panel_echo_cases" style="margin-bottom:6px;border:1px solid #b0c8e8;border-radius:5px;padding:6px 8px;background:#f5f9ff;">

        <!-- Boutons Normale / Anormale -->
        <div style="display:flex;gap:6px;margin-bottom:6px;">
            <button type="button" id="btn_echo_normale" onclick="setEchoGlobal('normale')"
                style="flex:1;padding:5px 4px;border:2px solid #27ae60;border-radius:4px;background:#27ae60;color:white;font-size:11px;font-weight:bold;cursor:pointer;">✅ Échographie normale</button>
            <button type="button" id="btn_echo_anormale" onclick="setEchoGlobal('anormale')"
                style="flex:1;padding:5px 4px;border:2px solid #e67e22;border-radius:4px;background:white;color:#e67e22;font-size:11px;font-weight:bold;cursor:pointer;">⚠️ Échographie anormale</button>
        </div>
        <div style="margin-top:6px;margin-bottom:4px;">
            <button type="button" id="btn_generer_echo"
                onclick="genererCmlmEcho(); document.getElementById('echo_normale_detail').style.display='none'; document.getElementById('cmlm_echo_detail').style.display='none'; document.getElementById('btn_generer_echo').style.display='none'; document.getElementById('lien_modifier_echo').style.display='block'; enregistrerAjax('echo');"
                style="background:#1a4a7a;color:white;border:none;border-radius:3px;padding:3px 12px;font-size:11px;cursor:pointer;">▶ Générer &amp; 💾</button>
            <button type="button" id="lien_modifier_echo" onclick="modifierEcho()" style="display:none;background:#e67e22;color:white;border:none;border-radius:3px;padding:3px 10px;font-size:11px;cursor:pointer;">↺ Modifier</button>
        </div>

        <!-- ══ NORMALE ══ -->
        <div id="echo_normale_detail" style="display:none;">
            <div style="font-size:11px;font-weight:bold;color:#27ae60;margin-bottom:3px;">Échographie normale</div>
            <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="echo-n" id="en_nocavite" checked value="absence d'hypertrophie ou de dilatation cavitaire"> Absence d'hypertrophie ou de dilatation cavitaire</label>
            <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="echo-n" id="en_flux" checked value="flux trans valvaires normaux"> Flux trans valvaires normaux</label>
            <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="echo-n" id="en_nohtap" checked value="absence d'HTAP"> Absence d'HTAP</label>
            <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="echo-n" id="en_og" checked value="oreillettes non dilatées"> Oreillettes non dilatées</label>
            <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="echo-n" id="en_vd" checked value="cavités droites non dilatées"> Cavités droites non dilatées</label>
            <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="echo-n" id="en_peri" checked value="péricarde sec"> Péricarde sec</label>
            <label style="font-size:11px;display:block;margin-bottom:4px;"><input type="checkbox" class="echo-n" id="en_aorte" checked value="aorte initiale non dilatée"> Aorte initiale non dilatée</label>
        </div>

        <!-- ══ ANORMALE ══ -->
        <div id="cmlm_echo_detail" style="display:none;">

        <!-- style aide-mémoire sections -->
        <style>
        .echo-section { font-size:11px;font-weight:bold;color:#1a4a7a;background:#dce8f5;border-radius:3px;padding:2px 5px;margin:5px 0 2px;cursor:pointer;display:flex;align-items:center;justify-content:space-between; }
        .ecg-section  { font-size:11px;font-weight:bold;color:#1a4a7a;background:#dce8f5;border-radius:3px;padding:2px 5px;margin:5px 0 2px;cursor:pointer;display:flex;align-items:center;justify-content:space-between; }
        .exam-section { font-size:11px;font-weight:bold;color:#1a4a7a;background:#dce8f5;border-radius:3px;padding:2px 5px;margin:5px 0 2px;cursor:pointer;display:flex;align-items:center;justify-content:space-between; }
        .echo-mesure  { font-size:10px;color:#555;margin:2px 0 3px 4px; }
        .echo-mesure input[type=text],
        .echo-mesure input[type=number] { width:44px;border:1px solid #ccc;border-radius:2px;padding:1px 3px;font-size:10px; }
        .echo-sub     { margin-left:12px; }
        .echo-sub2    { margin-left:22px; }
        .echo-sub3    { margin-left:32px; }
        .echo-lbl     { font-size:11px;display:block;margin-bottom:1px;cursor:pointer; }
        </style>

        <!-- ══ VALVULAIRE AORTIQUE ══ -->
        <div class="echo-section" onclick="toggleEchoSub('es_ao')"><label style="display:flex;align-items:center;gap:4px;cursor:pointer;margin:0;"><input type="checkbox" class="cmlm-ep" data-target="es_ao" onchange="toggleCmlmSub(this)" style="width:12px;height:12px;cursor:pointer;"> Valvulaire Aortique </label><span id="es_ao_arr">▶</span></div>
        <div id="es_ao" style="display:none;">
            <div class="echo-mesure">
                Grad moy : <input type="number" id="em_grad_ao" name="GRAD_MOY_AO" placeholder="mmHg" step="0.1">
                ½PHT : <input type="number" id="em_pht_ao" name="PHT_AO" placeholder="ms" step="0.1">
                Vmax IA : <input type="number" id="em_vmax_ia" name="VMAX_IA" placeholder="m/s" step="0.01">
            </div>
            <!-- Rétrécissement -->
            <label class="echo-lbl echo-sub"><input type="checkbox" class="cmlm-ep" data-target="es_ao_ra" onchange="toggleCmlmSub(this)"> Rétrécissement aortique</label>
            <div id="es_ao_ra" class="echo-sub2" style="display:none;">
                <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_ao_ra" onchange="exclusifGroup(this)" value="rétrécissement aortique très serré chirurgical"> très serré chirurgical</label>
                <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_ao_ra" onchange="exclusifGroup(this)" value="rétrécissement aortique serré"> serré</label>
                <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_ao_ra" onchange="exclusifGroup(this)" value="rétrécissement aortique lâche"> lâche</label>
            </div>
            <!-- Fuite -->
            <label class="echo-lbl echo-sub"><input type="checkbox" class="cmlm-ep" data-target="es_ao_fa" onchange="toggleCmlmSub(this)"> Fuite aortique</label>
            <div id="es_ao_fa" class="echo-sub2" style="display:none;">
                <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_ao_fa" onchange="exclusifGroup(this)" value="fuite aortique chirurgicale"> chirurgicale</label>
                <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_ao_fa" onchange="exclusifGroup(this)" value="fuite aortique non chirurgicale"> non chirurgicale</label>
            </div>
            <!-- Maladie -->
            <label class="echo-lbl echo-sub"><input type="checkbox" class="cmlm-ep" data-target="es_ao_ma" onchange="toggleCmlmSub(this)"> Maladie aortique</label>
            <div id="es_ao_ma" class="echo-sub2" style="display:none;">
                <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_ao_ma" onchange="exclusifGroup(this)" value="maladie aortique chirurgicale"> chirurgicale</label>
                <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_ao_ma" onchange="exclusifGroup(this)" value="maladie aortique non chirurgicale"> non chirurgicale</label>
            </div>
            <!-- Prothèse aortique -->
            <label class="echo-lbl echo-sub"><input type="checkbox" class="cmlm-ep" data-target="es_ao_proth" onchange="toggleCmlmSub(this)"> Prothèse valvulaire aortique</label>
            <div id="es_ao_proth" class="echo-sub2" style="display:none;">
                <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_ao_proth" onchange="exclusifGroup(this)" value="prothèse aortique fonctionnelle"> Prothèse fonctionnelle</label>
                <label class="echo-lbl"><input type="checkbox" class="cmlm-ep excl1" data-group="g2_ao_proth" data-target="es_ao_dysf" onchange="exclusifGroup(this);toggleCmlmSub(this)"> Dysfonction de prothèse</label>
                <div id="es_ao_dysf" class="echo-sub3" style="display:none;">
                    <!-- Obstructive -->
                    <label class="echo-lbl"><input type="checkbox" class="cmlm-ep" data-target="es_ao_obs" onchange="toggleCmlmSub(this)"> Dysfonction obstructive</label>
                    <div id="es_ao_obs" class="echo-sub3" style="display:none;">
                        <div class="echo-mesure">Grad moy : <input type="number" id="em_ao_obs_grad" placeholder="mmHg" step="0.1"></div>
                        <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_ao_obs" onchange="exclusifGroup(this)" value="dysfonction obstructive prothèse aortique légère"> Légère</label>
                        <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_ao_obs" onchange="exclusifGroup(this)" value="dysfonction obstructive prothèse aortique modérée"> Modérée</label>
                        <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_ao_obs" onchange="exclusifGroup(this)" value="dysfonction obstructive prothèse aortique sévère"> Sévère</label>
                        <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_ao_obs" onchange="exclusifGroup(this)" value="thrombose de prothèse aortique"> Thrombose de prothèse</label>
                        <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_ao_obs" onchange="exclusifGroup(this)" value="pannus prothèse aortique"> Pannus</label>
                    </div>
                    <!-- Fuite prothèse Ao -->
                    <label class="echo-lbl"><input type="checkbox" class="cmlm-ep" data-target="es_ao_fproth" onchange="toggleCmlmSub(this)"> Dysfonction par fuite</label>
                    <div id="es_ao_fproth" class="echo-sub3" style="display:none;">
                        <div class="echo-mesure">½PHT : <input type="number" id="em_ao_fproth_pht" placeholder="ms" step="0.1"> Vmax IA : <input type="number" id="em_ao_fproth_vmax" placeholder="m/s" step="0.01"></div>
                        <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_ao_fproth" onchange="exclusifGroup(this)" value="dysfonction par fuite prothèse aortique légère"> Légère</label>
                        <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_ao_fproth" onchange="exclusifGroup(this)" value="dysfonction par fuite prothèse aortique modérée"> Modérée</label>
                        <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_ao_fproth" onchange="exclusifGroup(this)" value="dysfonction par fuite prothèse aortique sévère"> Sévère</label>
                        <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_ao_fproth" onchange="exclusifGroup(this)" value="fuite paraprothétique aortique"> Fuite paraprothétique</label>
                    </div>
                    <!-- Dégénérescence -->
                    <label class="echo-lbl"><input type="checkbox" class="cmlm-ep" data-target="es_ao_degen" onchange="toggleCmlmSub(this)"> Dégénérescence de bioprothèse</label>
                    <div id="es_ao_degen" class="echo-sub3" style="display:none;">
                        <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_ao_degen" onchange="exclusifGroup(this)" value="dégénérescence bioprothèse aortique légère"> Légère</label>
                        <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_ao_degen" onchange="exclusifGroup(this)" value="dégénérescence bioprothèse aortique modérée"> Modérée</label>
                        <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_ao_degen" onchange="exclusifGroup(this)" value="dégénérescence bioprothèse aortique sévère"> Sévère</label>
                    </div>
                    <!-- Endocardite Ao -->
                    <label class="echo-lbl"><input type="checkbox" class="cmlm-ep" data-target="es_ao_endo" onchange="toggleCmlmSub(this)"> Endocardite sur prothèse</label>
                    <div id="es_ao_endo" class="echo-sub3" style="display:none;">
                        <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_ao_endo" onchange="exclusifGroup(this)" value="endocardite sur prothèse aortique non compliquée"> Non compliquée</label>
                        <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_ao_endo" onchange="exclusifGroup(this)" value="endocardite sur prothèse aortique compliquée"> Compliquée</label>
                    </div>
                    <!-- Désinsertion Ao -->
                    <label class="echo-lbl"><input type="checkbox" class="cmlm-ep" data-target="es_ao_desin" onchange="toggleCmlmSub(this)"> Désinsertion de prothèse</label>
                    <div id="es_ao_desin" class="echo-sub3" style="display:none;">
                        <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_ao_desin" onchange="exclusifGroup(this)" value="désinsertion partielle prothèse aortique"> Partielle</label>
                        <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_ao_desin" onchange="exclusifGroup(this)" value="désinsertion importante prothèse aortique"> Importante</label>
                    </div>
                    <!-- Discordance Ao -->
                    <label class="echo-lbl"><input type="checkbox" class="cmlm-ep" data-target="es_ao_discord" onchange="toggleCmlmSub(this)"> Discordance patient–prothèse</label>
                    <div id="es_ao_discord" class="echo-sub3" style="display:none;">
                        <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_ao_discord" onchange="exclusifGroup(this)" value="discordance patient–prothèse aortique légère"> Légère</label>
                        <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_ao_discord" onchange="exclusifGroup(this)" value="discordance patient–prothèse aortique modérée"> Modérée</label>
                        <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_ao_discord" onchange="exclusifGroup(this)" value="discordance patient–prothèse aortique sévère"> Sévère</label>
                    </div>
                    <!-- Conclusion prothèse Ao -->
                    <div style="font-size:10px;color:#1a4a7a;font-weight:bold;margin-top:3px;">Conclusion :</div>
                    <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_ao_concl" onchange="exclusifGroup(this)" value="dysfonction prothèse aortique non significative"> Dysfonction non significative</label>
                    <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_ao_concl" onchange="exclusifGroup(this)" value="dysfonction prothèse aortique significative"> Dysfonction significative</label>
                    <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_ao_concl" onchange="exclusifGroup(this)" value="dysfonction sévère prothèse aortique nécessitant avis spécialisé"> Dysfonction sévère — avis spécialisé</label>
                    <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_ao_concl" onchange="exclusifGroup(this)" value="urgence prothèse aortique (thrombose obstructive / endocardite compliquée / désinsertion majeure)"> Urgence</label>
                </div>
            </div>
        </div>

        <!-- ══ VALVULAIRE MITRALE ══ -->
        <div class="echo-section" onclick="toggleEchoSub('es_mi')"><label style="display:flex;align-items:center;gap:4px;cursor:pointer;margin:0;"><input type="checkbox" class="cmlm-ep" data-target="es_mi" onchange="toggleCmlmSub(this)" style="width:12px;height:12px;cursor:pointer;"> Valvulaire Mitrale </label><span id="es_mi_arr">▶</span></div>
        <div id="es_mi" style="display:none;">
            <div class="echo-mesure">
                Surface : <input type="number" id="em_surf_mi" name="SURF_MI" placeholder="cm²" step="0.01">
                SOR : <input type="number" id="em_sor_mi" name="SOR_MI" placeholder="mm²" step="0.1">
            </div>
            <!-- Rétrécissement mitral -->
            <label class="echo-lbl echo-sub"><input type="checkbox" class="cmlm-ep" data-target="es_mi_rm" onchange="toggleCmlmSub(this)"> Rétrécissement mitral</label>
            <div id="es_mi_rm" class="echo-sub2" style="display:none;">
                <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_mi_rm" onchange="exclusifGroup(this)" value="rétrécissement mitral très serré chirurgical"> très serré chirurgical</label>
                <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_mi_rm" onchange="exclusifGroup(this)" value="rétrécissement mitral serré"> serré</label>
                <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_mi_rm" onchange="exclusifGroup(this)" value="rétrécissement mitral lâche"> lâche</label>
            </div>
            <!-- Fuite mitrale -->
            <label class="echo-lbl echo-sub"><input type="checkbox" class="cmlm-ep" data-target="es_mi_fm" onchange="toggleCmlmSub(this)"> Fuite mitrale</label>
            <div id="es_mi_fm" class="echo-sub2" style="display:none;">
                <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_mi_fm" onchange="exclusifGroup(this)" value="fuite mitrale chirurgicale"> chirurgicale</label>
                <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_mi_fm" onchange="exclusifGroup(this)" value="fuite mitrale non chirurgicale"> non chirurgicale</label>
            </div>
            <!-- Maladie mitrale -->
            <label class="echo-lbl echo-sub"><input type="checkbox" class="cmlm-ep" data-target="es_mi_mm" onchange="toggleCmlmSub(this)"> Maladie mitrale</label>
            <div id="es_mi_mm" class="echo-sub2" style="display:none;">
                <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_mi_mm" onchange="exclusifGroup(this)" value="maladie mitrale chirurgicale"> chirurgicale</label>
                <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_mi_mm" onchange="exclusifGroup(this)" value="maladie mitrale non chirurgicale"> non chirurgicale</label>
            </div>
            <!-- Prolapsus -->
            <label class="echo-lbl echo-sub"><input type="checkbox" class="cmlm-ec" value="prolapsus mitral"> Prolapsus mitral</label>
            <!-- Prothèse mitrale -->
            <label class="echo-lbl echo-sub"><input type="checkbox" class="cmlm-ep" data-target="es_mi_proth" onchange="toggleCmlmSub(this)"> Prothèse valvulaire mitrale</label>
            <div id="es_mi_proth" class="echo-sub2" style="display:none;">
                <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_mi_proth" onchange="exclusifGroup(this)" value="prothèse mitrale fonctionnelle"> Prothèse fonctionnelle</label>
                <label class="echo-lbl"><input type="checkbox" class="cmlm-ep excl1" data-group="g2_mi_proth" data-target="es_mi_dysf" onchange="exclusifGroup(this);toggleCmlmSub(this)"> Dysfonction de prothèse</label>
                <div id="es_mi_dysf" class="echo-sub3" style="display:none;">
                    <label class="echo-lbl"><input type="checkbox" class="cmlm-ep" data-target="es_mi_obs" onchange="toggleCmlmSub(this)"> Dysfonction obstructive</label>
                    <div id="es_mi_obs" class="echo-sub3" style="display:none;">
                        <div class="echo-mesure">Grad moy : <input type="number" id="em_mi_obs_grad" placeholder="mmHg" step="0.1"></div>
                        <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_mi_obs" onchange="exclusifGroup(this)" value="dysfonction obstructive prothèse mitrale légère"> Légère</label>
                        <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_mi_obs" onchange="exclusifGroup(this)" value="dysfonction obstructive prothèse mitrale modérée"> Modérée</label>
                        <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_mi_obs" onchange="exclusifGroup(this)" value="dysfonction obstructive prothèse mitrale sévère"> Sévère</label>
                        <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_mi_obs" onchange="exclusifGroup(this)" value="thrombose de prothèse mitrale"> Thrombose de prothèse</label>
                        <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_mi_obs" onchange="exclusifGroup(this)" value="pannus prothèse mitrale"> Pannus</label>
                    </div>
                    <label class="echo-lbl"><input type="checkbox" class="cmlm-ep" data-target="es_mi_fproth" onchange="toggleCmlmSub(this)"> Dysfonction par fuite</label>
                    <div id="es_mi_fproth" class="echo-sub3" style="display:none;">
                        <div class="echo-mesure">SOR : <input type="number" id="em_mi_fproth_sor" placeholder="mm²" step="0.1"></div>
                        <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_mi_fproth" onchange="exclusifGroup(this)" value="dysfonction par fuite prothèse mitrale légère"> Légère</label>
                        <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_mi_fproth" onchange="exclusifGroup(this)" value="dysfonction par fuite prothèse mitrale modérée"> Modérée</label>
                        <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_mi_fproth" onchange="exclusifGroup(this)" value="dysfonction par fuite prothèse mitrale sévère"> Sévère</label>
                        <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_mi_fproth" onchange="exclusifGroup(this)" value="fuite paraprothétique mitrale"> Fuite paraprothétique</label>
                    </div>
                    <label class="echo-lbl"><input type="checkbox" class="cmlm-ep" data-target="es_mi_degen" onchange="toggleCmlmSub(this)"> Dégénérescence de bioprothèse</label>
                    <div id="es_mi_degen" class="echo-sub3" style="display:none;">
                        <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_mi_degen" onchange="exclusifGroup(this)" value="dégénérescence bioprothèse mitrale légère"> Légère</label>
                        <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_mi_degen" onchange="exclusifGroup(this)" value="dégénérescence bioprothèse mitrale modérée"> Modérée</label>
                        <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_mi_degen" onchange="exclusifGroup(this)" value="dégénérescence bioprothèse mitrale sévère"> Sévère</label>
                    </div>
                    <label class="echo-lbl"><input type="checkbox" class="cmlm-ep" data-target="es_mi_endo" onchange="toggleCmlmSub(this)"> Endocardite sur prothèse</label>
                    <div id="es_mi_endo" class="echo-sub3" style="display:none;">
                        <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_mi_endo" onchange="exclusifGroup(this)" value="endocardite sur prothèse mitrale non compliquée"> Non compliquée</label>
                        <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_mi_endo" onchange="exclusifGroup(this)" value="endocardite sur prothèse mitrale compliquée"> Compliquée</label>
                    </div>
                    <label class="echo-lbl"><input type="checkbox" class="cmlm-ep" data-target="es_mi_desin" onchange="toggleCmlmSub(this)"> Désinsertion de prothèse</label>
                    <div id="es_mi_desin" class="echo-sub3" style="display:none;">
                        <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_mi_desin" onchange="exclusifGroup(this)" value="désinsertion partielle prothèse mitrale"> Partielle</label>
                        <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_mi_desin" onchange="exclusifGroup(this)" value="désinsertion importante prothèse mitrale"> Importante</label>
                    </div>
                    <label class="echo-lbl"><input type="checkbox" class="cmlm-ep" data-target="es_mi_discord" onchange="toggleCmlmSub(this)"> Discordance patient–prothèse</label>
                    <div id="es_mi_discord" class="echo-sub3" style="display:none;">
                        <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_mi_discord" onchange="exclusifGroup(this)" value="discordance patient–prothèse mitrale légère"> Légère</label>
                        <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_mi_discord" onchange="exclusifGroup(this)" value="discordance patient–prothèse mitrale modérée"> Modérée</label>
                        <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_mi_discord" onchange="exclusifGroup(this)" value="discordance patient–prothèse mitrale sévère"> Sévère</label>
                    </div>
                    <div style="font-size:10px;color:#1a4a7a;font-weight:bold;margin-top:3px;">Conclusion :</div>
                    <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_mi_concl" onchange="exclusifGroup(this)" value="dysfonction prothèse mitrale non significative"> Dysfonction non significative</label>
                    <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_mi_concl" onchange="exclusifGroup(this)" value="dysfonction prothèse mitrale significative"> Dysfonction significative</label>
                    <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_mi_concl" onchange="exclusifGroup(this)" value="dysfonction sévère prothèse mitrale nécessitant avis spécialisé"> Dysfonction sévère — avis spécialisé</label>
                    <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_mi_concl" onchange="exclusifGroup(this)" value="urgence prothèse mitrale (thrombose obstructive / endocardite compliquée / désinsertion majeure)"> Urgence</label>
                </div>
            </div>
        </div>

        <!-- ══ VALVULAIRE TRICUSPIDE ══ -->
        <div class="echo-section" onclick="toggleEchoSub('es_tr')"><label style="display:flex;align-items:center;gap:4px;cursor:pointer;margin:0;"><input type="checkbox" class="cmlm-ep" data-target="es_tr" onchange="toggleCmlmSub(this)" style="width:12px;height:12px;cursor:pointer;"> Valvulaire Tricuspide </label><span id="es_tr_arr">▶</span></div>
        <div id="es_tr" style="display:none;">
            <div class="echo-mesure">HTAP : <input type="number" id="em_htap_tr" name="HTAP_TR" placeholder="mmHg" step="0.1"></div>
            <label class="echo-lbl echo-sub"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_tr_ft" onchange="exclusifGroup(this)" value="fuite tricuspide modérée"> Fuite tricuspide modérée</label>
            <label class="echo-lbl echo-sub"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_tr_ft" onchange="exclusifGroup(this)" value="fuite tricuspide moyenne"> Fuite tricuspide moyenne</label>
            <label class="echo-lbl echo-sub"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_tr_ft" onchange="exclusifGroup(this)" value="fuite tricuspide sévère"> Fuite tricuspide sévère</label>
            <label class="echo-lbl echo-sub"><input type="checkbox" class="cmlm-ep" data-target="es_tr_proth" onchange="toggleCmlmSub(this)"> Prothèse valvulaire tricuspide</label>
            <div id="es_tr_proth" class="echo-sub2" style="display:none;">
                <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_tr_proth" onchange="exclusifGroup(this)" value="annuloplastie tricuspidienne"> Annuloplastie tricuspidienne</label>
                <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_tr_proth" onchange="exclusifGroup(this)" value="réparation tricuspidienne"> Réparation</label>
            </div>
        </div>

        <!-- ══ VALVULAIRE PULMONAIRE ══ -->
        <div class="echo-section" onclick="toggleEchoSub('es_pu')"><label style="display:flex;align-items:center;gap:4px;cursor:pointer;margin:0;"><input type="checkbox" class="cmlm-ep" data-target="es_pu" onchange="toggleCmlmSub(this)" style="width:12px;height:12px;cursor:pointer;"> Valvulaire Pulmonaire </label><span id="es_pu_arr">▶</span></div>
        <div id="es_pu" style="display:none;">
            <label class="echo-lbl echo-sub"><input type="checkbox" class="cmlm-ep" data-target="es_pu_valv" onchange="toggleCmlmSub(this)"> Sténose valvulaire pulmonaire</label>
            <div id="es_pu_valv" class="echo-sub2" style="display:none;">
                <div class="echo-mesure">Grad moy : <input type="number" id="em_grad_pu_valv" name="GRAD_PU" placeholder="mmHg" step="0.1"></div>
                <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_pu_valv" onchange="exclusifGroup(this)" value="sténose valvulaire pulmonaire modérée"> modérée</label>
                <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_pu_valv" onchange="exclusifGroup(this)" value="sténose valvulaire pulmonaire moyenne"> moyenne</label>
                <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_pu_valv" onchange="exclusifGroup(this)" value="sténose valvulaire pulmonaire sévère"> sévère</label>
            </div>
            <label class="echo-lbl echo-sub"><input type="checkbox" class="cmlm-ep" data-target="es_pu_infund" onchange="toggleCmlmSub(this)"> Sténose infundibulaire pulmonaire</label>
            <div id="es_pu_infund" class="echo-sub2" style="display:none;">
                <div class="echo-mesure">Grad moy : <input type="number" id="em_grad_pu_infund" placeholder="mmHg" step="0.1"></div>
                <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_pu_infund" onchange="exclusifGroup(this)" value="sténose infundibulaire pulmonaire modérée"> modérée</label>
                <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_pu_infund" onchange="exclusifGroup(this)" value="sténose infundibulaire pulmonaire moyenne"> moyenne</label>
                <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_pu_infund" onchange="exclusifGroup(this)" value="sténose infundibulaire pulmonaire sévère"> sévère</label>
            </div>
        </div>

        <!-- ══ CARDIOPATHIE DILATÉE ══ -->
        <div class="echo-section" onclick="toggleEchoSub('es_dil')"><label style="display:flex;align-items:center;gap:4px;cursor:pointer;margin:0;"><input type="checkbox" class="cmlm-ep" data-target="es_dil" onchange="toggleCmlmSub(this)" style="width:12px;height:12px;cursor:pointer;"> Cardiopathie Dilatée </label><span id="es_dil_arr">▶</span></div>
        <div id="es_dil" style="display:none;">
            <div class="echo-mesure">
                FEVG : <input type="number" id="em_fevg2" name="FEVG" placeholder="%" step="0.1" oninput="reporterFEVG()">
                DTD-VG : <input type="number" id="em_DTD" name="DTD_VG" placeholder="mm">
                DTS-VG : <input type="number" id="em_DTS" name="DTS_VG" placeholder="mm">
                SIV : <input type="number" id="inp_SIV2" name="SIV" placeholder="mm" step="0.1" oninput="reporterSIV()">
                Global Strain : <input type="number" id="em_gstrain" name="GLOBAL_STRAIN" placeholder="%" step="0.1">
            </div>
            <label class="echo-lbl echo-sub"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_dil_fevg" onchange="exclusifGroup(this)" value="IC à FEVG réduite (< 40%)"> IC à FEVG réduite (&lt; 40%)</label>
            <label class="echo-lbl echo-sub"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_dil_fevg" onchange="exclusifGroup(this)" value="IC à FEVG modérément altérée (40-49%)"> IC à FEVG modérément altérée (40-49%)</label>
            <label class="echo-lbl echo-sub"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_dil_fevg" onchange="exclusifGroup(this)" value="IC à FEVG préservée (≥ 50%)"> IC à FEVG préservée (≥ 50%)</label>
            <label class="echo-lbl echo-sub"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_dil_fevg" onchange="exclusifGroup(this)" value="IC droite"> IC droite</label>
            <label class="echo-lbl echo-sub"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_dil_fevg" onchange="exclusifGroup(this)" value="IC globale"> IC globale</label>
        </div>

        <!-- ══ CARDIOPATHIE HYPERTROPHIQUE ══ -->
        <div class="echo-section" onclick="toggleEchoSub('es_hypert')"><label style="display:flex;align-items:center;gap:4px;cursor:pointer;margin:0;"><input type="checkbox" class="cmlm-ep" data-target="es_hypert" onchange="toggleCmlmSub(this)" style="width:12px;height:12px;cursor:pointer;"> Cardiopathie Hypertrophique </label><span id="es_hypert_arr">▶</span></div>
        <div id="es_hypert" style="display:none;">
            <div class="echo-mesure">
                SIV : <input type="number" id="em_siv_hypert" placeholder="mm" step="0.1">
                Grad moy : <input type="number" id="em_grad_hypert" placeholder="mmHg" step="0.1">
            </div>
            <label class="echo-lbl echo-sub"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_hypert" onchange="exclusifGroup(this)" value="cardiopathie hypertrophique obstructive"> Obstructive</label>
            <label class="echo-lbl echo-sub"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_hypert" onchange="exclusifGroup(this)" value="cardiopathie hypertrophique non obstructive"> Non obstructive</label>
            <label class="echo-lbl echo-sub"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_hypert" onchange="exclusifGroup(this)" value="cardiopathie restrictive"> Restrictive</label>
            <label class="echo-lbl echo-sub"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_hypert" onchange="exclusifGroup(this)" value="amylose cardiaque"> Amylose cardiaque</label>
        </div>

        <!-- ══ CARDIOPATHIE HYPERTENSIVE ══ -->
        <div class="echo-section" onclick="toggleEchoSub('es_hta')"><label style="display:flex;align-items:center;gap:4px;cursor:pointer;margin:0;"><input type="checkbox" class="cmlm-ep" data-target="es_hta" onchange="toggleCmlmSub(this)" style="width:12px;height:12px;cursor:pointer;"> Cardiopathie Hypertensive </label><span id="es_hta_arr">▶</span></div>
        <div id="es_hta" style="display:none;">
            <div class="echo-mesure">SIV : <input type="number" id="em_siv_hta" placeholder="mm" step="0.1" oninput="reporterSIV()"></div>
            <label class="echo-lbl echo-sub"><input type="checkbox" class="cmlm-ec" id="ce_hta_cb2" value="cardiopathie hypertensive"> Cardiopathie hypertensive</label>
        </div>

        <!-- ══ PÉRICARDE ══ -->
        <div class="echo-section" onclick="toggleEchoSub('es_peri')"><label style="display:flex;align-items:center;gap:4px;cursor:pointer;margin:0;"><input type="checkbox" class="cmlm-ep" data-target="es_peri" onchange="toggleCmlmSub(this)" style="width:12px;height:12px;cursor:pointer;"> Péricarde </label><span id="es_peri_arr">▶</span></div>
        <div id="es_peri" style="display:none;">
            <div class="echo-mesure">Diam. épanch. : <input type="number" id="em_diam_peric" name="DIAM_PERIC" placeholder="mm" step="0.1"></div>
            <label class="echo-lbl echo-sub"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_peri" onchange="exclusifGroup(this)" value="péricarde sec"> sec</label>
            <label class="echo-lbl echo-sub"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_peri" onchange="exclusifGroup(this)" value="décollement systolique"> décollement systolique</label>
            <label class="echo-lbl echo-sub"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_peri" onchange="exclusifGroup(this)" value="décollement systolodiastolique"> décollement systolodiastolique</label>
            <label class="echo-lbl echo-sub"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_peri" onchange="exclusifGroup(this)" value="épanchement péricardique minime (< 10mm, < 100mL)"> Épanchement — minime (&lt; 10mm)</label>
            <label class="echo-lbl echo-sub"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_peri" onchange="exclusifGroup(this)" value="épanchement péricardique modéré (10-20mm, 100-500mL)"> Épanchement — modéré (10-20mm)</label>
            <label class="echo-lbl echo-sub"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_peri" onchange="exclusifGroup(this)" value="épanchement péricardique sévère (> 20mm, > 500mL)"> Épanchement — sévère (&gt; 20mm)</label>
        </div>

        <!-- ══ OREILLETTES ══ -->
        <div class="echo-section" onclick="toggleEchoSub('es_oreil')"><label style="display:flex;align-items:center;gap:4px;cursor:pointer;margin:0;"><input type="checkbox" class="cmlm-ep" data-target="es_oreil" onchange="toggleCmlmSub(this)" style="width:12px;height:12px;cursor:pointer;"> Oreillettes </label><span id="es_oreil_arr">▶</span></div>
        <div id="es_oreil" style="display:none;">
            <div class="echo-mesure">
                OG : <input type="number" id="em_og_surf" name="OG_SURF" placeholder="cm²" step="0.01">
                OD : <input type="number" id="em_od_surf" name="OD_SURF" placeholder="cm²" step="0.01">
            </div>
            <label class="echo-lbl echo-sub"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_oreil" onchange="exclusifGroup(this)" value="oreillettes non dilatées"> non dilatées</label>
            <label class="echo-lbl echo-sub"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_oreil" onchange="exclusifGroup(this)" value="dilatées : massif auriculaire dilaté"> dilatées : massif auriculaire dilaté</label>
            <label class="echo-lbl echo-sub"><input type="checkbox" class="cmlm-ec" value="contraste spontané"> contraste spontané</label>
            <label class="echo-lbl echo-sub"><input type="checkbox" class="cmlm-ec" value="thrombus intra-auriculaire droit"> thrombus intra-auriculaire droit</label>
            <label class="echo-lbl echo-sub"><input type="checkbox" class="cmlm-ec" value="thrombus intra-auriculaire gauche"> thrombus intra-auriculaire gauche</label>
        </div>

        <!-- ══ VCI ══ -->
        <div class="echo-section" onclick="toggleEchoSub('es_vci')"><label style="display:flex;align-items:center;gap:4px;cursor:pointer;margin:0;"><input type="checkbox" class="cmlm-ep" data-target="es_vci" onchange="toggleCmlmSub(this)" style="width:12px;height:12px;cursor:pointer;"> VCI </label><span id="es_vci_arr">▶</span></div>
        <div id="es_vci" style="display:none;">
            <div class="echo-mesure">Diam. : <input type="number" id="em_diam_vci" name="DIAM_VCI" placeholder="mm" step="0.1"></div>
            <label class="echo-lbl echo-sub"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_vci" onchange="exclusifGroup(this)" value="VCI non dilatée et compliante"> non dilatée et compliante</label>
            <label class="echo-lbl echo-sub"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_vci" onchange="exclusifGroup(this)" value="VCI dilatée non compliante"> dilatée non compliante</label>
        </div>

        <!-- ══ AORTE INITIALE ══ -->
        <div class="echo-section" onclick="toggleEchoSub('es_aorte')"><label style="display:flex;align-items:center;gap:4px;cursor:pointer;margin:0;"><input type="checkbox" class="cmlm-ep" data-target="es_aorte" onchange="toggleCmlmSub(this)" style="width:12px;height:12px;cursor:pointer;"> Aorte Initiale </label><span id="es_aorte_arr">▶</span></div>
        <div id="es_aorte" style="display:none;">
            <div class="echo-mesure">Diam. : <input type="number" id="em_diam_ao_init" name="DIAM_AO_INIT" placeholder="mm" step="0.1"></div>
            <label class="echo-lbl echo-sub"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_aorte" onchange="exclusifGroup(this)" value="aorte initiale de diamètre normal"> de diamètre normal</label>
            <label class="echo-lbl echo-sub"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_aorte" onchange="exclusifGroup(this)" value="dilatation modérée de l'aorte initiale"> dilatation modérée</label>
            <label class="echo-lbl echo-sub"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_aorte" onchange="exclusifGroup(this)" value="dilatation anévrysmale de l'aorte initiale"> dilatation anévrysmale</label>
        </div>

        <!-- ══ CARDIOPATHIE ISCHÉMIQUE ══ -->
        <div class="echo-section" onclick="toggleEchoSub('es_isch')"><label style="display:flex;align-items:center;gap:4px;cursor:pointer;margin:0;"><input type="checkbox" class="cmlm-ep" data-target="es_isch" onchange="toggleCmlmSub(this)" style="width:12px;height:12px;cursor:pointer;"> Cardiopathie Ischémique </label><span id="es_isch_arr">▶</span></div>
        <div id="es_isch" style="display:none;">
            <label class="echo-lbl echo-sub"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_isch_cin" onchange="exclusifGroup(this)" value="cinétique globale et régionale normale"> Cinétique normale</label>
            <label class="echo-lbl echo-sub"><input type="checkbox" class="cmlm-ep excl1" data-group="g2_isch_cin" data-target="es_isch_detail" onchange="exclusifGroup(this);toggleCmlmSub(this)"> Trouble de la cinétique</label>
            <div id="es_isch_detail" class="echo-sub2" style="display:none;">
                <!-- Hypokinésie -->
                <label class="echo-lbl"><input type="checkbox" class="cmlm-ep" data-target="es_hypo" onchange="toggleCmlmSub(this)"> Hypokinésie</label>
                <div id="es_hypo" class="echo-sub2" style="display:none;">
                    <?php foreach (["Antérieur","Apical","Inférieur","Latéral","Postérieur"] as $t): ?>
                    <label class="echo-lbl"><input type="checkbox" class="cmlm-ec" value="hypokinésie <?= strtolower($t) ?>"> <?= $t ?></label>
                    <?php endforeach; ?>
                    <button type="button" onclick="appliquerMultiple('es_hypo')" style="margin-top:3px;background:#1a4a7a;color:white;border:none;border-radius:3px;padding:2px 10px;font-size:10px;cursor:pointer;">✓ OK</button>
                </div>
                <!-- Akinésie -->
                <label class="echo-lbl"><input type="checkbox" class="cmlm-ep" data-target="es_aki" onchange="toggleCmlmSub(this)"> Akinésie</label>
                <div id="es_aki" class="echo-sub2" style="display:none;">
                    <?php foreach (["Antérieur","Apical","Inférieur","Latéral","Postérieur"] as $t): ?>
                    <label class="echo-lbl"><input type="checkbox" class="cmlm-ec" value="akinésie <?= strtolower($t) ?>"> <?= $t ?></label>
                    <?php endforeach; ?>
                    <button type="button" onclick="appliquerMultiple('es_aki')" style="margin-top:3px;background:#1a4a7a;color:white;border:none;border-radius:3px;padding:2px 10px;font-size:10px;cursor:pointer;">✓ OK</button>
                </div>
                <!-- Dyskinésie -->
                <label class="echo-lbl"><input type="checkbox" class="cmlm-ep" data-target="es_dysk" onchange="toggleCmlmSub(this)"> Dyskinésie</label>
                <div id="es_dysk" class="echo-sub2" style="display:none;">
                    <?php foreach (["Antérieur","Apical","Inférieur","Latéral","Postérieur"] as $t): ?>
                    <label class="echo-lbl"><input type="checkbox" class="cmlm-ec" value="dyskinésie <?= strtolower($t) ?>"> <?= $t ?></label>
                    <?php endforeach; ?>
                    <button type="button" onclick="appliquerMultiple('es_dysk')" style="margin-top:3px;background:#1a4a7a;color:white;border:none;border-radius:3px;padding:2px 10px;font-size:10px;cursor:pointer;">✓ OK</button>
                </div>
            </div>
            <!-- Takotsubo -->
            <label class="echo-lbl echo-sub" style="margin-top:4px;"><input type="checkbox" class="cmlm-ep" data-target="es_tako" onchange="toggleCmlmSub(this)"> Syndrome de Takotsubo</label>
            <div id="es_tako" class="echo-sub2" style="display:none;">
                <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_tako" onchange="exclusifGroup(this)" value="syndrome de Takotsubo présent"> présent</label>
                <label class="echo-lbl"><input type="checkbox" class="cmlm-ec excl1" data-group="g2_tako" onchange="exclusifGroup(this)" value="syndrome de Takotsubo absent"> absent</label>
            </div>
        </div>

        </div><!-- fin cmlm_echo_detail -->
    </div><!-- fin panel_echo_cases -->

    <input type="hidden" name="CMLM_ECHO" id="cmlm_echo_val">

    <!-- Champs cachés exclusion Echo -->
    <input type="hidden" id="excl_DTSA"        name="excl_DTSA">
    <input type="hidden" id="excl_CONCLUSION1" name="excl_CONCLUSION1">

    <div class="champ" id="wrap_DTSA">
        <div class="label-excl"><label>DTSA</label><button type="button" class="btn-excl" onclick="toggleExcl('DTSA')" title="Exclure du rapport">−</button></div>
        <textarea name="DTSA" class="court" oninput="majConcatEcho()"></textarea>
    </div>

    <!-- Aperçu Echo -->
    <div class="champ" id="wrap_CONCLUSION1" style="margin-top:6px;">
        <div class="label-excl">
            <label style="font-size:10px;color:#2e6da4;font-weight:bold;">👁 Aperçu rapport Echo</label>
            <button type="button" class="btn-excl" onclick="toggleExcl('CONCLUSION1')" title="Exclure du rapport">−</button>
        </div>
        <textarea name="CONCLUSION1" id="conclusion1_echo"
            style="min-height:80px;background:#f0f7ff;border:1px solid #2e6da4;font-size:11px;color:#1a4a7a;resize:none;overflow:hidden;width:100%;padding:4px 6px;border-radius:3px;font-family:Arial,sans-serif;"
            oninput="majApercuEcho(); autoResize(this)"></textarea>
    </div>
    </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════
     COLONNE 3 : SYNTHÈSE CLINIQUE (lecture seule)
══════════════════════════════════════════════ -->
<div class="col-card">

    <!-- Motif -->
    <div style="margin-bottom:6px;padding-bottom:5px;border-bottom:1px solid #e8eef5;">
        <div style="font-size:11px;font-weight:bold;color:#1a4a7a;margin-bottom:3px;">📋 Motif</div>
        <div style="font-size:11px;color:#333;line-height:1.5;white-space:pre-wrap;min-height:16px;"><?= htmlspecialchars(trim($patient['MDC'] ?? '')) ?: '<span style="color:#bbb;font-style:italic;">—</span>' ?></div>
    </div>

    <!-- Antécédents -->
    <div style="margin-bottom:6px;padding-bottom:5px;border-bottom:1px solid #e8eef5;">
        <div style="font-size:11px;font-weight:bold;color:#1a4a7a;margin-bottom:3px;">📂 Antécédents</div>
        <div style="font-size:11px;color:#333;line-height:1.5;white-space:pre-wrap;min-height:16px;"><?= htmlspecialchars(trim($patient['ATCD'] ?? '')) ?: '<span style="color:#bbb;font-style:italic;">—</span>' ?></div>
    </div>

    <!-- Facteurs de risque -->
    <div style="margin-bottom:6px;padding-bottom:5px;border-bottom:1px solid #e8eef5;">
        <div style="font-size:11px;font-weight:bold;color:#1a4a7a;margin-bottom:3px;">⚠️ Facteurs de risque</div>
        <div style="font-size:11px;color:#333;line-height:1.5;white-space:pre-wrap;min-height:16px;"><?= htmlspecialchars(trim($patient['CHAMP_FDR'] ?? '')) ?: '<span style="color:#bbb;font-style:italic;">—</span>' ?></div>
    </div>

    <!-- Diagnostic -->
    <div style="margin-bottom:6px;padding-bottom:5px;border-bottom:1px solid #e8eef5;">
        <div style="font-size:11px;font-weight:bold;color:#1a4a7a;margin-bottom:3px;">🩺 Diagnostic</div>
        <div style="font-size:11px;color:#1a4a7a;font-weight:bold;line-height:1.5;white-space:pre-wrap;min-height:16px;"><?= htmlspecialchars(trim($patient['diagnostic'] ?? '')) ?: '<span style="color:#bbb;font-weight:normal;font-style:italic;">—</span>' ?></div>
    </div>

    <!-- Au total — Conduite à tenir -->
    <div>
        <div style="font-size:11px;font-weight:bold;color:#1a4a7a;margin-bottom:3px;">🎯 Au total — Conduite à tenir</div>
        <?php $cat = trim($examen['Conduite_ATenir'] ?? ''); if ($cat): ?>
        <div style="font-size:11px;color:#155724;background:#d4edda;border:1px solid #c3e6cb;border-radius:3px;padding:4px 6px;line-height:1.5;white-space:pre-wrap;"><?= htmlspecialchars($cat) ?></div>
        <?php else: ?>
        <div style="color:#bbb;font-size:11px;font-style:italic;">—</div>
        <?php endif; ?>
    </div>

</div>

</div><!-- FIN cols -->


<script>
/* ══════════════════════════════════════════════════════
   VARIABLES GLOBALES
══════════════════════════════════════════════════════ */
var exclusions     = {};   // champs exclus des colonnes Examen / ECG
var exclusionsEcho = {};   // champs numériques exclus de l'Echo
var echoMode       = 'normal'; // 'normal' ou 'anormal'

// Listes des champs par colonne (pour les labels "exclu")
var champsExamen = ['S_Fonctionnels','Auscult_Cardiaque','Auscult_Pulmonaire',
                    'Examen_Vasculaire','Signes_IVG','Signes_IVD','Autres_Symptomes',
                    'Conclusion','REMARQUE'];
var champsECG    = ['rythme_sv','trouble_rv','conduction_nodale','QRS',
                    'infrastructure_de_conduction','REPOLARISATION','CC'];
var champsEcho   = ['DOPPLER','DTSA','CONCLUSION1'];

/* ── Mettre à jour le label "N champ(s) exclu(s)" ── */
function majLabelExclu(labelId, champs) {
    var lbl = document.getElementById(labelId);
    if (!lbl) return;
    var nb = champs.filter(function(c){ return exclusions[c]; }).length;
    lbl.style.display = nb > 0 ? 'inline' : 'none';
    lbl.textContent   = nb > 0 ? '— ' + nb + ' champ' + (nb > 1 ? 's exclus' : ' exclu') + ' du rapport' : '';
}

/* Label Echo : compte exclusions (DOPPLER/DTSA/CONCLUSION1) + exclusionsEcho (champs numériques) */
function majLabelExcluEcho() {
    var lbl = document.getElementById('lbl_exclu_echo');
    if (!lbl) return;
    var nb1 = champsEcho.filter(function(c){ return exclusions[c]; }).length;
    var nb2 = Object.keys(exclusionsEcho).length;
    var nb  = nb1 + nb2;
    lbl.style.display = nb > 0 ? 'inline' : 'none';
    lbl.textContent   = nb > 0 ? '— ' + nb + ' champ' + (nb > 1 ? 's exclus' : ' exclu') + ' du rapport' : '';
}

/* ── Bouton ➕/➖ champs Examen/ECG ── */
function toggleExcl(nom) {
    var wrap = document.getElementById('wrap_' + nom);
    var btn  = wrap ? wrap.querySelector('.btn-excl') : null;
    if (!wrap || !btn) return;
    if (exclusions[nom]) {
        delete exclusions[nom];
        wrap.classList.remove('exclu-champ');
        btn.classList.remove('exclu'); btn.textContent = '−'; btn.title = 'Exclure du rapport';
    } else {
        exclusions[nom] = true;
        wrap.classList.add('exclu-champ');
        btn.classList.add('exclu'); btn.textContent = '+'; btn.title = 'Réintégrer dans le rapport';
    }
    var hid = document.getElementById('excl_' + nom);
    if (hid) hid.value = exclusions[nom] ? '1' : '';
    majLabelExclu('lbl_exclu_examen', champsExamen);
    majLabelExclu('lbl_exclu_ecg',    champsECG);
    majLabelExcluEcho();
    majApercuExamen();
    majApercuECG();
    if (echoMode === 'anormal') majConcatEcho(); else majApercuEcho();
}

/* ── Bouton ➕/➖ champs numériques Echo ── */
function toggleExclEcho(nom) {
    var wrap = document.getElementById('wrap_echo_' + nom);
    var btn  = wrap ? wrap.querySelector('.btn-excl') : null;
    if (!wrap || !btn) return;
    if (exclusionsEcho[nom]) {
        delete exclusionsEcho[nom];
        wrap.classList.remove('exclu-champ');
        btn.classList.remove('exclu'); btn.textContent = '−';
    } else {
        exclusionsEcho[nom] = true;
        wrap.classList.add('exclu-champ');
        btn.classList.add('exclu'); btn.textContent = '+';
    }
    majLabelExcluEcho();
    if (echoMode === 'anormal') majConcatEcho(); else majApercuEcho();
}

/* ══════════════════════════════════════════════════════
   APERÇU EXAMEN
══════════════════════════════════════════════════════ */
function majApercuExamen() {
    /* Affiche TAS/TAD/FC/POIDS en tête de l'aperçu en temps réel */
    var ap = document.getElementById('apercu_examen');
    if (!ap) return;
    var tas   = document.getElementById('inp_TAS');
    var tad   = document.getElementById('inp_TAD');
    var fc    = document.getElementById('inp_FC');
    var poids = document.getElementById('inp_POIDS');
    var mesure = '';
    if (tas && tas.value)   mesure += 'TA : ' + tas.value;
    if (tad && tad.value)   mesure += '/' + tad.value + ' mmHg';
    if (fc  && fc.value)    mesure += (mesure ? ' — ' : '') + 'FC : ' + fc.value + ' bpm';
    if (poids && poids.value) mesure += (mesure ? ' — ' : '') + 'Poids : ' + poids.value + ' kg';
    ap.value = mesure || '—';
}

/* ══════════════════════════════════════════════════════
   APERÇU ECG
══════════════════════════════════════════════════════ */
function majApercuECG() {
    var ap = document.getElementById('apercu_ecg');
    if (!ap) return;
    var g = function(n){
        var e = document.querySelector('input[type="text"][name='+n+'], textarea[name='+n+'], select[name='+n+']');
        return e ? e.value.trim() : '';
    };
    var p = [];
    var rsv = g('rythme_sv'), trv = g('trouble_rv'), freq = g('FREQUENCE');
    if (!exclusions['rythme_sv']  && rsv)  p.push('Rythme : ' + rsv);
    if (!exclusions['trouble_rv'] && trv)  p.push(trv);
    if (freq) p.push('FC : ' + freq + ' bat/min');
    var cn = g('conduction_nodale');
    if (!exclusions['conduction_nodale'] && cn) p.push('Conduction AV : ' + cn);
    var qrs = g('QRS');
    if (!exclusions['QRS'] && qrs) p.push('QRS : ' + qrs);
    var inf = g('infrastructure_de_conduction');
    if (!exclusions['infrastructure_de_conduction'] && inf) p.push(inf);
    var rep = g('REPOLARISATION');
    if (!exclusions['REPOLARISATION'] && rep) p.push('Repolarisation : ' + rep);
    ap.value = p.length > 0 ? p.map(function(x){ return '- ' + x; }).join('\n') : '—';
}

/* ══════════════════════════════════════════════════════
   APERÇU ECHO (fusionné avec la Conclusion)
══════════════════════════════════════════════════════ */
function majApercuEcho() {
    // La zone conclusion1_echo est l'aperçu — rien à faire ici
    // (appelée par majConcatEcho pour compatibilité)
}

/* ── Concaténation automatique en mode Anormal ── */
function majConcatEcho() {
    if (echoMode !== 'anormal') { majApercuEcho(); return; }
    var g = function(n){ var e=document.querySelector('[name='+n+']'); return e ? e.value.trim() : ''; };
    var p = [];
    [{n:'FEVG',l:'FE',u:'%'},{n:'DTD_VG',l:'DTD-VG',u:'mm'},{n:'DTS_VG',l:'DTS-VG',u:'mm'},
     {n:'SIV',l:'SIV',u:'mm'},{n:'PP',l:'PP',u:'mm'},{n:'RACINE_AO',l:'Racine Ao',u:'mm'},
     {n:'HTAP',l:'HTAP',u:''},{n:'CINETIQUE',l:'Cinétique',u:''}
    ].forEach(function(s) {
        if (exclusionsEcho[s.n]) return;
        var v = g(s.n); if (!v) return;
        p.push(s.l + ' : ' + v + s.u);
    });
    if (!exclusions['DOPPLER']) { var d=g('DOPPLER'); if(d) p.push('Doppler : '+d); }
    if (!exclusions['DTSA'])    { var t=g('DTSA');    if(t) p.push('DTSA : '+t); }
    var c1 = document.getElementById('conclusion1_echo');
    if (c1) { c1.value = p.length > 0 ? p.map(function(x){ return '- ' + x; }).join('\n') : ''; autoResize(c1); }
    majApercuEcho();
}


/* ══════════════════════════════════════════════════════
   PRESET EXAMEN
══════════════════════════════════════════════════════ */

function setExamenNormal() {
    modeExamen = 'normal';
    var bn = document.getElementById('btn_exam_normal');
    var ba = document.getElementById('btn_exam_anormal');
    if(bn){ bn.style.background='#27ae60'; bn.style.color='white'; }
    if(ba){ ba.style.background='white';   ba.style.color='#e67e22'; }
    var bln = document.getElementById('bloc_normal');
    var bla = document.getElementById('sympto_cases');
    if(bln) bln.style.display = 'block';
    if(bla) bla.style.display = 'none';
    var ct = document.getElementById('conduite_textarea');
    if(ct) ct.value = '';
    var ap = document.getElementById('apercu_examen');
    if(ap) ap.value = '—';
}

function setExamenAnormal() {
    modeExamen = 'anormal';
    var bn = document.getElementById('btn_exam_normal');
    var ba = document.getElementById('btn_exam_anormal');
    if(ba){ ba.style.background='#e67e22'; ba.style.color='white'; }
    if(bn){ bn.style.background='white';   bn.style.color='#27ae60'; }
    var bln = document.getElementById('bloc_normal');
    var bla = document.getElementById('sympto_cases');
    if(bln) bln.style.display = 'none';
    if(bla) bla.style.display = 'block';
    var ct = document.getElementById('conduite_textarea');
    if(ct) ct.value = '';
    var ap = document.getElementById('apercu_examen');
    if(ap) ap.value = '—';
}

/* ── Générer depuis le bloc Normal ── */
function genererConclusionNormal() {
    var parties = [];
    var tas = document.getElementById('inp_TAS');
    var tad = document.getElementById('inp_TAD');
    var fc  = document.getElementById('inp_FC');
    var mesure = '';
    if(tas && tas.value) mesure += 'TA : ' + tas.value;
    if(tad && tad.value) mesure += '/' + tad.value + ' mmHg';
    if(fc  && fc.value)  mesure += (mesure ? ' — ' : '') + 'FC : ' + fc.value + ' bpm';
    if(mesure) parties.push(mesure);
    ['n_sympto','n_auscult','n_oedemes','n_vasc'].forEach(function(id){
        var cb = document.getElementById(id);
        if(cb && cb.checked) parties.push(cb.value);
    });
    var ap = document.getElementById('apercu_examen');
    if(ap) { ap.value = parties.length > 0 ? parties.map(function(p){ return '- ' + p; }).join('\n') : '—'; autoResize(ap); }
    var condParts = [];
    var ecvn = document.getElementById('cat_ecvn');
    var apte = document.getElementById('cat_apte');
    if(ecvn && ecvn.checked) condParts.push(ecvn.value);
    else if(apte && apte.checked) condParts.push(apte.value);
    document.querySelectorAll('.cat-check:checked').forEach(function(cb){ if(cb.value) condParts.push(cb.value); });
    var autresN = document.getElementById('cat_autres_n');
    if(autresN && autresN.value.trim()) condParts.push(autresN.value.trim());
    var ct = document.getElementById('conduite_textarea');
    if(ct && condParts.length > 0) ct.value = condParts.map(function(p){ return '- ' + p; }).join('\n');
    // Ne pas cacher panel_sympto — seulement les listes (géré par le bouton onclick)
}

/* ── Exclusion mutuelle bloc Normal ── */
function syncCat(cb) {
    var ecvn=document.getElementById('cat_ecvn'), apte=document.getElementById('cat_apte');
    var lE=document.getElementById('lbl_cat_ecvn'), lA=document.getElementById('lbl_cat_apte');
    if(!ecvn||!apte) return;
    if(cb===ecvn&&ecvn.checked){apte.checked=false;if(lA){lA.style.opacity='0.4';lA.style.display='none';}if(lE){lE.style.opacity='1';lE.style.display='block';}}
    else if(cb===apte&&apte.checked){ecvn.checked=false;if(lE){lE.style.opacity='0.4';lE.style.display='none';}if(lA){lA.style.opacity='1';lA.style.display='block';}}
    else{if(lE){lE.style.opacity='1';lE.style.display='block';}if(lA){lA.style.opacity='1';lA.style.display='block';}}
}

/* ── Exclusion mutuelle bloc Anormal ── */
function syncCat2(cb) {
    var ecvn=document.getElementById('cat_ecvn2'), apte=document.getElementById('cat_apte2');
    var lE=document.getElementById('lbl_cat_ecvn2'), lA=document.getElementById('lbl_cat_apte2');
    if(!ecvn||!apte) return;
    if(cb===ecvn&&ecvn.checked){apte.checked=false;if(lA){lA.style.opacity='0.4';lA.style.display='none';}if(lE){lE.style.opacity='1';lE.style.display='block';}}
    else if(cb===apte&&apte.checked){ecvn.checked=false;if(lE){lE.style.opacity='0.4';lE.style.display='none';}if(lA){lA.style.opacity='1';lA.style.display='block';}}
    else{if(lE){lE.style.opacity='1';lE.style.display='block';}if(lA){lA.style.opacity='1';lA.style.display='block';}}
}

function remplirExamenNormal() { setExamenNormal(); }
function viderExamen() {
    var ct = document.getElementById('conduite_textarea');
    if(ct) ct.value = '';
    var ap = document.getElementById('apercu_examen');
    if(ap) ap.value = '—';
}
function setConclusionECVN() {}
function viderConclusionRemarque() {}

/* ── Auto-resize des zones Aperçu ── */
function autoResize(el) {
    if (!el) return;
    el.style.height = 'auto';
    el.style.height = (el.scrollHeight + 2) + 'px';
}

/* ── Focus clavier TAS → TAD → FC ── */
document.addEventListener('DOMContentLoaded', function() {
    var ordre = ['inp_TAS','inp_TAD','inp_FC'];
    /* Focus Echo : DTD → DTS → FEVG → SIV → PP */
    var ordreEcho = ['em_DTD','em_DTS','inp_FEVG','inp_SIV','em_PP'];
    ordreEcho.forEach(function(id, idx) {
        var el = document.getElementById(id);
        if(!el) return;
        el.addEventListener('keydown', function(e) {
            if(e.key !== 'Enter') return;
            e.preventDefault();
            if(idx + 1 < ordreEcho.length) { var next = document.getElementById(ordreEcho[idx+1]); if(next) next.focus(); }
        });
    });
    ordre.forEach(function(id, idx) {
        var el = document.getElementById(id);
        if(!el) return;
        el.addEventListener('keydown', function(e) {
            if(e.key !== 'Enter') return;
            e.preventDefault();
            if(idx + 1 < ordre.length) { var next = document.getElementById(ordre[idx+1]); if(next) next.focus(); }
        });
    });
    /* Auto-resize au chargement pour valeurs pré-remplies depuis la base */
    ['apercu_examen','apercu_ecg','conclusion1_echo'].forEach(function(id) {
        var el = document.getElementById(id); if (el) autoResize(el);
    });
});


/* ══════════════════════════════════════════════════════
   PRESET ECG
══════════════════════════════════════════════════════ */
function remplirECGNormal() {
    var s=function(n,v){ var e=document.querySelector('[name='+n+']'); if(e) e.value=v; };
    s('FREQUENCE','70'); s('rythme_sv','sinusal'); s('trouble_rv','régulier');
    s('rythme_v','normal'); s('conduction_nodale','normale'); s('QRS','normaux');
    s('infrastructure_de_conduction','conductInfraN normale'); s('REPOLARISATION','normale');
    s('SEGMENT_ST','normal'); s('ONDE_T','normale'); s('IDM','absents'); s('CC','ECG normal');
    majApercuECG();
}
function viderECG() {
    ['FREQUENCE','rythme_sv','trouble_rv','rythme_v','conduction_nodale','QRS',
     'infrastructure_de_conduction','REPOLARISATION','SEGMENT_ST','TOPOGRAPHIE_ST',
     'ONDE_T','TOPOGRAPHIE_T','IDM','TOPOGRAPHIE_Q','CC','AUTRES_SIGNES']
    .forEach(function(n){ var e=document.querySelector('[name='+n+']'); if(e) e.value=''; });
    majApercuECG();
}

/* ══════════════════════════════════════════════════════
   PRESET ECHO
══════════════════════════════════════════════════════ */
function remplirEchoNormal() {
    echoMode = 'normal'; exclusionsEcho = {};
    document.querySelectorAll('[id^=wrap_echo_]').forEach(function(w){
        w.classList.remove('exclu-champ');
        var b=w.querySelector('.btn-excl'); if(b){b.classList.remove('exclu');b.textContent='−';}
    });
    var te = document.getElementById('type_echo_val'); if(te) te.value='Echoscopie cardiaque';
    var s=function(n,v){ var e=document.querySelector('[name='+n+']'); if(e) e.value=v; };
    s('FEVG','60'); s('DTD_VG','50'); s('DTS_VG','32'); s('SIV','9'); s('PP','9');
    s('RACINE_AO','34'); s('HTAP','absente'); s('CINETIQUE','normale'); s('ECHOGENICITE','normale');
    s('DOPPLER','Flux au doppler normal');
    var c1 = document.getElementById('conclusion1_echo');
    if (c1) c1.value = "Absence de dilatation ou d'hypertrophie cavitaire. Flux au doppler : normal. " +
        "Cinetique globale et regionale normale. " +
        "Fonctions du ventricule gauche normale, Absence d'hypertension arterielle pulmonaire. " +
        "Pression de remplissage du ventricule gauche normale. Pericarde sec. " +
        "Oreillettes de volume normal, aorte ascendante de diametre normal.";
    majLabelExcluEcho();
    majApercuEcho();
}
function viderEcho() {
    echoMode = 'anormal'; exclusionsEcho = {};
    document.querySelectorAll('[id^=wrap_echo_]').forEach(function(w){
        w.classList.remove('exclu-champ');
        var b=w.querySelector('.btn-excl'); if(b){b.classList.remove('exclu');b.textContent='−';}
    });
    var te = document.getElementById('type_echo_val'); if(te) te.value='Echographie cardiaque';
    ['FEVG','DTD_VG','DTS_VG','SIV','PP','RACINE_AO','HTAP','CINETIQUE','ECHOGENICITE','DOPPLER','DTSA']
    .forEach(function(n){ var e=document.querySelector('[name='+n+']'); if(e) e.value=''; });
    var c1 = document.getElementById('conclusion1_echo'); if(c1) c1.value='';
    majLabelExcluEcho();
    majApercuEcho();
}

/* ══════════════════════════════════════════════════════
   AJAX — enregistrement sans rechargement
══════════════════════════════════════════════════════ */

// Touche Entrée sur mesures : passe au champ suivant
var mesures = ['TAS','TAD','FC','POIDS','TAILLE'];
mesures.forEach(function(n, idx) {
    var el = document.querySelector('[name='+n+']');
    if (!el) return;
    el.addEventListener('keydown', function(e) {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        var s = mesures[idx+1];
        if (s) { var f=document.querySelector('[name='+s+']'); if(f) f.focus(); }
    });
});

function _collectForm(onglet) {
    // Vider temporairement les champs exclus, collecter FormData, restaurer
    var sauv = {};
    Object.keys(exclusions).forEach(function(n){
        var e=document.querySelector('[name='+n+']');
        if(e){ sauv[n]=e.value; e.value=''; }
    });
    var data = new FormData(document.getElementById('form-'+onglet));
    Object.keys(sauv).forEach(function(n){
        var e=document.querySelector('[name='+n+']'); if(e) e.value=sauv[n];
    });
    return data;
}

function enregistrerAjax(onglet) {
    /* ── Validation TAS / TAD / FC obligatoires pour l'examen ── */
    if (onglet === 'examen') {
        var tas = document.getElementById('inp_TAS');
        var tad = document.getElementById('inp_TAD');
        var fc  = document.getElementById('inp_FC');
        var manquants = [];
        if (!tas || !tas.value.trim()) manquants.push('TAS');
        if (!tad || !tad.value.trim()) manquants.push('TAD');
        if (!fc  || !fc.value.trim())  manquants.push('FC');
        if (manquants.length > 0) {
            /* Mettre en rouge les champs vides */
            [['inp_TAS','TAS'],['inp_TAD','TAD'],['inp_FC','FC']].forEach(function(pair){
                var el = document.getElementById(pair[0]);
                if (el) el.style.border = (!el.value.trim()) ? '2px solid #e74c3c' : '1px solid #e67e22';
            });
            /* Modale bloquante */
            var overlay = document.createElement('div');
            overlay.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.55);z-index:9999;display:flex;align-items:center;justify-content:center;';
            overlay.innerHTML =
                '<div style="background:white;border-radius:8px;padding:28px 32px;max-width:340px;text-align:center;box-shadow:0 8px 32px rgba(0,0,0,0.35);">' +
                '<div style="font-size:40px;margin-bottom:10px;">🚫</div>' +
                '<div style="font-size:15px;font-weight:bold;color:#c0392b;margin-bottom:10px;">Enregistrement impossible</div>' +
                '<div style="font-size:13px;color:#333;margin-bottom:18px;">Les champs suivants sont obligatoires :<br><br>' +
                '<strong style="color:#c0392b;font-size:15px;">' + manquants.join(' — ') + '</strong></div>' +
                '<button onclick="this.closest(\'div[style*=fixed]\') ? document.body.removeChild(this.closest(\'div[style*=fixed]\')) : null; document.getElementById(\'inp_'+manquants[0]+'\').focus();" ' +
                'style="background:#c0392b;color:white;border:none;border-radius:5px;padding:8px 28px;font-size:13px;font-weight:bold;cursor:pointer;">OK</button>' +
                '</div>';
            document.body.appendChild(overlay);
            /* Fermeture aussi par clic sur le fond */
            overlay.addEventListener('click', function(e){ if(e.target===overlay){ document.body.removeChild(overlay); } });
            return; /* Bloquer l'enregistrement */
        }
        /* Rétablir les bordures si OK */
        ['inp_TAS','inp_TAD','inp_FC'].forEach(function(id){
            var el = document.getElementById(id);
            if (el) el.style.border = '1px solid #e67e22';
        });
    }
    var msgEl = document.getElementById('msg_'+onglet);
    if (msgEl){ msgEl.textContent='⏳...'; msgEl.style.display='inline'; msgEl.style.color='#888'; }
    var data = _collectForm(onglet);
    // Forcer CMLM_ECHO pour l'onglet echo
    if (onglet === 'echo') {
        var cmlmEl = document.getElementById('cmlm_echo_val');
        if (cmlmEl) data.set('CMLM_ECHO', cmlmEl.value || '');
    }
    // Forcer CMLM_EXAMEN et CMLM_ECG
    if (onglet === 'examen') {
        var apEx = document.getElementById('apercu_examen');
        if (apEx) data.set('CMLM_EXAMEN', apEx.value || '');
    }
    if (onglet === 'ecg') {
        var apEcg = document.getElementById('apercu_ecg');
        if (apEcg) data.set('CMLM_ECG', apEcg.value || '');
    }
    fetch('nouveau_bilan_clinique.php?id=<?= $id ?>', {
        method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:data
    })
    .then(function(r){ return r.json(); })
    .then(function(resp){
        if(msgEl){ msgEl.textContent=resp.msg||'✅ Enregistré'; msgEl.style.color='#27ae60'; msgEl.style.display='inline';
            setTimeout(function(){ msgEl.style.display='none'; },3000); }
    })
    .catch(function(err){
        if(msgEl){ msgEl.textContent='❌ Erreur : '+err; msgEl.style.color='#e74c3c'; msgEl.style.display='inline'; }
    });
}

function enregistrerTout() {
    var msgEl = document.getElementById('msg_tout');
    if(msgEl){ msgEl.textContent='⏳ Enregistrement...'; msgEl.style.display='inline'; msgEl.style.color='#888'; }
    var onglets = ['examen','ecg','echo'].filter(function(o){
        return document.getElementById('form-'+o);
    });
    Promise.all(onglets.map(function(o){
        var data = _collectForm(o);
        return fetch('nouveau_bilan_clinique.php?id=<?= $id ?>', {
            method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:data
        }).then(function(r){ return r.json(); });
    }))
    .then(function(){
        if(msgEl){ msgEl.textContent='✅ Tout enregistré'; msgEl.style.color='#2ecc71'; msgEl.style.display='inline';
            setTimeout(function(){ msgEl.style.display='none'; },3000); }
    })
    .catch(function(err){
        if(msgEl){ msgEl.textContent='❌ Erreur : '+err; msgEl.style.color='#e74c3c'; msgEl.style.display='inline'; }
    });
}
/* ════ NAVIGATION BILANS ════ */
// Nombre total d'enregistrements par type (injecté depuis PHP)
var nbrEnreg = { examen: <?= $nbExamen ?>, ecg: <?= $nbEcg ?>, echo: <?= $nbEcho ?> };
var bilanRang = { examen: 0, ecg: 0, echo: 0 };  // rang courant (1-based, 0=nouveau)
// bilanRef : clé primaire (N1 ou N°) de l'enregistrement affiché (0 = mode nouveau)
var bilanRef = { examen: 0, ecg: 0, echo: 0 };
function naviguerBilan(type, dir) {
    var id=<?= $id ?>, ref=bilanRef[type];
    var dirEffectif = dir;
    if (!ref && dir === 'prev') dirEffectif = 'last';
    if (!ref && dir === 'next') dirEffectif = 'first';
    var url='ajax_bilan_nav.php?id='+id+'&type='+type+'&dir='+dirEffectif;
    if (ref && dirEffectif!=='first' && dirEffectif!=='last') url+='&ref='+ref;
    fetch(url).then(function(r){return r.json();}).then(function(d){
        if (d.vide){alert('Pas d\'autre bilan disponible.');return;}
        if (d.erreur){alert(d.erreur);return;}
        bilanRef[type] = d.pk || 0;
        // Mise à jour rang : si ajax_bilan_nav retourne un rang, l'utiliser ; sinon calcul local
        if (d.rang) {
            bilanRang[type] = d.rang;
        } else {
            var total = nbrEnreg[type];
            if      (dir === 'first') bilanRang[type] = total;
            else if (dir === 'last')  bilanRang[type] = 1;
            else if (dir === 'prev')  bilanRang[type] = Math.min(total, (bilanRang[type] || 1) + 1);
            else if (dir === 'next')  bilanRang[type] = Math.max(1,     (bilanRang[type] || 1) - 1);
        }
        var rang = bilanRang[type], tot = nbrEnreg[type];
        var label = (d.date_affichage||'—') + (rang && tot ? ' (' + rang + '/' + tot + ')' : '');
        document.getElementById('navdate_'+type).textContent = label;
        var df=document.getElementById('date_'+type); if(df&&d.date_fmt) df.value=d.date_fmt;
        if(type==='examen'){
            ['TAS','TAD','FC','POIDS','TAILLE','S_Fonctionnels','Auscult_Cardiaque',
             'Auscult_Pulmonaire','Examen_Vasculaire','Signes_IVG','Signes_IVD',
             'Autres_Symptomes','Conclusion','REMARQUE','Conduite_ATenir','CMLM_EXAMEN']
            .forEach(function(n){var e=document.querySelector('[name='+n+']');if(e)e.value=d[n]||'';});
        }
        if(type==='ecg'){
            ['FREQUENCE','rythme_sv','trouble_rv','rythme_v','conduction_nodale','QRS',
             'infrastructure_de_conduction','REPOLARISATION','SEGMENT_ST','TOPOGRAPHIE_ST',
             'ONDE_T','TOPOGRAPHIE_T','IDM','TOPOGRAPHIE_Q','CC','AUTRES_SIGNES','CMLM_ECG']
            .forEach(function(n){var e=document.querySelector('[name='+n+']');if(e)e.value=d[n]||'';});
            
        }
        if(type==='echo'){
            ['FEVG','DTD_VG','DTS_VG','SIV','PP','RACINE_AO','HTAP','CINETIQUE',
             'ECHOGENICITE','DOPPLER','DTSA','CONCLUSION1']
            .forEach(function(n){var e=document.querySelector('[name='+n+']');if(e)e.value=d[n]||'';});
        }
    }).catch(function(e){alert('Erreur : '+e.message);});
}
/* ══ Navigation globale (Examen + ECG + Echo simultanément) ══ */
function naviguerTout(dir) {
    ['examen','ecg','echo'].forEach(function(type) {
        naviguerBilan(type, dir);
    });
    // Mettre à jour le label global après un court délai (les fetch sont async)
    setTimeout(function() {
        var labelEx = document.getElementById('navdate_examen');
        var lbl = document.getElementById('nav_global_label');
        if (lbl && labelEx) lbl.textContent = labelEx.textContent;
    }, 400);
}
function nouveauTout() {
    ['examen','ecg','echo'].forEach(function(type) {
        nouveauBilan(type);
    });
    var lbl = document.getElementById('nav_global_label');
    if (lbl) lbl.textContent = '— nouveau —';
}

function nouveauBilan(type) {
    bilanRef[type]=0;
    bilanRang[type] = 0;
    var dateAff = '<?= date("d/m/Y") ?>';
    document.getElementById('navdate_'+type).textContent = dateAff + ' (' + (nbrEnreg[type]+1) + ')';
    var df=document.getElementById('date_'+type); if(df) df.value='<?= $today ?>';
    if(type==='examen') {
        viderExamen();
        /* Vider TAS TAD FC POIDS */
        ['inp_TAS','inp_TAD','inp_FC','inp_POIDS'].forEach(function(id){
            var el=document.getElementById(id); if(el) el.value='';
        });
    }
    if(type==='ecg')    viderECG();
    if(type==='echo') {
        viderEcho();
        ['em_DTD','em_DTS','inp_FEVG','inp_SIV','em_PP'].forEach(function(id){
            var el=document.getElementById(id); if(el) el.value='';
        });
    }
}
/* ════ NAVIGATION BIOLOGIE (lecture seule) ════ */
const bioNbcBilans = <?= json_encode(array_map(fn($b) => [
    'n_bilan' => $b['n_bilan'],
    'date_fr' => $b['date_fr'],
], $bilansNBC)) ?>;
let bioNbcIdx = 0;

function bioNavNBC(dir) {
    if (!bioNbcBilans.length) return;
    if      (dir === 'first') bioNbcIdx = 0;
    else if (dir === 'last')  bioNbcIdx = bioNbcBilans.length - 1;
    else if (dir === 'prev')  bioNbcIdx = Math.max(0, bioNbcIdx - 1);
    else if (dir === 'next')  bioNbcIdx = Math.min(bioNbcBilans.length - 1, bioNbcIdx + 1);
    const b = bioNbcBilans[bioNbcIdx];
    var tot = bioNbcBilans.length;
    document.getElementById('bio-nbc-navdate').textContent = b.date_fr + ' (' + (bioNbcIdx+1) + '/' + tot + ')';
    // Charger via AJAX
    fetch('ajax_bio_dossier.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'get_detail', n_bilan: b.n_bilan})
    })
    .then(r => r.json())
    .then(d => {
        if (!d.ok) return;
        const zone = document.getElementById('bio-nbc-resultats');
        if (!d.lignes.length) {
            zone.innerHTML = '<span style="color:#999;font-size:11px;">Bilan vide</span>';
            return;
        }
        zone.innerHTML = d.lignes.map(l => {
            const v  = l.resultat || '';
            const an = (v !== '' && v.toUpperCase() !== 'N');
            const col = an ? '#c0392b' : '#aaa';
            const fw  = an ? 'bold'   : 'normal';
            const fs  = an ? '11px'   : '10px';
            return `<div style="display:flex;justify-content:space-between;padding:1px 0;border-bottom:1px solid #f8f8f8;">
                <span style="color:${col};font-weight:${fw};font-size:${fs};">${l.nom}</span>
                <span style="color:${col};font-weight:${fw};font-size:${fs};margin-left:6px;white-space:nowrap;">${v || '—'}</span>
            </div>`;
        }).join('');
    });
}

/* ── CMLM Echo ── */
function toggleCmlmEcho(anormal) {
    document.getElementById('cmlm_echo_detail').style.display = anormal ? 'block' : 'none';
    var nd = document.getElementById('echo_normale_detail');
    if (nd) nd.style.display = anormal ? 'none' : 'block';
}
function toggleCmlmSub(cb) {
    const t = document.getElementById(cb.dataset.target);
    if (t) t.style.display = cb.checked ? 'block' : 'none';
    if (!cb.checked && t) t.querySelectorAll('input').forEach(i => {
        i.checked = false;
        if (i.dataset && i.dataset.target) { const s = document.getElementById(i.dataset.target); if(s) s.style.display='none'; }
    });
}
/* ── Ouvrir/fermer les sections Echo ── */
function toggleEchoSub(id) {
    var el  = document.getElementById(id);
    var arr = document.getElementById(id + '_arr');
    if (!el) return;
    var open = el.style.display === 'none' || el.style.display === '';
    el.style.display  = open ? 'block' : 'none';
    if (arr) arr.textContent = open ? '▼' : '▶';
}

function genererCmlmEcho() {
    var isNormale = document.getElementById('echo_normale_detail') &&
                    document.getElementById('echo_normale_detail').style.display !== 'none';
    var parties = [];

    if (isNormale) {
        document.querySelectorAll('.echo-n:checked').forEach(function(cb) {
            if (cb.value && cb.value !== 'on') parties.push(cb.value);
        });
        if (parties.length === 0) parties.push('échodoppler cardiaque normale');
    } else {
        document.querySelectorAll('.cmlm-ec:checked').forEach(function(cb) {
            var txt = (cb.value && cb.value !== 'on') ? cb.value : '';
            if (!txt) return;

            /* ─ Aortique ─ */
            if (txt.indexOf('rétrécissement aortique') === 0) {
                var g = document.getElementById('em_grad_ao');
                if (g && g.value) txt += ' (grad moy=' + g.value + 'mmHg)';
            }
            if (txt.indexOf('fuite aortique') === 0) {
                var extras = [];
                var pht = document.getElementById('em_pht_ao');   if (pht && pht.value) extras.push('1/2PHT=' + pht.value + 'ms');
                var vm  = document.getElementById('em_vmax_ia');   if (vm  && vm.value)  extras.push('Vmax=' + vm.value + 'm/s');
                if (extras.length) txt += ' (' + extras.join(', ') + ')';
            }
            if (txt.indexOf('maladie aortique') === 0) {
                var extras = [];
                var g   = document.getElementById('em_grad_ao');   if (g   && g.value)   extras.push('grad moy=' + g.value + 'mmHg');
                var pht = document.getElementById('em_pht_ao');     if (pht && pht.value) extras.push('1/2PHT=' + pht.value + 'ms');
                var vm  = document.getElementById('em_vmax_ia');    if (vm  && vm.value)  extras.push('Vmax=' + vm.value + 'm/s');
                if (extras.length) txt += ' (' + extras.join(', ') + ')';
            }
            /* ─ Mitrale ─ */
            if (txt.indexOf('rétrécissement mitral') === 0) {
                var extras = [];
                var s = document.getElementById('em_surf_mi'); if (s && s.value) extras.push('surface=' + s.value + 'cm²');
                if (extras.length) txt += ' (' + extras.join(', ') + ')';
            }
            if (txt.indexOf('fuite mitrale') === 0) {
                var sor = document.getElementById('em_sor_mi'); if (sor && sor.value) txt += ' (SOR=' + sor.value + 'mm²)';
            }
            if (txt.indexOf('maladie mitrale') === 0) {
                var extras = [];
                var s   = document.getElementById('em_surf_mi'); if (s   && s.value)   extras.push('surface=' + s.value + 'cm²');
                var sor = document.getElementById('em_sor_mi');  if (sor && sor.value) extras.push('SOR=' + sor.value + 'mm²');
                if (extras.length) txt += ' (' + extras.join(', ') + ')';
            }
            /* ─ Tricuspide ─ */
            if (txt.indexOf('fuite tricuspide') === 0) {
                var h = document.getElementById('em_htap_tr'); if (h && h.value) txt += ' (HTAP=' + h.value + 'mmHg)';
            }
            /* ─ Pulmonaire ─ */
            if (txt.indexOf('sténose valvulaire pulmonaire') === 0) {
                var g = document.getElementById('em_grad_pu_valv'); if (g && g.value) txt += ' (grad moy=' + g.value + 'mmHg)';
            }
            if (txt.indexOf('sténose infundibulaire pulmonaire') === 0) {
                var g = document.getElementById('em_grad_pu_infund'); if (g && g.value) txt += ' (grad moy=' + g.value + 'mmHg)';
            }
            /* ─ VCI ─ */
            if (txt.indexOf('VCI') === 0) {
                var d = document.getElementById('em_diam_vci'); if (d && d.value) txt += ' (diam=' + d.value + 'mm)';
            }
            /* ─ Aorte initiale ─ */
            if (txt.indexOf('aorte initiale') !== -1 || txt.indexOf('dilatation') === 0) {
                var d = document.getElementById('em_diam_ao_init'); if (d && d.value) txt += ' (diam=' + d.value + 'mm)';
            }
            /* ─ Oreillettes ─ */
            if (txt === 'oreillettes non dilatées') {
                var extras = [];
                var og = document.getElementById('em_og_surf'); if (og && og.value) extras.push('OG=' + og.value + 'cm²');
                if (extras.length) txt += ' (' + extras.join(', ') + ')';
            }
            if (txt === 'dilatées : massif auriculaire dilaté') {
                var extras = [];
                var og = document.getElementById('em_og_surf'); if (og && og.value) extras.push('OG=' + og.value + 'cm²');
                var od = document.getElementById('em_od_surf'); if (od && od.value) extras.push('OD=' + od.value + 'cm²');
                if (extras.length) txt += ' (' + extras.join(', ') + ')';
            }
            /* ─ Péricarde ─ */
            if (txt.indexOf('péricarde') !== -1 || txt.indexOf('décollement') === 0 || txt.indexOf('épanchement') === 0) {
                var d = document.getElementById('em_diam_peric'); if (d && d.value) txt += ' (diam=' + d.value + 'mm)';
            }
            /* ─ Cardiopathie hypertensive ─ */
            if (txt === 'cardiopathie hypertensive') {
                var siv = document.getElementById('em_siv_hta'); if (siv && siv.value) txt += ' (SIV=' + siv.value + 'mm)';
            }
            /* ─ IC FEVG ─ */
            if (txt.indexOf('IC à FEVG') !== -1 || txt === 'IC droite' || txt === 'IC globale') {
                var extras = [];
                var fv = document.getElementById('em_fevg2'); if (fv && fv.value) extras.push('FEVG=' + fv.value + '%');
                var dtd = document.getElementById('em_DTD');  if (dtd && dtd.value) extras.push('DTD-VG=' + dtd.value + 'mm');
                var dts = document.getElementById('em_DTS');  if (dts && dts.value) extras.push('DTS-VG=' + dts.value + 'mm');
                var gs  = document.getElementById('em_gstrain'); if (gs && gs.value) extras.push('GS=' + gs.value + '%');
                if (extras.length) txt += ' (' + extras.join(', ') + ')';
            }
            /* ─ Cardiopathie hypertrophique ─ */
            if (txt.indexOf('cardiopathie hypertrophique') === 0 || txt === 'cardiopathie restrictive' || txt === 'amylose cardiaque') {
                var extras = [];
                var siv = document.getElementById('em_siv_hypert');  if (siv && siv.value) extras.push('SIV=' + siv.value + 'mm');
                var grd = document.getElementById('em_grad_hypert'); if (grd && grd.value) extras.push('grad moy=' + grd.value + 'mmHg');
                if (extras.length) txt += ' (' + extras.join(', ') + ')';
            }
            if (txt) parties.push(txt);
        });
        if (parties.length === 0) parties.push('échodoppler cardiaque anormale');
    }

    var result = parties.map(function(p){ return '- ' + p; }).join('\n');
    document.getElementById('cmlm_echo_val').value = result;
    var ap = document.getElementById('conclusion1_echo');
    if (ap) { ap.value = result; autoResize(ap); }
}



/* ── Absence grise les territoires (repol et ondes Q) ── */
function exclusifGroupRepol(cb, parentId) {
    var parent = document.getElementById(parentId);
    if (!parent) return;
    var territories = parent.querySelectorAll('input[type="checkbox"]:not(.ecg-repol-abs):not(.ecg-q-abs)');
    if (cb.checked) {
        cb.parentElement.style.color = '#c0392b';
        cb.parentElement.style.fontWeight = 'bold';
        territories.forEach(function(el) {
            el.checked = false;
            el.disabled = true;
            var lbl = el.parentElement;
            lbl.style.display = 'none';
            var br = lbl.nextSibling;
            if (br && br.nodeName === 'BR') br.style.display = 'none';
        });
    } else {
        cb.parentElement.style.color = '';
        cb.parentElement.style.fontWeight = '';
        territories.forEach(function(el) {
            el.disabled = false;
            var lbl = el.parentElement;
            lbl.style.display = '';
            var br = lbl.nextSibling;
            if (br && br.nodeName === 'BR') br.style.display = '';
        });
    }
}

/* ── Exclusion mutuelle =1 choix ── */
function exclusifGroup(cb) {
    var group = cb.dataset.group;
    if (!group) return;
    var all = document.querySelectorAll('input[data-group="' + group + '"]');
    if (cb.checked) {
        // Mettre le label coché en rouge gras
        cb.parentElement.style.color = '#c0392b';
        cb.parentElement.style.fontWeight = 'bold';
        all.forEach(function(el) {
            if (el !== cb) {
                el.checked = false;
                el.disabled = true;
                var lbl = el.parentElement;
                lbl.style.display = 'none';
                var br = lbl.nextSibling;
                if (br && br.nodeName === 'BR') br.style.display = 'none';
                var target = el.dataset.target;
                if (target) { var sub = document.getElementById(target); if (sub) sub.style.display = 'none'; }
            }
        });
    } else {
        // Retirer le rouge sur le label décoché
        cb.parentElement.style.color = '';
        cb.parentElement.style.fontWeight = '';
        all.forEach(function(el) {
            el.disabled = false;
            var lbl = el.parentElement;
            lbl.style.display = '';
            var br = lbl.nextSibling;
            if (br && br.nodeName === 'BR') br.style.display = '';
        });
    }
}

/* ── Boutons Normal/Anormal ECG ── */
function setEcgGlobal(val) {
    var bn = document.getElementById('btn_ecg_normal');
    var ba = document.getElementById('btn_ecg_anormal');
    /* Réinitialiser lien Modifier si on change de mode */
    var lm = document.getElementById('lien_modifier_ecg');
    if (lm) lm.style.display = 'none';
    var bg = document.getElementById('btn_generer_ecg');
    if (bg) bg.style.display = 'inline-block';
    var panel = document.getElementById('panel_ecg_cases');
    if (panel) panel.style.display = '';
    if (val === 'normal') {
        modeECG = 'normal';
        if (bn) { bn.style.background = '#27ae60'; bn.style.color = 'white'; }
        if (ba) { ba.style.background = 'white';   ba.style.color = '#e67e22'; }
        toggleECGAnormal(false);
        /* Afficher les cases ECG normal et les pré-cocher */
        var nd = document.getElementById('ecg_normal_detail');
        if (nd) {
            nd.style.display = 'block';
            nd.querySelectorAll('input[type="checkbox"]').forEach(function(cb){ cb.checked = true; });
        }
        /* Aperçu : FC + 5 items */
        var fcEl = document.getElementById('inp_FC');
        var prefN = fcEl && fcEl.value ? 'FC : ' + fcEl.value + ' bpm\n' : '';
        var lignes = [prefN + 'Rythme sinusal, absence de trouble de rythme',
            'Conduction auriculo-ventriculaire normale',
            'Conduction intra-ventriculaire normale',
            'Repolarisation normale',
            'Absence d\'ondes Q de nécrose'];
        var ap = document.getElementById('apercu_ecg');
        if (ap) { ap.value = lignes.join('\n'); autoResize(ap); }
    } else {
        modeECG = 'anormal';
        if (ba) { ba.style.background = '#e67e22'; ba.style.color = 'white'; }
        if (bn) { bn.style.background = 'white';   bn.style.color = '#27ae60'; }
        /* Cacher les cases ECG normal */
        var nd = document.getElementById('ecg_normal_detail');
        if (nd) { nd.style.display = 'none'; nd.querySelectorAll('input[type="checkbox"]').forEach(function(cb){ cb.checked = false; }); }
        toggleECGAnormal(true);
        var ap = document.getElementById('apercu_ecg');
        if (ap) ap.value = '';
    }
}

/* ── Boutons Normal/Anormal Echo ── */
function setEchoGlobal(val) {
    var bn = document.getElementById('btn_echo_normale');
    var ba = document.getElementById('btn_echo_anormale');
    var lm = document.getElementById('lien_modifier_echo');
    if (lm) lm.style.display = 'none';
    var btnGen = document.getElementById('btn_generer_echo');
    if (btnGen) btnGen.style.display = 'block';
    if (val === 'normale') {
        modeEcho = 'normale';
        if (bn) { bn.style.background = '#27ae60'; bn.style.color = 'white'; }
        if (ba) { ba.style.background = 'white';   ba.style.color = '#e67e22'; }
        toggleCmlmEcho(false);
    } else {
        modeEcho = 'anormale';
        if (ba) { ba.style.background = '#e67e22'; ba.style.color = 'white'; }
        if (bn) { bn.style.background = 'white';   bn.style.color = '#27ae60'; }
        toggleCmlmEcho(true);
    }
}

/* ── Ouvrir/fermer un sous-groupe (cases parent) ── */
function toggleSub(cb) {
    const target = document.getElementById(cb.dataset.target);
    if (target) target.style.display = cb.checked ? 'block' : 'none';
    if (!cb.checked && target) target.querySelectorAll('input').forEach(i => {
        i.checked = false;
        if (i.dataset && i.dataset.target) { const s = document.getElementById(i.dataset.target); if(s) s.style.display='none'; }
    });
}

/* ── Radio simple (1 seul choix, pas d'exclusion spéciale) ── */
function syncRadio(radio, subId) {
    /* Rien de spécial — le comportement radio natif suffit */
}

/* ── Rythmique : exclusions tachycardie ↔ bradycardie ── */
function syncRadioRythme(radio) {
    var val = radio.value;
    /* "absence de palpitations" → grise et cache les 3 autres */
    var lblAbs   = document.getElementById('lbl_rythme_abs');
    var lblPalp  = document.getElementById('lbl_rythme_palp');
    var lblTachy = document.getElementById('lbl_rythme_tachy');
    var lblBrady = document.getElementById('lbl_rythme_brady');
    /* Réinitialiser l'affichage */
    [lblAbs, lblPalp, lblTachy, lblBrady].forEach(function(l){ if(l){ l.style.opacity='1'; l.style.display='block'; } });
    if (val === 'absence de palpitations') {
        [lblPalp, lblTachy, lblBrady].forEach(function(l){ if(l){ l.style.opacity='0.35'; l.style.display='none'; } });
    } else if (val === 'tachycardie') {
        /* Grise absence + bradycardie */
        if(lblAbs)   { lblAbs.style.opacity='0.35';   lblAbs.style.display='none'; }
        if(lblBrady) { lblBrady.style.opacity='0.35'; lblBrady.style.display='none'; }
    } else if (val === 'bradycardie') {
        /* Grise absence + tachycardie */
        if(lblAbs)   { lblAbs.style.opacity='0.35';   lblAbs.style.display='none'; }
        if(lblTachy) { lblTachy.style.opacity='0.35'; lblTachy.style.display='none'; }
    }
}

/* ── Normal exclusif : si choix normal sélectionné → cache les autres, sinon restaure ── */
function syncRadioNormal(radio, subId, normalLblId) {
    var sub = document.getElementById(subId);
    if (!sub) return;
    var labels = sub.querySelectorAll('label');
    var normalLbl = document.getElementById(normalLblId);
    if (radio.value === normalLbl.querySelector('input').value) {
        /* Option normale choisie : cacher les options pathologiques */
        labels.forEach(function(l){
            if (l.id !== normalLblId) { l.style.opacity='0.35'; l.style.display='none'; }
        });
    } else {
        /* Option pathologique : cacher l'option normale */
        if (normalLbl) { normalLbl.style.opacity='0.35'; normalLbl.style.display='none'; }
    }
}

/* ── Conclusion globale : 1er et 2e s'excluent mutuellement, 3e libre ── */
function syncConclusion(cb) {
    var c1 = document.getElementById('concl_normal');
    var c2 = document.getElementById('concl_ecvn');
    var l1 = document.getElementById('lbl_concl_normal');
    var l2 = document.getElementById('lbl_concl_ecvn');
    if (!c1 || !c2) return;
    if (cb === c1 && c1.checked) {
        /* "Examen clinique normal" coché → grise "Examen cardio-vasculaire normal" */
        c2.checked = false;
        if(l2){ l2.style.opacity='0.35'; l2.style.display='none'; }
        if(l1){ l1.style.opacity='1';    l1.style.display='block'; }
    } else if (cb === c2 && c2.checked) {
        /* "Examen cardio-vasculaire normal" coché → grise "Examen clinique normal" */
        c1.checked = false;
        if(l1){ l1.style.opacity='0.35'; l1.style.display='none'; }
        if(l2){ l2.style.opacity='1';    l2.style.display='block'; }
    } else {
        /* Décoché → restaurer les deux */
        if(l1){ l1.style.opacity='1'; l1.style.display='block'; }
        if(l2){ l2.style.opacity='1'; l2.style.display='block'; }
    }
}

/* ── Réinitialiser toutes les exclusions visuelles dans sympto_cases ── */
function resetSymptoAffichage() {
    var sc = document.getElementById('sympto_cases');
    if (!sc) return;
    sc.querySelectorAll('label').forEach(function(l){ l.style.opacity='1'; l.style.display='block'; });
    sc.querySelectorAll('input[type="radio"]').forEach(function(r){ r.checked=false; });
    sc.querySelectorAll('input[type="checkbox"].sympto-parent').forEach(function(cb){ cb.checked=false; });
    sc.querySelectorAll('[id^=sub_]').forEach(function(d){ d.style.display='none'; });
}

function toggleECGAnormal(anormal) {
    document.getElementById('ecg_detail').style.display = anormal ? 'block' : 'none';
}
function genererConclusion() {
    var parties = [];

    /* 1. TAS / TAD / FC en tête */
    var tas = document.getElementById('inp_TAS');
    var tad = document.getElementById('inp_TAD');
    var fc  = document.getElementById('inp_FC');
    var mesure = '';
    if (tas && tas.value) mesure += 'TA : ' + tas.value;
    if (tad && tad.value) mesure += '/' + tad.value + ' mmHg';
    if (fc  && fc.value)  mesure += (mesure ? ' — ' : '') + 'FC : ' + fc.value + ' bpm';
    if (mesure) parties.push(mesure);

    /* 2. Radios symptomatologie anormale */
    ['sympto_angor','sympto_dyspnee','sympto_rythme','sympto_arterite'].forEach(function(name) {
        var r = document.querySelector('input[name="'+name+'"]:checked');
        if (r && r.value) parties.push(r.value);
    });
    /* Champ Autres artérite */
    var artAutres = document.getElementById('arterite_autres');
    if (artAutres && artAutres.value.trim()) parties.push(artAutres.value.trim());

    /* Signes IVD — plusieurs choix */
    document.querySelectorAll('.sx-ivd:checked').forEach(function(cb) {
        if (cb.value) parties.push(cb.value);
    });

    /* Phlébitique — plusieurs choix */
    document.querySelectorAll('.sx-phleb:checked').forEach(function(cb) {
        if (cb.value) parties.push(cb.value);
    });
    var phlAutres = document.getElementById('phlebite_autres');
    if (phlAutres && phlAutres.value.trim()) parties.push(phlAutres.value.trim());

    /* Remplit l'Aperçu rapport Examen */
    var ap = document.getElementById('apercu_examen');
    if (ap) { ap.value = parties.length > 0 ? parties.map(function(p){ return '- ' + p; }).join('\n') : '—'; autoResize(ap); }
}

/* exclusifVisible : dans un groupe, masque les non-sélectionnés sauf si on décoche tout */
function exclusifVisible(cb, groupId) {
    var container = document.getElementById(groupId);
    if (!container) return;
    // Seulement les labels directs (pas dans un sous-div enfant)
    var labels = Array.from(container.querySelectorAll('label')).filter(function(lbl) {
        return lbl.parentElement === container;
    });
    var anyChecked = labels.some(function(lbl) {
        var inp = lbl.querySelector('input[type="checkbox"]');
        return inp && inp.checked;
    });
    labels.forEach(function(lbl) {
        var inp = lbl.querySelector('input[type="checkbox"]');
        if (!inp) return;
        if (!anyChecked) {
            lbl.style.display = '';
            lbl.style.color = '';
            lbl.style.fontWeight = '';
            return;
        }
        lbl.style.display = inp.checked ? '' : 'none';
        if (inp.checked) {
            lbl.style.color = '#c0392b';
            lbl.style.fontWeight = 'bold';
        }
    });
}

/* genererRapportECG : remplit apercu_ecg (C/C reste libre) */
function genererRapportECG() {
    var fc  = document.getElementById('inp_FC');
    var prefixe = fc && fc.value ? 'FC : ' + fc.value + ' bpm' : '';

    var detail = document.getElementById('ecg_detail');
    var normalDetail = document.getElementById('ecg_normal_detail');
    var estNormal = detail && detail.style.display === 'none';
    var txt = '';

    if (estNormal && normalDetail && normalDetail.style.display !== 'none') {
        /* ECG normal : FC + cases cochées */
        var lignes = [];
        if (prefixe) lignes.push(prefixe);
        normalDetail.querySelectorAll('input[type="checkbox"]:checked').forEach(function(cb) {
            var lbl = cb.parentElement;
            if (lbl) lignes.push(lbl.textContent.trim());
        });
        txt = lignes.map(function(l){ return '- ' + l; }).join('\n');
    } else {
        var parties = [];
        if (prefixe) parties.push(prefixe);
        document.querySelectorAll('#panel_ecg_cases input[type="checkbox"]:checked').forEach(function(cb) {
            if (cb.classList.contains('ecg-parent')) return;
            if (cb.classList.contains('ecg-normal-cb')) return;
            if (cb.value && cb.value !== 'on') { parties.push(cb.value); return; }
            var lbl = cb.parentElement;
            if (lbl) { var t = lbl.textContent.trim(); if (t) parties.push(t); }
        });
        /* Pacemaker : ajouter date si renseignée */
        var paceDate = document.getElementById('ecg_pace_date');
        if (paceDate && paceDate.value.trim()) {
            var idx = parties.findIndex(function(p){ return p.indexOf('Électro-entraîné') !== -1 || p.indexOf('pacemaker') !== -1; });
            if (idx !== -1) parties[idx] += ', posé le ' + paceDate.value.trim();
        }
        txt = parties.map(function(l){ return '- ' + l; }).join('\n');
    }
    var ap = document.getElementById('apercu_ecg');
    if (ap) { ap.value = txt; autoResize(ap); }
}
/* Alias conservé pour compatibilité interne */
function genererCC() { genererRapportECG(); }

/* ── Choix multiples : masquer non-cochés, rouge sur cochés ── */
function appliquerMultiple(containerId) {
    var container = document.getElementById(containerId);
    if (!container) return;
    var toutes = container.querySelectorAll('input[type="checkbox"]');
    var nbCoches = 0;
    toutes.forEach(function(el) { if (el.checked) nbCoches++; });

    if (nbCoches > 0) {
        toutes.forEach(function(el) {
            var lbl = el.parentElement;
            var br  = lbl.nextSibling;
            if (el.checked) {
                lbl.style.color      = '#c0392b';
                lbl.style.fontWeight = 'bold';
                lbl.style.display    = '';
                if (br && br.nodeName === 'BR') br.style.display = '';
            } else {
                lbl.style.display = 'none';
                if (br && br.nodeName === 'BR') br.style.display = 'none';
            }
        });
    } else {
        toutes.forEach(function(el) {
            var lbl = el.parentElement;
            var br  = lbl.nextSibling;
            lbl.style.display    = '';
            lbl.style.color      = '';
            lbl.style.fontWeight = '';
            if (br && br.nodeName === 'BR') br.style.display = '';
        });
    }
}

/* ── Reporter valeurs écho dans les champs CMLM ── */
function reporterFEVG() {
    var el = document.getElementById('echo_FEVG');
    var fevg = el ? el.value.trim() : '';
    if (!fevg) return;
    ['ce_fevg_cons','ce_fevg_alt','ce_fevg_tres'].forEach(function(id) {
        var f = document.getElementById(id);
        if (f) f.value = fevg;
    });
}
function reporterSIV() {
    var siv = (document.querySelector('input[name="SIV"]') || {}).value || '';
    var el = document.getElementById('ce_siv');
    if (el && !el.value) el.value = siv;
}

/* ══════════════════════════════════════════════════════
   ÉPURER / RESTAURER — boutons globaux header
══════════════════════════════════════════════════════ */

/* Masque toutes les options non cochées, met les cochées en rouge gras */
function epurerTout() {
    // Tous les checkboxes du bilan (examen, ECG, Echo, CMLM)
    var toutes = document.querySelectorAll(
        '#panel_sympto input[type="checkbox"],' +
        '#panel_ecg_cases input[type="checkbox"],' +
        '.cmlm-ec,' +
        'input.ecg-child,' +
        'input.sympto-child'
    );
    // Utiliser un Set pour éviter les doublons
    var vus = new Set();
    toutes.forEach(function(cb) {
        if (vus.has(cb)) return;
        vus.add(cb);
        var lbl = cb.parentElement;
        var br  = lbl ? lbl.nextSibling : null;
        if (cb.checked) {
            if (lbl) { lbl.style.color = '#c0392b'; lbl.style.fontWeight = 'bold'; lbl.style.display = ''; }
            if (br && br.nodeName === 'BR') br.style.display = '';
        } else {
            if (lbl) lbl.style.display = 'none';
            if (br && br.nodeName === 'BR') br.style.display = 'none';
        }
    });
}

/* Remet tout à l'état initial : options visibles, pas de rouge, décoche tout */
function restaurerTout() {
    // Réactiver et afficher tous les checkboxes
    var toutes = document.querySelectorAll('input[type="checkbox"]');
    toutes.forEach(function(cb) {
        cb.checked  = false;
        cb.disabled = false;
        var lbl = cb.parentElement;
        var br  = lbl ? lbl.nextSibling : null;
        if (lbl) { lbl.style.display = ''; lbl.style.color = ''; lbl.style.fontWeight = ''; }
        if (br && br.nodeName === 'BR') br.style.display = '';
    });
    // Cacher tous les sous-panneaux (sub_*) qui s'ouvrent via toggleSub
    document.querySelectorAll('[id^="sub_"]').forEach(function(el) {
        el.style.display = 'none';
    });
    // Réinitialiser les boutons radio ECG (normal/anormal)
    document.querySelectorAll('input[name="ecg_global"]').forEach(function(r) {
        r.checked = false;
    });
    var ecgDetail = document.getElementById('ecg_detail');
    if (ecgDetail) ecgDetail.style.display = 'none';
    // Remettre les blocs de cases visibles
    var ps = document.getElementById('panel_sympto');
    if (ps) ps.style.display = '';
    var pe = document.getElementById('panel_ecg_cases');
    if (pe) pe.style.display = '';
    var pec = document.getElementById('panel_echo_cases');
    if (pec) pec.style.display = '';
    var lms = document.getElementById('lien_modifier_sympto');
    if (lms) lms.style.display = 'none';
    /* Cacher les cases sympto (visibles seulement si Examen anormal) */
    var sc = document.getElementById('sympto_cases');
    if (sc) sc.style.display = 'none';
    var lme = document.getElementById('lien_modifier_ecg');
    if (lme) lme.style.display = 'none';
    var lmec = document.getElementById('lien_modifier_echo');
    if (lmec) lmec.style.display = 'none';
    var bge = document.getElementById('btn_generer_echo');
    if (bge) bge.style.display = '';
    // Réinitialiser les variables internes exclusions
    if (typeof exclusions !== 'undefined') exclusions = {};
    if (typeof exclusionsEcho !== 'undefined') exclusionsEcho = {};
    /* Réinitialiser les cases Conduite à tenir */
    var catEcvn = document.getElementById('cat_ecvn');
    var catApte = document.getElementById('cat_apte');
    if (catEcvn) catEcvn.checked = false;
    if (catApte) catApte.checked = false;
    var lEcvn = document.getElementById('lbl_cat_ecvn');
    var lApte = document.getElementById('lbl_cat_apte');
    if(lEcvn){ lEcvn.style.opacity='1'; lEcvn.style.display='block'; }
    if(lApte){ lApte.style.opacity='1'; lApte.style.display='block'; }
    document.querySelectorAll('.cat-check').forEach(function(cb){ cb.checked=false; });
    var catAutres = document.getElementById('cat_autres');
    if (catAutres) catAutres.value = '';
    var ct = document.getElementById('conduite_textarea');
    if (ct) ct.value = '';
}



/* ── Sections dépliables ECG et Examen ── */
function toggleEcgSection(subId, arrId) {
    var sub = document.getElementById(subId);
    var arr = document.getElementById(arrId);
    if (!sub) return;
    var visible = sub.style.display !== 'none' && sub.style.display !== '';
    sub.style.display = visible ? 'none' : 'block';
    if (arr) arr.textContent = visible ? '▶' : '▼';
}
/* ── Panneau Conduite à tenir ── */
function toggleConduitePanel() {
    var p = document.getElementById('panel_conduite');
    if (!p) return;
    p.style.display = (p.style.display === 'none' || p.style.display === '') ? 'block' : 'none';
}
function toggleCatSection(secId, arrId) {
    var sec = document.getElementById(secId);
    var arr = document.getElementById(arrId);
    if (!sec) return;
    var visible = sec.style.display !== 'none' && sec.style.display !== '';
    sec.style.display = visible ? 'none' : 'block';
    if (arr) arr.textContent = visible ? '▶' : '▼';
}
function majConduiteTextarea() {
    var items = [];
    document.querySelectorAll('.cat-item:checked').forEach(function(cb) {
        if (cb.value) items.push('- ' + cb.value);
    });
    // Champs texte libres associés aux sections
    var champs = [
        'cat_trait_retenu','cat_bio_detail','cat_avis_autre',
        'cat_corresp_autre','cat_cr_bio','cat_apt_autre','cat_autres'
    ];
    champs.forEach(function(id) {
        var el = document.getElementById(id);
        if (el && el.value.trim()) items.push('- ' + el.value.trim());
    });
    var ta = document.getElementById('conduite_textarea');
    if (ta) ta.value = items.join('\n');
}
// Déclencher majConduiteTextarea au changement de chaque case
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.cat-item').forEach(function(cb) {
        cb.addEventListener('change', majConduiteTextarea);
    });
});

/* ── Modifier les cases — réafficher les listes ── */
/* Variables globales mémorisant le mode actif de chaque panneau */
var modeExamen = 'aucun'; // 'normal' ou 'anormal'
var modeECG    = 'aucun'; // 'normal' ou 'anormal'
var modeEcho   = 'aucun'; // 'normale' ou 'anormale'

function modifierExamen() {
    var bg = document.getElementById('btn_generer_examen');
    var bgh = document.getElementById('btn_generer_examen_h');
    var lh = document.getElementById('lien_modifier_sympto_h');
    if (bg) bg.style.display = 'inline-block';
    if (bgh) bgh.style.display = 'inline-block';
    document.getElementById('lien_modifier_sympto').style.display = 'none';
    if (lh) lh.style.display = 'none';
    if (modeExamen === 'normal') {
        document.getElementById('bloc_normal').style.display = 'block';
    } else if (modeExamen === 'anormal') {
        document.getElementById('sympto_cases').style.display = 'block';
    }
}
function modifierECG() {
    var bg = document.getElementById('btn_generer_ecg');
    var bgh = document.getElementById('btn_generer_ecg_h');
    var lh = document.getElementById('lien_modifier_ecg_h');
    if (bg) bg.style.display = 'inline-block';
    if (bgh) bgh.style.display = 'inline-block';
    document.getElementById('lien_modifier_ecg').style.display = 'none';
    if (lh) lh.style.display = 'none';
    if (modeECG === 'normal') {
        document.getElementById('ecg_normal_detail').style.display = 'block';
    } else if (modeECG === 'anormal') {
        document.getElementById('ecg_detail').style.display = 'block';
    }
}
function modifierEcho() {
    var bg = document.getElementById('btn_generer_echo');
    var bgh = document.getElementById('btn_generer_echo_h');
    var lh = document.getElementById('lien_modifier_echo_h');
    if (bg) bg.style.display = 'inline-block';
    if (bgh) bgh.style.display = 'inline-block';
    document.getElementById('lien_modifier_echo').style.display = 'none';
    if (lh) lh.style.display = 'none';
    if (modeEcho === 'normale') {
        document.getElementById('echo_normale_detail').style.display = 'block';
    } else if (modeEcho === 'anormale') {
        document.getElementById('cmlm_echo_detail').style.display = 'block';
    }
}


</script>

</body>
</html>
