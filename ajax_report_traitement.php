<?php
/**
 * ajax_report_traitement.php
 * Crée une nouvelle ordonnance identique à la courante
 * avec date = aujourd'hui + N mois
 * + une facture ECG 300 DH à la date du jour
 *
 * POST JSON : { "id": 1234, "mois": 3 }
 */

require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$id   = (int)($data['id'] ?? 0);
$mois = (int)($data['mois'] ?? 3);

if ($id == 0) {
    echo json_encode(['success' => false, 'error' => 'Patient invalide']);
    exit;
}

$db = getDB();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    // 1. Récupérer l'ordonnance courante (la plus récente)
    $stmtOrd = $db->prepare("SELECT TOP 1 * FROM ORD WHERE id = ? ORDER BY n_ordon DESC");
    $stmtOrd->execute([$id]);
    $ordCourante = $stmtOrd->fetch(PDO::FETCH_ASSOC);

    if (!$ordCourante) {
        echo json_encode(['success' => false, 'error' => 'Aucune ordonnance trouvée']);
        exit;
    }

    // 2. Calculer les nouvelles dates (format ISO 120 pour CONVERT)
    $dateAujourdHui     = date('Y-m-d H:i:s');   // ex: 2026-05-13 13:07:56
    $dateAujourdHuiDate = date('Y-m-d');           // ex: 2026-05-13

    $dtRdv = new DateTime();
    $dtRdv->modify("+{$mois} months");
    $dateNouveauRdv     = $dtRdv->format('Y-m-d') . ' 00:00:00'; // ex: 2026-08-13 00:00:00
    $dateNouveauRdvDate = $dtRdv->format('Y-m-d');                // ex: 2026-08-13

    $jourRdv = (int)$dtRdv->format('d');
    $moisRdv = (int)$dtRdv->format('m');

    // Nettoyer acte1 et HeureRDV
    $acte1    = trim($ordCourante['acte1'] ?? '');
    // Chercher le premier créneau libre à la date du nouveau RDV
$heureRDV = null;
$stmtOccup = $db->prepare("
    SELECT HeureRDV, COUNT(*) AS nb
    FROM ORD
    WHERE (
        ([DATE REDEZ VOUS] IS NOT NULL AND CAST([DATE REDEZ VOUS] AS DATE) = ?)
        OR (Date_Rdv IS NOT NULL AND CAST(Date_Rdv AS DATE) = ?)
    )
    AND HeureRDV IS NOT NULL AND HeureRDV != ''
    GROUP BY HeureRDV
");
$stmtOccup->execute([$dateNouveauRdvDate, $dateNouveauRdvDate]);
$occup = [];
while ($row = $stmtOccup->fetch(PDO::FETCH_ASSOC)) {
    $h = substr(trim($row['HeureRDV']), 0, 5);
    $occup[$h] = (int)$row['nb'];
}
for ($t = strtotime('09:00'); $t <= strtotime('16:00'); $t += 1800) {
    $h  = date('H:i', $t);
    $nb = $occup[$h] ?? 0;
    if ($nb < 2) { $heureRDV = $h; break; }
}

    $db->beginTransaction();

    // 3. Insérer la nouvelle ordonnance
    // CONVERT(datetime, ?, 120) → format ISO yyyy-mm-dd hh:mm:ss accepté par SQL Server
    // CONVERT(date,     ?, 23)  → format ISO yyyy-mm-dd pour colonne date
    $stmtInsert = $db->prepare("
        INSERT INTO ORD (
            id, date_ordon, acte1,
            Date_Rdv, [DATE REDEZ VOUS],
            HeureRDV, jour_rdv, mois_rdv,
            JourRDV, DateSaisie,
            Urgence, vu, SansReponse
        )
        VALUES (
            ?,
            CONVERT(datetime, ?, 120),
            ?,
            CONVERT(datetime, ?, 120),
            CONVERT(datetime, ?, 120),
            ?,
            ?, ?,
            CONVERT(date, ?, 23),
            CONVERT(datetime, ?, 120),
            0, 0, 0
        )
    ");

    $stmtInsert->execute([
        $id,
        $dateAujourdHui,        // date_ordon
        $acte1,                 // acte1
        $dateNouveauRdv,        // Date_Rdv
        $dateNouveauRdv,        // DATE REDEZ VOUS
        $heureRDV,              // HeureRDV
        $jourRdv,               // jour_rdv
        $moisRdv,               // mois_rdv
        $dateNouveauRdvDate,    // JourRDV
        $dateAujourdHui,        // DateSaisie
    ]);

    $nOrdNouveau = $db->query("SELECT MAX(n_ordon) FROM ORD WHERE id = $id")->fetchColumn();

    // 4. Copier les médicaments de l'ordonnance courante
    $nOrdCourant = $ordCourante['n_ordon'];
    $stmtMeds = $db->prepare("SELECT * FROM PROD WHERE N_ord = ? ORDER BY Ordre");
    $stmtMeds->execute([$nOrdCourant]);
    $meds = $stmtMeds->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($meds)) {
        $stmtInsertMed = $db->prepare("
            INSERT INTO PROD (N_ord, produit, posologie, DUREE, Ordre)
            VALUES (?, ?, ?, ?, ?)
        ");
        foreach ($meds as $idx => $med) {
            $stmtInsertMed->execute([
                $nOrdNouveau,
                $med['produit'],
                $med['posologie'],
                $med['DUREE'],
                $med['Ordre'] ?? ($idx + 1),
            ]);
        }
    }

    // 5. Créer une nouvelle facture ECG à 300 DH à la date du jour
    $stmtECG  = $db->query("SELECT TOP 1 n_acte FROM t_acte_simplifiée WHERE ACTE LIKE '%ECG%' ORDER BY n_acte");
    $nActeECG = $stmtECG->fetchColumn();
    $nFacture = null;

    if ($nActeECG) {
        $stmtFact = $db->prepare("
            INSERT INTO facture (id, date_facture, montant)
            VALUES (?, CONVERT(datetime, ?, 120), 300)
        ");
        $stmtFact->execute([$id, $dateAujourdHui]);

        $nFacture = $db->query("SELECT MAX(n_facture) FROM facture WHERE id = $id")->fetchColumn();

        $stmtDetail = $db->prepare("
            INSERT INTO detail_acte (N_fact, ACTE, [date-H], prixU, Versé, dette)
            VALUES (?, ?, CONVERT(datetime, ?, 120), 300, 300, 0)
        ");
        $stmtDetail->execute([$nFacture, $nActeECG, $dateAujourdHui]);
    }

    $db->commit();

    echo json_encode([
        'success'   => true,
        'n_ordon'   => $nOrdNouveau,
        'n_facture' => $nFacture,
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>