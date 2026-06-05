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
        <button type="button" class="btn-preset btn-anormal" onclick="viderExamen()" title="Vider les champs">✏️</button>
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

    <div class="sec">Mesures</div>
    <div style="display:flex;align-items:center;gap:5px;flex-wrap:wrap;margin-bottom:8px;">
        <label style="font-size:10px;color:#888;">TAS</label>
        <input type="number" name="TAS" placeholder="120" style="width:50px;padding:3px 5px;border:1px solid #ddd;border-radius:3px;font-size:11px;">
        <label style="font-size:10px;color:#888;">TAD</label>
        <input type="number" name="TAD" placeholder="80" style="width:50px;padding:3px 5px;border:1px solid #ddd;border-radius:3px;font-size:11px;">
        <label style="font-size:10px;color:#888;">FC</label>
        <input type="number" name="FC" placeholder="70" style="width:50px;padding:3px 5px;border:1px solid #ddd;border-radius:3px;font-size:11px;">
        <label style="font-size:10px;color:#888;">Poids</label>
        <input type="number" step="0.1" name="POIDS" placeholder="70" style="width:50px;padding:3px 5px;border:1px solid #ddd;border-radius:3px;font-size:11px;">
        <label style="font-size:10px;color:#888;">Taille</label>
        <input type="number" name="TAILLE" placeholder="170" style="width:50px;padding:3px 5px;border:1px solid #ddd;border-radius:3px;font-size:11px;">
    </div>

    <div class="sec">Clinique  </div>
    <div class="champ" id="wrap_S_Fonctionnels">
        <label>Signes fonctionnels</label>
        <div class="excl-wrap">
            <textarea name="S_Fonctionnels" class="court" oninput="majApercuExamen()"></textarea>
            <button type="button" class="btn-excl" onclick="toggleExcl('S_Fonctionnels')" title="Exclure du rapport">−</button>
        </div>
    </div>
    <div class="champ" id="wrap_Auscult_Cardiaque">
        <label>Auscultation cardiaque</label>
        <div class="excl-wrap">
            <textarea name="Auscult_Cardiaque" class="court" oninput="majApercuExamen()"></textarea>
            <button type="button" class="btn-excl" onclick="toggleExcl('Auscult_Cardiaque')" title="Exclure du rapport">−</button>
        </div>
    </div>
    <div class="champ" id="wrap_Auscult_Pulmonaire">
        <label>Auscultation pulmonaire</label>
        <div class="excl-wrap">
            <textarea name="Auscult_Pulmonaire" class="court" oninput="majApercuExamen()"></textarea>
            <button type="button" class="btn-excl" onclick="toggleExcl('Auscult_Pulmonaire')" title="Exclure du rapport">−</button>
        </div>
    </div>
    <div class="champ" id="wrap_Examen_Vasculaire">
        <label>Examen vasculaire</label>
        <div class="excl-wrap">
            <textarea name="Examen_Vasculaire" class="court" oninput="majApercuExamen()"></textarea>
            <button type="button" class="btn-excl" onclick="toggleExcl('Examen_Vasculaire')" title="Exclure du rapport">−</button>
        </div>
    </div>
    <div class="grid2">
        <div class="champ" id="wrap_Signes_IVG">
            <label>Signes IVG</label>
            <div class="excl-wrap">
                <textarea name="Signes_IVG" class="court" oninput="majApercuExamen()"></textarea>
                <button type="button" class="btn-excl" onclick="toggleExcl('Signes_IVG')" title="Exclure du rapport">−</button>
            </div>
        </div>
        <div class="champ" id="wrap_Signes_IVD">
            <label>Signes IVD</label>
            <div class="excl-wrap">
                <textarea name="Signes_IVD" class="court" oninput="majApercuExamen()"></textarea>
                <button type="button" class="btn-excl" onclick="toggleExcl('Signes_IVD')" title="Exclure du rapport">−</button>
            </div>
        </div>
    </div>
    <div class="champ" id="wrap_Autres_Symptomes">
        <label>Autres symptômes</label>
        <div class="excl-wrap">
            <textarea name="Autres_Symptomes" class="court" oninput="majApercuExamen()"></textarea>
            <button type="button" class="btn-excl" onclick="toggleExcl('Autres_Symptomes')" title="Exclure du rapport">−</button>
        </div>
    </div>

    <div class="sec">Conclusion &amp; Remarque</div>

    <!-- ── Cases à cocher Symptomatologie clinique ── -->
    <div id="panel_sympto" style="margin-bottom:6px;border:1px solid #b0c8e8;border-radius:5px;padding:6px 8px;background:#f5f9ff;">
        <div style="font-size:11px;font-weight:bold;color:#1a4a7a;margin-bottom:5px;">🩺 Symptomatologie — cochez pour générer la conclusion</div>

        <!-- Symptomatologie douloureuse =1 -->
        <div style="margin-top:2px;">
            <label style="font-size:11px;font-weight:bold;cursor:pointer;"><input type="checkbox" class="sympto-parent" data-target="sub_angor" onchange="toggleSub(this)"> Symptomatologie douloureuse (angor)</label>
        </div>
        <div id="sub_angor" style="display:none;margin-left:18px;margin-top:1px;">
            <label style="font-size:11px;"><input type="checkbox" class="sympto-child excl1" data-group="angor" onchange="exclusifGroup(this)" value="absence de symptomatologie douloureuse (angor)"> absence de symptomatologie douloureuse (angor)</label><br>
            <label style="font-size:11px;"><input type="checkbox" class="sympto-child excl1" data-group="angor" onchange="exclusifGroup(this)" value="angor d'effort"> angor d'effort</label><br>
            <label style="font-size:11px;"><input type="checkbox" class="sympto-child excl1" data-group="angor" onchange="exclusifGroup(this)" value="angor crescendo"> angor crescendo</label><br>
        </div>

        <!-- Symptomatologie dyspnéique =1 -->
        <div style="margin-top:3px;">
            <label style="font-size:11px;font-weight:bold;cursor:pointer;"><input type="checkbox" class="sympto-parent" data-target="sub_dyspnee" onchange="toggleSub(this)"> Symptomatologie dyspnéique</label>
        </div>
        <div id="sub_dyspnee" style="display:none;margin-left:18px;margin-top:1px;">
            <label style="font-size:11px;"><input type="checkbox" class="sympto-child excl1" data-group="dyspnee" onchange="exclusifGroup(this)" value="absence de dyspnée"> absence de dyspnée</label><br>
            <label style="font-size:11px;"><input type="checkbox" class="sympto-child excl1" data-group="dyspnee" onchange="exclusifGroup(this)" value="dyspnée stade I NYHA"> dyspnée stade I NYHA</label><br>
            <label style="font-size:11px;"><input type="checkbox" class="sympto-child excl1" data-group="dyspnee" onchange="exclusifGroup(this)" value="dyspnée d'effort stade II NYHA"> dyspnée d'effort stade II NYHA</label><br>
            <label style="font-size:11px;"><input type="checkbox" class="sympto-child excl1" data-group="dyspnee" onchange="exclusifGroup(this)" value="dyspnée d'effort stade III NYHA"> dyspnée d'effort stade III NYHA</label><br>
            <label style="font-size:11px;"><input type="checkbox" class="sympto-child excl1" data-group="dyspnee" onchange="exclusifGroup(this)" value="suspicion d'embolie pulmonaire"> suspicion d'embolie pulmonaire</label><br>
        </div>

        <!-- Symptomatologie rythmique =1 -->
        <div style="margin-top:3px;">
            <label style="font-size:11px;font-weight:bold;cursor:pointer;"><input type="checkbox" class="sympto-parent" data-target="sub_rythme" onchange="toggleSub(this)"> Symptomatologie rythmique</label>
        </div>
        <div id="sub_rythme" style="display:none;margin-left:18px;margin-top:1px;">
            <label style="font-size:11px;"><input type="checkbox" class="sympto-child excl1" data-group="rythme_sympto" onchange="exclusifGroup(this)" value="absence de palpitations"> absence de palpitations</label><br>
            <label style="font-size:11px;"><input type="checkbox" class="sympto-child excl1" data-group="rythme_sympto" onchange="exclusifGroup(this)" value="palpitations"> palpitations</label><br>
            <label style="font-size:11px;"><input type="checkbox" class="sympto-child excl1" data-group="rythme_sympto" onchange="exclusifGroup(this)" value="tachycardie"> tachycardie</label><br>
            <label style="font-size:11px;"><input type="checkbox" class="sympto-child excl1" data-group="rythme_sympto" onchange="exclusifGroup(this)" value="bradycardie"> bradycardie</label><br>
        
                <button type="button" onclick="appliquerMultiple('sub_rythme')" style="margin-top:3px;background:#1a4a7a;color:white;border:none;border-radius:3px;padding:2px 10px;font-size:10px;cursor:pointer;">✓ OK</button>
            </div>

        <!-- Symptomatologie artéritique =1 -->
        <div style="margin-top:3px;">
            <label style="font-size:11px;font-weight:bold;cursor:pointer;"><input type="checkbox" class="sympto-parent" data-target="sub_arterite" onchange="toggleSub(this)"> Symptomatologie artéritique des MI</label>
        </div>
        <div id="sub_arterite" style="display:none;margin-left:18px;margin-top:1px;">
            <label style="font-size:11px;"><input type="checkbox" class="sympto-child excl1" data-group="arterite" onchange="exclusifGroup(this)" value="périmètre de marche normal, absence de claudication intermittente"> périmètre de marche normal, absence de claudication intermittente</label><br>
            <label style="font-size:11px;"><input type="checkbox" class="sympto-child excl1" data-group="arterite" onchange="exclusifGroup(this)" value="artérite stade I"> artérite stade I</label><br>
            <label style="font-size:11px;"><input type="checkbox" class="sympto-child excl1" data-group="arterite" onchange="exclusifGroup(this)" value="artérite stade II"> artérite stade II</label><br>
            <label style="font-size:11px;"><input type="checkbox" class="sympto-child excl1" data-group="arterite" onchange="exclusifGroup(this)" value="artérite stade IV"> artérite stade IV</label><br>
            <label style="font-size:11px;"><input type="checkbox" class="sympto-child excl1" data-group="arterite" onchange="exclusifGroup(this)" value="gangrène"> gangrène</label><br>
        
                <button type="button" onclick="appliquerMultiple('sub_arterite')" style="margin-top:3px;background:#1a4a7a;color:white;border:none;border-radius:3px;padding:2px 10px;font-size:10px;cursor:pointer;">✓ OK</button>
            </div>

        <!-- Symptomatologie phlébitique ≥1 -->
        <div style="margin-top:3px;">
            <label style="font-size:11px;font-weight:bold;cursor:pointer;"><input type="checkbox" class="sympto-parent" data-target="sub_phlebite" onchange="toggleSub(this)"> Symptomatologie phlébitique</label>
        </div>
        <div id="sub_phlebite" style="display:none;margin-left:18px;margin-top:1px;">
            <label style="font-size:11px;"><input type="checkbox" class="sympto-child" value="absence de varices, absence d'œdèmes des MI"> absence de varices, absence d'œdèmes des MI</label><br>
            <label style="font-size:11px;"><input type="checkbox" class="sympto-child" value="varices des MI"> varices des MI</label><br>
            <label style="font-size:11px;"><input type="checkbox" class="sympto-child" value="phlébite des MI"> phlébite des MI</label><br>
            <label style="font-size:11px;"><input type="checkbox" class="sympto-child" value="trouble trophique des MI"> trouble trophique des MI</label><br>
        
                <button type="button" onclick="appliquerMultiple('sub_phlebite')" style="margin-top:3px;background:#1a4a7a;color:white;border:none;border-radius:3px;padding:2px 10px;font-size:10px;cursor:pointer;">✓ OK</button>
            </div>

        <button type="button" onclick="genererConclusion(); enregistrerAjax('examen'); document.getElementById('panel_sympto').style.display='none'; document.getElementById('lien_modifier_sympto').style.display='inline';" style="margin-top:6px;background:#1a4a7a;color:white;border:none;border-radius:3px;padding:3px 12px;font-size:11px;cursor:pointer;">▶ Générer & 💾</button>
    </div>
    <span id="lien_modifier_sympto" style="display:none;font-size:10px;">
        <a href="#" onclick="document.getElementById('panel_sympto').style.display=''; document.getElementById('lien_modifier_sympto').style.display='none'; return false;" style="color:#2e6da4;">↺ Modifier les cases</a>
    </span>
    <div class="sec">Au total — Conduite à tenir</div>
    <div class="champ">
        <label>Conduite à tenir</label>
        <textarea name="Conduite_ATenir" style="min-height:70px;" placeholder="Conclusion générale et plan de prise en charge..."></textarea>
    </div>

    <div class="champ" id="wrap_REMARQUE">
        <div class="label-excl">
            <label>Remarque</label>
            <button type="button" class="btn-excl" onclick="toggleExcl('REMARQUE')" title="Exclure du rapport">−</button>
        </div>
        <div class="excl-wrap">
            <textarea name="REMARQUE" class="court" oninput="majApercuExamen()"></textarea>
            <button type="button" onclick="viderConclusionRemarque()" title="Vider pour saisie libre"
                style="flex-shrink:0;height:20px;padding:0 5px;border:1px solid #e67e22;border-radius:3px;background:#e67e22;color:white;font-size:9px;font-weight:bold;cursor:pointer;white-space:nowrap;">ECVAN</button>
        </div>
    </div>
    <div class="champ" id="wrap_Conclusion">
        <div class="label-excl">
            <label>Conclusion</label>
            <button type="button" class="btn-excl" onclick="toggleExcl('Conclusion')" title="Exclure du rapport">−</button>
        </div>
        <div class="excl-wrap">
            <textarea name="Conclusion" class="court" oninput="majApercuExamen()" style="background:#fff8f0;border:1px solid #e67e22;"></textarea>
            <button type="button" onclick="setConclusionECVN()" title="Examen Cardio-Vasculaire Normal"
                style="flex-shrink:0;height:20px;padding:0 5px;border:1px solid #27ae60;border-radius:3px;background:#27ae60;color:white;font-size:9px;font-weight:bold;cursor:pointer;white-space:nowrap;">ECVN</button>
        </div>
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
    <div class="champ"><label>Fréquence (bpm)</label>
        <input type="number" name="FREQUENCE" placeholder="75" oninput="majApercuECG()" min="20" max="300">
    </div>

    <!-- 2. Rythme supra-ventriculaire -->
    <div class="champ" id="wrap_rythme_sv"><div class="label-excl"><label>Rythme supra-ventriculaire</label><button type="button" class="btn-excl" onclick="toggleExcl('rythme_sv')" title="Exclure du rapport">−</button></div>
        <select name="rythme_sv" onchange="majApercuECG()">
            <option value="">—</option>
            <?php foreach([
                'sinusal',
                'arythmie complete par fibrillation auriculaire',
                'tachysystolie auriculaire',
                'flutter auriculaire 1/1',
                'flutter auriculaire 2/1',
                'flutter auriculaire 3/1',
                'tachyarythmie',
                'bradyarythmie',
                'bradycardie sinusale',
                'tachycardie sinusale',
                'rythme jonctionelle',
                'rythme du sinus auriculaire',
                'electro entraine'
            ] as $v): ?>
            <option value="<?= $v ?>"><?= ucfirst($v) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- 3. Trouble de rythme ventriculaire -->
    <div class="champ" id="wrap_trouble_rv"><div class="label-excl"><label>Trouble de rythme ventriculaire</label><button type="button" class="btn-excl" onclick="toggleExcl('trouble_rv')" title="Exclure du rapport">−</button></div>
        <select name="trouble_rv" onchange="majApercuECG()">
            <option value="">—</option>
            <option>régulier</option>
            <option>irrégulier</option>
        </select>
    </div>

    <!-- 4. Rythme ventriculaire -->
    <div class="champ"><label>Rythme ventriculaire</label>
        <input type="text" name="rythme_v" placeholder="">
    </div>

    <!-- 5. Conduction nodale -->
    <div class="champ" id="wrap_conduction_nodale"><div class="label-excl"><label>Conduction nodale</label><button type="button" class="btn-excl" onclick="toggleExcl('conduction_nodale')" title="Exclure du rapport">−</button></div>
        <select name="conduction_nodale" onchange="majApercuECG()">
            <option value="">—</option>
            <?php foreach([
                'normale','BAV I','BAVII','BAVIII',
                'MOBITZ I','MOBITZ II','Luciani Weckenbeg'
            ] as $v): ?>
            <option value="<?= $v ?>"><?= $v ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- 6. QRS -->
    <div class="champ" id="wrap_QRS"><div class="label-excl"><label>QRS</label><button type="button" class="btn-excl" onclick="toggleExcl('QRS')" title="Exclure du rapport">−</button></div>
        <select name="QRS" onchange="majApercuECG()">
            <option value="">—</option>
            <?php foreach([
                'normaux',
                'bas voltage en derivations standarts'
            ] as $v): ?>
            <option value="<?= $v ?>"><?= ucfirst($v) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- 7. Conduction infranodale -->
    <div class="champ" id="wrap_infrastructure_de_conduction"><div class="label-excl"><label>Conduction infranodale</label><button type="button" class="btn-excl" onclick="toggleExcl('infrastructure_de_conduction')" title="Exclure du rapport">−</button></div>
        <select name="infrastructure_de_conduction">
            <option value="">—</option>
            <?php foreach([
                'conductInfraN normale',
                'Bloc incomplet gauche',
                'Bloc incomplet droit',
                'hemibloc anterieur gauche',
                'hemibloc posterieur',
                'bloc droit complet',
                'Bloc incomplet gauche et Bloc incomplet droit',
                'hemibloc incomplet gauche',
                'syndrome de preexitation'
            ] as $v): ?>
            <option value="<?= $v ?>"><?= ucfirst($v) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- 8. Repolarisation -->
    <div class="champ" id="wrap_REPOLARISATION"><div class="label-excl"><label>Repolarisation</label><button type="button" class="btn-excl" onclick="toggleExcl('REPOLARISATION')" title="Exclure du rapport">−</button></div>
        <select name="REPOLARISATION" onchange="majApercuECG()">
            <option value="">—</option>
            <option>normale</option>
            <option>anormale</option>
        </select>
    </div>

    <!-- 9. Segment ST + Topographie ST -->
    <div class="grid2">
        <div class="champ"><label>Segment ST</label>
            <select name="SEGMENT_ST">
                <option value="">—</option>
                <?php foreach([
                    'normal','plat',
                    'sous decalage ascendant',
                    'sous decalage descendant'
                ] as $v): ?>
                <option value="<?= $v ?>"><?= ucfirst($v) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="champ"><label>Topographie ST</label>
            <select name="TOPOGRAPHIE_ST">
                <option value="">—</option>
                <?php foreach([
                    'anterieur','anterieur etendu','antero-apical',
                    'antero-lateral','antero-septal','antero-septo-apical',
                    'apical','circonferonciel','inferieur','infero-lateral',
                    'infero-septal','lateral','latero-septal','posterieur',
                    'postero-apical','postero-lateral','postero-septal',
                    'septal','septo-apical','septal profond'
                ] as $v): ?>
                <option value="<?= $v ?>"><?= ucfirst($v) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- 10. Onde T + Topographie T -->
    <div class="grid2">
        <div class="champ"><label>Onde T</label>
            <select name="ONDE_T">
                <option value="">—</option>
                <?php foreach([
                    'normale','plates','negatives',
                    'trouble diffus de repolarisation'
                ] as $v): ?>
                <option value="<?= $v ?>"><?= ucfirst($v) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="champ"><label>Topographie T</label>
            <select name="TOPOGRAPHIE_T">
                <option value="">—</option>
                <?php foreach([
                    'anterieur','anterieur etendu','antero-apical',
                    'antero-lateral','antero-septal','antero-septo-apical',
                    'apical','circonferonciel','inferieur','infero-lateral',
                    'infero-septal','lateral','latero-septal','posterieur',
                    'postero-apical','postero-lateral','postero-septal',
                    'septal','septo-apical','septal profond'
                ] as $v): ?>
                <option value="<?= $v ?>"><?= ucfirst($v) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- 11. IDM + Topographie Q -->
    <div class="grid2">
        <div class="champ"><label>IDM (signes d'infarctus)</label>
            <select name="IDM">
                <option value="">—</option>
                <option>absents</option>
                <option>présents</option>
            </select>
        </div>
        <div class="champ"><label>Topographie Q</label>
            <select name="TOPOGRAPHIE_Q">
                <option value="">—</option>
                <?php foreach([
                    'anterieur','anterieur etendu','antero-apical',
                    'antero-lateral','antero-septal','antero-septo-apical',
                    'apical','circonferonciel','inferieur','infero-lateral',
                    'infero-septal','lateral','latero-septal','posterieur',
                    'postero-apical','postero-lateral','postero-septal',
                    'septal','septo-apical','septal profond'
                ] as $v): ?>
                <option value="<?= $v ?>"><?= ucfirst($v) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>


    <!-- ── Cases à cocher ECG ── -->
    <div id="panel_ecg_cases" style="margin-bottom:6px;border:1px solid #b0c8e8;border-radius:5px;padding:6px 8px;background:#f5f9ff;">
        <div style="font-size:11px;font-weight:bold;color:#1a4a7a;margin-bottom:5px;">📈 ECG — cochez pour générer C/C</div>
        <div style="margin-bottom:4px;">
            <label style="font-size:11px;font-weight:bold;"><input type="radio" name="ecg_global" value="normal" onchange="toggleECGAnormal(false)" checked> ECG sinusal normal</label>
            &nbsp;&nbsp;
            <label style="font-size:11px;font-weight:bold;"><input type="radio" name="ecg_global" value="anormal" onchange="toggleECGAnormal(true)"> ECG anormal</label>
        </div>
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
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child excl1" data-group="ecg_condav" onchange="exclusifGroup(this)" value="absence de trouble de conduction AV"> absence de trouble de conduction AV</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child excl1" data-group="ecg_condav" onchange="exclusifGroup(this)" value="BAV I / trouble de conduction"> BAV I / trouble de conduction</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child excl1" data-group="ecg_condav" onchange="exclusifGroup(this)" value="BAV II : Luciani-Wenckebach"> BAV II : Luciani-Wenckebach</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child excl1" data-group="ecg_condav" onchange="exclusifGroup(this)" value="BAV II : Mobitz I"> BAV II : Mobitz I</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child excl1" data-group="ecg_condav" onchange="exclusifGroup(this)" value="BAV II : Mobitz II"> BAV II : Mobitz II</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child excl1" data-group="ecg_condav" onchange="exclusifGroup(this)" value="BAV III"> BAV III</label><br>
            </div>

            <!-- Conduction intra-ventriculaire ≥1 -->
            <div style="margin-top:3px;">
                <label style="font-size:11px;font-weight:bold;cursor:pointer;"><input type="checkbox" class="ecg-parent" data-target="sub_ecg_condiv" onchange="toggleSub(this)"> Trouble de conduction intra-ventriculaire</label>
            </div>
            <div id="sub_ecg_condiv" style="display:none;margin-left:18px;margin-top:1px;">
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child excl1" data-group="ecg_condiv" onchange="exclusifGroup(this)" value="conduction intra-ventriculaire normale"> conduction intra-ventriculaire normale</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child excl1" data-group="ecg_condiv" onchange="exclusifGroup(this)" value="bloc incomplet gauche"> bloc incomplet gauche</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child excl1" data-group="ecg_condiv" onchange="exclusifGroup(this)" value="bloc incomplet droit"> bloc incomplet droit</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child excl1" data-group="ecg_condiv" onchange="exclusifGroup(this)" value="hémibloc antérieur gauche"> hémibloc antérieur gauche</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child excl1" data-group="ecg_condiv" onchange="exclusifGroup(this)" value="hémibloc postérieur"> hémibloc postérieur</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child excl1" data-group="ecg_condiv" onchange="exclusifGroup(this)" value="bloc droit complet"> bloc droit complet</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child excl1" data-group="ecg_condiv" onchange="exclusifGroup(this)" value="bloc incomplet gauche et bloc incomplet droit"> bloc incomplet gauche et bloc incomplet droit</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child excl1" data-group="ecg_condiv" onchange="exclusifGroup(this)" value="hémibloc incomplet gauche"> hémibloc incomplet gauche</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child excl1" data-group="ecg_condiv" onchange="exclusifGroup(this)" value="syndrome de pré-excitation"> syndrome de pré-excitation</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child excl1" data-group="ecg_condiv" onchange="exclusifGroup(this)" value="électro-entraîné, patient porteur d'un pacemaker"> électro-entraîné, patient porteur d'un pacemaker</label><br>
            </div>

            <!-- Repolarisation ≥1 -->
            <div style="margin-top:3px;">
                <label style="font-size:11px;font-weight:bold;cursor:pointer;"><input type="checkbox" class="ecg-parent" data-target="sub_ecg_repol" onchange="toggleSub(this)"> Trouble de repolarisation dans le territoire</label>
            </div>
            <div id="sub_ecg_repol" style="display:none;margin-left:18px;margin-top:1px;">
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child ecg-repol-abs" data-group="ecg_repol_abs" onchange="exclusifGroupRepol(this,'sub_ecg_repol')" value="absence de trouble de repolarisation"> absence de trouble de repolarisation</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child ecg-repol-ter"> antérieur</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child ecg-repol-ter"> antérieur étendu</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child ecg-repol-ter"> antéro-apical</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child ecg-repol-ter"> antéro-latéral</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child ecg-repol-ter"> antéro-septal</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child ecg-repol-ter"> antéro-septo-apical</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child ecg-repol-ter"> apical</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child ecg-repol-ter"> circonférentiel</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child ecg-repol-ter"> inférieur</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child ecg-repol-ter"> inféro-latéral</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child ecg-repol-ter"> latéral</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child ecg-repol-ter"> latéro-septal</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child ecg-repol-ter"> postérieur</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child ecg-repol-ter"> postéro-apical</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child ecg-repol-ter"> postéro-latéral</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child ecg-repol-ter"> postéro-septal</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child ecg-repol-ter"> septal</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child ecg-repol-ter"> septo-apical</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child ecg-repol-ter"> septal profond</label><br>
            
                <button type="button" onclick="appliquerMultiple('sub_ecg_repol')" style="margin-top:3px;background:#1a4a7a;color:white;border:none;border-radius:3px;padding:2px 10px;font-size:10px;cursor:pointer;">✓ OK</button>
            </div>

            <!-- Ischémie ondes Q ≥1 -->
            <div style="margin-top:3px;">
                <label style="font-size:11px;font-weight:bold;cursor:pointer;"><input type="checkbox" class="ecg-parent" data-target="sub_ecg_q" onchange="toggleSub(this)"> Signes d'ischémie (ondes Q) dans le territoire</label>
            </div>
            <div id="sub_ecg_q" style="display:none;margin-left:18px;margin-top:1px;">
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child ecg-q-abs" onchange="exclusifGroupRepol(this,'sub_ecg_q')" value="absence d'onde Q"> absence d'onde Q</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child ecg-q-ter"> antérieur</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child ecg-q-ter"> antérieur étendu</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child ecg-q-ter"> antéro-apical</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child ecg-q-ter"> antéro-latéral</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child ecg-q-ter"> antéro-septal</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child ecg-q-ter"> antéro-septo-apical</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child ecg-q-ter"> apical</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child ecg-q-ter"> circonférentiel</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child ecg-q-ter"> inférieur</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child ecg-q-ter"> inféro-latéral</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child ecg-q-ter"> latéral</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child ecg-q-ter"> latéro-septal</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child ecg-q-ter"> postérieur</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child ecg-q-ter"> postéro-apical</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child ecg-q-ter"> postéro-latéral</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child ecg-q-ter"> postéro-septal</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child ecg-q-ter"> septal</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child ecg-q-ter"> septo-apical</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child ecg-q-ter"> septal profond</label><br>
            
                <button type="button" onclick="appliquerMultiple('sub_ecg_q')" style="margin-top:3px;background:#1a4a7a;color:white;border:none;border-radius:3px;padding:2px 10px;font-size:10px;cursor:pointer;">✓ OK</button>
            </div>

            <!-- HVG =1 -->
            <div style="margin-top:3px;">
                <label style="font-size:11px;font-weight:bold;cursor:pointer;"><input type="checkbox" class="ecg-parent" data-target="sub_ecg_hvg" onchange="toggleSub(this)"> Signes d'HVG</label>
            </div>
            <div id="sub_ecg_hvg" style="display:none;margin-left:18px;margin-top:1px;">
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child excl1" data-group="ecg_hvg" onchange="exclusifGroup(this)" value="absence d'hypertrophie ventriculaire"> absence d'hypertrophie ventriculaire</label><br>
                <label style="font-size:11px;font-weight:bold;cursor:pointer;"><input type="checkbox" class="ecg-child excl1" data-group="ecg_hvg" data-target="sub_ecg_hvg_detail" onchange="exclusifGroup(this);toggleSub(this)" value="présents"> présents</label>
                <div id="sub_ecg_hvg_detail" style="display:none;margin-left:14px;">
                    <label style="font-size:11px;"><input type="checkbox" class="ecg-child excl1" data-group="ecg_hvg_type" onchange="exclusifGroup(this)" value="hypertrophie concentrique ventriculaire gauche"> hypertrophie concentrique ventriculaire gauche</label><br>
                    <label style="font-size:11px;"><input type="checkbox" class="ecg-child excl1" data-group="ecg_hvg_type" onchange="exclusifGroup(this)" value="hypertrophie septale"> hypertrophie septale</label><br>
                </div>
            </div>

            <!-- Bas voltage =1 -->
            <div style="margin-top:3px;">
                <label style="font-size:11px;font-weight:bold;cursor:pointer;"><input type="checkbox" class="ecg-parent" data-target="sub_ecg_voltage" onchange="toggleSub(this)"> Bas voltage</label>
            </div>
            <div id="sub_ecg_voltage" style="display:none;margin-left:18px;margin-top:1px;">
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child excl1" data-group="ecg_voltage" onchange="exclusifGroup(this)" value="absent"> absent</label><br>
                <label style="font-size:11px;"><input type="checkbox" class="ecg-child excl1" data-group="ecg_voltage" onchange="exclusifGroup(this)" value="présent"> présent</label><br>
            </div>

        </div>
        <button type="button" onclick="genererCC(); enregistrerAjax('ecg'); document.getElementById('panel_ecg_cases').style.display='none'; document.getElementById('lien_modifier_ecg').style.display='inline';" style="margin-top:6px;background:#1a4a7a;color:white;border:none;border-radius:3px;padding:3px 12px;font-size:11px;cursor:pointer;">▶ Générer & 💾</button>
    </div>
    <span id="lien_modifier_ecg" style="display:none;font-size:10px;">
        <a href="#" onclick="document.getElementById('panel_ecg_cases').style.display=''; document.getElementById('lien_modifier_ecg').style.display='none'; return false;" style="color:#2e6da4;">↺ Modifier les cases</a>
    </span>
    <!-- 13. Autres signes ECG (NOUVEAU) -->
    <div class="champ"><label>Autres signes ECG</label>
        <input type="text" name="AUTRES_SIGNES">
    </div>

    <!-- 12. C/C -->
    <div class="champ" id="wrap_CC"><div class="label-excl"><label>C/C</label><button type="button" class="btn-excl" onclick="toggleExcl('CC')" title="Exclure du rapport">−</button></div>
        <textarea name="CC" oninput="majApercuECG()" placeholder="ex: ECG normal" style="min-height:48px;resize:vertical;background:#fff8f0;border:1px solid #e67e22;"></textarea>
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
            style="min-height:45px;background:#f0f7ff;border:1px solid #2e6da4;font-size:11px;color:#1a4a7a;resize:vertical;width:100%;padding:4px 6px;border-radius:3px;font-family:Arial,sans-serif;"></textarea>
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

    <!-- Champs numériques avec bouton ➕/➖ -->
    <div class="grid2">
        <div class="champ" id="wrap_echo_FEVG">
            <div class="label-excl"><label>FEVG %</label><button type="button" class="btn-excl" onclick="toggleExclEcho('FEVG')" title="Exclure">−</button></div>
            <input type="text" name="FEVG" id="echo_FEVG" oninput="majConcatEcho()">
        </div>
        <div class="champ" id="wrap_echo_DTD_VG">
            <div class="label-excl"><label>DTD-VG mm</label><button type="button" class="btn-excl" onclick="toggleExclEcho('DTD_VG')" title="Exclure">−</button></div>
            <input type="text" name="DTD_VG" oninput="majConcatEcho()">
        </div>
        <div class="champ" id="wrap_echo_DTS_VG">
            <div class="label-excl"><label>DTS-VG mm</label><button type="button" class="btn-excl" onclick="toggleExclEcho('DTS_VG')" title="Exclure">−</button></div>
            <input type="text" name="DTS_VG" oninput="majConcatEcho()">
        </div>
        <div class="champ" id="wrap_echo_SIV">
            <div class="label-excl"><label>SIV mm</label><button type="button" class="btn-excl" onclick="toggleExclEcho('SIV')" title="Exclure">−</button></div>
            <input type="text" name="SIV" oninput="majConcatEcho()">
        </div>
        <div class="champ" id="wrap_echo_PP">
            <div class="label-excl"><label>PP mm</label><button type="button" class="btn-excl" onclick="toggleExclEcho('PP')" title="Exclure">−</button></div>
            <input type="text" name="PP" oninput="majConcatEcho()">
        </div>
        <div class="champ" id="wrap_echo_RACINE_AO">
            <div class="label-excl"><label>Racine Ao mm</label><button type="button" class="btn-excl" onclick="toggleExclEcho('RACINE_AO')" title="Exclure">−</button></div>
            <input type="text" name="RACINE_AO" oninput="majConcatEcho()">
        </div>
        <div class="champ" id="wrap_echo_HTAP">
            <div class="label-excl"><label>HTAP</label><button type="button" class="btn-excl" onclick="toggleExclEcho('HTAP')" title="Exclure">−</button></div>
            <input type="text" name="HTAP" oninput="majConcatEcho()">
        </div>
        <div class="champ" id="wrap_echo_CINETIQUE">
            <div class="label-excl"><label>Cinétique</label><button type="button" class="btn-excl" onclick="toggleExclEcho('CINETIQUE')" title="Exclure">−</button></div>
            <input type="text" name="CINETIQUE" oninput="majConcatEcho()">
        </div>
        <div class="champ"><label>Échogénicité</label><input type="text" name="ECHOGENICITE"></div>
    </div>


    <!-- ── Cases à cocher CMLM Echo ── -->
    <div id="panel_echo_cases" style="margin-bottom:6px;border:1px solid #b0c8e8;border-radius:5px;padding:6px 8px;background:#f5f9ff;">
        <div style="font-size:11px;font-weight:bold;color:#1a4a7a;margin-bottom:4px;">📋 CMLM — diagnostic échographique</div>

        <!-- Échodoppler normale / anormale =1 -->
        <div style="margin-bottom:4px;">
            <label style="font-size:11px;font-weight:bold;"><input type="radio" name="cmlm_echo_global" value="normale" onchange="toggleCmlmEcho(false)" checked> Échodoppler normale</label>
            &nbsp;&nbsp;
            <label style="font-size:11px;font-weight:bold;"><input type="radio" name="cmlm_echo_global" value="anormale" onchange="toggleCmlmEcho(true)"> Anormale</label>
        </div>

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
    <input type="hidden" name="CMLM_ECHO" id="cmlm_echo_val">
    <div class="champ" id="wrap_DOPPLER">
        <div class="label-excl"><label>Doppler</label><button type="button" class="btn-excl" onclick="toggleExcl('DOPPLER')" title="Exclure du rapport">−</button></div>
        <textarea name="DOPPLER" class="court" oninput="majConcatEcho()"></textarea>
    </div>
    <div class="champ" id="wrap_DTSA">
        <div class="label-excl"><label>DTSA</label><button type="button" class="btn-excl" onclick="toggleExcl('DTSA')" title="Exclure du rapport">−</button></div>
        <textarea name="DTSA" class="court" oninput="majConcatEcho()"></textarea>
    </div>

    <button type="button" id="btn_generer_echo" onclick="genererCmlmEcho(); enregistrerAjax('echo'); document.getElementById('panel_echo_cases').style.display='none'; document.getElementById('btn_generer_echo').style.display='none'; document.getElementById('lien_modifier_echo').style.display='inline';"
        style="margin-top:6px;background:#1a4a7a;color:white;border:none;border-radius:3px;padding:3px 12px;font-size:11px;cursor:pointer;">▶ Générer & 💾</button>
    <span id="lien_modifier_echo" style="display:none;font-size:10px;margin-left:6px;">
        <a href="#" onclick="document.getElementById('panel_echo_cases').style.display=''; document.getElementById('btn_generer_echo').style.display='inline-block'; document.getElementById('lien_modifier_echo').style.display='none'; return false;" style="color:#2e6da4;">↺ Modifier les cases</a>
    </span>
    <textarea id="cmlm_echo_apercu" readonly
        style="display:none;margin-top:4px;width:100%;min-height:40px;font-size:11px;color:#1a4a7a;background:#fff8f0;border:1px solid #e67e22;border-radius:3px;padding:4px 6px;font-family:Arial,sans-serif;resize:vertical;"></textarea>

    <!-- Champs cachés exclusion Echo -->
    <input type="hidden" id="excl_DOPPLER"     name="excl_DOPPLER">
    <input type="hidden" id="excl_DTSA"        name="excl_DTSA">
    <input type="hidden" id="excl_CONCLUSION1" name="excl_CONCLUSION1">

    <!-- Aperçu + Conclusion fusionnés : une seule zone bleue éditable -->
    <div class="champ" id="wrap_CONCLUSION1" style="margin-top:6px;">
        <div class="label-excl">
            <label style="font-size:10px;color:#2e6da4;font-weight:bold;">👁 Aperçu rapport Echo <small style="color:#888;font-weight:normal;">(modifiable)</small></label>
            <button type="button" class="btn-excl" onclick="toggleExcl('CONCLUSION1')" title="Exclure du rapport">−</button>
        </div>
        <textarea name="CONCLUSION1" id="conclusion1_echo"
            style="min-height:70px;background:#f0f7ff;border:1px solid #2e6da4;font-size:11px;color:#1a4a7a;resize:vertical;width:100%;padding:4px 6px;border-radius:3px;font-family:Arial,sans-serif;"
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
</div><!-- FIN card biologie -->

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
    var ap = document.getElementById('apercu_examen');
    if (!ap) return;
    var noms = ['S_Fonctionnels','Auscult_Cardiaque','Auscult_Pulmonaire',
                'Examen_Vasculaire','Signes_IVG','Signes_IVD','Autres_Symptomes'];
    var parties = [];
    noms.forEach(function(n) {
        if (exclusions[n]) return;
        var el = document.querySelector('textarea[name='+n+'], input[name='+n+'], select[name='+n+']');
        if (!el || el.tagName === 'INPUT' && el.type === 'checkbox') return;
        var v  = el ? el.value.trim() : '';
        if (!v || v === 'Absents') return;
        parties.push(v);
    });
    ap.value = parties.join(' ; ') || '—';
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
function remplirExamenNormal() {
    exclusions = {};
    document.querySelectorAll('.exclu-champ').forEach(function(el){ el.classList.remove('exclu-champ'); });
    document.querySelectorAll('.btn-excl').forEach(function(b){ b.classList.remove('exclu'); b.textContent='−'; b.title='Exclure du rapport'; });
    document.querySelectorAll('[id^=excl_]').forEach(function(h){ h.value=''; });
    var s = function(n,v){ var e=document.querySelector('[name='+n+']'); if(e) e.value=v; };
    s('S_Fonctionnels','Absence de symptomatologie orientant sur la sphère cardio-vasculaire');
    s('Auscult_Cardiaque','Auscultation Cardiaque Normale');
    s('Auscult_Pulmonaire','Auscultation Pulmonaire Normale');
    s('Examen_Vasculaire','Examen Vasculaire Normal');
    s('Signes_IVG','Absents'); s('Signes_IVD','Absents');
    s('Conduite_ATenir','Examen cardio-vasculaire normal');
    majApercuExamen();
}
function viderExamen() {
    exclusions = {};
    document.querySelectorAll('.exclu-champ').forEach(function(el){ el.classList.remove('exclu-champ'); });
    document.querySelectorAll('.btn-excl').forEach(function(b){ b.classList.remove('exclu'); b.textContent='−'; });
    document.querySelectorAll('[id^=excl_]').forEach(function(h){ h.value=''; });
    ['S_Fonctionnels','Auscult_Cardiaque','Auscult_Pulmonaire','Examen_Vasculaire',
     'Signes_IVG','Signes_IVD','Autres_Symptomes','Conduite_ATenir']
    .forEach(function(n){ var e=document.querySelector('[name='+n+']'); if(e) e.value=''; });
    majApercuExamen();
}

/* ── Conclusion : ECVN / ECVAN ── */
function setConclusionECVN() {
    var c = document.querySelector('[name=Conclusion]');
    if (c) { c.value = 'EXAMEN CLINIQUE NORMAL'; majApercuExamen(); }
}
function viderConclusionRemarque() {
    var c = document.querySelector('[name=Conclusion]');
    var r = document.querySelector('[name=REMARQUE]');
    if (c) c.value = '';
    if (r) r.value = '';
    majApercuExamen();
}

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
    if(type==='examen') viderExamen();
    if(type==='ecg')    viderECG();
    if(type==='echo')   viderEcho();
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
    const global = document.querySelector('input[name="cmlm_echo_global"]:checked');
    let parties = [];
    if (global && global.value === 'normale') {
        parties.push('échodoppler cardiaque normale');
    } else {
        document.querySelectorAll('.cmlm-ec:checked').forEach(function(cb) {
            var txt = (cb.value && cb.value !== 'on') ? cb.value : '';
            // Ajout valeurs FEVG
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
            // Ajout SIV pour hypertensive
            if (txt.indexOf('hypertensive') !== -1) {
                var siv = document.getElementById('ce_siv');
                if (siv && siv.value) txt += ' (SIV=' + siv.value + ')';
            }
            if (txt) parties.push(txt);
        });
        if (parties.length === 0) parties.push('échodoppler cardiaque anormale');
    }
    var result = parties.join(', ');
    document.getElementById('cmlm_echo_val').value = result;
    var ap = document.getElementById('cmlm_echo_apercu');
    if (ap) { ap.value = result; ap.style.display = 'block'; }
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

/* ── Cases à cocher Clinique + ECG ── */
function toggleSub(cb) {
    const target = document.getElementById(cb.dataset.target);
    if (target) target.style.display = cb.checked ? 'block' : 'none';
    if (!cb.checked && target) target.querySelectorAll('input').forEach(i => {
        i.checked = false;
        if (i.dataset && i.dataset.target) { const s = document.getElementById(i.dataset.target); if(s) s.style.display='none'; }
    });
}
function toggleECGAnormal(anormal) {
    document.getElementById('ecg_detail').style.display = anormal ? 'block' : 'none';
}
function genererConclusion() {
    var parties = [];
    document.querySelectorAll('#panel_sympto input[type="checkbox"].sympto-child:checked').forEach(function(cb) {
        if (cb.value && cb.value !== 'on') parties.push(cb.value);
    });
    var ta = document.querySelector('textarea[name="Conclusion"]');
    if (ta) { ta.value = parties.join(', '); ta.dispatchEvent(new Event('input')); }
}
function genererCC() {
    const global = document.querySelector('input[name="ecg_global"]:checked');
    let txt = '';
    if (global && global.value === 'normal') {
        txt = 'ECG sinusal normal';
    } else {
        var parties = [];
        document.querySelectorAll('#panel_ecg_cases input[type="checkbox"]:checked').forEach(function(cb) {
            // Ignorer les cases parent (rubrique) — elles n'ont pas de value utile
            if (cb.classList.contains('ecg-parent')) return;
            // Cases avec value explicite
            if (cb.value && cb.value !== 'on') {
                parties.push(cb.value);
                return;
            }
            // Cases territoire (repol/ondes Q) : lire le texte du label
            var lbl = cb.parentElement;
            if (lbl) {
                var t = lbl.textContent.trim();
                if (t) parties.push(t);
            }
        });
        txt = parties.join(' ; ');
    }
    const ta = document.querySelector('[name="CC"]');
    if (ta) { ta.value = txt; ta.dispatchEvent(new Event('input')); }
}

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
    var lme = document.getElementById('lien_modifier_ecg');
    if (lme) lme.style.display = 'none';
    var lmec = document.getElementById('lien_modifier_echo');
    if (lmec) lmec.style.display = 'none';
    var bge = document.getElementById('btn_generer_echo');
    if (bge) bge.style.display = '';
    // Réinitialiser les variables internes exclusions
    if (typeof exclusions !== 'undefined') exclusions = {};
    if (typeof exclusionsEcho !== 'undefined') exclusionsEcho = {};
}

</script>

</body>
</html>
