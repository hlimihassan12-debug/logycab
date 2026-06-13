<?php
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
if ($id == 0) { die("❌ Patient introuvable."); }

// ── Paramètres dates / exclusions passés par la modale ────────────────────
$date_ex   = preg_replace('/[^0-9]/', '', $_GET['date_ex']   ?? '');
$date_ecg  = preg_replace('/[^0-9]/', '', $_GET['date_ecg']  ?? '');
$date_echo = preg_replace('/[^0-9]/', '', $_GET['date_echo'] ?? '');
$excl_examen = !empty($_GET['excl_examen']);
$excl_ecg    = !empty($_GET['excl_ecg']);
$excl_echo   = !empty($_GET['excl_echo']);



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

// ── Dernier examen clinique (ou date choisie) ────────────────────────────
$examen = null;
if (!$excl_examen) {
    if ($date_ex) {
        $stmtEx = $db->prepare("SELECT TOP 1 * FROM t_examen WHERE NPAT = ? AND CONVERT(varchar(8), DateExam, 112) = ? ORDER BY N1 DESC");
        $stmtEx->execute([$id, $date_ex]);
    } else {
        $stmtEx = $db->prepare("SELECT TOP 1 * FROM t_examen WHERE NPAT = ? ORDER BY DateExam DESC, N1 DESC");
        $stmtEx->execute([$id]);
    }
    $examen = $stmtEx->fetch();
}

function concat_champs(array $vals): string {
    $parts = array_filter($vals, fn($v) => trim((string)$v) !== '');
    return implode("\n", $parts);
}

$texteExamen = '';
if ($examen) {
    $texteExamen = trim(concat_champs([
        $examen['S_Fonctionnels']     ?? '',
        $examen['Auscult_Cardiaque']  ?? '',
        $examen['Auscult_Pulmonaire'] ?? '',
        $examen['Examen_Vasculaire']  ?? '',
        (!empty($examen['Signes_IVG']) && trim($examen['Signes_IVG']) !== 'Absents')
            ? 'Signes IVG : ' . $examen['Signes_IVG'] : '',
        (!empty($examen['Signes_IVD']) && trim($examen['Signes_IVD']) !== 'Absents')
            ? 'Signes IVD : ' . $examen['Signes_IVD'] : '',
        $examen['Autres_Symptomes']   ?? '',
        $examen['Conclusion']         ?? '',
        $examen['REMARQUE']           ?? '',
    ]));
}
$conduiteATenir = htmlspecialchars(trim($examen['Conduite_ATenir'] ?? ''));

// ── Dernier ECG (ou date choisie) ────────────────────────────────────────
$ecg = null;
if (!$excl_ecg) {
    if ($date_ecg) {
        $stmtECG = $db->prepare("SELECT TOP 1 * FROM ecg WHERE CAST([N-PAT] AS INT) = ? AND CONVERT(varchar(8), [Date ECG], 112) = ? ORDER BY [N°] DESC");
        $stmtECG->execute([$id, $date_ecg]);
    } else {
        $stmtECG = $db->prepare("SELECT TOP 1 * FROM ecg WHERE CAST([N-PAT] AS INT) = ? ORDER BY [Date ECG] DESC, [N°] DESC");
        $stmtECG->execute([$id]);
    }
    $ecg = $stmtECG->fetch();
}

$texteECG = '';
if ($ecg) {
    $freq = $ecg['FREQUENCE'] ?? '';
    $parties = [];

    $rythme = '';
    if (!empty($ecg['RYTHME SUPRA VENTRICULAIRE'])) $rythme .= 'rythme : ' . $ecg['RYTHME SUPRA VENTRICULAIRE'];
    if (!empty($ecg['trouble de rythme']))           $rythme .= ', rythme ventriculaire : ' . $ecg['trouble de rythme'];
    if ($freq)                                        $rythme .= ($rythme ? ', ' : '') . 'fréquence cardiaque : ' . $freq . ' bat/min';
    if ($rythme) $parties[] = '-' . $rythme;

    $cond = '';
    if (!empty($ecg['LA CONDUCTION NODALE']))      $cond .= 'conduction auriculo-ventriculaire : ' . $ecg['LA CONDUCTION NODALE'];
    if (!empty($ecg['QRS']))                       $cond .= ($cond ? ', QRS : ' : 'QRS : ') . $ecg['QRS'];
    if (!empty($ecg['LA CONDUCTION INFRANODALE'])) $cond .= ', conduction intra-ventriculaire : ' . $ecg['LA CONDUCTION INFRANODALE'];
    if ($cond) $parties[] = '-' . $cond;

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

    if (!empty($ecg['IDM']) && $ecg['IDM'] !== 'absents') {
        $q = 'Signes d\'infarctus : ' . $ecg['IDM'];
        if (!empty($ecg['TOPOGRAPHIE_Q'])) $q .= ' (' . $ecg['TOPOGRAPHIE_Q'] . ')';
        $parties[] = '-' . $q;
    }

    if (!empty($ecg['C/C']))               $parties[] = $ecg['C/C'];
    if (!empty($ecg['AUTRES Signes ECG'])) $parties[] = $ecg['AUTRES Signes ECG'];

    $texteECG = implode("\n", $parties);
}

// ── Dernier Echo (ou date choisie) ───────────────────────────────────────
$echo = null;
if (!$excl_echo) {
    if ($date_echo) {
        $stmtEcho = $db->prepare("SELECT TOP 1 * FROM echo WHERE [N-PAT] = ? AND CONVERT(varchar(8), DATEchog, 112) = ? ORDER BY DATEchog DESC");
        $stmtEcho->execute([$id, $date_echo]);
    } else {
        $stmtEcho = $db->prepare("SELECT TOP 1 * FROM echo WHERE [N-PAT] = ? ORDER BY DATEchog DESC");
        $stmtEcho->execute([$id]);
    }
    $echo = $stmtEcho->fetch();
}

$texteEcho = '';
$titreEcho = 'Echographie cardiaque';
if ($echo) {
    if (!empty($echo['TYPE_ECHO'])) {
        $titreEcho = ucfirst(strtolower($echo['TYPE_ECHO']));
    }
    $texteEcho = htmlspecialchars(trim($echo['CONCLUSION1'] ?? ''));
}

// ── 3 dernières DATES de bilans biologie (groupées par date) ─────────────
$stmtBio3 = $db->prepare("
    SELECT TOP 3 CONVERT(varchar(10), date_bilan, 103) AS date_fr,
                 CONVERT(varchar(10), date_bilan, 112) AS date_tri
    FROM LE_BILAN
    WHERE id = ?
    GROUP BY CONVERT(varchar(10), date_bilan, 103),
             CONVERT(varchar(10), date_bilan, 112)
    ORDER BY date_tri DESC
");
$stmtBio3->execute([$id]);
$dernieresDatesBio = $stmtBio3->fetchAll();

$bilansRapport = [];
foreach ($dernieresDatesBio as $d) {
    $stmtIds = $db->prepare("
        SELECT n_bilan FROM LE_BILAN
        WHERE id = ?
          AND CONVERT(varchar(10), date_bilan, 103) = ?
    ");
    $stmtIds->execute([$id, $d['date_fr']]);
    $ids = $stmtIds->fetchAll(PDO::FETCH_COLUMN);
    if (empty($ids)) continue;

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmtAn = $db->prepare("
        SELECT c.analyse AS nom,
               ISNULL(a.résultat,'') AS resultat
        FROM analyses a
        LEFT JOIN C_ANALYSE c ON c.[N°TypeAnalyse] = a.bilan
        WHERE a.N_bilan IN ($placeholders)
          AND ISNULL(a.résultat,'') <> ''
          AND a.résultat <> 'N'
        ORDER BY c.rubrique, c.analyse
    ");
    $stmtAn->execute($ids);
    $anormaux = $stmtAn->fetchAll();
    if (!empty($anormaux)) {
        $bilansRapport[] = ['date_fr' => $d['date_fr'], 'anormaux' => $anormaux];
    }
}

// ── Date du jour ──────────────────────────────────────────────────────────
$dateAujNum = date('d/m/Y');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Rapport cardio-vasculaire — <?= htmlspecialchars($nomPatient) ?></title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }

@page { size: A4; margin: 0; }

body {
    font-family: Arial, sans-serif;
    font-size: 12px;
    color: #111;
    background: white;
    width: 210mm;
    min-height: 297mm;
    padding-top:    4cm;
    padding-bottom: 2cm;
    padding-left:   1.5cm;
    padding-right:  1.5cm;
}

/* ── Barre bouton imprimer ── */
.btn-print-bar {
    position: fixed; top:0; left:0; right:0;
    background: #1a4a7a; color: white;
    padding: 6px 20px;
    display: flex; align-items: center; gap: 12px;
    z-index: 999; font-size: 12px;
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
    display: flex; justify-content: flex-end; gap: 40px;
    font-size: 12px; margin-bottom: 10mm;
}

/* ── Titre encadré rouge ── */
.titre-rapport {
    border: 2px solid #cc0000; padding: 5px 12px;
    margin-bottom: 8mm; text-align: center;
}
.titre-rapport span {
    font-size: 14px; font-weight: bold;
    color: #cc0000; letter-spacing: 0.5px;
}

/* ── Bandeau patient ── */
.bandeau-patient {
    display: flex; justify-content: space-between; align-items: baseline;
    border-left: 4px solid #1a4a7a; padding-left: 8px;
    margin-bottom: 4mm;
}
.bandeau-patient .nom { font-size: 14px; font-weight: bold; text-transform: uppercase; }
.bandeau-patient .ddn { font-size: 12px; }

/* ══════════════════════════════════════════════
   BLOC UNIFORME : même espacement partout
   - .bloc       : conteneur (margin-top = espace après texte précédent)
   - .bloc-titre : titre souligné gras + ":"
   - .bloc-corps : texte avec barre gauche grise
   ══════════════════════════════════════════════ */
.bloc {
    margin-top: 3mm;
}
.bloc-titre {
    font-size: 12px;
    font-weight: bold;
    text-decoration: underline;
    margin-bottom: 0;
    color: #111;
    line-height: 1.2;
}
.bloc-corps {
    border-left: 3px solid #ccc;
    padding-left: 8px;
    font-size: 12px;
    line-height: 1.2;
    white-space: pre-wrap;
    word-wrap: break-word;
    padding-top: 0;
    padding-bottom: 0;
}

/* ── Biologie : tableau date | résultats ── */
.bio-table {
    display: table;
    border-left: 3px solid #ccc;
    padding-left: 8px;
    width: 100%;
}
.bio-ligne {
    display: table-row;
}
.bio-date {
    display: table-cell;
    white-space: nowrap;
    padding-right: 10px;
    vertical-align: top;
    font-size: 12px;
    line-height: 1.2;
}
.bio-valeurs {
    display: table-cell;
    font-size: 12px;
    line-height: 1.2;
    vertical-align: top;
}

/* ── Au total (barre bleue) ── */
.au-total-titre {
    font-size: 12px;
    font-weight: bold;
    text-decoration: underline;
    margin-top: 3mm;
    margin-bottom: 0;
    color: #111;
    line-height: 1.2;
}
.au-total-corps {
    border-left: 3px solid #1a4a7a;
    padding-left: 8px;
    font-size: 12px;
    line-height: 1.2;
    white-space: pre-wrap;
    word-wrap: break-word;
}

@media screen {
    body { margin: 36px auto 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.15); border: 1px solid #ddd; }
}
@media print {
    .btn-print-bar { display: none !important; }
    body { margin: 0; }
}
</style>
</head>
<body>

<!-- ── Barre bouton imprimer ── -->
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

<!-- ── Motif de consultation ── -->
<div class="bloc" style="margin-top:0;">
    <div class="bloc-titre">Motif de consultation :</div>
    <div class="bloc-corps"><?= $motif ?: '—' ?></div>
</div>

<!-- ── Antécédents ── -->
<div class="bloc">
    <div class="bloc-titre">Antécédents :</div>
    <div class="bloc-corps"><?= $atcd ?: '—' ?></div>
</div>

<!-- ── Facteurs de risque ── -->
<div class="bloc">
    <div class="bloc-titre">Facteurs de risque :</div>
    <div class="bloc-corps"><?php if (!empty($fdrListe)): ?><?= implode(' ; ', array_map('htmlspecialchars', $fdrListe)) ?><?php else: ?>—<?php endif; ?></div>
</div>

<!-- ── L'examen ── -->
<div class="bloc">
    <div class="bloc-titre">L'examen :</div>
    <div class="bloc-corps"><?= $texteExamen ? htmlspecialchars($texteExamen) : '—' ?></div>
</div>

<!-- ── Electrocardiogramme ── -->
<div class="bloc">
    <div class="bloc-titre">Electrocardiogramme :</div>
    <div class="bloc-corps"><?= $texteECG ? htmlspecialchars($texteECG) : '—' ?></div>
</div>

<!-- ── Echographie cardiaque ── -->
<div class="bloc">
    <div class="bloc-titre"><?= htmlspecialchars($titreEcho) ?> :</div>
    <div class="bloc-corps"><?= $texteEcho ?: '—' ?></div>
</div>

<!-- ── Bilan biologique ── -->
<?php if (!empty($bilansRapport)): ?>
<div class="bloc">
    <div class="bloc-titre">Bilan biologique :</div>
    <div class="bio-table">
    <?php foreach ($bilansRapport as $bilan):
        $parties = [];
        foreach ($bilan['anormaux'] as $bl) {
            $parties[] = htmlspecialchars($bl['nom']) . ' <strong>' . htmlspecialchars($bl['resultat']) . '</strong>';
        }
    ?>
        <div class="bio-ligne">
            <span class="bio-date"><?= htmlspecialchars($bilan['date_fr']) ?> :</span>
            <span class="bio-valeurs"><?= implode(' &nbsp;·&nbsp; ', $parties) ?></span>
        </div>
    <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- ── Au total ── -->
<div class="au-total-titre">Au total — Conduite à tenir :</div>
<div class="au-total-corps">
    <textarea class="editable-au-total" style="width:100%;min-height:50px;border:none;background:transparent;font-family:inherit;font-size:inherit;resize:vertical;padding:0;outline:none;" oninput="this.style.height='auto';this.style.height=this.scrollHeight+'px'"><?= $conduiteATenir ?></textarea>
</div>

<script>
window.addEventListener('afterprint', function() { window.close(); });
</script>
</body>
</html>
