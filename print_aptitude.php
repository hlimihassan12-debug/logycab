<?php
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
if ($id == 0) { die("❌ Patient introuvable."); }

// ── Patient ───────────────────────────────────────────────────────────────
$stmt = $db->prepare("SELECT * FROM ID WHERE [N°PAT] = ?");
$stmt->execute([$id]);
$patient = $stmt->fetch();
if (!$patient) { die("❌ Patient introuvable."); }

$nomPatient = strtoupper(trim($patient['NOMPRENOM'] ?? ''));
$ddn = '';
$age = '';
if (!empty($patient['DDN'])) {
    $ts = strtotime($patient['DDN']);
    if ($ts && $ts > 86400) {
        $ddn = date('d/m/Y', $ts);
        $age = (int)((time() - $ts) / 31557600);
    }
}

// ── Dernier examen clinique ───────────────────────────────────────────────
$stmtEx = $db->prepare("SELECT TOP 1 * FROM t_examen WHERE NPAT = ? ORDER BY DateExam DESC, N1 DESC");
$stmtEx->execute([$id]);
$examen = $stmtEx->fetch();
$texteExamen   = $examen ? htmlspecialchars(trim($examen['CMLM_EXAMEN'] ?? '')) : '';
$conduiteATenir = $examen ? htmlspecialchars(trim($examen['Conduite_ATenir'] ?? '')) : '';

// ── Dernier ECG ───────────────────────────────────────────────────────────
$stmtECG = $db->prepare("SELECT TOP 1 * FROM ecg WHERE CAST([N-PAT] AS INT) = ? ORDER BY [Date ECG] DESC, [N°] DESC");
$stmtECG->execute([$id]);
$ecg = $stmtECG->fetch();
$texteECG = $ecg ? htmlspecialchars(trim($ecg['CMLM_ECG'] ?? '')) : '';

// ── Dernier Echo ──────────────────────────────────────────────────────────
$stmtEcho = $db->prepare("SELECT TOP 1 * FROM echo WHERE [N-PAT] = ? ORDER BY DATEchog DESC");
$stmtEcho->execute([$id]);
$echo = $stmtEcho->fetch();
$texteEcho = $echo ? htmlspecialchars(trim($echo['CMLM_ECHO'] ?? '')) : '';

// ── Date du jour en français ──────────────────────────────────────────────
$moisFr = ['','janvier','février','mars','avril','mai','juin',
            'juillet','août','septembre','octobre','novembre','décembre'];
$dateAuj = date('j') . ' ' . $moisFr[(int)date('n')] . ' ' . date('Y');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Certificat d'aptitude — <?= htmlspecialchars($nomPatient) ?></title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
@page { size: B5; margin: 0; }

body {
    font-family: Arial, sans-serif;
    font-size: 12px;
    color: #111;
    background: white;
    width: 176mm;
    min-height: 250mm;
    padding-top: 2.5cm;
    padding-bottom: 1.2cm;
    padding-left: 1.5cm;
    padding-right: 1.5cm;
}

/* ── Barre boutons ── */
.btn-bar {
    position: fixed; top:0; left:0; right:0;
    background: #1a7a3a; color: white;
    padding: 6px 20px;
    display: flex; align-items: center; gap: 12px;
    z-index: 999; font-size: 12px;
}
.btn-print { background:#27ae60; color:white; border:none; border-radius:4px; padding:5px 16px; font-size:12px; font-weight:bold; cursor:pointer; }
.btn-print:hover { background:#1e8449; }
.btn-close { background:#e74c3c; color:white; border:none; border-radius:4px; padding:5px 12px; font-size:12px; cursor:pointer; margin-left:auto; }

/* ── Date ── */
.ligne-date { display:flex; justify-content:flex-end; margin-bottom:4mm; font-size:12px; }

/* ── Titre ── */
.titre-apt { border:2px solid #1a4a7a; padding:3px 12px; margin-bottom:4mm; text-align:center; }
.titre-apt span { font-size:14px; font-weight:bold; color:#1a4a7a; letter-spacing:0.5px; }

/* ── Intro ── */
.intro { font-size:12px; line-height:1.8; margin-bottom:3mm; }

/* ── Section ── */
.section { margin-top:3mm; }
.section-titre {
    font-size:12px; font-weight:bold; text-decoration:underline;
    color:#1a4a7a; margin-bottom:1mm; line-height:1.2;
}
.section-corps {
    border-left:3px solid #ccc; padding-left:8px;
    font-size:12px; line-height:1.3;
}
.sous-label { font-size:11px; color:#555; margin-bottom:1px; margin-top:4px; }

/* ── Textarea éditable ── */
.editable {
    width:100%; border:1px dashed #aaa; border-radius:3px;
    padding:4px 6px; font-size:12px; font-family:Arial,sans-serif;
    line-height:1.4; resize:vertical; background:#fafeff; color:#111;
}
.editable:focus { outline:none; border-color:#2e6da4; background:#f0f7ff; }

/* ── Signature ── */
.signature-bloc {
    margin-top:8mm;
    text-align:right;
    font-size:12px;
    font-weight:bold;
}

/* ── IMPRESSION ── */
@media screen {
    body { margin: 36px auto 20px; box-shadow:0 2px 10px rgba(0,0,0,0.15); border:1px solid #ddd; }
}
@media print {
    .btn-bar { display:none !important; }
    .editable {
        border:none !important; background:transparent !important;
        padding:0 !important; resize:none !important;
        overflow:visible !important; height:auto !important; min-height:0 !important;
    }
}
</style>
</head>
<body>

<!-- ── Barre boutons ── -->
<div class="btn-bar">
    <button class="btn-print" onclick="window.print()">🖨️ Imprimer</button>
    <span><?= htmlspecialchars($nomPatient) ?> — Certificat d'aptitude</span>
    <button class="btn-close" onclick="window.close()">✕ Fermer</button>
</div>

<!-- ── Date ── -->
<div class="ligne-date">Tétouan, le <?= $dateAuj ?></div>

<!-- ── Titre ── -->
<div class="titre-apt">
    <span>Certificat médical d'aptitude physique</span>
</div>

<!-- ── Intro ── -->
<div class="intro">
    Je soussigné, <strong>Dr Hassan Hlimi</strong>, certifie avoir examiné<br>
    <strong><?= htmlspecialchars($nomPatient) ?></strong><?php if ($age): ?>, âgé(e) de <strong><?= $age ?> ans</strong><?php endif; ?><?php if ($ddn): ?>, né(e) le <strong><?= $ddn ?></strong><?php endif; ?>
</div>

<!-- ── Examen clinique ── -->
<div class="section">
    <div class="section-titre">Examen clinique :</div>
    <div class="section-corps">
        <textarea class="editable" rows="2"><?= $texteExamen ?: '—' ?></textarea>
    </div>
</div>

<!-- ── ECG ── -->
<div class="section">
    <div class="section-titre">Électrocardiogramme :</div>
    <div class="section-corps">
        <textarea class="editable" rows="2"><?= $texteECG ?: '—' ?></textarea>
    </div>
</div>

<!-- ── Echo ── -->
<div class="section">
    <div class="section-titre">Échographie cardiaque :</div>
    <div class="section-corps">
        <textarea class="editable" rows="2"><?= $texteEcho ?: '—' ?></textarea>
    </div>
</div>

<!-- ── Au total ── -->
<div class="section">
    <div class="section-titre">Au total — Conduite à tenir :</div>
    <div class="section-corps">
        <textarea class="editable" rows="3"><?= $conduiteATenir ?: '' ?></textarea>
    </div>
</div>

<!-- ── Signature ── -->
<div class="signature-bloc">
    Dr Hassan Hlimi<br>
    <span style="font-weight:normal;font-size:11px;">Cardiologue — Tétouan</span>
</div>

<script>
/* ── Auto-hauteur textarea avant impression ── */
function ajusterHauteurTextareas() {
    document.querySelectorAll('textarea.editable').forEach(function(ta) {
        ta.style.height = 'auto';
        ta.style.height = ta.scrollHeight + 'px';
    });
}
ajusterHauteurTextareas();
window.addEventListener('beforeprint', ajusterHauteurTextareas);
window.addEventListener('afterprint', function() { window.close(); });
</script>
</body>
</html>
