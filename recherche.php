<?php
require_once 'backend/db.php';
$db = getDB();

// Thème
$themes_valides = ['theme-0','theme-a','theme-b','theme-c'];
$theme = $_COOKIE['logycab_theme'] ?? 'theme-0';
if (!in_array($theme, $themes_valides)) $theme = 'theme-0';

// Compteur RDV du jour / NbrMax (pour le bloc logo)
$nbRdvAujourd = $db->query("SELECT COUNT(*) FROM ORD WHERE CONVERT(date,[DATE REDEZ VOUS])=CONVERT(date,GETDATE()) OR CONVERT(date,Date_Rdv)=CONVERT(date,GETDATE())")->fetchColumn();
$nbrMax = 20;
try {
    $stmtMax = $db->prepare("SELECT Valeur FROM T_Config WHERE Cle='NbrMax'");
    $stmtMax->execute();
    $rowMax = $stmtMax->fetch(PDO::FETCH_ASSOC);
    if ($rowMax) $nbrMax = (int)$rowMax['Valeur'];
} catch (Exception $e) {}

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
<link rel="stylesheet" href="themes.css">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Arial, sans-serif; background: var(--th-bg-page); color: var(--th-color-text); }
.header { background: var(--th-bg-header-s); color: white; padding: 12px 20px; display: flex; align-items: center; gap: 15px; }
.header a { color: white; text-decoration: none; background: var(--th-btn-blue); padding: 6px 14px; border-radius: 4px; font-size: 14px; }
.header h1 { font-size: 18px; }
.container { padding: 20px; max-width: 900px; margin: 0 auto; }
.search-box { background: var(--th-bg-card); border-radius: 8px; padding: 20px; margin-bottom: 16px; box-shadow: 0 2px 8px var(--th-border-card); }
.search-box h2 { color: var(--th-color-primary); margin-bottom: 12px; font-size: 16px; }
.search-row { display: flex; gap: 10px; }
.search-row input { flex: 1; padding: 10px 14px; border: 2px solid var(--th-color-secondary); border-radius: 4px; font-size: 15px; background: var(--th-bg-card); color: var(--th-color-text); }
.search-row button { background: var(--th-btn-navy); color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-size: 15px; }
.search-row button:hover { background: var(--th-btn-blue); }
.search-hdr { padding: 2px 8px; border-radius: 4px; font-size: 11px; height: 26px;
    border: 1px solid rgba(255,255,255,0.35); background: rgba(255,255,255,0.12);
    color: white; outline: none; width: 190px; flex-shrink: 0; }
.search-hdr::placeholder { color: rgba(255,255,255,0.5); }
.search-hdr:focus { border-color: rgba(255,255,255,0.7); background: rgba(255,255,255,0.2); }
@keyframes heartbeat {
    0%,100% { transform: scale(1); }
    14%     { transform: scale(1.2); }
    28%     { transform: scale(1); }
    42%     { transform: scale(1.15); }
    56%     { transform: scale(1); }
}
.heart { display: inline-block; animation: heartbeat 1.6s infinite; color: #e74c3c; font-size: 20px; }
.logo-block { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }
.logo-block .nom-logo { font-size: 16px; font-weight: 900; letter-spacing: 1px; color: #fff; line-height: 1.1; }
.logo-block .sub { font-size: 9px; opacity: 0.85; color: #fff; white-space: nowrap; }
.header-clock { background: rgba(255,255,255,0.12); border-radius: 6px;
                padding: 3px 10px; text-align: center; min-width: 130px; flex-shrink: 0; }
.header-clock .ct { font-size: 15px; font-weight: bold; letter-spacing: 1px; color: white; }
.header-clock .cd { font-size: 9px; opacity: 0.75; }
table { width: 100%; border-collapse: collapse; background: var(--th-bg-card); border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px var(--th-border-card); }
thead { background: var(--th-bg-header-s); color: white; }
thead th { padding: 10px 12px; text-align: left; font-size: 13px; }
tbody tr { border-bottom: 1px solid var(--th-sep-color); cursor: pointer; }
tbody tr:hover { background: var(--th-bg-link-hover); }
tbody td { padding: 10px 12px; font-size: 13px; }
.nb { color: var(--th-color-text-muted); font-size: 13px; margin-bottom: 10px; }
a.lien-patient { color: var(--th-color-primary); text-decoration: none; font-weight: bold; }
</style>
</head>
<body class="<?= htmlspecialchars($theme) ?>">
<script src="home.js"></script>
<div class="header">
    <!-- GAUCHE : logo + cœur animé + compteur RDV du jour -->
    <div class="logo-block">
        <span class="heart">❤</span>
        <div>
            <div class="nom-logo">LOGYCAB</div>
            <div class="sub"><?= $nbRdvAujourd ?> RDV aujourd'hui / <?= $nbrMax ?> prévus</div>
        </div>
    </div>
    <!-- Recherche rapide -->
    <input class="search-hdr" type="text" placeholder="🔍 Rechercher patient..."
           onkeydown="if(event.key==='Enter'&&this.value.trim()) location.href='recherche.php?q='+encodeURIComponent(this.value.trim())">
    <!-- Espace flexible : pousse les boutons/horloge/déconnexion à droite -->
    <div style="flex:1;"></div>
    <a href="index.php" style="background:#c0392b;">🏠 Accueil</a>
    <a href="#" onclick="goHome();return false;" style="background:#27ae60;">🏠 Dossier</a>
    <a href="#" onclick="voirApercu();return false;" style="background:#27ae60;font-weight:bold;">📋 Aperçu</a>
    <a href="agenda.php" style="background:var(--th-btn-navy);">📅 Agenda</a>
    <a href="planning.php" style="background:var(--th-btn-blue);">📊 Planning</a>
    <a href="grille_semaine.php" style="background:var(--th-btn-blue);">📋 Grille</a>
    <a href="#" onclick="voirBiologie();return false;" style="background:#e67e22;">🧪 Biologie</a>
    <a href="jours_feries.php" style="background:#8e44ad;">📅 Fériés</a>
    <div class="header-clock">
        <div class="ct" id="clockTime">--:--:--</div>
        <div class="cd" id="clockDate">---</div>
    </div>
    <a href="logout.php" style="background:#e74c3c;" title="Déconnexion">⏻</a>
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
            <p style="color:var(--th-color-text-muted);text-align:center;padding:30px;">Aucun patient trouvé pour "<?= htmlspecialchars($q) ?>"</p>
        <?php endif; ?>
    <?php endif; ?>
</div>
<script>
(function() {
    const jours = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
    const mois  = ['Jan','Fév','Mar','Avr','Mai','Juin','Juil','Aoû','Sep','Oct','Nov','Déc'];
    function tick() {
        const n  = new Date();
        const h  = String(n.getHours()).padStart(2,'0');
        const m  = String(n.getMinutes()).padStart(2,'0');
        const s  = String(n.getSeconds()).padStart(2,'0');
        const ct = document.getElementById('clockTime');
        const cd = document.getElementById('clockDate');
        if (ct) ct.textContent = h+':'+m+':'+s;
        if (cd) cd.textContent = jours[n.getDay()]+' '+n.getDate()+' '+mois[n.getMonth()]+' '+n.getFullYear();
    }
    tick();
    setInterval(tick, 1000);
})();
</script>
</body>
</html>