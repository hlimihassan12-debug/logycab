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
                 Autres_Symptomes,Conclusion,REMARQUE,Conduite_ATenir)
                VALUES (?,CONVERT(datetime,?,120),?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$id,
                $dEx.' 00:00:00',$_POST['TAS']?:null,$_POST['TAD']?:null,
                $_POST['FC']?:null,$_POST['POIDS']?:null,$_POST['TAILLE']?:null,
                $_POST['S_Fonctionnels']?:null,$_POST['Auscult_Cardiaque']?:null,
                $_POST['Auscult_Pulmonaire']?:null,$_POST['Examen_Vasculaire']?:null,
                $_POST['Signes_IVG']?:null,$_POST['Signes_IVD']?:null,
                $_POST['Autres_Symptomes']?:null,$_POST['Conclusion']?:null,
                $_POST['REMARQUE']?:null,$_POST['Conduite_ATenir']?:null]);
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
                 [AUTRES Signes ECG])
                VALUES (?,CONVERT(datetime,?,120),?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
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
                $_POST['AUTRES_SIGNES']?:null]);
            if ($isAjax) { ob_clean(); header('Content-Type: application/json'); echo json_encode(['ok'=>true,'msg'=>'✅ ECG enregistré']); exit; }
        } catch (Exception $e) {
            if ($isAjax) { ob_clean(); header('Content-Type: application/json'); echo json_encode(['ok'=>false,'msg'=>'❌ ECG : '.$e->getMessage()]); exit; }
        }
        header("Location: ?id=$id&msg=ecg_ok"); exit;
    }

    if ($onglet === 'echo') {
        $dEcho = $_POST['DATEchog'] ?? date('Y-m-d');
        try {
            $db->prepare("INSERT INTO echo
                ([N-PAT],DATEchog,ECHOGENICITE,[RACINE-AO],
                 [DTD-VG],[DTS-VG],SIV,PP,FEVG,
                 CINETIQUE,HTAP,DOPPLER,CONCLUSION1,
                 [DOPPLER DES TRONCS SUPRA AORTIQUES],TYPE_ECHO,CMLM_ECHO)
                VALUES (?,CONVERT(datetime,?,120),?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$id,
                $dEcho.' 00:00:00',$_POST['ECHOGENICITE']?:null,
                $_POST['RACINE_AO']?:null,$_POST['DTD_VG']?:null,
                $_POST['DTS_VG']?:null,$_POST['SIV']?:null,
                $_POST['PP']?:null,$_POST['FEVG']?:null,
                $_POST['CINETIQUE']?:null,$_POST['HTAP']?:null,
                $_POST['DOPPLER']?:null,$_POST['CONCLUSION1']?:null,
                $_POST['DTSA']?:null,
                $_POST['TYPE_ECHO']?:null,
                $_POST['CMLM_ECHO']?:null]);
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

.cols { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 10px; padding: 10px; align-items: start; }

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
        <a href="dossier.php?id=<?= $id ?>" class="btn-retour" style="margin-left:0;">← Retour dossier</a>
    </div>
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
        <button type="button" class="btn-preset btn-normal" onclick="remplirExamenNormal()" title="Valeurs normales">✅</button>
        <button type="button" class="btn-preset btn-anormal" onclick="setExamenAnormal(); document.getElementById('panel_sympto').style.display=''; document.getElementById('lien_modifier_sympto').style.display='none';" title="Modifier les cases">✏️</button>
        <button type="button" class="btn-save" onclick="enregistrerAjax('examen')" title="Enregistrer">💾</button>
        <span style="flex:1;"></span>
        <input type="date" name="DateExam" value="<?= $today ?>" id="date_examen" style="border:1px solid #ddd;border-radius:3px;padding:2px 5px;font-size:11px;color:#1a4a7a;">
    </div>

   <div style="min-height:16px;"><span id="msg_examen" style="font-size:11px;color:#27ae60;font-weight:bold;display:none;"></span></div>
      <div style="min-height:14px;margin-bottom:4px;"><small id="lbl_exclu_examen" style="color:#e74c3c;font-weight:bold;font-size:9px;display:none;"></small></div>
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

        <!-- ══ BLOC NORMAL (affiché par ✅) ══ -->
        <div id="bloc_normal" style="display:none;">
            <div style="font-size:11px;font-weight:bold;color:#27ae60;margin-bottom:3px;">Examen clinique normal</div>
            <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" id="n_angor"   checked value="absence de symptomatologie douloureuse (angor)"> Absence de symptomatologie douloureuse (angor)</label>
            <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" id="n_dysp"    checked value="absence de dyspnée"> Absence de dyspnée</label>
            <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" id="n_palp"    checked value="absence de palpitations"> Absence de palpitations</label>
            <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" id="n_arterite" checked value="périmètre de marche normal, absence de claudication intermittente"> Périmètre de marche normal, absence de claudication intermittente</label>
            <label style="font-size:11px;display:block;margin-bottom:4px;"><input type="checkbox" id="n_phlebit" checked value="absence de varices, absence d'œdèmes des MI"> Absence de varices, absence d'œdèmes des MI</label>

            <!-- Conduite à tenir — bloc normal -->
            <div style="border-top:1px solid #c8dff0;padding-top:5px;margin-top:2px;">
                <div style="font-size:11px;font-weight:bold;color:#1a4a7a;margin-bottom:2px;">📋 Conduite à tenir</div>
                <label id="lbl_cat_ecvn" style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" id="cat_ecvn" value="Examen cardio-vasculaire normal" onchange="syncCat(this)"> Examen cardio-vasculaire normal</label>
                <label id="lbl_cat_apte" style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" id="cat_apte" value="Examen cardio-vasculaire normal, Apte à l'emploi sollicité" onchange="syncCat(this)"> Examen cardio-vasculaire normal, Apte à l'emploi sollicité</label>
                <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="cat-check" value="Contrôle régulier de la TA"> Contrôle régulier de la TA</label>
                <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="cat-check" value="Absence de contre-indication cardiaque à la chirurgie"> Absence de contre-indication cardiaque à la chirurgie</label>
                <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="cat-check" value="Contrôle ECG"> Contrôle ECG</label>
                <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="cat-check" value="Adaptation du traitement"> Adaptation du traitement</label>
                <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="cat-check" value="Bilan biologique de contrôle"> Bilan biologique de contrôle</label>
                <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="cat-check" value="Écho de contrôle à 6 mois"> Écho de contrôle à 6 mois</label>
                <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="cat-check" value="ECG de contrôle"> ECG de contrôle</label>
                <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="cat-check" value="Consultation cardiologique trimestrielle"> Consultation cardiologique trimestrielle</label>
                <div style="display:flex;align-items:center;gap:4px;margin-top:2px;">
                    <span style="font-size:11px;">Autres :</span>
                    <input type="text" id="cat_autres_n" placeholder="préciser..." style="flex:1;border:1px solid #ccc;border-radius:3px;padding:2px 5px;font-size:11px;">
                </div>
            </div>
            <button type="button" onclick="genererConclusionNormal(); enregistrerAjax('examen');" style="margin-top:6px;background:#1a4a7a;color:white;border:none;border-radius:3px;padding:3px 12px;font-size:11px;cursor:pointer;">▶ Générer &amp; 💾</button>
        </div>

        <!-- ══ BLOC ANORMAL (affiché par ⚠️) ══ -->
        <div id="sympto_cases" style="display:none;">

            <div style="font-size:11px;font-weight:bold;color:#e67e22;margin-bottom:4px;">Examen clinique anormal</div>

            <!-- Angor — 1 seul choix -->
            <div style="margin-bottom:2px;">
                <label style="font-size:11px;font-weight:bold;cursor:pointer;"><input type="checkbox" class="sympto-parent" data-target="sub_angor" onchange="toggleSub(this)"> Symptomatologie douloureuse (angor)</label>
            </div>
            <div id="sub_angor" style="display:none;margin-left:14px;margin-bottom:4px;">
                <label id="lbl_angor_0" style="font-size:11px;display:block;margin-bottom:2px;"><input type="radio" name="sympto_angor" value="absence de symptomatologie douloureuse (angor)" onchange="syncRadio(this,'sub_angor')"> absence de symptomatologie douloureuse (angor)</label>
                <label id="lbl_angor_1" style="font-size:11px;display:block;margin-bottom:2px;"><input type="radio" name="sympto_angor" value="angor d'effort" onchange="syncRadio(this,'sub_angor')"> angor d'effort</label>
                <label id="lbl_angor_2" style="font-size:11px;display:block;margin-bottom:2px;"><input type="radio" name="sympto_angor" value="angor crescendo" onchange="syncRadio(this,'sub_angor')"> angor crescendo</label>
            </div>

            <!-- Dyspnée — 1 seul choix -->
            <div style="margin-bottom:2px;">
                <label style="font-size:11px;font-weight:bold;cursor:pointer;"><input type="checkbox" class="sympto-parent" data-target="sub_dyspnee" onchange="toggleSub(this)"> Symptomatologie dyspnéique</label>
            </div>
            <div id="sub_dyspnee" style="display:none;margin-left:14px;margin-bottom:4px;">
                <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="radio" name="sympto_dyspnee" value="absence de dyspnée" onchange="syncRadio(this,'sub_dyspnee')"> absence de dyspnée</label>
                <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="radio" name="sympto_dyspnee" value="dyspnée stade I NYHA" onchange="syncRadio(this,'sub_dyspnee')"> dyspnée stade I NYHA</label>
                <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="radio" name="sympto_dyspnee" value="dyspnée d'effort stade II NYHA" onchange="syncRadio(this,'sub_dyspnee')"> dyspnée d'effort stade II NYHA</label>
                <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="radio" name="sympto_dyspnee" value="dyspnée d'effort stade III NYHA" onchange="syncRadio(this,'sub_dyspnee')"> dyspnée d'effort stade III NYHA</label>
                <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="radio" name="sympto_dyspnee" value="suspicion d'embolie pulmonaire" onchange="syncRadio(this,'sub_dyspnee')"> suspicion d'embolie pulmonaire</label>
            </div>

            <!-- Rythmique — 1 seul choix avec exclusions tachycardie/bradycardie -->
            <div style="margin-bottom:2px;">
                <label style="font-size:11px;font-weight:bold;cursor:pointer;"><input type="checkbox" class="sympto-parent" data-target="sub_rythme" onchange="toggleSub(this)"> Symptomatologie rythmique</label>
            </div>
            <div id="sub_rythme" style="display:none;margin-left:14px;margin-bottom:4px;">
                <label id="lbl_rythme_abs"   style="font-size:11px;display:block;margin-bottom:2px;"><input type="radio" name="sympto_rythme" value="absence de palpitations" onchange="syncRadioRythme(this)"> absence de palpitations</label>
                <label id="lbl_rythme_palp"  style="font-size:11px;display:block;margin-bottom:2px;"><input type="radio" name="sympto_rythme" value="palpitations" onchange="syncRadioRythme(this)"> palpitations</label>
                <label id="lbl_rythme_tachy" style="font-size:11px;display:block;margin-bottom:2px;"><input type="radio" name="sympto_rythme" value="tachycardie" onchange="syncRadioRythme(this)"> tachycardie</label>
                <label id="lbl_rythme_brady" style="font-size:11px;display:block;margin-bottom:2px;"><input type="radio" name="sympto_rythme" value="bradycardie" onchange="syncRadioRythme(this)"> bradycardie</label>
            </div>

            <!-- Artérite — 1 seul choix ; si normal → cache les autres -->
            <div style="margin-bottom:2px;">
                <label style="font-size:11px;font-weight:bold;cursor:pointer;"><input type="checkbox" class="sympto-parent" data-target="sub_arterite" onchange="toggleSub(this)"> Symptomatologie artéritique des MI</label>
            </div>
            <div id="sub_arterite" style="display:none;margin-left:14px;margin-bottom:4px;">
                <label id="lbl_arterite_0" style="font-size:11px;display:block;margin-bottom:2px;"><input type="radio" name="sympto_arterite" value="périmètre de marche normal, absence de claudication intermittente" onchange="syncRadioNormal(this,'sub_arterite','lbl_arterite_0')"> périmètre de marche normal, absence de claudication intermittente</label>
                <label id="lbl_arterite_1" style="font-size:11px;display:block;margin-bottom:2px;"><input type="radio" name="sympto_arterite" value="artérite stade I" onchange="syncRadioNormal(this,'sub_arterite','lbl_arterite_0')"> artérite stade I</label>
                <label id="lbl_arterite_2" style="font-size:11px;display:block;margin-bottom:2px;"><input type="radio" name="sympto_arterite" value="artérite stade II" onchange="syncRadioNormal(this,'sub_arterite','lbl_arterite_0')"> artérite stade II</label>
                <label id="lbl_arterite_3" style="font-size:11px;display:block;margin-bottom:2px;"><input type="radio" name="sympto_arterite" value="artérite stade IV" onchange="syncRadioNormal(this,'sub_arterite','lbl_arterite_0')"> artérite stade IV</label>
                <label id="lbl_arterite_4" style="font-size:11px;display:block;margin-bottom:2px;"><input type="radio" name="sympto_arterite" value="gangrène" onchange="syncRadioNormal(this,'sub_arterite','lbl_arterite_0')"> gangrène</label>
            </div>

            <!-- Phlébitique — 1 seul choix ; si normal → cache les autres -->
            <div style="margin-bottom:2px;">
                <label style="font-size:11px;font-weight:bold;cursor:pointer;"><input type="checkbox" class="sympto-parent" data-target="sub_phlebite" onchange="toggleSub(this)"> Symptomatologie phlébitique</label>
            </div>
            <div id="sub_phlebite" style="display:none;margin-left:14px;margin-bottom:4px;">
                <label id="lbl_phlebite_0" style="font-size:11px;display:block;margin-bottom:2px;"><input type="radio" name="sympto_phlebite" value="absence de varices, absence d'œdèmes des MI" onchange="syncRadioNormal(this,'sub_phlebite','lbl_phlebite_0')"> absence de varices, absence d'œdèmes des MI</label>
                <label id="lbl_phlebite_1" style="font-size:11px;display:block;margin-bottom:2px;"><input type="radio" name="sympto_phlebite" value="varices des MI" onchange="syncRadioNormal(this,'sub_phlebite','lbl_phlebite_0')"> varices des MI</label>
                <label id="lbl_phlebite_2" style="font-size:11px;display:block;margin-bottom:2px;"><input type="radio" name="sympto_phlebite" value="phlébite des MI" onchange="syncRadioNormal(this,'sub_phlebite','lbl_phlebite_0')"> phlébite des MI</label>
                <label id="lbl_phlebite_3" style="font-size:11px;display:block;margin-bottom:2px;"><input type="radio" name="sympto_phlebite" value="trouble trophique des MI" onchange="syncRadioNormal(this,'sub_phlebite','lbl_phlebite_0')"> trouble trophique des MI</label>
            </div>

            <!-- Conduite à tenir — bloc anormal -->
            <div style="border-top:1px solid #d0dff0;padding-top:5px;margin-top:2px;">
                <div style="font-size:11px;font-weight:bold;color:#1a4a7a;margin-bottom:2px;">📋 Conduite à tenir</div>
                <label id="lbl_cat_ecvn2" style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" id="cat_ecvn2" value="Examen cardio-vasculaire normal" onchange="syncCat2(this)"> Examen cardio-vasculaire normal</label>
                <label id="lbl_cat_apte2" style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" id="cat_apte2" value="Examen cardio-vasculaire normal, Apte à l'emploi sollicité" onchange="syncCat2(this)"> Examen cardio-vasculaire normal, Apte à l'emploi sollicité</label>
                <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="cat-check2" value="Contrôle régulier de la TA"> Contrôle régulier de la TA</label>
                <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="cat-check2" value="Absence de contre-indication cardiaque à la chirurgie"> Absence de contre-indication cardiaque à la chirurgie</label>
                <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="cat-check2" value="Contrôle ECG"> Contrôle ECG</label>
                <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="cat-check2" value="Adaptation du traitement"> Adaptation du traitement</label>
                <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="cat-check2" value="Bilan biologique de contrôle"> Bilan biologique de contrôle</label>
                <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="cat-check2" value="Écho de contrôle à 6 mois"> Écho de contrôle à 6 mois</label>
                <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="cat-check2" value="ECG de contrôle"> ECG de contrôle</label>
                <label style="font-size:11px;display:block;margin-bottom:2px;"><input type="checkbox" class="cat-check2" value="Consultation cardiologique trimestrielle"> Consultation cardiologique trimestrielle</label>
                <div style="display:flex;align-items:center;gap:4px;margin-top:2px;">
                    <span style="font-size:11px;">Autres :</span>
                    <input type="text" id="cat_autres_a" placeholder="préciser..." style="flex:1;border:1px solid #ccc;border-radius:3px;padding:2px 5px;font-size:11px;">
                </div>
            </div>
            <button type="button" onclick="genererConclusion(); document.getElementById('panel_sympto').style.display='none'; document.getElementById('lien_modifier_sympto').style.display='inline'; enregistrerAjax('examen');" style="margin-top:6px;background:#1a4a7a;color:white;border:none;border-radius:3px;padding:3px 12px;font-size:11px;cursor:pointer;">▶ Générer &amp; 💾</button>
        </div><!-- fin sympto_cases -->

    </div><!-- fin panel_sympto -->
    <span id="lien_modifier_sympto" style="display:none;font-size:10px;">
        <a href="#" onclick="document.getElementById('panel_sympto').style.display=''; document.getElementById('lien_modifier_sympto').style.display='none'; return false;" style="color:#2e6da4;">↺ Modifier les cases</a>
    </span>
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
        <textarea id="apercu_examen" readonly
            style="min-height:45px;background:#f0f7ff;border:1px solid #2e6da4;font-size:11px;color:#1a4a7a;resize:vertical;width:100%;padding:4px 6px;border-radius:3px;font-family:Arial,sans-serif;"></textarea>
    </div>

    <div class="sec">Au total — Conduite à tenir</div>
    <div class="champ">
        <textarea name="Conduite_ATenir" id="conduite_textarea" style="min-height:70px;" placeholder="Cliquez ▶ Générer conclusion pour remplir, ou saisie directe…"></textarea>
    </div>
    </form>
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
        <button type="button" class="btn-preset btn-normal" onclick="remplirECGNormal()" title="Valeurs normales">✅</button>
        <button type="button" class="btn-preset btn-anormal" onclick="viderECG()" title="Vider les champs">✏️</button>
        <button type="button" class="btn-save" onclick="enregistrerAjax('ecg')" title="Enregistrer">💾</button>
        <span style="flex:1;"></span>
        <input type="date" name="Date_ECG" value="<?= $today ?>" id="date_ecg" style="border:1px solid #ddd;border-radius:3px;padding:2px 5px;font-size:11px;color:#1a4a7a;">
    </div>
    <div style="min-height:16px;"><span id="msg_ecg" style="font-size:11px;color:#27ae60;font-weight:bold;display:none;"></span></div>
    <div style="min-height:14px;margin-bottom:4px;"><small id="lbl_exclu_ecg" style="color:#e74c3c;font-weight:bold;font-size:9px;display:none;"></small></div>
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
        <!-- champ radio caché pour compatibilité avec le reste du JS -->
        <input type="radio" name="ecg_global" value="normal"  id="ecg_r_normal"  style="display:none;" checked onchange="toggleECGAnormal(false)">
        <input type="radio" name="ecg_global" value="anormal" id="ecg_r_anormal" style="display:none;" onchange="toggleECGAnormal(true)">
        <div id="ecg_detail" style="display:none;">

            <!-- Trouble de rythme =1 -->
            <div style="margin-top:2px;">
                <label style="font-size:11px;font-weight:bold;cursor:pointer;"><input type="checkbox" class="ecg-parent" data-target="sub_ecg_rythme" onchange="toggleSub(this)"> Trouble de rythme</label>
            </div>
            <div id="sub_ecg_rythme" style="display:none;margin-left:18px;margin-top:1px;">
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child excl1" data-group="ecg_rythme" onchange="exclusifGroup(this)" value="rythme sinusal, absence de trouble de rythme"> rythme sinusal, absence de trouble de rythme</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child excl1" data-group="ecg_rythme" onchange="exclusifGroup(this)" value="arythmie complète par fibrillation auriculaire"> arythmie complète par fibrillation auriculaire</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child excl1" data-group="ecg_rythme" onchange="exclusifGroup(this)" value="tachyarythmie"> tachyarythmie</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child excl1" data-group="ecg_rythme" onchange="exclusifGroup(this)" value="bradyarythmie"> bradyarythmie</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child excl1" data-group="ecg_rythme" onchange="exclusifGroup(this)" value="flutter auriculaire"> flutter auriculaire</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child excl1" data-group="ecg_rythme" onchange="exclusifGroup(this)" value="hyperexcitabilité supra ventriculaire"> hyperexcitabilité supra ventriculaire</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child excl1" data-group="ecg_rythme" onchange="exclusifGroup(this)" value="hyperexcitabilité ventriculaire"> hyperexcitabilité ventriculaire</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child excl1" data-group="ecg_rythme" onchange="exclusifGroup(this)" value="salve de TV"> salve de TV</label><br>
            </div>

            <!-- Conduction AV =1 -->
            <div style="margin-top:3px;">
                <label style="font-size:11px;font-weight:bold;cursor:pointer;"><input type="checkbox" class="ecg-parent" data-target="sub_ecg_condav" onchange="toggleSub(this)"> Trouble de conduction auriculo-ventriculaire</label>
            </div>
            <div id="sub_ecg_condav" style="display:none;margin-left:18px;margin-top:1px;">
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child excl1" data-group="ecg_condav" onchange="exclusifGroup(this)" value="conduction auriculo-ventriculaire normale"> conduction auriculo-ventriculaire normale</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child excl1" data-group="ecg_condav" onchange="exclusifGroup(this)" value="BAV I / trouble de conduction"> BAV I / trouble de conduction</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child excl1" data-group="ecg_condav" onchange="exclusifGroup(this)" value="BAV II : Luciani-Wenckebach"> BAV II : Luciani-Wenckebach</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child excl1" data-group="ecg_condav" onchange="exclusifGroup(this)" value="BAV II : Mobitz II"> BAV II : Mobitz II</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child excl1" data-group="ecg_condav" onchange="exclusifGroup(this)" value="BAV III"> BAV III</label><br>
            </div>

            <!-- Conduction intra-ventriculaire ≥1 -->
            <div style="margin-top:3px;">
                <label style="font-size:11px;font-weight:bold;cursor:pointer;"><input type="checkbox" class="ecg-parent" data-target="sub_ecg_condiv" onchange="toggleSub(this)"> Trouble de conduction intra-ventriculaire</label>
            </div>
            <div id="sub_ecg_condiv" style="display:none;margin-left:18px;margin-top:1px;">
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child excl1" data-group="ecg_condiv" onchange="exclusifGroup(this)" value="bloc incomplet gauche"> bloc incomplet gauche</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child excl1" data-group="ecg_condiv" onchange="exclusifGroup(this)" value="bloc incomplet droit"> bloc incomplet droit</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child excl1" data-group="ecg_condiv" onchange="exclusifGroup(this)" value="hémibloc antérieur gauche"> hémibloc antérieur gauche</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child excl1" data-group="ecg_condiv" onchange="exclusifGroup(this)" value="hémibloc postérieur"> hémibloc postérieur</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child excl1" data-group="ecg_condiv" onchange="exclusifGroup(this)" value="bloc droit complet"> bloc droit complet</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child excl1" data-group="ecg_condiv" onchange="exclusifGroup(this)" value="syndrome de pré-excitation"> syndrome de pré-excitation</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child excl1" data-group="ecg_condiv" onchange="exclusifGroup(this)" value="électro-entraîné, patient porteur d'un pacemaker"> électro-entraîné, pacemaker</label><br>
            </div>

        </div><!-- fin ecg_detail -->
        <button type="button" id="btn_generer_ecg" onclick="genererRapportECG(); document.getElementById('panel_ecg_cases').style.display='none'; document.getElementById('lien_modifier_ecg').style.display='inline'; enregistrerAjax('ecg');" style="margin-top:6px;background:#1a4a7a;color:white;border:none;border-radius:3px;padding:3px 12px;font-size:11px;cursor:pointer;">▶ Générer &amp; 💾</button>
    </div><!-- fin panel_ecg_cases -->
    <span id="lien_modifier_ecg" style="display:none;font-size:10px;">
        <a href="#" onclick="document.getElementById('panel_ecg_cases').style.display=''; document.getElementById('lien_modifier_ecg').style.display='none'; return false;" style="color:#2e6da4;">↺ Modifier les cases</a>
    </span>

    <!-- 12. C/C -->
    <div class="champ" id="wrap_CC"><div class="label-excl"><label>C/C</label><button type="button" class="btn-excl" onclick="toggleExcl('CC')" title="Exclure du rapport">−</button></div>
        <textarea name="CC" oninput="majApercuECG()" placeholder="ex: ECG normal" style="min-height:48px;resize:vertical;"></textarea>
    </div>

    <!-- Autres signes ECG -->
    <div class="champ"><label>Autres signes ECG</label>
        <input type="text" name="AUTRES_SIGNES">
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
        <textarea id="apercu_ecg" readonly
            style="min-height:65px;background:#f0f7ff;border:1px solid #2e6da4;font-size:11px;color:#1a4a7a;resize:vertical;width:100%;padding:4px 6px;border-radius:3px;font-family:Arial,sans-serif;"></textarea>
    </div>
    </form>
</div>

<!-- ══════════════════════════════════════════════
     COLONNE 3 : ECHO-DOPPLER
══════════════════════════════════════════════ -->
<div class="col-card">
    <form id="form-echo">
    <input type="hidden" name="onglet" value="echo">
    <input type="hidden" name="ajax" value="1">
    <div class="col-title">
        <span style="font-size:12px;font-weight:bold;color:#1a4a7a;white-space:nowrap;">🫀 Echo-Doppler</span>
        <button type="button" class="btn-preset btn-normal" onclick="remplirEchoNormal()" title="Valeurs normales">✅</button>
        <button type="button" class="btn-preset btn-anormal" onclick="viderEcho()" title="Vider les champs">✏️</button>
        <button type="button" class="btn-save" onclick="enregistrerAjax('echo')" title="Enregistrer">💾</button>
        <span style="flex:1;"></span>
        <input type="date" name="DATEchog" value="<?= $today ?>" id="date_echo" style="border:1px solid #ddd;border-radius:3px;padding:2px 5px;font-size:11px;color:#1a4a7a;">
    </div>
    <div style="min-height:16px;"><span id="msg_echo" style="font-size:11px;color:#27ae60;font-weight:bold;display:none;"></span></div>
    <div style="min-height:14px;margin-bottom:4px;"><small id="lbl_exclu_echo" style="color:#e74c3c;font-weight:bold;font-size:9px;display:none;"></small></div>
    <div style="display:flex;align-items:center;gap:2px;background:#f0f4f8;border-radius:4px;padding:3px 5px;margin-bottom:8px;">
        <button type="button" onclick="naviguerBilan('echo','last')" title="Premier bilan" style="background:none;color:#2e6da4;border:1px solid #c5d8ed;border-radius:3px;height:20px;min-width:20px;padding:0 3px;font-size:11px;font-weight:bold;cursor:pointer;">|◀</button>
        <button type="button" onclick="naviguerBilan('echo','next')"  title="Précédent"    style="background:none;color:#2e6da4;border:1px solid #c5d8ed;border-radius:3px;height:20px;min-width:20px;padding:0 3px;font-size:11px;font-weight:bold;cursor:pointer;">◀</button>
        <span id="navdate_echo" style="flex:1;text-align:center;font-weight:bold;color:#1a4a7a;font-size:11px;">— nouveau —</span>
        <button type="button" onclick="naviguerBilan('echo','prev')"  title="Suivant"      style="background:none;color:#2e6da4;border:1px solid #c5d8ed;border-radius:3px;height:20px;min-width:20px;padding:0 3px;font-size:11px;font-weight:bold;cursor:pointer;">▶</button>
        <button type="button" onclick="naviguerBilan('echo','first')"  title="Dernier"      style="background:none;color:#2e6da4;border:1px solid #c5d8ed;border-radius:3px;height:20px;min-width:20px;padding:0 3px;font-size:11px;font-weight:bold;cursor:pointer;">▶|</button>
        <button type="button" onclick="nouveauBilan('echo')"          title="Nouveau"      style="background:#27ae60;color:white;border:1px solid #27ae60;border-radius:3px;height:20px;padding:0 6px;font-size:10px;font-weight:bold;cursor:pointer;">▶*</button>
    </div>

    <!-- TYPE_ECHO caché : mis à jour par Normal/Anormal -->
    <input type="hidden" name="TYPE_ECHO" id="type_echo_val" value="Echoscopie cardiaque">

    <!-- ── Cases à cocher CMLM Echo ── -->
    <div id="panel_echo_cases" style="margin-bottom:6px;border:1px solid #b0c8e8;border-radius:5px;padding:6px 8px;background:#f5f9ff;">
        <div style="font-size:11px;font-weight:bold;color:#1a4a7a;margin-bottom:6px;">📋 Échographie — cochez pour générer le rapport Echo</div>

        <!-- Échodoppler normale / anormale — boutons harmonisés -->
        <div style="display:flex;gap:6px;margin-bottom:6px;">
            <button type="button" id="btn_echo_normale"
                onclick="setEchoGlobal('normale')"
                style="flex:1;padding:5px 4px;border:2px solid #27ae60;border-radius:4px;background:#27ae60;color:white;font-size:11px;font-weight:bold;cursor:pointer;">
                ✅ Échographie normale
            </button>
            <button type="button" id="btn_echo_anormale"
                onclick="setEchoGlobal('anormale')"
                style="flex:1;padding:5px 4px;border:2px solid #e67e22;border-radius:4px;background:white;color:#e67e22;font-size:11px;font-weight:bold;cursor:pointer;">
                ⚠️ Échographie anormale
            </button>
        </div>
        <!-- radios cachés pour compatibilité JS -->
        <div id="cmlm_echo_detail" style="display:none;">

            <!-- Cardiopathie hypertensive ≥1 avec SIV -->
            <div style="margin-top:2px;">
                <label style="font-size:11px;font-weight:bold;"><input type="checkbox" class="cmlm-ec" value="cardiopathie hypertensive" onchange="if(this.checked) reporterSIV();"> Cardiopathie hypertensive</label>
                <input type="text" id="ce_siv" placeholder="SIV=" style="width:60px;border:1px solid #ccc;border-radius:2px;padding:1px 3px;font-size:10px;margin-left:4px;">
            </div>

            <!-- Cardiopathie valvulaire ≥1 -->
            <div style="margin-top:3px;">
                <label style="font-size:11px;font-weight:bold;cursor:pointer;"><input type="checkbox" class="cmlm-ep" data-target="ce_valv" onchange="toggleCmlmSub(this)"> Cardiopathie valvulaire</label>
            </div>
            <div id="ce_valv" style="display:none;margin-left:14px;">

                <!-- Aortique ≥1 -->
                <label style="font-size:11px;font-weight:bold;cursor:pointer;"><input type="checkbox" class="cmlm-ep" data-target="ce_ao" onchange="toggleCmlmSub(this)"> Aortique</label>
                <div id="ce_ao" style="display:none;margin-left:12px;">
                    <label style="font-size:11px;cursor:pointer;"><input type="checkbox" class="cmlm-ep" data-target="ce_ra" onchange="toggleCmlmSub(this)"> Rétrécissement aortique</label>
                    <div id="ce_ra" style="display:none;margin-left:12px;">
                        <label style="font-size:11px;"><input type="checkbox" class="cmlm-ec excl1" data-group="ce_ra_g" onchange="exclusifGroup(this)" value="rétrécissement aortique très serré chirurgical"> très serré chirurgical</label><br>
                        <label style="font-size:11px;"><input type="checkbox" class="cmlm-ec excl1" data-group="ce_ra_g" onchange="exclusifGroup(this)" value="rétrécissement aortique serré"> serré</label><br>
                        <label style="font-size:11px;"><input type="checkbox" class="cmlm-ec excl1" data-group="ce_ra_g" onchange="exclusifGroup(this)" value="rétrécissement aortique lâche"> lâche</label><br>
                    </div>
                    <label style="font-size:11px;cursor:pointer;"><input type="checkbox" class="cmlm-ep" data-target="ce_fa" onchange="toggleCmlmSub(this)"> Fuite aortique</label>
                    <div id="ce_fa" style="display:none;margin-left:12px;">
                        <label style="font-size:11px;"><input type="checkbox" class="cmlm-ec excl1" data-group="ce_fa_g" onchange="exclusifGroup(this)" value="fuite aortique chirurgicale"> chirurgicale</label><br>
                        <label style="font-size:11px;"><input type="checkbox" class="cmlm-ec excl1" data-group="ce_fa_g" onchange="exclusifGroup(this)" value="fuite aortique non chirurgicale"> non chirurgicale</label><br>
                    </div>
                    <label style="font-size:11px;cursor:pointer;"><input type="checkbox" class="cmlm-ep" data-target="ce_ma" onchange="toggleCmlmSub(this)"> Maladie aortique</label>
                    <div id="ce_ma" style="display:none;margin-left:12px;">
                        <label style="font-size:11px;"><input type="checkbox" class="cmlm-ec excl1" data-group="ce_ma_g" onchange="exclusifGroup(this)" value="maladie aortique chirurgicale"> chirurgicale</label><br>
                        <label style="font-size:11px;"><input type="checkbox" class="cmlm-ec excl1" data-group="ce_ma_g" onchange="exclusifGroup(this)" value="maladie aortique non chirurgicale"> non chirurgicale</label><br>
                    </div>
                </div>

                <!-- Mitrale ≥1 -->
                <label style="font-size:11px;font-weight:bold;cursor:pointer;"><input type="checkbox" class="cmlm-ep" data-target="ce_mi" onchange="toggleCmlmSub(this)"> Mitrale</label>
                <div id="ce_mi" style="display:none;margin-left:12px;">
                    <label style="font-size:11px;cursor:pointer;"><input type="checkbox" class="cmlm-ep" data-target="ce_rm" onchange="toggleCmlmSub(this)"> Rétrécissement mitral</label>
                    <div id="ce_rm" style="display:none;margin-left:12px;">
                        <label style="font-size:11px;"><input type="checkbox" class="cmlm-ec excl1" data-group="ce_rm_g" onchange="exclusifGroup(this)" value="rétrécissement mitral très serré chirurgical"> très serré chirurgical</label><br>
                        <label style="font-size:11px;"><input type="checkbox" class="cmlm-ec excl1" data-group="ce_rm_g" onchange="exclusifGroup(this)" value="rétrécissement mitral serré"> serré</label><br>
                        <label style="font-size:11px;"><input type="checkbox" class="cmlm-ec excl1" data-group="ce_rm_g" onchange="exclusifGroup(this)" value="rétrécissement mitral lâche"> lâche</label><br>
                    </div>
                    <label style="font-size:11px;cursor:pointer;"><input type="checkbox" class="cmlm-ep" data-target="ce_fm" onchange="toggleCmlmSub(this)"> Fuite mitrale</label>
                    <div id="ce_fm" style="display:none;margin-left:12px;">
                        <label style="font-size:11px;"><input type="checkbox" class="cmlm-ec excl1" data-group="ce_fm_g" onchange="exclusifGroup(this)" value="fuite mitrale chirurgicale"> chirurgicale</label><br>
                        <label style="font-size:11px;"><input type="checkbox" class="cmlm-ec excl1" data-group="ce_fm_g" onchange="exclusifGroup(this)" value="fuite mitrale non chirurgicale"> non chirurgicale</label><br>
                    </div>
                    <label style="font-size:11px;cursor:pointer;"><input type="checkbox" class="cmlm-ep" data-target="ce_mm" onchange="toggleCmlmSub(this)"> Maladie mitrale</label>
                    <div id="ce_mm" style="display:none;margin-left:12px;">
                        <label style="font-size:11px;"><input type="checkbox" class="cmlm-ec excl1" data-group="ce_mm_g" onchange="exclusifGroup(this)" value="maladie mitrale chirurgicale"> chirurgicale</label><br>
                        <label style="font-size:11px;"><input type="checkbox" class="cmlm-ec excl1" data-group="ce_mm_g" onchange="exclusifGroup(this)" value="maladie mitrale non chirurgicale"> non chirurgicale</label><br>
                    </div>
                </div>

                <!-- Tricuspidienne ≥1 -->
                <label style="font-size:11px;font-weight:bold;cursor:pointer;"><input type="checkbox" class="cmlm-ep" data-target="ce_tr" onchange="toggleCmlmSub(this)"> Tricuspidienne</label>
                <div id="ce_tr" style="display:none;margin-left:12px;">
                    <label style="font-size:11px;cursor:pointer;"><input type="checkbox" class="cmlm-ep" data-target="ce_rtr" onchange="toggleCmlmSub(this)"> Rétrécissement tricuspidien</label>
                    <div id="ce_rtr" style="display:none;margin-left:12px;">
                        <label style="font-size:11px;"><input type="checkbox" class="cmlm-ec excl1" data-group="ce_rtr_g" onchange="exclusifGroup(this)" value="rétrécissement tricuspidien serré"> serré</label><br>
                        <label style="font-size:11px;"><input type="checkbox" class="cmlm-ec excl1" data-group="ce_rtr_g" onchange="exclusifGroup(this)" value="rétrécissement tricuspidien lâche"> lâche</label><br>
                    </div>
                    <label style="font-size:11px;cursor:pointer;"><input type="checkbox" class="cmlm-ep" data-target="ce_ft" onchange="toggleCmlmSub(this)"> Fuite tricuspidienne avec HTAP</label>
                    <div id="ce_ft" style="display:none;margin-left:12px;">
                        <label style="font-size:11px;"><input type="checkbox" class="cmlm-ec excl1" data-group="ce_ft_g" onchange="exclusifGroup(this)" value="fuite tricuspidienne avec HTAP moyenne"> moyenne</label><br>
                        <label style="font-size:11px;"><input type="checkbox" class="cmlm-ec excl1" data-group="ce_ft_g" onchange="exclusifGroup(this)" value="fuite tricuspidienne avec HTAP importante"> importante</label><br>
                        <label style="font-size:11px;"><input type="checkbox" class="cmlm-ec excl1" data-group="ce_ft_g" onchange="exclusifGroup(this)" value="fuite tricuspidienne avec HTAP sévère"> sévère</label><br>
                        <label style="font-size:11px;"><input type="checkbox" class="cmlm-ec excl1" data-group="ce_ft_g" onchange="exclusifGroup(this)" value="maladie tricuspidienne avec HTAP"> maladie tricuspidienne</label><br>
                    </div>
                </div>

                <!-- Pulmonaire ≥1 -->
                <label style="font-size:11px;font-weight:bold;cursor:pointer;"><input type="checkbox" class="cmlm-ep" data-target="ce_pu" onchange="toggleCmlmSub(this)"> Pulmonaire</label>
                <div id="ce_pu" style="display:none;margin-left:12px;">
                    <label style="font-size:11px;cursor:pointer;"><input type="checkbox" class="cmlm-ep" data-target="ce_rp" onchange="toggleCmlmSub(this)"> Rétrécissement pulmonaire</label>
                    <div id="ce_rp" style="display:none;margin-left:12px;">
                        <label style="font-size:11px;"><input type="checkbox" class="cmlm-ec excl1" data-group="ce_rp_g" onchange="exclusifGroup(this)" value="rétrécissement pulmonaire valvulaire"> valvulaire</label><br>
                        <label style="font-size:11px;"><input type="checkbox" class="cmlm-ec excl1" data-group="ce_rp_g" onchange="exclusifGroup(this)" value="rétrécissement pulmonaire infundibulaire"> infundibulaire</label><br>
                    </div>
                    <label style="font-size:11px;cursor:pointer;"><input type="checkbox" class="cmlm-ep" data-target="ce_fp" onchange="toggleCmlmSub(this)"> Fuite pulmonaire</label>
                    <div id="ce_fp" style="display:none;margin-left:12px;">
                        <label style="font-size:11px;"><input type="checkbox" class="cmlm-ec excl1" data-group="ce_fp_g" onchange="exclusifGroup(this)" value="fuite pulmonaire moyenne"> moyenne</label><br>
                        <label style="font-size:11px;"><input type="checkbox" class="cmlm-ec excl1" data-group="ce_fp_g" onchange="exclusifGroup(this)" value="fuite pulmonaire importante"> importante</label><br>
                        <label style="font-size:11px;"><input type="checkbox" class="cmlm-ec excl1" data-group="ce_fp_g" onchange="exclusifGroup(this)" value="fuite pulmonaire sévère"> sévère</label><br>
                    </div>
                    <label style="font-size:11px;"><input type="checkbox" class="cmlm-ec" value="maladie pulmonaire"> Maladie pulmonaire</label><br>
                </div>

                <!-- Prothèse ≥1 -->
                <label style="font-size:11px;font-weight:bold;cursor:pointer;"><input type="checkbox" class="cmlm-ep" data-target="ce_proth" onchange="toggleCmlmSub(this)"> Patient porteur de prothèse</label>
                <div id="ce_proth" style="display:none;margin-left:12px;">
                    <label style="font-size:11px;cursor:pointer;"><input type="checkbox" class="cmlm-ep" data-target="ce_prao" onchange="toggleCmlmSub(this)"> En position aortique</label>
                    <div id="ce_prao" style="display:none;margin-left:12px;">
                        <label style="font-size:11px;"><input type="checkbox" class="cmlm-ec excl1" data-group="ce_prao_g" onchange="exclusifGroup(this)" value="patient porteur de prothèse mécanique en position aortique"> prothèse mécanique</label><br>
                        <label style="font-size:11px;"><input type="checkbox" class="cmlm-ec excl1" data-group="ce_prao_g" onchange="exclusifGroup(this)" value="patient porteur d'une bioprothèse en position aortique"> bioprothèse</label><br>
                    </div>
                    <label style="font-size:11px;cursor:pointer;"><input type="checkbox" class="cmlm-ep" data-target="ce_prmi" onchange="toggleCmlmSub(this)"> En position mitrale</label>
                    <div id="ce_prmi" style="display:none;margin-left:12px;">
                        <label style="font-size:11px;"><input type="checkbox" class="cmlm-ec excl1" data-group="ce_prmi_g" onchange="exclusifGroup(this)" value="patient porteur de prothèse mécanique en position mitrale"> prothèse mécanique</label><br>
                        <label style="font-size:11px;"><input type="checkbox" class="cmlm-ec excl1" data-group="ce_prmi_g" onchange="exclusifGroup(this)" value="patient porteur d'une bioprothèse en position mitrale"> bioprothèse</label><br>
                    </div>
                    <label style="font-size:11px;cursor:pointer;"><input type="checkbox" class="cmlm-ep" data-target="ce_prtr" onchange="toggleCmlmSub(this)"> En position tricuspidienne</label>
                    <div id="ce_prtr" style="display:none;margin-left:12px;">
                        <label style="font-size:11px;"><input type="checkbox" class="cmlm-ec excl1" data-group="ce_prtr_g" onchange="exclusifGroup(this)" value="patient a subi une plastie tricuspide"> plastie tricuspide</label><br>
                        <label style="font-size:11px;"><input type="checkbox" class="cmlm-ec excl1" data-group="ce_prtr_g" onchange="exclusifGroup(this)" value="patient a subi une annuloplastie de l'anneau tricuspide"> annuloplastie</label><br>
                    </div>
                </div>
            </div>

            <!-- Cardiopathie ischémique =1 (normale/hypo/aki) puis territoire ≥1 -->
            <div style="margin-top:3px;">
                <label style="font-size:11px;font-weight:bold;cursor:pointer;"><input type="checkbox" class="cmlm-ep" data-target="ce_isch" onchange="toggleCmlmSub(this)"> Cardiopathie ischémique (cinétique)</label>
            </div>
            <div id="ce_isch" style="display:none;margin-left:14px;">
                <label style="font-size:11px;"><input type="checkbox" class="cmlm-ec excl1" data-group="ce_isch_type" onchange="exclusifGroup(this)" value="cinétique globale et régionale normale"> cinétique globale et régionale normale</label><br>
                <!-- Hypokinésie =1 choix de type, territoire ≥1 -->
                <label style="font-size:11px;font-weight:bold;cursor:pointer;"><input type="checkbox" class="cmlm-ec excl1" data-group="ce_isch_type" data-target="ce_hypo" onchange="exclusifGroup(this);toggleCmlmSub(this)" value="hypokinésie du territoire"> hypokinésie du territoire</label>
                <div id="ce_hypo" style="display:none;margin-left:12px;">
                    <?php foreach (['antérieur','antérieur étendu','antéro-apical','antéro-latéral','antéro-septal','antéro-septo-apical','apical','circonférentiel','inférieur','inféro-latéral','latéral','latéro-septal','postérieur','postéro-apical','postéro-latéral','postéro-septal','septal','septo-apical','septal profond'] as $t): ?>
                    <label style="font-size:11px;"><input type="checkbox" class="cmlm-ec"> <?= $t ?></label><br>
                    <?php endforeach; ?>
                
                <button type="button" onclick="appliquerMultiple('ce_hypo')" style="margin-top:3px;background:#1a4a7a;color:white;border:none;border-radius:3px;padding:2px 10px;font-size:10px;cursor:pointer;">✓ OK</button>
            </div>
                <!-- Akinésie =1 choix de type, territoire ≥1 -->
                <label style="font-size:11px;font-weight:bold;cursor:pointer;"><input type="checkbox" class="cmlm-ec excl1" data-group="ce_isch_type" data-target="ce_aki" onchange="exclusifGroup(this);toggleCmlmSub(this)" value="akinésie du territoire"> akinésie du territoire</label>
                <div id="ce_aki" style="display:none;margin-left:12px;">
                    <?php foreach (['antérieur','antérieur étendu','antéro-apical','antéro-latéral','antéro-septal','antéro-septo-apical','apical','circonférentiel','inférieur','inféro-latéral','latéral','latéro-septal','postérieur','postéro-apical','postéro-latéral','postéro-septal','septal','septo-apical','septal profond'] as $t): ?>
                    <label style="font-size:11px;"><input type="checkbox" class="cmlm-ec"> <?= $t ?></label><br>
                    <?php endforeach; ?>
                
                <button type="button" onclick="appliquerMultiple('ce_aki')" style="margin-top:3px;background:#1a4a7a;color:white;border:none;border-radius:3px;padding:2px 10px;font-size:10px;cursor:pointer;">✓ OK</button>
            </div>
            </div>

            <!-- Cardiopathie dilatée =1 FEVG -->
            <div style="margin-top:3px;">
                <label style="font-size:11px;font-weight:bold;cursor:pointer;"><input type="checkbox" class="cmlm-ep" data-target="ce_dil" onchange="toggleCmlmSub(this); if(this.checked) reporterFEVG();"> Cardiopathie dilatée</label>
            </div>
            <div id="ce_dil" style="display:none;margin-left:14px;">
                <label style="font-size:11px;"><input type="checkbox" class="cmlm-ec excl1" data-group="ce_fevg" value="FEVG conservée" onchange="exclusifGroup(this)"> FEVG conservée <input type="text" id="ce_fevg_cons" placeholder="%" style="width:36px;border:1px solid #ccc;border-radius:2px;padding:1px 3px;font-size:10px;"></label><br>
                <label style="font-size:11px;"><input type="checkbox" class="cmlm-ec excl1" data-group="ce_fevg" value="FEVG altérée" onchange="exclusifGroup(this)"> FEVG altérée <input type="text" id="ce_fevg_alt" placeholder="%" style="width:36px;border:1px solid #ccc;border-radius:2px;padding:1px 3px;font-size:10px;"></label><br>
                <label style="font-size:11px;"><input type="checkbox" class="cmlm-ec excl1" data-group="ce_fevg" value="FEVG très altérée en bas débit" onchange="exclusifGroup(this)"> FEVG très altérée en bas débit <input type="text" id="ce_fevg_tres" placeholder="%" style="width:36px;border:1px solid #ccc;border-radius:2px;padding:1px 3px;font-size:10px;"></label><br>
            </div>

        </div>
    </div><!-- fin panel_echo_cases -->

    <!-- Bouton Générer sous les cases -->
    <button type="button" id="btn_generer_echo"
        onclick="genererCmlmEcho(); document.getElementById('panel_echo_cases').style.display='none'; document.getElementById('btn_generer_echo').style.display='none'; document.getElementById('lien_modifier_echo').style.display='inline'; enregistrerAjax('echo');"
        style="margin-top:4px;margin-bottom:4px;background:#1a4a7a;color:white;border:none;border-radius:3px;padding:3px 12px;font-size:11px;cursor:pointer;width:100%;">▶ Générer &amp; 💾 Échocardiographie</button>
    <span id="lien_modifier_echo" style="display:none;font-size:10px;margin-left:6px;">
        <a href="#" onclick="document.getElementById('panel_echo_cases').style.display=''; document.getElementById('btn_generer_echo').style.display='block'; document.getElementById('lien_modifier_echo').style.display='none'; return false;" style="color:#2e6da4;">↺ Modifier les cases</a>
    </span>

    <input type="hidden" name="CMLM_ECHO" id="cmlm_echo_val">

    <!-- Mesures Echo -->
    <style>
        #em_DTD::-webkit-inner-spin-button,#em_DTD::-webkit-outer-spin-button,
        #em_DTS::-webkit-inner-spin-button,#em_DTS::-webkit-outer-spin-button,
        #inp_FEVG::-webkit-inner-spin-button,#inp_FEVG::-webkit-outer-spin-button,
        #inp_SIV::-webkit-inner-spin-button, #inp_SIV::-webkit-outer-spin-button,
        #em_PP::-webkit-inner-spin-button,   #em_PP::-webkit-outer-spin-button
        { -webkit-appearance:none; margin:0; }
        #em_DTD,#em_DTS,#inp_FEVG,#inp_SIV,#em_PP { -moz-appearance:textfield; }
    </style>
    <div style="display:flex;align-items:center;gap:5px;flex-wrap:wrap;margin:6px 0 4px 0;">
        <label style="font-size:10px;color:#888;">DTD-VG</label>
        <input type="number" id="em_DTD" name="DTD_VG" placeholder="mm" style="width:48px;padding:2px 4px;border:1px solid #ddd;border-radius:3px;font-size:11px;">
        <label style="font-size:10px;color:#888;">DTS-VG</label>
        <input type="number" id="em_DTS" name="DTS_VG" placeholder="mm" style="width:48px;padding:2px 4px;border:1px solid #ddd;border-radius:3px;font-size:11px;">
        <label style="font-size:10px;color:#888;">FEVG</label>
        <input type="number" id="inp_FEVG" name="FEVG" placeholder="%" style="width:44px;padding:2px 4px;border:1px solid #ddd;border-radius:3px;font-size:11px;" oninput="reporterFEVG()">
        <label style="font-size:10px;color:#888;">SIV</label>
        <input type="number" id="inp_SIV" step="0.1" name="SIV" placeholder="mm" style="width:44px;padding:2px 4px;border:1px solid #ddd;border-radius:3px;font-size:11px;" oninput="reporterSIV()">
        <label style="font-size:10px;color:#888;">PP</label>
        <input type="number" id="em_PP" step="0.1" name="PP" placeholder="mm" style="width:44px;padding:2px 4px;border:1px solid #ddd;border-radius:3px;font-size:11px;">
    </div>

    <div class="champ" id="wrap_echo_HTAP">
        <div class="label-excl"><label>HTAP</label><button type="button" class="btn-excl" onclick="toggleExclEcho('HTAP')" title="Exclure">−</button></div>
        <input type="text" name="HTAP" oninput="majConcatEcho()">
    </div>
    <div class="champ" id="wrap_DTSA">
        <div class="label-excl"><label>DTSA</label><button type="button" class="btn-excl" onclick="toggleExcl('DTSA')" title="Exclure du rapport">−</button></div>
        <textarea name="DTSA" class="court" oninput="majConcatEcho()"></textarea>
    </div>

    <!-- Champs cachés exclusion Echo -->
    <input type="hidden" id="excl_DTSA"        name="excl_DTSA">
    <input type="hidden" id="excl_CONCLUSION1" name="excl_CONCLUSION1">

    <!-- Aperçu Echo -->
    <div class="champ" id="wrap_CONCLUSION1" style="margin-top:6px;">
        <div class="label-excl">
            <label style="font-size:10px;color:#2e6da4;font-weight:bold;">👁 Aperçu rapport Echo <small style="color:#888;font-weight:normal;">(modifiable)</small></label>
            <button type="button" class="btn-excl" onclick="toggleExcl('CONCLUSION1')" title="Exclure du rapport">−</button>
        </div>
        <textarea name="CONCLUSION1" id="conclusion1_echo"
            style="min-height:80px;background:#f0f7ff;border:1px solid #2e6da4;font-size:11px;color:#1a4a7a;resize:vertical;width:100%;padding:4px 6px;border-radius:3px;font-family:Arial,sans-serif;"
            oninput="majApercuEcho()"></textarea>
    </div>
    </form>
</div>

<!-- ══════════════════════════════════════════════
     COLONNE 4 : BIOLOGIE (lecture seule)
══════════════════════════════════════════════ -->
<div class="col-card" id="card-bio-nbc">
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
    <div id="bio-nbc-resultats" style="font-size:11px;">
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
    ap.value = p.join(' ; ') || '—';
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
    if (c1) c1.value = p.join(' ; ');
    majApercuEcho();
}


/* ══════════════════════════════════════════════════════
   PRESET EXAMEN
══════════════════════════════════════════════════════ */

function setExamenNormal() {
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
    ['n_angor','n_dysp','n_palp','n_arterite','n_phlebit'].forEach(function(id){
        var cb = document.getElementById(id);
        if(cb && cb.checked) parties.push(cb.value);
    });
    var ap = document.getElementById('apercu_examen');
    if(ap) ap.value = parties.length > 0 ? parties.join('. ') : '—';
    var condParts = [];
    var ecvn = document.getElementById('cat_ecvn');
    var apte = document.getElementById('cat_apte');
    if(ecvn && ecvn.checked) condParts.push(ecvn.value);
    else if(apte && apte.checked) condParts.push(apte.value);
    document.querySelectorAll('.cat-check:checked').forEach(function(cb){ if(cb.value) condParts.push(cb.value); });
    var autresN = document.getElementById('cat_autres_n');
    if(autresN && autresN.value.trim()) condParts.push(autresN.value.trim());
    var ct = document.getElementById('conduite_textarea');
    if(ct && condParts.length > 0) ct.value = condParts.join('. ');
    document.getElementById('panel_sympto').style.display = 'none';
    document.getElementById('lien_modifier_sympto').style.display = 'inline';
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
             'Autres_Symptomes','Conclusion','REMARQUE','Conduite_ATenir']
            .forEach(function(n){var e=document.querySelector('[name='+n+']');if(e)e.value=d[n]||'';});
        }
        if(type==='ecg'){
            ['FREQUENCE','rythme_sv','trouble_rv','rythme_v','conduction_nodale','QRS',
             'infrastructure_de_conduction','REPOLARISATION','SEGMENT_ST','TOPOGRAPHIE_ST',
             'ONDE_T','TOPOGRAPHIE_T','IDM','TOPOGRAPHIE_Q','CC','AUTRES_SIGNES']
            .forEach(function(n){var e=document.querySelector('[name='+n+']');if(e)e.value=d[n]||'';});
            majApercuECG();
        }
        if(type==='echo'){
            ['FEVG','DTD_VG','DTS_VG','SIV','PP','RACINE_AO','HTAP','CINETIQUE',
             'ECHOGENICITE','DOPPLER','DTSA','CONCLUSION1']
            .forEach(function(n){var e=document.querySelector('[name='+n+']');if(e)e.value=d[n]||'';});
        }
    }).catch(function(e){alert('Erreur : '+e.message);});
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
}
function toggleCmlmSub(cb) {
    const t = document.getElementById(cb.dataset.target);
    if (t) t.style.display = cb.checked ? 'block' : 'none';
    if (!cb.checked && t) t.querySelectorAll('input').forEach(i => {
        i.checked = false;
        if (i.dataset && i.dataset.target) { const s = document.getElementById(i.dataset.target); if(s) s.style.display='none'; }
    });
}
function genererCmlmEcho() {
    var detail = document.getElementById('cmlm_echo_detail');
    var estNormale = detail && detail.style.display === 'none';
    let parties = [];
    if (estNormale) {
        parties.push('échodoppler cardiaque normale');
    } else {
        document.querySelectorAll('.cmlm-ec:checked').forEach(function(cb) {
            var txt = (cb.value && cb.value !== 'on') ? cb.value : '';
            if (txt === 'FEVG conservée') {
                var v = document.getElementById('ce_fevg_cons');
                if (v && v.value) txt += ' ' + v.value + '%';
            } else if (txt === 'FEVG très altérée en bas débit') {
                var v = document.getElementById('ce_fevg_tres');
                if (v && v.value) txt += ' ' + v.value + '%';
            } else if (txt === 'FEVG altérée') {
                var v = document.getElementById('ce_fevg_alt');
                if (v && v.value) txt += ' ' + v.value + '%';
            }
            if (txt.indexOf('hypertensive') !== -1) {
                var siv = document.getElementById('ce_siv');
                if (siv && siv.value) txt += ' (SIV=' + siv.value + ')';
            }
            if (txt) parties.push(txt);
        });
        if (parties.length === 0) parties.push('échodoppler cardiaque anormale');
    }
    /* Un item par ligne */
    var result = parties.map(function(p){return '- '+p;}).join('\n');
    document.getElementById('cmlm_echo_val').value = result;
    /* Remplir l'aperçu principal */
    var ap = document.getElementById('conclusion1_echo');
    if (ap) ap.value = result;
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
    var panel = document.getElementById('panel_ecg_cases');
    if (panel) panel.style.display = '';
    if (val === 'normal') {
        if (bn) { bn.style.background = '#27ae60'; bn.style.color = 'white'; }
        if (ba) { ba.style.background = 'white';   ba.style.color = '#e67e22'; }
        toggleECGAnormal(false);
        /* Remplir aperçu ECG normal directement */
        var fcEl = document.getElementById('inp_FC');
        var prefN = fcEl && fcEl.value ? 'FC : ' + fcEl.value + ' bpm — ' : '';
        var ap = document.getElementById('apercu_ecg');
        if (ap) ap.value = prefN + 'ECG sinusal normal';
    } else {
        if (ba) { ba.style.background = '#e67e22'; ba.style.color = 'white'; }
        if (bn) { bn.style.background = 'white';   bn.style.color = '#27ae60'; }
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
        if (bn) { bn.style.background = '#27ae60'; bn.style.color = 'white'; }
        if (ba) { ba.style.background = 'white';   ba.style.color = '#e67e22'; }
        toggleCmlmEcho(false);
    } else {
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
    ['sympto_angor','sympto_dyspnee','sympto_rythme','sympto_arterite','sympto_phlebite'].forEach(function(name) {
        var r = document.querySelector('input[name="'+name+'"]:checked');
        if (r && r.value) parties.push(r.value);
    });

    /* Remplit l'Aperçu rapport Examen */
    var ap = document.getElementById('apercu_examen');
    if (ap) ap.value = parties.length > 0 ? parties.join('. ') : '—';

    /* 3. Conduite à tenir bloc Anormal */
    var condParts = [];
    var catEcvn2 = document.getElementById('cat_ecvn2');
    var catApte2 = document.getElementById('cat_apte2');
    if (catEcvn2 && catEcvn2.checked) condParts.push(catEcvn2.value);
    else if (catApte2 && catApte2.checked) condParts.push(catApte2.value);
    document.querySelectorAll('.cat-check2:checked').forEach(function(cb) {
        if (cb.value) condParts.push(cb.value);
    });
    var autresA = document.getElementById('cat_autres_a');
    if (autresA && autresA.value.trim()) condParts.push(autresA.value.trim());

    if (condParts.length > 0) {
        var ct = document.getElementById('conduite_textarea');
        if (ct) ct.value = condParts.join('. ');
    }
}
/* genererRapportECG : remplit apercu_ecg (C/C reste libre) */
function genererRapportECG() {
    /* FC en tête */
    var fc  = document.getElementById('inp_FC');
    var prefixe = '';
    if (fc && fc.value) prefixe = 'FC : ' + fc.value + ' bpm — ';

    var detail = document.getElementById('ecg_detail');
    var estNormal = detail && detail.style.display === 'none';
    var txt = '';
    if (estNormal) {
        txt = prefixe + 'ECG sinusal normal';
    } else {
        var parties = [];
        document.querySelectorAll('#panel_ecg_cases input[type="checkbox"]:checked').forEach(function(cb) {
            if (cb.classList.contains('ecg-parent')) return;
            if (cb.value && cb.value !== 'on') { parties.push(cb.value); return; }
            var lbl = cb.parentElement;
            if (lbl) { var t = lbl.textContent.trim(); if (t) parties.push(t); }
        });
        txt = prefixe + parties.join('\n');
    }
    /* Aperçu uniquement — C/C reste vide */
    var ap = document.getElementById('apercu_ecg');
    if (ap) ap.value = txt;
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

</script>

</body>
</html>
