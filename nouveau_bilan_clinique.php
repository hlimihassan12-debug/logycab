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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $onglet = $_POST['onglet'] ?? '';

    if ($onglet === 'examen') {
        $dEx = $_POST['DateExam'] ?? date('Y-m-d');
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
        header("Location: ?id=$id&msg=examen_ok"); exit;
    }

    if ($onglet === 'ecg') {
        $dEcg = $_POST['Date_ECG'] ?? date('Y-m-d');
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
        header("Location: ?id=$id&msg=ecg_ok"); exit;
    }

    if ($onglet === 'echo') {
        $dEcho = $_POST['DATEchog'] ?? date('Y-m-d');
        $db->prepare("INSERT INTO echo
            ([N-PAT],DATEchog,ECHOGENICITE,[RACINE-AO],
             [DTD-VG],[DTS-VG],SIV,PP,FEVG,
             CINETIQUE,HTAP,DOPPLER,CONCLUSION1,
             [DOPPLER DES TRONCS SUPRA AORTIQUES])
            VALUES (?,CONVERT(datetime,?,120),?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([$id,
            $dEcho.' 00:00:00',$_POST['ECHOGENICITE']?:null,
            $_POST['RACINE_AO']?:null,$_POST['DTD_VG']?:null,
            $_POST['DTS_VG']?:null,$_POST['SIV']?:null,
            $_POST['PP']?:null,$_POST['FEVG']?:null,
            $_POST['CINETIQUE']?:null,$_POST['HTAP']?:null,
            $_POST['DOPPLER']?:null,$_POST['CONCLUSION1']?:null,
            $_POST['DTSA']?:null]);
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
    <div style="display:flex;gap:6px;margin-left:auto;">
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
    <form method="POST" id="form-examen" onsubmit="return appliquerExclusions()">
    <input type="hidden" name="onglet" value="examen">
    <div class="col-title">
        🩺 Examen clinique
        <div style="display:flex;align-items:center;gap:4px;flex-wrap:wrap;">
            <button type="button" class="btn-preset btn-normal" onclick="remplirExamenNormal()">✅ Normal</button>
            <button type="button" class="btn-preset btn-anormal" onclick="viderExamen()">⚠️ Anormal</button>
            <input type="date" name="DateExam" value="<?= $today ?>" style="border:1px solid #ddd;border-radius:3px;padding:2px 5px;font-size:11px;color:#1a4a7a;">
            <button type="submit" class="btn-save">💾 Enregistrer</button>
        </div>
    </div>
    <?php if (!empty($msgs['examen'])): ?><div class="msg"><?= $msgs['examen'] ?></div><?php endif; ?>

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

    <div class="sec">Clinique &nbsp;<small style="color:#aaa;font-weight:normal;font-size:9px;">— ➖ exclut du rapport</small></div>
    <div class="champ" id="wrap_S_Fonctionnels">
        <label>Signes fonctionnels</label>
        <div class="excl-wrap">
            <textarea name="S_Fonctionnels" class="court"></textarea>
            <button type="button" class="btn-excl" onclick="toggleExcl('S_Fonctionnels')" title="Exclure du rapport">−</button>
        </div>
    </div>
    <div class="champ" id="wrap_Auscult_Cardiaque">
        <label>Auscultation cardiaque</label>
        <div class="excl-wrap">
            <textarea name="Auscult_Cardiaque" class="court"></textarea>
            <button type="button" class="btn-excl" onclick="toggleExcl('Auscult_Cardiaque')" title="Exclure du rapport">−</button>
        </div>
    </div>
    <div class="champ" id="wrap_Auscult_Pulmonaire">
        <label>Auscultation pulmonaire</label>
        <div class="excl-wrap">
            <textarea name="Auscult_Pulmonaire" class="court"></textarea>
            <button type="button" class="btn-excl" onclick="toggleExcl('Auscult_Pulmonaire')" title="Exclure du rapport">−</button>
        </div>
    </div>
    <div class="champ" id="wrap_Examen_Vasculaire">
        <label>Examen vasculaire</label>
        <div class="excl-wrap">
            <textarea name="Examen_Vasculaire" class="court"></textarea>
            <button type="button" class="btn-excl" onclick="toggleExcl('Examen_Vasculaire')" title="Exclure du rapport">−</button>
        </div>
    </div>
    <div class="grid2">
        <div class="champ" id="wrap_Signes_IVG">
            <label>Signes IVG</label>
            <div class="excl-wrap">
                <textarea name="Signes_IVG" class="court"></textarea>
                <button type="button" class="btn-excl" onclick="toggleExcl('Signes_IVG')" title="Exclure du rapport">−</button>
            </div>
        </div>
        <div class="champ" id="wrap_Signes_IVD">
            <label>Signes IVD</label>
            <div class="excl-wrap">
                <textarea name="Signes_IVD" class="court"></textarea>
                <button type="button" class="btn-excl" onclick="toggleExcl('Signes_IVD')" title="Exclure du rapport">−</button>
            </div>
        </div>
    </div>
    <div class="champ" id="wrap_Autres_Symptomes">
        <label>Autres symptômes</label>
        <div class="excl-wrap">
            <textarea name="Autres_Symptomes" class="court"></textarea>
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
    <form method="POST">
    <input type="hidden" name="onglet" value="ecg">
    <div class="col-title">
        ⚡ ECG
        <div style="display:flex;align-items:center;gap:4px;flex-wrap:wrap;">
            <button type="button" class="btn-preset btn-normal" onclick="remplirECGNormal()">✅ Normal</button>
            <button type="button" class="btn-preset btn-anormal" onclick="viderECG()">⚠️ Anormal</button>
            <input type="date" name="Date_ECG" value="<?= $today ?>" style="border:1px solid #ddd;border-radius:3px;padding:2px 5px;font-size:11px;color:#1a4a7a;">
            <button type="submit" class="btn-save">💾 Enregistrer</button>
        </div>
    </div>
    <?php if (!empty($msgs['ecg'])): ?><div class="msg"><?= $msgs['ecg'] ?></div><?php endif; ?>

    <!-- 1. Fréquence -->
    <div class="champ"><label>Fréquence (bpm)</label>
        <input type="number" name="FREQUENCE" placeholder="75" min="20" max="300">
    </div>

    <!-- 2. Rythme supra-ventriculaire -->
    <div class="champ" id="wrap_rythme_sv"><div class="label-excl"><label>Rythme supra-ventriculaire</label><button type="button" class="btn-excl" onclick="toggleExcl('rythme_sv')" title="Exclure du rapport">−</button></div>
        <select name="rythme_sv">
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
        <select name="trouble_rv">
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
        <select name="conduction_nodale">
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
        <select name="QRS">
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
        <select name="REPOLARISATION">
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
        <input type="text" name="CC" placeholder="ex: ECG normal">
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
    </form>
</div>

<!-- ══════════════════════════════════════════════
     COLONNE 3 : ECHO-DOPPLER
══════════════════════════════════════════════ -->
<div class="col-card">
    <form method="POST">
    <input type="hidden" name="onglet" value="echo">
    <div class="col-title">
        🫀 Echo-Doppler
        <div style="display:flex;align-items:center;gap:4px;flex-wrap:wrap;">
            <button type="button" class="btn-preset btn-normal" onclick="remplirEchoNormal()">✅ Normal</button>
            <button type="button" class="btn-preset btn-anormal" onclick="viderEcho()">⚠️ Anormal</button>
            <input type="date" name="DATEchog" value="<?= $today ?>" style="border:1px solid #ddd;border-radius:3px;padding:2px 5px;font-size:11px;color:#1a4a7a;">
            <button type="submit" class="btn-save">💾 Enregistrer</button>
        </div>
    </div>
    <?php if (!empty($msgs['echo'])): ?><div class="msg"><?= $msgs['echo'] ?></div><?php endif; ?>

    <div class="grid2">
        <div class="champ"><label>FEVG %</label><input type="text" name="FEVG"></div>
        <div class="champ"><label>DTD-VG mm</label><input type="text" name="DTD_VG"></div>
        <div class="champ"><label>DTS-VG mm</label><input type="text" name="DTS_VG"></div>
        <div class="champ"><label>SIV mm</label><input type="text" name="SIV"></div>
        <div class="champ"><label>PP mm</label><input type="text" name="PP"></div>
        <div class="champ"><label>Racine Ao mm</label><input type="text" name="RACINE_AO"></div>
        <div class="champ"><label>HTAP</label><input type="text" name="HTAP"></div>
        <div class="champ"><label>Cinétique</label><input type="text" name="CINETIQUE"></div>
        <div class="champ"><label>Échogénicité</label><input type="text" name="ECHOGENICITE"></div>
    </div>
    <div class="champ" id="wrap_DOPPLER">
        <div class="label-excl"><label>Doppler</label><button type="button" class="btn-excl" onclick="toggleExcl('DOPPLER')" title="Exclure du rapport">−</button></div>
        <textarea name="DOPPLER" class="court"></textarea>
    </div>
    <div class="champ" id="wrap_DTSA">
        <div class="label-excl"><label>DTSA</label><button type="button" class="btn-excl" onclick="toggleExcl('DTSA')" title="Exclure du rapport">−</button></div>
        <textarea name="DTSA" class="court"></textarea>
    </div>
    <div class="champ" id="wrap_CONCLUSION1">
        <div class="label-excl"><label>Conclusion</label><button type="button" class="btn-excl" onclick="toggleExcl('CONCLUSION1')" title="Exclure du rapport">−</button></div>
        <textarea name="CONCLUSION1" style="min-height:60px;"></textarea>
    </div>
    <!-- Champs cachés exclusion Echo -->
    <input type="hidden" id="excl_DOPPLER"     name="excl_DOPPLER">
    <input type="hidden" id="excl_DTSA"        name="excl_DTSA">
    <input type="hidden" id="excl_CONCLUSION1" name="excl_CONCLUSION1">
    </form>
</div>

</div><!-- FIN cols -->

<script>
/* ════════════════════════════════════════════════════
   EXCLUSION CONCATÉNATION — valable pour l'examen en cours
   ════════════════════════════════════════════════════ */
var exclusions = {};   // ex: { 'S_Fonctionnels': true }

/* Au submit : vide les champs exclus pour qu'ils soient enregistrés vides */
function appliquerExclusions() {
    Object.keys(exclusions).forEach(function(nom) {
        var el = document.querySelector('[name=' + nom + ']');
        if (el) el.value = '';
    });
    return true;  // laisser le formulaire s'envoyer
}

function toggleExcl(nom) {
    var wrap = document.getElementById('wrap_' + nom);
    var btn  = wrap ? wrap.querySelector('.btn-excl') : null;
    if (!wrap || !btn) return;
    if (exclusions[nom]) {
        // Réintégrer
        delete exclusions[nom];
        wrap.classList.remove('exclu-champ');
        btn.classList.remove('exclu');
        btn.textContent = '−';
        btn.title = 'Exclure du rapport';
    } else {
        // Exclure
        exclusions[nom] = true;
        wrap.classList.add('exclu-champ');
        btn.classList.add('exclu');
        btn.textContent = '+';
        btn.title = 'Réintégrer dans le rapport';
    }
    // Met à jour le champ caché correspondant
    var hid = document.getElementById('excl_' + nom);
    if (hid) hid.value = exclusions[nom] ? '1' : '';
}

/* ── Examen : Normal ── */
function remplirExamenNormal() {
    exclusions = {};  // réinitialiser les exclusions
    document.querySelectorAll('.exclu-champ').forEach(function(el) {
        el.classList.remove('exclu-champ');
    });
    document.querySelectorAll('.btn-excl').forEach(function(btn) {
        btn.classList.remove('exclu');
        btn.textContent = '−';
        btn.title = 'Exclure du rapport';
    });
    document.querySelectorAll('[id^=excl_]').forEach(function(h){ h.value=''; });

    document.querySelector('[name=S_Fonctionnels]').value     = 'Absence de symptomatologie orientant sur la sphère cardio-vasculaire';
    document.querySelector('[name=Auscult_Cardiaque]').value  = 'Auscultation Cardiaque Normale';
    document.querySelector('[name=Auscult_Pulmonaire]').value = 'Auscultation Pulmonaire Normale';
    document.querySelector('[name=Examen_Vasculaire]').value  = 'Examen Vasculaire Normal';
    document.querySelector('[name=Signes_IVG]').value         = 'Absents';
    document.querySelector('[name=Signes_IVD]').value         = 'Absents';
    document.querySelector('[name=Conduite_ATenir]').value    = 'Examen cardio-vasculaire normal';
}
function viderExamen() {
    exclusions = {};
    document.querySelectorAll('.exclu-champ').forEach(function(el){ el.classList.remove('exclu-champ'); });
    document.querySelectorAll('.btn-excl').forEach(function(btn){ btn.classList.remove('exclu'); btn.textContent='−'; });
    document.querySelectorAll('[id^=excl_]').forEach(function(h){ h.value=''; });
    ['S_Fonctionnels','Auscult_Cardiaque','Auscult_Pulmonaire',
     'Examen_Vasculaire','Signes_IVG','Signes_IVD','Autres_Symptomes','Conduite_ATenir']
    .forEach(function(n){ var el=document.querySelector('[name='+n+']'); if(el) el.value=''; });
}

/* ── ECG : Normal ── */
function remplirECGNormal() {
    var setVal = function(n,v){ var el=document.querySelector('[name='+n+']'); if(el) el.value=v; };
    setVal('FREQUENCE','70');
    setVal('rythme_sv','sinusal');
    setVal('trouble_rv','régulier');
    setVal('rythme_v','normal');
    setVal('conduction_nodale','normale');
    setVal('QRS','normaux');
    setVal('infrastructure_de_conduction','conductInfraN normale');
    setVal('REPOLARISATION','normale');
    setVal('SEGMENT_ST','normal');
    setVal('ONDE_T','normale');
    setVal('IDM','absents');
    setVal('CC','ECG normal');
}
function viderECG() {
    ['FREQUENCE','rythme_sv','trouble_rv','rythme_v','conduction_nodale',
     'QRS','infrastructure_de_conduction','REPOLARISATION','SEGMENT_ST',
     'TOPOGRAPHIE_ST','ONDE_T','TOPOGRAPHIE_T','IDM','TOPOGRAPHIE_Q','CC','AUTRES_SIGNES']
    .forEach(function(n){ var el=document.querySelector('[name='+n+']'); if(el) el.value=''; });
}

/* ── Echo : Normal ── */
function remplirEchoNormal() {
    var setVal = function(n,v){ var el=document.querySelector('[name='+n+']'); if(el) el.value=v; };
    setVal('FEVG','60'); setVal('DTD_VG','50'); setVal('DTS_VG','32');
    setVal('SIV','9');   setVal('PP','9');      setVal('RACINE_AO','34');
    setVal('HTAP','absente'); setVal('CINETIQUE','normale'); setVal('ECHOGENICITE','normale');
    document.querySelector('[name=DOPPLER]').value = 'Flux au doppler normal';
    document.querySelector('[name=CONCLUSION1]').value =
        "Absence de dilatation ou d'hypertrophie cavitaire. Flux au doppler : normal.\n" +
        "Cinetique globale et regionale normale\n" +
        "Fonctions du ventricule gauche normale, Absence d'hypertension arterielle pulmonaire\n" +
        "Pression de remplissage du ventricule gauche normale. Pericarde sec.\n" +
        "Oreillettes de volume normal, aorte ascendante de diametre normal";
}
function viderEcho() {
    ['FEVG','DTD_VG','DTS_VG','SIV','PP','RACINE_AO','HTAP','CINETIQUE','ECHOGENICITE']
    .forEach(function(n){ var el=document.querySelector('[name='+n+']'); if(el) el.value=''; });
    ['DOPPLER','DTSA','CONCLUSION1']
    .forEach(function(n){ var el=document.querySelector('[name='+n+']'); if(el) el.value=''; });
}
</script>

</body>
</html>
