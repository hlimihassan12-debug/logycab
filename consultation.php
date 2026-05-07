<?php
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
if (!$patient) { die("❌ Patient introuvable !"); }

// Toutes les ordonnances
$stmt2 = $db->prepare("
    SELECT n_ordon, date_ordon, [DATE REDEZ VOUS], HeureRDV, acte1,
           Urgence, vu, Observation
    FROM ORD WHERE id = ?
    ORDER BY date_ordon DESC
");
$stmt2->execute([$id]);
$ordonnances = $stmt2->fetchAll();

// Dernière ordonnance
$dernOrd = !empty($ordonnances) ? $ordonnances[0] : null;
// Médicaments de la dernière ordonnance
$medicaments = [];
if ($dernOrd) {
    $stmtMed = $db->prepare("
        SELECT p.posologie, p.DUREE, p.Ordre,
               pr.PRODUIT
        FROM PROD p
        LEFT JOIN PRODUITS pr ON p.produit = pr.NuméroPRODUIT
        WHERE p.N_ord = ?
        ORDER BY p.Ordre
    ");
    $stmtMed->execute([$dernOrd['n_ordon']]);
    $medicaments = $stmtMed->fetchAll();
}
// Historique actes (ECG, EDC, DTSA...)
$stmtActes = $db->prepare("
    SELECT 
        MAX(CASE WHEN d.ACTE = 65 THEN f.date_facture END) AS dernier_ECG,
        MAX(CASE WHEN d.ACTE = 66 THEN f.date_facture END) AS dernier_EDC,
        MAX(CASE WHEN d.ACTE = 67 THEN f.date_facture END) AS dernier_DAMI,
        MAX(CASE WHEN d.ACTE = 68 THEN f.date_facture END) AS dernier_DVMI,
        MAX(CASE WHEN d.ACTE = 69 THEN f.date_facture END) AS dernier_DTSA,
        MAX(CASE WHEN d.ACTE = 74 THEN f.date_facture END) AS dernier_DAR,
        MAX(CASE WHEN d.ACTE = 76 THEN f.date_facture END) AS dernier_EDCP,
        MAX(CASE WHEN d.ACTE = 77 THEN f.date_facture END) AS dernier_IV
    FROM detail_acte d
    INNER JOIN facture f ON d.N_fact = f.n_facture
    WHERE f.id = ?
");
$stmtActes->execute([$id]);
$actes = $stmtActes->fetch();
// Dernier examen clinique
$stmt3 = $db->prepare("
    SELECT TOP 1 DateExam, TAS, TAD, FC, POIDS, TAILLE, IMC,
           S_Fonctionnels, Auscult_Cardiaque, Conclusion, REMARQUE,
           FDR_HTA, FDR_Diabete, FDR_Tabac, FDR_Obesite,
           FDR_Age, FDR_ATCD_IDM_Fam, FDR_LDL_Oui, FDR_TG_Oui
    FROM t_examen WHERE NPAT = ?
    ORDER BY DateExam DESC
");
$stmt3->execute([$id]);
$examen = $stmt3->fetch();

// Calcul age
$age = '';
if ($patient['DDN']) {
    $naissance = new DateTime($patient['DDN']);
    $now = new DateTime();
    $age = $naissance->diff($now)->y . ' ans';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Consultation — <?= htmlspecialchars($patient['NOMPRENOM']) ?></title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Arial, sans-serif; background: #f0f4f8; color: #333; }
.header { background: #1a4a7a; color: white; padding: 10px 20px; display: flex; align-items: center; gap: 15px; flex-wrap: wrap; }
.header a { color: white; text-decoration: none; background: #2e6da4; padding: 6px 14px; border-radius: 4px; font-size: 13px; }
.header h1 { font-size: 16px; flex: 1; }
.patient-bar { background: #2e6da4; color: white; padding: 10px 20px; display: flex; gap: 30px; flex-wrap: wrap; font-size: 13px; }
.patient-bar .info { display: flex; flex-direction: column; }
.patient-bar .info label { font-size: 10px; opacity: 0.8; text-transform: uppercase; }
.patient-bar .info span { font-weight: bold; font-size: 14px; }
.main { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; padding: 12px; }
@media (max-width: 768px) { .main { grid-template-columns: 1fr; } }
.card { background: white; border-radius: 8px; padding: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.card h2 { color: #1a4a7a; font-size: 14px; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 2px solid #e0e0e0; }
.rdv-box { background: #e8f4fd; border-radius: 6px; padding: 12px; margin-bottom: 10px; }
.rdv-date { font-size: 20px; font-weight: bold; color: #1a4a7a; }
.acte-badge { background: #e8f5e9; color: #2e7d32; padding: 3px 10px; border-radius: 10px; font-size: 12px; display: inline-block; margin-top: 6px; }
table { width: 100%; border-collapse: collapse; font-size: 12px; }
thead { background: #1a4a7a; color: white; }
thead th { padding: 7px 8px; text-align: left; }
tbody tr { border-bottom: 1px solid #eee; }
tbody tr:hover { background: #f0f7ff; }
tbody td { padding: 7px 8px; }
.grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.champ label { font-size: 10px; color: #888; text-transform: uppercase; font-weight: bold; display: block; }
.champ span { font-size: 13px; }
.actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 10px; }
.btn { padding: 7px 14px; border-radius: 4px; border: none; cursor: pointer; font-size: 13px; text-decoration: none; display: inline-block; }
.btn-primary { background: #1a4a7a; color: white; }
.btn-success { background: #27ae60; color: white; }
.btn:hover { opacity: 0.85; }
.card-full { grid-column: 1 / -1; }
.fdr-badge { background: #ffe0e0; color: #c0392b; padding: 2px 8px; border-radius: 10px; font-size: 12px; margin-right: 4px; display: inline-block; margin-top: 4px; }
</style>
</head>
<body>

<div class="header">
  <?php if (isset($_GET['msg']) && ($_GET['msg'] == 'ok' || $_GET['msg'] == 'ordonnance')): ?>
<div style="background:#27ae60;color:white;padding:10px 20px;font-size:14px;">
    ✅ Traitement reporté avec succès ! Nouveau RDV dans 3 mois + facture ECG créée.
</div>
<?php endif; ?>
    <a href="agenda.php">◀ Agenda</a>
    <a href="recherche.php">🔍 Recherche</a>
    <h1>🩺 Tableau de bord — <?= htmlspecialchars($patient['NOMPRENOM']) ?></h1>
    <a href="patient.php?id=<?= $id ?>">👤 Fiche</a>
    <a href="bilan.php?id=<?= $id ?>">🧪 Bilans</a>
</div>

<div class="patient-bar">
    <div class="info"><label>N° Patient</label><span><?= $patient['N°PAT'] ?></span></div>
    <div class="info"><label>Nom complet</label><span><?= htmlspecialchars($patient['NOMPRENOM']) ?></span></div>
    <div class="info"><label>Age</label><span><?= $age ?></span></div>
    <div class="info"><label>Telephone</label><span><?= htmlspecialchars($patient['TEL D'] ?? '—') ?></span></div>
    <div class="info"><label>Mutuelle</label><span><?= htmlspecialchars($patient['MUTUELLE'] ?? '—') ?></span></div>
</div>

<div class="main">

    <!-- PROCHAIN RDV -->
    <div class="card">
        <h2>📅 Prochain RDV</h2>
        <?php if ($dernOrd && $dernOrd['DATE REDEZ VOUS']): ?>
        <div class="rdv-box">
            <div class="rdv-date"><?= date('d/m/Y', strtotime($dernOrd['DATE REDEZ VOUS'])) ?></div>
            <div style="color:#555;">🕐 <?= htmlspecialchars($dernOrd['HeureRDV'] ?? 'Heure non definie') ?></div>
            <?php if ($dernOrd['acte1']): ?>
                <span class="acte-badge"><?= htmlspecialchars($dernOrd['acte1']) ?></span>
            <?php endif; ?>
        </div>
        <?php else: ?>
            <p style="color:#999;">Aucun RDV prevu</p>
        <?php endif; ?>
        <div class="actions">
<a href="nouvelle_ordonnance.php?id=<?= $id ?>" class="btn btn-primary">📋 Nouvelle ordonnance</a>            <a href="reporter_traitement.php?id=<?= $id ?>" class="btn btn-success" 
   onclick="return confirm('Reporter le même traitement pour 3 mois ?')">
   💊 Reporter traitement
</a>
        </div>
    </div>

    <!-- INFOS PATIENT -->
    <div class="card">
        <h2>👤 Informations patient</h2>
        <div class="grid2">
            <div class="champ"><label>Date naissance</label><span><?= $patient['DDN'] ? date('d/m/Y', strtotime($patient['DDN'])) : '—' ?></span></div>
            <div class="champ"><label>Sexe</label><span><?= htmlspecialchars($patient['SXE'] ?? '—') ?></span></div>
            <div class="champ"><label>Tel. bureau</label><span><?= htmlspecialchars($patient['TEL B'] ?? '—') ?></span></div>
            <div class="champ"><label>Mutuelle</label><span><?= htmlspecialchars($patient['MUTUELLE'] ?? '—') ?></span></div>
        </div>
        <?php if (!empty($patient['ATCD'])): ?>
        <div style="margin-top:10px;padding:8px;background:#fff3cd;border-radius:4px;font-size:12px;">
            <strong>ATCD :</strong> <?= htmlspecialchars($patient['ATCD']) ?>
        </div>
        <?php endif; ?>
    </div>
<!-- MEDICAMENTS -->
<?php if (!empty($medicaments)): ?>
<div class="card card-full">
    <h2>💊 Dernière ordonnance — <?= $dernOrd['date_ordon'] ? date('d/m/Y', strtotime($dernOrd['date_ordon'])) : '—' ?></h2>
    <table>
        <thead>
            <tr>
                <th>Médicament</th>
                <th>Posologie</th>
                <th>Durée</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($medicaments as $m): ?>
            <tr>
                <td><strong><?= htmlspecialchars($m['PRODUIT'] ?? 'Inconnu') ?></strong></td>
                <td><?= htmlspecialchars($m['posologie'] ?? '') ?></td>
                <td><?= htmlspecialchars($m['DUREE'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

    <!-- EXAMEN CLINIQUE -->
    <?php if ($examen): ?>
    <div class="card">
        <h2>🩺 Dernier examen — <?= $examen['DateExam'] ? date('d/m/Y', strtotime($examen['DateExam'])) : '—' ?></h2>
        <div class="grid2">
            <div class="champ">
                <label>Tension arterielle</label>
                <?php
                    $tas = (int)($examen['TAS'] ?? 0);
                    $tad = (int)($examen['TAD'] ?? 0);
                    $coulTA = '#333';
                    if ($tas >= 140 || $tad >= 90) $coulTA = '#e74c3c';
                    elseif ($tas >= 130 || $tad >= 80) $coulTA = '#f39c12';
                    elseif ($tas > 0) $coulTA = '#27ae60';
                ?>
                <span style="font-size:16px;font-weight:bold;color:<?= $coulTA ?>">
                    <?= ($tas && $tad) ? $tas.'/'.$tad.' mmHg' : '—' ?>
                </span>
            </div>
            <div class="champ"><label>Frequence cardiaque</label><span><?= $examen['FC'] ? $examen['FC'].' bpm' : '—' ?></span></div>
            <div class="champ"><label>Poids / Taille</label><span><?= $examen['POIDS'] ? $examen['POIDS'].' kg' : '—' ?> / <?= $examen['TAILLE'] ? $examen['TAILLE'].' cm' : '—' ?></span></div>
            <div class="champ"><label>IMC</label><span><?= $examen['IMC'] ? number_format($examen['IMC'], 1).' kg/m2' : '—' ?></span></div>
        </div>
        <?php if ($examen['Conclusion']): ?>
        <div style="margin-top:10px;padding:8px;background:#e8f4fd;border-radius:4px;font-size:12px;">
            <strong>Conclusion :</strong> <?= htmlspecialchars($examen['Conclusion']) ?>
        </div>
        <?php endif; ?>
        <?php
        $fdrs = [];
        if ($examen['FDR_HTA']) $fdrs[] = 'HTA';
        if ($examen['FDR_Diabete']) $fdrs[] = 'Diabete';
        if ($examen['FDR_Tabac']) $fdrs[] = 'Tabac';
        if ($examen['FDR_Obesite']) $fdrs[] = 'Obesite';
        if ($examen['FDR_Age']) $fdrs[] = 'Age';
        if ($examen['FDR_ATCD_IDM_Fam']) $fdrs[] = 'ATCD IDM Fam.';
        if ($examen['FDR_LDL_Oui']) $fdrs[] = 'Hypercholest.';
        if ($examen['FDR_TG_Oui']) $fdrs[] = 'Hypertrigly.';
        ?>
        <?php if (!empty($fdrs)): ?>
        <div style="margin-top:10px;">
            <label style="font-size:11px;color:#888;text-transform:uppercase;font-weight:bold;">Facteurs de risque</label>
            <div style="margin-top:4px;">
                <?php foreach ($fdrs as $fdr): ?>
                    <span class="fdr-badge"><?= $fdr ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- HISTORIQUE ACTES -->
<div class="card card-full">
    <h2>🔬 Historique des actes</h2>
    <table>
        <thead>
            <tr>
                <th>Acte</th>
                <th>Dernier réalisé</th>
                <th>Délai</th>
                <th>Statut</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $listeActes = [
            'ECG'  => ['date' => $actes['dernier_ECG'],  'seuil' => 1,  'unite' => 'mois'],
            'EDC'  => ['date' => $actes['dernier_EDC'],  'seuil' => 11, 'unite' => 'mois'],
            'DTSA' => ['date' => $actes['dernier_DTSA'], 'seuil' => 11, 'unite' => 'mois'],
            'DAMI' => ['date' => $actes['dernier_DAMI'], 'seuil' => 11, 'unite' => 'mois'],
            'DVMI' => ['date' => $actes['dernier_DVMI'], 'seuil' => 11, 'unite' => 'mois'],
            'DAR'  => ['date' => $actes['dernier_DAR'],  'seuil' => 11, 'unite' => 'mois'],
            'EDCP' => ['date' => $actes['dernier_EDCP'], 'seuil' => 6,  'unite' => 'mois'],
            'IV'   => ['date' => $actes['dernier_IV'],   'seuil' => 99, 'unite' => 'mois'],
        ];
        foreach ($listeActes as $nom => $info):
            if (!$info['date']) continue; // Jamais fait — on n'affiche pas
            $dernDate = new DateTime($info['date']);
            $now = new DateTime();
            $diff = $dernDate->diff($now);
            $moisEcoules = $diff->y * 12 + $diff->m;
            $depasse = $moisEcoules >= $info['seuil'];
            $couleur = $depasse ? '#e74c3c' : '#27ae60';
            $icone = $depasse ? '⚠️' : '✅';
            if ($diff->y > 0) {
                $delai = $diff->y . ' an(s) ' . $diff->m . ' mois';
            } else {
                $delai = $diff->m . ' mois';
            }
        ?>
            <tr>
                <td><strong><?= $nom ?></strong></td>
                <td><?= date('d/m/Y', strtotime($info['date'])) ?></td>
                <td><?= $delai ?></td>
                <td style="color:<?= $couleur ?>;font-weight:bold;"><?= $icone ?> <?= $depasse ? 'A renouveler' : 'OK' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
    <!-- HISTORIQUE CONSULTATIONS -->
    <div class="card card-full">
        <h2>📋 Historique des consultations (<?= count($ordonnances) ?>)</h2>
        <table>
            <thead>
                <tr>
                    <th>N° Ord.</th>
                    <th>Date consultation</th>
                    <th>Prochain RDV</th>
                    <th>Heure</th>
                    <th>Actes prevus</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($ordonnances as $o): ?>
                <tr>
                    <td><?= $o['n_ordon'] ?></td>
                    <td><?= $o['date_ordon'] ? date('d/m/Y', strtotime($o['date_ordon'])) : '—' ?></td>
                    <td><?= $o['DATE REDEZ VOUS'] ? date('d/m/Y', strtotime($o['DATE REDEZ VOUS'])) : '—' ?></td>
                    <td><?= htmlspecialchars($o['HeureRDV'] ?? '') ?></td>
                    <td><?php if ($o['acte1']): ?><span class="acte-badge"><?= htmlspecialchars($o['acte1']) ?></span><?php endif; ?></td>
                    <td><?= $o['vu'] ? '✅ Vu' : '' ?><?= $o['Urgence'] ? '🚨' : '' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>
</body>
</html>