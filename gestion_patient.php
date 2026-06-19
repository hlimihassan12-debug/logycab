<?php
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/db.php';
$db = getDB();
$modeInit = $_GET['mode'] ?? 'ajouter';
// Thème
$themes_valides = ['theme-0','theme-a','theme-b','theme-c'];
$theme = $_COOKIE['logycab_theme'] ?? 'theme-0';
if (!in_array($theme, $themes_valides)) $theme = 'theme-0';
if (!in_array($modeInit, ['ajouter','modifier','supprimer'])) $modeInit = 'ajouter';
$idInit = (int)($_GET['id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Gestion patients</title>
<link rel="stylesheet" href="themes.css">
<style>
* { box-sizing:border-box; margin:0; padding:0; }
body { font-family:var(--th-font-body); font-size:13px; background:var(--th-bg-page); color:var(--th-color-text); }

.header {
    background:var(--th-bg-header);
    color:white; padding:8px 16px;
    display:flex; align-items:center; gap:10px;
}
.header h1 { font-size:14px; margin-left:8px; }
.bh { display:inline-flex; align-items:center; padding:4px 10px; border-radius:4px;
      font-size:11px; font-weight:bold; color:white; text-decoration:none;
      border:none; cursor:pointer; }
.bh-green  { background:#27ae60; }
.bh-red    { background:#c0392b; }
.bh-navy   { background:var(--th-btn-navy); }
.header-clock { margin-left:auto; background:rgba(255,255,255,0.12);
    border-radius:6px; padding:4px 10px; text-align:center; }
.header-clock .ct { font-size:14px; font-weight:bold; }
.header-clock .cd { font-size:9px; opacity:0.75; }

.page { display:flex; justify-content:center; padding:24px 16px; }

.carte { background:var(--th-bg-card); border-radius:6px;
    box-shadow:0 2px 12px rgba(0,0,0,0.1); width:500px; max-width:98vw; }

.carte-titre {
    background:var(--th-bg-header);
    color:white; padding:10px 16px; border-radius:6px 6px 0 0;
    font-size:14px; font-weight:bold;
}

/* Onglets */
.ongs { display:flex; padding:8px 12px 0; border-bottom:2px solid #e0e8f0; }
.ong {
    padding:5px 14px; border:none; cursor:pointer;
    font-size:12px; font-weight:bold;
    background:#e8f0f8; color:#555;
    border-radius:4px 4px 0 0;
    border-bottom:2px solid transparent;
    margin-bottom:-2px; margin-right:4px;
}
.ong.on      { background:var(--th-color-secondary); color:white; border-bottom-color:var(--th-color-secondary); }
.ong.del     { background:#fde8e8; color:#c0392b; }
.ong.del.on  { background:#e74c3c; color:white; border-bottom-color:#e74c3c; }

/* Sections */
.sec { display:none; padding:16px; }
.sec.on { display:block; }

/* Tableau formulaire */
table.f { width:100%; border-collapse:collapse; }
table.f tr { border-bottom:1px solid #f0f0f0; }
table.f tr:last-child { border-bottom:none; }
table.f td { padding:6px 8px; vertical-align:middle; }
table.f td.L { width:155px; font-size:11px; color:var(--th-color-text-muted); font-weight:bold; white-space:nowrap; }
table.f td.V { }

/* Champs */
.inp {
    width:100%; padding:5px 7px;
    border:1px solid #ccd6e0; border-radius:3px;
    font-size:12px; font-family:Arial,sans-serif;
}
.inp:focus { outline:none; border-color:#2e6da4; box-shadow:0 0 0 2px rgba(46,109,164,0.12); }
.inp.err { border-color:#e74c3c; background:#fff8f8; }
.info-box {
    background:var(--th-bg-link-hover); border:1px solid var(--th-border-statsbar);
    border-radius:3px; padding:5px 7px;
    font-size:12px; color:var(--th-color-primary); font-weight:bold;
}

/* Age + DDN */
.age-row { display:flex; gap:8px; align-items:flex-start; }
.age-bloc { width:60px; text-align:center; }
.age-val { font-size:18px; font-weight:bold; color:var(--th-color-primary);
    background:#f0f4f8; border:1px solid #dde3ea;
    border-radius:3px; padding:3px 6px; display:block; }
.age-lbl { font-size:9px; color:#888; }
.age-inp { width:60px; padding:5px 7px; border:1px solid #ccd6e0;
    border-radius:3px; font-size:14px; font-weight:bold;
    color:#1a4a7a; text-align:center; font-family:Arial,sans-serif; }
.age-inp:focus { outline:none; border-color:var(--th-color-secondary); }

/* Radios */
.radios { display:flex; flex-direction:column; gap:3px; padding:2px 0; }
.radios label { display:flex; align-items:center; gap:6px;
    font-size:12px; cursor:pointer; padding:3px 6px; border-radius:3px; }
.radios label:hover { background:#f0f4f8; }
.radios input[type=radio] { accent-color:#2e6da4; }

/* Messages erreur */
.merr { color:#e74c3c; font-size:10px; margin-top:2px; display:none; }
.merr.on { display:block; }

/* Zone recherche */
.zrech { display:flex; gap:6px; margin-bottom:12px; }
.zrech input { flex:1; padding:5px 8px; border:1px solid #ccd6e0; border-radius:3px; font-size:12px; }
.zrech button { background:var(--th-btn-navy); color:white; border:none; border-radius:3px;
    padding:5px 12px; font-size:12px; font-weight:bold; cursor:pointer; }

/* Alerte */
.alerte { background:#fff3cd; border:1px solid #ffc107; border-radius:4px;
    padding:8px 12px; color:#856404; font-size:11px; margin-bottom:10px; }

/* Boutons action */
.ba { padding:6px 18px; border:none; border-radius:4px;
    font-size:12px; font-weight:bold; cursor:pointer; }
.ba:hover { opacity:0.85; }
.ba-save { background:#27ae60; color:white; }
.ba-del  { background:#e74c3c; color:white; }
.ba-ann  { background:#95a5a6; color:white; }
.ba-nav  { color:white; padding:6px 18px; border-radius:4px;
    font-size:12px; font-weight:bold; text-decoration:none;
    font-family:Arial,sans-serif; }

/* Résultat */
.mres { margin-top:10px; padding:7px 12px; border-radius:4px;
    font-size:12px; display:none; }
.mres.ok  { background:#d4edda; color:#155724; border:1px solid #c3e6cb; display:block; }
.mres.nok { background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; display:block; }
</style>
</head>
<body class="<?= htmlspecialchars($theme) ?>">

<script src="home.js"></script>
<div class="header">
    <button onclick="goHome()" class="bh bh-green">🏠 Dossier</button>
    <a href="index.php"     class="bh bh-red">🏠 Accueil</a>
    <a href="agenda.php"    class="bh bh-navy">📅 Agenda</a>
    <a href="recherche.php" class="bh bh-navy">🔍 Recherche</a>
    <h1>👤 Gestion des patients</h1>
    <div class="header-clock" style="margin-left:auto;">
        <div class="ct" id="clockTime">--:--:--</div>
        <div class="cd" id="clockDate">---</div>
    </div>
</div>

<div class="page">
<div class="carte">

<div class="carte-titre">👤 Gestion des patients</div>

<div class="ongs">
    <button class="ong" id="ong-ajouter"   onclick="ong('ajouter')">➕ Ajouter</button>
    <button class="ong" id="ong-modifier"  onclick="ong('modifier')">✏️ Modifier</button>
    <button class="ong del" id="ong-supprimer" onclick="ong('supprimer')">🗑 Supprimer</button>
</div>

<!-- ══ AJOUTER ══ -->
<div class="sec" id="sec-ajouter">
<table class="f">
<tr>
    <td class="L">N° Patient</td>
    <td class="V"><div class="info-box" id="aj-npat">Attribué automatiquement</div></td>
</tr>
<tr>
    <td class="L">Nom et prénom *</td>
    <td class="V">
        <input class="inp" type="text" id="aj-nom" placeholder="NOM PRÉNOM"
               oninput="this.value=this.value.toUpperCase()"
               onkeydown="if(event.key==='Enter'){event.preventDefault();document.getElementById('aj-ddn').focus();}">
        <div class="merr" id="e-aj-nom">Obligatoire.</div>
    </td>
</tr>
<tr>
    <td class="L">Date de naissance</td>
    <td class="V">
        <input class="inp" type="text" id="aj-ddn" placeholder="JJ/MM/AAAA ou AAAA" autocomplete="off" maxlength="10"
               oninput="fmtDDN(this);syncAge('aj')"
               onkeydown="if(event.key==='Enter'){event.preventDefault();document.getElementById('aj-age-inp').focus();}">
        <div class="merr" id="e-aj-ddn">Format : JJ/MM/AAAA ou AAAA.</div>
    </td>
</tr>
<tr>
    <td class="L">Âge</td>
    <td class="V">
        <div class="age-row">
            <div class="age-bloc">
                <input class="age-inp" type="text" id="aj-age-inp" maxlength="3" placeholder="—"
                       oninput="this.value=this.value.replace(/[^0-9]/g,'')"
                       onkeydown="if(event.key==='Enter'){event.preventDefault();document.getElementById('aj-cin').focus();}">
                <div class="age-lbl">ans</div>
            </div>
            <div style="font-size:10px;color:#888;padding-top:6px;">
                Calculé depuis DDN<br>ou saisie directe
            </div>
        </div>
    </td>
</tr>
<tr>
    <td class="L">CIN</td>
    <td class="V">
        <input class="inp" type="text" id="aj-cin" placeholder="BE1234567" autocomplete="off" maxlength="9"
               oninput="this.value=this.value.toUpperCase()"
               onkeydown="if(event.key==='Enter'){event.preventDefault();document.getElementById('aj-tel').focus();}">
        <div class="merr" id="e-aj-cin">Format invalide. Ex: W10574, BE123456 (1-2 lettres majuscules + 1 a 6 chiffres).</div>
    </td>
</tr>
<tr>
    <td class="L">Téléphone</td>
    <td class="V">
        <input class="inp" type="text" id="aj-tel" placeholder="0612345678" autocomplete="off" maxlength="10"
               oninput="this.value=this.value.replace(/[^0-9]/g,'')"
               onkeydown="if(event.key==='Enter'){event.preventDefault();document.getElementById('aj-rem').focus();}">
        <div class="merr" id="e-aj-tel">06 ou 07 + 8 chiffres.</div>
    </td>
</tr>
<tr>
    <td class="L" style="vertical-align:top;padding-top:10px;">Couverture sociale *</td>
    <td class="V">
        <div class="radios" id="aj-radios">
            <label><input type="radio" name="aj-mut" value="CNSS">  CNSS</label>
            <label><input type="radio" name="aj-mut" value="CNOPS"> CNOPS</label>
            <label><input type="radio" name="aj-mut" value="AMO">   AMO</label>
            <label><input type="radio" name="aj-mut" value="AUTRES">AUTRES</label>
        </div>
        <div class="merr" id="e-aj-mut">Veuillez selectionner une couverture sociale.</div>
    </td>
</tr>
<tr>
    <td class="L" style="vertical-align:top;padding-top:8px;">Remarque</td>
    <td class="V">
        <textarea class="inp" id="aj-rem" rows="2" style="resize:vertical;"
                  onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();enreg('ajouter');}"></textarea>
    </td>
</tr>
<tr>
    <td class="L">Date recrutement</td>
    <td class="V"><div class="info-box" style="color:#888;font-weight:normal;font-size:11px;">Automatique</div></td>
</tr>
</table>

<div style="margin-top:14px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
    <button class="ba ba-save" id="btn-aj-enreg" onclick="enreg('ajouter')">💾 Enregistrer</button>
    <a id="aj-dos"   href="#" style="background:#1a4a7a;display:none;" class="ba-nav">🏠 Dossier</a>
    <a id="aj-bilan" href="#" style="background:#27ae60;display:none;" class="ba-nav">📋 Aperçu bilan</a>
</div>
<div id="msg-ajouter" class="mres"></div>
</div>

<!-- ══ MODIFIER ══ -->
<div class="sec" id="sec-modifier">
<div class="zrech">
    <input type="text" id="mod-rech" placeholder="N° patient..."
           onkeydown="if(event.key==='Enter') charger('modifier')">
    <button onclick="charger('modifier')">🔍 Charger</button>
</div>
<div id="form-mod" style="display:none;">
<table class="f">
<tr>
    <td class="L">N° Patient</td>
    <td class="V"><div class="info-box" id="mod-npat">—</div><input type="hidden" id="mod-id"></td>
</tr>
<tr>
    <td class="L">Nom et prénom *</td>
    <td class="V">
        <input type="text" name="fakeusernameremembered2" style="display:none;" tabindex="-1">
        <input class="inp" type="text" id="mod-nom" autocomplete="off" name="mod_nom_65573"
               oninput="this.value=this.value.toUpperCase()"
               onkeydown="if(event.key==='Enter'){event.preventDefault();document.getElementById('mod-ddn').focus();}">
        <div class="merr" id="e-mod-nom">Obligatoire.</div>
    </td>
</tr>
<tr>
    <td class="L">Date de naissance</td>
    <td class="V">
        <input class="inp" type="text" id="mod-ddn" placeholder="JJ/MM/AAAA ou AAAA" autocomplete="off" maxlength="10"
               oninput="fmtDDN(this);syncAge('mod')"
               onkeydown="if(event.key==='Enter'){event.preventDefault();document.getElementById('mod-age-inp').focus();}">
        <div class="merr" id="e-mod-ddn">Format : JJ/MM/AAAA ou AAAA.</div>
    </td>
</tr>
<tr>
    <td class="L">Âge</td>
    <td class="V">
        <div class="age-row">
            <div class="age-bloc">
                <input class="age-inp" type="text" id="mod-age-inp" maxlength="3" placeholder="—"
                       oninput="this.value=this.value.replace(/[^0-9]/g,'')"
                       onkeydown="if(event.key==='Enter'){event.preventDefault();document.getElementById('mod-cin').focus();}">
                <div class="age-lbl">ans</div>
            </div>
            <div style="font-size:10px;color:#888;padding-top:6px;">Calculé depuis DDN<br>ou saisie directe</div>
        </div>
    </td>
</tr>
<tr>
    <td class="L">CIN</td>
    <td class="V">
        <input class="inp" type="text" id="mod-cin" maxlength="9" autocomplete="off"
               oninput="this.value=this.value.toUpperCase()"
               onkeydown="if(event.key==='Enter'){event.preventDefault();document.getElementById('mod-tel').focus();}">
        <div class="merr" id="e-mod-cin">Format invalide. Ex: W10574, BE123456 (1-2 lettres majuscules + 1 a 6 chiffres).</div>
    </td>
</tr>
<tr>
    <td class="L">Téléphone</td>
    <td class="V">
        <input class="inp" type="text" id="mod-tel" maxlength="10" autocomplete="off"
               oninput="this.value=this.value.replace(/[^0-9]/g,'')"
               onkeydown="if(event.key==='Enter'){event.preventDefault();document.getElementById('mod-rem').focus();}">
        <div class="merr" id="e-mod-tel">06 ou 07 + 8 chiffres.</div>
    </td>
</tr>
<tr>
    <td class="L" style="vertical-align:top;padding-top:10px;">Couverture sociale *</td>
    <td class="V">
        <div class="radios">
            <label><input type="radio" name="mod-mut" value="CNSS">  CNSS</label>
            <label><input type="radio" name="mod-mut" value="CNOPS"> CNOPS</label>
            <label><input type="radio" name="mod-mut" value="AMO">   AMO</label>
            <label><input type="radio" name="mod-mut" value="AUTRES">AUTRES</label>
        </div>
        <div class="merr" id="e-mod-mut">Veuillez selectionner une couverture sociale.</div>
    </td>
</tr>
<tr>
    <td class="L" style="vertical-align:top;padding-top:8px;">Remarque</td>
    <td class="V"><textarea class="inp" id="mod-rem" rows="2" style="resize:vertical;"></textarea></td>
</tr>
<tr>
    <td class="L">Date recrutement</td>
    <td class="V"><div class="info-box" id="mod-recrt" style="color:#888;font-weight:normal;font-size:11px;">—</div></td>
</tr>
</table>
<div style="margin-top:14px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
    <button class="ba ba-save" onclick="enreg('modifier')">💾 Enregistrer</button>
    <a id="mod-dos"   href="#" style="background:#1a4a7a;display:none;" class="ba-nav">🏠 Dossier</a>
    <a id="mod-bilan" href="#" style="background:#27ae60;display:none;" class="ba-nav">📋 Aperçu bilan</a>
</div>
<div id="msg-modifier" class="mres"></div>
</div>
</div>

<!-- ══ SUPPRIMER ══ -->
<div class="sec" id="sec-supprimer">
<div class="zrech">
    <input type="text" id="sup-rech" placeholder="N° patient..."
           onkeydown="if(event.key==='Enter') charger('supprimer')">
    <button onclick="charger('supprimer')">🔍 Charger</button>
</div>
<div id="form-sup" style="display:none;">
    <input type="hidden" id="sup-id">
    <div class="alerte">⚠️ Suppression définitive.</div>
    <table class="f">
    <tr>
        <td class="L">Patient</td>
        <td class="V"><div class="info-box" id="sup-nom" style="color:#e74c3c;">—</div></td>
    </tr>
    <tr>
        <td class="L">Date recrutement</td>
        <td class="V"><div class="info-box" id="sup-recrt">—</div></td>
    </tr>
    </table>
    <div style="margin-top:14px;display:flex;gap:8px;">
        <button class="ba ba-del" onclick="supprimer()">🗑 Confirmer</button>
        <button class="ba ba-ann" onclick="annulerSup()">✕ Annuler</button>
    </div>
    <div id="msg-supprimer" class="mres"></div>
</div>
</div>

</div><!-- fin carte -->
</div><!-- fin page -->

<script>
// -- Horloge -----------------------------------------------------------------
(function(){
    var J=['Dim','Lun','Mar','Mer','Jeu','Ven','Sam'];
    var M=['Jan','Fev','Mar','Avr','Mai','Jun','Jul','Aou','Sep','Oct','Nov','Dec'];
    function pad(x){return String(x).padStart(2,'0');}
    function tick(){
        var n=new Date();
        document.getElementById('clockTime').textContent=pad(n.getHours())+':'+pad(n.getMinutes())+':'+pad(n.getSeconds());
        document.getElementById('clockDate').textContent=J[n.getDay()]+' '+n.getDate()+' '+M[n.getMonth()];
    }
    tick(); setInterval(tick,1000);
})();

// -- Onglets ------------------------------------------------------------------
function ong(m) {
    ['ajouter','modifier','supprimer'].forEach(function(x){
        document.getElementById('ong-'+x).classList.remove('on');
        document.getElementById('sec-'+x).classList.remove('on');
    });
    document.getElementById('ong-'+m).classList.add('on');
    document.getElementById('sec-'+m).classList.add('on');
}

// -- DDN : slash automatique --------------------------------------------------
function fmtDDN(inp) {
    var raw = inp.value.replace(/[^0-9]/g,'');
    if (raw.length <= 4 && inp.value.indexOf('/')===-1) { inp.value=raw; return; }
    var v = raw;
    if (v.length>2) v=v.substring(0,2)+'/'+v.substring(2);
    if (v.length>5) v=v.substring(0,5)+'/'+v.substring(5);
    if (v.length>10) v=v.substring(0,10);
    inp.value=v;
}

// -- Calcul -ge ---------------------------------------------------------------
function ageDepuisDDN(ddn) {
    var mf=/^(\d{2})\/(\d{2})\/(\d{4})$/.exec(ddn);
    var ma=/^(\d{4})$/.exec(ddn);
    var j,mo,a;
    if (mf) { j=parseInt(mf[1]); mo=parseInt(mf[2])-1; a=parseInt(mf[3]); }
    else if (ma) { j=1; mo=0; a=parseInt(ma[1]); }
    else return null;
    var nais=new Date(a,mo,j);
    if (isNaN(nais.getTime())) return null;
    var auj=new Date();
    var age=auj.getFullYear()-nais.getFullYear();
    if (auj.getMonth()<mo||(auj.getMonth()===mo&&auj.getDate()<j)) age--;
    return (age>=0&&age<130)?age:null;
}

function syncAge(pfx) {
    var ddn=document.getElementById(pfx+'-ddn').value.trim();
    var age=ageDepuisDDN(ddn);
    var el=document.getElementById(pfx+'-age-inp');
    if (age!==null) el.value=age;
}

// -- Validations --------------------------------------------------------------
function vNom(pfx) {
    var ok=document.getElementById(pfx+'-nom').value.trim().length>0;
    document.getElementById(pfx+'-nom').classList.toggle('err',!ok);
    document.getElementById('e-'+pfx+'-nom').classList.toggle('on',!ok);
    return ok;
}
function vDDN(pfx) {
    var v=document.getElementById(pfx+'-ddn').value.trim();
    if (!v) { document.getElementById(pfx+'-ddn').classList.remove('err'); document.getElementById('e-'+pfx+'-ddn').classList.remove('on'); return true; }
    var ok=/^\d{4}$/.test(v)||/^\d{2}\/\d{2}\/\d{4}$/.test(v);
    document.getElementById(pfx+'-ddn').classList.toggle('err',!ok);
    document.getElementById('e-'+pfx+'-ddn').classList.toggle('on',!ok);
    return ok;
}
function vCIN(pfx) {
    var v=document.getElementById(pfx+'-cin').value.trim();
    if (!v) { document.getElementById(pfx+'-cin').classList.remove('err'); document.getElementById('e-'+pfx+'-cin').classList.remove('on'); return true; }
    var ok=/^[A-Z]{1,2}[0-9]{1,6}$/.test(v);
    document.getElementById(pfx+'-cin').classList.toggle('err',!ok);
    document.getElementById('e-'+pfx+'-cin').classList.toggle('on',!ok);
    return ok;
}
function vMut(pfx) {
    var ok=document.querySelector('input[name="'+pfx+'-mut"]:checked')!==null;
    document.getElementById('e-'+pfx+'-mut').classList.toggle('on',!ok);
    return ok;
}
function vTel(pfx) {
    var v=document.getElementById(pfx+'-tel').value.trim();
    if (!v) { document.getElementById(pfx+'-tel').classList.remove('err'); document.getElementById('e-'+pfx+'-tel').classList.remove('on'); return true; }
    var ok=/^(06|07)[0-9]{8}$/.test(v);
    if (!ok) {
        var rem=document.getElementById(pfx+'-rem');
        if (rem&&rem.value.indexOf('Tel. etranger')===-1)
            rem.value=(rem.value.trim()?rem.value.trim()+'\n':'')+'Tel. etranger : '+v;
    }
    document.getElementById(pfx+'-tel').classList.toggle('err',!ok);
    document.getElementById('e-'+pfx+'-tel').classList.toggle('on',!ok);
    return ok;
}

// -- Enregistrer --------------------------------------------------------------
function enreg(mode) {
    var p=(mode==='ajouter')?'aj':'mod';
    var ok=true;
    if (!vNom(p)) ok=false;
    if (!vDDN(p)) ok=false;
    if (!vCIN(p)) ok=false;
    if (!vMut(p)) ok=false;
    if (!ok) return;

    var mut=document.querySelector('input[name="'+p+'-mut"]:checked');
    var d=new FormData();
    d.append('action',mode==='ajouter'?'ajouter':'modifier');
    d.append('nom',document.getElementById(p+'-nom').value.trim());
    d.append('ddn',document.getElementById(p+'-ddn').value.trim());
    d.append('age',document.getElementById(p+'-age-inp').value.trim());
    d.append('cin',document.getElementById(p+'-cin').value.trim());
    d.append('tel',document.getElementById(p+'-tel').value.trim());
    d.append('mutuelle',mut?mut.value:'');
    d.append('remarque',document.getElementById(p+'-rem').value.trim());
    if (mode==='modifier') d.append('id',document.getElementById('mod-id').value);

    var msg=document.getElementById('msg-'+mode);
    msg.className='mres ok'; msg.textContent=' Enregistrement...';

    var pfx = p;
    var modeLocal = mode;
    fetch('ajax_patient.php',{method:'POST',body:d})
    .then(function(r){return r.json();})
    .then(function(res){
        console.log('THEN res:', JSON.stringify(res), 'pfx:', pfx);
        var msgEl = document.getElementById('msg-'+modeLocal);
        if (res.ok === true && res.id > 0) {
            msgEl.className='mres ok';
            msgEl.textContent='OK : '+res.msg+' - N '+res.id;
            document.getElementById(pfx+'-npat').textContent='N '+res.id;
            var bdos=document.getElementById(pfx+'-dos');
            var bbl=document.getElementById(pfx+'-bilan');
            if (bdos) { bdos.href='dossier.php?id='+res.id; bdos.style.display='inline-block'; }
            if (bbl)  { bbl.href='nouveau_bilan_clinique.php?id='+res.id; bbl.style.display='inline-block'; }
            if (modeLocal==='ajouter') setTimeout(reinitAj, 10000);
        } else {
            msgEl.className='mres nok';
            msgEl.textContent='ERREUR : '+(res.msg||'Erreur inconnue');
        }
    })
    .catch(function(e){
        var msgEl = document.getElementById('msg-'+modeLocal);
        msgEl.className='mres nok';
        msgEl.textContent='ERREUR connexion : '+e.message;
    });
}

function reinitAj() {
    ['aj-nom','aj-ddn','aj-age-inp','aj-cin','aj-tel','aj-rem'].forEach(function(id){
        var el=document.getElementById(id);
        if(el){el.value='';el.classList.remove('err');}
    });
    document.querySelectorAll('input[name="aj-mut"]').forEach(function(r){r.checked=false;});
    ['e-aj-nom','e-aj-ddn','e-aj-cin','e-aj-tel','e-aj-mut'].forEach(function(id){
        var el=document.getElementById(id); if(el) el.classList.remove('on');
    });
    document.getElementById('aj-npat').textContent='Attribue automatiquement';
    document.getElementById('aj-dos').style.display='none';
    document.getElementById('aj-bilan').style.display='none';
    document.getElementById('msg-ajouter').className='mres';
    document.getElementById('msg-ajouter').textContent='';
}

// -- Charger patient -----------------------------------------------------------
function charger(mode) {
    var val=document.getElementById(mode==='modifier'?'mod-rech':'sup-rech').value.trim();
    if (!val) return;
    var d=new FormData(); d.append('action','charger'); d.append('id',val);
    fetch('ajax_patient.php',{method:'POST',body:d})
    .then(function(r){return r.json();})
    .then(function(res){
        if (res.ok) remplir(mode,res);
        else alert(' '+res.msg);
    })
    .catch(function(){alert(' Erreur connexion.');});
}

function remplir(mode,p) {
    if (mode==='modifier') {
        document.getElementById('form-mod').style.display='block';
        document.getElementById('mod-id').value=p.id;
        document.getElementById('mod-npat').textContent='N '+p.id;
        document.getElementById('mod-nom').value=p.nom;
        document.getElementById('mod-ddn').value=p.ddn;
        document.getElementById('mod-age-inp').value=p.age||'';
        document.getElementById('mod-cin').value=p.cin;
        document.getElementById('mod-tel').value=p.tel;
        document.getElementById('mod-rem').value=p.remarque;
        document.getElementById('mod-recrt').textContent=p.daterecrt||'-';
        document.querySelectorAll('input[name="mod-mut"]').forEach(function(r){r.checked=(r.value===p.mutuelle);});
        var bdos=document.getElementById('mod-dos'); bdos.href='dossier.php?id='+p.id; bdos.style.display='inline-block';
        var bbl=document.getElementById('mod-bilan'); bbl.href='nouveau_bilan_clinique.php?id='+p.id; bbl.style.display='inline-block';
        document.getElementById('msg-modifier').className='mres';
    } else {
        document.getElementById('form-sup').style.display='block';
        document.getElementById('sup-id').value=p.id;
        document.getElementById('sup-nom').textContent='N '+p.id+' - '+p.nom;
        document.getElementById('sup-recrt').textContent=p.daterecrt||'-';
        document.getElementById('msg-supprimer').className='mres';
    }
}

// -- Supprimer ----------------------------------------------------------------
function supprimer() {
    var id=document.getElementById('sup-id').value; if (!id) return;
    var msg=document.getElementById('msg-supprimer');
    msg.className='mres ok'; msg.textContent=' Suppression...';
    var d=new FormData(); d.append('action','supprimer'); d.append('id',id);
    fetch('ajax_patient.php',{method:'POST',body:d})
    .then(function(r){return r.json();})
    .then(function(res){
        msg.className='mres '+(res.ok?'ok':'nok');
        msg.textContent=(res.ok?' ':' ')+res.msg;
        if (res.ok){ document.getElementById('form-sup').style.display='none'; document.getElementById('sup-rech').value=''; }
    })
    .catch(function(){ msg.className='mres nok'; msg.textContent=' Erreur connexion.'; });
}

function annulerSup() {
    document.getElementById('form-sup').style.display='none';
    document.getElementById('sup-rech').value='';
    document.getElementById('msg-supprimer').className='mres';
}

// -- Init ---------------------------------------------------------------------
ong('<?= $modeInit ?>');
<?php if ($idInit > 0): ?>
(function(){
    var m='<?= $modeInit ?>';
    if (m==='modifier'){ document.getElementById('mod-rech').value=<?= $idInit ?>; charger('modifier'); }
    else if (m==='supprimer'){ document.getElementById('sup-rech').value=<?= $idInit ?>; charger('supprimer'); }
})();
<?php endif; ?>
</script>
</body>
</html>
