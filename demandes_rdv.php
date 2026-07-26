<?php
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';
$db = getDB();

// Compteur RDV du jour / NbrMax (pour le bloc logo, cohérent avec les autres pages)
$nbRdvAujourd = $db->query("SELECT COUNT(*) FROM ORD WHERE CONVERT(date,[DATE REDEZ VOUS])=CONVERT(date,GETDATE()) OR CONVERT(date,Date_Rdv)=CONVERT(date,GETDATE())")->fetchColumn();
$nbrMax = 20;
try {
    $stmtMax = $db->prepare("SELECT Valeur FROM T_Config WHERE Cle='NbrMax'");
    $stmtMax->execute();
    $rowMax = $stmtMax->fetch(PDO::FETCH_ASSOC);
    if ($rowMax) $nbrMax = (int)$rowMax['Valeur'];
} catch (Exception $e) {}

// ── Cherche un patient existant par téléphone (9 derniers chiffres), sinon le crée ──
function trouverOuCreerPatient(PDO $db, string $nom, string $telephone): int {
    $chiffres = preg_replace('/\D/', '', $telephone);
    $dernier9 = substr($chiffres, -9);

    if ($dernier9 !== '') {
        $stmt = $db->prepare("SELECT TOP 1 [N°PAT] FROM ID WHERE [TEL D] LIKE ? ORDER BY [N°PAT] DESC");
        $stmt->execute(['%' . $dernier9]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) return (int)$row['N°PAT'];
    }

    $stmt = $db->prepare("
        INSERT INTO ID (NOMPRENOM, [TEL D], DateRecrt)
        OUTPUT INSERTED.[N°PAT]
        VALUES (?, ?, CONVERT(datetime,?,120))
    ");
    $stmt->execute([strtoupper($nom), $telephone ?: null, date('Y-m-d H:i:s')]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? (int)$row['N°PAT'] : 0;
}

// Traitement Confirmer / Refuser
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($id > 0 && in_array($action, ['confirmer', 'refuser'], true)) {

        if ($action === 'refuser') {
            $stmt = $db->prepare("UPDATE T_DemandesRDV SET statut = 'refuse' WHERE id = ?");
            $stmt->execute([$id]);
            header('Location: demandes_rdv.php?msg=refuse');
            exit;
        }

        // ── Confirmer : automatisation ──
        try {
            $stmtD = $db->prepare("SELECT * FROM T_DemandesRDV WHERE id = ?");
            $stmtD->execute([$id]);
            $demande = $stmtD->fetch(PDO::FETCH_ASSOC);

            if (!$demande) {
                header('Location: demandes_rdv.php?msg=erreur');
                exit;
            }

            $patientId = trouverOuCreerPatient($db, $demande['nom'], $demande['telephone']);

            $msgRetour = 'confirme_sansrdv';
            if ($patientId > 0 && !empty($demande['date_souhaitee'])) {
                $dateRdv  = $demande['date_souhaitee'] . ' 00:00:00';
                $heureRdv = $demande['heure_souhaitee'] ?: '';
                $acte     = $demande['motif'] ?: '';
                $obs      = 'Demande site web' . (!empty($demande['message']) ? ' — ' . $demande['message'] : '');

                $stmtRdv = $db->prepare("
                    INSERT INTO ORD (id, Date_Rdv, HeureRDV, acte1, Observation, DateSaisie, vu, SansReponse, Urgence)
                    VALUES (?, CONVERT(datetime,?,120), ?, ?, ?, CONVERT(datetime,?,120), 0, 0, 0)
                ");
                $stmtRdv->execute([$patientId, $dateRdv, $heureRdv, $acte, $obs, date('Y-m-d H:i:s')]);
                $msgRetour = 'confirme_rdv';
            }

            $stmt = $db->prepare("UPDATE T_DemandesRDV SET statut = 'confirme' WHERE id = ?");
            $stmt->execute([$id]);

            header('Location: demandes_rdv.php?msg=' . $msgRetour);
            exit;
        } catch (Exception $e) {
            header('Location: demandes_rdv.php?msg=erreur');
            exit;
        }
    }

    header('Location: demandes_rdv.php');
    exit;
}

$enAttente = $db->query("SELECT * FROM T_DemandesRDV WHERE statut = 'en_attente' ORDER BY date_creation DESC")->fetchAll(PDO::FETCH_ASSOC);
$traitees  = $db->query("SELECT TOP 30 * FROM T_DemandesRDV WHERE statut != 'en_attente' ORDER BY date_creation DESC")->fetchAll(PDO::FETCH_ASSOC);

function formatDateFr($d) {
    if (!$d) return '—';
    $ts = strtotime($d);
    return $ts ? date('d/m/Y', $ts) : $d;
}

// Thème
$themes_valides = ['theme-0','theme-a','theme-b','theme-c'];
$theme = $_COOKIE['logycab_theme'] ?? 'theme-0';
if (!in_array($theme, $themes_valides)) $theme = 'theme-0';

// Message de retour après confirmer/refuser
$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Demandes de rendez-vous — Logycab</title>
<link rel="stylesheet" href="themes.css">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: var(--th-font-body); background: var(--th-bg-page); font-size: 13px; }

.header { background: var(--th-bg-header); color: white; padding: 6px 14px;
          display: flex; align-items: center; gap: 8px; flex-wrap: nowrap; }
.btn-h { color: white; text-decoration: none; border: none; cursor: pointer;
         padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: bold;
         display: inline-flex; align-items: center; height: 26px; white-space: nowrap; }
.btn-h.navy   { background: var(--th-btn-navy); }
.btn-h.blue   { background: var(--th-btn-blue); }
.btn-h.grey   { background: #888; pointer-events: none; opacity: 0.7; cursor: default; }
.btn-h:not(.grey):hover { opacity: 0.85; }
@keyframes heartbeat {
    0%,100% { transform: scale(1); }
    14%     { transform: scale(1.2); }
    28%     { transform: scale(1); }
    42%     { transform: scale(1.15); }
    56%     { transform: scale(1); }
}
.heart { display: inline-block; animation: heartbeat 1.6s infinite; color: #e74c3c; font-size: 20px; }
.logo-block { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }
.logo-block .nom { font-size: 16px; font-weight: 900; letter-spacing: 1px; color: #fff; line-height: 1.1; }
.logo-block .sub { font-size: 9px; opacity: 0.85; color: #fff; white-space: nowrap; }

.container { max-width: 900px; margin: 24px auto; padding: 0 16px; }
.card { background: var(--th-bg-card); border-radius: 8px;
        box-shadow: 0 2px 8px var(--th-border-card); margin-bottom: 20px; overflow: hidden; }
.card-header { background: var(--th-bg-header-s); color: white; padding: 10px 16px;
               font-size: 13px; font-weight: bold; display:flex; justify-content:space-between; align-items:center; }
.badge-count { background: #e74c3c; color: white; border-radius: 10px; padding: 1px 9px; font-size: 11px; }

.item-demande { padding: 12px 16px; border-bottom: 1px solid var(--th-sep-color); display: flex; gap: 16px; align-items: flex-start; }
.item-demande:last-child { border-bottom: none; }
.item-demande:hover { background: var(--th-bg-link-hover); }
.item-infos { flex: 1; }
.item-infos .nom { font-weight: bold; color: var(--th-color-primary); font-size: 13px; }
.item-infos .detail { font-size: 11.5px; color: var(--th-color-text-muted); margin-top: 3px; line-height: 1.5; }
.item-actions { display: flex; gap: 6px; flex-shrink: 0; }
.btn-conf { background: #27ae60; color: white; border: none; border-radius: 4px; padding: 6px 12px; cursor: pointer; font-size: 11px; font-weight: bold; }
.btn-ref  { background: #e74c3c; color: white; border: none; border-radius: 4px; padding: 6px 12px; cursor: pointer; font-size: 11px; font-weight: bold; }
.btn-conf:hover, .btn-ref:hover { opacity: 0.85; }

.statut-badge { font-size: 10px; font-weight: bold; padding: 2px 8px; border-radius: 10px; }
.statut-badge.confirme { background: #e8f5e9; color: #27ae60; }
.statut-badge.refuse   { background: #fde8e8; color: #c0392b; }

.empty { padding: 24px; text-align: center; color: var(--th-color-text-muted); font-style: italic; }

.alerte { margin: 0 0 16px; padding: 12px 16px; border-radius: 6px; font-size: 12.5px; font-weight: bold; }
.alerte.ok    { background: #e8f5e9; color: #1a7a40; border-left: 4px solid #27ae60; }
.alerte.warn  { background: #fff6e0; color: #8a6300; border-left: 4px solid #f39c12; }
.alerte.err   { background: #fde8e8; color: #c0392b; border-left: 4px solid #c0392b; }
</style>
</head>
<body class="<?= htmlspecialchars($theme) ?>">

<div class="header">
    <div class="logo-block">
        <span class="heart">❤</span>
        <div>
            <div class="nom">LOGYCAB</div>
            <div class="sub"><?= (int)$nbRdvAujourd ?> RDV aujourd'hui / <?= $nbrMax ?> prévus</div>
        </div>
    </div>
    <div style="flex:1;"></div>
    <a href="index.php"    class="btn-h" style="background:#c0392b;">🏠 Accueil</a>
    <a href="agenda.php"   class="btn-h navy">📅 Agenda</a>
    <span                   class="btn-h grey">📥 Demandes RDV</span>
</div>

<div class="container">

    <?php if ($msg === 'confirme_rdv'): ?>
        <div class="alerte ok">✅ Demande confirmée — le rendez-vous a été créé automatiquement dans l'agenda.</div>
    <?php elseif ($msg === 'confirme_sansrdv'): ?>
        <div class="alerte warn">⚠ Demande confirmée, mais aucune date n'avait été précisée par le patient — pensez à créer le RDV manuellement dans l'agenda.</div>
    <?php elseif ($msg === 'refuse'): ?>
        <div class="alerte err">✘ Demande refusée.</div>
    <?php elseif ($msg === 'erreur'): ?>
        <div class="alerte err">⚠ Une erreur est survenue pendant le traitement de la demande.</div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <span>📥 Demandes en attente</span>
            <span class="badge-count"><?= count($enAttente) ?></span>
        </div>
        <?php if (empty($enAttente)): ?>
            <div class="empty">Aucune demande en attente</div>
        <?php else: foreach ($enAttente as $d): ?>
            <div class="item-demande">
                <div class="item-infos">
                    <div class="nom"><?= htmlspecialchars($d['nom']) ?></div>
                    <div class="detail">
                        📞 <?= htmlspecialchars($d['telephone']) ?>
                        <?php if (!empty($d['email'])): ?> · ✉ <?= htmlspecialchars($d['email']) ?><?php endif; ?><br>
                        <?php if (!empty($d['motif'])): ?>Motif : <?= htmlspecialchars($d['motif']) ?><br><?php endif; ?>
                        <?php if (!empty($d['date_souhaitee']) || !empty($d['heure_souhaitee'])): ?>
                            Souhait : <?= formatDateFr($d['date_souhaitee']) ?> <?= htmlspecialchars($d['heure_souhaitee'] ?? '') ?><br>
                        <?php endif; ?>
                        <?php if (!empty($d['message'])): ?>« <?= htmlspecialchars($d['message']) ?> »<br><?php endif; ?>
                        Reçu le <?= formatDateFr($d['date_creation']) ?>
                    </div>
                </div>
                <div class="item-actions">
                    <form method="POST">
                        <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                        <input type="hidden" name="action" value="confirmer">
                        <button type="submit" class="btn-conf">✔ Confirmer</button>
                    </form>
                    <form method="POST">
                        <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                        <input type="hidden" name="action" value="refuser">
                        <button type="submit" class="btn-ref">✘ Refuser</button>
                    </form>
                </div>
            </div>
        <?php endforeach; endif; ?>
    </div>

    <div class="card">
        <div class="card-header"><span>🗂 Historique récent</span></div>
        <?php if (empty($traitees)): ?>
            <div class="empty">Aucune demande traitée</div>
        <?php else: foreach ($traitees as $d): ?>
            <div class="item-demande">
                <div class="item-infos">
                    <div class="nom"><?= htmlspecialchars($d['nom']) ?>
                        <span class="statut-badge <?= $d['statut'] ?>"><?= $d['statut'] === 'confirme' ? 'Confirmé' : 'Refusé' ?></span>
                    </div>
                    <div class="detail">
                        📞 <?= htmlspecialchars($d['telephone']) ?> · Reçu le <?= formatDateFr($d['date_creation']) ?>
                    </div>
                </div>
            </div>
        <?php endforeach; endif; ?>
    </div>
</div>

</body>
</html>
