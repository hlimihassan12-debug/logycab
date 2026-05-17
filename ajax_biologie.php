<?php
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';
header('Content-Type: application/json; charset=utf-8');

$db     = getDB();
$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {

        // ── Liste des profils (rubriques) depuis C_ANALYSE ─────────────
        case 'get_profils':
            $stmt = $db->query("
                SELECT DISTINCT rubrique
                FROM C_ANALYSE
                WHERE rubrique IS NOT NULL AND rubrique <> ''
                ORDER BY rubrique
            ");
            echo json_encode(['ok' => true, 'profils' => $stmt->fetchAll(PDO::FETCH_COLUMN)]);
            break;

        // ── Analyses d'un profil ────────────────────────────────────────
        case 'get_analyses_profil':
            $profil = $input['profil'] ?? '';
            $stmt = $db->prepare("
                SELECT [N°TypeAnalyse] AS id, analyse
                FROM C_ANALYSE
                WHERE rubrique = ?
                ORDER BY [N°TypeAnalyse]
            ");
            $stmt->execute([$profil]);
            echo json_encode(['ok' => true, 'analyses' => $stmt->fetchAll()]);
            break;

        // ── Toutes les analyses — distinct sur analyse, sans doublons ──
        case 'get_all_analyses':
            $stmt = $db->query("
                SELECT MIN([N°TypeAnalyse]) AS id, analyse
                FROM C_ANALYSE
                GROUP BY analyse
                ORDER BY analyse
            ");
            echo json_encode(['ok' => true, 'analyses' => $stmt->fetchAll()]);
            break;

        // ── Liste des bilans d'un patient ───────────────────────────────
        case 'get_bilans':
            $id = (int)($input['id'] ?? 0);
            $stmt = $db->prepare("
                SELECT b.n_bilan,
                       CONVERT(varchar(10), b.date_bilan, 103) AS date_fr,
                       b.date_bilan,
                       ISNULL(b.observation, '') AS observation,
                       COUNT(a.N_analyse) AS nb_analyses
                FROM LE_BILAN b
                LEFT JOIN analyses a ON a.N_bilan = b.n_bilan
                WHERE b.id = ?
                GROUP BY b.n_bilan, b.date_bilan, b.observation
                ORDER BY b.date_bilan DESC
            ");
            $stmt->execute([$id]);
            echo json_encode(['ok' => true, 'bilans' => $stmt->fetchAll()]);
            break;

        // ── Détail d'un bilan (analyses + résultats) ───────────────────
        case 'get_detail_bilan':
            $n_bilan = (int)($input['n_bilan'] ?? 0);
            $stmt = $db->prepare("
                SELECT a.N_analyse,
                       a.bilan AS id_analyse,
                       c.analyse AS nom_analyse,
                       c.rubrique,
                       ISNULL(a.résultat, '') AS resultat
                FROM analyses a
                LEFT JOIN C_ANALYSE c ON c.[N°TypeAnalyse] = a.bilan
                WHERE a.N_bilan = ?
                ORDER BY c.rubrique, c.analyse
            ");
            $stmt->execute([$n_bilan]);
            // Info bilan
            $stmtB = $db->prepare("
                SELECT n_bilan, CONVERT(varchar(10), date_bilan, 103) AS date_fr,
                       ISNULL(observation,'') AS observation
                FROM LE_BILAN WHERE n_bilan = ?
            ");
            $stmtB->execute([$n_bilan]);
            $bilan = $stmtB->fetch();
            echo json_encode(['ok' => true, 'bilan' => $bilan, 'lignes' => $stmt->fetchAll()]);
            break;

        // ── Créer un nouveau bilan ──────────────────────────────────────
        case 'creer_bilan':
            $id         = (int)($input['id'] ?? 0);
            $date_bilan = $input['date_bilan'] ?? date('Y-m-d');
            $obs        = $input['observation'] ?? '';
            if (!$id) { echo json_encode(['ok'=>false,'msg'=>'Patient manquant']); break; }
            $stmt = $db->prepare("
                INSERT INTO LE_BILAN (id, date_bilan, observation)
                OUTPUT INSERTED.n_bilan
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$id, $date_bilan, $obs]);
            $n_bilan = $stmt->fetchColumn();
            echo json_encode(['ok' => true, 'n_bilan' => $n_bilan]);
            break;

        // ── Ajouter des analyses à un bilan (sans doublons) ────────────
        case 'ajouter_analyses':
            $n_bilan   = (int)($input['n_bilan'] ?? 0);
            $ids       = $input['ids'] ?? [];   // tableau de N°TypeAnalyse
            if (!$n_bilan || empty($ids)) { echo json_encode(['ok'=>false,'msg'=>'Données manquantes']); break; }

            // Récupérer les analyses déjà présentes
            $stmtEx = $db->prepare("SELECT bilan FROM analyses WHERE N_bilan = ?");
            $stmtEx->execute([$n_bilan]);
            $existants = $stmtEx->fetchAll(PDO::FETCH_COLUMN);

            $ajoutes = 0;
            $stmtIns = $db->prepare("
                INSERT INTO analyses (N_bilan, bilan, résultat) VALUES (?, ?, '')
            ");
            foreach ($ids as $id_analyse) {
                $id_analyse = (int)$id_analyse;
                if (!in_array($id_analyse, $existants)) {
                    $stmtIns->execute([$n_bilan, $id_analyse]);
                    $ajoutes++;
                }
            }
            echo json_encode(['ok' => true, 'ajoutes' => $ajoutes]);
            break;

        // ── Sauvegarder un résultat ─────────────────────────────────────
        case 'sauver_resultat':
            $n_analyse = (int)($input['n_analyse'] ?? 0);
            $resultat  = $input['resultat'] ?? '';
            if (!$n_analyse) { echo json_encode(['ok'=>false,'msg'=>'Ligne manquante']); break; }
            $stmt = $db->prepare("UPDATE analyses SET résultat = ? WHERE N_analyse = ?");
            $stmt->execute([$resultat, $n_analyse]);
            echo json_encode(['ok' => true]);
            break;

        // ── Mettre "N" dans tous les champs vides ──────────────────────
        case 'tout_normal':
            $n_bilan = (int)($input['n_bilan'] ?? 0);
            $stmt = $db->prepare("
                UPDATE analyses SET résultat = 'N'
                WHERE N_bilan = ? AND (résultat IS NULL OR résultat = '')
            ");
            $stmt->execute([$n_bilan]);
            echo json_encode(['ok' => true, 'nb' => $stmt->rowCount()]);
            break;

        // ── Supprimer une ligne d'analyse ───────────────────────────────
        case 'supprimer_ligne':
            $n_analyse = (int)($input['n_analyse'] ?? 0);
            $stmt = $db->prepare("DELETE FROM analyses WHERE N_analyse = ?");
            $stmt->execute([$n_analyse]);
            echo json_encode(['ok' => true]);
            break;

        // ── Supprimer toutes les analyses d'un bilan ───────────────────
        case 'vider_bilan':
            $n_bilan = (int)($input['n_bilan'] ?? 0);
            $stmt = $db->prepare("DELETE FROM analyses WHERE N_bilan = ?");
            $stmt->execute([$n_bilan]);
            echo json_encode(['ok' => true]);
            break;

        // ── Supprimer un bilan entier ───────────────────────────────────
        case 'supprimer_bilan':
            $n_bilan = (int)($input['n_bilan'] ?? 0);
            $db->prepare("DELETE FROM analyses  WHERE N_bilan = ?")->execute([$n_bilan]);
            $db->prepare("DELETE FROM LE_BILAN  WHERE n_bilan = ?")->execute([$n_bilan]);
            echo json_encode(['ok' => true]);
            break;

        // ── Mettre à jour la date et observation d'un bilan ────────────
        case 'maj_bilan':
            $n_bilan    = (int)($input['n_bilan'] ?? 0);
            $date_bilan = $input['date_bilan'] ?? '';
            $obs        = $input['observation'] ?? '';
            $stmt = $db->prepare("UPDATE LE_BILAN SET date_bilan=?, observation=? WHERE n_bilan=?");
            $stmt->execute([$date_bilan, $obs, $n_bilan]);
            echo json_encode(['ok' => true]);
            break;

        default:
            echo json_encode(['ok' => false, 'msg' => 'Action inconnue : ' . $action]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
}
