<?php
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';
$db = getDB();

// ── Période selon bouton ───────────────────────────────────────
$mode   = $_GET['mode'] ?? 'semaine';
$today  = isset($_GET['date']) ? new DateTime($_GET['date']) : new DateTime();
$todayS = $today->format('Y-m-d');

switch ($mode) {
    case 'aujourd_hui':
        $debut = new DateTime($todayS);
        $fin   = new DateTime($todayS);
        break;
   case 'semaine':
        $debut = new DateTime($todayS);
        $debut->modify('monday this week');
        $fin = clone $debut;
        $fin->modify('+6 days');
        break;
    case 'date':
        $debut = new DateTime($todayS);
        $debut->modify('monday this week');
        $fin = clone $debut;
        $fin->modify('+6 days');
        break;
    case 'mois':
        $debut = new DateTime($today->format('Y-m-01'));
        $fin   = new DateTime($today->format('Y-m-t'));
        break;
    case '3mois':
        $debut = new DateTime($todayS);
        $fin   = (clone $debut)->modify('+3 months -1 day');
        break;
    case '6mois':
        $debut = new DateTime($todayS);
        $fin   = (clone $debut)->modify('+6 months -1 day');
        break;
    default:
        $debut = new DateTime($todayS);
        $debut->modify('monday this week');
        $fin = clone $debut;
        $fin->modify('+6 days');
}

$debutS = $debut->format('Y-m-d');
$finS   = $fin->format('Y-m-d');

// ── NbrMax depuis T_Config ─────────────────────────────────────
$nbrMax = 20; // valeur par défaut
try {
    $stmtMax = $db->prepare("SELECT Valeur FROM T_Config WHERE Cle = 'NbrMax'");
    $stmtMax->execute();
    $row = $stmtMax->fetch(PDO::FETCH_ASSOC);
    if ($row) $nbrMax = (int)$row['Valeur'];
} catch (Exception $e) { /* garde la valeur par défaut */ }

// ── Jours fériés ──────────────────────────────────────────────
$feriesLabels = []; // ['2026-05-01' => 'Jour férié']
try {
    $stmtF = $db->query("SELECT DateFerie FROM T_JourFeries ORDER BY DateFerie");
    while ($f = $stmtF->fetch(PDO::FETCH_ASSOC)) {
        $raw = trim($f['DateFerie'] ?? '');
        $dateKey = '';
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $raw, $m)) {
            $dateKey = $m[3].'-'.$m[2].'-'.$m[1];
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            $dateKey = $raw;
        } else {
            $ts = strtotime($raw);
            if ($ts) $dateKey = date('Y-m-d', $ts);
        }
        if ($dateKey) $feriesLabels[$dateKey] = 'Jour férié';
    }
} catch (Exception $e) { /* ignore */ }

// ── RDV par jour ──────────────────────────────────────────────
$stmtRdv = $db->prepare("
    SELECT CONVERT(date, [DATE REDEZ VOUS]) AS jour, COUNT(*) AS nb
    FROM ORD
    WHERE CONVERT(date, [DATE REDEZ VOUS]) BETWEEN ? AND ?
    GROUP BY CONVERT(date, [DATE REDEZ VOUS])
");
$stmtRdv->execute([$debutS, $finS]);
$rdvParJour = [];
while ($row = $stmtRdv->fetch(PDO::FETCH_ASSOC)) {
    $rdvParJour[$row['jour']] = (int)$row['nb'];
}
$totalPeriode = array_sum($rdvParJour);

// ── Générer tous les jours ─────────────────────────────────────
$jours = [];
$cur = clone $debut;
while ($cur <= $fin) {
    $jours[] = $cur->format('Y-m-d');
    $cur->modify('+1 day');
}

// ── Fonctions utilitaires ──────────────────────────────────────
function labelCourt($dateStr) {
    $mois = ['','Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'];
    $ts = strtotime($dateStr);
    return date('d',$ts) . '/' . $mois[(int)date('n',$ts)] . '/' . date('Y',$ts);
}
function estWeekend($dateStr) {
    $w = (int)date('w', strtotime($dateStr));
    return $w === 0 || $w === 6;
}

// Couleurs fond selon nb RDV (weekend et fériés gérés séparément)
function couleurFond($nb, $nbrMax) {
    if ($nb === 0)            return '#f5f7fa';
    $ratio = $nb / $nbrMax;
    if ($ratio <= 0.25)       return '#eafaf1'; // vert très clair
    if ($ratio <= 0.5)        return '#fef9e7'; // jaune très clair
    if ($ratio <= 0.75)       return '#fef0e6'; // orange très clair
    if ($ratio < 1.0)         return '#fde8e8'; // rouge clair
    return '#f8d7da';                           // rouge saturé = plein
}
function couleurBarre($nb, $nbrMax) {
    if ($nb === 0) return '#dde';
    $ratio = $nb / $nbrMax;
    if ($ratio <= 0.25) return '#27ae60'; // vert
    if ($ratio <= 0.5)  return '#f1c40f'; // jaune
    if ($ratio <= 0.75) return '#3498db'; // bleu
    return '#e74c3c';                     // rouge
}

// Regrouper les jours par mois
$parMois = [];
foreach ($jours as $j) {
    $cle = date('Y-m', strtotime($j));
    $parMois[$cle][] = $j;
}
$moisNoms = ['01'=>'Janvier','02'=>'Février','03'=>'Mars','04'=>'Avril',
             '05'=>'Mai','06'=>'Juin','07'=>'Juillet','08'=>'Août',
             '09'=>'Septembre','10'=>'Octobre','11'=>'Novembre','12'=>'Décembre'];
$joursNoms = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
$joursNomsCourts = ['Dim','Lun','Mar','Mer','Jeu','Ven','Sam'];

// Regroupe les jours d'un mois en lignes : Lun→Ven séparés, puis Sam+Dim fusionnés en "WE j-j"
// $alignerCalendrier = true → on cale sur les vraies semaines civiles (Lundi→Dimanche) du
// mois entier, avec des cases vides avant le 1er jour et après le dernier, afin que la
// ligne week-end tombe TOUJOURS sur la même hauteur de ligne, quel que soit le mois.
function grouperSemaines($joursM, $alignerCalendrier = false) {
    if (empty($joursM)) return [];

    if (!$alignerCalendrier) {
        // ── Comportement simple : seulement les jours réellement présents ──
        $lignes = [];
        $i = 0;
        $n = count($joursM);
        while ($i < $n) {
            $jour = $joursM[$i];
            $dow  = (int)date('w', strtotime($jour));
            if ($dow === 6) {
                $samedi   = $jour;
                $dimanche = ($i + 1 < $n) ? $joursM[$i + 1] : null;
                if ($dimanche && (int)date('w', strtotime($dimanche)) === 0) {
                    $lignes[] = ['type' => 'we', 'jours' => [$samedi, $dimanche], 'vide' => false];
                    $i += 2;
                } else {
                    $lignes[] = ['type' => 'we', 'jours' => [$samedi], 'vide' => false];
                    $i += 1;
                }
            } elseif ($dow === 0) {
                $lignes[] = ['type' => 'we', 'jours' => [$jour], 'vide' => false];
                $i += 1;
            } else {
                $lignes[] = ['type' => 'jour', 'jours' => [$jour], 'vide' => false];
                $i += 1;
            }
        }
        return $lignes;
    }

    // ── Alignement calendrier : semaines civiles complètes Lundi→Dimanche ──
    $present = array_flip($joursM);

    $cleMois         = date('Y-m', strtotime($joursM[0]));
    $premierJourMois = $cleMois . '-01';
    $dernierJourMois = date('Y-m-t', strtotime($premierJourMois));

    // Lundi de la semaine qui contient le 1er du mois
    $dowPremier = (int)date('N', strtotime($premierJourMois)); // 1=lundi … 7=dimanche
    $lundiDebut = new DateTime($premierJourMois);
    $lundiDebut->modify('-' . ($dowPremier - 1) . ' days');

    // Dimanche de la semaine qui contient le dernier jour du mois
    $dowDernier  = (int)date('N', strtotime($dernierJourMois));
    $dimancheFin = new DateTime($dernierJourMois);
    $dimancheFin->modify('+' . (7 - $dowDernier) . ' days');

    $lignes = [];
    $cur = clone $lundiDebut;
    while ($cur <= $dimancheFin) {
        // 5 lignes individuelles : Lundi → Vendredi (vides si hors mois/période)
        for ($d = 0; $d < 5; $d++) {
            $ds = $cur->format('Y-m-d');
            $lignes[] = ['type' => 'jour', 'jours' => [$ds], 'vide' => !isset($present[$ds])];
            $cur->modify('+1 day');
        }
        // 1 ligne fusionnée : Samedi + Dimanche
        $sam = $cur->format('Y-m-d'); $cur->modify('+1 day');
        $dim = $cur->format('Y-m-d'); $cur->modify('+1 day');
        $samOk = isset($present[$sam]);
        $dimOk = isset($present[$dim]);
        $joursWe = [];
        if ($samOk) $joursWe[] = $sam;
        if ($dimOk) $joursWe[] = $dim;
        if (empty($joursWe)) $joursWe = [$sam, $dim];
        $lignes[] = ['type' => 'we', 'jours' => $joursWe, 'vide' => (!$samOk && !$dimOk)];
    }
    return $lignes;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php
$themes_valides = ['theme-0','theme-a','theme-b','theme-c'];
$theme = $_COOKIE['logycab_theme'] ?? 'theme-0';
if (!in_array($theme, $themes_valides)) $theme = 'theme-0';
?>
<title>Planning — Logycab</title>
<link rel="stylesheet" href="themes.css">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
       background: var(--th-bg-page); font-size: 13px; color: var(--th-color-text); }

/* ── Header ── */
.header {
    background: var(--th-bg-header);
    color: white; padding: 6px 14px;
    display: flex; align-items: center; gap: 8px; flex-wrap: nowrap;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}
.header h1 { font-size: 15px; font-weight: 700; white-space: nowrap; }
.btn-h { color: white; text-decoration: none; border: none; cursor: pointer;
         padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: bold;
         display: inline-flex; align-items: center; height: 26px; white-space: nowrap; }
.btn-h.green  { background: #27ae60; }
.btn-h.navy   { background: var(--th-btn-navy); }
.btn-h.blue   { background: var(--th-btn-blue); }
.btn-h.orange { background: #e67e22; }
.btn-h.purple { background: #8e44ad; }
.btn-h.grey   { background: #888; pointer-events: none; opacity: 0.7; cursor: default; }
.btn-h:not(.grey):hover { opacity: 0.82; }
/* Barre recherche intégrée dans le header */
.search-hdr {
    padding: 2px 8px; border-radius: 4px; font-size: 11px; height: 26px;
    border: 1px solid rgba(255,255,255,0.35); background: rgba(255,255,255,0.12);
    color: white; outline: none; width: 170px; flex-shrink: 0;
}
.search-hdr::placeholder { color: rgba(255,255,255,0.5); }
.search-hdr:focus { border-color: rgba(255,255,255,0.7); background: rgba(255,255,255,0.2); }
.header-clock { background: rgba(255,255,255,0.12);
                border-radius: 6px; padding: 3px 10px; text-align: center;
                min-width: 130px; flex-shrink: 0; }
.header-clock .ct { font-size: 15px; font-weight: bold; letter-spacing: 1px; color: white; }
.header-clock .cd { font-size: 9px; opacity: 0.75; }

/* ── Barre de modes ── */
.mode-bar {
    background: var(--th-bg-card); padding: 6px 14px;
    display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
    border-bottom: 2px solid var(--th-border-statsbar);
    box-shadow: 0 2px 4px rgba(0,0,0,0.06);
}

/* ── Style unique pour toutes les boîtes de la barre (modes / dates / total / légende) ── */
.btn-mode, .date-box, .leg-item {
    border: 1px solid #ccc; border-radius: 4px; background: white;
    font-size: 12px; font-weight: 600; color: #333;
}
.btn-mode {
    padding: 5px 14px; cursor: pointer; text-decoration: none; display: inline-block;
    transition: all 0.15s;
}
.btn-mode:hover { border-color: #2e6da4; color: #2e6da4; }
.btn-mode.actif { background: var(--th-btn-navy); color: white; border-color: var(--th-btn-navy); }

.date-box {
    padding: 5px 12px; display: inline-flex; align-items: center; white-space: nowrap;
}
.grp-debut { margin-left: 10px; }

/* ── Légende (intégrée à la barre de modes) ── */
.leg-titre { font-size: 12px; font-weight: 600; color: #333; }
.leg-item  { display: flex; align-items: center; gap: 6px; padding: 5px 10px; }
.leg-swatch { width: 16px; height: 14px; border-radius: 2px; flex-shrink: 0; }

/* ── Corps principal ── */
.planning-body { padding: 10px 14px; }

/* ── Conteneur des mois — colonnes adaptatives à la largeur d'écran ── */
.mois-conteneur {
    display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-start;
}
.mois-bloc {
    flex: 0 0 220px; width: 220px;
}
.mois-titre {
    font-size: 12px; font-weight: 800; color: var(--th-color-primary);
    text-transform: uppercase; letter-spacing: 1px;
    padding: 4px 4px 4px; margin-bottom: 4px;
    border-bottom: 2px solid var(--th-border-statsbar);
    text-align: center;
}

/* ── Liste des jours du mois ── */
.mois-jours { display: flex; flex-direction: column; gap: 1px; }

/* ── Ligne jour normal — 3 blocs distincts : Nom | Jauge | Fraction ── */
.jour-ligne {
    display: flex; align-items: stretch; gap: 2px;
    text-decoration: none; height: 21px;
    margin-bottom: 0;
}
.jour-ligne:hover .jl-nom { filter: brightness(0.97); }
.jour-ligne.today .jl-nom { outline: 2px solid #2e6da4; outline-offset: -2px; }

/* Jour férié — marquage violet bien visible (boîte + barre + fraction) */
.jour-ligne.ferie .jl-nom      { background: #f3e5f7; border-color: #9b59b6; color: #6c3483; }
.jour-ligne.ferie .jl-barre-wrap { background: #9b59b6; }
.jour-ligne.ferie .jl-fraction { border-color: #9b59b6; color: #6c3483; }

.jl-nom {
    background: white; border: 1px solid #1a2a3a; border-radius: 4px;
    font-size: 12px; font-weight: 800; color: #1a2a3a;
    display: flex; align-items: center; justify-content: center;
    width: 84px; flex-shrink: 0; white-space: nowrap; overflow: hidden;
}
.jl-badge { font-size: 9px; flex-shrink: 0; position: absolute; }
.jl-barre-wrap {
    flex: 1; border: 1px solid #1a2a3a; border-radius: 4px;
    overflow: hidden; min-width: 30px; background: #e8e8ec;
    display: flex;
}
.jl-barre-fill { height: 100%; transition: width 0.3s; }
.jl-fraction {
    background: white; border: 1px solid #1a2a3a; border-radius: 4px;
    font-size: 12px; font-weight: 800; color: #1a2a3a;
    display: flex; align-items: center; justify-content: center;
    width: 56px; flex-shrink: 0; white-space: nowrap;
}

/* ── Ligne week-end fusionnée — bandeau pleine largeur ── */
.jour-ligne.we {
    background: #1a1a2e; justify-content: center;
    border: 1px solid #0d0d1a; border-radius: 4px;
    height: 21px;
}
.jour-ligne.we .jl-nom.we-label {
    background: transparent; border: none; width: auto; padding: 0 10px;
    font-size: 12px; font-weight: 700; color: #ffe082;
    letter-spacing: 1px; display: flex; align-items: center;
}

/* ── Case vide de calage (jour hors mois) — aplat sombre uni ── */
.jour-ligne.vide {
    background: #1c4a57;
    border: 1px solid #123640; border-radius: 4px;
}
.jour-ligne.vide.we { height: 21px; }

/* ── Vue Aujourd'hui : 1 seule colonne, plus large ── */
.mois-conteneur.une-col .mois-bloc { flex: 1 1 100%; width: 100%; max-width: 360px; }

</style>
</head>
<body class="<?= htmlspecialchars($theme) ?>">

<!-- HEADER -->
<script src="home.js"></script>
<div class="header">
    <!-- GAUCHE : recherche par date -->
    <input id="searchInput" class="search-hdr" type="text" placeholder="🔍 Date ou jour..."
          oninput="filtrerPlanning(this.value)">
    <button id="btnClearSearch" onclick="clearSearch()"
            style="display:none;background:rgba(255,255,255,0.2);color:white;border:none;
                   border-radius:4px;padding:2px 7px;cursor:pointer;font-size:11px;height:24px;">✕</button>
    <span id="searchInfo" style="color:rgba(255,255,255,0.8);font-size:10px;white-space:nowrap;"></span>
    <!-- MILIEU : boutons fixes (planning = gris car page courante) -->
    <button onclick="goHome()"          class="btn-h green" >🏠 Dossier</button>
    <a href="agenda.php"                class="btn-h navy"  >📅 Agenda</a>
    <span                               class="btn-h grey"  >📊 Planning</span>
    <a href="grille_semaine.php"        class="btn-h blue"  >📋 Grille</a>
    <a href="recherche.php" class="btn-h orange" title="Recherchez un patient pour accéder à la biologie">🧪 Biologie</a>
    <a href="jours_feries.php"          class="btn-h purple">📅 Fériés</a>
	<button class="btn-h navy" onclick="toggleGoDate()" title="Aller à une date">🔍 Date</button>
	<!-- ══ PANNEAU ALLER À UNE DATE ══ -->
<div id="goDatePanel" style="display:none; position:fixed; top:52px; left:50%; transform:translateX(-50%);
     background:#1a4a7a; color:white; padding:10px 16px; border-radius:8px; z-index:9999;
     box-shadow:0 4px 16px rgba(0,0,0,0.4); display:none; align-items:center; gap:8px; flex-wrap:wrap;">
    <span style="font-size:12px; font-weight:bold;">Aller à :</span>
    <input type="date" id="gdNatif" onchange="gdSyncTexte()"
           style="border-radius:4px; border:none; padding:3px 6px; font-size:13px; cursor:pointer;">
    <input type="text" id="gdTexte" placeholder="JJ/MM/AAAA" maxlength="10"
           oninput="gdSyncNatif()" onkeydown="if(event.key==='Enter') gdAller()"
           style="width:90px; border-radius:4px; border:none; padding:3px 6px; font-size:13px; text-align:center;">
    <button onclick="gdAller()"
            style="background:#27ae60; color:white; border:none; border-radius:4px;
                   padding:4px 10px; cursor:pointer; font-size:13px;">Aller ▶</button>
    <button onclick="toggleGoDate()"
            style="background:rgba(255,255,255,0.2); color:white; border:none; border-radius:4px;
                   padding:4px 8px; cursor:pointer; font-size:12px;">✕</button>
</div>
<script>
function toggleGoDate() {
    var p = document.getElementById('goDatePanel');
    p.style.display = (p.style.display === 'none' || p.style.display === '') ? 'flex' : 'none';
}
function gdSyncTexte() {
    var v = document.getElementById('gdNatif').value; // AAAA-MM-JJ
    if (v) {
        var parts = v.split('-');
        document.getElementById('gdTexte').value = parts[2]+'/'+parts[1]+'/'+parts[0];
    }
}
function gdSyncNatif() {
    var v = document.getElementById('gdTexte').value;
    var m = v.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
    if (m) document.getElementById('gdNatif').value = m[3]+'-'+m[2]+'-'+m[1];
}
function gdAller() {
    var v = document.getElementById('gdNatif').value;
    if (!v) { var t = document.getElementById('gdTexte').value;
               var m = t.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
               if (m) v = m[3]+'-'+m[2]+'-'+m[1]; }
    if (v) window.location.href = 'planning.php?date=' + v;
    else alert('Entrez une date valide (JJ/MM/AAAA)');
}
</script>
    <!-- TITRE -->
    <h1 style="margin-left:8px;">📊 Planning</h1>
    <!-- DROITE : horloge -->
    <div class="header-clock" style="margin-left:auto;">
        <div class="ct" id="clockTime">--:--:--</div>
        <div class="cd" id="clockDate">---</div>
    </div>
</div>

<!-- BARRE MODES + PÉRIODE + LÉGENDE (une seule ligne, style homogène) -->
<div class="mode-bar">
    <?php
    $modes = [
        'aujourd_hui' => "Aujourd'hui",
        'semaine'     => 'Semaine',
        'mois'        => 'Mois',
        '3mois'       => '3 Mois',
        '6mois'       => '6 Mois',
    ];
    foreach ($modes as $k => $lbl):
    ?>
    <a href="planning.php?mode=<?= $k ?>"
       class="btn-mode <?= $mode===$k ? 'actif' : '' ?>">
        <?= $lbl ?>
    </a>
    <?php endforeach; ?>

    <span class="date-box grp-debut">📅 <?= labelCourt($debutS) ?><?php if ($debutS !== $finS): ?> → <?= labelCourt($finS) ?><?php endif; ?></span>
    <span class="date-box" title="NbrMax = <?= $nbrMax ?> patients/jour">🔢 <?= $totalPeriode ?> / <?= $nbrMax * count(array_filter($jours, fn($j) => !estWeekend($j) && !isset($feriesLabels[$j]))) ?> RDV</span>

    <strong class="leg-titre grp-debut">Légende :</strong>
    <div class="leg-item"><div class="leg-swatch" style="background:#27ae60;"></div> ≤ 25 %</div>
    <div class="leg-item"><div class="leg-swatch" style="background:#f1c40f;"></div> ≤ 50 %</div>
    <div class="leg-item"><div class="leg-swatch" style="background:#3498db;"></div> ≤ 75 %</div>
    <div class="leg-item"><div class="leg-swatch" style="background:#e74c3c;"></div> 75-100 %</div>
    <div class="leg-item"><div class="leg-swatch" style="background:#9b59b6;"></div> Jour férié</div>
</div>

<!-- PLANNING -->
<div class="planning-body">
<div class="mois-conteneur<?= ($mode === 'aujourd_hui') ? ' une-col' : '' ?>">
<?php
$alignerCalendrier = in_array($mode, ['mois', '3mois', '6mois']);
foreach ($parMois as $cleM => $joursM):
    $moisLabel = $moisNoms[date('m', strtotime($cleM.'-01'))] . ' ' . date('Y', strtotime($cleM.'-01'));
    $lignes = grouperSemaines($joursM, $alignerCalendrier);
?>
<div class="mois-bloc">
    <div class="mois-titre">📅 <?= $moisLabel ?></div>

    <div class="mois-jours">
    <?php foreach ($lignes as $ligne):
        if (!empty($ligne['vide'])) {
            // ── Case vide de calage (hors mois) — légèrement visible ──
    ?>
        <div class="jour-ligne vide<?= $ligne['type'] === 'we' ? ' we' : '' ?>"></div>
    <?php
            continue;
        }
        $estWE = $ligne['type'] === 'we';

        if (!$estWE) {
            // ── Ligne jour normal (Lun-Ven) ──
            $jour    = $ligne['jours'][0];
            $nb      = $rdvParJour[$jour] ?? 0;
            $ferie   = isset($feriesLabels[$jour]);
            $isToday = ($jour === $todayS);
            $dow     = (int)date('w', strtotime($jour));
            $nomJour = $joursNomsCourts[$dow];
            $numJour = date('j', strtotime($jour));

            $classes = 'jour-ligne';
            if ($ferie) $classes .= ' ferie';
            if ($isToday) $classes .= ' today';

            $barPct   = $nbrMax > 0 ? min(100, round($nb / $nbrMax * 100)) : 0;
            $barColor = $ferie ? '#9b59b6' : couleurBarre($nb, $nbrMax);
    ?>
        <a class="<?= $classes ?>" href="agenda.php?date=<?= $jour ?>"
           data-date="<?= date('d/m/Y', strtotime($jour)) ?>" data-nom="<?= strtolower($joursNoms[$dow]) ?>">
            <span class="jl-nom"><?= $nomJour ?> <?= $numJour ?><?= $isToday ? ' ★' : '' ?><?= $ferie ? ' 🟣' : '' ?></span>
            <span class="jl-barre-wrap"><span class="jl-barre-fill" style="width:<?= $barPct ?>%;background:<?= $barColor ?>;"></span></span>
            <span class="jl-fraction"><?= $nb ?>/<?= $nbrMax ?></span>
        </a>
    <?php } else {
            // ── Ligne week-end fusionnée (Sam[-Dim]) ──
            $jrs = $ligne['jours'];
            $premierNum = date('j', strtotime($jrs[0]));
            $dernierNum = date('j', strtotime($jrs[count($jrs)-1]));
            $label = (count($jrs) > 1) ? "WE $premierNum-$dernierNum" : "WE $premierNum";
            $dateAttr = date('d/m/Y', strtotime($jrs[0]));
    ?>
        <a class="jour-ligne we" href="agenda.php?date=<?= $jrs[0] ?>" data-date="<?= $dateAttr ?>" data-nom="we">
            <span class="jl-nom we-label"><?= $label ?></span>
        </a>
    <?php } endforeach; ?>
    </div>
</div>
<?php endforeach; ?>
</div>
</div>

<script>
// ── Recherche par date / jour ──────────────────────────────────
function filtrerPlanning(v) {
    v = v.toLowerCase().trim();
    let first = null, found = 0;
    document.querySelectorAll('.jour-ligne:not(.vide)').forEach(c => {
        const date = (c.dataset.date || '').toLowerCase();
        const nom  = (c.dataset.nom  || '').toLowerCase();
        const match = !v || date.includes(v) || nom.includes(v);
        c.style.opacity = match ? '1' : '0.2';
        c.style.pointerEvents = match ? '' : 'none';
        if (match && v) { found++; if (!first) first = c; }
    });
    if (first) {
        first.scrollIntoView({ behavior: 'smooth', block: 'center' });
        first.style.outline = '3px solid #f39c12';
        setTimeout(() => first.style.outline = '', 1500);
    }
    const btnClear = document.getElementById('btnClearSearch');
    const info     = document.getElementById('searchInfo');
    if (btnClear) btnClear.style.display = v ? 'inline-block' : 'none';
    if (info)     info.textContent = v ? found + ' jour(s) trouvé(s)' : '';
}
function clearSearch() {
    document.getElementById('searchInput').value = '';
    filtrerPlanning('');
}
// ── Horloge ────────────────────────────────────────────────────
(function() {
    const jours = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
    const mois  = ['Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'];
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
    tick(); setInterval(tick, 1000);
})();
</script>

</body>
</html>
 