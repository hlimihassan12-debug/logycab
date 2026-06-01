<?php
// index.php — Splash screen Logycab
// Aucune base de données nécessaire
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logycab — Cabinet cardio-vasculaire</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #0d2b4e 0%, #1a4a7a 50%, #0d2b4e 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Arial, sans-serif;
            color: white;
            overflow: hidden;
        }

        /* Horloge en haut */
        #horloge {
            position: fixed;
            top: 22px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 15px;
            color: #a8c8e8;
            letter-spacing: 1px;
            font-weight: 300;
            text-align: center;
        }
        #horloge .heure {
            font-size: 22px;
            font-weight: 600;
            color: #d0e8ff;
            display: block;
        }

        /* Conteneur central */
        .splash-centre {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 18px;
            animation: fadeIn 1s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* Icône cœur ECG */
        .logo-icone {
            font-size: 52px;
            line-height: 1;
            filter: drop-shadow(0 0 18px rgba(100,180,255,0.5));
        }

        /* Nom de l'application */
        .logo-nom {
            font-size: 56px;
            font-weight: 800;
            letter-spacing: 6px;
            color: #ffffff;
            text-shadow: 0 0 30px rgba(100,180,255,0.6), 0 2px 8px rgba(0,0,0,0.4);
            text-transform: uppercase;
        }

        /* Trait séparateur */
        .separateur {
            width: 180px;
            height: 2px;
            background: linear-gradient(90deg, transparent, #5aaaf0, transparent);
            border-radius: 2px;
        }

        /* Nom du médecin */
        .medecin-nom {
            font-size: 22px;
            font-weight: 600;
            color: #d0e8ff;
            letter-spacing: 2px;
        }

        /* Spécialité */
        .medecin-specialite {
            font-size: 14px;
            font-weight: 300;
            color: #7aafd4;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        /* Bouton Entrer */
        .btn-entrer {
            margin-top: 30px;
            padding: 14px 50px;
            font-size: 16px;
            font-weight: 600;
            letter-spacing: 2px;
            color: #0d2b4e;
            background: linear-gradient(135deg, #5aaaf0, #2e6da4);
            border: none;
            border-radius: 40px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 4px 20px rgba(90,170,240,0.4);
        }
        .btn-entrer:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(90,170,240,0.6);
            color: #0d2b4e;
        }
        .btn-entrer:active {
            transform: translateY(0);
        }

        /* Version en bas */
        .version {
            position: fixed;
            bottom: 16px;
            right: 20px;
            font-size: 11px;
            color: #3a6a9a;
            letter-spacing: 1px;
        }
    </style>
</head>
<body>

    <!-- Horloge -->
    <div id="horloge">
        <span class="heure" id="heure">--:--:--</span>
        <span id="date-txt"></span>
    </div>

    <!-- Contenu central -->
    <div class="splash-centre">

        <div class="logo-icone">🫀</div>

        <div class="logo-nom">Logycab</div>

        <div class="separateur"></div>

        <div class="medecin-nom">Dr Hlimi Hassan</div>

        <div class="medecin-specialite">Cabinet d'explorations cardio-vasculaires</div>

        <a href="agenda.php" class="btn-entrer">Entrer &rarr;</a>

    </div>

    <!-- Version -->
    <div class="version">v2026.05</div>

    <!-- Horloge JavaScript -->
    <script>
        const joursS = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
        const moisS  = ['Janvier','Février','Mars','Avril','Mai','Juin',
                        'Juillet','Août','Septembre','Octobre','Novembre','Décembre'];

        function majHorloge() {
            const now = new Date();
            const h   = String(now.getHours()).padStart(2, '0');
            const m   = String(now.getMinutes()).padStart(2, '0');
            const s   = String(now.getSeconds()).padStart(2, '0');
            document.getElementById('heure').textContent = h + ':' + m + ':' + s;

            const jour  = joursS[now.getDay()];
            const date  = now.getDate();
            const mois  = moisS[now.getMonth()];
            const annee = now.getFullYear();
            document.getElementById('date-txt').textContent = jour + ' ' + date + ' ' + mois + ' ' + annee;
        }

        majHorloge();
        setInterval(majHorloge, 1000);
    </script>

</body>
</html>
