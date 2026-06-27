<?php
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';
$db = getDB();

// RDV d'aujourd'hui (pour le bloc logo, comme sur les autres pages)
$nbRdvAujourd = $db->query("SELECT COUNT(*) FROM ORD WHERE CONVERT(date,[DATE REDEZ VOUS])=CONVERT(date,GETDATE()) OR CONVERT(date,Date_Rdv)=CONVERT(date,GETDATE())")->fetchColumn();
$nbrMax = 20;
try {
    $stmtMax = $db->prepare("SELECT Valeur FROM T_Config WHERE Cle='NbrMax'");
    $stmtMax->execute();
    $rowMax = $stmtMax->fetch(PDO::FETCH_ASSOC);
    if ($rowMax) $nbrMax = (int)$rowMax['Valeur'];
} catch (Exception $e) {}

// Thème
$themes_valides = ['theme-0','theme-a','theme-b','theme-c'];
$theme = $_COOKIE['logycab_theme'] ?? 'theme-0';
if (!in_array($theme, $themes_valides)) $theme = 'theme-0';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Logycab — Factures</title>
<link rel="stylesheet" href="themes.css">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Segoe UI', Arial, sans-serif; background: var(--th-bg-page); font-size: 12px; color: var(--th-color-text); }

/* ══ HEADER (identique aux autres pages) ══ */
.header {
    background: var(--th-bg-header-s); color: white;
    padding: 5px 12px;
    display: flex; align-items: center; gap: 8px; flex-wrap: nowrap;
}
.btn-h {
    color: white; text-decoration: none; border: none; cursor: pointer;
    padding: 3px 10px; border-radius: 4px; font-size: 11px; font-weight: bold;
    display: inline-flex; align-items: center; height: 24px; white-space: nowrap;
}
.btn-h.green  { background: #27ae60; }
.btn-h.navy   { background: var(--th-btn-navy); }
.btn-h.blue   { background: var(--th-btn-blue); }
.btn-h.orange { background: #e67e22; }
.btn-h.purple { background: #8e44ad; }
.btn-h.grey   { background: #888; pointer-events: none; opacity: 0.7; cursor: default; }
.btn-h:not(.grey):hover { opacity: 0.85; }
@keyframes heartbeat {
    0%,100% { transform: scale(1); }
    14%     { transform: scale(1.2); }
    28%     { transform: scale(1); }
    42%     { transform: scale(1.15); }
    56%     { transform: scale(1); }
}
.heart { display: inline-block; animation: heartbeat 1.6s infinite; color: #e74c3c; font-size: 20px; }
.logo-block { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }
.logo-block .nom-logo { font-size: 16px; font-weight: 900; letter-spacing: 1px; color: #fff; line-height: 1.1; }
.logo-block .sub { font-size: 9px; opacity: 0.85; color: #fff; white-space: nowrap; }
.hclock {
    background: rgba(255,255,255,0.12); border-radius: 6px;
    padding: 3px 10px; text-align: center; min-width: 130px; flex-shrink: 0;
}
.hclock .ct { font-size: 15px; font-weight: bold; letter-spacing: 1px; color: white; }
.hclock .cd { font-size: 9px; opacity: 0.75; }

/* ══ TITRE DE PAGE ══ */
.page-title {
    background: #16a085; color: white; padding: 8px 16px;
    font-size: 14px; font-weight: bold;
}

/* ══ PANNEAU DE CONTRÔLE ══ */
.controls {
    background: var(--th-bg-card); border-bottom: 2px solid var(--th-border-card);
    padding: 10px 16px; display: flex; flex-direction: column; gap: 8px;
}
.controls-row { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.controls-label { font-size: 11px; font-weight: bold; color: var(--th-color-text-muted); margin-right: 4px; }

.tab-vue, .tab-gran {
    background: var(--th-bg-page); color: var(--th-color-text);
    border: 1px solid var(--th-border-card); border-radius: 5px;
    padding: 6px 14px; font-size: 12px; font-weight: bold; cursor: pointer;
}
.tab-vue:hover, .tab-gran:hover { background: var(--th-bg-link-hover); }
.tab-vue.active { background: #16a085; color: white; border-color: #16a085; }
.tab-gran.active { background: #2e6da4; color: white; border-color: #2e6da4; }

.date-range { display: flex; align-items: center; gap: 6px; font-size: 11px; }
.date-range input[type=date] {
    padding: 4px 6px; border: 1px solid var(--th-border-card); border-radius: 4px;
    font-size: 11px; background: var(--th-bg-card); color: var(--th-color-text);
}
.btn-mini {
    padding: 5px 10px; border-radius: 4px; font-size: 11px; font-weight: bold;
    border: none; cursor: pointer; color: white;
}
.btn-mini.appliquer { background: #2e6da4; }
.btn-mini.reset      { background: #888; }
.btn-mini:hover { opacity: 0.85; }

/* ══ RÉSUMÉ ══ */
.resume-bar {
    background: #1a4a7a; color: white; padding: 8px 16px;
    font-size: 13px; font-weight: bold; display: flex; justify-content: space-between; align-items: center;
}
.resume-bar .montant { color: #FFD700; font-size: 15px; }

/* ══ LISTE MULTI-COLONNES (lignes compactes étalées en colonnes) ══ */
.table-wrap { padding: 16px; }
.liste-resultats {
    column-gap: 18px;
    background: var(--th-bg-card);
}
.liste-resultats.col-3 { column-count: 3; }
.liste-resultats.col-4 { column-count: 4; }
.ligne-compacte {
    display: flex; align-items: baseline; gap: 6px;
    padding: 6px 10px; border-bottom: 1px solid var(--th-sep-color);
    break-inside: avoid; -webkit-column-break-inside: avoid;
    font-size: 12px;
}
.ligne-compacte:hover { background: var(--th-bg-link-hover); }
.ligne-compacte .lbl { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ligne-compacte .cpt { color: var(--th-color-text-muted); font-size: 10px; white-space: nowrap; flex-shrink: 0; }
.ligne-compacte .mtt { font-weight: bold; color: #16a085; white-space: nowrap; flex-shrink: 0; min-width: 64px; text-align: right; }
.lien-patient { color: var(--th-color-text); text-decoration: none; }
.lien-patient:hover { text-decoration: underline; color: #2e6da4; }
.aucune-donnee { padding: 30px; text-align: center; color: var(--th-color-text-muted); font-size: 13px; }
</style>
</head>
<body class="<?= htmlspecialchars($theme) ?>">

<script src="home.js"></script>

<!-- ══ HEADER ══ -->
<div class="header">
    <div class="logo-block">
        <span class="heart">❤</span>
        <div>
            <div class="nom-logo">LOGYCAB</div>
            <div class="sub"><?= (int)$nbRdvAujourd ?> RDV aujourd'hui / <?= $nbrMax ?> prévus</div>
        </div>
    </div>
    <div style="flex:1;"></div>
    <a href="index.php"            class="btn-h" style="background:#c0392b;">🏠 Accueil</a>
    <button onclick="goHome()"     class="btn-h green">🏠 Dossier</button>
    <button onclick="voirApercu()" class="btn-h" style="background:#27ae60;font-weight:bold;">📋 Aperçu</button>
    <a href="agenda.php"           class="btn-h navy">📅 Agenda</a>
    <a href="planning.php"         class="btn-h blue">📊 Planning</a>
    <a href="grille_semaine.php"   class="btn-h blue">📋 Grille</a>
    <button onclick="voirBiologie()" class="btn-h orange">🧪 Biologie</button>
    <a href="jours_feries.php"     class="btn-h purple">📅 Fériés</a>
    <div class="hclock">
        <div class="ct" id="clockTime">--:--:--</div>
        <div class="cd" id="clockDate">---</div>
    </div>
    <a href="logout.php" class="btn-h" style="background:#e74c3c;" title="Déconnexion">⏻</a>
</div>

<!-- ══ TITRE ══ -->
<div class="page-title">🧾 Consultation des factures — Chiffre d'affaire (encaissé)</div>

<!-- ══ CONTRÔLES ══ -->
<div class="controls">
    <div class="controls-row">
        <span class="controls-label">Vue :</span>
        <button class="tab-vue active" data-vue="patient">👤 Par patient</button>
        <button class="tab-vue" data-vue="total">💰 Total</button>
        <button class="tab-vue" data-vue="ECG">📈 ECG</button>
        <button class="tab-vue" data-vue="EDC">🫀 EDC</button>
        <button class="tab-vue" data-vue="DTSA">🩸 DTSA</button>
        <button class="tab-vue" data-vue="DVMI">🦵 DVMI</button>
        <span style="width:1px;height:20px;background:var(--th-border-card);margin:0 6px;"></span>
        <input type="text" id="rechPatientFiltre" placeholder="🔍 Filtrer par nom ou N°..."
               style="padding:5px 10px;border:1px solid var(--th-border-card);border-radius:4px;
                      font-size:12px;width:220px;background:var(--th-bg-card);color:var(--th-color-text);">
        <button class="btn-mini reset" id="btnRechClear" style="display:none;">✕</button>
    </div>
    <div class="controls-row">
        <span class="controls-label">Période :</span>
        <button class="tab-gran" data-gran="jour">Jour</button>
        <button class="tab-gran active" data-gran="mois">Mois</button>
        <button class="tab-gran" data-gran="trimestre">Trimestre</button>
        <button class="tab-gran" data-gran="annee">Année</button>
        <span style="width:1px;height:20px;background:var(--th-border-card);margin:0 6px;"></span>
        <div class="date-range">
            Du <input type="date" id="dateDebut">
            au <input type="date" id="dateFin">
            <button class="btn-mini appliquer" id="btnAppliquer">🔍 Appliquer</button>
            <button class="btn-mini reset" id="btnReset">↺ Période par défaut</button>
        </div>
    </div>
</div>

<!-- ══ RÉSUMÉ ══ -->
<div class="resume-bar">
    <span id="resumeTexte">Chargement...</span>
    <span class="montant" id="resumeMontant">-- DH</span>
</div>

<!-- ══ LISTE DE RÉSULTATS (multi-colonnes) ══ -->
<div class="table-wrap">
    <div class="liste-resultats col-4" id="listeResultats"></div>
</div>

<script>
// ── État courant ────────────────────────────────────────────────
let etat = { vue: 'patient', gran: 'mois', dateDebut: '', dateFin: '' };

function formatDH(n) {
    n = Math.round(n);
    return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' DH';
}

function chargerDonnees() {
    const params = new URLSearchParams({
        vue: etat.vue,
        granularite: etat.gran,
    });
    if (etat.dateDebut && etat.dateFin) {
        params.set('date_debut', etat.dateDebut);
        params.set('date_fin', etat.dateFin);
    }
    document.getElementById('resumeTexte').textContent = 'Chargement...';
    fetch('ajax_factures.php?' + params.toString())
        .then(r => r.json())
        .then(afficher)
        .catch(() => { document.getElementById('resumeTexte').textContent = '❌ Erreur de chargement'; });
}

function afficher(data) {
    // Met à jour les champs de date avec ceux réellement utilisés
    document.getElementById('dateDebut').value = data.date_debut;
    document.getElementById('dateFin').value   = data.date_fin;

    // Nombre de colonnes : 3 pour "par patient", 4 pour les autres vues
    const liste = document.getElementById('listeResultats');
    liste.classList.remove('col-3', 'col-4');
    liste.classList.add(data.vue === 'patient' ? 'col-3' : 'col-4');
    liste.innerHTML = '';

    if (!data.lignes.length) {
        liste.innerHTML = '<div class="aucune-donnee">Aucune facture sur cette période.</div>';
    } else {
        data.lignes.forEach(function(l) {
            const ligne = document.createElement('div');
            ligne.className = 'ligne-compacte';
            let labelHtml;
            if (data.vue === 'patient' && l.n_pat) {
                labelHtml = '<a class="lien-patient" href="dossier.php?id=' + l.n_pat + '">' +
                            l.label + ' — N°' + l.n_pat + '</a>';
                ligne.dataset.nom   = l.label.toLowerCase();
                ligne.dataset.npat  = String(l.n_pat);
            } else {
                labelHtml = l.label;
            }
            ligne.innerHTML =
                '<span class="lbl">' + labelHtml + '</span>' +
                '<span class="cpt">' + l.nb + ' ' + data.colonne_compte.toLowerCase() + '</span>' +
                '<span class="mtt">' + formatDH(l.montant) + '</span>';
            liste.appendChild(ligne);
        });
    }

    // Réapplique le filtre patient s'il était déjà saisi
    const rech = document.getElementById('rechPatientFiltre');
    if (rech.value.trim()) rech.dispatchEvent(new Event('input'));

    // Résumé (le total reste affiché dans la barre du haut)
    const labelVue = {
        patient: 'tous patients', total: 'toutes recettes', ECG: 'ECG', EDC: 'EDC', DTSA: 'DTSA', DVMI: 'DVMI'
    }[data.vue] || data.vue;
    document.getElementById('resumeTexte').textContent =
        'Du ' + data.date_debut.split('-').reverse().join('/') +
        ' au ' + data.date_fin.split('-').reverse().join('/') +
        ' — ' + labelVue;
    document.getElementById('resumeMontant').textContent = formatDH(data.total_montant);
}

// ── Boutons "Vue" ────────────────────────────────────────────────
document.querySelectorAll('.tab-vue').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.tab-vue').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        etat.vue = btn.dataset.vue;
        etat.dateDebut = ''; etat.dateFin = ''; // recalcule la période par défaut pour cette vue
        const rech = document.getElementById('rechPatientFiltre');
        rech.style.display = (etat.vue === 'patient') ? 'inline-block' : 'none';
        rech.value = '';
        document.getElementById('btnRechClear').style.display = 'none';
        chargerDonnees();
    });
});

// ── Boutons "Période" ────────────────────────────────────────────
document.querySelectorAll('.tab-gran').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.tab-gran').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        etat.gran = btn.dataset.gran;
        etat.dateDebut = ''; etat.dateFin = ''; // recalcule la période par défaut pour cette granularité
        chargerDonnees();
    });
});

// ── Dates personnalisées ─────────────────────────────────────────
document.getElementById('btnAppliquer').addEventListener('click', function() {
    etat.dateDebut = document.getElementById('dateDebut').value;
    etat.dateFin   = document.getElementById('dateFin').value;
    chargerDonnees();
});
document.getElementById('btnReset').addEventListener('click', function() {
    etat.dateDebut = ''; etat.dateFin = '';
    chargerDonnees();
});

// ── Filtre patient (live, sans rechargement) ──────────────────────
document.getElementById('rechPatientFiltre').addEventListener('input', function() {
    const q = this.value.trim().toLowerCase();
    document.getElementById('btnRechClear').style.display = q ? 'inline-block' : 'none';
    const estNumerique = /^\d+$/.test(q);
    document.querySelectorAll('#listeResultats .ligne-compacte').forEach(function(ligne) {
        let visible;
        if (!q) {
            visible = true;
        } else if (estNumerique) {
            visible = (ligne.dataset.npat || '') === q;
        } else {
            visible = (ligne.dataset.nom || '').includes(q);
        }
        ligne.style.display = visible ? 'flex' : 'none';
    });
});
document.getElementById('btnRechClear').addEventListener('click', function() {
    const rech = document.getElementById('rechPatientFiltre');
    rech.value = '';
    rech.dispatchEvent(new Event('input'));
    rech.focus();
});

// ── Horloge ──────────────────────────────────────────────────────
(function tick() {
    const now  = new Date();
    const jrs  = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
    const mois = ['Janvier','Février','Mars','Avril','Mai','Juin',
                  'Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
    const pad  = n => String(n).padStart(2,'0');
    document.getElementById('clockTime').textContent =
        pad(now.getHours())+':'+pad(now.getMinutes())+':'+pad(now.getSeconds());
    document.getElementById('clockDate').textContent =
        jrs[now.getDay()]+' '+now.getDate()+' '+mois[now.getMonth()]+' '+now.getFullYear();
    setTimeout(tick, 1000);
})();

// ── Chargement initial ────────────────────────────────────────────
chargerDonnees();
</script>
</body>
</html>
