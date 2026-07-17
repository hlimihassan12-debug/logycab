<?php
require_once __DIR__ . '/backend/auth.php';

// Sauvegarde du thème choisi (POST)
if (!empty($_POST['theme'])) {
    $themes_valides = ['theme-0','theme-a','theme-b','theme-c'];
    $choix = in_array($_POST['theme'], $themes_valides) ? $_POST['theme'] : 'theme-0';
    setcookie('logycab_theme', $choix, time() + (365 * 24 * 3600), '/');
    header('Location: parametres.php?ok=1');
    exit;
}

// Sauvegarde des infos du cabinet / horaires (site public)
if (isset($_POST['form']) && $_POST['form'] === 'cabinet') {
    $db = getDB();

    function upsertConfig(PDO $db, string $cle, string $valeur): void {
        $valeur = mb_substr($valeur, 0, 100); // T_Config.Valeur est NVARCHAR(100)
        $stmt = $db->prepare("UPDATE T_Config SET Valeur = ? WHERE Cle = ?");
        $stmt->execute([$valeur, $cle]);
        if ($stmt->rowCount() === 0) {
            $db->prepare("INSERT INTO T_Config (Cle, Valeur) VALUES (?, ?)")->execute([$cle, $valeur]);
        }
    }

    $champsCabinet = ['Cabinet_Nom', 'Cabinet_Adresse', 'Cabinet_Telephone', 'Cabinet_Email', 'Cabinet_Description'];
    foreach ($champsCabinet as $cle) {
        upsertConfig($db, $cle, trim($_POST[$cle] ?? ''));
    }

    $jours = ['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche'];
    foreach ($jours as $jour) {
        upsertConfig($db, 'Horaire_' . $jour, trim($_POST['Horaire_' . $jour] ?? ''));
    }

    header('Location: parametres.php?ok=2');
    exit;
}

$theme_actuel = $_COOKIE['logycab_theme'] ?? 'theme-0';
$ok = isset($_GET['ok']) ? (int)$_GET['ok'] : 0;

// Charge les infos cabinet / horaires actuelles
$db = getDB();
$cabinetCfg = [];
try {
    $stmtCab = $db->query("SELECT Cle, Valeur FROM T_Config WHERE Cle LIKE 'Cabinet_%' OR Cle LIKE 'Horaire_%'");
    while ($row = $stmtCab->fetch(PDO::FETCH_ASSOC)) {
        $cabinetCfg[$row['Cle']] = $row['Valeur'];
    }
} catch (Exception $e) {}
$joursSemaine = ['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Logycab — Paramètres</title>
<link rel="stylesheet" href="themes.css">
<style>
* { box-sizing:border-box; margin:0; padding:0; }
body {
    font-family: var(--th-font-body, Arial, sans-serif);
    font-size: 12px;
    background: var(--th-bg-page);
    color: var(--th-color-text);
    min-height: 100vh;
}
.header {
    background: var(--th-bg-header);
    color: white;
    padding: 8px 16px;
    display: flex;
    align-items: center;
    gap: 12px;
}
.header-title {
    font-size: 16px;
    font-weight: 700;
    font-family: var(--th-font-logo);
    letter-spacing: var(--th-logo-spacing);
    color: var(--th-color-accent, white);
}
.btn-retour {
    margin-left: auto;
    background: var(--th-btn-grey);
    color: white;
    border: none;
    border-radius: 4px;
    padding: 5px 14px;
    font-size: 11px;
    font-weight: bold;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
}
.page-body { max-width: 700px; margin: 30px auto; padding: 0 20px; }
.section-titre {
    font-size: 14px;
    font-weight: bold;
    color: var(--th-color-primary);
    border-bottom: 2px solid var(--th-border-statsbar);
    padding-bottom: 6px;
    margin-bottom: 20px;
}
.msg-ok {
    background: #d5f0e0;
    color: #1a7a40;
    border-left: 4px solid #27ae60;
    padding: 8px 14px;
    border-radius: 4px;
    margin-bottom: 18px;
    font-size: 12px;
}
.themes-grille {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
    margin-bottom: 28px;
}
.theme-card {
    border: 2px solid transparent;
    border-radius: 8px;
    overflow: hidden;
    cursor: pointer;
    transition: border-color 0.15s, box-shadow 0.15s;
    background: white;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}
.theme-card:hover { box-shadow: 0 4px 14px rgba(0,0,0,0.15); }
.theme-card.actif { border-color: #27ae60; box-shadow: 0 0 0 3px rgba(39,174,96,0.25); }
.theme-preview-header {
    height: 38px;
    display: flex;
    align-items: center;
    padding: 0 12px;
    gap: 8px;
}
.theme-preview-logo { font-size: 13px; font-weight: 700; color: white; letter-spacing: 2px; }
.theme-preview-dot { width: 8px; height: 8px; border-radius: 50%; background: rgba(255,255,255,0.5); margin-left: auto; }
.theme-preview-body { padding: 10px 12px; background: white; }
.theme-preview-bar { height: 5px; border-radius: 3px; margin-bottom: 5px; }
.theme-preview-bar.w100 { width: 100%; }
.theme-preview-bar.w70  { width: 70%; }
.theme-preview-bar.w50  { width: 50%; }
.theme-info { padding: 8px 12px; border-top: 1px solid #f0f0f0; display: flex; align-items: center; justify-content: space-between; }
.theme-nom { font-size: 12px; font-weight: bold; color: #333; }
.theme-tag { font-size: 10px; color: #888; }
.badge-actif { background: #27ae60; color: white; font-size: 9px; padding: 2px 7px; border-radius: 10px; font-weight: bold; }
.btn-appliquer {
    display: block;
    width: 100%;
    padding: 10px;
    border: none;
    border-radius: 5px;
    font-size: 13px;
    font-weight: bold;
    cursor: pointer;
    background: var(--th-color-primary);
    color: white;
    margin-top: 6px;
    transition: opacity 0.15s;
}
.btn-appliquer:hover { opacity: 0.87; }

/* Aperçus spécifiques */
.prev-0-h { background: linear-gradient(135deg, #1a4a7a, #2e6da4); }
.prev-0-b1 { background: #2e6da4; }
.prev-0-b2 { background: #27ae60; }
.prev-0-b3 { background: #8e44ad; }

.prev-a-h { background: linear-gradient(135deg, #0d2b4e, #1a5276); }
.prev-a-b1 { background: #8b1a2e; }
.prev-a-b2 { background: #1a5276; }
.prev-a-b3 { background: #27ae60; }

.prev-b-h { background: linear-gradient(135deg, #1a1a2e, #16213e); }
.prev-b-body { background: #0f0f1e; }
.prev-b-b1 { background: #00d4aa; }
.prev-b-b2 { background: #0099cc; }
.prev-b-b3 { background: #6655dd; }

.prev-c-h { background: linear-gradient(135deg, #2c3e50, #34495e); }
.prev-c-body { background: #fdf9f0; }
.prev-c-b1 { background: #c0973a; }
.prev-c-b2 { background: #2c3e50; }
.prev-c-b3 { background: #5d9b6b; }

/* Formulaire infos cabinet / horaires */
.champ-param { margin-bottom: 14px; }
.champ-param label { display: block; font-size: 11px; font-weight: bold; color: var(--th-color-text-muted); margin-bottom: 4px; }
.champ-param input, .champ-param textarea {
    width: 100%; padding: 8px 10px; border: 1px solid var(--th-border-statsbar);
    border-radius: 4px; font-size: 12px; font-family: inherit; resize: vertical;
}
.champ-param-double { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.horaires-grille { display: grid; grid-template-columns: repeat(2, 1fr); gap: 4px 14px; margin-bottom: 16px; }
@media (max-width: 600px) { .champ-param-double, .horaires-grille { grid-template-columns: 1fr; } }
</style>
</head>
<body class="<?= htmlspecialchars($theme_actuel) ?>">

<div class="header">
    <div class="header-title">⚙ Paramètres — Logycab</div>
    <a href="index.php" class="btn-retour">🏠 Accueil</a>
</div>

<div class="page-body">

    <?php if ($ok === 1): ?>
    <div class="msg-ok">✓ Thème appliqué avec succès. Il sera actif sur toutes les pages.</div>
    <?php elseif ($ok === 2): ?>
    <div class="msg-ok">✓ Informations du cabinet enregistrées. Elles sont maintenant affichées sur le site public.</div>
    <?php endif; ?>

    <div class="section-titre">🎨 Apparence — Choisir un thème</div>

    <form method="POST" action="parametres.php">
    <div class="themes-grille">

        <!-- THÈME 0 -->
        <div class="theme-card <?= $theme_actuel==='theme-0' ? 'actif' : '' ?>">
            <div class="theme-preview-header prev-0-h">
                <div class="theme-preview-logo">LOGYCAB</div>
                <div class="theme-preview-dot"></div>
            </div>
            <div class="theme-preview-body">
                <div class="theme-preview-bar w100 prev-0-b1"></div>
                <div class="theme-preview-bar w70  prev-0-b2"></div>
                <div class="theme-preview-bar w50  prev-0-b3"></div>
            </div>
            <div class="theme-info">
                <div>
                    <div class="theme-nom">Thème 0 — Actuel</div>
                    <div class="theme-tag">Bleu #1a4a7a · original</div>
                </div>
                <?php if ($theme_actuel==='theme-0'): ?>
                <span class="badge-actif">✓ Actif</span>
                <?php endif; ?>
            </div>
            <button type="submit" name="theme" value="theme-0" class="btn-appliquer" style="background:#1a4a7a;">Appliquer</button>
        </div>

        <!-- THÈME A -->
        <div class="theme-card <?= $theme_actuel==='theme-a' ? 'actif' : '' ?>">
            <div class="theme-preview-header prev-a-h">
                <div class="theme-preview-logo" style="font-family:Georgia,serif;">LOGYCAB</div>
                <div class="theme-preview-dot"></div>
            </div>
            <div class="theme-preview-body">
                <div class="theme-preview-bar w100 prev-a-b1"></div>
                <div class="theme-preview-bar w70  prev-a-b2"></div>
                <div class="theme-preview-bar w50  prev-a-b3"></div>
            </div>
            <div class="theme-info">
                <div>
                    <div class="theme-nom">Thème A — Classique Médical</div>
                    <div class="theme-tag">Marine #0d2b4e · Bordeaux #8b1a2e</div>
                </div>
                <?php if ($theme_actuel==='theme-a'): ?>
                <span class="badge-actif">✓ Actif</span>
                <?php endif; ?>
            </div>
            <button type="submit" name="theme" value="theme-a" class="btn-appliquer" style="background:#0d2b4e;">Appliquer</button>
        </div>

        <!-- THÈME B -->
        <div class="theme-card <?= $theme_actuel==='theme-b' ? 'actif' : '' ?>">
            <div class="theme-preview-header prev-b-h">
                <div class="theme-preview-logo" style="font-family:'Courier New',monospace;color:#00d4aa;letter-spacing:4px;">LOGYCAB</div>
                <div class="theme-preview-dot" style="background:#00d4aa;"></div>
            </div>
            <div class="theme-preview-body prev-b-body">
                <div class="theme-preview-bar w100 prev-b-b1"></div>
                <div class="theme-preview-bar w70  prev-b-b2"></div>
                <div class="theme-preview-bar w50  prev-b-b3"></div>
            </div>
            <div class="theme-info">
                <div>
                    <div class="theme-nom">Thème B — Moderne Clinique</div>
                    <div class="theme-tag">Nuit #1a1a2e · Menthe #00d4aa</div>
                </div>
                <?php if ($theme_actuel==='theme-b'): ?>
                <span class="badge-actif">✓ Actif</span>
                <?php endif; ?>
            </div>
            <button type="submit" name="theme" value="theme-b" class="btn-appliquer" style="background:#1a1a2e;">Appliquer</button>
        </div>

        <!-- THÈME C -->
        <div class="theme-card <?= $theme_actuel==='theme-c' ? 'actif' : '' ?>">
            <div class="theme-preview-header prev-c-h">
                <div class="theme-preview-logo" style="font-family:Georgia,serif;letter-spacing:3px;">Logycab</div>
                <div class="theme-preview-dot" style="background:#c0973a;"></div>
            </div>
            <div class="theme-preview-body prev-c-body">
                <div class="theme-preview-bar w100 prev-c-b1"></div>
                <div class="theme-preview-bar w70  prev-c-b2"></div>
                <div class="theme-preview-bar w50  prev-c-b3"></div>
            </div>
            <div class="theme-info">
                <div>
                    <div class="theme-nom">Thème C — Élégance Sobre</div>
                    <div class="theme-tag">Ardoise #2c3e50 · Or #c0973a</div>
                </div>
                <?php if ($theme_actuel==='theme-c'): ?>
                <span class="badge-actif">✓ Actif</span>
                <?php endif; ?>
            </div>
            <button type="submit" name="theme" value="theme-c" class="btn-appliquer" style="background:#2c3e50;">Appliquer</button>
        </div>

    </div>
    </form>

    <p style="font-size:11px;color:#aaa;text-align:center;margin-bottom:36px;">
        Le thème est sauvegardé dans un cookie — valable 1 an sur ce navigateur.
    </p>

    <div class="section-titre">🏥 Site public — Infos du cabinet</div>

    <form method="POST" action="parametres.php">
    <input type="hidden" name="form" value="cabinet">

    <div class="champ-param">
        <label>Nom du cabinet</label>
        <input type="text" maxlength="100" name="Cabinet_Nom" value="<?= htmlspecialchars($cabinetCfg['Cabinet_Nom'] ?? '') ?>">
    </div>
    <div class="champ-param">
        <label>Adresse</label>
        <textarea name="Cabinet_Adresse" rows="2" maxlength="100"><?= htmlspecialchars($cabinetCfg['Cabinet_Adresse'] ?? '') ?></textarea>
    </div>
    <div class="champ-param-double">
        <div class="champ-param">
            <label>Téléphone</label>
            <input type="text" maxlength="100" name="Cabinet_Telephone" value="<?= htmlspecialchars($cabinetCfg['Cabinet_Telephone'] ?? '') ?>">
        </div>
        <div class="champ-param">
            <label>Email</label>
            <input type="text" maxlength="100" name="Cabinet_Email" value="<?= htmlspecialchars($cabinetCfg['Cabinet_Email'] ?? '') ?>">
        </div>
    </div>
    <div class="champ-param">
        <label>Description (affichée en page d'accueil du site — 100 caractères max)</label>
        <textarea name="Cabinet_Description" rows="3" maxlength="100"><?= htmlspecialchars($cabinetCfg['Cabinet_Description'] ?? '') ?></textarea>
    </div>

    <div class="section-titre" style="margin-top:24px;font-size:12px;">🕒 Horaires d'ouverture</div>
    <div class="horaires-grille">
        <?php foreach ($joursSemaine as $jour): ?>
        <div class="champ-param">
            <label><?= $jour ?></label>
            <input type="text" maxlength="100" name="Horaire_<?= $jour ?>" placeholder="ex. 09:00–16:00 ou Fermé"
                   value="<?= htmlspecialchars($cabinetCfg['Horaire_' . $jour] ?? '') ?>">
        </div>
        <?php endforeach; ?>
    </div>

    <button type="submit" class="btn-appliquer" style="max-width:260px;margin-top:10px;">Enregistrer</button>
    </form>

    <p style="font-size:11px;color:#aaa;text-align:center;margin-top:14px;">
        Ces informations apparaissent sur le site public (dossier <code>site/</code>).
    </p>

</div>
</body>
</html>
