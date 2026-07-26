<?php
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';
$db = getDB();

$id   = (int)($_GET['id']  ?? 0);
$nOrd = (int)($_GET['ord'] ?? 0);

if ($id == 0 || $nOrd == 0) { die("❌ Paramètres manquants."); }

// Patient
$stmtPat = $db->prepare("SELECT * FROM ID WHERE [N°PAT] = ?");
$stmtPat->execute([$id]);
$patient = $stmtPat->fetch();
if (!$patient) { die("❌ Patient introuvable."); }

// Ordonnance
$stmtOrd = $db->prepare("SELECT * FROM ORD WHERE n_ordon = ? AND id = ?");
$stmtOrd->execute([$nOrd, $id]);
$ord = $stmtOrd->fetch();
if (!$ord) { die("❌ Ordonnance introuvable."); }

// Médicaments
$stmtMed = $db->prepare("
    SELECT p.posologie, p.DUREE, p.Ordre, pr.PRODUIT
    FROM PROD p
    LEFT JOIN PRODUITS pr ON p.produit = pr.NuméroPRODUIT
    WHERE p.N_ord = ?
    ORDER BY p.Ordre
");
$stmtMed->execute([$nOrd]);
$medicaments = $stmtMed->fetchAll();

// Date ordonnance formatée
$dateOrd = '—';
if (!empty($ord['date_ordon'])) {
    $ts = strtotime($ord['date_ordon']);
    if ($ts && $ts > 86400) $dateOrd = date('d/m/Y', $ts);
}

// Date RDV formatée
$dateRDV  = '';
$heureRDV = '';
$acteRDV  = '';
if (!empty($ord['DATE REDEZ VOUS'])) {
    $ts = strtotime($ord['DATE REDEZ VOUS']);
    if ($ts && $ts > 86400) {
        // Jour en français
        $jours = ['dimanche','lundi','mardi','mercredi','jeudi','vendredi','samedi'];
        $mois  = ['','janvier','février','mars','avril','mai','juin',
                  'juillet','août','septembre','octobre','novembre','décembre'];
        $dateRDV = $jours[date('w',$ts)] . ' ' . date('j',$ts) . ' ' . $mois[(int)date('n',$ts)] . ' ' . date('Y',$ts);
    }
}
if (!empty($ord['HeureRDV'])) {
    $heureRDV = htmlspecialchars($ord['HeureRDV']);
}
if (!empty($ord['acte1'])) {
    $acteRDV = htmlspecialchars($ord['acte1']);
}

$nomPatient = htmlspecialchars(strtoupper($patient['NOMPRENOM'] ?? ''));
$nPat       = htmlspecialchars($patient['N°PAT'] ?? '');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Ordonnance — <?= $nomPatient ?></title>
<style>
    * { margin:0; padding:0; box-sizing:border-box; }

    @page {
        size: 147mm 212mm;
        margin: 0;
    }

    body {
        font-family: Arial, sans-serif;
        font-size: 13px;
        color: #111;
        background: white;
        width: 147mm;
        min-height: 212mm;
        padding-top:    5cm;    /* en-tête physique */
        padding-bottom: 2cm;    /* pied physique */
        padding-left:   1cm;    /* marge gauche */
        padding-right:  1cm;    /* marge droite */
    }

    /* ══ EN-TÊTE : NOM à gauche, DATE+N° à droite ══ */
    .entete-donnees {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 0;
        border-bottom: 1px solid #ccc;
        padding-bottom: 4px;
    }
    .nom-patient {
        font-size: 14px;
        font-weight: bold;
        flex: 1;
        padding-right: 5cm;     /* NOM↔DATE = 5cm */
        word-break: break-word;
    }
    .infos-droite {
        text-align: right;
        flex-shrink: 0;
        white-space: nowrap;
    }
    .infos-droite .date-ord {
        font-size: 13px;
        font-weight: bold;
        margin-bottom: 3px;
    }
    .infos-droite .n-pat {
        font-size: 15px;
        font-weight: bold;
        color: #111;
    }
    .infos-droite .n-ord {
        font-size: 11px;
        color: #aaa;
        margin-top: 2px;
    }

    /* ══ MÉDICAMENTS ══ */
    .liste-meds {
        margin-top: 3mm;        /* espace nom patient → 1er médicament */
        padding-left: 7mm;      /* décalage niveau 1 : nom médicament */
    }
    .med-item {
        margin-bottom: 4mm;     /* entre médicaments = 4mm */
    }
    .med-nom {
        font-size: 13px;
        font-weight: bold;
        text-transform: uppercase;
        margin-bottom: 2mm;     /* espace nom médicament → posologie */
        white-space: nowrap;
    }
    .med-detail {
        font-size: 12px;
        color: #333;
        display: flex;
        gap: 0.5cm;             /* posologie↔durée = 5mm */
        padding-left: 14mm;     /* décalage niveau 2 : posologie */
    }
    .med-poso  { white-space: nowrap; }
    .med-duree { white-space: nowrap; color: #444; }

    /* ══ RDV BAS DE PAGE ══ */
    .rdv-footer {
        position: fixed;
        bottom: 2cm;
        left:  1cm;
        right: 1cm;
        border-top: 1px solid #ccc;
        padding-top: 6px;
        font-size: 12px;
    }
    .rdv-ligne {
        display: flex;
        align-items: baseline;
        gap: 8px;
        flex-wrap: wrap;
    }
    .rdv-label  { color: #555; white-space: nowrap; }
    .rdv-val    { font-weight: bold; }
    .rdv-heure  { font-weight: bold; margin-left: 4px; }
    .ar-label   { direction: rtl; unicode-bidi: isolate; font-family: Tahoma, Arial, sans-serif; color: #555; }

    /* ══ INFO PATIENT BILINGUE — une seule ligne compacte ══ */
    .info-patient {
        margin-top: 4px;
        padding-top: 3px;
        border-top: 1px dashed #ccc;
        font-size: 8px;
        color: #555;
        display: flex;
        flex-wrap: wrap;
        align-items: baseline;
        gap: 5px;
    }
    .info-patient .ar {
        direction: rtl;
        unicode-bidi: isolate;
        font-family: Tahoma, Arial, sans-serif;
    }
    .info-patient .sep { color: #bbb; }
    .info-patient .site { direction: ltr; unicode-bidi: isolate; }

    @media screen {
        body {
            margin: 10px auto;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            border: 1px solid #ddd;
        }
    }
</style>
</head>
<body>

<!-- ══ EN-TÊTE DONNÉES ══ -->
<div class="entete-donnees">
    <div class="nom-patient"><?= $nomPatient ?></div>
    <div class="infos-droite">
        <div class="date-ord"><?= $dateOrd ?></div>
        <div class="n-pat"><?= $nPat ?></div>
        <div class="n-ord"><?= $nOrd ?></div>
    </div>
</div>

<!-- ══ LISTE MÉDICAMENTS ══ -->
<div class="liste-meds">
<?php if (!empty($medicaments)): ?>
    <?php foreach ($medicaments as $i => $m): ?>
    <div class="med-item">
        <div class="med-nom"><?= ($i+1) ?>) <?= htmlspecialchars($m['PRODUIT'] ?? '') ?></div>
        <div class="med-detail">
            <span class="med-poso"><?= htmlspecialchars($m['posologie'] ?? '') ?></span>
            <?php if (!empty($m['DUREE'])): ?>
            <span class="med-duree">Traitement de <?= htmlspecialchars($m['DUREE']) ?></span>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
<?php else: ?>
    <p style="color:#999;">Aucun médicament enregistré.</p>
<?php endif; ?>
</div>

<!-- ══ RDV BAS DE PAGE ══ -->
<?php if ($dateRDV): ?>
<div class="rdv-footer">
    <div class="rdv-ligne">
        <span class="rdv-label">RDV :</span>
        <span class="rdv-val"><?= $dateRDV ?></span>
        <?php if ($heureRDV): ?>
        <span class="rdv-label">A :</span>
        <span class="rdv-heure"><?= $heureRDV ?></span>
        <?php endif; ?>
        <span class="ar-label" dir="rtl">: الموعد</span>
        <?php if ($acteRDV): ?>
        <span class="rdv-label">Acte :</span>
        <span class="rdv-val"><?= $acteRDV ?></span>
        <?php endif; ?>
    </div>

    <div class="info-patient">
        <span class="fr">RDV en ligne possible</span>
        <span class="site">« drhlimihassan.com »</span>
        <span class="ar" dir="rtl">يمكن حجز الموعد عبر</span>
    </div>
</div>
<?php endif; ?>

<script>
    // Impression automatique à l'ouverture
    window.onload = function() {
        window.print();
        // Fermer l'onglet automatiquement après impression
        window.addEventListener('afterprint', function() {
            window.close();
        });
    };
</script>
</body>
</html>
