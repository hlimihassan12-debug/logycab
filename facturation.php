<?php
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';
$db = getDB();

// ── Compteur RDV du jour (bloc logo, comme les autres pages) ──
$todayAff = date('Y-m-d');
$stmtCfgG = $db->prepare("SELECT Valeur FROM T_Config WHERE Cle='NbrMax'");
$stmtCfgG->execute();
$nbrMaxG = (int)($stmtCfgG->fetchColumn() ?: 20);
$stmtNbG = $db->prepare("SELECT COUNT(*) FROM ORD WHERE CONVERT(date,[DATE REDEZ VOUS]) = ? OR CONVERT(date,Date_Rdv) = ?");
$stmtNbG->execute([$todayAff, $todayAff]);
$nbPatientsG = (int)$stmtNbG->fetchColumn();

// ── Thème (cookie partagé) ──
$themes_valides = ['theme-0','theme-a','theme-b','theme-c'];
$theme = $_COOKIE['logycab_theme'] ?? 'theme-0';
if (!in_array($theme, $themes_valides)) $theme = 'theme-0';

// ── Patient ──
$id = (int)($_GET['id'] ?? 0);
$patient = null;

if ($id > 0) {
    $stmt = $db->prepare("SELECT * FROM ID WHERE [N°PAT] = ?");
    $stmt->execute([$id]);
    $patient = $stmt->fetch();
}

$factures = [];
$factCourante = null;
$idxFact = 0;
$detailActes = [];
$listeActes = [];

if ($patient) {
    $stmtFact = $db->prepare("
        SELECT f.n_facture, f.id, f.date_facture, f.montant,
               ISNULL(SUM(d.prixU),0) AS total,
               ISNULL(SUM(d.Versé),0) AS verse_total,
               ISNULL(SUM(d.dette),0) AS dette_total
        FROM facture f
        LEFT JOIN detail_acte d ON f.n_facture = d.N_fact
        WHERE f.id = ?
        GROUP BY f.n_facture, f.id, f.date_facture, f.montant
        ORDER BY f.date_facture DESC");
    $stmtFact->execute([$id]);
    $factures = $stmtFact->fetchAll();

    $nFact = (int)($_GET['fact'] ?? ($factures ? $factures[0]['n_facture'] : 0));
    foreach ($factures as $i => $f) { if ($f['n_facture'] == $nFact) { $factCourante = $f; $idxFact = $i; break; } }

    if ($factCourante) {
        $stmtDA = $db->prepare("SELECT d.*, a.ACTE AS nom_acte FROM detail_acte d LEFT JOIN t_acte_simplifiée a ON d.ACTE = a.n_acte WHERE d.N_fact = ?");
        $stmtDA->execute([$factCourante['n_facture']]);
        $detailActes = $stmtDA->fetchAll();
    }

    $listeActes = $db->query("SELECT n_acte, ACTE, cout FROM t_acte_simplifiée ORDER BY ACTE")->fetchAll();
}

$factPremiere = $factures ? $factures[0]['n_facture'] : 0;
$factDerniere = $factures ? $factures[count($factures)-1]['n_facture'] : 0;
$factPrev = ($idxFact > 0) ? $factures[$idxFact-1]['n_facture'] : ($factCourante['n_facture'] ?? 0);
$factNext = ($idxFact < count($factures)-1) ? $factures[$idxFact+1]['n_facture'] : ($factCourante['n_facture'] ?? 0);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Logycab — Facturation</title>
<link rel="stylesheet" href="themes.css">
<style>
* { box-sizing:border-box; margin:0; padding:0; }
body { font-family:Arial,sans-serif; font-size:12px; background:var(--th-bg-page); color:var(--th-color-text); }
.header { background:var(--th-bg-header); color:white; padding:8px 16px; display:flex; align-items:center; gap:10px; }
.logo-block { display:flex; align-items:center; gap:8px; }
.heart { font-size:22px; color:#ff6b6b; }
.nom-logo { font-size:16px; font-weight:700; letter-spacing:1px; }
.sub { font-size:10px; opacity:0.75; }
.bh { padding:4px 10px; border-radius:4px; font-size:11px; font-weight:bold; text-decoration:none; color:white; border:none; cursor:pointer; white-space:nowrap; }
.bh-red{background:#c0392b;} .bh-navy{background:#1a4a7a;} .bh-blue{background:#2e6da4;}
.bh-orange{background:#e67e22;} .bh-purple{background:#8e44ad;} .bh-green{background:#27ae60;}
.btn-search{background:rgba(255,255,255,0.15);color:white;padding:4px 10px;border-radius:4px;text-decoration:none;font-size:13px;}
.header-clock { background:rgba(255,255,255,0.12); border-radius:6px; padding:4px 10px; text-align:center; }
.header-clock .ct { font-size:15px; font-weight:bold; color:white; }
.header-clock .cd { font-size:9px; opacity:0.75; }

.page { padding:16px; max-width:820px; margin:0 auto; }
.carte { background:var(--th-bg-card); border-radius:8px; padding:16px; box-shadow:0 1px 4px rgba(0,0,0,0.1); }
.carte-titre { font-size:16px; font-weight:bold; color:var(--th-color-primary); margin-bottom:12px; }

.zrech { display:flex; gap:8px; margin-bottom:16px; }
.zrech input { flex:1; padding:8px; border:1px solid #ccc; border-radius:4px; font-size:13px; }
.zrech button { background:#1a4a7a; color:white; border:none; padding:8px 16px; border-radius:4px; cursor:pointer; font-size:13px; }

.patient-bar { display:flex; gap:20px; background:var(--th-bg-card); border-bottom:2px solid var(--th-color-primary); padding:8px 16px; margin-bottom:14px; border-radius:4px; }
.patient-bar .info label { font-size:10px; color:#888; display:block; }
.patient-bar .info span { font-size:13px; font-weight:bold; }

.nav-btn { background:#eee; border:1px solid #ccc; border-radius:3px; padding:2px 8px; cursor:pointer; font-size:11px; color:#333; text-decoration:none; display:inline-block; }

table.fact { width:100%; border-collapse:collapse; font-size:12px; margin-top:8px; }
table.fact thead { background:#1a4a7a; color:white; }
table.fact th, table.fact td { padding:5px 8px; }
table.fact tbody tr { border-bottom:1px solid #eee; }
table.fact tfoot { background:#f0f4f8; font-weight:bold; }

@media print {
    .header, .zrech, .no-print { display:none !important; }
    body { background:white; }
    .page { padding:0; max-width:176mm; }
    @page { size:176mm 250mm; margin:15mm 10mm; }
}
</style>
</head>
<body class="<?= htmlspecialchars($theme) ?>">
<script src="home.js"></script>

<div class="header no-print">
    <div class="logo-block">
        <span class="heart">❤</span>
        <div>
            <div class="nom-logo">LOGYCAB</div>
            <div class="sub"><?= $nbPatientsG ?> RDV aujourd'hui / <?= $nbrMaxG ?> prévus</div>
        </div>
    </div>
    <a href="recherche.php" class="btn-search" title="Recherche">🔍</a>
    <div style="flex:1;"></div>
    <a href="index.php" class="bh bh-red">🏠 Accueil</a>
    <a href="agenda.php" class="bh bh-navy">📅 Agenda</a>
    <a href="planning.php" class="bh bh-blue">📊 Planning</a>
    <a href="grille_semaine.php" class="bh bh-blue">📋 Grille</a>
    <a href="biologie.php<?= $id ? '?id='.$id : '' ?>" class="bh bh-orange">🧪 Biologie</a>
    <span class="bh" style="background:#555;">🧾 Facturation</span>
    <a href="impression.php<?= $id ? '?id='.$id : '' ?>" class="bh bh-green">🖨️ Impression</a>
    <a href="jours_feries.php" class="bh bh-purple">📅 Fériés</a>
    <div class="header-clock">
        <div class="ct" id="clockTime">--:--:--</div>
        <div class="cd" id="clockDate">---</div>
    </div>
    <a href="logout.php" class="bh bh-red" title="Déconnexion">⏻</a>
</div>

<div class="page">
<div class="carte">
<div class="carte-titre no-print">🧾 Facturation</div>

<div class="zrech no-print">
    <input type="text" id="rech-npat" placeholder="N° patient..." value="<?= $id ?: '' ?>"
           onkeydown="if(event.key==='Enter') allerPatient()">
    <button onclick="allerPatient()">🔍 Charger</button>
</div>

<?php if (!$patient): ?>
    <p style="color:var(--th-color-text-muted);">Entrez un numéro de patient, ou utilisez la <a href="recherche.php">recherche</a> pour trouver un patient par nom.</p>
<?php else: ?>

    <div class="patient-bar no-print">
        <div class="info"><label>N°</label><span><?= $patient['N°PAT'] ?></span></div>
        <div class="info"><label>Nom</label><span><?= htmlspecialchars($patient['NOMPRENOM']) ?></span></div>
        <div class="info"><label>Tel</label><span><?= htmlspecialchars($patient['TEL D'] ?? '—') ?></span></div>
        <div class="info"><label>Mutuelle</label><span><?= htmlspecialchars($patient['MUTUELLE'] ?? '—') ?></span></div>
    </div>

    <div id="zone-impression">
        <div style="text-align:center;margin-bottom:10px;">
            <strong>Facture — <?= htmlspecialchars($patient['NOMPRENOM']) ?></strong><br>
            <span style="font-size:11px;color:#888;">N° patient <?= $patient['N°PAT'] ?></span>
        </div>

        <?php if ($factCourante): ?>
        <?php $tsFA=strtotime($factCourante['date_facture']??''); $dfA=($tsFA&&$tsFA>86400)?date('d/m/Y',$tsFA):'—'; ?>
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <span style="font-size:12px;font-weight:bold;color:var(--th-color-primary);">Facture N° <?= $factCourante['n_facture'] ?></span>
            <span style="font-size:12px;font-weight:bold;"><?= $dfA ?></span>
        </div>
        <table class="fact">
            <thead><tr><th style="text-align:left;">Date acte</th><th style="text-align:left;">Acte</th><th style="text-align:right;">Versé</th><th style="text-align:right;">Reste</th></tr></thead>
            <tbody>
            <?php foreach ($detailActes as $da): ?>
                <tr>
                    <td><?= $da['date-H'] ? date('d/m/Y',strtotime($da['date-H'])) : '—' ?></td>
                    <td><?= htmlspecialchars($da['nom_acte'] ?? 'Acte '.$da['ACTE']) ?></td>
                    <td style="text-align:right;"><?= number_format($da['Versé'],0,',',' ') ?></td>
                    <td style="text-align:right;color:<?=$da['dette']>0?'#e74c3c':'#27ae60'?>;"><?= number_format($da['dette'],0,',',' ') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2">Total</td>
                    <td style="text-align:right;"><?= number_format($factCourante['verse_total'],0,',',' ') ?> DH</td>
                    <td style="text-align:right;color:<?=$factCourante['dette_total']>0?'#e74c3c':'#27ae60'?>;"><?= number_format($factCourante['dette_total'],0,',',' ') ?> DH</td>
                </tr>
            </tfoot>
        </table>
        <div class="no-print" style="display:flex;justify-content:center;gap:4px;margin-top:8px;flex-wrap:wrap;">
            <a href="?id=<?= $id ?>&fact=<?= $factPremiere ?>" class="nav-btn">|◀</a>
            <a href="?id=<?= $id ?>&fact=<?= $factPrev ?>" class="nav-btn">◀</a>
            <span style="font-size:11px;color:var(--th-color-primary);font-weight:bold;padding:2px 6px;"><?= $idxFact+1 ?> / <?= count($factures) ?></span>
            <a href="?id=<?= $id ?>&fact=<?= $factNext ?>" class="nav-btn">▶</a>
            <a href="?id=<?= $id ?>&fact=<?= $factDerniere ?>" class="nav-btn">▶|</a>
            <a href="print_facture.php?id=<?= $id ?>&fact=<?= $factCourante['n_facture'] ?>" target="_blank" class="nav-btn" style="background:#2e6da4;color:white;text-decoration:none;">🖨️ Imprimer</a>
            <button type="button" onclick="toggleNouvelleFacture()" class="nav-btn" style="background:#27ae60;color:white;">✚ Nouvelle facture</button>
        </div>
        <?php else: ?>
        <p style="color:var(--th-color-text-muted);">Aucune facture pour ce patient.</p>
        <div class="no-print" style="display:flex;justify-content:center;margin-top:8px;">
            <button type="button" onclick="toggleNouvelleFacture()" class="nav-btn" style="background:#27ae60;color:white;">✚ Nouvelle facture</button>
        </div>
        <?php endif; ?>
    </div>

    <!-- FORMULAIRE NOUVELLE FACTURE -->
    <div id="formNouvelleFacture" class="no-print" style="display:none;margin-top:14px;border-top:2px solid #1a4a7a;padding-top:10px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
            <strong style="color:var(--th-color-primary);font-size:13px;">Nouvelle facture</strong>
            <button type="button" onclick="toggleNouvelleFacture()" style="background:none;border:none;cursor:pointer;font-size:14px;">✕</button>
        </div>
        <div style="margin-bottom:8px;">
            <label style="font-size:11px;font-weight:600;">Date facture :</label>
            <input type="date" id="nf_date" value="<?= date('Y-m-d') ?>" style="margin-left:8px;border:1px solid #cdd5de;border-radius:3px;padding:3px 6px;font-size:12px;">
        </div>
        <table class="fact">
            <thead><tr><th style="text-align:left;">Date acte</th><th style="text-align:left;">Acte</th><th style="text-align:right;">Versé</th><th style="text-align:right;">Reste</th><th></th></tr></thead>
            <tbody id="nf_lignes"></tbody>
            <tfoot>
                <tr>
                    <td colspan="2">Total</td>
                    <td style="text-align:right;" id="nf_totalVerse">0 DH</td>
                    <td style="text-align:right;color:#c0392b;" id="nf_totalDette">0 DH</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
        <div style="display:flex;gap:8px;margin-top:8px;">
            <button type="button" onclick="nfAjouterLigne()" style="background:#2e6da4;color:white;border:none;border-radius:3px;padding:5px 12px;cursor:pointer;font-size:11px;">✚ Acte</button>
            <button type="button" onclick="nfEnregistrer()" style="background:#1a4a7a;color:white;border:none;border-radius:3px;padding:5px 14px;cursor:pointer;font-size:11px;font-weight:600;">💾 Enregistrer</button>
            <span id="nf_msg" style="font-size:11px;align-self:center;"></span>
        </div>
    </div>

<?php endif; ?>
</div>
</div>

<script>
function allerPatient() {
    const v = document.getElementById('rech-npat').value.trim();
    if (v) window.location.href = 'facturation.php?id=' + encodeURIComponent(v);
}

const nfActes = <?= json_encode(array_map(fn($a)=>['n_acte'=>$a['n_acte'],'ACTE'=>$a['ACTE'],'cout'=>(float)$a['cout']],$listeActes)) ?>;
let nfIdx = 0;

function toggleNouvelleFacture() {
    const form = document.getElementById('formNouvelleFacture');
    const visible = form.style.display !== 'none';
    form.style.display = visible ? 'none' : 'block';
    if (!visible && document.getElementById('nf_lignes').children.length === 0) nfAjouterLigne();
}

function nfAjouterLigne() {
    const i = nfIdx++;
    const today = document.getElementById('nf_date').value;
    let opts = '<option value="">— Acte —</option>';
    nfActes.forEach(a => { opts += `<option value="${a.n_acte}" data-cout="${a.cout}">${a.ACTE}</option>`; });
    const tr = document.createElement('tr');
    tr.innerHTML = `<td><input type="date" id="nf_dateacte_${i}" value="${today}" style="border:1px solid #ddd;border-radius:3px;padding:2px;font-size:11px;width:105px;"></td>
        <td><select id="nf_acte_${i}" onchange="nfRemplirPrix(${i})" style="width:100%;border:1px solid #ddd;border-radius:3px;padding:2px;font-size:11px;">${opts}</select></td>
        <input type="hidden" id="nf_prix_${i}" value="">
        <td><input type="number" id="nf_verse_${i}" min="0" step="0.01" value="0" oninput="nfRecalculer(${i})" style="width:70px;border:1px solid #ddd;border-radius:3px;padding:2px;font-size:11px;text-align:right;"></td>
        <td style="text-align:right;font-weight:600;color:#c0392b;" id="nf_dette_${i}">0</td>
        <td><button type="button" onclick="this.closest('tr').remove();nfMajTotaux()" style="background:#e74c3c;color:white;border:none;border-radius:3px;padding:2px 6px;cursor:pointer;font-size:10px;">✕</button></td>`;
    document.getElementById('nf_lignes').appendChild(tr);
}

function nfRemplirPrix(i) {
    const sel = document.getElementById(`nf_acte_${i}`);
    const cout = sel.options[sel.selectedIndex]?.getAttribute('data-cout') || '';
    document.getElementById(`nf_prix_${i}`).value = cout;
    const verseEl = document.getElementById(`nf_verse_${i}`);
    if (verseEl) verseEl.value = cout ? parseFloat(cout) : 0;
    nfRecalculer(i);
}

function nfRecalculer(i) {
    const prix  = parseFloat(document.getElementById(`nf_prix_${i}`)?.value) || 0;
    const verse = parseFloat(document.getElementById(`nf_verse_${i}`)?.value) || 0;
    const el = document.getElementById(`nf_dette_${i}`);
    if (el) el.textContent = (prix - verse).toLocaleString('fr-FR') + ' DH';
    nfMajTotaux();
}

function nfMajTotaux() {
    let tv = 0, td = 0;
    document.querySelectorAll('#nf_lignes tr').forEach(tr => {
        const sel = tr.querySelector('select');
        if (!sel) return;
        const idx = sel.id.replace('nf_acte_', '');
        const p = parseFloat(document.getElementById(`nf_prix_${idx}`)?.value) || 0;
        const v = parseFloat(document.getElementById(`nf_verse_${idx}`)?.value) || 0;
        tv += v; td += (p - v);
    });
    document.getElementById('nf_totalVerse').textContent = tv.toLocaleString('fr-FR') + ' DH';
    document.getElementById('nf_totalDette').textContent = td.toLocaleString('fr-FR') + ' DH';
}

function nfEnregistrer() {
    const patientId = <?= $id ?: 0 ?>;
    const date_facture = document.getElementById('nf_date').value;
    const lignes = [];
    document.querySelectorAll('#nf_lignes tr').forEach(tr => {
        const sel = tr.querySelector('select');
        if (!sel) return;
        const idx = sel.id.replace('nf_acte_', '');
        const acte  = document.getElementById(`nf_acte_${idx}`)?.value;
        const prix  = parseFloat(document.getElementById(`nf_prix_${idx}`)?.value) || 0;
        const verse = parseFloat(document.getElementById(`nf_verse_${idx}`)?.value) || 0;
        const dateA = document.getElementById(`nf_dateacte_${idx}`)?.value;
        if (acte) lignes.push({acte, prix, verse, date_acte: dateA});
    });
    const msgEl = document.getElementById('nf_msg');
    if (lignes.length === 0) { msgEl.textContent = '⚠ Ajoutez au moins un acte.'; msgEl.style.color = '#e74c3c'; return; }
    msgEl.textContent = 'Enregistrement…'; msgEl.style.color = '#999';
    fetch('ajax_nouvelle_facture.php', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({id: patientId, date_facture, lignes})})
    .then(r => r.json()).then(data => {
        if (data.success) window.location.href = `facturation.php?id=${patientId}&fact=${data.n_facture}`;
        else { msgEl.textContent = '❌ ' + data.error; msgEl.style.color = '#e74c3c'; }
    }).catch(() => { msgEl.textContent = '❌ Erreur réseau'; msgEl.style.color = '#e74c3c'; });
}
</script>
</body>
</html>
