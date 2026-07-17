</main>

<footer class="site-footer">
    <div class="site-footer-inner">
        <div class="site-footer-col">
            <strong><?= htmlspecialchars($nomCabinet) ?></strong>
            <p><?= nl2br(htmlspecialchars($cfg['Cabinet_Adresse'] ?? '')) ?></p>
        </div>
        <div class="site-footer-col">
            <p>📞 <?= htmlspecialchars($cfg['Cabinet_Telephone'] ?? '') ?></p>
            <p>✉ <?= htmlspecialchars($cfg['Cabinet_Email'] ?? '') ?></p>
        </div>
        <div class="site-footer-col">
            <p><a href="horaires.php">Voir les horaires d'ouverture</a></p>
            <p><a href="rendez-vous.php">Prendre rendez-vous</a></p>
        </div>
    </div>
    <div class="site-footer-bas">
        &copy; <?= date('Y') ?> <?= htmlspecialchars($nomCabinet) ?>
    </div>
</footer>

</body>
</html>
