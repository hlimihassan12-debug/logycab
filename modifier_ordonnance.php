<?php
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';
$db = getDB();

$id   = (int)($_GET['id']  ?? 0);
$nOrd = (int)($_GET['ord'] ?? 0);
if ($id == 0 || $nOrd == 0) { header('Location: recherche.php'); exit; }

// Patient
$stmtPat = $db->prepare("SELECT * FROM ID WHERE [N°PAT] = ?");
$stmtPat->execute([$id]);
$patient = $stmtPat->fetch();
if (!$patient) { die("❌ Patient introuvable !"); }

// Ordonnance
$stmtOrd = $db->prepare("SELECT * FROM ORD WHERE n_ordon = ? AND id = ?");
$stmtOrd->execute([$nOrd, $id]);
$ord = $stmtOrd->fetch();
if (!$ord) { die("❌ Ordonnance introuvable !"); }

// Médicaments de cette ordonnance
$stmtMeds = $db->prepare("SELECT p.*, pr.PRODUIT AS nom_produit FROM PROD p LEFT JOIN PRODUITS pr ON p.produit = pr.NuméroPRODUIT WHERE p.N_ord = ? ORDER BY p.Ordre");
$stmtMeds->execute([$nOrd]);
$medicaments = $stmtMeds->fetchAll();

// Listes
$listeMeds = $db->query("SELECT NuméroPRODUIT, PRODUIT FROM PRODUITS ORDER BY PRODUIT")->fetchAll();
$posologies = [
    '1 cp 1 fois par jour','1 cp 1 jour sur deux','1 cp 2 fois par jour',
    '1 cp 3 fois par jour','1 cp 4 fois par jour','1 cp alterné avec 1cp + 1/4 cp',
    '1 gel 1 fois par jour','1 gel 2 fois par jour','1 gel 3 fois par jour','1 gel 4 fois par jour',
    '1 sachet 1 x par jour','1 sachet 3 x par jour',
    '1/2 cp 1 fois par jour','1/2 cp 1 jour sur deux','1/2 cp 2 fois par jour',
    '1/2 cp 3 fois par jour','1/2 cp 4 fois par jour','1/2 cp par jour',
    '1/4 cp 1 fois par jour','1/4 cp 1 jour sur deux','1/4 cp 2 fois par jour',
    '1/4 cp 3 fois par jour','1/4 cp 4 fois par jour',
    '1/4 cp alterné avec 1/2 cp','1/4 cp alterné avec rien',
    '2 cp 1 fois par jour','2 cp 2 fois par jour','2 cp 3 fois par jour',
    '3 cp 1 fois par jour','3/4 cp 1 fois par jour','3/4 cp alterné avec 1 cp','4 gel 1 fois par jour',
];
$durees = ['1 semaine','2 semaines','1 mois','2 mois','3 mois','6 mois'];

// Dates formatées pour les inputs
$dateOrdVal = '';
if (!empty($ord['date_ordon'])) {
    $ts = strtotime($ord['date_ordon']);
    if ($ts && $ts > 86400) $dateOrdVal = date('Y-m-d', $ts);
}
$dateRdvVal = '';
if (!empty($ord['DATE REDEZ VOUS'])) {
    $ts = strtotime($ord['DATE REDEZ VOUS']);
    if ($ts && $ts > 86400) $dateRdvVal = date('Y-m-d', $ts);
}
$heureRdvVal = trim($ord['HeureRDV'] ?? '');
$acteVal     = trim($ord['acte1'] ?? '');

$age = '';
if ($patient['DDN']) {
    $naissance = new DateTime($patient['DDN']);
    $age = $naissance->diff(new DateTime())->y;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Modifier Ordonnance N°<?= $nOrd ?></title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Arial, sans-serif; background: #f0f4f8; font-size: 13px; }

.header { background: #1a4a7a; color: white; padding: 8px 16px; display: flex; align-items: center; gap: 10px; }
.header h1 { font-size: 15px; flex: 1; }
.btn-header { color: white; text-decoration: none; background: #2e6da4; padding: 5px 12px; border-radius: 4px; font-size: 12px; border: none; cursor: pointer; }
.btn-header:hover { background: #3a7bc8; }
.btn-header.orange { background: #e67e22; }
.btn-header.red    { background: #e74c3c; }

.patient-bar { background: #000; color: #FFD700; padding: 6px 16px; display: flex; gap: 20px; flex-wrap: wrap; font-size: 12px; }
.patient-bar label { font-size: 10px; opacity: 0.8; text-transform: uppercase; display: block; color: #FFD700; }
.patient-bar span  { font-weight: bold; color: #FFD700; }

.container { max-width: 900px; margin: 20px auto; padding: 0 12px; display: flex; flex-direction: column; gap: 14px; }

.card { background: white; border-radius: 8px; padding: 16px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); }
.card-title { color: #1a4a7a; font-size: 13px; font-weight: bold; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 2px solid #e0e0e0; display: flex; align-items: center; gap: 8px; }

.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.form-group { display: flex; flex-direction: column; gap: 4px; }
.form-group.full { grid-column: 1 / -1; }
.form-group label { font-size: 10px; color: #888; text-transform: uppercase; font-weight: bold; }
.form-group input, .form-group select {
    padding: 6px 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px;
    transition: border-color 0.2s;
}
.form-group input:focus, .form-group select:focus { outline: none; border-color: #2e6da4; }

/* RDV section */
.rdv-section { background: #f8f0ff; border: 1px solid #c9a0f0; border-radius: 6px; padding: 12px; }
.rdv-section label { font-size: 10px; color: #8e44ad; font-weight: bold; text-transform: uppercase; display: block; margin-bottom: 6px; }
.delai-btns { display: flex; gap: 4px; flex-wrap: wrap; margin-bottom: 8px; }
.delai-btn { padding: 3px 10px; border: 1px solid #8e44ad; border-radius: 3px; cursor: pointer; font-size: 11px; background: white; color: #8e44ad; }
.delai-btn:hover, .delai-btn.actif { background: #8e44ad; color: white; }
.rdv-row { display: flex; gap: 8px; align-items: center; margin-bottom: 6px; }
.rdv-row input[type=date] { flex: 1; padding: 5px 8px; border: 1px solid #8e44ad; border-radius: 4px; font-size: 12px; }
.heure-badge { background: #e8d5f5; color: #8e44ad; padding: 5px 12px; border-radius: 4px; font-size: 13px; font-weight: bold; white-space: nowrap; min-width: 70px; text-align: center; }

.jauge-jour { display: flex; align-items: center; gap: 6px; margin-bottom: 6px; font-size: 11px; }
.jauge-bar  { flex: 1; height: 8px; background: #e0e0e0; border-radius: 4px; overflow: hidden; }
.jauge-fill { height: 100%; border-radius: 4px; transition: width 0.3s; }
.jauge-fill.ok   { background: #27ae60; }
.jauge-fill.warn { background: #f39c12; }
.jauge-fill.full { background: #e74c3c; }

.creneaux-grille  { display: flex; flex-wrap: wrap; gap: 3px; margin-top: 4px; }
.creneaux-loading { font-size: 11px; color: #888; font-style: italic; }
.creneaux-msg     { font-size: 11px; color: #e74c3c; font-weight: bold; }
.creneau-btn { padding: 3px 7px; border-radius: 3px; border: 2px solid transparent; cursor: pointer; font-size: 11px; font-weight: bold; min-width: 48px; text-align: center; }
.creneau-btn.libre  { background: #27ae60; color: white; border-color: #1e8449; }
.creneau-btn.moyen  { background: #f39c12; color: white; border-color: #d68910; }
.creneau-btn.plein  { background: #e74c3c; color: #fdd; border-color: #c0392b; cursor: not-allowed; opacity: 0.7; }
.creneau-btn.selectionne { border-color: #1a4a7a !important; box-shadow: 0 0 0 3px rgba(26,74,122,0.35); }

/* Actes boutons */
.actes-btns { display: flex; gap: 4px; flex-wrap: wrap; margin-top: 6px; }
.acte-btn { background: #8e44ad; color: white; border: none; padding: 3px 8px; border-radius: 3px; cursor: pointer; font-size: 11px; }
.acte-btn:hover { background: #7d3c98; }

/* Médicaments */
.meds-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.meds-table th { background: #1a4a7a; color: white; padding: 6px 8px; text-align: left; font-size: 11px; }
.meds-table td { padding: 4px 6px; border-bottom: 1px solid #eee; vertical-align: middle; }
.meds-table select { width: 100%; border: 1px solid #ddd; border-radius: 3px; padding: 3px 4px; font-size: 11px; }
.btn-del { background: #e74c3c; color: white; border: none; border-radius: 3px; padding: 2px 8px; cursor: pointer; font-size: 11px; }
.btn-add { background: #27ae60; color: white; border: none; border-radius: 4px; padding: 6px 14px; cursor: pointer; font-size: 12px; margin-top: 8px; }

/* Actions bas */
.actions { display: flex; gap: 10px; align-items: center; justify-content: flex-end; padding: 10px 0; }
.btn-save { background: #1a4a7a; color: white; border: none; border-radius: 6px; padding: 10px 28px; cursor: pointer; font-size: 14px; font-weight: bold; }
.btn-save:hover { background: #2e6da4; }
.btn-cancel { background: #95a5a6; color: white; border: none; border-radius: 6px; padding: 10px 20px; cursor: pointer; font-size: 13px; text-decoration: none; }
.msg-status { font-size: 12px; font-weight: bold; }
.msg-status.ok  { color: #27ae60; }
.msg-status.err { color: #e74c3c; }
</style>
</head>
<body>

<!-- HEADER -->
<div class="header">
    <a href="dossier.php?id=<?= $id ?>&ord=<?= $nOrd ?>" class="btn-header">◀ Retour dossier</a>
    <h1>✏️ Modifier ordonnance N°<?= $nOrd ?></h1>
</div>

<!-- BANDEAU PATIENT -->
<div class="patient-bar">
    <div><label>N°</label><span><?= $id ?></span></div>
    <div><label>Nom</label><span><?= htmlspecialchars($patient['NOMPRENOM']) ?></span></div>
    <div><label>Âge</label><span><?= $age ?> ans</span></div>
    <div><label>DDN</label><span><?= $patient['DDN'] ? date('d/m/Y', strtotime($patient['DDN'])) : '—' ?></span></div>
</div>

<div class="container">

    <!-- ══ DATE ORDONNANCE + ACTE ══ -->
    <div class="card">
        <div class="card-title">📋 Informations ordonnance</div>
        <div class="form-grid">
            <div class="form-group">
                <label>Date de l'ordonnance</label>
                <input type="date" id="date_ordon" value="<?= $dateOrdVal ?>">
            </div>
            <div class="form-group">
                <label>Acte</label>
                <input type="text" id="acte" value="<?= htmlspecialchars($acteVal) ?>" placeholder="ECG, EDC, CONTROL...">
                <div class="actes-btns">
                    <?php foreach (['ECG','EDC','ECG+EDC','ECG+EDC+DTSA','DTSA','DVMI','BILAN','CONTROL','DAMI'] as $ba): ?>
                    <button type="button" class="acte-btn" onclick="document.getElementById('acte').value='<?= $ba ?>'"><?= $ba ?></button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ══ DATE ET HEURE RDV ══ -->
    <div class="card">
        <div class="card-title">📅 Date &amp; Heure RDV</div>
        <div class="rdv-section">
            <label>Choisir le délai ou saisir directement</label>
            <div class="delai-btns">
                <button type="button" class="delai-btn" onclick="rdvSetDelai(1,0)">1M</button>
                <button type="button" class="delai-btn" onclick="rdvSetDelai(3,0)">3M</button>
                <button type="button" class="delai-btn" onclick="rdvSetDelai(6,0)">6M</button>
                <button type="button" class="delai-btn" onclick="rdvSetDelai(0,7)">7J</button>
                <button type="button" class="delai-btn" onclick="rdvSetDelai(0,15)">15J</button>
                <button type="button" class="delai-btn" onclick="rdvSetDelai(0,21)">21J</button>
            </div>
            <div class="rdv-row">
                <input type="date" id="date_rdv" value="<?= $dateRdvVal ?>" onchange="chargerCreneaux(this.value, true)">
                <div class="heure-badge" id="heure_badge"><?= $heureRdvVal ?: '—:——' ?></div>
            </div>
            <input type="hidden" id="heure_rdv" value="<?= htmlspecialchars($heureRdvVal) ?>">

            <div class="jauge-jour" id="jauge" style="display:none;">
                <span id="jauge_txt" style="white-space:nowrap;color:#555;font-size:10px;"></span>
                <div class="jauge-bar"><div class="jauge-fill ok" id="jauge_fill" style="width:0%"></div></div>
            </div>
            <div class="creneaux-loading" id="rdv_loading" style="display:none;">⏳ Chargement…</div>
            <div class="creneaux-msg"     id="rdv_msg"     style="display:none;"></div>
            <div class="creneaux-grille"  id="rdv_grille"></div>
        </div>
    </div>

    <!-- ══ MÉDICAMENTS ══ -->
    <div class="card">
        <div class="card-title">💊 Médicaments</div>
        <table class="meds-table">
            <thead>
                <tr>
                    <th style="width:40%;">Médicament</th>
                    <th style="width:35%;">Posologie</th>
                    <th style="width:18%;">Durée</th>
                    <th style="width:7%;"></th>
                </tr>
            </thead>
            <tbody id="meds_tbody">
            <?php foreach ($medicaments as $idx => $med): ?>
            <tr id="med_row_<?= $idx ?>">
                <td>
                    <select name="med_produit_<?= $idx ?>" class="med-produit">
                        <option value="">— Médicament —</option>
                        <?php foreach ($listeMeds as $lm): ?>
                        <option value="<?= $lm['NuméroPRODUIT'] ?>" <?= $lm['NuméroPRODUIT'] == $med['produit'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($lm['PRODUIT']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <select name="med_poso_<?= $idx ?>" class="med-poso">
                        <option value="">— Posologie —</option>
                        <?php foreach ($posologies as $p): ?>
                        <option value="<?= htmlspecialchars($p) ?>" <?= $p === $med['posologie'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <select name="med_duree_<?= $idx ?>" class="med-duree">
                        <option value="">— Durée —</option>
                        <?php foreach ($durees as $d): ?>
                        <option value="<?= htmlspecialchars($d) ?>" <?= $d === $med['DUREE'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($d) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td><button type="button" class="btn-del" onclick="this.closest('tr').remove()">✕</button></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <button type="button" class="btn-add" onclick="ajouterLigne()">✚ Ajouter médicament</button>
    </div>

    <!-- ══ ACTIONS ══ -->
    <div class="actions">
        <span class="msg-status" id="msg_status"></span>
        <a href="dossier.php?id=<?= $id ?>&ord=<?= $nOrd ?>" class="btn-cancel">Annuler</a>
        <button type="button" class="btn-save" onclick="enregistrer()">💾 Enregistrer</button>
    </div>

</div><!-- fin container -->

<script>
const listeMeds   = <?= json_encode(array_map(fn($m)=>['id'=>$m['NuméroPRODUIT'],'nom'=>$m['PRODUIT']], $listeMeds)) ?>;
const posologies  = <?= json_encode($posologies) ?>;
const durees      = <?= json_encode($durees) ?>;

// ── Créneaux ─────────────────────────────────────────────────────────────
function chargerCreneaux(date, heureAuto) {
    if (!date || !/^\d{4}-\d{2}-\d{2}$/.test(date) || date === '1970-01-01') return;

    const grille  = document.getElementById('rdv_grille');
    const loading = document.getElementById('rdv_loading');
    const msgEl   = document.getElementById('rdv_msg');
    const jaugeEl = document.getElementById('jauge');

    grille.innerHTML = '';
    msgEl.style.display   = 'none';
    loading.style.display = 'block';
    jaugeEl.style.display = 'none';

    if (heureAuto) {
        document.getElementById('heure_rdv').value = '';
        document.getElementById('heure_badge').textContent = '—:——';
    }

    fetch('ajax_creneaux.php?date=' + date)
    .then(r => r.json())
    .then(data => {
        loading.style.display = 'none';

        if (!data.date_ok) {
            msgEl.textContent   = '⛔ ' + data.raison;
            msgEl.style.display = 'block';
            document.getElementById('date_rdv').value  = '';
            document.getElementById('heure_rdv').value = '';
            document.getElementById('heure_badge').textContent = '—:——';
            return;
        }

        if (data.jour_complet) {
            msgEl.textContent   = '⛔ Journée complète (' + data.total_jour + '/' + data.max_jour + ' patients).';
            msgEl.style.display = 'block';
        }

        // Jauge
        const pct = Math.min(100, Math.round(data.total_jour / data.max_jour * 100));
        const cl  = pct < 60 ? 'ok' : pct < 90 ? 'warn' : 'full';
        document.getElementById('jauge_txt').textContent = data.total_jour + ' / ' + data.max_jour + ' patients';
        const fill = document.getElementById('jauge_fill');
        fill.style.width = pct + '%';
        fill.className   = 'jauge-fill ' + cl;
        jaugeEl.style.display = 'flex';

        // Créneaux
        const heureActuelle = document.getElementById('heure_rdv').value;
        data.creneaux.forEach(c => {
            const btn = document.createElement('button');
            btn.type        = 'button';
            btn.textContent = c.heure;
            btn.className   = 'creneau-btn ' + c.statut;
            btn.title       = c.nb + ' patient(s)';
            if (c.statut === 'plein') {
                btn.disabled = true;
            } else {
                btn.onclick = () => selectionnerCreneau(c.heure);
            }
            if (c.heure === heureActuelle) btn.classList.add('selectionne');
            grille.appendChild(btn);
        });

        // Auto-sélection premier créneau libre
        if (heureAuto && data.premier_libre) {
            selectionnerCreneau(data.premier_libre);
        }
    })
    .catch(() => {
        loading.style.display = 'none';
        msgEl.textContent     = '❌ Erreur de connexion';
        msgEl.style.display   = 'block';
    });
}

function selectionnerCreneau(heure) {
    document.getElementById('heure_rdv').value       = heure;
    document.getElementById('heure_badge').textContent = heure;
    document.querySelectorAll('.creneau-btn').forEach(b => {
        b.classList.remove('selectionne');
        if (b.textContent === heure && !b.disabled) b.classList.add('selectionne');
    });
}

function rdvSetDelai(mois, jours) {
    if (mois > 0) {
        const loading = document.getElementById('rdv_loading');
        const msgEl   = document.getElementById('rdv_msg');
        const grille  = document.getElementById('rdv_grille');
        const d = new Date();
        d.setMonth(d.getMonth() + mois);
        const dateCible = d.toISOString().split('T')[0];
        grille.innerHTML = '';
        msgEl.style.display   = 'none';
        loading.style.display = 'block';
        loading.textContent   = '⏳ Recherche…';
        fetch('ajax_prochain_jour.php', {
            method: 'POST', headers: {'Content-Type':'application/json'},
            body: JSON.stringify({date_cible: dateCible})
        })
        .then(r => r.json())
        .then(data => {
            loading.style.display = 'none';
            loading.textContent   = '⏳ Chargement…';
            if (data.error) { msgEl.textContent = '⛔ ' + data.error; msgEl.style.display = 'block'; return; }
            document.getElementById('date_rdv').value = data.date_trouvee;
            chargerCreneaux(data.date_trouvee, true);
        })
        .catch(() => { loading.style.display = 'none'; loading.textContent = '⏳ Chargement…'; });
    } else {
        const d = new Date();
        d.setDate(d.getDate() + jours);
        const date = d.toISOString().split('T')[0];
        document.getElementById('date_rdv').value = date;
        chargerCreneaux(date, true);
    }
}

// ── Médicaments ───────────────────────────────────────────────────────────
let rowIdx = <?= count($medicaments) ?>;

function ajouterLigne() {
    const tbody = document.getElementById('meds_tbody');
    const i = rowIdx++;

    let optsMed = '<option value="">— Médicament —</option>';
    listeMeds.forEach(m => { optsMed += `<option value="${m.id}">${m.nom}</option>`; });

    let optsPoso = '<option value="">— Posologie —</option>';
    posologies.forEach(p => { optsPoso += `<option value="${p}">${p}</option>`; });

    let optsDuree = '<option value="">— Durée —</option>';
    durees.forEach(d => { optsDuree += `<option value="${d}">${d}</option>`; });

    const tr = document.createElement('tr');
    tr.id = 'med_row_' + i;
    tr.innerHTML = `
        <td><select class="med-produit" style="width:100%;border:1px solid #ddd;border-radius:3px;padding:3px;font-size:11px;">${optsMed}</select></td>
        <td><select class="med-poso"    style="width:100%;border:1px solid #ddd;border-radius:3px;padding:3px;font-size:11px;">${optsPoso}</select></td>
        <td><select class="med-duree"   style="width:100%;border:1px solid #ddd;border-radius:3px;padding:3px;font-size:11px;">${optsDuree}</select></td>
        <td><button type="button" class="btn-del" onclick="this.closest('tr').remove()">✕</button></td>`;
    tbody.appendChild(tr);
}

// ── Enregistrer ───────────────────────────────────────────────────────────
function enregistrer() {
    const msgEl = document.getElementById('msg_status');

    const date_ordon = document.getElementById('date_ordon').value;
    const acte       = document.getElementById('acte').value;
    const date_rdv   = document.getElementById('date_rdv').value;
    const heure_rdv  = document.getElementById('heure_rdv').value;

    if (!date_ordon) { msgEl.textContent = '⛔ La date de l\'ordonnance est obligatoire.'; msgEl.className = 'msg-status err'; document.getElementById('date_ordon').focus(); return; }
    if (!date_rdv)   { msgEl.textContent = '⛔ La date de RDV est obligatoire.';           msgEl.className = 'msg-status err'; document.getElementById('date_rdv').focus();   return; }
    if (!heure_rdv)  { msgEl.textContent = '⛔ L\'heure de RDV est obligatoire.';           msgEl.className = 'msg-status err'; return; }

    // Collecter médicaments
    const lignes = [];
    document.querySelectorAll('#meds_tbody tr').forEach(tr => {
        const produit = tr.querySelector('.med-produit')?.value;
        const poso    = tr.querySelector('.med-poso')?.value;
        const duree   = tr.querySelector('.med-duree')?.value;
        if (produit) lignes.push({ produit, poso, duree });
    });

    msgEl.textContent = '⏳ Enregistrement…';
    msgEl.className   = 'msg-status';

    fetch('ajax_modifier_ordonnance.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            n_ordon: <?= $nOrd ?>,
            id: <?= $id ?>,
            date_ordon,
            acte,
            date_rdv,
            heure_rdv,
            lignes
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            msgEl.textContent = '✅ Enregistré !';
            msgEl.className   = 'msg-status ok';
            setTimeout(() => {
                window.location.href = 'dossier.php?id=<?= $id ?>&ord=<?= $nOrd ?>';
            }, 800);
        } else {
            msgEl.textContent = '❌ ' + data.error;
            msgEl.className   = 'msg-status err';
        }
    })
    .catch(() => {
        msgEl.textContent = '❌ Erreur réseau';
        msgEl.className   = 'msg-status err';
    });
}

// Charger créneaux au démarrage si date RDV existe
document.addEventListener('DOMContentLoaded', () => {
    const dateInit = document.getElementById('date_rdv').value;
    if (dateInit && dateInit !== '1970-01-01') chargerCreneaux(dateInit, false);
});
</script>
</body>
</html>