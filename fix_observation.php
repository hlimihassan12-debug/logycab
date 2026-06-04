<?php
$file = __DIR__ . '/dossier.php';
$content = file_get_contents($file);

// Remplacer toutes les variantes corrompues de 'observation'
$content = preg_replace('/b\s*\.\s*observation/u', 'b.observation', $content);
$content = preg_replace('/ISNULL\s*\(\s*b\s*\.\s*observation/u', 'ISNULL(b.observation', $content);

file_put_contents($file, $content);
echo "OK - corrections appliquées";
?>