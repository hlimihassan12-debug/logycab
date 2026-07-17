<?php
$pageTitre = 'Horaires';
require_once __DIR__ . '/inc/header.php';

$jours = joursSemaine();
$jourAujourdhui = $jours[(int)date('N') - 1];
?>

<div class="site-section">
    <h1 class="site-titre">Horaires d'ouverture</h1>
    <p class="site-soustitre">Le cabinet est ouvert aux horaires suivants. Pour une consultation, pensez à prendre rendez-vous à l'avance.</p>

    <table class="table-horaires">
        <thead>
            <tr><th>Jour</th><th>Horaires</th></tr>
        </thead>
        <tbody>
            <?php foreach ($jours as $jour): $h = horaireDuJour($cfg, $jour); ?>
            <tr class="<?= $jour === $jourAujourdhui ? 'jour-actuel' : '' ?>">
                <td><?= htmlspecialchars($jour) ?><?= $jour === $jourAujourdhui ? ' (aujourd\'hui)' : '' ?></td>
                <td class="<?= strtolower($h) === 'fermé' ? 'horaire-ferme' : '' ?>"><?= htmlspecialchars($h) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div style="text-align:center;margin-top:34px;">
        <a href="rendez-vous.php" class="btn btn-primaire">Prendre rendez-vous</a>
    </div>
</div>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
