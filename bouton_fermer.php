<!-- Bouton Fermer (fixe, bas droite) -->
<button type="button" onclick="fermerPageLogycab()" class="btn-fermer-logycab" title="Fermer cette page">✕ Fermer</button>
<style>
.btn-fermer-logycab{
    position:fixed; bottom:16px; right:16px; z-index:500;
    background:#555; color:#fff; border:none; border-radius:20px;
    padding:8px 16px; font-size:13px; cursor:pointer;
    box-shadow:0 2px 6px rgba(0,0,0,0.3);
}
.btn-fermer-logycab:hover{ background:#333; }
</style>
<script>
function fermerPageLogycab(){
    setTimeout(function(){
        alert("Vous pouvez fermer cet onglet avec Ctrl+W (ou Cmd+W sur Mac).");
    }, 200);
    window.close();
}
</script>
