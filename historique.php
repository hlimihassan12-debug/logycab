<?php
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
if ($id == 0) { header('Location: recherche.php'); exit; }

// ── Patient ────────────────────────────────────────────────────
$stmtPat = $db->prepare("SELECT * FROM ID WHERE [N°PAT] = ?");
$stmtPat->execute([$id]);
$patient = $stmtPat->fetch();
if (!$patient) { die("❌ Patient introuvable !"); }

$age = '';
if ($patient['DDN']) {
    $naissance = new DateTime($patient['DDN']);
    $age = $naissance->diff(new DateTime())->y;
}

// ── Toutes les ordonnances (DESC) ─────────────────────────────
$stmtOrd = $db->prepare("SELECT * FROM ORD WHERE id=? ORDER BY date_ordon DESC");
$stmtOrd->execute([$id]);
$ordonnances = $stmtOrd->fetchAll(PDO::FETCH_ASSOC);

// ── Médicaments par ordonnance ─────────────────────────────────
$medicamentsParOrd = [];
if (!empty($ordonnances)) {
    $nOrds = array_column($ordonnances, 'n_ordon');
    $ph = implode(',', array_fill(0, count($nOrds), '?'));
    $stmtMeds = $db->prepare("
        SELECT p.N_ord, p.posologie, p.DUREE, pr.PRODUIT
        FROM PROD p
        LEFT JOIN PRODUITS pr ON p.produit = pr.NuméroPRODUIT
        WHERE p.N_ord IN ($ph)
        ORDER BY p.N_ord, p.Ordre
    ");
    $stmtMeds->execute($nOrds);
    foreach ($stmtMeds->fetchAll() as $m) {
        $medicamentsParOrd[$m['N_ord']][] = $m;
    }
}

// ── RDV prochain = date_ordon de l'ordonnance suivante ─────────
$rdvProchain = [];
$ordsChron = array_reverse($ordonnances);
for ($i = 0; $i < count($ordsChron); $i++) {
    $nOrd = $ordsChron[$i]['n_ordon'];
    $rdvProchain[$nOrd] = isset($ordsChron[$i+1]) ? $ordsChron[$i+1]['date_ordon'] : null;
}

// ── Toutes les factures (DESC) ────────────────────────────────
$stmtFact = $db->prepare("
    SELECT f.n_facture, f.date_facture,
           ISNULL(SUM(d.prixU),0) AS total,
           ISNULL(SUM(d.Versé),0) AS verse_total,
           ISNULL(SUM(d.dette),0) AS dette_total
    FROM facture f
    LEFT JOIN detail_acte d ON f.n_facture = d.N_fact
    WHERE f.id = ?
    GROUP BY f.n_facture, f.date_facture
    ORDER BY f.date_facture DESC
");
$stmtFact->execute([$id]);
$factures = $stmtFact->fetchAll(PDO::FETCH_ASSOC);

// ── Détails actes par facture ──────────────────────────────────
$detailsParFact = [];
if (!empty($factures)) {
    $nFacts = array_column($factures, 'n_facture');
    $ph2 = implode(',', array_fill(0, count($nFacts), '?'));
    $stmtDA = $db->prepare("
        SELECT d.N_fact, d.prixU, d.Versé, d.dette, d.[date-H], a.ACTE AS nom_acte
        FROM detail_acte d
        LEFT JOIN t_acte_simplifiée a ON d.ACTE = a.n_acte
        WHERE d.N_fact IN ($ph2)
        ORDER BY d.N_fact, d.N_aacte
    ");
    $stmtDA->execute($nFacts);
    foreach ($stmtDA->fetchAll() as $da) {
        $detailsParFact[$da['N_fact']][] = $da;
    }
}

// ── Totaux globaux ─────────────────────────────────────────────
$totalGeneral = array_sum(array_column($factures, 'total'));
$verseGeneral = array_sum(array_column($factures, 'verse_total'));
$detteGeneral = array_sum(array_column($factures, 'dette_total'));

// ── Index de navigation ────────────────────────────────────────
$nbOrd  = count($ordonnances);
$nbFact = count($factures);
$iOrd   = max(0, min((int)($_GET['iOrd']  ?? 0), $nbOrd  > 0 ? $nbOrd-1  : 0));
$iSynch = (int)($_GET['synch'] ?? 1);

// ── En mode synchronisé : trouver l'index facture correspondant à la date de l'ordonnance
// En mode indépendant  : utiliser iFact passé en GET
$ordCour = $ordonnances[$iOrd] ?? null;

$iFact = 0;
if ($iSynch && $ordCour) {
    // Chercher la facture dont la date correspond à l'ordonnance courante
    $tsOrdC    = strtotime($ordCour['date_ordon'] ?? '');
    $dateMatch = ($tsOrdC && $tsOrdC > 86400) ? date('Y-m-d', $tsOrdC) : '';
    $found = false;
    foreach ($factures as $fi => $f) {
        $tsF = strtotime($f['date_facture'] ?? '');
        if ($tsF && $tsF > 86400 && date('Y-m-d', $tsF) === $dateMatch) {
            $iFact = $fi;
            $found = true;
            break;
        }
    }
    // Si pas trouvée, garder iFact=0 mais signaler
    if (!$found) {
        // On garde l'iFact du GET si disponible, sinon 0
        $iFact = max(0, min((int)($_GET['iFact'] ?? 0), $nbFact > 0 ? $nbFact-1 : 0));
    }
} else {
    $iFact = max(0, min((int)($_GET['iFact'] ?? 0), $nbFact > 0 ? $nbFact-1 : 0));
}

$factCour  = $factures[$iFact] ?? null;

// Indicateur : la facture affichée correspond-elle à la date de l'ordonnance ?
$factSynchee = false;
if ($iSynch && $ordCour && $factCour) {
    $tsOrdC = strtotime($ordCour['date_ordon'] ?? '');
    $tsFact = strtotime($factCour['date_facture'] ?? '');
    if ($tsOrdC && $tsFact && date('Y-m-d',$tsOrdC) === date('Y-m-d',$tsFact)) {
        $factSynchee = true;
    }
}

// ── Fonction URL ───────────────────────────────────────────────
function navUrl($id, $iOrd, $iFact, $synch) {
    return "historique.php?id={$id}&iOrd={$iOrd}&iFact={$iFact}&synch={$synch}";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Historique — <?= htmlspecialchars($patient['NOMPRENOM']) ?></title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Arial, sans-serif; background: #f0f4f8; font-size: 13px; }

.header { background: #1a4a7a; color: white; padding: 8px 16px;
          display: flex; align-items: center; gap: 10px; }
.header h1 { font-size: 15px; flex: 1; }
.btn-header { color: white; text-decoration: none; background: #2e6da4;
              padding: 5px 12px; border-radius: 4px; font-size: 12px;
              border: none; cursor: pointer; }

.patient-bar { background: #000; color: #FFD700; padding: 6px 16px;
               display: flex; gap: 20px; flex-wrap: wrap; font-size: 12px; }
.patient-bar label { font-size: 10px; opacity: 0.8; text-transform: uppercase;
                     display: block; color: #FFD700; }
.patient-bar span  { font-weight: bold; color: #FFD700; }

/* ── Layout 70/30 ── */
.main-layout { display: flex; gap: 12px; padding: 12px; align-items: flex-start; }
.col-ord  { flex: 0 0 69%; max-width: 69%; }
.col-fact { flex: 0 0 29%; max-width: 29%; }

.col-title { font-size: 13px; font-weight: bold; color: #1a4a7a;
             margin-bottom: 8px; padding-bottom: 4px;
             border-bottom: 2px solid #1a4a7a;
             display: flex; align-items: center; gap: 6px; }

/* ── Stats ── */
.stats-bar { display: flex; gap: 8px; margin-bottom: 10px; flex-wrap: wrap; }
.stat-card { background: white; border-radius: 6px; padding: 5px 12px;
             box-shadow: 0 1px 4px rgba(0,0,0,0.1); text-align: center; }
.stat-card .val { font-size: 17px; font-weight: bold; color: #1a4a7a; }
.stat-card .val.green { color: #27ae60; }
.stat-card .val.red   { color: #e74c3c; }
.stat-card .lbl { font-size: 10px; color: #888; text-transform: uppercase; }

/* ── Bouton synchronisation ── */
.btn-synch { border: none; border-radius: 4px; padding: 5px 12px;
             cursor: pointer; font-size: 11px; font-weight: bold;
             text-decoration: none; display: inline-block; }
.btn-synch.on  { background: #27ae60; color: white; }
.btn-synch.off { background: #ddd; color: #555; }

/* ── Barre navigation ── */
.nav-bar { display: flex; align-items: center; gap: 8px;
           background: white; border-radius: 8px; padding: 6px 12px;
           box-shadow: 0 1px 4px rgba(0,0,0,0.1); margin-bottom: 10px; }
.nav-btn { background: #1a4a7a; color: white; border: none; border-radius: 4px;
           padding: 5px 16px; cursor: pointer; font-size: 15px; font-weight: bold;
           text-decoration: none; display: inline-block; line-height: 1; }
.nav-btn:hover { background: #2e6da4; }
.nav-btn.disabled { background: #ccc; cursor: default; pointer-events: none; }
.nav-counter { flex: 1; text-align: center; font-size: 12px; font-weight: bold; color: #555; }
.nav-annee { font-size: 11px; color: #888; font-weight: normal; }

/* ── Indicateur synch sur la facture ── */
.synch-indicator {
    font-size: 10px; text-align: center; margin-bottom: 6px;
    padding: 3px 8px; border-radius: 4px; display: inline-block;
}
.synch-indicator.ok  { background: #e8f8ee; color: #27ae60; }
.synch-indicator.off { background: #fff3cd; color: #856404; }

/* ── Carte ordonnance ── */
.ord-card { background: white; border-radius: 8px; padding: 12px 16px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.1); border-left: 4px solid #1a4a7a; }
.ord-card.recent { border-left-color: #27ae60; }
.ord-header { display: flex; align-items: center; gap: 8px;
              margin-bottom: 8px; flex-wrap: wrap; }
.ord-num  { font-size: 11px; color: #888; }
.ord-date { font-size: 14px; font-weight: bold; color: #1a4a7a; }
.ord-acte { background: #1a4a7a; color: white; padding: 2px 10px;
            border-radius: 10px; font-size: 11px; font-weight: bold; }
.ord-actions { margin-left: auto; }

.meds-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px,1fr));
             gap: 4px; margin-top: 6px; }
.med-ligne { background: #f8f9fa; border-radius: 4px; padding: 4px 8px;
             font-size: 11px; display: flex; gap: 6px; align-items: baseline; }
.med-nom   { font-weight: bold; color: #1a4a7a; }
.med-poso  { color: #555; }
.med-duree { color: #27ae60; font-weight: bold; margin-left: auto; white-space: nowrap; }

/* ── Pied ordonnance RDV ── */
.ord-footer { margin-top: 10px; padding-top: 8px; border-top: 1px dashed #ddd;
              display: flex; gap: 10px; flex-wrap: wrap; }
.rdv-badge { display: flex; align-items: center; gap: 5px; border-radius: 6px;
             padding: 4px 12px; font-size: 11px; background: #f0f4f8; }
.rdv-badge .rdv-label { color: #888; font-size: 10px; text-transform: uppercase; }
.rdv-badge .rdv-val   { font-weight: bold; color: #1a4a7a; }
.rdv-badge.prochain   { background: #e8f8ee; }
.rdv-badge.prochain .rdv-val { color: #27ae60; }
.rdv-badge.vide       { background: #f8f9fa; }
.rdv-badge.vide .rdv-val { color: #bbb; font-style: italic; }

/* ── Bouton modifier ── */
.btn-action   { border: none; border-radius: 4px; padding: 4px 10px; cursor: pointer;
                font-size: 11px; font-weight: bold; text-decoration: none; display: inline-block; }
.btn-modifier { background: #e67e22; color: white; }
.btn-modifier:hover { background: #d35400; }

/* ── Carte facture ── */
.fact-card { background: white; border-radius: 8px; padding: 12px 14px;
             box-shadow: 0 1px 4px rgba(0,0,0,0.1); border-left: 4px solid #27ae60; }
.fact-card.avec-dette { border-left-color: #e74c3c; }
.fact-header { display: flex; align-items: center; gap: 8px;
               margin-bottom: 8px; flex-wrap: wrap; }
.fact-num  { font-size: 11px; color: #888; }
.fact-date { font-size: 13px; font-weight: bold; color: #1a4a7a; }

.badge { padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: bold; }
.badge-total { background: #e8f8ee; color: #27ae60; }
.badge-verse { background: #e8f0fb; color: #2e6da4; }
.badge-dette { background: #fde8e8; color: #e74c3c; }

.actes-table { width: 100%; border-collapse: collapse; font-size: 11px; margin-top: 6px; }
.actes-table th { background: #f0f4f8; color: #555; padding: 4px 6px;
                  text-align: left; font-size: 10px; text-transform: uppercase; }
.actes-table td { padding: 4px 6px; border-bottom: 1px solid #f0f0f0; }
.actes-table tr:last-child td { border-bottom: none; }

.empty { text-align: center; color: #999; padding: 40px; font-size: 13px; }
</style>
</head>
<body>

<!-- Header -->
<div class="header">
    <a href="dossier.php?id=<?= $id ?>" class="btn-header">◀ Dossier</a>
    <h1>📋 Historique — <?= htmlspecialchars($patient['NOMPRENOM']) ?></h1>
</div>

<!-- Barre patient -->
<div class="patient-bar">
    <div><label>N°</label><span><?= $id ?></span></div>
    <div><label>Nom</label><span><?= htmlspecialchars($patient['NOMPRENOM']) ?></span></div>
    <div><label>Âge</label><span><?= $age ?> ans</span></div>
    <div><label>DDN</label><span><?= $patient['DDN'] ? date('d/m/Y', strtotime($patient['DDN'])) : '—' ?></span></div>
</div>

<div class="main-layout">

    <!-- ══════════════════════════════════
         ORDONNANCES — 70%
    ══════════════════════════════════ -->
    <div class="col-ord">

        <div class="col-title">📋 Ordonnances</div>

        <!-- Stats + bouton synch -->
        <div class="stats-bar">
            <div class="stat-card">
                <div class="val"><?= $nbOrd ?></div>
                <div class="lbl">Total</div>
            </div>
            <?php if ($nbOrd > 0):
                $tsFirst = strtotime($ordonnances[$nbOrd-1]['date_ordon'] ?? '');
                $tsLast  = strtotime($ordonnances[0]['date_ordon'] ?? '');
            ?>
            <div class="stat-card">
                <div class="val" style="font-size:13px;"><?= ($tsFirst && $tsFirst>86400) ? date('d/m/Y',$tsFirst) : '—' ?></div>
                <div class="lbl">1ère visite</div>
            </div>
            <div class="stat-card">
                <div class="val" style="font-size:13px;"><?= ($tsLast && $tsLast>86400) ? date('d/m/Y',$tsLast) : '—' ?></div>
                <div class="lbl">Dernière visite</div>
            </div>
            <?php endif; ?>
            <div style="margin-left:auto;display:flex;align-items:center;">
                <?php if ($iSynch): ?>
                <a href="<?= navUrl($id,$iOrd,$iFact,0) ?>" class="btn-synch on" title="Cliquer pour désynchroniser">🔗 Synchronisé</a>
                <?php else: ?>
                <a href="<?= navUrl($id,$iOrd,$iFact,1) ?>" class="btn-synch off" title="Cliquer pour synchroniser">🔓 Indépendant</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($nbOrd === 0): ?>
            <div class="empty">Aucune ordonnance enregistrée.</div>
        <?php else: ?>

        <!-- Navigation ordonnances -->
        <div class="nav-bar">
            <?php
                $iPrev = $iOrd + 1; // ◀ plus ancienne
                $iNext = $iOrd - 1; // ▶ plus récente
                $tsN   = strtotime($ordCour['date_ordon'] ?? '');
            ?>
            <a href="<?= navUrl($id,$iPrev,$iFact,$iSynch) ?>" class="nav-btn <?= $iPrev>=$nbOrd?'disabled':'' ?>">◀</a>
            <div class="nav-counter">
                Visite <?= $nbOrd - $iOrd ?> / <?= $nbOrd ?>
                <?php if ($tsN && $tsN>86400): ?>
                <br><span class="nav-annee"><?= date('Y',$tsN) ?></span>
                <?php endif; ?>
            </div>
            <a href="<?= navUrl($id,$iNext,$iFact,$iSynch) ?>" class="nav-btn <?= $iNext<0?'disabled':'' ?>">▶</a>
        </div>

        <!-- Carte ordonnance courante -->
        <?php if ($ordCour):
            $tsOrd   = strtotime($ordCour['date_ordon'] ?? '');
            $dateOrd = ($tsOrd && $tsOrd>86400) ? date('d/m/Y',$tsOrd) : '—';
            $acte    = trim($ordCour['acte1'] ?? '');
            $meds    = $medicamentsParOrd[$ordCour['n_ordon']] ?? [];

            $tsRdvPrev   = !empty($ordCour['DATE REDEZ VOUS']) ? strtotime($ordCour['DATE REDEZ VOUS']) : false;
            $dateRdvPrev = ($tsRdvPrev && $tsRdvPrev>86400) ? date('d/m/Y',$tsRdvPrev) : null;
            $heure       = trim($ordCour['HeureRDV'] ?? '');

            $tsVsuiv   = !empty($rdvProchain[$ordCour['n_ordon']]) ? strtotime($rdvProchain[$ordCour['n_ordon']]) : false;
            $dateVsuiv = ($tsVsuiv && $tsVsuiv>86400) ? date('d/m/Y',$tsVsuiv) : null;
        ?>
        <div class="ord-card <?= $iOrd===0?'recent':'' ?>">
            <div class="ord-header">
                <span class="ord-num">N° <?= $ordCour['n_ordon'] ?></span>
                <span class="ord-date">📅 <?= $dateOrd ?></span>
                <?php if ($acte): ?>
                <span class="ord-acte"><?= htmlspecialchars($acte) ?></span>
                <?php endif; ?>
                <div class="ord-actions">
                    <a href="modifier_ordonnance.php?id=<?= $id ?>&ord=<?= $ordCour['n_ordon'] ?>" class="btn-action btn-modifier">✏️ Modifier</a>
                </div>
            </div>

            <?php if (!empty($meds)): ?>
            <div class="meds-grid">
                <?php foreach ($meds as $m): ?>
                <div class="med-ligne">
                    <span class="med-nom"><?= htmlspecialchars($m['PRODUIT'] ?? '—') ?></span>
                    <span class="med-poso"><?= htmlspecialchars($m['posologie'] ?? '') ?></span>
                    <span class="med-duree"><?= htmlspecialchars($m['DUREE'] ?? '') ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
                <span style="font-size:11px;color:#aaa;">Aucun médicament enregistré</span>
            <?php endif; ?>

            <!-- RDV prévu + Visite suivante -->
            <div class="ord-footer">
                <?php if ($dateRdvPrev): ?>
                <div class="rdv-badge">
                    📆 <span class="rdv-label">RDV prévu</span>&nbsp;
                    <span class="rdv-val"><?= $dateRdvPrev ?></span>
                    <?php if ($heure): ?>&nbsp;<span style="color:#888;font-size:10px;">⏰ <?= htmlspecialchars($heure) ?></span><?php endif; ?>
                </div>
                <?php else: ?>
                <div class="rdv-badge vide">
                    📆 <span class="rdv-label">RDV prévu</span>&nbsp;<span class="rdv-val">—</span>
                </div>
                <?php endif; ?>

                <?php if ($dateVsuiv): ?>
                <div class="rdv-badge prochain">
                    ✅ <span class="rdv-label">Visite suivante</span>&nbsp;
                    <span class="rdv-val"><?= $dateVsuiv ?></span>
                </div>
                <?php elseif ($iOrd===0): ?>
                <div class="rdv-badge prochain">
                    🔵 <span class="rdv-label">Visite suivante</span>&nbsp;
                    <span class="rdv-val">Dernière visite</span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php endif; // nbOrd > 0 ?>

    </div><!-- /col-ord -->


    <!-- ══════════════════════════════════
         FACTURES — 30%
    ══════════════════════════════════ -->
    <div class="col-fact">

        <div class="col-title">💰 Factures</div>

        <!-- Stats globales factures -->
        <div class="stats-bar" style="flex-wrap:wrap;gap:6px;">
            <div class="stat-card">
                <div class="val"><?= $nbFact ?></div>
                <div class="lbl">Total</div>
            </div>
            <div class="stat-card">
                <div class="val" style="font-size:12px;"><?= number_format($totalGeneral,0,',',' ') ?> DH</div>
                <div class="lbl">Général</div>
            </div>
            <div class="stat-card">
                <div class="val green" style="font-size:12px;"><?= number_format($verseGeneral,0,',',' ') ?> DH</div>
                <div class="lbl">Versé</div>
            </div>
            <div class="stat-card">
                <div class="val <?= $detteGeneral>0?'red':'' ?>" style="font-size:12px;"><?= number_format($detteGeneral,0,',',' ') ?> DH</div>
                <div class="lbl">Reste</div>
            </div>
        </div>

        <?php if ($nbFact === 0): ?>
            <div class="empty">Aucune facture enregistrée.</div>
        <?php else: ?>

        <!-- Navigation factures — TOUJOURS VISIBLE -->
        <div class="nav-bar">
            <?php
                // En mode synch : la navigation facture change iFact mais PAS iOrd
                // On passe synch=0 pour que le clic sur ◀▶ facture ne re-synchronise pas
                $iFPrev = $iFact + 1;
                $iFNext = $iFact - 1;
                $tsNF   = strtotime($factCour['date_facture'] ?? '');
            ?>
            <a href="<?= navUrl($id,$iOrd,$iFPrev, $iSynch===1 ? 0 : 0) ?>" class="nav-btn <?= $iFPrev>=$nbFact?'disabled':'' ?>">◀</a>
            <div class="nav-counter">
                Facture <?= $nbFact - $iFact ?> / <?= $nbFact ?>
                <?php if ($tsNF && $tsNF>86400): ?>
                <br><span class="nav-annee"><?= date('Y',$tsNF) ?></span>
                <?php endif; ?>
            </div>
            <a href="<?= navUrl($id,$iOrd,$iFNext, 0) ?>" class="nav-btn <?= $iFNext<0?'disabled':'' ?>">▶</a>
        </div>

        <!-- Indicateur : facture synchronisée ou non avec l'ordonnance -->
        <?php if ($iSynch): ?>
        <div style="text-align:center;margin-bottom:8px;">
            <?php if ($factSynchee): ?>
            <span class="synch-indicator ok">✅ Même date que l'ordonnance</span>
            <?php else: ?>
            <span class="synch-indicator off">⚠ Date différente de l'ordonnance</span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Carte facture courante -->
        <?php if ($factCour):
            $tsF      = strtotime($factCour['date_facture'] ?? '');
            $dateFact = ($tsF && $tsF>86400) ? date('d/m/Y',$tsF) : '—';
            $details  = $detailsParFact[$factCour['n_facture']] ?? [];
            $avecDette = $factCour['dette_total'] > 0;
        ?>
        <div class="fact-card <?= $avecDette?'avec-dette':'' ?>">
            <div class="fact-header">
                <span class="fact-num">N° <?= $factCour['n_facture'] ?></span>
                <span class="fact-date">📅 <?= $dateFact ?></span>
            </div>
            <div style="display:flex;gap:5px;flex-wrap:wrap;margin-bottom:8px;">
                <span class="badge badge-total">💰 <?= number_format($factCour['total'],0,',',' ') ?> DH</span>
                <span class="badge badge-verse">✅ <?= number_format($factCour['verse_total'],0,',',' ') ?> DH</span>
                <?php if ($avecDette): ?>
                <span class="badge badge-dette">⚠ <?= number_format($factCour['dette_total'],0,',',' ') ?> DH</span>
                <?php endif; ?>
            </div>

            <?php if (!empty($details)): ?>
            <table class="actes-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Acte</th>
                        <th style="text-align:right;">Prix</th>
                        <th style="text-align:right;">Versé</th>
                        <th style="text-align:right;">Reste</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($details as $da):
                    $tsDA   = !empty($da['date-H']) ? strtotime($da['date-H']) : false;
                    $dateDA = ($tsDA && $tsDA>86400) ? date('d/m/Y',$tsDA) : '—';
                ?>
                <tr>
                    <td><?= $dateDA ?></td>
                    <td><?= htmlspecialchars($da['nom_acte'] ?? '—') ?></td>
                    <td style="text-align:right;"><?= number_format($da['prixU'],0,',',' ') ?> DH</td>
                    <td style="text-align:right;"><?= number_format($da['Versé'],0,',',' ') ?> DH</td>
                    <td style="text-align:right;font-weight:bold;color:<?= $da['dette']>0?'#e74c3c':'#27ae60' ?>;">
                        <?= number_format($da['dette'],0,',',' ') ?> DH
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <span style="font-size:11px;color:#aaa;">Aucun détail</span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php endif; // nbFact > 0 ?>

    </div><!-- /col-fact -->

</div><!-- /main-layout -->

</body>
</html>