<?php
// game_bomb_toss.php
// "BOMB TOSS!" — Sequential MCQ pressure game.
// Options are revealed ONE AT A TIME. Player has 4 seconds per option to
// either LOCK IN (final answer) or TOSS (discard forever, see next option).
// No going back. Timeout = explosion = penalty.
// Uses $_SESSION['quiz_data']['questions'] — same contract as the other games.
// Saves XP via save_quiz_result.php.

session_start();
require_once __DIR__ . '/game_mode_access.php';
requireGameModeAccess('bomb_toss');

$questions = $_SESSION['quiz_data']['questions'] ?? [];
if (empty($questions)) {
    header("Location: quizzes.php?error=no_questions_found");
    exit();
}

// Only true 4-option MCQs work for this mode (needs a real elimination set)
$mcq = [];
foreach ($questions as $q) {
    if (!empty($q['options']) && count($q['options']) >= 4 && !empty($q['answer'])) {
        $q['options'] = array_slice(array_values($q['options']), 0, 4);
        $mcq[] = $q;
    }
}
if (count($mcq) < 3) {
    header("Location: quizzes.php?error=not_enough_questions");
    exit();
}

$title   = $_SESSION['quiz_data']['title'] ?? 'Bomb Toss Quest';
$total_q = count($mcq);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Bomb Toss! | PinnaQuest</title>
<link href="https://fonts.googleapis.com/css2?family=Bangers&family=Baloo+2:wght@500;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* ══════════════════════════════════════════════════════════════
   ROOT — Cartoon Comic Palette
══════════════════════════════════════════════════════════════ */
:root{
    --paper:#fff4d6; --ink:#1a1a1a;
    --red:#ff3b3b; --red-d:#c81f1f;
    --green:#38d45a; --green-d:#1e9e3c;
    --yellow:#ffd23f; --yellow-d:#e6ac00;
    --blue:#3fa9ff; --blue-d:#1c7fd6;
    --purple:#a855f7; --purple-d:#8b3fd6;
    --orange:#ff8c2b; --orange-d:#d4620a;
    --pink:#ff5fa0;
}
*{margin:0;padding:0;box-sizing:border-box;font-family:'Baloo 2',sans-serif;-webkit-tap-highlight-color:transparent;user-select:none;}
.comic{font-family:'Bangers',cursive;letter-spacing:1px;}

html,body{height:100%;overflow:hidden;background:var(--paper);color:var(--ink);}

/* ── COMIC CARTOON BACKGROUND ── */
#bg{
    position:fixed;inset:0;z-index:0;
    background-color:var(--paper);
    background-image:
        /* Halftone dots */
        radial-gradient(circle,rgba(0,0,0,.065) 2px,transparent 2.2px);
    background-size:18px 18px;
    overflow:hidden;
}
#bg::before{
    content:'';position:absolute;inset:0;pointer-events:none;
    background:
        radial-gradient(circle at 18% 14%,rgba(255,140,43,.32),transparent 42%),
        radial-gradient(circle at 84% 78%,rgba(63,169,255,.28),transparent 42%),
        radial-gradient(circle at 50% 50%,rgba(255,210,63,.12),transparent 60%);
}
/* Speed lines in corners */
#bg::after{
    content:'';position:absolute;inset:0;pointer-events:none;
    background:
        repeating-conic-gradient(from 135deg at 0% 0%,
            transparent 0deg, transparent 2.5deg,
            rgba(255,140,43,.06) 2.5deg, rgba(255,140,43,.06) 5deg)
            0 0 / 100% 100%,
        repeating-conic-gradient(from -45deg at 100% 100%,
            transparent 0deg, transparent 2.5deg,
            rgba(63,169,255,.06) 2.5deg, rgba(63,169,255,.06) 5deg)
            0 0 / 100% 100%;
}
/* Floating comic elements */
.bg-star{
    position:absolute;pointer-events:none;
    font-family:'Bangers',cursive;
    animation:bgFloat var(--bf-dur,6s) ease-in-out infinite alternate;
    opacity:.14;
}
@keyframes bgFloat{
    from{transform:translateY(0) rotate(var(--bf-r0,-5deg));}
    to  {transform:translateY(-18px) rotate(var(--bf-r1,5deg));}
}

/* Comic panel border used everywhere */
.ink-border{ border:4px solid var(--ink); box-shadow:6px 6px 0 var(--ink); }

/* ══════════════════════════════════════════════════════════════
   HUD
══════════════════════════════════════════════════════════════ */
#hud{
    position:fixed;top:0;left:0;right:0;z-index:80;
    display:flex;align-items:center;gap:10px;
    padding:10px 16px;
    background:linear-gradient(180deg,var(--yellow) 0%,#e6b800 100%);
    border-bottom:5px solid var(--ink);
    box-shadow:0 4px 0 rgba(0,0,0,.18);
}
.hud-logo{font-size:22px;color:var(--red-d);text-shadow:2px 2px 0 #fff,-1px -1px 0 rgba(0,0,0,.2);flex-shrink:0;
    letter-spacing:1px;}
.hud-spacer{flex:1;}
.hud-chip{
    background:#fff;border:3px solid var(--ink);border-radius:12px;
    padding:5px 12px;text-align:center;
    box-shadow:3px 3px 0 var(--ink),0 1px 0 rgba(255,255,255,.5) inset;
}
.hud-chip .v{font-size:18px;font-weight:800;}
.hud-chip .l{font-size:8px;font-weight:700;color:#666;text-transform:uppercase;letter-spacing:.5px;}
#lives-chip .v{color:var(--red-d);}
#score-chip .v{color:var(--blue-d);}
#q-counter-chip .v{color:var(--purple-d);}
#streak-chip{display:none;}
#streak-chip .v{color:var(--orange);}

/* ── GLOBAL QUESTION TIMER BAR ── */
#q-timer-bar-wrap{
    position:fixed;top:62px;left:0;right:0;z-index:78;
    height:8px;background:rgba(0,0,0,.1);
    border-bottom:2px solid rgba(0,0,0,.12);
}
#q-timer-fill{
    height:100%;width:100%;
    background:linear-gradient(90deg,var(--green-d),var(--green));
    transition:width 1s linear, background .3s;
    box-shadow:0 0 6px var(--green);
}
#q-timer-fill.warn  {background:linear-gradient(90deg,var(--orange-d),var(--orange));box-shadow:0 0 6px var(--orange);}
#q-timer-fill.danger{
    background:linear-gradient(90deg,var(--red-d),var(--red));
    box-shadow:0 0 8px var(--red);
    animation:qBarPanic .3s steps(2) infinite;
}
@keyframes qBarPanic{0%,100%{opacity:1;}50%{opacity:.5;}}

/* ══════════════════════════════════════════════════════════════
   INSTRUCTIONS OVERLAY
══════════════════════════════════════════════════════════════ */
#inst-screen{
    position:fixed;inset:0;z-index:400;
    background:rgba(20,10,0,.88);
    display:flex;align-items:center;justify-content:center;
    padding:20px;
}
.inst-card{
    background:var(--paper);border-radius:22px;
    max-width:620px;width:100%;padding:28px 30px;
    text-align:center;
    box-shadow:0 0 0 5px var(--ink),8px 8px 0 var(--ink);
}
.inst-title{font-size:50px;color:var(--red-d);text-shadow:4px 4px 0 var(--ink);margin-bottom:2px;line-height:1;}
.inst-sub{font-size:14px;color:#555;font-weight:700;margin-bottom:18px;}
.inst-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:18px;text-align:left;}
.inst-item{
    background:#fff;border:3px solid var(--ink);border-radius:14px;
    padding:12px 14px;box-shadow:3px 3px 0 var(--ink);
    font-size:12.5px;line-height:1.5;font-weight:600;
}
.inst-item b.tag{
    display:inline-block;font-family:'Bangers',cursive;font-size:14px;
    padding:1px 8px;border-radius:6px;margin-right:4px;color:#fff;
}
.tag-go{background:var(--green-d);}
.tag-toss{background:var(--blue-d);}
.tag-boom{background:var(--red-d);}
.tag-last{background:var(--purple);}
.inst-start-btn{
    font-family:'Bangers',cursive;font-size:26px;letter-spacing:3px;
    background:linear-gradient(180deg,var(--green) 0%,var(--green-d) 100%);
    color:#fff;border:4px solid var(--ink);
    padding:16px 52px;border-radius:16px;cursor:pointer;
    box-shadow:0 7px 0 var(--green-d), 0 7px 0 var(--ink);
    transition:.08s;text-shadow:2px 2px 0 rgba(0,0,0,.3);
}
.inst-start-btn:hover{filter:brightness(1.06);}
.inst-start-btn:active{transform:translateY(5px);box-shadow:0 2px 0 var(--green-d);}

/* ══════════════════════════════════════════════════════════════
   GAME STAGE
══════════════════════════════════════════════════════════════ */
#stage{
    position:fixed;inset:0;z-index:10;
    display:none;flex-direction:column;align-items:center;
    padding:84px 16px 10px;
}
#stage.active{display:flex;}

.q-progress{font-size:13px;font-weight:800;color:#7a5300;margin-bottom:6px;
    letter-spacing:.5px;text-transform:uppercase;}

/* Question speech-bubble */
#q-card{
    background:#fff;max-width:660px;width:100%;
    border-radius:20px;padding:18px 26px;text-align:center;
    margin-bottom:10px;position:relative;
    box-shadow:5px 5px 0 var(--ink), 0 0 0 4px var(--ink);
}
#q-card::after{
    content:'';position:absolute;bottom:-14px;left:50%;transform:translateX(-50%);
    border-left:14px solid transparent;border-right:14px solid transparent;
    border-top:14px solid var(--ink);
}
#q-card::before{
    content:'';position:absolute;bottom:-9px;left:50%;transform:translateX(-50%);
    border-left:10px solid transparent;border-right:10px solid transparent;
    border-top:10px solid #fff;z-index:1;
}
#q-text{font-size:18px;font-weight:800;line-height:1.45;color:#111;}

/* Discard trail (tossed options, tiny, crossed out) */
#discard-trail{display:flex;gap:6px;margin:12px 0 4px;flex-wrap:wrap;justify-content:center;max-width:640px;}
.discard-chip{
    font-size:11px;font-weight:800;color:#a33;background:#ffe1e1;
    border:2px solid var(--red-d);border-radius:20px;padding:4px 12px;
    text-decoration:line-through;opacity:.88;
    box-shadow:2px 2px 0 rgba(0,0,0,.15);
}

/* ── ARENA: character (left) + buttons (right) ── */
#arena{
    flex:1;width:100%;max-width:900px;
    display:flex;flex-direction:row;align-items:center;justify-content:center;
    gap:28px;position:relative;
}
#char-section{
    display:flex;flex-direction:column;align-items:center;
    flex-shrink:0;
}
#btn-section{
    display:flex;flex-direction:column;align-items:stretch;
    gap:14px;min-width:200px;max-width:240px;
    justify-content:center;
}

/* Fuse timer ring */
#fuse-wrap{position:relative;width:116px;height:116px;margin-bottom:4px;}
#fuse-svg{transform:rotate(-90deg);}
#fuse-track{fill:none;stroke:#00000022;stroke-width:9;}
#fuse-bar{fill:none;stroke:var(--red);stroke-width:9;stroke-linecap:round;transition:stroke-dashoffset 1s linear, stroke .2s;}
#fuse-num{
    position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
    font-family:'Bangers',cursive;font-size:44px;color:var(--red-d);
    text-shadow:2px 2px 0 rgba(0,0,0,.15);
}
#fuse-num.danger{animation:fusePulse .35s infinite;color:#b00;}
@keyframes fusePulse{0%,100%{transform:scale(1)}50%{transform:scale(1.22)}}

/* ── CHARACTER HOLD (slightly left-offset) ── */
#char-wrap{position:relative;width:520px;height:520px;margin-left:0px;}
#char-wrap svg{width:100%;height:100%;overflow:visible;}

/* Shake (explosion) */
#char-wrap.shake{animation:charShake .42s;}
@keyframes charShake{
    0%,100%{transform:translateX(0) rotate(0)}
    15%{transform:translateX(-12px) rotate(-5deg)}
    35%{transform:translateX(12px) rotate(5deg)}
    55%{transform:translateX(-8px) rotate(-3deg)}
    75%{transform:translateX(8px) rotate(3deg)}
}

/* Throw animation (body tilts, arm flings) */
#char-wrap.throwing{animation:charThrow .46s ease-out forwards;}
@keyframes charThrow{
    0%   {transform:rotate(0deg) translateX(0);}
    25%  {transform:rotate(-10deg) translateX(-5px);}
    55%  {transform:rotate(8deg) translateX(4px);}
    100% {transform:rotate(0deg) translateX(0);}
}
#char-wrap.throwing #throw-arm{animation:armFling .46s ease-out forwards;}
@keyframes armFling{
    0%   {transform:rotate(0deg);}
    35%  {transform:rotate(-140deg) translateY(-12px);}
    100% {transform:rotate(-100deg) translateY(-8px);}
}

/* Celebrate (lock-in correct) */
#char-wrap.celebrating{animation:charBounce .52s cubic-bezier(.34,1.56,.64,1);}
@keyframes charBounce{
    0%   {transform:translateY(0) scale(1);}
    35%  {transform:translateY(-22px) scale(1.06);}
    65%  {transform:translateY(-10px) scale(.98);}
    100% {transform:translateY(0) scale(1);}
}
#char-wrap.celebrating #celebrate-arm{animation:armVictory .52s cubic-bezier(.34,1.56,.64,1) forwards;}
@keyframes armVictory{
    0%   {transform:rotate(0deg);}
    40%  {transform:rotate(-130deg) translateY(-14px);}
    100% {transform:rotate(-110deg) translateY(-10px);}
}

/* Pop in (new option appears) */
#char-wrap.pop-in{animation:popIn .38s cubic-bezier(.34,1.56,.64,1);}
@keyframes popIn{from{transform:scale(0) rotate(-12deg);opacity:0;}to{transform:scale(1) rotate(0);opacity:1;}}

/* Flying bomb (spawned on toss) */
.flying-bomb{
    position:fixed;z-index:200;pointer-events:none;
    width:70px;height:70px;
    animation:bombArc .5s cubic-bezier(.17,.67,.55,1) forwards;
    font-size:52px;line-height:1;text-align:center;
}
@keyframes bombArc{
    0%   {transform:translate(0,0) rotate(0deg) scale(1);opacity:1;}
    50%  {transform:translate(110px,-100px) rotate(180deg) scale(1.1);}
    100% {transform:translate(200px,-30px) rotate(360deg) scale(.2);opacity:0;}
}

/* Option text — BIGGER + clear inside bomb */
#opt-text{
    position:absolute;
    left:73.8%;top:57%;
    transform:translate(-50%,-50%);
    width:130px;
    max-height:180px;
    text-align:center;font-weight:800;
    font-size:20px;   /* overridden dynamically by JS */
    color:#fff;line-height:1.3;pointer-events:none;
    word-break:break-word;overflow-wrap:break-word;
    hyphens:auto;
    display:flex;align-items:center;justify-content:center;
    text-shadow:
        2px  2px 0 rgba(0,0,0,.98),
        -2px -2px 0 rgba(0,0,0,.98),
        2px -2px 0 rgba(0,0,0,.98),
        -2px  2px 0 rgba(0,0,0,.98),
        0 0 18px rgba(0,0,0,.95);
    transition:opacity .15s;
}
/* Option label (A / B / C / D) */
#opt-label{
    position:absolute;
    left:73.8%;top:39%;
    transform:translate(-50%,-50%);
    width:40px;height:40px;
    display:flex;align-items:center;justify-content:center;
    background:var(--yellow);border:3px solid var(--ink);border-radius:50%;
    font-family:'Bangers',cursive;font-size:20px;color:var(--ink);
    box-shadow:2px 2px 0 var(--ink);
    pointer-events:none;
    z-index:5;
}

/* Explosion burst */
.boom-burst{
    position:fixed;z-index:500;pointer-events:none;
    font-family:'Bangers',cursive;font-size:68px;color:var(--red-d);
    text-shadow:4px 4px 0 #fff,5px 5px 0 #000;
    animation:boomPop .85s ease-out forwards;
    text-align:center;
}
@keyframes boomPop{
    0%{transform:translate(-50%,-50%) scale(.2) rotate(-12deg);opacity:0;}
    30%{transform:translate(-50%,-50%) scale(1.25) rotate(4deg);opacity:1;}
    100%{transform:translate(-50%,-50%) scale(1) rotate(0);opacity:0;}
}
.spark{
    position:fixed;z-index:499;pointer-events:none;width:8px;height:8px;border-radius:50%;
    animation:sparkOut .55s ease-out forwards;
}
@keyframes sparkOut{
    0%{transform:translate(0,0) scale(1);opacity:1;}
    100%{transform:translate(var(--sx),var(--sy)) scale(.2);opacity:0;}
}

/* Screen flash */
#flash{position:fixed;inset:0;z-index:490;pointer-events:none;opacity:0;transition:opacity .08s;}
#flash.boom{background:rgba(255,60,60,.35);}
#flash.win{background:rgba(56,212,90,.28);}
#flash.on{opacity:1;transition:none;}

/* ── ACTION BUTTONS (vertical stack on right) ── */
#action-row{display:none;} /* kept for JS compat, hidden */
.act-btn{
    font-family:'Bangers',cursive;font-size:28px;letter-spacing:2px;
    border:5px solid var(--ink);border-radius:18px;padding:22px 18px;cursor:pointer;
    transition:transform .08s,filter .08s;color:#fff;
    text-shadow:2px 2px 0 rgba(0,0,0,.3);
    position:relative;overflow:hidden;width:100%;
}
.act-btn:hover{filter:brightness(1.07);}
.act-btn:active{transform:translateY(5px);}
/* Shiny top edge */
.act-btn::before{
    content:'';position:absolute;top:0;left:8%;right:8%;
    height:4px;border-radius:0 0 4px 4px;
    background:rgba(255,255,255,.35);
}
#btn-lock{
    background:linear-gradient(180deg,#4cee6e 0%,var(--green) 45%,var(--green-d) 100%);
    box-shadow:0 7px 0 #0f6e28, 0 7px 0 var(--ink), 0 0 0 5px var(--ink);
}
#btn-lock:active{box-shadow:0 2px 0 #0f6e28,0 2px 0 var(--ink),0 0 0 5px var(--ink);}
#btn-toss{
    background:linear-gradient(180deg,#66c0ff 0%,var(--blue) 45%,var(--blue-d) 100%);
    box-shadow:0 7px 0 #0a5298, 0 7px 0 var(--ink), 0 0 0 5px var(--ink);
}
#btn-toss:active{box-shadow:0 2px 0 #0a5298,0 2px 0 var(--ink),0 0 0 5px var(--ink);}
#btn-toss.hidden{display:none;}
.last-tag{
    display:none;text-align:center;font-family:'Bangers',cursive;
    color:var(--purple);font-size:14px;letter-spacing:1px;
    text-shadow:1px 1px 0 rgba(0,0,0,.15);
    border:3px dashed var(--purple);border-radius:12px;padding:8px 10px;
    background:rgba(168,85,247,.08);
}
.last-tag.show{display:block;}

/* ══════════════════════════════════════════════════════════════
   BETWEEN-QUESTION OVERLAY
══════════════════════════════════════════════════════════════ */
#overlay{
    position:fixed;inset:0;z-index:150;
    background:rgba(20,10,0,.85);
    display:flex;flex-direction:column;align-items:center;justify-content:center;
    opacity:0;pointer-events:none;transition:opacity .2s;text-align:center;padding:20px;
}
#overlay.show{opacity:1;pointer-events:all;}
#ov-title{font-family:'Bangers',cursive;font-size:46px;text-shadow:3px 3px 0 #000;}
#ov-sub{font-size:14px;color:#ddd;margin-top:8px;font-weight:700;}

/* ══════════════════════════════════════════════════════════════
   RESULT SCREEN
══════════════════════════════════════════════════════════════ */
#result-screen{
    display:none;position:fixed;inset:0;z-index:300;
    background:linear-gradient(160deg,#fff4d6,#ffe1b0);
    flex-direction:column;align-items:center;justify-content:center;
    text-align:center;padding:30px;
}
#result-screen.show{display:flex;}
.res-emoji{font-size:70px;margin-bottom:8px;}
.res-title{font-size:44px;color:var(--red-d);text-shadow:2px 2px 0 #000;}
.res-sub{font-size:14px;color:#654;font-weight:700;margin:8px 0 18px;}
.res-stats{display:flex;gap:12px;flex-wrap:wrap;justify-content:center;margin-bottom:18px;}
.res-stat{
    background:#fff;border:3px solid var(--ink);border-radius:14px;
    padding:14px 22px;box-shadow:4px 4px 0 var(--ink);min-width:100px;
}
.res-stat .v{font-family:'Bangers',cursive;font-size:26px;color:var(--blue-d);}
.res-stat .l{font-size:9px;font-weight:800;color:#888;text-transform:uppercase;}
#xp-badge{
    font-family:'Bangers',cursive;font-size:16px;color:var(--green-d);
    background:#fff;border:3px solid var(--green-d);border-radius:20px;
    padding:8px 22px;margin-bottom:20px;
}
.res-btns{display:flex;gap:12px;flex-wrap:wrap;justify-content:center;}
.btn-r{
    font-family:'Bangers',cursive;font-size:15px;letter-spacing:1px;
    border:3px solid var(--ink);border-radius:14px;padding:12px 26px;cursor:pointer;
}
.btn-r-green{background:var(--green);color:#fff;box-shadow:0 5px 0 var(--green-d);}
.btn-r-ghost{background:#fff;color:#333;box-shadow:0 5px 0 #999;}

.confetti-bit{position:fixed;z-index:999;pointer-events:none;animation:confFall var(--cd) var(--cl) ease-in forwards;}
@keyframes confFall{0%{transform:translateY(-20px) rotate(0);opacity:1;}100%{transform:translateY(110vh) rotate(720deg);opacity:0;}}


/* ══════════════════════════════════════════════════════════════
   GAMIFIED GRAPHICS UPGRADE PACK — embers, fuse danger glow,
   blinking eyes, extra sweat, button ripple, shockwave, HUD pulse
══════════════════════════════════════════════════════════════ */

/* Ambient ember/spark dust drifting up from the background */
#embers{position:fixed;inset:0;z-index:1;pointer-events:none;overflow:hidden;}
.ember-bit{
    position:absolute;bottom:-10px;border-radius:50%;
    background:radial-gradient(circle,#fff3c2 0%,#ffb020 55%,transparent 75%);
    opacity:0;animation:emberRise var(--dur,7s) linear var(--del,0s) infinite;
    filter:drop-shadow(0 0 3px rgba(255,140,20,.7));
}
@keyframes emberRise{
    0%{transform:translateY(0) translateX(0);opacity:0;}
    10%{opacity:.8;}
    100%{transform:translateY(-105vh) translateX(var(--drift,15px));opacity:0;}
}

/* Blinking cartoon eyes (idle ambiance) */
#char-wrap svg circle[cx="124"][cy="68"],
#char-wrap svg circle[cx="166"][cy="68"]{
    animation:eyeBlink 4.6s ease-in-out infinite;
    transform-origin:center;
    transform-box:fill-box;
}
#char-wrap svg circle[cx="166"][cy="68"]{ animation-delay:.08s; }
@keyframes eyeBlink{
    0%,92%,100%{transform:scaleY(1);}
    95%{transform:scaleY(.12);}
}

/* Bomb danger pulse — synced via .fuse-warn / .fuse-danger on #char-wrap */
#char-wrap.fuse-warn #bomb-group circle:nth-child(2){
    animation:bombWarnPulse 1s ease-in-out infinite;
}
#char-wrap.fuse-danger #bomb-group circle:nth-child(2){
    animation:bombDangerPulse .4s ease-in-out infinite;
}
@keyframes bombWarnPulse{
    0%,100%{ filter:drop-shadow(0 0 0 rgba(255,140,20,0)); }
    50%    { filter:drop-shadow(0 0 10px rgba(255,140,20,.75)); }
}
@keyframes bombDangerPulse{
    0%,100%{ filter:drop-shadow(0 0 4px rgba(255,0,0,.4)); }
    50%    { filter:drop-shadow(0 0 18px rgba(255,0,0,.95)); }
}

/* Extra sweat drop that appears + drips faster as the fuse burns down */
#sweat-extra{
    opacity:0; transform:translateY(0) scale(.6);
    transition:opacity .2s;
}
#char-wrap.fuse-warn #sweat-extra{
    opacity:1; animation:sweatDrip 1.1s ease-in infinite;
}
#char-wrap.fuse-danger #sweat-extra{
    opacity:1; animation:sweatDrip .5s ease-in infinite;
}
@keyframes sweatDrip{
    0%  { transform:translateY(0) scale(.6); opacity:0; }
    15% { opacity:1; }
    100%{ transform:translateY(22px) scale(.9); opacity:0; }
}

/* Button ripple */
.act-btn{position:relative;overflow:hidden;}
.btn-ripple{
    position:absolute;border-radius:50%;background:rgba(255,255,255,.6);
    transform:scale(0);pointer-events:none;animation:rippleGrow .55s ease-out forwards;
}
@keyframes rippleGrow{ to { transform:scale(3.4); opacity:0; } }

/* Radial shockwave flash used on BOOM / big WIN */
.screen-shockwave{
    position:fixed;top:50%;left:50%;z-index:480;pointer-events:none;
    width:20px;height:20px;border-radius:50%;
    transform:translate(-50%,-50%);
    border:6px solid rgba(255,255,255,.85);
    animation:shockwaveGrow .55s ease-out forwards;
}
@keyframes shockwaveGrow{
    0%  { width:20px;height:20px;opacity:1; }
    100%{ width:1400px;height:1400px;opacity:0; }
}

/* HUD low-lives urgent pulse */
#lives-chip.low-lives{ animation:livesPulse .7s ease-in-out infinite; }
@keyframes livesPulse{
    0%,100%{ box-shadow:3px 3px 0 var(--ink),0 0 0 rgba(255,0,0,0); }
    50%    { box-shadow:3px 3px 0 var(--ink),0 0 14px rgba(255,0,0,.65); }
}
/* Streak chip fire glow */
#streak-chip.hot{ animation:streakGlow .9s ease-in-out infinite; }
@keyframes streakGlow{
    0%,100%{ box-shadow:3px 3px 0 var(--ink),0 0 0 rgba(255,140,20,0); }
    50%    { box-shadow:3px 3px 0 var(--ink),0 0 12px rgba(255,140,20,.7); }
}

/* Result screen — staggered stat pop-in + comic burst backdrop */
#result-screen::before{
    content:'';position:absolute;inset:0;pointer-events:none;
    background:repeating-conic-gradient(from 0deg at 50% 50%,
        rgba(255,178,32,.10) 0deg, transparent 8deg, transparent 16deg, rgba(255,178,32,.10) 24deg);
    animation:burstSpin 20s linear infinite;
}
@keyframes burstSpin{ to { transform:rotate(360deg); } }
.res-stat{ animation:statPop .4s cubic-bezier(.34,1.56,.64,1) both; }
.res-stat:nth-child(1){ animation-delay:.05s; }
.res-stat:nth-child(2){ animation-delay:.15s; }
.res-stat:nth-child(3){ animation-delay:.25s; }
@keyframes statPop{ from{ transform:scale(.5); opacity:0; } to{ transform:scale(1); opacity:1; } }

@media(max-width:640px){
    .inst-grid{grid-template-columns:1fr;}
    #arena{flex-direction:column;gap:12px;}
    #char-wrap{width:280px;height:295px;margin-left:0;}
    #opt-text{font-size:14px;width:132px;}
    #fuse-wrap{width:96px;height:96px;}
    #fuse-num{font-size:36px;}
    .act-btn{font-size:22px;padding:14px 10px;}
    #q-text{font-size:15px;}
    #btn-section{flex-direction:row;min-width:unset;max-width:unset;width:100%;}
}

/* ══════════════════════════════════════════════════════════════
   BOMB EXPLOSION ANIMATION — cartoon fire + smoke
══════════════════════════════════════════════════════════════ */
#explosion-wrap{
    position:fixed;inset:0;z-index:450;pointer-events:none;
    display:none;
}
#explosion-wrap.active{ display:block; }

/* ── FIREBALL LAYERS ── */
.exp-fire{
    position:absolute;
    border-radius:50%;
    transform:translate(-50%,-50%) scale(0);
    animation:fireBurst var(--ef-dur,.7s) var(--ef-delay,0s) cubic-bezier(.22,.61,.36,1) forwards;
}
@keyframes fireBurst{
    0%  {transform:translate(-50%,-50%) scale(0);   opacity:1;}
    35% {transform:translate(-50%,-50%) scale(1.1); opacity:1;}
    70% {transform:translate(-50%,-50%) scale(1.8); opacity:.7;}
    100%{transform:translate(-50%,-50%) scale(2.8); opacity:0;}
}

/* ── FIRE TONGUES (irregular blobs) ── */
.exp-tongue{
    position:absolute;
    border-radius:50% 50% 30% 40%;
    transform-origin:bottom center;
    transform:translate(-50%,-100%) scaleY(0);
    animation:tongueLick var(--et-dur,.55s) var(--et-delay,0s) ease-out forwards;
}
@keyframes tongueLick{
    0%  {transform:translate(-50%,-80%) scaleY(0) rotate(var(--et-rot,0deg));opacity:1;}
    40% {transform:translate(-50%,-120%) scaleY(1.2) rotate(calc(var(--et-rot,0deg) * .5));opacity:1;}
    100%{transform:translate(calc(-50% + var(--et-dx,0px)),-200%) scaleY(.4) rotate(0);opacity:0;}
}

/* ── SMOKE PUFFS ── */
.exp-smoke{
    position:absolute;
    border-radius:50%;
    background:radial-gradient(circle at 35% 30%,#666 0%,#333 50%,transparent 100%);
    transform:translate(-50%,-50%) scale(0);
    animation:smokePuff var(--es-dur,.9s) var(--es-delay,.1s) ease-out forwards;
}
@keyframes smokePuff{
    0%  {transform:translate(-50%,-50%) scale(0);                         opacity:.88;}
    25% {transform:translate(-50%,calc(-50% - 18px)) scale(.7);           opacity:.75;}
    55% {transform:translate(calc(-50% + var(--es-dx,0px)),calc(-50% - 50px)) scale(1.2); opacity:.5;}
    100%{transform:translate(calc(-50% + var(--es-dx,0px)),calc(-50% - 110px)) scale(1.8);opacity:0;}
}

/* ── SHOCKWAVE RING ── */
.exp-ring{
    position:absolute;
    border-radius:50%;
    border:8px solid rgba(255,160,0,.75);
    transform:translate(-50%,-50%) scale(0);
    animation:shockRing .55s ease-out forwards;
}
.exp-ring.ring2{
    border-color:rgba(255,80,0,.45);
    border-width:4px;
    animation-duration:.75s;
    animation-delay:.08s;
}
@keyframes shockRing{
    0%  {transform:translate(-50%,-50%) scale(0);  opacity:1;}
    100%{transform:translate(-50%,-50%) scale(5.5);opacity:0;}
}

/* ── EMBER SPARKS ── */
.exp-ember{
    position:absolute;
    border-radius:50%;
    animation:emberFly var(--em-dur,.6s) ease-out forwards;
}
@keyframes emberFly{
    0%  {transform:translate(0,0) scale(1);          opacity:1;}
    100%{transform:translate(var(--em-x),var(--em-y)) scale(.15);opacity:0;}
}

/* ── BOOM TEXT ── */
.exp-boom-txt{
    position:absolute;
    transform:translate(-50%,-50%) scale(0) rotate(-8deg);
    font-family:'Bangers',cursive;
    color:#fff;
    text-shadow:
        3px  3px 0 var(--red-d),
        -2px -2px 0 #000,
        5px  5px 0 #000,
        0 0 30px #ff8800;
    animation:boomTxtPop .5s .12s cubic-bezier(.34,1.56,.64,1) forwards,
              boomTxtFade .35s .55s ease-in forwards;
    white-space:nowrap;
}
@keyframes boomTxtPop{
    0%  {transform:translate(-50%,-50%) scale(0) rotate(-12deg);opacity:1;}
    100%{transform:translate(-50%,-50%) scale(1) rotate(4deg);  opacity:1;}
}
@keyframes boomTxtFade{
    0%  {opacity:1;}
    100%{opacity:0;}
}

/* ── SMOKE TRAIL from fuse ── */
.exp-fuse-smoke{
    position:absolute;
    border-radius:50%;
    background:rgba(40,40,40,.7);
    animation:fuseSmoke .6s ease-out forwards;
}
@keyframes fuseSmoke{
    0%  {transform:translate(-50%,-50%) scale(0);opacity:.8;}
    100%{transform:translate(-50%,calc(-50% - 40px)) scale(1.2);opacity:0;}
}

</style>
</head>
<body>

<div id="bg">
    <!-- Floating comic word accents -->
    <div class="bg-star" style="font-size:52px;top:8%;left:5%;color:var(--orange);--bf-dur:5s;--bf-r0:-8deg;--bf-r1:4deg;">💥</div>
    <div class="bg-star" style="font-size:44px;top:12%;right:6%;color:var(--blue);--bf-dur:7s;--bf-r0:5deg;--bf-r1:-6deg;">⭐</div>
    <div class="bg-star" style="font-size:38px;bottom:14%;left:4%;color:var(--red);--bf-dur:6.5s;--bf-r0:3deg;--bf-r1:-8deg;">💣</div>
    <div class="bg-star" style="font-size:46px;bottom:10%;right:5%;color:var(--green);--bf-dur:8s;--bf-r0:-4deg;--bf-r1:6deg;">✨</div>
    <div class="bg-star" style="font-size:30px;top:40%;left:2%;color:var(--purple);--bf-dur:5.5s;">🔥</div>
    <div class="bg-star" style="font-size:30px;top:35%;right:2%;color:var(--yellow-d);--bf-dur:6.8s;">⚡</div>
</div>
<div id="embers"></div>
<div id="q-timer-bar-wrap"><div id="q-timer-fill"></div></div>
<div id="flash"></div>
<div id="explosion-wrap"></div>

<!-- ── HUD ── -->
<div id="hud">
    <div class="hud-logo comic">💣 BOMB TOSS!</div>
    <div class="hud-spacer"></div>
    <div class="hud-chip" id="q-counter-chip"><div class="v" id="hud-q">1/<?php echo $total_q; ?></div><div class="l">Question</div></div>
    <div class="hud-chip" id="streak-chip"><div class="v" id="hud-streak">0🔥</div><div class="l">Streak</div></div>
    <div class="hud-chip" id="lives-chip"><div class="v" id="hud-lives">❤️❤️❤️</div><div class="l">Lives</div></div>
    <div class="hud-chip" id="score-chip"><div class="v" id="hud-score">0</div><div class="l">Score</div></div>
</div>

<!-- ── INSTRUCTIONS ── -->
<div id="inst-screen">
    <div class="inst-card ink-border">
        <div class="inst-title comic">💣 BOMB TOSS!</div>
        <div class="inst-sub">ONE CHOICE AT A TIME. NO TAKE-BACKS. DON'T LET IT BLOW UP.</div>
        <div class="inst-grid">
            <div class="inst-item"><b class="tag tag-go">LOCK IN</b> Think this option is <b>correct</b>? Slam Lock In before the fuse runs out!</div>
            <div class="inst-item"><b class="tag tag-toss">TOSS</b> Not it? Toss the option away forever and see the next one.</div>
            <div class="inst-item"><b class="tag tag-boom">BOOM!</b> Hesitate too long — <b>4 seconds</b> — and it explodes. Big penalty, no more chances on that question!</div>
            <div class="inst-item"><b class="tag tag-last">LAST ONE</b> Toss away 3 options and the 4th is forced — Lock In only!</div>
        </div>
        <button class="inst-start-btn" id="inst-start-btn">START! 💥</button>
    </div>
</div>

<!-- ── GET READY / RESULT-OF-QUESTION OVERLAY ── -->
<div id="overlay">
    <div id="ov-title" class="comic">READY?</div>
    <div id="ov-sub"><?php echo htmlspecialchars($title); ?> · <?php echo $total_q; ?> questions</div>
</div>

<!-- ── GAME STAGE ── -->
<div id="stage">
    <div class="q-progress" id="q-progress">QUESTION 1 / <?php echo $total_q; ?></div>
    <div id="q-card" class="ink-border"><div id="q-text">Loading...</div></div>
    <div id="discard-trail"></div>

    <div id="arena">
      <div id="char-section">
        <div id="fuse-wrap">
            <svg id="fuse-svg" width="116" height="116">
                <circle id="fuse-track" cx="58" cy="58" r="50"></circle>
                <circle id="fuse-bar" cx="58" cy="58" r="50" stroke-dasharray="314" stroke-dashoffset="0"></circle>
            </svg>
            <div id="fuse-num">4</div>
        </div>

        <div id="char-wrap">
            <svg viewBox="0 0 290 290" xmlns="http://www.w3.org/2000/svg" style="transform-box:fill-box;">
                <!-- ── SHADOW ── -->
                <ellipse cx="145" cy="285" rx="90" ry="8" fill="#00000018"/>

                <!-- ── LEGS ── -->
                <rect x="96"  y="195" width="30" height="52" rx="14" fill="#2255aa" stroke="#111" stroke-width="4"/>
                <rect x="164" y="195" width="30" height="52" rx="14" fill="#2255aa" stroke="#111" stroke-width="4"/>
                <!-- Shoes -->
                <ellipse cx="111" cy="248" rx="24" ry="12" fill="#111" stroke="#111" stroke-width="2"/>
                <ellipse cx="179" cy="248" rx="24" ry="12" fill="#111" stroke="#111" stroke-width="2"/>
                <!-- Shoe highlight -->
                <ellipse cx="106" cy="244" rx="10" ry="4" fill="#444"/>
                <ellipse cx="174" cy="244" rx="10" ry="4" fill="#444"/>

                <!-- ── BODY / SHIRT ── -->
                <ellipse cx="145" cy="148" rx="68" ry="54" fill="#ff9933" stroke="#111" stroke-width="5"/>
                <!-- Shirt details -->
                <ellipse cx="145" cy="136" rx="52" ry="40" fill="#ff8c20" opacity=".5"/>
                <!-- Belt -->
                <rect x="82" y="178" width="126" height="18" rx="5" fill="#111"/>
                <rect x="135" y="174" width="20" height="26" rx="4" fill="#ffd23f" stroke="#111" stroke-width="3"/>
                <rect x="139" y="182" width="12" height="10" rx="2" fill="#111" opacity=".5"/>

                <!-- ── LEFT ARM (non-throwing, idle) ── -->
                <g transform="rotate(-22,90,175)" transform-box="fill-box">
                    <rect x="60" y="140" width="28" height="62" rx="14" fill="#ffcc88" stroke="#111" stroke-width="4"/>
                    <!-- Hand -->
                    <circle cx="74" cy="204" r="16" fill="#ffcc88" stroke="#111" stroke-width="4"/>
                </g>

                <!-- ── RIGHT ARM (throw arm) + BOMB held in hand ── -->
                <g id="throw-arm" style="transform-origin:200px 150px;transform-box:fill-box;">
                <g id="celebrate-arm" style="transform-origin:200px 150px;transform-box:fill-box;">
                    <!-- Arm -->
                    <rect x="200" y="140" width="28" height="62" rx="14" fill="#ffcc88" stroke="#111" stroke-width="4"/>
                    <!-- Hand (grips bottom of bomb) -->
                    <circle cx="214" cy="204" r="16" fill="#ffcc88" stroke="#111" stroke-width="4"/>
                    <!-- ══ BOMB held in hand — center at (214,158) ══ -->
                    <g id="bomb-group" transform="translate(214,158)">
                        <!-- Outer glow halo -->
                        <circle cx="0" cy="0" r="58" fill="rgba(0,0,0,.10)"/>
                        <!-- Main bomb body -->
                        <circle cx="0" cy="0" r="52" fill="#1e1e1e" stroke="#000" stroke-width="6"/>
                        <!-- Glossy top-left highlight -->
                        <ellipse cx="-14" cy="-12" rx="18" ry="11" fill="#5a5a5a" opacity=".42"/>
                        <ellipse cx="-20" cy="-18" rx="7" ry="5" fill="#888" opacity=".28"/>
                        <!-- Danger stripe band -->
                        <clipPath id="bombClip"><circle cx="0" cy="0" r="52"/></clipPath>
                        <rect x="-56" y="22" width="112" height="14" fill="#ffcc00" opacity=".16" clip-path="url(#bombClip)"/>
                        <rect x="-56" y="36" width="112" height="10" fill="#ff4400" opacity=".10" clip-path="url(#bombClip)"/>
                        <!-- Inner ring text boundary cue -->
                        <circle cx="0" cy="0" r="44" fill="none" stroke="rgba(255,255,255,.06)" stroke-width="4"/>
                        <!-- Fuse rope (curvy, exits top-right) -->
                        <path d="M8 -50 Q26 -72 48 -62" stroke="#9b6b3a" stroke-width="8" fill="none" stroke-linecap="round"/>
                        <path d="M28 -64 Q42 -72 48 -64" stroke="#7a4a2a" stroke-width="6" fill="none" stroke-linecap="round" opacity=".5"/>
                        <!-- Fuse spark cluster -->
                        <circle id="bomb-spark" cx="48" cy="-62" r="9" fill="#ffdd33"/>
                        <circle cx="48" cy="-62" r="16" fill="#ff9900" opacity=".45"/>
                        <circle cx="48" cy="-62" r="22" fill="#ffcc00" opacity=".18"/>
                    </g>
                </g>
                </g>

                <!-- ── HEAD ── -->
                <circle cx="145" cy="72" r="62" fill="#ffd9a8" stroke="#111" stroke-width="5"/>
                <!-- Red cap -->
                <ellipse cx="145" cy="35" rx="56" ry="24" fill="#dd2222" stroke="#111" stroke-width="4"/>
                <rect x="90" y="32" width="110" height="20" fill="#dd2222" stroke="#111" stroke-width="3"/>
                <!-- Cap brim -->
                <rect x="82" y="48" width="126" height="10" rx="5" fill="#bb1111" stroke="#111" stroke-width="3"/>
                <!-- Cap star badge -->
                <circle cx="145" cy="40" r="10" fill="#ffd23f" stroke="#111" stroke-width="2"/>
                <text x="145" y="44" text-anchor="middle" font-size="10" fill="#111" font-family="sans-serif">★</text>

                <!-- Ears -->
                <circle cx="83"  cy="74" r="16" fill="#ffd9a8" stroke="#111" stroke-width="4"/>
                <circle cx="207" cy="74" r="16" fill="#ffd9a8" stroke="#111" stroke-width="4"/>
                <circle cx="83"  cy="74" r="8" fill="#ffb8a0"/>
                <circle cx="207" cy="74" r="8" fill="#ffb8a0"/>

                <!-- ── FACE ── -->
                <!-- Eyes (big cartoon) -->
                <circle cx="124" cy="68" r="14" fill="#fff" stroke="#111" stroke-width="3"/>
                <circle cx="166" cy="68" r="14" fill="#fff" stroke="#111" stroke-width="3"/>
                <!-- Pupils -->
                <circle cx="127" cy="70" r="7" fill="#111"/>
                <circle cx="169" cy="70" r="7" fill="#111"/>
                <!-- Eye shine -->
                <circle cx="130" cy="66" r="3" fill="#fff"/>
                <circle cx="172" cy="66" r="3" fill="#fff"/>

                <!-- Worried eyebrows -->
                <path d="M110 50 Q124 44 130 52" stroke="#111" stroke-width="5" fill="none" stroke-linecap="round"/>
                <path d="M180 50 Q166 44 160 52" stroke="#111" stroke-width="5" fill="none" stroke-linecap="round"/>

                <!-- Sweat drop (worried) -->
                <path d="M196 44 Q202 36 200 48" stroke="none" fill="#88ccff" opacity=".9"/>
                <ellipse cx="199" cy="48" rx="5" ry="7" fill="#88ccff" stroke="#6699cc" stroke-width="1.5"/>
                <ellipse id="sweat-extra" cx="118" cy="52" rx="4" ry="6" fill="#88ccff" stroke="#6699cc" stroke-width="1.5"/>

                <!-- Cheek blush -->
                <ellipse cx="104" cy="82" rx="16" ry="9" fill="#ffaaaa" opacity=".45"/>
                <ellipse cx="186" cy="82" rx="16" ry="9" fill="#ffaaaa" opacity=".45"/>

                <!-- Nose -->
                <ellipse cx="145" cy="80" rx="7" ry="5" fill="#ffb090" stroke="#cc8060" stroke-width="1.5"/>

                <!-- Mouth (nervous zigzag) -->
                <path id="char-mouth" d="M124 96 Q133 102 145 97 Q157 102 166 96"
                      stroke="#111" stroke-width="4" fill="none" stroke-linecap="round"/>

                <!-- bomb-group moved into throw-arm above -->
            </svg>
            <!-- Option label badge (A/B/C/D) -->
            <div id="opt-label">A</div>
            <!-- Option text inside bomb -->
            <div id="opt-text">Loading…</div>
        </div><!-- /char-wrap -->
      </div><!-- /char-section -->

      <div id="btn-section">
        <div class="last-tag comic" id="last-tag">⚠ LAST OPTION — FORCED LOCK IN! ⚠</div>
        <button class="act-btn" id="btn-toss" onclick="tossOption()">TOSS 🔄</button>
        <button class="act-btn" id="btn-lock" onclick="lockIn()">LOCK IN ✅</button>
      </div><!-- /btn-section -->

    </div><!-- /arena -->
    <!-- hidden legacy row for JS compat -->
    <div id="action-row" style="display:none;"></div>
</div><!-- /stage -->

<!-- ── RESULT SCREEN ── -->
<div id="result-screen">
    <div class="res-emoji" id="res-emoji">🏆</div>
    <div class="res-title comic" id="res-title">QUEST CLEARED!</div>
    <div class="res-sub" id="res-sub">You survived the bomb toss.</div>
    <div class="res-stats">
        <div class="res-stat"><div class="v" id="res-score">0</div><div class="l">Score</div></div>
        <div class="res-stat"><div class="v" id="res-correct">0</div><div class="l">Correct</div></div>
        <div class="res-stat"><div class="v" id="res-acc">0%</div><div class="l">Accuracy</div></div>
    </div>
    <div id="xp-badge" class="ink-border">+0 XP EARNED!</div>
    <div class="res-btns">
        <button class="btn-r btn-r-ghost" onclick="retakeCurrentGame(this)">↻ RETAKE GAME</button>
        <button class="btn-r btn-r-ghost" onclick="window.location.href='quizzes.php'">＋ NEW QUEST</button>
        <button class="btn-r btn-r-green" onclick="window.location.href='studentdashboard.php'">🏠 DASHBOARD</button>
    </div>
</div>


<!-- BGM audio element — place bgsoundbomb.mp3 in same folder as game -->
<audio id="bgm-audio" loop preload="auto">
    <source src="bgsoundbomb.mp3" type="audio/mpeg">
</audio>
<script>
/* ═══════════════════════════════════════════════════════════
   DATA
═══════════════════════════════════════════════════════════ */
const QUESTIONS = <?php echo json_encode(array_values($mcq)); ?>;
const TOTAL_Q   = QUESTIONS.length;
const TIME_PER_OPT = 5;     // seconds per option
const QUESTION_TIME = 10;   // global question timer
const MAX_LIVES = 3;

/* ═══════════════════════════════════════════════════════════
   STATE
═══════════════════════════════════════════════════════════ */
let qIdx = 0, score = 0, correct = 0, lives = MAX_LIVES, streak = 0;
let questionSecs = QUESTION_TIME;
let questionTimerIv = null;
let optList = [];       // shuffled {text, isCorrect} for current question
let optIdx = 0;          // which option is currently held
let discarded = [];      // texts already tossed
let fuseSecs = TIME_PER_OPT;
let fuseTimer = null;
let locked = false;
let quizLog = [];
const completionToken = (window.crypto && crypto.randomUUID)
    ? crypto.randomUUID() : `${Date.now()}-${Math.random()}`;

const FUSE_CIRC = 314; // 2*PI*50

/* ═══════════════════════════════════════════════════════════
   AUDIO — tiny 8-bit-ish cues
═══════════════════════════════════════════════════════════ */
let _AC=null;
function getAC(){ if(!_AC){ try{_AC=new (window.AudioContext||window.webkitAudioContext)();}catch(e){return null;} } if(_AC.state==='suspended') _AC.resume(); return _AC; }
function beep(f0,f1,dur,vol,type='square'){
    const ac=getAC(); if(!ac) return;
    const t=ac.currentTime;
    const o=ac.createOscillator(), g=ac.createGain();
    o.type=type; o.frequency.setValueAtTime(f0,t);
    if(f1!==f0) o.frequency.exponentialRampToValueAtTime(f1,t+dur);
    g.gain.setValueAtTime(vol,t); g.gain.exponentialRampToValueAtTime(0.001,t+dur);
    o.connect(g); g.connect(ac.destination); o.start(t); o.stop(t+dur+0.02);
}
function sndTick(){ beep(700,700,.05,.06); }
function sndTickDanger(){ beep(900,900,.06,.14); }
function sndToss(){
    const ac=getAC(); if(!ac) return;
    const t=ac.currentTime;

    // LAYER 1: Cartoon WHOOSH — noise swept through rising bandpass
    (()=>{
        const sr=ac.sampleRate;
        const buf=ac.createBuffer(1,Math.floor(sr*.38),sr);
        const d=buf.getChannelData(0);
        for(let i=0;i<d.length;i++) d[i]=(Math.random()*2-1);
        const s=ac.createBufferSource();
        const bp=ac.createBiquadFilter(); bp.type='bandpass'; bp.Q.value=1.8;
        bp.frequency.setValueAtTime(180,t);
        bp.frequency.exponentialRampToValueAtTime(2400,t+.32);
        const g=ac.createGain();
        s.buffer=buf; s.connect(bp); bp.connect(g); g.connect(ac.destination);
        g.gain.setValueAtTime(0,t);
        g.gain.linearRampToValueAtTime(.7,t+.04);
        g.gain.exponentialRampToValueAtTime(.001,t+.36);
        s.start(t); s.stop(t+.38);
    })();

    // LAYER 2: Cartoon ZIP — fast sine sweep low→high (classic throw zip)
    (()=>{
        const o=ac.createOscillator(), g=ac.createGain();
        o.type='sine';
        o.frequency.setValueAtTime(120,t);
        o.frequency.exponentialRampToValueAtTime(3200,t+.22);
        g.gain.setValueAtTime(.55,t);
        g.gain.exponentialRampToValueAtTime(.001,t+.24);
        o.connect(g); g.connect(ac.destination);
        o.start(t); o.stop(t+.25);
    })();

    // LAYER 3: Cartoon BOING — triangle sine that bounces (spring release)
    (()=>{
        const o=ac.createOscillator(), g=ac.createGain();
        o.type='triangle';
        o.frequency.setValueAtTime(900,t+.18);
        o.frequency.exponentialRampToValueAtTime(180,t+.55);
        g.gain.setValueAtTime(0,t+.18);
        g.gain.linearRampToValueAtTime(.45,t+.22);
        g.gain.exponentialRampToValueAtTime(.001,t+.55);
        o.connect(g); g.connect(ac.destination);
        o.start(t+.18); o.stop(t+.58);
    })();

    // LAYER 4: Percussive THWACK — short noise pop at the moment of release
    (()=>{
        const sr=ac.sampleRate;
        const buf=ac.createBuffer(1,Math.floor(sr*.06),sr);
        const d=buf.getChannelData(0);
        for(let i=0;i<d.length;i++) d[i]=(Math.random()*2-1);
        const s=ac.createBufferSource();
        const hp=ac.createBiquadFilter(); hp.type='highpass'; hp.frequency.value=1200;
        const g=ac.createGain();
        s.buffer=buf; s.connect(hp); hp.connect(g); g.connect(ac.destination);
        g.gain.setValueAtTime(.6,t); g.gain.exponentialRampToValueAtTime(.001,t+.055);
        s.start(t); s.stop(t+.06);
    })();
}
function sndLock(){ beep(220,380,.09,.12); beep(440,600,.12,.1); }
function sndCorrect(){ [523,659,784,1047].forEach((f,i)=>setTimeout(()=>beep(f,f,.16,.12),i*80)); }
function sndBoom(){
    const ac=getAC(); if(!ac) return;
    const t=ac.currentTime;
    // LAYER 1: Sub-bass MEGA WHUMP
    (()=>{
        const sr=ac.sampleRate;
        const buf=ac.createBuffer(1,Math.floor(sr*1.4),sr);
        const d=buf.getChannelData(0);
        for(let i=0;i<d.length;i++) d[i]=(Math.random()*2-1);
        const s=ac.createBufferSource();
        const lp=ac.createBiquadFilter(); lp.type='lowpass'; lp.frequency.value=100; lp.Q.value=1.5;
        const g=ac.createGain();
        s.buffer=buf; s.connect(lp); lp.connect(g); g.connect(ac.destination);
        g.gain.setValueAtTime(0,t);
        g.gain.linearRampToValueAtTime(1.15,t+.012);
        g.gain.exponentialRampToValueAtTime(.35,t+.18);
        g.gain.exponentialRampToValueAtTime(.001,t+1.3);
        s.start(t); s.stop(t+1.4);
    })();
    // LAYER 2: Wideband explosive CRACK
    (()=>{
        const sr=ac.sampleRate;
        const buf=ac.createBuffer(1,Math.floor(sr*.08),sr);
        const d=buf.getChannelData(0);
        for(let i=0;i<d.length;i++) d[i]=(Math.random()*2-1);
        const s=ac.createBufferSource();
        const g=ac.createGain();
        s.buffer=buf; s.connect(g); g.connect(ac.destination);
        g.gain.setValueAtTime(0,t);
        g.gain.linearRampToValueAtTime(1.7,t+.003);
        g.gain.exponentialRampToValueAtTime(.001,t+.075);
        s.start(t); s.stop(t+.08);
    })();
    // LAYER 3: Mid BANG punch
    (()=>{
        const sr=ac.sampleRate;
        const buf=ac.createBuffer(1,Math.floor(sr*.25),sr);
        const d=buf.getChannelData(0);
        for(let i=0;i<d.length;i++) d[i]=(Math.random()*2-1);
        const s=ac.createBufferSource();
        const bp=ac.createBiquadFilter(); bp.type='bandpass'; bp.frequency.value=480; bp.Q.value=0.4;
        const g=ac.createGain();
        s.buffer=buf; s.connect(bp); bp.connect(g); g.connect(ac.destination);
        g.gain.setValueAtTime(.95,t); g.gain.exponentialRampToValueAtTime(.001,t+.22);
        s.start(t); s.stop(t+.26);
    })();
    // LAYER 4: Deep sine thump (220→18 Hz sweep)
    (()=>{
        const o=ac.createOscillator(),g=ac.createGain();
        o.type='sine';
        o.frequency.setValueAtTime(220,t);
        o.frequency.exponentialRampToValueAtTime(18,t+.65);
        g.gain.setValueAtTime(0,t);
        g.gain.linearRampToValueAtTime(.95,t+.01);
        g.gain.exponentialRampToValueAtTime(.001,t+.65);
        o.connect(g); g.connect(ac.destination); o.start(t); o.stop(t+.68);
    })();
    // LAYER 5: Rumbling debris (long low tail)
    (()=>{
        const sr=ac.sampleRate;
        const buf=ac.createBuffer(1,Math.floor(sr*.9),sr);
        const d=buf.getChannelData(0);
        for(let i=0;i<d.length;i++) d[i]=(Math.random()*2-1);
        const s=ac.createBufferSource();
        const lp=ac.createBiquadFilter(); lp.type='lowpass'; lp.frequency.value=260;
        const g=ac.createGain();
        s.buffer=buf; s.connect(lp); lp.connect(g); g.connect(ac.destination);
        g.gain.setValueAtTime(0,t+.06);
        g.gain.linearRampToValueAtTime(.48,t+.14);
        g.gain.exponentialRampToValueAtTime(.001,t+.85);
        s.start(t); s.stop(t+.9);
    })();
    // LAYER 6: High sizzle crackle
    (()=>{
        const sr=ac.sampleRate;
        const buf=ac.createBuffer(1,Math.floor(sr*.5),sr);
        const d=buf.getChannelData(0);
        for(let i=0;i<d.length;i++) d[i]=(Math.random()*2-1);
        const s=ac.createBufferSource();
        const hp=ac.createBiquadFilter(); hp.type='highpass'; hp.frequency.value=2800;
        const g=ac.createGain();
        s.buffer=buf; s.connect(hp); hp.connect(g); g.connect(ac.destination);
        g.gain.setValueAtTime(.3,t+.015);
        g.gain.exponentialRampToValueAtTime(.001,t+.42);
        s.start(t); s.stop(t+.5);
    })();
    // LAYER 7: Low sawtooth reverb tail
    (()=>{
        const o=ac.createOscillator(),g=ac.createGain();
        o.type='sawtooth';
        o.frequency.setValueAtTime(55,t+.06);
        o.frequency.exponentialRampToValueAtTime(18,t+.55);
        g.gain.setValueAtTime(.22,t+.06); g.gain.exponentialRampToValueAtTime(.001,t+.55);
        o.connect(g); g.connect(ac.destination); o.start(t+.06); o.stop(t+.58);
    })();
}

/* ═══════════════════════════════════════════════════════════
   UTIL
═══════════════════════════════════════════════════════════ */

/* ── BGM CONTROL ── */
const bgmAudio = document.getElementById('bgm-audio');
function startBGM(){
    if(!bgmAudio) return;
    bgmAudio.volume = 0.35;
    bgmAudio.play().catch(()=>{});
}
function stopBGM(){
    if(!bgmAudio) return;
    bgmAudio.pause();
    bgmAudio.currentTime = 0;
}

/* ═══════════════════════════════════════════════════════════
   CARTOON BOMB EXPLOSION ANIMATION
═══════════════════════════════════════════════════════════ */
function triggerExplosion(){
    const charEl = document.getElementById('char-wrap');
    const rect   = charEl.getBoundingClientRect();
    // Bomb center: ~66% down, 50% across
    const cx = rect.left + rect.width * 0.50;
    const cy = rect.top  + rect.height * 0.65;

    const wrap = document.getElementById('explosion-wrap');
    wrap.innerHTML = '';
    wrap.classList.add('active');

    // ── Shockwave rings ──
    [0,1].forEach(i=>{
        const r = document.createElement('div');
        r.className = 'exp-ring' + (i===1?' ring2':'');
        r.style.cssText = `left:${cx}px;top:${cy}px;width:60px;height:60px;`;
        wrap.appendChild(r);
    });

    // ── Fireball layers: yellow core → orange → red ──
    const fires=[
        {w:240,h:240,grad:'radial-gradient(circle,#ffee88 0%,#ffcc00 28%,#ff8800 60%,transparent 100%)',dur:.75,delay:0},
        {w:180,h:180,grad:'radial-gradient(circle,#ffcc00 0%,#ff6600 45%,#cc2200 80%,transparent 100%)',dur:.7,delay:.04},
        {w:120,h:120,grad:'radial-gradient(circle,#ff9900 0%,#cc2200 55%,transparent 100%)',dur:.65,delay:.08},
    ];
    fires.forEach(f=>{
        const el=document.createElement('div');
        el.className='exp-fire';
        el.style.cssText=`left:${cx}px;top:${cy}px;width:${f.w}px;height:${f.h}px;
            background:${f.grad};--ef-dur:${f.dur}s;--ef-delay:${f.delay}s;`;
        wrap.appendChild(el);
    });

    // ── Fire tongues (8 directions) ──
    for(let i=0;i<8;i++){
        const angle = (i/8)*360;
        const rad   = angle*Math.PI/180;
        const dx    = Math.cos(rad)*30;
        const dy    = Math.sin(rad)*30;
        const sz    = 45+Math.random()*40;
        const el    = document.createElement('div');
        el.className='exp-tongue';
        const col = i%2===0 ? '#ff8800' : '#ff4400';
        el.style.cssText=`
            left:${cx+dx}px;top:${cy+dy}px;
            width:${sz}px;height:${sz*1.6}px;
            background:radial-gradient(circle at 50% 80%,${col},transparent 80%);
            --et-dur:${.4+Math.random()*.2}s;
            --et-delay:${Math.random()*.08}s;
            --et-rot:${(Math.random()-.5)*60}deg;
            --et-dx:${dx*1.2}px;
        `;
        wrap.appendChild(el);
    }

    // ── Smoke puffs (6 puffs) ──
    for(let i=0;i<6;i++){
        const ang  = (i/6)*Math.PI*2;
        const dist = 30+Math.random()*40;
        const dx   = Math.cos(ang)*dist;
        const sz   = 55+Math.random()*50;
        const el   = document.createElement('div');
        el.className='exp-smoke';
        el.style.cssText=`
            left:${cx+dx}px;top:${cy}px;
            width:${sz}px;height:${sz}px;
            --es-dur:${.85+Math.random()*.3}s;
            --es-delay:${.08+Math.random()*.18}s;
            --es-dx:${dx*.5}px;
        `;
        wrap.appendChild(el);
    }

    // ── Ember sparks (12 pieces) ──
    const emberCols=['#ff4400','#ff8800','#ffcc00','#ff6600','#fff'];
    for(let i=0;i<14;i++){
        const ang  = Math.random()*Math.PI*2;
        const dist = 60+Math.random()*100;
        const el   = document.createElement('div');
        el.className='exp-ember';
        const sz=4+Math.random()*8;
        el.style.cssText=`
            left:${cx}px;top:${cy}px;width:${sz}px;height:${sz}px;
            background:${emberCols[Math.floor(Math.random()*emberCols.length)]};
            --em-x:${Math.cos(ang)*dist}px;
            --em-y:${Math.sin(ang)*dist}px;
            --em-dur:${.45+Math.random()*.35}s;
            animation-delay:${Math.random()*.08}s;
        `;
        wrap.appendChild(el);
    }

    // ── BOOM! text ──
    const boom=document.createElement('div');
    boom.className='exp-boom-txt';
    boom.style.cssText=`left:${cx}px;top:${cy-40}px;font-size:clamp(52px,8vw,80px);`;
    boom.innerText='BOOM!';
    wrap.appendChild(boom);

    // ── Fuse smoke trail (small puffs above bomb) ──
    for(let i=0;i<4;i++){
        const el=document.createElement('div');
        el.className='exp-fuse-smoke';
        const sz=12+i*8;
        el.style.cssText=`
            left:${cx+28+i*6}px;top:${cy-60-i*10}px;
            width:${sz}px;height:${sz}px;
            animation-delay:${i*.05}s;
        `;
        wrap.appendChild(el);
    }

    // Cleanup
    setTimeout(()=>{
        wrap.classList.remove('active');
        wrap.innerHTML='';
    },1400);
}
function rand(min,max){ return Math.random()*(max-min)+min; }
function shuffle(arr){ const a=[...arr]; for(let i=a.length-1;i>0;i--){ const j=Math.floor(Math.random()*(i+1)); [a[i],a[j]]=[a[j],a[i]]; } return a; }
/* ═══════════════════════════════════════════════════════════
   GAMIFIED GRAPHICS UPGRADE PACK — helpers
═══════════════════════════════════════════════════════════ */
let _emberIv = null;
function startEmbers(){
    const host = document.getElementById('embers');
    if (!host || _emberIv) return;
    _emberIv = setInterval(()=>{
        const el = document.createElement('div');
        const size = 3 + Math.random()*5;
        el.className = 'ember-bit';
        el.style.cssText = `left:${Math.random()*100}vw;width:${size}px;height:${size}px;
            --dur:${6+Math.random()*4}s;--del:0s;--drift:${(Math.random()*70-35).toFixed(0)}px;`;
        host.appendChild(el);
        setTimeout(()=>el.remove(), 11000);
    }, 500);
}

function addRipple(e, btn){
    const rect = btn.getBoundingClientRect();
    const r = document.createElement('span');
    const d = Math.max(rect.width, rect.height);
    r.className = 'btn-ripple';
    r.style.width = r.style.height = d + 'px';
    r.style.left = ((e.clientX ?? rect.left+rect.width/2) - rect.left - d/2) + 'px';
    r.style.top  = ((e.clientY ?? rect.top+rect.height/2)  - rect.top  - d/2) + 'px';
    btn.appendChild(r);
    setTimeout(()=>r.remove(), 600);
}
document.getElementById('btn-lock')?.addEventListener('click', (e)=>addRipple(e, e.currentTarget));
document.getElementById('btn-toss')?.addEventListener('click', (e)=>addRipple(e, e.currentTarget));

function spawnShockwave(){
    const s = document.createElement('div');
    s.className = 'screen-shockwave';
    document.body.appendChild(s);
    setTimeout(()=>s.remove(), 600);
}

function updateHUD(){
    document.getElementById('hud-score').innerText = score;
    document.getElementById('hud-lives').innerText = '❤️'.repeat(lives) + '🖤'.repeat(MAX_LIVES-lives);
    document.getElementById('hud-q').innerText = `${qIdx+1}/${TOTAL_Q}`;
    document.getElementById('lives-chip').classList.toggle('low-lives', lives === 1);
    const sc = document.getElementById('streak-chip');
    if (streak >= 2) { sc.style.display='flex'; document.getElementById('hud-streak').innerText = streak+'🔥'; sc.classList.toggle('hot', streak >= 4); }
    else sc.style.display='none';
}

/* ═══════════════════════════════════════════════════════════
   QUESTION FLOW
═══════════════════════════════════════════════════════════ */
function startCountdown(){
    document.getElementById('inst-screen').style.display='none';
    const ov = document.getElementById('overlay');
    ov.classList.add('show');
    let c = 3;
    const t = document.getElementById('ov-title');
    const tick = ()=>{ t.innerText = c>0? c : 'GO!'; };
    tick();
    const iv = setInterval(()=>{
        c--;
        if(c>=0){ sndTick(); tick(); }
        if(c<0){ clearInterval(iv); ov.classList.remove('show'); getAC(); startBGM(); showQuestion(qIdx); }
    },700);
}

function showQuestion(idx){
    if (idx >= TOTAL_Q || lives <= 0) { finishGame(); return; }
    document.getElementById('stage').classList.add('active');
    const q = QUESTIONS[idx];
    document.getElementById('q-progress').innerText = `QUESTION ${idx+1} / ${TOTAL_Q}`;
    document.getElementById('q-text').innerText = q.question || '(no question text)';
    document.getElementById('discard-trail').innerHTML = '';

    optList = shuffle(q.options.map(o => ({ text:o, isCorrect: o.trim() === (q.answer||'').trim() })));
    optIdx = 0; discarded = []; locked = false;

    startQuestionTimer();
    renderOption(true);
}

function startQuestionTimer(){
    clearInterval(questionTimerIv);
    questionSecs = QUESTION_TIME;
    renderQBar();
    questionTimerIv = setInterval(()=>{
        questionSecs--;
        renderQBar();
        if(questionSecs<=0){ clearInterval(questionTimerIv); clearInterval(fuseTimer); explode(true); }
    },1000);
}
function renderQBar(){
    const pct = Math.max(0, questionSecs / QUESTION_TIME) * 100;
    const fill = document.getElementById('q-timer-fill');
    fill.style.width = pct + '%';
    fill.className = pct < 20 ? 'danger' : pct < 45 ? 'warn' : '';
}

function renderOption(popIn){
    const el = document.getElementById('char-wrap');
    el.classList.remove('toss-out','fuse-warn','fuse-danger');
    if (popIn) { el.classList.remove('pop-in'); void el.offsetWidth; el.classList.add('pop-in'); }

    const opt = optList[optIdx];
    const optEl = document.getElementById('opt-text');
    optEl.innerText = opt.text;
    // Dynamic font size: bigger so text is easy to read inside bomb
    const tlen = (opt.text||'').length;
    optEl.style.fontSize = tlen > 70 ? '14px'
                         : tlen > 55 ? '16px'
                         : tlen > 40 ? '18px'
                         : tlen > 25 ? '20px'
                         : tlen > 14 ? '22px'
                         : '26px';
    const labels = ['A','B','C','D'];
    document.getElementById('opt-label').innerText = labels[optIdx] || '?';

    const isLast = (optIdx === optList.length - 1);
    document.getElementById('btn-toss').classList.toggle('hidden', isLast);
    document.getElementById('last-tag').classList.toggle('show', isLast);

    startFuse();
}

function startFuse(){
    clearInterval(fuseTimer);
    fuseSecs = TIME_PER_OPT;
    renderFuse();
    fuseTimer = setInterval(()=>{
        fuseSecs -= 1;
        renderFuse();
        if (fuseSecs <= 2) sndTickDanger(); else if(fuseSecs <= 4) sndTick();
        if (fuseSecs <= 0) { clearInterval(fuseTimer); explode(true); }
    },1000);
}

function renderFuse(){
    const pct = Math.max(0, fuseSecs / TIME_PER_OPT);
    const bar = document.getElementById('fuse-bar');
    bar.style.strokeDashoffset = FUSE_CIRC * (1-pct);
    bar.style.stroke = pct <= 0.34 ? '#ff0000' : (pct <= 0.6 ? '#ff8c2b' : '#38d45a');
    const num = document.getElementById('fuse-num');
    num.innerText = Math.max(0, fuseSecs);
    num.classList.toggle('danger', fuseSecs <= 2);
    const cw = document.getElementById('char-wrap');
    cw.classList.toggle('fuse-danger', pct <= 0.34);
    cw.classList.toggle('fuse-warn', pct > 0.34 && pct <= 0.6);
}

/* ── LOCK IN ── */
function lockIn(){
    if (locked) return;
    locked = true;
    clearInterval(fuseTimer);
    sndLock();
    const opt = optList[optIdx];
    const q = QUESTIONS[qIdx];

    quizLog.push({
        q: q.question, type:'multiple_choice', options: q.options,
        correct_answer: q.answer, user_answer: opt.text, is_correct: !!opt.isCorrect
    });

    if (opt.isCorrect) {
        clearInterval(questionTimerIv);
        correct++; streak++;
        const pts = 400 + Math.round((fuseSecs/TIME_PER_OPT) * 300) + Math.min(300, streak*40);
        score += pts;
        updateHUD();
        flashScreen('win');
        sndCorrect();
        // Celebrate animation
        const cw = document.getElementById('char-wrap');
        cw.classList.remove('throwing','celebrating','pop-in');
        void cw.offsetWidth;
        cw.classList.add('celebrating');
        setTimeout(()=>cw.classList.remove('celebrating'), 600);
        // Change mouth to smile
        const mouth = document.getElementById('char-mouth');
        if(mouth) { mouth.setAttribute('d','M124 95 Q145 108 166 95'); }
        showBoomText('#38d45a', '✅ LOCK IN!', `+${pts} pts`);
        setTimeout(()=>{ if(mouth) mouth.setAttribute('d','M124 96 Q133 102 145 97 Q157 102 166 96'); }
            , 1000);
        setTimeout(()=>advance(), 1000);
    } else {
        streak = 0;
        explode(false);
    }
}

/* ── TOSS ── */
function tossOption(){
    if (locked) return;
    if (optIdx >= optList.length - 1) return;
    clearInterval(fuseTimer);
    sndToss();

    // Throwing animation on character
    const el = document.getElementById('char-wrap');
    el.classList.remove('throwing','celebrating','pop-in');
    void el.offsetWidth;
    el.classList.add('throwing');

    // Spawn flying bomb emoji (arc)
    const rect = el.getBoundingClientRect();
    const fb = document.createElement('div');
    fb.className = 'flying-bomb';
    fb.style.left = (rect.left + rect.width*.48) + 'px';
    fb.style.top  = (rect.top  + rect.height*.62) + 'px';
    fb.innerText = '💣';
    document.body.appendChild(fb);
    setTimeout(()=> fb.remove(), 550);

    // Briefly hide opt-text + label
    document.getElementById('opt-text').style.opacity = '0';
    document.getElementById('opt-label').style.opacity = '0';

    // Discard chip
    const chip = document.createElement('div');
    chip.className = 'discard-chip';
    chip.innerText = optList[optIdx].text.length > 28 ? optList[optIdx].text.slice(0,26)+'…' : optList[optIdx].text;
    document.getElementById('discard-trail').appendChild(chip);

    optIdx++;
    setTimeout(()=>{
        el.classList.remove('throwing');
        document.getElementById('opt-text').style.opacity = '1';
        document.getElementById('opt-label').style.opacity = '1';
        renderOption(true);
    }, 480);
}

/* ── EXPLODE (timeout OR wrong lock-in) ── */
function explode(wasTimeout){
    clearInterval(questionTimerIv);
    clearInterval(fuseTimer);
    lives = Math.max(0, lives-1);
    streak = 0;
    updateHUD();
    sndBoom();
    triggerExplosion();
    spawnShockwave();
    flashScreen('boom');
    document.getElementById('char-wrap').classList.add('shake');
    setTimeout(()=>document.getElementById('char-wrap').classList.remove('shake'), 420);
    spawnSparks();
    showBoomText('#ff3b3b', 'BOOM!', wasTimeout ? "TIME'S UP!" : 'WRONG PICK!');

    if (wasTimeout) {
        const q = QUESTIONS[qIdx];
        quizLog.push({
            q: q.question, type:'multiple_choice', options: q.options,
            correct_answer: q.answer, user_answer: null, is_correct: false
        });
    }

    if (lives <= 0) { setTimeout(()=>finishGame(), 1100); return; }
    setTimeout(()=>advance(), 1100);
}

function advance(){
    qIdx++;
    if (qIdx >= TOTAL_Q || lives <= 0) { finishGame(); return; }
    const ov = document.getElementById('overlay');
    document.getElementById('ov-title').innerText = 'NEXT UP!';
    document.getElementById('ov-sub').innerText = `Question ${qIdx+1} of ${TOTAL_Q}`;
    ov.classList.add('show');
    setTimeout(()=>{ ov.classList.remove('show'); showQuestion(qIdx); }, 900);
}

/* ═══════════════════════════════════════════════════════════
   FX HELPERS
═══════════════════════════════════════════════════════════ */
function flashScreen(type){
    const f = document.getElementById('flash');
    f.className = type + ' on';
    setTimeout(()=> f.className = type, 90);
    setTimeout(()=> f.className = '', 380);
}
function showBoomText(color, big, small){
    const wrap = document.getElementById('char-wrap');
    const rect = wrap.getBoundingClientRect();
    const x = rect.left + rect.width/2, y = rect.top + rect.height*0.25;
    const el = document.createElement('div');
    el.className = 'boom-burst';
    el.style.left = x+'px'; el.style.top = y+'px'; el.style.color = color;
    el.innerHTML = `${big}<div style="font-size:16px;color:#333;text-shadow:none;margin-top:2px;">${small}</div>`;
    document.body.appendChild(el);
    setTimeout(()=>el.remove(), 850);
}
function spawnSparks(){
    const wrap = document.getElementById('char-wrap');
    const rect = wrap.getBoundingClientRect();
    const cx = rect.left+rect.width/2, cy = rect.top+rect.height*0.55;
    const colors = ['#ff3b3b','#ffd23f','#ff8c2b','#fff'];
    for(let i=0;i<16;i++){
        const s = document.createElement('div');
        s.className='spark';
        const a = (i/16)*Math.PI*2, d = 40+Math.random()*60;
        s.style.left=cx+'px'; s.style.top=cy+'px';
        s.style.background = colors[i%colors.length];
        s.style.setProperty('--sx', Math.cos(a)*d+'px');
        s.style.setProperty('--sy', Math.sin(a)*d+'px');
        document.body.appendChild(s);
        setTimeout(()=>s.remove(), 600);
    }
}

/* ═══════════════════════════════════════════════════════════
   FINISH
═══════════════════════════════════════════════════════════ */
function finishGame(){
    stopBGM();
    clearInterval(questionTimerIv);
    clearInterval(fuseTimer);
    document.getElementById('stage').classList.remove('active');
    const acc = TOTAL_Q>0 ? Math.round((correct/TOTAL_Q)*100) : 0;
    const ranOut = lives <= 0;
    document.getElementById('res-emoji').innerText = ranOut ? '💥' : (acc>=80 ? '🏆' : (acc>=50 ? '🎖️' : '🧨'));
    document.getElementById('res-title').innerText = ranOut ? 'BOOM! GAME OVER' : 'QUEST CLEARED!';
    document.getElementById('res-sub').innerText = `${correct} of ${TOTAL_Q} correctly defused.`;
    document.getElementById('res-score').innerText = score;
    document.getElementById('res-correct').innerText = correct;
    document.getElementById('res-acc').innerText = acc+'%';

    window.gameResultSavePromise = fetch('save_quiz_result.php', {
        method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`score=${score}&correct_answers=${correct}&total_questions=${TOTAL_Q}&quiz_log=${encodeURIComponent(JSON.stringify(quizLog))}&completion_token=${encodeURIComponent(completionToken)}`
    }).then(r=>r.json()).then(d=>{
        if (d.success) {
            const badge = document.getElementById('xp-badge');
            badge.innerText = d.xp_earned > 0 ? `+${d.xp_earned} XP EARNED!` : (d.xp_message || 'No quiz XP awarded.');
        }
    }).catch(()=>{});

    setTimeout(()=>{
        document.getElementById('result-screen').classList.add('show');
        if (!ranOut && acc>=50) spawnConfetti();
    }, 350);
}
function spawnConfetti(){
    const colors=['#ffd23f','#38d45a','#3fa9ff','#ff3b3b','#a855f7'];
    for(let i=0;i<70;i++){
        const el=document.createElement('div');
        el.className='confetti-bit';
        const sz=5+Math.random()*7;
        el.style.cssText=`left:${Math.random()*100}vw;top:-20px;width:${sz}px;height:${sz*(Math.random()>.5?1:2)}px;background:${colors[Math.floor(Math.random()*colors.length)]};--cd:${1.4+Math.random()*1.6}s;--cl:${Math.random()*.8}s;border-radius:${Math.random()>.5?'50%':'2px'};`;
        document.body.appendChild(el);
        setTimeout(()=>el.remove(),3200);
    }
}

/* ═══════════════════════════════════════════════════════════
   INIT
═══════════════════════════════════════════════════════════ */
document.getElementById('inst-start-btn').addEventListener('click', ()=>{
    getAC();
    startEmbers();
    startCountdown();
});
updateHUD();
</script>
<script src="retake_current_game.js"></script>
</body>
</html>