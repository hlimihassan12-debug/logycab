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
             Autres_Symptomes,Conclusion,REMARQUE)
            VALUES (?,CONVERT(datetime,?,120),?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([$id,
            $dEx.' 00:00:00',$_POST['TAS']?:null,$_POST['TAD']?:null,
            $_POST['FC']?:null,$_POST['POIDS']?:null,$_POST['TAILLE']?:null,
            $_POST['S_Fonctionnels']?:null,$_POST['Auscult_Cardiaque']?:null,
            $_POST['Auscult_Pulmonaire']?:null,$_POST['Examen_Vasculaire']?:null,
            $_POST['Signes_IVG']?:null,$_POST['Signes_IVD']?:null,
            $_POST['Autres_Symptomes']?:null,$_POST['Conclusion']?:null,
            $_POST['REMARQUE']?:null]);
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
        $dEcho    = $_POST['DATEchog'] ?? date('Y-m-d');
        $typeEcho = $_POST['TYPE_ECHO'] ?? 'TSD'; // 'TSD' ou 'TAD'
        $db->prepare("INSERT INTO echo
            ([N-PAT], DATEchog,
             CINETIQUE, [DTD-VG], FEVG, PTDVG, HTAP,
             SIV, PP, AO_ASC, PERICARDE, [S,OG],
             GLOBAL_STRAIN, DOPPLER,
             [DOPPLER DES TRONCS SUPRA AORTIQUES],
             CONCLUSION1, TYPE_ECHO)
            VALUES (?,CONVERT(datetime,?,120),
             ?,?,?,?,?,
             ?,?,?,?,?,
             ?,?,
             ?,
             ?,?)")
        ->execute([$id, $dEcho.' 00:00:00',
            $_POST['CINETIQUE']     ?? null,
            $_POST['DTD_VG']        ?? null,
            $_POST['FEVG']          ?? null,
            $_POST['PTDVG']         ?? null,
            $_POST['HTAP']          ?? null,
            $_POST['SIV']           ?? null,
            $_POST['PP']            ?? null,
            $_POST['AO_ASC']        ?? null,
            $_POST['PERICARDE']     ?? null,
            $_POST['S_OG']          ?? null,
            $_POST['GLOBAL_STRAIN'] ?? null,
            $_POST['DOPPLER']       ?? null,
            $_POST['DTSA']          ?? null,
            $_POST['CONCLUSION1']   ?? null,
            $typeEcho]);
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

.cols { display: grid; grid-template-columns: 1fr 1fr 2fr; gap: 10px; padding: 10px; align-items: start; }

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
</style>
</head>
<body>

<div class="header">
    <div>
        <div class="sub">🩺 Nouveau bilan clinique</div>
        <h1><?= htmlspecialchars($nom) ?> &nbsp;—&nbsp; N° <?= $id ?></h1>
    </div>
    <a href="dossier.php?id=<?= $id ?>" class="btn-retour">← Retour dossier</a>
</div>

<div class="cols">

<!-- ══════════════════════════════════════════════
     COLONNE 1 : EXAMEN CLINIQUE
══════════════════════════════════════════════ -->
<div class="col-card">
    <form method="POST">
    <input type="hidden" name="onglet" value="examen">
    <div class="col-title">
        🩺 Examen clinique
        <div class="date-enreg">
            <input type="date" name="DateExam" value="<?= $today ?>">
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

    <div class="sec">Clinique</div>
    <div class="champ"><label>Signes fonctionnels</label><textarea name="S_Fonctionnels"></textarea></div>
    <div class="champ"><label>Auscultation cardiaque</label><textarea name="Auscult_Cardiaque"></textarea></div>
    <div class="champ"><label>Auscultation pulmonaire</label><textarea name="Auscult_Pulmonaire"></textarea></div>
    <div class="champ"><label>Examen vasculaire</label><textarea name="Examen_Vasculaire"></textarea></div>
    <div class="grid2">
        <div class="champ"><label>Signes IVG</label><textarea name="Signes_IVG"></textarea></div>
        <div class="champ"><label>Signes IVD</label><textarea name="Signes_IVD"></textarea></div>
    </div>
    <div class="champ"><label>Autres symptômes</label><textarea name="Autres_Symptomes"></textarea></div>

    <div class="sec">Conclusion</div>
    <div class="champ"><label>Conclusion</label><textarea name="Conclusion" style="min-height:60px;"></textarea></div>
    <div class="champ"><label>Remarque</label><textarea name="REMARQUE"></textarea></div>
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
        <div class="date-enreg">
            <input type="date" name="Date_ECG" value="<?= $today ?>">
            <button type="submit" class="btn-save">💾 Enregistrer</button>
        </div>
    </div>
    <?php if (!empty($msgs['ecg'])): ?><div class="msg"><?= $msgs['ecg'] ?></div><?php endif; ?>

    <!-- 1. Fréquence -->
    <div class="champ"><label>Fréquence (bpm)</label>
        <input type="number" name="FREQUENCE" placeholder="75" min="20" max="300">
    </div>

    <!-- 2. Rythme supra-ventriculaire -->
    <div class="champ"><label>Rythme supra-ventriculaire</label>
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

    <!-- 3. Trouble de rythme ventriculaire (NOUVEAU) -->
    <div class="champ"><label>Trouble de rythme ventriculaire</label>
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
    <div class="champ"><label>Conduction nodale</label>
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
    <div class="champ"><label>QRS</label>
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
    <div class="champ"><label>Conduction infranodale</label>
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
    <div class="champ"><label>Repolarisation</label>
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
    <div class="champ"><label>C/C</label>
        <input type="text" name="CC" placeholder="ex: ECG normal">
    </div>

    <!-- 13. Autres signes ECG (NOUVEAU) -->
    <div class="champ"><label>Autres signes ECG</label>
        <input type="text" name="AUTRES_SIGNES">
    </div>

    </form>
</div>

<!-- ══════════════════════════════════════════════
     COLONNE 3 : ECHO-DOPPLER
══════════════════════════════════════════════ -->
<div class="col-card" style="grid-column: span 1;">
    <?php if (!empty($msgs['echo'])): ?>
        <div class="msg"><?= $msgs['echo'] ?></div>
    <?php endif; ?>

    <!-- En-tête avec date commune -->
    <div class="col-title">
        🫀 Echo / Echo-Doppler
        <div class="date-enreg">
            <input type="date" id="echo_date" value="<?= $today ?>">
            <button type="button" onclick="remplirNormal()"
                style="background:#2e6da4;color:white;border:none;border-radius:4px;padding:3px 8px;font-size:11px;font-weight:bold;cursor:pointer;white-space:nowrap;">
                ✅ Normal
            </button>
            <button type="button" onclick="viderChamps()"
                style="background:#e67e22;color:white;border:none;border-radius:4px;padding:3px 8px;font-size:11px;font-weight:bold;cursor:pointer;white-space:nowrap;">
                ✏️ Anormal
            </button>
            <button type="button" onclick="enregistrerEcho()"
                style="background:#27ae60;color:white;border:none;border-radius:4px;padding:3px 8px;font-size:11px;font-weight:bold;cursor:pointer;white-space:nowrap;">
                💾 Enregistrer
            </button>
        </div>
    </div>

    <!-- ZONE UNIQUE : TAD avec bouton TSD intégré dans le titre -->
    <div style="background:#eafaf1;border:2px solid #27ae60;border-radius:6px;padding:10px;">

        <!-- Titre avec bouton TSD à gauche -->
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
            <button type="button" onclick="soumettreEchoNormal()"
                style="background:#2e6da4;color:white;border:none;border-radius:4px;padding:4px 10px;font-size:11px;font-weight:bold;cursor:pointer;white-space:nowrap;">
                TSD
            </button>
            <span style="font-size:11px;font-weight:bold;color:#1e8449;text-transform:uppercase;letter-spacing:0.5px;">
                Echographie Cardiaque
            </span>
        </div>

        <!-- ── ZONE DROITE : TAD (examen anormal) ── -->
        <div>
            <form method="POST" id="form_tad">
                <input type="hidden" name="onglet" value="echo">
                <input type="hidden" name="TYPE_ECHO" id="type_echo_val" value="TAD">
                <input type="hidden" name="DATEchog" id="tad_date">

                <div class="grid2">
                    <div class="champ">
                        <label>DTD-VG (mm)</label>
                        <input type="text" name="DTD_VG" id="tad_dtdvg" oninput="mettreAJourConclusion()" placeholder="ex: 55">
                    </div>
                    <div class="champ">
                        <label>FEVG (%)</label>
                        <input type="text" name="FEVG" id="tad_fevg" oninput="mettreAJourConclusion()" placeholder="ex: 60">
                    </div>
                    <div class="champ">
                        <label>SIV (mm)</label>
                        <input type="text" name="SIV" id="tad_siv" oninput="mettreAJourConclusion()" placeholder="ex: 10">
                    </div>
                    <div class="champ">
                        <label>PP (mm)</label>
                        <input type="text" name="PP" id="tad_pp" oninput="mettreAJourConclusion()" placeholder="ex: 10">
                    </div>
                    <div class="champ">
                        <label>S,OG (cm²)</label>
                        <input type="text" name="S_OG" id="tad_sog" oninput="mettreAJourConclusion()" placeholder="ex: 20">
                    </div>
                    <div class="champ">
                        <label>AO Ascendante (mm)</label>
                        <input type="text" name="AO_ASC" id="tad_aoasc" oninput="mettreAJourConclusion()" placeholder="ex: 35">
                    </div>
                    <div class="champ">
                        <label>HTAP (mmHg)</label>
                        <input type="text" name="HTAP" id="tad_htap" oninput="mettreAJourConclusion()" placeholder="ex: 35">
                    </div>
                    <div class="champ">
                        <label>PTDVG</label>
                        <input type="text" name="PTDVG" id="tad_ptdvg" oninput="mettreAJourConclusion()" placeholder="normale">
                    </div>
                    <div class="champ">
                        <label>Global Strain (%)</label>
                        <input type="text" name="GLOBAL_STRAIN" id="tad_gs" oninput="mettreAJourConclusion()" placeholder="ex: -18">
                    </div>
                    <div class="champ">
                        <label>Péricarde</label>
                        <input type="text" name="PERICARDE" id="tad_pericarde" oninput="mettreAJourConclusion()" placeholder="sec">
                    </div>
                </div>

                <div class="champ">
                    <label>Cinétique VG</label>
                    <input type="text" name="CINETIQUE" id="tad_cinetique" oninput="mettreAJourConclusion()" placeholder="cinétique globale et régionale normale">
                </div>
                <div class="champ">
                    <label>Doppler</label>
                    <textarea name="DOPPLER" id="tad_doppler" oninput="mettreAJourConclusion()" style="min-height:40px;" placeholder="flux trans valvaires normal"></textarea>
                </div>
                <div class="champ">
                    <label>DTSA</label>
                    <textarea name="DTSA" id="tad_dtsa" oninput="mettreAJourConclusion()" style="min-height:36px;"></textarea>
                </div>

                <!-- CONCLUSION AUTO -->
                <div class="champ" style="margin-top:6px;">
                    <label style="font-weight:bold;color:#1e8449;">Conclusion (auto-générée)</label>
                    <textarea name="CONCLUSION1" id="tad_conclusion" style="min-height:80px;background:#f0fff4;border-color:#27ae60;font-size:10px;" readonly></textarea>
                </div>
            </form>
        </div><!-- fin zone TAD intérieure -->

    </div><!-- fin zone verte -->
</div><!-- fin col-card Echo -->

</div><!-- FIN cols -->

<script>
function remplirNormal() {
    document.getElementById('type_echo_val').value = 'TSD';
    document.getElementById('tad_cinetique').value = 'cinétique globale et régionale normale';
    document.getElementById('tad_dtdvg').value     = 'normal';
    document.getElementById('tad_fevg').value      = '> 60%';
    document.getElementById('tad_ptdvg').value     = 'normale';
    document.getElementById('tad_htap').value      = "absence d'hypertension artérielle pulmonaire";
    document.getElementById('tad_siv').value       = "absence d'hypertrophie septale";
    document.getElementById('tad_pp').value        = "absence d'hypertrophie de la paroi postérieure";
    document.getElementById('tad_aoasc').value     = 'non dilatée';
    document.getElementById('tad_pericarde').value = 'sec';
    document.getElementById('tad_sog').value       = 'non dilatée';
    document.getElementById('tad_gs').value        = 'normal';
    document.getElementById('tad_doppler').value   = 'flux trans valvaires intracardiaques normaux';
    document.getElementById('tad_dtsa').value      = 'sans particularité';
    mettreAJourConclusion();
}

function viderChamps() {
    document.getElementById('type_echo_val').value  = 'TAD';
    document.getElementById('tad_cinetique').value  = '';
    document.getElementById('tad_dtdvg').value      = '';
    document.getElementById('tad_fevg').value       = '';
    document.getElementById('tad_ptdvg').value      = '';
    document.getElementById('tad_htap').value       = '';
    document.getElementById('tad_siv').value        = '';
    document.getElementById('tad_pp').value         = '';
    document.getElementById('tad_aoasc').value      = '';
    document.getElementById('tad_pericarde').value  = '';
    document.getElementById('tad_sog').value        = '';
    document.getElementById('tad_gs').value         = '';
    document.getElementById('tad_doppler').value    = '';
    document.getElementById('tad_dtsa').value       = '';
    document.getElementById('tad_conclusion').value = '';
}

function enregistrerEcho() {
    document.getElementById('tad_date').value = document.getElementById('echo_date').value;
    mettreAJourConclusion();
    document.getElementById('form_tad').submit();
}

function mettreAJourConclusion() {
    var dtdvg    = document.getElementById('tad_dtdvg').value.trim();
    var fevg     = document.getElementById('tad_fevg').value.trim();
    var cinetique= document.getElementById('tad_cinetique').value.trim() || 'cinétique globale et régionale normale';
    var sog      = document.getElementById('tad_sog').value.trim();
    var aoasc    = document.getElementById('tad_aoasc').value.trim();
    var doppler  = document.getElementById('tad_doppler').value.trim();
    var dtsa     = document.getElementById('tad_dtsa').value.trim();
    var siv      = document.getElementById('tad_siv').value.trim();
    var pp       = document.getElementById('tad_pp').value.trim();
    var htap     = document.getElementById('tad_htap').value.trim();
    var ptdvg    = document.getElementById('tad_ptdvg').value.trim();
    var gs       = document.getElementById('tad_gs').value.trim();
    var pericarde= document.getElementById('tad_pericarde').value.trim() || 'sec';

    var texte = '';

    // Ligne 1 : mesures VG principales
    if (dtdvg || fevg) {
        if (dtdvg) texte += 'DTD-VG: ' + dtdvg + ' mm';
        if (dtdvg && fevg) texte += ', ';
        if (fevg) texte += 'FEVG : ' + fevg + '%';
        texte += ', cinétique VG : ' + cinetique;
        texte += '\n';
    }

    // Ligne 2 : parois
    if (siv || pp) {
        texte += '-parois : SIV: ' + (siv||'—') + ' mm, PP: ' + (pp||'—') + ' mm';
        texte += '\n';
    }

    // Ligne 3 : surface OG
    if (sog) {
        texte += '-surface du massif auriculaire : OG: ' + sog + ' cm²';
        texte += '\n';
    }

    // Ligne 4 : aorte
    if (aoasc) {
        texte += '-diamètre de l\'aorte : Ao ascendante: ' + aoasc + ' mm';
        texte += '\n';
    }

    // Ligne 5 : HTAP
    if (htap) {
        texte += '-HTAP : ' + htap + ' mmHg';
        texte += '\n';
    }

    // Ligne 6 : PTDVG
    if (ptdvg) {
        texte += '-Pression de remplissage du VG : ' + ptdvg;
        texte += '\n';
    }

    // Ligne 7 : Péricarde
    texte += '-Péricarde : ' + pericarde;
    texte += '\n';

    // Ligne 8 : Global Strain
    if (gs) {
        texte += '-Global Strain : ' + gs + '%';
        texte += '\n';
    }

    // Ligne 9 : Doppler
    if (doppler) {
        texte += '-Au doppler : ' + doppler;
        texte += '\n';
    }

    // Ligne 10 : DTSA
    if (dtsa) {
        texte += '-Doppler des troncs supra-aortiques : ' + dtsa;
        texte += '\n';
    }

    document.getElementById('tad_conclusion').value = texte.trim();
}

// Initialiser la conclusion au chargement
mettreAJourConclusion();
</script>

</body>
</html>
