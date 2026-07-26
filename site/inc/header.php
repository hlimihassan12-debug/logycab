<?php
require_once __DIR__ . '/site_config.php';
$cfg = getCabinetConfig();
$navActuelle = basename($_SERVER['SCRIPT_NAME']);
$nomCabinet = $cfg['Cabinet_Nom'] ?? 'Cabinet médical';

// Cache-busting : force le rechargement du CSS à chaque modification du fichier
$cssPath = __DIR__ . '/../assets/site.css';
$cssVersion = file_exists($cssPath) ? filemtime($cssPath) : time();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= isset($pageTitre) ? htmlspecialchars($pageTitre) . ' — ' : '' ?><?= htmlspecialchars($nomCabinet) ?></title>
<link rel="stylesheet" href="assets/site.css?v=<?= $cssVersion ?>">
</head>
<body>

<header class="site-header">
    <div class="site-header-inner">
        <a href="index.php" class="site-logo">
            <span class="site-logo-coeur">❤</span>
            <span><?= htmlspecialchars($nomCabinet) ?></span>
        </a>
        <nav class="site-nav">
            <a href="index.php"       class="<?= $navActuelle === 'index.php' ? 'actif' : '' ?>">Accueil</a>
            <a href="cabinet.php"     class="<?= $navActuelle === 'cabinet.php' ? 'actif' : '' ?>">Le cabinet</a>
            <a href="horaires.php"    class="<?= $navActuelle === 'horaires.php' ? 'actif' : '' ?>">Horaires</a>
            <a href="rendez-vous.php" class="btn-nav-rdv <?= $navActuelle === 'rendez-vous.php' ? 'actif' : '' ?>">Prendre RDV</a>
        </nav>
    </div>
</header>

<main class="site-main">
