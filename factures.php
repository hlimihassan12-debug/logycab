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

// Toutes les factures avec totaux
$stmtFact = $db->prepare("
    SELECT f.n_facture, f.date_facture,
           ISNULL(SUM(d.prixU),0)  AS total,
           ISNULL(SUM(d.Versé),0)  AS verse_total,
           ISNULL(SUM(d.dette),0)  AS dette_total
    FROM facture f
    LEFT JOIN detail_acte d ON f.n_facture = d.N_fact
    WHERE f.id = ?
    GROUP BY f.n_facture, f.date_facture
    ORDER BY f.date_facture DESC
");
$stmtFact->execute([$id]);
$factures = $stmtFact->fetchAll();

// Détails actes pour chaque facture
$detailsParFact = [];
if (!empty($factures)) {
    $nFacts = array_column($factures, 'n_facture');
    $placeholders = implode(',', array_fill(0, count($nFacts), '?'));
    $stmtDA = $db->prepare("
        SELECT d.N_fact, d.prixU, d.Versé, d.dette, d.[date-H], a.ACTE AS nom_acte
        FROM detail_acte d
        LEFT JOIN t_acte_simplifiée a ON d.ACTE = a.n_acte
        WHERE d.N_fact IN ($placeholders)
        ORDER BY d.N_fact, d.N_aacte
    ");
    $stmtDA->execute($nFacts);
    foreach ($stmtDA->fetchAll() as $da) {
        $detailsParFact[$da['N_fact']][] = $da;
    }
}

// Totaux globaux
$totalGeneral = array_sum(array_column($factures, 'total'));
$verseGeneral = array_sum(array_column($factures, 'verse_total'));
$detteGeneral = array_sum(array_column($factures, 'dette_total'));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Factures — <?= htmlspecialchars($patient['NOMPRENOM']) ?></title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Arial, sans-serif; background: #f0f4f8; font-size: 13px; }

.header { background: #1a4a7a; color: white; padding: 8px 16px; display: flex; align-items: center; gap: 10px; }
.header h1 { font-size: 15px; flex: 1; }
.btn-header { color: white; text-decoration: none; background: #2e6da4; padding: 5px 12px; border-radius: 4px; font-size: 12px; border: none; cursor: pointer; }

.patient-bar { background: #000; color: #FFD700; padding: 6px 16px; display: flex; gap: 20px; flex-wrap: wrap; font-size: 12px; }
.patient-bar label { font-size: 10px; opacity: 0.8; text-transform: uppercase; display: block; color: #FFD700; }
.patient-bar span  { font-weight: bold; color: #FFD700; }

.container { max-width: 1000px; margin: 16px auto; padding: 0 12px; }

.stats-bar { display: flex; gap: 12px; margin-bottom: 14px; flex-wrap: wrap; }
.stat-card { background: white; border-radius: 6px; padding: 8px 16px; box-shadow: 0 1px 4px rgba(0,0,0,0.1); text-align: center; }
.stat-card .val { font-size: 20px; font-weight: bold; color: #1a4a7a; }
.stat-card .val.green { color: #27ae60; }
.stat-card .val.red   { color: #e74c3c; }
.stat-card .lbl { font-size: 10px; color: #888; text-transform: uppercase; }

.fact-card { background: white; border-radius: 8px; padding: 12px 16px; box-shadow: 0 1px 4px rgba(0,0,0,0.1); margin-bottom: 10px; border-left: 4px solid #27ae60; }
.fact-card.avec-dette { border-left-color: #e74c3c; }

.fact-header { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; flex-wrap: wrap; }
.fact-num  { font-size: 11px; color: #888; }
.fact-date { font-size: 14px; font-weight: bold; color: #1a4a7a; }
.badge-total { background: #e8f8ee; color: #27ae60; padding: 2px 10px; border-radius: 10px; font-size: 12px; font-weight: bold; }
.badge-verse { background: #e8f0fb; color: #2e6da4; padding: 2px 10px; border-radius: 10px; font-size: 12px; font-weight: bold; }
.badge-dette { background: #fde8e8; color: #e74c3c; padding: 2px 10px; border-radius: 10px; font-size: 12px; font-weight: bold; }

.actes-table { width: 100%; border-collapse: collapse; font-size: 11px; margin-top: 6px; }
.actes-table th { background: #f0f4f8; color: #555; padding: 4px 8px; text-align: left; font-size: 10px; text-transform: uppercase; }
.actes-table td { padding: 4px 8px; border-bottom: 1px solid #f0f0f0; }
.actes-table tr:last-child td { border-bottom: none; }

.separateur-annee { text-align: center; margin: 16px 0 10px; font-size: 11px; font-weight: bold; color: #888; letter-spacing: 2px; text-transform: uppercase; position: relative; }
.separateur-annee::before, .separateur-annee::after { content: ''; position: absolute; top: 50%; width: 40%; height: 1px; background: #ddd; }
.separateur-annee::before { left: 0; }
.separateur-annee::after { right: 0; }

.empty { text-align: center; color: #999; padding: 40px; font-size: 14px; }
</style>
</head>
<body>

<div class="header">
    <a href="dossier.php?id=<?= $id ?>" class="btn-header">◀ Retour dossier</a>
    <h1>💰 Factures — <?= htmlspecialchars($patient['NOMPRENOM']) ?></h1>
</div>

<div class="patient-bar">
    <div><label>N°</label><span><?= $id ?></span></div>
    <div><label>Nom</label><span><?= htmlspecialchars($patient['NOMPRENOM']) ?></span></div>
    <div><label>Âge</label><span><?= $age ?> ans</span></div>
    <div><label>DDN</label><span><?= $patient['DDN'] ? date('d/m/Y', strtotime($patient['DDN'])) : '—' ?></span></div>
</div>

<div class="container">

    <!-- Stats globales -->
    <div class="stats-bar">
        <div class="stat-card">
            <div class="val"><?= count($factures) ?></div>
            <div class="lbl">Factures</div>
        </div>
        <div class="stat-card">
            <div class="val"><?= number_format($totalGeneral, 0, ',', ' ') ?> DH</div>
            <div class="lbl">Total général</div>
        </div>
        <div class="stat-card">
            <div class="val green"><?= number_format($verseGeneral, 0, ',', ' ') ?> DH</div>
            <div class="lbl">Total versé</div>
        </div>
        <div class="stat-card">
            <div class="val <?= $detteGeneral > 0 ? 'red' : '' ?>"><?= number_format($detteGeneral, 0, ',', ' ') ?> DH</div>
            <div class="lbl">Total reste</div>
        </div>
    </div>

    <!-- Liste factures -->
    <?php if (empty($factures)): ?>
        <div class="empty">Aucune facture enregistrée pour ce patient.</div>
    <?php else: ?>
    <?php
    $anneeActuelle = null;
    foreach ($factures as $fact):
        $tsF     = strtotime($fact['date_facture'] ?? '');
        $dateFact = ($tsF && $tsF > 86400) ? date('d/m/Y', $tsF) : '—';
        $annee   = ($tsF && $tsF > 86400) ? date('Y', $tsF) : '—';
        $details = $detailsParFact[$fact['n_facture']] ?? [];
        $avecDette = $fact['dette_total'] > 0;

        if ($annee !== $anneeActuelle):
            $anneeActuelle = $annee;
    ?>
        <div class="separateur-annee"><?= $annee ?></div>
    <?php endif; ?>

    <div class="fact-card <?= $avecDette ? 'avec-dette' : '' ?>">
        <div class="fact-header">
            <span class="fact-num">N° <?= $fact['n_facture'] ?></span>
            <span class="fact-date">📅 <?= $dateFact ?></span>
            <span class="badge-total">💰 <?= number_format($fact['total'], 0, ',', ' ') ?> DH</span>
            <span class="badge-verse">✅ <?= number_format($fact['verse_total'], 0, ',', ' ') ?> DH</span>
            <?php if ($avecDette): ?>
            <span class="badge-dette">⚠ Reste <?= number_format($fact['dette_total'], 0, ',', ' ') ?> DH</span>
            <?php endif; ?>
        </div>

        <?php if (!empty($details)): ?>
        <table class="actes-table">
            <thead>
                <tr>
                    <th>Date acte</th>
                    <th>Acte</th>
                    <th style="text-align:right;">Prix</th>
                    <th style="text-align:right;">Versé</th>
                    <th style="text-align:right;">Reste</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($details as $da): ?>
            <tr>
                <td><?php
                    $tsDA = !empty($da['date-H']) ? strtotime($da['date-H']) : false;
                    echo ($tsDA && $tsDA > 86400) ? date('d/m/Y', $tsDA) : '—';
                ?></td>
                <td><?= htmlspecialchars($da['nom_acte'] ?? '—') ?></td>
                <td style="text-align:right;"><?= number_format($da['prixU'], 0, ',', ' ') ?> DH</td>
                <td style="text-align:right;"><?= number_format($da['Versé'], 0, ',', ' ') ?> DH</td>
                <td style="text-align:right;color:<?= $da['dette'] > 0 ? '#e74c3c' : '#27ae60' ?>;font-weight:bold;"><?= number_format($da['dette'], 0, ',', ' ') ?> DH</td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <span style="font-size:11px;color:#aaa;">Aucun détail</span>
        <?php endif; ?>
    </div>

    <?php endforeach; ?>
    <?php endif; ?>

</div>
</body>
</html>