<?php
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
if ($id == 0) { header('Location: recherche.php'); exit; }

// Récupérer infos patient
$stmtP = $db->prepare("SELECT * FROM ID WHERE [N°PAT] = ?");
$stmtP->execute([$id]);
$patient = $stmtP->fetch();

// Récupérer la liste des actes disponibles
$actes = $db->query("SELECT n_acte, ACTE, cout FROM t_acte_simplifiée ORDER BY ACTE")->fetchAll();

$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date_facture = $_POST['date_facture'] ?? date('Y-m-d');
    $lignes = $_POST['lignes'] ?? [];

    // Filtrer les lignes vides
    $lignesValides = [];
    foreach ($lignes as $l) {
        if (!empty($l['acte']) && isset($l['prixU']) && $l['prixU'] !== '') {
            $lignesValides[] = $l;
        }
    }

    if (empty($lignesValides)) {
        $erreur = "Veuillez ajouter au moins un acte.";
    } else {
        try {
            $db->beginTransaction();

            // Créer la facture
            $stmt = $db->prepare("INSERT INTO facture (id, date_facture, mode_paiement, montant, remarque) VALUES (?, ?, NULL, 0, NULL)");
            $stmt->execute([$id, $date_facture]);
            $nFact = $db->lastInsertId();

            // Insérer les lignes d'actes
            $stmtDA = $db->prepare("INSERT INTO detail_acte (N_fact, date_H, ACTE, prixU, QTIT, Versé, dette) VALUES (?, ?, ?, ?, ?, 0, ?)");
            $totalMontant = 0;
            foreach ($lignesValides as $l) {
                $dateActe = !empty($l['date_acte']) ? $l['date_acte'] : $date_facture;
                $acteId   = (int)$l['acte'];
                $prixU    = (float)$l['prixU'];
                $qtit     = max(1, (int)($l['qtit'] ?? 1));
                $verse    = (float)($l['verse'] ?? 0);
                $dette    = ($prixU * $qtit) - $verse;
                $totalMontant += $prixU * $qtit;

                $stmtDA->execute([$nFact, $dateActe, $acteId, $prixU, $qtit, $dette]);
            }

            // Mettre à jour le montant total de la facture
            $db->prepare("UPDATE facture SET montant = ? WHERE n_facture = ?")->execute([$totalMontant, $nFact]);

            $db->commit();
            header("Location: dossier.php?id=$id&fact=$nFact");
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            $erreur = "Erreur lors de l'enregistrement : " . $e->getMessage();
        }
    }
}

$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Nouvelle Facture — Logycab</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'Segoe UI', Tahoma, sans-serif;
    background: #f0f4f8;
    color: #1a2a3a;
    font-size: 13px;
}

/* ── Header ── */
.header {
    background: #1a4a7a;
    color: white;
    padding: 10px 20px;
    display: flex;
    align-items: center;
    gap: 16px;
}
.header h1 { font-size: 16px; font-weight: 600; }
.header .patient-info { font-size: 12px; opacity: 0.85; }
.btn-retour {
    background: rgba(255,255,255,0.2);
    color: white;
    border: none;
    border-radius: 4px;
    padding: 5px 12px;
    cursor: pointer;
    font-size: 12px;
    text-decoration: none;
    display: inline-block;
}
.btn-retour:hover { background: rgba(255,255,255,0.35); }

/* ── Contenu ── */
.container {
    max-width: 820px;
    margin: 24px auto;
    padding: 0 16px;
}

.card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    padding: 20px;
    margin-bottom: 16px;
}

.card h2 {
    font-size: 14px;
    color: #1a4a7a;
    margin-bottom: 14px;
    padding-bottom: 8px;
    border-bottom: 2px solid #e8f0f8;
}

/* ── Date facture ── */
.champ-date { display: flex; align-items: center; gap: 12px; }
.champ-date label { font-weight: 600; min-width: 100px; }
.champ-date input[type=date] {
    border: 1px solid #cdd5de;
    border-radius: 4px;
    padding: 5px 10px;
    font-size: 13px;
}

/* ── Table actes ── */
.table-actes {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
}
.table-actes thead th {
    background: #1a4a7a;
    color: white;
    padding: 7px 8px;
    text-align: left;
    font-weight: 500;
}
.table-actes thead th:last-child { text-align: center; }
.table-actes tbody tr { border-bottom: 1px solid #eef0f3; }
.table-actes tbody tr:hover { background: #f7fafd; }
.table-actes td { padding: 5px 6px; }

.table-actes select,
.table-actes input[type=date],
.table-actes input[type=number] {
    width: 100%;
    border: 1px solid #cdd5de;
    border-radius: 3px;
    padding: 4px 6px;
    font-size: 12px;
    background: white;
}
.table-actes input[type=number] { text-align: right; }

/* Colonnes */
.col-date  { width: 115px; }
.col-acte  { width: auto; }
.col-prix  { width: 90px; }
.col-qtit  { width: 55px; }
.col-verse { width: 90px; }
.col-dette { width: 90px; }
.col-del   { width: 36px; }

.dette-cell {
    text-align: right;
    font-weight: 600;
    color: #c0392b;
    padding-right: 8px;
}

/* ── Pied de table ── */
.tfoot-total {
    background: #f0f4f8;
    font-weight: 700;
}
.tfoot-total td { padding: 7px 8px; }

/* ── Boutons ── */
.actions {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}
.btn-add {
    background: #2ecc71;
    color: white;
    border: none;
    border-radius: 4px;
    padding: 7px 16px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 600;
}
.btn-add:hover { background: #27ae60; }

.btn-save {
    background: #1a4a7a;
    color: white;
    border: none;
    border-radius: 4px;
    padding: 8px 24px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
}
.btn-save:hover { background: #153d66; }

.btn-del {
    background: #e74c3c;
    color: white;
    border: none;
    border-radius: 3px;
    padding: 3px 7px;
    cursor: pointer;
    font-size: 11px;
}
.btn-del:hover { background: #c0392b; }

/* ── Erreur ── */
.erreur {
    background: #fdecea;
    border: 1px solid #e74c3c;
    color: #c0392b;
    padding: 10px 14px;
    border-radius: 5px;
    margin-bottom: 14px;
    font-weight: 600;
}
</style>
</head>
<body>

<div class="header">
    <a href="dossier.php?id=<?= $id ?>" class="btn-retour">← Retour</a>
    <div>
        <div class="header h1">Nouvelle Facture</div>
        <?php if ($patient): ?>
        <div class="patient-info">
            Patient : <?= htmlspecialchars($patient['NOMPRENOM'] ?? '') ?>
            &nbsp;|&nbsp; Dossier n° <?= $id ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="container">

    <?php if ($erreur): ?>
    <div class="erreur"><?= htmlspecialchars($erreur) ?></div>
    <?php endif; ?>

    <form method="POST">

    <!-- Date facture -->
    <div class="card">
        <h2>Informations de la facture</h2>
        <div class="champ-date">
            <label>Date facture :</label>
            <input type="date" name="date_facture" value="<?= htmlspecialchars($_POST['date_facture'] ?? $today) ?>" required>
        </div>
    </div>

    <!-- Lignes d'actes -->
    <div class="card">
        <h2>Actes facturés</h2>
        <table class="table-actes" id="tableActes">
            <thead>
                <tr>
                    <th class="col-date">Date acte</th>
                    <th class="col-acte">Acte</th>
                    <th class="col-prix">Prix (DH)</th>
                    <th class="col-qtit">Qté</th>
                    <th class="col-verse">Versé (DH)</th>
                    <th class="col-dette">Reste (DH)</th>
                    <th class="col-del"></th>
                </tr>
            </thead>
            <tbody id="lignes">
                <!-- Lignes ajoutées par JS -->
            </tbody>
            <tfoot>
                <tr class="tfoot-total">
                    <td colspan="2" style="text-align:right;">Total</td>
                    <td style="text-align:right;" id="totalPrix">0</td>
                    <td></td>
                    <td style="text-align:right;" id="totalVerse">0</td>
                    <td style="text-align:right;color:#c0392b;" id="totalDette">0</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>

        <br>
        <div class="actions">
            <button type="button" class="btn-add" onclick="ajouterLigne()">✚ Ajouter un acte</button>
        </div>
    </div>

    <div class="actions">
        <button type="submit" class="btn-save">💾 Enregistrer la facture</button>
        <a href="dossier.php?id=<?= $id ?>" class="btn-retour" style="background:#888;">Annuler</a>
    </div>

    </form>
</div>

<script>
const actes = <?= json_encode(array_map(fn($a) => [
    'n_acte' => $a['n_acte'],
    'ACTE'   => $a['ACTE'],
    'cout'   => $a['cout']
], $actes)) ?>;

const today = '<?= $today ?>';
let idx = 0;

function ajouterLigne(dateActe, acteId, prixU, qtit, verse) {
    const i = idx++;
    const tbody = document.getElementById('lignes');
    const tr = document.createElement('tr');

    // Options actes
    let opts = '<option value="">— Sélectionner —</option>';
    actes.forEach(a => {
        const sel = (acteId && a.n_acte == acteId) ? 'selected' : '';
        opts += `<option value="${a.n_acte}" data-cout="${a.cout}" ${sel}>${a.ACTE}</option>`;
    });

    tr.innerHTML = `
        <td class="col-date">
            <input type="date" name="lignes[${i}][date_acte]" value="${dateActe || today}">
        </td>
        <td class="col-acte">
            <select name="lignes[${i}][acte]" onchange="remplirPrix(this, ${i})" required>
                ${opts}
            </select>
        </td>
        <td class="col-prix">
            <input type="number" name="lignes[${i}][prixU]" id="prix_${i}"
                   value="${prixU || ''}" min="0" step="0.01"
                   oninput="recalculer(${i})" placeholder="0">
        </td>
        <td class="col-qtit">
            <input type="number" name="lignes[${i}][qtit]" id="qtit_${i}"
                   value="${qtit || 1}" min="1" step="1"
                   oninput="recalculer(${i})">
        </td>
        <td class="col-verse">
            <input type="number" name="lignes[${i}][verse]" id="verse_${i}"
                   value="${verse || 0}" min="0" step="0.01"
                   oninput="recalculer(${i})" placeholder="0">
        </td>
        <td class="col-dette dette-cell" id="dette_${i}">0</td>
        <td class="col-del">
            <button type="button" class="btn-del" onclick="supprimerLigne(this)">✕</button>
        </td>
    `;
    tbody.appendChild(tr);

    // Si acte pré-sélectionné, calculer
    if (acteId) recalculer(i);
}

function remplirPrix(sel, i) {
    const opt = sel.options[sel.selectedIndex];
    const cout = opt.getAttribute('data-cout') || '';
    document.getElementById(`prix_${i}`).value = cout;
    recalculer(i);
}

function recalculer(i) {
    const prix  = parseFloat(document.getElementById(`prix_${i}`)?.value) || 0;
    const qtit  = parseInt(document.getElementById(`qtit_${i}`)?.value) || 1;
    const verse = parseFloat(document.getElementById(`verse_${i}`)?.value) || 0;
    const dette = (prix * qtit) - verse;
    const detteEl = document.getElementById(`dette_${i}`);
    if (detteEl) detteEl.textContent = formatNum(dette);
    majTotaux();
}

function supprimerLigne(btn) {
    btn.closest('tr').remove();
    majTotaux();
}

function majTotaux() {
    let totalPrix = 0, totalVerse = 0, totalDette = 0;
    document.querySelectorAll('#lignes tr').forEach(tr => {
        const prixI  = tr.querySelector('input[name*="prixU"]');
        const qtitI  = tr.querySelector('input[name*="qtit"]');
        const verseI = tr.querySelector('input[name*="verse"]');
        const prix   = parseFloat(prixI?.value) || 0;
        const qtit   = parseInt(qtitI?.value) || 1;
        const verse  = parseFloat(verseI?.value) || 0;
        totalPrix  += prix * qtit;
        totalVerse += verse;
        totalDette += (prix * qtit) - verse;
    });
    document.getElementById('totalPrix').textContent  = formatNum(totalPrix);
    document.getElementById('totalVerse').textContent = formatNum(totalVerse);
    document.getElementById('totalDette').textContent = formatNum(totalDette);
}

function formatNum(n) {
    return n.toLocaleString('fr-FR', {minimumFractionDigits: 0, maximumFractionDigits: 2}) + ' DH';
}

// Ajouter une première ligne au chargement
ajouterLigne();
</script>

</body>
</html>