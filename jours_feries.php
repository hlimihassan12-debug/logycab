<?php
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';
$db = getDB();

$stmtF = $db->query("SELECT DateFerie FROM T_JourFeries ORDER BY DateFerie");
$feriesRaw = $stmtF->fetchAll(PDO::FETCH_COLUMN);

$feries = [];
foreach ($feriesRaw as $f) {
    $f = trim($f);
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $f, $m)) {
        $feries[] = $m[3].'-'.$m[2].'-'.$m[1];
    } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f)) {
        $feries[] = $f;
    } else {
        $ts = strtotime($f);
        if ($ts) $feries[] = date('Y-m-d', $ts);
    }
}
sort($feries);

function formatFr($date) {
    $jours = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
    $mois  = ['Janvier','Février','Mars','Avril','Mai','Juin',
               'Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
    $ts = strtotime($date . ' 12:00:00');
    if (!$ts) return $date;
    return $jours[(int)date('w',$ts)] . ' ' . (int)date('j',$ts) . ' '
         . $mois[(int)date('n',$ts)-1] . ' ' . date('Y',$ts);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Jours Fériés</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Arial, sans-serif; background: #f0f4f8; font-size: 13px; }

.header { background: #1a4a7a; color: white; padding: 6px 14px;
          display: flex; align-items: center; gap: 8px; flex-wrap: nowrap; }
.header h1 { font-size: 14px; white-space: nowrap; }
.btn-h { color: white; text-decoration: none; border: none; cursor: pointer;
         padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: bold;
         display: inline-flex; align-items: center; height: 26px; white-space: nowrap; }
.btn-h.blue   { background: #2e6da4; }
.btn-h.green  { background: #27ae60; }
.btn-h.purple { background: #8e44ad; }
.btn-h.red    { background: #e74c3c; }
.btn-h:hover  { opacity: 0.85; }
.header-clock { margin-left: auto; background: rgba(255,255,255,0.12);
                border-radius: 6px; padding: 3px 10px; text-align: center;
                min-width: 130px; flex-shrink: 0; }
.header-clock .ct { font-size: 15px; font-weight: bold; letter-spacing: 1px; color: #f0f4f8; }
.header-clock .cd { font-size: 9px; opacity: 0.75; }

.container { max-width: 700px; margin: 24px auto; padding: 0 16px; }

.card { background: white; border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 20px; overflow: hidden; }
.card-header { background: #1a4a7a; color: white; padding: 10px 16px;
               font-size: 13px; font-weight: bold; }

/* Formulaire ajout */
.form-add { padding: 14px 16px; display: flex; gap: 10px; align-items: center;
            border-bottom: 1px solid #e0e8f0; }
.form-add input[type=date] { padding: 6px 10px; border: 1px solid #2e6da4;
    border-radius: 4px; font-size: 12px; cursor: pointer; }
.btn-add { background: #27ae60; color: white; border: none; border-radius: 4px;
           padding: 6px 16px; cursor: pointer; font-size: 12px; font-weight: bold; }
.btn-add:hover { opacity: 0.85; }

/* Liste */
.liste { padding: 8px 0; }
.item-ferie { display: flex; align-items: center; gap: 10px;
              padding: 7px 16px; border-bottom: 1px solid #f0f4f8; }
.item-ferie:last-child { border-bottom: none; }
.item-ferie:hover { background: #f8fafc; }

.badge-jour { font-size: 10px; font-weight: bold; padding: 2px 8px;
              border-radius: 10px; flex-shrink: 0; }
.badge-jour.dim { background: #fde8e8; color: #c0392b; }
.badge-jour.sam { background: #fff3cd; color: #856404; }
.badge-jour.lun { background: #e8f0fb; color: #2e6da4; }
.badge-jour.std { background: #e8f5e9; color: #27ae60; }

.item-label { flex: 1; color: #1a4a7a; font-weight: bold; }
.item-date  { font-size: 11px; color: #888; font-family: monospace; }
.btn-sup { background: #e74c3c; color: white; border: none; border-radius: 4px;
           padding: 2px 8px; cursor: pointer; font-size: 11px; font-weight: bold; }
.btn-sup:hover { opacity: 0.85; }

.empty { padding: 20px; text-align: center; color: #bbb; font-style: italic; }

/* Toast */
.toast { position: fixed; top: 16px; right: 16px; padding: 10px 18px;
         border-radius: 6px; font-size: 12px; font-weight: bold;
         z-index: 9999; display: none; color: white;
         box-shadow: 0 4px 12px rgba(0,0,0,0.25); }
.toast.show    { display: block; }
.toast.success { background: #27ae60; }
.toast.error   { background: #e74c3c; }

/* Compteur */
.compteur { padding: 10px 16px; font-size: 11px; color: #888;
            border-top: 1px solid #e0e8f0; text-align: right; }
</style>
</head>
<body>

<div class="header">
    <a href="recherche.php" class="btn-h green">🏠 Accueil</a>
    <a href="agenda.php"    class="btn-h blue">📅 Agenda</a>
    <a href="dossier.php"   class="btn-h blue">🩺 Dossier</a>
    <a href="planning.php"  class="btn-h blue">📊 Planning</a>
    <h1>📅 Jours Fériés &amp; Fermetures</h1>
    <!-- Horloge -->
    <div class="header-clock">
        <div class="ct" id="clockTime">--:--:--</div>
        <div class="cd" id="clockDate">---</div>
    </div>
</div>

<div class="container">
    <div class="card">
        <div class="card-header">➕ Ajouter un jour férié / fermeture</div>
        <div class="form-add">
            <input type="date" id="nouvelle-date">
            <button class="btn-add" onclick="ajouterFerie()">Ajouter</button>
            <span id="msg-add" style="font-size:11px;color:#e74c3c;"></span>
        </div>
    </div>

    <div class="card">
        <div class="card-header">📋 Liste des jours enregistrés</div>
        <div class="liste" id="liste-feries">
            <?php if (empty($feries)): ?>
            <div class="empty">Aucun jour enregistré</div>
            <?php else: ?>
            <?php foreach ($feries as $d):
                $ts  = strtotime($d . ' 12:00:00');
                $dow = (int)date('w', $ts);
                $noms = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
                $cl   = $dow===0 ? 'dim' : ($dow===6 ? 'sam' : ($dow===1 ? 'lun' : 'std'));
                $dFr  = date('d/m/Y', $ts);
            ?>
            <div class="item-ferie" id="row-<?= str_replace('-','',$d) ?>">
                <span class="badge-jour <?= $cl ?>"><?= $noms[$dow] ?></span>
                <span class="item-label"><?= formatFr($d) ?></span>
                <span class="item-date"><?= $dFr ?></span>
                <button class="btn-sup" onclick="supprimerFerie('<?= $d ?>')">🗑</button>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="compteur" id="compteur">
            <?= count($feries) ?> jour(s) enregistré(s)
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
const joursNoms = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
const moisNoms  = ['Janvier','Février','Mars','Avril','Mai','Juin',
                   'Juillet','Août','Septembre','Octobre','Novembre','Décembre'];

function toast(msg, type='success') {
    const el = document.getElementById('toast');
    el.textContent = msg;
    el.className = 'toast show ' + type;
    setTimeout(() => el.className = 'toast', 2800);
}

function formatFrJs(dateStr) {
    const d = new Date(dateStr + 'T12:00:00');
    return joursNoms[d.getDay()] + ' ' + d.getDate() + ' ' + moisNoms[d.getMonth()] + ' ' + d.getFullYear();
}

function badgeClass(dateStr) {
    const dow = new Date(dateStr + 'T12:00:00').getDay();
    return dow===0 ? 'dim' : dow===6 ? 'sam' : dow===1 ? 'lun' : 'std';
}

function dateFr(dateStr) {
    const [a,m,j] = dateStr.split('-');
    return j+'/'+m+'/'+a;
}

async function ajouterFerie() {
    const d   = document.getElementById('nouvelle-date').value;
    const msg = document.getElementById('msg-add');
    msg.textContent = '';
    if (!d) { msg.textContent = 'Choisissez une date.'; return; }

    const r = await fetch('ajax_jours_feries.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ action:'ajouter', date:d })
    });
    const data = await r.json();

    if (!data.ok) { msg.textContent = data.error || 'Erreur'; return; }

    // Ajouter la ligne dans le DOM sans recharger
    const dow  = new Date(d + 'T12:00:00').getDay();
    const cl   = badgeClass(d);
    const id   = 'row-' + d.replace(/-/g,'');
    const html = `
    <div class="item-ferie" id="${id}">
        <span class="badge-jour ${cl}">${joursNoms[dow]}</span>
        <span class="item-label">${formatFrJs(d)}</span>
        <span class="item-date">${dateFr(d)}</span>
        <button class="btn-sup" onclick="supprimerFerie('${d}')">🗑</button>
    </div>`;

    const liste = document.getElementById('liste-feries');
    // Supprimer le message "Aucun jour" si présent
    const empty = liste.querySelector('.empty');
    if (empty) empty.remove();

    // Insérer en ordre (simple : ajouter à la fin, rechargement pour tri)
    liste.insertAdjacentHTML('beforeend', html);
    document.getElementById('nouvelle-date').value = '';
    majCompteur(+1);
    toast('✅ ' + formatFrJs(d) + ' ajouté');
}

async function supprimerFerie(date) {
    if (!confirm('Supprimer ' + formatFrJs(date) + ' ?')) return;

    const r = await fetch('ajax_jours_feries.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ action:'supprimer', date:date })
    });
    const data = await r.json();
    if (!data.ok) { toast('Erreur suppression', 'error'); return; }

    const row = document.getElementById('row-' + date.replace(/-/g,''));
    if (row) row.remove();
    majCompteur(-1);
    toast('🗑 Supprimé');

    // Si liste vide, afficher message
    const liste = document.getElementById('liste-feries');
    if (!liste.querySelector('.item-ferie')) {
        liste.innerHTML = '<div class="empty">Aucun jour enregistré</div>';
    }
}

function majCompteur(delta) {
    const el = document.getElementById('compteur');
    const n  = parseInt(el.textContent) + delta;
    el.textContent = n + ' jour(s) enregistré(s)';
}

// ── Horloge ───────────────────────────────────────────
(function() {
    const jours = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
    const mois  = ['Jan','Fév','Mar','Avr','Mai','Juin','Juil','Aoû','Sep','Oct','Nov','Déc'];
    function tick() {
        const n  = new Date();
        const h  = String(n.getHours()).padStart(2,'0');
        const m  = String(n.getMinutes()).padStart(2,'0');
        const s  = String(n.getSeconds()).padStart(2,'0');
        const ct = document.getElementById('clockTime');
        const cd = document.getElementById('clockDate');
        if (ct) ct.textContent = h+':'+m+':'+s;
        if (cd) cd.textContent = jours[n.getDay()]+' '+n.getDate()+' '+mois[n.getMonth()]+' '+n.getFullYear();
    }
    tick();
    setInterval(tick, 1000);
})();
</script>
</body>
</html>
