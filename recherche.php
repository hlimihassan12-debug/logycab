<?php
require_once 'backend/db.php';
$db = getDB();

$q = trim($_GET['q'] ?? '');
$action = trim($_GET['action'] ?? '');
$patients = [];

if (strlen($q) >= 2) {
    if (is_numeric($q)) {
        $stmt = $db->prepare("SELECT TOP 20 [N°PAT], NOMPRENOM, [TEL D], MUTUELLE FROM ID WHERE [N°PAT] = ? ORDER BY NOMPRENOM");
        $stmt->execute([(int)$q]);
    } else {
        $stmt = $db->prepare("SELECT TOP 20 [N°PAT], NOMPRENOM, [TEL D], MUTUELLE FROM ID WHERE NOMPRENOM LIKE ? ORDER BY NOMPRENOM");
        $stmt->execute(['%' . $q . '%']);
    }
    $patients = $stmt->fetchAll();
}

// Nombre total de patients (calculé, plus jamais codé en dur)
$nbPatients = (int)$db->query("SELECT COUNT(*) FROM ID")->fetchColumn();

// Où envoyer une fois le patient choisi, selon le bouton cliqué depuis l'accueil.
// "comptabilite" n'a pas encore de page dédiée -> on retombe sur le dossier en attendant.
$destinations = [
    'nouveau_medicament'  => 'dossier.php?id=',
    'chercher_ordonnance' => 'ordonnances.php?id=',
    'donner_bilan'        => 'biologie.php?id=',
    'saisir_bilan'        => 'biologie.php?id=',
    'cmlm'                => 'print_cmlm.php?id=',
    'rapport'             => 'print_rapport.php?id=',
    'autres_rapports'     => 'dossier.php?id=',
    'factures'            => 'dossier.php?id=',
    'comptabilite'        => 'dossier.php?id=',
];
$destination = $destinations[$action] ?? 'dossier.php?id=';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Recherche patient — Logycab</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Arial, sans-serif; background: #f0f4f8; }
.header { background: #1a4a7a; color: white; padding: 12px 20px; display: flex; align-items: center; gap: 15px; }
.header a { color: white; text-decoration: none; background: #2e6da4; padding: 6px 14px; border-radius: 4px; font-size: 14px; }
.header h1 { font-size: 18px; }
.container { padding: 20px; max-width: 900px; margin: 0 auto; }
.search-box { background: white; border-radius: 8px; padding: 20px; margin-bottom: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.search-box h2 { color: #1a4a7a; margin-bottom: 12px; font-size: 16px; }
.search-row { display: flex; gap: 10px; }
.search-row input { flex: 1; padding: 10px 14px; border: 2px solid #2e6da4; border-radius: 4px; font-size: 15px; }
.search-row button { background: #1a4a7a; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-size: 15px; }
.search-row button:hover { background: #2e6da4; }
table { width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
thead { background: #1a4a7a; color: white; }
thead th { padding: 10px 12px; text-align: left; font-size: 13px; }
tbody tr { border-bottom: 1px solid #eee; cursor: pointer; }
tbody tr:hover { background: #f0f7ff; }
tbody td { padding: 10px 12px; font-size: 13px; }
.nb { color: #888; font-size: 13px; margin-bottom: 10px; }
a.lien-patient { color: #1a4a7a; text-decoration: none; font-weight: bold; }
</style>
</head>
<body>
<script src="home.js"></script>
<div class="header">
    <a href="index.php" style="background:#c0392b;">🏠 Accueil</a>
    <a href="#" onclick="goHome();return false;" style="background:#27ae60;">🏠 Dossier</a>
    <a href="agenda.php">📅 Agenda</a>
    <a href="planning.php" style="background:#2e6da4;">📊 Planning</a>
    <a href="grille_semaine.php" style="background:#2e6da4;">📋 Grille</a>
    <span style="background:#888;opacity:0.7;cursor:default;">🧪 Biologie</span>
    <a href="jours_feries.php" style="background:#8e44ad;">📅 Fériés</a>
    <a href="logout.php" style="background:#e74c3c;">🚪 Déco</a>
    <h1>🔍 Recherche patient<?= $action ? ' — '.htmlspecialchars(str_replace('_',' ',$action)) : '' ?></h1>
</div>
<div class="container">
    <div class="search-box">
        <h2>Rechercher parmi <?= number_format($nbPatients, 0, ',', ' ') ?> patients</h2>
        <form method="GET">
            <?php if ($action): ?><input type="hidden" name="action" value="<?= htmlspecialchars($action) ?>"><?php endif; ?>
            <div class="search-row">
                <input type="text" name="q" value="<?= htmlspecialchars($q) ?>"
                       placeholder="Tapez un nom ou un N° patient..." autofocus>
                <button type="submit">🔍 Rechercher</button>
            </div>
        </form>
    </div>
    <?php if (strlen($q) >= 2): ?>
        <p class="nb"><?= count($patients) ?> résultat(s) pour "<?= htmlspecialchars($q) ?>"</p>
        <?php if (!empty($patients)): ?>
        <table>
            <thead>
                <tr>
                    <th>N°</th>
                    <th>Nom complet</th>
                    <th>Téléphone</th>
                    <th>Mutuelle</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($patients as $p): ?>
                <tr onclick="window.location='<?= $destination ?><?= $p['N°PAT'] ?>'">
                    <td><?= $p['N°PAT'] ?></td>
                    <td><a class="lien-patient" href="<?= $destination ?><?= $p['N°PAT'] ?>"><?= htmlspecialchars($p['NOMPRENOM']) ?></a></td>
                    <td><?= htmlspecialchars($p['TEL D'] ?? '') ?></td>
                    <td><?= htmlspecialchars($p['MUTUELLE'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <p style="color:#999;text-align:center;padding:30px;">Aucun patient trouvé pour "<?= htmlspecialchars($q) ?>"</p>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>