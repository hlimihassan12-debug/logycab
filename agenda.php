<?php
require_once __DIR__ . '/backend/auth.php';

if (!isset($_SESSION['user'])) {
    header('Location: /logycab/login.php');
    exit;
}
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'backend/db.php';

$db = getDB();

// --- Date affichée ---
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// --- Jours fériés ---
function estFerie($db, $date) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM T_JoursFeries WHERE DateFerie = ?");
    $stmt->execute([$date]);
    return $stmt->fetchColumn() > 0;
}

// --- Prochain jour ouvrable ---
function prochainJourOuvrable($db, $date, $sens = 1) {
    $d = new DateTime($date);
    $d->modify($sens > 0 ? '+1 day' : '-1 day');
    for ($i = 0; $i < 30; $i++) {
        $dow = (int)$d->format('N');
        $ds  = $d->format('Y-m-d');
        if ($dow < 6 && !estFerie($db, $ds)) return $ds;
        $d->modify($sens > 0 ? '+1 day' : '-1 day');
    }
    return $date;
}

// --- Actions POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'vu') {
        $n = (int)$_POST['n_ordon'];
        $stmt = $db->prepare("SELECT vu FROM ORD WHERE n_ordon = ?");
        $stmt->execute([$n]);
        $vu = $stmt->fetchColumn();
        $db->prepare("UPDATE ORD SET vu = ? WHERE n_ordon = ?")->execute([$vu ? 0 : 1, $n]);
    }

    if ($action === 'ajouter') {
        $npat    = (int)$_POST['npat'];
        $heure   = $_POST['heure'];
        $dateRdv = $_POST['date'];
        $stmt = $db->prepare("SELECT COUNT(*) FROM ID WHERE [N°PAT] = ?");
        $stmt->execute([$npat]);
        if ($stmt->fetchColumn() > 0) {
            $stmt2 = $db->prepare("SELECT COUNT(*) FROM ORD WHERE id=? AND CAST([DATE REDEZ VOUS] AS DATE)=?");
            $stmt2->execute([$npat, $dateRdv]);
            if ($stmt2->fetchColumn() == 0) {
                $db->prepare("INSERT INTO ORD (id, date_ordon, [DATE REDEZ VOUS], Date_Rdv, HeureRDV, DateSaisie)
                              VALUES (?, GETDATE(), ?, ?, ?, GETDATE())")
                   ->execute([$npat, $dateRdv, $dateRdv, $heure]);
            }
        }
    }

    header("Location: agenda.php?date=" . $_POST['date']);
    exit;
}

// --- Navigation dates ---
$datePrev = prochainJourOuvrable($db, $date, -1);
$dateNext = prochainJourOuvrable($db, $date, +1);

// --- Patients du jour ---
$stmt = $db->prepare("
    SELECT o.n_ordon, o.HeureRDV, o.Urgence, o.vu, o.SansReponse,
           o.Observation, o.acte1,
           i.NOMPRENOM, i.[TEL D], i.[N°PAT]
    FROM ORD o
    INNER JOIN ID i ON o.id = i.[N°PAT]
    WHERE CAST(o.[DATE REDEZ VOUS] AS DATE) = ?
    ORDER BY o.HeureRDV, o.n_ordon
");
$stmt->execute([$date]);
$patients = $stmt->fetchAll();
$nbPatients = count($patients);

// --- CA du jour ---
$stmtCA = $db->prepare("
    SELECT ISNULL(SUM(d.Versé), 0) AS total
    FROM detail_acte d
    INNER JOIN facture f ON d.N_fact = f.n_facture
    WHERE CAST(f.date_facture AS DATE) = ?
");
$stmtCA->execute([$date]);
$ca = $stmtCA->fetch()['total'];

// --- Limite du jour ---
$stmtLim = $db->prepare("SELECT Valeur FROM T_Config WHERE Cle = ?");
$stmtLim->execute(['MaxPatients_' . $date]);
$limJour = $stmtLim->fetch();
if (!$limJour) {
    $stmtLim->execute(['MaxPatientsJour']);
    $limJour = $stmtLim->fetch();
}
$limite = $limJour ? (int)$limJour['Valeur'] : 30;

// --- Créneaux horaires ---
$creneaux = [];
for ($h = 9; $h <= 16; $h++) {
    foreach ([0, 30] as $m) {
        if ($h == 16 && $m == 30) break;
        $creneaux[] = sprintf('%02d:%02d', $h, $m);
    }
}

// --- Date en français ---
$jours = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
$mois  = ['','Janvier','Février','Mars','Avril','Mai','Juin','Juillet',
           'Août','Septembre','Octobre','Novembre','Décembre'];
$ts      = strtotime($date);
$jourAff = $jours[date('w',$ts)] . ' ' . date('d',$ts) . ' ' .
           $mois[(int)date('m',$ts)] . ' ' . date('Y',$ts);
$dateAff = date('d/m/Y', $ts);

// --- Couleur jauge patients ---
$pct = $limite > 0 ? round($nbPatients / $limite * 100) : 0;
$couleurJauge = $pct < 70 ? '#2ecc71' : ($pct < 90 ? '#f39c12' : '#e74c3c');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Agenda — Cabinet Dr Hlimi</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Arial, sans-serif; background: #f0f4f8; color: #333; padding-bottom: 60px; }
.header { background: #1a4a7a; color: white; padding: 10px 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; }
.header h1 { font-size: 16px; }
.date-nav { display: flex; align-items: center; gap: 8px; }
.date-nav a { color: white; background: #2e6da4; padding: 6px 14px; border-radius: 4px; text-decoration: none; font-size: 16px; }
.date-nav a:hover { background: #3a7fc1; }
.date-nav strong { font-size: 15px; }
.stats-bar { background: #2e6da4; color: white; padding: 8px 20px; display: flex; gap: 20px; align-items: center; flex-wrap: wrap; }
.jauge-wrap { display: flex; align-items: center; gap: 8px; }
.jauge-bg { background: rgba(255,255,255,0.3); border-radius: 10px; width: 120px; height: 12px; }
.jauge-fill { height: 12px; border-radius: 10px; background: <?= $couleurJauge ?>; width: <?= min($pct,100) ?>%; }
.btn-ajouter { background: #27ae60; color: white; border: none; padding: 7px 16px; border-radius: 4px; cursor: pointer; font-size: 14px; }
.btn-ajouter:hover { background: #2ecc71; }
.container { padding: 12px; }
table { width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
thead { background: #1a4a7a; color: white; }
thead th { padding: 10px 12px; text-align: left; font-size: 13px; }
tbody tr { border-bottom: 1px solid #e8e8e8; transition: background 0.15s; }
tbody tr:hover { background: #f0f7ff; }
tbody td { padding: 9px 12px; font-size: 13px; vertical-align: middle; }
.vu td { background: #e0e0e0 !important; color: #666; }
.urgence td { background: #ffe0e0 !important; }
.heure { font-weight: bold; color: #1a4a7a; font-size: 14px; white-space: nowrap; }
.acte-badge { background: #e8f5e9; color: #2e7d32; padding: 2px 8px; border-radius: 10px; font-size: 12px; display: inline-block; }
.btn-vu { background: none; border: 1px solid #888; padding: 3px 10px; border-radius: 4px; cursor: pointer; font-size: 12px; }
.btn-vu:hover { background: #e0e0e0; }
.modal-bg { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 100; align-items: center; justify-content: center; }
.modal-bg.active { display: flex; }
.modal { background: white; border-radius: 8px; padding: 24px; width: 340px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); }
.modal h3 { margin-bottom: 16px; color: #1a4a7a; }
.modal label { display: block; margin-bottom: 4px; font-size: 13px; font-weight: bold; }
.modal input, .modal select { width: 100%; padding: 8px; margin-bottom: 14px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; }
.modal-btns { display: flex; gap: 10px; justify-content: flex-end; }
.modal-btns button { padding: 8px 18px; border-radius: 4px; border: none; cursor: pointer; font-size: 14px; }
.btn-ok { background: #1a4a7a; color: white; }
.btn-cancel { background: #e0e0e0; }
.search-box { background: white; border-radius: 8px; padding: 16px; margin-bottom: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); display: flex; gap: 10px; align-items: center; }
.search-box input { flex: 1; padding: 8px 12px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; }
#resultats-recherche { background: white; border-radius: 4px; border: 1px solid #ccc; max-height: 200px; overflow-y: auto; display: none; position: absolute; z-index: 50; width: 300px; }
#resultats-recherche div { padding: 8px 12px; cursor: pointer; font-size: 13px; border-bottom: 1px solid #eee; }
#resultats-recherche div:hover { background: #f0f7ff; }
.footer-ca { background: #1a4a7a; color: white; padding: 10px 20px; text-align: right; font-size: 15px; position: fixed; bottom: 0; width: 100%; }
.footer-ca span { font-size: 18px; font-weight: bold; color: #ffd700; }
</style>
</head>
<body>

<div class="header">
    <h1>🏥 Cabinet Dr Hlimi — Cardiologue Tétouan</h1>
<a href="logout.php" style="color:white;text-decoration:none;background:#e74c3c;padding:6px 14px;border-radius:4px;font-size:13px;margin-left:10px;">🚪 Déconnexion</a>    <div class="date-nav">
        <a href="?date=<?= $datePrev ?>">◀</a>
        <strong><?= $jourAff ?></strong>
        <a href="?date=<?= $dateNext ?>">▶</a>
        <a href="?date=<?= date('Y-m-d') ?>" style="font-size:13px;padding:5px 10px;">Aujourd'hui</a>
    </div>
</div>

<div class="stats-bar">
    <div class="jauge-wrap">
        Patients :&nbsp;<strong><?= $nbPatients ?> / <?= $limite ?></strong>
        <div class="jauge-bg"><div class="jauge-fill"></div></div>
    </div>
    <div>CA : <strong><?= number_format($ca, 2, ',', ' ') ?> DH</strong></div>
    <a href="recherche.php" style="background:#6c757d;color:white;padding:7px 16px;border-radius:4px;text-decoration:none;font-size:14px;">🔍 Recherche</a>
    <button class="btn-ajouter" onclick="ouvrirModal()">➕ Ajouter patient</button>
</div>

<div class="container">
<div class="search-box" style="position:relative;">
    <input type="text" id="recherche" placeholder="Rechercher un patient (nom ou N°)..." oninput="rechercherPatient(this.value)">
    <div id="resultats-recherche"></div>
</div>

<table>
    <thead>
        <tr>
            <th>Heure</th>
            <th>N°</th>
            <th>Patient</th>
            <th>Téléphone</th>
            <th>Actes prévus</th>
            <th>Observation</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php if (empty($patients)): ?>
        <tr><td colspan="7" style="text-align:center;padding:30px;color:#999;">Aucun patient prévu ce jour</td></tr>
    <?php else: ?>
        <?php foreach ($patients as $p): ?>
        <?php $classe = $p['vu'] ? 'vu' : ($p['Urgence'] ? 'urgence' : ''); ?>
        <tr class="<?= $classe ?>">
            <td class="heure"><?= $p['Urgence'] ? '🚨 URGENT' : htmlspecialchars($p['HeureRDV'] ?? '--:--') ?></td>
            <td><?= $p['N°PAT'] ?></td>
            <td><a href="consultation.php?id=<?= $p['N°PAT'] ?>" style="color:#1a4a7a;text-decoration:none;font-weight:bold;"><?= htmlspecialchars($p['NOMPRENOM']) ?></a></td>
            <td><?= htmlspecialchars($p['TEL D'] ?? '') ?></td>
            <td><?php if ($p['acte1']): ?><span class="acte-badge"><?= htmlspecialchars($p['acte1']) ?></span><?php endif; ?></td>
            <td><?= htmlspecialchars($p['Observation'] ?? '') ?></td>
            <td>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="vu">
                    <input type="hidden" name="n_ordon" value="<?= $p['n_ordon'] ?>">
                    <input type="hidden" name="date" value="<?= $date ?>">
                    <button class="btn-vu" type="submit"><?= $p['vu'] ? '↩️ Annuler' : '✅ Vu' ?></button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>
</div>

<div class="modal-bg" id="modal">
    <div class="modal">
        <h3>➕ Ajouter un patient</h3>
        <form method="POST">
            <input type="hidden" name="action" value="ajouter">
            <input type="hidden" name="date" value="<?= $date ?>">
            <label>N° Patient :</label>
            <input type="number" name="npat" id="modal-npat" required placeholder="Ex: 1234">
            <label>Créneau horaire :</label>
            <select name="heure">
                <?php foreach ($creneaux as $c): ?>
                    <option value="<?= $c ?>"><?= $c ?></option>
                <?php endforeach; ?>
            </select>
            <div class="modal-btns">
                <button type="button" class="btn-cancel" onclick="fermerModal()">Annuler</button>
                <button type="submit" class="btn-ok">Ajouter</button>
            </div>
        </form>
    </div>
</div>

<div class="footer-ca">
    CA du <?= $dateAff ?> : <span><?= number_format($ca, 2, ',', ' ') ?> DH</span>
</div>

<script>
function ouvrirModal() { document.getElementById('modal').classList.add('active'); document.getElementById('modal-npat').focus(); }
function fermerModal() { document.getElementById('modal').classList.remove('active'); }
document.getElementById('modal').addEventListener('click', function(e) { if (e.target === this) fermerModal(); });

function rechercherPatient(val) {
    const div = document.getElementById('resultats-recherche');
    if (val.length < 2) { div.style.display = 'none'; return; }
    fetch('backend/api/recherche_patient.php?q=' + encodeURIComponent(val))
        .then(r => r.json())
        .then(data => {
            if (data.length === 0) { div.style.display = 'none'; return; }
            div.innerHTML = data.map(p => `<div onclick="window.location='patient.php?id=${p.npat}'""><strong>${p.npat}</strong> — ${p.nom}</div>`).join('');
            div.style.display = 'block';
        });
}
</script>
</body>
</html>