<?php
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
if ($id == 0) die("❌ Patient introuvable.");

$stmt = $db->prepare("SELECT * FROM ID WHERE [N°PAT] = ?");
$stmt->execute([$id]);
$patient = $stmt->fetch();
if (!$patient) die("❌ Patient introuvable.");

$nomPatient = strtoupper(trim($patient['NOMPRENOM'] ?? ''));
$ddn = ''; $age = '';
if (!empty($patient['DDN'])) {
    $ts = strtotime($patient['DDN']);
    if ($ts && $ts > 86400) { $ddn = date('d/m/Y', $ts); $age = (new DateTime($patient['DDN']))->diff(new DateTime())->y; }
}
$atcd    = htmlspecialchars(trim($patient['ATCD'] ?? ''));
$diagRaw = htmlspecialchars(trim($patient['diagnostic'] ?? ''));

$stmtEx = $db->prepare("SELECT TOP 1 * FROM t_examen WHERE NPAT = ? ORDER BY DateExam DESC, N1 DESC");
$stmtEx->execute([$id]);
$examen = $stmtEx->fetch();
$texteExamen    = $examen ? htmlspecialchars(trim($examen['CMLM_EXAMEN'] ?? '')) : '';
$conduiteATenir = $examen ? htmlspecialchars(trim($examen['Conduite_ATenir'] ?? '')) : '';

$stmtECG = $db->prepare("SELECT TOP 1 * FROM ecg WHERE CAST([N-PAT] AS INT) = ? ORDER BY [Date ECG] DESC, [N°] DESC");
$stmtECG->execute([$id]);
$ecg = $stmtECG->fetch();
$texteECG = $ecg ? htmlspecialchars(trim($ecg['CMLM_ECG'] ?? '')) : '';

$stmtEcho = $db->prepare("SELECT TOP 1 * FROM echo WHERE [N-PAT] = ? ORDER BY DATEchog DESC, [N°] DESC");
$stmtEcho->execute([$id]);
$echo = $stmtEcho->fetch();
$texteEcho = $echo ? htmlspecialchars(trim($echo['CMLM_ECHO'] ?? '')) : '';

$stmtBio3 = $db->prepare("SELECT TOP 3 CONVERT(varchar(10), date_bilan, 103) AS date_fr, CONVERT(varchar(10), date_bilan, 112) AS date_tri FROM LE_BILAN WHERE id = ? GROUP BY CONVERT(varchar(10), date_bilan, 103), CONVERT(varchar(10), date_bilan, 112) ORDER BY date_tri DESC");
$stmtBio3->execute([$id]);
$datesBio = $stmtBio3->fetchAll();
$bioTexte = '';
foreach ($datesBio as $d) {
    $stmtIds = $db->prepare("SELECT n_bilan FROM LE_BILAN WHERE id = ? AND CONVERT(varchar(10), date_bilan, 103) = ?");
    $stmtIds->execute([$id, $d['date_fr']]);
    $ids = $stmtIds->fetchAll(PDO::FETCH_COLUMN);
    if (empty($ids)) continue;
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $stmtAn = $db->prepare("SELECT c.analyse AS nom, ISNULL(a.résultat,'') AS resultat FROM analyses a LEFT JOIN C_ANALYSE c ON c.[N°TypeAnalyse] = a.bilan WHERE a.N_bilan IN ($ph) AND ISNULL(a.résultat,'') <> '' AND a.résultat <> 'N' ORDER BY c.rubrique, c.analyse");
    $stmtAn->execute($ids);
    $anormaux = $stmtAn->fetchAll();
    if (!empty($anormaux)) {
        $parties = [];
        foreach ($anormaux as $an) $parties[] = htmlspecialchars($an['nom']).' '.htmlspecialchars($an['resultat']);
        $bioTexte .= $d['date_fr'].' : '.implode(', ', $parties)."\n";
    }
}
$bioTexte = htmlspecialchars(trim($bioTexte));

$stmtSpec = $db->prepare("SELECT id_spec, libelle FROM T_Specialites ORDER BY ordre, libelle");
$stmtSpec->execute();
$specialites = $stmtSpec->fetchAll();

$dateAuj = date('d/m/Y');
?>
<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">
<title>Lettre — <?= htmlspecialchars($nomPatient) ?></title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
@page { size: B5; margin: 0; }
body { font-family:Arial,sans-serif; font-size:12px; color:#111; background:white; width:176mm; min-height:250mm; padding-top:4cm; padding-bottom:2cm; padding-left:1.5cm; padding-right:1.5cm; }
.btn-bar { position:fixed; top:0; left:0; right:0; background:#1a4a7a; color:white; padding:6px 20px; display:flex; align-items:center; gap:12px; z-index:999; }
.btn-print { background:#27ae60; color:white; border:none; border-radius:4px; padding:5px 16px; font-size:12px; font-weight:bold; cursor:pointer; }
.btn-close  { background:#e74c3c; color:white; border:none; border-radius:4px; padding:5px 12px; font-size:12px; cursor:pointer; margin-left:auto; }
.ligne-date { text-align:right; margin-bottom:5mm; font-size:12px; }
.dest-label { font-size:12px; font-weight:bold; color:#1a4a7a; margin-bottom:3px; }
.spec-grille { display:flex; flex-wrap:wrap; gap:6px 18px; margin:4px 0 4px 16px; }
.spec-case { display:flex; align-items:center; gap:4px; font-size:12px; cursor:pointer; }
.spec-case input[type="radio"] { cursor:pointer; accent-color:#1a4a7a; }
.autre-spec { display:flex; align-items:center; gap:6px; margin-top:5px; margin-left:16px; }
.autre-spec input[type="text"] { border:1px solid #ccc; border-radius:3px; padding:3px 7px; font-size:12px; width:200px; }
.btn-ajouter-spec { background:#2e6da4; color:white; border:none; border-radius:3px; padding:3px 10px; font-size:11px; cursor:pointer; }
.identite { background:#f0f7ff; border-left:3px solid #2e6da4; padding:4px 8px; margin:4mm 0; font-size:12px; line-height:1.6; }
.section { margin-top:3mm; }
.section-titre { font-size:12px; font-weight:bold; text-decoration:underline; color:#1a4a7a; margin-bottom:1mm; }
.section-corps { border-left:3px solid #ccc; padding-left:8px; font-size:12px; line-height:1.4; white-space:pre-wrap; word-wrap:break-word; }
.sous-label { font-size:11px; color:#555; margin-top:3px; margin-bottom:1px; }
.editable { width:100%; border:1px dashed #aaa; border-radius:3px; padding:4px 6px; font-size:12px; font-family:Arial,sans-serif; line-height:1.4; resize:vertical; background:#fafeff; color:#111; }
.editable:focus { outline:none; border-color:#2e6da4; background:#f0f7ff; }
.au-total-titre { font-size:12px; font-weight:bold; text-decoration:underline; margin-top:3mm; margin-bottom:1mm; color:#111; }
.au-total-corps { border-left:3px solid #1a4a7a; padding-left:8px; font-size:12px; line-height:1.4; white-space:pre-wrap; }
.formule { margin-top:6mm; font-size:12px; line-height:1.6; }
.signature { margin-top:8mm; font-size:12px; font-weight:bold; }
@media screen { body { margin:36px auto 20px; box-shadow:0 2px 10px rgba(0,0,0,0.15); border:1px solid #ddd; } }
@media print {
    .btn-bar, .no-print, .autre-spec { display:none !important; }
    .editable { border:none !important; background:transparent !important; padding:0 !important; resize:none !important; overflow:visible !important; height:auto !important; }
    .identite { background:white !important; }
    .spec-case input[type="radio"]:not(:checked)+label { display:none; }
    .spec-case input[type="radio"]:not(:checked)       { display:none; }
    .spec-case input[type="radio"]:checked             { display:none; }
    .spec-case input[type="radio"]:checked+label       { font-weight:bold; }
}
</style></head><body>

<div class="btn-bar">
    <button class="btn-print" onclick="window.print()">🖨️ Imprimer</button>
    <span>✉️ Lettre — <?= htmlspecialchars($nomPatient) ?></span>
    <button class="btn-close" onclick="window.close()">✕ Fermer</button>
</div>

<div style="margin-bottom:5mm;">
    <div class="dest-label">À l'attention du :</div>
    <div class="spec-grille" id="spec_grille">
    <?php foreach ($specialites as $sp): ?>
        <div class="spec-case" id="case_<?= $sp['id_spec'] ?>">
            <input type="radio" name="destinataire" id="spec_<?= $sp['id_spec'] ?>" value="<?= htmlspecialchars($sp['libelle']) ?>" onchange="majSelection(this)">
            <label for="spec_<?= $sp['id_spec'] ?>"><?= htmlspecialchars($sp['libelle']) ?></label>
        </div>
    <?php endforeach; ?>
    </div>
    <div class="autre-spec no-print">
        <span style="font-size:11px;color:#555;">Autre :</span>
        <input type="text" id="autre_spec_input" placeholder="Ex : Neurochirurgien…">
        <button class="btn-ajouter-spec" onclick="ajouterSpecialite()">+ Ajouter</button>
        <span id="spec_msg" style="font-size:11px;color:#27ae60;"></span>
    </div>
</div>

<div class="ligne-date">Tétouan, le <?= $dateAuj ?></div>

<div class="identite">
    <strong><?= htmlspecialchars($nomPatient) ?></strong>
    <?php if ($age): ?> — <?= $age ?> ans<?php endif; ?>
    <?php if ($ddn): ?>, né(e) le <?= $ddn ?><?php endif; ?>
</div>

<div class="section" style="margin-top:0;">
    <div class="section-corps">
        C'est un(e) patient(e) qui présente :
        <textarea class="editable" rows="2"><?= $diagRaw ?: '—' ?></textarea>
    </div>
</div>

<?php if ($atcd): ?>
<div class="section">
    <div class="section-titre">Antécédents :</div>
    <div class="section-corps"><textarea class="editable" rows="2"><?= $atcd ?></textarea></div>
</div>
<?php endif; ?>

<div class="section">
    <div class="section-titre">Données cliniques :</div>
    <div class="section-corps">
        <div class="sous-label">Examen clinique :</div>
        <textarea class="editable" rows="2"><?= $texteExamen ?: '—' ?></textarea>
        <div class="sous-label">Examen ECG :</div>
        <textarea class="editable" rows="2"><?= $texteECG ?: '—' ?></textarea>
        <div class="sous-label">Examen Écho-Doppler :</div>
        <textarea class="editable" rows="2"><?= $texteEcho ?: '—' ?></textarea>
        <?php if ($bioTexte): ?>
        <div class="sous-label">Examen biologique :</div>
        <textarea class="editable" rows="2"><?= $bioTexte ?></textarea>
        <?php endif; ?>
    </div>
</div>

<div class="au-total-titre">Au total — Conduite à tenir :</div>
<div class="au-total-corps"><textarea class="editable" rows="2"><?= $conduiteATenir ?: '—' ?></textarea></div>

<div class="formule">Avec mes cordiales salutations confraternelles,</div>
<div class="signature">Dr Hassan Hlimi<br><span style="font-weight:normal;font-size:11px;">Cardiologue — Tétouan</span></div>

<script>
function majSelection(radio) {
    document.querySelectorAll('.spec-case').forEach(c=>c.classList.remove('selected'));
    if(radio.checked) radio.closest('.spec-case').classList.add('selected');
}
function ajouterSpecialite() {
    const input=document.getElementById('autre_spec_input'), msg=document.getElementById('spec_msg'), lib=input.value.trim();
    if(!lib) return;
    msg.textContent='Enregistrement…'; msg.style.color='#999';
    fetch('ajax_cmlm_specialites.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'ajouter',libelle:lib})})
    .then(r=>r.json()).then(d=>{
        if(d.success){
            if(!d.doublon){
                const grille=document.getElementById('spec_grille'), div=document.createElement('div');
                div.className='spec-case selected'; div.id='case_'+d.id_spec;
                div.innerHTML=`<input type="radio" name="destinataire" id="spec_${d.id_spec}" value="${lib.replace(/"/g,'&quot;')}" onchange="majSelection(this)" checked><label for="spec_${d.id_spec}">${lib}</label>`;
                grille.appendChild(div);
                document.querySelectorAll('.spec-case').forEach(c=>{ if(c.id!=='case_'+d.id_spec) c.classList.remove('selected'); });
            } else { const r=document.getElementById('spec_'+d.id_spec); if(r){r.checked=true;majSelection(r);} }
            input.value=''; msg.textContent='✓ Ajouté'; msg.style.color='#27ae60'; setTimeout(()=>msg.textContent='',2500);
        } else { msg.textContent='❌'+(d.error||'Erreur'); msg.style.color='#e74c3c'; }
    }).catch(()=>{msg.textContent='❌ Erreur réseau';msg.style.color='#e74c3c';});
}
document.getElementById('autre_spec_input').addEventListener('keydown',e=>{ if(e.key==='Enter') ajouterSpecialite(); });
document.querySelectorAll('textarea.editable').forEach(t=>{ t.style.height='auto'; t.style.height=t.scrollHeight+'px'; });
window.addEventListener('beforeprint',()=>document.querySelectorAll('textarea.editable').forEach(t=>{ t.style.height='auto'; t.style.height=t.scrollHeight+'px'; }));
window.addEventListener('afterprint',()=>window.close());
</script>
</body></html>
