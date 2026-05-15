<?php
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';
$db = getDB();

// ── Période selon bouton ───────────────────────────────────────
$mode    = $_GET['mode'] ?? 'semaine';
$today   = new DateTime();
$todayS  = $today->format('Y-m-d');

switch ($mode) {
    case 'aujourd_hui':
        $debut = new DateTime($todayS);
        $fin   = new DateTime($todayS);
        break;
    case 'semaine':
        // Lundi de la semaine courante → dimanche
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

// ── Compter RDV par jour ───────────────────────────────────────
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

// ── Total période ──────────────────────────────────────────────
$totalPeriode = array_sum($rdvParJour);

// ── Générer tous les jours de la période ──────────────────────
$jours = [];
$cur = clone $debut;
while ($cur <= $fin) {
    $jours[] = $cur->format('Y-m-d');
    $cur->modify('+1 day');
}

// ── Labels français ────────────────────────────────────────────
function labelJour($dateStr) {
    $jours = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
    $mois  = ['','Janvier','Février','Mars','Avril','Mai','Juin',
               'Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
    $ts = strtotime($dateStr);
    return $jours[date('w',$ts)] . ' ' . date('j',$ts) . ' ' . $mois[(int)date('n',$ts)] . ' ' . date('Y',$ts);
}
function labelCourt($dateStr) {
    $mois = ['','Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'];
    $ts = strtotime($dateStr);
    return date('d',$ts) . '/' . $mois[(int)date('n',$ts)] . '/' . date('Y',$ts);
}
function estWeekend($dateStr) {
    $w = (int)date('w', strtotime($dateStr));
    return $w === 0 || $w === 6;
}
function couleurNb($nb, $weekend) {
    if ($weekend) return ['bg'=>'#111','txt'=>'#555','badge'=>'#222','badgetxt'=>'#444'];
    if ($nb === 0) return ['bg'=>'#fafafa','txt'=>'#bbb','badge'=>'#eee','badgetxt'=>'#ccc'];
    if ($nb <= 5)  return ['bg'=>'#f0fff4','txt'=>'#1a5c2a','badge'=>'#27ae60','badgetxt'=>'white'];
    if ($nb <= 15) return ['bg'=>'#fffbf0','txt'=>'#7a4f00','badge'=>'#f39c12','badgetxt'=>'white'];
    if ($nb <= 25) return ['bg'=>'#fff5ee','txt'=>'#7a3500','badge'=>'#e67e22','badgetxt'=>'white'];
    return           ['bg'=>'#fff0f0','txt'=>'#7a0000','badge'=>'#e74c3c','badgetxt'=>'white'];
}
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
    color: white; padding: 8px 16px;
    display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}
.header h1 { font-size: 16px; font-weight: 700; letter-spacing: 0.5px; }
.btn-h { color: white; text-decoration: none; border: none; cursor: pointer;
         padding: 5px 12px; border-radius: 5px; font-size: 11px; font-weight: bold;
         display: inline-block; transition: opacity 0.15s; }
.btn-h.blue   { background: #2e6da4; }
.btn-h.green  { background: #27ae60; }
.btn-h:hover  { opacity: 0.82; }

/* ── Barre de modes ── */
.mode-bar {
    background: white; padding: 8px 16px;
    display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
    border-bottom: 2px solid #e0e8f0;
    box-shadow: 0 2px 4px rgba(0,0,0,0.06);
}
.btn-mode {
    padding: 5px 16px; border-radius: 20px; font-size: 12px; font-weight: bold;
    border: 2px solid #ddd; background: white; color: #555;
    cursor: pointer; text-decoration: none; display: inline-block;
    transition: all 0.15s;
}
.btn-mode:hover { border-color: #2e6da4; color: #2e6da4; }
.btn-mode.actif { background: #1a4a7a; color: white; border-color: #1a4a7a; }

/* Infos période */
.periode-info {
    margin-left: auto; display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
}
.date-box {
    background: #f0f4f8; border: 1px solid #ddd; border-radius: 5px;
    padding: 4px 10px; font-size: 12px; color: #444; font-weight: bold;
}
.total-badge {
    background: #1a4a7a; color: white; border-radius: 20px;
    padding: 4px 14px; font-size: 12px; font-weight: bold;
}

/* ── Légende ── */
.legende {
    background: white; padding: 5px 16px;
    display: flex; gap: 12px; align-items: center; flex-wrap: wrap;
    border-bottom: 1px solid #e0e8f0; font-size: 11px;
}
.leg-item { display: flex; align-items: center; gap: 5px; }
.leg-dot  { width: 12px; height: 12px; border-radius: 3px; }

/* ── Liste des jours ── */
.liste { padding: 10px 16px; max-width: 800px; }

.jour-row {
    display: flex; align-items: center;
    border-radius: 7px; margin-bottom: 4px;
    padding: 7px 14px; gap: 12px;
    cursor: pointer; transition: transform 0.1s, box-shadow 0.1s;
    border: 1px solid rgba(0,0,0,0.06);
    text-decoration: none;
}
.jour-row:hover {
    transform: translateX(4px);
    box-shadow: 0 3px 10px rgba(0,0,0,0.12);
    filter: brightness(0.97);
}

/* Aujourd'hui : contour spécial */
.jour-row.today {
    outline: 2px solid #2e6da4;
    outline-offset: 1px;
}

.jour-label {
    flex: 1; font-size: 13px; font-weight: 600;
    white-space: nowrap;
}
.jour-label .jour-nom { font-weight: 700; }
.jour-label .jour-date { font-weight: 400; font-size: 12px; margin-left: 4px; }

/* Badge nombre */
.nb-badge {
    min-width: 46px; text-align: center;
    padding: 3px 10px; border-radius: 12px;
    font-size: 13px; font-weight: 800;
    flex-shrink: 0;
}

/* Barre visuelle proportionnelle */
.nb-bar-wrap {
    width: 160px; height: 8px; background: rgba(0,0,0,0.07);
    border-radius: 4px; flex-shrink: 0; overflow: hidden;
}
.nb-bar-fill { height: 100%; border-radius: 4px; transition: width 0.3s; }

.aujourdhui-label {
    font-size: 10px; font-weight: bold; color: #2e6da4;
    background: #e8f0fb; padding: 2px 7px; border-radius: 8px;
    flex-shrink: 0;
}

/* Section mois */
.mois-header {
    padding: 8px 14px 4px;
    font-size: 11px; font-weight: 700;
    color: #888; text-transform: uppercase;
    letter-spacing: 1.5px;
}

/* ── Empty ── */
.empty { text-align: center; padding: 60px; color: #bbb; font-size: 14px; }
</style>
</head>
<body>

<!-- HEADER -->
<div class="header">
    <a href="recherche.php" class="btn-h blue">◀ Accueil</a>
    <h1>📅 Planning</h1>
    <a href="agenda.php" class="btn-h green">📋 Agenda du jour</a>
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
        <span style="color:#aaa;font-size:12px;">→</span>
        <span class="date-box"><?= labelCourt($finS) ?></span>
        <?php endif; ?>
        <span class="total-badge">🔢 <?= $totalPeriode ?> RDV</span>
    </div>
</div>

<!-- LÉGENDE -->
<div class="legende">
    <strong style="color:#666;font-size:10px;text-transform:uppercase;letter-spacing:1px;">Légende :</strong>
    <div class="leg-item"><div class="leg-dot" style="background:#27ae60;"></div> &lt; 5 patients</div>
    <div class="leg-item"><div class="leg-dot" style="background:#f39c12;"></div> 6 – 15 patients</div>
    <div class="leg-item"><div class="leg-dot" style="background:#e67e22;"></div> 16 – 25 patients</div>
    <div class="leg-item"><div class="leg-dot" style="background:#e74c3c;"></div> &gt; 25 patients</div>
    <div class="leg-item"><div class="leg-dot" style="background:#111;"></div> Week-end</div>
</div>

<!-- LISTE JOURS -->
<div class="liste">
<?php
// Max pour la barre proportionnelle
$maxNb = max(array_merge([1], array_values($rdvParJour)));

$dernierMois = '';
foreach ($jours as $jour):
    $nb       = $rdvParJour[$jour] ?? 0;
    $weekend  = estWeekend($jour);
    $couleurs = couleurNb($nb, $weekend);
    $isToday  = ($jour === $todayS);

    // En-tête de mois (pour vues longues)
    $moisCourant = date('Y-m', strtotime($jour));
    if ($moisCourant !== $dernierMois && in_array($mode, ['3mois','6mois'])) {
        $dernierMois = $moisCourant;
        $moisNoms = ['01'=>'Janvier','02'=>'Février','03'=>'Mars','04'=>'Avril',
                     '05'=>'Mai','06'=>'Juin','07'=>'Juillet','08'=>'Août',
                     '09'=>'Septembre','10'=>'Octobre','11'=>'Novembre','12'=>'Décembre'];
        $moisLabel = $moisNoms[date('m', strtotime($jour))] . ' ' . date('Y', strtotime($jour));
        echo '<div class="mois-header">📅 ' . $moisLabel . '</div>';
    }

    // Noms du jour
    $joursN = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
    $nomJour = $joursN[(int)date('w', strtotime($jour))];
    $numJour = date('j', strtotime($jour));
    $moisNoms2 = ['01'=>'Jan','02'=>'Fév','03'=>'Mar','04'=>'Avr','05'=>'Mai',
                  '06'=>'Jun','07'=>'Jul','08'=>'Aoû','09'=>'Sep','10'=>'Oct',
                  '11'=>'Nov','12'=>'Déc'];
    $moisAbr = $moisNoms2[date('m', strtotime($jour))];

    // Largeur barre (min 2% si nb>0 pour visibilité)
    $barW = $nb > 0 ? max(2, round($nb / $maxNb * 100)) : 0;
?>
<a class="jour-row <?= $isToday ? 'today' : '' ?>"
   href="agenda.php?date=<?= $jour ?>"
   style="background:<?= $couleurs['bg'] ?>;color:<?= $couleurs['txt'] ?>;">

    <span class="jour-label">
        <span class="jour-nom"><?= $nomJour ?></span>
        <span class="jour-date"><?= $numJour . ' ' . $moisAbr ?></span>
    </span>

    <?php if ($isToday): ?>
    <span class="aujourdhui-label">Aujourd'hui</span>
    <?php endif; ?>

    <!-- Barre proportionnelle -->
    <?php if (!$weekend): ?>
    <div class="nb-bar-wrap">
        <div class="nb-bar-fill"
             style="width:<?= $barW ?>%;background:<?= $couleurs['badge'] ?>;"></div>
    </div>
    <?php endif; ?>

    <!-- Badge nombre -->
    <span class="nb-badge"
          style="background:<?= $couleurs['badge'] ?>;color:<?= $couleurs['badgetxt'] ?>;">
        <?= $weekend ? '' : $nb ?>
    </span>

</a>
<?php endforeach; ?>

<?php if (empty($jours)): ?>
<div class="empty">📭 Aucune donnée pour cette période</div>
<?php endif; ?>
</div>

</body>
</html>