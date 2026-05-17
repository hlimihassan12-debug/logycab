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

        // Nom cliquable
        $vuNomCl = $p['vu'] ? 'vu' : ($p['absent'] ? 'absent' : '');
        $html .= "<span class='pat-nom-txt {$vuNomCl}' id='gnom-{$ord}'
                        onclick=\"location.href='dossier.php?id={$idE}'\"
                        title='{$nomE}'>".htmlspecialchars($nom)."</span>";

        $html .= "</div>";
    }
    return $html;
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
    <!-- MILIEU : boutons fixes (grille = gris car page courante) -->
    <button onclick="goHome()"          class="btn-h green" >🏠 Dossier</button>
    <a href="agenda.php"                class="btn-h navy"  >📅 Agenda</a>
    <a href="planning.php"              class="btn-h blue"  >📊 Planning</a>
    <span                               class="btn-h grey"  >📋 Grille</span>
    <a href="biologie.php" class="btn-h orange">🧪 Biologie</a>
    <a href="jours_feries.php"          class="btn-h purple">📅 Fériés</a>
    <!-- TITRE -->
    <h1 style="margin-left:8px;">📋 Grille Semaine</h1>
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
            <?= $jourNoms[$i] ?><br>
            <span style="font-size:10px;font-weight:normal;opacity:0.85;">
                <?= dateCourt($jourS) ?>
                <?php if ($estFerie):   ?>&nbsp;🔴<?php endif; ?>
                <?php if ($estAujourd): ?>&nbsp;★<?php endif; ?>
            </span>
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
    <!-- Heure à GAUCHE -->
    <td class="td-heure"><?= $cr ?></td>

    <?php foreach ($jours5 as $jourS):
        $estFerie   = isset($feriesSet[$jourS]);
        $estAujourd = ($jourS === $todayS);
        $colCl      = $estFerie ? 'col-ferie' : ($estAujourd ? 'col-today' : '');
        $patients   = $grille[$jourS][$cr] ?? [];
    ?>
    <!-- 1 seule cellule par jour : N° + boutons + Nom sur la même ligne -->
    <td class="col-jour <?= $colCl ?>">
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
    <td class="col-jour <?= $colCl ?>">
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

<!-- ══ MODAL DÉPLACER ══ -->
<div class="modal-ov" id="modalDep">
    <div class="modal-box">
        <h3>📆 Déplacer le RDV</h3>
        <input type="hidden" id="depOrd">
        <div id="depMsg" style="font-size:11px;color:#666;margin-bottom:10px;min-height:16px;"></div>
        <div class="modal-btns">
            <button class="btn-av"  id="btnAvant"  onclick="depChoisir('avant')" style="display:none">◀ Avant</button>
            <button class="btn-ok"  id="btnGarder" onclick="depConfirmer()"      style="display:none">Garder</button>
            <button class="btn-ap"  id="btnApres"  onclick="depChoisir('apres')" style="display:none">Après ▶</button>
            <button class="btn-ch"                 onclick="depManuel()">📅 Choisir date</button>
            <button class="btn-ann"                onclick="fermerModal('modalDep')">✕ Annuler</button>
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

// ── Déplacer ──────────────────────────────────────────────────
let depDateCourante = '', depAvant = '', depApres = '';

async function gDeplacer(n, dateJour) {
    document.getElementById('depOrd').value = n;
    depDateCourante = dateJour;
    document.getElementById('depMsg').textContent = 'Vérification…';
    ['btnAvant','btnGarder','btnApres'].forEach(id =>
        document.getElementById(id).style.display = 'none');

    const r = await fetch('ajax_prochain_jour.php?date=' + dateJour);
    const d = await r.json();
    depAvant = d.avant ?? '';
    depApres = d.apres ?? '';

    let msg = 'Date : ' + dateJour;
    if (d.statut === 'ferie')  msg += ' 🔴 Jour férié';
    if (d.statut === 'samedi') msg += ' (Samedi)';
    if (d.statut === 'lundi')  msg += ' (Lundi)';
    document.getElementById('depMsg').textContent = msg;

    if (depAvant) document.getElementById('btnAvant').style.display = '';
    document.getElementById('btnGarder').style.display = '';
    if (depApres) document.getElementById('btnApres').style.display = '';

    ouvrirModal('modalDep');
}

async function depChoisir(sens) {
    const date = sens === 'avant' ? depAvant : depApres;
    if (!date) return;
    const n = document.getElementById('depOrd').value;
    const r = await ajax('deplacer_rdv', {n_ordon:n, nouvelle_date:date});
    if (r.ok) {
        toast('RDV déplacé → '+date+' 📅');
        fermerModal('modalDep');
        setTimeout(() => location.reload(), 800);
    } else toast('Erreur', 'error');
}

async function depConfirmer() {
    const n = document.getElementById('depOrd').value;
    const r = await ajax('deplacer_rdv', {n_ordon:n, nouvelle_date:depDateCourante});
    if (r.ok) { toast('Date conservée'); fermerModal('modalDep'); }
    else toast('Erreur', 'error');
}

function depManuel() {
    const d = prompt('Date (AAAA-MM-JJ) :', depDateCourante);
    if (!d) return;
    const n = document.getElementById('depOrd').value;
    ajax('deplacer_rdv', {n_ordon:n, nouvelle_date:d}).then(r => {
        if (r.ok) {
            toast('RDV déplacé → '+d+' 📅');
            fermerModal('modalDep');
            setTimeout(() => location.reload(), 800);
        } else toast('Erreur', 'error');
    });
}

// ── Modales ───────────────────────────────────────────────────
function ouvrirModal(id) { document.getElementById(id).classList.add('show'); }
function fermerModal(id) { document.getElementById(id).classList.remove('show'); }
document.querySelectorAll('.modal-ov').forEach(m =>
    m.addEventListener('click', e => { if (e.target===m) m.classList.remove('show'); }));

// ── Recherche ─────────────────────────────────────────────────
function filtrerGrille(v) {
    v = v.toLowerCase().trim();
    document.getElementById('btnClearSearch').style.display = v ? 'inline-block' : 'none';
    let found = 0;
    document.querySelectorAll('.pat-row[data-nom]').forEach(row => {
        const match = !v || row.dataset.nom.includes(v) || String(row.dataset.id).includes(v);
        row.classList.toggle('hidden-search', !match);
        if (match && v) { row.classList.add('highlight'); found++; }
        else row.classList.remove('highlight');
    });
    document.getElementById('searchInfo').textContent = v ? found+' résultat(s)' : '';
}
function clearSearch() {
    document.getElementById('searchInput').value = '';
    filtrerGrille('');
}
</script>
</body>
</html>
