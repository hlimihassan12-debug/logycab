<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Test impression</title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    @page {
        size: 176mm 250mm;
        margin: 0;
    }
    body {
        width:      176mm;
        height:     250mm;
        background: white;
        font-family: Arial, sans-serif;
        font-size:  10pt;
        position:   relative;
    }
    /* Bord de la feuille — trait rouge tout autour */
    .bord {
        position: absolute;
        top: 0; left: 0; right: 0; bottom: 0;
        border: 2pt solid red;
    }
    /* Ligne à 5mm du bord gauche */
    .marge-gauche {
        position: absolute;
        top: 0; bottom: 0;
        left: 14pt;
        border-left: 1pt dashed blue;
    }
    /* Ligne à 5mm du bord droit */
    .marge-droite {
        position: absolute;
        top: 0; bottom: 0;
        right: 14pt;
        border-right: 1pt dashed blue;
    }
    /* Ligne à 5cm du haut */
    .entete-bas {
        position: absolute;
        left: 0; right: 0;
        top: 142pt;
        border-top: 2pt solid green;
    }
    /* Labels */
    .label {
        position: absolute;
        font-size: 8pt;
        color: #333;
    }
</style>
</head>
<body>
    <div class="bord"></div>
    <div class="marge-gauche"></div>
    <div class="marge-droite"></div>
    <div class="entete-bas"></div>

    <!-- Labels explicatifs -->
    <span class="label" style="top:2pt; left:16pt; color:blue;">← 5mm du bord gauche (trait bleu)</span>
    <span class="label" style="top:2pt; right:16pt; color:blue; text-align:right;">5mm du bord droit →</span>
    <span class="label" style="top:145pt; left:16pt; color:green;">← 5cm du haut = bas de l'en-tête (trait vert)</span>
    <span class="label" style="top:2pt; left:50%; color:red;">Bord feuille (trait rouge)</span>

    <!-- Texte de test positionné à 5mm gauche, juste sous la ligne verte -->
    <div style="position:absolute; top:162pt; left:14pt; font-size:13pt; font-weight:bold;">
        NOM PATIENT TEST
    </div>
    <div style="position:absolute; top:162pt; right:14pt; font-size:11pt; text-align:right;">
        21/05/2026<br>
        N° 363<br>
        <span style="color:#aaa; font-size:9pt;">N° 347</span>
    </div>
</body>
</html>
