<?php
$fichier = 'C:/xampp/htdocs/logycab/exports-access/patients.csv';

$handle = fopen($fichier, 'r');

// Lire les 3 premières lignes
for ($i = 0; $i < 3; $i++) {
    $ligne = fgets($handle);
    echo "<pre>" . htmlspecialchars($ligne) . "</pre>";
    echo "<hr>";
}

fclose($handle);