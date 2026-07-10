<?php
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';
$db = getDB();

$todayAff = date('Y-m-d');
$stmtCfgG = $db->prepare("SELECT Valeur FROM T_Config WHERE Cle='NbrMax'");
$stmtCfgG->execute();
$nbrMaxG = (int)($stmtCfgG->fetchColumn() ?: 20);
$stmtNbG = $db->prepare("SELECT COUNT(*) FROM ORD WHERE CONVERT(date,[DATE REDEZ VOUS]) = ? OR CONVERT(date,Date_Rdv) = ?");
$stmtNbG->execute([$todayAff, $todayAff]);
$nbPatientsG = (int)$stmtNbG->fetchColumn();

$themes_valides = ['theme-0','theme-a','theme-b','theme-c'];
$theme = $_COOKIE['logycab_theme'] ?? 'theme-0';
if (!in_array($theme, $themes_valides)) $theme = 'theme-0';

$id = (int)($_GET['id'] ?? 0);
$patient = null;
$ordonnances = [];

if ($id > 0) {
    $stmt = $db->prepare("SELECT * FROM ID WHERE [N°PAT] = ?");
    $stmt->execute([$id]);
    $patient = $stmt->fetch();

    if ($patient) {
        $stmtOrd = $db->prepare("SELECT n_ordon, date_ordon, acte1 FROM ORD WHERE id = ? ORDER BY date_ordon DESC");
        $stmtOrd->execute([$id]);
        $ordonnances = $stmtOrd->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Logycab — Impression</title>
<link rel="stylesheet" href="themes.css">
<style>
* { box-sizing:border-box; margin:0; padding:0; }
body { font-family:Arial,sans-serif; font-size:12px; background:var(--th-bg-page); color:var(--th-color-text); }
.header { background:var(--th-bg-header); color:white; padding:8px 16px; display:flex; align-items:center; gap:10px; }
.logo-block { display:flex; align-items:center; gap:8px; }
.heart { font-size:22px; color:#ff6b6b; }
.nom-logo { font-size:16px; font-weight:700; letter-spacing:1px; }
.sub { font-size:10px; opacity:0.75; }
.bh { padding:4px 10px; border-radius:4px; font-size:11px; font-weight:bold; text-decoration:none; color:white; border:none; cursor:pointer; white-space:nowrap; }
.bh-red{background:#c0392b;} .bh-navy{background:#1a4a7a;} .bh-blue{background:#2e6da4;}
.bh-orange{background:#e67e22;} .bh-purple{background:#8e44ad;} .bh-green{background:#27ae60;}
.btn-search{background:rgba(255,255,255,0.15);color:white;padding:4px 10px;border-radius:4px;text-decoration:none;font-size:13px;}
.header-clock { background:rgba(255,255,255,0.12); border-radius:6px; padding:4px 10px; text-align:center; }
.header-clock .ct { font-size:15px; font-weight:bold; color:white; }
.header-clock .cd { font-size:9px; opacity:0.75; }

.page { padding:16px; max-width:820px; margin:0 auto; }
.carte { background:var(--th-bg-card); border-radius:8px; padding:16px; box-shadow:0 1px 4px rgba(0,0,0,0.1); }
.carte-titre { font-size:16px; font-weight:bold; color:var(--th-color-primary); margin-bottom:12px; }

.zrech { display:flex; gap:8px; margin-bottom:16px; }
.zrech input { flex:1; padding:8px; border:1px solid #ccc; border-radius:4px; font-size:13px; }
.zrech button { background:#1a4a7a; color:white; border:none; padding:8px 16px; border-radius:4px; cursor:pointer; font-size:13px; }

.patient-bar { display:flex; gap:20px; background:var(--th-bg-card); border-bottom:2px solid var(--th-color-primary); padding:8px 16px; margin-bottom:14px; border-radius:4px; }
.patient-bar .info label { font-size:10px; color:#888; display:block; }
.patient-bar .info span { font-size:13px; font-weight:bold; }

table.doc { width:100%; border-collapse:collapse; font-size:12px; margin-top:8px; }
table.doc thead { background:#1a4a7a; color:white; }
table.doc th, table.doc td { padding:6px 8px; }
table.doc tbody tr { border-bottom:1px solid #eee; }
.btn-imp { background:#1a4a7a; color:white; border:none; padding:4px 10px; border-radius:3px; font-size:11px; text-decoration:none; cursor:pointer; }
</style>
</head>
<body class="<?= htmlspecialchars($theme) ?>">
<script src="home.js"></script>

<div class="header">
    <div class="logo-block">
        <span class="heart">❤</span>
        <div>
            <div class="nom-logo">LOGYCAB</div>
            <div class="sub"><?= $nbPatientsG ?> RDV aujourd'hui / <?= $nbrMaxG ?> prévus</div>
        </div>
    </div>
    <a href="recherche.php" class="btn-search" title="Recherche">🔍</a>
    <div style="flex:1;"></div>
    <a href="index.php" class="bh bh-red">🏠 Accueil</a>
    <a href="agenda.php" class="bh bh-navy">📅 Agenda</a>
    <a href="planning.php" class="bh bh-blue">📊 Planning</a>
    <a href="grille_semaine.php" class="bh bh-blue">📋 Grille</a>
    <a href="biologie.php<?= $id ? '?id='.$id : '' ?>" class="bh bh-orange">🧪 Biologie</a>
    <a href="facturation.php<?= $id ? '?id='.$id : '' ?>" class="bh bh-green">🧾 Facturation</a>
    <span class="bh" style="background:#555;">🖨️ Impression</span>
    <a href="jours_feries.php" class="bh bh-purple">📅 Fériés</a>
    <div class="header-clock">
        <div class="ct" id="clockTime">--:--:--</div>
        <div class="cd" id="clockDate">---</div>
    </div>
    <a href="logout.php" class="bh bh-red" title="Déconnexion">⏻</a>
</div>

<div class="page">
<div class="carte">
<div class="carte-titre">🖨️ Impression</div>

<div class="zrech">
    <input type="text" id="rech-npat" placeholder="N° patient..." value="<?= $id ?: '' ?>"
           onkeydown="if(event.key==='Enter') allerPatient()">
    <button onclick="allerPatient()">🔍 Charger</button>
</div>

<?php if (!$patient): ?>
    <p style="color:var(--th-color-text-muted);">Entrez un numéro de patient, ou utilisez la <a href="recherche.php">recherche</a> pour trouver un patient par nom.</p>
<?php else: ?>

    <div class="patient-bar">
        <div class="info"><label>N°</label><span><?= $patient['N°PAT'] ?></span></div>
        <div class="info"><label>Nom</label><span><?= htmlspecialchars($patient['NOMPRENOM']) ?></span></div>
        <div class="info"><label>Tel</label><span><?= htmlspecialchars($patient['TEL D'] ?? '—') ?></span></div>
    </div>

    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px;">
        <strong style="font-size:13px;color:var(--th-color-primary);">Ordonnances</strong>
        <a href="biologie.php?id=<?= $id ?>" class="btn-imp" style="background:#e67e22;">🧪 Aller à la Biologie (bilan à faire)</a>
    </div>

    <?php if ($ordonnances): ?>
    <table class="doc">
        <thead><tr><th style="text-align:left;">Date</th><th style="text-align:left;">Acte</th><th style="text-align:center;">Imprimer</th></tr></thead>
        <tbody>
        <?php foreach ($ordonnances as $o): ?>
            <?php $ts = strtotime($o['date_ordon'] ?? ''); $dOrd = ($ts && $ts > 86400) ? date('d/m/Y', $ts) : '—'; ?>
            <tr>
                <td><?= $dOrd ?></td>
                <td><?= htmlspecialchars($o['acte1'] ?? '—') ?></td>
                <td style="text-align:center;">
                    <a href="print_ordonnance.php?id=<?= $id ?>&ord=<?= $o['n_ordon'] ?>" target="_blank" class="btn-imp">🖨️ Ordonnance</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
        <p style="color:var(--th-color-text-muted);margin-top:8px;">Aucune ordonnance enregistrée pour ce patient.</p>
    <?php endif; ?>

<?php endif; ?>
</div>
</div>

<script>
function allerPatient() {
    const v = document.getElementById('rech-npat').value.trim();
    if (v) window.location.href = 'impression.php?id=' + encodeURIComponent(v);
}
</script>
</body>
</html>
