<?php
require_once __DIR__ . '/backend/auth.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Logycab — Cardiologie</title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
html, body { width:100%; height:100%; }
body {
  font-family: 'Courier New', monospace;
  background: #0d0d1a;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 100vh;
  overflow: hidden;
  position: relative;
}
.grid-bg {
  position: fixed;
  inset: 0;
  background-image:
    linear-gradient(rgba(0,212,170,0.04) 1px, transparent 1px),
    linear-gradient(90deg, rgba(0,212,170,0.04) 1px, transparent 1px);
  background-size: 30px 30px;
  pointer-events: none;
  z-index: 0;
}
.content {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
}
/* ── Logo ── */
.logo-bloc {
  display: flex;
  align-items: center;
  gap: 20px;
  margin-bottom: 10px;
}
.heart-wrap {
  position: relative;
  width: 60px;
  height: 60px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.heart-svg {
  width: 54px;
  height: 54px;
  animation: hbeat 1.6s infinite;
  filter: drop-shadow(0 0 10px #e74c3c);
}
@keyframes hbeat {
  0%,100% { transform: scale(1); }
  14%     { transform: scale(1.2); }
  28%     { transform: scale(1); }
  42%     { transform: scale(1.15); }
  56%     { transform: scale(1); }
}
.pulse-ring {
  position: absolute;
  width: 58px; height: 58px;
  border: 1px solid rgba(231,76,60,0.4);
  border-radius: 50%;
  animation: pulseRing 1.6s infinite;
}
@keyframes pulseRing {
  0%   { transform: scale(0.8); opacity: 0.6; }
  100% { transform: scale(1.9); opacity: 0; }
}
.logo-text {
  color: #00d4aa;
  font-size: 48px;
  font-weight: 700;
  letter-spacing: 8px;
  text-shadow: 0 0 24px rgba(0,212,170,0.35);
}
.logo-sub {
  color: #4ecdc4;
  font-size: 12px;
  letter-spacing: 4px;
  text-transform: uppercase;
  margin-top: 4px;
  opacity: 0.75;
}
/* ── Nom ── */
.dr-name {
  color: #7fb3d0;
  font-size: 13px;
  letter-spacing: 3px;
  margin-bottom: 32px;
}
/* ── ECG ── */
.ecg-wrap {
  width: 70vw;
  max-width: 640px;
  min-width: 300px;
  margin-bottom: 28px;
}
.ecg-label {
  color: rgba(0,212,170,0.35);
  font-size: 9px;
  letter-spacing: 3px;
  margin-bottom: 5px;
}
canvas#ecg {
  width: 100%;
  height: 90px;
  display: block;
  border-bottom: 0.5px solid rgba(0,212,170,0.12);
}
/* ── Horloge ── */
.clock-bloc {
  color: #00d4aa;
  font-size: 36px;
  font-weight: 700;
  letter-spacing: 6px;
  margin-bottom: 6px;
  text-shadow: 0 0 18px rgba(0,212,170,0.28);
}
.clock-date {
  color: rgba(0,212,170,0.5);
  font-size: 11px;
  letter-spacing: 4px;
  margin-bottom: 36px;
  text-transform: uppercase;
}
/* ── Message Entrée ── */
.enter-msg {
  color: rgba(0,212,170,0.45);
  font-size: 12px;
  letter-spacing: 4px;
  animation: blink 2.2s infinite;
}
@keyframes blink {
  0%,100% { opacity: 0.45; }
  50%      { opacity: 0.1; }
}
</style>
</head>
<body>

<div class="grid-bg"></div>

<div class="content">

  <!-- Logo -->
  <div class="logo-bloc">
    <div class="heart-wrap">
      <div class="pulse-ring"></div>
      <svg class="heart-svg" viewBox="0 0 32 29" fill="none">
        <path d="M16 27S2 18 2 9.5A7.5 7.5 0 0 1 16 5.6 7.5 7.5 0 0 1 30 9.5C30 18 16 27 16 27z" fill="#e74c3c"/>
      </svg>
    </div>
    <div>
      <div class="logo-text">LOGYCAB</div>
      <div class="logo-sub">Cabinet de Cardiologie</div>
    </div>
  </div>

  <!-- Nom -->
  <div class="dr-name">Dr Hassan Hlimi &mdash; Cardiologue &middot; T&eacute;touan</div>

  <!-- ECG -->
  <div class="ecg-wrap">
    <div class="ecg-label">ECG &mdash; SURVEILLANCE CONTINUE</div>
    <canvas id="ecg"></canvas>
  </div>

  <!-- Horloge -->
  <div class="clock-bloc" id="splashTime">--:--:--</div>
  <div class="clock-date" id="splashDate">---</div>

  <!-- Message -->
  <div class="enter-msg">&#9654; APPUYEZ SUR ENTR&Eacute;E POUR COMMENCER</div>

</div>

<script>
/* ── HORLOGE ── */
(function() {
    var jours = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
    var mois  = ['Jan','\u00e9v','Mar','Avr','Mai','Juin','Juil','Ao\u00fb','Sep','Oct','Nov','D\u00e9c'];
    mois[1] = 'F\u00e9v';
    function tick() {
        var n = new Date();
        var h = String(n.getHours()).padStart(2,'0');
        var m = String(n.getMinutes()).padStart(2,'0');
        var s = String(n.getSeconds()).padStart(2,'0');
        var t = document.getElementById('splashTime');
        var d = document.getElementById('splashDate');
        if (t) t.textContent = h+':'+m+':'+s;
        if (d) d.textContent = jours[n.getDay()]+' '+n.getDate()+' '+mois[n.getMonth()]+' '+n.getFullYear();
    }
    tick();
    setInterval(tick, 1000);
})();

/* ── ECG ANIME ── */
(function() {
    var canvas = document.getElementById('ecg');
    if (!canvas) return;
    canvas.width  = canvas.offsetWidth  || 640;
    canvas.height = canvas.offsetHeight || 90;
    var ctx = canvas.getContext('2d');
    var W = canvas.width;
    var H = canvas.height;
    var mid = H / 2;
    var speed = 0.8;
    var color = '#00d4aa';

    function buildBeat(startX) {
        var pts = [];
        function push(dx, dy) { pts.push([startX + dx, mid - dy * (H * 0.40)]); }
        var i;
        for (i = 0; i < 32; i++) push(i, 0);
        push(32,0); push(34,0.12); push(37,0.22); push(40,0.12); push(43,0);
        for (i = 0; i < 12; i++) push(43+i, 0);
        push(55,0); push(57,-0.18); push(59,0.95); push(60,-0.52); push(62,-0.08); push(64,0);
        for (i = 0; i < 8; i++) push(64+i, 0.04);
        push(72,0.04); push(75,0.28); push(79,0.40); push(83,0.28); push(88,0.08); push(93,0);
        for (i = 0; i < 22; i++) push(93+i, 0);
        return pts;
    }

    var beatLen = 115;
    var allPts = [];
    for (var b = 0; b < 6; b++) {
        var beat = buildBeat(b * beatLen);
        for (var k = 0; k < beat.length; k++) allPts.push(beat[k]);
    }

    var offset = 0;

    function draw() {
        ctx.clearRect(0, 0, W, H);

        ctx.strokeStyle = 'rgba(0,212,170,0.06)';
        ctx.lineWidth = 0.5;
        var x, y;
        for (x = 0; x < W; x += 25) {
            ctx.beginPath(); ctx.moveTo(x,0); ctx.lineTo(x,H); ctx.stroke();
        }
        for (y = 0; y < H; y += 25) {
            ctx.beginPath(); ctx.moveTo(0,y); ctx.lineTo(W,y); ctx.stroke();
        }

        ctx.strokeStyle = color;
        ctx.lineWidth = 1.8;
        ctx.shadowColor = color;
        ctx.shadowBlur = 5;
        ctx.beginPath();
        var started = false;
        for (var i = 0; i < allPts.length; i++) {
            var px = allPts[i][0] - offset;
            var py = allPts[i][1];
            if (px < 0 || px > W) continue;
            if (!started) { ctx.moveTo(px, py); started = true; }
            else ctx.lineTo(px, py);
        }
        ctx.stroke();
        ctx.shadowBlur = 0;

        offset += speed;
        if (offset >= beatLen) offset -= beatLen;

        requestAnimationFrame(draw);
    }
    draw();
})();

/* ── NAVIGATION VERS ACCUEIL ── */
function allerAccueil() {
    window.location.href = 'index.php';
}
setTimeout(function() {
    document.addEventListener('keydown', function() {
        allerAccueil();
    });
    document.addEventListener('click', function() {
        allerAccueil();
    });
}, 2000);
</script>
</body>
</html>
