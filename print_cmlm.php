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

$nomPatient = strtoupper(trim($patient['NOMPRENOM'] ?? ''));
$ddn = '';
if (!empty($patient['DDN'])) {
    $ts = strtotime($patient['DDN']);
    if ($ts && $ts > 86400) $ddn = date('d/m/Y', $ts);
}

// ── Diagnostics ───────────────────────────────────────────────────────────
$stmtD1 = $db->prepare("SELECT diagnostic FROM t_diagnostic WHERE id = ? ORDER BY N_dic");
$stmtD1->execute([$id]);
$diag1 = $stmtD1->fetchAll(PDO::FETCH_COLUMN);

$stmtD2 = $db->prepare("SELECT DicII FROM T_dianstcII WHERE id = ? ORDER BY N_DIC_II");
$stmtD2->execute([$id]);
$diag2 = $stmtD2->fetchAll(PDO::FETCH_COLUMN);

$stmtD3 = $db->prepare("SELECT dic_non_cardio FROM T_id_dic_non_cardio WHERE id = ? ORDER BY N_dic_non_cardio");
$stmtD3->execute([$id]);
$diag3 = $stmtD3->fetchAll(PDO::FETCH_COLUMN);

$tousLesdiags = array_filter(array_merge($diag1, $diag2, $diag3));
$diagTexte = implode(', ', $tousLesdiags);

// ── Dernier examen ────────────────────────────────────────────────────────
$stmtEx = $db->prepare("SELECT TOP 1 * FROM t_examen WHERE NPAT = ? ORDER BY DateExam DESC, N1 DESC");
$stmtEx->execute([$id]);
$examen = $stmtEx->fetch();

function concat_champs_cmlm(array $vals): string {
    $parts = array_filter($vals, fn($v) => trim((string)$v) !== '');
    return implode("\n", $parts);
}

// Clinique CMLM : uniquement le champ Conclusion
$texteExamen = trim($examen['Conclusion'] ?? '');

// ── Dernier ECG ───────────────────────────────────────────────────────────
$stmtECG = $db->prepare("SELECT TOP 1 * FROM ecg WHERE CAST([N-PAT] AS INT) = ? ORDER BY [Date ECG] DESC, [N°] DESC");
$stmtECG->execute([$id]);
$ecg = $stmtECG->fetch();

// ECG CMLM : uniquement le champ C/C
$texteECG = $ecg ? trim($ecg['C/C'] ?? '') : '';

// ── Dernier Echo ─────────────────────────────────────────────────────────
$stmtEcho = $db->prepare("SELECT TOP 1 * FROM echo WHERE [N-PAT] = ? ORDER BY DATEchog DESC, [N°] DESC");
$stmtEcho->execute([$id]);
$echo = $stmtEcho->fetch();
// Echo CMLM : champ CMLM_ECHO (généré depuis les cases à cocher du bilan)
$texteEcho = $echo ? trim($echo['CMLM_ECHO'] ?? '') : '';

// ── Biologie : analyses anormales (3 derniers bilans) ────────────────────
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
$bioAnormauxNoms = []; // pour surveillance
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
        foreach ($anormaux as $an) {
            $parties[] = $an['nom'].' '.$an['resultat'];
            $bioAnormauxNoms[] = $an['nom'];
        }
        $bioTexte .= $d['date_fr'].' : '.implode(', ', $parties)."\n";
    }
}
$bioTexte = trim($bioTexte);
$bioAnormauxNoms = array_unique($bioAnormauxNoms);
$bioSurveillanceHint = !empty($bioAnormauxNoms) ? ' (accent sur : '.implode(', ', $bioAnormauxNoms).')' : '';

// ── 4 dernières ordonnances avec médicaments ──────────────────────────────
$stmtOrds = $db->prepare("SELECT TOP 4 n_ordon, date_ordon FROM ORD WHERE id = ? ORDER BY date_ordon DESC");
$stmtOrds->execute([$id]);
$ordonnances = $stmtOrds->fetchAll();

$ordsAvecMeds = [];
foreach ($ordonnances as $ord) {
    $stmtMed = $db->prepare("
        SELECT p.*, pr.PRODUIT AS nom_produit
        FROM PROD p LEFT JOIN PRODUITS pr ON p.produit = pr.NuméroPRODUIT
        WHERE p.N_ord = ? ORDER BY p.Ordre
    ");
    $stmtMed->execute([$ord['n_ordon']]);
    $meds = $stmtMed->fetchAll();
    if (!empty($meds)) {
        $ts = strtotime($ord['date_ordon'] ?? '');
        $ordsAvecMeds[] = [
            'n_ordon'    => $ord['n_ordon'],
            'date_fr'    => ($ts && $ts > 86400) ? date('d/m/Y', $ts) : '—',
            'medicaments'=> $meds,
        ];
    }
}

// ── Spécialistes ──────────────────────────────────────────────────────────
$stmtSpec = $db->prepare("SELECT id_spec, libelle FROM T_Specialites ORDER BY ordre, libelle");
$stmtSpec->execute();
$specialites = $stmtSpec->fetchAll();

$dateAuj = date('d/m/Y');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>CMLM — <?= htmlspecialchars($nomPatient) ?></title>
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
    background: #1a4a7a; color: white;
    padding: 6px 20px;
    display: flex; align-items: center; gap: 12px;
    z-index: 999; font-size: 12px;
}
.btn-print { background:#27ae60; color:white; border:none; border-radius:4px; padding:5px 16px; font-size:12px; font-weight:bold; cursor:pointer; }
.btn-print:hover { background:#1e8449; }
.btn-close { background:#e74c3c; color:white; border:none; border-radius:4px; padding:5px 12px; font-size:12px; cursor:pointer; margin-left:auto; }

/* ── Titre ── */
.titre-cmlm { border:2px solid #1a4a7a; padding:3px 12px; margin-bottom:3mm; text-align:center; }
.titre-cmlm span { font-size:14px; font-weight:bold; color:#1a4a7a; letter-spacing:0.5px; }

/* ── Date ── */
.ligne-date { display:flex; justify-content:flex-end; margin-bottom:4mm; font-size:12px; }

/* ── Intro ── */
.intro { font-size:12px; line-height:1.5; margin-bottom:3mm; }

/* ── Section ── */
.section { margin-top:2mm; }
.section-titre {
    font-size:12px; font-weight:bold; text-decoration:underline;
    color:#1a4a7a; margin-bottom:1mm; line-height:1.2;
}
.section-corps {
    border-left:3px solid #ccc; padding-left:8px;
    font-size:12px; line-height:1.3;
}

/* ── Textarea éditable (écran seulement) ── */
.editable {
    width:100%; border:1px dashed #aaa; border-radius:3px;
    padding:4px 6px; font-size:12px; font-family:Arial,sans-serif;
    line-height:1.4; resize:vertical; background:#fafeff;
    color:#111;
}
.editable:focus { outline:none; border-color:#2e6da4; background:#f0f7ff; }

/* ── Ordonnances navigation ── */
.ord-nav { display:flex; align-items:center; gap:6px; margin-bottom:4px; flex-wrap:wrap; }
.ord-nav-btn {
    background:#1a4a7a; color:white; border:none; border-radius:3px;
    padding:2px 8px; font-size:11px; cursor:pointer;
}
.ord-nav-btn:hover { background:#2e6da4; }
.ord-label { font-size:11px; color:#555; font-weight:bold; }

.med-liste { list-style:none; margin:0; padding:0; }
.med-ligne {
    display:flex; align-items:center; justify-content:space-between;
    padding:3px 6px; border-bottom:1px solid #eee; font-size:12px;
}
.med-ligne:last-child { border-bottom:none; }
.btn-ald {
    background:#e67e22; color:white; border:none; border-radius:3px;
    padding:2px 8px; font-size:10px; cursor:pointer; flex-shrink:0; margin-left:8px;
}
.btn-ald:hover { background:#d35400; }
.btn-ald.deja { background:#95a5a6; cursor:default; }

/* ── Traitement retenu ── */
.traitement-retenu { list-style:none; padding:0; margin:2px 0; }
.traitement-retenu li {
    display:flex; align-items:center; gap:6px;
    padding:1px 0; font-size:12px;
}
.btn-retirer {
    background:#e74c3c; color:white; border:none; border-radius:50%;
    width:16px; height:16px; font-size:10px; cursor:pointer;
    display:flex; align-items:center; justify-content:center; flex-shrink:0;
    line-height:1;
}

/* ── Surveillance ── */
.surv-ligne { display:flex; align-items:center; gap:6px; margin:1px 0; font-size:12px; }
.surv-freq { color:#555; font-size:11px; }

/* ── Spécialistes ── */
.spec-grille { display:flex; flex-wrap:wrap; gap:8px 16px; margin:4px 0; }
.spec-case { display:flex; align-items:center; gap:4px; font-size:12px; }

/* ── Autre spécialité ── */
.autre-spec { display:flex; align-items:center; gap:6px; margin-top:6px; }
.autre-spec input[type=text] {
    border:1px solid #ccc; border-radius:3px; padding:3px 6px;
    font-size:12px; width:180px;
}
.btn-ajouter-spec {
    background:#2e6da4; color:white; border:none; border-radius:3px;
    padding:3px 10px; font-size:11px; cursor:pointer;
}

/* ── Bas de page ── */
.attestation { margin-top:4mm; font-size:12px; font-style:italic; border-top:1px solid #ccc; padding-top:2mm; }

/* ── IMPRESSION ── */
@media screen {
    body { margin: 36px auto 20px; box-shadow:0 2px 10px rgba(0,0,0,0.15); border:1px solid #ddd; }
}
@media print {
    .btn-bar, .ord-nav, .med-liste-wrap, .btn-retirer, .btn-ajouter-spec,
    .autre-spec input, .no-print { display:none !important; }

    .editable {
        border:none !important; background:transparent !important;
        padding:0 !important; resize:none !important;
        overflow:visible !important;
        height:auto !important;
        min-height:0 !important;
    }

    /* Traitement retenu : puces propres */
    .traitement-retenu li::before { content:"• "; }
    .traitement-item-text { font-weight:normal; }

    /* Checkboxes : afficher seulement les cochées */
    .spec-case input[type=checkbox]:not(:checked) + label { display:none; }
    .spec-case input[type=checkbox]:not(:checked) { display:none; }
    .spec-case input[type=checkbox]:checked { display:none; }
    .spec-case input[type=checkbox]:checked + label::before { content:"✓ "; }

    .surv-ligne input[type=checkbox]:not(:checked) { display:none; }
    .surv-ligne input[type=checkbox]:not(:checked) ~ * { display:none; }
    .surv-ligne input[type=checkbox]:checked { display:none; }
    .surv-ligne input[type=checkbox]:checked ~ label { display:inline; }
    .surv-ligne input[type=checkbox]:checked ~ label::before { content:"• "; }
}
</style>
</head>
<body>

<!-- ── Barre boutons ── -->
<div class="btn-bar">
    <button class="btn-print" onclick="window.print()">🖨️ Imprimer</button>
    <span><?= htmlspecialchars($nomPatient) ?> — CMLM</span>
    <button class="btn-close" onclick="window.close()">✕ Fermer</button>
</div>

<!-- ── Date ── -->
<div class="ligne-date">Tétouan, le <?= $dateAuj ?></div>

<!-- ── Titre ── -->
<div class="titre-cmlm">
    <span>Certificat Médical de Longue Maladie</span>
</div>

<!-- ── Intro ── -->
<div class="intro">
    Je soussigné, <strong>Dr Hassan Hlimi</strong>, certifie que <strong><?= htmlspecialchars($nomPatient) ?></strong><?php if ($ddn): ?>, né(e) le <strong><?= $ddn ?></strong><?php endif; ?>
</div>

<!-- ══ 1. AFFECTION ══════════════════════════════════════════════════════ -->
<div class="section">
    <div class="section-titre">Souffre d'une affection médicale cardiologique chronique :</div>
    <div class="section-corps">
        <textarea class="editable" id="txt_affection" rows="2"><?= htmlspecialchars($diagTexte ?: '—') ?></textarea>
    </div>
</div>

<!-- ══ 2. DIAGNOSTIC ═════════════════════════════════════════════════════ -->
<div class="section">
    <div class="section-titre">Diagnostic :</div>
    <div class="section-corps">

        <div style="margin-bottom:3px;">
            <div style="font-size:11px;color:#555;margin-bottom:1px;">Examen clinique :</div>
            <textarea class="editable" id="txt_clinique" rows="2"><?= htmlspecialchars($texteExamen ?: '—') ?></textarea>
        </div>

        <div style="margin-bottom:3px;">
            <div style="font-size:11px;color:#555;margin-bottom:1px;">Examen ECG :</div>
            <textarea class="editable" id="txt_ecg" rows="2"><?= htmlspecialchars($texteECG ?: '—') ?></textarea>
        </div>

        <div style="margin-bottom:3px;">
            <div style="font-size:11px;color:#555;margin-bottom:1px;">Examen Echo-doppler :</div>
            <textarea class="editable" id="txt_echo" rows="2"><?= htmlspecialchars($texteEcho ?: '—') ?></textarea>
        </div>

        <div style="margin-bottom:2px;">
            <div style="font-size:11px;color:#555;margin-bottom:1px;">Examen biologique :</div>
            <textarea class="editable" id="txt_bio" rows="2"><?= htmlspecialchars($bioTexte ?: '—') ?></textarea>
        </div>

    </div><!-- fin section-corps diagnostic -->
</div><!-- fin section diagnostic -->

<!-- ══ 3. TRAITEMENT ══════════════════════════════════════════════════════ -->
<div class="section">
    <div class="section-titre">Son état nécessite un traitement médical au long cours, actuellement sous :</div>
    <div class="section-corps">

        <!-- Navigation ordonnances (écran seulement) -->
        <?php if (!empty($ordsAvecMeds)): ?>
        <div class="no-print" style="margin-bottom:6px;">
            <div class="ord-nav">
                <span class="ord-label">Ordonnance :</span>
                <?php foreach ($ordsAvecMeds as $i => $ord): ?>
                <button class="ord-nav-btn" onclick="afficherOrd(<?= $i ?>)"
                    id="btn_ord_<?= $i ?>"
                    style="<?= $i===0 ? 'background:#c0392b;' : '' ?>">
                    <?= htmlspecialchars($ord['date_fr']) ?>
                </button>
                <?php endforeach; ?>
            </div>

            <?php foreach ($ordsAvecMeds as $i => $ord): ?>
            <div id="ord_panel_<?= $i ?>" class="med-liste-wrap" style="<?= $i>0 ? 'display:none;' : '' ?>border:1px solid #dde;border-radius:4px;padding:4px 8px;margin-bottom:6px;">
                <ul class="med-liste">
                <?php foreach ($ord['medicaments'] as $m): ?>
                <li class="med-ligne">
                    <span><?= htmlspecialchars($m['nom_produit'] ?? '') ?>
                        <?php if (!empty($m['posologie'])): ?><span style="color:#888;font-size:11px;"> — <?= htmlspecialchars($m['posologie']) ?></span><?php endif; ?>
                    </span>
                    <button class="btn-ald"
                        onclick="ajouterALD('<?= addslashes(htmlspecialchars($m['nom_produit'] ?? '')) ?>', '<?= addslashes(htmlspecialchars($m['posologie'] ?? '')) ?>', this)">
                        ALD
                    </button>
                </li>
                <?php endforeach; ?>
                </ul>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Traitement retenu -->
        <div style="font-size:11px;color:#1a4a7a;font-weight:bold;margin-bottom:3px;">Traitement retenu :</div>
        <ul class="traitement-retenu" id="liste_ald">
            <li id="ald_vide" style="color:#aaa;font-size:11px;font-style:italic;">Aucun médicament ajouté — cliquez sur [ALD]</li>
        </ul>

    </div>
</div>

<!-- ══ 4+5. NÉCESSITE PAR AILLEURS ════════════════════════════════════════ -->
<div class="section">
    <div class="section-titre">Nécessite par ailleurs :</div>
    <div class="section-corps">

        <!-- Surveillance régulière -->
        <div style="margin-bottom:2px;">
            <div style="font-size:12px;font-weight:bold;margin-bottom:1px;padding-left:16px;">une surveillance régulière :</div>
            <div style="padding-left:32px;">
                <div class="surv-ligne">
                    <input type="checkbox" id="surv_clin" checked>
                    <label for="surv_clin">Clinique</label>
                    <span class="surv-freq">(trimestrielle)</span>
                </div>
                <div class="surv-ligne">
                    <input type="checkbox" id="surv_ecg" checked>
                    <label for="surv_ecg">Électrocardiographique</label>
                    <span class="surv-freq">(trimestrielle)</span>
                </div>
                <div class="surv-ligne">
                    <input type="checkbox" id="surv_echo" checked>
                    <label for="surv_echo">Échographique</label>
                    <span class="surv-freq">(annuelle)</span>
                </div>
                <div class="surv-ligne">
                    <input type="checkbox" id="surv_bio" checked>
                    <label for="surv_bio">Biologique</label>
                    <span class="surv-freq"><?= htmlspecialchars('(en fonction du diagnostic'.$bioSurveillanceHint.')') ?></span>
                </div>
            </div>
        </div>

        <!-- Avis spécialiste -->
        <div style="margin-top:3px;">
            <div style="font-size:12px;font-weight:bold;margin-bottom:1px;padding-left:16px;">Avis spécialiste :</div>
            <div style="padding-left:32px;">
                <div class="spec-grille" id="spec_grille">
                <?php foreach ($specialites as $sp): ?>
                    <div class="spec-case">
                        <input type="checkbox" id="spec_<?= $sp['id_spec'] ?>" value="<?= htmlspecialchars($sp['libelle']) ?>">
                        <label for="spec_<?= $sp['id_spec'] ?>"><?= htmlspecialchars($sp['libelle']) ?></label>
                    </div>
                <?php endforeach; ?>
                </div>

                <!-- Ajouter spécialité -->
                <div class="autre-spec no-print">
                    <span style="font-size:11px;color:#555;">Autre :</span>
                    <input type="text" id="autre_spec_input" placeholder="Spécialité…">
                    <button class="btn-ajouter-spec" onclick="ajouterSpecialite()">+ Ajouter</button>
                    <span id="spec_msg" style="font-size:11px;color:#27ae60;"></span>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- ══ Attestation ════════════════════════════════════════════════════════ -->
<div class="attestation">
    Attestation délivrée à l'intéressé(e) pour usage administratif.
</div>

<script>
/* ── Navigation ordonnances ── */
const nbOrds = <?= count($ordsAvecMeds) ?>;
function afficherOrd(idx) {
    for (let i = 0; i < nbOrds; i++) {
        const panel = document.getElementById('ord_panel_' + i);
        const btn   = document.getElementById('btn_ord_' + i);
        if (panel) panel.style.display = (i === idx) ? 'block' : 'none';
        if (btn)   btn.style.background = (i === idx) ? '#c0392b' : '#1a4a7a';
    }
}

/* ── ALD : ajouter médicament au traitement retenu ── */
const aldAjoutes = new Set();

function ajouterALD(produit, posologie, btn) {
    const cle = produit;
    if (aldAjoutes.has(cle)) return;
    aldAjoutes.add(cle);

    // Griser tous les boutons ALD du même produit
    document.querySelectorAll('.btn-ald').forEach(b => {
        if (b.onclick && b.onclick.toString().includes(produit.replace(/'/g, "\\'"))) {
            b.classList.add('deja');
            b.textContent = '✓';
        }
    });

    // Supprimer message "aucun"
    const vide = document.getElementById('ald_vide');
    if (vide) vide.remove();

    // Ajouter à la liste retenue
    const li = document.createElement('li');
    const texte = produit + (posologie ? ' — ' + posologie : '');
    li.innerHTML = `<button class="btn-retirer" onclick="retirerALD('${cle.replace(/'/g,"\\'")}', this.parentElement)" title="Retirer">✕</button>
                    <span class="traitement-item-text">• ${texte}</span>`;
    li.dataset.cle = cle;
    document.getElementById('liste_ald').appendChild(li);
}

function retirerALD(cle, li) {
    aldAjoutes.delete(cle);
    // Réactiver boutons ALD
    document.querySelectorAll('.btn-ald').forEach(b => {
        if (b.onclick && b.onclick.toString().includes(cle.replace(/'/g,"\\'"))) {
            b.classList.remove('deja');
            b.textContent = 'ALD';
        }
    });
    li.remove();
    if (document.getElementById('liste_ald').children.length === 0) {
        const vide = document.createElement('li');
        vide.id = 'ald_vide';
        vide.style.cssText = 'color:#aaa;font-size:11px;font-style:italic;';
        vide.textContent = 'Aucun médicament ajouté — cliquez sur [ALD]';
        document.getElementById('liste_ald').appendChild(vide);
    }
}

/* ── Ajouter spécialité personnalisée ── */
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
            // Ajouter à la grille et cocher directement
            const grille = document.getElementById('spec_grille');
            const div = document.createElement('div');
            div.className = 'spec-case';
            div.innerHTML = `<input type="checkbox" id="spec_${d.id_spec}" value="${lib}" checked>
                             <label for="spec_${d.id_spec}">${lib}</label>`;
            grille.appendChild(div);
            input.value = '';
            msg.textContent = '✓ Ajouté'; msg.style.color = '#27ae60';
            setTimeout(() => msg.textContent = '', 2000);
        } else {
            msg.textContent = '❌ ' + (d.error || 'Erreur'); msg.style.color = '#e74c3c';
        }
    })
    .catch(() => { msg.textContent = '❌ Erreur réseau'; msg.style.color = '#e74c3c'; });
}

/* ── Enter dans le champ spécialité ── */
document.getElementById('autre_spec_input').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') ajouterSpecialite();
});

/* ── Fermer après impression ── */
window.addEventListener('afterprint', function() { window.close(); });

/* ── Auto-hauteur textarea avant impression ── */
function ajusterHauteurTextareas() {
    document.querySelectorAll('textarea.editable').forEach(function(ta) {
        ta.style.height = 'auto';
        ta.style.height = ta.scrollHeight + 'px';
    });
}
// Ajuster à l'ouverture et avant impression
ajusterHauteurTextareas();
window.addEventListener('beforeprint', ajusterHauteurTextareas);


</script>

</body>
</html>
