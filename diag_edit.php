<?php
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';
$db = getDB();

$id   = (int)($_GET['id'] ?? 0);
$type = (int)($_GET['type'] ?? 1);
if ($id == 0) { header('Location: recherche.php'); exit; }

$stmtP = $db->prepare("SELECT NOMPRENOM FROM ID WHERE [N°PAT] = ?");
$stmtP->execute([$id]);
$patient = $stmtP->fetch();

$titres = [1 => 'Diagnostic principal', 2 => 'Diagnostic II', 3 => 'Diagnostic non cardiologique'];
$titre  = $titres[$type] ?? 'Diagnostic';

$tables = [
    1 => ['table' => 't_diagnostic',       'champ' => 'diagnostic'],
    2 => ['table' => 'T_dianstcII',         'champ' => 'DicII'],
    3 => ['table' => 'T_id_dic_non_cardio', 'champ' => 'dic_non_cardio'],
];
$t = $tables[$type];

$stmt = $db->prepare("SELECT {$t['champ']} FROM {$t['table']} WHERE id = ? ORDER BY 1");
$stmt->execute([$id]);
$existants = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmtCat = $db->prepare("SELECT diagnostic FROM catalogue_diagnostic WHERE type = ? ORDER BY diagnostic");
$stmtCat->execute([$type]);
$catalogue = $stmtCat->fetchAll(PDO::FETCH_COLUMN);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lignes = explode('||', $_POST['selected'] ?? '');
    $db->prepare("DELETE FROM {$t['table']} WHERE id = ?")->execute([$id]);
    foreach ($lignes as $d) {
        $d = trim($d);
        if (empty($d)) continue;
        $check = $db->prepare("SELECT COUNT(*) FROM catalogue_diagnostic WHERE diagnostic = ? AND type = ?");
        $check->execute([$d, $type]);
        if ($check->fetchColumn() == 0) {
            $db->prepare("INSERT INTO catalogue_diagnostic (diagnostic, type) VALUES (?, ?)")->execute([$d, $type]);
        }
        $db->prepare("INSERT INTO {$t['table']} (id, {$t['champ']}) VALUES (?, ?)")->execute([$id, $d]);
    }
    header("Location: dossier.php?id=$id");
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title><?= $titre ?></title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Arial, sans-serif; background: #f0f4f8; }
.header { background: #1a4a7a; color: white; padding: 10px 20px; display: flex; align-items: center; gap: 15px; }
.header a { color: white; text-decoration: none; background: #2e6da4; padding: 6px 14px; border-radius: 4px; font-size: 13px; }
.header h1 { font-size: 16px; }
.container { padding: 20px; max-width: 600px; margin: 0 auto; }
.card { background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.card h2 { color: #1a4a7a; font-size: 15px; margin-bottom: 16px; padding-bottom: 8px; border-bottom: 2px solid #e0e0e0; }
.diag-coche { display: flex; align-items: center; gap: 8px; padding: 6px 8px; background: #e8f4fd; border-radius: 4px; margin-bottom: 6px; font-size: 13px; }
.diag-coche button { background: #e74c3c; color: white; border: none; border-radius: 3px; padding: 2px 8px; cursor: pointer; font-size: 12px; }
.autocomplete-wrap { position: relative; margin-top: 12px; }
.autocomplete-wrap input { width: 100%; padding: 8px 12px; border: 2px solid #2e6da4; border-radius: 4px; font-size: 13px; }
.suggestions { position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #ccc; border-radius: 4px; max-height: 200px; overflow-y: auto; z-index: 100; display: none; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
.suggestion-item { padding: 8px 12px; cursor: pointer; font-size: 13px; border-bottom: 1px solid #f0f0f0; }
.suggestion-item:hover { background: #f0f7ff; }
.suggestion-new { color: #27ae60; font-style: italic; }
.btn-save { background: #27ae60; color: white; border: none; padding: 12px; border-radius: 4px; cursor: pointer; font-size: 15px; font-weight: bold; margin-top: 16px; width: 100%; }
.info { color: #888; font-size: 12px; margin-bottom: 8px; }
</style>
</head>
<body>
<div class="header">
    <a href="dossier.php?id=<?= $id ?>">◀ Retour</a>
    <h1>✏️ <?= $titre ?> — <?= htmlspecialchars($patient['NOMPRENOM']) ?></h1>
</div>
<div class="container">
<div class="card">
    <h2><?= $titre ?></h2>
    <p class="info">Cliquez sur le champ pour voir la liste — tapez pour filtrer</p>
    <form method="POST">
        <input type="hidden" name="selected" id="selected" value="<?= htmlspecialchars(implode('||', $existants)) ?>">
        
        <div id="liste-coches">
        <?php foreach ($existants as $d): ?>
            <div class="diag-coche" data-val="<?= htmlspecialchars($d) ?>">
                <span style="flex:1;"><?= htmlspecialchars($d) ?></span>
                <button type="button" onclick="supprimerDiag(this)">✕</button>
            </div>
        <?php endforeach; ?>
        </div>

        <div class="autocomplete-wrap">
            <input type="text" id="saisie" placeholder="Cliquez ou tapez pour chercher..." autocomplete="off">
            <div class="suggestions" id="suggestions"></div>
        </div>

        <button type="submit" class="btn-save">💾 Enregistrer</button>
    </form>
</div>
</div>

<script>
const catalogue = <?= json_encode($catalogue) ?>;
const saisie = document.getElementById('saisie');
const suggestions = document.getElementById('suggestions');
const listeCoches = document.getElementById('liste-coches');
const selectedInput = document.getElementById('selected');

function getDejaCoches() {
    return Array.from(listeCoches.querySelectorAll('.diag-coche')).map(d => d.dataset.val);
}

function mettreAjourSelected() {
    selectedInput.value = getDejaCoches().join('||');
}

function afficherSuggestions(val) {
    const dejaCoches = getDejaCoches();
    suggestions.innerHTML = '';
    
    let filtres = val.length === 0 
        ? catalogue.filter(d => !dejaCoches.includes(d))
        : catalogue.filter(d => d.toLowerCase().includes(val.toLowerCase()) && !dejaCoches.includes(d));

    filtres.slice(0, 15).forEach(d => {
        const div = document.createElement('div');
        div.className = 'suggestion-item';
        div.textContent = d;
        div.onclick = () => ajouterDiag(d);
        suggestions.appendChild(div);
    });

    if (val.length >= 2 && !catalogue.some(d => d.toLowerCase() === val.toLowerCase())) {
        const div = document.createElement('div');
        div.className = 'suggestion-item suggestion-new';
        div.textContent = '➕ Ajouter : "' + val + '"';
        div.onclick = () => ajouterDiag(val);
        suggestions.appendChild(div);
    }

    suggestions.style.display = suggestions.children.length > 0 ? 'block' : 'none';
}

saisie.addEventListener('focus', () => afficherSuggestions(''));
saisie.addEventListener('input', function() { afficherSuggestions(this.value.trim()); });

function ajouterDiag(valeur) {
    if (!valeur) return;
    const div = document.createElement('div');
    div.className = 'diag-coche';
    div.dataset.val = valeur;
    div.innerHTML = `<span style="flex:1;">${valeur}</span>
                     <button type="button" onclick="supprimerDiag(this)">✕</button>`;
    listeCoches.appendChild(div);
    mettreAjourSelected();
    saisie.value = '';
    suggestions.style.display = 'none';
}

function supprimerDiag(btn) {
    btn.closest('.diag-coche').remove();
    mettreAjourSelected();
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.autocomplete-wrap')) {
        suggestions.style.display = 'none';
    }
});
</script>
</body>
</html>