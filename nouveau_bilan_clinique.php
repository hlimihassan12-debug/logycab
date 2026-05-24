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
                 [DOPPLER DES TRONCS SUPRA AORTIQUES],TYPE_ECHO)
                VALUES (?,CONVERT(datetime,?,120),?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$id,
                $dEcho.' 00:00:00',$_POST['ECHOGENICITE']?:null,
                $_POST['RACINE_AO']?:null,$_POST['DTD_VG']?:null,
                $_POST['DTS_VG']?:null,$_POST['SIV']?:null,
                $_POST['PP']?:null,$_POST['FEVG']?:null,
                $_POST['CINETIQUE']?:null,$_POST['HTAP']?:null,
                $_POST['DOPPLER']?:null,$_POST['CONCLUSION1']?:null,
                $_POST['DTSA']?:null,
                $_POST['TYPE_ECHO']?:null]);
            if ($isAjax) { ob_clean(); header('Content-Type: application/json'); echo json_encode(['ok'=>true,'msg'=>'✅ Echo enregistré']); exit; }
        } catch (Exception $e) {
            if ($isAjax) { ob_clean(); header('Content-Type: application/json'); echo json_encode(['ok'=>false,'msg'=>'❌ Echo : '.$e->getMessage()]); exit; }
        }
        header("Location: ?id=$id&msg=echo_ok"); exit;
    }
}

$today = date('Y-m-d');
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

.btn-save {
    background: #27ae60; color: white;
    border: none; border-radius: 4px; padding: 3px 10px;
    font-size: 11px; font-weight: bold; cursor: pointer;
    white-space: nowrap;
}
.btn-save:hover { background: #1e8449; }

.date-enreg {
    display: flex; align-items: center; gap: 6px;
}
.date-enreg input[type=date] {
    border: 1px solid #ddd; border-radius: 3px;
    padding: 2px 5px; font-size: 11px; color: #1a4a7a;
}
/* Boutons Normal / Anormal */
.btn-preset {
    padding: 2px 8px; border-radius: 3px; border: none;
    font-size: 11px; font-weight: bold; cursor: pointer; white-space: nowrap;
}
.btn-normal  { background: #27ae60; color: white; }
.btn-normal:hover  { background: #1e8449; }
.btn-anormal { background: #e67e22; color: white; }
.btn-anormal:hover { background: #d35400; }

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
        🩺 Examen clinique
        <div style="display:flex;align-items:center;gap:4px;flex-wrap:wrap;">
            <button type="button" class="btn-preset btn-normal" onclick="remplirExamenNormal()">✅ Normal</button>
            <button type="button" class="btn-preset btn-anormal" onclick="viderExamen()">⚠️ Anormal</button>
            <input type="date" name="DateExam" value="<?= $today ?>" style="border:1px solid #ddd;border-radius:3px;padding:2px 5px;font-size:11px;color:#1a4a7a;">
            <button type="button" class="btn-save" onclick="enregistrerAjax('examen')">💾 Enregistrer</button>
        </div>
    </div>
    <div style="min-height:16px;"><span id="msg_examen" style="font-size:11px;color:#27ae60;font-weight:bold;display:none;"></span></div>

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

    <div class="sec">Clinique &nbsp;<small id="lbl_exclu_examen" style="color:#e74c3c;font-weight:bold;font-size:9px;display:none;"></small></div>
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

    <div class="sec">Conclusion</div>
    <div class="champ"><label>Conclusion</label><textarea name="Conclusion" class="court"></textarea></div>
    <div class="champ"><label>Remarque</label><textarea name="REMARQUE" class="court"></textarea></div>

    <!-- Champs cachés pour l'exclusion de concaténation -->
    <input type="hidden" id="excl_S_Fonctionnels"     name="excl_S_Fonctionnels">
    <input type="hidden" id="excl_Auscult_Cardiaque"  name="excl_Auscult_Cardiaque">
    <input type="hidden" id="excl_Auscult_Pulmonaire" name="excl_Auscult_Pulmonaire">
    <input type="hidden" id="excl_Examen_Vasculaire"  name="excl_Examen_Vasculaire">
    <input type="hidden" id="excl_Signes_IVG"         name="excl_Signes_IVG">
    <input type="hidden" id="excl_Signes_IVD"         name="excl_Signes_IVD">
    <input type="hidden" id="excl_Autres_Symptomes"   name="excl_Autres_Symptomes">

    <!-- Zone prévisualisation concaténation Examen -->
    <div class="champ" style="margin-top:6px;">
        <label style="font-size:10px;color:#2e6da4;font-weight:bold;">👁 Aperçu rapport Examen</label>
        <textarea id="apercu_examen" readonly
            style="min-height:45px;background:#f0f7ff;border:1px solid #2e6da4;font-size:10px;color:#1a4a7a;resize:vertical;width:100%;padding:4px 6px;border-radius:3px;font-family:Arial,sans-serif;"></textarea>
    </div>

    <div class="sec">Au total — Conduite à tenir</div>
    <div class="champ">
        <label>Conduite à tenir</label>
        <textarea name="Conduite_ATenir" style="min-height:70px;" placeholder="Conclusion générale et plan de prise en charge..."></textarea>
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
        ⚡ ECG
        <div style="display:flex;align-items:center;gap:4px;flex-wrap:wrap;">
            <button type="button" class="btn-preset btn-normal" onclick="remplirECGNormal()">✅ Normal</button>
            <button type="button" class="btn-preset btn-anormal" onclick="viderECG()">⚠️ Anormal</button>
            <input type="date" name="Date_ECG" value="<?= $today ?>" style="border:1px solid #ddd;border-radius:3px;padding:2px 5px;font-size:11px;color:#1a4a7a;">
            <button type="button" class="btn-save" onclick="enregistrerAjax('ecg')">💾 Enregistrer</button>
        </div>
    </div>
    <div style="min-height:16px;"><span id="msg_ecg" style="font-size:11px;color:#27ae60;font-weight:bold;display:none;"></span></div>
    <div style="min-height:14px;margin-bottom:4px;">
        <small id="lbl_exclu_ecg" style="color:#e74c3c;font-weight:bold;font-size:9px;display:none;"></small>
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

    <!-- 12. C/C -->
    <div class="champ" id="wrap_CC"><div class="label-excl"><label>C/C</label><button type="button" class="btn-excl" onclick="toggleExcl('CC')" title="Exclure du rapport">−</button></div>
        <input type="text" name="CC" oninput="majApercuECG()" placeholder="ex: ECG normal">
    </div>

    <!-- 13. Autres signes ECG (NOUVEAU) -->
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
            style="min-height:45px;background:#f0f7ff;border:1px solid #2e6da4;font-size:10px;color:#1a4a7a;resize:vertical;width:100%;padding:4px 6px;border-radius:3px;font-family:Arial,sans-serif;"></textarea>
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
        🫀 Echo-Doppler
        <div style="display:flex;align-items:center;gap:4px;flex-wrap:wrap;">
            <button type="button" class="btn-preset btn-normal" onclick="remplirEchoNormal()">✅ Normal</button>
            <button type="button" class="btn-preset btn-anormal" onclick="viderEcho()">⚠️ Anormal</button>
            <input type="date" name="DATEchog" value="<?= $today ?>" style="border:1px solid #ddd;border-radius:3px;padding:2px 5px;font-size:11px;color:#1a4a7a;">
            <button type="button" class="btn-save" onclick="enregistrerAjax('echo')">💾 Enregistrer</button>
        </div>
    </div>
    <div style="min-height:16px;"><span id="msg_echo" style="font-size:11px;color:#27ae60;font-weight:bold;display:none;"></span></div>
    <div style="min-height:14px;margin-bottom:4px;">
        <small id="lbl_exclu_echo" style="color:#e74c3c;font-weight:bold;font-size:9px;display:none;"></small>
    </div>

    <!-- TYPE_ECHO caché : mis à jour par Normal/Anormal -->
    <input type="hidden" name="TYPE_ECHO" id="type_echo_val" value="Echoscopie cardiaque">

    <!-- Champs numériques avec bouton ➕/➖ -->
    <div class="grid2">
        <div class="champ" id="wrap_echo_FEVG">
            <div class="label-excl"><label>FEVG %</label><button type="button" class="btn-excl" onclick="toggleExclEcho('FEVG')" title="Exclure">−</button></div>
            <input type="text" name="FEVG" oninput="majConcatEcho()">
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

    <div class="champ" id="wrap_DOPPLER">
        <div class="label-excl"><label>Doppler</label><button type="button" class="btn-excl" onclick="toggleExcl('DOPPLER')" title="Exclure du rapport">−</button></div>
        <textarea name="DOPPLER" class="court" oninput="majConcatEcho()"></textarea>
    </div>
    <div class="champ" id="wrap_DTSA">
        <div class="label-excl"><label>DTSA</label><button type="button" class="btn-excl" onclick="toggleExcl('DTSA')" title="Exclure du rapport">−</button></div>
        <textarea name="DTSA" class="court" oninput="majConcatEcho()"></textarea>
    </div>

    <!-- Aperçu + Conclusion fusionnés : une seule zone bleue éditable -->
    <div class="champ" id="wrap_CONCLUSION1" style="margin-top:6px;">
        <div class="label-excl">
            <label style="font-size:10px;color:#2e6da4;font-weight:bold;">👁 Aperçu rapport Echo <small style="color:#888;font-weight:normal;">(modifiable)</small></label>
            <button type="button" class="btn-excl" onclick="toggleExcl('CONCLUSION1')" title="Exclure du rapport">−</button>
        </div>
        <textarea name="CONCLUSION1" id="conclusion1_echo"
            style="min-height:70px;background:#f0f7ff;border:1px solid #2e6da4;font-size:10px;color:#1a4a7a;resize:vertical;width:100%;padding:4px 6px;border-radius:3px;font-family:Arial,sans-serif;"
            oninput="majApercuEcho()"></textarea>
    </div>

    <!-- Champs cachés exclusion Echo -->
    <input type="hidden" id="excl_DOPPLER"     name="excl_DOPPLER">
    <input type="hidden" id="excl_DTSA"        name="excl_DTSA">
    <input type="hidden" id="excl_CONCLUSION1" name="excl_CONCLUSION1">
    </form>
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
                    'Examen_Vasculaire','Signes_IVG','Signes_IVD','Autres_Symptomes'];
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
        var el = document.querySelector('[name='+n+']');
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
    var g = function(n){ var e=document.querySelector('[name='+n+']'); return e ? e.value.trim() : ''; };
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
    var cc = g('CC');
    if (!exclusions['CC'] && cc) p.push(cc);
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
</script>

</body>
</html>
