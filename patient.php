<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'backend/db.php';
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
if ($id == 0) {
    header('Location: agenda.php');
    exit;
}

// Récupérer le patient
$stmt = $db->prepare("SELECT * FROM ID WHERE [N°PAT] = ?");
$stmt->execute([$id]);
$patient = $stmt->fetch();

if (!$patient) {
    die("❌ Patient introuvable !");
}

// Dernières ordonnances
$stmt2 = $db->prepare("
    SELECT TOP 5 n_ordon, date_ordon, [DATE REDEZ VOUS], HeureRDV, acte1
    FROM ORD
    WHERE id = ?
    ORDER BY date_ordon DESC
");
$stmt2->execute([$id]);
$ordonnances = $stmt2->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Fiche patient — <?= htmlspecialchars($patient['NOMPRENOM']) ?></title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Arial, sans-serif; background: #f0f4f8; color: #333; }

.header {
    background: #1a4a7a; color: white;
    padding: 12px 20px;
    display: flex; align-items: center; gap: 15px;
}
.header a {
    color: white; text-decoration: none;
    background: #2e6da4; padding: 6px 14px;
    border-radius: 4px; font-size: 14px;
}
.header h1 { font-size: 18px; }

.container { padding: 20px; max-width: 900px; margin: 0 auto; }

.card {
    background: white; border-radius: 8px;
    padding: 20px; margin-bottom: 16px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.card h2 {
    color: #1a4a7a; font-size: 16px;
    margin-bottom: 14px;
    padding-bottom: 8px;
    border-bottom: 2px solid #e0e0e0;
}

.grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 12px;
}
.champ label {
    font-size: 11px; color: #888;
    text-transform: uppercase; font-weight: bold;
    display: block; margin-bottom: 3px;
}
.champ span {
    font-size: 14px; color: #333;
    display: block;
}
.champ span:empty::after { content: '—'; color: #ccc; }

table { width: 100%; border-collapse: collapse; }
thead { background: #1a4a7a; color: white; }
thead th { padding: 8px 12px; text-align: left; font-size: 13px; }
tbody tr { border-bottom: 1px solid #eee; }
tbody tr:hover { background: #f5f9ff; }
tbody td { padding: 8px 12px; font-size: 13px; }

.acte-badge {
    background: #e8f5e9; color: #2e7d32;
    padding: 2px 8px; border-radius: 10px; font-size: 12px;
}
.btn-retour {
    display: inline-block;
    margin-top: 16px;
    background: #1a4a7a; color: white;
    padding: 8px 20px; border-radius: 4px;
    text-decoration: none; font-size: 14px;
}
</style>
</head>
<body>

<div class="header">
    <a href="agenda.php">◀ Agenda</a>
<a href="dossier.php?id=<?= $id ?>" style="background:#27ae60;">🏠 Home</a>
<a href="recherche.php" style="background:#8e44ad;">🔍 Recherche</a>
<h1>🧑‍⚕️ Fiche patient — <?= htmlspecialchars($patient['NOMPRENOM']) ?></h1>
</div>

<div class="container">

    <!-- INFOS PATIENT -->
    <div class="card">
        <h2>👤 Informations personnelles</h2>
        <div class="grid">
            <div class="champ">
                <label>N° Patient</label>
                <span><?= $patient['N°PAT'] ?></span>
            </div>
            <div class="champ">
                <label>Nom complet</label>
                <span><?= htmlspecialchars($patient['NOMPRENOM']) ?></span>
            </div>
            <div class="champ">
                <label>Date de naissance</label>
                <span><?= $patient['DDN'] ? date('d/m/Y', strtotime($patient['DDN'])) : '—' ?></span>
            </div>
            <div class="champ">
                <label>Âge</label>
                <span><?= $patient['AGE'] ? $patient['AGE'] . ' ans' : '—' ?></span>
            </div>
            <div class="champ">
                <label>Sexe</label>
                <span><?= htmlspecialchars($patient['SXE'] ?? '') ?></span>
            </div>
            <div class="champ">
                <label>Téléphone</label>
                <span><?= htmlspecialchars($patient['TEL D'] ?? '') ?></span>
            </div>
            <div class="champ">
                <label>Téléphone bureau</label>
                <span><?= htmlspecialchars($patient['TEL B'] ?? '') ?></span>
            </div>
            <div class="champ">
                <label>Mutuelle</label>
                <span><?= htmlspecialchars($patient['MUTUELLE'] ?? '') ?></span>
            </div>
        </div>
    </div>

    <!-- DERNIÈRES ORDONNANCES -->
    <div class="card">
        <h2>📋 Dernières consultations</h2>
        <?php if (empty($ordonnances)): ?>
            <p style="color:#999;">Aucune consultation enregistrée.</p>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>N° Ord.</th>
                    <th>Date consultation</th>
                    <th>Prochain RDV</th>
                    <th>Heure</th>
                    <th>Actes prévus</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($ordonnances as $o): ?>
                <tr>
                    <td><a href="dossier.php?id=<?= $id ?>&ord=<?= $o['n_ordon'] ?>" style="color:#2e6da4;font-weight:bold;"><?= $o['n_ordon'] ?></a></td>
<td><?= $o['date_ordon'] ? date('d/m/Y', strtotime($o['date_ordon'])) : '—' ?></td>
                    <td><?= $o['date_ordon'] ? date('d/m/Y', strtotime($o['date_ordon'])) : '—' ?></td>
                    <td><?= $o['DATE REDEZ VOUS'] ? date('d/m/Y', strtotime($o['DATE REDEZ VOUS'])) : '—' ?></td>
                    <td><?= htmlspecialchars($o['HeureRDV'] ?? '') ?></td>
                    <td>
                        <?php if ($o['acte1']): ?>
                            <span class="acte-badge"><?= htmlspecialchars($o['acte1']) ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

</div>

</body>
</html>