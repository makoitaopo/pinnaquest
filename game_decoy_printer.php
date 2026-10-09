<?php
// game_decoy_printer.php  —  "The Decoy Printer: Overlapping Ink"
// A fill-in-the-blank typing game. The player types the missing word, but a
// "Ghost Typist" AI simultaneously types a decoy word into the exact same
// character slots — blue ink (player) and red ink (AI) overlap and jumble
// together. The real keystrokes are tracked cleanly under the hood; only
// the VISUAL layer is chaos. Player must trust muscle memory over sight.
// Only makes sense for fill_blank quizzes — gated in pre_quiz_summary.php.
// Saves XP via save_quiz_result.php (same system as every other game mode).

session_start();
require_once __DIR__ . '/game_mode_access.php';
requireGameModeAccess('decoy_printer');

$questions = $_SESSION['quiz_data']['questions'] ?? [];
if (empty($questions)) {
    header("Location: quizzes.php?error=no_questions_found");
    exit();
}

// Every valid fill-in-the-blank question is playable. The terminal grows
// with the answer length, so longer answers are not silently dropped.
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

$title       = $_SESSION['quiz_data']['title'] ?? 'The Decoy Printer';
$total_items = count($items);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>The Decoy Printer | PinnaQuest</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=VT323&family=Courier+Prime:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* ══════════════════════════════════════════════════════════════
   DESIGN TOKENS — Dot-matrix printer / analog interference theme
══════════════════════════════════════════════════════════════ */
:root {
    --paper:      #f3f0e8;
    --paper-d:    #e5dece;
    --ink-black:  #24333a;
    --room:       #1b2931;
    --room-2:     #263740;
    --terminal:   #263842;
    --muted:      #b5c1c0;
    --blue-text:  #286d91;
    --red-text:   #ae5149;
    --green-text: #2d714e;

    --blue:       #83c2e4;
    --blue-glow:  rgba(131,194,228,.24);
    --red:        #ef9185;
    --red-glow:   rgba(239,145,133,.24);

    --amber:      #f0c372;
    --green:      #80d3a6;
    --danger:     #e77e78;

    --font-head: 'Courier Prime', 'Space Mono', monospace;
    --font-term: 'VT323', monospace;
    --font-mono: 'Space Mono', monospace;
}

* , *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }

html, body {
    width: 100%; min-height: 100vh;
    background: var(--room);
    color: #edf0eb;
    font-family: var(--font-mono);
    overflow-x: hidden;
    user-select: none;
}

/* ══════════════════════════════════════════════════════════════
   AMBIENT ROOM BACKGROUND
══════════════════════════════════════════════════════════════ */
#bg {
    position: fixed; inset: 0; z-index: 0; pointer-events: none;
    background:
        radial-gradient(ellipse at 50% -10%, rgba(240,195,114,.045) 0%, transparent 55%),
        radial-gradient(ellipse at 50% 110%, rgba(131,194,228,.04) 0%, transparent 50%),
        linear-gradient(180deg, var(--room-2), var(--room));
}
#bg::after {
    content: '';
    position: absolute; inset: 0;
    background-image: repeating-linear-gradient(0deg, rgba(255,255,255,.008) 0 1px, transparent 1px 3px);
    animation: scanDrift 9s linear infinite;
}
@keyframes scanDrift { from { background-position: 0 0; } to { background-position: 0 240px; } }

.static-flash {
    position: fixed; inset: 0; z-index: 250; pointer-events: none;
    background: repeating-linear-gradient(0deg, rgba(235,242,239,.035) 0 2px, transparent 2px 4px);
    opacity: 0; mix-blend-mode: overlay;
}
.static-flash.on { animation: staticBurst .28s steps(3); }
@keyframes staticBurst { 0%,100%{opacity:0;} 30%{opacity:.24;} 60%{opacity:.08;} }

/* ══════════════════════════════════════════════════════════════
   HUD
══════════════════════════════════════════════════════════════ */
#hud {
    position: fixed; top: 0; left: 0; right: 0; z-index: 90;
    background: rgba(27,41,49,.96); border-bottom: 1px solid rgba(240,195,114,.24);
    padding: 10px 18px; display: flex; align-items: center; gap: 14px;
    backdrop-filter: blur(6px);
}
.hud-logo { font-family: var(--font-head); font-weight: 700; font-size: 13px; color: var(--amber); letter-spacing: 1px; white-space: nowrap; }
.hud-logo span { display: block; font-size: 9px; color: var(--muted); letter-spacing: 1px; font-weight: 400; }
.hud-spacer { flex: 1; }
.hud-lives { display: flex; gap: 5px; }
.life-ink { font-size: 17px; filter: drop-shadow(0 0 3px rgba(240,195,114,.25)); transition: .3s; }
.life-ink.lost { filter: grayscale(1) opacity(.2); transform: scale(.7); }
.hud-chip { background: rgba(255,255,255,.07); border: 1px solid rgba(255,255,255,.14); border-radius: 8px; padding: 5px 12px; text-align: center; min-width: 54px; }
.hud-chip .v { font-family: var(--font-head); font-weight: 700; font-size: 14px; color: var(--paper); display: block; }
.hud-chip .l { font-size: 8px; letter-spacing: 1px; color: var(--muted); text-transform: uppercase; }

#timer-track { position: fixed; top: 45px; left: 0; right: 0; height: 10px; background: rgba(255,255,255,.1); z-index: 300; box-shadow: 0 1px 0 rgba(0,0,0,.2); }
#timer-fill { position:relative; height: 100%; background: linear-gradient(90deg, var(--green), var(--amber)); transition: width 1s linear, background .3s; }
#timer-fill.warn   { background: linear-gradient(90deg, var(--amber), #e4a94f); }
#timer-fill.danger { background: linear-gradient(90deg, #c65350, var(--danger)); }
#timer-secs {
    position: fixed; top: 45px; right: 10px; z-index: 301;
    transform: translateY(-50%); margin-top: 5px;
    font-family: var(--font-head); font-size: 11px; font-weight: 700;
    color: var(--paper); background: rgba(27,41,49,.94); border: 1px solid rgba(240,195,114,.42);
    border-radius: 10px; padding: 1px 8px; letter-spacing: .5px; pointer-events:none;
}
#timer-secs.warn   { color: var(--amber); border-color: var(--amber); }
#timer-secs.danger { color: var(--danger); border-color: var(--danger); }

/* ══════════════════════════════════════════════════════════════
   MAIN STAGE
══════════════════════════════════════════════════════════════ */
#stage {
    position: relative; z-index: 5;
    max-width: 760px; margin: 0 auto;
    padding: 76px 18px 40px;
    display: flex; flex-direction: column; align-items: center;
}

.progress-label { font-family: var(--font-head); font-size: 11px; color: var(--muted); letter-spacing: 2px; text-transform: uppercase; margin-bottom: 10px; }

/* Sentence card — looks like a printed strip of paper */
.sentence-card {
    background: var(--paper);
    color: var(--ink-black);
    border-radius: 4px;
    padding: 26px 30px;
    width: 100%;
    box-shadow: 0 14px 34px rgba(0,0,0,.5), inset 0 0 0 1px rgba(0,0,0,.08);
    position: relative;
    margin-bottom: 22px;
    font-family: var(--font-mono);
}
.sentence-card::before {
    content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 6px;
    background: repeating-linear-gradient(0deg, #c7beaa 0 4px, transparent 4px 9px);
}
.sentence-text {
    font-size: 1.2rem; font-weight: 700; line-height: 1.7; padding-left: 10px;
}
.blank-marker {
    display: inline-block; min-width: 60px; border-bottom: 3px solid var(--ink-black);
    margin: 0 4px;
}

/* ── Ghost typist status line ── */
.ghost-status {
    display: flex; align-items: center; gap: 8px;
    font-family: var(--font-term); font-size: 16px; color: var(--red);
    margin-bottom: 10px; opacity: 0; transition: opacity .3s;
}
.ghost-status.active { opacity: 1; }
.ghost-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--red); animation: ghostBlink 1.2s infinite; box-shadow: 0 0 5px var(--red-glow); }
@keyframes ghostBlink { 0%,100%{opacity:1;} 50%{opacity:.5;} }

/* ══════════════════════════════════════════════════════════════
   THE TERMINAL — where the overlapping ink happens
══════════════════════════════════════════════════════════════ */
#terminal-wrap {
    width: 100%;
    background: var(--terminal);
    border: 2px solid rgba(240,195,114,.34);
    border-radius: 10px;
    padding: 22px 18px;
    box-shadow: inset 0 0 24px rgba(0,0,0,.22), 0 10px 26px rgba(0,0,0,.22);
    cursor: text;
    position: relative;
    margin-bottom: 18px;
}
#terminal-wrap.locked { opacity: .5; cursor: default; }
#terminal-wrap::before {
    content: 'INPUT.TXT';
    position: absolute; top: -11px; left: 14px;
    background: var(--terminal); padding: 0 8px;
    font-family: var(--font-head); font-size: 9px; letter-spacing: 2px; color: var(--muted);
}

#terminal-row {
    display: flex; flex-wrap: wrap; gap: 4px; justify-content: center;
    min-height: 52px;
}
.ink-slot {
    position: relative;
    width: 30px; height: 46px;
    border-bottom: 3px solid rgba(255,255,255,.18);
    display: flex; align-items: center; justify-content: center;
    font-family: var(--font-term); font-size: 30px; font-weight: 400;
    flex-shrink: 0;
}
.ink-slot.space-slot { border-bottom-style: dashed; width: 16px; }
.ink-layer {
    position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
    line-height: 1; pointer-events: none;
}
.ink-layer.blue { color: var(--blue); text-shadow: 0 1px 3px var(--blue-glow); }
.ink-layer.red  { color: var(--red);  text-shadow: 0 1px 3px var(--red-glow); animation: inkJitter .38s infinite alternate; }
@keyframes inkJitter {
    0%   { transform: translate(-.8px, -.3px) rotate(-3deg) scale(1.02); }
    100% { transform: translate(.8px, .4px) rotate(3deg) scale(.98); }
}
.ink-slot.both-inked .ink-layer.blue { animation: inkJitter .38s infinite alternate-reverse; }
.ink-slot.both-inked { filter: contrast(1.08); }

.real-input {
    position: absolute; opacity: 0; pointer-events: none;
    width: 1px; height: 1px;
}

/* Terminal action row */
.terminal-actions { display: flex; gap: 10px; justify-content: center; margin-bottom: 18px; }
.btn-term {
    font-family: var(--font-head); font-size: 12px; font-weight: 700; letter-spacing: .5px;
    padding: 12px 24px; border-radius: 8px; cursor: pointer; border: none;
    display: flex; align-items: center; gap: 8px; transition: transform .1s;
}
.btn-term:active { transform: translateY(2px); }
.btn-submit { background: linear-gradient(135deg, var(--green), #58b888); color: #183126; box-shadow: 0 5px 0 #376d51; }
.btn-clear  { background: rgba(255,255,255,.08); color: var(--paper); border: 1.5px solid rgba(255,255,255,.2); box-shadow: 0 5px 0 rgba(0,0,0,.24); }

/* Feedback strip */
.feedback-strip {
    font-family: var(--font-term); font-size: 18px; text-align: center;
    min-height: 26px; margin-bottom: 6px; transition: .2s;
}
.feedback-strip.ok  { color: var(--green); }
.feedback-strip.bad { color: var(--red); }

/* Score footer */
#score-footer {
    width: 100%; display: flex; justify-content: space-between; align-items: center;
    font-family: var(--font-head); font-size: 11px; color: var(--muted);
    border-top: 1px dashed rgba(255,255,255,.16); padding-top: 12px; letter-spacing: 1px;
}
#score-val { color: var(--amber); font-weight: 700; font-size: 15px; }

/* ══════════════════════════════════════════════════════════════
   OVERLAYS
══════════════════════════════════════════════════════════════ */
.overlay {
    position: fixed; inset: 0; z-index: 300;
    background: rgba(16,28,34,.88); backdrop-filter: blur(5px);
    display: none; align-items: center; justify-content: center; padding: 22px;
}
.overlay.show { display: flex; }
.overlay-card {
    background: var(--paper); color: var(--ink-black);
    border-radius: 14px; padding: 32px 30px; max-width: 560px; width: 100%;
    box-shadow: 0 30px 70px rgba(0,0,0,.38);
    text-align: center; animation: cardPop .35s cubic-bezier(.34,1.56,.64,1);
    max-height: 88vh; overflow-y: auto; font-family: var(--font-mono);
}
@keyframes cardPop { from { transform: scale(.88); opacity: 0; } to { transform: scale(1); opacity: 1; } }

.overlay-title { font-family: var(--font-head); font-weight: 700; font-size: 1.5rem; color: var(--ink-black); margin-bottom: 6px; letter-spacing: 1px; }
.overlay-title.glitch-title { position: relative; }
.overlay-sub { font-size: .95rem; color: #53616a; margin-bottom: 20px; }

.rules-list { text-align: left; display: flex; flex-direction: column; gap: 13px; margin-bottom: 24px; }
.rule-row { display: flex; gap: 12px; align-items: flex-start; }
.rule-icon { font-size: 18px; flex-shrink: 0; width: 26px; text-align: center; }
.rule-text { font-size: .92rem; line-height: 1.5; }
.rule-text b { color: var(--red-text); }
.rule-text b.blue-txt { color: var(--blue-text); }

.btn-start {
    background: var(--ink-black); color: var(--paper); border: none;
    padding: 14px 32px; border-radius: 8px;
    font-family: var(--font-head); font-weight: 700; font-size: .9rem; letter-spacing: 1px;
    cursor: pointer; box-shadow: 0 6px 0 #182329;
}
.btn-start:active { transform: translateY(4px); box-shadow: 0 2px 0 #182329; }

/* Demo strip in instructions */
.demo-strip { display: flex; justify-content: center; gap: 4px; margin: 6px 0 20px; }
.demo-slot { width: 26px; height: 36px; border-bottom: 3px solid #a8a498; display: flex; align-items: center; justify-content: center; position: relative; font-family: var(--font-term); font-size: 24px; }
.demo-slot .dl { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; }
.demo-slot .dl.b { color: var(--blue-text); }
.demo-slot .dl.r { color: var(--red-text); transform: rotate(4deg) translate(1px,-1px); }

/* Result overlay */
.result-emoji { font-size: 56px; margin-bottom: 10px; }
.result-stats { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; margin: 16px 0; }
.rstat { background: rgba(0,0,0,.06); border: 1px solid rgba(0,0,0,.1); border-radius: 10px; padding: 12px 18px; min-width: 88px; }
.rstat .rv { font-family: var(--font-head); font-weight: 700; font-size: 1.3rem; display: block; }
.rstat .rl { font-size: 9px; color: #53616a; text-transform: uppercase; letter-spacing: .5px; }
.xp-badge { display: inline-block; background: rgba(128,211,166,.2); border: 1.5px solid var(--green); color: var(--green-text); font-family: var(--font-head); font-weight: 700; font-size: .85rem; padding: 8px 20px; border-radius: 50px; margin: 6px 0 18px; }
.result-btns { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
.btn-ghost2 { background: transparent; border: 1.5px solid rgba(0,0,0,.2); color: var(--ink-black); padding: 12px 22px; border-radius: 8px; font-family: var(--font-head); font-weight: 700; font-size: .8rem; cursor: pointer; }


/* ══════════════════════════════════════════════════════════════
   ENHANCEMENT PACK — hints, sound-reactive FX, dot-matrix graphics
══════════════════════════════════════════════════════════════ */

/* CRT vignette + flicker over the whole room */
#crt-vignette{
    position:fixed;inset:0;z-index:260;pointer-events:none;
    background:radial-gradient(ellipse at center, transparent 55%, rgba(0,0,0,.28) 100%);
    mix-blend-mode:multiply;
}
#crt-vignette::after{
    content:'';position:absolute;inset:0;
    background:rgba(255,255,255,.008);
}

/* Paper-feed entrance for the sentence card (looks like it's printing out) */
.sentence-card.feed-in{ animation: paperFeed .45s cubic-bezier(.2,.8,.3,1); }
@keyframes paperFeed{
    0%   { transform: translateY(-38px); opacity:0; clip-path: inset(0 0 100% 0); }
    60%  { opacity:1; }
    100% { transform: translateY(0); opacity:1; clip-path: inset(0 0 0% 0); }
}

/* Length hint under the sentence card */
.hint-line{
    font-family: var(--font-head); font-size: 10.5px; letter-spacing: 1.5px;
    color:var(--muted); text-transform:uppercase; margin: -12px 0 14px;
}
.hint-line b{ color: var(--amber); }

/* Print head — slides across the terminal to track progress */
#print-head-track{ position:relative; width:100%; height:6px; margin-bottom:6px; }
#print-head{
    position:absolute; top:0; left:0; width:14px; height:6px; border-radius:2px;
    background: linear-gradient(180deg, #ffd27a, var(--amber));
    box-shadow: 0 0 5px rgba(240,195,114,.38);
    transition: left .12s linear;
}

/* Blinking terminal caret in the next empty slot */
.ink-slot.cursor::after{
    content:''; position:absolute; bottom:2px; left:50%; transform:translateX(-50%);
    width:16px; height:3px; background: var(--amber);
    animation: caretBlink 1s step-start infinite;
    box-shadow:0 0 4px rgba(240,195,114,.38);
}
@keyframes caretBlink{ 50%{ opacity:.4; } }

/* Interference flicker on the whole terminal while the ghost is active */
#terminal-wrap.interference{ animation: interferenceShake .42s infinite; }
@keyframes interferenceShake{
    0%,100%{ filter:none; }
    50%    { filter: drop-shadow(1px 0 0 var(--red-glow)) drop-shadow(-1px 0 0 var(--blue-glow)); }
}

/* Ink cartridge losing a drop when a life is spent */
.life-ink.dripping{ position:relative; }
.life-ink.dripping::after{
    content:'💧'; position:absolute; left:50%; top:100%; transform:translateX(-50%);
    font-size:10px; animation: inkDrip .6s ease-in forwards;
}
@keyframes inkDrip{
    0%  { opacity:1; transform:translate(-50%,0); }
    100%{ opacity:0; transform:translate(-50%,16px); }
}

/* Hint button + panel */
.btn-hint{
    background: linear-gradient(135deg, var(--amber), #dda955); color:#352916;
    box-shadow: 0 5px 0 #87683a;
}
.btn-hint:disabled{ opacity:.4; cursor:default; box-shadow:none; }
.hint-panel{
    display:none; flex-wrap:wrap; gap:8px; justify-content:center;
    margin: -6px 0 16px;
}
.hint-panel.show{ display:flex; }
.hint-pill{
    font-family: var(--font-term); font-size: 17px; letter-spacing: 1px;
    background: rgba(240,195,114,.12); border: 1.5px solid rgba(240,195,114,.42);
    color: var(--amber); padding: 7px 16px; border-radius: 20px; cursor:default;
    user-select: none;
}
.hint-countdown{
    width: 100%; height: 5px; background: rgba(240,195,114,.18);
    border-radius: 3px; overflow: hidden; margin-top: 4px;
}
.hint-countdown-fill{
    height: 100%; width: 100%; background: var(--amber);
    transition: width linear 0s;
}
.hint-note{
    font-family: var(--font-head); font-size: 9.5px; color:var(--muted);
    text-align:center; letter-spacing:1px; margin: -8px 0 14px; text-transform:uppercase;
}

@media (max-width: 480px) {
    .sentence-text { font-size: 1rem; }
    .ink-slot { width: 24px; height: 40px; font-size: 24px; }
}
</style>
</head>
<body>

<div id="bg"></div>
<div class="static-flash" id="static-flash"></div>
<div id="crt-vignette"></div>

<!-- ═══ HUD ═══ -->
<div id="hud">
    <div class="hud-logo">🖨️ THE DECOY PRINTER<span>overlapping ink</span></div>
    <div class="hud-spacer"></div>
    <div class="hud-lives" id="hud-lives"><span class="life-ink">🖋️</span><span class="life-ink">🖋️</span><span class="life-ink">🖋️</span></div>
    <div class="hud-chip"><span class="v" id="hud-q">1/<?php echo $total_items; ?></span><span class="l">Item</span></div>
    <div class="hud-chip"><span class="v" id="hud-score">0</span><span class="l">Score</span></div>
</div>
<div id="timer-track"><div id="timer-fill" style="width:100%"></div></div>
<div id="timer-secs">28s</div>

<!-- ═══ STAGE ═══ -->
<div id="stage">
    <div class="progress-label" id="progress-label">ITEM 1 OF <?php echo $total_items; ?></div>

    <div class="sentence-card" id="sentence-card">
        <div class="sentence-text" id="sentence-text">Loading transmission...</div>
    </div>
    <div class="hint-line" id="hint-line">&nbsp;</div>

    <div class="ghost-status" id="ghost-status"><span class="ghost-dot"></span> UNKNOWN PROCESS IS WRITING TO THIS FIELD...</div>

    <div id="terminal-wrap">
        <div id="print-head-track"><div id="print-head"></div></div>
        <div id="terminal-row"></div>
        <input type="text" id="real-input" class="real-input" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false">
    </div>

    <div class="hint-panel" id="hint-panel"></div>
    <div class="hint-note" id="hint-note"></div>

    <div class="terminal-actions">
        <button class="btn-term btn-clear" id="btn-clear" onclick="clearPlayerInput()"><i class="fa-solid fa-eraser"></i> Clear</button>
        <button class="btn-term btn-hint" id="btn-hint" onclick="useHint()"><i class="fa-solid fa-lightbulb"></i> Hint (-150)</button>
        <button class="btn-term btn-submit" id="btn-submit" onclick="submitAnswer()"><i class="fa-solid fa-print"></i> Print Answer</button>
    </div>

    <div class="feedback-strip" id="feedback-strip"></div>

    <div id="score-footer">
        <span>📠 <?php echo htmlspecialchars($title); ?></span>
        <span>SCORE: <span id="score-val">0</span></span>
    </div>
</div>

<!-- ═══ INSTRUCTIONS OVERLAY ═══ -->
<div class="overlay show" id="overlay-instructions">
    <div class="overlay-card">
        <div class="overlay-title">🖨️ The Decoy Printer</div>
        <div class="overlay-sub">Overlapping Ink — trust your fingers, not your eyes.</div>

        <div class="demo-strip">
            <div class="demo-slot"><span class="dl b">T</span><span class="dl r">D</span></div>
            <div class="demo-slot"><span class="dl b">I</span><span class="dl r">A</span></div>
            <div class="demo-slot"><span class="dl b">M</span><span class="dl r">R</span></div>
            <div class="demo-slot"><span class="dl b">E</span><span class="dl r">K</span></div>
        </div>

        <div class="rules-list">
            <div class="rule-row"><div class="rule-icon">⌨️</div><div class="rule-text">Read the sentence, then type the missing word using your keyboard. Your letters print in <b class="blue-txt">BLUE</b> ink.</div></div>
            <div class="rule-row"><div class="rule-icon">👻</div><div class="rule-text">The moment you start typing, a <b>Ghost Typist</b> begins printing a completely different decoy word into the exact same slots — in <b>RED</b> ink.</div></div>
            <div class="rule-row"><div class="rule-icon">🌀</div><div class="rule-text">The two inks overlap and smear into an unreadable mess. You will <b>not</b> be able to visually confirm your spelling — type from memory and rhythm.</div></div>
            <div class="rule-row"><div class="rule-icon">🎭</div><div class="rule-text">Your real keystrokes are tracked cleanly underneath the chaos — the mess is only visual. Press <b>Print Answer</b> (or Enter) when you're confident.</div></div>
            <div class="rule-row"><div class="rule-icon">🖋️</div><div class="rule-text">Wrong answers cost one of your 3 ink cartridges. Lose all three and the transmission is lost.</div></div>
            <div class="rule-row"><div class="rule-icon">💡</div><div class="rule-text">Stuck? Press <b>Hint (-150)</b> — the correct word flashes for a few seconds among 3 decoys, then vanishes. No clicking to fill it in — memorize it fast and type it yourself. Usable every question, at a point cost.</div></div>
        </div>

        <button class="btn-start" onclick="closeInstructions()">Begin Transmission →</button>
    </div>
</div>

<!-- ═══ RESULT OVERLAY ═══ -->
<div class="overlay" id="overlay-result">
    <div class="overlay-card">
        <div class="result-emoji" id="res-emoji">🖨️</div>
        <div class="overlay-title" id="res-title">TRANSMISSION COMPLETE</div>
        <div class="overlay-sub" id="res-sub">You fought through the interference.</div>
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
const TIME_PER_Q = 28;
const MAX_LIVES  = 3;
const MIN_SLOTS  = 6;

/* Fallback decoy word bank — mixed everyday words of varying length so we
   can always find something plausible even if the quiz has few answers
   to borrow from. */
const DECOY_BANK = [
    'DARK','LIGHT','FAST','SLOW','TRUE','FALSE','EARLY','LATER','NORTH','SOUTH',
    'INNER','OUTER','QUIET','LOUDLY','SILENT','MOTION','STATIC','HIDDEN','VISIBLE',
    'BROKEN','WHOLE','MODERN','ANCIENT','SIMPLE','COMPLEX','DIRECT','RANDOM',
    'GHOST','SHADOW','MIRROR','ECHO','SIGNAL','NOISE','PAPER','METAL','GLASS',
    'RIVER','STONE','CLOUD','SPARK','FROST','EMBER','ORBIT','PULSE','DRIFT'
];

/* ══════════════════════════════════════════════════════════════
   SOUND ENGINE — dot-matrix printer / interference SFX (Web Audio)
══════════════════════════════════════════════════════════════ */
let _AC = null, _hum = null, _humGain = null;
function getAC(){
    if (!_AC) { try { _AC = new (window.AudioContext || window.webkitAudioContext)(); } catch(e) { return null; } }
    if (_AC.state === 'suspended') _AC.resume();
    return _AC;
}
function beep(f0, f1, dur, vol, type='square'){
    const ac = getAC(); if (!ac) return;
    const osc = ac.createOscillator(), gain = ac.createGain();
    osc.type = type;
    osc.frequency.setValueAtTime(f0, ac.currentTime);
    osc.frequency.exponentialRampToValueAtTime(Math.max(f1,1), ac.currentTime + dur);
    gain.gain.setValueAtTime(vol, ac.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.001, ac.currentTime + dur);
    osc.connect(gain).connect(ac.destination);
    osc.start(); osc.stop(ac.currentTime + dur + .02);
}
function noiseBurst(dur, vol){
    const ac = getAC(); if (!ac) return;
    const bufSize = ac.sampleRate * dur;
    const buf = ac.createBuffer(1, bufSize, ac.sampleRate);
    const data = buf.getChannelData(0);
    for (let i=0;i<bufSize;i++) data[i] = (Math.random()*2-1) * (1 - i/bufSize);
    const src = ac.createBufferSource(); src.buffer = buf;
    const gain = ac.createGain(); gain.gain.setValueAtTime(vol, ac.currentTime);
    const filt = ac.createBiquadFilter(); filt.type = 'highpass'; filt.frequency.value = 1800;
    src.connect(filt).connect(gain).connect(ac.destination);
    src.start();
}
function sndKeyClick(){ beep(1200, 700, .035, .05, 'square'); }
function sndGhostClick(){ beep(340, 220, .04, .045, 'sawtooth'); }
function sndStatic(){ noiseBurst(.09, .05); }
function sndSubmit(){ beep(500, 180, .12, .1, 'square'); setTimeout(()=>noiseBurst(.05,.04), 60); }
function sndCorrect(){ [660,880,1100,1320].forEach((f,i)=>setTimeout(()=>beep(f,f,.14,.1),i*70)); }
function sndWrong(){ beep(220,60,.32,.12,'sawtooth'); setTimeout(()=>noiseBurst(.12,.06), 40); }
function sndLifeLost(){ beep(180,50,.4,.14,'square'); }
function sndHint(){ beep(880,1320,.1,.08,'triangle'); }
function sndComplete(win){
    const seq = win ? [523,659,784,1047,1319] : [400,340,280,220];
    seq.forEach((f,i)=>setTimeout(()=>beep(f,f,.18,.11),i*110));
}
function startAmbience(){
    const ac = getAC(); if (!ac || _hum) return;
    _hum = ac.createOscillator(); _humGain = ac.createGain();
    _hum.type = 'sawtooth'; _hum.frequency.value = 62;
    const filt = ac.createBiquadFilter(); filt.type = 'lowpass'; filt.frequency.value = 220;
    _humGain.gain.value = .015;
    _hum.connect(filt).connect(_humGain).connect(ac.destination);
    _hum.start();
}
function stopAmbience(){
    if (_hum) { try { _hum.stop(); } catch(e){} _hum = null; _humGain = null; }
}

/* ══════════════════════════════════════════════════════════════
   GAME STATE
══════════════════════════════════════════════════════════════ */
let currentIdx   = 0;
let score        = 0;
let correctCount = 0;
let lives        = MAX_LIVES;
let answered     = false;
let timerSecs    = TIME_PER_Q;
let timerIv      = null;

let playerBuffer = '';   // the REAL tracked answer — never scrambled
let decoyBuffer  = '';   // what the ghost has visually printed so far
let decoyQueue   = [];   // remaining letters of the current decoy word
let decoyTimer   = null;
let decoyLoopId  = 0;    // increments each question to invalidate stale timers
let gameStarted  = false;
let currentAnswerLen = MIN_SLOTS; // real answer length — shown as a length hint
let hintUsed     = false;      // per-question flag (kept for scoring display)
let hintRevealTimer = null;
const HINT_PENALTY = 150;
const HINT_REVEAL_MS = 2600;   // pills are shown just long enough to memorize, not to click
let quizLog = []; // per-question breakdown sent to save_quiz_result.php -> solo_quiz_answers
const completionToken = (window.crypto && crypto.randomUUID)
    ? crypto.randomUUID() : `${Date.now()}-${Math.random()}`;

const realInput   = document.getElementById('real-input');
const terminalRow = document.getElementById('terminal-row');
const terminalWrap= document.getElementById('terminal-wrap');
const ghostStatus = document.getElementById('ghost-status');

/* ══════════════════════════════════════════════════════════════
   DECOY WORD SELECTION
══════════════════════════════════════════════════════════════ */
function pickDecoyWord(targetLen) {
    // Prefer borrowing another question's answer (extra unsettling — feels
    // like the ghost knows the quiz too), otherwise fall back to the bank.
    const otherAnswers = ITEMS
        .map(it => it.answer.toUpperCase().replace(/[^A-Z]/g, ''))
        .filter(a => a && a !== (ITEMS[currentIdx]?.answer || '').toUpperCase().replace(/[^A-Z]/g, ''));
    const pool = [...otherAnswers, ...DECOY_BANK];
    const closeEnough = pool.filter(w => Math.abs(w.length - targetLen) <= 2);
    const finalPool = closeEnough.length ? closeEnough : pool;
    return finalPool[Math.floor(Math.random() * finalPool.length)] || 'STATIC';
}

/* ══════════════════════════════════════════════════════════════
   RENDER: the overlapping ink terminal
══════════════════════════════════════════════════════════════ */
function renderTerminal() {
    const slotCount = Math.max(MIN_SLOTS, currentAnswerLen, playerBuffer.length, decoyBuffer.length);
    let html = '';
    for (let i = 0; i < slotCount; i++) {
        const pChar = playerBuffer[i] || '';
        const dChar = decoyBuffer[i]  || '';
        const isSpace = pChar === ' ' || dChar === ' ';
        const both = pChar && dChar;
        const isCursor = !answered && i === playerBuffer.length;
        let cls = 'ink-slot' + (isSpace ? ' space-slot' : '') + (both ? ' both-inked' : '') + (isCursor ? ' cursor' : '');
        let inner = '';
        if (pChar && pChar !== ' ') inner += `<span class="ink-layer blue">${escapeHtml(pChar.toUpperCase())}</span>`;
        if (dChar && dChar !== ' ') inner += `<span class="ink-layer red">${escapeHtml(dChar.toUpperCase())}</span>`;
        html += `<div class="${cls}">${inner}</div>`;
    }
    terminalRow.innerHTML = html;

    const head = document.getElementById('print-head');
    if (head) {
        const progress = slotCount > 0 ? Math.max(playerBuffer.length, decoyBuffer.length) / slotCount : 0;
        head.style.left = `calc(${Math.min(100, progress*100)}% - 7px)`;
    }
}
function escapeHtml(s) { return s.replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }

/* ══════════════════════════════════════════════════════════════
   GHOST TYPIST — types decoy letters into the same slots over time
══════════════════════════════════════════════════════════════ */
function startGhostTypist(myLoopId) {
    ghostStatus.classList.add('active');
    scheduleNextDecoyWord(myLoopId);
}

function scheduleNextDecoyWord(myLoopId) {
    if (myLoopId !== decoyLoopId || answered) return;
    const targetLen = Math.max(3, playerBuffer.length || (ITEMS[currentIdx]?.answer.length ?? 5));
    const word = pickDecoyWord(targetLen);
    decoyQueue = word.split('');
    typeNextDecoyChar(myLoopId);
}

function typeNextDecoyChar(myLoopId) {
    clearTimeout(decoyTimer);
    if (myLoopId !== decoyLoopId || answered) return;
    terminalWrap.classList.add('interference');

    if (decoyQueue.length === 0) {
        // brief pause, then a fresh decoy word begins — relentless interference
        decoyTimer = setTimeout(() => scheduleNextDecoyWord(myLoopId), 260 + Math.random() * 260);
        return;
    }

    const nextChar = decoyQueue.shift();
    const idx = decoyBuffer.length;
    decoyBuffer = decoyBuffer + nextChar;
    renderTerminal();
    flashStatic();
    sndGhostClick();

    const delay = 140 + Math.random() * 170;
    decoyTimer = setTimeout(() => typeNextDecoyChar(myLoopId), delay);
}

function flashStatic() {
    const el = document.getElementById('static-flash');
    el.classList.remove('on'); void el.offsetWidth; el.classList.add('on');
}

/* ══════════════════════════════════════════════════════════════
   REAL INPUT CAPTURE
══════════════════════════════════════════════════════════════ */
terminalWrap.addEventListener('click', () => { if (!answered) realInput.focus(); });

realInput.addEventListener('input', () => {
    if (answered) return;
    const wasEmpty = playerBuffer.length === 0;
    const grew = realInput.value.length > playerBuffer.length;
    playerBuffer = realInput.value;
    renderTerminal();
    if (grew) sndKeyClick();
    if (wasEmpty && playerBuffer.length > 0 && gameStarted) {
        // first keystroke of this question — summon the ghost after a beat
        const myLoopId = decoyLoopId;
        setTimeout(() => startGhostTypist(myLoopId), 320 + Math.random() * 260);
    }
});
realInput.addEventListener('paste', e => e.preventDefault());
realInput.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); submitAnswer(); } });

function clearPlayerInput() {
    if (answered) return;
    playerBuffer = '';
    realInput.value = '';
    renderTerminal();
    realInput.focus();
}

/* ══════════════════════════════════════════════════════════════
   HINT — reveals the real answer buried among 3 decoys, for a
   score penalty. Picking the wrong pill just fills the input —
   it does NOT auto-fail, so it still has to be Printed to count.
══════════════════════════════════════════════════════════════ */
function useHint() {
    // One hint PER QUESTION — but it's a MEMORY flash, not a click-to-fill —
    // the pills vanish on their own before you can safely pick one, so it only
    // narrows the field for a few seconds instead of handing you the answer.
    if (answered || hintUsed) return;
    hintUsed = true;
    sndHint();
    const item = ITEMS[currentIdx];
    const correct = item.answer.trim();
    const seen = new Set([correct.toUpperCase()]);
    const decoys = [];
    let guard = 0;
    while (decoys.length < 3 && guard < 30) {
        guard++;
        const w = pickDecoyWord(correct.length);
        const key = w.toUpperCase();
        if (!seen.has(key)) { seen.add(key); decoys.push(w); }
    }
    const pills = shuffleArr([correct, ...decoys]);

    const panel = document.getElementById('hint-panel');
    const note  = document.getElementById('hint-note');
    const btn   = document.getElementById('btn-hint');
    panel.innerHTML = pills.map(p => `<span class="hint-pill">${escapeHtml(p)}</span>`).join('')
        + '<div class="hint-countdown"><div class="hint-countdown-fill" id="hint-countdown-fill"></div></div>';
    panel.classList.add('show');
    note.innerText = 'MEMORIZE — you won\'t see this again. Type fast, from memory.';
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-lightbulb"></i> Hint used';

    requestAnimationFrame(() => {
        const fill = document.getElementById('hint-countdown-fill');
        if (fill) fill.style.transitionDuration = (HINT_REVEAL_MS / 1000) + 's', fill.style.width = '0%';
    });

    clearTimeout(hintRevealTimer);
    hintRevealTimer = setTimeout(() => {
        panel.classList.remove('show');
        panel.innerHTML = '';
        sndStatic();
        note.innerText = 'Hint gone. Type the answer you remember.';
    }, HINT_REVEAL_MS);
}
function shuffleArr(arr) {
    const a = [...arr];
    for (let i = a.length - 1; i > 0; i--) { const j = Math.floor(Math.random() * (i + 1)); [a[i], a[j]] = [a[j], a[i]]; }
    return a;
}

/* ══════════════════════════════════════════════════════════════
   OVERLAY FLOW
══════════════════════════════════════════════════════════════ */
function closeInstructions() {
    document.getElementById('overlay-instructions').classList.remove('show');
    getAC();
    startAmbience();
    gameStarted = true;
    updateHUD();
    loadQuestion(0);
}

/* ══════════════════════════════════════════════════════════════
   QUESTION FLOW
══════════════════════════════════════════════════════════════ */
function updateHUD() {
    document.getElementById('hud-q').innerText = `${currentIdx + 1}/${TOTAL}`;
    document.getElementById('hud-score').innerText = score;
    document.getElementById('score-val').innerText = score;
    document.querySelectorAll('.life-ink').forEach((el, i) => el.classList.toggle('lost', i >= lives));
}

function loadQuestion(idx) {
    if (idx >= TOTAL || lives <= 0) { finishGame(); return; }
    currentIdx = idx;
    answered   = false;
    hintUsed   = false; // per-question flag — hint is available again every new item
    decoyLoopId++;
    clearTimeout(decoyTimer);
    decoyQueue  = [];
    decoyBuffer = '';
    playerBuffer = '';
    realInput.value = '';
    ghostStatus.classList.remove('active');
    terminalWrap.classList.remove('locked');
    terminalWrap.classList.remove('interference');
    document.getElementById('feedback-strip').innerText = '';
    document.getElementById('feedback-strip').className = 'feedback-strip';
    document.getElementById('progress-label').innerText = `ITEM ${idx + 1} OF ${TOTAL}`;

    const item = ITEMS[idx];
    currentAnswerLen = item.answer.length;
    const display = item.question.replace(/____+/g, '<span class="blank-marker">&nbsp;</span>');
    document.getElementById('sentence-text').innerHTML = display;
    document.getElementById('hint-line').innerHTML = `<b>${currentAnswerLen}</b> character slot${currentAnswerLen===1?'':'s'} — count the spaces too`;

    const card = document.getElementById('sentence-card');
    card.classList.remove('feed-in'); void card.offsetWidth; card.classList.add('feed-in');

    clearTimeout(hintRevealTimer);
    const btnHint = document.getElementById('btn-hint');
    if (btnHint) {
        btnHint.disabled = false;
        btnHint.innerHTML = `<i class="fa-solid fa-lightbulb"></i> Hint (-${HINT_PENALTY})`;
    }
    const hintPanel = document.getElementById('hint-panel');
    hintPanel.classList.remove('show'); hintPanel.innerHTML = '';
    document.getElementById('hint-note').innerText = '';

    renderTerminal();
    updateHUD();
    startTimer();
    setTimeout(() => realInput.focus(), 200);
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
    const state = pct < 20 ? 'danger' : pct < 45 ? 'warn' : '';
    const fill = document.getElementById('timer-fill');
    fill.style.width = Math.max(0, pct) + '%';
    fill.className = state;
    const secsEl = document.getElementById('timer-secs');
    secsEl.innerText = Math.max(0, timerSecs) + 's';
    secsEl.className = state;
}

/* ══════════════════════════════════════════════════════════════
   SUBMIT / CHECK
══════════════════════════════════════════════════════════════ */
function submitAnswer(timedOut) {
    if (answered) return;
    answered = true;
    decoyLoopId++; // invalidates any pending ghost timers for this question
    clearInterval(timerIv);
    clearTimeout(decoyTimer);
    terminalWrap.classList.add('locked');
    ghostStatus.classList.remove('active');
    terminalWrap.classList.remove('interference');
    document.getElementById('hint-panel').classList.remove('show');
    sndSubmit();

    const item = ITEMS[currentIdx];
    const typed = (playerBuffer || '').trim();
    const isCorrect = !timedOut && typed.toLowerCase() === item.answer.trim().toLowerCase();

    quizLog.push({
        q: item.question, type: 'fill_blank', options: [],
        correct_answer: item.answer, user_answer: timedOut ? null : typed, is_correct: !!isCorrect
    });

    const fb = document.getElementById('feedback-strip');
    if (isCorrect) {
        correctCount++;
        let pts = 350 + Math.max(0, timerSecs) * 12;
        if (hintUsed) pts = Math.max(50, pts - HINT_PENALTY);
        score += pts;
        fb.innerText = `✔ CLEAN SIGNAL — "${item.answer.toUpperCase()}" (+${pts}${hintUsed ? ', hint used' : ''})`;
        fb.className = 'feedback-strip ok';
        setTimeout(sndCorrect, 80);
    } else {
        lives--;
        const lostIdx = lives; // index of the icon that just got crossed out
        fb.innerText = timedOut
            ? `✖ TRANSMISSION LOST — the answer was "${item.answer.toUpperCase()}"`
            : `✖ CORRUPTED — the answer was "${item.answer.toUpperCase()}"`;
        fb.className = 'feedback-strip bad';
        setTimeout(sndWrong, 80);
        setTimeout(sndLifeLost, 120);
        const lifeIcons = document.querySelectorAll('.life-ink');
        if (lifeIcons[lostIdx]) {
            lifeIcons[lostIdx].classList.add('dripping');
            setTimeout(() => lifeIcons[lostIdx].classList.remove('dripping'), 650);
        }
    }
    updateHUD();

    // Briefly reveal the player's clean (un-scrambled) input so they see
    // what they actually typed, for closure.
    decoyBuffer = '';
    renderTerminal();

    setTimeout(() => {
        if (lives <= 0) { finishGame(); return; }
        loadQuestion(currentIdx + 1);
    }, 1700);
}

/* ══════════════════════════════════════════════════════════════
   FINISH
══════════════════════════════════════════════════════════════ */
function finishGame() {
    clearInterval(timerIv);
    clearTimeout(decoyTimer);
    stopAmbience();
    const acc = TOTAL > 0 ? Math.round((correctCount / TOTAL) * 100) : 0;
    const ranOut = lives <= 0 && (currentIdx + 1) < TOTAL;
    sndComplete(!ranOut && acc >= 50);

    document.getElementById('res-emoji').innerText = ranOut ? '📴' : (acc >= 80 ? '🏆' : (acc >= 50 ? '🖨️' : '📠'));
    document.getElementById('res-title').innerText = ranOut ? 'SIGNAL LOST' : 'TRANSMISSION COMPLETE';
    document.getElementById('res-sub').innerText = `${correctCount} of ${TOTAL} words printed cleanly.`;
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

/* ══════════════════════════════════════════════════════════════
   INIT
══════════════════════════════════════════════════════════════ */
renderTerminal();
</script>
<script src="retake_current_game.js"></script>
</body>
</html>