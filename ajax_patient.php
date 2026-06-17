<?php
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';
header('Content-Type: application/json; charset=utf-8');

$db     = getDB();
$action = $_POST['action'] ?? '';

// YYYYMMDD sans tirets — format 112 SQL Server
function dateVersSQL($d) {
    if (!$d) return null;
    $d = trim($d);
    if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $d, $m)) return $m[3].$m[2].$m[1];
    if (preg_match('#^(\d{4})$#', $d, $m))               return $m[1].'0101';
    return null;
}

function calculerAgePHP($yyyymmdd) {
    if (!$yyyymmdd || strlen($yyyymmdd) < 8) return null;
    $s = substr($yyyymmdd,0,4).'-'.substr($yyyymmdd,4,2).'-'.substr($yyyymmdd,6,2);
    $ts = strtotime($s);
    if (!$ts || $ts < 0) return null;
    return (int)(new DateTime(date('Y-m-d',$ts)))->diff(new DateTime())->y;
}

// ── AJOUTER ───────────────────────────────────────────────────────────────
if ($action === 'ajouter') {
    $nom      = strtoupper(trim($_POST['nom']      ?? ''));
    $ddnBrut  = trim($_POST['ddn']                 ?? '');
    $ageSaisi = trim($_POST['age']                 ?? '');
    $cin      = strtoupper(trim($_POST['cin']      ?? ''));
    $tel      = trim($_POST['tel']                 ?? '');
    $mutuelle = trim($_POST['mutuelle']            ?? '');
    $remarque = trim($_POST['remarque']            ?? '');
    $dateRecrt = date('Y-m-d H:i:s');

    if (!$nom)     { echo json_encode(['ok'=>false,'msg'=>'Le nom est obligatoire.']);              exit; }
    if (!$mutuelle){ echo json_encode(['ok'=>false,'msg'=>'La couverture sociale est obligatoire.']); exit; }

    $ddnNorm = $ddnBrut ? dateVersSQL($ddnBrut) : null;
    $age = $ddnNorm ? calculerAgePHP($ddnNorm) : (is_numeric($ageSaisi) ? (int)$ageSaisi : null);

    try {
        // Utiliser OUTPUT INSERTED pour récupérer le N° généré fiablement
        if ($ddnNorm) {
            $stmt = $db->prepare("
                INSERT INTO ID (NOMPRENOM, DDN, AGE, CIN, [TEL D], MUTUELLE, REMARQUE, DateRecrt)
                OUTPUT INSERTED.[N°PAT]
                VALUES (?, CONVERT(datetime,?,112), ?, ?, ?, ?, ?, CONVERT(datetime,?,120))
            ");
            $stmt->execute([$nom, $ddnNorm, $age, $cin?:null, $tel?:null, $mutuelle?:null, $remarque?:null, $dateRecrt]);
        } else {
            $stmt = $db->prepare("
                INSERT INTO ID (NOMPRENOM, DDN, AGE, CIN, [TEL D], MUTUELLE, REMARQUE, DateRecrt)
                OUTPUT INSERTED.[N°PAT]
                VALUES (?, NULL, ?, ?, ?, ?, ?, CONVERT(datetime,?,120))
            ");
            $stmt->execute([$nom, $age, $cin?:null, $tel?:null, $mutuelle?:null, $remarque?:null, $dateRecrt]);
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $newId = $row ? (int)$row['N°PAT'] : 0;

        if ($newId > 0) {
            echo json_encode(['ok'=>true, 'id'=>$newId, 'msg'=>'Patient ajouté avec succès.']);
        } else {
            echo json_encode(['ok'=>false, 'msg'=>'Insertion OK mais N° non récupéré.']);
        }
    } catch (Exception $e) {
        echo json_encode(['ok'=>false, 'msg'=>'Erreur SQL : '.$e->getMessage()]);
    }
    exit;
}

// ── MODIFIER ──────────────────────────────────────────────────────────────
if ($action === 'modifier') {
    $id       = (int)($_POST['id']    ?? 0);
    $nom      = strtoupper(trim($_POST['nom']      ?? ''));
    $ddnBrut  = trim($_POST['ddn']                 ?? '');
    $ageSaisi = trim($_POST['age']                 ?? '');
    $cin      = strtoupper(trim($_POST['cin']      ?? ''));
    $tel      = trim($_POST['tel']                 ?? '');
    $mutuelle = trim($_POST['mutuelle']            ?? '');
    $remarque = trim($_POST['remarque']            ?? '');

    if (!$id)      { echo json_encode(['ok'=>false,'msg'=>'Données manquantes.']);                   exit; }
    if (!$nom)     { echo json_encode(['ok'=>false,'msg'=>'Le nom est obligatoire.']);               exit; }
    if (!$mutuelle){ echo json_encode(['ok'=>false,'msg'=>'La couverture sociale est obligatoire.']); exit; }

    $ddnNorm = $ddnBrut ? dateVersSQL($ddnBrut) : null;
    $age = $ddnNorm ? calculerAgePHP($ddnNorm) : (is_numeric($ageSaisi) ? (int)$ageSaisi : null);

    try {
        if ($ddnNorm) {
            $stmt = $db->prepare("UPDATE ID SET NOMPRENOM=?, DDN=CONVERT(datetime,?,112), AGE=?, CIN=?, [TEL D]=?, MUTUELLE=?, REMARQUE=? WHERE [N°PAT]=?");
            $stmt->execute([$nom, $ddnNorm, $age, $cin?:null, $tel?:null, $mutuelle?:null, $remarque?:null, $id]);
        } else {
            $stmt = $db->prepare("UPDATE ID SET NOMPRENOM=?, DDN=NULL, AGE=?, CIN=?, [TEL D]=?, MUTUELLE=?, REMARQUE=? WHERE [N°PAT]=?");
            $stmt->execute([$nom, $age, $cin?:null, $tel?:null, $mutuelle?:null, $remarque?:null, $id]);
        }
        echo json_encode(['ok'=>true, 'id'=>$id, 'msg'=>'Patient modifié avec succès.']);
    } catch (Exception $e) {
        echo json_encode(['ok'=>false, 'msg'=>'Erreur SQL : '.$e->getMessage()]);
    }
    exit;
}

// ── SUPPRIMER ─────────────────────────────────────────────────────────────
if ($action === 'supprimer') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['ok'=>false,'msg'=>'Patient introuvable.']); exit; }
    try {
        $db->prepare("DELETE FROM ID WHERE [N°PAT]=?")->execute([$id]);
        echo json_encode(['ok'=>true, 'msg'=>'Patient supprimé.']);
    } catch (Exception $e) {
        echo json_encode(['ok'=>false, 'msg'=>'Erreur SQL : '.$e->getMessage()]);
    }
    exit;
}

// ── CHARGER ───────────────────────────────────────────────────────────────
if ($action === 'charger') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['ok'=>false,'msg'=>'ID manquant.']); exit; }

    $stmt = $db->prepare("SELECT * FROM ID WHERE [N°PAT]=?");
    $stmt->execute([$id]);
    $p = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$p) { echo json_encode(['ok'=>false,'msg'=>'Patient introuvable.']); exit; }

    $ddn_fr = ''; $annee_seule = false;
    if (!empty($p['DDN'])) {
        $ts = strtotime($p['DDN']);
        if ($ts && $ts > 86400) {
            $ddn_fr = date('d/m/Y', $ts);
            if (date('d/m', $ts) === '01/01') $annee_seule = true;
        }
    }
    $recrt_fr = '';
    if (!empty($p['DateRecrt'])) {
        $ts = strtotime($p['DateRecrt']);
        if ($ts && $ts > 86400) $recrt_fr = date('d/m/Y H:i', $ts);
    }

    echo json_encode([
        'ok'=>true, 'id'=>(int)$p['N°PAT'],
        'nom'         => $p['NOMPRENOM'] ?? '',
        'ddn'         => $ddn_fr,
        'annee_seule' => $annee_seule,
        'age'         => $p['AGE']       ?? '',
        'cin'         => $p['CIN']       ?? '',
        'tel'         => $p['TEL D']     ?? '',
        'mutuelle'    => $p['MUTUELLE']  ?? '',
        'remarque'    => $p['REMARQUE']  ?? '',
        'daterecrt'   => $recrt_fr,
    ]);
    exit;
}

echo json_encode(['ok'=>false,'msg'=>'Action inconnue.']);
