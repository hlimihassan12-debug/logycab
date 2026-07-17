<?php
require_once __DIR__ . '/../../backend/db.php';

function getCabinetConfig(): array {
    static $cfg = null;
    if ($cfg === null) {
        $db = getDB();
        $stmt = $db->query("SELECT Cle, Valeur FROM T_Config WHERE Cle LIKE 'Cabinet_%' OR Cle LIKE 'Horaire_%'");
        $cfg = [];
        while ($row = $stmt->fetch()) {
            $cfg[$row['Cle']] = $row['Valeur'];
        }
    }
    return $cfg;
}

function joursSemaine(): array {
    return ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
}

function horaireDuJour(array $cfg, string $jourFr): string {
    return $cfg['Horaire_' . $jourFr] ?? 'Fermé';
}
