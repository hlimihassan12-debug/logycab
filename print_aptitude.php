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

$dateAuj = date('d/m/Y');
?>
<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">
<title>Aptitude — <?= htmlspecialchars($nomPatient) ?></title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
@page { size: B5; margin: 0; }
body { font-family:Arial,sans-serif; font-size:12px; color:#111; background:white; width:176mm; min-height:250mm; padding-top:3.5cm; padding-bottom:2cm; padding-left:1.5cm; padding-right:1.5cm; }
.btn-bar { position:fixed; top:0; left:0; right:0; background:#27ae60; color:white; padding:6px 20px; display:flex; align-items:center; gap:12px; z-index:999; }
.btn-print { background:white; color:#27ae60; border:none; border-radius:4px; padding:5px 16px; font-size:12px; font-weight:bold; cursor:pointer; }
.btn-close  { background:#e74c3c; color:white; border:none; border-radius:4px; padding:5px 12px; font-size:12px; cursor:pointer; margin-left:auto; }
.ligne-date { text-align:right; margin-bottom:6mm; font-size:12px; }
.titre-apt { border:2px solid #27ae60; padding:5px 12px; margin-bottom:5mm; text-align:center; }
.titre-apt span { font-size:14px; font-weight:bold; color:#27ae60; }
.intro { font-size:12px; line-height:1.6; margin-bottom:4mm; }
.section { margin-top:3mm; }
.section-titre { font-size:12px; font-weight:bold; text-decoration:underline; color:#111; margin-bottom:1mm; }
.section-corps { border-left:3px solid #ccc; padding-left:8px; font-size:12px; line-height:1.4; white-space:pre-wrap; word-wrap:break-word; }
.editable { width:100%; border:1px dashed #aaa; border-radius:3px; padding:4px 6px; font-size:12px; font-family:Arial,sans-serif; line-height:1.4; resize:vertical; background:#fafeff; color:#111; }
.editable:focus { outline:none; border-color:#27ae60; background:#f0fff4; }
.au-total-titre { font-size:12px; font-weight:bold; text-decoration:underline; margin-top:4mm; margin-bottom:1mm; color:#111; }
.au-total-corps { border-left:3px solid #27ae60; padding-left:8px; font-size:12px; line-height:1.4; white-space:pre-wrap; }
.signature { margin-top:10mm; font-size:12px; font-weight:bold; }
@media screen { body { margin:36px auto 20px; box-shadow:0 2px 10px rgba(0,0,0,0.15); border:1px solid #ddd; } }
@media print {
    .btn-bar { display:none !important; } body { margin:0; }
    .editable { border:none !important; background:transparent !important; padding:0 !important; resize:none !important; overflow:visible !important; height:auto !important; }
}
</style></head><body>

<div class="btn-bar">
    <button class="btn-print" onclick="window.print()">🖨️ Imprimer</button>
    <span>🏅 Aptitude — <?= htmlspecialchars($nomPatient) ?></span>
    <button class="btn-close" onclick="window.close()">✕ Fermer</button>
</div>

<div class="ligne-date">Tétouan, le <?= $dateAuj ?></div>

<div class="titre-apt"><span>Certificat médical d'aptitude physique</span></div>

<div class="intro">
    Je soussigné, <strong>Dr Hassan Hlimi</strong>, Cardiologue à Tétouan, certifie avoir examiné ce jour :<br>
    <strong><?= htmlspecialchars($nomPatient) ?></strong>
    <?php if ($age): ?>, âgé(e) de <strong><?= $age ?> ans</strong><?php endif; ?>
    <?php if ($ddn): ?>, né(e) le <strong><?= $ddn ?></strong><?php endif; ?>
</div>

<div class="section">
    <div class="section-titre">Examen clinique :</div>
    <div class="section-corps"><textarea class="editable" rows="2"><?= $texteExamen ?: '—' ?></textarea></div>
</div>

<div class="section">
    <div class="section-titre">Examen ECG :</div>
    <div class="section-corps"><textarea class="editable" rows="2"><?= $texteECG ?: '—' ?></textarea></div>
</div>

<div class="section">
    <div class="section-titre">Examen Écho-Doppler :</div>
    <div class="section-corps"><textarea class="editable" rows="2"><?= $texteEcho ?: '—' ?></textarea></div>
</div>

<div class="au-total-titre">Au total — Conduite à tenir :</div>
<div class="au-total-corps"><textarea class="editable" rows="2"><?= $conduiteATenir ?: '—' ?></textarea></div>

<div class="signature">
    Dr Hassan Hlimi<br>
    <span style="font-weight:normal;font-size:11px;">Cardiologue — Tétouan</span>
</div>

<script>
document.querySelectorAll('textarea.editable').forEach(t=>{ t.style.height='auto'; t.style.height=t.scrollHeight+'px'; });
window.addEventListener('beforeprint',()=>document.querySelectorAll('textarea.editable').forEach(t=>{ t.style.height='auto'; t.style.height=t.scrollHeight+'px'; }));
window.addEventListener('afterprint',()=>window.close());
</script>
</body></html>
