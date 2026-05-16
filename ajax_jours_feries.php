<?php
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';
header('Content-Type: application/json');

$db     = getDB();
$body   = json_decode(file_get_contents('php://input'), true);
$action = $body['action'] ?? $_GET['action'] ?? '';

try {

    switch ($action) {

        // ── Lister tous les jours fériés ─────────────────────
        case 'lister': {
            $stmt = $db->query("SELECT DateFerie FROM T_JourFeries ORDER BY DateFerie");
            $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
            // Normaliser en YYYY-MM-DD pour l'affichage
            $dates = [];
            foreach ($rows as $r) {
                $r = trim($r);
                if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $r, $m)) {
                    $dates[] = $m[3].'-'.$m[2].'-'.$m[1];
                } else {
                    $ts = strtotime($r);
                    if ($ts) $dates[] = date('Y-m-d', $ts);
                }
            }
            sort($dates);
            echo json_encode(['ok' => true, 'dates' => $dates]);
            break;
        }

        // ── Ajouter un jour férié ─────────────────────────────
        case 'ajouter': {
            $date = trim($body['date'] ?? '');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                echo json_encode(['ok' => false, 'error' => 'Date invalide']); break;
            }
            // Vérifier si déjà présent
            $check = $db->prepare("SELECT COUNT(*) FROM T_JourFeries WHERE DateFerie = ?");
            $check->execute([$date]);
            if ($check->fetchColumn() > 0) {
                echo json_encode(['ok' => false, 'error' => 'Date déjà enregistrée']); break;
            }
            $db->prepare("INSERT INTO T_JourFeries (DateFerie) VALUES (?)")->execute([$date]);
            echo json_encode(['ok' => true]);
            break;
        }

        // ── Supprimer un jour férié ───────────────────────────
        case 'supprimer': {
            $date = trim($body['date'] ?? '');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                echo json_encode(['ok' => false, 'error' => 'Date invalide']); break;
            }
            // Supprimer les deux formats possibles (YYYY-MM-DD et DD/MM/YYYY)
            $d = new DateTime($date);
            $db->prepare("DELETE FROM T_JourFeries WHERE DateFerie = ? OR DateFerie = ?")->execute([
                $d->format('Y-m-d'),
                $d->format('d/m/Y')
            ]);
            echo json_encode(['ok' => true]);
            break;
        }

        default:
            echo json_encode(['ok' => false, 'error' => 'Action inconnue']);
    }

} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
