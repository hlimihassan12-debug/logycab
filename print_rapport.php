<?php
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
if ($id == 0) die("❌ Patient introuvable.");

$date_ex   = preg_replace('/[^0-9]/', '', $_GET['date_ex']   ?? '');
$date_ecg  = preg_replace('/[^0-9]/', '', $_GET['date_ecg']  ?? '');
$date_echo = preg_replace('/[^0-9]/', '', $_GET['date_echo'] ?? '');
$excl_examen = !empty($_GET['excl_examen']);
$excl_ecg    = !empty($_GET['excl_ecg']);
$excl_echo   = !empty($_GET['excl_echo']);

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
$motif    = trim($patient['MOTIF CONSULTATION'] ?? '');
$atcd     = trim($patient['ATCD'] ?? '');
$diagRaw  = trim($patient['diagnostic'] ?? '');
$fdrTexte = trim($patient['CHAMP_FDR'] ?? '');

// ── Mise en ligne : transforme un texte multi-lignes ("- item1\n- item2") en une seule ligne ("item1 ; item2")
// Même fonction que print_lettre.php et print_cmlm.php — pas de puces, un paragraphe qui coule.
function enLigne($texte) {
    $lignes = preg_split('/\r\n|\r|\n/', trim((string)$texte));
    $out = [];
    foreach ($lignes as $l) {
        $l = trim($l);
        if ($l === '') continue;
        if (strpos($l, '- ') === 0) $l = substr($l, 2);
        $out[] = $l;
    }
    return implode(' ; ', $out);
}

$examen = null;
if (!$excl_examen) {
    if ($date_ex) { $s = $db->prepare("SELECT TOP 1 * FROM t_examen WHERE NPAT = ? AND CONVERT(varchar(8), DateExam, 112) = ? ORDER BY N1 DESC"); $s->execute([$id, $date_ex]); }
    else          { $s = $db->prepare("SELECT TOP 1 * FROM t_examen WHERE NPAT = ? ORDER BY DateExam DESC, N1 DESC"); $s->execute([$id]); }
    $examen = $s->fetch();
}
$texteExamen    = $examen ? trim($examen['CMLM_EXAMEN'] ?? '') : '';
$conduiteATenir = $examen ? trim($examen['Conduite_ATenir'] ?? '') : '';

$ecg = null;
if (!$excl_ecg) {
    if ($date_ecg) { $s = $db->prepare("SELECT TOP 1 * FROM ecg WHERE CAST([N-PAT] AS INT) = ? AND CONVERT(varchar(8), [Date ECG], 112) = ? ORDER BY [N°] DESC"); $s->execute([$id, $date_ecg]); }
    else           { $s = $db->prepare("SELECT TOP 1 * FROM ecg WHERE CAST([N-PAT] AS INT) = ? ORDER BY [Date ECG] DESC, [N°] DESC"); $s->execute([$id]); }
    $ecg = $s->fetch();
}
$texteECG = $ecg ? trim($ecg['CMLM_ECG'] ?? '') : '';

$echo = null;
if (!$excl_echo) {
    if ($date_echo) { $s = $db->prepare("SELECT TOP 1 * FROM echo WHERE [N-PAT] = ? AND CONVERT(varchar(8), DATEchog, 112) = ? ORDER BY DATEchog DESC, [N°] DESC"); $s->execute([$id, $date_echo]); }
    else            { $s = $db->prepare("SELECT TOP 1 * FROM echo WHERE [N-PAT] = ? ORDER BY DATEchog DESC, [N°] DESC"); $s->execute([$id]); }
    $echo = $s->fetch();
}
$texteEcho = $echo ? trim($echo['CMLM_ECHO'] ?? '') : '';

$stmtBio3 = $db->prepare("SELECT TOP 3 CONVERT(varchar(10), date_bilan, 103) AS date_fr, CONVERT(varchar(10), date_bilan, 112) AS date_tri FROM LE_BILAN WHERE id = ? GROUP BY CONVERT(varchar(10), date_bilan, 103), CONVERT(varchar(10), date_bilan, 112) ORDER BY date_tri DESC");
$stmtBio3->execute([$id]);
$datesBio = $stmtBio3->fetchAll();
$bilansRapport = [];
foreach ($datesBio as $d) {
    $stmtIds = $db->prepare("SELECT n_bilan FROM LE_BILAN WHERE id = ? AND CONVERT(varchar(10), date_bilan, 103) = ?");
    $stmtIds->execute([$id, $d['date_fr']]);
    $ids = $stmtIds->fetchAll(PDO::FETCH_COLUMN);
    if (empty($ids)) continue;
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $stmtAn = $db->prepare("SELECT c.analyse AS nom, ISNULL(a.résultat,'') AS resultat FROM analyses a LEFT JOIN C_ANALYSE c ON c.[N°TypeAnalyse] = a.bilan WHERE a.N_bilan IN ($ph) AND ISNULL(a.résultat,'') <> '' AND a.résultat <> 'N' ORDER BY c.rubrique, c.analyse");
    $stmtAn->execute($ids);
    $anormaux = $stmtAn->fetchAll();
    if (!empty($anormaux)) $bilansRapport[] = ['date_fr' => $d['date_fr'], 'anormaux' => $anormaux];
}

$dateAuj = date('d/m/Y');
?>
<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">
<title>Compte rendu CV — <?= htmlspecialchars($nomPatient) ?></title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
@page { size: 147mm 212mm; margin: 0; }
body { font-family:Arial,sans-serif; font-size:12px; color:#111; background:white; width:147mm; padding-top:5.3cm; padding-bottom:1.5cm; padding-left:1.5cm; padding-right:1.5cm; }
.btn-bar { position:fixed; top:0; left:0; right:0; background:#1a4a7a; color:white; padding:6px 20px; display:flex; align-items:center; gap:12px; z-index:999; font-size:12px; }
.btn-print { background:#27ae60; color:white; border:none; border-radius:4px; padding:5px 16px; font-size:12px; font-weight:bold; cursor:pointer; }
.btn-close  { background:#e74c3c; color:white; border:none; border-radius:4px; padding:5px 12px; font-size:12px; cursor:pointer; margin-left:auto; }
.ligne-date { display:flex; justify-content:flex-end; margin-bottom:2mm; font-size:12px; }
.titre-rapport { border:2px solid #cc0000; padding:1px 10px; margin-bottom:1.5mm; text-align:center; }
.titre-rapport span { font-size:12px; font-weight:bold; color:#cc0000; }
.bandeau-patient { display:flex; justify-content:space-between; align-items:baseline; border-left:4px solid #1a4a7a; padding-left:8px; margin-bottom:1.5mm; }
.bandeau-patient .nom { font-size:12px; font-weight:bold; text-transform:uppercase; }
.bloc { margin-top:1mm; }
.bloc-titre { font-size:12px; font-weight:bold; text-decoration:underline; margin-bottom:0.3mm; color:#111; }
.bloc-corps { border-left:3px solid #ccc; padding-left:8px; font-size:12px; line-height:1.15; white-space:pre-wrap; word-wrap:break-word; }
.bio-table { display:table; border-left:3px solid #ccc; padding-left:8px; width:100%; }
.bio-ligne { display:table-row; }
.bio-date   { display:table-cell; white-space:nowrap; padding-right:10px; vertical-align:top; font-size:12px; }
.bio-vals   { display:table-cell; font-size:12px; vertical-align:top; }
.au-total-titre { font-size:12px; font-weight:bold; text-decoration:underline; margin-top:1.5mm; margin-bottom:0.3mm; color:#111; }
.au-total-corps { border-left:3px solid #1a4a7a; padding-left:8px; font-size:12px; line-height:1.15; white-space:pre-wrap; }
@media screen { body { margin:36px auto 20px; box-shadow:0 2px 10px rgba(0,0,0,0.15); border:1px solid #ddd; } }
@media print  { .btn-bar { display:none !important; } body { margin:0; } }
</style></head><body>

<div class="btn-bar">
    <button class="btn-print" onclick="window.print()">🖨️ Imprimer</button>
    <span><?= htmlspecialchars($nomPatient) ?> — Compte rendu CV</span>
    <button class="btn-close" onclick="window.close()">✕ Fermer</button>
</div>

<div class="ligne-date">Tétouan, le <?= $dateAuj ?></div>

<div class="titre-rapport"><span>Compte rendu de l'examen cardio-vasculaire</span></div>

<div class="bandeau-patient">
    <span class="nom"><?= htmlspecialchars($nomPatient) ?></span>
    <span><?php if ($age): ?>Age : <?= $age ?> ans<?php endif; ?><?php if ($ddn): ?> — né(e) le <?= $ddn ?><?php endif; ?></span>
</div>

<div class="bloc" style="margin-top:0;">
    <div class="bloc-titre">Cher confrère, chère consœur,</div>
</div>

<div class="bloc">
    <div class="bloc-titre">Motif de consultation :</div>
    <div class="bloc-corps"><?= htmlspecialchars($motif ? enLigne($motif) : '—') ?></div>
</div>

<div class="bloc">
    <div class="bloc-titre">Antécédents :</div>
    <div class="bloc-corps"><?= htmlspecialchars($atcd ? enLigne($atcd) : '—') ?></div>
</div>

<?php if ($fdrTexte): ?>
<div class="bloc">
    <div class="bloc-titre">Facteurs de risque :</div>
    <div class="bloc-corps"><?= htmlspecialchars(enLigne($fdrTexte)) ?></div>
</div>
<?php endif; ?>

<div class="bloc">
    <div class="bloc-titre">Diagnostic :</div>
    <div class="bloc-corps"><?= htmlspecialchars($diagRaw ? enLigne($diagRaw) : '—') ?></div>
</div>

<div class="bloc">
    <div class="bloc-titre">Examen clinique :</div>
    <div class="bloc-corps"><?= htmlspecialchars($texteExamen ? enLigne($texteExamen) : '—') ?></div>
</div>

<div class="bloc">
    <div class="bloc-titre">Examen ECG :</div>
    <div class="bloc-corps"><?= htmlspecialchars($texteECG ? enLigne($texteECG) : '—') ?></div>
</div>

<div class="bloc">
    <div class="bloc-titre">Examen Écho-Doppler :</div>
    <div class="bloc-corps"><?= htmlspecialchars($texteEcho ? enLigne($texteEcho) : '—') ?></div>
</div>

<?php if (!empty($bilansRapport)): ?>
<div class="bloc">
    <div class="bloc-titre">Examen biologique :</div>
    <div class="bio-table">
    <?php foreach ($bilansRapport as $bilan):
        $parties = [];
        foreach ($bilan['anormaux'] as $bl) $parties[] = htmlspecialchars($bl['nom']).' <strong>'.htmlspecialchars($bl['resultat']).'</strong>';
    ?>
        <div class="bio-ligne">
            <span class="bio-date"><?= htmlspecialchars($bilan['date_fr']) ?> :</span>
            <span class="bio-vals"><?= implode(' · ', $parties) ?></span>
        </div>
    <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="au-total-titre">Au total — Conduite à tenir :</div>
<div class="au-total-corps"><?= htmlspecialchars($conduiteATenir ? enLigne($conduiteATenir) : 'Traitement prescrit et RDV fixé') ?></div>

<script>window.addEventListener('afterprint', function(){ window.close(); });</script>
</body></html>
