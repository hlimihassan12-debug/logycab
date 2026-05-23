<?php
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';
$db = getDB();

$id    = (int)($_GET['id']    ?? 0);
$debut = $_GET['debut'] ?? '';   // format YYYY-MM-DD
$fin   = $_GET['fin']   ?? '';   // format YYYY-MM-DD
$sexe  = $_GET['sexe']  ?? 'M';  // M ou F

if ($id == 0) { die("❌ Patient introuvable."); }

// Récupérer le patient
$stmt = $db->prepare("SELECT * FROM ID WHERE [N°PAT] = ?");
$stmt->execute([$id]);
$patient = $stmt->fetch();
if (!$patient) { die("❌ Patient introuvable."); }

$nomPatient = strtoupper(trim($patient['NOMPRENOM'] ?? ''));
$nPat       = $patient['N°PAT'] ?? '';

// Formater les dates en JJ/MM/AAAA
function fmtDate($d) {
    if (!$d) return '___________';
    $ts = strtotime($d);
    if (!$ts || $ts <= 86400) return '___________';
    return date('d/m/Y', $ts);
}

// Calculer le nombre de jours
$nbrJ = 0;
if ($debut && $fin) {
    $d1 = new DateTime($debut);
    $d2 = new DateTime($fin);
    $nbrJ = (int)$d1->diff($d2)->days;
}

$debutAff = fmtDate($debut);
$finAff   = fmtDate($fin);

// Accord selon le sexe
$interesse = ($sexe === 'F') ? "l'intéressée" : "l'intéressé";

// Numéro discret : date+heure courts ex: 250523-0914
$numCert = date('ymd-Hi');

// Date du jour en français
$moisFr = ['','janvier','février','mars','avril','mai','juin',
            'juillet','août','septembre','octobre','novembre','décembre'];
$dateAuj = date('j') . ' ' . $moisFr[(int)date('n')] . ' ' . date('Y');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Certificat médical — <?= htmlspecialchars($nomPatient) ?></title>
<style>
    * { margin:0; padding:0; box-sizing:border-box; }

    @page {
        size: 176mm 250mm;
        margin: 0;
    }

    body {
        font-family: Arial, sans-serif;
        font-size: 13px;
        color: #111;
        background: white;
        width: 176mm;
        min-height: 250mm;
        padding-top:    5cm;    /* réservé à l'en-tête physique imprimé */
        padding-bottom: 2cm;    /* pied de page physique */
        padding-left:   1cm;
        padding-right:  1cm;
    }

    /* ── Numéro discret en haut à droite ── */
    .num-cert {
        position: absolute;
        top: 4.6cm;             /* juste sous l'en-tête physique */
        right: 1cm;
        font-size: 9px;
        color: #bbb;
        letter-spacing: 0.5px;
    }

    /* ── Titre centré ── */
    .titre-cert {
        text-align: center;
        margin-bottom: 14mm;
        margin-top: 4mm;
    }
    .titre-cert span {
        font-size: 13px;
        font-weight: bold;
        letter-spacing: 2px;
        text-transform: uppercase;
        border-top: 1px solid #000;
        border-bottom: 1px solid #000;
        padding: 2px 16px;
    }

    /* ── Corps du certificat ── */
    .corps {
        font-size: 13px;
        line-height: 2;
    }

    .nom-patient {
        font-size: 14px;
        font-weight: bold;
        margin: 4mm 0;
        padding-left: 4mm;
    }

    .ligne-repos {
        display: flex;
        align-items: baseline;
        gap: 6px;
        margin: 2mm 0;
    }
    .ligne-repos .label { white-space: nowrap; }
    .ligne-repos .valeur {
        font-weight: bold;
        min-width: 20mm;
    }

    /* ── Signature bas de page ── */
    .signature {
        position: fixed;
        bottom: 2.4cm;
        right: 1cm;
        text-align: right;
        font-size: 12px;
        color: #333;
    }

    @media screen {
        body {
            position: relative;
            margin: 10px auto;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            border: 1px solid #ddd;
        }
    }
    @media print {
        .signature { position: fixed; }
    }
</style>
</head>
<body>

<!-- Numéro discret -->
<div class="num-cert"><?= $numCert ?></div>

<!-- Titre -->
<div class="titre-cert">
    <span>Certificat médical</span>
</div>

<!-- Corps -->
<div class="corps">

    <p>Je soussigné Dr Hassan HLIMI, certifie avoir examiné</p>

    <div class="nom-patient"><?= htmlspecialchars($nomPatient) ?></div>

    <p>Et déclare que son état de santé nécessite un arrêt</p>

    <br>

    <div class="ligne-repos">
        <span class="label">de travail de</span>
        <span class="valeur"><?= $nbrJ ?> jour<?= $nbrJ > 1 ? 's' : '' ?></span>
    </div>

    <div class="ligne-repos">
        <span class="label">À compter du :</span>
        <span class="valeur"><?= $debutAff ?></span>
    </div>

    <div class="ligne-repos">
        <span class="label">Jusqu'au :</span>
        <span class="valeur"><?= $finAff ?></span>
    </div>

    <br>

    <p>Ce certificat est délivré à <?= $interesse ?> pour servir ce que de droit.</p>

</div>

<!-- Signature -->
<div class="signature">
    <p>Tétouan, le <?= $dateAuj ?></p>
    <br><br>
    <p style="font-size:11px;color:#888;">Signature et cachet</p>
</div>

<script>
    window.onload = function() {
        window.print();
        window.addEventListener('afterprint', function() {
            window.close();
        });
    };
</script>
</body>
</html>
