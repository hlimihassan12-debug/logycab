<?php
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';
$db = getDB();

$id   = (int)($_GET['id']  ?? 0);
$nOrd = (int)($_GET['ord'] ?? 0);

if ($id == 0 || $nOrd == 0) { die("Manque id et ord dans l'URL"); }

$stmt = $db->prepare("SELECT * FROM ORD WHERE n_ordon = ? AND id = ?");
$stmt->execute([$nOrd, $id]);
$ord = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ord) { die("Ordonnance introuvable"); }

echo "<h3>Champs RDV pour ordonnance $nOrd / patient $id</h3>";
echo "<table border='1' cellpadding='6' style='font-family:Arial;font-size:14px'>";
$champs = ['DATE REDEZ VOUS', 'Date_Rdv', 'HeureRDV', 'acte1', 'Observation'];
foreach ($champs as $c) {
    $val = $ord[$c] ?? '⚠️ champ inexistant';
    echo "<tr><td><b>$c</b></td><td>" . htmlspecialchars((string)$val) . "</td></tr>";
}
echo "</table>";
echo "<hr><h4>Tous les champs de l'ordonnance :</h4><pre>";
foreach ($ord as $k => $v) {
    echo htmlspecialchars($k) . " = " . htmlspecialchars((string)$v) . "\n";
}
echo "</pre>";
?>
