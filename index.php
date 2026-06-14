<?php
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';
$db = getDB();

// Statistiques rapides
$nbPatients = $db->query("SELECT COUNT(DISTINCT id) FROM ORD")->fetchColumn();
$nbRdvAujourd = $db->query("SELECT COUNT(*) FROM ORD WHERE CONVERT(date,[DATE REDEZ VOUS])=CONVERT(date,GETDATE()) OR CONVERT(date,Date_Rdv)=CONVERT(date,GETDATE())")->fetchColumn();
$dateAuj = date('d/m/Y');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Logycab — Accueil</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Arial, sans-serif; font-size: 12px; background: #f0f4f8; color: #222; min-height: 100vh; }

/* ── Header ── */
.header {
    background: linear-gradient(135deg, #1a4a7a, #2e6da4);
    color: white; padding: 8px 16px;
    display: flex; align-items: center; gap: 10px;
}
.header h1 { font-size: 18px; font-weight: 700; letter-spacing: 1px; }
.header .sub { font-size: 10px; opacity: 0.7; }
.header-clock { background: rgba(255,255,255,0.12); border-radius: 6px; padding: 4px 10px; text-align: center; margin-left: auto; }
.header-clock .ct { font-size: 15px; font-weight: bold; letter-spacing: 1px; color: #f0f4f8; }
.header-clock .cd { font-size: 9px; opacity: 0.75; }
.btn-h {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 4px 10px; border-radius: 4px; font-size: 11px;
    font-weight: bold; text-decoration: none; color: white;
    white-space: nowrap; cursor: pointer; border: none;
}
.btn-h.blue   { background: #2e6da4; }
.btn-h.navy   { background: #1a4a7a; }
.btn-h.green  { background: #27ae60; }
.btn-h.grey   { background: #7f8c8d; }

/* ── Bandeau stats ── */
.stats-bar {
    background: white; border-bottom: 2px solid #c5d8ed;
    padding: 6px 20px; display: flex; gap: 24px; align-items: center;
}
.stat-item { display: flex; flex-direction: column; align-items: center; }
.stat-item .val { font-size: 18px; font-weight: bold; color: #1a4a7a; }
.stat-item .lbl { font-size: 10px; color: #888; }

/* ── Grille modules ── */
.modules {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    padding: 20px;
    max-width: 1100px;
    margin: 0 auto;
}

/* ── Card module ── */
.module-card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    overflow: hidden;
    transition: box-shadow 0.15s;
}
.module-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.14); }

.module-header {
    padding: 10px 14px;
    display: flex; align-items: center; gap: 8px;
    color: white; font-weight: bold; font-size: 13px;
}
.module-header .ico { font-size: 18px; }

.module-body { padding: 8px 0; }

.mod-link {
    display: flex; align-items: center; gap: 8px;
    padding: 7px 16px;
    text-decoration: none; color: #333;
    font-size: 12px;
    border-left: 3px solid transparent;
    transition: background 0.1s, border-color 0.1s;
}
.mod-link:hover { background: #f0f4f8; }
.mod-link .arrow { color: #c5d8ed; font-size: 10px; margin-left: auto; }
.mod-link .ico-s { font-size: 14px; width: 20px; text-align: center; }

/* Couleurs par module */
.m-patient .module-header { background: #2e6da4; }
.m-patient .mod-link:hover { border-left-color: #2e6da4; }

.m-ordonnance .module-header { background: #27ae60; }
.m-ordonnance .mod-link:hover { border-left-color: #27ae60; }

.m-rdv .module-header { background: #8e44ad; }
.m-rdv .mod-link:hover { border-left-color: #8e44ad; }

.m-bilan .module-header { background: #e67e22; }
.m-bilan .mod-link:hover { border-left-color: #e67e22; }

.m-rapports .module-header { background: #c0392b; }
.m-rapports .mod-link:hover { border-left-color: #c0392b; }

.m-compta .module-header { background: #16a085; }
.m-compta .mod-link:hover { border-left-color: #16a085; }

/* séparateur entre liens */
.mod-sep { height: 1px; background: #f0f0f0; margin: 2px 0; }

/* ── Coeur animé ── */
@keyframes heartbeat {
    0%,100% { transform: scale(1); }
    14%     { transform: scale(1.2); }
    28%     { transform: scale(1); }
    42%     { transform: scale(1.15); }
    56%     { transform: scale(1); }
}
.heart { display: inline-block; animation: heartbeat 1.6s infinite; color: #e74c3c; font-size: 22px; }
</style>
</head>
<body>

<!-- ══ HEADER ══ -->
<div class="header">
    <div>
        <div style="display:flex;align-items:center;gap:8px;">
            <span class="heart">❤</span>
            <div>
                <div style="font-size:20px;font-weight:900;letter-spacing:2px;">LOGYCAB</div>
                <div class="sub">Cabinet de Cardiologie — Dr H. Hlimi</div>
            </div>
        </div>
    </div>
    <!-- Recherche rapide -->
    <div style="margin-left:24px;position:relative;">
        <input type="text" id="rech-patient" placeholder="🔍 Rechercher patient..."
            style="padding:4px 10px;border:none;border-radius:4px;font-size:11px;width:200px;background:rgba(255,255,255,0.9);color:#333;">
        <div id="rech-suggestions" style="position:absolute;top:100%;left:0;width:260px;background:white;
             border:1px solid #ccc;border-radius:4px;max-height:200px;overflow-y:auto;
             z-index:1000;display:none;box-shadow:0 4px 12px rgba(0,0,0,0.2);"></div>
    </div>
    <!-- Boutons rapides -->
    <a href="agenda.php"        class="btn-h navy">📅 Agenda</a>
    <a href="planning.php"      class="btn-h blue">📊 Planning</a>
    <a href="grille_semaine.php" class="btn-h blue">📋 Grille</a>
    <a href="jours_feries.php"  class="btn-h" style="background:#8e44ad;">📅 Fériés</a>
    <!-- Horloge -->
    <div class="header-clock">
        <div id="clockTime" class="ct">--:--:--</div>
        <div id="clockDate" class="cd">---</div>
    </div>
</div>

<!-- ══ BANDEAU STATS ══ -->
<div class="stats-bar">
    <div class="stat-item">
        <span class="val"><?= number_format((int)$nbPatients, 0, ',', ' ') ?></span>
        <span class="lbl">Patients</span>
    </div>
    <div style="width:1px;height:28px;background:#e0e0e0;"></div>
    <div class="stat-item">
        <span class="val" style="color:#8e44ad;"><?= (int)$nbRdvAujourd ?></span>
        <span class="lbl">RDV aujourd'hui</span>
    </div>
    <div style="width:1px;height:28px;background:#e0e0e0;"></div>
    <div class="stat-item">
        <span class="val" style="color:#27ae60;"><?= $dateAuj ?></span>
        <span class="lbl">Date du jour</span>
    </div>
    <div style="margin-left:auto;font-size:11px;color:#aaa;font-style:italic;">
        Bienvenue, Dr Hlimi
    </div>
</div>

<!-- ══ MODULES ══ -->
<div class="modules">

    <!-- 1. GESTION PATIENTS -->
    <div class="module-card m-patient">
        <div class="module-header">
            <span class="ico">👤</span> Gestion des patients
        </div>
        <div class="module-body">
            <a href="recherche.php?action=nouveau" class="mod-link">
                <span class="ico-s">➕</span> Ajouter un patient
                <span class="arrow">▶</span>
            </a>
            <div class="mod-sep"></div>
            <a href="recherche.php" class="mod-link">
                <span class="ico-s">🔍</span> Chercher un patient
                <span class="arrow">▶</span>
            </a>
        </div>
    </div>

    <!-- 2. GESTION ORDONNANCES -->
    <div class="module-card m-ordonnance">
        <div class="module-header">
            <span class="ico">💊</span> Gestion des ordonnances
        </div>
        <div class="module-body">
            <a href="recherche.php?action=nouveau_medicament" class="mod-link">
                <span class="ico-s">➕</span> Ajouter un médicament
                <span class="arrow">▶</span>
            </a>
            <div class="mod-sep"></div>
            <a href="recherche.php?action=chercher_ordonnance" class="mod-link">
                <span class="ico-s">🔍</span> Chercher une ordonnance
                <span class="arrow">▶</span>
            </a>
        </div>
    </div>

    <!-- 3. GESTION RENDEZ-VOUS -->
    <div class="module-card m-rdv">
        <div class="module-header">
            <span class="ico">📅</span> Gestion des rendez-vous
        </div>
        <div class="module-body">
            <a href="agenda.php" class="mod-link">
                <span class="ico-s">📋</span> Planning du jour
                <span class="arrow">▶</span>
            </a>
            <div class="mod-sep"></div>
            <a href="grille_semaine.php" class="mod-link">
                <span class="ico-s">📅</span> Planning semaine
                <span class="arrow">▶</span>
            </a>
            <div class="mod-sep"></div>
            <a href="planning.php" class="mod-link">
                <span class="ico-s">📊</span> Planning mois / 3M / 6M
                <span class="arrow">▶</span>
            </a>
            <div class="mod-sep"></div>
            <a href="agenda.php?action=nouveau_rdv" class="mod-link">
                <span class="ico-s">➕</span> Donner un RDV
                <span class="arrow">▶</span>
            </a>
            <div class="mod-sep"></div>
            <a href="agenda.php?action=modifier_rdv" class="mod-link">
                <span class="ico-s">✏️</span> Modifier un RDV
                <span class="arrow">▶</span>
            </a>
        </div>
    </div>

    <!-- 4. GESTION BILANS -->
    <div class="module-card m-bilan">
        <div class="module-header">
            <span class="ico">🩺</span> Gestion des bilans
        </div>
        <div class="module-body">
            <a href="recherche.php?action=donner_bilan" class="mod-link">
                <span class="ico-s">📋</span> Donner un bilan (prescrire)
                <span class="arrow">▶</span>
            </a>
            <div class="mod-sep"></div>
            <a href="recherche.php?action=saisir_bilan" class="mod-link">
                <span class="ico-s">✏️</span> Saisir les résultats
                <span class="arrow">▶</span>
            </a>
        </div>
    </div>

    <!-- 5. GESTION RAPPORTS -->
    <div class="module-card m-rapports">
        <div class="module-header">
            <span class="ico">📑</span> Gestion des rapports
        </div>
        <div class="module-body">
            <a href="recherche.php?action=cmlm" class="mod-link">
                <span class="ico-s">📄</span> Attestation de maladie longue durée
                <span class="arrow">▶</span>
            </a>
            <div class="mod-sep"></div>
            <a href="recherche.php?action=rapport" class="mod-link">
                <span class="ico-s">📋</span> Rapport médical cardio-vasculaire
                <span class="arrow">▶</span>
            </a>
            <div class="mod-sep"></div>
            <a href="recherche.php?action=autres_rapports" class="mod-link">
                <span class="ico-s">📂</span> Autres rapports (lettre, certificat, aptitude)
                <span class="arrow">▶</span>
            </a>
        </div>
    </div>

    <!-- 6. GESTION COMPTABILITE -->
    <div class="module-card m-compta">
        <div class="module-header">
            <span class="ico">💰</span> Gestion de la comptabilité
        </div>
        <div class="module-body">
            <a href="recherche.php?action=factures" class="mod-link">
                <span class="ico-s">🧾</span> Consulter les factures
                <span class="arrow">▶</span>
            </a>
            <div class="mod-sep"></div>
            <a href="recherche.php?action=comptabilite" class="mod-link">
                <span class="ico-s">📊</span> Tableau de bord comptable
                <span class="arrow">▶</span>
            </a>
        </div>
    </div>

</div><!-- fin modules -->

<script>
// ── Horloge ──
(function() {
    const jours = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
    const mois  = ['Jan','Fév','Mar','Avr','Mai','Juin','Juil','Aoû','Sep','Oct','Nov','Déc'];
    function tick() {
        const n = new Date();
        const h = String(n.getHours()).padStart(2,'0');
        const m = String(n.getMinutes()).padStart(2,'0');
        const s = String(n.getSeconds()).padStart(2,'0');
        const ct = document.getElementById('clockTime');
        const cd = document.getElementById('clockDate');
        if (ct) ct.textContent = h+':'+m+':'+s;
        if (cd) cd.textContent = jours[n.getDay()]+' '+n.getDate()+' '+mois[n.getMonth()]+' '+n.getFullYear();
    }
    tick();
    setInterval(tick, 1000);
})();

// ── Recherche patient (auto-complétion) ──
(function() {
    const inp  = document.getElementById('rech-patient');
    const sugg = document.getElementById('rech-suggestions');
    if (!inp) return;
    inp.addEventListener('input', function() {
        const q = this.value.trim();
        if (q.length < 2) { sugg.style.display='none'; return; }
        fetch('ajax_search_patient.php?q=' + encodeURIComponent(q))
            .then(r => r.json())
            .then(data => {
                sugg.innerHTML = '';
                if (!data.length) { sugg.style.display='none'; return; }
                data.forEach(function(p) {
                    const d = document.createElement('div');
                    d.style.cssText = 'padding:6px 10px;cursor:pointer;border-bottom:1px solid #f0f0f0;font-size:11px;';
                    d.textContent = p.nom + ' — N°' + p.id;
                    d.onmouseover = function(){ this.style.background='#f0f4f8'; };
                    d.onmouseout  = function(){ this.style.background=''; };
                    d.onclick = function(){ window.location.href='dossier.php?id='+p.id; };
                    sugg.appendChild(d);
                });
                sugg.style.display = 'block';
            }).catch(() => { sugg.style.display='none'; });
    });
    document.addEventListener('click', function(e) {
        if (!inp.contains(e.target)) sugg.style.display='none';
    });
})();
</script>
</body>
</html>
