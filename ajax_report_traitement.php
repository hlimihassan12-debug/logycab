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

try {
    // 1. Récupérer l'ordonnance courante (la plus récente)
    $stmtOrd = $db->prepare("SELECT TOP 1 * FROM ORD WHERE id = ? ORDER BY date_ordon DESC");
    $stmtOrd->execute([$id]);
    $ordCourante = $stmtOrd->fetch(PDO::FETCH_ASSOC);

    if (!$ordCourante) {
        echo json_encode(['success' => false, 'error' => 'Aucune ordonnance trouvée']);
        exit;
    }

    // 2. Calculer les nouvelles dates
    $dateAujourd = date('Y-m-d');
    $dateNouvelleOrd = date('Y-m-d H:i:s'); // date ordonnance = aujourd'hui

    $dtRdv = new DateTime();
    $dtRdv->modify("+{$mois} months");
    $dateNouveauRdv = $dtRdv->format('Y-m-d') . ' 00:00:00';
    $jourRdv  = (int)$dtRdv->format('d');
    $moisRdv  = (int)$dtRdv->format('m');

    $db->beginTransaction();

    // 3. Insérer la nouvelle ordonnance
    $stmtInsert = $db->prepare("
        INSERT INTO ORD (
            id, date_ordon, acte1,
            [DATE REDEZ VOUS], HeureRDV,
            jour_rdv, mois_rdv, JourRDV
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmtInsert->execute([
        $id,
        $dateNouvelleOrd,
        $ordCourante['acte1'] ?? '',
        $dateNouveauRdv,
        $ordCourante['HeureRDV'] ?? '',
        $jourRdv,
        $moisRdv,
        $dateNouveauRdv,
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
    // Trouver le n_acte de l'ECG dans t_acte_simplifiée
    $stmtECG = $db->query("SELECT TOP 1 n_acte FROM t_acte_simplifiée WHERE ACTE LIKE '%ECG%' ORDER BY n_acte");
    $nActeECG = $stmtECG->fetchColumn();

    if ($nActeECG) {
        // Créer la facture
        $stmtFact = $db->prepare("
            INSERT INTO facture (id, date_facture, montant)
            VALUES (?, ?, 300)
        ");
        $stmtFact->execute([$id, $dateAujourd . ' 00:00:00']);
        $nFacture = $db->query("SELECT MAX(n_facture) FROM facture WHERE id = $id")->fetchColumn();

        // Créer le détail acte
        $stmtDetail = $db->prepare("
            INSERT INTO detail_acte (N_fact, ACTE, [date-H], prixU, Versé, dette)
            VALUES (?, ?, ?, 300, 300, 0)
        ");
        $stmtDetail->execute([$nFacture, $nActeECG, $dateAujourd . ' 00:00:00']);
    }

    $db->commit();

    echo json_encode([
        'success'  => true,
        'n_ordon'  => $nOrdNouveau,
        'n_facture' => $nFacture ?? null,
    ]);

} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>