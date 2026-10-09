<?php
// game_typo_gremlin.php  —  "The Typo Gremlin: Whack-a-Mole Keyboard"
// A fill-in-the-blank typing game. The moment the player nails the FIRST
// correct letter of the answer, a cartoon Gremlin wakes up and starts
// physically swapping adjacent keys on the on-screen keyboard — and those
// swaps are REAL: pressing a swapped key actually types the wrong letter.
// The player can click/tap the Gremlin to stun it for 3s and restore the
// keyboard, or just adapt to the chaos on the fly.
// Only makes sense for fill_blank quizzes — gated in pre_quiz_summary.php.
// Saves XP via save_quiz_result.php (same system as every other game mode).

session_start();
require_once __DIR__ . '/game_mode_access.php';
requireGameModeAccess('typo_gremlin');

$questions = $_SESSION['quiz_data']['questions'] ?? [];
if (empty($questions)) {
    header("Location: quizzes.php?error=no_questions_found");
    exit();
}

// Include every valid fill-blank answer. The keyboard is scrollable and the
// game should not become unavailable just because an answer is longer.
$items = [];
foreach ($questions as $q) {
    $qtext = $q['question'] ?? '';
    $isFillBlank = (($q['type'] ?? '') === 'fill_blank') || str_contains($qtext, '____');
    if (!$isFillBlank || !str_contains($qtext, '____')) continue;

    $answer = trim($q['answer'] ?? '');
    if (!$answer) continue;

    $items[] = ['question' => $qtext, 'answer' => $answer];
}

if (count($items) < 2) {
    header("Location: quizzes.php?error=not_enough_questions");
    exit();
}

$title       = $_SESSION['quiz_data']['title'] ?? 'The Typo Gremlin';
$total_items = count($items);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>The Typo Gremlin | PinnaQuest</title>
<link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* ══════════════════════════════════════════════════════════════
   DESIGN TOKENS — mischievous comic-goblin workshop
══════════════════════════════════════════════════════════════ */
:root {
    --bg-deep:   #1b1030;
    --bg-mid:    #2a1a45;
    --panel:     #241536;
    --panel-line:rgba(163,230,53,.22);

    --gremlin:   #7fd93b;
    --gremlin-d: #4f9020;
    --purple:    #a855f7;
    --gold:      #fbbf24;
    --danger:    #ef4444;
    --blue:      #38bdf8;

    --key-bg:    #3a2a54;
    --key-bg-h:  #4c3870;
    --key-swap:  #ef4444;
    --key-text:  #f4f1fb;

    --font-head: 'Fredoka', sans-serif;
    --font-mono: 'Space Mono', monospace;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }

html, body {
    width: 100%; min-height: 100vh;
    background: var(--bg-deep);
    color: #f4f1fb;
    font-family: var(--font-head);
    overflow-x: hidden;
    user-select: none;
}

/* ══════════════════════════════════════════════════════════════
   AMBIENT BACKGROUND — comic halftone workshop
══════════════════════════════════════════════════════════════ */
#bg {
    position: fixed; inset: 0; z-index: 0; pointer-events: none;
    background:
        radial-gradient(circle at 15% 10%, rgba(127,217,59,.08) 0%, transparent 45%),
        radial-gradient(circle at 85% 85%, rgba(168,85,247,.12) 0%, transparent 50%),
        linear-gradient(160deg, var(--bg-mid), var(--bg-deep));
}
#bg::after {
    content: '';
    position: absolute; inset: 0;
    background-image: radial-gradient(circle, rgba(255,255,255,.05) 1.4px, transparent 1.6px);
    background-size: 20px 20px;
}

/* ══════════════════════════════════════════════════════════════
   HUD
══════════════════════════════════════════════════════════════ */
#hud {
    position: fixed; top: 0; left: 0; right: 0; z-index: 90;
    background: rgba(20,10,35,.94); border-bottom: 2px solid var(--panel-line);
    padding: 10px 18px; display: flex; align-items: center; gap: 12px;
    backdrop-filter: blur(6px);
}
.hud-logo { font-weight: 700; font-size: 15px; color: var(--gremlin); letter-spacing: .5px; white-space: nowrap; }
.hud-logo span { display: block; font-size: 9px; color: #b3a4d1; font-weight: 500; }
.hud-spacer { flex: 1; }
.hud-lives { display: flex; gap: 5px; }
.life-key { font-size: 18px; filter: drop-shadow(0 0 4px rgba(127,217,59,.4)); transition: .3s; }
.life-key.lost { filter: grayscale(1) opacity(.2); transform: scale(.7) rotate(-8deg); }
.hud-chip { background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.1); border-radius: 10px; padding: 5px 12px; text-align: center; min-width: 52px; }
.hud-chip .v { font-weight: 700; font-size: 14px; color: #f4f1fb; display: block; }
.hud-chip .l { font-size: 8px; letter-spacing: 1px; color: #b3a4d1; text-transform: uppercase; }

#timer-track { position: fixed; top: 46px; left: 0; right: 0; height: 5px; background: rgba(255,255,255,.06); z-index: 89; }
#timer-fill { height: 100%; background: linear-gradient(90deg, var(--gremlin), #a3e635); transition: width 1s linear, background .3s; }
#timer-fill.warn   { background: linear-gradient(90deg, var(--gold), #f59e0b); }
#timer-fill.danger { background: linear-gradient(90deg, #b91c1c, var(--danger)); animation: panic .3s infinite; }
@keyframes panic { 0%,100%{opacity:1;} 50%{opacity:.5;} }

/* ══════════════════════════════════════════════════════════════
   STAGE
══════════════════════════════════════════════════════════════ */
#stage {
    position: relative; z-index: 5;
    max-width: 720px; margin: 0 auto;
    padding: 78px 16px 40px;
    display: flex; flex-direction: column; align-items: center;
}
.progress-label { font-size: 11px; font-weight: 700; color: #b3a4d1; letter-spacing: 2px; text-transform: uppercase; margin-bottom: 10px; }

.sentence-card {
    background: var(--panel); border: 2px solid var(--panel-line); border-radius: 20px;
    padding: 24px 28px; width: 100%; margin-bottom: 16px;
    box-shadow: 0 12px 30px rgba(0,0,0,.4);
}
.sentence-text { font-size: 1.3rem; font-weight: 600; line-height: 1.6; color: #f4f1fb; text-align: center; }
.blank-marker { display: inline-block; min-width: 70px; border-bottom: 3px solid var(--gremlin); margin: 0 4px; }

/* Typed readout */
.readout-wrap {
    width: 100%; background: #14091f; border: 2px solid var(--panel-line); border-radius: 12px;
    padding: 14px 18px; margin-bottom: 14px; min-height: 46px;
    display: flex; align-items: center; justify-content: center;
    font-family: var(--font-mono); font-size: 1.4rem; font-weight: 700; letter-spacing: 3px;
    color: var(--gremlin);
}
.readout-cursor { display: inline-block; width: 2px; height: 22px; background: var(--gremlin); margin-left: 3px; animation: blink .8s steps(1) infinite; }
@keyframes blink { 0%,100%{opacity:1;} 50%{opacity:0;} }

.gremlin-banner {
    display: flex; align-items: center; gap: 8px; font-size: 12px; font-weight: 700;
    color: var(--danger); margin-bottom: 10px; opacity: 0; transition: opacity .3s;
}
.gremlin-banner.active { opacity: 1; }
.gremlin-banner .dot { width: 7px; height: 7px; border-radius: 50%; background: var(--danger); animation: ghostBlink .5s infinite; }
@keyframes ghostBlink { 0%,100%{opacity:1;} 50%{opacity:.2;} }
.gremlin-banner.stunned { color: var(--blue); }
.gremlin-banner.stunned .dot { background: var(--blue); }

/* ══════════════════════════════════════════════════════════════
   ON-SCREEN KEYBOARD + GREMLIN
══════════════════════════════════════════════════════════════ */
#keyboard-zone {
    position: relative;
    width: 100%; max-width: 720px;
    background: rgba(255,255,255,.03); border: 2px solid var(--panel-line); border-radius: 20px;
    padding: 30px 18px 20px;
    margin-bottom: 16px;
}
.kb-row { display: flex; justify-content: center; gap: 8px; margin-bottom: 8px; }
.kb-row.row2 { margin-left: 24px; }
.kb-row.row3 { margin-left: 48px; }
.kb-key {
    width: 46px; height: 50px; border-radius: 10px;
    background: var(--key-bg); border: 1.5px solid rgba(255,255,255,.08);
    display: flex; align-items: center; justify-content: center;
    font-family: var(--font-mono); font-weight: 700; font-size: 19px; color: var(--key-text);
    position: relative; transition: transform .12s, background .2s, border-color .2s;
    box-shadow: 0 3px 0 rgba(0,0,0,.35);
}
.kb-key.pressed { transform: translateY(2px); box-shadow: 0 1px 0 rgba(0,0,0,.35); background: var(--key-bg-h); }
.kb-key.swapped { border-color: var(--key-swap); color: #fecaca; animation: keyWobble .28s ease; }
.kb-key.swapped::after {
    content: ''; position: absolute; top: -3px; right: -3px; width: 8px; height: 8px;
    background: var(--key-swap); border-radius: 50%; box-shadow: 0 0 6px var(--key-swap);
}
@keyframes keyWobble { 0%,100%{transform:rotate(0deg);} 25%{transform:rotate(-8deg) scale(1.1);} 75%{transform:rotate(8deg) scale(1.1);} }

/* The Gremlin sprite — real cartoon character now, not an emoji */
#gremlin-sprite {
    position: absolute; z-index: 20;
    width: 92px; height: 100px; cursor: pointer;
    transform: translate(-50%, -96%);
    transition: left .5s cubic-bezier(.34,1.56,.64,1), top .5s cubic-bezier(.34,1.56,.64,1), opacity .3s;
    filter: drop-shadow(0 6px 10px rgba(0,0,0,.5));
    opacity: 0; pointer-events: none;
    animation: gremlinBob 1.1s ease-in-out infinite;
}
#gremlin-svg { width: 100%; height: 100%; display: block; }
#gremlin-sprite.visible { opacity: 1; pointer-events: all; }
#gremlin-sprite.stunned { animation: gremlinWobbleStun .5s ease-in-out infinite; filter: grayscale(.55) drop-shadow(0 6px 10px rgba(0,0,0,.5)); }
#gremlin-sprite:active { transform: translate(-50%, -96%) scale(.85); }
@keyframes gremlinBob { 0%,100% { transform: translate(-50%,-96%) rotate(-4deg); } 50% { transform: translate(-50%,-108%) rotate(4deg); } }
@keyframes gremlinWobbleStun { 0%,100% { transform: translate(-50%,-96%) rotate(-3deg); } 50% { transform: translate(-50%,-92%) rotate(3deg); } }

#gremlin-sprite.peeking { opacity: 1; pointer-events: none; animation: gremlinPeek 1s ease-in-out infinite; }
@keyframes gremlinPeek {
    0%,100% { transform: translate(-50%,-40%) rotate(-5deg); }
    50%     { transform: translate(-50%,-58%) rotate(5deg); }
}

/* Face-state swaps (drawn as SVG groups, toggled by JS — no more emoji reuse) */
.gremlin-face-state { transition: opacity .25s ease; }
.gremlin-limb { transition: transform .3s ease; transform-origin: 60px 74px; }
#gremlin-sprite.stunned .gremlin-limb { transform: translateY(6px) rotate(8deg); opacity: .8; }
#gremlin-sprite.peeking #gremlin-arm-front,
#gremlin-sprite.peeking #gremlin-arm-back { opacity: 0; }

.stun-star {
    position: absolute; z-index: 25; font-size: 15px; pointer-events: none;
    animation: stunStarFly .7s ease-out forwards;
}
@keyframes stunStarFly {
    0%   { transform: translate(-50%,-50%) scale(.4) rotate(0deg); opacity: 1; }
    100% { transform: translate(calc(-50% + var(--dx)), calc(-50% + var(--dy))) scale(1.1) rotate(200deg); opacity: 0; }
}
.smash-poof {
    position: absolute; z-index: 24; width: 64px; height: 64px; border-radius: 50%;
    background: radial-gradient(circle, rgba(56,189,248,.6), transparent 70%);
    transform: translate(-50%,-50%) scale(.2); pointer-events: none;
    animation: poofOut .5s ease-out forwards;
}
.smash-poof-outer {
    width: 96px; height: 96px;
    background: radial-gradient(circle, rgba(255,255,255,.35), transparent 72%);
    animation: poofOut .6s ease-out .05s forwards;
}
@keyframes poofOut { 0% { transform: translate(-50%,-50%) scale(.2); opacity: 1; } 100% { transform: translate(-50%,-50%) scale(2.4); opacity: 0; } }

#keyboard-zone.smash-shake { animation: kbShake .34s ease; }
@keyframes kbShake {
    0%,100% { transform: translate(0,0); }
    20% { transform: translate(-4px,2px); }
    40% { transform: translate(4px,-2px); }
    60% { transform: translate(-3px,-2px); }
    80% { transform: translate(3px,2px); }
}

/* Terminal actions */
.terminal-actions { display: flex; gap: 10px; justify-content: center; margin-bottom: 16px; }
.btn-term {
    font-weight: 700; font-size: 13px; letter-spacing: .3px;
    padding: 12px 24px; border-radius: 10px; cursor: pointer; border: none;
    display: flex; align-items: center; gap: 8px; transition: transform .1s; font-family: var(--font-head);
}
.btn-term:active { transform: translateY(2px); }
.btn-submit { background: linear-gradient(135deg, var(--gremlin), var(--gremlin-d)); color: #0d1f04; box-shadow: 0 5px 0 #2c5610; }
.btn-clear  { background: rgba(255,255,255,.06); color: #f4f1fb; border: 1.5px solid rgba(255,255,255,.15); box-shadow: 0 5px 0 rgba(0,0,0,.4); }

.btn-hint { background: linear-gradient(135deg, var(--gold), #d97706); color: #2a1600; box-shadow: 0 5px 0 #7c4a08; }
.btn-hint:disabled { opacity: .45; cursor: not-allowed; box-shadow: 0 5px 0 rgba(0,0,0,.25); filter: grayscale(.4); }
.hint-line {
    min-height: 18px; font-family: var(--font-mono); font-size: 13px; font-weight: 700;
    letter-spacing: 2px; color: var(--gold); text-align: center; margin-bottom: 6px;
    opacity: 0; transform: translateY(-4px); transition: opacity .25s ease, transform .25s ease;
}
.hint-line.show { opacity: 1; transform: translateY(0); }

.feedback-strip { font-size: 15px; font-weight: 700; text-align: center; min-height: 22px; margin-bottom: 6px; }
.feedback-strip.ok  { color: var(--gremlin); }
.feedback-strip.bad { color: var(--danger); }

#score-footer {
    width: 100%; display: flex; justify-content: space-between; align-items: center;
    font-size: 12px; color: #b3a4d1; border-top: 1px dashed var(--panel-line); padding-top: 12px;
}
#score-val { color: var(--gold); font-weight: 700; font-size: 15px; }

/* ══════════════════════════════════════════════════════════════
   TOASTS
══════════════════════════════════════════════════════════════ */
#toast-layer { position: fixed; top: 60px; left: 50%; transform: translateX(-50%); z-index: 200; display: flex; flex-direction: column; align-items: center; gap: 6px; pointer-events: none; }
.toast {
    background: rgba(20,10,35,.95); border: 1.5px solid var(--panel-line); border-radius: 30px;
    padding: 8px 20px; font-size: 12.5px; font-weight: 700; white-space: nowrap;
    animation: toastPop .35s cubic-bezier(.34,1.56,.64,1), toastFade .3s ease 1.6s forwards;
}
.toast.smash { border-color: var(--blue); color: var(--blue); }
.toast.swap  { border-color: var(--danger); color: #fca5a5; }
@keyframes toastPop { from { transform: scale(.7) translateY(-8px); opacity: 0; } to { transform: scale(1) translateY(0); opacity: 1; } }
@keyframes toastFade { to { opacity: 0; transform: translateY(-10px); } }

/* ══════════════════════════════════════════════════════════════
   OVERLAYS
══════════════════════════════════════════════════════════════ */
.overlay {
    position: fixed; inset: 0; z-index: 300;
    background: rgba(10,4,20,.94); backdrop-filter: blur(5px);
    display: none; align-items: center; justify-content: center; padding: 22px;
}
.overlay.show { display: flex; }
.overlay-card {
    background: var(--panel); border: 2px solid var(--panel-line); border-radius: 22px;
    padding: 32px 30px; max-width: 560px; width: 100%;
    box-shadow: 0 30px 70px rgba(0,0,0,.6); text-align: center;
    animation: cardPop .35s cubic-bezier(.34,1.56,.64,1); max-height: 88vh; overflow-y: auto;
}
@keyframes cardPop { from { transform: scale(.88); opacity: 0; } to { transform: scale(1); opacity: 1; } }
.overlay-emoji { font-size: 52px; margin-bottom: 8px; }
.overlay-title { font-weight: 700; font-size: 1.5rem; color: var(--gremlin); margin-bottom: 6px; }
.overlay-sub { font-size: .95rem; color: #b3a4d1; margin-bottom: 20px; }

.rules-list { text-align: left; display: flex; flex-direction: column; gap: 13px; margin-bottom: 24px; }
.rule-row { display: flex; gap: 12px; align-items: flex-start; }
.rule-icon { font-size: 20px; flex-shrink: 0; width: 28px; text-align: center; }
.rule-text { font-size: .92rem; line-height: 1.5; color: #f4f1fb; }
.rule-text b { color: var(--gremlin); }
.rule-text b.red { color: var(--danger); }
.rule-text b.blueish { color: var(--blue); }

.btn-start {
    background: linear-gradient(135deg, var(--gremlin), var(--gremlin-d)); color: #0d1f04; border: none;
    padding: 14px 32px; border-radius: 12px; font-weight: 700; font-size: .95rem;
    cursor: pointer; box-shadow: 0 6px 0 #2c5610; font-family: var(--font-head);
}
.btn-start:active { transform: translateY(4px); box-shadow: 0 2px 0 #2c5610; }

.result-stats { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; margin: 16px 0; }
.rstat { background: rgba(255,255,255,.05); border: 1px solid var(--panel-line); border-radius: 12px; padding: 12px 18px; min-width: 88px; }
.rstat .rv { font-weight: 700; font-size: 1.3rem; color: var(--gold); display: block; }
.rstat .rl { font-size: 9px; color: #b3a4d1; text-transform: uppercase; letter-spacing: .5px; }
.xp-badge { display: inline-block; background: rgba(127,217,59,.14); border: 1.5px solid var(--gremlin); color: var(--gremlin); font-weight: 700; font-size: .9rem; padding: 8px 20px; border-radius: 50px; margin: 6px 0 18px; }
.result-btns { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
.btn-ghost2 { background: transparent; border: 1.5px solid var(--panel-line); color: #f4f1fb; padding: 12px 22px; border-radius: 10px; font-weight: 700; font-size: .85rem; cursor: pointer; font-family: var(--font-head); }

@media (max-width: 480px) {
    .sentence-text { font-size: 1.05rem; }
    .kb-key { width: 32px; height: 38px; font-size: 14px; }
    .kb-row.row2 { margin-left: 16px; }
    .kb-row.row3 { margin-left: 32px; }
    #gremlin-sprite { width: 66px; height: 72px; }
}
</style>
</head>
<body>

<div id="bg"></div>
<div id="toast-layer"></div>

<!-- ═══ HUD ═══ -->
<div id="hud">
    <div class="hud-logo">👺 THE TYPO GREMLIN<span>whack-a-mole keyboard</span></div>
    <div class="hud-spacer"></div>
    <div class="hud-lives" id="hud-lives"><span class="life-key">⌨️</span><span class="life-key">⌨️</span><span class="life-key">⌨️</span></div>
    <div class="hud-chip"><span class="v" id="hud-q">1/<?php echo $total_items; ?></span><span class="l">Item</span></div>
    <div class="hud-chip"><span class="v" id="hud-score">0</span><span class="l">Score</span></div>
</div>
<div id="timer-track"><div id="timer-fill" style="width:100%"></div></div>

<!-- ═══ STAGE ═══ -->
<div id="stage">
    <div class="progress-label" id="progress-label">ITEM 1 OF <?php echo $total_items; ?></div>

    <div class="sentence-card">
        <div class="sentence-text" id="sentence-text">Loading trivia...</div>
    </div>

    <div class="gremlin-banner" id="gremlin-banner"><span class="dot"></span> <span id="gremlin-banner-text">THE GREMLIN IS SWAPPING YOUR KEYS!</span></div>

    <div class="readout-wrap"><span id="readout-text"></span><span class="readout-cursor"></span></div>

    <div id="keyboard-zone">
        <div class="kb-row row1" id="kb-row1"></div>
        <div class="kb-row row2" id="kb-row2"></div>
        <div class="kb-row row3" id="kb-row3"></div>
        <div id="gremlin-sprite">
            <svg id="gremlin-svg" viewBox="0 0 120 130" xmlns="http://www.w3.org/2000/svg">
                <!-- shadow -->
                <ellipse cx="60" cy="122" rx="30" ry="6" fill="#000" opacity=".25"/>

                <!-- tail -->
                <path d="M78 100 Q100 108 96 122" stroke="#4f9020" stroke-width="7" fill="none" stroke-linecap="round"/>

                <!-- back arm (drops when stunned) -->
                <g id="gremlin-arm-back" class="gremlin-limb">
                    <path d="M84 74 Q102 70 106 52" stroke="#6fc72f" stroke-width="10" fill="none" stroke-linecap="round"/>
                    <circle cx="107" cy="49" r="7" fill="#6fc72f"/>
                    <path d="M103 45 l3 -5 M107 43 l1 -6 M111 45 l4 -5" stroke="#245b0d" stroke-width="2" stroke-linecap="round"/>
                </g>

                <!-- body / belly -->
                <ellipse cx="60" cy="88" rx="30" ry="26" fill="#7fd93b"/>
                <ellipse cx="60" cy="94" rx="19" ry="15" fill="#bff076"/>

                <!-- legs -->
                <ellipse cx="46" cy="114" rx="9" ry="7" fill="#5cab27"/>
                <ellipse cx="74" cy="114" rx="9" ry="7" fill="#5cab27"/>

                <!-- front arm (raised in mischief pose, drops when stunned) -->
                <g id="gremlin-arm-front" class="gremlin-limb">
                    <path d="M36 76 Q16 66 14 46" stroke="#6fc72f" stroke-width="10" fill="none" stroke-linecap="round"/>
                    <circle cx="13" cy="43" r="7" fill="#6fc72f"/>
                    <path d="M8 40 l-4 -5 M13 38 l0 -6 M18 40 l4 -5" stroke="#245b0d" stroke-width="2" stroke-linecap="round"/>
                </g>

                <!-- ears -->
                <path d="M32 52 Q10 34 20 12 Q40 22 42 48 Z" fill="#7fd93b" stroke="#4f9020" stroke-width="2"/>
                <path d="M88 52 Q110 34 100 12 Q80 22 78 48 Z" fill="#7fd93b" stroke="#4f9020" stroke-width="2"/>
                <path d="M28 46 Q18 34 24 20" fill="none" stroke="#245b0d" stroke-width="2" stroke-linecap="round" opacity=".5"/>
                <path d="M92 46 Q102 34 96 20" fill="none" stroke="#245b0d" stroke-width="2" stroke-linecap="round" opacity=".5"/>

                <!-- head -->
                <ellipse cx="60" cy="54" rx="34" ry="30" fill="#8fe84a"/>
                <ellipse cx="60" cy="66" rx="20" ry="10" fill="#bff076" opacity=".55"/>

                <!-- spikes on head -->
                <path d="M46 26 L50 14 L54 27 Z" fill="#4f9020"/>
                <path d="M60 22 L64 8 L68 23 Z" fill="#4f9020"/>
                <path d="M74 26 L78 14 L82 27 Z" fill="#4f9020"/>

                <!-- eyes: normal (mischievous slit) -->
                <g id="gremlin-eyes-normal" class="gremlin-face-state">
                    <ellipse cx="47" cy="52" rx="9" ry="11" fill="#fff6d0"/>
                    <ellipse cx="73" cy="52" rx="9" ry="11" fill="#fff6d0"/>
                    <ellipse cx="49" cy="54" rx="3.6" ry="6" fill="#1a0f00"/>
                    <ellipse cx="71" cy="54" rx="3.6" ry="6" fill="#1a0f00"/>
                    <path d="M38 42 Q47 36 55 42" stroke="#245b0d" stroke-width="2.4" fill="none" stroke-linecap="round"/>
                    <path d="M65 42 Q73 36 82 42" stroke="#245b0d" stroke-width="2.4" fill="none" stroke-linecap="round"/>
                </g>

                <!-- eyes: stunned (dizzy spirals) -->
                <g id="gremlin-eyes-stunned" class="gremlin-face-state" style="opacity:0">
                    <circle cx="47" cy="52" r="9" fill="#fff6d0"/>
                    <circle cx="73" cy="52" r="9" fill="#fff6d0"/>
                    <path d="M47 46 a6 6 0 1 1 -4 4" stroke="#1a0f00" stroke-width="2" fill="none"/>
                    <path d="M73 46 a6 6 0 1 1 -4 4" stroke="#1a0f00" stroke-width="2" fill="none"/>
                </g>

                <!-- mouth: normal (fanged grin) -->
                <g id="gremlin-mouth-normal" class="gremlin-face-state">
                    <path d="M42 68 Q60 84 78 68 Q60 78 42 68 Z" fill="#3a1414"/>
                    <path d="M48 69 L51 76 L54 69 Z" fill="#fff" />
                    <path d="M66 69 L69 76 L72 69 Z" fill="#fff" />
                    <path d="M57 71 L60 78 L63 71 Z" fill="#fff" />
                </g>

                <!-- mouth: stunned (small dazed 'o') -->
                <g id="gremlin-mouth-stunned" class="gremlin-face-state" style="opacity:0">
                    <ellipse cx="60" cy="71" rx="6" ry="8" fill="#3a1414"/>
                    <path d="M58 88 Q60 94 64 90" stroke="#ff7fae" stroke-width="3" fill="none" stroke-linecap="round"/>
                </g>
            </svg>
        </div>
    </div>

    <div class="terminal-actions">
        <button class="btn-term btn-clear" id="btn-clear" onclick="clearPlayerInput()"><i class="fa-solid fa-eraser"></i> Clear</button>
        <button class="btn-term btn-hint" id="btn-hint" onclick="useHint()"><i class="fa-solid fa-lightbulb"></i> Hint</button>
        <button class="btn-term btn-submit" id="btn-submit" onclick="submitAnswer()"><i class="fa-solid fa-check"></i> Submit</button>
    </div>

    <div class="hint-line" id="hint-line"></div>

    <div class="feedback-strip" id="feedback-strip"></div>

    <div id="score-footer">
        <span>🛠️ <?php echo htmlspecialchars($title); ?></span>
        <span>SCORE: <span id="score-val">0</span></span>
    </div>
</div>

<!-- ═══ INSTRUCTIONS OVERLAY ═══ -->
<div class="overlay show" id="overlay-instructions">
    <div class="overlay-card">
        <div class="overlay-emoji">👺⌨️</div>
        <div class="overlay-title">The Typo Gremlin</div>
        <div class="overlay-sub">Whack-a-Mole Keyboard — chaos lives in your fingertips.</div>

        <div class="rules-list">
            <div class="rule-row"><div class="rule-icon">📖</div><div class="rule-text">Read the trivia sentence and type the missing word using your <b>physical keyboard</b>. Watch the on-screen keyboard for what's really happening.</div></div>
            <div class="rule-row"><div class="rule-icon">😴</div><div class="rule-text">The Gremlin sleeps at first. The moment you type the <b>first correct letter</b>, he wakes up and jumps onto the keyboard.</div></div>
            <div class="rule-row"><div class="rule-icon">🥾</div><div class="rule-text">He stomps on <b class="red">adjacent keys</b> and swaps their places. Press what you think is "A" — you might actually type "S" instead!</div></div>
            <div class="rule-row"><div class="rule-icon">👆</div><div class="rule-text"><b class="blueish">Click or tap the Gremlin</b> to smash him — stuns him for 3 seconds and instantly restores the keyboard to normal, plus a small score bonus.</div></div>
            <div class="rule-row"><div class="rule-icon">🧠</div><div class="rule-text">Or just adapt: watch the swapped key markers and compensate on the fly. Either strategy works!</div></div>
            <div class="rule-row"><div class="rule-icon">⌨️</div><div class="rule-text">Wrong answers cost one of your 3 keyboards. Lose all three and it's game over.</div></div>
            <div class="rule-row"><div class="rule-icon">💡</div><div class="rule-text">Stuck? Tap <b class="blueish">Hint</b> once per item — it reveals the word's length and a couple of its letters (small score trade-off).</div></div>
            <div class="rule-row"><div class="rule-icon">👀</div><div class="rule-text">Watch closely — the Gremlin peeks over the keyboard right before he wakes up!</div></div>
        </div>

        <button class="btn-start" onclick="closeInstructions()">Wake the Workshop →</button>
    </div>
</div>

<!-- ═══ RESULT OVERLAY ═══ -->
<div class="overlay" id="overlay-result">
    <div class="overlay-card">
        <div class="overlay-emoji" id="res-emoji">🏆</div>
        <div class="overlay-title" id="res-title">WORKSHOP CLEARED!</div>
        <div class="overlay-sub" id="res-sub">You outsmarted the Gremlin.</div>
        <div class="result-stats">
            <div class="rstat"><span class="rv" id="res-correct">0</span><span class="rl">Correct</span></div>
            <div class="rstat"><span class="rv" id="res-score">0</span><span class="rl">Score</span></div>
            <div class="rstat"><span class="rv" id="res-acc">0%</span><span class="rl">Accuracy</span></div>
        </div>
        <div class="xp-badge" id="xp-badge" style="display:none;">+0 XP EARNED!</div>
        <div class="result-btns">
            <button class="btn-ghost2" onclick="retakeCurrentGame(this)">↻ Retake Game</button>
            <button class="btn-ghost2" onclick="window.location.href='quizzes.php'">🔄 New Quest</button>
            <button class="btn-start" onclick="window.location.href='studentdashboard.php'">🏠 Dashboard</button>
        </div>
    </div>
</div>

<script>
/* ══════════════════════════════════════════════════════════════
   DATA
══════════════════════════════════════════════════════════════ */
const ITEMS      = <?php echo json_encode($items); ?>;
const TOTAL      = ITEMS.length;
const TIME_PER_Q = 30;
const MAX_LIVES  = 3;
const MAX_SWAPS  = 3;         // max concurrent swapped pairs
const STUN_MS    = 3000;
const SMASH_BONUS = 60;

/* Approximate physical-adjacency map for a standard QWERTY keyboard.
   Used so the Gremlin only ever swaps keys that are actually near each
   other on a real board. */
const ADJ = {
    Q:['W','A'], W:['Q','E','A','S'], E:['W','R','S','D'], R:['E','T','D','F'],
    T:['R','Y','F','G'], Y:['T','U','G','H'], U:['Y','I','H','J'], I:['U','O','J','K'],
    O:['I','P','K','L'], P:['O','L'],
    A:['Q','W','S','Z'], S:['A','W','E','D','Z','X'], D:['S','E','R','F','X','C'],
    F:['D','R','T','G','C','V'], G:['F','T','Y','H','V','B'], H:['G','Y','U','J','B','N'],
    J:['H','U','I','K','N','M'], K:['J','I','O','L','M'], L:['K','O','P'],
    Z:['A','S','X'], X:['Z','S','D','C'], C:['X','D','F','V'], V:['C','F','G','B'],
    B:['V','G','H','N'], N:['B','H','J','M'], M:['N','J','K'],
};
const ROWS = [['Q','W','E','R','T','Y','U','I','O','P'], ['A','S','D','F','G','H','J','K','L'], ['Z','X','C','V','B','N','M']];

/* ══════════════════════════════════════════════════════════════
   GAME STATE
══════════════════════════════════════════════════════════════ */
let currentIdx    = 0;
let score         = 0;
let correctCount  = 0;
let lives         = MAX_LIVES;
let answered      = false;
let listening     = false;
let timerSecs     = TIME_PER_Q;
let timerIv       = null;

let buffer        = [];           // the ACTUAL typed letters (post-swap corruption included)
let swapMap       = {};           // physicalLetter -> effectiveLetter (symmetric pairs)
let activeSwaps   = [];           // list of [L1,L2] pairs currently active
let gremlinState  = 'dormant';    // dormant | active | stunned
let gremlinTimer  = null;
let stunTimer     = null;
let gremlinLoopId = 0;
let quizLog       = [];           // per-question breakdown sent to save_quiz_result.php -> solo_quiz_answers
const completionToken = (window.crypto && crypto.randomUUID)
    ? crypto.randomUUID() : `${Date.now()}-${Math.random()}`;
let hintUsed      = false;        // one hint per item

function resetSwapMap() {
    swapMap = {};
    ROWS.flat().forEach(l => swapMap[l] = l);
    activeSwaps = [];
}

/* ══════════════════════════════════════════════════════════════
   KEYBOARD RENDER
══════════════════════════════════════════════════════════════ */
function buildKeyboard() {
    ['kb-row1','kb-row2','kb-row3'].forEach((id, i) => {
        const el = document.getElementById(id);
        el.innerHTML = ROWS[i].map(l => `<div class="kb-key" id="key-${l}" data-letter="${l}">${l}</div>`).join('');
    });
}
function renderKeyboardState() {
    ROWS.flat().forEach(l => {
        const el = document.getElementById('key-' + l);
        if (!el) return;
        const display = swapMap[l] || l;
        el.innerText = display;
        el.classList.toggle('swapped', display !== l);
    });
}
function flashKeyPress(letter) {
    const el = document.getElementById('key-' + letter);
    if (!el) return;
    el.classList.add('pressed');
    setTimeout(() => el.classList.remove('pressed'), 130);
}

/* ══════════════════════════════════════════════════════════════
   READOUT
══════════════════════════════════════════════════════════════ */
function renderReadout() {
    document.getElementById('readout-text').innerText = buffer.join('');
}

/* ══════════════════════════════════════════════════════════════
   TOASTS
══════════════════════════════════════════════════════════════ */
function showToast(msg, cls) {
    const layer = document.getElementById('toast-layer');
    const el = document.createElement('div');
    el.className = 'toast ' + (cls || '');
    el.innerText = msg;
    layer.appendChild(el);
    setTimeout(() => el.remove(), 1950);
}

/* ══════════════════════════════════════════════════════════════
   AUDIO — tiny comic sound effects
══════════════════════════════════════════════════════════════ */
let _AC = null;
function getAC() { if (!_AC) { try { _AC = new (window.AudioContext||window.webkitAudioContext)(); } catch(e){ return null; } } if (_AC.state==='suspended') _AC.resume(); return _AC; }
function beep(f0,f1,dur,vol,type='square') {
    const ac = getAC(); if (!ac) return;
    const t = ac.currentTime;
    const o = ac.createOscillator(), g = ac.createGain();
    o.type = type; o.frequency.setValueAtTime(f0,t);
    if (f1 !== f0) o.frequency.exponentialRampToValueAtTime(f1, t+dur);
    g.gain.setValueAtTime(vol,t); g.gain.exponentialRampToValueAtTime(0.001, t+dur);
    o.connect(g); g.connect(ac.destination); o.start(t); o.stop(t+dur+0.02);
}
function sndType()   { beep(500,460,.04,.05); }
function sndSwap()   { beep(180,90,.18,.16,'sawtooth'); }
function sndSmash()  {
    beep(700,1100,.12,.14);
    beep(300,150,.15,.1,'sawtooth');
    setTimeout(()=>beep(120,60,.2,.12,'sawtooth'),40); // low "clonk" body-thud layer
}
function sndCorrect(){ [523,659,784,1047].forEach((f,i)=>setTimeout(()=>beep(f,f,.16,.12),i*80)); }
function sndWrong()  { beep(260,80,.32,.2,'sawtooth'); }
function sndHint()   { beep(660,990,.12,.09,'triangle'); setTimeout(()=>beep(880,1180,.1,.07,'triangle'),70); }
function sndPeek()   { beep(300,340,.05,.05,'triangle'); setTimeout(()=>beep(260,300,.05,.05,'triangle'),70); }
function sndWake()   {
    // layered cartoon growl: low rumble + a snarl on top
    beep(110,70,.28,.16,'sawtooth');
    setTimeout(()=>beep(180,120,.22,.13,'sawtooth'),80);
    setTimeout(()=>beep(260,190,.14,.09,'square'),170);
}

/* ══════════════════════════════════════════════════════════════
   GREMLIN AI
══════════════════════════════════════════════════════════════ */
function positionGremlinNear(letter) {
    const key = document.getElementById('key-' + letter);
    const zone = document.getElementById('keyboard-zone');
    const sprite = document.getElementById('gremlin-sprite');
    if (!key || !zone) return;
    const kr = key.getBoundingClientRect();
    const zr = zone.getBoundingClientRect();
    sprite.style.left = (kr.left - zr.left + kr.width/2) + 'px';
    sprite.style.top  = (kr.top  - zr.top) + 'px';
}

/* Swap the cartoon gremlin's face between mischievous (normal) and dazed (stunned) */
function setGremlinFace(state) {
    document.getElementById('gremlin-eyes-normal').style.opacity   = state === 'stunned' ? 0 : 1;
    document.getElementById('gremlin-eyes-stunned').style.opacity  = state === 'stunned' ? 1 : 0;
    document.getElementById('gremlin-mouth-normal').style.opacity  = state === 'stunned' ? 0 : 1;
    document.getElementById('gremlin-mouth-stunned').style.opacity = state === 'stunned' ? 1 : 0;
}

/* A cheeky preview: the Gremlin peeks up from behind the keyboard right
   before he wakes, so players get a fair warning something's about to happen. */
function peekGremlin() {
    if (gremlinState !== 'dormant' || answered) return;
    positionGremlinNear('H');
    const sprite = document.getElementById('gremlin-sprite');
    sprite.classList.add('peeking');
    sndPeek();
    setTimeout(() => sprite.classList.remove('peeking'), 850);
}

/* Visual payoff for landing a smash: a shockwave poof + a burst of stun stars
   flying outward from the Gremlin's position. */
function spawnSmashFx() {
    const zone = document.getElementById('keyboard-zone');
    const sprite = document.getElementById('gremlin-sprite');
    if (!zone || !sprite) return;
    const left = sprite.style.left, top = sprite.style.top;

    const poof = document.createElement('div');
    poof.className = 'smash-poof';
    poof.style.left = left; poof.style.top = top;
    zone.appendChild(poof);
    setTimeout(() => poof.remove(), 520);

    // second, wider ring for extra punch now that the gremlin sprite reads much bigger
    const poof2 = document.createElement('div');
    poof2.className = 'smash-poof smash-poof-outer';
    poof2.style.left = left; poof2.style.top = top;
    zone.appendChild(poof2);
    setTimeout(() => poof2.remove(), 620);

    const glyphs = ['⭐','💫','✨'];
    const count = 9;
    for (let i = 0; i < count; i++) {
        const star = document.createElement('div');
        star.className = 'stun-star';
        star.innerText = glyphs[i % glyphs.length];
        star.style.left = left; star.style.top = top;
        const angle = (Math.PI * 2 * i) / count;
        const dist = 54 + (i % 3) * 12;
        star.style.setProperty('--dx', Math.round(Math.cos(angle) * dist) + 'px');
        star.style.setProperty('--dy', Math.round(Math.sin(angle) * dist) + 'px');
        star.style.animationDelay = (i % 3) * 0.04 + 's';
        zone.appendChild(star);
        setTimeout(() => star.remove(), 820);
    }

    // camera-shake bump on the whole keyboard zone for extra weight
    zone.classList.add('smash-shake');
    setTimeout(() => zone.classList.remove('smash-shake'), 340);
}

function wakeGremlin() {
    if (gremlinState !== 'dormant') return;
    gremlinState = 'active';
    gremlinLoopId++;
    const myLoop = gremlinLoopId;
    document.getElementById('gremlin-sprite').classList.add('visible');
    document.getElementById('gremlin-sprite').classList.remove('stunned');
    setGremlinFace('normal');
    document.getElementById('gremlin-banner').classList.add('active');
    document.getElementById('gremlin-banner').classList.remove('stunned');
    document.getElementById('gremlin-banner-text').innerText = 'THE GREMLIN IS SWAPPING YOUR KEYS!';
    positionGremlinNear('G');
    sndWake();
    showToast('👺 The Gremlin woke up!', 'swap');
    scheduleNextSwap(myLoop);
}

function scheduleNextSwap(myLoop) {
    clearTimeout(gremlinTimer);
    if (myLoop !== gremlinLoopId || answered || gremlinState !== 'active') return;
    const delay = 1500 + Math.random() * 1200;
    gremlinTimer = setTimeout(() => performSwap(myLoop), delay);
}

function performSwap(myLoop) {
    if (myLoop !== gremlinLoopId || answered || gremlinState !== 'active') return;
    if (activeSwaps.length >= MAX_SWAPS) { scheduleNextSwap(myLoop); return; }

    // find a fresh, currently-untouched adjacent pair
    const candidates = [];
    Object.keys(ADJ).forEach(l1 => {
        if (swapMap[l1] !== l1) return; // already swapped
        ADJ[l1].forEach(l2 => {
            if (swapMap[l2] === l2) candidates.push([l1, l2]);
        });
    });
    if (candidates.length === 0) { scheduleNextSwap(myLoop); return; }

    const [l1, l2] = candidates[Math.floor(Math.random() * candidates.length)];
    swapMap[l1] = l2; swapMap[l2] = l1;
    activeSwaps.push([l1, l2]);
    renderKeyboardState();
    positionGremlinNear(l1);
    sndSwap();
    showToast(`👺 Swapped ${l1} ↔ ${l2}!`, 'swap');

    const key1 = document.getElementById('key-' + l1), key2 = document.getElementById('key-' + l2);
    [key1, key2].forEach(k => { if (k) { k.classList.remove('swapped'); void k.offsetWidth; k.classList.add('swapped'); } });

    scheduleNextSwap(myLoop);
}

function smashGremlin() {
    if (gremlinState !== 'active' || answered) return;
    gremlinState = 'stunned';
    gremlinLoopId++;
    clearTimeout(gremlinTimer);
    sndSmash();
    spawnSmashFx();
    score += SMASH_BONUS;
    updateHUD();
    showToast(`💥 SMASHED! +${SMASH_BONUS} pts`, 'smash');

    document.getElementById('gremlin-sprite').classList.add('stunned');
    setGremlinFace('stunned');
    document.getElementById('gremlin-banner').classList.add('stunned');
    document.getElementById('gremlin-banner-text').innerText = 'GREMLIN STUNNED — KEYBOARD RESTORED';

    resetSwapMap();
    renderKeyboardState();

    clearTimeout(stunTimer);
    stunTimer = setTimeout(() => {
        if (answered) return;
        gremlinState = 'active';
        gremlinLoopId++;
        const myLoop = gremlinLoopId;
        document.getElementById('gremlin-sprite').classList.remove('stunned');
        setGremlinFace('normal');
        document.getElementById('gremlin-banner').classList.remove('stunned');
        document.getElementById('gremlin-banner-text').innerText = 'THE GREMLIN IS SWAPPING YOUR KEYS!';
        showToast('👺 The Gremlin recovered...', 'swap');
        scheduleNextSwap(myLoop);
    }, STUN_MS);
}

document.getElementById('gremlin-sprite').addEventListener('click', smashGremlin);
document.getElementById('gremlin-sprite').addEventListener('touchstart', e => { e.preventDefault(); smashGremlin(); });

/* ══════════════════════════════════════════════════════════════
   PHYSICAL KEYBOARD CAPTURE
══════════════════════════════════════════════════════════════ */
document.addEventListener('keydown', e => {
    if (!listening || answered) return;

    if (e.key === 'Enter') { e.preventDefault(); submitAnswer(); return; }
    if (e.key === 'Backspace') { e.preventDefault(); buffer.pop(); renderReadout(); return; }
    if (e.key === ' ') { e.preventDefault(); buffer.push(' '); renderReadout(); return; }

    if (e.key.length === 1 && /[a-zA-Z]/.test(e.key)) {
        e.preventDefault();
        const physical = e.key.toUpperCase();
        const effective = swapMap[physical] || physical;
        flashKeyPress(physical);
        sndType();
        buffer.push(effective);
        renderReadout();

        // wake the gremlin the instant the FIRST correct letter lands
        if (gremlinState === 'dormant' && buffer.length === 1) {
            const item = ITEMS[currentIdx];
            if (buffer[0].toUpperCase() === (item.answer[0] || '').toUpperCase()) {
                wakeGremlin();
            }
        }
    }
});

function clearPlayerInput() {
    if (answered) return;
    buffer = [];
    renderReadout();
}

const HINT_PENALTY = 90;
function useHint() {
    if (answered || hintUsed) return;
    hintUsed = true;
    const item = ITEMS[currentIdx];
    const answer = item.answer;
    const revealCount = answer.replace(/\s/g,'').length <= 4 ? 1 : 2;
    let shown = 0;
    const masked = answer.split('').map(ch => {
        if (ch === ' ') return '␣';
        if (shown < revealCount) { shown++; return ch.toUpperCase(); }
        return '_';
    }).join(' ');
    document.getElementById('hint-line').innerText = `💡 ${answer.length} letters: ${masked}`;
    document.getElementById('hint-line').classList.add('show');
    document.getElementById('btn-hint').disabled = true;
    document.getElementById('btn-hint').innerHTML = '<i class="fa-solid fa-lightbulb"></i> Used';
    score = Math.max(0, score - HINT_PENALTY);
    updateHUD();
    sndHint();
    showToast(`💡 Hint used (-${HINT_PENALTY} pts)`, 'swap');
}

/* ══════════════════════════════════════════════════════════════
   OVERLAY FLOW
══════════════════════════════════════════════════════════════ */
function closeInstructions() {
    document.getElementById('overlay-instructions').classList.remove('show');
    buildKeyboard();
    loadQuestion(0);
}

/* ══════════════════════════════════════════════════════════════
   QUESTION FLOW
══════════════════════════════════════════════════════════════ */
function updateHUD() {
    document.getElementById('hud-q').innerText = `${currentIdx + 1}/${TOTAL}`;
    document.getElementById('hud-score').innerText = score;
    document.getElementById('score-val').innerText = score;
    document.querySelectorAll('.life-key').forEach((el,i)=> el.classList.toggle('lost', i >= lives));
}

function loadQuestion(idx) {
    if (idx >= TOTAL || lives <= 0) { finishGame(); return; }
    currentIdx   = idx;
    answered     = false;
    listening    = true;
    buffer       = [];
    hintUsed     = false;
    gremlinState = 'dormant';
    gremlinLoopId++;
    clearTimeout(gremlinTimer);
    clearTimeout(stunTimer);
    resetSwapMap();
    renderKeyboardState();
    renderReadout();

    document.getElementById('gremlin-sprite').classList.remove('visible','stunned','peeking');
    setGremlinFace('normal');
    document.getElementById('gremlin-banner').classList.remove('active','stunned');
    document.getElementById('feedback-strip').innerText = '';
    document.getElementById('feedback-strip').className = 'feedback-strip';
    document.getElementById('progress-label').innerText = `ITEM ${idx + 1} OF ${TOTAL}`;
    document.getElementById('hint-line').innerText = '';
    document.getElementById('hint-line').classList.remove('show');
    document.getElementById('btn-hint').disabled = false;
    document.getElementById('btn-hint').innerHTML = '<i class="fa-solid fa-lightbulb"></i> Hint';

    const item = ITEMS[idx];
    document.getElementById('sentence-text').innerHTML =
        item.question.replace(/____+/g, '<span class="blank-marker">&nbsp;</span>');

    updateHUD();
    startTimer();
    setTimeout(() => { if (!answered && gremlinState === 'dormant') peekGremlin(); }, 900);
}

function startTimer() {
    clearInterval(timerIv);
    timerSecs = TIME_PER_Q;
    renderTimerUI();
    timerIv = setInterval(() => {
        if (answered) return;
        timerSecs--;
        renderTimerUI();
        if (timerSecs <= 0) { clearInterval(timerIv); submitAnswer(true); }
    }, 1000);
}
function renderTimerUI() {
    const pct = (timerSecs / TIME_PER_Q) * 100;
    const fill = document.getElementById('timer-fill');
    fill.style.width = pct + '%';
    fill.className = pct < 20 ? 'danger' : pct < 45 ? 'warn' : '';
}

/* ══════════════════════════════════════════════════════════════
   SUBMIT
══════════════════════════════════════════════════════════════ */
function submitAnswer(timedOut) {
    if (answered) return;
    answered  = true;
    listening = false;
    gremlinLoopId++;
    clearInterval(timerIv);
    clearTimeout(gremlinTimer);
    clearTimeout(stunTimer);

    document.getElementById('gremlin-sprite').classList.remove('visible');
    document.getElementById('gremlin-banner').classList.remove('active','stunned');

    const item = ITEMS[currentIdx];
    const typed = buffer.join('').trim();
    const isCorrect = !timedOut && typed.toLowerCase() === item.answer.trim().toLowerCase();

    quizLog.push({
        q: item.question, type: 'fill_blank', options: [],
        correct_answer: item.answer, user_answer: typed || '(no answer)', is_correct: isCorrect
    });

    const fb = document.getElementById('feedback-strip');
    if (isCorrect) {
        correctCount++;
        const pts = 320 + Math.max(0, timerSecs) * 11;
        score += pts;
        fb.innerText = `✔ CORRECT — "${item.answer}" (+${pts})`;
        fb.className = 'feedback-strip ok';
        sndCorrect();
    } else {
        lives--;
        fb.innerText = timedOut
            ? `⌛ TIME'S UP — the answer was "${item.answer}"`
            : `✖ WRONG — you typed "${typed || '(nothing)'}" · answer was "${item.answer}"`;
        fb.className = 'feedback-strip bad';
        sndWrong();
    }
    updateHUD();
    resetSwapMap();
    renderKeyboardState();

    setTimeout(() => {
        if (lives <= 0) { finishGame(); return; }
        loadQuestion(currentIdx + 1);
    }, 1900);
}

/* ══════════════════════════════════════════════════════════════
   FINISH
══════════════════════════════════════════════════════════════ */
function finishGame() {
    clearInterval(timerIv);
    clearTimeout(gremlinTimer);
    clearTimeout(stunTimer);
    listening = false;

    const acc = TOTAL > 0 ? Math.round((correctCount / TOTAL) * 100) : 0;
    const ranOut = lives <= 0 && (currentIdx + 1) < TOTAL;

    document.getElementById('res-emoji').innerText = ranOut ? '🔧' : (acc >= 80 ? '🏆' : (acc >= 50 ? '👺' : '🥴'));
    document.getElementById('res-title').innerText = ranOut ? 'WORKSHOP OVERRUN' : 'WORKSHOP CLEARED!';
    document.getElementById('res-sub').innerText = `${correctCount} of ${TOTAL} words typed correctly.`;
    document.getElementById('res-correct').innerText = correctCount;
    document.getElementById('res-score').innerText = score;
    document.getElementById('res-acc').innerText = acc + '%';

    window.gameResultSavePromise = fetch('save_quiz_result.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `score=${score}&correct_answers=${correctCount}&total_questions=${TOTAL}&quiz_log=${encodeURIComponent(JSON.stringify(quizLog))}&completion_token=${encodeURIComponent(completionToken)}`
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            const badge = document.getElementById('xp-badge');
            badge.innerText = d.xp_earned > 0 ? `+${d.xp_earned} XP EARNED!` : (d.xp_message || 'No quiz XP awarded.');
            badge.style.display = 'inline-block';
        }
    })
    .catch(() => {});

    document.getElementById('overlay-result').classList.add('show');
}
</script>
<script src="retake_current_game.js"></script>
</body>
</html>