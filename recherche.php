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

// Lit une date française jj/mm/aaaa ou un format ISO aaaa-mm-jj, sinon null
function parseDateFr($v) {
    $v = trim($v);
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $v, $m)) return $m[3] . '-' . $m[2] . '-' . $m[1];
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) return $v;
    return null;
}

// ── Lecture des 5 groupes + connecteurs ─────────────────────────
$g1_val = trim($_GET['g1_val'] ?? '');
$g1_mod = trim($_GET['g1_mod'] ?? '');
if ($g1_mod === '') $g1_mod = is_numeric($g1_val) ? 'egal' : 'contient'; // comportement historique par défaut

$g2_mod = trim($_GET['g2_mod'] ?? 'exact');
$g2_d1  = trim($_GET['g2_d1'] ?? '');
$g2_d2  = trim($_GET['g2_d2'] ?? '');

$g3_mod  = trim($_GET['g3_mod'] ?? 'egal');
$g3_val  = trim($_GET['g3_val'] ?? '');
$g3_val2 = trim($_GET['g3_val2'] ?? '');

$g4_mod = trim($_GET['g4_mod'] ?? 'contient');
$g4_val = trim($_GET['g4_val'] ?? '');

$g5_mod = trim($_GET['g5_mod'] ?? 'egal');
$g5_val = trim($_GET['g5_val'] ?? '');

$conn2 = ($_GET['conn2'] ?? 'et') === 'ou' ? 'OU' : 'ET';
$conn3 = ($_GET['conn3'] ?? 'et') === 'ou' ? 'OU' : 'ET';
$conn4 = ($_GET['conn4'] ?? 'et') === 'ou' ? 'OU' : 'ET';
$conn5 = ($_GET['conn5'] ?? 'et') === 'ou' ? 'OU' : 'ET';

$action = trim($_GET['action'] ?? '');

// Conserve la compatibilité avec tous les liens existants de l'appli (recherche.php?q=...)
$qHeritage = trim($_GET['q'] ?? '');
if ($qHeritage !== '' && $g1_val === '') {
    $g1_val = $qHeritage;
    $g1_mod = is_numeric($g1_val) ? 'egal' : 'contient';
}

$groupes = [];
$needsOrd = false;

// Groupe 1 — Identité (nom/prénom ou N° patient)
if (strlen($g1_val) >= 1) {
    $numerique = is_numeric($g1_val);
    $champ = $numerique ? "CAST(i.[N°PAT] AS VARCHAR(20))" : "i.NOMPRENOM";
    switch ($g1_mod) {
        case 'commence': $sql = "$champ LIKE ?"; $val = $g1_val . '%'; break;
        case 'contient': $sql = "$champ LIKE ?"; $val = '%' . $g1_val . '%'; break;
        case 'termine':  $sql = "$champ LIKE ?"; $val = '%' . $g1_val; break;
        default:         $sql = "$champ = ?";     $val = $g1_val;
    }
    $groupes[] = ['sql' => $sql, 'params' => [$val], 'libelle' => 'identité « ' . htmlspecialchars($g1_val) . ' »', 'conn' => null];
}

// Groupe 2 — Date de consultation (passée ou à venir, combine les 2 champs RDV)
if ($g2_mod === 'entre' && $g2_d1 && $g2_d2) {
    $needsOrd = true;
    $sql = "(CONVERT(date,o.[DATE REDEZ VOUS]) BETWEEN ? AND ? OR CONVERT(date,o.Date_Rdv) BETWEEN ? AND ?)";
    $groupes[] = ['sql' => $sql, 'params' => [$g2_d1, $g2_d2, $g2_d1, $g2_d2], 'libelle' => 'consultation entre le ' . date('d/m/Y', strtotime($g2_d1)) . ' et le ' . date('d/m/Y', strtotime($g2_d2)), 'conn' => $conn2];
} elseif (($g2_mod === 'avant' || $g2_mod === 'apres') && $g2_d1) {
    $needsOrd = true;
    $op = $g2_mod === 'avant' ? '<' : '>';
    $sql = "(CONVERT(date,o.[DATE REDEZ VOUS]) $op ? OR CONVERT(date,o.Date_Rdv) $op ?)";
    $groupes[] = ['sql' => $sql, 'params' => [$g2_d1, $g2_d1], 'libelle' => 'consultation ' . ($g2_mod === 'avant' ? 'avant' : 'après') . ' le ' . date('d/m/Y', strtotime($g2_d1)), 'conn' => $conn2];
} elseif ($g2_mod === 'exact' && $g2_d1) {
    $needsOrd = true;
    $sql = "(CONVERT(date,o.[DATE REDEZ VOUS]) = ? OR CONVERT(date,o.Date_Rdv) = ?)";
    $groupes[] = ['sql' => $sql, 'params' => [$g2_d1, $g2_d1], 'libelle' => 'consultation le ' . date('d/m/Y', strtotime($g2_d1)), 'conn' => $conn2];
}

// Groupe 3 — Âge ou date de naissance (le champ accepte un nombre OU une date jj/mm/aaaa)
$g3_estDate  = parseDateFr($g3_val) !== null;
$g3_estAge   = $g3_val !== '' && is_numeric($g3_val) && !$g3_estDate;
if ($g3_estAge || $g3_estDate) {
    if ($g3_estAge) {
        $age = (int)$g3_val;
        switch ($g3_mod) {
            case 'plus':  $sql = "i.DDN <= DATEADD(YEAR,-CAST(? AS INT),CONVERT(date,GETDATE()))"; $params = [$age]; $lib = 'âge plus de ' . $age . ' ans'; break;
            case 'moins': $sql = "i.DDN >  DATEADD(YEAR,-CAST(? AS INT),CONVERT(date,GETDATE()))"; $params = [$age]; $lib = 'âge moins de ' . $age . ' ans'; break;
            case 'entre':
                if ($g3_val2 === '' || !is_numeric($g3_val2)) { $sql = null; break; }
                $age2 = (int)$g3_val2;
                if ($age2 < $age) { $tmp = $age; $age = $age2; $age2 = $tmp; }
                $sql = "i.DDN <= DATEADD(YEAR,-CAST(? AS INT),CONVERT(date,GETDATE())) AND i.DDN > DATEADD(YEAR,-CAST(? AS INT),CONVERT(date,GETDATE()))";
                $params = [$age, $age2 + 1]; $lib = 'âge entre ' . $age . ' et ' . $age2 . ' ans';
                break;
            default: // egal
                $sql = "i.DDN <= DATEADD(YEAR,-CAST(? AS INT),CONVERT(date,GETDATE())) AND i.DDN > DATEADD(YEAR,-CAST(? AS INT),CONVERT(date,GETDATE()))";
                $params = [$age, $age + 1]; $lib = 'âge ' . $age . ' ans';
        }
    } else {
        $ddn1 = parseDateFr($g3_val);
        switch ($g3_mod) {
            case 'plus':  $sql = "CONVERT(date,i.DDN) > ?"; $params = [$ddn1]; $lib = 'né(e) après le ' . date('d/m/Y', strtotime($ddn1)); break;
            case 'moins': $sql = "CONVERT(date,i.DDN) < ?"; $params = [$ddn1]; $lib = 'né(e) avant le ' . date('d/m/Y', strtotime($ddn1)); break;
            case 'entre':
                $ddn2 = parseDateFr($g3_val2);
                if ($ddn2 !== null) {
                    if ($ddn2 < $ddn1) { $tmp = $ddn1; $ddn1 = $ddn2; $ddn2 = $tmp; }
                    $sql = "CONVERT(date,i.DDN) BETWEEN ? AND ?"; $params = [$ddn1, $ddn2];
                    $lib = 'né(e) entre le ' . date('d/m/Y', strtotime($ddn1)) . ' et le ' . date('d/m/Y', strtotime($ddn2));
                } else { $sql = null; }
                break;
            default: // egal
                $sql = "CONVERT(date,i.DDN) = ?"; $params = [$ddn1]; $lib = 'né(e) le ' . date('d/m/Y', strtotime($ddn1));
        }
    }
    if (!empty($sql)) {
        $groupes[] = ['sql' => $sql, 'params' => $params, 'libelle' => $lib, 'conn' => $conn3];
    }
}

// Groupe 4 — Téléphone
if (strlen($g4_val) >= 2) {
    $sql = $g4_mod === 'egal' ? "i.[TEL D] = ?" : "i.[TEL D] LIKE ?";
    $val = $g4_mod === 'egal' ? $g4_val : '%' . $g4_val . '%';
    $groupes[] = ['sql' => $sql, 'params' => [$val], 'libelle' => 'téléphone « ' . htmlspecialchars($g4_val) . ' »', 'conn' => $conn4];
}

// Groupe 5 — CIN
if (strlen($g5_val) >= 1) {
    $sql = $g5_mod === 'egal' ? "i.CIN = ?" : "i.CIN LIKE ?";
    $val = $g5_mod === 'egal' ? $g5_val : '%' . $g5_val . '%';
    $groupes[] = ['sql' => $sql, 'params' => [$val], 'libelle' => 'CIN « ' . htmlspecialchars($g5_val) . ' »', 'conn' => $conn5];
}

// ── Assemblage gauche à droite ───────────────────────────────────
$patients = [];
$rechercheLancee = !empty($groupes);
$libelleRecherche = '';
if ($rechercheLancee) {
    $whereSql = '';
    $whereParams = [];
    $libelles = [];
    foreach ($groupes as $i => $g) {
        if ($i === 0) {
            $whereSql = $g['sql'];
        } else {
            $whereSql = '(' . $whereSql . ') ' . ($g['conn'] === 'OU' ? 'OR' : 'AND') . ' (' . $g['sql'] . ')';
        }
        $whereParams = array_merge($whereParams, $g['params']);
        if ($i > 0) $libelles[] = ($g['conn'] === 'OU' ? 'ou' : 'et') . ' ' . $g['libelle'];
        else $libelles[] = $g['libelle'];
    }
    $libelleRecherche = implode(' ', $libelles);

    $sqlFrom = $needsOrd
        ? "FROM ID i INNER JOIN ORD o ON o.id = i.[N°PAT]"
        : "FROM ID i";
    $sql = "SELECT DISTINCT TOP 50 i.[N°PAT], i.NOMPRENOM, i.[TEL D], i.MUTUELLE $sqlFrom WHERE $whereSql ORDER BY i.NOMPRENOM";
    $stmt = $db->prepare($sql);
    $stmt->execute($whereParams);
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
.adv-input { padding: 7px 10px; border: 2px solid var(--th-color-secondary); border-radius: 4px;
             font-size: 13px; background: var(--th-bg-card); color: var(--th-color-text); }
.adv-input[type="date"] { cursor: pointer; }
.adv-sep { font-size: 12px; color: var(--th-color-text-muted); }
.adv-bar { display: flex; align-items: flex-end; gap: 10px; flex-wrap: wrap; }
.adv-grp { display: flex; flex-direction: column; gap: 6px; }
.adv-label { font-size: 11px; color: var(--th-color-text-muted); font-weight: bold; }
.adv-mod { padding: 7px 8px; border: 1px solid var(--th-color-secondary); border-radius: 4px;
           font-size: 12px; background: var(--th-bg-card); color: var(--th-color-text); cursor: pointer; }
.adv-conn { padding: 6px 4px; border: none; border-radius: 4px; font-size: 12px; font-weight: bold;
            background: var(--th-btn-navy); color: white; cursor: pointer; flex-shrink: 0;
            margin-bottom: 6px; width: 56px; text-align: center; }
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
           ondblclick="location.href='recherche.php'+(this.value.trim()?('?q='+encodeURIComponent(this.value.trim())):'')"
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
        <form method="GET" id="form-recherche">
            <?php if ($action): ?><input type="hidden" name="action" value="<?= htmlspecialchars($action) ?>"><?php endif; ?>
            <div class="adv-bar">

                <div class="adv-grp">
                    <label class="adv-label">Identité</label>
                    <select name="g1_mod" class="adv-mod">
                        <option value="egal"     <?= $g1_mod==='egal'?'selected':'' ?>>Égal à</option>
                        <option value="commence" <?= $g1_mod==='commence'?'selected':'' ?>>Commence par</option>
                        <option value="contient" <?= $g1_mod==='contient'?'selected':'' ?>>Contient</option>
                        <option value="termine"  <?= $g1_mod==='termine'?'selected':'' ?>>Se termine par</option>
                    </select>
                    <input type="text" name="g1_val" class="adv-input" style="width:170px;"
                           value="<?= htmlspecialchars($g1_val) ?>" placeholder="Nom, prénom ou N°">
                </div>

                <select name="conn2" class="adv-conn">
                    <option value="et" <?= $conn2==='ET'?'selected':'' ?>>et</option>
                    <option value="ou" <?= $conn2==='OU'?'selected':'' ?>>ou</option>
                </select>

                <div class="adv-grp">
                    <label class="adv-label">Date de consultation</label>
                    <select name="g2_mod" id="g2-mod" class="adv-mod" onchange="majG2()">
                        <option value="exact" <?= $g2_mod==='exact'?'selected':'' ?>>À cette date</option>
                        <option value="avant" <?= $g2_mod==='avant'?'selected':'' ?>>Avant le</option>
                        <option value="apres" <?= $g2_mod==='apres'?'selected':'' ?>>Après le</option>
                        <option value="entre" <?= $g2_mod==='entre'?'selected':'' ?>>Entre deux dates</option>
                    </select>
                    <input type="date" name="g2_d1" class="adv-input" value="<?= htmlspecialchars($g2_d1) ?>">
                    <span class="adv-sep" id="g2-et" style="display:none;">et le</span>
                    <input type="date" name="g2_d2" id="g2-d2" class="adv-input" value="<?= htmlspecialchars($g2_d2) ?>" style="display:none;">
                </div>

                <select name="conn3" class="adv-conn">
                    <option value="et" <?= $conn3==='ET'?'selected':'' ?>>et</option>
                    <option value="ou" <?= $conn3==='OU'?'selected':'' ?>>ou</option>
                </select>

                <div class="adv-grp">
                    <label class="adv-label">Âge ou naissance</label>
                    <select name="g3_mod" id="g3-mod" class="adv-mod" onchange="majG3()">
                        <option value="egal"  <?= $g3_mod==='egal'?'selected':'' ?>>Égal à</option>
                        <option value="plus"  <?= $g3_mod==='plus'?'selected':'' ?>>Plus de</option>
                        <option value="moins" <?= $g3_mod==='moins'?'selected':'' ?>>Moins de</option>
                        <option value="entre" <?= $g3_mod==='entre'?'selected':'' ?>>Entre</option>
                    </select>
                    <input type="text" name="g3_val" class="adv-input" style="width:140px;"
                           value="<?= htmlspecialchars($g3_val) ?>" placeholder="ex: 55 ou 12/05/1970">
                    <span class="adv-sep" id="g3-et" style="display:none;">et</span>
                    <input type="text" name="g3_val2" id="g3-val2" class="adv-input" style="width:140px;display:none;"
                           value="<?= htmlspecialchars($g3_val2) ?>" placeholder="ex: 65 ou 12/05/1980">
                </div>

                <select name="conn4" class="adv-conn">
                    <option value="et" <?= $conn4==='ET'?'selected':'' ?>>et</option>
                    <option value="ou" <?= $conn4==='OU'?'selected':'' ?>>ou</option>
                </select>

                <div class="adv-grp">
                    <label class="adv-label">Téléphone</label>
                    <select name="g4_mod" class="adv-mod">
                        <option value="contient" <?= $g4_mod==='contient'?'selected':'' ?>>Contient</option>
                        <option value="egal"     <?= $g4_mod==='egal'?'selected':'' ?>>Égal à</option>
                    </select>
                    <input type="text" name="g4_val" class="adv-input" style="width:130px;"
                           value="<?= htmlspecialchars($g4_val) ?>" placeholder="06...">
                </div>

                <select name="conn5" class="adv-conn">
                    <option value="et" <?= $conn5==='ET'?'selected':'' ?>>et</option>
                    <option value="ou" <?= $conn5==='OU'?'selected':'' ?>>ou</option>
                </select>

                <div class="adv-grp">
                    <label class="adv-label">CIN</label>
                    <select name="g5_mod" class="adv-mod">
                        <option value="egal"     <?= $g5_mod==='egal'?'selected':'' ?>>Égal à</option>
                        <option value="contient" <?= $g5_mod==='contient'?'selected':'' ?>>Contient</option>
                    </select>
                    <input type="text" name="g5_val" class="adv-input" style="width:130px;"
                           value="<?= htmlspecialchars($g5_val) ?>" placeholder="AB123456">
                </div>

            </div>
            <div style="margin-top:12px;text-align:right;">
                <button type="submit">🔍 Rechercher</button>
            </div>
        </form>
    </div>
    <?php if ($rechercheLancee): ?>
        <p class="nb"><?= count($patients) ?> résultat(s) pour <?= $libelleRecherche ?></p>
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
            <p style="color:var(--th-color-text-muted);text-align:center;padding:30px;">Aucun patient trouvé pour <?= $libelleRecherche ?></p>
        <?php endif; ?>
    <?php endif; ?>
</div>
<script>
function majG2() {
    const montrer = document.getElementById('g2-mod').value === 'entre';
    document.getElementById('g2-d2').style.display = montrer ? 'inline-block' : 'none';
    document.getElementById('g2-et').style.display  = montrer ? 'inline' : 'none';
}
function majG3() {
    const montrer = document.getElementById('g3-mod').value === 'entre';
    document.getElementById('g3-val2').style.display = montrer ? 'inline-block' : 'none';
    document.getElementById('g3-et').style.display    = montrer ? 'inline' : 'none';
}
majG2();
majG3();
</script>
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