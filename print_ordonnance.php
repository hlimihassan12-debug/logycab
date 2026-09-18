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
        size: 148mm 210mm;
        margin: 0;
    }

    body {
        font-family: Arial, sans-serif;
        font-size: 13px;
        color: #111;
        background: white;
        width: 148mm;
        min-height: 210mm;
        padding-top:    5.3cm;    /* en-tête physique (mesuré : texte descend à ~5.1-5.2cm) */
        padding-bottom: 1.7cm;    /* pied physique */
        padding-left:   1cm;      /* marge gauche */
        padding-right:  1cm;      /* marge droite */
    }

    /* ══ EN-TÊTE : NOM | DATE | N°PAT/N°ORD — tout sur une seule ligne ══ */
    .entete-donnees {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        gap: 8px;
        margin-top: 1cm;        /* descend le bloc nom→médicaments (ajusté de 2.5cm à 1cm) */
        margin-bottom: 0;
        border-bottom: 1px solid #ccc;
        padding-bottom: 3px;
        white-space: nowrap;
    }
    .nom-patient {
        font-size: 13px;
        font-weight: bold;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .entete-date {
        font-size: 11px;
        font-weight: bold;
        flex-shrink: 0;
    }
    .entete-numeros {
        font-size: 11px;
        font-weight: bold;
        color: #333;
        flex-shrink: 0;
    }
    .entete-numeros .n-ord {
        font-weight: normal;
        color: #999;
    }

    /* ══ MÉDICAMENTS ══ */
    .liste-meds {
        margin-top: 6mm;        /* espace nom patient → 1er médicament (3mm + 3mm ajoutés) */
        padding-left: 7mm;      /* décalage niveau 1 : nom médicament */
    }
    .med-item {
        margin-bottom: 4mm;     /* entre médicaments (2mm + 2mm ajoutés) */
    }
    .med-nom {
        font-size: 13px;
        font-weight: bold;
        text-transform: uppercase;
        margin-bottom: 1mm;     /* espace nom médicament → posologie (était 2mm) */
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
        bottom: 1.7cm;
        left:  1cm;
        right: 1cm;
        border-top: 1px solid #ccc;
        padding-top: 6px;
        font-size: 12px;
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        gap: 10px;
    }
    .rdv-footer-texte { flex: 1; min-width: 0; }
    .rdv-ligne {
        display: flex;
        align-items: baseline;
        gap: 8px;
        flex-wrap: wrap;
    }
    .rdv-label  { color: #555; white-space: nowrap; }
    .rdv-val    { font-weight: bold; }
    .rdv-heure  { font-weight: bold; margin-left: 4px; }
    .rdv-acte-ligne { margin-top: 4px; }

    /* ══ QR CODE — prise de RDV en ligne (côte à côte, collé à la bande bleue) ══ */
    .rdv-qr {
        flex-shrink: 0;
        display: flex;
        align-items: center;
        gap: 2mm;
    }
    .rdv-qr img {
        width: 1.6cm;
        height: 1.6cm;
        display: block;
    }
    .rdv-qr .rdv-qr-legende {
        font-size: 8px;
        color: #777;
        text-align: left;
        line-height: 1.3;
    }

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
    <div class="entete-date"><?= $dateOrd ?></div>
    <div class="entete-numeros"><?= $nPat ?> <span class="n-ord">/ <?= $nOrd ?></span></div>
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
<div class="rdv-footer">
    <div class="rdv-footer-texte">
        <?php if ($dateRDV): ?>
        <div class="rdv-ligne">
            <span class="rdv-label">date rendez-vous</span>
            <span class="rdv-val"><?= $dateRDV ?></span>
            <?php if ($heureRDV): ?>
            <span class="rdv-label">A :</span>
            <span class="rdv-heure"><?= $heureRDV ?></span>
            <?php endif; ?>
        </div>
        <?php if ($acteRDV): ?>
        <div class="rdv-acte-ligne">
            <span class="rdv-label">pour</span>
            <span style="font-weight:bold;margin-left:6px;"><?= $acteRDV ?></span>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    <div class="rdv-qr">
        <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAXIAAAFyAQAAAADAX2ykAAAC5ElEQVR4nO2bS4rkMAyGP40Ds3SgD1BHSW7WzM0qR6kbOMsCh38Wdh4FPd00pGvKIC1CpfItBEKS9bCJ78j061s4OO+88z/Kz1ZlnM1sZDGzvn4262H93j1HH+dP5oMkSRAlXecOhltXTCvdOhgkSelZ+jh/Nl+dE7CRIKbL3RjS5snFsZ+nj/Nn8d3xZe5humTE3PHxwevV9Hf+e3wJyBDE1AfpGvN/1cf5M3hJ1Yw2AkyXu9n7zYzh1lET8xP1cf5MfinHYyCUoGzvt98CgmwEmMzWTPyK+jv/ieggqb4yaIvK8Qjo+mr6O/+FSClIStV1qxljphZEj5zbtz0+3g0AXQmqldLcATFTLa2M2SU/Rx/nT+fN+sXsXRkG3c3GKNm4peMR8PNVkzw166YgXaNUvHYThsTWv/L43B6/mpaw2TKXSF17lusHP181yVf/vZZcu5a5QzEt638xVyd2+zbGl/6kDQkZGIKQbeoTYu4x4tJBBE19eoI+zp/Lb62roPKruClQi+CYYUjB6982+UPBSzFyqpauRk5Bxb4lUr+a/s5/IbWNsZa5a+sqaPXk1bFx+7bJrxMisz4I5g5d4910Xdc5mHqvf9vmSwQG9OeS6/7GeGhSBj3yP62P8yfxa30EQFyXdGprYy+Cvf/cKL/NF3iof9M22t/qX8+/LfIc2lRbGH7oeeRD99Lt2xq/9ZrzoRN9mP/u/We3b4v88QQ1pDok/Hj+6/2NBvnH/Y0tE8PDpCEFef5tld/vL5SrC1smnn8Xn7aRxbz+bZJfz8/at67CwZ1rkbQ93H+b5A9bOUNajMnqfg5lu7KvTaxX1d/5f8nD/YUobLoIBi11J2u6CIOQIaYn6OP8uTzr6mQdKOy10L7OUcTn+y3yxX/3u0aaDMRsCHKnyYKApdNz9HH+XL4DaiyG+S3bkN6yDVo6A7AhvUE9Tr/l19Pf+c+lg7h7b0b1sQ2M2G8y+P6z886/Fv8XFnFyqcyuptgAAAAASUVORK5CYII=" alt="QR — Prendre RDV en ligne">
        <div class="rdv-qr-legende">Prendre RDV<br>en ligne</div>
    </div>
</div>

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
