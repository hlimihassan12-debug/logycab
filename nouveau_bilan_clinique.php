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

// ── TRAITEMENT ──────────────────────────────────────────────────────
// Convertit YYYY-MM-DD en format accepté par SQL Server datetime
function toSqlDate($d) {
    if (!$d) return null;
    $ts = strtotime($d);
    return ($ts && $ts > 0) ? date('Y-m-d H:i:s', $ts) : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $onglet = $_POST['onglet'] ?? '';

    if ($onglet === 'examen') {
        $dEx = $_POST['DateExam'] ?? $_POST['Date de l\'examen'] ?? date('Y-m-d');
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
        $dEcg = $_POST['Date_ECG'] ?? $_POST['Date ECG'] ?? date('Y-m-d');
        $db->prepare("INSERT INTO ecg
            ([N-PAT],[Date ECG],[trouble de rythme],
             [RYTHME SUPRA VENTRICULAIRE],[RYTHME VENTRICULAIRE],
             FREQUENCE,[LA CONDUCTION NODALE],QRS,
             [LA CONDUCTION INFRANODALE],[LA REPOLARISATION],
             [SEGMENT ST],TOPOGRAPHIE_ST,ONDE_T,TOPOGRAPHIE_T,
             IDM,TOPOGRAPHIE_Q,[ONDE EPSILON],[ONDE U],
             [AUTRES Signes ECG],[C/C])
            VALUES (?,CONVERT(datetime,?,120),?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([$id,
            $dEcg.' 00:00:00',
            $_POST['problème']?:null,
            $_POST['rythme_sv']?:null,
            $_POST['rythme_v']?:null,
            $_POST['FRÉQUENCE']?:null,
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
            isset($_POST['ONDE_EPSILON'])?1:0,
            isset($_POST['ONDE_U'])?1:0,
            $_POST['AUTRES_SIGNES']?:null,
            $_POST['CC']?:null]);
        header("Location: ?id=$id&msg=ecg_ok"); exit;
    }

    if ($onglet === 'echo') {
        $dEcho = $_POST['DATEchog'] ?? $_POST['Date Echo'] ?? $_POST['Date echo-doppler'] ?? date('Y-m-d');
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
    display: flex; align-items: center; gap: 6px;
}

.msg { padding: 6px 10px; border-radius: 4px; margin-bottom: 10px;
       font-size: 11px; background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }

.sec { font-size: 10px; font-weight: bold; color: #888; text-transform: uppercase;
       letter-spacing: 0.5px; margin: 10px 0 6px; }

.champ { margin-bottom: 7px; }
.champ label { font-size: 10px; color: #888; display: block; margin-bottom: 2px; }
.champ input, .champ textarea {
    width: 100%; padding: 4px 6px;
    border: 1px solid #ddd; border-radius: 3px;
    font-size: 11px; font-family: Arial, sans-serif;
}
.champ input:focus, .champ textarea:focus {
    outline: none; border-color: #2e6da4;
    box-shadow: 0 0 0 2px rgba(46,109,164,0.12);
}
.champ textarea { resize: vertical; min-height: 48px; }

.grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
.grid3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 6px; }

.btn-save {
    width: 100%; background: #27ae60; color: white;
    border: none; border-radius: 4px; padding: 7px;
    font-size: 12px; font-weight: bold; cursor: pointer;
    margin-top: 10px;
}
.btn-save:hover { background: #1e8449; }
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

    <!-- ══ COLONNE 1 : EXAMEN CLINIQUE ══ -->
    <div class="col-card">
        <form method="POST" id="form-examen">
        <input type="hidden" name="onglet" value="examen">
        <div class="col-title">🩺 Examen clinique
            <span style="display:flex;align-items:center;gap:6px;">
                <input type="date" name="DateExam" value="<?= $today ?>" style="border:1px solid #ddd;border-radius:3px;padding:2px 5px;font-size:11px;color:#1a4a7a;">
                <button type="submit" class="btn-save" style="margin:0;width:auto;padding:3px 10px;font-size:11px;">💾 Enregistrer</button>
            </span>
        </div>
        <?php if (!empty($msgs['examen'])): ?><div class="msg"><?= $msgs['examen'] ?></div><?php endif; ?>
        <div class="sec">Mesures</div>
        <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-bottom:7px;">
            <label style="font-size:10px;color:#888;white-space:nowrap;">TAS</label>
            <input type="number" name="TAS" placeholder="120" style="width:52px;padding:4px 5px;border:1px solid #ddd;border-radius:3px;font-size:11px;">
            <label style="font-size:10px;color:#888;white-space:nowrap;">TAD</label>
            <input type="number" name="TAD" placeholder="80" style="width:52px;padding:4px 5px;border:1px solid #ddd;border-radius:3px;font-size:11px;">
            <label style="font-size:10px;color:#888;white-space:nowrap;">FC</label>
            <input type="number" name="FC" placeholder="70" style="width:52px;padding:4px 5px;border:1px solid #ddd;border-radius:3px;font-size:11px;">
            <label style="font-size:10px;color:#888;white-space:nowrap;">Poids</label>
            <input type="number" step="0.1" name="POIDS" placeholder="70" style="width:52px;padding:4px 5px;border:1px solid #ddd;border-radius:3px;font-size:11px;">
            <label style="font-size:10px;color:#888;white-space:nowrap;">Taille</label>
            <input type="number" name="TAILLE" placeholder="170" style="width:52px;padding:4px 5px;border:1px solid #ddd;border-radius:3px;font-size:11px;">
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

    <!-- ══ COLONNE 2 : ECG ══ -->
    <div class="col-card">
        <form method="POST" id="form-ecg">
        <input type="hidden" name="onglet" value="ecg">
        <div class="col-title">⚡ ECG
            <span style="display:flex;align-items:center;gap:6px;">
                <input type="date" name="Date_ECG" value="<?= $today ?>" style="border:1px solid #ddd;border-radius:3px;padding:2px 5px;font-size:11px;color:#1a4a7a;">
                <button type="submit" class="btn-save" style="margin:0;width:auto;padding:3px 10px;font-size:11px;">💾 Enregistrer</button>
            </span>
        </div>
        <?php if (!empty($msgs['ecg'])): ?><div class="msg"><?= $msgs['ecg'] ?></div><?php endif; ?>

        <div class="champ"><label>Fréquence (bpm)</label><input type="number" name="FRÉQUENCE" placeholder="75"></div>

        <div class="champ"><label>Rythme supra-ventriculaire</label>
            <select name="rythme_sv">
                <option value="">—</option>
                <?php foreach(['sinusal','arythmie complete par fibrillation auriculaire','tachysystolie auriculaire','flutter auriculaire 1/1','flutter auriculaire 2/1','flutter auriculaire 3/1','tachyarythmie','bradyarythmie','bradycardie sinusale','tachycardie sinusale','rythme jonctionelle','rythme du sinus auriculaire','electro entraine'] as $v): ?>
                <option value="<?= $v ?>"><?= ucfirst($v) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="champ"><label>C/C</label><input type="text" name="CC"></div>

        <!-- BOUTON BASCULE MODE DÉTAILLÉ -->
        <div style="text-align:center;margin:8px 0;">
            <button type="button" onclick="toggleECGDetail()" id="btn-ecg-detail"
                style="background:#e8f0fb;color:#1a4a7a;border:1px solid #2e6da4;border-radius:4px;padding:4px 14px;font-size:11px;cursor:pointer;">
                ▼ Mode détaillé
            </button>
        </div>

        <div id="ecg-detail" style="display:none;">
            <div class="champ"><label>Trouble de rythme ventriculaire</label><input type="text" name="problème"></div>
            <div class="champ"><label>Rythme ventriculaire</label><input type="text" name="rythme_v"></div>

            <div class="champ"><label>Conduction nodale</label>
                <select name="conduction_nodale">
                    <option value="">—</option>
                    <?php foreach(['normale','BAV I','BAVII','BAVIII','MOBITZ I','MOBITZ II','Luciani Weckenbeg'] as $v): ?>
                    <option value="<?= $v ?>"><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="champ"><label>QRS</label>
                <select name="QRS">
                    <option value="">—</option>
                    <?php foreach(['normaux','bas voltage en derivations standarts'] as $v): ?>
                    <option value="<?= $v ?>"><?= ucfirst($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="champ"><label>Conduction infranodale</label>
                <select name="infrastructure_de_conduction">
                    <option value="">—</option>
                    <?php foreach(['conductInfraN normale','Bloc incomplet gauche','Bloc incomplet droit','hemibloc anterieur gauche','hemibloc posterieur','bloc droit complet','Bloc incomplet gauche et Bloc incomplet droit','hemibloc incomplet gauche','syndrome de preexitation'] as $v): ?>
                    <option value="<?= $v ?>"><?= ucfirst($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="champ"><label>Repolarisation</label>
                <select name="REPOLARISATION">
                    <option value="">—</option>
                    <?php foreach(['normale','anormale'] as $v): ?>
                    <option value="<?= $v ?>"><?= ucfirst($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid2">
                <div class="champ"><label>Segment ST</label>
                    <select name="SEGMENT_ST">
                        <option value="">—</option>
                        <?php foreach(['normal','plat','sous decalage ascendant','sous decalage descendant'] as $v): ?>
                        <option value="<?= $v ?>"><?= ucfirst($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="champ"><label>Topographie ST</label>
                    <select name="TOPOGRAPHIE_ST">
                        <option value="">—</option>
                        <?php foreach(['anterieur','anterieur etendu','antero-apical','antero-lateral','antero-septal','antero-septo-apical','apical','circonferonciel','inferieur','infero-lateral','infero-septal','lateral','latero-septal','posterieur','postero-apical','postero-lateral','postero-septal','septal','septo-apical','septal profond'] as $v): ?>
                        <option value="<?= $v ?>"><?= ucfirst($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid2">
                <div class="champ"><label>Onde T</label>
                    <select name="ONDE_T">
                        <option value="">—</option>
                        <?php foreach(['normale','plates','negatives','trouble diffus de repolarisation'] as $v): ?>
                        <option value="<?= $v ?>"><?= ucfirst($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="champ"><label>Topographie T</label>
                    <select name="TOPOGRAPHIE_T">
                        <option value="">—</option>
                        <?php foreach(['anterieur','anterieur etendu','antero-apical','antero-lateral','antero-septal','antero-septo-apical','apical','circonferonciel','inferieur','infero-lateral','infero-septal','lateral','latero-septal','posterieur','postero-apical','postero-lateral','postero-septal','septal','septo-apical','septal profond'] as $v): ?>
                        <option value="<?= $v ?>"><?= ucfirst($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid2">
                <div class="champ"><label>IDM</label>
                    <select name="IDM">
                        <option value="">—</option>
                        <?php foreach(['absents','présents'] as $v): ?>
                        <option value="<?= $v ?>"><?= ucfirst($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="champ"><label>Topographie Q</label>
                    <select name="TOPOGRAPHIE_Q">
                        <option value="">—</option>
                        <?php foreach(['anterieur','anterieur etendu','antero-apical','antero-lateral','antero-septal','antero-septo-apical','apical','circonferonciel','inferieur','infero-lateral','infero-septal','lateral','latero-septal','posterieur','postero-apical','postero-lateral','postero-septal','septal','septo-apical','septal profond'] as $v): ?>
                        <option value="<?= $v ?>"><?= ucfirst($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="display:flex;gap:16px;margin:6px 0;">
                <label style="font-size:11px;display:flex;align-items:center;gap:4px;cursor:pointer;">
                    <input type="checkbox" name="ONDE_EPSILON"> Onde Epsilon
                </label>
                <label style="font-size:11px;display:flex;align-items:center;gap:4px;cursor:pointer;">
                    <input type="checkbox" name="ONDE_U"> Onde U
                </label>
            </div>

            <div class="champ"><label>Autres signes ECG</label><input type="text" name="AUTRES_SIGNES"></div>
        </div><!-- FIN ecg-detail -->

        </form>
    </div>

    <!-- ══ COLONNE 3 : ECHO-DOPPLER ══ -->
    <div class="col-card">
        <form method="POST" id="form-echo">
        <input type="hidden" name="onglet" value="echo">
        <div class="col-title">🫀 Echo-Doppler
            <span style="display:flex;align-items:center;gap:6px;">
                <input type="date" name="DATEchog" value="<?= $today ?>" style="border:1px solid #ddd;border-radius:3px;padding:2px 5px;font-size:11px;color:#1a4a7a;">
                <button type="submit" class="btn-save" style="margin:0;width:auto;padding:3px 10px;font-size:11px;">💾 Enregistrer</button>
            </span>
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
        <div class="champ"><label>Doppler</label><textarea name="DOPPLER"></textarea></div>
        <div class="champ"><label>DTSA</label><textarea name="DTSA"></textarea></div>
        <div class="champ"><label>Conclusion</label><textarea name="CONCLUSION1" style="min-height:60px;"></textarea></div>
        </form>
    </div>

</div><!-- FIN cols -->
<script>
function toggleECGDetail() {
    const d = document.getElementById('ecg-detail');
    const b = document.getElementById('btn-ecg-detail');
    const visible = d.style.display !== 'none';
    d.style.display = visible ? 'none' : 'block';
    b.textContent  = visible ? '▼ Mode détaillé' : '▲ Mode simplifié';
}
</script>
</body>
</html>
