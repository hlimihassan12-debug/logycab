<?php
require_once __DIR__ . '/backend/auth.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'backend/db.php';
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
if ($id == 0) { header('Location: recherche.php'); exit; }

try {
    // 1. Récupérer la dernière ordonnance
    $stmt = $db->prepare("
        SELECT TOP 1 n_ordon, date_ordon 
        FROM ORD WHERE id = ? 
        ORDER BY date_ordon DESC
    ");
    $stmt->execute([$id]);
    $dernOrd = $stmt->fetch();

    if (!$dernOrd) {
        die("❌ Aucune ordonnance trouvée !");
    }

    $nOrdSource = $dernOrd['n_ordon'];

    // 2. Créer nouvelle ordonnance
    $db->prepare("
        INSERT INTO ORD (id, date_ordon, DateSaisie)
        VALUES (?, CAST(GETDATE() AS DATE), GETDATE())
    ")->execute([$id]);
    
    $nNewOrd = $db->lastInsertId();

    // 3. Copier les médicaments
    $db->prepare("
        INSERT INTO PROD (N_ord, produit, posologie, DUREE, Ordre)
        SELECT ?, produit, posologie, DUREE, Ordre
        FROM PROD WHERE N_ord = ?
    ")->execute([$nNewOrd, $nOrdSource]);

    // 4. Créer facture ECG
    $db->prepare("
        INSERT INTO facture (id, date_facture)
        VALUES (?, CAST(GETDATE() AS DATE))
    ")->execute([$id]);
    
    $nNewFact = $db->lastInsertId();

    // Récupérer coût ECG
    $stmtECG = $db->prepare("SELECT cout FROM t_acte_simplifiée WHERE n_acte = 65");
    $stmtECG->execute();
    $coutECG = $stmtECG->fetchColumn() ?: 300;

    // Insérer acte ECG
    $db->prepare("
        INSERT INTO detail_acte (N_fact, [date-H], ACTE, prixU, Versé)
        VALUES (?, CAST(GETDATE() AS DATE), 65, ?, ?)
    ")->execute([$nNewFact, $coutECG, $coutECG]);

    // 5. Calculer RDV dans 3 mois
    $db->prepare("
        UPDATE ORD SET 
            [DATE REDEZ VOUS] = DATEADD(month, 3, CAST(GETDATE() AS DATE)),
            Date_Rdv = DATEADD(month, 3, CAST(GETDATE() AS DATE)),
            mois_rdv = 3
        WHERE n_ordon = ?
    ")->execute([$nNewOrd]);

    // Rediriger vers consultation
    header("Location: consultation.php?id=$id&msg=ok");
    exit;

} catch (Exception $e) {
    die("❌ Erreur : " . $e->getMessage());
}