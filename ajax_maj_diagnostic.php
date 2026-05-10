<?php
/**
 * ajax_maj_diagnostic.php
 * Gestion CRUD des diagnostics patient
 *
 * POST JSON actions :
 * { "action": "update", "type": 1, "n_dic": 5, "valeur": "nouveau texte" }
 * { "action": "add",    "type": 1, "id": 1234, "valeur": "nouveau diagnostic" }
 * { "action": "delete", "type": 1, "n_dic": 5, "id": 1234 }
 *
 * type 1 = t_diagnostic       (N_dic, diagnostic)
 * type 2 = T_dianstcII        (N_DIC_II, DicII)
 * type 3 = T_id_dic_non_cardio (N_dic_non_cardio, dic_non_cardio)
 */

require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';

header('Content-Type: application/json');

$data   = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';
$type   = (int)($data['type'] ?? 1);
$id_pat = (int)($data['id']   ?? 0);
$valeur = trim($data['valeur'] ?? '');

// Config par type
$config = [
    1 => ['table' => 't_diagnostic',          'pk' => 'N_dic',           'champ' => 'diagnostic'],
    2 => ['table' => 'T_dianstcII',            'pk' => 'N_DIC_II',        'champ' => 'DicII'],
    3 => ['table' => 'T_id_dic_non_cardio',    'pk' => 'N_dic_non_cardio','champ' => 'dic_non_cardio'],
];

if (!isset($config[$type])) {
    echo json_encode(['success' => false, 'error' => 'Type invalide']);
    exit;
}

$tbl   = $config[$type]['table'];
$pk    = $config[$type]['pk'];
$champ = $config[$type]['champ'];

$db = getDB();

try {
    switch ($action) {

        case 'update':
            $n_dic = (int)($data['n_dic'] ?? 0);
            $stmt = $db->prepare("UPDATE [$tbl] SET [$champ] = ? WHERE [$pk] = ?");
            $stmt->execute([$valeur, $n_dic]);
            echo json_encode(['success' => true]);
            break;

        case 'add':
            if ($id_pat == 0) throw new Exception('Patient invalide');
            // N_dic est IDENTITY — ne pas l'insérer, SQL Server le génère
            $stmt = $db->prepare("INSERT INTO [$tbl] (id, [$champ]) VALUES (?, ?)");
            $stmt->execute([$id_pat, $valeur]);
            $newPk = $db->query("SELECT @@IDENTITY")->fetchColumn();
            echo json_encode(['success' => true, 'n_dic' => (int)$newPk]);
            break;

        case 'delete':
            $n_dic = (int)($data['n_dic'] ?? 0);
            $stmt = $db->prepare("DELETE FROM [$tbl] WHERE [$pk] = ? AND id = ?");
            $stmt->execute([$n_dic, $id_pat]);
            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Action inconnue']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>