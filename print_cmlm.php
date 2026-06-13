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

$stmtFDR = $db->prepare("SELECT FDR FROM patient_fdr WHERE id = ? ORDER BY N");
$stmtFDR->execute([$id]);
$fdrListe = $stmtFDR->fetchAll(PDO::FETCH_COLUMN);
$fdrTexte = !empty($fdrListe) ? implode(' ; ', array_map('htmlspecialchars', $fdrListe)) : '';

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

// ── Ordonnances ───────────────────────────────────────────────────────────
$stmtOrds = $db->prepare("SELECT TOP 4 n_ordon, date_ordon FROM ORD WHERE id = ? ORDER BY date_ordon DESC");
$stmtOrds->execute([$id]);
$ordonnances = $stmtOrds->fetchAll();
$ordsAvecMeds = [];
foreach ($ordonnances as $ord) {
    $stmtMed = $db->prepare("SELECT p.*, pr.PRODUIT AS nom_produit FROM PROD p LEFT JOIN PRODUITS pr ON p.produit = pr.NuméroPRODUIT WHERE p.N_ord = ? ORDER BY p.Ordre");
    $stmtMed->execute([$ord['n_ordon']]);
    $meds = $stmtMed->fetchAll();
    if (!empty($meds)) {
        $ts = strtotime($ord['date_ordon'] ?? '');
        $ordsAvecMeds[] = ['n_ordon' => $ord['n_ordon'], 'date_fr' => ($ts && $ts > 86400) ? date('d/m/Y', $ts) : '—', 'medicaments' => $meds];
    }
}

$stmtSpec = $db->prepare("SELECT id_spec, libelle FROM T_Specialites ORDER BY ordre, libelle");
$stmtSpec->execute();
$specialites = $stmtSpec->fetchAll();

$dateAuj = date('d/m/Y');
?>
<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">
<title>CMLM — <?= htmlspecialchars($nomPatient) ?></title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
@page { size: B5; margin: 0; }
body { font-family:Arial,sans-serif; font-size:12px; color:#111; background:white; width:176mm; min-height:250mm; padding-top:3cm; padding-bottom:1.5cm; padding-left:1.5cm; padding-right:1.5cm; }
.btn-bar { position:fixed; top:0; left:0; right:0; background:#1a4a7a; color:white; padding:6px 20px; display:flex; align-items:center; gap:12px; z-index:999; }
.btn-print { background:#27ae60; color:white; border:none; border-radius:4px; padding:5px 16px; font-size:12px; font-weight:bold; cursor:pointer; }
.btn-close  { background:#e74c3c; color:white; border:none; border-radius:4px; padding:5px 12px; font-size:12px; cursor:pointer; margin-left:auto; }
.ligne-date { text-align:right; margin-bottom:4mm; font-size:12px; }
.titre-cmlm { border:2px solid #1a4a7a; padding:4px 12px; margin-bottom:4mm; text-align:center; }
.titre-cmlm span { font-size:14px; font-weight:bold; color:#1a4a7a; }
.intro { font-size:12px; line-height:1.5; margin-bottom:3mm; }
.section { margin-top:2.5mm; }
.section-titre { font-size:12px; font-weight:bold; text-decoration:underline; color:#1a4a7a; margin-bottom:1mm; }
.section-corps { border-left:3px solid #ccc; padding-left:8px; font-size:12px; line-height:1.3; white-space:pre-wrap; word-wrap:break-word; }
.editable { width:100%; border:1px dashed #aaa; border-radius:3px; padding:4px 6px; font-size:12px; font-family:Arial,sans-serif; line-height:1.4; resize:vertical; background:#fafeff; color:#111; }
.editable:focus { outline:none; border-color:#2e6da4; background:#f0f7ff; }
.sous-label { font-size:11px; color:#555; margin-top:3px; margin-bottom:1px; }
.ord-nav { display:flex; align-items:center; gap:6px; margin-bottom:4px; flex-wrap:wrap; }
.ord-nav-btn { background:#1a4a7a; color:white; border:none; border-radius:3px; padding:2px 8px; font-size:11px; cursor:pointer; }
.med-liste { list-style:none; margin:0; padding:0; }
.med-ligne { display:flex; align-items:center; justify-content:space-between; padding:3px 6px; border-bottom:1px solid #eee; font-size:12px; }
.btn-ald { background:#e67e22; color:white; border:none; border-radius:3px; padding:2px 8px; font-size:10px; cursor:pointer; flex-shrink:0; margin-left:8px; }
.btn-ald.deja { background:#95a5a6; cursor:default; }
.traitement-retenu { list-style:none; padding:0; margin:2px 0; }
.traitement-retenu li { display:flex; align-items:center; gap:6px; padding:1px 0; font-size:12px; }
.btn-retirer { background:#e74c3c; color:white; border:none; border-radius:50%; width:16px; height:16px; font-size:10px; cursor:pointer; line-height:1; }
.spec-grille { display:flex; flex-wrap:wrap; gap:8px 16px; margin:4px 0; }
.spec-case { display:flex; align-items:center; gap:4px; font-size:12px; }
.autre-spec { display:flex; align-items:center; gap:6px; margin-top:6px; }
.autre-spec input[type=text] { border:1px solid #ccc; border-radius:3px; padding:3px 6px; font-size:12px; width:180px; }
.btn-ajouter-spec { background:#2e6da4; color:white; border:none; border-radius:3px; padding:3px 10px; font-size:11px; cursor:pointer; }
.attestation { margin-top:4mm; font-size:12px; font-style:italic; border-top:1px solid #ccc; padding-top:2mm; }
.au-total-titre { font-size:12px; font-weight:bold; text-decoration:underline; margin-top:3mm; margin-bottom:1mm; color:#111; }
.au-total-corps { border-left:3px solid #1a4a7a; padding-left:8px; font-size:12px; line-height:1.4; white-space:pre-wrap; }
@media screen { body { margin:36px auto 20px; box-shadow:0 2px 10px rgba(0,0,0,0.15); border:1px solid #ddd; } }
@media print {
    .btn-bar, .ord-nav, .med-liste-wrap, .btn-retirer, .btn-ajouter-spec, .autre-spec, .no-print { display:none !important; }
    .editable { border:none !important; background:transparent !important; padding:0 !important; resize:none !important; overflow:visible !important; height:auto !important; }
    .traitement-retenu li::before { content:"• "; }
    .spec-case input[type=checkbox]:not(:checked) + label { display:none; }
    .spec-case input[type=checkbox]:not(:checked)         { display:none; }
    .spec-case input[type=checkbox]:checked               { display:none; }
    .spec-case input[type=checkbox]:checked + label::before { content:"✓ "; }
}
</style></head><body>

<div class="btn-bar">
    <button class="btn-print" onclick="window.print()">🖨️ Imprimer</button>
    <span><?= htmlspecialchars($nomPatient) ?> — CMLM</span>
    <button class="btn-close" onclick="window.close()">✕ Fermer</button>
</div>

<div class="ligne-date">Tétouan, le <?= $dateAuj ?></div>

<div class="titre-cmlm"><span>Attestation de maladie de longue durée</span></div>

<div class="intro">
    Je soussigné, <strong>Dr Hassan Hlimi</strong>, certifie que <strong><?= htmlspecialchars($nomPatient) ?></strong>
    <?php if ($age): ?>, âgé(e) de <strong><?= $age ?> ans</strong><?php endif; ?>
    <?php if ($ddn): ?>, né(e) le <strong><?= $ddn ?></strong><?php endif; ?>
</div>

<div class="section">
    <div class="section-titre">Souffre d'une affection médicale cardiologique chronique :</div>
    <div class="section-corps"><textarea class="editable" rows="2"><?= $diagRaw ?: '—' ?></textarea></div>
</div>

<?php if ($atcd): ?>
<div class="section">
    <div class="section-titre">Antécédents :</div>
    <div class="section-corps"><textarea class="editable" rows="2"><?= $atcd ?></textarea></div>
</div>
<?php endif; ?>

<?php if ($fdrTexte): ?>
<div class="section">
    <div class="section-titre">Facteurs de risque :</div>
    <div class="section-corps"><textarea class="editable" rows="1"><?= $fdrTexte ?></textarea></div>
</div>
<?php endif; ?>

<div class="section">
    <div class="section-titre">Bilan clinique :</div>
    <div class="section-corps">
        <div class="sous-label">Examen clinique :</div>
        <textarea class="editable" rows="2"><?= $texteExamen ?: '—' ?></textarea>
        <div class="sous-label">Examen ECG :</div>
        <textarea class="editable" rows="2"><?= $texteECG ?: '—' ?></textarea>
        <div class="sous-label">Examen Écho-Doppler :</div>
        <textarea class="editable" rows="2"><?= $texteEcho ?: '—' ?></textarea>
        <div class="sous-label">Examen biologique :</div>
        <textarea class="editable" rows="2"><?= $bioTexte ?: '—' ?></textarea>
    </div>
</div>

<div class="section">
    <div class="section-titre">Son état nécessite un traitement médical au long cours, actuellement sous :</div>
    <div class="section-corps">
        <?php if (!empty($ordsAvecMeds)): ?>
        <div class="no-print" style="margin-bottom:6px;">
            <div class="ord-nav">
                <span style="font-size:11px;font-weight:bold;color:#555;">Ordonnance :</span>
                <?php foreach ($ordsAvecMeds as $i => $ord): ?>
                <button class="ord-nav-btn" onclick="afficherOrd(<?= $i ?>)" id="btn_ord_<?= $i ?>" style="<?= $i===0?'background:#c0392b;':'' ?>"><?= htmlspecialchars($ord['date_fr']) ?></button>
                <?php endforeach; ?>
            </div>
            <?php foreach ($ordsAvecMeds as $i => $ord): ?>
            <div id="ord_panel_<?= $i ?>" class="med-liste-wrap" style="<?= $i>0?'display:none;':'' ?>border:1px solid #dde;border-radius:4px;padding:4px 8px;margin-bottom:6px;">
                <ul class="med-liste">
                <?php foreach ($ord['medicaments'] as $m): ?>
                <li class="med-ligne">
                    <span><?= htmlspecialchars($m['nom_produit'] ?? '') ?><?php if (!empty($m['posologie'])): ?> <span style="color:#888;font-size:11px;">— <?= htmlspecialchars($m['posologie']) ?></span><?php endif; ?></span>
                    <button class="btn-ald" onclick="ajouterALD('<?= addslashes(htmlspecialchars($m['nom_produit'] ?? '')) ?>','<?= addslashes(htmlspecialchars($m['posologie'] ?? '')) ?>',this)">ALD</button>
                </li>
                <?php endforeach; ?>
                </ul>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div style="font-size:11px;color:#1a4a7a;font-weight:bold;margin-bottom:3px;">Traitement retenu :</div>
        <ul class="traitement-retenu" id="liste_ald">
            <li id="ald_vide" style="color:#aaa;font-size:11px;font-style:italic;">Aucun médicament ajouté — cliquez sur [ALD]</li>
        </ul>
    </div>
</div>

<div class="au-total-titre">Au total — Conduite à tenir :</div>
<div class="au-total-corps"><textarea class="editable" rows="3"><?= $conduiteATenir ?: '—' ?></textarea></div>

<div class="attestation">Attestation délivrée à l'intéressé(e) pour usage administratif.</div>

<script>
const nbOrds = <?= count($ordsAvecMeds) ?>;
function afficherOrd(idx) {
    for (let i=0;i<nbOrds;i++) {
        const p=document.getElementById('ord_panel_'+i), b=document.getElementById('btn_ord_'+i);
        if(p) p.style.display=(i===idx)?'block':'none';
        if(b) b.style.background=(i===idx)?'#c0392b':'#1a4a7a';
    }
}
const aldAjoutes = new Set();
function ajouterALD(produit, posologie, btn) {
    if(aldAjoutes.has(produit)) return;
    aldAjoutes.add(produit);
    document.querySelectorAll('.btn-ald').forEach(b => { if(b.onclick&&b.onclick.toString().includes(produit.replace(/'/g,"\\'"))) { b.classList.add('deja'); b.textContent='✓'; } });
    const vide=document.getElementById('ald_vide'); if(vide) vide.remove();
    const li=document.createElement('li');
    li.innerHTML=`<button class="btn-retirer" onclick="retirerALD('${produit.replace(/'/g,"\\'")}',this.parentElement)">✕</button><span>${produit+(posologie?' — '+posologie:'')}</span>`;
    li.dataset.cle=produit;
    document.getElementById('liste_ald').appendChild(li);
}
function retirerALD(cle,li) {
    aldAjoutes.delete(cle);
    document.querySelectorAll('.btn-ald').forEach(b=>{ if(b.onclick&&b.onclick.toString().includes(cle.replace(/'/g,"\\'"))) { b.classList.remove('deja'); b.textContent='ALD'; } });
    li.remove();
    if(!document.getElementById('liste_ald').children.length) {
        const v=document.createElement('li'); v.id='ald_vide'; v.style.cssText='color:#aaa;font-size:11px;font-style:italic;'; v.textContent='Aucun médicament ajouté — cliquez sur [ALD]';
        document.getElementById('liste_ald').appendChild(v);
    }
}
document.querySelectorAll('textarea.editable').forEach(t=>{ t.style.height='auto'; t.style.height=t.scrollHeight+'px'; });
window.addEventListener('beforeprint',()=>document.querySelectorAll('textarea.editable').forEach(t=>{ t.style.height='auto'; t.style.height=t.scrollHeight+'px'; }));
window.addEventListener('afterprint',()=>window.close());
</script>
</body></html>
