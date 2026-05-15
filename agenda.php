<?php
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';
$db = getDB();

// ── Date affichée ──────────────────────────────────────────────
// On reçoit ?date=YYYY-MM-DD ou on prend aujourd'hui
$dateParam = $_GET['date'] ?? date('Y-m-d');
try {
    $dateObj = new DateTime($dateParam);
} catch (Exception $e) {
    $dateObj = new DateTime();
}
$dateAff   = $dateObj->format('Y-m-d');
$datePrev  = (clone $dateObj)->modify('-1 day')->format('Y-m-d');
$dateSuiv  = (clone $dateObj)->modify('+1 day')->format('Y-m-d');
$dateLabel = strftime_fr($dateAff); // ex: Lundi 15 mai 2026

// Fonction affichage date en français
function strftime_fr($date) {
    $jours = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
    $mois  = ['','Janvier','Février','Mars','Avril','Mai','Juin',
               'Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
    $ts = strtotime($date);
    return $jours[date('w',$ts)] . ' ' . date('j',$ts) . ' ' . $mois[(int)date('n',$ts)] . ' ' . date('Y',$ts);
}

// ── Limite du jour (T_Config) ──────────────────────────────────
$stmtCfg = $db->prepare("SELECT Valeur FROM T_Config WHERE Cle='NbrMax'");
$stmtCfg->execute();
$nbrMax  = (int)($stmtCfg->fetchColumn() ?: 20);

// ── Patients du jour (Date_Rdv = $dateAff) ─────────────────────
$stmtPat = $db->prepare("
    SELECT o.n_ordon, o.id, o.HeureRDV, o.Vu, o.SansReponse,
           o.Observation, o.acte1,
           p.NOMPRENOM, p.[TEL D] AS tel,
           ISNULL(SUM(d.Versé),0) AS montant_verse
    FROM ORD o
    LEFT JOIN ID p ON o.id = p.[N°PAT]
    LEFT JOIN facture f ON f.id = o.id
        AND CONVERT(date, f.date_facture) = CONVERT(date, o.Date_Rdv)
    LEFT JOIN detail_acte d ON d.N_fact = f.n_facture
    WHERE CONVERT(date, o.Date_Rdv) = ?
    GROUP BY o.n_ordon, o.id, o.HeureRDV, o.Vu, o.SansReponse,
             o.Observation, o.acte1, p.NOMPRENOM, p.[TEL D]
    ORDER BY o.HeureRDV, o.n_ordon
");
$stmtPat->execute([$dateAff]);
$patients = $stmtPat->fetchAll(PDO::FETCH_ASSOC);
$nbPatients = count($patients);

// ── Total versé du jour ────────────────────────────────────────
$totalVerse = array_sum(array_column($patients, 'montant_verse'));

// ── Total versé global (tous patients ce jour, avec ou sans RDV) ─
$stmtGlobal = $db->prepare("
    SELECT ISNULL(SUM(d.Versé),0)
    FROM facture f
    LEFT JOIN detail_acte d ON d.N_fact = f.n_facture
    WHERE CONVERT(date, f.date_facture) = ?
");
$stmtGlobal->execute([$dateAff]);
$totalGlobal = (float)$stmtGlobal->fetchColumn();

// ── Créneaux horaires 9h→16h par demi-heure ────────────────────
$creneaux = [];
for ($h = 9; $h <= 16; $h++) {
    $creneaux[] = sprintf('%02d:00', $h);
    if ($h < 16) $creneaux[] = sprintf('%02d:30', $h);
}

// Compter patients par créneau
$patParCreneau = [];
foreach ($patients as $pat) {
    $heure = trim($pat['HeureRDV'] ?? '');
    // Normaliser l'heure au format HH:MM
    if (preg_match('/^(\d{1,2}):(\d{2})/', $heure, $m)) {
        $key = sprintf('%02d:%02d', $m[1], $m[2]);
        $patParCreneau[$key] = ($patParCreneau[$key] ?? 0) + 1;
    }
}

// ── RDV à venir par période (pour le mini-calendrier) ──────────
function countRdvPeriode($db, $dateDebut, $dateFin) {
    $stmt = $db->prepare("
        SELECT CONVERT(date, Date_Rdv) AS jour, COUNT(*) AS nb
        FROM ORD
        WHERE CONVERT(date, Date_Rdv) BETWEEN ? AND ?
        GROUP BY CONVERT(date, Date_Rdv)
    ");
    $stmt->execute([$dateDebut, $dateFin]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Agenda — <?= htmlspecialchars(strftime_fr($dateAff)) ?></title>
<style>
/* ── Reset & base ── */
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Arial, sans-serif; background: #f0f4f8; font-size: 13px; }

/* ── Header ── */
.header { background: #1a4a7a; color: white; padding: 8px 14px;
          display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.header h1 { font-size: 15px; flex: 1; min-width: 200px; }
.btn-h { color: white; text-decoration: none; border: none; cursor: pointer;
         padding: 5px 12px; border-radius: 4px; font-size: 11px; font-weight: bold; }
.btn-h.blue   { background: #2e6da4; }
.btn-h.green  { background: #27ae60; }
.btn-h.orange { background: #e67e22; }
.btn-h.grey   { background: #666; }
.btn-h:hover  { opacity: 0.85; }

/* ── Barre navigation date ── */
.nav-date { background: #0f3460; color: white; padding: 6px 14px;
            display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.nav-date .date-label { font-size: 15px; font-weight: bold; flex: 1; text-align: center; }
.btn-nav { background: rgba(255,255,255,0.15); color: white; border: none;
           padding: 4px 14px; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: bold; }
.btn-nav:hover { background: rgba(255,255,255,0.3); }
.btn-nav.disabled { opacity: 0.4; cursor: default; }

/* ── Barre stats ── */
.stats-bar { background: white; padding: 8px 14px; display: flex; gap: 10px;
             align-items: center; flex-wrap: wrap; border-bottom: 2px solid #e0e8f0;
             box-shadow: 0 2px 4px rgba(0,0,0,0.06); }
.stat-pill { background: #f0f4f8; border-radius: 20px; padding: 4px 14px;
             font-size: 12px; display: flex; align-items: center; gap: 5px; }
.stat-pill .val { font-weight: bold; font-size: 16px; color: #1a4a7a; }
.stat-pill .lbl { color: #888; font-size: 10px; }
.stat-pill.green .val { color: #27ae60; }
.stat-pill.red   .val { color: #e74c3c; }
.stat-pill.orange .val { color: #e67e22; }

/* ── Recherche ── */
.search-bar { background: white; padding: 6px 14px; border-bottom: 1px solid #e0e8f0; }
.search-input { width: 280px; padding: 5px 10px; border: 1px solid #ccc;
                border-radius: 20px; font-size: 12px; outline: none; }
.search-input:focus { border-color: #2e6da4; }

/* ── Layout principal ── */
.main { display: flex; gap: 0; }

/* ── Colonne créneaux (gauche) ── */
.col-creneaux { width: 120px; min-width: 120px; background: white;
                border-right: 2px solid #e0e8f0; padding: 8px 6px;
                position: sticky; top: 0; max-height: calc(100vh - 180px);
                overflow-y: auto; }
.col-creneaux h3 { font-size: 10px; color: #888; text-transform: uppercase;
                   text-align: center; margin-bottom: 6px; letter-spacing: 1px; }
.creneau-item { display: flex; align-items: center; gap: 5px; margin-bottom: 4px;
                padding: 4px 6px; border-radius: 6px; cursor: pointer;
                border: 1px solid transparent; transition: all 0.2s; }
.creneau-item:hover { border-color: #2e6da4; }
.creneau-heure { font-size: 11px; font-weight: bold; color: #555; min-width: 38px; }
.creneau-dot { width: 14px; height: 14px; border-radius: 50%; flex-shrink: 0; }
.creneau-dot.vert   { background: #27ae60; }
.creneau-dot.jaune  { background: #f39c12; }
.creneau-dot.rouge  { background: #e74c3c; }
.creneau-nb { font-size: 10px; color: #888; }

/* ── Colonne patients (droite) ── */
.col-patients { flex: 1; padding: 10px; overflow-x: auto; }

/* ── Carte patient ── */
.pat-card { background: white; border-radius: 8px; padding: 10px 14px;
            margin-bottom: 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.08);
            border-left: 4px solid #2e6da4; transition: box-shadow 0.2s; }
.pat-card:hover { box-shadow: 0 3px 12px rgba(0,0,0,0.12); }
.pat-card.vu      { border-left-color: #27ae60; background: #f8fff8; }
.pat-card.absent  { border-left-color: #e74c3c; background: #fff8f8; }
.pat-card.hidden  { display: none; } /* recherche */

.pat-header { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 8px; }
.pat-heure  { background: #1a4a7a; color: white; padding: 2px 10px;
              border-radius: 10px; font-size: 12px; font-weight: bold; min-width: 60px; text-align: center; }
.pat-nom    { font-size: 14px; font-weight: bold; color: #1a4a7a; flex: 1; }
.pat-nom.vu     { color: #888; }
.pat-nom.absent { color: #e67e22; }
.pat-num    { font-size: 11px; color: #888; }
.pat-tel    { font-size: 11px; color: #555; }
.pat-acte   { background: #e8f0fb; color: #2e6da4; padding: 2px 8px;
              border-radius: 10px; font-size: 10px; font-weight: bold; }

/* ── Actions patient ── */
.pat-actions { display: flex; gap: 5px; flex-wrap: wrap; align-items: center; }
.btn-p { border: none; border-radius: 4px; padding: 4px 10px; cursor: pointer;
         font-size: 11px; font-weight: bold; }
.btn-vu     { background: #27ae60; color: white; }
.btn-absent { background: #e74c3c; color: white; }
.btn-wa     { background: #25D366; color: white; }
.btn-dep    { background: #8e44ad; color: white; }
.btn-sup    { background: #c0392b; color: white; }
.btn-eff    { background: #95a5a6; color: white; }
.btn-p:hover { opacity: 0.85; }

/* ── Ligne d'infos patient ── */
.pat-row2 { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-top: 6px; }
.obs-input { flex: 1; min-width: 150px; padding: 3px 8px; border: 1px solid #ddd;
             border-radius: 4px; font-size: 11px; }
.creneau-select { padding: 3px 6px; border: 1px solid #ddd; border-radius: 4px;
                  font-size: 11px; color: #555; }
.montant-input { width: 80px; padding: 3px 8px; border: 1px solid #ddd;
                 border-radius: 4px; font-size: 11px; text-align: right; }
.montant-label { font-size: 10px; color: #888; }

/* ── Bas de page ── */
.footer-bar { background: #1a4a7a; color: white; padding: 8px 14px;
              display: flex; gap: 20px; align-items: center; flex-wrap: wrap;
              position: sticky; bottom: 0; }
.footer-stat { text-align: center; }
.footer-stat .fval { font-size: 16px; font-weight: bold; }
.footer-stat .flbl { font-size: 10px; opacity: 0.8; text-transform: uppercase; }
.footer-stat.green .fval { color: #2ecc71; }
.footer-stat.orange .fval { color: #f39c12; }
.btn-detail-global { background: rgba(255,255,255,0.15); border: none; color: white;
                     padding: 4px 12px; border-radius: 4px; cursor: pointer; font-size: 11px; }
.btn-detail-global:hover { background: rgba(255,255,255,0.3); }

/* ── Empty state ── */
.empty { text-align: center; padding: 60px 20px; color: #aaa; }
.empty .ico { font-size: 48px; margin-bottom: 10px; }

/* ── Toast notifications ── */
.toast { position: fixed; top: 20px; right: 20px; background: #333; color: white;
         padding: 10px 18px; border-radius: 6px; font-size: 12px; z-index: 9999;
         display: none; box-shadow: 0 4px 12px rgba(0,0,0,0.3); }
.toast.show { display: block; animation: fadeIn 0.3s; }
.toast.success { background: #27ae60; }
.toast.error   { background: #e74c3c; }
@keyframes fadeIn { from { opacity:0; transform: translateY(-10px); } to { opacity:1; transform: translateY(0); } }

/* ── Modal simple ── */
.modal-overlay { display: none; position: fixed; top:0; left:0; width:100%; height:100%;
                 background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
.modal-overlay.show { display: flex; }
.modal-box { background: white; border-radius: 10px; padding: 20px; min-width: 320px;
             box-shadow: 0 10px 40px rgba(0,0,0,0.3); }
.modal-box h3 { color: #1a4a7a; margin-bottom: 14px; font-size: 14px; }
.modal-box input { width: 100%; padding: 7px 10px; border: 1px solid #ccc;
                   border-radius: 4px; font-size: 13px; margin-bottom: 12px; }
.modal-btns { display: flex; gap: 8px; justify-content: flex-end; }
.modal-btns button { padding: 6px 16px; border: none; border-radius: 4px;
                     cursor: pointer; font-size: 12px; font-weight: bold; }
.modal-btns .btn-ok  { background: #1a4a7a; color: white; }
.modal-btns .btn-ann { background: #ddd; color: #555; }
</style>
</head>
<body>

<!-- ── HEADER ── -->
<div class="header">
    <a href="recherche.php" class="btn-h blue">◀ Accueil</a>
    <h1>📅 Agenda</h1>
    <button class="btn-h green" onclick="ouvrirAjoutPatient()">➕ Ajouter patient</button>
    <button class="btn-h orange" onclick="modifierLimite()">⚙️ Limite (<?= $nbrMax ?>)</button>
    <button class="btn-h grey"  onclick="voirSemaine()">📊 Vue semaine</button>
    <button class="btn-h green" onclick="envoyerWaListe()">📲 WhatsApp liste</button>
</div>

<!-- ── NAVIGATION DATE ── -->
<div class="nav-date">
    <a href="agenda.php?date=<?= $datePrev ?>" class="btn-nav">◀</a>
    <div class="date-label">📅 <?= htmlspecialchars(strftime_fr($dateAff)) ?></div>
    <a href="agenda.php?date=<?= $dateSuiv ?>" class="btn-nav">▶</a>
    <!-- Raccourcis périodes -->
    <div style="margin-left:auto;display:flex;gap:5px;flex-wrap:wrap;">
        <?php
        $periodes = [
            '1S' => '+7 days', '1M' => '+1 month',
            '3M' => '+3 months', '6M' => '+6 months'
        ];
        foreach ($periodes as $label => $offset):
            $dateCible = (clone $dateObj)->modify($offset)->format('Y-m-d');
        ?>
        <a href="agenda.php?date=<?= $dateCible ?>" class="btn-nav" style="font-size:11px;padding:3px 10px;"><?= $label ?></a>
        <?php endforeach; ?>
    </div>
</div>

<!-- ── STATS + RECHERCHE ── -->
<div class="stats-bar">
    <div class="stat-pill">
        <span class="val"><?= $nbPatients ?></span>
        <span class="lbl">/ <?= $nbrMax ?> RDV</span>
    </div>
    <div class="stat-pill <?= $nbPatients >= $nbrMax ? 'red' : ($nbPatients >= $nbrMax*0.7 ? 'orange' : 'green') ?>">
        <span class="val"><?= $nbrMax - $nbPatients ?></span>
        <span class="lbl">places libres</span>
    </div>
    <div class="stat-pill green">
        <span class="val"><?= number_format($totalVerse, 0, ',', ' ') ?> DH</span>
        <span class="lbl">versé (RDV)</span>
    </div>
    <!-- Recherche -->
    <div style="margin-left:auto;">
        <input type="text" class="search-input" id="searchPat"
               placeholder="🔍 Rechercher un patient..."
               oninput="filtrerPatients(this.value)">
    </div>
</div>

<!-- ── LAYOUT PRINCIPAL ── -->
<div class="main">

    <!-- ── CRÉNEAUX (gauche) ── -->
    <div class="col-creneaux">
        <h3>Créneaux</h3>
        <?php foreach ($creneaux as $cr):
            $nb = $patParCreneau[$cr] ?? 0;
            $classe = $nb === 0 ? 'vert' : ($nb === 1 ? 'jaune' : 'rouge');
        ?>
        <div class="creneau-item" onclick="scrollToCreneau('<?= $cr ?>')">
            <span class="creneau-heure"><?= $cr ?></span>
            <span class="creneau-dot <?= $classe ?>"></span>
            <?php if ($nb > 0): ?>
            <span class="creneau-nb"><?= $nb ?></span>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ── LISTE PATIENTS (droite) ── -->
    <div class="col-patients">

    <?php if (empty($patients)): ?>
        <div class="empty">
            <div class="ico">📭</div>
            <div>Aucun rendez-vous ce jour</div>
        </div>
    <?php else: ?>

    <?php foreach ($patients as $i => $pat):
        $estVu     = (bool)$pat['Vu'];
        $estAbsent = (bool)$pat['SansReponse'];
        $cardClass = $estVu ? 'vu' : ($estAbsent ? 'absent' : '');
        $nomClass  = $estVu ? 'vu' : ($estAbsent ? 'absent' : '');
        $heure     = trim($pat['HeureRDV'] ?? '—');
        $obs       = htmlspecialchars($pat['Observation'] ?? '');
        $acte      = trim($pat['acte1'] ?? '');
        $telRaw    = preg_replace('/\D/', '', $pat['tel'] ?? '');
        // Format WhatsApp Maroc : 06... → 2126...
        $telWa = $telRaw ? ('212' . ltrim($telRaw, '0')) : '';
        $msgWa = urlencode('Bonjour, rappel de votre RDV le ' . strftime_fr($dateAff) . ' à ' . $heure . '. Cabinet Dr Hassan.');
    ?>
    <div class="pat-card <?= $cardClass ?>" id="card-<?= $pat['n_ordon'] ?>"
         data-nom="<?= strtolower(htmlspecialchars($pat['NOMPRENOM'] ?? '')) ?>"
         data-heure="<?= htmlspecialchars($heure) ?>">

        <!-- En-tête patient -->
        <div class="pat-header">
            <span class="pat-heure" id="heure-<?= $pat['n_ordon'] ?>"><?= htmlspecialchars($heure) ?></span>
            <span class="pat-nom <?= $nomClass ?>" id="nom-<?= $pat['n_ordon'] ?>">
                <?= htmlspecialchars($pat['NOMPRENOM'] ?? '—') ?>
            </span>
            <span class="pat-num">N° <?= $pat['id'] ?></span>
            <?php if ($pat['tel']): ?>
            <span class="pat-tel">📞 <?= htmlspecialchars($pat['tel']) ?></span>
            <?php endif; ?>
            <?php if ($acte): ?>
            <span class="pat-acte">🔬 <?= htmlspecialchars($acte) ?></span>
            <?php endif; ?>
            <!-- Lien dossier -->
            <a href="dossier.php?id=<?= $pat['id'] ?>" class="btn-p"
               style="background:#1a4a7a;color:white;text-decoration:none;">📋 Dossier</a>
        </div>

        <!-- Actions -->
        <div class="pat-actions">
            <!-- Vu / Non vu -->
            <button class="btn-p btn-vu" id="btnVu-<?= $pat['n_ordon'] ?>"
                    onclick="toggleVu(<?= $pat['n_ordon'] ?>, <?= $estVu?1:0 ?>)">
                <?= $estVu ? '✅ Vu' : '👁 Marquer Vu' ?>
            </button>
            <!-- Absent -->
            <button class="btn-p btn-absent" id="btnAbs-<?= $pat['n_ordon'] ?>"
                    onclick="toggleAbsent(<?= $pat['n_ordon'] ?>, <?= $estAbsent?1:0 ?>)">
                <?= $estAbsent ? '🔴 Absent' : '❌ Absent' ?>
            </button>
            <!-- Déplacer -->
            <button class="btn-p btn-dep" onclick="deplacerRdv(<?= $pat['n_ordon'] ?>, '<?= $dateAff ?>')">
                📆 Déplacer
            </button>
            <!-- WhatsApp individuel -->
            <?php if ($telWa): ?>
            <a href="https://wa.me/<?= $telWa ?>?text=<?= $msgWa ?>" target="_blank"
               class="btn-p btn-wa" style="text-decoration:none;">📲 WA</a>
            <?php endif; ?>
            <!-- Effacer heure -->
            <button class="btn-p btn-eff" onclick="effacerHeure(<?= $pat['n_ordon'] ?>)">🕐 Effacer heure</button>
            <!-- Supprimer -->
            <button class="btn-p btn-sup" onclick="supprimerRdv(<?= $pat['n_ordon'] ?>, '<?= htmlspecialchars($pat['NOMPRENOM'] ?? '') ?>')">🗑 Supprimer</button>
        </div>

        <!-- Ligne 2 : créneau, observation, montant -->
        <div class="pat-row2">
            <!-- Zone créneau horaire -->
            <select class="creneau-select" onchange="changerHeure(<?= $pat['n_ordon'] ?>, this.value)">
                <option value="">— Heure —</option>
                <?php foreach ($creneaux as $cr): ?>
                <option value="<?= $cr ?>" <?= $heure === $cr ? 'selected' : '' ?>><?= $cr ?></option>
                <?php endforeach; ?>
            </select>
            <!-- Observation -->
            <input type="text" class="obs-input" placeholder="Observation..."
                   value="<?= $obs ?>"
                   onblur="sauvegarderObs(<?= $pat['n_ordon'] ?>, this.value)">
            <!-- Montant payé -->
            <span class="montant-label">💰</span>
            <input type="number" class="montant-input"
                   placeholder="0"
                   value="<?= $pat['montant_verse'] > 0 ? (int)$pat['montant_verse'] : '' ?>"
                   readonly
                   style="background:#f8f9fa;color:<?= $pat['montant_verse']>0?'#27ae60':'#aaa' ?>;">
            <span class="montant-label">DH versé</span>
        </div>

    </div><!-- /pat-card -->
    <?php endforeach; ?>

    <?php endif; ?>
    </div><!-- /col-patients -->

</div><!-- /main -->

<!-- ── BAS DE PAGE ── -->
<div class="footer-bar">
    <div class="footer-stat green">
        <div class="fval"><?= number_format($totalVerse, 0, ',', ' ') ?> DH</div>
        <div class="flbl">Versé (patients RDV)</div>
    </div>
    <div class="footer-stat orange">
        <div class="fval"><?= number_format($totalGlobal, 0, ',', ' ') ?> DH</div>
        <div class="flbl">Versé global (tous)</div>
    </div>
    <button class="btn-detail-global" onclick="voirDetailGlobal()">📊 Détail global</button>
    <div style="margin-left:auto;font-size:11px;opacity:0.7;">
        <?= $nbPatients ?> patient(s) — <?= htmlspecialchars(strftime_fr($dateAff)) ?>
    </div>
</div>

<!-- ── TOAST ── -->
<div class="toast" id="toast"></div>

<!-- ── MODAL AJOUT PATIENT ── -->
<div class="modal-overlay" id="modalAjout">
    <div class="modal-box">
        <h3>➕ Ajouter un patient au RDV du jour</h3>
        <input type="text" id="addSearch" placeholder="Rechercher patient par nom ou N°..."
               oninput="rechercherPatientAjout(this.value)">
        <div id="addResults" style="max-height:200px;overflow-y:auto;margin-bottom:12px;"></div>
        <div class="modal-btns">
            <button class="btn-ann" onclick="fermerModal('modalAjout')">Annuler</button>
        </div>
    </div>
</div>

<!-- ── MODAL DÉPLACER ── -->
<div class="modal-overlay" id="modalDeplacer">
    <div class="modal-box">
        <h3>📆 Déplacer le RDV</h3>
        <input type="hidden" id="depOrd">
        <div style="display:flex;gap:8px;margin-bottom:10px;flex-wrap:wrap;">
            <button class="btn-p btn-dep" onclick="deplacerJ(-1)">◀ -1 jour</button>
            <button class="btn-p btn-dep" onclick="deplacerJ(+1)">+1 jour ▶</button>
        </div>
        <input type="date" id="depDate" style="width:100%;padding:6px;border:1px solid #ccc;border-radius:4px;margin-bottom:10px;">
        <div class="modal-btns">
            <button class="btn-ann" onclick="fermerModal('modalDeplacer')">Annuler</button>
            <button class="btn-ok" onclick="confirmerDeplacement()">✔ Confirmer</button>
        </div>
    </div>
</div>

<!-- ── MODAL VUE SEMAINE ── -->
<div class="modal-overlay" id="modalSemaine">
    <div class="modal-box" style="min-width:400px;">
        <h3>📊 RDV de la semaine</h3>
        <div id="semaineListe" style="margin-bottom:12px;"></div>
        <div class="modal-btns">
            <button class="btn-ann" onclick="fermerModal('modalSemaine')">Fermer</button>
        </div>
    </div>
</div>

<script>
const dateAff  = '<?= $dateAff ?>';
const nbrMax   = <?= $nbrMax ?>;

// ── Toast ─────────────────────────────────────────────────────
function toast(msg, type='success') {
    const el = document.getElementById('toast');
    el.textContent = msg;
    el.className = 'toast show ' + type;
    setTimeout(() => el.className = 'toast', 2800);
}

// ── Scroll vers créneau ───────────────────────────────────────
function scrollToCreneau(heure) {
    const cards = document.querySelectorAll('.pat-card');
    for (const c of cards) {
        if (c.dataset.heure === heure) {
            c.scrollIntoView({ behavior: 'smooth', block: 'center' });
            c.style.outline = '2px solid #2e6da4';
            setTimeout(() => c.style.outline = '', 1500);
            return;
        }
    }
}

// ── Filtrer patients (recherche) ──────────────────────────────
function filtrerPatients(val) {
    const v = val.toLowerCase().trim();
    document.querySelectorAll('.pat-card').forEach(c => {
        c.classList.toggle('hidden', v && !c.dataset.nom.includes(v));
    });
}

// ── Ajax générique ────────────────────────────────────────────
async function ajax(action, data) {
    const res = await fetch('ajax_agenda.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action, ...data })
    });
    return res.json();
}

// ── Marquer Vu ────────────────────────────────────────────────
async function toggleVu(nOrd, estVu) {
    const r = await ajax('toggle_vu', { n_ordon: nOrd, vu: estVu ? 0 : 1 });
    if (r.ok) {
        const card = document.getElementById('card-' + nOrd);
        const btn  = document.getElementById('btnVu-' + nOrd);
        const nom  = document.getElementById('nom-' + nOrd);
        if (!estVu) {
            card.classList.add('vu'); card.classList.remove('absent');
            nom.classList.add('vu'); nom.classList.remove('absent');
            btn.textContent = '✅ Vu';
        } else {
            card.classList.remove('vu');
            nom.classList.remove('vu');
            btn.textContent = '👁 Marquer Vu';
        }
        toast(!estVu ? 'Patient marqué Vu ✅' : 'Statut Vu retiré');
    }
}

// ── Marquer Absent ────────────────────────────────────────────
async function toggleAbsent(nOrd, estAbsent) {
    const r = await ajax('toggle_absent', { n_ordon: nOrd, absent: estAbsent ? 0 : 1 });
    if (r.ok) {
        const card = document.getElementById('card-' + nOrd);
        const btn  = document.getElementById('btnAbs-' + nOrd);
        const nom  = document.getElementById('nom-' + nOrd);
        if (!estAbsent) {
            card.classList.add('absent'); card.classList.remove('vu');
            nom.classList.add('absent'); nom.classList.remove('vu');
            btn.textContent = '🔴 Absent';
        } else {
            card.classList.remove('absent');
            nom.classList.remove('absent');
            btn.textContent = '❌ Absent';
        }
        toast(!estAbsent ? 'Patient marqué Absent 🔴' : 'Statut Absent retiré');
    }
}

// ── Changer créneau horaire ───────────────────────────────────
async function changerHeure(nOrd, heure) {
    const r = await ajax('changer_heure', { n_ordon: nOrd, heure });
    if (r.ok) {
        document.getElementById('heure-' + nOrd).textContent = heure || '—';
        const card = document.getElementById('card-' + nOrd);
        card.dataset.heure = heure;
        toast('Heure mise à jour ⏰');
    }
}

// ── Effacer heure ─────────────────────────────────────────────
async function effacerHeure(nOrd) {
    const r = await ajax('changer_heure', { n_ordon: nOrd, heure: '' });
    if (r.ok) {
        document.getElementById('heure-' + nOrd).textContent = '—';
        // Remettre select à vide
        const sel = document.querySelector(`#card-${nOrd} .creneau-select`);
        if (sel) sel.value = '';
        toast('Heure effacée');
    }
}

// ── Sauvegarder observation ───────────────────────────────────
async function sauvegarderObs(nOrd, obs) {
    const r = await ajax('sauvegarder_obs', { n_ordon: nOrd, observation: obs });
    if (r.ok) toast('Observation enregistrée 📝');
}

// ── Déplacer RDV ──────────────────────────────────────────────
let depOrdCourant = null;
let depDateCourante = null;

function deplacerRdv(nOrd, dateCour) {
    depOrdCourant  = nOrd;
    depDateCourante = dateCour;
    document.getElementById('depOrd').value  = nOrd;
    document.getElementById('depDate').value = dateCour;
    ouvrirModal('modalDeplacer');
}

function deplacerJ(delta) {
    const d = new Date(document.getElementById('depDate').value);
    d.setDate(d.getDate() + delta);
    document.getElementById('depDate').value = d.toISOString().split('T')[0];
}

async function confirmerDeplacement() {
    const nOrd    = document.getElementById('depOrd').value;
    const newDate = document.getElementById('depDate').value;
    if (!newDate) return;
    const r = await ajax('deplacer_rdv', { n_ordon: nOrd, nouvelle_date: newDate });
    if (r.ok) {
        toast('RDV déplacé au ' + newDate + ' 📅');
        fermerModal('modalDeplacer');
        setTimeout(() => location.reload(), 800);
    } else {
        toast('Erreur lors du déplacement', 'error');
    }
}

// ── Supprimer RDV ─────────────────────────────────────────────
async function supprimerRdv(nOrd, nom) {
    if (!confirm('Supprimer le RDV de ' + nom + ' ?')) return;
    const r = await ajax('supprimer_rdv', { n_ordon: nOrd });
    if (r.ok) {
        document.getElementById('card-' + nOrd).remove();
        toast('RDV supprimé 🗑');
    } else {
        toast('Erreur suppression', 'error');
    }
}

// ── Modifier limite ───────────────────────────────────────────
async function modifierLimite() {
    const val = prompt('Nouvelle limite de patients par jour :', nbrMax);
    if (!val || isNaN(val)) return;
    const r = await ajax('modifier_limite', { nbrmax: parseInt(val) });
    if (r.ok) {
        toast('Limite mise à jour : ' + val + ' patients');
        setTimeout(() => location.reload(), 800);
    }
}

// ── Vue semaine ───────────────────────────────────────────────
async function voirSemaine() {
    const r = await ajax('rdv_semaine', { date: dateAff });
    if (r.ok) {
        const div = document.getElementById('semaineListe');
        div.innerHTML = r.jours.map(j => {
            const cl = j.nb < 5 ? '#27ae60' : j.nb < 16 ? '#f39c12' : '#e74c3c';
            return `<div style="display:flex;align-items:center;gap:10px;padding:5px 0;border-bottom:1px solid #eee;">
                <a href="agenda.php?date=${j.date}" style="color:#1a4a7a;font-weight:bold;text-decoration:none;min-width:180px;">${j.label}</a>
                <span style="background:${cl};color:white;padding:2px 10px;border-radius:10px;font-size:12px;">${j.nb} RDV</span>
            </div>`;
        }).join('');
        ouvrirModal('modalSemaine');
    }
}

// ── WhatsApp liste ────────────────────────────────────────────
function envoyerWaListe() {
    const liens = [...document.querySelectorAll('.btn-wa')];
    if (liens.length === 0) { toast('Aucun numéro disponible', 'error'); return; }
    let i = 0;
    function next() {
        if (i >= liens.length) { toast('WhatsApp envoyés : ' + liens.length); return; }
        window.open(liens[i].href, '_blank');
        i++; setTimeout(next, 1500);
    }
    next();
}

// ── Ajouter patient ───────────────────────────────────────────
function ouvrirAjoutPatient() {
    document.getElementById('addSearch').value = '';
    document.getElementById('addResults').innerHTML = '';
    ouvrirModal('modalAjout');
}

let searchTimer;
function rechercherPatientAjout(val) {
    clearTimeout(searchTimer);
    if (val.length < 2) { document.getElementById('addResults').innerHTML = ''; return; }
    searchTimer = setTimeout(async () => {
        const r = await ajax('rechercher_patient', { q: val });
        if (r.ok) {
            document.getElementById('addResults').innerHTML = r.patients.map(p =>
                `<div onclick="ajouterPatient(${p.id}, '${p.nom.replace(/'/g,"\\'")}', '${dateAff}')"
                      style="padding:6px 10px;cursor:pointer;border-bottom:1px solid #eee;font-size:12px;"
                      onmouseover="this.style.background='#f0f4f8'" onmouseout="this.style.background=''">
                    <strong>${p.nom}</strong> <span style="color:#888;">N° ${p.id}</span>
                 </div>`
            ).join('') || '<div style="padding:10px;color:#aaa;font-size:12px;">Aucun résultat</div>';
        }
    }, 300);
}

async function ajouterPatient(idPat, nom, date) {
    const r = await ajax('ajouter_rdv', { id: idPat, date_rdv: date });
    if (r.ok) {
        toast('RDV ajouté pour ' + nom + ' ✅');
        fermerModal('modalAjout');
        setTimeout(() => location.reload(), 800);
    } else {
        toast('Erreur ajout RDV', 'error');
    }
}

// ── Détail versé global ───────────────────────────────────────
async function voirDetailGlobal() {
    const r = await ajax('detail_global', { date: dateAff });
    if (r.ok && r.patients) {
        alert('DÉTAIL VERSÉ GLOBAL — ' + dateAff + '\n\n' +
            r.patients.map(p => p.nom + ' : ' + p.montant + ' DH').join('\n') +
            '\n\nTOTAL : ' + r.total + ' DH');
    }
}

// ── Modales ───────────────────────────────────────────────────
function ouvrirModal(id) { document.getElementById(id).classList.add('show'); }
function fermerModal(id) { document.getElementById(id).classList.remove('show'); }
document.querySelectorAll('.modal-overlay').forEach(m => {
    m.addEventListener('click', e => { if (e.target === m) m.classList.remove('show'); });
});
</script>

</body>
</html>