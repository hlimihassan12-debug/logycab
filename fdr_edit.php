<?php
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
if ($id == 0) { header('Location: recherche.php'); exit; }

$stmtP = $db->prepare("SELECT NOMPRENOM FROM ID WHERE [N°PAT] = ?");
$stmtP->execute([$id]);
$patient = $stmtP->fetch();

$listeFDR = [
    "L'âge",
    "ATCD IDM famille < 50 ans H < 65 ans F",
    "ATCD AVC < 45 ans",
    "Tabagisme",
    "Diabète > 1.26",
    "HTA",
    "Ldl cholestérol",
    "Triglycérides",
    "Obésité IMC > 50",
    "Surpoids IMC > 25",
    "T de taille > 80 F 94 H",
    "Sédentarité",
    "Syndrome métabolique",
    "Stress et dépression",
    "Les troubles du sommeil",
    "Srass",
    "La consommation de drogues illicites",
    "Pas de facteur de risque",
];

$stmtActuels = $db->prepare("SELECT FDR FROM patient_fdr WHERE id = ?");
$stmtActuels->execute([$id]);
$fdrActuels = $stmtActuels->fetchAll(PDO::FETCH_COLUMN);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $choisis = $_POST['fdr'] ?? [];
    try {
        $db->prepare("DELETE FROM patient_fdr WHERE id = ?")->execute([$id]);
        foreach ($choisis as $fdr) {
            $db->prepare("INSERT INTO patient_fdr (id, FDR) VALUES (?, ?)")->execute([$id, $fdr]);
        }
        header("Location: dossier.php?id=$id");
        exit;
    } catch (Exception $e) {
        die("❌ Erreur : " . $e->getMessage());
    }
}
    
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>FDR</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Arial, sans-serif; background: #f0f4f8; }
.header { background: #1a4a7a; color: white; padding: 10px 20px; display: flex; align-items: center; gap: 15px; }
.header a { color: white; text-decoration: none; background: #2e6da4; padding: 6px 14px; border-radius: 4px; font-size: 13px; }
.header h1 { font-size: 16px; }
.container { padding: 20px; max-width: 500px; margin: 0 auto; }
.card { background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.card h2 { color: #1a4a7a; font-size: 15px; margin-bottom: 16px; padding-bottom: 8px; border-bottom: 2px solid #e0e0e0; }
.fdr-ligne { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
.fdr-ligne input { width: 18px; height: 18px; cursor: pointer; }
.fdr-ligne label { cursor: pointer; flex: 1; }
.fdr-ligne.coche { background: #fff0f0; border-radius: 4px; padding-left: 6px; }
.btn-save { background: #27ae60; color: white; border: none; padding: 12px; border-radius: 4px; cursor: pointer; font-size: 15px; font-weight: bold; margin-top: 16px; width: 100%; }
</style>
</head>
<body>
<div class="header">
    <a href="dossier.php?id=<?= $id ?>">◀ Retour</a>
    <h1>⚠️ Facteurs de risque — <?= htmlspecialchars($patient['NOMPRENOM']) ?></h1>
</div>
<div class="container">
<div class="card">
    <h2>Cochez les facteurs de risque du patient</h2>
    <form method="POST">
        <?php foreach ($listeFDR as $fdr): 
            $coche = in_array($fdr, $fdrActuels);
        ?>
        <div class="fdr-ligne <?= $coche ? 'coche' : '' ?>">
            <input type="checkbox" name="fdr[]" value="<?= htmlspecialchars($fdr) ?>"
                   id="fdr_<?= md5($fdr) ?>" <?= $coche ? 'checked' : '' ?>>
            <label for="fdr_<?= md5($fdr) ?>"><?= htmlspecialchars($fdr) ?></label>
        </div>
        <?php endforeach; ?>
        <button type="submit" class="btn-save">💾 Enregistrer</button>
    </form>
</div>
</div>
</body>
</html>