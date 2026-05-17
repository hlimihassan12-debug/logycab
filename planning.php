<?php
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';
$db = getDB();

// ── Période selon bouton ───────────────────────────────────────
$mode   = $_GET['mode'] ?? 'semaine';
$today  = new DateTime();
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
$feriesLabels = []; // ['2026-05-01' => 'Fête du Travail']
try {
    $stmtF = $db->query("SELECT DateFerie, Label FROM T_JourFeries ORDER BY DateFerie");
    while ($f = $stmtF->fetch(PDO::FETCH_ASSOC)) {
        $raw = trim($f['DateFerie'] ?? '');
        $lbl = trim($f['Label'] ?? 'Jour férié');
        $dateKey = '';
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $raw, $m)) {
            $dateKey = $m[3].'-'.$m[2].'-'.$m[1];
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            $dateKey = $raw;
        } else {
            $ts = strtotime($raw);
            if ($ts) $dateKey = date('Y-m-d', $ts);
        }
        if ($dateKey) $feriesLabels[$dateKey] = $lbl;
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
    if ($ratio <= 0.25) return '#27ae60';
    if ($ratio <= 0.5)  return '#f39c12';
    if ($ratio <= 0.75) return '#e67e22';
    return '#e74c3c';
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
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Planning — Logycab</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
       background: #f0f4f8; font-size: 13px; }

/* ── Header ── */
.header {
    background: linear-gradient(135deg, #1a4a7a 0%, #0f3460 100%);
    color: white; padding: 6px 14px;
    display: flex; align-items: center; gap: 8px; flex-wrap: nowrap;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}
.header h1 { font-size: 15px; font-weight: 700; white-space: nowrap; }
.btn-h { color: white; text-decoration: none; border: none; cursor: pointer;
         padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: bold;
         display: inline-flex; align-items: center; height: 26px; white-space: nowrap; }
.btn-h.green  { background: #27ae60; }
.btn-h.navy   { background: #1a4a7a; }
.btn-h.blue   { background: #2e6da4; }
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
.header-clock .ct { font-size: 15px; font-weight: bold; letter-spacing: 1px; color: #f0f4f8; }
.header-clock .cd { font-size: 9px; opacity: 0.75; }

/* ── Barre de modes ── */
.mode-bar {
    background: white; padding: 6px 14px;
    display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
    border-bottom: 2px solid #e0e8f0;
    box-shadow: 0 2px 4px rgba(0,0,0,0.06);
}
.btn-mode {
    padding: 4px 14px; border-radius: 20px; font-size: 12px; font-weight: bold;
    border: 2px solid #ddd; background: white; color: #555;
    cursor: pointer; text-decoration: none; display: inline-block;
    transition: all 0.15s;
}
.btn-mode:hover { border-color: #2e6da4; color: #2e6da4; }
.btn-mode.actif { background: #1a4a7a; color: white; border-color: #1a4a7a; }

.periode-info {
    margin-left: auto; display: flex; align-items: center; gap: 8px;
}
.date-box {
    background: #f0f4f8; border: 1px solid #ddd; border-radius: 4px;
    padding: 3px 8px; font-size: 11px; color: #444; font-weight: bold;
}
.total-badge {
    background: #1a4a7a; color: white; border-radius: 20px;
    padding: 3px 12px; font-size: 12px; font-weight: bold;
}

/* ── Légende ── */
.legende {
    background: white; padding: 4px 14px;
    display: flex; gap: 14px; align-items: center; flex-wrap: wrap;
    border-bottom: 1px solid #e0e8f0; font-size: 11px; color: #555;
}
.leg-item { display: flex; align-items: center; gap: 5px; }
.leg-dot  { width: 11px; height: 11px; border-radius: 3px; flex-shrink:0; }

/* ── Corps principal ── */
.planning-body { padding: 10px 14px; }

/* ── Bloc mois ── */
.mois-bloc { margin-bottom: 16px; }
.mois-titre {
    font-size: 12px; font-weight: 800; color: #1a4a7a;
    text-transform: uppercase; letter-spacing: 2px;
    padding: 6px 4px 4px; margin-bottom: 6px;
    border-bottom: 2px solid #d0dcea;
}

/* ── Grille 3 colonnes ── */
.grille {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 5px;
}

/* ── Carte jour — UNE SEULE LIGNE compacte ── */
.jour-card {
    border-radius: 5px;
    padding: 3px 7px;
    border: 1px solid rgba(0,0,0,0.08);
    text-decoration: none;
    display: flex;
    flex-direction: row;
    align-items: center;
    gap: 5px;
    height: 26px;
    overflow: hidden;
    transition: box-shadow 0.1s, filter 0.1s;
    cursor: pointer;
}
.jour-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    filter: brightness(0.96);
}
.jour-card.today {
    outline: 2px solid #2e6da4;
    outline-offset: 1px;
}

/* Nom + numéro du jour */
.jour-nom { font-size: 11px; font-weight: 700; white-space: nowrap; flex-shrink: 0; }
.jour-num { font-size: 11px; font-weight: 400; white-space: nowrap; flex-shrink: 0; margin-right: 2px; }

/* Badges */
.badge-today {
    font-size: 8px; font-weight: bold;
    background: #2e6da4; color: white;
    border-radius: 6px; padding: 1px 4px; flex-shrink: 0;
}
.badge-ferie {
    font-size: 8px; font-weight: bold;
    background: rgba(255,255,255,0.25); color: white;
    border-radius: 6px; padding: 1px 4px; flex-shrink: 0;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 70px;
}

/* Barre proportionnelle */
.barre-wrap {
    flex: 1; height: 5px; background: rgba(0,0,0,0.10);
    border-radius: 3px; overflow: hidden; min-width: 20px;
}
.barre-fill { height: 100%; border-radius: 3px; transition: width 0.3s; }

/* Fraction X/20 */
.fraction {
    font-size: 10px; font-weight: 700;
    white-space: nowrap; flex-shrink: 0;
    min-width: 32px; text-align: right;
}

/* jour-top et jour-bottom : display contents = les enfants rejoignent la ligne principale */
.jour-top, .jour-bottom { display: contents; }

/* ── Styles weekend ── */
.jour-card.weekend {
    background: #1a1a2e !important;
    border-color: #0d0d1a;
}
.jour-card.weekend .jour-nom,
.jour-card.weekend .jour-num { color: #ffe082; }
.jour-card.weekend .fraction { color: #555; }

/* ── Styles jour férié ── */
.jour-card.ferie {
    background: #4a235a !important;
    border-color: #3a1a4a;
}
.jour-card.ferie .jour-nom,
.jour-card.ferie .jour-num { color: #f3e5f5; }
.jour-card.ferie .fraction { color: #ce93d8; }

/* ── Texte jours normaux ── */
.jour-card:not(.weekend):not(.ferie) .jour-nom { color: #1a2a3a; }
.jour-card:not(.weekend):not(.ferie) .jour-num  { color: #3a4a5a; }
.jour-card:not(.weekend):not(.ferie) .fraction  { color: #2a3a4a; }

/* ── Vue Aujourd'hui : 1 colonne ── */
.grille.une-col { grid-template-columns: 1fr; }
</style>
</head>
<body>

<!-- HEADER -->
<script src="home.js"></script>
<div class="header">
    <!-- GAUCHE : recherche par date -->
    <input class="search-hdr" type="text" id="searchInput" placeholder="🔍 Date ou jour (ex: 12/05, Lundi)..."
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
    <!-- TITRE -->
    <h1 style="margin-left:8px;">📊 Planning</h1>
    <!-- DROITE : horloge -->
    <div class="header-clock" style="margin-left:auto;">
        <div class="ct" id="clockTime">--:--:--</div>
        <div class="cd" id="clockDate">---</div>
    </div>
</div>

<!-- BARRE MODES -->
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

    <div class="periode-info">
        <span class="date-box">📅 <?= labelCourt($debutS) ?></span>
        <?php if ($debutS !== $finS): ?>
        <span style="color:#aaa;font-size:11px;">→</span>
        <span class="date-box"><?= labelCourt($finS) ?></span>
        <?php endif; ?>
        <span class="total-badge">🔢 <?= $totalPeriode ?> / <?= $nbrMax * count(array_filter($jours, fn($j) => !estWeekend($j) && !isset($feriesLabels[$j]))) ?> RDV</span>
    </div>
</div>

<!-- LÉGENDE -->
<div class="legende">
    <strong style="font-size:10px;text-transform:uppercase;letter-spacing:1px;color:#666;">Légende :</strong>
    <div class="leg-item"><div class="leg-dot" style="background:#27ae60;"></div> ≤ 25 %</div>
    <div class="leg-item"><div class="leg-dot" style="background:#f39c12;"></div> ≤ 50 %</div>
    <div class="leg-item"><div class="leg-dot" style="background:#e67e22;"></div> ≤ 75 %</div>
    <div class="leg-item"><div class="leg-dot" style="background:#e74c3c;"></div> &gt; 75 % / complet</div>
    <div class="leg-item"><div class="leg-dot" style="background:#1a1a2e;"></div> Week-end</div>
    <div class="leg-item"><div class="leg-dot" style="background:#4a235a;"></div> Jour férié</div>
    <span style="margin-left:auto;color:#888;font-size:10px;">NbrMax = <?= $nbrMax ?> patients/jour</span>
</div>

<!-- PLANNING -->
<div class="planning-body">
<?php foreach ($parMois as $cleM => $joursM):
    $moisLabel = $moisNoms[date('m', strtotime($cleM.'-01'))] . ' ' . date('Y', strtotime($cleM.'-01'));
    $nbCols    = ($mode === 'aujourd_hui') ? 'une-col' : '';
?>
<div class="mois-bloc">
    <?php if (count($parMois) > 1 || in_array($mode, ['mois','3mois','6mois'])): ?>
    <div class="mois-titre">📅 <?= $moisLabel ?></div>
    <?php endif; ?>

    <div class="grille <?= $nbCols ?>">
    <?php foreach ($joursM as $jour):
        $nb      = $rdvParJour[$jour] ?? 0;
        $weekend = estWeekend($jour);
        $ferie   = isset($feriesLabels[$jour]);
        $isToday = ($jour === $todayS);
        $dow     = (int)date('w', strtotime($jour));
        $nomJour = $joursNoms[$dow];
        $numJour = date('j', strtotime($jour));

        // Classes CSS
        $classes = 'jour-card';
        if ($weekend) $classes .= ' weekend';
        elseif ($ferie) $classes .= ' ferie';
        if ($isToday) $classes .= ' today';

        // Fond (jours normaux seulement)
        $fondStyle = '';
        if (!$weekend && !$ferie) {
            $fondStyle = 'background:' . couleurFond($nb, $nbrMax) . ';';
        }

        // Barre
        $barPct  = $nbrMax > 0 ? min(100, round($nb / $nbrMax * 100)) : 0;
        $barColor = ($weekend || $ferie) ? 'rgba(255,255,255,0.2)' : couleurBarre($nb, $nbrMax);
    ?>
    <a class="<?= $classes ?>"
       href="agenda.php?date=<?= $jour ?>"
       data-date="<?= date('d/m/Y', strtotime($jour)) ?>"
       data-nom="<?= strtolower($nomJour) ?>"
       style="<?= $fondStyle ?>">

        <div class="jour-top">
            <span>
                <span class="jour-nom"><?= $nomJour ?></span>
                <span class="jour-num">
                    <?= $weekend ? date('d/m/Y', strtotime($jour)) : $numJour ?>
                </span>
            </span>
            <?php if ($isToday): ?>
                <span class="badge-today">Aujourd'hui</span>
            <?php elseif ($ferie): ?>
                <span class="badge-ferie" title="<?= htmlspecialchars($feriesLabels[$jour]) ?>">
                    <?= htmlspecialchars(mb_substr($feriesLabels[$jour], 0, 12)) ?>
                </span>
            <?php endif; ?>
        </div>

        <?php if (!$weekend && !$ferie): ?>
        <div class="jour-bottom">
            <div class="barre-wrap">
                <div class="barre-fill"
                     style="width:<?= $barPct ?>%;background:<?= $barColor ?>;"></div>
            </div>
            <span class="fraction"><?= $nb ?> / <?= $nbrMax ?></span>
        </div>
        <?php endif; ?>

    </a>
    <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>
</div>

<script>
// ── Recherche par date / jour ──────────────────────────────────
function filtrerPlanning(v) {
    v = v.toLowerCase().trim();
    let first = null, found = 0;
    document.querySelectorAll('.jour-card').forEach(c => {
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
