<?php
// game_bubble_pop.php  —  Target Practice Quiz  (replaces Bubble Pop)
// Pixel arcade shooter: shoot the correct falling target! 3 lives, 15 s/question.
// Uses $_SESSION['quiz_data']['questions'] — same contract as quiz.php.
// Saves XP via save_quiz_result.php.

session_start();
require_once __DIR__ . '/game_mode_access.php';
requireGameModeAccess('bubble_pop');

$questions = $_SESSION['quiz_data']['questions'] ?? [];
if (empty($questions)) {
    header("Location: quizzes.php?error=no_questions_found");
    exit();
}

$mcq = [];
foreach ($questions as $q) {
    if (!empty($q['options']) && count($q['options']) >= 4 && !empty($q['answer'])) {
        $mcq[] = $q;
    }
}
if (count($mcq) < 2) $mcq = array_values($questions);
if (count($mcq) < 2) {
    header("Location: quizzes.php?error=not_enough_questions");
    exit();
}

$title   = $_SESSION['quiz_data']['title'] ?? 'Target Practice';
$total_q = count($mcq);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Target Practice | PinnaQuest</title>
<link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&family=VT323&display=swap"
      rel="stylesheet">
<style>
/* ══════════════════════════════════════════════════════════════
   ROOT TOKENS — Pixel Arcade Shooter
══════════════════════════════════════════════════════════════ */
:root {
    --bg:          #070b0e;
    --amber:       #ffaa00;
    --neon-green:  #39ff14;
    --neon-red:    #ff3c3c;
    --cyan:        #00ccff;
    --text:        #c8d8e8;
    --dim:         #445566;
    --scan:        rgba(0,0,0,.16);

    /* Gem palette per target (fill + glow) */
    --t0: #ff2244;  --t0g: rgba(255,34,68,.65);   /* ruby red */
    --t1: #0088ff;  --t1g: rgba(0,136,255,.65);   /* sapphire blue */
    --t2: #00e87a;  --t2g: rgba(0,232,122,.65);   /* emerald green */
    --t3: #ffcc00;  --t3g: rgba(255,204,0,.65);   /* gold topaz */

    /* Turret */
    --turret-base:  #0e1e2e;
    --turret-metal: #1e3a52;
    --turret-hi:    #2a5575;
    --turret-barrel:#0d2030;
}

*, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

html, body {
    width:100%; height:100%;
    overflow:hidden;
    font-family: 'Press Start 2P', monospace;
    background: var(--bg);
    color: var(--text);
    user-select:none;
    -webkit-user-select:none;
    cursor:crosshair;
    image-rendering:pixelated;
}

/* ══════════════════════════════════════════════════════════════
   PIXEL ARCADE BACKGROUND — Multi-layer space city scene
══════════════════════════════════════════════════════════════ */
#bg {
    position:fixed; inset:0; z-index:0;
    background: linear-gradient(180deg,
        #01030a 0%,   #020510 18%,
        #040c1e 45%,  #051526 70%,
        #061830 85%,  #071c38 100%);
}

/* ── STAR LAYERS — 3 speeds for parallax depth ── */
.star-layer {
    position:absolute; inset:0;
    background:transparent;
}
.star-layer-1 {
    animation: starsDrift1 80s linear infinite;
    box-shadow:
        42px 18px 0 1px rgba(255,255,255,.9),
        135px 55px 0 1px rgba(200,220,255,.7),
        278px 22px 0 1px rgba(255,255,255,.8),
        412px 88px 0 2px rgba(255,255,200,.7),
        560px 14px 0 1px rgba(200,230,255,.9),
        698px 72px 0 1px rgba(255,255,255,.6),
        810px 30px 0 1px rgba(200,200,255,.8),
        945px 60px 0 2px rgba(255,240,200,.7),
        1088px 22px 0 1px rgba(255,255,255,.9),
        1210px 85px 0 1px rgba(200,220,255,.6),
        1340px 40px 0 1px rgba(255,255,255,.8),
        80px 130px 0 1px rgba(255,255,255,.5),
        220px 145px 0 1px rgba(200,200,255,.4),
        370px 120px 0 1px rgba(255,255,200,.5),
        520px 160px 0 1px rgba(255,255,255,.4),
        670px 105px 0 1px rgba(255,255,255,.5),
        820px 142px 0 1px rgba(200,230,255,.4),
        970px 118px 0 1px rgba(255,255,255,.5),
        1120px 155px 0 1px rgba(255,255,200,.4),
        1270px 125px 0 1px rgba(255,255,255,.5),
        1420px 148px 0 1px rgba(200,200,255,.4);
}
.star-layer-2 {
    animation: starsDrift2 55s linear infinite;
    box-shadow:
        25px 35px 0 1px rgba(255,255,255,.6),
        188px 12px 0 1px rgba(200,240,255,.5),
        310px 68px 0 1px rgba(255,255,255,.7),
        455px 28px 0 1px rgba(255,200,200,.5),
        590px 82px 0 1px rgba(200,255,200,.6),
        730px 15px 0 1px rgba(255,255,255,.5),
        875px 55px 0 1px rgba(200,200,255,.7),
        1010px 35px 0 1px rgba(255,255,200,.5),
        1155px 70px 0 1px rgba(255,255,255,.6),
        1300px 18px 0 1px rgba(200,240,255,.5);
}
.star-layer-3 {
    animation: starsDrift3 35s linear infinite;
    box-shadow:
        90px 8px 0 2px rgba(255,255,255,.8),
        240px 44px 0 1px rgba(255,220,100,.7),
        395px 5px 0 2px rgba(100,220,255,.8),
        550px 38px 0 1px rgba(255,255,255,.9),
        705px 10px 0 2px rgba(255,100,200,.6),
        860px 48px 0 1px rgba(255,255,255,.8),
        1015px 6px 0 2px rgba(100,255,200,.7),
        1170px 42px 0 1px rgba(255,200,100,.8),
        1325px 12px 0 2px rgba(255,255,255,.9);
}
@keyframes starsDrift1  { 0%{background-position:0 0} 100%{background-position:0 600px} }
@keyframes starsDrift2  { 0%{background-position:0 0} 100%{background-position:0 400px} }
@keyframes starsDrift3  { 0%{background-position:0 0} 100%{background-position:0 280px} }

/* ── NEBULA GLOW ── */
.nebula {
    position:absolute; border-radius:50%; pointer-events:none; mix-blend-mode:screen;
}
.nebula-1 {
    width:340px; height:180px; top:15%; left:10%;
    background:radial-gradient(ellipse, rgba(0,100,255,.07) 0%, transparent 70%);
    animation: nebulaPulse 8s ease-in-out infinite alternate;
}
.nebula-2 {
    width:260px; height:200px; top:5%; right:12%;
    background:radial-gradient(ellipse, rgba(180,0,255,.06) 0%, transparent 70%);
    animation: nebulaPulse 11s ease-in-out infinite alternate-reverse;
}
.nebula-3 {
    width:200px; height:120px; top:35%; left:55%;
    background:radial-gradient(ellipse, rgba(0,200,150,.05) 0%, transparent 70%);
    animation: nebulaPulse 7s ease-in-out infinite alternate;
}
@keyframes nebulaPulse { from{opacity:.6;transform:scale(1)} to{opacity:1;transform:scale(1.15)} }

/* ── PIXEL CITY SKYLINE (bottom layer) ── */
#city-skyline {
    position:absolute; bottom:0; left:0; right:0;
    height:200px; pointer-events:none;
}
.city-svg { width:100%; height:100%; }

/* ── NEON FLOOR GRID ── */
.neon-grid {
    position:absolute; bottom:0; left:0; right:0;
    height:90px; overflow:hidden; pointer-events:none;
    perspective:280px;
}
.neon-grid-inner {
    position:absolute; inset:0;
    background:
        repeating-linear-gradient(
            90deg,
            transparent 0px, transparent 59px,
            rgba(0,204,255,.18) 59px, rgba(0,204,255,.18) 60px
        ),
        linear-gradient(to bottom, transparent 0%, rgba(0,204,255,.08) 100%);
    transform: rotateX(55deg) scaleX(1.8);
    transform-origin: bottom center;
    animation: gridScroll 3s linear infinite;
}
@keyframes gridScroll {
    0%   { background-position: 0 0; }
    100% { background-position: 0 60px; }
}

/* CRT scanlines overlay */
body::after {
    content:'';
    position:fixed; inset:0; z-index:999;
    background:repeating-linear-gradient(
        to bottom,
        transparent 0px, transparent 2px,
        var(--scan) 2px, var(--scan) 3px
    );
    pointer-events:none;
}

/* Pixel corner brackets */
.corner {
    position:fixed; z-index:5;
    width:44px; height:44px;
    pointer-events:none; opacity:.3;
}
.corner::before, .corner::after { content:''; position:absolute; background:var(--cyan); }
.corner::before { top:0; left:0; width:28px; height:3px; }
.corner::after  { top:0; left:0; width:3px;  height:28px; }
.corner-tl { top:10px; left:10px; }
.corner-tr { top:10px; right:10px; transform:scaleX(-1); }
.corner-bl { bottom:10px; left:10px; transform:scaleY(-1); }
.corner-br { bottom:10px; right:10px; transform:scale(-1); }

/* ══════════════════════════════════════════════════════════════
   HUD  (sticky top bar)
══════════════════════════════════════════════════════════════ */
#hud {
    position:fixed; top:0; left:0; right:0; z-index:100;
    height:56px;
    background:rgba(3,5,8,.97);
    border-bottom:2px solid rgba(0,204,255,.22);
    box-shadow:0 2px 0 rgba(0,0,0,.6), 0 4px 20px rgba(0,204,255,.06);
    display:flex; align-items:center;
    padding:0 16px; gap:12px;
}

.hud-logo {
    font-size:10px; letter-spacing:2px;
    color:var(--neon-red);
    text-shadow:0 0 14px var(--neon-red), 0 0 28px rgba(255,60,60,.3), 2px 2px 0 #500;
    white-space:nowrap; flex-shrink:0;
    padding:4px 8px;
    border:1px solid rgba(255,60,60,.22);
    background:rgba(255,30,30,.06);
}

/* Lives */
#lives-display { display:flex; gap:5px; align-items:center; flex-shrink:0; }
.life-icon {
    font-size:20px; line-height:1;
    transition:filter .2s, transform .2s;
    filter:drop-shadow(0 0 5px var(--neon-red));
}
.life-icon.lost { filter:grayscale(1) opacity(.22); transform:scale(.7); }

.hud-spacer { flex:1; }

/* LCD info chips */
.hud-chip {
    font-size:8px;
    background:rgba(0,0,0,.8);
    border:1px solid rgba(255,255,255,.1);
    border-top:2px solid rgba(255,255,255,.18);
    border-bottom:2px solid rgba(0,0,0,.8);
    box-shadow:0 0 0 1px rgba(0,0,0,.6), 2px 2px 0 rgba(0,0,0,.5);
    padding:5px 10px; text-align:center; min-width:58px;
    position:relative;
}
.hud-chip::after {
    content:''; position:absolute; inset:1px;
    border-top:1px solid rgba(255,255,255,.06);
    border-left:1px solid rgba(255,255,255,.06);
    pointer-events:none;
}
.hud-chip .val { font-size:15px; display:block; margin-bottom:3px; }
.hud-chip .lbl { font-size:6px; color:var(--dim); letter-spacing:.6px; }
.hud-chip.c-cyan  .val { color:var(--cyan);      text-shadow:0 0 10px var(--cyan), 0 0 20px rgba(0,204,255,.3); }
.hud-chip.c-amber .val { color:var(--amber);     text-shadow:0 0 10px var(--amber),0 0 20px rgba(255,170,0,.3); }
.hud-chip.c-green .val { color:var(--neon-green);text-shadow:0 0 10px var(--neon-green),0 0 20px rgba(57,255,20,.3); }

/* ══════════════════════════════════════════════════════════════
   QUESTION PANEL
══════════════════════════════════════════════════════════════ */
#question-panel {
    position:fixed; top:56px; left:0; right:0; z-index:90;
    display:flex; flex-direction:column; align-items:center;
    padding:12px 16px 8px; pointer-events:none;
}

/* Timer bar */
#timer-track {
    width:min(560px,96%); height:6px;
    background:rgba(255,255,255,.06);
    border:1px solid rgba(255,255,255,.05);
    margin-bottom:10px;
}
#timer-fill {
    height:100%;
    background:var(--neon-green);
    box-shadow:0 0 8px var(--neon-green);
    transition:width 1s linear;
}
#timer-fill.warn   { background:var(--amber);    box-shadow:0 0 8px var(--amber); }
#timer-fill.danger { background:var(--neon-red); box-shadow:0 0 8px var(--neon-red); }

/* Question card */
#q-card {
    width:min(560px,96%);
    background:rgba(4,9,14,.88);
    border:1px solid rgba(0,204,255,.16);
    border-top:2px solid rgba(0,204,255,.32);
    padding:14px 18px; text-align:center;
}
.q-eyebrow {
    font-size:7px; letter-spacing:2px;
    color:var(--cyan); text-shadow:0 0 7px var(--cyan);
    margin-bottom:8px;
}
#q-text {
    font-family:'VT323',monospace; font-size:clamp(16px,3.2vw,22px);
    font-weight:normal; line-height:1.5; color:#eef4ff;
    text-shadow:0 0 12px rgba(200,220,255,.25);
}

/* ══════════════════════════════════════════════════════════════
   ARENA
══════════════════════════════════════════════════════════════ */
#arena {
    position:fixed; inset:0; z-index:10;
    pointer-events:none; overflow:hidden;
}

/* ══════════════════════════════════════════════════════════════
   PIXEL GEM TARGETS — each option gets a distinct crystal shape
══════════════════════════════════════════════════════════════ */
.target {
    position:absolute;
    display:flex; align-items:center; justify-content:center;
    text-align:center; cursor:crosshair;
    pointer-events:all;
    animation:
        targetFall    var(--fall-dur,8s)    linear        forwards,
        targetWobble  var(--wobble-dur,2.8s) ease-in-out  infinite alternate,
        gemPulse      1.8s ease-in-out infinite alternate;
    filter:drop-shadow(0 0 8px var(--gem-glow, rgba(255,255,255,.5)));
}
.target:hover  { filter:drop-shadow(0 0 18px var(--gem-glow)) brightness(1.18); }
.target:active { filter:drop-shadow(0 0 28px var(--gem-glow)) brightness(1.5); }
@keyframes gemPulse {
    from { filter:drop-shadow(0 0 6px var(--gem-glow,.5)) brightness(1); }
    to   { filter:drop-shadow(0 0 18px var(--gem-glow)) brightness(1.08); }
}

/* Inner gem decoration lines */
.target::before {
    content:''; position:absolute; inset:14% 20%;
    background:linear-gradient(135deg,
        rgba(255,255,255,.18) 0%, rgba(255,255,255,.04) 40%,
        transparent 50%, rgba(0,0,0,.12) 100%);
    pointer-events:none; z-index:1;
}
/* Pixel scanline texture on each target */
.target::after {
    content:''; position:absolute; inset:0;
    background:repeating-linear-gradient(
        0deg, transparent 0px, transparent 3px,
        rgba(0,0,0,.08) 3px, rgba(0,0,0,.08) 4px
    );
    pointer-events:none; z-index:2;
}

/* Answer text */
.target-text {
    position:relative; z-index:5;
    font-family:'VT323',monospace;
    font-weight:normal; text-align:center;
    color:#fff;
    text-shadow:
        1px  1px 0 #000, -1px -1px 0 #000,
        1px -1px 0 #000, -1px  1px 0 #000,
        0 0 10px rgba(0,0,0,1);
    line-height:1.2; padding:6px 10px;
    max-width:88%; word-break:break-word;
    overflow-wrap:break-word;
    background:rgba(0,0,0,.78);
    border:1px solid rgba(255,255,255,.3);
    border-top:1px solid rgba(255,255,255,.5);
    letter-spacing:.5px;
    pointer-events:none;
}

/* ── GEM SHAPE 0 — Ruby Octagon ── */
.target-0 {
    --gem-glow: var(--t0g);
    clip-path:polygon(30% 0%,70% 0%,100% 30%,100% 70%,70% 100%,30% 100%,0% 70%,0% 30%);
    background:linear-gradient(135deg,
        #ff667a 0%, #cc1133 25%, #880020 55%, #cc1133 80%, #ff4466 100%);
    box-shadow:inset 0 2px 0 rgba(255,180,180,.3), inset 0 -2px 0 rgba(0,0,0,.5);
}
/* ── GEM SHAPE 1 — Sapphire Shield ── */
.target-1 {
    --gem-glow: var(--t1g);
    clip-path:polygon(12% 0%,88% 0%,100% 18%,100% 68%,50% 100%,0% 68%,0% 18%);
    background:linear-gradient(160deg,
        #66aaff 0%, #1166cc 30%, #003388 60%, #1166cc 85%, #4499ff 100%);
    box-shadow:inset 0 2px 0 rgba(150,200,255,.3), inset 0 -2px 0 rgba(0,0,0,.5);
}
/* ── GEM SHAPE 2 — Emerald Hex ── */
.target-2 {
    --gem-glow: var(--t2g);
    clip-path:polygon(25% 0%,75% 0%,100% 50%,75% 100%,25% 100%,0% 50%);
    background:linear-gradient(135deg,
        #44ffaa 0%, #00cc55 28%, #006633 58%, #00cc55 82%, #33ee88 100%);
    box-shadow:inset 0 2px 0 rgba(100,255,180,.3), inset 0 -2px 0 rgba(0,0,0,.5);
}
/* ── GEM SHAPE 3 — Topaz Diamond ── */
.target-3 {
    --gem-glow: var(--t3g);
    clip-path:polygon(50% 0%,100% 38%,82% 100%,18% 100%,0% 38%);
    background:linear-gradient(160deg,
        #ffe066 0%, #ddaa00 28%, #886600 58%, #ddaa00 82%, #ffdd44 100%);
    box-shadow:inset 0 2px 0 rgba(255,240,150,.3), inset 0 -2px 0 rgba(0,0,0,.5);
}

/* ── FALL & WOBBLE ANIMATIONS ── */
@keyframes targetFall {
    0%   { transform:translateX(var(--drift-start,0px)) translateY(0); opacity:0; }
    6%   { opacity:1; }
    92%  { opacity:1; }
    100% { transform:translateX(var(--drift-end,0px)) translateY(var(--fall-dist,110vh)); opacity:0; }
}
@keyframes targetWobble {
    from { margin-left:0; }
    to   { margin-left:var(--wobble-amt,10px); }
}

/* ── SHOOT ANIMATIONS ── */
.target.shot-correct {
    animation:shootHit .55s ease-out forwards !important;
    pointer-events:none;
}
.target.shot-wrong {
    animation:shootMiss .55s ease-out forwards !important;
    pointer-events:none;
}
@keyframes shootHit {
    0%   { transform:scale(1) rotate(0deg);   opacity:1;  filter:brightness(1); }
    10%  { transform:scale(1.3) rotate(5deg); filter:brightness(5) saturate(.2); }
    30%  { transform:scale(1.8) rotate(-8deg);filter:brightness(7); opacity:.8; }
    60%  { transform:scale(2.4) rotate(12deg);opacity:.4; filter:brightness(8) hue-rotate(60deg); }
    100% { transform:scale(3.2) rotate(20deg);opacity:0;  filter:brightness(10); }
}
@keyframes shootMiss {
    0%   { transform:scale(1)    rotate(0);    opacity:1; }
    20%  { transform:scale(1.1)  rotate(-7deg); filter:brightness(2.5) hue-rotate(180deg); }
    45%  { transform:scale(.88)  rotate(7deg); filter:brightness(1); }
    70%  { transform:scale(1.04) rotate(-2deg); }
    100% { transform:scale(1)    rotate(0);    opacity:0; }
}

/* ── HIT / MISS SCREEN FLASH ── */
#hit-flash {
    position:fixed; inset:0; z-index:180;
    pointer-events:none; opacity:0;
    transition:opacity .08s;
}
#hit-flash.correct { background:rgba(57,255,20,.16); }
#hit-flash.wrong   { background:rgba(255,60,60,.22); }
#hit-flash.on      { opacity:1; transition:opacity .04s; }

/* ── IMPACT RING (expands on click) ── */
.impact-ring {
    position:fixed; pointer-events:none; z-index:200;
    border-radius:50%;
    transform:translate(-50%,-50%);
    animation:impactRing .55s ease-out forwards;
}
@keyframes impactRing {
    0%   { width:0;    height:0;    opacity:1; border-width:4px; }
    60%  { opacity:.7; }
    100% { width:80px; height:80px; opacity:0; border-width:1px; }
}

/* ── BANG TEXT ── */
.bang-txt {
    position:fixed; pointer-events:none; z-index:201;
    font-family:'Press Start 2P',monospace; font-size:15px;
    transform:translate(-50%,-50%);
    text-shadow:2px 2px 0 #000, 0 0 14px currentColor;
    animation:bangPop .75s ease-out forwards;
}
@keyframes bangPop {
    0%   { transform:translate(-50%,-55%) scale(.3) rotate(-10deg); opacity:1; }
    35%  { transform:translate(-50%,-110%) scale(1.18) rotate(4deg); opacity:1; }
    100% { transform:translate(-50%,-160%) scale(.9) rotate(-2deg); opacity:0; }
}

/* ── FLOATING SCORE POP ── */
.score-pop {
    position:fixed; pointer-events:none; z-index:202;
    font-family:'Press Start 2P',monospace; font-size:16px;
    color:var(--amber);
    text-shadow:2px 2px 0 #000, 0 0 14px var(--amber);
    animation:scorePop 1.1s ease-out forwards;
}
@keyframes scorePop {
    0%   { transform:translateY(0)     scale(.8); opacity:1; }
    60%  { transform:translateY(-55px) scale(1.1); opacity:1; }
    100% { transform:translateY(-90px) scale(.9); opacity:0; }
}

/* ── AMBIENT PIXEL SPARKS ── */
.px-spark {
    position:fixed; pointer-events:none; z-index:2;
    image-rendering:pixelated;
    animation:sparkFall var(--sp-dur,4s) linear forwards;
}
@keyframes sparkFall {
    0%   { opacity:.85; transform:translateY(0)      rotate(0deg); }
    100% { opacity:0;   transform:translateY(110vh)  rotate(540deg); }
}

/* ══════════════════════════════════════════════════════════════
   INSTRUCTIONS SCREEN
══════════════════════════════════════════════════════════════ */
#instructions-screen {
    position:fixed; inset:0; z-index:250;
    display:flex; flex-direction:column;
    align-items:center; justify-content:center;
    background:rgba(3,6,9,.97);
    padding:24px 20px; text-align:center;
    animation:fadeIn .35s ease-out;
}
.inst-title {
    font-size:clamp(13px,3.5vw,22px);
    color:var(--neon-red);
    text-shadow:0 0 18px var(--neon-red), 3px 3px 0 #500;
    letter-spacing:2px; margin-bottom:18px;
}
.inst-subtitle {
    font-family:'VT323',monospace; font-size:clamp(16px,3vw,22px);
    color:var(--cyan); text-shadow:0 0 8px var(--cyan);
    margin-bottom:22px; letter-spacing:1px;
}
.inst-grid {
    display:grid; grid-template-columns:1fr 1fr;
    gap:10px; max-width:600px; width:100%;
    margin-bottom:24px;
}
.inst-card {
    background:rgba(0,0,0,.6);
    border:1px solid rgba(255,255,255,.09);
    border-top:2px solid var(--amber);
    padding:12px 14px; text-align:left;
}
.inst-card .ic-icon { font-size:22px; display:block; margin-bottom:6px; }
.inst-card .ic-text {
    font-family:'VT323',monospace;
    font-size:clamp(14px,2.5vw,18px);
    color:rgba(255,255,255,.82); line-height:1.35;
}
.inst-card .ic-text b { color:var(--amber); }
.inst-tip {
    font-family:'VT323',monospace; font-size:clamp(14px,2.2vw,17px);
    color:rgba(255,255,255,.4); margin-bottom:22px; max-width:480px;
    line-height:1.5;
}
.inst-start-btn {
    font-family:'Press Start 2P',monospace;
    font-size:clamp(9px,2vw,13px); letter-spacing:2px;
    padding:14px 36px; border:none; cursor:crosshair;
    background:var(--neon-red); color:#000;
    box-shadow:0 0 18px rgba(255,60,60,.5), 4px 4px 0 #600;
    animation:instBtnPulse 1.1s ease-in-out infinite alternate;
    transition:transform .12s, box-shadow .12s;
}
.inst-start-btn:hover { transform:translateY(-3px) scale(1.04); }
@keyframes instBtnPulse {
    from { box-shadow:0 0 14px rgba(255,60,60,.4), 4px 4px 0 #600; }
    to   { box-shadow:0 0 28px rgba(255,60,60,.75), 4px 4px 0 #600; }
}
@media(max-width:480px) {
    .inst-grid { grid-template-columns:1fr; gap:8px; }
    .inst-card { padding:10px 12px; }
}

/* ══════════════════════════════════════════════════════════════
   OVERLAY  (between questions / get ready)
══════════════════════════════════════════════════════════════ */
#overlay {
    position:fixed; inset:0; z-index:150;
    display:flex; flex-direction:column;
    align-items:center; justify-content:center;
    background:rgba(3,6,9,.93);
    opacity:0; pointer-events:none;
    transition:opacity .25s;
    text-align:center; padding:30px;
}
#overlay.show { opacity:1; pointer-events:all; }

.ov-big {
    font-size:clamp(26px,7vw,56px);
    color:#fff; letter-spacing:3px;
    text-shadow:0 0 30px var(--neon-red), 3px 3px 0 #500;
    animation:ovFlash .38s step-end;
}
@keyframes ovFlash { 0%,49%{ opacity:0; } 50%,100%{ opacity:1; } }
.ov-sub {
    font-family:'VT323',monospace; font-size:20px;
    color:rgba(255,255,255,.42); margin-top:12px; letter-spacing:1px;
}

/* ══════════════════════════════════════════════════════════════
   RESULT SCREEN
══════════════════════════════════════════════════════════════ */
#result-screen {
    display:none;
    position:fixed; inset:0; z-index:300;
    background:linear-gradient(155deg,#040608 0%,#080c11 50%,#040608 100%);
    flex-direction:column; align-items:center; justify-content:center;
    text-align:center; padding:30px;
    animation:fadeIn .45s ease-out;
}
#result-screen.show { display:flex; }
@keyframes fadeIn { from { opacity:0; } to { opacity:1; } }

.result-trophy {
    font-size:64px; margin-bottom:14px;
    animation:trophySpin .7s cubic-bezier(.34,1.56,.64,1);
}
@keyframes trophySpin {
    from { transform:scale(0) rotate(-180deg); }
    to   { transform:scale(1) rotate(0); }
}
.result-title {
    font-size:clamp(16px,4.5vw,32px); color:#fff;
    text-shadow:0 0 22px var(--neon-red), 3px 3px 0 #600;
    letter-spacing:2px; margin-bottom:8px;
}
.result-sub {
    font-family:'VT323',monospace; font-size:20px;
    color:rgba(255,255,255,.48); margin-bottom:24px;
}
.result-stats {
    display:flex; gap:12px; justify-content:center;
    margin-bottom:22px; flex-wrap:wrap;
}
.rs {
    background:rgba(0,0,0,.55);
    border:1px solid rgba(255,255,255,.1);
    border-top:2px solid var(--amber);
    padding:16px 22px; min-width:100px;
}
.rs .rv {
    font-size:28px; color:var(--amber);
    text-shadow:0 0 10px var(--amber);
    display:block; margin-bottom:4px;
}
.rs .rl { font-size:7px; color:rgba(255,255,255,.4); letter-spacing:.5px; }
.xp-badge {
    font-size:12px;
    background:rgba(255,170,0,.1);
    border:2px solid var(--amber); color:var(--amber);
    text-shadow:0 0 8px var(--amber);
    padding:10px 24px; margin-bottom:22px;
    animation:xpPop .45s .4s cubic-bezier(.34,1.56,.64,1) both;
}
@keyframes xpPop { from { transform:scale(.4); opacity:0; } to { transform:scale(1); opacity:1; } }

.result-btns { display:flex; gap:10px; flex-wrap:wrap; justify-content:center; }
.btn-r {
    padding:12px 22px; border:none;
    font-family:'Press Start 2P'; font-size:9px;
    letter-spacing:1px; cursor:crosshair;
    transition:transform .12s, box-shadow .12s;
}
.btn-r:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(0,0,0,.5); }
.btn-red   { background:var(--neon-red); color:#000; box-shadow:0 0 12px rgba(255,60,60,.35); }
.btn-ghost { background:rgba(255,255,255,.08); color:#999; border:1px solid rgba(255,255,255,.14); }

/* Pixel confetti */
.conf {
    position:fixed; pointer-events:none; z-index:999;
    image-rendering:pixelated;
    animation:confFall var(--cd,2s) var(--cl,0s) ease-in forwards;
}
@keyframes confFall {
    0%   { transform:translateY(-10px) rotate(0deg); opacity:1; }
    100% { transform:translateY(110vh) rotate(720deg); opacity:0; }
}

/* ── Responsive ── */
@media (max-width:480px) {
    .target-text { font-size:10px; }
    #q-text      { font-size:15px; }
    .hud-logo    { font-size:8px; }
    .hud-chip    { min-width:50px; padding:4px 7px; }
    .hud-chip .val { font-size:12px; }
}

/* ══════════════════════════════════════════════════════════════
   PIXEL ART TURRET
══════════════════════════════════════════════════════════════ */
#turret-wrap {
    position:fixed; bottom:0; left:50%;
    transform:translateX(-50%);
    z-index:80; pointer-events:none;
    display:flex; flex-direction:column; align-items:center;
}

/* Turret base / tank body */
#turret-body {
    width:88px; height:40px;
    background:linear-gradient(180deg,var(--turret-hi) 0%,var(--turret-metal) 55%,var(--turret-base) 100%);
    border:2px solid rgba(0,204,255,.25);
    border-bottom:none;
    box-shadow:
        0 0 0 2px rgba(0,0,0,.8),
        inset 0 2px 0 rgba(255,255,255,.1),
        0 0 18px rgba(0,204,255,.12);
    position:relative;
    display:flex; align-items:center; justify-content:center;
}
/* pixel art panel lines */
#turret-body::before {
    content:''; position:absolute;
    top:6px; left:10px; right:10px; height:2px;
    background:rgba(0,204,255,.2);
    box-shadow:0 8px 0 rgba(0,204,255,.1);
}
/* Cockpit window */
#turret-body::after {
    content:''; position:absolute;
    width:18px; height:12px;
    background:rgba(0,204,255,.25);
    border:1px solid rgba(0,204,255,.4);
    box-shadow:0 0 8px rgba(0,204,255,.3);
    top:8px; left:50%; transform:translateX(-50%);
}

/* Turret pivot + barrel assembly */
#turret-pivot {
    position:absolute;
    top:-22px; left:50%; transform:translateX(-50%);
    width:36px; height:36px;
    display:flex; align-items:center; justify-content:center;
    transition:transform .08s linear;
}
#turret-dome {
    position:absolute; inset:0;
    background:radial-gradient(circle at 40% 35%,
        var(--turret-hi) 0%,var(--turret-metal) 55%,var(--turret-base) 100%);
    border:2px solid rgba(0,204,255,.3);
    border-radius:50%;
    box-shadow:0 0 12px rgba(0,204,255,.18), inset 0 2px 0 rgba(255,255,255,.15);
}
#turret-barrel {
    position:absolute;
    bottom:50%; left:50%;
    transform:translateX(-50%);
    width:8px; height:28px;
    transform-origin:bottom center;
    background:linear-gradient(90deg,var(--turret-hi),var(--turret-barrel),var(--turret-hi));
    border:1px solid rgba(0,204,255,.22);
    border-radius:2px 2px 0 0;
    box-shadow:0 0 6px rgba(0,204,255,.15);
}
/* Barrel muzzle */
#turret-barrel::after {
    content:''; position:absolute;
    top:-4px; left:-3px; right:-3px; height:6px;
    background:var(--cyan);
    border-radius:2px;
    box-shadow:0 0 8px var(--cyan), 0 0 16px rgba(0,204,255,.5);
}

/* Turret wheels / tracks */
#turret-tracks {
    width:104px; height:14px;
    background:linear-gradient(180deg,#0a1520 0%,#060e18 100%);
    border:2px solid rgba(0,204,255,.18);
    border-top:none;
    box-shadow:0 0 0 2px rgba(0,0,0,.7);
    display:flex; justify-content:space-between; align-items:center;
    padding:0 4px;
}
.turret-wheel {
    width:10px; height:10px;
    border-radius:50%;
    background:radial-gradient(circle at 35% 30%, #1e3a52, #060e18);
    border:1px solid rgba(0,204,255,.25);
    box-shadow:0 0 4px rgba(0,204,255,.2);
}

/* Muzzle flash */
.muzzle-flash {
    position:fixed; z-index:81; pointer-events:none;
    width:22px; height:22px;
    background:radial-gradient(circle,#fff 0%,var(--cyan) 35%,transparent 70%);
    border-radius:50%;
    transform:translate(-50%,-50%);
    animation:muzzleFlash .18s ease-out forwards;
}
@keyframes muzzleFlash {
    0%   { transform:translate(-50%,-50%) scale(0.5); opacity:1; }
    50%  { transform:translate(-50%,-50%) scale(1.8); opacity:.9; }
    100% { transform:translate(-50%,-50%) scale(2.5); opacity:0; }
}

/* ── LASER BEAM ── */
.laser-beam {
    position:fixed; z-index:79; pointer-events:none;
    height:3px;
    background:linear-gradient(to right,
        rgba(0,204,255,.0) 0%, rgba(0,204,255,.9) 20%,
        #fff 50%, rgba(0,204,255,.9) 80%, rgba(0,204,255,.0) 100%);
    box-shadow:0 0 6px rgba(0,204,255,.8), 0 0 14px rgba(0,204,255,.4);
    transform-origin:0 50%;
    animation:laserFade .22s ease-out forwards;
}
.laser-beam.wrong {
    background:linear-gradient(to right,
        rgba(255,60,60,.0) 0%, rgba(255,60,60,.9) 20%,
        #fff 50%, rgba(255,60,60,.9) 80%, rgba(255,60,60,.0) 100%);
    box-shadow:0 0 6px rgba(255,60,60,.8), 0 0 14px rgba(255,60,60,.4);
}
@keyframes laserFade {
    0%  { opacity:1; height:4px; }
    60% { opacity:.7; }
    100%{ opacity:0; height:2px; }
}

/* ── PIXEL EXPLOSION SHARDS ── */
.px-shard {
    position:fixed; z-index:82; pointer-events:none;
    width:6px; height:6px;
    animation:shardFly var(--sd,.6s) ease-out forwards;
}
@keyframes shardFly {
    0%   { transform:translate(0,0) rotate(0deg);   opacity:1; }
    100% { transform:translate(var(--sx,30px),var(--sy,-40px)) rotate(360deg); opacity:0; }
}

/* ── ENHANCED IMPACT RING ── */
.impact-ring {
    position:fixed; z-index:81; pointer-events:none;
    width:40px; height:40px;
    transform:translate(-50%,-50%);
    border-radius:50%;
    animation:impactExpand .5s ease-out forwards;
}
@keyframes impactExpand {
    0%   { transform:translate(-50%,-50%) scale(0.3); opacity:1; }
    100% { transform:translate(-50%,-50%) scale(3.5); opacity:0; }
}

/* ── ENHANCED HIT FLASH ── */
#hit-flash {
    position:fixed; inset:0; z-index:88;
    pointer-events:none; opacity:0;
    transition:opacity .18s;
}
#hit-flash.on.correct { background:rgba(57,255,20,.12); opacity:1; }
#hit-flash.on.wrong   { background:rgba(255,60,60,.18); opacity:1; }

</style>
</head>
<body>

<!-- ── ELABORATE PIXEL ARCADE BACKGROUND ── -->
<div id="bg">
    <!-- Star parallax layers -->
    <div class="star-layer star-layer-1"></div>
    <div class="star-layer star-layer-2"></div>
    <div class="star-layer star-layer-3"></div>

    <!-- Nebula glows -->
    <div class="nebula nebula-1"></div>
    <div class="nebula nebula-2"></div>
    <div class="nebula nebula-3"></div>

    <!-- Pixel city skyline -->
    <div id="city-skyline">
        <svg class="city-svg" viewBox="0 0 1440 160" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="xMidYMax meet">
            <!-- Far buildings — dark silhouettes -->
            <rect x="0"    y="100" width="55"  height="60" fill="#050c18"/>
            <rect x="5"    y="80"  width="20"  height="20" fill="#050c18"/>
            <rect x="55"   y="110" width="40"  height="50" fill="#060e1c"/>
            <rect x="95"   y="90"  width="30"  height="70" fill="#050c18"/>
            <rect x="125"  y="120" width="50"  height="40" fill="#060e1c"/>
            <rect x="175"  y="95"  width="35"  height="65" fill="#050c18"/>
            <rect x="210"  y="75"  width="25"  height="85" fill="#060e1c"/>
            <rect x="235"  y="115" width="45"  height="45" fill="#050c18"/>
            <rect x="280"  y="85"  width="40"  height="75" fill="#060e1c"/>
            <rect x="320"  y="100" width="30"  height="60" fill="#050c18"/>
            <rect x="350"  y="70"  width="55"  height="90" fill="#060e1c"/>
            <rect x="405"  y="105" width="35"  height="55" fill="#050c18"/>
            <rect x="440"  y="88"  width="28"  height="72" fill="#060e1c"/>
            <rect x="468"  y="115" width="42"  height="45" fill="#050c18"/>
            <rect x="510"  y="78"  width="50"  height="82" fill="#060e1c"/>
            <rect x="560"  y="100" width="32"  height="60" fill="#050c18"/>
            <rect x="592"  y="65"  width="48"  height="95" fill="#060e1c"/>
            <rect x="640"  y="108" width="36"  height="52" fill="#050c18"/>
            <rect x="676"  y="82"  width="44"  height="78" fill="#060e1c"/>
            <rect x="720"  y="98"  width="38"  height="62" fill="#050c18"/>
            <rect x="758"  y="72"  width="52"  height="88" fill="#060e1c"/>
            <rect x="810"  y="112" width="30"  height="48" fill="#050c18"/>
            <rect x="840"  y="88"  width="42"  height="72" fill="#060e1c"/>
            <rect x="882"  y="100" width="35"  height="60" fill="#050c18"/>
            <rect x="917"  y="68"  width="50"  height="92" fill="#060e1c"/>
            <rect x="967"  y="105" width="38"  height="55" fill="#050c18"/>
            <rect x="1005" y="80"  width="45"  height="80" fill="#060e1c"/>
            <rect x="1050" y="115" width="32"  height="45" fill="#050c18"/>
            <rect x="1082" y="75"  width="55"  height="85" fill="#060e1c"/>
            <rect x="1137" y="102" width="40"  height="58" fill="#050c18"/>
            <rect x="1177" y="85"  width="28"  height="75" fill="#060e1c"/>
            <rect x="1205" y="118" width="48"  height="42" fill="#050c18"/>
            <rect x="1253" y="78"  width="52"  height="82" fill="#060e1c"/>
            <rect x="1305" y="100" width="35"  height="60" fill="#050c18"/>
            <rect x="1340" y="65"  width="50"  height="95" fill="#060e1c"/>
            <rect x="1390" y="110" width="50"  height="50" fill="#050c18"/>

            <!-- Building windows (neon glow) -->
            <rect x="10"   y="108" width="5"  height="4" fill="#ffaa00" opacity=".7"/>
            <rect x="18"   y="108" width="5"  height="4" fill="#00ccff" opacity=".5"/>
            <rect x="100"  y="98"  width="5"  height="4" fill="#ffaa00" opacity=".6"/>
            <rect x="108"  y="98"  width="5"  height="4" fill="#ffaa00" opacity=".4"/>
            <rect x="100"  y="108" width="5"  height="4" fill="#00ccff" opacity=".5"/>
            <rect x="215"  y="82"  width="5"  height="4" fill="#ff4466" opacity=".6"/>
            <rect x="215"  y="92"  width="5"  height="4" fill="#ffaa00" opacity=".5"/>
            <rect x="360"  y="78"  width="5"  height="4" fill="#00ccff" opacity=".7"/>
            <rect x="370"  y="78"  width="5"  height="4" fill="#00ccff" opacity=".5"/>
            <rect x="360"  y="88"  width="5"  height="4" fill="#ffaa00" opacity=".4"/>
            <rect x="514"  y="85"  width="6"  height="4" fill="#ff4466" opacity=".7"/>
            <rect x="522"  y="85"  width="6"  height="4" fill="#ffaa00" opacity=".5"/>
            <rect x="514"  y="95"  width="6"  height="4" fill="#00ccff" opacity=".5"/>
            <rect x="597"  y="72"  width="5"  height="4" fill="#00ccff" opacity=".8"/>
            <rect x="605"  y="72"  width="5"  height="4" fill="#00ccff" opacity=".6"/>
            <rect x="597"  y="82"  width="5"  height="4" fill="#ffaa00" opacity=".5"/>
            <rect x="762"  y="80"  width="5"  height="4" fill="#ff4466" opacity=".7"/>
            <rect x="770"  y="80"  width="5"  height="4" fill="#ffaa00" opacity=".5"/>
            <rect x="762"  y="90"  width="5"  height="4" fill="#00ccff" opacity=".5"/>
            <rect x="921"  y="75"  width="6"  height="4" fill="#00ccff" opacity=".7"/>
            <rect x="929"  y="75"  width="6"  height="4" fill="#ffaa00" opacity=".5"/>
            <rect x="1087" y="82"  width="5"  height="4" fill="#ff4466" opacity=".6"/>
            <rect x="1095" y="82"  width="5"  height="4" fill="#ffaa00" opacity=".5"/>
            <rect x="1344" y="72"  width="5"  height="4" fill="#00ccff" opacity=".8"/>
            <rect x="1352" y="72"  width="5"  height="4" fill="#00ccff" opacity=".5"/>

            <!-- Antenna/tower tops -->
            <line x1="215"  y1="75" x2="215" y2="62" stroke="#0a1e30" stroke-width="2"/>
            <rect x="212"   y="59" width="6"  height="4" fill="#ff4466" opacity=".9"/>
            <line x1="597"  y1="65" x2="597" y2="50" stroke="#0a1e30" stroke-width="2"/>
            <rect x="594"   y="47" width="6"  height="5" fill="#00ccff" opacity=".9"/>
            <line x1="1087" y1="75" x2="1087" y2="58" stroke="#0a1e30" stroke-width="2"/>
            <rect x="1084"  y="55" width="6"  height="5" fill="#ffaa00" opacity=".9"/>

            <!-- Ground / road line -->
            <rect x="0" y="155" width="1440" height="5" fill="#0a1a28"/>
            <rect x="0" y="158" width="1440" height="2" fill="rgba(0,204,255,.15)"/>
        </svg>
    </div>

    <!-- Neon floor grid -->
    <div class="neon-grid"><div class="neon-grid-inner"></div></div>
</div>

<!-- Corner bracket decorations -->
<div class="corner corner-tl"></div>
<div class="corner corner-tr"></div>
<div class="corner corner-bl"></div>
<div class="corner corner-br"></div>

<!-- Target arena -->
<div id="arena"></div>

<!-- ── PIXEL ART TURRET ── -->
<div id="turret-wrap">
    <div id="turret-body">
        <div id="turret-pivot" id="turret-pivot">
            <div id="turret-dome"></div>
            <div id="turret-barrel"></div>
        </div>
    </div>
    <div id="turret-tracks">
        <div class="turret-wheel"></div>
        <div class="turret-wheel"></div>
        <div class="turret-wheel"></div>
        <div class="turret-wheel"></div>
        <div class="turret-wheel"></div>
    </div>
</div>

<!-- Screen flash overlay -->
<div id="hit-flash"></div>

<!-- ── HUD ── -->
<div id="hud">
    <div class="hud-logo">[ TARGET PRACTICE ]</div>
    <div id="lives-display">
        <span class="life-icon" id="h0">🎯</span>
        <span class="life-icon" id="h1">🎯</span>
        <span class="life-icon" id="h2">🎯</span>
    </div>
    <div class="hud-spacer"></div>
    <div class="hud-chip c-cyan">
        <span class="val" id="hud-q">1/<?php echo $total_q; ?></span>
        <span class="lbl">STAGE</span>
    </div>
    <div class="hud-chip c-amber">
        <span class="val" id="hud-score">0</span>
        <span class="lbl">SCORE</span>
    </div>
    <div class="hud-chip c-green">
        <span class="val" id="hud-correct">0</span>
        <span class="lbl">HITS</span>
    </div>
</div>

<!-- ── QUESTION PANEL ── -->
<div id="question-panel">
    <div id="timer-track"><div id="timer-fill" style="width:100%"></div></div>
    <div id="q-card">
        <div class="q-eyebrow">▶ SHOOT THE CORRECT ANSWER</div>
        <div id="q-text">LOADING BRIEFING...</div>
    </div>
</div>

<!-- ── INSTRUCTIONS SCREEN ── -->
<div id="instructions-screen">
    <div class="inst-title">🎯 TARGET PRACTICE</div>
    <div class="inst-subtitle">— HOW TO PLAY —</div>
    <div class="inst-grid">
        <div class="inst-card">
            <span class="ic-icon">🎯</span>
            <div class="ic-text"><b>SHOOT</b> the falling target with the <b>correct answer</b> before it escapes!</div>
        </div>
        <div class="inst-card">
            <span class="ic-icon">💀</span>
            <div class="ic-text">You have <b>3 lives</b>. Wrong shot or missed target = <b>1 life lost</b>.</div>
        </div>
        <div class="inst-card">
            <span class="ic-icon">⏱️</span>
            <div class="ic-text"><b>15 seconds</b> per question. Shoot fast for <b>bonus points!</b></div>
        </div>
        <div class="inst-card">
            <span class="ic-icon">⭐</span>
            <div class="ic-text">The <b>correct target</b> falls slightly <b>slower</b> — use that to your advantage!</div>
        </div>
    </div>
    <div class="inst-tip">🖱️ Click / tap a target to shoot it &nbsp;|&nbsp; Green flash = HIT &nbsp;|&nbsp; Red flash = MISS</div>
    <button class="inst-start-btn" id="inst-start-btn">[ START MISSION ]</button>
</div>

<!-- ── GET READY / BETWEEN-Q OVERLAY ── -->
<div id="overlay">
    <div class="ov-big" id="ov-text">READY!</div>
    <div class="ov-sub" id="ov-sub">
        <?php echo htmlspecialchars($title); ?> · <?php echo $total_q; ?> questions
    </div>
</div>

<!-- ── RESULT SCREEN ── -->
<div id="result-screen">
    <div class="result-trophy">🏆</div>
    <div class="result-title" id="res-title">MISSION COMPLETE!</div>
    <div class="result-sub"  id="res-sub">Target practice quiz complete.</div>
    <div class="result-stats">
        <div class="rs"><span class="rv" id="res-score">0</span><span class="rl">Score</span></div>
        <div class="rs"><span class="rv" id="res-correct">0</span><span class="rl">Hits</span></div>
        <div class="rs"><span class="rv" id="res-acc">0%</span><span class="rl">Accuracy</span></div>
    </div>
    <div class="xp-badge" id="xp-badge">+0 XP EARNED!</div>
    <div class="result-btns">
        <button class="btn-r btn-ghost" onclick="retakeCurrentGame(this)">
            &#8635; RETAKE GAME
        </button>
        <button class="btn-r btn-ghost" onclick="window.location.href='quizzes.php'">
            &#9654; NEW QUEST
        </button>
        <button class="btn-r btn-red" onclick="window.location.href='studentdashboard.php'">
            &#8962; DASHBOARD
        </button>
    </div>
</div>

<script>
/* ═══════════════════════════════════════════════════════════
   DATA  (injected from PHP session — identical to bubble pop)
═══════════════════════════════════════════════════════════ */
const QUESTIONS  = <?php echo json_encode(array_values($mcq)); ?>;
const TOTAL_Q    = QUESTIONS.length;
const TIME_PER_Q = 15;
const MAX_LIVES  = 3;

/* ═══════════════════════════════════════════════════════════
   GAME STATE  (identical to bubble pop)
═══════════════════════════════════════════════════════════ */
let qIdx       = 0;
let score      = 0;
let correct    = 0;
let lives      = MAX_LIVES;
let answered   = false;
let timerSecs  = TIME_PER_Q;
let timerIv    = null;
let activeBubs = [];   // { el, isCorrect }
let sparkTimer = null;
let quizLog = [];
const completionToken = (window.crypto && crypto.randomUUID)
    ? crypto.randomUUID() : `${Date.now()}-${Math.random()}`;
/* ═══════════════════════════════════════════════════════════
   WEB AUDIO — 8-bit arcade sounds (no external files)
═══════════════════════════════════════════════════════════ */
let _AC = null;
function getAC() {
    if (!_AC) {
        try { _AC = new (window.AudioContext || window.webkitAudioContext)(); }
        catch(e) { return null; }
    }
    if (_AC.state === 'suspended') _AC.resume();
    return _AC;
}

function playSound(type) {
    const ac = getAC();
    if (!ac) return;
    const t = ac.currentTime;

    function osc(waveform, freq0, freq1, dur, vol) {
        const o = ac.createOscillator(), g = ac.createGain();
        o.connect(g); g.connect(ac.destination);
        o.type = waveform;
        o.frequency.setValueAtTime(freq0, t);
        if (freq1 !== freq0) o.frequency.exponentialRampToValueAtTime(freq1, t + dur);
        g.gain.setValueAtTime(vol, t);
        g.gain.exponentialRampToValueAtTime(0.001, t + dur);
        o.start(t); o.stop(t + dur + 0.01);
    }

    if (type === 'shoot') {
        // Gunshot: square + white-noise burst
        osc('square', 380, 80, 0.13, 0.22);
        const buf = ac.createBuffer(1, ac.sampleRate * 0.09, ac.sampleRate);
        const d   = buf.getChannelData(0);
        for (let i = 0; i < d.length; i++) d[i] = (Math.random() * 2 - 1) * 0.18;
        const src = ac.createBufferSource(), gn = ac.createGain();
        src.buffer = buf; src.connect(gn); gn.connect(ac.destination);
        gn.gain.setValueAtTime(1, t);
        gn.gain.exponentialRampToValueAtTime(0.001, t + 0.08);
        src.start(t);

    } else if (type === 'correct') {
        // Ascending 8-bit fanfare
        [330, 440, 550, 880].forEach((f, i) => {
            const o = ac.createOscillator(), g = ac.createGain();
            o.connect(g); g.connect(ac.destination);
            o.type = 'square'; o.frequency.value = f;
            const st = t + i * 0.075;
            g.gain.setValueAtTime(0, st);
            g.gain.linearRampToValueAtTime(0.16, st + 0.04);
            g.gain.exponentialRampToValueAtTime(0.001, st + 0.18);
            o.start(st); o.stop(st + 0.2);
        });

    } else if (type === 'wrong') {
        // Descending buzz
        osc('sawtooth', 260, 70, 0.32, 0.22);

    } else if (type === 'miss') {
        // Low warning thud
        osc('sawtooth', 160, 38, 0.46, 0.28);

    } else if (type === 'countdown') {
        osc('square', 660, 660, 0.14, 0.14);

    } else if (type === 'start') {
        // Launch jingle
        [220, 330, 440, 660].forEach((f, i) => {
            const o = ac.createOscillator(), g = ac.createGain();
            o.connect(g); g.connect(ac.destination);
            o.type = 'square'; o.frequency.value = f;
            const st = t + i * 0.09;
            g.gain.setValueAtTime(0, st);
            g.gain.linearRampToValueAtTime(0.18, st + 0.05);
            g.gain.exponentialRampToValueAtTime(0.001, st + 0.22);
            o.start(st); o.stop(st + 0.24);
        });
    }
}


/* ═══════════════════════════════════════════════════════════
   BACKGROUND MUSIC — 8-bit arcade chiptune (looping)
   Action-shooter vibe: punchy bass + melodic lead in C minor
═══════════════════════════════════════════════════════════ */
let _bgmPlaying = false;
let _bgmTimeoutId = null;

// Note frequencies (Hz) — C minor pentatonic + passing tones
const NOTE = {
    C3:131, D3:147, Eb3:156, F3:175, G3:196, Ab3:208, Bb3:233,
    C4:262, D4:294, Eb4:311, F4:349, G4:392, Ab4:415, Bb4:466,
    C5:523, D5:587, Eb5:622, F5:698, G5:784,
};

// [frequency, duration-in-beats]
const BGM_MELODY = [
    [NOTE.C5, .5],[NOTE.G4, .25],[NOTE.Ab4,.25],[NOTE.C5, .5],[NOTE.Bb4,.5],
    [NOTE.G4, .5],[NOTE.F4, .25],[NOTE.G4, .25],[NOTE.Ab4,.5],[NOTE.G4, .5],
    [NOTE.F4, .5],[NOTE.Eb4,.25],[NOTE.F4, .25],[NOTE.G4, .5],[NOTE.F4, .5],
    [NOTE.Eb4,.5],[NOTE.D4, .5], [NOTE.C4, 1.0],
    [NOTE.Eb5,.5],[NOTE.D5, .25],[NOTE.Eb5,.25],[NOTE.F5, .5],[NOTE.Eb5,.5],
    [NOTE.D5, .5],[NOTE.C5, .25],[NOTE.D5, .25],[NOTE.Eb5,.5],[NOTE.D5, .5],
    [NOTE.C5, .5],[NOTE.Bb4,.25],[NOTE.C5, .25],[NOTE.D5, .5],[NOTE.C5, .5],
    [NOTE.Bb4,.5],[NOTE.Ab4,.5], [NOTE.G4, 1.0],
];

const BGM_BASS = [
    [NOTE.C3, .5],[NOTE.C3, .5],[NOTE.G3, .5],[NOTE.G3, .5],
    [NOTE.Ab3,.5],[NOTE.Ab3,.5],[NOTE.Eb3,.5],[NOTE.Eb3,.5],
    [NOTE.F3, .5],[NOTE.F3, .5],[NOTE.C3, .5],[NOTE.C3, .5],
    [NOTE.G3, .5],[NOTE.G3, .5],[NOTE.D3, .5],[NOTE.D3, .5],
    [NOTE.C3, .5],[NOTE.C3, .5],[NOTE.G3, .5],[NOTE.G3, .5],
    [NOTE.Ab3,.5],[NOTE.Ab3,.5],[NOTE.Eb3,.5],[NOTE.Eb3,.5],
    [NOTE.F3, .5],[NOTE.F3, .5],[NOTE.C3, .5],[NOTE.C3, .5],
    [NOTE.G3, .5],[NOTE.G3, .5],[NOTE.D3, .5],[NOTE.D3, .5],
];

// Hi-hat rhythm: [1=hit, 0=rest] every 16th note at BPM=138
const BGM_HAT = [1,0,1,0, 1,0,1,0, 1,0,1,0, 1,0,1,0,
                  1,0,1,0, 1,0,1,0, 1,0,1,0, 1,0,1,0,
                  1,0,1,0, 1,0,1,0, 1,0,1,0, 1,0,1,0,
                  1,0,1,0, 1,0,1,0, 1,0,1,0, 1,0,1,0];

function _scheduleBGMLoop(ac, startTime) {
    if (!_bgmPlaying) return;
    const BPM  = 138;
    const BEAT = 60 / BPM;

    // — Lead melody (square wave) —
    let t = startTime;
    BGM_MELODY.forEach(([freq, beats]) => {
        const dur = beats * BEAT;
        const o   = ac.createOscillator();
        const g   = ac.createGain();
        o.connect(g); g.connect(ac.destination);
        o.type = 'square';
        o.frequency.value = freq;
        g.gain.setValueAtTime(0,     t);
        g.gain.linearRampToValueAtTime(0.055, t + 0.01);
        g.gain.setValueAtTime(0.055, t + dur * 0.72);
        g.gain.linearRampToValueAtTime(0,     t + dur);
        o.start(t); o.stop(t + dur + 0.01);
        t += dur;
    });
    const loopDur = t - startTime;

    // — Bass (triangle wave) —
    let bt = startTime;
    BGM_BASS.forEach(([freq, beats]) => {
        const dur = beats * BEAT;
        const o   = ac.createOscillator();
        const g   = ac.createGain();
        o.connect(g); g.connect(ac.destination);
        o.type = 'triangle';
        o.frequency.value = freq;
        g.gain.setValueAtTime(0,     bt);
        g.gain.linearRampToValueAtTime(0.07, bt + 0.01);
        g.gain.setValueAtTime(0.07,  bt + dur * 0.55);
        g.gain.linearRampToValueAtTime(0,    bt + dur);
        o.start(bt); o.stop(bt + dur + 0.01);
        bt += dur;
    });

    // — Hi-hat (noise bursts) —
    const hatStep = BEAT / 4;
    BGM_HAT.forEach((hit, idx) => {
        if (!hit) return;
        const ht  = startTime + idx * hatStep;
        const buf = ac.createBuffer(1, Math.floor(ac.sampleRate * 0.03), ac.sampleRate);
        const d   = buf.getChannelData(0);
        for (let k = 0; k < d.length; k++) d[k] = (Math.random() * 2 - 1);
        const src = ac.createBufferSource();
        const g   = ac.createGain();
        src.buffer = buf;
        src.connect(g); g.connect(ac.destination);
        g.gain.setValueAtTime(0.025, ht);
        g.gain.exponentialRampToValueAtTime(0.001, ht + 0.025);
        src.start(ht); src.stop(ht + 0.035);
    });

    // Schedule next loop 200ms before current one ends
    _bgmTimeoutId = setTimeout(() => {
        if (_bgmPlaying) _scheduleBGMLoop(ac, startTime + loopDur);
    }, (loopDur - 0.2) * 1000);
}

function startBGMusic() {
    if (_bgmPlaying) return;
    const ac = getAC();
    if (!ac) return;
    _bgmPlaying = true;
    _scheduleBGMLoop(ac, ac.currentTime + 0.08);
}

function stopBGMusic() {
    _bgmPlaying = false;
    clearTimeout(_bgmTimeoutId);
}

/* ═══════════════════════════════════════════════════════════
   DOM REFS
═══════════════════════════════════════════════════════════ */
const arena    = document.getElementById('arena');
const qText    = document.getElementById('q-text');
const overlay  = document.getElementById('overlay');
const ovText   = document.getElementById('ov-text');
const ovSub    = document.getElementById('ov-sub');
const timerFill= document.getElementById('timer-fill');
const hitFlash = document.getElementById('hit-flash');

function rand(min, max) { return Math.random() * (max - min) + min; }

/* ═══════════════════════════════════════════════════════════
   PIXEL TURRET — aim & fire
═══════════════════════════════════════════════════════════ */
const turretPivot  = document.getElementById('turret-pivot');
const turretBarrel = document.getElementById('turret-barrel');
const turretWrap   = document.getElementById('turret-wrap');

function getTurretMuzzlePos() {
    const rect   = turretWrap.getBoundingClientRect();
    const cx     = rect.left + rect.width / 2;
    const cy     = rect.top  + 18; // top of the dome
    return { cx, cy };
}

function aimTurret(tx, ty) {
    const { cx, cy } = getTurretMuzzlePos();
    const angle = Math.atan2(ty - cy, tx - cx) * 180 / Math.PI + 90;
    turretPivot.style.transform = `translateX(-50%) rotate(${angle}deg)`;
}

function fireLaser(tx, ty, isCorrect) {
    const { cx, cy } = getTurretMuzzlePos();
    const dx  = tx - cx, dy = ty - cy;
    const len = Math.hypot(dx, dy);
    const ang = Math.atan2(dy, dx) * 180 / Math.PI;

    // Laser beam
    const beam = document.createElement('div');
    beam.className = 'laser-beam' + (isCorrect ? '' : ' wrong');
    beam.style.cssText = `
        left:${cx}px; top:${cy}px;
        width:${len}px;
        transform:rotate(${ang}deg);
        transform-origin:0 50%;
    `;
    document.body.appendChild(beam);
    setTimeout(() => beam.remove(), 280);

    // Muzzle flash at barrel tip
    const muzzX = cx + Math.cos((ang) * Math.PI/180) * 28;
    const muzzY = cy + Math.sin((ang) * Math.PI/180) * 28;
    const flash = document.createElement('div');
    flash.className = 'muzzle-flash';
    flash.style.cssText = `left:${muzzX}px; top:${muzzY}px;`;
    document.body.appendChild(flash);
    setTimeout(() => flash.remove(), 220);

    // Pixel shards on target
    const colors = isCorrect
        ? ['#39ff14','#00ccff','#ffff00','#ffffff']
        : ['#ff3c3c','#ff8800','#ffffff'];
    for (let i = 0; i < 8; i++) {
        const s = document.createElement('div');
        s.className = 'px-shard';
        const a = (i / 8) * Math.PI * 2;
        const d = 35 + Math.random() * 50;
        s.style.cssText = `
            left:${tx}px; top:${ty}px;
            background:${colors[Math.floor(Math.random()*colors.length)]};
            --sx:${Math.cos(a)*d}px; --sy:${Math.sin(a)*d}px;
            --sd:${.35+Math.random()*.45}s;
        `;
        document.body.appendChild(s);
        setTimeout(() => s.remove(), 900);
    }
}


/* ═══════════════════════════════════════════════════════════
   HUD  (identical logic to bubble pop)
═══════════════════════════════════════════════════════════ */
function updateHUD() {
    document.getElementById('hud-q').innerText      = `${qIdx + 1}/${TOTAL_Q}`;
    document.getElementById('hud-score').innerText  = score;
    document.getElementById('hud-correct').innerText= correct;
    for (let i = 0; i < MAX_LIVES; i++) {
        document.getElementById(`h${i}`).className =
            'life-icon' + (i >= lives ? ' lost' : '');
    }
}

/* ═══════════════════════════════════════════════════════════
   AMBIENT PIXEL SPARKS  (replacing bg bubbles)
═══════════════════════════════════════════════════════════ */
function spawnSpark() {
    const el = document.createElement('div');
    el.className = 'px-spark';
    const sz  = rand(2, 7);
    const dur = rand(2.5, 7);
    const colors = ['#39ff14','#00ccff','#ffaa00','#ff3c3c','#ffffff','#dd77ff'];
    el.style.cssText = `
        width:${sz}px; height:${sz}px;
        left:${rand(0, 100)}vw; top:-8px;
        background:${colors[Math.floor(Math.random() * colors.length)]};
        opacity:${rand(0.3, 0.9)};
        --sp-dur:${dur}s;
    `;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), dur * 1000 + 600);
}
function startSparks() { spawnSpark(); sparkTimer = setInterval(spawnSpark, 550); }
function stopSparks()  { clearInterval(sparkTimer); }

/* ═══════════════════════════════════════════════════════════
   TIMER  (identical to bubble pop)
═══════════════════════════════════════════════════════════ */
function startTimer() {
    clearInterval(timerIv);
    timerSecs = TIME_PER_Q;
    renderTimer();
    timerIv = setInterval(() => {
        if (answered) return;
        timerSecs--;
        renderTimer();
        if (timerSecs <= 0) { clearInterval(timerIv); onTimeout(); }
    }, 1000);
}

function renderTimer() {
    const pct = (timerSecs / TIME_PER_Q) * 100;
    timerFill.style.width = pct + '%';
    timerFill.className   = pct < 20 ? 'danger' : pct < 40 ? 'warn' : '';
}

/* ═══════════════════════════════════════════════════════════
   BUILD OPTIONS  — fills in missing / incomplete choices
   using answers from other questions as distractors.
   This prevents "Wrong A / Wrong B / Wrong C" placeholders.
═══════════════════════════════════════════════════════════ */
function buildOpts(q) {
    const ans = (q.answer || '').trim().toLowerCase();

    // Collect all other questions' answers as distractor pool (de-duped)
    const pool = [];
    const seen = new Set([ans]);
    QUESTIONS.forEach(r => {
        // Use options array entries first (richer variety)
        if (Array.isArray(r.options)) {
            r.options.forEach(o => {
                const t = (o || '').trim();
                if (t && !seen.has(t.toLowerCase())) { seen.add(t.toLowerCase()); pool.push(t); }
            });
        }
        // Also add the question's own answer
        const ra = (r.answer || '').trim();
        if (ra && !seen.has(ra.toLowerCase())) { seen.add(ra.toLowerCase()); pool.push(ra); }
    });

    // Shuffle pool
    for (let i = pool.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [pool[i], pool[j]] = [pool[j], pool[i]];
    }

    let opts;
    if (Array.isArray(q.options) && q.options.length >= 4) {
        // Already has 4+ proper options — use as-is
        opts = q.options.slice(0, 4);
    } else if (Array.isArray(q.options) && q.options.length >= 2) {
        // Has some options — pad to 4 with distractors
        opts = [...q.options];
        for (const d of pool) {
            if (opts.length >= 4) break;
            if (!opts.some(o => o.trim().toLowerCase() === d.toLowerCase())) opts.push(d);
        }
    } else {
        // No options (fill-in-blank, etc.) — build from answer + pool
        opts = [q.answer || ''];
        for (const d of pool) {
            if (opts.length >= 4) break;
            opts.push(d);
        }
    }

    // Pad to exactly 4 if pool was too small
    let pad = 1;
    while (opts.length < 4) { opts.push(`Choice ${pad++}`); }

    // Shuffle the final 4 options
    for (let i = opts.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [opts[i], opts[j]] = [opts[j], opts[i]];
    }

    return opts;
}

/* ═══════════════════════════════════════════════════════════
   SPAWN TARGETS  (fall from TOP → BOTTOM  vs bubble pop's rise)
═══════════════════════════════════════════════════════════ */
function spawnTargets(q) {
    clearTargets();

    const opts    = buildOpts(q);   // smart 4-option builder — no more "Wrong A/B/C"
    const vw      = window.innerWidth;
    const vh      = window.innerHeight;
    const isMobile = vw < 600;

    opts.forEach((opt, i) => {
        setTimeout(() => {
            if (answered) return;

            // Correct: text-match only (index unreliable after buildOpts shuffle)
            const isCorrect = (opt && opt.trim() === (q.answer || '').trim());

            // Shape + font based on text length
            const textLen  = (opt || '').length;
            // Base circle diameter
            const baseSize = isMobile ? 100 : 125;
            // Height stays circular; width GROWS for long text (pill/oval shape)
            const height   = baseSize;
            const isLong   = textLen > 20;
            const isVLong  = textLen > 35;
            // Pill width: grows with text length, capped so 4 pills fit screen
            const maxW     = Math.floor((vw - 32) / opts.length) - 8;
            const pillW    = isLong
                ? Math.min(maxW, isMobile
                    ? Math.max(baseSize, textLen * 7 + 20)
                    : Math.max(baseSize, textLen * 9 + 24))
                : baseSize;
            // border-radius: 50% = circle; big value = pill for non-square
            const bRadius  = isLong ? '60px' : '50%';
            // Font: NEVER below 16px for VT323
            const fontSize = isVLong
                ? (isMobile ? '15px' : '17px')
                : isLong
                ? (isMobile ? '17px' : '20px')
                : (isMobile ? '19px' : '23px');

            // Horizontal lanes — evenly distributed (use pillW for positioning)
            const margin    = pillW / 2 + 10;
            const laneW     = (vw - margin * 2) / opts.length;
            const laneX     = margin + laneW * i + rand(-laneW * 0.15, laneW * 0.15);
            const xPos      = Math.max(pillW / 2, Math.min(vw - pillW / 2, laneX));

            // Every target follows the same 15-second timeline as the
            // question timer. Do not let any answer escape early.
            const fallDur   = TIME_PER_Q;
            const driftAmt  = rand(-20, 20);
            const wobbleAmt = rand(7, 18);
            const wobbleDur = rand(1.8, 3.5);

            const el = document.createElement('div');
            el.className = `target target-${i % 4}`;
            el.style.cssText = `
                width:${pillW}px; height:${height}px;
                border-radius:${bRadius};
                left:${xPos - pillW / 2}px;
                top:-${height + 52}px;
                --fall-dur:${fallDur}s;
                --fall-dist:${vh + height + 70}px;
                --drift-start:${-driftAmt}px;
                --drift-end:${driftAmt}px;
                --wobble-amt:${wobbleAmt}px;
                --wobble-dur:${wobbleDur}s;
            `;

            // Answer text — full text, no truncation, font always readable
            const txt = document.createElement('div');
            txt.className = 'target-text';
            txt.style.fontSize = fontSize;
            txt.textContent = (opt || '');
            el.appendChild(txt);

            el.addEventListener('click', ev => {
                aimTurret(ev.clientX, ev.clientY);
                fireLaser(ev.clientX, ev.clientY, isCorrect);
                onTargetShot(el, isCorrect, ev.clientX, ev.clientY);
            });
            el.addEventListener('animationend', ev => {
                if (ev.animationName === 'targetFall') onTargetEscape(el, isCorrect);
            });

            arena.appendChild(el);
            activeBubs.push({ el, isCorrect });

        }, 0);   // all choices start with the timer
    });
}

function clearTargets() {
    activeBubs.forEach(b => { if (b.el.parentNode) b.el.parentNode.removeChild(b.el); });
    activeBubs = [];
}

/* ═══════════════════════════════════════════════════════════
   SHOOT EVENTS  (maps to bubble pop's pop events)
═══════════════════════════════════════════════════════════ */
function onTargetShot(el, isCorrect, x, y) {
    if (answered) return;
    answered = true;
    clearInterval(timerIv);

    playSound('shoot');
    showImpactRing(x, y, isCorrect);
    showBang(x, y, isCorrect ? 'HIT!' : 'MISS!', isCorrect ? '#39ff14' : '#ff3c3c');

    // ── LOG ──
    const q = QUESTIONS[qIdx];
    const shotText = el.querySelector('.target-text')?.textContent || '';
    quizLog.push({
        q: q.question,
        type: 'multiple_choice',
        options: activeBubs.map(b => b.el.querySelector('.target-text')?.textContent || ''),
        correct_answer: q.answer,
        user_answer: shotText,
        is_correct: isCorrect
    });

    if (isCorrect) {
        el.classList.add('shot-correct');
        triggerFlash('correct');
        const pts = 500 + timerSecs * 30;
        score   += pts;
        correct++;
        playSound('correct');
        updateHUD();
        showScorePop(`+${pts}`, x, y);
        setTimeout(() => nextQuestion(true), 700);

    } else {
        el.classList.add('shot-wrong');
        triggerFlash('wrong');
        playSound('wrong');
        loseLife();
        activeBubs.forEach(b => {
            if (b.isCorrect && b.el !== el) {
                b.el.style.animation = 'none';
                b.el.style.filter    = 'brightness(2.5) drop-shadow(0 0 18px #39ff14)';
                b.el.style.transform = 'scale(1.18)';
            }
        });
        setTimeout(() => nextQuestion(false), 1100);
    }
}

function onTargetEscape(el, isCorrect) {
    if (answered || timerSecs <= 0 || !el.parentNode) return;
    if (!isCorrect) { el.remove(); return; }

    answered = true;
    clearInterval(timerIv);
    playSound('miss');
    triggerFlash('wrong');
    loseLife();

    // ── LOG ──
    const q = QUESTIONS[qIdx];
    quizLog.push({
        q: q.question,
        type: 'multiple_choice',
        options: [],
        correct_answer: q.answer,
        user_answer: null,
        is_correct: false
    });

    setTimeout(() => nextQuestion(false), 900);
}

function onTimeout() {
    if (answered) return;
    answered = true;
    playSound('miss');
    triggerFlash('wrong');
    loseLife();

    // ── LOG ──
    const q = QUESTIONS[qIdx];
    quizLog.push({
        q: q.question,
        type: 'multiple_choice',
        options: [],
        correct_answer: q.answer,
        user_answer: null,
        is_correct: false
    });

    setTimeout(() => nextQuestion(false), 900);
}
/* ═══════════════════════════════════════════════════════════
   LIFE LOSS  (identical to bubble pop)
═══════════════════════════════════════════════════════════ */
function loseLife() {
    if (lives > 0) lives--;
    updateHUD();
    if (lives === 0) {
        clearInterval(timerIv);
        clearTargets();
        setTimeout(() => finishGame(true), 600);
    }
}

function triggerFlash(type) {
    hitFlash.className = `on ${type}`;
    setTimeout(() => hitFlash.className = '', 260);
}

/* ═══════════════════════════════════════════════════════════
   VFX HELPERS
═══════════════════════════════════════════════════════════ */
function showImpactRing(x, y, isCorrect) {
    const el = document.createElement('div');
    el.className = 'impact-ring';
    const clr = isCorrect ? '#39ff14' : '#ff3c3c';
    el.style.cssText = `
        left:${x}px; top:${y}px;
        border:3px solid ${clr};
        box-shadow:0 0 14px ${clr};
    `;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 600);
}

function showBang(x, y, text, color) {
    const el = document.createElement('div');
    el.className = 'bang-txt';
    el.innerText = text;
    el.style.left  = `${Math.max(40, Math.min(window.innerWidth - 70, x))}px`;
    el.style.top   = `${Math.max(80, Math.min(window.innerHeight - 60, y))}px`;
    el.style.color = color;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 800);
}

function showScorePop(txt, x, y) {
    const el = document.createElement('div');
    el.className = 'score-pop';
    el.innerText  = txt;
    el.style.left = `${Math.max(50, Math.min(window.innerWidth - 90, x - 20))}px`;
    el.style.top  = `${Math.max(100, Math.min(window.innerHeight - 80, y - 30))}px`;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 1200);
}

/* ═══════════════════════════════════════════════════════════
   QUESTION FLOW  (identical to bubble pop)
═══════════════════════════════════════════════════════════ */
function showQuestion(idx) {
    if (idx >= TOTAL_Q) { finishGame(false); return; }
    const q  = QUESTIONS[idx];
    answered = false;
    qText.innerText = q.question || '(no question text)';
    updateHUD();
    spawnTargets(q);
    startTimer();
}

function nextQuestion(wasCorrect) {
    clearTargets();
    qIdx++;
    if (qIdx >= TOTAL_Q) { finishGame(false); return; }

    const label = wasCorrect ? '\u2713 HIT!' : '\u2717 MISSED!';
    const color = wasCorrect ? '#39ff14' : '#ff3c3c';
    const shade = wasCorrect ? '#040' : '#600';
    ovText.innerText   = label;
    ovText.style.color = color;
    ovText.style.textShadow = `0 0 28px ${color}, 3px 3px 0 ${shade}`;
    ovSub.innerText = `Stage ${qIdx + 1} of ${TOTAL_Q} incoming\u2026`;
    overlay.classList.add('show');

    setTimeout(() => {
        overlay.classList.remove('show');
        showQuestion(qIdx);
    }, 1100);
}

/* ═══════════════════════════════════════════════════════════
   FINISH  (identical to bubble pop — same XP save endpoint)
═══════════════════════════════════════════════════════════ */
function finishGame(ranOutOfLives) {
    clearInterval(timerIv);
    stopBGMusic();               // 🔇 stop BGM on game over / finish
    stopSparks();
    clearTargets();

    const acc   = TOTAL_Q > 0 ? Math.round((correct / TOTAL_Q) * 100) : 0;
    const emoji = acc >= 80 ? '\uD83C\uDFC6' : acc >= 50 ? '\uD83C\uDF96\uFE0F' : '\uD83D\uDCAB';
    const title = ranOutOfLives ? 'GAME OVER' : 'MISSION COMPLETE!';

    document.querySelector('.result-trophy').innerText   = emoji;
    document.getElementById('res-title').innerText      = title;
    document.getElementById('res-sub').innerText        =
        `${correct} of ${TOTAL_Q} targets hit correctly.`;
    document.getElementById('res-score').innerText      = score;
    document.getElementById('res-correct').innerText    = correct;
    document.getElementById('res-acc').innerText        = acc + '%';

    window.gameResultSavePromise = fetch('save_quiz_result.php', {
    method:  'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body:    `score=${score}&correct_answers=${correct}&total_questions=${TOTAL_Q}&quiz_log=${encodeURIComponent(JSON.stringify(quizLog))}&completion_token=${encodeURIComponent(completionToken)}`
})
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            const badge = document.getElementById('xp-badge');
            badge.innerText = d.xp_earned > 0 ? `+${d.xp_earned} XP EARNED!` : (d.xp_message || 'No quiz XP awarded.');
        }
    })
    .catch(() => {});

    setTimeout(() => {
        document.getElementById('result-screen').classList.add('show');
        if (acc >= 50) spawnConfetti();
    }, 400);
}

/* ═══════════════════════════════════════════════════════════
   PIXEL CONFETTI
═══════════════════════════════════════════════════════════ */
function spawnConfetti() {
    const colors = ['#ffaa00','#39ff14','#00ccff','#ff3c3c','#ffffff','#dd77ff'];
    for (let i = 0; i < 80; i++) {
        const el = document.createElement('div');
        el.className = 'conf';
        const sz = 4 + Math.floor(Math.random() * 6);
        el.style.cssText = `
            left:${Math.random() * 100}vw; top:-20px;
            width:${sz}px; height:${sz * (Math.random() > .5 ? 1 : 2)}px;
            background:${colors[Math.floor(Math.random() * colors.length)]};
            --cd:${1.4 + Math.random() * 1.6}s;
            --cl:${Math.random() * 0.8}s;
            border-radius:${Math.random() > .5 ? '0' : '50%'};
        `;
        document.body.appendChild(el);
        setTimeout(() => el.remove(), 3200);
    }
}

/* ═══════════════════════════════════════════════════════════
   INIT — show instructions first, then 3-2-1 countdown
═══════════════════════════════════════════════════════════ */
startSparks();

function startCountdown() {
    document.getElementById('instructions-screen').style.display = 'none';
    overlay.classList.add('show');

    let countdown = 3;
    ovText.innerText        = countdown;
    ovText.style.color      = '#ff3c3c';
    ovText.style.textShadow = '0 0 30px #ff3c3c, 3px 3px 0 #500';
    ovSub.innerText = `<?php echo htmlspecialchars($title); ?> \u00B7 ${TOTAL_Q} questions \u00B7 3 lives`;

    const cdIv = setInterval(() => {
        countdown--;
        if (countdown > 0) {
            playSound('countdown');
            ovText.innerText = countdown;
            ovText.style.animation = 'none';
            void ovText.offsetWidth;
            ovText.style.animation = 'ovFlash .38s step-end';
        } else {
            clearInterval(cdIv);
            playSound('start');
            startBGMusic();
            overlay.classList.remove('show');
            showQuestion(0);
        }
    }, 1000);
}

// Wire START MISSION button
document.getElementById('inst-start-btn').addEventListener('click', () => {
    getAC();   // unlock AudioContext on first user gesture
    startCountdown();
});
</script>
<script src="retake_current_game.js"></script>
</body>
</html>
