<?php
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
if ($id == 0) die("❌ Patient introuvable.");

// ── Patient ───────────────────────────────────────────────────────────────
$stmt = $db->prepare("SELECT * FROM ID WHERE [N°PAT] = ?");
$stmt->execute([$id]);
$patient = $stmt->fetch();
if (!$patient) die("❌ Patient introuvable.");

$nomPatient   = strtoupper(trim($patient['NOMPRENOM'] ?? ''));
$motifConsult = trim($patient['MOTIF CONSULTATION'] ?? '');
$ddn = '';
$age = '';
if (!empty($patient['DDN'])) {
    $ts = strtotime($patient['DDN']);
    if ($ts && $ts > 86400) {
        $ddn = date('d/m/Y', $ts);
        $age = (new DateTime($patient['DDN']))->diff(new DateTime())->y;
    }
}

// ── Diagnostic & ATCD ─────────────────────────────────────────────────────
$stmtPat2 = $db->prepare("SELECT diagnostic, ATCD FROM ID WHERE [N°PAT] = ?");
$stmtPat2->execute([$id]);
$patExtra = $stmtPat2->fetch();
$diagTexte = trim($patExtra['diagnostic'] ?? '');
$atcdTexte = trim($patExtra['ATCD'] ?? '');

// ── Dernier examen ────────────────────────────────────────────────────────
$stmtEx = $db->prepare("SELECT TOP 1 * FROM t_examen WHERE NPAT = ? ORDER BY DateExam DESC, N1 DESC");
$stmtEx->execute([$id]);
$examen = $stmtEx->fetch();
$texteExamen = trim($examen['Conclusion'] ?? '');

// ── Dernier ECG ───────────────────────────────────────────────────────────
$stmtECG = $db->prepare("SELECT TOP 1 * FROM ecg WHERE CAST([N-PAT] AS INT) = ? ORDER BY [Date ECG] DESC, [N°] DESC");
$stmtECG->execute([$id]);
$ecg = $stmtECG->fetch();
$texteECG = $ecg ? trim($ecg['C/C'] ?? '') : '';

// ── Dernier Echo ──────────────────────────────────────────────────────────
$stmtEcho = $db->prepare("SELECT TOP 1 * FROM echo WHERE [N-PAT] = ? ORDER BY DATEchog DESC, [N°] DESC");
$stmtEcho->execute([$id]);
$echo = $stmtEcho->fetch();
$texteEcho = $echo ? trim($echo['CMLM_ECHO'] ?? '') : '';

// ── Biologie ─────────────────────────────────────────────────────────────
$stmtBio3 = $db->prepare("
    SELECT TOP 3 CONVERT(varchar(10), date_bilan, 103) AS date_fr,
                 CONVERT(varchar(10), date_bilan, 112) AS date_tri
    FROM LE_BILAN WHERE id = ?
    GROUP BY CONVERT(varchar(10), date_bilan, 103), CONVERT(varchar(10), date_bilan, 112)
    ORDER BY date_tri DESC
");
$stmtBio3->execute([$id]);
$datesBio = $stmtBio3->fetchAll();

$bioTexte = '';
foreach ($datesBio as $d) {
    $stmtIds = $db->prepare("SELECT n_bilan FROM LE_BILAN WHERE id = ? AND CONVERT(varchar(10), date_bilan, 103) = ?");
    $stmtIds->execute([$id, $d['date_fr']]);
    $ids = $stmtIds->fetchAll(PDO::FETCH_COLUMN);
    if (empty($ids)) continue;
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $stmtAn = $db->prepare("
        SELECT c.analyse AS nom, ISNULL(a.résultat,'') AS resultat
        FROM analyses a LEFT JOIN C_ANALYSE c ON c.[N°TypeAnalyse] = a.bilan
        WHERE a.N_bilan IN ($ph) AND ISNULL(a.résultat,'') <> '' AND a.résultat <> 'N'
        ORDER BY c.rubrique, c.analyse
    ");
    $stmtAn->execute($ids);
    $anormaux = $stmtAn->fetchAll();
    if (!empty($anormaux)) {
        $parties = [];
        foreach ($anormaux as $an) $parties[] = $an['nom'].' '.$an['resultat'];
        $bioTexte .= $d['date_fr'].' : '.implode(', ', $parties)."\n";
    }
}
$bioTexte = trim($bioTexte);

// ── Dernière ordonnance avec médicaments ──────────────────────────────────
$stmtOrd = $db->prepare("SELECT TOP 1 n_ordon, date_ordon FROM ORD WHERE id = ? ORDER BY date_ordon DESC");
$stmtOrd->execute([$id]);
$dernOrd = $stmtOrd->fetch();

$medicaments = [];
$dateOrd = '';
if ($dernOrd) {
    $stmtMed = $db->prepare("
        SELECT p.*, pr.PRODUIT AS nom_produit
        FROM PROD p LEFT JOIN PRODUITS pr ON p.produit = pr.NuméroPRODUIT
        WHERE p.N_ord = ? ORDER BY p.Ordre
    ");
    $stmtMed->execute([$dernOrd['n_ordon']]);
    $medicaments = $stmtMed->fetchAll();
    $ts = strtotime($dernOrd['date_ordon'] ?? '');
    $dateOrd = ($ts && $ts > 86400) ? date('d/m/Y', $ts) : '';
}

// ── Spécialistes ─────────────────────────────────────────────────────────
$stmtSpec = $db->prepare("SELECT id_spec, libelle FROM T_Specialites ORDER BY ordre, libelle");
$stmtSpec->execute();
$specialites = $stmtSpec->fetchAll();

$dateAuj = date('d/m/Y');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Lettre — <?= htmlspecialchars($nomPatient) ?></title>
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
    padding-top: 1.4cm;
    padding-bottom: 1.2cm;
    padding-left: 1.5cm;
    padding-right: 1.5cm;
}

/* ── Barre boutons ── */
.btn-bar {
    position:fixed; top:0; left:0; right:0;
    background:#1a4a7a; color:white;
    padding:6px 20px;
    display:flex; align-items:center; gap:12px;
    z-index:999; font-size:12px;
}
.btn-print { background:#27ae60; color:white; border:none; border-radius:4px; padding:5px 16px; font-size:12px; font-weight:bold; cursor:pointer; }
.btn-print:hover { background:#1e8449; }
.btn-close  { background:#e74c3c; color:white; border:none; border-radius:4px; padding:5px 12px; font-size:12px; cursor:pointer; margin-left:auto; }

/* ── Champ contenteditable — remplace les textareas ── */
.editable {
    width: 100%;
    min-height: 1.8em;
    border: 1px dashed #aaa;
    border-radius: 3px;
    padding: 4px 6px;
    font-size: 12px;
    font-family: Arial, sans-serif;
    line-height: 1.5;
    background: #fafeff;
    color: #111;
    white-space: pre-wrap;
    word-break: break-word;
    cursor: text;
}
.editable:focus {
    outline: 2px solid #2e6da4;
    background: #f0f7ff;
}
/* Placeholder simulé */
.editable:empty::before {
    content: attr(data-placeholder);
    color: #bbb;
    font-style: italic;
    pointer-events: none;
}

/* ── Destinataire ── */
.dest-label { font-size:12px; font-weight:bold; color:#1a4a7a; margin-bottom:2px; }
.spec-grille { display:flex; flex-wrap:wrap; gap:4px 16px; margin:2px 0 2px 16px; }
.spec-case { display:flex; align-items:center; gap:4px; font-size:12px; cursor:pointer; }
.spec-case input[type="radio"] { cursor:pointer; accent-color:#1a4a7a; }
.spec-case label { cursor:pointer; }
.spec-case.selected label { font-weight:bold; color:#1a4a7a; }

.autre-spec { display:flex; align-items:center; gap:6px; margin-top:3px; margin-left:16px; }
.autre-spec input[type="text"] { border:1px solid #ccc; border-radius:3px; padding:3px 7px; font-size:12px; width:200px; }
.btn-ajouter-spec { background:#2e6da4; color:white; border:none; border-radius:3px; padding:3px 10px; font-size:11px; cursor:pointer; }
.btn-ajouter-spec:hover { background:#1a4a7a; }

/* ── Ligne date ── */
.ligne-date-lettre { text-align:right; font-size:12px; margin-bottom:2mm; }

/* ── Objet ── */
.objet { font-size:12px; font-weight:bold; border-bottom:1px solid #ccc; padding-bottom:1mm; margin-bottom:2mm; }

/* ── Identité patient ── */
.identite-patient { background:#f0f7ff; border-left:3px solid #2e6da4; padding:3px 8px; margin-bottom:2mm; font-size:12px; line-height:1.4; }

/* ── Sections ── */
.section { margin-top:2mm; }
.section-titre { font-size:12px; font-weight:bold; text-decoration:underline; color:#1a4a7a; margin-bottom:0.5mm; }
.section-corps { border-left:3px solid #ccc; padding-left:8px; font-size:12px; line-height:1.4; }
.sous-label { font-size:11px; color:#555; margin-bottom:1px; margin-top:3px; }

/* ── Médicaments ── */
.med-liste-print { list-style:none; padding:0; margin:2px 0; }
.med-ligne-print { padding:1px 0; font-size:12px; line-height:1.5; }
.med-ligne-print::before { content:"• "; }

/* ── Formule finale ── */
.formule-finale { margin-top:3mm; font-size:12px; line-height:1.4; }
.signature-bloc { margin-top:5mm; font-size:12px; font-weight:bold; }

/* ══ IMPRESSION ══════════════════════════════════════════════════════════ */
@media screen {
    body { margin:36px auto 20px; box-shadow:0 2px 10px rgba(0,0,0,0.15); border:1px solid #ddd; }
}
@media print {
    .btn-bar, .no-print, .autre-spec { display:none !important; }

    /* contenteditable : supprimer la bordure à l'impression */
    .editable {
        border: none !important;
        background: transparent !important;
        padding: 0 !important;
        outline: none !important;
    }
    /* Masquer le placeholder à l'impression */
    .editable:empty::before { content: '' !important; }

    /* Cases radio : n'afficher que la cochée */
    .spec-case input[type="radio"]:not(:checked) + label { display:none; }
    .spec-case input[type="radio"]:not(:checked)         { display:none; }
    .spec-case input[type="radio"]:checked               { display:none; }
    .spec-case input[type="radio"]:checked + label       { font-weight:bold; }

    .identite-patient { background:white !important; }
}
</style>
</head>
<body>

<!-- ── Barre boutons ── -->
<div class="btn-bar">
    <button class="btn-print" onclick="window.print()">🖨️ Imprimer</button>
    <span>✉️ Lettre — <?= htmlspecialchars($nomPatient) ?></span>
    <button class="btn-close" onclick="window.close()">✕ Fermer</button>
</div>

<!-- ══ DESTINATAIRE ═══════════════════════════════════════════════════════ -->
<div style="margin-bottom:2mm;">
    <div class="dest-label">À l'attention du :</div>

    <div class="spec-grille" id="spec_grille">
    <?php foreach ($specialites as $sp): ?>
        <div class="spec-case" id="case_<?= $sp['id_spec'] ?>">
            <input type="radio" name="destinataire" id="spec_<?= $sp['id_spec'] ?>"
                   value="<?= htmlspecialchars($sp['libelle']) ?>"
                   onchange="majSelection(this)">
            <label for="spec_<?= $sp['id_spec'] ?>"><?= htmlspecialchars($sp['libelle']) ?></label>
        </div>
    <?php endforeach; ?>
    </div>

    <div class="autre-spec no-print">
        <span style="font-size:11px;color:#555;">Autre :</span>
        <input type="text" id="autre_spec_input" placeholder="Ex : Neurochirurgien…" autocomplete="off">
        <button class="btn-ajouter-spec" onclick="ajouterSpecialite()">+ Ajouter</button>
        <span id="spec_msg" style="font-size:11px;color:#27ae60;"></span>
    </div>
</div>

<!-- ── Date ── -->
<div class="ligne-date-lettre">Tétouan, le <?= $dateAuj ?></div>

<!-- ── Objet ── -->
<div class="objet">Objet : Lettre de correspondance concernant le patient <?= htmlspecialchars($nomPatient) ?></div>

<!-- ── Identité patient ── -->
<div class="identite-patient">
    <strong>Patient :</strong> <?= htmlspecialchars($nomPatient) ?><?php if ($age): ?> — <?= $age ?> ans<?php endif; ?><?php if ($ddn): ?>, né(e) le <?= $ddn ?><?php endif; ?>
    — N°PAT : <?= $id ?>
</div>

<!-- ══ 1. MOTIF ═══════════════════════════════════════════════════════════ -->
<div class="section">
    <div class="section-titre">Motif :</div>
    <div class="section-corps">
        <div class="editable" contenteditable="true"
             data-placeholder="Saisir le motif de la correspondance…"><?= htmlspecialchars($motifConsult) ?></div>
    </div>
</div>

<!-- ══ 2. ANTÉCÉDENTS & DIAGNOSTIC ═══════════════════════════════════════ -->
<div class="section">
    <div class="section-titre">Antécédents et diagnostic :</div>
    <div class="section-corps">
        <?php if ($atcdTexte): ?>
        <div class="sous-label">Antécédents :</div>
        <div class="editable" contenteditable="true"><?= htmlspecialchars($atcdTexte) ?></div>
        <?php endif; ?>
        <?php if ($diagTexte): ?>
        <div class="sous-label">Diagnostic :</div>
        <div class="editable" contenteditable="true"><?= htmlspecialchars($diagTexte) ?></div>
        <?php endif; ?>
        <?php if (!$atcdTexte && !$diagTexte): ?>
        <div class="editable" contenteditable="true" data-placeholder="Antécédents et diagnostic…">—</div>
        <?php endif; ?>
    </div>
</div>

<!-- ══ 3. DONNÉES CLINIQUES ═══════════════════════════════════════════════ -->
<?php if ($texteExamen || $texteECG || $texteEcho || $bioTexte): ?>
<div class="section">
    <div class="section-titre">Données cliniques :</div>
    <div class="section-corps">

        <?php if ($texteExamen): ?>
        <div class="sous-label">Examen clinique :</div>
        <div class="editable" contenteditable="true"><?= htmlspecialchars($texteExamen) ?></div>
        <?php endif; ?>

        <?php if ($texteECG): ?>
        <div class="sous-label">ECG :</div>
        <div class="editable" contenteditable="true"><?= htmlspecialchars($texteECG) ?></div>
        <?php endif; ?>

        <?php if ($texteEcho): ?>
        <div class="sous-label">Écho-Doppler :</div>
        <div class="editable" contenteditable="true"><?= htmlspecialchars($texteEcho) ?></div>
        <?php endif; ?>

        <?php if ($bioTexte): ?>
        <div class="sous-label">Biologie :</div>
        <div class="editable" contenteditable="true"><?= htmlspecialchars($bioTexte) ?></div>
        <?php endif; ?>

    </div>
</div>
<?php endif; ?>

<!-- ══ 4. TRAITEMENT EN COURS ════════════════════════════════════════════ -->
<?php if (!empty($medicaments)): ?>
<div class="section">
    <div class="section-titre">Traitement en cours<?= $dateOrd ? ' (ordonnance du '.$dateOrd.')' : '' ?> :</div>
    <div class="section-corps">
        <ul class="med-liste-print">
        <?php foreach ($medicaments as $m): ?>
            <?php
                $ligne = htmlspecialchars($m['nom_produit'] ?? '');
                if (!empty($m['posologie'])) $ligne .= ' — '.htmlspecialchars($m['posologie']);
                if (!empty($m['DUREE']))     $ligne .= ' — '.htmlspecialchars($m['DUREE']);
            ?>
            <li class="med-ligne-print"><?= $ligne ?></li>
        <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php endif; ?>

<!-- ══ 5. CONCLUSION ═════════════════════════════════════════════════════ -->
<div class="section">
    <div class="section-titre">Conclusion :</div>
    <div class="section-corps">
        <div class="editable" contenteditable="true">Je vous adresse ce patient pour avis et prise en charge.</div>
    </div>
</div>

<!-- ── Formule de politesse ── -->
<div class="formule-finale">
    Avec mes cordiales salutations confraternelles,
</div>
<div class="signature-bloc">
    Dr Hassan Hlimi<br>
    <span style="font-weight:normal;font-size:11px;">Cardiologue — Tétouan</span>
</div>

<script>
/* ── Sélection destinataire ── */
function majSelection(radio) {
    document.querySelectorAll('.spec-case').forEach(c => c.classList.remove('selected'));
    if (radio.checked) radio.closest('.spec-case').classList.add('selected');
}

/* ── Ajouter spécialité ── */
function ajouterSpecialite() {
    const input = document.getElementById('autre_spec_input');
    const msg   = document.getElementById('spec_msg');
    const lib   = input.value.trim();
    if (!lib) return;

    msg.textContent = 'Enregistrement…'; msg.style.color = '#999';

    fetch('ajax_cmlm_specialites.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'ajouter', libelle: lib})
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            if (!d.doublon) {
                const grille = document.getElementById('spec_grille');
                const div = document.createElement('div');
                div.className = 'spec-case selected';
                div.id = 'case_' + d.id_spec;
                div.innerHTML =
                    `<input type="radio" name="destinataire" id="spec_${d.id_spec}"
                            value="${lib.replace(/"/g,'&quot;')}"
                            onchange="majSelection(this)" checked>
                     <label for="spec_${d.id_spec}">${lib}</label>`;
                grille.appendChild(div);
                document.querySelectorAll('.spec-case').forEach(c => {
                    if (c.id !== 'case_' + d.id_spec) c.classList.remove('selected');
                });
            } else {
                const radio = document.getElementById('spec_' + d.id_spec);
                if (radio) { radio.checked = true; majSelection(radio); }
                msg.textContent = '(déjà présent — sélectionné)';
                msg.style.color = '#e67e22';
                setTimeout(() => msg.textContent = '', 3000);
                input.value = '';
                return;
            }
            input.value = '';
            msg.textContent = '✓ Ajouté'; msg.style.color = '#27ae60';
            setTimeout(() => msg.textContent = '', 2500);
        } else {
            msg.textContent = '❌ ' + (d.error || 'Erreur'); msg.style.color = '#e74c3c';
        }
    })
    .catch(() => { msg.textContent = '❌ Erreur réseau'; msg.style.color = '#e74c3c'; });
}

document.getElementById('autre_spec_input').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') ajouterSpecialite();
});

/* ── Fermer après impression ── */
window.addEventListener('afterprint', function() { window.close(); });
</script>

</body>
</html>
