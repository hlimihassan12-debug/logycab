<?php
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';
header('Content-Type: application/json');

$db   = getDB();
$body = json_decode(file_get_contents('php://input'), true);
$action = $body['action'] ?? '';

// ── Fonction utilitaire jours de la semaine ────────────────────
function strftime_fr($date) {
    $jours = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
    $mois  = ['','Janvier','Février','Mars','Avril','Mai','Juin',
               'Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
    $ts = strtotime($date);
    return $jours[date('w',$ts)] . ' ' . date('j',$ts) . ' ' . $mois[(int)date('n',$ts)] . ' ' . date('Y',$ts);
}

try {

    switch ($action) {

        // ── Marquer Vu ───────────────────────────────────────
        case 'toggle_vu': {
            $nOrd = (int)$body['n_ordon'];
            $vu   = (int)$body['vu'];
            $db->prepare("UPDATE ORD SET Vu=? WHERE n_ordon=?")->execute([$vu, $nOrd]);
            echo json_encode(['ok' => true]);
            break;
        }

        // ── Marquer Absent ───────────────────────────────────
        case 'toggle_absent': {
            $nOrd   = (int)$body['n_ordon'];
            $absent = (int)$body['absent'];
            $db->prepare("UPDATE ORD SET SansReponse=? WHERE n_ordon=?")->execute([$absent, $nOrd]);
            echo json_encode(['ok' => true]);
            break;
        }

        // ── Changer / effacer heure ───────────────────────────
        case 'changer_heure': {
            $nOrd  = (int)$body['n_ordon'];
            $heure = trim($body['heure'] ?? '');
            $val   = $heure === '' ? null : $heure;
            $db->prepare("UPDATE ORD SET HeureRDV=? WHERE n_ordon=?")->execute([$val, $nOrd]);
            echo json_encode(['ok' => true]);
            break;
        }

        // ── Sauvegarder observation ───────────────────────────
        case 'sauvegarder_obs': {
            $nOrd = (int)$body['n_ordon'];
            $obs  = trim($body['observation'] ?? '');
            $db->prepare("UPDATE ORD SET Observation=? WHERE n_ordon=?")->execute([$obs, $nOrd]);
            echo json_encode(['ok' => true]);
            break;
        }

        // ── Déplacer RDV ──────────────────────────────────────
        case 'deplacer_rdv': {
            $nOrd    = (int)$body['n_ordon'];
            $newDate = $body['nouvelle_date'] ?? '';
            if (!$newDate) { echo json_encode(['ok'=>false,'err'=>'Date manquante']); break; }
            $db->prepare("UPDATE ORD SET [DATE REDEZ VOUS]=? WHERE n_ordon=?")->execute([$newDate, $nOrd]);
            echo json_encode(['ok' => true]);
            break;
        }

        // ── Supprimer RDV (uniquement Date_Rdv, pas l'ordonnance) ──
        case 'supprimer_rdv': {
            $nOrd = (int)$body['n_ordon'];
            // On efface uniquement Date_Rdv pour ne pas perdre l'ordonnance
            $db->prepare("UPDATE ORD SET [DATE REDEZ VOUS]=NULL WHERE n_ordon=?")->execute([$nOrd]);
            echo json_encode(['ok' => true]);
            break;
        }

        // ── Modifier limite NbrMax ────────────────────────────
        case 'modifier_limite': {
            $nbrmax = (int)$body['nbrmax'];
            if ($nbrmax < 1 || $nbrmax > 100) { echo json_encode(['ok'=>false]); break; }
            // T_Config clé/valeur : mise à jour ou insertion
            $check = $db->prepare("SELECT COUNT(*) FROM T_Config WHERE Cle='NbrMax'");
            $check->execute();
            if ($check->fetchColumn() > 0) {
                $db->prepare("UPDATE T_Config SET Valeur=? WHERE Cle='NbrMax'")->execute([$nbrmax]);
            } else {
                $db->prepare("INSERT INTO T_Config (Cle, Valeur) VALUES ('NbrMax',?)")->execute([$nbrmax]);
            }
            echo json_encode(['ok' => true]);
            break;
        }

        // ── RDV de la semaine ─────────────────────────────────
        case 'rdv_semaine': {
            $date  = $body['date'] ?? date('Y-m-d');
            $debut = (new DateTime($date))->modify('monday this week')->format('Y-m-d');
            $fin   = (new DateTime($date))->modify('saturday this week')->format('Y-m-d');

            $stmt = $db->prepare("
                SELECT CONVERT(date, [DATE REDEZ VOUS]) AS jour, COUNT(*) AS nb
                FROM ORD
                WHERE CONVERT(date, [DATE REDEZ VOUS]) BETWEEN ? AND ?
                GROUP BY CONVERT(date, [DATE REDEZ VOUS])
                ORDER BY CONVERT(date, [DATE REDEZ VOUS])
            ");
            $stmt->execute([$debut, $fin]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Construire tableau semaine complet (lundi→samedi)
            $jours = [];
            $cur = new DateTime($debut);
            $finDt = new DateTime($fin);
            while ($cur <= $finDt) {
                $d = $cur->format('Y-m-d');
                $nb = 0;
                foreach ($rows as $r) {
                    if ($r['jour'] === $d) { $nb = (int)$r['nb']; break; }
                }
                $jours[] = ['date' => $d, 'label' => strftime_fr($d), 'nb' => $nb];
                $cur->modify('+1 day');
            }
            echo json_encode(['ok' => true, 'jours' => $jours]);
            break;
        }

        // ── Rechercher patient pour ajout ─────────────────────
        case 'rechercher_patient': {
            $q = trim($body['q'] ?? '');
            if (strlen($q) < 2) { echo json_encode(['ok'=>true,'patients'=>[]]); break; }

            if (is_numeric($q)) {
                $stmt = $db->prepare("SELECT TOP 10 [N°PAT] AS id, NOMPRENOM AS nom FROM ID WHERE [N°PAT]=?");
                $stmt->execute([(int)$q]);
            } else {
                $stmt = $db->prepare("SELECT TOP 10 [N°PAT] AS id, NOMPRENOM AS nom FROM ID WHERE NOMPRENOM LIKE ?");
                $stmt->execute(['%' . $q . '%']);
            }
            $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['ok' => true, 'patients' => $patients]);
            break;
        }

        // ── Ajouter RDV (nouvelle ligne dans ORD) ────────────
        case 'ajouter_rdv': {
            $idPat   = (int)$body['id'];
            $dateRdv = $body['date_rdv'] ?? date('Y-m-d');

            // Vérifier que le patient existe
            $check = $db->prepare("SELECT COUNT(*) FROM ID WHERE [N°PAT]=?");
            $check->execute([$idPat]);
            if (!$check->fetchColumn()) {
                echo json_encode(['ok'=>false,'err'=>'Patient introuvable']);
                break;
            }

            // Créer une ordonnance minimale avec Date_Rdv = aujourd'hui
            $stmt = $db->prepare("
                INSERT INTO ORD (id, date_ordon, [DATE REDEZ VOUS], DateSaisie)
                VALUES (?, CONVERT(datetime, ?, 120), CONVERT(datetime, ?, 120), GETDATE())
            ");
            $stmt->execute([$idPat, $dateRdv . ' 00:00:00', $dateRdv . ' 00:00:00']);
            echo json_encode(['ok' => true]);
            break;
        }

        // ── Détail versé global du jour ───────────────────────
        case 'detail_global': {
            $date = $body['date'] ?? date('Y-m-d');
            $stmt = $db->prepare("
                SELECT p.NOMPRENOM AS nom, ISNULL(SUM(d.Versé),0) AS montant
                FROM facture f
                LEFT JOIN detail_acte d ON d.N_fact = f.n_facture
                LEFT JOIN ID p ON p.[N°PAT] = f.id
                WHERE CONVERT(date, f.date_facture) = ?
                GROUP BY p.NOMPRENOM
                HAVING ISNULL(SUM(d.Versé),0) > 0
                ORDER BY p.NOMPRENOM
            ");
            $stmt->execute([$date]);
            $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $total    = array_sum(array_column($patients, 'montant'));
            echo json_encode(['ok'=>true, 'patients'=>$patients, 'total'=>$total]);
            break;
        }

        default:
            echo json_encode(['ok'=>false,'err'=>'Action inconnue: '.$action]);
    }

} catch (Exception $e) {
    echo json_encode(['ok' => false, 'err' => $e->getMessage()]);
}
?>