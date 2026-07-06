<?php
require_once __DIR__ . '/backend/db.php';
$db = getDB();

// Catalogue médicaments pour les selects
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

// Onglet actif selon paramètre URL (1=catalogue, 2=chercher, 3=rédiger)
// Thème
$themes_valides = ['theme-0','theme-a','theme-b','theme-c'];
$theme = $_COOKIE['logycab_theme'] ?? 'theme-0';
if (!in_array($theme, $themes_valides)) $theme = 'theme-0';

$ongletActif = (int)($_GET['ong'] ?? 1);
if ($ongletActif < 1 || $ongletActif > 3) $ongletActif = 1;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Logycab — Gestion des ordonnances</title>
<link rel="stylesheet" href="themes.css">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:var(--th-font-body); font-size:13px; background:var(--th-bg-page); color:var(--th-color-text); min-height:100vh; }

/* ── Header ── */
.header {
    background:var(--th-bg-header);
    color:white; padding:10px 20px;
    display:flex; align-items:center; justify-content:space-between;
}
.header h1 { font-size:17px; font-weight:bold; }
.header-btns { display:flex; gap:8px; }
.btn-header {
    background:rgba(255,255,255,0.15); color:white; border:1px solid rgba(255,255,255,0.3);
    border-radius:5px; padding:5px 12px; cursor:pointer; font-size:12px; text-decoration:none;
    display:inline-flex; align-items:center; gap:4px;
}
.btn-header:hover { background:rgba(255,255,255,0.28); }

/* ── Onglets ── */
.onglets {
    display:flex; gap:0;
    background:var(--th-bg-header-s); padding:0 20px;
    border-bottom:3px solid #27ae60;
}
.ong-btn {
    background:none; color:#aac4e8; border:none; padding:10px 22px;
    cursor:pointer; font-size:13px; font-weight:600; border-bottom:3px solid transparent;
    margin-bottom:-3px; transition:all .15s;
}
.ong-btn:hover { color:white; }
.ong-btn.actif { color:white; border-bottom:3px solid #27ae60; background:rgba(255,255,255,0.08); }

/* ── Contenu ── */
.contenu { padding:20px; max-width:1400px; margin:0 auto; }
.panel { display:none; }
.panel.actif { display:block; }

/* ── Cards ── */
.card {
    background:var(--th-bg-card); border-radius:8px;
    box-shadow:0 2px 8px var(--th-border-card);
    padding:12px 16px; margin-bottom:8px;
}
.card-titre {
    font-size:14px; font-weight:bold; color:#1a4a7a;
    margin-bottom:8px; padding-bottom:6px;
    border-bottom:2px solid var(--th-border-statsbar);
}

/* ── Formulaire ── */
.form-row { display:flex; gap:10px; margin-bottom:10px; align-items:flex-end; flex-wrap:wrap; }
.form-group { display:flex; flex-direction:column; gap:4px; }
.form-group label { font-size:11px; color:var(--th-color-text-muted); font-weight:bold; text-transform:uppercase; }
.form-group input, .form-group select, .form-group textarea {
    border:1px solid #cdd5de; border-radius:5px; padding:6px 10px;
    font-size:13px; font-family:Arial,sans-serif;
}
.form-group input:focus, .form-group select:focus {
    outline:none; border-color:#2e6da4; box-shadow:0 0 0 2px rgba(46,109,164,0.15);
}

/* ── Boutons ── */
.btn { border:none; border-radius:5px; padding:7px 16px; cursor:pointer; font-size:12px; font-weight:600; display:inline-flex; align-items:center; gap:5px; }
.btn-vert   { background:#27ae60; color:white; }
.btn-bleu   { background:var(--th-btn-navy); color:white; }
.btn-orange { background:#e67e22; color:white; }
.btn-rouge  { background:#e74c3c; color:white; }
.btn-gris   { background:#95a5a6; color:white; }
.btn-violet { background:#8e44ad; color:white; }
.btn-teal   { background:#16a085; color:white; }
.btn:hover  { opacity:.88; }
.btn:disabled { opacity:.4; cursor:default; }
.barre-btns { display:flex; gap:8px; flex-wrap:wrap; margin-top:12px; }

/* ── Tableau résultats ── */
.tbl { width:100%; border-collapse:collapse; font-size:11px; }
.tbl th { background:var(--th-bg-header-s); color:white; padding:3px 8px; text-align:left; }
.tbl td { padding:2px 8px; border-bottom:1px solid var(--th-sep-color); line-height:1.4; color:#222; }
.tbl tr:hover td { background:var(--th-bg-link-hover); cursor:pointer; }
.tbl tr.selectionne td { background:#e8f5e9; font-weight:600; }

/* ── Lignes médicaments ── */
.lig-med-tbl { width:100%; border-collapse:collapse; font-size:12px; margin-top:6px; }
.lig-med-tbl th { background:var(--th-color-secondary); color:white; padding:5px 8px; text-align:left; }
.lig-med-tbl td { padding:4px 6px; border-bottom:1px solid var(--th-sep-color); color:var(--th-color-text); vertical-align:middle; }
.lig-med-tbl select, .lig-med-tbl input { width:100%; border:1px solid #ddd; border-radius:3px; padding:4px 6px; font-size:12px; }

/* ── Autocomplete ── */
.autocomplete-wrap { position:relative; }
.autocomplete-list {
    position:absolute; top:100%; left:0; right:0; background:white;
    border:1px solid #cdd5de; border-top:none; border-radius:0 0 5px 5px;
    max-height:180px; overflow-y:auto; z-index:1000;
    box-shadow:0 4px 12px rgba(0,0,0,0.15);
}
.autocomplete-list div {
    padding:7px 12px; cursor:pointer; font-size:12px; color:#222;
}
.autocomplete-list div:hover { background:#e8f0fa; }

/* ── RDV optionnel ── */
.rdv-section { background:#f8f0ff; border:1px solid #c9a0f0; border-radius:6px; padding:12px; margin-top:10px; }
.rdv-titre { font-size:11px; color:#8e44ad; font-weight:bold; margin-bottom:8px; }
.rdv-delais { display:flex; gap:4px; flex-wrap:wrap; margin-bottom:8px; }
.rdv-delais button {
    background:#8e44ad; color:white; border:none;
    padding:3px 10px; border-radius:3px; cursor:pointer; font-size:11px;
}
.rdv-delais button:hover { background:#7d3c98; }
.creneau-grille { display:flex; flex-wrap:wrap; gap:4px; margin-top:6px; }
.creneau-btn {
    padding:3px 8px; border:1px solid #ccc; border-radius:3px;
    cursor:pointer; font-size:11px; background:white;
}
.creneau-btn.libre     { background:#eafaf1; border-color:#27ae60; color:#1a5e33; }
.creneau-btn.occupe    { background:#fef9e7; border-color:#f39c12; color:#7d5a00; }
.creneau-btn.plein     { background:#fdedec; border-color:#e74c3c; color:#922b21; cursor:default; opacity:.6; }
.creneau-btn.selectionne { background:var(--th-btn-navy); color:white; border-color:var(--th-btn-navy); font-weight:bold; }

/* ── Tags liste catalogue ── */
.tag-med {
    display:inline-flex; align-items:center; gap:5px;
    background:#e8f5e9; border:1px solid #27ae60; border-radius:4px;
    padding:3px 8px; font-size:12px; margin:2px;
}
.tag-med .del { cursor:pointer; color:#e74c3c; font-weight:bold; font-size:13px; }

/* ── Boutons filtre tranche quantité ── */
.btn-filtre-qte {
    font-size:10px; padding:3px 9px; border-radius:12px; cursor:pointer;
    border:1px solid var(--th-color-secondary); background:transparent; color:var(--th-color-secondary);
    font-weight:bold;
}
.btn-filtre-qte:hover { background:var(--th-bg-link-hover); }
.btn-filtre-qte.actif { background:var(--th-color-secondary); color:white; }

.btn-tri {
    font-size:10px; padding:3px 9px; border-radius:12px; cursor:pointer;
    border:1px solid #8e44ad; background:transparent; color:#8e44ad;
    font-weight:bold;
}
.btn-tri:hover { background:var(--th-bg-link-hover); }
.btn-tri.actif { background:#8e44ad; color:white; }

/* ── Grille catalogue 4 colonnes ── */
.grille-catalogue {
    display:grid; grid-template-columns:repeat(6, minmax(0, 220px));
    grid-auto-flow:column; gap:1px;
    justify-content:start;
}
.carte-med {
    background:var(--th-bg-card); border:1px solid var(--th-sep-color); border-radius:3px;
    padding:1px 5px; font-size:10px; line-height:1.1; display:flex; align-items:center; gap:4px;
    cursor:pointer; transition:background 0.1s; width:100%; min-height:18px;
}
.carte-med:hover { background:var(--th-bg-link-hover); }
.carte-med.orphelin { background:#fff8f0; border-color:#f39c12; }
.carte-med .nom-med { color:var(--th-color-text); font-weight:600; line-height:1.25;
                       min-width:0; flex-shrink:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.carte-med .nb-util { font-size:10px; font-weight:bold; flex-shrink:0; white-space:nowrap; margin-left:auto; padding-left:6px; }
.carte-med .nb-util.zero { color:#e67e22; }
.carte-med .nb-util.ok   { color:#27ae60; }
.carte-med .btn-sup-med {
    background:#e74c3c; color:white; border:none; border-radius:3px;
    padding:1px 6px; font-size:10px; cursor:pointer; flex-shrink:0;
}
@media (max-width: 1500px) { .grille-catalogue { grid-template-columns:repeat(5, minmax(0, 220px)); } }
@media (max-width: 1100px) { .grille-catalogue { grid-template-columns:repeat(4, minmax(0, 220px)); } }
@media (max-width: 700px) { .grille-catalogue { grid-template-columns:repeat(3, minmax(0, 220px)); } }
@media (max-width: 500px)  { .grille-catalogue { grid-template-columns:repeat(2, minmax(0, 220px)); } }


/* ── Messages ── */
.msg-ok  { color:#27ae60; font-size:12px; font-weight:600; }
.msg-err { color:#e74c3c; font-size:12px; font-weight:600; }

/* ── Recherche produit inline ── */
.prod-recherche-row { display:flex; gap:8px; align-items:center; margin-bottom:8px; }
.prod-recherche-row input { flex:1; }
</style>
</head>
<body class="<?= htmlspecialchars($theme) ?>">

<!-- ══ HEADER ══ -->
<div class="header">
    <h1>💊 Gestion des ordonnances</h1>
    <div class="header-btns">
        <a href="index.php" class="btn-header">🏠 Accueil</a>
    </div>
</div>

<!-- ══ ONGLETS ══ -->
<div class="onglets">
    <button class="ong-btn <?= $ongletActif===1?'actif':'' ?>" onclick="switchOng(1)">➕ Catalogue médicaments</button>
    <button class="ong-btn <?= $ongletActif===2?'actif':'' ?>" onclick="switchOng(2)">🔍 Chercher une ordonnance</button>
    <button class="ong-btn <?= $ongletActif===3?'actif':'' ?>" onclick="switchOng(3)">📝 Rédiger une ordonnance</button>
</div>

<!-- ══════════════════════════════════════════════════
     PANEL 1 — CATALOGUE MÉDICAMENTS
══════════════════════════════════════════════════ -->
<div class="contenu">
<div id="panel1" class="panel <?= $ongletActif===1?'actif':'' ?>">

    <!-- Ajouter -->
    <div class="card">
        <div class="card-titre">➕ Ajouter un médicament au catalogue</div>
        <div class="form-row">
            <div class="form-group autocomplete-wrap" style="width:260px;">
                <label>Nom du médicament</label>
                <input type="text" id="p1_nom" placeholder="Saisir le nom..." oninput="p1RechercherDoublon()"
                       style="text-transform:uppercase;" autocomplete="off">
                <div class="autocomplete-list" id="p1_sugg" style="display:none;"></div>
            </div>
            <div class="form-group">
                <label>&nbsp;</label>
                <button class="btn btn-vert" onclick="p1Ajouter()">💾 Ajouter et enregistrer</button>
            </div>
            <div class="form-group" style="margin-left:24px;">
                <label>&nbsp;</label>
                <input type="text" id="p1_filtre" placeholder="🔍 Filtrer..." oninput="p1Filtrer()"
                       style="border:1px solid #cdd;border-radius:4px;padding:6px 10px;font-size:12px;width:200px;background:#fff;color:#222;">
            </div>
        </div>
        <div id="p1_msg" style="min-height:0;font-size:12px;margin-top:2px;"></div>
    </div>

    <!-- Liste -->
    <div class="card">

        <!-- Barre de tri -->
        <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-bottom:8px;padding-bottom:8px;border-bottom:1px solid var(--th-sep-color);">
            <span style="font-size:10px;color:var(--th-color-text-muted);font-weight:bold;">TRIER :</span>
            <button class="btn-tri actif" id="btn_tri_alpha" data-dir="az" onclick="p1ToggleTri('alpha')">A → Z</button>
            <button class="btn-tri" id="btn_tri_nbr" data-dir="asc" onclick="p1ToggleTri('nbr')">Nbr ↑</button>
            <button class="btn-tri" id="btn_tri_date" data-dir="asc" onclick="p1ToggleTri('date')">Date ↑</button>

            <span style="font-size:10px;color:var(--th-color-text-muted);font-weight:bold;margin-left:10px;">FILTRER PAR QUANTITÉ :</span>
            <button class="btn-filtre-qte" data-tranche="" onclick="p1FiltrerTranche('')">Tous</button>
            <button class="btn-filtre-qte" data-tranche="0-10"   onclick="p1FiltrerTranche('0-10')">≤ 10</button>
            <button class="btn-filtre-qte" data-tranche="11-100" onclick="p1FiltrerTranche('11-100')">11 – 100</button>
            <button class="btn-filtre-qte" data-tranche="101-500" onclick="p1FiltrerTranche('101-500')">101 – 500</button>
            <button class="btn-filtre-qte" data-tranche="501-99999" onclick="p1FiltrerTranche('501-99999')">&gt; 500</button>
        </div>

        <div id="p1_liste_wrap">
            <p style="color:#999;font-size:12px;">Cliquez sur Actualiser pour charger la liste.</p>
        </div>
    </div>

    <!-- Modale ordonnances du produit (double-clic) -->
    <div id="p1_ord_modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;
         background:rgba(0,0,0,0.5);z-index:9999;overflow-y:auto;">
        <div style="background:white;border-radius:8px;width:860px;max-width:96vw;
                    margin:40px auto;box-shadow:0 8px 32px rgba(0,0,0,0.3);">
            <div style="background:var(--th-bg-header-s);color:white;padding:10px 16px;border-radius:8px 8px 0 0;
                        display:flex;justify-content:space-between;align-items:center;">
                <span id="p1_ord_titre" style="font-weight:bold;font-size:14px;">📋 Ordonnances</span>
                <button onclick="document.getElementById('p1_ord_modal').style.display='none'"
                        style="background:rgba(255,255,255,0.2);color:white;border:none;border-radius:4px;
                               padding:4px 12px;cursor:pointer;font-size:13px;">✕ Fermer</button>
            </div>
            <div style="padding:16px;">
                <div id="p1_ord_wrap"></div>
            </div>
        </div>
    </div>

</div>

<!-- ══════════════════════════════════════════════════
     PANEL 2 — CHERCHER UNE ORDONNANCE
══════════════════════════════════════════════════ -->
<div id="panel2" class="panel <?= $ongletActif===2?'actif':'' ?>">

    <!-- Recherche -->
    <div class="card">
        <div class="card-titre">🔍 Rechercher une ordonnance</div>
        <div class="form-row">
            <div class="form-group">
                <label>Date ordonnance</label>
                <input type="date" id="p2_date" style="width:160px;"
                    oninput="p2ActiverChamp('date')" onchange="p2ActiverChamp('date')">
            </div>
            <div style="align-self:flex-end;color:#aaa;font-size:13px;padding-bottom:8px;font-weight:bold;">ou</div>
            <div class="form-group" style="flex:1;">
                <label>Nom / Prénom du patient</label>
                <input type="text" id="p2_nom" placeholder="Rechercher par nom..."
                    oninput="p2ActiverChamp('nom')">
            </div>
            <div style="align-self:flex-end;color:#aaa;font-size:13px;padding-bottom:8px;font-weight:bold;">ou</div>
            <div class="form-group">
                <label>N° Patient</label>
                <input type="number" id="p2_nopat" placeholder="N°" style="width:90px;"
                    oninput="p2ActiverChamp('nopat')">
            </div>
            <div class="form-group">
                <label>&nbsp;</label>
                <button class="btn btn-bleu" onclick="p2Rechercher()">🔍 Rechercher</button>
            </div>
        </div>
        <div id="p2_msg" style="min-height:16px;font-size:12px;"></div>
    </div>

    <!-- Résultats -->
    <div class="card" id="p2_res_card" style="display:none;">
        <div class="card-titre">Résultats</div>
        <div id="p2_resultats"></div>
    </div>

    <!-- Formulaire modification -->
    <div class="card" id="p2_form_card" style="display:none;">
        <div class="card-titre">✏️ Modifier l'ordonnance</div>

        <input type="hidden" id="p2_n_ordon">
        <input type="hidden" id="p2_id_patient">

        <div class="form-row">
            <div class="form-group">
                <label>Date ordonnance</label>
                <input type="date" id="p2_f_date" style="width:160px;">
            </div>
            <div class="form-group" style="flex:1;">
                <label>Patient</label>
                <input type="text" id="p2_f_patient" readonly
                       style="background:#f5f5f5;color:#555;">
            </div>
            <div class="form-group">
                <label>N° PAT</label>
                <input type="text" id="p2_f_nopat" readonly
                       style="background:#f5f5f5;color:#555;width:80px;">
            </div>
        </div>

        <!-- Médicaments -->
        <div style="font-size:12px;font-weight:bold;color:var(--th-color-primary);margin:10px 0 6px;">💊 Médicaments :</div>
        <table class="lig-med-tbl">
            <thead>
                <tr>
                    <th style="width:38%">Médicament</th>
                    <th style="width:30%">Posologie</th>
                    <th style="width:22%">Durée</th>
                    <th style="width:10%"></th>
                </tr>
            </thead>
            <tbody id="p2_lignes"></tbody>
        </table>
        <button class="btn btn-vert" style="margin-top:8px;" onclick="p2AjouterLigne()">✚ Médicament</button>

        <!-- RDV optionnel -->
        <div class="rdv-section" style="margin-top:14px;">
            <div class="rdv-titre">📅 Date &amp; heure RDV <span style="font-weight:normal;color:#aaa;">(optionnel)</span></div>
            <input type="hidden" id="p2_rdv_h">
            <input type="hidden" id="p2_heure_h">
            <div style="display:flex;gap:10px;align-items:center;margin-bottom:8px;flex-wrap:wrap;">
                <div class="form-group">
                    <label>Date RDV</label>
                    <input type="date" id="p2_rdv_visible" onchange="p2RdvDateChange(this.value)"
                           style="width:160px;">
                </div>
                <div class="form-group">
                    <label>Heure RDV</label>
                    <input type="text" id="p2_heure_visible" placeholder="—:——" readonly
                           style="width:80px;background:#f5f5f5;">
                </div>
                <div class="form-group">
                    <label>Acte</label>
                    <input type="text" id="p2_acte" placeholder="ECG, CONTROL…" style="width:120px;">
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button class="btn btn-gris" onclick="p2EffacerRdv()">✕ Effacer RDV</button>
                </div>
            </div>
            <div class="rdv-delais">
                <span style="font-size:11px;color:#555;">Délai rapide :</span>
                <button onclick="p2RdvDelai(1,0)">1M</button>
                <button onclick="p2RdvDelai(3,0)">3M</button>
                <button onclick="p2RdvDelai(6,0)">6M</button>
                <button onclick="p2RdvDelai(0,7)">+7j</button>
            </div>
            <div id="p2_rdv_grille" class="creneau-grille"></div>
            <div id="p2_rdv_msg" style="font-size:11px;color:#e74c3c;margin-top:4px;"></div>
        </div>

        <div id="p2_f_msg" style="min-height:18px;font-size:12px;margin-top:8px;"></div>

        <div class="barre-btns">
            <button class="btn btn-vert"   onclick="p2Enregistrer()">💾 Enregistrer</button>
            <button class="btn btn-bleu"   id="p2_btn_imprimer" onclick="p2Imprimer()">🖨️ Imprimer</button>
            <button class="btn btn-violet" id="p2_btn_dossier"  onclick="p2OuvrirDossier()">📂 Retour au dossier</button>
            <button class="btn btn-gris"   onclick="p2Fermer()">✕ Fermer</button>
            <a href="index.php" class="btn btn-teal">🏠 Accueil</a>
        </div>
    </div>

</div>

<!-- ══════════════════════════════════════════════════
     PANEL 3 — RÉDIGER UNE ORDONNANCE
══════════════════════════════════════════════════ -->
<div id="panel3" class="panel <?= $ongletActif===3?'actif':'' ?>">

    <div class="card">
        <div class="card-titre">📝 Rédiger une ordonnance</div>
        <p style="font-size:12px;color:var(--th-color-text-muted);margin-bottom:12px;">
            Cherchez le patient : vous serez redirigé vers son dossier pour rédiger l'ordonnance (médicaments + RDV).
        </p>

        <div class="form-row" style="align-items:flex-end;">
            <div class="form-group autocomplete-wrap" style="flex:1;">
                <label>Patient — Nom / Prénom</label>
                <input type="text" id="p3_nom" placeholder="Rechercher par nom..."
                       oninput="p3AutocompletePatient()" autocomplete="off">
                <div class="autocomplete-list" id="p3_sugg_nom" style="display:none;"></div>
            </div>
            <div style="align-self:flex-end;color:#999;font-size:12px;padding-bottom:8px;">ou</div>
            <div class="form-group">
                <label>N° Patient</label>
                <input type="number" id="p3_nopat" placeholder="N°" style="width:90px;"
                       oninput="p3ChercherParNum()" onkeydown="if(event.key==='Enter') p3AllerDossier()">
            </div>
            <div class="form-group">
                <label>&nbsp;</label>
                <button class="btn btn-vert" onclick="p3AllerDossier()">📝 Rédiger l'ordonnance</button>
            </div>
        </div>
        <div id="p3_patient_choisi" style="background:#e8f5e9;border:1px solid #27ae60;border-radius:5px;
             padding:6px 12px;font-size:12px;font-weight:bold;color:#1a5e33;margin-bottom:10px;display:none;">
        </div>
        <input type="hidden" id="p3_id_patient">
    </div>

</div>
</div><!-- fin .contenu -->

<script>
// ════════════════════════════════════════════════════════════
// DONNÉES PHP → JS
// ════════════════════════════════════════════════════════════
const MEDS   = <?= json_encode(array_map(fn($m)=>['id'=>$m['NuméroPRODUIT'],'nom'=>$m['PRODUIT']],$listeMeds)) ?>;
const POSOS  = <?= json_encode($posologies) ?>;
const DUREES = <?= json_encode($durees) ?>;

// ── Onglets ──────────────────────────────────────────────────
function switchOng(n) {
    document.querySelectorAll('.panel').forEach(p => p.classList.remove('actif'));
    document.querySelectorAll('.ong-btn').forEach((b,i) => b.classList.toggle('actif', i===n-1));
    document.getElementById('panel'+n).classList.add('actif');
    history.replaceState(null,'','?ong='+n);
}

// ════════════════════════════════════════════════════════════
// PANEL 1 — CATALOGUE
// ════════════════════════════════════════════════════════════
let p1Liste = [];

function p1RechercherDoublon() {
    const q = document.getElementById('p1_nom').value.trim();
    const sugg = document.getElementById('p1_sugg');
    document.getElementById('p1_msg').textContent = '';
    if (q.length < 2) { sugg.style.display = 'none'; return; }
    fetch('ajax_gestion_ord.php?action=chercher_produits&q=' + encodeURIComponent(q))
        .then(r => r.json()).then(data => {
            if (!data.length) { sugg.style.display = 'none'; return; }
            sugg.innerHTML = data.map(d =>
                `<div onclick="p1SelectProduit('${d.PRODUIT.replace(/'/g,"\\'")}')">
                    ${d.PRODUIT}
                </div>`).join('');
            sugg.style.display = 'block';
        });
}

function p1SelectProduit(nom) {
    document.getElementById('p1_nom').value = nom;
    document.getElementById('p1_sugg').style.display = 'none';
    document.getElementById('p1_msg').innerHTML = `<span class="msg-err">⚠️ Ce médicament existe déjà dans le catalogue.</span>`;
}

document.addEventListener('click', e => {
    if (!e.target.closest('.autocomplete-wrap')) {
        document.querySelectorAll('.autocomplete-list').forEach(el => el.style.display = 'none');
    }
});

function p1Ajouter() {
    const nom = document.getElementById('p1_nom').value.trim().toUpperCase();
    const msgEl = document.getElementById('p1_msg');
    if (!nom) { msgEl.innerHTML = '<span class="msg-err">⛔ Veuillez saisir un nom.</span>'; return; }
    msgEl.innerHTML = '<span style="color:#999;">Enregistrement…</span>';
    fetch('ajax_gestion_ord.php', { method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({action:'ajouter_produit', nom}) })
        .then(r => r.json()).then(data => {
            if (data.success) {
                msgEl.innerHTML = `<span class="msg-ok">✅ "${data.nom}" ajouté au catalogue.</span>`;
                document.getElementById('p1_nom').value = '';
                p1Liste.push({id: data.id, nom: data.nom, nb: 0, datePremiere: null});
                p1AfficherListe(p1Liste);
            } else {
                msgEl.innerHTML = `<span class="msg-err">❌ ${data.error}</span>`;
            }
        });
}

function p1ChargerListe() {
    document.getElementById('p1_liste_wrap').innerHTML = '<span style="color:#999;">Chargement…</span>';
    fetch('ajax_gestion_ord.php?action=liste_produits')
        .then(r => r.json()).then(data => {
            p1Liste = data.map(d => ({
                id: d.NuméroPRODUIT, nom: d.PRODUIT, nb: parseInt(d.nb_utilisations)||0,
                datePremiere: d.premiere_prescription || null
            }));
            p1AfficherListe(p1Liste);
        });
}

let p1TrancheActive = '';
let p1TriActif = 'alpha_az';

function p1ToggleTri(groupe) {
    const btn = document.getElementById('btn_tri_' + groupe);
    const etaitActif = btn.classList.contains('actif');

    // Si déjà actif → on inverse sa direction. Sinon on garde sa direction actuelle.
    if (etaitActif) {
        if (groupe === 'alpha') btn.dataset.dir = (btn.dataset.dir === 'az') ? 'za' : 'az';
        else btn.dataset.dir = (btn.dataset.dir === 'asc') ? 'desc' : 'asc';
    }
    const dir = btn.dataset.dir;

    // Activer ce bouton, désactiver les autres
    document.querySelectorAll('.btn-tri').forEach(b => b.classList.remove('actif'));
    btn.classList.add('actif');

    // Mettre à jour le libellé + le tri actif
    if (groupe === 'alpha') {
        btn.textContent = dir === 'az' ? 'A → Z' : 'Z → A';
        p1TriActif = dir === 'az' ? 'alpha_az' : 'alpha_za';
    } else if (groupe === 'nbr') {
        btn.textContent = dir === 'asc' ? 'Nbr ↑' : 'Nbr ↓';
        p1TriActif = dir === 'asc' ? 'util_asc' : 'util_desc';
    } else if (groupe === 'date') {
        btn.textContent = dir === 'asc' ? 'Date ↑' : 'Date ↓';
        p1TriActif = dir === 'asc' ? 'date_asc' : 'date_desc';
    }

    p1Filtrer();
}

function p1Trier(liste) {
    const triee = [...liste];
    switch (p1TriActif) {
        case 'alpha_az':  triee.sort((a,b) => a.nom.localeCompare(b.nom)); break;
        case 'alpha_za':  triee.sort((a,b) => b.nom.localeCompare(a.nom)); break;
        case 'util_desc': triee.sort((a,b) => b.nb - a.nb); break;
        case 'util_asc':  triee.sort((a,b) => a.nb - b.nb); break;
        case 'date_asc':
            triee.sort((a,b) => (a.datePremiere||'9999') > (b.datePremiere||'9999') ? 1 : -1);
            break;
        case 'date_desc':
            triee.sort((a,b) => (a.datePremiere||'0000') < (b.datePremiere||'0000') ? 1 : -1);
            break;
    }
    return triee;
}

function p1FiltrerTranche(tranche) {
    p1TrancheActive = tranche;
    document.querySelectorAll('.btn-filtre-qte').forEach(b => {
        b.classList.toggle('actif', b.dataset.tranche === tranche);
    });
    p1Filtrer();
}

function p1AfficherListe(liste) {
    const wrap = document.getElementById('p1_liste_wrap');
    if (!liste.length) { wrap.innerHTML = '<p style="color:#999;font-size:12px;">Aucun résultat.</p>'; return; }

    const nbOrphelins = liste.filter(m => m.nb === 0).length;
    const listeTriee = p1Trier(liste);

    let html = `<div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;flex-wrap:wrap;">
        <span style="font-size:11px;color:var(--th-color-text-muted);">${liste.length} médicament(s)</span>`;
    if (nbOrphelins > 0) {
        html += `<span style="font-size:11px;background:#fff3cd;border:1px solid #f39c12;border-radius:3px;padding:2px 8px;color:#856404;">
            ⚠️ ${nbOrphelins} non utilisé(s)</span>
        <button class="btn btn-orange" style="padding:2px 8px;font-size:11px;" onclick="p1SupprimerOrphelins()">
            🗑 Supprimer les non utilisés</button>`;
    }
    html += `<span style="font-size:10px;color:var(--th-color-text-muted);margin-left:auto;">💡 Double-clic sur une carte → voir ses ordonnances</span></div>`;
    const nbCols = window.innerWidth <= 500 ? 2 : (window.innerWidth <= 700 ? 3 : (window.innerWidth <= 1100 ? 4 : (window.innerWidth <= 1500 ? 5 : 6)));
    const nbLignes = Math.ceil(listeTriee.length / nbCols);
    html += `<div class="grille-catalogue" id="p1_grille" style="grid-template-rows:repeat(${nbLignes}, auto);"></div>`;
    wrap.innerHTML = html;

    const grille = document.getElementById('p1_grille');
    listeTriee.forEach(m => {
        const orphelin = m.nb === 0;
        const carte = document.createElement('div');
        carte.className = 'carte-med' + (orphelin ? ' orphelin' : '');
        carte.title = m.nom + ' — Double-clic pour voir les ordonnances';
        carte.innerHTML = `
            <span class="nom-med">${m.nom}</span>
            <span class="nb-util ${orphelin ? 'zero' : 'ok'}">${m.nb}</span>
            <button class="btn-sup-med" onclick="event.stopPropagation();p1Supprimer(${m.id},'${m.nom.replace(/'/g, "\\'")}')">🗑</button>`;
        carte.addEventListener('dblclick', () => p1VoirOrdonnances(m.id, m.nom));
        grille.appendChild(carte);
    });
}

function p1Filtrer() {
    const q = document.getElementById('p1_filtre').value.toLowerCase();
    let filtree = p1Liste.filter(m => m.nom.toLowerCase().includes(q));

    if (p1TrancheActive) {
        const [min, max] = p1TrancheActive.split('-').map(Number);
        filtree = filtree.filter(m => m.nb >= min && m.nb <= max);
    }

    p1AfficherListe(filtree);
}

function p1Supprimer(id, nom) {
    if (!confirm(`Supprimer "${nom}" du catalogue ?`)) return;
    fetch('ajax_gestion_ord.php', { method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({action:'supprimer_produit', id}) })
        .then(r => r.json()).then(data => {
            if (data.success) {
                p1Liste = p1Liste.filter(m => m.id !== id);
                p1AfficherListe(p1Liste);
                document.getElementById('p1_ord_modal').style.display = 'none';
            } else {
                alert('❌ ' + data.error);
            }
        });
}

function p1SupprimerOrphelins() {
    const nb = p1Liste.filter(m => m.nb === 0).length;
    if (!confirm(`Supprimer les ${nb} médicament(s) non utilisés du catalogue ?`)) return;
    fetch('ajax_gestion_ord.php', { method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({action:'supprimer_orphelins'}) })
        .then(r => r.json()).then(data => {
            if (data.success) {
                p1Liste = p1Liste.filter(m => m.nb > 0);
                p1AfficherListe(p1Liste);
                document.getElementById('p1_msg').innerHTML =
                    `<span class="msg-ok">✅ ${data.nb} médicament(s) supprimé(s).</span>`;
            }
        });
}

function p1VoirOrdonnances(prodId, prodNom) {
    const card = document.getElementById('p1_ord_modal');
    const wrap = document.getElementById('p1_ord_wrap');
    document.getElementById('p1_ord_titre').textContent = `📋 Ordonnances contenant : ${prodNom}`;
    wrap.innerHTML = '<span style="color:#999;font-size:12px;">⏳ Chargement…</span>';
    card.style.display = 'flex';

    fetch(`ajax_gestion_ord.php?action=ordonnances_du_produit&prod_id=${prodId}`)
        .then(r => {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.text(); // text d'abord pour voir les erreurs PHP
        })
        .then(txt => {
            let rows;
            try { rows = JSON.parse(txt); }
            catch(e) {
                wrap.innerHTML = `<pre style="color:#e74c3c;font-size:11px;white-space:pre-wrap;">${txt.substring(0,500)}</pre>`;
                return;
            }
            if (!Array.isArray(rows)) {
                wrap.innerHTML = `<p style="color:#e74c3c;font-size:12px;">Erreur : ${rows.error || JSON.stringify(rows)}</p>`;
                return;
            }
            if (!rows.length) {
                wrap.innerHTML = '<p style="color:#999;font-size:12px;">Aucune ordonnance — médicament non utilisé.</p>';
                return;
            }
            const tbl = document.createElement('table');
            tbl.className = 'tbl';
            tbl.innerHTML = `<thead><tr>
                <th>N° Ord</th><th>Date</th><th>Patient</th><th>N°PAT</th><th>Posologie</th><th>Durée</th><th></th>
            </tr></thead>`;
            const tbody = document.createElement('tbody');
            rows.forEach(r => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${r.n_ordon}</td>
                    <td>${r.date_fr || '—'}</td>
                    <td>${r.NOMPRENOM || '—'}</td>
                    <td>${r.id}</td>
                    <td style="font-size:11px;">${r.posologie || '—'}</td>
                    <td style="font-size:11px;">${r.DUREE || '—'}</td>
                    <td><button class="btn btn-violet" style="padding:2px 7px;font-size:11px;"
                        onclick="p1OuvrirModif(${r.n_ordon},${r.id})">✏️ Modifier</button></td>`;
                tbody.appendChild(tr);
            });
            tbl.appendChild(tbody);
            wrap.innerHTML = `<p style="font-size:11px;color:#999;margin-bottom:4px;">${rows.length} ordonnance(s)</p>`;
            wrap.appendChild(tbl);
        })
        .catch(e => {
            wrap.innerHTML = `<p style="color:#e74c3c;font-size:12px;">❌ Erreur réseau : ${e.message}</p>`;
        });
}

function p1OuvrirModif(nOrd, id) {
    switchOng(2);
    fetch(`ajax_gestion_ord.php?action=get_ordonnance&n_ordon=${nOrd}&id=${id}`)
        .then(r => r.json()).then(ord => {
            if (ord.error) { alert('❌ ' + ord.error); return; }
            document.getElementById('p2_res_card').style.display = 'none';
            document.getElementById('p2_msg').innerHTML =
                `<span class="msg-ok">Ordonnance N°${nOrd} chargée depuis le catalogue.</span>`;
            p2ChargerFormulaire(ord);
        });
}


// ════════════════════════════════════════════════════════════
// PANEL 2 — CHERCHER / MODIFIER
// ════════════════════════════════════════════════════════════
let p2Idx = 0;

// Champ actif : 'date' | 'nom' | 'nopat'
function p2ActiverChamp(champ) {
    // Vider immédiatement les deux autres champs à chaque frappe
    if (champ !== 'date')  document.getElementById('p2_date').value  = '';
    if (champ !== 'nom')   document.getElementById('p2_nom').value   = '';
    if (champ !== 'nopat') document.getElementById('p2_nopat').value = '';
    // Effacer résultats et formulaire précédents
    document.getElementById('p2_res_card').style.display  = 'none';
    document.getElementById('p2_form_card').style.display = 'none';
    document.getElementById('p2_msg').textContent = '';
}

function p2Rechercher() {
    const date  = document.getElementById('p2_date').value;
    const nom   = document.getElementById('p2_nom').value.trim();
    const nopat = document.getElementById('p2_nopat').value.trim();
    if (!date && !nom && !nopat) {
        document.getElementById('p2_msg').innerHTML = '<span class="msg-err">Veuillez renseigner un critère de recherche.</span>';
        return;
    }

    const msgEl = document.getElementById('p2_msg');
    msgEl.innerHTML = '<span style="color:#999;">Recherche…</span>';

    const params = new URLSearchParams({action:'chercher_ordonnances'});
    if (date)  params.set('date',  date);
    if (nom)   params.set('nom',   nom);
    if (nopat) params.set('nopat', nopat);

    fetch('ajax_gestion_ord.php?' + params)
        .then(r => r.json()).then(data => {
            msgEl.innerHTML = data.length
                ? `<span class="msg-ok">${data.length} résultat(s) — cliquez sur une ligne pour modifier.</span>`
                : '<span class="msg-err">Aucune ordonnance trouvée.</span>';
            p2AfficherResultats(data);
        });
}

function p2AfficherResultats(rows) {
    const card = document.getElementById('p2_res_card');
    const wrap = document.getElementById('p2_resultats');
    if (!rows.length) { card.style.display = 'none'; return; }
    card.style.display = 'block';
    const tbl = document.createElement('table');
    tbl.className = 'tbl';
    tbl.innerHTML = `<thead><tr>
        <th>N° Ord</th><th>Date</th><th>Patient</th><th>N°PAT</th><th>Médicaments</th><th>RDV</th>
    </tr></thead>`;
    const tbody = document.createElement('tbody');
    rows.forEach(r => {
        const meds = (r.medicaments||[]).map(m=>m.PRODUIT).filter(Boolean).join(', ') || '—';
        const rdv  = r['DATE REDEZ VOUS'] ? r['DATE REDEZ VOUS'].split(' ')[0] : '—';
        const tr = document.createElement('tr');
        tr.innerHTML = `<td>${r.n_ordon}</td><td>${r.date_fr||'—'}</td>
            <td>${r.NOMPRENOM||'—'}</td><td>${r.id}</td>
            <td style="max-width:200px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;">${meds}</td>
            <td>${rdv}</td>`;
        tr.onclick = () => {
            document.querySelectorAll('#p2_resultats tr').forEach(x => x.classList.remove('selectionne'));
            tr.classList.add('selectionne');
            p2ChargerFormulaire(r);
        };
        tbody.appendChild(tr);
    });
    tbl.appendChild(tbody);
    wrap.innerHTML = '';
    wrap.appendChild(tbl);
}

function p2ChargerFormulaire(ord) {
    document.getElementById('p2_form_card').style.display = 'block';
    document.getElementById('p2_n_ordon').value   = ord.n_ordon;
    document.getElementById('p2_id_patient').value = ord.id;
    document.getElementById('p2_f_patient').value  = ord.NOMPRENOM || '';
    document.getElementById('p2_f_nopat').value    = ord.id;
    document.getElementById('p2_f_msg').textContent = '';

    // Date ordonnance
    if (ord.date_ordon) {
        const d = ord.date_ordon.split(' ')[0]; // YYYY-MM-DD
        document.getElementById('p2_f_date').value = d.substring(0,10);
    }

    // RDV
    const rdvRaw = ord['DATE REDEZ VOUS'] || '';
    if (rdvRaw) {
        const rdvDate = rdvRaw.split(' ')[0].substring(0,10);
        document.getElementById('p2_rdv_h').value       = rdvDate;
        document.getElementById('p2_rdv_visible').value = rdvDate;
    } else {
        document.getElementById('p2_rdv_h').value       = '';
        document.getElementById('p2_rdv_visible').value = '';
    }
    document.getElementById('p2_heure_h').value       = ord.HeureRDV || '';
    document.getElementById('p2_heure_visible').value = ord.HeureRDV || '';
    document.getElementById('p2_acte').value           = ord.acte1 || '';

    // Médicaments
    const tbody = document.getElementById('p2_lignes');
    tbody.innerHTML = '';
    p2Idx = 0;
    (ord.medicaments || []).forEach(m => p2AjouterLigne(m));
    if (!ord.medicaments || !ord.medicaments.length) p2AjouterLigne();

    document.getElementById('p2_form_card').scrollIntoView({behavior:'smooth'});
}

function p2AjouterLigne(med) {
    const i = p2Idx++;
    let optsMed = '<option value="">— Médicament —</option>';
    MEDS.forEach(m => {
        const sel = med && m.nom === med.PRODUIT ? ' selected' : '';
        optsMed += `<option value="${m.id}"${sel}>${m.nom}</option>`;
    });
    let optsPoso = '<option value="">— Posologie —</option>';
    POSOS.forEach(p => {
        const sel = med && med.posologie === p ? ' selected' : '';
        optsPoso += `<option value="${p}"${sel}>${p}</option>`;
    });
    let optsDuree = '<option value="">— Durée —</option>';
    DUREES.forEach(d => {
        const sel = med && med.DUREE === d ? ' selected' : '';
        optsDuree += `<option value="${d}"${sel}>${d}</option>`;
    });
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td><select id="p2_med_${i}" style="width:100%">${optsMed}</select></td>
        <td><select id="p2_poso_${i}" style="width:100%">${optsPoso}</select></td>
        <td><select id="p2_duree_${i}" style="width:100%">${optsDuree}</select></td>
        <td><button class="btn btn-rouge" style="padding:3px 7px;" onclick="this.closest('tr').remove()">🗑</button></td>`;
    document.getElementById('p2_lignes').appendChild(tr);
}

function p2RdvDelai(mois, jours) {
    const d = new Date();
    if (mois)  d.setMonth(d.getMonth() + mois);
    if (jours) d.setDate(d.getDate() + jours);
    const iso = d.toISOString().split('T')[0];
    document.getElementById('p2_rdv_h').value       = iso;
    document.getElementById('p2_rdv_visible').value = iso;
    p2RdvDateChange(iso);
}

function p2RdvDateChange(val) {
    document.getElementById('p2_rdv_h').value = val;
    if (!val) { document.getElementById('p2_rdv_grille').innerHTML = ''; return; }
    p2ChargerCreneaux(val);
}

function p2ChargerCreneaux(date) {
    const grille = document.getElementById('p2_rdv_grille');
    const msg    = document.getElementById('p2_rdv_msg');
    grille.innerHTML = '<span style="color:#999;font-size:11px;">⏳ Chargement créneaux…</span>';
    msg.textContent = '';
    fetch('ajax_creneaux.php?date=' + date).then(r => r.json()).then(data => {
        grille.innerHTML = '';
        if (!data.date_ok) { msg.textContent = '⛔ ' + data.raison; return; }
        data.creneaux.forEach(c => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.textContent = c.heure;
            btn.className = 'creneau-btn ' + c.statut;
            if (c.statut === 'plein') { btn.disabled = true; }
            else btn.onclick = () => {
                document.querySelectorAll('#p2_rdv_grille .creneau-btn').forEach(b => b.classList.remove('selectionne'));
                btn.classList.add('selectionne');
                document.getElementById('p2_heure_h').value       = c.heure;
                document.getElementById('p2_heure_visible').value = c.heure;
            };
            grille.appendChild(btn);
        });
    }).catch(() => { grille.innerHTML = ''; });
}

function p2EffacerRdv() {
    ['p2_rdv_h','p2_rdv_visible','p2_heure_h'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('p2_heure_visible').value = '';
    document.getElementById('p2_rdv_grille').innerHTML = '';
}

function p2Enregistrer() {
    const nOrd  = document.getElementById('p2_n_ordon').value;
    const id    = document.getElementById('p2_id_patient').value;
    const date  = document.getElementById('p2_f_date').value;
    const msgEl = document.getElementById('p2_f_msg');
    if (!nOrd || !id) { msgEl.innerHTML = '<span class="msg-err">⛔ Aucune ordonnance sélectionnée.</span>'; return; }
    if (!date) { msgEl.innerHTML = '<span class="msg-err">⛔ La date est obligatoire.</span>'; return; }

    const lignes = [];
    document.querySelectorAll('#p2_lignes tr').forEach(tr => {
        const idx = tr.querySelector('select')?.id?.replace('p2_med_','');
        if (idx === undefined) return;
        const med  = document.getElementById(`p2_med_${idx}`)?.value;
        const poso = document.getElementById(`p2_poso_${idx}`)?.value;
        const duree = document.getElementById(`p2_duree_${idx}`)?.value;
        if (med) lignes.push({med, poso, duree});
    });

    msgEl.innerHTML = '<span style="color:#999;">Enregistrement…</span>';
    fetch('ajax_gestion_ord.php', { method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({
            action:'modifier_ordonnance',
            n_ordon: nOrd, id, date_ordon: date,
            date_rdv:  document.getElementById('p2_rdv_h').value,
            heure_rdv: document.getElementById('p2_heure_h').value,
            acte:      document.getElementById('p2_acte').value,
            lignes
        }) })
        .then(r => r.json()).then(data => {
            if (data.success) {
                msgEl.innerHTML = '<span class="msg-ok">✅ Ordonnance mise à jour.</span>';
            } else {
                msgEl.innerHTML = `<span class="msg-err">❌ ${data.error}</span>`;
            }
        });
}

function p2Imprimer() {
    const nOrd = document.getElementById('p2_n_ordon').value;
    const id   = document.getElementById('p2_id_patient').value;
    if (nOrd && id) window.open(`print_ordonnance.php?id=${id}&ord=${nOrd}`, '_blank');
}

function p2OuvrirDossier() {
    const id = document.getElementById('p2_id_patient').value;
    if (id) window.location.href = `dossier.php?id=${id}`;
}

function p2Fermer() {
    document.getElementById('p2_form_card').style.display = 'none';
    document.getElementById('p2_n_ordon').value = '';
    document.querySelectorAll('#p2_resultats tr').forEach(x => x.classList.remove('selectionne'));
}

// ════════════════════════════════════════════════════════════
// PANEL 3 — RÉDIGER UNE ORDONNANCE
// ════════════════════════════════════════════════════════════
function p3AutocompletePatient() {
    const q = document.getElementById('p3_nom').value.trim();
    const sugg = document.getElementById('p3_sugg_nom');
    document.getElementById('p3_id_patient').value = '';
    p3MasquerPatientChoisi();
    if (q.length < 2) { sugg.style.display = 'none'; return; }
    fetch('ajax_gestion_ord.php?action=chercher_patients&q=' + encodeURIComponent(q))
        .then(r => r.json()).then(data => {
            if (!data.length) { sugg.style.display = 'none'; return; }
            sugg.innerHTML = data.map(p =>
                `<div onclick="p3SelectPatient(${p['N°PAT']},'${p.NOMPRENOM.replace(/'/g,"\\'")}')">
                    <strong>${p.NOMPRENOM}</strong> <span style="color:#999;font-size:11px;">N°${p['N°PAT']}</span>
                </div>`).join('');
            sugg.style.display = 'block';
        });
}

function p3ChercherParNum() {
    const n = document.getElementById('p3_nopat').value.trim();
    if (!n || n.length < 2) return;
    fetch('ajax_gestion_ord.php?action=get_patient&id=' + encodeURIComponent(n))
        .then(r => r.json()).then(data => {
            if (data['N°PAT']) {
                p3SelectPatient(data['N°PAT'], data.NOMPRENOM);
                document.getElementById('p3_nom').value = data.NOMPRENOM;
            }
        });
}

function p3SelectPatient(id, nom) {
    document.getElementById('p3_id_patient').value = id;
    document.getElementById('p3_nom').value        = nom;
    document.getElementById('p3_nopat').value      = id;
    document.getElementById('p3_sugg_nom').style.display = 'none';
    const div = document.getElementById('p3_patient_choisi');
    div.textContent = `✅ Patient sélectionné : ${nom}  (N° ${id})`;
    div.style.display = 'block';
}

function p3MasquerPatientChoisi() {
    document.getElementById('p3_patient_choisi').style.display = 'none';
}

// Redirige vers dossier.php, qui ouvre directement le formulaire "Nouvelle ordonnance"
function p3AllerDossier() {
    const id = document.getElementById('p3_id_patient').value || document.getElementById('p3_nopat').value.trim();
    if (!id) { alert('⛔ Veuillez sélectionner un patient (nom ou numéro).'); return; }
    window.location.href = `dossier.php?id=${id}&action=nouvelle_ordonnance`;
}

// ── Init ─────────────────────────────────────────────────────
window.addEventListener('DOMContentLoaded', () => {
    p1ChargerListe();  // chargement automatique du catalogue
});
</script>
</body>
</html>
