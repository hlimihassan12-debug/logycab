// home.js — Logycab
// Lit le cookie 'dernier_patient' et redirige vers son dossier
// Si pas de cookie → recherche.php
function goHome() {
    const match = document.cookie.match(/(?:^|;\s*)dernier_patient=(\d+)/);
    if (match && match[1]) {
        window.location.href = 'dossier.php?id=' + match[1];
    } else {
        window.location.href = 'recherche.php';
    }
}
