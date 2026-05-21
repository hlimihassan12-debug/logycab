<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Test impression 2</title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    @page { size: 176mm 250mm; margin: 0; }
    body {
        width: 176mm; height: 250mm;
        background: white;
        font-family: Arial, sans-serif;
        font-size: 10pt;
        position: relative;
    }
    .bord       { position:absolute; top:0; left:0; right:0; bottom:0; border: 2pt solid red; }
    .entete-bas { position:absolute; left:0; right:0; top:142pt; border-top: 2pt solid green; }

    /* On teste 3 positions gauche différentes — laquelle est visible ? */
    .test-a { position:absolute; top:162pt; left:14pt;  font-size:11pt; color:blue; }
    .test-b { position:absolute; top:175pt; left:48pt;  font-size:11pt; color:orange; }
    .test-c { position:absolute; top:188pt; left:76pt;  font-size:11pt; color:green; }

    /* 3 positions bas différentes */
    .bas-a { position:absolute; bottom:57pt;  left:76pt; font-size:10pt; color:blue;   border-top:1pt solid blue;   padding-top:3pt; }
    .bas-b { position:absolute; bottom:85pt;  left:76pt; font-size:10pt; color:orange; border-top:1pt solid orange; padding-top:3pt; }
    .bas-c { position:absolute; bottom:113pt; left:76pt; font-size:10pt; color:green;  border-top:1pt solid green;  padding-top:3pt; }
</style>
</head>
<body>
    <div class="bord"></div>
    <div class="entete-bas"></div>

    <!-- Test gauche -->
    <div class="test-a">A (14pt=5mm) : NOM PATIENT TEST</div>
    <div class="test-b">B (48pt=17mm) : NOM PATIENT TEST</div>
    <div class="test-c">C (76pt=27mm) : NOM PATIENT TEST</div>

    <!-- Test bas -->
    <div class="bas-a">BAS-A (57pt=2cm) : Rendez-vous le lundi 1 juin 2026</div>
    <div class="bas-b">BAS-B (85pt=3cm) : Rendez-vous le lundi 1 juin 2026</div>
    <div class="bas-c">BAS-C (113pt=4cm) : Rendez-vous le lundi 1 juin 2026</div>
</body>
</html>
