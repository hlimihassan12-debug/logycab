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
                <select id="heure_souhaitee" name="heure_souhaitee">
                    <option value="">— Choisissez d'abord une date —</option>
                </select>
                <div id="msg_creneaux" style="font-size:12px; margin-top:4px; color:#c0392b;"></div>
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

<script>
(function () {
    var champDate   = document.getElementById('date_souhaitee');
    var selectHeure = document.getElementById('heure_souhaitee');
    var msgCreneaux  = document.getElementById('msg_creneaux');

    function formatHeureLabel(heure) {
        // "09:00" -> "9h00"
        var parts = heure.split(':');
        return parseInt(parts[0], 10) + 'h' + parts[1];
    }

    function viderSelect(messagePlaceholder) {
        selectHeure.innerHTML = '';
        var opt = document.createElement('option');
        opt.value = '';
        opt.textContent = messagePlaceholder;
        selectHeure.appendChild(opt);
    }

    champDate.addEventListener('change', function () {
        var dateChoisie = champDate.value;
        msgCreneaux.textContent = '';

        if (!dateChoisie) {
            viderSelect('— Choisissez d\'abord une date —');
            return;
        }

        viderSelect('Chargement des créneaux...');
        selectHeure.disabled = true;

        fetch('ajax_creneaux_dispo.php?date=' + encodeURIComponent(dateChoisie))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                selectHeure.disabled = false;

                if (!data.ok) {
                    viderSelect('— Date invalide —');
                    return;
                }

                if (data.ferme) {
                    viderSelect('— Cabinet fermé ce jour —');
                    msgCreneaux.textContent = data.raison || 'Cabinet fermé ce jour-là.';
                    return;
                }

                if (data.jour_complet) {
                    viderSelect('— Journée complète —');
                    msgCreneaux.textContent = 'Cette journée est complète. Merci de choisir une autre date.';
                    return;
                }

                viderSelect('— Choisissez une heure (facultatif) —');

                var auMoinsUnDispo = false;
                data.creneaux.forEach(function (cr) {
                    var opt = document.createElement('option');
                    opt.value = cr.heure;
                    if (cr.disponible) {
                        opt.textContent = formatHeureLabel(cr.heure);
                        auMoinsUnDispo = true;
                    } else {
                        opt.textContent = formatHeureLabel(cr.heure) + ' — complet';
                        opt.disabled = true;
                    }
                    selectHeure.appendChild(opt);
                });

                if (!auMoinsUnDispo) {
                    msgCreneaux.textContent = 'Tous les créneaux de cette journée sont pris. Merci de choisir une autre date.';
                }
            })
            .catch(function () {
                selectHeure.disabled = false;
                viderSelect('— Erreur de chargement —');
                msgCreneaux.textContent = 'Impossible de charger les créneaux, réessayez.';
            });
    });
})();
</script>

<?php require_once __DIR__ . '/inc/footer.php'; ?>
