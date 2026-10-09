<?php
// =====================================================================
// rdv-casablanca.php : page ouverte par le QR "Rendez-vous" du flyer
// du cabinet de Casablanca.
//
// INTERRUPTEUR : mettre $ouvert = true le jour de l'ouverture.
//   - false : affiche le message "ouverture prochaine"
//   - true  : envoie directement vers la page de prise de RDV
// =====================================================================
$ouvert = false;

// Facultatif : texte de la date d'ouverture (laisser vide '' si inconnue)
$date_ouverture = '';

if ($ouvert) {
    header('Location: rendez-vous.php');
    exit;
}
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cabinet de Cardiologie - Casablanca</title>
<style>
  body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
         background: #EAF1F8; font-family: 'Segoe UI', Arial, sans-serif; color: #1F2D3D; }
  .carte { width: 90%; max-width: 420px; background: #fff; border-radius: 14px; overflow: hidden;
           box-shadow: 0 6px 24px rgba(26,74,122,0.15); text-align: center; }
  .bandeau { background: #1a4a7a; color: #fff; padding: 18px 20px; }
  .bandeau h1 { margin: 0; font-size: 21px; }
  .bandeau .ar { margin-top: 4px; font-size: 17px; }
  .contenu { padding: 24px 22px 26px; }
  .icone { font-size: 40px; }
  h2 { color: #1a4a7a; font-size: 20px; margin: 10px 0 8px; }
  p { margin: 6px 0; line-height: 1.5; font-size: 15px; }
  .ar { direction: rtl; }
  .date { display: inline-block; margin-top: 10px; padding: 6px 14px; border-radius: 999px;
          background: #EAF1F8; color: #1a4a7a; font-weight: 700; }
  hr { border: none; border-top: 1px solid #D5DFEA; margin: 18px 0; }
  .adresse { font-size: 14px; color: #3E4C5A; }
</style>
</head>
<body>
<div class="carte">
  <div class="bandeau">
    <h1>Cabinet de Cardiologie</h1>
    <div class="ar">عيادة أمراض القلب والشرايين</div>
  </div>
  <div class="contenu">
    <div class="icone">
      <svg width="46" height="46" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2.5" fill="none" stroke="#1a4a7a" stroke-width="1.8"></rect><line x1="3" y1="10" x2="21" y2="10" stroke="#1a4a7a" stroke-width="1.8"></line><line x1="8" y1="3" x2="8" y2="7" stroke="#1a4a7a" stroke-width="1.8" stroke-linecap="round"></line><line x1="16" y1="3" x2="16" y2="7" stroke="#1a4a7a" stroke-width="1.8" stroke-linecap="round"></line><path d="M12 18.5 C 8.5 16, 7.5 14.5, 8.5 13 C 9.5 11.8, 11.2 12.2, 12 13.4 C 12.8 12.2, 14.5 11.8, 15.5 13 C 16.5 14.5, 15.5 16, 12 18.5 Z" fill="#E25B4B"></path></svg>
    </div>
    <h2>Ouverture prochaine</h2>
    <p>Le cabinet de Casablanca ouvrira prochainement.<br>
       La prise de rendez-vous en ligne sera disponible dès l'ouverture.</p>
    <p class="ar">ستفتح العيادة بالدار البيضاء أبوابها قريبا.<br>
       سيكون حجز المواعيد عبر الإنترنت متاحا ابتداء من يوم الافتتاح.</p>
    <?php if ($date_ouverture !== ''): ?>
      <div class="date">Ouverture prévue : <?= htmlspecialchars($date_ouverture) ?></div>
    <?php endif; ?>
    <hr>
    <p class="adresse"><strong>161, Bd Sidi Abderrahmane</strong> — Hay Hanae, Casablanca<br>
       <span class="ar">161، شارع سيدي عبد الرحمان — حي الهناء، الدار البيضاء</span></p>
    <p>Merci de votre confiance · شكرا على ثقتكم</p>
  </div>
</div>
</body>
</html>
