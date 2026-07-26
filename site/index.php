<?php
$pageTitre = 'Accueil';
require_once __DIR__ . '/inc/header.php';

$jours = joursSemaine();
$jourIndex = (int)date('N') - 1; // 0=Lundi ... 6=Dimanche
$jourAujourdhui = $jours[$jourIndex];
$horaireAujourdhui = horaireDuJour($cfg, $jourAujourdhui);
?>

<section class="hero">
    <h1>Bienvenue au <?= htmlspecialchars($nomCabinet) ?></h1>
    <p><?= htmlspecialchars($cfg['Cabinet_Description'] ?? '') ?></p>
    <div class="hero-boutons">
        <a href="rendez-vous.php" class="btn btn-primaire">Prendre rendez-vous</a>
        <a href="cabinet.php" class="btn btn-secondaire">Découvrir le cabinet</a>
    </div>
</section>

<div class="site-section site-section-tight">
    <div class="bandeau-horaires">
        <div>🕒 Aujourd'hui (<?= htmlspecialchars($jourAujourdhui) ?>) : <strong><?= htmlspecialchars($horaireAujourdhui) ?></strong></div>
        <a href="horaires.php">Voir tous les horaires ▶</a>
    </div>
</div>

<div class="site-section">
    <h2 class="site-titre">Pourquoi choisir notre cabinet</h2>
    <p class="site-soustitre">Une prise en charge attentive, dans un cadre moderne et accessible.</p>
    <div class="grille-3">
        <div class="carte">
            <span class="ico">🩺</span>
            <h3>Suivi personnalisé</h3>
            <p>Chaque patient est suivi avec attention, dans la durée, par la même équipe.</p>
        </div>
        <div class="carte">
            <span class="ico">📅</span>
            <h3>Rendez-vous simplifié</h3>
            <p>Envoyez une demande de rendez-vous en ligne, nous vous confirmons rapidement le créneau.</p>
        </div>
        <div class="carte">
            <span class="ico">🏥</span>
            <h3>Cabinet accessible</h3>
            <p>Facilement accessible, avec un accueil chaleureux dès votre arrivée.</p>
        </div>
    </div>
</div>

<div class="site-section site-section-tight">
    <h2 class="site-titre">Le cabinet en images</h2>
    <div class="galerie">
        <div class="galerie-item">🏥</div>
        <div class="galerie-item">🩺</div>
        <div class="galerie-item">🛋</div>
    </div>
    <p style="margin-top:8px;"><a href="cabinet.php">Voir toutes les photos et l'adresse ▶</a></p>
</div>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
