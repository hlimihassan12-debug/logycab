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

// ── Créneaux 9h→16h par demi-heure ────────────────────────────
$creneaux = [];
for ($h = 9; $h <= 16; $h++) {
    $creneaux[] = sprintf('%02d:00', $h);
    if ($h < 16) $creneaux[] = sprintf('%02d:30', $h);
}

// Compter patients par créneau (inclut patients sans heure → comptés dans premier créneau)
$patParCreneau = [];
foreach ($patients as $pat) {
    $h = trim($pat['HeureRDV'] ?? '');
    if (preg_match('/^(\d{1,2}):(\d{2})/', $h, $m)) {
        $key = sprintf('%02d:%02d', $m[1], $m[2]);
        $patParCreneau[$key] = ($patParCreneau[$key] ?? 0) + 1;
    }
}
// Patients sans heure : signalés séparément dans la colonne créneaux
$sansCreneau = 0;
foreach ($patients as $pat) {
    $h = trim($pat['HeureRDV'] ?? '');
    if (!preg_match('/^(\d{1,2}):(\d{2})/', $h)) $sansCreneau++;
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
.header { background: #1a4a7a; color: white; padding: 5px 12px;
          display: flex; align-items: center; gap: 6px; flex-wrap: nowrap; }
.header h1 { font-size: 14px; white-space: nowrap; }
/* btn-h : boutons de l'en-tête UNIQUEMENT — pas affectés par btn-wa-end */
.btn-h { color: white; text-decoration: none; border: none; cursor: pointer;
         padding: 3px 9px; border-radius: 4px; font-size: 11px; font-weight: bold;
         display: inline-flex; align-items: center; height: 24px;
         white-space: nowrap; line-height: 1; }
.btn-h.blue   { background: #2e6da4; }
.btn-h.green  { background: #27ae60; }
.btn-h.orange { background: #e67e22; }
.btn-h.grey   { background: #555; }
.btn-h:hover  { opacity: 0.85; }

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

/* ── Carte patient — hauteur réduite pour 15-20 patients/page ── */
.pat-card { background: white; border-radius: 5px; padding: 3px 8px;
            margin-bottom: 3px; box-shadow: 0 1px 2px rgba(0,0,0,0.07);
            border-left: 4px solid #2e6da4; }
.pat-card.vu     { border-left-color: #27ae60; background: #f8fff9; }
.pat-card.absent { border-left-color: #e74c3c; background: #fff8f8; }
.pat-card.hidden { display: none; }

/* Ligne principale — overflow hidden : rien ne déborde jamais */
.pat-line { display: flex; align-items: center; gap: 5px; flex-wrap: nowrap; overflow: hidden; }

/* ── Éléments LARGEUR FIXE — ne bougent pas selon le contenu ── */
.pat-heure { background: #1a4a7a; color: white; padding: 0;
             border-radius: 6px; font-size: 11px; font-weight: bold;
             width: 54px; height: 22px; text-align: center; flex-shrink: 0;
             display: inline-flex; align-items: center; justify-content: center; }

/* Numéro patient : largeur fixe, aligné à droite, sans préfixe N° */
.pat-num   { font-size: 10px; color: #999; flex-shrink: 0;
             width: 32px; text-align: right; }

/* Téléphone : largeur fixe, police monospace pour alignement */
.pat-tel   { font-size: 10px; color: #666; flex-shrink: 0; white-space: nowrap;
             font-family: monospace; width: 88px; }

/* Nom : largeur fixe, overflow caché avec ellipse si trop long */
.pat-nom   { font-size: 12px; font-weight: bold; color: #1a4a7a;
             width: 180px; max-width: 180px; flex-shrink: 0;
             overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pat-nom.vu     { color: #aaa; }
.pat-nom.absent { color: #e67e22; }

/* Acte : largeur FIXE — ne s'étire jamais selon le contenu */
.pat-acte  { background: #e8f0fb; color: #2e6da4; padding: 1px 5px;
             border-radius: 6px; font-size: 9px; font-weight: bold; flex-shrink: 0;
             width: 90px; max-width: 90px;
             overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/* Boutons compacts — largeur fixe */
.btn-p { border: none; border-radius: 4px; padding: 2px 6px; cursor: pointer;
         font-size: 10px; font-weight: bold; flex-shrink: 0; height: 22px;
         display: inline-flex; align-items: center; justify-content: center; }
.btn-vu     { background: #27ae60; color: white; width: 44px; }
.btn-absent { background: #e74c3c; color: white; width: 44px; }
.btn-dep    { background: #8e44ad; color: white; width: 26px; }
.btn-sup    { background: #c0392b; color: white; width: 26px; }
.btn-dos    { background: #1a4a7a; color: white; text-decoration: none;
              display: inline-flex; align-items: center; justify-content: center;
              width: 26px; height: 22px; }
.btn-p:hover, .btn-dos:hover { opacity: 0.85; }

/* Créneau select — largeur fixe, hauteur alignée aux boutons */
.creneau-select { padding: 0 3px; border: 1px solid #ddd; border-radius: 4px;
                  font-size: 10px; color: #555; flex-shrink: 0;
                  width: 62px; height: 22px; }

/* Montant DH — largeur fixe */
.montant-badge { font-size: 10px; font-weight: bold; flex-shrink: 0;
                 white-space: nowrap; width: 54px; text-align: right; }
.montant-badge.ok  { color: #27ae60; }
.montant-badge.non { color: #bbb; }

/* ── Observation : occupe tout l'espace restant, police bleue visible ── */
.obs-input { flex: 1; min-width: 120px; padding: 2px 7px;
             border: 1px solid #c5d8ee; border-radius: 4px;
             font-size: 11px; height: 22px;
             color: #1a4a7a; background: #f5f9ff; }
.obs-input::placeholder { color: #aac0d8; font-style: italic; }
.obs-input:focus { outline: none; border-color: #2e6da4; background: white; }

/* ── Bouton WhatsApp à droite — icône SVG officielle ── */
.btn-wa-end {
    flex-shrink: 0;
    width: 26px; height: 22px;
    border-radius: 6px;
    display: inline-flex; align-items: center; justify-content: center;
    text-decoration: none;
    transition: opacity 0.2s;
}
.btn-wa-end:hover { opacity: 0.85; }
/* Avec numéro : vert WhatsApp */
.btn-wa-end.has-tel  { background: #25D366; }
/* Sans numéro : gris */
.btn-wa-end.no-tel   { background: #ccc; cursor: default; pointer-events: none; }
/* SVG WhatsApp blanc */
.btn-wa-end svg { width: 15px; height: 15px; fill: white; }

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

/* ── Calendrier picker ── */
input[type=date].date-pick { padding: 4px 8px; border: 1px solid rgba(255,255,255,0.4);
    border-radius: 4px; background: rgba(255,255,255,0.12); color: white;
    font-size: 12px; cursor: pointer; }
input[type=date].date-pick::-webkit-calendar-picker-indicator { filter: invert(1); }
</style>
</head>
<body>

<!-- ── HEADER ── -->
<script src="home.js"></script>
<div class="header">
    <button onclick="goHome()" class="btn-h green">🏠 Dossier</button>
    <a href="recherche.php" class="btn-h blue">🔍 Recherche</a>
    <h1>📅 Agenda</h1>
    <button class="btn-h green"  onclick="ouvrirAjoutPatient()">➕ Ajouter</button>
    <button class="btn-h orange" onclick="modifierLimite()">⚙️ Max (<?= $nbrMax ?>)</button>
    <button class="btn-h grey"   onclick="voirSemaine()">📊 Semaine</button>
    <a href="planning.php"       class="btn-h blue">📅 Planning</a>
    <a href="grille_semaine.php" class="btn-h blue">📋 Grille</a>
    <a href="jours_feries.php" class="btn-h" style="background:#8e44ad;">📅 Fériés</a>
    <!-- Horloge widget identique à dossier.php -->
    <div style="margin-left:auto; background:rgba(255,255,255,0.12); border-radius:6px;
                padding:3px 10px; text-align:center; min-width:130px; flex-shrink:0;">
        <div id="clockTime" style="font-size:15px;font-weight:bold;letter-spacing:1px;color:#f0f4f8;">--:--:--</div>
        <div id="clockDate" style="font-size:9px;opacity:0.75;">---</div>
    </div>
    <!-- Icône WA liste tout à droite -->
    <button onclick="envoyerWaListe()" title="Envoyer WhatsApp à tous"
            style="background:#25D366; border:none; border-radius:8px;
                   width:34px; height:34px; cursor:pointer; padding:0; flex-shrink:0;
                   display:inline-flex; align-items:center; justify-content:center;">
        <svg viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg" style="width:20px;height:20px;fill:white;">
            <path d="M16 2C8.28 2 2 8.28 2 16c0 2.44.65 4.73 1.78 6.72L2 30l7.48-1.96A13.94 13.94 0 0016 30c7.72 0 14-6.28 14-14S23.72 2 16 2zm0 25.4a11.35 11.35 0 01-5.78-1.57l-.41-.25-4.44 1.16 1.18-4.32-.27-.44A11.36 11.36 0 014.6 16C4.6 9.7 9.7 4.6 16 4.6S27.4 9.7 27.4 16 22.3 27.4 16 27.4zm6.23-8.5c-.34-.17-2.01-.99-2.32-1.1-.31-.11-.54-.17-.77.17-.22.34-.87 1.1-1.07 1.33-.2.22-.39.25-.73.08-.34-.17-1.43-.53-2.73-1.68-1.01-.9-1.69-2.01-1.89-2.35-.2-.34-.02-.52.15-.69.15-.15.34-.39.51-.59.17-.2.22-.34.34-.57.11-.22.06-.42-.03-.59-.08-.17-.77-1.86-1.06-2.55-.28-.67-.56-.58-.77-.59h-.66c-.22 0-.57.08-.87.42-.31.34-1.17 1.14-1.17 2.78s1.2 3.23 1.36 3.45c.17.22 2.35 3.59 5.7 5.04.8.34 1.42.55 1.9.7.8.25 1.53.22 2.1.13.64-.1 1.97-.81 2.25-1.59.28-.78.28-1.46.2-1.59-.09-.14-.31-.22-.65-.39z"/>
        </svg>
    </button>
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
    <input type="text" class="search-input" placeholder="🔍 Rechercher..."
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
        <?php if ($sansCreneau > 0): ?>
        <div class="cr-item" style="border-top:1px dashed #c0a0d0;margin-top:4px;">
            <span class="cr-heure" style="font-size:9px;color:#8e44ad;">Sans<br>heure</span>
            <span class="cr-dot rouge"></span>
            <span class="cr-nb"><?= $sansCreneau ?></span>
        </div>
        <?php endif; ?>
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
    ?>
    <div class="pat-card <?= $cardCl ?>"
         id="card-<?= $pat['n_ordon'] ?>"
         data-nom="<?= strtolower(htmlspecialchars($pat['NOMPRENOM'] ?? '')) ?>"
         data-heure="<?= $heure ?>">

        <div class="pat-line">

            <!-- 1. Heure -->
            <span class="pat-heure" id="heure-<?= $pat['n_ordon'] ?>"><?= htmlspecialchars($heureAff) ?></span>

            <!-- 2. N° patient (sans préfixe N°, juste le numéro) -->
            <span class="pat-num"><?= $pat['id'] ?></span>

            <!-- 3. Téléphone : texte simple, sans emoji -->
            <?php if ($tel): ?>
            <span class="pat-tel"><?= htmlspecialchars($tel) ?></span>
            <?php else: ?>
            <span class="pat-tel" style="color:#ddd;">—</span>
            <?php endif; ?>

            <!-- 4. Nom -->
            <span class="pat-nom <?= $nomCl ?>" id="nom-<?= $pat['n_ordon'] ?>">
                <?= htmlspecialchars($pat['NOMPRENOM'] ?? '—') ?>
            </span>

            <!-- 5. Acte — toujours présent pour garder l'alignement -->
            <span class="pat-acte"><?= $acte ? '🔬 '.htmlspecialchars($acte) : '' ?></span>

            <!-- 6. Bouton Vu -->
            <button class="btn-p btn-vu" id="btnVu-<?= $pat['n_ordon'] ?>"
                    title="Marquer comme vu / annuler"
                    onclick="toggleVu(<?= $pat['n_ordon'] ?>, <?= $estVu?1:0 ?>)">
                <?= $estVu ? '✅ Vu' : '👁 Vu' ?>
            </button>

            <!-- 7. Bouton Absent -->
            <button class="btn-p btn-absent" id="btnAbs-<?= $pat['n_ordon'] ?>"
                    title="Marquer comme absent / annuler"
                    onclick="toggleAbsent(<?= $pat['n_ordon'] ?>, <?= $estAbsent?1:0 ?>)">
                <?= $estAbsent ? '🔴 Abs' : '❌ Abs' ?>
            </button>

            <!-- 8. Bouton Déplacer — losange SVG -->
            <button class="btn-p btn-dep"
                    title="Déplacer le RDV à une autre date"
                    onclick="deplacerRdv(<?= $pat['n_ordon'] ?>, '<?= $dateAff ?>')">
                <svg viewBox="0 0 22 16" xmlns="http://www.w3.org/2000/svg" style="width:18px;height:13px;">
                    <polygon points="11,1 21,8 11,15 1,8" fill="white" stroke="rgba(255,255,255,0.4)" stroke-width="0.5"/>
                    <line x1="11" y1="1" x2="11" y2="15" stroke="rgba(0,0,0,0.25)" stroke-width="1.2"/>
                    <polygon points="11,1 21,8 11,15" fill="rgba(255,255,255,0.55)"/>
                </svg>
            </button>

            <!-- 9. Dossier -->
            <a href="dossier.php?id=<?= $pat['id'] ?>" class="btn-p btn-dos"
               title="Ouvrir le dossier patient">📋</a>

            <!-- 10. Supprimer -->
            <button class="btn-p btn-sup"
                    title="Supprimer ce RDV définitivement"
                    onclick="supprimerRdv(<?= $pat['n_ordon'] ?>, '<?= htmlspecialchars($pat['NOMPRENOM'] ?? '') ?>')">🗑</button>

            <!-- 11. Créneau horaire -->
            <select class="creneau-select"
                    title="Attribuer un créneau horaire"
                    onchange="changerHeure(<?= $pat['n_ordon'] ?>, this.value)">
                <option value="">—H—</option>
                <?php foreach ($creneaux as $cr): ?>
                <option value="<?= $cr ?>" <?= $heure === $cr ? 'selected' : '' ?>><?= $cr ?></option>
                <?php endforeach; ?>
            </select>

            <!-- 12. Montant DH -->
            <span class="montant-badge <?= $verse > 0 ? 'ok' : 'non' ?>"
                  title="Montant versé ce jour">
                <?= $verse > 0 ? number_format($verse,0,',',' ').' DH' : '0 DH' ?>
            </span>

            <!-- 13. Observation -->
            <input type="text" class="obs-input" placeholder="Observation..."
                   title="Note libre — sauvegardée automatiquement à la sortie du champ"
                   value="<?= $obs ?>"
                   onblur="sauvegarderObs(<?= $pat['n_ordon'] ?>, this.value)">

            <!-- 14. Bouton WhatsApp (tout à droite) — icône SVG officielle -->
            <?php if ($telWa): ?>
            <a href="https://wa.me/<?= $telWa ?>?text=<?= $msgWa ?>"
               target="_blank"
               class="btn-wa-end has-tel"
               title="Envoyer WhatsApp à <?= htmlspecialchars($tel) ?>">
                <svg viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
                    <path d="M16 2C8.28 2 2 8.28 2 16c0 2.44.65 4.73 1.78 6.72L2 30l7.48-1.96A13.94 13.94 0 0016 30c7.72 0 14-6.28 14-14S23.72 2 16 2zm0 25.4a11.35 11.35 0 01-5.78-1.57l-.41-.25-4.44 1.16 1.18-4.32-.27-.44A11.36 11.36 0 014.6 16C4.6 9.7 9.7 4.6 16 4.6S27.4 9.7 27.4 16 22.3 27.4 16 27.4zm6.23-8.5c-.34-.17-2.01-.99-2.32-1.1-.31-.11-.54-.17-.77.17-.22.34-.87 1.1-1.07 1.33-.2.22-.39.25-.73.08-.34-.17-1.43-.53-2.73-1.68-1.01-.9-1.69-2.01-1.89-2.35-.2-.34-.02-.52.15-.69.15-.15.34-.39.51-.59.17-.2.22-.34.34-.57.11-.22.06-.42-.03-.59-.08-.17-.77-1.86-1.06-2.55-.28-.67-.56-.58-.77-.59h-.66c-.22 0-.57.08-.87.42-.31.34-1.17 1.14-1.17 2.78s1.2 3.23 1.36 3.45c.17.22 2.35 3.59 5.7 5.04.8.34 1.42.55 1.9.7.8.25 1.53.22 2.1.13.64-.1 1.97-.81 2.25-1.59.28-.78.28-1.46.2-1.59-.09-.14-.31-.22-.65-.39z"/>
                </svg>
            </a>
            <?php else: ?>
            <span class="btn-wa-end no-tel"
                  title="Pas de numéro disponible">
                <svg viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
                    <path d="M16 2C8.28 2 2 8.28 2 16c0 2.44.65 4.73 1.78 6.72L2 30l7.48-1.96A13.94 13.94 0 0016 30c7.72 0 14-6.28 14-14S23.72 2 16 2zm0 25.4a11.35 11.35 0 01-5.78-1.57l-.41-.25-4.44 1.16 1.18-4.32-.27-.44A11.36 11.36 0 014.6 16C4.6 9.7 9.7 4.6 16 4.6S27.4 9.7 27.4 16 22.3 27.4 16 27.4zm6.23-8.5c-.34-.17-2.01-.99-2.32-1.1-.31-.11-.54-.17-.77.17-.22.34-.87 1.1-1.07 1.33-.2.22-.39.25-.73.08-.34-.17-1.43-.53-2.73-1.68-1.01-.9-1.69-2.01-1.89-2.35-.2-.34-.02-.52.15-.69.15-.15.34-.39.51-.59.17-.2.22-.34.34-.57.11-.22.06-.42-.03-.59-.08-.17-.77-1.86-1.06-2.55-.28-.67-.56-.58-.77-.59h-.66c-.22 0-.57.08-.87.42-.31.34-1.17 1.14-1.17 2.78s1.2 3.23 1.36 3.45c.17.22 2.35 3.59 5.7 5.04.8.34 1.42.55 1.9.7.8.25 1.53.22 2.1.13.64-.1 1.97-.81 2.25-1.59.28-.78.28-1.46.2-1.59-.09-.14-.31-.22-.65-.39z"/>
                </svg>
            </span>
            <?php endif; ?>

        </div><!-- /.pat-line -->
    </div><!-- /.pat-card -->
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

<!-- MODAL DÉPLACER -->
<div class="modal-ov" id="modalDep">
    <div class="modal-box">
        <h3>📆 Déplacer le RDV</h3>
        <input type="hidden" id="depOrd">
        <div style="display:flex;gap:8px;margin-bottom:10px;">
            <button class="btn-ok" onclick="deplacerJ(-1)">◀ -1 jour</button>
            <button class="btn-ok" onclick="deplacerJ(+1)">+1 jour ▶</button>
        </div>
        <input type="date" class="modal-inp" id="depDate">
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

// ── Horloge (identique à dossier.php) ────────────────
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

// ── Filtrer ───────────────────────────────────────────
function filtrerPatients(v) {
    v = v.toLowerCase().trim();
    document.querySelectorAll('.pat-card').forEach(c =>
        c.classList.toggle('hidden', !!v && !c.dataset.nom.includes(v)));
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

// ── Vu ────────────────────────────────────────────────
async function toggleVu(n, estVu) {
    const r = await ajax('toggle_vu', {n_ordon:n, vu: estVu?0:1});
    if (!r.ok) return;
    const card = document.getElementById('card-'+n);
    const btn  = document.getElementById('btnVu-'+n);
    const nom  = document.getElementById('nom-'+n);
    if (!estVu) {
        card.classList.add('vu'); card.classList.remove('absent');
        nom.classList.add('vu');  btn.textContent = '✅ Vu';
    } else {
        card.classList.remove('vu'); nom.classList.remove('vu');
        btn.textContent = '👁 Vu';
    }
    toast(estVu ? 'Statut Vu retiré' : 'Marqué Vu ✅');
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
    } else {
        card.classList.remove('absent'); nom.classList.remove('absent');
        btn.textContent = '❌ Abs';
    }
    toast(estAbs ? 'Statut Absent retiré' : 'Marqué Absent 🔴');
}

// ── Mettre à jour les points colorés des créneaux ────
function majCreneaux() {
    // Recompter depuis le DOM
    const comptage = {};
    document.querySelectorAll('.pat-card:not(.hidden)').forEach(c => {
        const h = c.dataset.heure;
        if (h) comptage[h] = (comptage[h] || 0) + 1;
    });
    // Mettre à jour chaque ligne créneau
    document.querySelectorAll('.cr-item').forEach(item => {
        const heure = item.querySelector('.cr-heure')?.textContent.trim();
        if (!heure) return;
        const nb  = comptage[heure] || 0;
        const dot = item.querySelector('.cr-dot');
        let nbEl  = item.querySelector('.cr-nb');
        // Couleur du point
        if (dot) {
            dot.className = 'cr-dot ' + (nb === 0 ? 'vert' : nb === 1 ? 'jaune' : 'rouge');
        }
        // Chiffre à côté
        if (nb > 0) {
            if (!nbEl) {
                nbEl = document.createElement('span');
                nbEl.className = 'cr-nb';
                item.appendChild(nbEl);
            }
            nbEl.textContent = nb;
        } else {
            if (nbEl) nbEl.remove();
        }
    });
}

// ── Changer heure ─────────────────────────────────────
async function changerHeure(n, heure) {
    if (heure) {
        const cards = [...document.querySelectorAll('.pat-card:not(.hidden)')];
        let count = 0;
        cards.forEach(c => { if (c.dataset.heure === heure && c.id !== 'card-'+n) count++; });
        if (count >= 2) {
            toast('⚠ Créneau ' + heure + ' déjà complet (max 2 patients)', 'error');
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
        majCreneaux(); // ← actualise les points colorés à gauche
        toast('Heure mise à jour ⏰');
    }
}

// ── Observation ───────────────────────────────────────
async function sauvegarderObs(n, obs) {
    const r = await ajax('sauvegarder_obs', {n_ordon:n, observation:obs});
    if (r.ok) toast('Observation enregistrée 📝');
}

// ── Déplacer ──────────────────────────────────────────
function deplacerRdv(n, d) {
    document.getElementById('depOrd').value  = n;
    document.getElementById('depDate').value = d;
    ouvrirModal('modalDep');
}
function deplacerJ(delta) {
    const d = new Date(document.getElementById('depDate').value);
    d.setDate(d.getDate() + delta);
    document.getElementById('depDate').value = d.toISOString().split('T')[0];
}
async function confirmerDeplacement() {
    const n = document.getElementById('depOrd').value;
    const d = document.getElementById('depDate').value;
    if (!d) return;
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
    const liens = [...document.querySelectorAll('.btn-wa-end.has-tel')];
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
