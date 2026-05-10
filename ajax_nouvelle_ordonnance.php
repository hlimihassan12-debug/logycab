<?php
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$id   = (int)($data['id'] ?? 0);

// ── Validations obligatoires ──────────────────────────────────────────
if ($id == 0) {
    echo json_encode(['success' => false, 'error' => 'Patient invalide']);
    exit;
}
if (empty($data['date_ordon'])) {
    echo json_encode(['success' => false, 'error' => 'Date ordonnance obligatoire']);
    exit;
}
if (empty($data['date_rdv'])) {
    echo json_encode(['success' => false, 'error' => 'Date RDV obligatoire']);
    exit;
}
if (empty($data['heure_rdv'])) {
    echo json_encode(['success' => false, 'error' => 'Heure RDV obligatoire']);
    exit;
}
// ─────────────────────────────────────────────────────────────────────

// ── Formatage des dates ───────────────────────────────────────────────
$date_ordon = trim($data['date_ordon']) . ' 00:00:00';   // "2026-05-10 00:00:00"

$date_rdv = trim($data['date_rdv']) . ' 00:00:00';        // "2026-08-10 00:00:00"

$heure_rdv = trim($data['heure_rdv']);                     // "09:30"

$acte   = $data['acte']   ?? '';
$lignes = $data['lignes'] ?? [];

// Calcul jour_rdv et mois_rdv (colonnes numériques dans ORD)
$dtRdv   = new DateTime($data['date_rdv']);
$jour_rdv = (int)$dtRdv->format('d');
$mois_rdv = (int)$dtRdv->format('m');

$db = getDB();

try {
    $db->beginTransaction();

    // INSERT ordonnance avec toutes les dates
    $stmt = $db->prepare("
        INSERT INTO ORD (
            id, date_ordon, acte1,
            [DATE REDEZ VOUS], HeureRDV,
            jour_rdv, mois_rdv, JourRDV
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $id,
        $date_ordon,
        $acte,
        $date_rdv,    // DATE REDEZ VOUS — correctement rempli
        $heure_rdv,   // HeureRDV — correctement rempli
        $jour_rdv,
        $mois_rdv,
        $date_rdv,    // JourRDV (même valeur que DATE REDEZ VOUS)
    ]);

    $nOrd = $db->query("SELECT MAX(n_ordon) FROM ORD WHERE id = $id")->fetchColumn();

    // INSERT médicaments
    if (!empty($lignes)) {
        $stmtMed = $db->prepare("
            INSERT INTO PROD (N_ord, produit, posologie, DUREE, Ordre)
            VALUES (?, ?, ?, ?, ?)
        ");
        foreach ($lignes as $idx => $l) {
            $stmtMed->execute([
                $nOrd,
                (int)$l['med'],
                $l['poso'],
                $l['duree'],
                $idx + 1
            ]);
        }
    }

    $db->commit();
    echo json_encode(['success' => true, 'n_ordon' => $nOrd]);

} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>