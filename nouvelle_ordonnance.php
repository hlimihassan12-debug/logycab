<?php
require_once __DIR__ . '/backend/auth.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'backend/db.php';
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
if ($id == 0) { header('Location: recherche.php'); exit; }

$stmt = $db->prepare("SELECT * FROM ID WHERE [N°PAT] = ?");
$stmt->execute([$id]);
$patient = $stmt->fetch();

$stmtMed = $db->prepare("SELECT NuméroPRODUIT, PRODUIT FROM PRODUITS ORDER BY PRODUIT");
$stmtMed->execute();
$catalogue = $stmtMed->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['produit'])) {
    try {
        $db->prepare("INSERT INTO ORD (id, date_ordon, DateSaisie) VALUES (?, CAST(GETDATE() AS DATE), GETDATE())")->execute([$id]);
        $nNewOrd = $db->lastInsertId();

        $produits   = $_POST['produit'] ?? [];
        $posologies = $_POST['posologie'] ?? [];
        $durees     = $_POST['duree'] ?? [];

        foreach ($produits as $i => $prod) {
            if (empty($prod)) continue;
            $db->prepare("INSERT INTO PROD (N_ord, produit, posologie, DUREE, Ordre) VALUES (?,?,?,?,?)")
               ->execute([$nNewOrd, (int)$prod, $posologies[$i] ?? '', $durees[$i] ?? '', $i + 1]);
        }

        $delai = (int)($_POST['delai_mois'] ?? 3);
        $db->prepare("UPDATE ORD SET [DATE REDEZ VOUS]=DATEADD(month,?,CAST(GETDATE() AS DATE)), Date_Rdv=DATEADD(month,?,CAST(GETDATE() AS DATE)), mois_rdv=? WHERE n_ordon=?")
           ->execute([$delai, $delai, $delai, $nNewOrd]);

        header("Location: consultation.php?id=$id&msg=ordonnance");
        exit;
    } catch (Exception $e) {
        $erreur = $e->getMessage();
    }
}

$nbLignes = max(1, (int)($_GET['lignes'] ?? 1));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Nouvelle ordonnance</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Arial, sans-serif; background: #f0f4f8; }
.header { background: #1a4a7a; color: white; padding: 10px 20px; display: flex; align-items: center; gap: 15px; }
.header a { color: white; text-decoration: none; background: #2e6da4; padding: 6px 14px; border-radius: 4px; font-size: 13px; }
.header h1 { font-size: 16px; }
.patient-bar { background: #2e6da4; color: white; padding: 8px 20px; font-size: 13px; display: flex; gap: 30px; }
.container { padding: 20px; max-width: 900px; margin: 0 auto; }
.card { background: white; border-radius: 8px; padding: 20px; margin-bottom: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.card h2 { color: #1a4a7a; font-size: 15px; margin-bottom: 14px; padding-bottom: 8px; border-bottom: 2px solid #e0e0e0; }
.ligne-med { display: grid; grid-template-columns: 3fr 2fr 1fr; gap: 8px; margin-bottom: 8px; }
.ligne-med select, .ligne-med input { padding: 7px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px; width: 100%; }
.delai-box { display: flex; gap: 10px; flex-wrap: wrap; }
.delai-radio { display: none; }
.delai-label { padding: 8px 16px; border: 2px solid #2e6da4; border-radius: 4px; cursor: pointer; font-size: 13px; color: #2e6da4; }
.delai-radio:checked + .delai-label { background: #1a4a7a; color: white; border-color: #1a4a7a; }
.btn-save { background: #27ae60; color: white; border: none; padding: 12px 30px; border-radius: 4px; cursor: pointer; font-size: 15px; font-weight: bold; }
.btn-plus { background: #2e6da4; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 13px; text-decoration: none; display: inline-block; margin-top: 8px; }
label { font-size: 11px; color: #888; text-transform: uppercase; font-weight: bold; display: block; margin-bottom: 3px; }
</style>
</head>
<body>

<div class="header">
    <a href="consultation.php?id=<?= $id ?>">◀ Retour</a>
    <h1>📋 Nouvelle ordonnance — <?= htmlspecialchars($patient['NOMPRENOM']) ?></h1>
</div>

<div class="patient-bar">
    <span><strong><?= htmlspecialchars($patient['NOMPRENOM']) ?></strong> — N°<?= $id ?> — <?= date('d/m/Y') ?></span>
</div>

<div class="container">

<?php if (isset($erreur)): ?>
<div style="background:#ffe0e0;padding:10px;border-radius:4px;color:#c0392b;margin-bottom:12px;">❌ <?= htmlspecialchars($erreur) ?></div>
<?php endif; ?>

<form method="POST">

<div class="card">
    <h2>💊 Médicaments</h2>
    <div style="display:grid;grid-template-columns:3fr 2fr 1fr;gap:8px;margin-bottom:6px;">
        <label>Médicament</label>
        <label>Posologie</label>
        <label>Durée</label>
    </div>
    <?php for ($i = 0; $i < $nbLignes; $i++): ?>
    <div class="ligne-med">
        <select name="produit[]">
            <option value="">-- Choisir --</option>
            <?php foreach ($catalogue as $c): ?>
                <option value="<?= $c['NuméroPRODUIT'] ?>"><?= htmlspecialchars($c['PRODUIT']) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="text" name="posologie[]" placeholder="1 cp 1 fois/jour">
        <input type="text" name="duree[]" placeholder="3 mois">
    </div>
    <?php endfor; ?>
    <a href="?id=<?= $id ?>&lignes=<?= $nbLignes + 1 ?>" class="btn-plus">➕ Ajouter ligne</a>
<?php if ($nbLignes > 1): ?>
<a href="?id=<?= $id ?>&lignes=<?= $nbLignes - 1 ?>" style="background:#e74c3c;color:white;border:none;padding:8px 16px;border-radius:4px;cursor:pointer;font-size:13px;text-decoration:none;display:inline-block;margin-top:8px;margin-left:8px;">➖ Supprimer ligne</a>
<?php endif; ?>
</div>

<div class="card">
    <h2>📅 Prochain RDV</h2>
    <div class="delai-box">
        <input type="radio" name="delai_mois" value="1" id="d1" class="delai-radio">
        <label for="d1" class="delai-label">1 mois</label>
        <input type="radio" name="delai_mois" value="3" id="d3" class="delai-radio" checked>
        <label for="d3" class="delai-label">3 mois</label>
        <input type="radio" name="delai_mois" value="6" id="d6" class="delai-radio">
        <label for="d6" class="delai-label">6 mois</label>
        <input type="radio" name="delai_mois" value="12" id="d12" class="delai-radio">
        <label for="d12" class="delai-label">1 an</label>
    </div>
</div>

<button type="submit" class="btn-save">💾 Enregistrer l'ordonnance</button>

</form>
</div>
</body>
</html>