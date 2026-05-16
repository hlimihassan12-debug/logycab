<?php
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';
$db = getDB();

// ── Date affichée ──────────────────────────────────────────────
$dateParam = $_GET['date'] ?? date('Y-m-d');
try { $dateObj = new DateTime($dateParam); }
catch (Exception $e) { $dateObj = new DateTime(); }
$dateAff  = $dateObj->format('Y-m-d');
$datePrev = (clone $dateObj)->modify('-1 day')->format('Y-m-d');
$dateSuiv = (clone $dateObj)->modify('+1 day')->format('Y-m-d');

function strftime_fr($date) {
    $jours = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
    $mois  = ['','Janvier','Février','Mars','Avril','Mai','Juin',
               'Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
    $ts = strtotime($date);
    if (!$ts || $ts < 0) return $date;
    return $jours[date('w',$ts)] . ' ' . date('j',$ts) . ' ' . $mois[(int)date('n',$ts)] . ' ' . date('Y',$ts);
}
function navDate($base, $offset) {
    return (new DateTime($base))->modify($offset)->format('Y-m-d');
}

// ── Limite du jour ─────────────────────────────────────────────
$stmtCfg = $db->prepare("SELECT Valeur FROM T_Config WHERE Cle='NbrMax'");
$stmtCfg->execute();
$nbrMax = (int)($stmtCfg->fetchColumn() ?: 20);

// ── Patients du jour ───────────────────────────────────────────
$stmtPat = $db->prepare("
    SELECT o.n_ordon, o.id, o.HeureRDV, o.Vu, o.SansReponse,
           o.Observation, o.acte1,
           p.NOMPRENOM, p.[TEL D] AS tel,
           ISNULL(SUM(d.Versé),0) AS montant_verse
    FROM ORD o
    LEFT JOIN ID p ON o.id = p.[N°PAT]
    LEFT JOIN facture f ON f.id = o.id
        AND CONVERT(date, f.date_facture) = ?
    LEFT JOIN detail_acte d ON d.N_fact = f.n_facture
    WHERE CONVERT(date, o.[DATE REDEZ VOUS]) = ?
    GROUP BY o.n_ordon, o.id, o.HeureRDV, o.Vu, o.SansReponse,
             o.Observation, o.acte1, p.NOMPRENOM, p.[TEL D]
    ORDER BY o.HeureRDV, o.n_ordon
");
$stmtPat->execute([$dateAff, $dateAff]);
$patients   = $stmtPat->fetchAll(PDO::FETCH_ASSOC);
$nbPatients = count($patients);
$totalVerse = array_sum(array_column($patients, 'montant_verse'));

// ── Total versé global ─────────────────────────────────────────
$stmtGlobal = $db->prepare("
    SELECT ISNULL(SUM(d.Versé),0)
    FROM facture f
    LEFT JOIN detail_acte d ON d.N_fact = f.n_facture
    WHERE CONVERT(date, f.date_facture) = ?
");
$stmtGlobal->execute([$dateAff]);
$totalGlobal = (float)$stmtGlobal->fetchColumn();

// ── Créneaux 9h→16h30 par demi-heure ──────────────────────────
$creneaux = [];
for ($h = 9; $h <= 16; $h++) {
    $creneaux[] = sprintf('%02d:00', $h);
    if ($h < 16) $creneaux[] = sprintf('%02d:30', $h);
}
// Ajouter 16:30 manuellement
$creneaux[] = '16:30';

// Compter patients par créneau
$patParCreneau = [];
foreach ($patients as $pat) {
    $h = trim($pat['HeureRDV'] ?? '');
    if (preg_match('/^(\d{1,2}):(\d{2})/', $h, $m)) {
        $key = sprintf('%02d:%02d', $m[1], $m[2]);
        $patParCreneau[$key] = ($patParCreneau[$key] ?? 0) + 1;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Agenda — <?= htmlspecialchars(strftime_fr($dateAff)) ?></title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Arial, sans-serif; background: #f0f4f8; font-size: 12px; }

/* ── Header ── */
.header { background: #1a4a7a; color: white; padding: 6px 12px;
          display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.header h1 { font-size: 14px; }
.btn-h { color: white; text-decoration: none; border: none; cursor: pointer;
         padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: bold;
         display: inline-block; }
.btn-h.blue   { background: #2e6da4; }
.btn-h.green  { background: #27ae60; }
.btn-h.orange { background: #e67e22; }
.btn-h.grey   { background: #555; }
.btn-h.active { background: #f39c12; }
.btn-h:hover  { opacity: 0.85; }

/* Horloge temps réel */
.clock-display {
    margin-left: auto;
    background: rgba(255,255,255,0.12);
    border-radius: 6px;
    padding: 3px 10px;
    font-size: 12px;
    font-weight: bold;
    letter-spacing: 1px;
    text-align: right;
    min-width: 130px;
}
.clock-display .clock-time { font-size: 15px; color: #f0f4f8; }
.clock-display .clock-date { font-size: 9px; opacity: 0.75; }

/* ── Navigation date ── */
.nav-date { background: #0f3460; color: white; padding: 5px 10px;
            display: flex; align-items: center; justify-content: center;
            gap: 5px; flex-wrap: wrap; }
.date-label { font-size: 14px; font-weight: bold; min-width: 220px; text-align: center; }
.btn-nav { background: rgba(255,255,255,0.18); color: white; border: none;
           padding: 3px 12px; border-radius: 4px; cursor: pointer;
           font-size: 13px; font-weight: bold; text-decoration: none;
           display: inline-block; }
.btn-nav:hover { background: rgba(255,255,255,0.35); }
.btn-nav.sm { font-size: 10px; padding: 3px 7px; opacity: 0.8; }
.btn-nav.sm:hover { opacity: 1; }
.nav-sep { width: 1px; height: 20px; background: rgba(255,255,255,0.2); margin: 0 3px; }

/* ── Stats bar ── */
.stats-bar { background: white; padding: 6px 12px; display: flex; gap: 10px;
             align-items: center; flex-wrap: wrap;
             border-bottom: 2px solid #e0e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.06); }
.stat-pill { background: #f0f4f8; border-radius: 16px; padding: 3px 12px;
             font-size: 11px; display: flex; align-items: center; gap: 4px; }
.stat-pill .val { font-weight: bold; font-size: 15px; color: #1a4a7a; }
.stat-pill .lbl { color: #888; font-size: 10px; }
.stat-pill.green .val { color: #27ae60; }
.stat-pill.red   .val { color: #e74c3c; }
.stat-pill.orange .val { color: #e67e22; }
.search-input { padding: 4px 10px; border: 1px solid #ccc; border-radius: 16px;
                font-size: 11px; width: 220px; outline: none; margin-left: auto; }
.search-input:focus { border-color: #2e6da4; }

/* ── Layout ── */
.main { display: flex; }

/* ── Créneaux ── */
.col-creneaux { width: 100px; min-width: 100px; background: white;
                border-right: 2px solid #e0e8f0; padding: 6px 4px;
                position: sticky; top: 0; }
.col-creneaux h4 { font-size: 9px; color: #aaa; text-align: center;
                   text-transform: uppercase; margin-bottom: 5px; letter-spacing: 1px; }
.cr-item { display: flex; align-items: center; gap: 4px; margin-bottom: 3px;
           padding: 3px 5px; border-radius: 5px; cursor: pointer;
           border: 1px solid transparent; }
.cr-item:hover { border-color: #2e6da4; background: #f0f4f8; }
.cr-heure { font-size: 11px; font-weight: bold; color: #444; min-width: 35px; }
.cr-dot { width: 12px; height: 12px; border-radius: 50%; flex-shrink: 0; }
.cr-dot.vert   { background: #27ae60; }
.cr-dot.jaune  { background: #f39c12; }
.cr-dot.rouge  { background: #e74c3c; }
.cr-nb { font-size: 10px; color: #888; }

/* ── Patients ── */
.col-patients { flex: 1; padding: 8px; overflow-x: auto; }

/* ── Carte patient — LIGNE UNIQUE avec colonnes fixes ── */
.pat-card { background: white; border-radius: 6px; padding: 5px 8px;
            margin-bottom: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            border-left: 4px solid #2e6da4; }
.pat-card.vu     { border-left-color: #27ae60; background: #f8fff9; }
.pat-card.absent { border-left-color: #e74c3c; background: #fff8f8; }
.pat-card.hidden { display: none; }

/* Ligne principale : colonnes à largeur fixe */
.pat-line {
    display: flex;
    align-items: center;
    gap: 4px;
    white-space: nowrap;
    overflow: hidden;
}

/* Colonne heure */
.pat-heure {
    background: #1a4a7a; color: white; padding: 2px 6px;
    border-radius: 8px; font-size: 11px; font-weight: bold;
    width: 50px; text-align: center; flex-shrink: 0;
}

/* N° patient */
.pat-num {
    font-size: 10px; color: #888;
    width: 42px; flex-shrink: 0; overflow: hidden;
    text-overflow: ellipsis;
}

/* Téléphone — sans icône, largeur fixe 10 chiffres */
.pat-tel {
    font-size: 11px; color: #555;
    width: 90px; flex-shrink: 0;
    overflow: hidden; text-overflow: ellipsis;
    font-family: monospace;
    letter-spacing: 0.3px;
}

/* Nom — double-clic ouvre dossier */
.pat-nom {
    font-size: 12px; font-weight: bold; color: #1a4a7a;
    width: 300px; flex-shrink: 0;
    overflow: hidden; text-overflow: ellipsis;
    cursor: pointer;
    border-radius: 3px;
    padding: 1px 3px;
}
.pat-nom:hover { background: #e8f0fb; text-decoration: underline; }
.pat-nom.vu     { color: #aaa; }
.pat-nom.absent { color: #e67e22; }

/* Boutons compacts */
.btn-p { border: none; border-radius: 4px; padding: 2px 7px; cursor: pointer;
         font-size: 10px; font-weight: bold; flex-shrink: 0; }
.btn-vu     { background: #27ae60; color: white; }
.btn-absent { background: #e74c3c; color: white; }
.btn-dep    { background: #8e44ad; color: white; }
.btn-sup    { background: #c0392b; color: white; width: 26px; text-align: center; }
.btn-p:hover { opacity: 0.85; }

/* WhatsApp — icône SVG verte, visible seulement au survol de la carte */
.btn-wa-wrap {
    width: 28px; flex-shrink: 0; display: flex; align-items: center; justify-content: center;
}
.btn-wa {
    text-decoration: none; display: none; line-height: 0;
}
.pat-card:hover .btn-wa { display: inline-flex; }
.btn-wa svg { width: 22px; height: 22px; }
.btn-wa-placeholder {
    display: flex; align-items: center; justify-content: center; line-height: 0;
}
.btn-wa-placeholder svg { width: 22px; height: 22px; opacity: 0.15; }
.pat-card:hover .btn-wa-placeholder { display: none; }

/* Créneau select compact */
.creneau-select { padding: 2px 2px; border: 1px solid #ddd; border-radius: 4px;
                  font-size: 10px; color: #555; flex-shrink: 0; width: 65px; }

/* Observation inline — largeur fixe ~200px */
.obs-input {
    width: 200px; flex-shrink: 0; padding: 2px 4px;
    border: 1px solid #ddd; border-radius: 4px; font-size: 10px;
}

/* Montant */
.montant-badge { font-size: 10px; font-weight: bold; flex-shrink: 0; width: 52px; text-align: right; }
.montant-badge.ok  { color: #27ae60; }
.montant-badge.non { color: #ccc; }

/* ── Footer ── */
.footer-bar { background: #1a4a7a; color: white; padding: 6px 12px;
              display: flex; gap: 16px; align-items: center; flex-wrap: wrap;
              position: sticky; bottom: 0; }
.footer-stat .fval { font-size: 15px; font-weight: bold; }
.footer-stat .flbl { font-size: 9px; opacity: 0.75; text-transform: uppercase; }
.footer-stat.green .fval { color: #2ecc71; }
.footer-stat.orange .fval { color: #f39c12; }
.btn-fg { background: rgba(255,255,255,0.15); border: none; color: white;
          padding: 3px 10px; border-radius: 4px; cursor: pointer; font-size: 10px; }
.btn-fg:hover { background: rgba(255,255,255,0.3); }

/* ── Empty ── */
.empty { text-align: center; padding: 50px; color: #bbb; font-size: 14px; }

/* ── Toast ── */
.toast { position: fixed; top: 16px; right: 16px; padding: 9px 16px;
         border-radius: 6px; font-size: 12px; z-index: 9999; display: none;
         box-shadow: 0 4px 12px rgba(0,0,0,0.25); color: white; }
.toast.show    { display: block; }
.toast.success { background: #27ae60; }
.toast.error   { background: #e74c3c; }
.toast.info    { background: #2e6da4; }

/* ── Modals ── */
.modal-ov { display: none; position: fixed; top:0; left:0; width:100%; height:100%;
            background: rgba(0,0,0,0.45); z-index: 1000;
            align-items: center; justify-content: center; }
.modal-ov.show { display: flex; }
.modal-box { background: white; border-radius: 10px; padding: 18px;
             min-width: 300px; box-shadow: 0 10px 40px rgba(0,0,0,0.3); }
.modal-box h3 { color: #1a4a7a; margin-bottom: 12px; font-size: 13px; }
.modal-inp { width: 100%; padding: 6px 10px; border: 1px solid #ccc;
             border-radius: 4px; font-size: 12px; margin-bottom: 10px; }
.modal-btns { display: flex; gap: 8px; justify-content: flex-end; }
.modal-btns button { padding: 5px 14px; border: none; border-radius: 4px;
                     cursor: pointer; font-size: 11px; font-weight: bold; }
.btn-ok  { background: #1a4a7a; color: white; }
.btn-ann { background: #ddd; color: #555; }

/* Avertissement jour férié / lundi */
.dep-avertissement {
    background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px;
    padding: 6px 10px; font-size: 11px; color: #856404;
    margin-bottom: 10px; display: none;
}

/* ── Calendrier picker ── */
input[type=date].date-pick { padding: 4px 8px; border: 1px solid rgba(255,255,255,0.4);
    border-radius: 4px; background: rgba(255,255,255,0.12); color: white;
    font-size: 12px; cursor: pointer; }
input[type=date].date-pick::-webkit-calendar-picker-indicator { filter: invert(1); }

/* Tooltip nom patient */
.pat-nom[title]:hover::after {
    content: attr(title);
    position: absolute;
    background: #1a4a7a;
    color: white;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 10px;
    white-space: nowrap;
    z-index: 100;
    margin-top: 20px;
    margin-left: -50px;
    pointer-events: none;
}
.pat-nom { position: relative; }
</style>
</head>
<body>

<!-- ── HEADER ── -->
<div class="header">
    <a href="recherche.php" class="btn-h blue">🏠 Accueil</a>
    <h1>📅 Agenda</h1>
    <a href="agenda.php?date=<?= $dateAff ?>" class="btn-h active">📅 Agenda</a>
    <a href="planning.php" class="btn-h blue">📆 Planning</a>
    <button class="btn-h green"  onclick="ouvrirAjoutPatient()">➕ Ajouter</button>
    <button class="btn-h orange" onclick="modifierLimite()">⚙️ Max (<?= $nbrMax ?>)</button>
    <button class="btn-h grey"   onclick="voirSemaine()">📊 Semaine</button>
    <button class="btn-h green"  onclick="envoyerWaListe()">📲 WA liste</button>

    <!-- Horloge temps réel -->
    <div class="clock-display">
        <div class="clock-time" id="clockTime">--:--:--</div>
        <div class="clock-date" id="clockDate">---</div>
    </div>
</div>

<!-- ── NAVIGATION DATE ── -->
<div class="nav-date">
    <a href="agenda.php?date=<?= navDate($dateAff,'-6 months') ?>" class="btn-nav sm">◀6M</a>
    <a href="agenda.php?date=<?= navDate($dateAff,'-3 months') ?>" class="btn-nav sm">◀3M</a>
    <a href="agenda.php?date=<?= navDate($dateAff,'-1 month')  ?>" class="btn-nav sm">◀1M</a>
    <a href="agenda.php?date=<?= navDate($dateAff,'-7 days')   ?>" class="btn-nav sm">◀1S</a>
    <div class="nav-sep"></div>

    <a href="agenda.php?date=<?= $datePrev ?>" class="btn-nav">◀</a>
    <span class="date-label">📅 <?= htmlspecialchars(strftime_fr($dateAff)) ?></span>
    <a href="agenda.php?date=<?= $dateSuiv ?>" class="btn-nav">▶</a>

    <input type="date" class="date-pick" value="<?= $dateAff ?>"
           onchange="location.href='agenda.php?date='+this.value">

    <div class="nav-sep"></div>
    <a href="agenda.php?date=<?= navDate($dateAff,'+7 days')   ?>" class="btn-nav sm">1S▶</a>
    <a href="agenda.php?date=<?= navDate($dateAff,'+1 month')  ?>" class="btn-nav sm">1M▶</a>
    <a href="agenda.php?date=<?= navDate($dateAff,'+3 months') ?>" class="btn-nav sm">3M▶</a>
    <a href="agenda.php?date=<?= navDate($dateAff,'+6 months') ?>" class="btn-nav sm">6M▶</a>
</div>

<!-- ── STATS ── -->
<div class="stats-bar">
    <div class="stat-pill <?= $nbPatients >= $nbrMax ? 'red' : ($nbPatients > $nbrMax*0.7 ? 'orange' : '') ?>">
        <span class="val"><?= $nbPatients ?></span>
        <span class="lbl">/ <?= $nbrMax ?> RDV</span>
    </div>
    <div class="stat-pill green">
        <span class="val"><?= $nbrMax - $nbPatients ?></span>
        <span class="lbl">places libres</span>
    </div>
    <div class="stat-pill green">
        <span class="val"><?= number_format($totalVerse,0,',',' ') ?> DH</span>
        <span class="lbl">versé RDV</span>
    </div>
    <!-- Recherche sur nom ET N°PAT -->
    <input type="text" class="search-input"
           placeholder="🔍 Nom ou N° patient..."
           oninput="filtrerPatients(this.value)">
</div>

<!-- ── LAYOUT ── -->
<div class="main">

    <!-- ── CRÉNEAUX ── -->
    <div class="col-creneaux">
        <h4>Créneaux</h4>
        <?php foreach ($creneaux as $cr):
            $nb = $patParCreneau[$cr] ?? 0;
            $cl = $nb === 0 ? 'vert' : ($nb === 1 ? 'jaune' : 'rouge');
        ?>
        <div class="cr-item" onclick="scrollToCreneau('<?= $cr ?>')">
            <span class="cr-heure"><?= $cr ?></span>
            <span class="cr-dot <?= $cl ?>"></span>
            <?php if ($nb): ?><span class="cr-nb"><?= $nb ?></span><?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ── PATIENTS ── -->
    <div class="col-patients">
    <?php if (empty($patients)): ?>
        <div class="empty">📭 Aucun rendez-vous ce jour</div>
    <?php else: ?>

    <?php foreach ($patients as $pat):
        $estVu     = (bool)$pat['Vu'];
        $estAbsent = (bool)$pat['SansReponse'];
        $cardCl    = $estVu ? 'vu' : ($estAbsent ? 'absent' : '');
        $nomCl     = $estVu ? 'vu' : ($estAbsent ? 'absent' : '');
        $heure     = trim($pat['HeureRDV'] ?? '');
        $heureAff  = $heure ?: '—';
        $tel       = trim($pat['tel'] ?? '');
        $telRaw    = preg_replace('/\D/', '', $tel);
        $telWa     = $telRaw ? ('212' . ltrim($telRaw, '0')) : '';
        $msgWa     = urlencode('Bonjour, rappel RDV le ' . strftime_fr($dateAff) . ' à ' . $heureAff . '. Cabinet Dr Hassan.');
        $acte      = trim($pat['acte1'] ?? '');
        $obs       = htmlspecialchars($pat['Observation'] ?? '');
        $verse     = (float)$pat['montant_verse'];
        // Nom complet pour tooltip si tronqué
        $nomComplet = htmlspecialchars($pat['NOMPRENOM'] ?? '—');
    ?>
    <div class="pat-card <?= $cardCl ?>"
         id="card-<?= $pat['n_ordon'] ?>"
         data-nom="<?= strtolower($nomComplet) ?>"
         data-num="<?= $pat['id'] ?>"
         data-heure="<?= $heure ?>">

        <div class="pat-line">
            <!-- Heure -->
            <span class="pat-heure" id="heure-<?= $pat['n_ordon'] ?>"><?= htmlspecialchars($heureAff) ?></span>

            <!-- N° patient -->
            <span class="pat-num">N°<?= $pat['id'] ?></span>

            <!-- Téléphone SANS icône -->
            <span class="pat-tel"><?= htmlspecialchars($tel) ?></span>

            <!-- Nom — double-clic ouvre dossier, title = nom complet pour tooltip -->
            <span class="pat-nom <?= $nomCl ?>"
                  id="nom-<?= $pat['n_ordon'] ?>"
                  title="<?= $nomComplet ?> — N°<?= $pat['id'] ?>"
                  ondblclick="window.location.href='dossier.php?id=<?= $pat['id'] ?>'">
                <?= $nomComplet ?>
            </span>

            <!-- Bouton Vu (toggle : marquer / annuler) -->
            <button class="btn-p btn-vu" id="btnVu-<?= $pat['n_ordon'] ?>"
                    onclick="toggleVu(<?= $pat['n_ordon'] ?>, <?= $estVu?1:0 ?>)">
                <?= $estVu ? '✅ Vu' : '👁 Vu' ?>
            </button>

            <!-- Bouton Absent (toggle) -->
            <button class="btn-p btn-absent" id="btnAbs-<?= $pat['n_ordon'] ?>"
                    onclick="toggleAbsent(<?= $pat['n_ordon'] ?>, <?= $estAbsent?1:0 ?>)">
                <?= $estAbsent ? '🔴 Abs' : '❌ Abs' ?>
            </button>

            <!-- Bouton Déplacer -->
            <button class="btn-p btn-dep"
                    onclick="deplacerRdv(<?= $pat['n_ordon'] ?>, '<?= $dateAff ?>')">📆</button>

            <!-- WhatsApp — icône SVG officielle, visible au survol seulement -->
            <?php
            // SVG officiel WhatsApp (logo rond vert)
            $svgWa = '<svg viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
                <circle cx="24" cy="24" r="24" fill="#25D366"/>
                <path fill="white" d="M34.5 13.5A14.9 14.9 0 0 0 24 9C15.7 9 9 15.7 9 24c0 2.6.7 5.2 2 7.4L9 39l7.8-2c2.1 1.1 4.5 1.8 7.2 1.8 8.3 0 15-6.7 15-15 0-4-.6-7.6-4.5-10.3zM24 37.1c-2.3 0-4.5-.6-6.4-1.7l-.5-.3-4.6 1.2 1.2-4.5-.3-.5A12.9 12.9 0 0 1 11.1 24c0-7.1 5.8-12.9 12.9-12.9 3.4 0 6.6 1.3 9 3.7s3.7 5.6 3.7 9c0 7.2-5.8 13.3-12.7 13.3zm7.1-9.7c-.4-.2-2.3-1.1-2.6-1.2-.3-.1-.6-.2-.8.2-.2.4-.9 1.2-1.1 1.4-.2.2-.4.2-.8 0-.4-.2-1.6-.6-3-1.9-1.1-1-1.9-2.2-2.1-2.6-.2-.4 0-.6.2-.8l.6-.7c.2-.2.2-.4.4-.6.1-.2.1-.4 0-.6-.1-.2-.8-2-1.1-2.7-.3-.7-.6-.6-.8-.6h-.7c-.2 0-.6.1-.9.4-.3.3-1.2 1.2-1.2 2.9s1.3 3.4 1.4 3.6c.2.2 2.5 3.8 6 5.3.8.4 1.5.6 2 .7.8.3 1.6.2 2.2.1.7-.1 2.1-.9 2.4-1.7.3-.8.3-1.5.2-1.7-.1-.1-.3-.2-.6-.4z"/>
            </svg>';
            ?>
            <div class="btn-wa-wrap">
                <?php if ($telWa): ?>
                <a href="https://wa.me/<?= $telWa ?>?text=<?= $msgWa ?>"
                   target="_blank" class="btn-wa" title="Envoyer WhatsApp">
                    <?= $svgWa ?>
                </a>
                <span class="btn-wa-placeholder"><?= $svgWa ?></span>
                <?php else: ?>
                <span class="btn-wa-placeholder" title="Pas de numéro"><?= $svgWa ?></span>
                <?php endif; ?>
            </div>

            <!-- Créneau horaire -->
            <select class="creneau-select"
                    onchange="changerHeure(<?= $pat['n_ordon'] ?>, this.value)">
                <option value="">—H—</option>
                <?php foreach ($creneaux as $cr): ?>
                <option value="<?= $cr ?>" <?= $heure === $cr ? 'selected' : '' ?>><?= $cr ?></option>
                <?php endforeach; ?>
            </select>

            <!-- Observation -->
            <input type="text" class="obs-input" placeholder="Obs..."
                   value="<?= $obs ?>"
                   onblur="sauvegarderObs(<?= $pat['n_ordon'] ?>, this.value)">

            <!-- Montant versé -->
            <span class="montant-badge <?= $verse > 0 ? 'ok' : 'non' ?>">
                <?= $verse > 0 ? number_format($verse,0,',',' ').' DH' : '0 DH' ?>
            </span>

            <!-- Supprimer -->
            <button class="btn-p btn-sup"
                    onclick="supprimerRdv(<?= $pat['n_ordon'] ?>, '<?= addslashes($pat['NOMPRENOM'] ?? '') ?>')">🗑</button>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
    </div>
</div>

<!-- ── FOOTER ── -->
<div class="footer-bar">
    <div class="footer-stat green">
        <div class="fval"><?= number_format($totalVerse,0,',',' ') ?> DH</div>
        <div class="flbl">Versé (avec RDV)</div>
    </div>
    <div class="footer-stat orange">
        <div class="fval"><?= number_format($totalGlobal,0,',',' ') ?> DH</div>
        <div class="flbl">Versé global</div>
    </div>
    <button class="btn-fg" onclick="voirDetailGlobal()">📊 Détail global</button>
    <div style="margin-left:auto;font-size:10px;opacity:0.6;">
        <?= $nbPatients ?> patient(s) — <?= htmlspecialchars(strftime_fr($dateAff)) ?>
    </div>
</div>

<!-- TOAST -->
<div class="toast" id="toast"></div>

<!-- MODAL AJOUTER -->
<div class="modal-ov" id="modalAjout">
    <div class="modal-box">
        <h3>➕ Ajouter un patient</h3>
        <input type="text" class="modal-inp" id="addSearch"
               placeholder="Nom ou N° patient..."
               oninput="rechercherPatientAjout(this.value)">
        <div id="addResults" style="max-height:200px;overflow-y:auto;margin-bottom:10px;border:1px solid #eee;border-radius:4px;"></div>
        <div class="modal-btns">
            <button class="btn-ann" onclick="fermerModal('modalAjout')">Annuler</button>
        </div>
    </div>
</div>

<!-- MODAL DÉPLACER — avec vérification jours fériés/lundi -->
<div class="modal-ov" id="modalDep">
    <div class="modal-box">
        <h3>📆 Déplacer le RDV</h3>
        <input type="hidden" id="depOrd">
        <div style="display:flex;gap:8px;margin-bottom:10px;">
            <button class="btn-ok" onclick="deplacerJ(-1)">◀ -1 jour</button>
            <button class="btn-ok" onclick="deplacerJ(+1)">+1 jour ▶</button>
        </div>
        <input type="date" class="modal-inp" id="depDate"
               onchange="verifierJourDep(this.value)">
        <!-- Avertissement affiché si jour interdit -->
        <div class="dep-avertissement" id="depAvert"></div>
        <!-- Date corrigée proposée -->
        <div id="depCorrection" style="display:none;margin-bottom:10px;">
            <span style="font-size:11px;color:#555;">Date corrigée : </span>
            <strong id="depDateCorrigee" style="color:#1a4a7a;font-size:12px;"></strong>
            <button onclick="appliquerCorrection()" style="margin-left:8px;padding:2px 8px;
                border:none;border-radius:4px;background:#27ae60;color:white;
                cursor:pointer;font-size:10px;">Utiliser cette date</button>
        </div>
        <div class="modal-btns">
            <button class="btn-ann" onclick="fermerModal('modalDep')">Annuler</button>
            <button class="btn-ok"  onclick="confirmerDeplacement()">✔ Confirmer</button>
        </div>
    </div>
</div>

<!-- MODAL SEMAINE -->
<div class="modal-ov" id="modalSem">
    <div class="modal-box" style="min-width:380px;">
        <h3>📊 Semaine en cours</h3>
        <div id="semListe" style="margin-bottom:12px;"></div>
        <div class="modal-btns">
            <button class="btn-ann" onclick="fermerModal('modalSem')">Fermer</button>
        </div>
    </div>
</div>

<script>
const dateAff = '<?= $dateAff ?>';
const nbrMax  = <?= $nbrMax ?>;

// ── Horloge temps réel ────────────────────────────────
(function miseAJourHorloge() {
    const jours = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
    const mois  = ['Jan','Fév','Mar','Avr','Mai','Juin','Juil','Aoû','Sep','Oct','Nov','Déc'];
    function tick() {
        const n = new Date();
        const h = String(n.getHours()).padStart(2,'0');
        const m = String(n.getMinutes()).padStart(2,'0');
        const s = String(n.getSeconds()).padStart(2,'0');
        document.getElementById('clockTime').textContent = h+':'+m+':'+s;
        document.getElementById('clockDate').textContent =
            jours[n.getDay()] + ' ' + n.getDate() + ' ' + mois[n.getMonth()] + ' ' + n.getFullYear();
    }
    tick();
    setInterval(tick, 1000);
})();

// ── Toast ─────────────────────────────────────────────
function toast(msg, type='success') {
    const el = document.getElementById('toast');
    el.textContent = msg;
    el.className = 'toast show ' + type;
    setTimeout(() => el.className = 'toast', 2600);
}

// ── Scroll créneau ────────────────────────────────────
function scrollToCreneau(h) {
    for (const c of document.querySelectorAll('.pat-card')) {
        if (c.dataset.heure === h) {
            c.scrollIntoView({ behavior:'smooth', block:'center' });
            c.style.outline = '2px solid #2e6da4';
            setTimeout(() => c.style.outline='', 1500);
            return;
        }
    }
}

// ── Filtrer — sur nom ET N°PAT ────────────────────────
function filtrerPatients(v) {
    v = v.toLowerCase().trim();
    document.querySelectorAll('.pat-card').forEach(c => {
        if (!v) { c.classList.remove('hidden'); return; }
        const matchNom = c.dataset.nom.includes(v);
        const matchNum = String(c.dataset.num).includes(v);
        c.classList.toggle('hidden', !matchNom && !matchNum);
    });
}

// ── Ajax ──────────────────────────────────────────────
async function ajax(action, data) {
    const r = await fetch('ajax_agenda.php', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({action, ...data})
    });
    return r.json();
}

// ── Vu (toggle : marquer / annuler) ──────────────────
async function toggleVu(n, estVu) {
    const r = await ajax('toggle_vu', {n_ordon:n, vu: estVu?0:1});
    if (!r.ok) return;
    const card = document.getElementById('card-'+n);
    const btn  = document.getElementById('btnVu-'+n);
    const nom  = document.getElementById('nom-'+n);
    if (!estVu) {
        card.classList.add('vu'); card.classList.remove('absent');
        nom.classList.add('vu');  btn.textContent = '✅ Vu';
        btn.onclick = () => toggleVu(n, 1);
    } else {
        card.classList.remove('vu'); nom.classList.remove('vu');
        btn.textContent = '👁 Vu';
        btn.onclick = () => toggleVu(n, 0);
    }
    toast(estVu ? 'Statut Vu retiré ↩' : 'Marqué Vu ✅');
}

// ── Absent ────────────────────────────────────────────
async function toggleAbsent(n, estAbs) {
    const r = await ajax('toggle_absent', {n_ordon:n, absent: estAbs?0:1});
    if (!r.ok) return;
    const card = document.getElementById('card-'+n);
    const btn  = document.getElementById('btnAbs-'+n);
    const nom  = document.getElementById('nom-'+n);
    if (!estAbs) {
        card.classList.add('absent'); card.classList.remove('vu');
        nom.classList.add('absent'); btn.textContent = '🔴 Abs';
        btn.onclick = () => toggleAbsent(n, 1);
    } else {
        card.classList.remove('absent'); nom.classList.remove('absent');
        btn.textContent = '❌ Abs';
        btn.onclick = () => toggleAbsent(n, 0);
    }
    toast(estAbs ? 'Statut Absent retiré ↩' : 'Marqué Absent 🔴');
}

// ── Changer heure ─────────────────────────────────────
async function changerHeure(n, heure) {
    if (heure) {
        const cards = [...document.querySelectorAll('.pat-card:not(.hidden)')];
        let count = 0;
        cards.forEach(c => { if (c.dataset.heure === heure && c.id !== 'card-'+n) count++; });
        if (count >= 2) {
            toast('⚠ Créneau ' + heure + ' déjà complet (max 2)', 'error');
            const sel = document.querySelector('#card-'+n+' .creneau-select');
            if (sel) sel.value = document.getElementById('heure-'+n).textContent.trim();
            return;
        }
    }
    const r = await ajax('changer_heure', {n_ordon:n, heure});
    if (r.ok) {
        const el = document.getElementById('heure-'+n);
        el.textContent = heure || '—';
        document.getElementById('card-'+n).dataset.heure = heure;
        toast('Heure mise à jour ⏰');
    }
}

// ── Observation ───────────────────────────────────────
async function sauvegarderObs(n, obs) {
    const r = await ajax('sauvegarder_obs', {n_ordon:n, observation:obs});
    if (r.ok) toast('Observation enregistrée 📝');
}

// ── Déplacer — avec vérification jours fériés/lundi ──
let dateCorigee = null; // stocke la date corrigée proposée

/**
 * Vérifie si une date est autorisée.
 * Règles : Lundi (1) interdit, Dimanche (0) interdit, Jours fériés interdits.
 * Samedi (6) autorisé.
 */
async function verifierJourDep(dateStr) {
    if (!dateStr) return;
    const avert = document.getElementById('depAvert');
    const corr  = document.getElementById('depCorrection');
    avert.style.display = 'none';
    corr.style.display  = 'none';
    dateCorigee = null;

    // Vérifier côté serveur (jours fériés + règles cabinet)
    const r = await ajax('verifier_jour', {date: dateStr});
    if (!r.ok) return;

    if (!r.autorise) {
        avert.style.display = 'block';
        avert.textContent   = '⚠ ' + r.raison + ' → Date suggérée : ' + r.date_corrigee_label;
        dateCorigee = r.date_corrigee;
        document.getElementById('depDateCorrigee').textContent = r.date_corrigee_label;
        corr.style.display = 'block';
    }
}

function appliquerCorrection() {
    if (!dateCorigee) return;
    document.getElementById('depDate').value = dateCorigee;
    document.getElementById('depAvert').style.display = 'none';
    document.getElementById('depCorrection').style.display = 'none';
    dateCorigee = null;
    toast('Date corrigée appliquée ✅', 'info');
}

function deplacerRdv(n, d) {
    document.getElementById('depOrd').value  = n;
    document.getElementById('depDate').value = d;
    document.getElementById('depAvert').style.display = 'none';
    document.getElementById('depCorrection').style.display = 'none';
    dateCorigee = null;
    ouvrirModal('modalDep');
}
function deplacerJ(delta) {
    const d = new Date(document.getElementById('depDate').value);
    d.setDate(d.getDate() + delta);
    const newDate = d.toISOString().split('T')[0];
    document.getElementById('depDate').value = newDate;
    verifierJourDep(newDate);
}
async function confirmerDeplacement() {
    const n = document.getElementById('depOrd').value;
    let   d = document.getElementById('depDate').value;
    if (!d) return;

    // Si une correction existe et n'a pas été appliquée, l'appliquer auto
    if (dateCorigee) {
        if (!confirm('La date choisie est invalide. Utiliser la date corrigée : ' +
                     document.getElementById('depDateCorrigee').textContent + ' ?')) return;
        d = dateCorigee;
    }

    const r = await ajax('deplacer_rdv', {n_ordon:n, nouvelle_date:d});
    if (r.ok) {
        toast('RDV déplacé 📅'); fermerModal('modalDep');
        setTimeout(() => location.reload(), 800);
    } else toast('Erreur déplacement', 'error');
}

// ── Supprimer ─────────────────────────────────────────
async function supprimerRdv(n, nom) {
    if (!confirm('Supprimer le RDV de ' + nom + ' ?')) return;
    const r = await ajax('supprimer_rdv', {n_ordon:n});
    if (r.ok) { document.getElementById('card-'+n).remove(); toast('RDV supprimé 🗑'); }
    else toast('Erreur suppression', 'error');
}

// ── Modifier limite ───────────────────────────────────
async function modifierLimite() {
    const v = prompt('Nouvelle limite patients/jour :', nbrMax);
    if (!v || isNaN(v)) return;
    const r = await ajax('modifier_limite', {nbrmax: parseInt(v)});
    if (r.ok) { toast('Limite : '+v+' patients'); setTimeout(() => location.reload(), 800); }
}

// ── Vue semaine ───────────────────────────────────────
async function voirSemaine() {
    const r = await ajax('rdv_semaine', {date: dateAff});
    if (!r.ok) return;
    document.getElementById('semListe').innerHTML = r.jours.map(j => {
        const cl = j.nb < 5 ? '#27ae60' : j.nb < 16 ? '#f39c12' : '#e74c3c';
        return `<div style="display:flex;align-items:center;gap:10px;padding:5px 0;border-bottom:1px solid #eee;">
            <a href="agenda.php?date=${j.date}" style="color:#1a4a7a;font-weight:bold;text-decoration:none;min-width:200px;">${j.label}</a>
            <span style="background:${cl};color:white;padding:2px 10px;border-radius:10px;font-size:11px;">${j.nb} RDV</span>
        </div>`;
    }).join('');
    ouvrirModal('modalSem');
}

// ── WhatsApp liste ────────────────────────────────────
function envoyerWaListe() {
    const liens = [...document.querySelectorAll('.btn-wa[href]')];
    if (!liens.length) { toast('Aucun numéro disponible', 'error'); return; }
    let i = 0;
    function next() {
        if (i >= liens.length) { toast('WhatsApp : '+i+' envoyés'); return; }
        window.open(liens[i].href, '_blank'); i++; setTimeout(next, 1500);
    }
    next();
}

// ── Ajouter patient ───────────────────────────────────
function ouvrirAjoutPatient() {
    document.getElementById('addSearch').value = '';
    document.getElementById('addResults').innerHTML = '';
    ouvrirModal('modalAjout');
}
let searchTimer;
function rechercherPatientAjout(v) {
    clearTimeout(searchTimer);
    if (v.length < 2) { document.getElementById('addResults').innerHTML=''; return; }
    searchTimer = setTimeout(async () => {
        const r = await ajax('rechercher_patient', {q:v});
        if (!r.ok) return;
        document.getElementById('addResults').innerHTML = r.patients.length
            ? r.patients.map(p =>
                `<div onclick="ajouterPatient(${p.id},'${p.nom.replace(/'/g,"\\'")}','${dateAff}')"
                      style="padding:6px 10px;cursor:pointer;border-bottom:1px solid #f0f0f0;font-size:11px;"
                      onmouseover="this.style.background='#f0f4f8'" onmouseout="this.style.background=''">
                    <strong>${p.nom}</strong> <span style="color:#888;">N°${p.id}</span>
                 </div>`).join('')
            : '<div style="padding:10px;color:#aaa;font-size:11px;">Aucun résultat</div>';
    }, 300);
}
async function ajouterPatient(id, nom, date) {
    const r = await ajax('ajouter_rdv', {id, date_rdv: date});
    if (r.ok) {
        toast('RDV ajouté : '+nom+' ✅');
        fermerModal('modalAjout');
        setTimeout(() => location.reload(), 800);
    } else toast('Erreur ajout', 'error');
}

// ── Détail global ─────────────────────────────────────
async function voirDetailGlobal() {
    const r = await ajax('detail_global', {date: dateAff});
    if (r.ok && r.patients) {
        alert('VERSÉ GLOBAL — '+dateAff+'\n\n'+
            r.patients.map(p=>p.nom+' : '+p.montant+' DH').join('\n')+
            '\n\nTOTAL : '+r.total+' DH');
    }
}

// ── Modales ───────────────────────────────────────────
function ouvrirModal(id) { document.getElementById(id).classList.add('show'); }
function fermerModal(id) { document.getElementById(id).classList.remove('show'); }
document.querySelectorAll('.modal-ov').forEach(m =>
    m.addEventListener('click', e => { if (e.target===m) m.classList.remove('show'); }));
</script>
</body>
</html>