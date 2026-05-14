<?php
/**
 * ajax_modifier_ordonnance.php
 * Modifie une ordonnance existante :
 * - date_ordon, acte1, DATE REDEZ VOUS, HeureRDV, jour_rdv, mois_rdv, JourRDV
 * - Recrée les médicaments (PROD) : supprime les anciens, insère les nouveaux
 *
 * POST JSON :
 * {
 *   "n_ordon"   : 1234,
 *   "id"        : 56,
 *   "date_ordon": "2026-05-14",
 *   "acte"      : "ECG",
 *   "date_rdv"  : "2026-08-14",
 *   "heure_rdv" : "09:30",
 *   "lignes"    : [
 *     { "produit": 12, "poso": "1 cp 1 fois par jour", "duree": "3 mois" },
 *     ...
 *   ]
 * }
 */

require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';

header('Content-Type: application/json');

$data   = json_decode(file_get_contents('php://input'), true);
$nOrd   = (int)($data['n_ordon']    ?? 0);
$id     = (int)($data['id']         ?? 0);
$dateOrd= trim($data['date_ordon']  ?? '');
$acte   = trim($data['acte']        ?? '');
$dateRdv= trim($data['date_rdv']    ?? '');
$heure  = trim($data['heure_rdv']   ?? '');
$lignes = $data['lignes']           ?? [];

if ($nOrd == 0 || $id == 0) {
    echo json_encode(['success' => false, 'error' => 'Données invalides']);
    exit;
}
if (!$dateOrd || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateOrd)) {
    echo json_encode(['success' => false, 'error' => 'Date ordonnance invalide']);
    exit;
}
if (!$dateRdv || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateRdv)) {
    echo json_encode(['success' => false, 'error' => 'Date RDV invalide']);
    exit;
}
if (!$heure) {
    echo json_encode(['success' => false, 'error' => 'Heure RDV obligatoire']);
    exit;
}

$db = getDB();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    $db->beginTransaction();

    // Calculer jour_rdv / mois_rdv / JourRDV à partir de date_rdv
    $dtRdv   = new DateTime($dateRdv);
    $jourRdv = (int)$dtRdv->format('d');
    $moisRdv = (int)$dtRdv->format('m');
    $dateRdvDatetime = $dateRdv . ' 00:00:00'; // format ISO pour CONVERT 120
    $dateOrdDatetime = $dateOrd . ' 00:00:00';

    // 1. Mettre à jour l'ordonnance
    $stmtUpd = $db->prepare("
        UPDATE ORD SET
            date_ordon        = CONVERT(datetime, ?, 120),
            acte1             = ?,
            [DATE REDEZ VOUS] = CONVERT(datetime, ?, 120),
            Date_Rdv          = CONVERT(datetime, ?, 120),
            HeureRDV          = ?,
            jour_rdv          = ?,
            mois_rdv          = ?,
            JourRDV           = CONVERT(date, ?, 23)
        WHERE n_ordon = ? AND id = ?
    ");
    $stmtUpd->execute([
        $dateOrdDatetime,   // date_ordon
        $acte,              // acte1
        $dateRdvDatetime,   // DATE REDEZ VOUS
        $dateRdvDatetime,   // Date_Rdv
        $heure,             // HeureRDV
        $jourRdv,           // jour_rdv
        $moisRdv,           // mois_rdv
        $dateRdv,           // JourRDV
        $nOrd,
        $id,
    ]);

    // 2. Supprimer les anciens médicaments
    $stmtDel = $db->prepare("DELETE FROM PROD WHERE N_ord = ?");
    $stmtDel->execute([$nOrd]);

    // 3. Insérer les nouveaux médicaments
    if (!empty($lignes)) {
        $stmtIns = $db->prepare("
            INSERT INTO PROD (N_ord, produit, posologie, DUREE, Ordre)
            VALUES (?, ?, ?, ?, ?)
        ");
        foreach ($lignes as $idx => $ligne) {
            $produit = (int)($ligne['produit'] ?? 0);
            $poso    = trim($ligne['poso']     ?? '');
            $duree   = trim($ligne['duree']    ?? '');
            if ($produit > 0) {
                $stmtIns->execute([$nOrd, $produit, $poso, $duree, $idx + 1]);
            }
        }
    }

    $db->commit();

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>