<?php
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
if ($id == 0) { die("❌ Patient introuvable."); }

// ── Patient ──────────────────────────────────────────────────────────────────
$stmt = $db->prepare("SELECT * FROM ID WHERE [N°PAT] = ?");
$stmt->execute([$id]);
$patient = $stmt->fetch();
if (!$patient) { die("❌ Patient introuvable."); }

$nomPatient = strtoupper(trim($patient['NOMPRENOM'] ?? ''));
$ddn = '';
if (!empty($patient['DDN'])) {
    $ts = strtotime($patient['DDN']);
    if ($ts && $ts > 86400) $ddn = date('d/m/Y', $ts);
}
$motif = htmlspecialchars($patient['MOTIF CONSULTATION'] ?? '');
$atcd  = htmlspecialchars($patient['ATCD'] ?? '');

// ── FDR depuis patient_fdr ─────────────────────────────────────────────────
$stmtFDR = $db->prepare("SELECT FDR FROM patient_fdr WHERE id = ? ORDER BY N");
$stmtFDR->execute([$id]);
$fdrListe = $stmtFDR->fetchAll(PDO::FETCH_COLUMN);

// ── Dernier examen clinique ────────────────────────────────────────────────
$stmtEx = $db->prepare("SELECT TOP 1 * FROM t_examen WHERE NPAT = ? ORDER BY DateExam DESC, N1 DESC");
$stmtEx->execute([$id]);
$examen = $stmtEx->fetch();

// Concaténation de l'examen (champs non vides, séparés par " — ")
function concat_champs(array $vals): string {
    $parts = array_filter($vals, fn($v) => trim((string)$v) !== '');
    return implode("\n", $parts);
}

$texteExamen = '';
if ($examen) {
    $texteExamen = concat_champs([
        $examen['S_Fonctionnels']     ?? '',
        $examen['Auscult_Cardiaque']  ?? '',
        $examen['Auscult_Pulmonaire'] ?? '',
        $examen['Examen_Vasculaire']  ?? '',
        (!empty($examen['Signes_IVG']) && $examen['Signes_IVG'] !== 'Absents')
            ? 'Signes IVG : ' . $examen['Signes_IVG'] : '',
        (!empty($examen['Signes_IVD']) && $examen['Signes_IVD'] !== 'Absents')
            ? 'Signes IVD : ' . $examen['Signes_IVD'] : '',
        $examen['Autres_Symptomes']   ?? '',
    ]);
}
$conduiteATenir = htmlspecialchars($examen['Conduite_ATenir'] ?? '');

// ── Dernier ECG ───────────────────────────────────────────────────────────
$stmtECG = $db->prepare("SELECT TOP 1 * FROM ecg WHERE [N-PAT] = ? ORDER BY [Date ECG] DESC");
$stmtECG->execute([$id]);
$ecg = $stmtECG->fetch();

$texteECG = '';
if ($ecg) {
    $freq = $ecg['FREQUENCE'] ?? '';
    $parties = [];

    // Rythme
    $rythme = '';
    if (!empty($ecg['RYTHME SUPRA VENTRICULAIRE'])) $rythme .= 'rythme : ' . $ecg['RYTHME SUPRA VENTRICULAIRE'];
    if (!empty($ecg['trouble de rythme']))           $rythme .= ', rythme ventriculaire : ' . $ecg['trouble de rythme'];
    if ($freq)                                        $rythme .= ($rythme ? ', ' : '') . 'fréquence cardiaque : ' . $freq . ' bat/min';
    if ($rythme) $parties[] = '-' . $rythme;

    // Conduction
    $cond = '';
    if (!empty($ecg['LA CONDUCTION NODALE']))    $cond .= 'conduction auriculo-ventriculaire : ' . $ecg['LA CONDUCTION NODALE'];
    if (!empty($ecg['QRS']))                     $cond .= ($cond ? ', QRS : ' : 'QRS : ') . $ecg['QRS'];
    if (!empty($ecg['LA CONDUCTION INFRANODALE'])) $cond .= ', conduction intra-ventriculaire : ' . $ecg['LA CONDUCTION INFRANODALE'];
    if ($cond) $parties[] = '-' . $cond;

    // Repolarisation
    $repol = '';
    if (!empty($ecg['LA REPOLARISATION'])) $repol .= 'Repolarisation : ' . $ecg['LA REPOLARISATION'];
    if (!empty($ecg['SEGMENT ST'])) {
        $st = $ecg['SEGMENT ST'];
        if (!empty($ecg['TOPOGRAPHIE_ST'])) $st .= ' (' . $ecg['TOPOGRAPHIE_ST'] . ')';
        $repol .= ($repol ? ', segment ST : ' : 'segment ST : ') . $st;
    }
    if (!empty($ecg['ONDE_T'])) {
        $t = $ecg['ONDE_T'];
        if (!empty($ecg['TOPOGRAPHIE_T'])) $t .= ' (' . $ecg['TOPOGRAPHIE_T'] . ')';
        $repol .= ($repol ? ', onde T : ' : 'onde T : ') . $t;
    }
    if ($repol) $parties[] = '-' . $repol;

    // IDM
    if (!empty($ecg['IDM']) && $ecg['IDM'] !== 'absents') {
        $q = 'Signes d\'infarctus : ' . $ecg['IDM'];
        if (!empty($ecg['TOPOGRAPHIE_Q'])) $q .= ' (' . $ecg['TOPOGRAPHIE_Q'] . ')';
        $parties[] = '-' . $q;
    }

    // C/C et Autres
    if (!empty($ecg['C/C']))              $parties[] = $ecg['C/C'];
    if (!empty($ecg['AUTRES Signes ECG'])) $parties[] = $ecg['AUTRES Signes ECG'];

    $texteECG = implode("\n", $parties);
}

// ── Dernier Echo ──────────────────────────────────────────────────────────
$stmtEcho = $db->prepare("SELECT TOP 1 * FROM echo WHERE [N-PAT] = ? ORDER BY DATEchog DESC");
$stmtEcho->execute([$id]);
$echo = $stmtEcho->fetch();

$texteEcho     = '';
$titreEcho     = 'ECHO-DOPPLER CARDIAQUE';
if ($echo) {
    // Titre selon TYPE_ECHO (si le champ existe)
    if (!empty($echo['TYPE_ECHO'])) {
        $titreEcho = strtoupper($echo['TYPE_ECHO']);
    }
    $texteEcho = htmlspecialchars(trim($echo['CONCLUSION1'] ?? ''));
}

// ── Date du jour en français ──────────────────────────────────────────────
$moisFr = ['','janvier','février','mars','avril','mai','juin',
            'juillet','août','septembre','octobre','novembre','décembre'];
$dateAuj = date('j') . ' ' . $moisFr[(int)date('n')] . ' ' . date('Y');
$dateAujNum = date('d/m/Y');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Rapport cardio-vasculaire — <?= htmlspecialchars($nomPatient) ?></title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }

@page {
    size: A4;
    margin: 0;
}

body {
    font-family: Arial, sans-serif;
    font-size: 12px;
    color: #111;
    background: white;
    width: 210mm;
    min-height: 297mm;
    padding-top:    4cm;    /* réservé à l'en-tête physique imprimé */
    padding-bottom: 2cm;
    padding-left:   1.5cm;
    padding-right:  1.5cm;
    position: relative;
}

/* ── Bouton imprimer (écran uniquement) ── */
.btn-print-bar {
    position: fixed;
    top: 0; left: 0; right: 0;
    background: #1a4a7a;
    color: white;
    padding: 6px 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    z-index: 999;
    font-size: 12px;
}
.btn-print {
    background: #27ae60; color: white;
    border: none; border-radius: 4px;
    padding: 5px 16px; font-size: 12px;
    font-weight: bold; cursor: pointer;
}
.btn-print:hover { background: #1e8449; }
.btn-close {
    background: #e74c3c; color: white;
    border: none; border-radius: 4px;
    padding: 5px 12px; font-size: 12px;
    cursor: pointer; margin-left: auto;
}

/* ── Date + ville ── */
.ligne-date {
    display: flex;
    justify-content: flex-end;
    gap: 40px;
    font-size: 12px;
    margin-bottom: 10mm;
}

/* ── Titre encadré rouge ── */
.titre-rapport {
    border: 2px solid #cc0000;
    padding: 5px 12px;
    margin-bottom: 8mm;
    text-align: center;
}
.titre-rapport span {
    font-size: 14px;
    font-weight: bold;
    color: #cc0000;
    letter-spacing: 0.5px;
}

/* ── Bandeau patient ── */
.bandeau-patient {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    border-left: 4px solid #1a4a7a;
    padding-left: 8px;
    margin-bottom: 6mm;
}
.bandeau-patient .nom {
    font-size: 14px;
    font-weight: bold;
    text-transform: uppercase;
}
.bandeau-patient .ddn {
    font-size: 12px;
}

/* ── Sections ── */
.section {
    margin-bottom: 5mm;
}
.section-label {
    font-size: 11px;
    text-decoration: underline;
    font-weight: normal;
    margin-bottom: 2mm;
    color: #111;
}
.section-corps {
    border-left: 3px solid #ccc;
    padding-left: 8px;
    font-size: 12px;
    line-height: 1.6;
    white-space: pre-wrap;
    word-wrap: break-word;
}
.section-inline {
    display: flex;
    gap: 20mm;
    margin-bottom: 5mm;
}
.section-inline .col {
    flex: 1;
}

/* ── Titres de sections majeurs ── */
.titre-section {
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
    text-decoration: underline;
    margin-bottom: 3mm;
    margin-top: 4mm;
    color: #111;
}

/* ── Au total ── */
.au-total-label {
    font-size: 12px;
    text-decoration: underline;
    margin-top: 5mm;
    margin-bottom: 2mm;
    color: #111;
}
.au-total-corps {
    border-left: 3px solid #1a4a7a;
    padding-left: 8px;
    font-size: 12px;
    line-height: 1.7;
    white-space: pre-wrap;
    word-wrap: break-word;
    min-height: 20mm;
}

/* ── FDR badges ── */
.fdr-liste {
    border-left: 3px solid #ccc;
    padding-left: 8px;
    font-size: 12px;
    line-height: 1.7;
}

@media screen {
    body {
        margin: 36px auto 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.15);
        border: 1px solid #ddd;
    }
}
@media print {
    .btn-print-bar { display: none !important; }
    body { margin: 0; }
}
</style>
</head>
<body>

<!-- ── Barre bouton imprimer (visible uniquement à l'écran) ── -->
<div class="btn-print-bar">
    <button class="btn-print" onclick="window.print()">🖨️ Imprimer</button>
    <span><?= htmlspecialchars($nomPatient) ?> — N° <?= $id ?></span>
    <button class="btn-close" onclick="window.close()">✕ Fermer</button>
</div>

<!-- ── Date + ville ── -->
<div class="ligne-date">
    <span>Tétouan le :</span>
    <span><?= $dateAujNum ?></span>
</div>

<!-- ── Titre encadré rouge ── -->
<div class="titre-rapport">
    <span>Compte rendu de l'examen cardio-vasculaire</span>
</div>

<!-- ── Bandeau patient ── -->
<div class="bandeau-patient">
    <span class="nom"><?= htmlspecialchars($nomPatient) ?></span>
    <span class="ddn">DDN : &nbsp;&nbsp;&nbsp; <?= $ddn ?: '—' ?></span>
</div>

<!-- ── Motif + Antécédents (côte à côte) ── -->
<div class="section-inline">
    <div class="col">
        <div class="section-label">Motif de consultation :</div>
        <div class="section-corps"><?= $motif ?: '—' ?></div>
    </div>
    <div class="col">
        <div class="section-label">Antécédents :</div>
        <div class="section-corps"><?= $atcd ?: '—' ?></div>
    </div>
</div>

<!-- ── FDR ── -->
<div class="section">
    <div class="section-label">FDR :</div>
    <div class="fdr-liste">
        <?php if (!empty($fdrListe)):
            $chunks = array_chunk($fdrListe, 3);
            foreach ($chunks as $chunk): ?>
        - <?= implode('- ', array_map('htmlspecialchars', $chunk)) ?><br>
        <?php endforeach;
        else: ?>
        —
        <?php endif; ?>
    </div>
</div>

<!-- ── L'Examen ── -->
<div class="section">
    <div class="section-label">L'examen :</div>
    <div class="section-corps"><?= $texteExamen ? htmlspecialchars($texteExamen) : '—' ?></div>
</div>

<!-- ── ECG ── -->
<div class="titre-section">Electro Cardiogramme</div>
<div class="section-corps" style="margin-bottom:5mm;"><?= $texteECG ? htmlspecialchars($texteECG) : '—' ?></div>

<!-- ── Echo-Doppler ── -->
<div class="titre-section"><?= htmlspecialchars($titreEcho) ?></div>
<div class="section-corps" style="margin-bottom:5mm;"><?= $texteEcho ?: '—' ?></div>

<!-- ── Au total ── -->
<div class="au-total-label">Au total :</div>
<div class="au-total-corps"><?= $conduiteATenir ?: '' ?></div>

<script>
window.addEventListener('afterprint', function() {
    window.close();
});
</script>
</body>
</html>
