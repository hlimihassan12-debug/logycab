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
<title>Logycab — Statistiques</title>
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

/* ══ CONTRÔLES (granularité de la courbe) ══ */
.controls-row { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; background: var(--th-bg-card); padding: 8px 16px; }
.controls-label { font-size: 11px; font-weight: bold; color: var(--th-color-text-muted); margin-right: 4px; }
.tab-gran {
    background: var(--th-bg-page); color: var(--th-color-text);
    border: 1px solid var(--th-border-card); border-radius: 5px;
    padding: 6px 14px; font-size: 12px; font-weight: bold; cursor: pointer;
}
.tab-gran:hover { background: var(--th-bg-link-hover); }
.tab-gran.active { background: #2e6da4; color: white; border-color: #2e6da4; }

/* ══ GRILLE DE CARTES ══ */
.stats-wrap { padding: 16px; display: grid; grid-template-columns: 1fr 1fr; gap: 16px; max-width: 1200px; margin: 0 auto; }
@media (max-width: 900px) { .stats-wrap { grid-template-columns: 1fr; } }
.carte {
    background: var(--th-bg-card); border-radius: 8px; box-shadow: 0 2px 8px var(--th-border-card);
    padding: 14px 16px;
}
.carte-titre { font-size: 13px; font-weight: bold; color: var(--th-color-primary); margin-bottom: 10px; display: flex; align-items: center; gap: 6px; }
.carte.pleine-largeur { grid-column: 1 / -1; }

/* ── Répartition fidélité ── */
.rep-ligne { display: flex; align-items: center; gap: 8px; margin-bottom: 7px; font-size: 12px; }
.rep-ligne .rep-lbl { width: 110px; flex-shrink: 0; }
.rep-ligne .rep-barre-fond { flex: 1; height: 14px; background: var(--th-bg-page); border-radius: 3px; overflow: hidden; }
.rep-ligne .rep-barre { height: 100%; border-radius: 3px; transition: width 0.3s; }
.rep-ligne .rep-val { width: 70px; flex-shrink: 0; text-align: right; font-weight: bold; }

/* ── Ponctualité ── */
.ponct-grand { font-size: 26px; font-weight: bold; text-align: center; margin: 6px 0 12px; }
.ponct-detail { display: flex; justify-content: space-around; text-align: center; font-size: 11px; }
.ponct-detail .n { display: block; font-size: 17px; font-weight: bold; }

/* ── Courbe (barres CSS) ── */
.courbe-wrap { display: flex; align-items: flex-end; gap: 4px; height: 180px; padding: 10px 4px 0; overflow-x: auto; }
.courbe-barre-col { display: flex; flex-direction: column; align-items: center; min-width: 26px; flex-shrink: 0; }
.courbe-barre { width: 18px; background: #2e6da4; border-radius: 3px 3px 0 0; transition: height 0.3s; }
.courbe-val { font-size: 9px; color: var(--th-color-text-muted); margin-bottom: 2px; }
.courbe-lbl { font-size: 9px; color: var(--th-color-text-muted); margin-top: 4px; writing-mode: vertical-rl; text-orientation: mixed; max-height: 70px; }
.chargement, .aucune-donnee { padding: 20px; text-align: center; color: var(--th-color-text-muted); }
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
    <a href="factures.php"         class="btn-h" style="background:#16a085;">🧾 Factures</a>
    <a href="agenda.php"           class="btn-h navy">📅 Agenda</a>
    <div class="hclock">
        <div class="ct" id="clockTime">--:--:--</div>
        <div class="cd" id="clockDate">---</div>
    </div>
    <a href="logout.php" class="btn-h" style="background:#e74c3c;" title="Déconnexion">⏻</a>
</div>

<!-- ══ TITRE ══ -->
<div class="page-title">📈 Statistiques — Fidélité &amp; ponctualité des patients</div>

<div class="stats-wrap">

    <!-- ══ CARTE 1 : RÉPARTITION FIDÉLITÉ ══ -->
    <div class="carte">
        <div class="carte-titre">🔄 Régularité des consultations</div>
        <div id="repartitionWrap" class="chargement">Chargement...</div>
    </div>

    <!-- ══ CARTE 2 : PONCTUALITÉ ══ -->
    <div class="carte">
        <div class="carte-titre">📍 Ponctualité par rapport au RDV proposé</div>
        <div id="ponctualiteWrap" class="chargement">Chargement...</div>
    </div>

    <!-- ══ CARTE 3 : COURBE (pleine largeur) ══ -->
    <div class="carte pleine-largeur">
        <div class="carte-titre">📊 Patients vus par période</div>
        <div class="controls-row" style="padding:0 0 8px;background:none;">
            <span class="controls-label">Période :</span>
            <button class="tab-gran" data-gran="jour">Jour</button>
            <button class="tab-gran active" data-gran="mois">Mois</button>
            <button class="tab-gran" data-gran="trimestre">Trimestre</button>
            <button class="tab-gran" data-gran="annee">Année</button>
        </div>
        <div id="courbeWrap" class="chargement">Chargement...</div>
    </div>

</div>

<script>
const COULEURS_REPARTITION = {
    mensuel:     { lbl: 'Mensuel',      couleur: '#27ae60' },
    trimestriel: { lbl: 'Trimestriel',  couleur: '#2e6da4' },
    semestriel:  { lbl: 'Semestriel',   couleur: '#f39c12' },
    annuel:      { lbl: 'Annuel',       couleur: '#e67e22' },
    irregulier:  { lbl: 'Irrégulier',   couleur: '#e74c3c' },
    insuffisant: { lbl: '1 seule visite', couleur: '#95a5a6' },
};

let etatGran = 'mois';

function chargerStatistiques() {
    fetch('ajax_statistiques.php?granularite=' + etatGran)
        .then(r => r.json())
        .then(afficherTout)
        .catch(() => {
            document.getElementById('repartitionWrap').innerHTML = '<div class="aucune-donnee">❌ Erreur de chargement</div>';
            document.getElementById('ponctualiteWrap').innerHTML = '<div class="aucune-donnee">❌ Erreur de chargement</div>';
            document.getElementById('courbeWrap').innerHTML = '<div class="aucune-donnee">❌ Erreur de chargement</div>';
        });
}

function afficherTout(data) {
    afficherRepartition(data.repartition, data.nb_patients_total);
    afficherPonctualite(data.ponctualite);
    afficherCourbe(data.chart);
}

function afficherRepartition(rep, total) {
    const wrap = document.getElementById('repartitionWrap');
    if (!total) { wrap.innerHTML = '<div class="aucune-donnee">Aucune donnée.</div>'; return; }
    let html = '';
    Object.keys(COULEURS_REPARTITION).forEach(function(cle) {
        const info = COULEURS_REPARTITION[cle];
        const val  = rep[cle] || 0;
        const pct  = total ? Math.round(val / total * 100) : 0;
        html += '<div class="rep-ligne">' +
            '<span class="rep-lbl">' + info.lbl + '</span>' +
            '<span class="rep-barre-fond"><span class="rep-barre" style="width:' + pct + '%;background:' + info.couleur + ';"></span></span>' +
            '<span class="rep-val">' + val + ' (' + pct + '%)</span>' +
            '</div>';
    });
    html += '<div style="margin-top:6px;font-size:11px;color:var(--th-color-text-muted);">Total : ' + total + ' patients ayant au moins une consultation</div>';
    wrap.innerHTML = html;
}

function afficherPonctualite(p) {
    const wrap = document.getElementById('ponctualiteWrap');
    if (!p.nb_transitions) { wrap.innerHTML = '<div class="aucune-donnee">Pas encore assez de RDV suivis d\'une visite pour calculer la ponctualité.</div>'; return; }
    const moy = p.moyenne_jours;
    const texte = moy === null ? '—' :
        (moy >= 0 ? Math.round(Math.abs(moy)) + ' j après le RDV' : Math.round(Math.abs(moy)) + ' j avant le RDV');
    const couleur = moy === null ? '#888' : (Math.abs(moy) <= 14 ? '#27ae60' : (Math.abs(moy) <= 30 ? '#f39c12' : '#e74c3c'));
    let html = '<div class="ponct-grand" style="color:' + couleur + ';">' + texte + '</div>';
    html += '<div class="ponct-detail">' +
        '<div><span class="n" style="color:#27ae60;">' + p.ponctuels + '</span>ponctuel<br>(±14j)</div>' +
        '<div><span class="n" style="color:#f39c12;">' + p.retard_modere + '</span>écart modéré<br>(15-30j)</div>' +
        '<div><span class="n" style="color:#e74c3c;">' + p.retard_important + '</span>écart important<br>(&gt;30j)</div>' +
        '</div>';
    html += '<div style="margin-top:10px;font-size:11px;color:var(--th-color-text-muted);">Calculé sur ' + p.nb_transitions + ' visites suivant un RDV proposé (moyenne pondérée par visite).</div>';
    wrap.innerHTML = html;
}

function afficherCourbe(chart) {
    const wrap = document.getElementById('courbeWrap');
    if (!chart.labels.length) { wrap.innerHTML = '<div class="aucune-donnee">Aucune donnée sur cette période.</div>'; return; }
    const max = Math.max.apply(null, chart.data.concat([1]));
    let html = '<div class="courbe-wrap">';
    chart.labels.forEach(function(lbl, i) {
        const val = chart.data[i];
        const h = Math.round((val / max) * 140);
        html += '<div class="courbe-barre-col">' +
            '<span class="courbe-val">' + val + '</span>' +
            '<div class="courbe-barre" style="height:' + Math.max(h, 2) + 'px;"></div>' +
            '<span class="courbe-lbl">' + lbl + '</span>' +
            '</div>';
    });
    html += '</div>';
    wrap.innerHTML = html;
}

// ── Boutons Période ──
document.querySelectorAll('.tab-gran').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.tab-gran').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        etatGran = btn.dataset.gran;
        chargerStatistiques();
    });
});

// ── Horloge ──
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

chargerStatistiques();
</script>
</body>
</html>
