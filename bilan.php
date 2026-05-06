<?php
require_once 'backend/auth.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'backend/db.php';
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
if ($id == 0) { header('Location: recherche.php'); exit; }

// Patient
$stmt = $db->prepare("SELECT * FROM ID WHERE [N°PAT] = ?");
$stmt->execute([$id]);
$patient = $stmt->fetch();

// Actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Nouveau bilan
    if ($action === 'nouveau_bilan') {
        $db->prepare("INSERT INTO LE_BILAN (id, date_bilan) VALUES (?, CAST(GETDATE() AS DATE))")
           ->execute([$id]);
        $nBilan = $db->lastInsertId();

        // Analyses cochées
        $analyses = $_POST['analyses'] ?? [];
        foreach ($analyses as $nAnalyse) {
            $db->prepare("INSERT INTO analyses (N_bilan, bilan, résultat) VALUES (?,?,'')")
               ->execute([$nBilan, (int)$nAnalyse]);
        }
        header("Location: bilan.php?id=$id&bilan=$nBilan&msg=ok");
        exit;
    }

    // Sauvegarder résultats
    if ($action === 'sauver_resultats') {
        $nBilan  = (int)$_POST['n_bilan'];
        $resultats = $_POST['resultat'] ?? [];
        foreach ($resultats as $nAnalyse => $val) {
            $db->prepare("UPDATE analyses SET résultat=? WHERE N_bilan=? AND bilan=?")
               ->execute([$val, $nBilan, (int)$nAnalyse]);
        }
        header("Location: bilan.php?id=$id&bilan=$nBilan&msg=sauve");
        exit;
    }

    // Tout normal
    if ($action === 'tout_normal') {
        $nBilan = (int)$_POST['n_bilan'];
        $db->prepare("UPDATE analyses SET résultat='N' WHERE N_bilan=? AND (résultat IS NULL OR résultat='')")
           ->execute([$nBilan]);
        header("Location: bilan.php?id=$id&bilan=$nBilan");
        exit;
    }
}

// Bilans du patient
$stmtBilans = $db->prepare("SELECT * FROM LE_BILAN WHERE id=? ORDER BY date_bilan DESC");
$stmtBilans->execute([$id]);
$bilans = $stmtBilans->fetchAll();

// Bilan sélectionné
$nBilanSel = (int)($_GET['bilan'] ?? ($bilans ? $bilans[0]['n_bilan'] : 0));

// Analyses du bilan sélectionné
$analyses = [];
if ($nBilanSel) {
    $stmtAn = $db->prepare("
        SELECT a.bilan, a.résultat, c.analyse, c.rubrique
        FROM analyses a
        INNER JOIN C_ANALYSE c ON a.bilan = c.[N°TypeAnalyse]
        WHERE a.N_bilan = ?
        ORDER BY c.rubrique, c.analyse
    ");
    $stmtAn->execute([$nBilanSel]);
    $analyses = $stmtAn->fetchAll();
}

// Catalogue analyses par rubrique
$stmtCat = $db->prepare("SELECT [N°TypeAnalyse], analyse, rubrique FROM C_ANALYSE ORDER BY rubrique, analyse");
$stmtCat->execute();
$catalogue = $stmtCat->fetchAll();
$parRubrique = [];
foreach ($catalogue as $c) {
    $parRubrique[$c['rubrique']][] = $c;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bilans — <?= htmlspecialchars($patient['NOMPRENOM']) ?></title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Arial, sans-serif; background: #f0f4f8; }
.header { background: #1a4a7a; color: white; padding: 10px 20px; display: flex; align-items: center; gap: 15px; }
.header a { color: white; text-decoration: none; background: #2e6da4; padding: 6px 14px; border-radius: 4px; font-size: 13px; }
.header h1 { font-size: 16px; }
.patient-bar { background: #2e6da4; color: white; padding: 8px 20px; font-size: 13px; display: flex; gap: 30px; }
.container { padding: 16px; display: grid; grid-template-columns: 250px 1fr; gap: 16px; }
@media (max-width: 768px) { .container { grid-template-columns: 1fr; } }
.card { background: white; border-radius: 8px; padding: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.card h2 { color: #1a4a7a; font-size: 14px; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 2px solid #e0e0e0; }
.bilan-item { padding: 8px; border-radius: 4px; cursor: pointer; font-size: 13px; margin-bottom: 4px; border: 1px solid #e0e0e0; }
.bilan-item:hover { background: #f0f7ff; }
.bilan-item.actif { background: #1a4a7a; color: white; border-color: #1a4a7a; }
.btn { padding: 7px 14px; border-radius: 4px; border: none; cursor: pointer; font-size: 13px; text-decoration: none; display: inline-block; }
.btn-primary { background: #1a4a7a; color: white; }
.btn-success { background: #27ae60; color: white; }
.btn-warning { background: #f39c12; color: white; }
.btn-sm { padding: 4px 10px; font-size: 12px; }
table { width: 100%; border-collapse: collapse; }
thead { background: #1a4a7a; color: white; }
thead th { padding: 8px 10px; text-align: left; font-size: 13px; }
tbody tr { border-bottom: 1px solid #eee; }
tbody td { padding: 7px 10px; font-size: 13px; }
.resultat-input { width: 120px; padding: 4px 6px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px; }
.normal { color: #27ae60; }
.anormal { color: #e74c3c; font-weight: bold; }
.rubrique-header { background: #e8f4fd; font-weight: bold; font-size: 12px; color: #1a4a7a; padding: 5px 10px; }

/* Modal nouveau bilan */
.modal-bg { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 100; align-items: center; justify-content: center; }
.modal-bg.active { display: flex; }
.modal { background: white; border-radius: 8px; padding: 20px; width: 500px; max-height: 80vh; overflow-y: auto; box-shadow: 0 4px 20px rgba(0,0,0,0.3); }
.modal h3 { color: #1a4a7a; margin-bottom: 12px; }
.rubrique-titre { font-weight: bold; color: #2e6da4; margin: 10px 0 5px; font-size: 13px; }
.check-ligne { display: flex; align-items: center; gap: 8px; padding: 4px 0; font-size: 13px; }
.modal-btns { display: flex; gap: 10px; justify-content: flex-end; margin-top: 16px; }
</style>
</head>
<body>

<div class="header">
    <a href="consultation.php?id=<?= $id ?>">◀ Consultation</a>
    <h1>🧪 Bilans biologiques — <?= htmlspecialchars($patient['NOMPRENOM']) ?></h1>
</div>

<div class="patient-bar">
    <span><strong><?= htmlspecialchars($patient['NOMPRENOM']) ?></strong> — N°<?= $id ?></span>
</div>

<?php if (isset($_GET['msg'])): ?>
<div style="background:#d4edda;color:#155724;padding:10px 20px;font-size:13px;">
    ✅ <?= $_GET['msg'] == 'ok' ? 'Bilan créé !' : 'Résultats sauvegardés !' ?>
</div>
<?php endif; ?>

<div class="container">

    <!-- LISTE BILANS -->
    <div>
        <div class="card">
            <h2>📋 Bilans</h2>
            <button class="btn btn-success" style="width:100%;margin-bottom:10px;" onclick="document.getElementById('modal').classList.add('active')">
                ➕ Nouveau bilan
            </button>
            <?php foreach ($bilans as $b): ?>
            <a href="bilan.php?id=<?= $id ?>&bilan=<?= $b['n_bilan'] ?>" 
               class="bilan-item <?= $b['n_bilan'] == $nBilanSel ? 'actif' : '' ?>" 
               style="display:block;text-decoration:none;color:inherit;">
                📅 <?= $b['date_bilan'] ? date('d/m/Y', strtotime($b['date_bilan'])) : '—' ?>
            </a>
            <?php endforeach; ?>
            <?php if (empty($bilans)): ?>
                <p style="color:#999;font-size:13px;">Aucun bilan</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- DETAIL BILAN -->
    <div>
        <?php if ($nBilanSel && !empty($analyses)): ?>
        <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                <h2 style="margin:0;border:none;padding:0;">🔬 Résultats du bilan</h2>
                <div style="display:flex;gap:8px;">
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="action" value="tout_normal">
                        <input type="hidden" name="n_bilan" value="<?= $nBilanSel ?>">
                        <button type="submit" class="btn btn-warning btn-sm">✅ Tout normal</button>
                    </form>
                </div>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="sauver_resultats">
                <input type="hidden" name="n_bilan" value="<?= $nBilanSel ?>">

                <table>
                    <thead>
                        <tr>
                            <th>Analyse</th>
                            <th>Résultat</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $rubriqueActuelle = '';
                    foreach ($analyses as $a):
                        if ($a['rubrique'] != $rubriqueActuelle):
                            $rubriqueActuelle = $a['rubrique'];
                    ?>
                        <tr><td colspan="3" class="rubrique-header">📁 <?= htmlspecialchars($rubriqueActuelle) ?></td></tr>
                    <?php endif; ?>
                        <tr>
                            <td><?= htmlspecialchars($a['analyse']) ?></td>
                            <td>
                                <input type="text" 
                                       name="resultat[<?= $a['bilan'] ?>]" 
                                       value="<?= htmlspecialchars($a['résultat'] ?? '') ?>"
                                       class="resultat-input"
                                       placeholder="N = Normal">
                            </td>
                            <td>
                                <?php if ($a['résultat'] == 'N'): ?>
                                    <span class="normal">✅ Normal</span>
                                <?php elseif (!empty($a['résultat'])): ?>
                                    <span class="anormal">⚠️ <?= htmlspecialchars($a['résultat']) ?></span>
                                <?php else: ?>
                                    <span style="color:#ccc;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <div style="margin-top:12px;">
                    <button type="submit" class="btn btn-primary">💾 Sauvegarder résultats</button>
                </div>
            </form>
        </div>
        <?php elseif ($nBilanSel): ?>
            <div class="card"><p style="color:#999;">Aucune analyse dans ce bilan.</p></div>
        <?php else: ?>
            <div class="card"><p style="color:#999;">Créez un nouveau bilan pour commencer.</p></div>
        <?php endif; ?>
    </div>

</div>

<!-- MODAL NOUVEAU BILAN -->
<div class="modal-bg" id="modal">
    <div class="modal">
        <h3>➕ Nouveau bilan — choisir les analyses</h3>
        <form method="POST">
            <input type="hidden" name="action" value="nouveau_bilan">
            <?php foreach ($parRubrique as $rubrique => $liste): ?>
            <div class="rubrique-titre">📁 <?= htmlspecialchars($rubrique) ?></div>
            <?php foreach ($liste as $c): ?>
            <div class="check-ligne">
                <input type="checkbox" name="analyses[]" value="<?= $c['N°TypeAnalyse'] ?>" id="an<?= $c['N°TypeAnalyse'] ?>">
                <label for="an<?= $c['N°TypeAnalyse'] ?>" style="font-size:13px;color:#333;text-transform:none;font-weight:normal;">
                    <?= htmlspecialchars($c['analyse']) ?>
                </label>
            </div>
            <?php endforeach; ?>
            <?php endforeach; ?>
            <div class="modal-btns">
                <button type="button" class="btn" style="background:#e0e0e0;" onclick="document.getElementById('modal').classList.remove('active')">Annuler</button>
                <button type="submit" class="btn btn-success">Créer le bilan</button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('modal').addEventListener('click', function(e) {
    if (e.target === this) this.classList.remove('active');
});
</script>
</body>
</html>