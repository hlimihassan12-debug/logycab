<?php
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';
header('Content-Type: application/json; charset=utf-8');

$db    = getDB();
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {

        // ── Liste des bilans d'un patient (id + date + nb anormaux) ────
        case 'get_bilans':
            $id = (int)($input['id'] ?? $_GET['id'] ?? 0);
            if (!$id) { echo json_encode(['ok'=>false,'msg'=>'id manquant']); break; }
            $stmt = $db->prepare("
                SELECT b.n_bilan,
                       CONVERT(varchar(10), b.date_bilan, 103) AS date_fr,
                       b.date_bilan,
                       ISNULL(b.observation,'') AS observation,
                       COUNT(a.N_analyse)        AS nb_total,
                       SUM(CASE WHEN ISNULL(a.résultat,'') <> '' AND a.résultat <> 'N' THEN 1 ELSE 0 END) AS nb_anormal
                FROM LE_BILAN b
                LEFT JOIN analyses a ON a.N_bilan = b.n_bilan
                WHERE b.id = ?
                GROUP BY b.n_bilan, b.date_bilan, b.observation
                ORDER BY b.date_bilan DESC
            ");
            $stmt->execute([$id]);
            echo json_encode(['ok'=>true, 'bilans'=>$stmt->fetchAll()]);
            break;

        // ── Détail d'un bilan : toutes les lignes avec flag anormal ────
        case 'get_detail':
            $n_bilan = (int)($input['n_bilan'] ?? $_GET['n_bilan'] ?? 0);
            if (!$n_bilan) { echo json_encode(['ok'=>false,'msg'=>'n_bilan manquant']); break; }

            // Info bilan
            $stmtB = $db->prepare("
                SELECT n_bilan,
                       CONVERT(varchar(10), date_bilan, 103) AS date_fr,
                       ISNULL(observation,'') AS observation
                FROM LE_BILAN WHERE n_bilan = ?
            ");
            $stmtB->execute([$n_bilan]);
            $bilan = $stmtB->fetch();

            // Lignes d'analyses
            $stmtL = $db->prepare("
                SELECT a.N_analyse,
                       c.analyse AS nom,
                       c.rubrique,
                       ISNULL(a.résultat,'') AS resultat
                FROM analyses a
                LEFT JOIN C_ANALYSE c ON c.[N°TypeAnalyse] = a.bilan
                WHERE a.N_bilan = ?
                ORDER BY c.rubrique, c.analyse
            ");
            $stmtL->execute([$n_bilan]);
            $lignes = $stmtL->fetchAll();

            // Marquer anormal = résultat non vide ET != 'N'
            foreach ($lignes as &$l) {
                $v = trim($l['resultat']);
                $l['anormal'] = ($v !== '' && strtoupper($v) !== 'N') ? 1 : 0;
            }
            unset($l);

            echo json_encode(['ok'=>true, 'bilan'=>$bilan, 'lignes'=>$lignes]);
            break;

        default:
            echo json_encode(['ok'=>false,'msg'=>'Action inconnue']);
    }
} catch (Exception $e) {
    echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}
