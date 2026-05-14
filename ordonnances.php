<?php
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
if ($id == 0) { header('Location: recherche.php'); exit; }

$stmtPat = $db->prepare("SELECT * FROM ID WHERE [N°PAT] = ?");
$stmtPat->execute([$id]);
$patient = $stmtPat->fetch();
if (!$patient) { die("❌ Patient introuvable !"); }

$age = '';
if ($patient['DDN']) {
    $naissance = new DateTime($patient['DDN']);
    $age = $naissance->diff(new DateTime())->y;
}

// Toutes les ordonnances
$stmtOrd = $db->prepare("SELECT * FROM ORD WHERE id=? ORDER BY date_ordon DESC");
$stmtOrd->execute([$id]);
$ordonnances = $stmtOrd->fetchAll();

// Médicaments pour chaque ordonnance
$medicamentsParOrd = [];
if (!empty($ordonnances)) {
    $nOrds = array_column($ordonnances, 'n_ordon');
    $placeholders = implode(',', array_fill(0, count($nOrds), '?'));
    $stmtMeds = $db->prepare("
        SELECT p.N_ord, p.posologie, p.DUREE, pr.PRODUIT
        FROM PROD p
        LEFT JOIN PRODUITS pr ON p.produit = pr.NuméroPRODUIT
        WHERE p.N_ord IN ($placeholders)
        ORDER BY p.N_ord, p.Ordre
    ");
    $stmtMeds->execute($nOrds);
    foreach ($stmtMeds->fetchAll() as $m) {
        $medicamentsParOrd[$m['N_ord']][] = $m;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ordonnances — <?= htmlspecialchars($patient['NOMPRENOM']) ?></title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Arial, sans-serif; background: #f0f4f8; font-size: 13px; }

.header { background: #1a4a7a; color: white; padding: 8px 16px; display: flex; align-items: center; gap: 10px; }
.header h1 { font-size: 15px; flex: 1; }
.btn-header { color: white; text-decoration: none; background: #2e6da4; padding: 5px 12px; border-radius: 4px; font-size: 12px; border: none; cursor: pointer; }
.btn-header.green { background: #27ae60; }

.patient-bar { background: #000; color: #FFD700; padding: 6px 16px; display: flex; gap: 20px; flex-wrap: wrap; font-size: 12px; }
.patient-bar label { font-size: 10px; opacity: 0.8; text-transform: uppercase; display: block; color: #FFD700; }
.patient-bar span  { font-weight: bold; color: #FFD700; }

.container { max-width: 1000px; margin: 16px auto; padding: 0 12px; }

.stats-bar { display: flex; gap: 12px; margin-bottom: 14px; flex-wrap: wrap; }
.stat-card { background: white; border-radius: 6px; padding: 8px 16px; box-shadow: 0 1px 4px rgba(0,0,0,0.1); text-align: center; }
.stat-card .val { font-size: 22px; font-weight: bold; color: #1a4a7a; }
.stat-card .lbl { font-size: 10px; color: #888; text-transform: uppercase; }

.ord-card { background: white; border-radius: 8px; padding: 12px 16px; box-shadow: 0 1px 4px rgba(0,0,0,0.1); margin-bottom: 10px; border-left: 4px solid #1a4a7a; transition: box-shadow 0.2s; }
.ord-card:hover { box-shadow: 0 3px 12px rgba(0,0,0,0.15); }
.ord-card.recent { border-left-color: #27ae60; }

.ord-header { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; flex-wrap: wrap; }
.ord-num { font-size: 11px; color: #888; }
.ord-date { font-size: 14px; font-weight: bold; color: #1a4a7a; }
.ord-acte { background: #1a4a7a; color: white; padding: 2px 10px; border-radius: 10px; font-size: 11px; font-weight: bold; }
.ord-rdv { background: #e8d5f5; color: #8e44ad; padding: 2px 10px; border-radius: 10px; font-size: 11px; font-weight: bold; }
.ord-heure { background: #f0f4f8; color: #555; padding: 2px 8px; border-radius: 10px; font-size: 11px; }
.ord-actions { margin-left: auto; display: flex; gap: 6px; }

.meds-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 4px; margin-top: 6px; }
.med-ligne { background: #f8f9fa; border-radius: 4px; padding: 4px 8px; font-size: 11px; color: #333; display: flex; gap: 6px; align-items: baseline; }
.med-nom { font-weight: bold; color: #1a4a7a; }
.med-poso { color: #555; }
.med-duree { color: #27ae60; font-weight: bold; margin-left: auto; white-space: nowrap; }

.btn-action { border: none; border-radius: 4px; padding: 4px 10px; cursor: pointer; font-size: 11px; font-weight: bold; text-decoration: none; display: inline-block; }
.btn-modifier { background: #e67e22; color: white; }
.btn-modifier:hover { background: #d35400; }
.btn-retour  { background: #2e6da4; color: white; }
.btn-retour:hover { background: #1a4a7a; }

.empty { text-align: center; color: #999; padding: 40px; font-size: 14px; }
.separateur-annee { text-align: center; margin: 16px 0 10px; font-size: 11px; font-weight: bold; color: #888; letter-spacing: 2px; text-transform: uppercase; position: relative; }
.separateur-annee::before, .separateur-annee::after { content: ''; position: absolute; top: 50%; width: 40%; height: 1px; background: #ddd; }
.separateur-annee::before { left: 0; }
.separateur-annee::after { right: 0; }
</style>
</head>
<body>

<div class="header">
    <a href="dossier.php?id=<?= $id ?>" class="btn-header">◀ Retour dossier</a>
    <h1>📋 Ordonnances — <?= htmlspecialchars($patient['NOMPRENOM']) ?></h1>
</div>

<div class="patient-bar">
    <div><label>N°</label><span><?= $id ?></span></div>
    <div><label>Nom</label><span><?= htmlspecialchars($patient['NOMPRENOM']) ?></span></div>
    <div><label>Âge</label><span><?= $age ?> ans</span></div>
    <div><label>DDN</label><span><?= $patient['DDN'] ? date('d/m/Y', strtotime($patient['DDN'])) : '—' ?></span></div>
</div>

<div class="container">

    <!-- Stats -->
    <div class="stats-bar">
        <div class="stat-card">
            <div class="val"><?= count($ordonnances) ?></div>
            <div class="lbl">Ordonnances</div>
        </div>
        <?php if (!empty($ordonnances)): ?>
        <?php $tsFirst = strtotime($ordonnances[count($ordonnances)-1]['date_ordon'] ?? ''); ?>
        <?php $tsLast  = strtotime($ordonnances[0]['date_ordon'] ?? ''); ?>
        <div class="stat-card">
            <div class="val" style="font-size:14px;"><?= ($tsFirst && $tsFirst > 86400) ? date('d/m/Y', $tsFirst) : '—' ?></div>
            <div class="lbl">Première visite</div>
        </div>
        <div class="stat-card">
            <div class="val" style="font-size:14px;"><?= ($tsLast && $tsLast > 86400) ? date('d/m/Y', $tsLast) : '—' ?></div>
            <div class="lbl">Dernière visite</div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Liste ordonnances -->
    <?php if (empty($ordonnances)): ?>
        <div class="empty">Aucune ordonnance enregistrée pour ce patient.</div>
    <?php else: ?>
    <?php
    $anneeActuelle = null;
    foreach ($ordonnances as $idx => $ord):
        $tsOrd   = strtotime($ord['date_ordon'] ?? '');
        $dateOrd = ($tsOrd && $tsOrd > 86400) ? date('d/m/Y', $tsOrd) : '—';
        $annee   = ($tsOrd && $tsOrd > 86400) ? date('Y', $tsOrd) : '—';

        $tsRdv   = !empty($ord['DATE REDEZ VOUS']) ? strtotime($ord['DATE REDEZ VOUS']) : false;
        $dateRdv = ($tsRdv && $tsRdv > 86400) ? date('d/m/Y', $tsRdv) : '—';
        $heure   = trim($ord['HeureRDV'] ?? '');
        $acte    = trim($ord['acte1'] ?? '');
        $meds    = $medicamentsParOrd[$ord['n_ordon']] ?? [];
        $isRecent = ($idx === 0);

        if ($annee !== $anneeActuelle):
            $anneeActuelle = $annee;
    ?>
        <div class="separateur-annee"><?= $annee ?></div>
    <?php endif; ?>

    <div class="ord-card <?= $isRecent ? 'recent' : '' ?>">
        <div class="ord-header">
            <span class="ord-num">N° <?= $ord['n_ordon'] ?></span>
            <span class="ord-date">📅 <?= $dateOrd ?></span>
            <?php if ($acte): ?>
            <span class="ord-acte"><?= htmlspecialchars($acte) ?></span>
            <?php endif; ?>
            <?php if ($dateRdv !== '—'): ?>
            <span class="ord-rdv">📆 RDV <?= $dateRdv ?></span>
            <?php endif; ?>
            <?php if ($heure): ?>
            <span class="ord-heure">⏰ <?= htmlspecialchars($heure) ?></span>
            <?php endif; ?>
            <div class="ord-actions">
                <a href="modifier_ordonnance.php?id=<?= $id ?>&ord=<?= $ord['n_ordon'] ?>" class="btn-action btn-modifier">✏️ Modifier</a>
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
            <span style="font-size:11px;color:#aaa;">Aucun médicament</span>
        <?php endif; ?>
    </div>

    <?php endforeach; ?>
    <?php endif; ?>

</div>
</body>
</html>