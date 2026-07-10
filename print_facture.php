<?php
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';
$db = getDB();

$id    = (int)($_GET['id']   ?? 0);
$nFact = (int)($_GET['fact'] ?? 0);

if ($id == 0 || $nFact == 0) { die("❌ Paramètres manquants."); }

$stmtPat = $db->prepare("SELECT * FROM ID WHERE [N°PAT] = ?");
$stmtPat->execute([$id]);
$patient = $stmtPat->fetch();
if (!$patient) { die("❌ Patient introuvable."); }

$stmtFact = $db->prepare("SELECT * FROM facture WHERE n_facture = ? AND id = ?");
$stmtFact->execute([$nFact, $id]);
$facture = $stmtFact->fetch();
if (!$facture) { die("❌ Facture introuvable."); }

$stmtDA = $db->prepare("SELECT d.*, a.ACTE AS nom_acte FROM detail_acte d LEFT JOIN t_acte_simplifiée a ON d.ACTE = a.n_acte WHERE d.N_fact = ?");
$stmtDA->execute([$nFact]);
$actes = $stmtDA->fetchAll();

$total = 0;
foreach ($actes as $a) { $total += (float)$a['Versé']; }

$dateFact = '—';
if (!empty($facture['date_facture'])) {
    $ts = strtotime($facture['date_facture']);
    if ($ts && $ts > 86400) $dateFact = date('d/m/Y', $ts);
}

$nomPatient = htmlspecialchars(strtoupper(trim($patient['NOMPRENOM'] ?? '')));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Note d'honoraires — <?= $nomPatient ?></title>
<style>
    * { margin:0; padding:0; box-sizing:border-box; }

    @page {
        size: 147mm 212mm;
        margin: 0;
    }

    body {
        font-family: Arial, sans-serif;
        font-size: 13px;
        color: #111;
        background: white;
        width: 147mm;
        padding-top:    5cm;      /* en-tête physique pré-imprimé (mesuré) */
        padding-bottom: 1.7cm;    /* pied physique pré-imprimé (mesuré) */
        padding-left:   1cm;
        padding-right:  1cm;
    }

    .ligne-titre {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-top: 1cm;
        margin-bottom: 24px;
    }

    .titre {
        margin-left: 2cm;
        font-size: 16px;
        font-weight: bold;
        letter-spacing: 1px;
        text-decoration: underline;
        white-space: nowrap;
    }

    .reference {
        font-size: 11px;
        color: #555;
        text-align: right;
        line-height: 1.6;
        white-space: nowrap;
    }

    .entete {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        margin-bottom: 20px;
    }
    .entete .nom { font-weight: bold; font-size: 14px; margin-left: 1cm; }

    table { width: 100%; border-collapse: collapse; margin-top: 6px; }
    td { padding: 6px 4px; font-size: 13px; vertical-align: top; }
    .col-date { width: 90px; color: #555; }
    .col-acte { padding-left: calc(4px + 1cm); }
    .montant  { text-align: right; white-space: nowrap; }
    .total-row td { border-top: 2px solid #111; font-weight: bold; padding-top: 12px; }

    .btn-fermer {
        display: inline-block;
        margin-top: 30px;
        background: #888;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 4px;
        font-size: 13px;
        font-weight: bold;
        cursor: pointer;
    }
    @media print { .btn-fermer { display: none; } }
</style>
</head>
<body>

<div class="ligne-titre">
    <div class="titre">NOTE D'HONORAIRES</div>
    <div class="reference">
        <div>N° patient <?= htmlspecialchars($patient['N°PAT']) ?></div>
        <div>N° facture <?= $nFact ?></div>
        <div>Identifiant fiscal 56907800</div>
    </div>
</div>

<div class="entete">
    <div class="nom"><?= $nomPatient ?></div>
    <div><?= $dateFact ?></div>
</div>

<table>
<?php foreach ($actes as $a): ?>
    <?php $tsA = strtotime($a['date-H'] ?? ''); $dA = ($tsA && $tsA > 86400) ? date('d/m/Y', $tsA) : $dateFact; ?>
    <tr>
        <td class="col-date"><?= $dA ?></td>
        <td class="col-acte"><?= htmlspecialchars($a['nom_acte'] ?? ('Acte ' . $a['ACTE'])) ?></td>
        <td class="montant"><?= number_format((float)$a['Versé'], 2, ',', ' ') ?> DH</td>
    </tr>
<?php endforeach; ?>
    <tr class="total-row">
        <td></td>
        <td>Total</td>
        <td class="montant"><?= number_format($total, 2, ',', ' ') ?> DH</td>
    </tr>
</table>

<button type="button" class="btn-fermer" onclick="window.close()">✕ Fermer</button>

<script>
    window.onload = function() {
        window.print();
        window.addEventListener('afterprint', function() {
            window.close();
        });
    };
</script>
</body>
</html>
