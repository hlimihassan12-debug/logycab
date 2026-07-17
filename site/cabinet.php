<?php
$pageTitre = 'Le cabinet';
require_once __DIR__ . '/inc/header.php';

$adresse = $cfg['Cabinet_Adresse'] ?? '';
$mapsUrl = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($adresse);
?>

<div class="site-section">
    <h1 class="site-titre">Le cabinet</h1>
    <p class="site-soustitre"><?= htmlspecialchars($cfg['Cabinet_Description'] ?? '') ?></p>

    <div class="grille-2" style="align-items:start;">
        <div class="carte">
            <h3>📍 Adresse</h3>
            <p style="font-size:14px;color:var(--site-text);margin-bottom:12px;">
                <?= nl2br(htmlspecialchars($adresse)) ?>
            </p>
            <a href="<?= htmlspecialchars($mapsUrl) ?>" class="btn btn-primaire" target="_blank" rel="noopener">
                Voir sur Google Maps
            </a>
        </div>
        <div class="carte">
            <h3>☎ Contact</h3>
            <p style="font-size:14px;color:var(--site-text);">
                Téléphone : <?= htmlspecialchars($cfg['Cabinet_Telephone'] ?? '') ?><br>
                Email : <?= htmlspecialchars($cfg['Cabinet_Email'] ?? '') ?>
            </p>
        </div>
    </div>
</div>

<div class="site-section site-section-tight">
    <h2 class="site-titre">Photos du cabinet</h2>
    <div class="galerie">
        <div class="galerie-item">🏥</div>
        <div class="galerie-item">🩺</div>
        <div class="galerie-item">🛋</div>
        <div class="galerie-item">🚪</div>
        <div class="galerie-item">🖥</div>
        <div class="galerie-item">🌿</div>
    </div>
    <p style="margin-top:14px;font-size:12px;color:var(--site-text-muted);">
        Photos provisoires — à remplacer par de vraies photos du cabinet.
    </p>
</div>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
