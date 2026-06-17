<?php
require_once __DIR__ . '/backend/db.php';
header('Content-Type: application/json; charset=utf-8');

$db = getDB();

// Lire le body JSON une seule fois (pour les POST en application/json)
$bodyRaw  = file_get_contents('php://input');
$bodyJson = $bodyRaw ? (json_decode($bodyRaw, true) ?? []) : [];

// L'action peut venir : 1) du body JSON, 2) de $_POST, 3) de $_GET
$action = $bodyJson['action'] ?? $_POST['action'] ?? $_GET['action'] ?? '';

// ══════════════════════════════════════════════════════════════
// ACTION : chercher_patients  — autocomplétion (nom ou n°pat)
// ══════════════════════════════════════════════════════════════
if ($action === 'chercher_patients') {
    $q = trim($_GET['q'] ?? '');
    if (strlen($q) < 2) { echo json_encode([]); exit; }
    $stmt = $db->prepare("SELECT TOP 15 [N°PAT], NOMPRENOM FROM ID
        WHERE NOMPRENOM LIKE ? OR CAST([N°PAT] AS VARCHAR) LIKE ?
        ORDER BY NOMPRENOM");
    $stmt->execute(["%$q%", "%$q%"]);
    echo json_encode($stmt->fetchAll());
    exit;
}

// ══════════════════════════════════════════════════════════════
// ACTION : get_patient  — infos patient par id
// ══════════════════════════════════════════════════════════════
if ($action === 'get_patient') {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $db->prepare("SELECT [N°PAT], NOMPRENOM FROM ID WHERE [N°PAT] = ?");
    $stmt->execute([$id]);
    $p = $stmt->fetch();
    echo json_encode($p ?: ['error' => 'Patient introuvable']);
    exit;
}

// ══════════════════════════════════════════════════════════════
// ACTION : chercher_produits  — autocomplétion catalogue
// ══════════════════════════════════════════════════════════════
if ($action === 'chercher_produits') {
    $q = trim($_GET['q'] ?? '');
    if (strlen($q) < 1) { echo json_encode([]); exit; }
    $stmt = $db->prepare("SELECT TOP 20 NuméroPRODUIT, PRODUIT FROM PRODUITS
        WHERE PRODUIT LIKE ? ORDER BY PRODUIT");
    $stmt->execute(["%$q%"]);
    echo json_encode($stmt->fetchAll());
    exit;
}

// ══════════════════════════════════════════════════════════════
// ACTION : liste_produits  — liste complète catalogue
// ══════════════════════════════════════════════════════════════
if ($action === 'liste_produits') {
    // Compte les utilisations dans PROD pour détecter les orphelins
    $stmt = $db->query("
        SELECT pr.NuméroPRODUIT, pr.PRODUIT,
               COUNT(pd.N_ord) AS nb_utilisations
        FROM PRODUITS pr
        LEFT JOIN PROD pd ON pd.produit = pr.NuméroPRODUIT
        GROUP BY pr.NuméroPRODUIT, pr.PRODUIT
        ORDER BY pr.PRODUIT
    ");
    echo json_encode($stmt->fetchAll());
    exit;
}

// ══════════════════════════════════════════════════════════════
// ACTION : supprimer_orphelins  — supprimer tous les non utilisés
// ══════════════════════════════════════════════════════════════
if ($action === 'supprimer_orphelins') {
    $stmt = $db->query("
        SELECT pr.NuméroPRODUIT FROM PRODUITS pr
        LEFT JOIN PROD pd ON pd.produit = pr.NuméroPRODUIT
        WHERE pd.N_ord IS NULL
    ");
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (!$ids) { echo json_encode(['success'=>true,'nb'=>0]); exit; }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $db->prepare("DELETE FROM PRODUITS WHERE NuméroPRODUIT IN ($placeholders)")->execute($ids);
    echo json_encode(['success'=>true,'nb'=>count($ids)]);
    exit;
}

// ══════════════════════════════════════════════════════════════
// ACTION : ordonnances_du_produit  — lister les ordonnances contenant un produit
// ══════════════════════════════════════════════════════════════
if ($action === 'ordonnances_du_produit') {
    $prodId = (int)($_GET['prod_id'] ?? 0);
    if (!$prodId) { echo json_encode([]); exit; }
    $stmt = $db->prepare("
        SELECT
            o.n_ordon, o.id,
            CONVERT(varchar, o.date_ordon, 103) AS date_fr,
            o.date_ordon,
            p.NOMPRENOM,
            pd.posologie, pd.DUREE
        FROM PROD pd
        JOIN ORD o  ON o.n_ordon = pd.N_ord
        JOIN ID  p  ON p.[N°PAT] = o.id
        WHERE pd.produit = ?
        ORDER BY o.date_ordon DESC
    ");
    $stmt->execute([$prodId]);
    echo json_encode($stmt->fetchAll());
    exit;
}

// ══════════════════════════════════════════════════════════════
// ACTION : ajouter_produit  — ajouter au catalogue PRODUITS
// ══════════════════════════════════════════════════════════════
if ($action === 'ajouter_produit') {
    $nom = strtoupper(trim($bodyJson['nom'] ?? ''));
    if (!$nom) { echo json_encode(['success'=>false,'error'=>'Nom vide']); exit; }
    // Vérifier doublon
    $stmt = $db->prepare("SELECT COUNT(*) FROM PRODUITS WHERE PRODUIT = ?");
    $stmt->execute([$nom]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success'=>false,'error'=>'Ce médicament existe déjà dans le catalogue']);
        exit;
    }
    // Insérer
    $db->prepare("INSERT INTO PRODUITS (PRODUIT) VALUES (?)")->execute([$nom]);
    // Récupérer l'ID généré
    $newId = $db->lastInsertId();
    echo json_encode(['success'=>true,'id'=>$newId,'nom'=>$nom]);
    exit;
}

// ══════════════════════════════════════════════════════════════
// ACTION : supprimer_produit  — supprimer du catalogue
// ══════════════════════════════════════════════════════════════
if ($action === 'supprimer_produit') {
    $id = (int)($bodyJson['id'] ?? 0);
    if (!$id) { echo json_encode(['success'=>false,'error'=>'ID invalide']); exit; }
    // Vérifier si utilisé dans des ordonnances
    $stmt = $db->prepare("SELECT COUNT(*) FROM PROD WHERE produit = ?");
    $stmt->execute([$id]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success'=>false,'error'=>'Ce médicament est utilisé dans des ordonnances existantes — suppression impossible']);
        exit;
    }
    $db->prepare("DELETE FROM PRODUITS WHERE NuméroPRODUIT = ?")->execute([$id]);
    echo json_encode(['success'=>true]);
    exit;
}

// ══════════════════════════════════════════════════════════════
// ACTION : chercher_ordonnances  — par date / nom / n°pat
// ══════════════════════════════════════════════════════════════
if ($action === 'chercher_ordonnances') {
    $date  = trim($_GET['date']  ?? '');
    $nom   = trim($_GET['nom']   ?? '');
    $nopat = trim($_GET['nopat'] ?? '');

    $where = []; $params = [];

    if ($date) {
        // date au format YYYY-MM-DD (input type=date)
        $dateSQL = str_replace('-', '', $date); // → YYYYMMDD
        $where[]  = "CONVERT(varchar,o.date_ordon,112) = ?";
        $params[] = $dateSQL;
    }
    if ($nom) {
        $where[]  = "p.NOMPRENOM LIKE ?";
        $params[] = "%$nom%";
    }
    if ($nopat) {
        $where[]  = "o.id = ?";
        $params[] = (int)$nopat;
    }

    if (empty($where)) { echo json_encode([]); exit; }

    $sql = "SELECT TOP 50
        o.n_ordon, o.id, o.date_ordon,
        CONVERT(varchar,o.date_ordon,103) AS date_fr,
        o.[DATE REDEZ VOUS], o.HeureRDV, o.acte1,
        p.NOMPRENOM
        FROM ORD o
        LEFT JOIN ID p ON o.id = p.[N°PAT]
        WHERE " . implode(' AND ', $where) . "
        ORDER BY o.date_ordon DESC, o.n_ordon DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Ajouter les médicaments pour chaque ordonnance
    foreach ($rows as &$row) {
        $stmtM = $db->prepare("SELECT pr.PRODUIT, pd.posologie, pd.DUREE
            FROM PROD pd LEFT JOIN PRODUITS pr ON pd.produit = pr.NuméroPRODUIT
            WHERE pd.N_ord = ? ORDER BY pd.Ordre");
        $stmtM->execute([$row['n_ordon']]);
        $row['medicaments'] = $stmtM->fetchAll();
    }
    echo json_encode($rows);
    exit;
}

// ══════════════════════════════════════════════════════════════
// ACTION : get_ordonnance  — charger une ordonnance complète
// ══════════════════════════════════════════════════════════════
if ($action === 'get_ordonnance') {
    $nOrd = (int)($_GET['n_ordon'] ?? 0);
    $id   = (int)($_GET['id']     ?? 0);
    if (!$nOrd || !$id) { echo json_encode(['error'=>'Paramètres manquants']); exit; }

    $stmt = $db->prepare("SELECT o.*, p.NOMPRENOM,
        CONVERT(varchar,o.date_ordon,120) AS date_ordon_iso,
        CONVERT(varchar,o.[DATE REDEZ VOUS],120) AS date_rdv_iso
        FROM ORD o LEFT JOIN ID p ON o.id = p.[N°PAT]
        WHERE o.n_ordon = ? AND o.id = ?");
    $stmt->execute([$nOrd, $id]);
    $ord = $stmt->fetch();
    if (!$ord) { echo json_encode(['error'=>'Ordonnance introuvable']); exit; }

    $stmtM = $db->prepare("SELECT pd.*, pr.PRODUIT
        FROM PROD pd LEFT JOIN PRODUITS pr ON pd.produit = pr.NuméroPRODUIT
        WHERE pd.N_ord = ? ORDER BY pd.Ordre");
    $stmtM->execute([$nOrd]);
    $ord['medicaments'] = $stmtM->fetchAll();

    echo json_encode($ord);
    exit;
}

// ══════════════════════════════════════════════════════════════
// ACTION : enregistrer_ordonnance  — nouvelle ordonnance
// ══════════════════════════════════════════════════════════════
if ($action === 'enregistrer_ordonnance') {
    $patientId = (int)($bodyJson['id']        ?? 0);
    $dateOrdon = trim($bodyJson['date_ordon'] ?? '');
    $acte      = trim($bodyJson['acte']       ?? '');
    $dateRdv   = trim($bodyJson['date_rdv']   ?? '');
    $heureRdv  = trim($bodyJson['heure_rdv']  ?? '');
    $lignes    = $bodyJson['lignes']          ?? [];

    if (!$patientId || !$dateOrdon) {
        echo json_encode(['success'=>false,'error'=>'Patient et date obligatoires']); exit;
    }

    // Vérifier que le patient existe
    $stmt = $db->prepare("SELECT COUNT(*) FROM ID WHERE [N°PAT] = ?");
    $stmt->execute([$patientId]);
    if (!$stmt->fetchColumn()) {
        echo json_encode(['success'=>false,'error'=>'Patient introuvable']); exit;
    }

    $dateOrdSQL = str_replace('-', '', $dateOrdon); // YYYYMMDD
    $dateRdvSQL = $dateRdv ? str_replace('-', '', $dateRdv) : null;

    if ($dateRdvSQL) {
        $db->prepare("INSERT INTO ORD (id, date_ordon, [DATE REDEZ VOUS], HeureRDV, acte1)
            VALUES (?, CONVERT(datetime,?,112), CONVERT(datetime,?,112), ?, ?)")
            ->execute([$patientId, $dateOrdSQL, $dateRdvSQL, $heureRdv ?: null, $acte ?: null]);
    } else {
        $db->prepare("INSERT INTO ORD (id, date_ordon, [DATE REDEZ VOUS], HeureRDV, acte1)
            VALUES (?, CONVERT(datetime,?,112), NULL, ?, ?)")
            ->execute([$patientId, $dateOrdSQL, $heureRdv ?: null, $acte ?: null]);
    }

    // Récupérer n_ordon généré
    $nOrd = (int)$db->query("SELECT TOP 1 n_ordon FROM ORD WHERE id=$patientId ORDER BY n_ordon DESC")->fetchColumn();

    // Insérer les lignes médicaments
    foreach ($lignes as $i => $ligne) {
        $medId = (int)($ligne['med']  ?? 0);
        $poso  = trim($ligne['poso']  ?? '');
        $duree = trim($ligne['duree'] ?? '');
        if (!$medId) continue;
        $db->prepare("INSERT INTO PROD (N_ord, produit, posologie, DUREE, Ordre) VALUES (?,?,?,?,?)")
            ->execute([$nOrd, $medId, $poso ?: null, $duree ?: null, $i+1]);
    }

    echo json_encode(['success'=>true,'n_ordon'=>$nOrd,'id'=>$patientId]);
    exit;
}

// ══════════════════════════════════════════════════════════════
// ACTION : modifier_ordonnance  — mettre à jour une ordonnance
// ══════════════════════════════════════════════════════════════
if ($action === 'modifier_ordonnance') {
    $nOrd      = (int)($bodyJson['n_ordon']    ?? 0);
    $patientId = (int)($bodyJson['id']         ?? 0);
    $dateOrdon = trim($bodyJson['date_ordon']  ?? '');
    $acte      = trim($bodyJson['acte']        ?? '');
    $dateRdv   = trim($bodyJson['date_rdv']    ?? '');
    $heureRdv  = trim($bodyJson['heure_rdv']   ?? '');
    $lignes    = $bodyJson['lignes']           ?? [];

    if (!$nOrd || !$patientId) {
        echo json_encode(['success'=>false,'error'=>'Paramètres manquants']); exit;
    }

    $dateOrdSQL = str_replace('-', '', $dateOrdon);
    $dateRdvSQL = $dateRdv ? str_replace('-', '', $dateRdv) : null;

    if ($dateRdvSQL) {
        $db->prepare("UPDATE ORD SET
            date_ordon = CONVERT(datetime,?,112),
            [DATE REDEZ VOUS] = CONVERT(datetime,?,112),
            HeureRDV = ?, acte1 = ?
            WHERE n_ordon = ? AND id = ?")
            ->execute([$dateOrdSQL, $dateRdvSQL, $heureRdv ?: null, $acte ?: null, $nOrd, $patientId]);
    } else {
        $db->prepare("UPDATE ORD SET
            date_ordon = CONVERT(datetime,?,112),
            [DATE REDEZ VOUS] = NULL,
            HeureRDV = ?, acte1 = ?
            WHERE n_ordon = ? AND id = ?")
            ->execute([$dateOrdSQL, $heureRdv ?: null, $acte ?: null, $nOrd, $patientId]);
    }

    // Supprimer anciennes lignes puis réinsérer
    $db->prepare("DELETE FROM PROD WHERE N_ord = ?")->execute([$nOrd]);
    foreach ($lignes as $i => $ligne) {
        $medId = (int)($ligne['med']  ?? 0);
        $poso  = trim($ligne['poso']  ?? '');
        $duree = trim($ligne['duree'] ?? '');
        if (!$medId) continue;
        $db->prepare("INSERT INTO PROD (N_ord, produit, posologie, DUREE, Ordre) VALUES (?,?,?,?,?)")
            ->execute([$nOrd, $medId, $poso ?: null, $duree ?: null, $i+1]);
    }

    echo json_encode(['success'=>true,'n_ordon'=>$nOrd,'id'=>$patientId]);
    exit;
}

// ══════════════════════════════════════════════════════════════
// ACTION : creer_facture  — crée une entrée dans facture
// ══════════════════════════════════════════════════════════════
if ($action === 'creer_facture') {
    $patientId = (int)($bodyJson['id']      ?? 0);
    $nOrd      = (int)($bodyJson['n_ordon'] ?? 0);
    if (!$patientId) { echo json_encode(['success'=>false,'error'=>'Patient manquant']); exit; }

    $today = date('Ymd');
    $db->prepare("INSERT INTO facture (id, date_facture, n_ordon) VALUES (?, CONVERT(datetime,?,112), ?)")
        ->execute([$patientId, $today, $nOrd ?: null]);
    $nFact = (int)$db->query("SELECT TOP 1 n_facture FROM facture WHERE id=$patientId ORDER BY n_facture DESC")->fetchColumn();

    echo json_encode(['success'=>true,'n_facture'=>$nFact,'id'=>$patientId]);
    exit;
}

echo json_encode(['success'=>false,'error'=>"Action '$action' inconnue"]);
