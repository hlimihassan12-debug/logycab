<?php
$pageTitre = 'Accueil';
require_once __DIR__ . '/inc/header.php';

$jours = joursSemaine();
$jourIndex = (int)date('N') - 1; // 0=Lundi ... 6=Dimanche
$jourAujourdhui = $jours[$jourIndex];
$horaireAujourdhui = horaireDuJour($cfg, $jourAujourdhui);
?>

<style>
.hero-nouveau { background:#1a1aE0; color:#fff; padding:14px 20px 18px; text-align:center; }
.hero-nouveau .titre-top { font-weight:800; font-size:15px; line-height:1.5; letter-spacing:0.3px; }
.hero-mid { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; max-width:1000px; margin:14px auto 0; text-align:left; }
.hero-bio-fr, .hero-bio-ar { flex:1; font-size:12px; line-height:1.6; }
.hero-bio-fr p, .hero-bio-ar p { margin:2px 0; font-weight:700; }
.hero-bio-fr .nom { font-size:15px; }
.hero-bio-fr .metier { color:#ff5555; }
.hero-bio-ar { text-align:right; color:#ff8080; }
.hero-bio-ar .nom { color:#fff; font-size:15px; }
.hero-heart { flex:0 0 auto; font-size:70px; line-height:1; margin-top:4px; }
.hero-boutons-nouveau { display:flex; justify-content:center; gap:0; margin-top:16px; flex-wrap:wrap; }
.btn-hero { display:inline-block; padding:12px 26px; font-weight:800; font-size:14px; text-decoration:none; cursor:default; }
.btn-hero.rouge  { background:#e02020; color:#fff; }
.btn-hero.bleu   { background:#3aa0e0; color:#fff; }
.btn-hero.jaune  { background:#ffe680; color:#333; }
a.btn-hero:hover { filter:brightness(1.08); cursor:pointer; }
@media (max-width:700px) {
    .hero-mid { flex-direction:column; align-items:center; text-align:center; }
    .hero-bio-ar { text-align:center; }
}
</style>

<section class="hero-nouveau">
    <div class="titre-top">
        CABINET DE CARDIOLOGIE ET D'EXPLORATIONS CARDIO-VASCULAIRES :<br>
        ECG, HOLTERS : TENSIONNEL ET RYTHMIQUE,<br>
        ECHODOPLER CARDIAQUE ET VASCULAIRE, ADULTES ET ENFANTS
    </div>

    <div class="hero-mid">
        <div class="hero-bio-fr">
            <p class="nom">Dr Hassan Hlimi</p>
            <p class="metier">Cardiologue</p>
            <p>Lauréat de la faculté de médecine de Rabat</p>
            <p>Diplômé de la faculté de médecine de Paris</p>
            <p>Spécialiste des maladies du cœur et des vaisseaux</p>
            <p>Diplômé de cardiologie pédiatrique</p>
            <p>Ancien attaché des hôpitaux de Paris</p>
        </div>

        <div class="hero-heart">🫀</div>

        <div class="hero-bio-ar" dir="rtl">
            <p class="nom">الدكتور حليمي حسن</p>
            <p>اختصاصي في أمراض القلب لدى الكبار والأطفال</p>
            <p>خريج كلية الطب الرباط</p>
            <p>حائز على دبلوم أمراض القلب والشرايين بباريس</p>
            <p>أخصائي في الفحص بالصدى للقلب والشرايين بباريس</p>
            <p>اختصاصي في أمراض القلب لدى الأطفال</p>
            <p>اختصاصي سابقا بمستشفيات باريس</p>
        </div>
    </div>

    <div class="hero-boutons-nouveau">
        <a href="rendez-vous.php" class="btn-hero rouge">Prendre Rendez-vous</a>
        <span class="btn-hero bleu">Bien venue</span>
        <a href="cabinet.php" class="btn-hero jaune">Découvrir le cabinet</a>
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
