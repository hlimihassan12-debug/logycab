<?php
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';
$db = getDB();

// Compteur RDV du jour / NbrMax (pour le bloc logo)
$nbRdvAujourd = $db->query("SELECT COUNT(*) FROM ORD WHERE CONVERT(date,[DATE REDEZ VOUS])=CONVERT(date,GETDATE()) OR CONVERT(date,Date_Rdv)=CONVERT(date,GETDATE())")->fetchColumn();
$nbrMax = 20;
try {
    $stmtMax = $db->prepare("SELECT Valeur FROM T_Config WHERE Cle='NbrMax'");
    $stmtMax->execute();
    $rowMax = $stmtMax->fetch(PDO::FETCH_ASSOC);
    if ($rowMax) $nbrMax = (int)$rowMax['Valeur'];
} catch (Exception $e) {}

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: recherche.php'); exit; }

$stmt = $db->prepare("SELECT [N°PAT], NOMPRENOM, DDN, CIN, MUTUELLE FROM ID WHERE [N°PAT] = ?");
$stmt->execute([$id]);
$patient = $stmt->fetch();
if (!$patient) { die("❌ Patient introuvable"); }

$age = '—';
if (!empty($patient['DDN'])) {
    $naissance = new DateTime($patient['DDN']);
    $age = $naissance->diff(new DateTime())->y;
}

setcookie('dernier_patient', $id, time() + 86400*30, '/');

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
<title>Biologie — <?= htmlspecialchars($patient['NOMPRENOM']) ?></title>
<link rel="stylesheet" href="themes.css">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Segoe UI', Tahoma, sans-serif; background: var(--th-bg-page); font-size: 13px;
       height: 100vh; overflow: hidden; display: flex; flex-direction: column; }

/* ── HEADER ── */
.header { background: var(--th-bg-header);
          color: white; padding: 5px 12px; flex-shrink: 0;
          display: flex; align-items: center; gap: 7px;
          box-shadow: 0 2px 8px rgba(0,0,0,0.2); }
.header h1 { font-size: 14px; font-weight: 700; white-space: nowrap; }
.btn-h { color: white; text-decoration: none; border: none; cursor: pointer;
         padding: 3px 9px; border-radius: 4px; font-size: 11px; font-weight: bold;
         display: inline-flex; align-items: center; height: 24px; white-space: nowrap; }
.btn-h.green  { background: var(--th-btn-green); }
.btn-h.navy   { background: var(--th-btn-navy); border: 1px solid rgba(255,255,255,0.3); }
.btn-h.blue   { background: var(--th-btn-blue); }
.btn-h.orange { background: #e67e22; }
.btn-h.purple { background: #8e44ad; }
.btn-h.grey   { background: #888; pointer-events: none; opacity: 0.7; cursor: default; }
.btn-h:not(.grey):hover { opacity: 0.85; }
.search-hdr { padding: 2px 8px; border-radius: 4px; font-size: 11px; height: 24px;
    border: 1px solid rgba(255,255,255,0.35); background: rgba(255,255,255,0.12);
    color: white; outline: none; width: 190px; flex-shrink: 0; }
.search-hdr::placeholder { color: rgba(255,255,255,0.5); }
.search-hdr:focus { border-color: rgba(255,255,255,0.7); background: rgba(255,255,255,0.2); }
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
.header-clock { background: rgba(255,255,255,0.12); border-radius: 6px;
                padding: 3px 10px; text-align: center; min-width: 130px; flex-shrink: 0; }
.header-clock .ct { font-size: 15px; font-weight: bold; letter-spacing: 1px; color: #f0f4f8; }
.header-clock .cd { font-size: 9px; opacity: 0.75; }

/* ── BANDEAU PATIENT ── */
.patient-bar { background: #000000; color: white; padding: 4px 14px; flex-shrink: 0;
               display: flex; align-items: center; gap: 12px; }
.patient-bar .nom { font-size: 13px; font-weight: bold; color: #FFD700; }
.patient-bar .num { font-size: 11px; color: rgba(255,255,255,0.7); }
.patient-bar .det { font-size: 11px; color: rgba(255,255,255,0.7); }

/* ── BARRE BILANS ── */
.bilans-bar { background: var(--th-bg-card); padding: 6px 10px; flex-shrink: 0;
              display: flex; align-items: center; gap: 8px;
              border-bottom: 2px solid var(--th-border-statsbar); }
.bilans-bar label { font-size: 11px; color: var(--th-color-text-muted); font-weight: bold; }
.select-bilan { padding: 3px 8px; border: 1px solid var(--th-color-secondary); border-radius: 4px;
                font-size: 12px; cursor: pointer; min-width: 140px;
                background: var(--th-bg-card); color: var(--th-color-text); }
.btn-bar { border: none; border-radius: 4px; padding: 3px 10px; cursor: pointer;
           font-size: 11px; font-weight: bold; color: white; height: 26px;
           display: inline-flex; align-items: center; }
.btn-bar.green  { background: #27ae60; }
.btn-bar.red    { background: #e74c3c; }
.btn-bar.purple { background: #8e44ad; }
.btn-bar.blue   { background: #2e6da4; }
.btn-bar:hover  { opacity: 0.85; }
input.date-bilan { padding: 3px 6px; border: 1px solid var(--th-border-card); border-radius: 4px;
                   font-size: 12px; cursor: pointer; background: var(--th-bg-card); color: var(--th-color-text); }
input.obs-bilan  { padding: 3px 6px; border: 1px solid var(--th-border-card); border-radius: 4px;
                   font-size: 12px; width: 160px; background: var(--th-bg-card); color: var(--th-color-text); }

/* ── LAYOUT 4 COLONNES ── */
.layout { display: grid;
          grid-template-columns: 190px 200px 200px 1fr;
          flex: 1; overflow: hidden; min-height: 0; }

/* En-tête commun */
.col-hdr { background: var(--th-bg-header-s); color: white; padding: 6px 10px;
           font-size: 11px; font-weight: bold; flex-shrink: 0;
           display: flex; align-items: center; justify-content: space-between; }
.col-body { flex: 1; overflow-y: auto; min-height: 0; }

/* ════ COL 1 : PROFILS ════ */
.col-profils { display: flex; flex-direction: column; overflow: hidden;
               border-right: 2px solid var(--th-border-statsbar); background: var(--th-bg-link-hover); }

.profil-item { padding: 5px 10px; border-bottom: 1px solid var(--th-sep-color);
               cursor: pointer; background: var(--th-bg-card);
               font-size: 12px; color: var(--th-color-text); font-weight: bold;
               transition: background 0.1s; }
.profil-item:hover { background: var(--th-bg-link-hover); }
.profil-item.actif { background: var(--th-color-secondary); color: white; }
/* Bouton "TOUS LES BILANS" en bas */
.profil-item.tous { background: var(--th-bg-header-s); color: #FFD700;
                    font-weight: 900; text-align: center;
                    border-top: 2px solid var(--th-color-primary); position: sticky; bottom: 0; }
.profil-item.tous:hover { background: var(--th-color-primary); }

/* ════ COL 2 : ANALYSES DU PROFIL ════ */
.col-analyses { display: flex; flex-direction: column; overflow: hidden;
                border-right: 2px solid var(--th-border-statsbar); background: var(--th-bg-link-hover); }

.analyse-item { padding: 5px 10px; border-bottom: 1px solid var(--th-sep-color);
                cursor: pointer; background: var(--th-bg-card); font-size: 12px;
                color: var(--th-color-text);
                display: flex; align-items: center; gap: 6px;
                transition: background 0.1s; }
.analyse-item:hover { background: #eafaf1; color: #222; }
.analyse-item.dans-panier { background: #d5f5e3; color: #1e8449; font-weight: bold; }
.analyse-item .plus { color: #27ae60; font-size: 14px; font-weight: 900; flex-shrink: 0; }
.analyse-item.dans-panier .plus { color: #1e8449; }

/* ════ COL 3 : PANIER / SÉLECTION ════ */
.col-panier { display: flex; flex-direction: column; overflow: hidden;
              border-right: 2px solid var(--th-border-statsbar); background: var(--th-bg-card); }

.panier-item { padding: 4px 8px; border-bottom: 1px solid var(--th-sep-color);
               background: var(--th-bg-card); font-size: 12px; font-weight: bold; color: var(--th-color-primary);
               display: flex; align-items: center; gap: 5px; }
.panier-item .del { background: none; border: none; cursor: pointer;
                    color: var(--th-color-text-muted); font-size: 13px; margin-left: auto; }
.panier-item .del:hover { color: #e74c3c; }

/* Boutons panier — EN HAUT */
.panier-header { padding: 6px 8px; border-bottom: 2px solid var(--th-border-statsbar);
                 display: flex; flex-direction: column; gap: 5px; flex-shrink: 0;
                 background: var(--th-bg-card); }

/* ════ COL 4 : RÉSULTATS ════ */
.col-resultats { display: flex; flex-direction: column; overflow: hidden; background: var(--th-bg-card); }

/* Toolbar résultats */
.toolbar-res { background: var(--th-bg-link-hover); padding: 6px 10px; flex-shrink: 0;
               display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
               border-bottom: 2px solid var(--th-border-statsbar); }

.tbl-wrap { flex: 1; overflow-y: auto; }
table.bio { width: 100%; border-collapse: collapse; }
table.bio thead th { background: var(--th-bg-header-s); color: white; padding: 7px 10px;
                     font-size: 11px; text-align: left; position: sticky; top: 0; z-index: 2; }
table.bio tbody tr { border-bottom: 1px solid var(--th-sep-color); }
table.bio tbody tr:hover { background: var(--th-bg-link-hover); }
table.bio tbody td { padding: 2px 8px; vertical-align: middle; }
.td-rubrique { background: #f0eafa !important; font-size: 10px;
               color: #7d3c98; font-weight: bold; padding: 2px 8px !important; }
.td-analyse  { font-size: 12px; font-weight: bold; color: var(--th-color-primary); }
.inp-res { width: 130px; padding: 1px 5px; border: 1px solid var(--th-border-card);
           border-radius: 3px; font-size: 11px; height: 22px;
           background: var(--th-bg-card); color: var(--th-color-text); }
.inp-res:focus   { border-color: var(--th-color-secondary); outline: none; background: var(--th-bg-link-hover); }
.inp-res.normal  { border-color: #27ae60; color: #27ae60; font-weight: bold; background: #f0fff5; }
.inp-res.anormal { border-color: #e74c3c; color: #e74c3c; font-weight: bold; background: #fff5f5; }
.btn-del-l { background: none; border: none; cursor: pointer; color: var(--th-color-text-muted); font-size: 14px; }
.btn-del-l:hover { color: #e74c3c; }

.placeholder { flex: 1; display: flex; align-items: center; justify-content: center;
               color: var(--th-color-text-muted); font-size: 13px; text-align: center; padding: 20px; }
.empty { padding: 16px; text-align: center; color: var(--th-color-text-muted); font-size: 12px; }

/* Toast */
.toast { position: fixed; bottom: 20px; right: 20px; padding: 9px 16px;
         border-radius: 6px; font-size: 12px; font-weight: bold; z-index: 9999;
         display: none; color: white; box-shadow: 0 4px 12px rgba(0,0,0,0.25); }
.toast.show    { display: block; }
.toast.success { background: #27ae60; }
.toast.error   { background: #e74c3c; }
.toast.info    { background: #2e6da4; }

@media print {
    .header, .patient-bar, .bilans-bar, .col-profils, .col-analyses,
    .col-panier, .toolbar-res, .btn-del-l { display: none !important; }
    .layout { display: block; }
    body { overflow: visible; height: auto; }
    .col-resultats { overflow: visible; }
    .tbl-wrap { overflow: visible; }
    table.bio thead th { background: #1a4a7a !important;
        -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>
</head>
<body class="<?= htmlspecialchars($theme) ?>">

<!-- HEADER -->
<script src="home.js"></script>
<div class="header">
    <!-- GAUCHE : logo + cœur animé + compteur RDV du jour -->
    <div class="logo-block">
        <span class="heart">❤</span>
        <div>
            <div class="nom-logo">LOGYCAB</div>
            <div class="sub"><?= $nbRdvAujourd ?> RDV aujourd'hui / <?= $nbrMax ?> prévus</div>
        </div>
    </div>
    <input class="search-hdr" type="text" placeholder="🔍 Rechercher patient..."
           onkeydown="if(event.key==='Enter'&&this.value.trim()) location.href='recherche.php?q='+encodeURIComponent(this.value.trim())">
    <!-- Espace flexible -->
    <div style="flex:1;"></div>
    <button onclick="ouvrirModalRapport()" class="btn-h" style="background:#c0392b;">📄 Rapport</button>
    <a href="index.php" class="btn-h" style="background:#c0392b;">🏠 Accueil</a>
    <button onclick="goHome()"        class="btn-h green" >🏠 Dossier</button>
    <button onclick="voirApercu()" class="btn-h" style="background:#27ae60;font-weight:bold;">📋 Aperçu</button>
    <a href="agenda.php"              class="btn-h navy"  >📅 Agenda</a>
    <a href="planning.php"            class="btn-h blue"  >📊 Planning</a>
    <a href="grille_semaine.php"      class="btn-h blue"  >📋 Grille</a>
    <span                             class="btn-h grey"  >🧪 Biologie</span>
    <a href="jours_feries.php"        class="btn-h purple">📅 Fériés</a>
    <div class="header-clock">
        <div class="ct" id="clockTime">--:--:--</div>
        <div class="cd" id="clockDate">---</div>
    </div>
    <a href="logout.php" class="btn-h" style="background:#e74c3c;" title="Déconnexion">⏻</a>
</div>

<!-- BANDEAU PATIENT -->
<div class="patient-bar">
    <span class="num">N° <?= $id ?></span>
    <span class="nom"><?= htmlspecialchars($patient['NOMPRENOM']) ?></span>
    <span class="det"><?= $age ?> ans</span>
    <span class="det">DDN <?= $patient['DDN'] ? date('d/m/Y', strtotime($patient['DDN'])) : '—' ?></span>
    <span class="det">CIN <?= htmlspecialchars($patient['CIN'] ?? '—') ?></span>
    <span class="det">Mutuelle <?= htmlspecialchars($patient['MUTUELLE'] ?? '—') ?></span>
</div>

<!-- BARRE BILANS -->
<div class="bilans-bar">
    <label>📋 Bilan :</label>
    <select class="select-bilan" id="select-bilan" onchange="ouvrirBilan(this.value)">
        <option value="">— Sélectionner —</option>
    </select>
    <button class="btn-bar green" onclick="nouveauBilan()">➕ Nouveau</button>
    <button class="btn-bar red"   onclick="supprimerBilanActif()" id="btn-del-bilan" style="display:none;">🗑 Suppr. bilan</button>
    <span style="width:1px;height:20px;background:#ddd;flex-shrink:0;"></span>
    <label>📅</label>
    <input type="date" class="date-bilan" id="tb-date" onchange="majBilan()" style="display:none;">
    <label>📝</label>
    <input type="text" class="obs-bilan" id="tb-obs" placeholder="Observation..." onchange="majBilan()" style="display:none;">
    <span style="margin-left:auto;"></span>
    <button class="btn-bar green"  id="btn-n"     onclick="toutNormal()"   style="display:none;">✅ N partout</button>
    <button class="btn-bar red"    id="btn-vider" onclick="viderBilan()"   style="display:none;">🗑 Tout suppr.</button>
    <button class="btn-bar purple" id="btn-print" onclick="window.print()" style="display:none;">🖨 Imprimer</button>
</div>

<!-- LAYOUT 4 COLONNES -->
<div class="layout">

    <!-- ══ COL 1 : PROFILS ══ -->
    <div class="col-profils">
        <div class="col-hdr">📂 Profils</div>
        <div class="col-body" id="liste-profils">
            <div class="empty">Chargement...</div>
        </div>
    </div>

    <!-- ══ COL 2 : ANALYSES DU PROFIL ══ -->
    <div class="col-analyses">
        <div class="col-hdr" id="hdr-col2">🔬 Analyses</div>
        <div class="col-body" id="liste-analyses">
            <div class="empty">← Cliquez sur un profil</div>
        </div>
    </div>

    <!-- ══ COL 3 : PANIER ══ -->
    <div class="col-panier">
        <div class="col-hdr">
            🛒 Sélection
            <span id="nb-panier" style="font-size:10px;opacity:0.8;">0</span>
        </div>
        <!-- Boutons TOUJOURS EN HAUT, avant la liste -->
        <div class="panier-header">
            <button class="btn-bar blue"  onclick="toutSelectionner()" style="width:100%;">☑ Tout sélectionner</button>
            <button class="btn-bar green" onclick="inserer()"          style="width:100%;font-size:13px;">✅ Insérer</button>
        </div>
        <div class="col-body" id="liste-panier">
            <div class="empty">Cliquez sur<br>une analyse</div>
        </div>
    </div>

    <!-- ══ COL 4 : RÉSULTATS ══ -->
    <div class="col-resultats">
        <div class="col-hdr" id="hdr-col4">📋 Résultats</div>
        <div class="tbl-wrap" id="tbl-wrap">
            <div class="placeholder" id="placeholder">
                Sélectionnez des analyses et cliquez sur Insérer
            </div>
            <table class="bio" id="tbl-bio" style="display:none;">
                <thead>
                    <tr>
                        <th>Analyse</th>
                        <th style="width:150px;">Résultat</th>
                        <th style="width:30px;"></th>
                    </tr>
                </thead>
                <tbody id="tbody-bio"></tbody>
            </table>
        </div>
    </div>

</div>
<div class="toast" id="toast"></div>

<!-- ══ MODALE RAPPORT CARDIO-VASCULAIRE (identique à dossier.php) ══ -->
<div id="modal-rapport" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;
     background:rgba(0,0,0,0.55);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:var(--th-bg-card);border-radius:10px;width:380px;max-width:96%;
                box-shadow:0 8px 32px rgba(0,0,0,0.3);overflow:hidden;">
        <!-- Header -->
        <div style="background:#c0392b;color:white;padding:10px 16px;
                    display:flex;align-items:center;justify-content:space-between;">
            <span style="font-weight:bold;font-size:13px;">🖨️ Rapport cardio-vasculaire</span>
            <button onclick="fermerModalRapport()" style="background:none;border:none;color:white;
                    font-size:18px;cursor:pointer;line-height:1;">✕</button>
        </div>
        <!-- Corps -->
        <div style="padding:16px;">
            <div id="rapport-loading" style="text-align:center;color:var(--th-color-text-muted);font-size:12px;padding:20px 0;">
                Chargement des dates…
            </div>
            <div id="rapport-contenu" style="display:none;">
                <!-- Ligne Examen -->
                <div id="ligne-examen" style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
                    <span style="width:130px;font-size:12px;font-weight:bold;color:var(--th-color-text);">🩺 Examen clinique :</span>
                    <select id="sel-examen" style="flex:1;font-size:12px;padding:3px 6px;border:1px solid var(--th-border-card);border-radius:4px;background:var(--th-bg-card);color:var(--th-color-text);"></select>
                    <button onclick="toggleExclusionRubrique('examen')" id="btn-excl-examen"
                        title="Exclure cette rubrique du rapport"
                        style="width:28px;height:28px;border:1px solid #e74c3c;border-radius:4px;
                               background:#fff;color:#e74c3c;font-size:14px;cursor:pointer;flex-shrink:0;">✕</button>
                </div>
                <!-- Ligne ECG -->
                <div id="ligne-ecg" style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
                    <span style="width:130px;font-size:12px;font-weight:bold;color:var(--th-color-text);">📈 ECG :</span>
                    <select id="sel-ecg" style="flex:1;font-size:12px;padding:3px 6px;border:1px solid var(--th-border-card);border-radius:4px;background:var(--th-bg-card);color:var(--th-color-text);"></select>
                    <button onclick="toggleExclusionRubrique('ecg')" id="btn-excl-ecg"
                        title="Exclure cette rubrique du rapport"
                        style="width:28px;height:28px;border:1px solid #e74c3c;border-radius:4px;
                               background:#fff;color:#e74c3c;font-size:14px;cursor:pointer;flex-shrink:0;">✕</button>
                </div>
                <!-- Ligne Echo -->
                <div id="ligne-echo" style="display:flex;align-items:center;gap:8px;margin-bottom:16px;">
                    <span style="width:130px;font-size:12px;font-weight:bold;color:var(--th-color-text);">🫀 Echo-Doppler :</span>
                    <select id="sel-echo" style="flex:1;font-size:12px;padding:3px 6px;border:1px solid var(--th-border-card);border-radius:4px;background:var(--th-bg-card);color:var(--th-color-text);"></select>
                    <button onclick="toggleExclusionRubrique('echo')" id="btn-excl-echo"
                        title="Exclure cette rubrique du rapport"
                        style="width:28px;height:28px;border:1px solid #e74c3c;border-radius:4px;
                               background:#fff;color:#e74c3c;font-size:14px;cursor:pointer;flex-shrink:0;">✕</button>
                </div>
                <!-- Boutons action -->
                <div style="display:flex;justify-content:flex-end;gap:8px;">
                    <button onclick="fermerModalRapport()"
                        style="background:#95a5a6;color:white;border:none;border-radius:5px;
                               padding:6px 16px;font-size:12px;cursor:pointer;">Annuler</button>
                    <button onclick="imprimerRapport()"
                        style="background:#c0392b;color:white;border:none;border-radius:5px;
                               padding:6px 16px;font-size:12px;font-weight:bold;cursor:pointer;">🖨️ Imprimer</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const patientId = <?= $id ?>;
let bilanActif  = null;   // n_bilan courant
let panier      = [];     // [{id, nom}] — sélection en cours

// ── Horloge ──────────────────────────────────────────────────
(function(){
    const J=['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
    const M=['Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'];
    function pad(x){return String(x).padStart(2,'0');}
    function tick(){
        const n=new Date();
        const ct=document.getElementById('clockTime');
        const cd=document.getElementById('clockDate');
        if(ct) ct.textContent=pad(n.getHours())+':'+pad(n.getMinutes())+':'+pad(n.getSeconds());
        if(cd) cd.textContent=J[n.getDay()]+' '+n.getDate()+' '+M[n.getMonth()]+' '+n.getFullYear();
    }
    tick(); setInterval(tick,1000);
})();

// ── Toast ────────────────────────────────────────────────────
function toast(msg,type='success'){
    const el=document.getElementById('toast');
    el.textContent=msg; el.className='toast show '+type;
    setTimeout(()=>el.className='toast',2800);
}

// ── Ajax ─────────────────────────────────────────────────────
async function ajax(action,data={}){
    const r=await fetch('ajax_biologie.php',{
        method:'POST', headers:{'Content-Type':'application/json'},
        body:JSON.stringify({action,...data})
    });
    return r.json();
}

function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
function fr2iso(fr){const p=String(fr||'').split('/');return p.length===3?p[2]+'-'+p[1]+'-'+p[0]:fr||'';}

// ══════════════════════════════════════════════════════════════
// BARRE BILANS
// ══════════════════════════════════════════════════════════════
async function chargerSelectBilans(selectId=null){
    const res=await ajax('get_bilans',{id:patientId});
    const sel=document.getElementById('select-bilan');
    sel.innerHTML='<option value="">— Sélectionner un bilan —</option>';
    if(res.ok&&res.bilans.length){
        res.bilans.forEach(b=>{
            const opt=document.createElement('option');
            opt.value=b.n_bilan;
            opt.textContent='📅 '+b.date_fr+' ('+b.nb_analyses+' analyses)';
            if(selectId&&b.n_bilan==selectId) opt.selected=true;
            sel.appendChild(opt);
        });
    }
    if(selectId) ouvrirBilan(selectId);
}

async function nouveauBilan(){
    const date=prompt('Date du bilan (AAAA-MM-JJ) :', new Date().toISOString().split('T')[0]);
    if(!date) return;
    const res=await ajax('creer_bilan',{id:patientId,date_bilan:date,observation:''});
    if(res.ok){
        toast('Bilan créé ✅');
        await chargerSelectBilans(res.n_bilan);
    } else toast(res.msg||'Erreur','error');
}

async function ouvrirBilan(n_bilan){
    if(!n_bilan){
        bilanActif=null;
        masquerToolbar(); return;
    }
    bilanActif=parseInt(n_bilan);
    // Charger infos bilan pour date/obs
    const det=await ajax('get_detail_bilan',{n_bilan:bilanActif});
    if(det.ok&&det.bilan){
        document.getElementById('tb-date').value=fr2iso(det.bilan.date_fr);
        document.getElementById('tb-obs').value=det.bilan.observation||'';
    }
    afficherToolbar();
    await chargerProfils();
    afficherResultats(det.lignes||[]);
    // Vider panier
    panier=[];
    afficherPanier();
}

function afficherToolbar(){
    ['tb-date','tb-obs','btn-del-bilan','btn-n','btn-vider','btn-print']
        .forEach(id=>document.getElementById(id).style.display='');
}
function masquerToolbar(){
    ['tb-date','tb-obs','btn-del-bilan','btn-n','btn-vider','btn-print']
        .forEach(id=>document.getElementById(id).style.display='none');
}

async function supprimerBilanActif(){
    if(!bilanActif||!confirm('Supprimer ce bilan et toutes ses analyses ?')) return;
    const res=await ajax('supprimer_bilan',{n_bilan:bilanActif});
    if(res.ok){
        toast('Bilan supprimé');
        bilanActif=null; panier=[];
        masquerToolbar();
        afficherResultats([]);
        afficherPanier();
        document.getElementById('liste-analyses').innerHTML='<div class="empty">← Cliquez sur un profil</div>';
        document.getElementById('hdr-col2').textContent='🔬 Analyses';
        await chargerSelectBilans();
    }
}

async function majBilan(){
    if(!bilanActif) return;
    const date=document.getElementById('tb-date').value;
    const obs =document.getElementById('tb-obs').value||'';
    if(!date) return;
    await ajax('maj_bilan',{n_bilan:bilanActif,date_bilan:date,observation:obs});
    await chargerSelectBilans(bilanActif);
}

// ══════════════════════════════════════════════════════════════
// COL 1 : PROFILS
// ══════════════════════════════════════════════════════════════
async function chargerProfils(){
    const res=await ajax('get_profils');
    const el=document.getElementById('liste-profils');
    if(!res.ok||!res.profils.length){
        el.innerHTML='<div class="empty">Aucun profil</div>'; return;
    }
    // Profils dans l'ordre + TOUS LES BILANS en bas
    let html=res.profils.map(p=>
        `<div class="profil-item" onclick="chargerAnalysesProfil('${esc(p)}',this)">${esc(p)}</div>`
    ).join('');
    html+=`<div class="profil-item tous" onclick="chargerTousLesAnalyses(this)">★ TOUS LES BILANS</div>`;
    el.innerHTML=html;
}

// ══════════════════════════════════════════════════════════════
// COL 2 : ANALYSES D'UN PROFIL
// ══════════════════════════════════════════════════════════════
async function chargerAnalysesProfil(profil, btnEl){
    // Surbrillance profil actif
    document.querySelectorAll('.profil-item').forEach(e=>e.classList.remove('actif'));
    if(btnEl) btnEl.classList.add('actif');

    document.getElementById('hdr-col2').textContent='🔬 '+profil;
    const res=await ajax('get_analyses_profil',{profil});
    if(!res.ok){return;}
    afficherListeAnalyses(res.analyses);
}

async function chargerTousLesAnalyses(btnEl){
    document.querySelectorAll('.profil-item').forEach(e=>e.classList.remove('actif'));
    if(btnEl) btnEl.classList.add('actif');
    document.getElementById('hdr-col2').textContent='🔬 Tous les bilans';
    const res=await ajax('get_all_analyses');
    if(!res.ok){return;}
    afficherListeAnalyses(res.analyses);
}

function afficherListeAnalyses(analyses){
    const el=document.getElementById('liste-analyses');
    if(!analyses.length){
        el.innerHTML='<div class="empty">Aucune analyse dans ce profil</div>'; return;
    }
    // Ids déjà dans le panier
    const idsP=panier.map(p=>p.id);
    el.innerHTML=analyses.map(a=>{
        const dansPanier=idsP.includes(a.id);
        const cls=dansPanier?'analyse-item dans-panier':'analyse-item';
        return `<div class="${cls}" id="ai-${a.id}"
                     onclick="togglePanier(${a.id},'${esc(a.analyse||a.nom_analyse||'')}',this)">
                    <span class="plus">${dansPanier?'✓':'+'}</span>
                    ${esc(a.analyse||a.nom_analyse||'')}
                </div>`;
    }).join('');
}

// ══════════════════════════════════════════════════════════════
// COL 3 : PANIER
// ══════════════════════════════════════════════════════════════
function togglePanier(id, nom, btnEl){
    const idx=panier.findIndex(p=>p.id===id);
    if(idx>=0){
        // Retirer du panier
        panier.splice(idx,1);
        if(btnEl){ btnEl.classList.remove('dans-panier');
                   btnEl.querySelector('.plus').textContent='+'; }
    } else {
        // Ajouter au panier
        panier.push({id,nom});
        if(btnEl){ btnEl.classList.add('dans-panier');
                   btnEl.querySelector('.plus').textContent='✓'; }
    }
    afficherPanier();
}

function afficherPanier(){
    const el=document.getElementById('liste-panier');
    const nb=document.getElementById('nb-panier');
    nb.textContent=panier.length;
    if(!panier.length){
        el.innerHTML='<div class="empty">Cliquez sur<br>une analyse</div>'; return;
    }
    el.innerHTML=panier.map((p,i)=>`
        <div class="panier-item">
            <span>${esc(p.nom)}</span>
            <button class="del" onclick="retirerPanier(${i})" title="Retirer">✕</button>
        </div>`).join('');
}

function retirerPanier(idx){
    const id=panier[idx].id;
    panier.splice(idx,1);
    afficherPanier();
    // Désélectionner dans col 2
    const el=document.getElementById('ai-'+id);
    if(el){ el.classList.remove('dans-panier');
            const pl=el.querySelector('.plus'); if(pl) pl.textContent='+'; }
}

async function toutSelectionner(){
    // Sélectionner toutes les analyses visibles dans col 2
    document.querySelectorAll('.analyse-item:not(.dans-panier)').forEach(el=>{
        const onclick=el.getAttribute('onclick')||'';
        // Extraire id et nom depuis onclick="togglePanier(X,'Y',this)"
        const m=onclick.match(/togglePanier\((\d+),'([^']+)'/);
        if(m){ togglePanier(parseInt(m[1]),m[2],el); }
    });
}

// ══════════════════════════════════════════════════════════════
// INSÉRER → Résultats
// ══════════════════════════════════════════════════════════════
async function inserer(){
    if(!bilanActif){ toast('Sélectionnez d\'abord un bilan','error'); return; }
    if(!panier.length){ toast('Panier vide — sélectionnez des analyses','error'); return; }
    const ids=panier.map(p=>p.id);
    const res=await ajax('ajouter_analyses',{n_bilan:bilanActif,ids});
    if(res.ok){
        const msg=res.ajoutes>0
            ? `✅ ${res.ajoutes} analyse(s) insérée(s)${res.ajoutes<ids.length?' (doublons ignorés)':''}`
            : 'Toutes déjà présentes';
        toast(msg, res.ajoutes>0?'success':'info');
        // Recharger résultats
        const det=await ajax('get_detail_bilan',{n_bilan:bilanActif});
        afficherResultats(det.lignes||[]);
        // Vider panier
        panier=[];
        afficherPanier();
        // Mettre à jour col 2 si encore affichée
        document.querySelectorAll('.analyse-item').forEach(el=>{
            el.classList.remove('dans-panier');
            const pl=el.querySelector('.plus'); if(pl) pl.textContent='+';
        });
        // Mettre à jour select bilan
        await chargerSelectBilans(bilanActif);
    } else toast(res.msg||'Erreur','error');
}

// ══════════════════════════════════════════════════════════════
// COL 4 : RÉSULTATS
// ══════════════════════════════════════════════════════════════
function afficherResultats(lignes){
    const placeholder=document.getElementById('placeholder');
    const tbl        =document.getElementById('tbl-bio');
    const tbody      =document.getElementById('tbody-bio');
    const hdr        =document.getElementById('hdr-col4');

    hdr.textContent='📋 Résultats — '+lignes.length+' analyse(s)';

    if(!lignes.length){
        placeholder.style.display='flex';
        tbl.style.display='none';
        return;
    }
    placeholder.style.display='none';
    tbl.style.display='table';

    // Afficher uniquement les analyses, SANS en-tête de rubrique
    let html='';
    lignes.forEach(l=>{
        const v=l.resultat||'';
        const cls=v==='N'||v==='Normal'?'normal':(v!==''?'anormal':'');
        html+=`<tr>
            <td class="td-analyse">${esc(l.nom_analyse)}</td>
            <td><input class="inp-res ${cls}" type="text" value="${esc(v)}"
                   onchange="sauverResultat(${l.N_analyse},this)"
                   oninput="styliserInput(this)"></td>
            <td><button class="btn-del-l" onclick="supprimerLigne(${l.N_analyse})"
                        title="Supprimer">✕</button></td>
        </tr>`;
    });
    tbody.innerHTML=html;
}

function styliserInput(inp){
    const v=inp.value.trim();
    inp.className='inp-res '+(v==='N'||v==='Normal'?'normal':(v!==''?'anormal':''));
}

async function sauverResultat(n_analyse,inp){
    styliserInput(inp);
    const res=await ajax('sauver_resultat',{n_analyse,resultat:inp.value.trim()});
    if(res.ok) toast('✅ Enregistré');
    else toast('Erreur','error');
}

async function toutNormal(){
    if(!bilanActif) return;
    const res=await ajax('tout_normal',{n_bilan:bilanActif});
    if(res.ok){
        toast(`✅ ${res.nb} champ(s) mis à N`);
        const det=await ajax('get_detail_bilan',{n_bilan:bilanActif});
        afficherResultats(det.lignes||[]);
    }
}

async function viderBilan(){
    if(!bilanActif||!confirm('Supprimer toutes les analyses de ce bilan ?')) return;
    const res=await ajax('vider_bilan',{n_bilan:bilanActif});
    if(res.ok){
        toast('Bilan vidé');
        afficherResultats([]);
        await chargerSelectBilans(bilanActif);
    }
}

async function supprimerLigne(n_analyse){
    const res=await ajax('supprimer_ligne',{n_analyse});
    if(res.ok){
        toast('Ligne supprimée');
        const det=await ajax('get_detail_bilan',{n_bilan:bilanActif});
        afficherResultats(det.lignes||[]);
        await chargerSelectBilans(bilanActif);
    }
}

// ── Init ──────────────────────────────────────────────────────
chargerSelectBilans();

/* ── Rapport cardio-vasculaire (identique à dossier.php) ── */
var rapportExclusions = { examen: false, ecg: false, echo: false };

function ouvrirModalRapport() {
    rapportExclusions = { examen: false, ecg: false, echo: false };
    ['examen','ecg','echo'].forEach(function(r) {
        var btn = document.getElementById('btn-excl-' + r);
        var ligne = document.getElementById('ligne-' + r);
        if (btn) { btn.textContent = '✕'; btn.style.background = '#fff'; btn.style.color = '#e74c3c'; }
        if (ligne) ligne.style.opacity = '1';
    });

    document.getElementById('modal-rapport').style.display = 'flex';
    document.getElementById('rapport-loading').style.display = 'block';
    document.getElementById('rapport-contenu').style.display = 'none';

    fetch('ajax_dates_rapport.php?id=' + patientId)
        .then(function(r){ return r.json(); })
        .then(function(d) {
            remplirSelectRapport('sel-examen', d.examen || []);
            remplirSelectRapport('sel-ecg',    d.ecg    || []);
            remplirSelectRapport('sel-echo',   d.echo   || []);
            document.getElementById('rapport-loading').style.display = 'none';
            document.getElementById('rapport-contenu').style.display = 'block';
        })
        .catch(function(e) {
            document.getElementById('rapport-loading').textContent = 'Erreur de chargement.';
        });
}

function remplirSelectRapport(selectId, dates) {
    var sel = document.getElementById(selectId);
    sel.innerHTML = '';
    if (!dates || dates.length === 0) {
        var opt = document.createElement('option');
        opt.value = '';
        opt.textContent = '— aucun —';
        sel.appendChild(opt);
        sel.disabled = true;
        var rubrique = selectId.replace('sel-','');
        rapportExclusions[rubrique] = true;
        var btn = document.getElementById('btn-excl-' + rubrique);
        var ligne = document.getElementById('ligne-' + rubrique);
        if (btn) { btn.textContent = '↩'; btn.style.background = '#e74c3c'; btn.style.color = '#fff'; }
        if (ligne) ligne.style.opacity = '0.4';
    } else {
        sel.disabled = false;
        dates.forEach(function(d, i) {
            var opt = document.createElement('option');
            opt.value = d.date_tri;
            opt.textContent = d.date_fr;
            if (i === 0) opt.selected = true;
            sel.appendChild(opt);
        });
    }
}

function toggleExclusionRubrique(rubrique) {
    rapportExclusions[rubrique] = !rapportExclusions[rubrique];
    var btn   = document.getElementById('btn-excl-' + rubrique);
    var ligne = document.getElementById('ligne-' + rubrique);
    var sel   = document.getElementById('sel-' + rubrique);
    if (rapportExclusions[rubrique]) {
        if (btn)   { btn.textContent = '↩'; btn.style.background = '#e74c3c'; btn.style.color = '#fff'; }
        if (ligne) ligne.style.opacity = '0.4';
        if (sel)   sel.disabled = true;
    } else {
        if (btn)   { btn.textContent = '✕'; btn.style.background = '#fff'; btn.style.color = '#e74c3c'; }
        if (ligne) ligne.style.opacity = '1';
        if (sel)   sel.disabled = false;
    }
}

function fermerModalRapport() {
    document.getElementById('modal-rapport').style.display = 'none';
}

function imprimerRapport() {
    var url = 'print_rapport.php?id=' + patientId;
    if (!rapportExclusions.examen) {
        var v = document.getElementById('sel-examen').value;
        if (v) url += '&date_ex=' + encodeURIComponent(v);
    } else {
        url += '&excl_examen=1';
    }
    if (!rapportExclusions.ecg) {
        var v = document.getElementById('sel-ecg').value;
        if (v) url += '&date_ecg=' + encodeURIComponent(v);
    } else {
        url += '&excl_ecg=1';
    }
    if (!rapportExclusions.echo) {
        var v = document.getElementById('sel-echo').value;
        if (v) url += '&date_echo=' + encodeURIComponent(v);
    } else {
        url += '&excl_echo=1';
    }
    window.open(url, '_blank');
    fermerModalRapport();
}

document.getElementById('modal-rapport').addEventListener('click', function(e) {
    if (e.target === this) fermerModalRapport();
});
</script>
</body>
</html>
