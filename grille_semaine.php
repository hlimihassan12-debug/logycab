<?php
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';
$db = getDB();

// ── Semaine affichée ──────────────────────────────────────────
$today  = new DateTime();
$todayS = $today->format('Y-m-d');

if (!empty($_GET['sem'])) {
    try { $lundi = new DateTime($_GET['sem']); }
    catch (Exception $e) { $lundi = new DateTime($todayS); }
} else {
    $lundi = new DateTime($todayS);
}
if ((int)$lundi->format('N') !== 1) $lundi->modify('monday this week');

$vendredi  = (clone $lundi)->modify('+4 days');
$lundiS    = $lundi->format('Y-m-d');
$vendrediS = $vendredi->format('Y-m-d');
$semPrecS  = (clone $lundi)->modify('-7 days')->format('Y-m-d');
$semSuivS  = (clone $lundi)->modify('+7 days')->format('Y-m-d');

$lundiNow = new DateTime($todayS);
if ((int)$lundiNow->format('N') !== 1) $lundiNow->modify('monday this week');
$lundiNowS = $lundiNow->format('Y-m-d');

// ── NbrMax depuis T_Config ────────────────────────────────────
$nbrMax = 20;
try {
    $stmtMax = $db->prepare("SELECT Valeur FROM T_Config WHERE Cle='NbrMax'");
    $stmtMax->execute();
    $rowMax = $stmtMax->fetch(PDO::FETCH_ASSOC);
    if ($rowMax) $nbrMax = (int)$rowMax['Valeur'];
} catch (Exception $e) {}

// ── Les 5 jours Lun→Ven ───────────────────────────────────────
$jourNoms = ['Lundi','Mardi','Mercredi','Jeudi','Vendredi'];
$jours5   = [];
for ($i = 0; $i < 5; $i++)
    $jours5[] = (clone $lundi)->modify("+$i days")->format('Y-m-d');

// ── Créneaux 09:00 → 16:00 par demi-heure ─────────────────────
$creneaux = [];
for ($h = 9; $h <= 16; $h++) {
    $creneaux[] = sprintf('%02d:00', $h);
    if ($h < 16) $creneaux[] = sprintf('%02d:30', $h);
}
$creneauxSet = array_flip($creneaux);

// ── Jours fériés ──────────────────────────────────────────────
$feriesSet = [];
try {
    $stmtF = $db->query("SELECT DateFerie FROM T_JourFeries");
    while ($f = $stmtF->fetch(PDO::FETCH_ASSOC)) {
        $raw = trim($f['DateFerie'] ?? '');
        $dk  = '';
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $raw, $m))
            $dk = "{$m[3]}-{$m[2]}-{$m[1]}";
        elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw))
            $dk = $raw;
        else { $ts = strtotime($raw); if ($ts) $dk = date('Y-m-d', $ts); }
        if ($dk) $feriesSet[$dk] = true;
    }
} catch (Exception $e) {}

// ── RDV de la semaine — TOUS ───────────────────────────────────
$stmtRdv = $db->prepare("
    SELECT
        o.n_ordon,
        o.id,
        CONVERT(varchar(10), o.[DATE REDEZ VOUS], 23)    AS jour,
        ISNULL(CONVERT(varchar(5), o.HeureRDV), '')       AS heure_brute,
        ISNULL(p.NOMPRENOM, '')                           AS nom,
        ISNULL(p.[TEL D], '')                             AS tel,
        ISNULL(o.acte1, '')                               AS acte,
        ISNULL(o.Vu, 0)                                   AS vu,
        ISNULL(o.SansReponse, 0)                          AS absent
    FROM ORD o
    LEFT JOIN ID p ON o.id = p.[N°PAT]
    WHERE CONVERT(date, o.[DATE REDEZ VOUS]) BETWEEN ? AND ?
    ORDER BY jour,
             CASE WHEN o.HeureRDV IS NULL THEN 1 ELSE 0 END,
             o.HeureRDV, o.n_ordon
");
$stmtRdv->execute([$lundiS, $vendrediS]);

$grille    = [];
$totalJour = [];

while ($row = $stmtRdv->fetch(PDO::FETCH_ASSOC)) {
    $j = $row['jour'];
    if (!$j) continue;
    $totalJour[$j] = ($totalJour[$j] ?? 0) + 1;

    $h = substr(trim($row['heure_brute']), 0, 5);
    if (preg_match('/^(\d):(\d{2})$/', $h, $m)) $h = '0'.$m[1].':'.$m[2];

    $patient = [
        'id'     => $row['id'],
        'nom'    => $row['nom'],
        'tel'    => $row['tel'],
        'ord'    => $row['n_ordon'],
        'acte'   => $row['acte'],
        'vu'     => (int)$row['vu'],
        'absent' => (int)$row['absent'],
        'heure'  => $h,
        'jour'   => $j,
    ];

    if (isset($creneauxSet[$h])) {
        $grille[$j][$h][] = $patient;
    } else {
        $grille[$j]['__sans_heure__'][] = $patient;
    }
}

// ── Chiffre d'affaire par jour ────────────────────────────────
$stmtCA = $db->prepare("
    SELECT
        CONVERT(varchar(10), f.date_facture, 23) AS jour,
        ISNULL(SUM(d.Versé), 0)                  AS ca
    FROM facture f
    LEFT JOIN detail_acte d ON d.N_fact = f.n_facture
    WHERE CONVERT(date, f.date_facture) BETWEEN ? AND ?
    GROUP BY CONVERT(varchar(10), f.date_facture, 23)
");
$stmtCA->execute([$lundiS, $vendrediS]);
$caJour = [];
while ($row = $stmtCA->fetch(PDO::FETCH_ASSOC)) {
    $caJour[$row['jour']] = (float)$row['ca'];
}

// ── Utilitaires ───────────────────────────────────────────────
function dateLong($dateS) {
    $mois = ['','Janvier','Février','Mars','Avril','Mai','Juin',
             'Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
    $ts = strtotime($dateS);
    return date('j',$ts).' '.$mois[(int)date('n',$ts)].' '.date('Y',$ts);
}
function dateCourt($dateS) {
    $ts = strtotime($dateS);
    $m  = ['','Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'];
    return date('d',$ts).'/'.$m[(int)date('n',$ts)];
}

// ── Rendu d'un bloc patients dans une cellule ─────────────────
// N° et Nom sont dans la MÊME cellule, sur la même ligne
function renderPatients(array $patients): string {
    $html = '';
    foreach ($patients as $pi => $p) {
        $statCl = $p['vu'] ? 'vu' : ($p['absent'] ? 'absent' : 'normal');
        $nom = mb_strtoupper(trim($p['nom']));
        if (mb_strlen($nom) > 18) $nom = mb_substr($nom,0,17).'…';
        $id  = htmlspecialchars($p['id']);
        $ord = $p['ord'];
        $jour= $p['jour'];
        $nomE= htmlspecialchars($p['nom']);
        $idE = urlencode($p['id']);

        $html .= "<div class='pat-row {$statCl}' id='grow-{$ord}'
                       data-ord='{$ord}'
                       data-nom='".strtolower(htmlspecialchars($p['nom']))."'
                       data-id='{$p['id']}'>";

        // N° patient en premier, taille réduite
        $html .= "<span class='pat-id'>{$id}</span>";

        // Boutons mini
        $vuLabel = $p['vu'] ? '✓' : '👁';
        $html .= "<button class='btn-g gvu' id='gbvu-{$ord}'
                          title='Vu' onclick='gToggleVu({$ord},{$p['vu']})'>
                      {$vuLabel}</button>";
        $html .= "<button class='btn-g gabs'
                          title='Absent' onclick='gToggleAbsent({$ord},{$p['absent']})'>
                      ✕</button>";
        $html .= "<button class='btn-g gdep'
                          title='Déplacer' onclick='gDeplacer({$ord},\"{$jour}\")'>
                      ◆</button>";
        $html .= "<a href='dossier.php?id={$idE}' class='btn-g gdos' title='Dossier'>📋</a>";
        $html .= "<button class='btn-g gsup'
                          title='Supprimer' onclick='gSupprimer({$ord},{$p['id']},\"{$nomE}\")'>
                      🗑</button>";

        // Champ heure — select créneaux comme agenda.php
        $heureVal = (strlen($p['heure']) === 5) ? $p['heure'] : '';
        $html .= "<select class='g-heure' id='gheure-{$ord}' title='Attribuer un créneau'
                          onchange='gSetHeure({$ord}, this.value)'>
                      <option value=''>—H—</option>";
        foreach (['09:00','09:30','10:00','10:30','11:00','11:30',
                  '12:00','12:30','13:00','13:30','14:00','14:30',
                  '15:00','15:30','16:00','16:30'] as $cr) {
            $sel = ($heureVal === $cr) ? 'selected' : '';
            $html .= "<option value='{$cr}' {$sel}>{$cr}</option>";
        }
        $html .= "</select>";

        // Nom cliquable
        $vuNomCl = $p['vu'] ? 'vu' : ($p['absent'] ? 'absent' : '');
        $html .= "<span class='pat-nom-txt {$vuNomCl}' id='gnom-{$ord}'
                        onclick=\"location.href='dossier.php?id={$idE}'\"
                        title='{$nomE}'>".htmlspecialchars($nom)."</span>";

        $html .= "</div>";
    }
    return $html;
}
function couleurBarre(int $nb, int $max): string {
    if ($nb === 0) return '#b0c8e0';
    $r = $max > 0 ? $nb / $max : 1;
    if ($r <= 0.25) return '#27ae60';
    if ($r <= 0.50) return '#f39c12';
    if ($r <= 0.75) return '#e67e22';
    return '#e74c3c';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Grille Semaine</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Arial, sans-serif; background: #f0f4f8; font-size: 12px; }

/* ══ HEADER ══ */
.header {
    background: #1a4a7a; color: white;
    padding: 5px 12px;
    display: flex; align-items: center; gap: 8px; flex-wrap: nowrap;
}
.header h1 { font-size: 14px; white-space: nowrap; margin-left: auto; }
.btn-h {
    color: white; text-decoration: none; border: none; cursor: pointer;
    padding: 3px 10px; border-radius: 4px; font-size: 11px; font-weight: bold;
    display: inline-flex; align-items: center; height: 24px; white-space: nowrap;
}
.btn-h.green  { background: #27ae60; }
.btn-h.navy   { background: #1a4a7a; }
.btn-h.blue   { background: #2e6da4; }
.btn-h.orange { background: #e67e22; }
.btn-h.purple { background: #8e44ad; }
.btn-h.grey   { background: #888; pointer-events: none; opacity: 0.7; cursor: default; }
.btn-h:not(.grey):hover { opacity: 0.85; }
/* Barre recherche dans le header — filtre local */
.search-hdr {
    padding: 2px 8px; border-radius: 4px; font-size: 11px; height: 24px;
    border: 1px solid rgba(255,255,255,0.35); background: rgba(255,255,255,0.12);
    color: white; outline: none; width: 200px; flex-shrink: 0;
}
.search-hdr::placeholder { color: rgba(255,255,255,0.5); }
.search-hdr:focus { border-color: rgba(255,255,255,0.7); background: rgba(255,255,255,0.2); }

/* Horloge à DROITE */
.hclock {
    background: rgba(255,255,255,0.12); border-radius: 6px;
    padding: 3px 10px; text-align: center; min-width: 130px; flex-shrink: 0;
}
.hclock .ct { font-size: 15px; font-weight: bold; letter-spacing: 1px; color: #f0f4f8; }
.hclock .cd { font-size: 9px; opacity: 0.75; }

/* ══ NAVIGATION SEMAINE ══ */
.nav-sem {
    background: #1a4a7a; color: white; padding: 5px 12px;
    display: flex; align-items: center; justify-content: center; gap: 10px;
    border-top: 1px solid rgba(255,255,255,0.1);
}
.nav-sem .label-sem { font-size: 13px; font-weight: bold; min-width: 320px; text-align: center; }
.btn-nav {
    background: rgba(255,255,255,0.18); color: white; border: none;
    padding: 4px 14px; border-radius: 4px; font-size: 12px; font-weight: bold;
    text-decoration: none; cursor: pointer;
}
.btn-nav:hover { background: rgba(255,255,255,0.35); }
.btn-today {
    background: #27ae60; color: white; border: none;
    padding: 4px 10px; border-radius: 4px;
    font-size: 11px; font-weight: bold; text-decoration: none;
}

/* ══ TABLEAU ══ */
.wrap { padding: 8px 10px; overflow-x: auto; }

table.grille {
    border-collapse: collapse;
    width: 100%; min-width: 700px;
    background: white;
    box-shadow: 0 2px 8px rgba(0,0,0,0.12);
    border-radius: 8px; overflow: hidden;
}

/* En-têtes */
table.grille thead th {
    background: #1a4a7a; color: white;
    padding: 6px 4px; font-size: 11px; font-weight: bold;
    border: 1px solid #155a9a; text-align: center;
}
table.grille thead th.th-heure { width: 50px; background: #0f3460; }

/* Corps */
table.grille tbody td {
    border: 1px solid #d0dce8;
    padding: 2px 3px; vertical-align: top; font-size: 11px;
}

/* Colonne heure — à GAUCHE, toujours première */
td.td-heure {
    background: #e8f0f8; color: #1a4a7a; font-weight: bold;
    text-align: center; white-space: nowrap; width: 50px;
}
tr.heure-pleine td          { border-top: 2px solid #a0b8d0 !important; }
tr.heure-pleine td.td-heure { background: #dce8f5; }

/* Sans heure */
tr.tr-sans-heure td          { border-top: 2px dashed #c0a0d0 !important; background: #faf0ff; }
tr.tr-sans-heure td.td-heure { background: #e8d5f5; color: #6a1a8a; font-size: 9px; }

/* Total & CA */
tr.tr-total td { border-top: 3px solid #1a4a7a !important; background: #e8f0f8; font-weight: bold; text-align: center; padding: 3px; }
tr.tr-total td.td-heure { background: #1a4a7a; color: white; font-size: 10px; }
tr.tr-ca td { background: #f0fff4; font-weight: bold; text-align: center; color: #27ae60; padding: 3px; border-top: 1px solid #c0dce8 !important; }
tr.tr-ca td.td-heure { background: #1a6a4a; color: white; font-size: 10px; }

/* Couleurs colonnes spéciales */
td.col-jour { min-width: 180px; }
td.col-today { background: #eef5ff; }
td.col-ferie { background: #f3e5f5; }

/* ══ LIGNE PATIENT — N° et Nom dans la MÊME cellule ══ */
.pat-row {
    display: flex; align-items: center; gap: 3px;
    border-radius: 3px; padding: 1px 3px; margin-bottom: 2px;
    min-height: 20px;
}
.pat-row.vu     { background: #eafaf1; }
.pat-row.absent { background: #fff0f0; }
.pat-row.normal { background: #f0f4fb; }

/* N° patient — compact à gauche de chaque ligne */
.pat-id {
    font-size: 9px; color: #999; white-space: nowrap;
    min-width: 26px; text-align: right; flex-shrink: 0;
}

/* Boutons mini */
.btn-g {
    border: none; border-radius: 3px; cursor: pointer;
    font-size: 9px; font-weight: bold;
    width: 17px; height: 17px; flex-shrink: 0;
    display: inline-flex; align-items: center; justify-content: center;
    padding: 0;
}
.btn-g.gvu  { background: #27ae60; color: white; }
.btn-g.gabs { background: #e74c3c; color: white; }
.btn-g.gdep { background: #8e44ad; color: white; }
.btn-g.gdos { background: #2e6da4; color: white; text-decoration: none; }
.btn-g.gsup { background: #c0392b; color: white; }
.btn-g:hover { opacity: 0.8; }

/* Nom patient */
.pat-nom-txt {
    font-size: 11px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    flex: 1; cursor: pointer; color: #1a4a7a;
}
.pat-nom-txt:hover { text-decoration: underline; color: #e67e22; }
.pat-nom-txt.vu     { color: #aaa; text-decoration: line-through; }
.pat-nom-txt.absent { color: #e74c3c; }

/* Barre progression sous l'entête jour */
.barre-jour { margin-top:4px; height:5px; background:#dde; border-radius:3px; overflow:hidden; }
.barre-jour-fill { height:100%; border-radius:3px; transition:width 0.3s; }
.fraction-jour { font-size:9px; opacity:0.85; margin-top:2px; font-weight:bold; }

/* Pastille couleur créneau — dans chaque cellule jour×créneau */
.col-jour { position: relative; }
.cr-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 3px; vertical-align: middle; flex-shrink: 0; }
.cr-dot.vert  { background: #27ae60; }
.cr-dot.jaune { background: #f39c12; }
.cr-dot.rouge { background: #e74c3c; }

/* Champ heure dans la ligne patient */
.g-heure {
    width: 54px; height: 17px; font-size: 10px;
    border: 1px solid #ddd; border-radius: 3px;
    padding: 0 2px; flex-shrink: 0;
    color: #555; background: #f0f8ff; cursor: pointer;
}
.g-heure:focus { border-color: #2e6da4; outline: none; }

/* Recherche */
.pat-row.highlight      { background: #fff3cd !important; outline: 1px solid #f39c12; }
.pat-row.hidden-search  { display: none !important; }

/* ══ MODALES ══ */
.modal-ov {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,0.5); z-index: 1000;
    align-items: center; justify-content: center;
}
.modal-ov.show { display: flex; }
.modal-box {
    background: white; border-radius: 10px; padding: 18px;
    min-width: 320px; box-shadow: 0 10px 40px rgba(0,0,0,0.3);
}
.modal-box h3 { color: #1a4a7a; margin-bottom: 12px; font-size: 13px; }
.modal-inp { width: 100%; padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 12px; margin-bottom: 10px; }
.modal-btns { display: flex; gap: 8px; justify-content: flex-end; flex-wrap: wrap; }
.modal-btns button, .modal-btns a {
    padding: 5px 12px; border: none; border-radius: 4px;
    cursor: pointer; font-size: 11px; font-weight: bold;
    text-decoration: none; display: inline-flex; align-items: center;
}
.btn-ok  { background: #1a4a7a; color: white; }
.btn-ann { background: #ddd; color: #555; }
.btn-av  { background: #27ae60; color: white; }
.btn-ap  { background: #2980b9; color: white; }
.btn-ch  { background: #8e44ad; color: white; }

/* Toast */
.toast {
    position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%);
    background: #1a4a7a; color: white; padding: 8px 20px;
    border-radius: 20px; font-size: 12px; opacity: 0;
    transition: opacity 0.3s; z-index: 2000; pointer-events: none;
}
.toast.show  { opacity: 1; }
.toast.error { background: #c0392b; }

/* Légende */
.legende { padding: 6px 12px; display: flex; gap: 10px; font-size: 10px; color: #666; flex-wrap: wrap; }
.legende span { padding: 2px 8px; border-radius: 3px; }
</style>
</head>
<body>

<!-- ══ HEADER : recherche à gauche, boutons au milieu, horloge à droite ══ -->
<script src="home.js"></script>
<div class="header">
    <!-- GAUCHE : recherche locale -->
    <input class="search-hdr" type="text" id="searchInput"
           placeholder="🔍 Rechercher patient..."
           oninput="filtrerGrille(this.value)">
    <button id="btnClearSearch" onclick="clearSearch()"
            style="display:none;background:rgba(255,255,255,0.2);color:white;border:none;
                   border-radius:4px;padding:2px 7px;cursor:pointer;font-size:11px;height:24px;">✕</button>
    <span id="searchInfo" style="color:rgba(255,255,255,0.8);font-size:10px;white-space:nowrap;"></span>
    <!-- MILIEU : boutons fixes (grille = gris car page courante) -->
    <button onclick="goHome()"          class="btn-h green" >🏠 Dossier</button>
    <a href="agenda.php"                class="btn-h navy"  >📅 Agenda</a>
    <a href="planning.php"              class="btn-h blue"  >📊 Planning</a>
    <span                               class="btn-h grey"  >📋 Grille</span>
    <a href="biologie.php"              class="btn-h orange">🧪 Biologie</a>
    <a href="jours_feries.php"          class="btn-h purple">📅 Fériés</a>
    <!-- DROITE : horloge -->
    <div class="hclock" style="margin-left:auto;">
        <div class="ct" id="clockTime">--:--:--</div>
        <div class="cd" id="clockDate">---</div>
    </div>
</div>

<!-- ══ NAVIGATION SEMAINE ══ -->
<div class="nav-sem">
    <a href="grille_semaine.php?sem=<?= $semPrecS ?>" class="btn-nav">◀ Semaine préc.</a>
    <span class="label-sem">
        Semaine du <?= dateLong($lundiS) ?> au <?= dateLong($vendrediS) ?>
    </span>
    <a href="grille_semaine.php?sem=<?= $semSuivS ?>" class="btn-nav">Semaine suiv. ▶</a>
    <?php if ($lundiS !== $lundiNowS): ?>
    <a href="grille_semaine.php" class="btn-today">📅 Cette semaine</a>
    <?php endif; ?>
</div>

<!-- ══ TABLEAU : 1 colonne par jour (Heure + 5 jours) ══ -->
<div class="wrap">
<table class="grille">
<thead>
    <tr>
        <!-- Heure toujours en PREMIERE colonne à gauche -->
        <th class="th-heure">Heure</th>
        <?php foreach ($jours5 as $i => $jourS):
            $estFerie   = isset($feriesSet[$jourS]);
            $estAujourd = ($jourS === $todayS);
            $bg = $estFerie   ? 'background:#8e44ad;'
                : ($estAujourd ? 'background:#2980b9;' : '');
        ?>
        <th class="col-jour" style="<?= $bg ?>">
            <span style="font-size:11px;font-weight:bold;">
                <?= dateCourt($jourS) ?> — <?= $jourNoms[$i] ?>
                <?php if ($estFerie):   ?>&nbsp;🔴<?php endif; ?>
                <?php if ($estAujourd): ?>&nbsp;★<?php endif; ?>
            </span>
            <?php
                $nbJour  = $totalJour[$jourS] ?? 0;
                $pct     = $nbrMax > 0 ? min(100, round($nbJour / $nbrMax * 100)) : 0;
                $barCol  = couleurBarre($nbJour, $nbrMax);
            ?>
            <div style="margin-top:5px;background:rgba(255,255,255,0.25);border-radius:4px;height:10px;overflow:hidden;">
                <div style="width:<?= $pct ?>%;height:100%;background:<?= $barCol ?>;border-radius:4px;transition:width 0.3s;"></div>
            </div>
            <span style="font-size:10px;font-weight:bold;opacity:0.9;"><?= $nbJour ?> / <?= $nbrMax ?></span>
        </th>
        <?php endforeach; ?>
    </tr>
</thead>
<tbody>

<?php
// ══ LIGNES CRÉNEAUX ══
foreach ($creneaux as $cr):
    $isHeurePleine = (substr($cr, 3, 2) === '00');
    $trClass = $isHeurePleine ? ' class="heure-pleine"' : '';
?>
<tr<?= $trClass ?>>
    <!-- Heure à GAUCHE : juste le texte, les pastilles sont dans chaque cellule -->
    <td class="td-heure"><?= $cr ?></td>

    <?php foreach ($jours5 as $jourS):
        $estFerie   = isset($feriesSet[$jourS]);
        $estAujourd = ($jourS === $todayS);
        $colCl      = $estFerie ? 'col-ferie' : ($estAujourd ? 'col-today' : '');
        $patients   = $grille[$jourS][$cr] ?? [];
        $nbPat      = count($patients);
        $dotCl      = $nbPat === 0 ? 'vert' : ($nbPat === 1 ? 'jaune' : 'rouge');
    ?>
    <td class="col-jour <?= $colCl ?>" data-jour="<?= $jourS ?>" data-cr="<?= $cr ?>" data-nb="<?= $nbPat ?>">
        <span class="cr-dot <?= $dotCl ?>" id="dot-<?= $jourS ?>-<?= str_replace(':','',$cr) ?>"></span>
        <?= renderPatients($patients) ?>
    </td>
    <?php endforeach; ?>
</tr>
<?php endforeach; ?>

<?php
// ══ LIGNE SANS HEURE ══
$hasSansHeure = false;
foreach ($jours5 as $j) {
    if (!empty($grille[$j]['__sans_heure__'])) { $hasSansHeure = true; break; }
}
if ($hasSansHeure):
?>
<tr class="tr-sans-heure">
    <td class="td-heure" title="RDV sans créneau">Sans<br>heure</td>
    <?php foreach ($jours5 as $jourS):
        $estFerie   = isset($feriesSet[$jourS]);
        $estAujourd = ($jourS === $todayS);
        $colCl      = $estFerie ? 'col-ferie' : ($estAujourd ? 'col-today' : '');
        $patients   = $grille[$jourS]['__sans_heure__'] ?? [];
    ?>
    <td class="col-jour <?= $colCl ?>" data-jour="<?= $jourS ?>" data-cr="__sans_heure__">
        <?= renderPatients($patients) ?>
    </td>
    <?php endforeach; ?>
</tr>
<?php endif; ?>

<!-- ══ TOTAL RDV ══ -->
<tr class="tr-total">
    <td class="td-heure">TOTAL</td>
    <?php foreach ($jours5 as $jourS):
        $total = $totalJour[$jourS] ?? 0;
        $estFerie   = isset($feriesSet[$jourS]);
        $estAujourd = ($jourS === $todayS);
        $col = $estFerie ? 'color:#8e44ad;' : ($estAujourd ? 'color:#2980b9;' : 'color:#1a4a7a;');
    ?>
    <td style="<?= $col ?>"><?= $total ?> RDV</td>
    <?php endforeach; ?>
</tr>

<!-- ══ CA ══ -->
<tr class="tr-ca">
    <td class="td-heure">CA</td>
    <?php foreach ($jours5 as $jourS):
        $ca = $caJour[$jourS] ?? 0;
    ?>
    <td><?= $ca > 0 ? number_format($ca,0,',',' ').' DH' : '— DH' ?></td>
    <?php endforeach; ?>
</tr>

</tbody>
</table>
</div>

<!-- Légende -->
<div class="legende">
    <span style="background:#eafaf1;">✓ Vu</span>
    <span style="background:#fff0f0;">✕ Absent</span>
    <span style="background:#f0f4fb;">Normal</span>
    <span style="background:#faf0ff;">Sans heure</span>
    <span style="background:#eef5ff;">★ Aujourd'hui</span>
    <span style="background:#f3e5f5;">🔴 Jour férié</span>
</div>

<div class="toast" id="toast"></div>

<!-- ══ MODAL JOURNÉE PLEINE ══ -->
<div class="modal-ov" id="modalPlein">
    <div class="modal-box">
        <h3>📅 Journée complète</h3>
        <div id="pleinMsg" style="font-size:11px;color:#666;margin-bottom:14px;white-space:pre-line;"></div>
        <div class="modal-btns">
            <button class="btn-av" onclick="pleinChoisirJour(-1)">◀ Jour précédent</button>
            <button class="btn-ap" onclick="pleinChoisirJour(+1)">Jour suivant ▶</button>
            <button class="btn-ann" onclick="pleinAnnuler()">✕ Annuler</button>
        </div>
    </div>
</div>

<script>
// ── Horloge ───────────────────────────────────────────────────
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

// ── Toast ─────────────────────────────────────────────────────
function toast(msg, type='success') {
    const el = document.getElementById('toast');
    el.textContent = msg;
    el.className = 'toast show ' + type;
    setTimeout(() => el.className = 'toast', 2600);
}

// ── Ajax (même endpoint qu'agenda.php) ───────────────────────
async function ajax(action, data) {
    const r = await fetch('ajax_agenda.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({action, ...data})
    });
    return r.json();
}

// ── Vu ────────────────────────────────────────────────────────
async function gToggleVu(n, estVu) {
    const r = await ajax('toggle_vu', {n_ordon:n, vu: estVu?0:1});
    if (!r.ok) return;
    const row = document.getElementById('grow-'+n);
    const btn = document.getElementById('gbvu-'+n);
    const nom = document.getElementById('gnom-'+n);
    if (!estVu) {
        row.className = row.className.replace(/\b(normal|absent)\b/g,'vu');
        btn.textContent = '✓';
        if (nom) { nom.className = nom.className.replace(/\babsent\b/g,'') + ' vu'; }
        toast('Marqué Vu ✅');
    } else {
        row.className = row.className.replace(/\bvu\b/g,'normal');
        btn.textContent = '👁';
        if (nom) nom.className = nom.className.replace(/\bvu\b/g,'');
        toast('Statut Vu retiré');
    }
}

// ── Absent ────────────────────────────────────────────────────
async function gToggleAbsent(n, estAbs) {
    const r = await ajax('toggle_absent', {n_ordon:n, absent: estAbs?0:1});
    if (!r.ok) return;
    const row = document.getElementById('grow-'+n);
    const nom = document.getElementById('gnom-'+n);
    if (!estAbs) {
        row.className = row.className.replace(/\b(normal|vu)\b/g,'absent');
        if (nom) { nom.className = nom.className.replace(/\bvu\b/g,'') + ' absent'; }
        toast('Marqué Absent 🔴');
    } else {
        row.className = row.className.replace(/\babsent\b/g,'normal');
        if (nom) nom.className = nom.className.replace(/\babsent\b/g,'');
        toast('Statut Absent retiré');
    }
}

// ── Supprimer ─────────────────────────────────────────────────
async function gSupprimer(n, id, nom) {
    if (!confirm('Supprimer le RDV de ' + nom + ' ?')) return;
    const r = await ajax('supprimer_rdv', {n_ordon:n});
    if (r.ok) {
        const row = document.getElementById('grow-'+n);
        if (row) row.remove();
        toast('RDV supprimé 🗑');
    } else toast('Erreur suppression', 'error');
}

// ── Créneaux ordonnés ─────────────────────────────────────────
const CRENEAUX = ['09:00','09:30','10:00','10:30','11:00','11:30',
                  '12:00','12:30','13:00','13:30','14:00','14:30',
                  '15:00','15:30','16:00','16:30'];

// ── Compter patients dans une cellule (jour + créneau) ────────
function compterCell(jour, cr, excludeOrd) {
    const td = document.querySelector(`td[data-jour="${jour}"][data-cr="${cr}"]`);
    if (!td) return 0;
    let count = 0;
    td.querySelectorAll('.pat-row').forEach(row => {
        if (row.id !== 'grow-' + excludeOrd) count++;
    });
    return count;
}

// ── Trouver le jour d'un patient ──────────────────────────────
function trouverJour(ord) {
    const row = document.getElementById('grow-' + ord);
    if (!row) return null;
    const td = row.closest('td[data-jour]');
    return td ? td.dataset.jour : null;
}

// ── Mettre à jour la pastille d'une cellule ───────────────────
function majPastille(jour, cr, nb) {
    const dotId = 'dot-' + jour + '-' + cr.replace(':','');
    const dot = document.getElementById(dotId);
    if (!dot) return;
    dot.className = 'cr-dot ' + (nb === 0 ? 'vert' : nb === 1 ? 'jaune' : 'rouge');
}

// ── Variables modale plein ────────────────────────────────────
let _pleinOrd, _pleinJour, _pleinHeure;

// ── Modifier heure ────────────────────────────────────────────
async function gSetHeure(ord, heure) {
    const sel = document.getElementById('gheure-' + ord);
    const ancienneHeure = sel ? sel.getAttribute('data-old') || '' : '';
    if (sel) sel.setAttribute('data-old', heure);

    if (!heure) {
        // Suppression de l'heure → on enregistre directement
        await _enregistrerHeure(ord, '', ancienneHeure);
        return;
    }

    const jour = trouverJour(ord);
    if (!jour) return;

    const nb = compterCell(jour, heure, ord);

    if (nb < 2) {
        // Créneau libre → on enregistre
        await _enregistrerHeure(ord, heure, ancienneHeure);
        return;
    }

    // Créneau plein → chercher prochain libre ce jour
    const idx = CRENEAUX.indexOf(heure);
    let prochainLibre = null;
    for (let i = idx + 1; i < CRENEAUX.length; i++) {
        if (compterCell(jour, CRENEAUX[i], ord) < 2) {
            prochainLibre = CRENEAUX[i];
            break;
        }
    }

    if (prochainLibre) {
        // Proposer le prochain créneau libre
        if (confirm(`⚠ Créneau ${heure} complet.\nProchain créneau libre : ${prochainLibre}\nConfirmer ?`)) {
            if (sel) sel.value = prochainLibre;
            sel.setAttribute('data-old', prochainLibre);
            await _enregistrerHeure(ord, prochainLibre, ancienneHeure);
        } else {
            if (sel) sel.value = ancienneHeure;
        }
    } else {
        // Toute la journée est pleine → modale choix jour
        _pleinOrd   = ord;
        _pleinJour  = jour;
        _pleinHeure = heure;
        if (sel) sel.value = ancienneHeure;
        document.getElementById('pleinMsg').textContent =
            `Journée du ${jour} complète pour ${heure} et après.\nChoisir un autre jour :`;
        ouvrirModal('modalPlein');
    }
}

async function _enregistrerHeure(ord, heure, ancienneHeure) {
    const r = await ajax('changer_heure', {n_ordon: ord, heure: heure});
    if (r.ok) {
        toast('Heure mise à jour : ' + (heure || '—') + ' ⏰');
        setTimeout(() => location.reload(), 800);
    } else {
        toast('Erreur : ' + (r.err || 'inconnue'), 'error');
        const sel = document.getElementById('gheure-' + ord);
        if (sel) sel.value = ancienneHeure;
    }
}

async function pleinChoisirJour(delta) {
    // Calculer la nouvelle date (jour+ ou jour-)
    const d = new Date(_pleinJour);
    d.setDate(d.getDate() + delta);
    // Sauter week-end
    while (d.getDay() === 0 || d.getDay() === 6) d.setDate(d.getDate() + delta);
    const nouvelleDate = d.toISOString().split('T')[0];
    fermerModal('modalPlein');
    const r = await ajax('deplacer_rdv', {n_ordon: _pleinOrd, nouvelle_date: nouvelleDate});
    if (r.ok) {
        toast('RDV déplacé → ' + nouvelleDate + ' 📅');
        setTimeout(() => location.reload(), 800);
    } else toast('Erreur déplacement', 'error');
}

function pleinAnnuler() {
    fermerModal('modalPlein');
}

// ── Déplacer ──────────────────────────────────────────────────
// ── Déplacer RDV — logique identique à dossier.php ───────────
let _depOrd = null, _depDateBase = null;

function gDeplacer(n, dateJour) {
    _depOrd      = n;
    _depDateBase = dateJour;
    // Toujours montrer la modale avec choix
    jfAfficherChoixDeplacement(dateJour, n);
}

function jfAfficherChoixDeplacement(dateBase, ord) {
    jfFermer();
    function jourOuvre(date, delta) {
        const d = new Date(date + 'T12:00:00');
        do { d.setDate(d.getDate() + delta); } while (d.getDay() === 0 || d.getDay() === 6);
        return d.toISOString().split('T')[0];
    }
    const avant  = jourOuvre(dateBase, -1);
    const apres  = jourOuvre(dateBase, +1);
    const dow    = new Date(dateBase + 'T12:00:00').getDay(); // 1=lundi, 6=sam
    const estLundiOuSam = (dow === 1 || dow === 6);
    const base   = 'border:none;border-radius:6px;padding:8px 14px;cursor:pointer;font-size:12px;font-weight:bold;';
    let btns =
        `<button style="${base}background:#2e6da4;color:white;" onclick="jfChoisirDep('${avant}',${ord})">◀ ${dateEnFr(avant)}</button>`;
    if (estLundiOuSam)
        btns += `<button style="${base}background:#e67e22;color:white;" onclick="jfChoisirDep('${dateBase}',${ord})">Garder ce jour</button>`;
    btns +=
        `<button style="${base}background:#1a4a7a;color:white;" onclick="jfChoisirDep('${apres}',${ord})">${dateEnFr(apres)} ▶</button>` +
        `<button style="${base}background:#555;color:white;"    onclick="jfChoisirDateDep(${ord})">📅 Choisir date</button>` +
        `<button style="${base}background:#ddd;color:#444;"     onclick="jfFermer()">✕ Annuler</button>`;

    document.body.insertAdjacentHTML('beforeend', `
    <div id="modal-jour-ferme" style="position:fixed;top:0;left:0;width:100%;height:100%;
         background:rgba(0,0,0,0.5);z-index:9999;display:flex;align-items:center;justify-content:center;">
        <div style="background:white;border-radius:10px;padding:24px 28px;
                    max-width:500px;width:92%;box-shadow:0 10px 40px rgba(0,0,0,0.3);">
            <div style="font-size:14px;font-weight:bold;color:#1a4a7a;margin-bottom:6px;">📆 Déplacer le RDV</div>
            <div style="font-size:12px;color:#666;margin-bottom:18px;">RDV actuel : ${dateEnFr(dateBase)} — Choisir une nouvelle date :</div>
            <div id="jf-btns" style="display:flex;flex-wrap:wrap;gap:8px;">${btns}</div>
            <div id="jf-datepicker" style="display:none;margin-top:14px;">
                <input type="date" id="jf-input-date" value="${dateBase}"
                       style="padding:5px 8px;border:1px solid #2e6da4;border-radius:4px;font-size:12px;">
                <button style="${base}background:#1a4a7a;color:white;margin-left:8px;"
                        onclick="jfConfirmerDateDep(${ord})">✔ Confirmer</button>
            </div>
        </div>
    </div>`);
}

function jfChoisirDep(date, ord) {
    jfFermer();
    verifierEtAppliquerDate(date, function(dateValidee) {
        ajax('deplacer_rdv', {n_ordon: ord, nouvelle_date: dateValidee}).then(r => {
            if (r.ok) {
                toast('RDV déplacé → ' + dateEnFr(dateValidee) + ' 📅');
                setTimeout(() => location.reload(), 800);
            } else toast('Erreur déplacement : ' + (r.err || ''), 'error');
        });
    });
}

function jfChoisirDateDep(ord) {
    document.getElementById('jf-datepicker').style.display = 'block';
    document.getElementById('jf-btns').style.display = 'none';
}

function jfConfirmerDateDep(ord) {
    const d = document.getElementById('jf-input-date').value;
    if (!d) return;
    jfFermer();
    jfChoisirDep(d, ord);
}

// ── Modale jour fermé/spécial (identique dossier.php) ────────
function dateEnFr(d) {
    if (!d) return '';
    const [a,m,j] = d.split('-');
    return j+'/'+m+'/'+a;
}

function jfFermer() {
    const m = document.getElementById('modal-jour-ferme');
    if (m) m.remove();
}

function jfAfficher(data, onChoix) {
    jfFermer();
    const estSamedi = data.est_samedi || false;
    const estLundi  = data.est_lundi  || false;
    let titre, sousTitre;
    if (estLundi) {
        titre     = '⚠️ Lundi — Habituellement non travaillé';
        sousTitre = 'Le lundi est généralement réservé. Que souhaitez-vous faire ?';
    } else if (estSamedi) {
        titre     = '⚠️ Samedi — Demi-journée habituelle';
        sousTitre = 'Le samedi est particulier. Que souhaitez-vous faire ?';
    } else {
        titre     = '⛔ ' + (data.raison || 'Jour fermé') + ' — Cabinet fermé';
        sousTitre = 'Ce jour est fermé. Choisissez une alternative :';
    }
    const base = 'border:none;border-radius:6px;padding:8px 14px;cursor:pointer;font-size:12px;font-weight:bold;';
    let btns = '';
    if (data.date_avant)
        btns += `<button style="${base}background:#2e6da4;color:white;" onclick="jfChoisir('${data.date_avant}')">◀ ${data.label_avant||dateEnFr(data.date_avant)}</button>`;
    if ((estLundi || estSamedi) && data.date_cible) {
        const lbl = estLundi ? 'Garder lundi' : 'Garder samedi';
        btns += `<button style="${base}background:#e67e22;color:white;" onclick="jfChoisir('${data.date_cible}')">${lbl}</button>`;
    }
    if (data.date_apres)
        btns += `<button style="${base}background:#1a4a7a;color:white;" onclick="jfChoisir('${data.date_apres}')">${data.label_apres||dateEnFr(data.date_apres)} ▶</button>`;
    btns += `<button style="${base}background:#555;color:white;" onclick="jfChoisirDate()">📅 Choisir date</button>`;
    btns += `<button style="${base}background:#ddd;color:#444;" onclick="jfFermer()">✕ Annuler</button>`;

    document.body.insertAdjacentHTML('beforeend', `
    <div id="modal-jour-ferme" style="position:fixed;top:0;left:0;width:100%;height:100%;
         background:rgba(0,0,0,0.5);z-index:9999;display:flex;align-items:center;justify-content:center;">
        <div style="background:white;border-radius:10px;padding:24px 28px;
                    max-width:500px;width:92%;box-shadow:0 10px 40px rgba(0,0,0,0.3);">
            <div style="font-size:14px;font-weight:bold;color:#1a4a7a;margin-bottom:6px;">${titre}</div>
            <div style="font-size:12px;color:#666;margin-bottom:18px;">${sousTitre}</div>
            <div id="jf-btns" style="display:flex;flex-wrap:wrap;gap:8px;">${btns}</div>
            <div id="jf-datepicker" style="display:none;margin-top:14px;">
                <input type="date" id="jf-input-date"
                       style="padding:5px 8px;border:1px solid #2e6da4;border-radius:4px;font-size:12px;">
                <button style="${base}background:#1a4a7a;color:white;margin-left:8px;" onclick="jfConfirmerDate()">✔ Confirmer</button>
            </div>
        </div>
    </div>`);
    window._jfCallback = onChoix;
}

function jfChoisir(date) {
    jfFermer();
    if (window._jfCallback) { window._jfCallback(date); window._jfCallback = null; }
}
function jfChoisirDate() {
    document.getElementById('jf-datepicker').style.display = 'block';
    document.getElementById('jf-btns').style.display = 'none';
}
function jfConfirmerDate() {
    const d = document.getElementById('jf-input-date').value;
    if (!d) return;
    jfFermer();
    if (window._jfCallback) { window._jfCallback(d); window._jfCallback = null; }
}

function verifierEtAppliquerDate(dateCible, callback) {
    fetch('ajax_prochain_jour.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ date_cible: dateCible })
    })
    .then(r => r.json())
    .then(data => {
        if (data.error) { alert('❌ ' + data.error); return; }
        if (data.ok) {
            callback(data.date_trouvee);
        } else {
            jfAfficher(
                { ...data, date_cible: dateCible },
                (dateChoisie) => verifierEtAppliquerDate(dateChoisie, callback)
            );
        }
    })
    .catch(() => alert('❌ Erreur réseau'));
}

// ── Modales ───────────────────────────────────────────────────
function ouvrirModal(id) { document.getElementById(id).classList.add('show'); }
function fermerModal(id) { document.getElementById(id).classList.remove('show'); }
document.querySelectorAll('.modal-ov').forEach(m =>
    m.addEventListener('click', e => { if (e.target===m) m.classList.remove('show'); }));

// ── Recherche ─────────────────────────────────────────────────
function filtrerGrille(v) {
    v = v.toLowerCase().trim();
    let first = null, found = 0;
    document.querySelectorAll('.pat-row[data-nom]').forEach(row => {
        const match = !v || row.dataset.nom.includes(v) || String(row.dataset.id).includes(v);
        row.classList.toggle('hidden-search', !match);
        if (match && v) {
            row.classList.add('highlight');
            found++;
            if (!first) first = row;
        } else {
            row.classList.remove('highlight');
        }
    });
    if (first) {
        first.scrollIntoView({ behavior: 'smooth', block: 'center' });
        first.style.outline = '2px solid #2e6da4';
        setTimeout(() => first.style.outline = '', 1500);
    }
    const btnClear = document.getElementById('btnClearSearch');
    const info     = document.getElementById('searchInfo');
    if (btnClear) btnClear.style.display = v ? 'inline-block' : 'none';
    if (info)     info.textContent = v ? found + ' résultat(s)' : '';

    afficherBtnEtendu(v, found);
}

function clearSearch() {
    document.getElementById('searchInput').value = '';
    filtrerGrille('');
    masquerEtendu();
}

// ── Recherche étendue sur période ─────────────────────────────
function afficherBtnEtendu(v, found) {
    let zone = document.getElementById('zone-etendue');
    if (!v) { if (zone) zone.style.display = 'none'; return; }
    if (!zone) {
        zone = document.createElement('div');
        zone.id = 'zone-etendue';
        zone.style.cssText = 'position:fixed;top:36px;left:0;z-index:500;background:#1a4a7a;' +
            'color:white;padding:6px 12px;display:flex;align-items:center;gap:8px;font-size:11px;' +
            'border-bottom:2px solid #f39c12;width:100%;box-shadow:0 2px 8px rgba(0,0,0,0.3);';
        document.body.appendChild(zone);
    }
    zone.style.display = 'flex';
    const msg = found > 0 ? `✅ ${found} sur cette semaine — Chercher aussi sur :` : `⚠ Pas trouvé sur cette semaine — Chercher sur :`;
    zone.innerHTML = `<span>${msg}</span>
        <button onclick="rechercheEtendue(1)"  style="${_btnStyle('#27ae60')}">± 1 mois</button>
        <button onclick="rechercheEtendue(3)"  style="${_btnStyle('#f39c12')}">± 3 mois</button>
        <button onclick="rechercheEtendue(6)"  style="${_btnStyle('#e74c3c')}">± 6 mois</button>
        <div id="resultat-etendu" style="flex:1;"></div>`;
}

function masquerEtendu() {
    const z = document.getElementById('zone-etendue');
    if (z) z.style.display = 'none';
}

function _btnStyle(bg) {
    return `background:${bg};color:white;border:none;border-radius:4px;` +
           `padding:3px 10px;cursor:pointer;font-size:11px;font-weight:bold;`;
}

async function rechercheEtendue(mois) {
    const v       = document.getElementById('searchInput').value.trim();
    const dateRef = '<?= $lundiS ?>';
    const res     = document.getElementById('resultat-etendu');
    if (res) res.innerHTML = '<i>Recherche…</i>';

    const r = await fetch('ajax_agenda.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({action:'rechercher_rdv_periode', q:v, mois, date_ref:dateRef})
    });
    const d = await r.json();
    if (!d.ok || !d.rdvs.length) {
        if (res) res.innerHTML = '<span style="color:#f39c12;">Aucun RDV trouvé sur ±'+mois+' mois</span>';
        return;
    }
    let html = '<div style="display:flex;flex-wrap:wrap;gap:6px;">';
    d.rdvs.forEach(rdv => {
        const [a,m,j] = (rdv.jour||'').split('-');
        const dateFr  = rdv.jour ? j+'/'+m+'/'+a : '?';
        const heure   = rdv.heure ? ' '+rdv.heure : '';
        // Calculer le lundi de la semaine du RDV
        const dt = new Date(rdv.jour+'T12:00:00');
        const diff = (dt.getDay()+6)%7;
        dt.setDate(dt.getDate()-diff);
        const sem = dt.toISOString().split('T')[0];
        html += `<a href="grille_semaine.php?sem=${sem}"
                    style="background:rgba(255,255,255,0.15);color:white;text-decoration:none;
                           border-radius:4px;padding:2px 8px;font-size:11px;white-space:nowrap;">
                    📅 ${dateFr}${heure} — ${rdv.nom}
                 </a>`;
    });
    html += '</div>';
    if (res) res.innerHTML = html;
}
</script>
</body>
</html>
