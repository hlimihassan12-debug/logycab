<?php
$pageTitre = 'Prendre rendez-vous';
require_once __DIR__ . '/inc/header.php';

$ok = isset($_GET['ok']);
$erreur = $_GET['erreur'] ?? '';
?>

<div class="site-section" style="max-width:700px;">
    <h1 class="site-titre">Prendre rendez-vous</h1>
    <p class="site-soustitre">
        Remplissez ce formulaire pour envoyer une demande de rendez-vous. Le cabinet vous
        recontactera pour confirmer la date et l'heure.
    </p>

    <?php if ($ok): ?>
        <div class="alerte-ok">
            ✅ Votre demande a bien été envoyée. Le cabinet vous recontactera pour la confirmer.
        </div>
    <?php elseif ($erreur === 'champs_manquants'): ?>
        <div class="alerte-erreur">
            ⚠ Merci de renseigner au moins votre nom et votre numéro de téléphone.
        </div>
    <?php elseif ($erreur === 'technique'): ?>
        <div class="alerte-erreur">
            ⚠ Une erreur est survenue, merci de réessayer ou de nous appeler directement.
        </div>
    <?php endif; ?>

    <form class="form-rdv" method="POST" action="enregistrer_rdv.php">
        <div class="champ-double">
            <div class="champ">
                <label class="champ-obligatoire" for="nom">Nom complet</label>
                <input type="text" id="nom" name="nom" required>
            </div>
            <div class="champ">
                <label class="champ-obligatoire" for="telephone">Téléphone</label>
                <input type="tel" id="telephone" name="telephone" required>
            </div>
        </div>

        <div class="champ">
            <label for="email">Email (facultatif)</label>
            <input type="email" id="email" name="email">
        </div>

        <div class="champ">
            <label for="motif">Motif de la consultation (facultatif)</label>
            <input type="text" id="motif" name="motif" placeholder="Ex : consultation de suivi, première visite...">
        </div>

        <div class="champ-double">
            <div class="champ">
                <label for="date_souhaitee">Date souhaitée (facultatif)</label>
                <input type="date" id="date_souhaitee" name="date_souhaitee">
            </div>
            <div class="champ">
                <label for="heure_souhaitee">Heure souhaitée (facultatif)</label>
                <input type="time" id="heure_souhaitee" name="heure_souhaitee">
            </div>
        </div>

        <div class="champ">
            <label for="message">Message (facultatif)</label>
            <textarea id="message" name="message" rows="4"></textarea>
        </div>

        <!-- Champ piège anti-spam : doit rester vide, invisible pour un humain -->
        <div class="piege">
            <label for="site_web">Site web</label>
            <input type="text" id="site_web" name="site_web" tabindex="-1" autocomplete="off">
        </div>

        <button type="submit" class="btn btn-primaire" style="width:100%;">Envoyer ma demande</button>
    </form>
</div>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
