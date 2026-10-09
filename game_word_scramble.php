<?php
// game_word_scramble.php
// Word Scramble — students drag/tap letter tiles to reconstruct the answer word/phrase.
// Uses the complete set of valid questions from the engine.
// Saves result via save_quiz_result.php (same XP system).

session_start();
require_once __DIR__ . '/game_mode_access.php';
requireGameModeAccess('word_scramble');

$questions = $_SESSION['quiz_data']['questions'] ?? [];

if (empty($questions)) {
    header("Location: quizzes.php?error=no_questions_found");
    exit();
}

// Build scramble items — prefer fill_blank, accept any
$items = [];
foreach ($questions as $q) {
    $answer  = trim($q['answer'] ?? '');
    $qtext   = $q['question']   ?? '';
    if (!$answer || !$qtext) continue;

    // Clean question text for display
    $display_q = str_replace(['____', '___'], '___', $qtext);
    if (mb_strlen($display_q) > 120) {
        $display_q = mb_substr($display_q, 0, 117) . '…';
    }

    $items[] = ['question' => $display_q, 'answer' => $answer];
}

if (count($items) < 2) {
    header("Location: quizzes.php?error=not_enough_questions");
    exit();
}

$title       = $_SESSION['quiz_data']['title'] ?? 'Word Scramble';
$total_items = count($items);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>Word Scramble | PinnaQuest</title>
<link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&family=VT323:wght@400&family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* ═══════════════════════════════════════════
   RETRO RPG DESIGN TOKENS
═══════════════════════════════════════════ */
:root {
    /* Sky / World Palette */
    --sky-top:    #0a0a1a;
    --sky-mid:    #0d1b3e;
    --sky-bottom: #1a3a6b;

    /* Ground */
    --ground:     #2d4a1e;
    --ground-d:   #1a2e10;
    --grass:      #3d6b27;
    --grass-l:    #4e8a30;

    /* RPG UI Palette */
    --panel-bg:   #1a1a2e;
    --panel-d:    #0f0f1a;
    --panel-l:    #252545;
    --window-bg:  #16213e;

    /* Pixel borders */
    --px-border:  #4a4a8a;
    --px-border-l:#6a6aaa;
    --px-border-d:#2a2a5a;
    --px-shadow:  #000000;

    /* RPG Colors */
    --green:      #38c850;
    --green-d:    #1e7a28;
    --green-glow: rgba(56,200,80,0.3);
    --gold:       #f0c040;
    --gold-d:     #b08820;
    --gold-glow:  rgba(240,192,64,0.3);
    --purple:     #8844cc;
    --purple-l:   #aa66ff;
    --red:        #e03030;
    --red-l:      #ff5555;
    --blue:       #4488ff;
    --blue-d:     #2255cc;
    --cyan:       #44ccff;
    --white:      #e8e8f0;
    --cream:      #f0e8c8;
    --muted:      #7878a8;

    /* Stone / Wood tile colors */
    --stone-top:  #8888aa;
    --stone-mid:  #6666888;
    --stone-d:    #444466;
    --wood-top:   #c8884444;
    --wood-mid:   #a86030;
    --wood-d:     #6a3818;

    /* Pixel font */
    --font-pixel: 'Press Start 2P', monospace;
    --font-vt:    'VT323', monospace;
    --font-body:  'Nunito', sans-serif;
}

/* ── PIXEL RENDERING ── */
*,*::before,*::after {
    margin: 0; padding: 0; box-sizing: border-box;
    image-rendering: pixelated;
}

/* ── BODY & BACKGROUND ── */
body {
    font-family: var(--font-body);
    background: var(--sky-top);
    color: var(--white);
    min-height: 100vh;
    overflow-x: hidden;
    position: relative;
}

/* ═══════════════════════════════════════════
   PIXEL WORLD BACKGROUND
═══════════════════════════════════════════ */
#pixel-world {
    position: fixed;
    inset: 0;
    z-index: 0;
    pointer-events: none;
    overflow: hidden;
}

/* Sky gradient */
.px-sky {
    position: absolute;
    inset: 0;
    background: linear-gradient(
        to bottom,
        #050510 0%,
        #0a0a2a 25%,
        #0d1b55 55%,
        #1a3a7a 75%,
        #2a5a8a 90%,
        #3a6a80 100%
    );
}

/* ── STARS (CSS box-shadow constellation) ── */
.px-stars {
    position: absolute;
    top: 0; left: 0;
    width: 2px; height: 2px;
    background: transparent;
    animation: starsTwinkle 3s ease-in-out infinite alternate;
    box-shadow:
        /* Layer 1 — small bright stars */
        12px 8px 0 #fff, 45px 22px 0 #fff, 78px 5px 0 #ffffcc,
        102px 34px 0 #fff, 135px 12px 0 #ccccff, 168px 28px 0 #fff,
        203px 6px 0 #fff, 240px 41px 0 #ffffcc, 275px 18px 0 #fff,
        312px 33px 0 #fff, 355px 8px 0 #ccffff, 390px 25px 0 #fff,
        428px 44px 0 #fff, 465px 11px 0 #fff, 500px 37px 0 #ffffcc,
        535px 5px 0 #fff, 572px 30px 0 #fff, 615px 19px 0 #ccccff,
        650px 42px 0 #fff, 688px 14px 0 #fff, 725px 38px 0 #fff,
        760px 7px 0 #ffffcc, 800px 26px 0 #fff, 840px 45px 0 #fff,
        880px 15px 0 #ccffff, 920px 32px 0 #fff, 960px 9px 0 #fff,
        1000px 40px 0 #ffffcc, 1040px 22px 0 #fff, 1080px 6px 0 #fff,
        1120px 35px 0 #ccccff, 1160px 18px 0 #fff, 1200px 43px 0 #fff,
        1240px 11px 0 #fff, 1280px 29px 0 #ffffcc, 1320px 4px 0 #fff,
        1360px 36px 0 #fff, 1400px 21px 0 #ccffff, 1440px 13px 0 #fff,
        /* Layer 2 — dimmer stars */
        25px 60px 0 rgba(255,255,255,0.5), 70px 75px 0 rgba(255,255,200,0.4),
        115px 58px 0 rgba(200,200,255,0.5), 160px 82px 0 rgba(255,255,255,0.3),
        205px 65px 0 rgba(255,255,255,0.5), 255px 78px 0 rgba(255,255,200,0.4),
        300px 55px 0 rgba(255,255,255,0.6), 348px 70px 0 rgba(200,255,255,0.4),
        395px 62px 0 rgba(255,255,255,0.5), 445px 80px 0 rgba(255,255,200,0.3),
        495px 57px 0 rgba(255,255,255,0.5), 545px 73px 0 rgba(200,200,255,0.4),
        595px 64px 0 rgba(255,255,255,0.6), 645px 85px 0 rgba(255,255,255,0.3),
        695px 59px 0 rgba(255,200,200,0.4), 745px 77px 0 rgba(255,255,255,0.5),
        795px 68px 0 rgba(200,255,200,0.4), 845px 83px 0 rgba(255,255,255,0.3),
        895px 61px 0 rgba(255,255,200,0.5), 945px 74px 0 rgba(255,255,255,0.4),
        995px 56px 0 rgba(200,200,255,0.5), 1045px 80px 0 rgba(255,255,255,0.3),
        1095px 67px 0 rgba(255,255,255,0.5), 1145px 76px 0 rgba(255,200,255,0.4),
        1195px 60px 0 rgba(255,255,255,0.6), 1245px 84px 0 rgba(255,255,200,0.3),
        1295px 72px 0 rgba(255,255,255,0.5), 1345px 65px 0 rgba(200,255,255,0.4),
        1395px 79px 0 rgba(255,255,255,0.5), 1435px 58px 0 rgba(255,255,255,0.4);
}
.px-stars-2 {
    position: absolute;
    top: 0; left: 0;
    width: 1px; height: 1px;
    background: transparent;
    animation: starsTwinkle 4.5s ease-in-out infinite alternate-reverse;
    box-shadow:
        38px 95px 0 rgba(255,255,255,0.7), 88px 110px 0 rgba(255,255,200,0.6),
        138px 98px 0 rgba(200,200,255,0.7), 188px 115px 0 rgba(255,255,255,0.5),
        238px 100px 0 rgba(255,255,255,0.7), 288px 120px 0 rgba(255,255,200,0.6),
        340px 95px 0 rgba(255,255,255,0.8), 392px 108px 0 rgba(200,255,255,0.6),
        442px 102px 0 rgba(255,255,255,0.7), 492px 118px 0 rgba(255,255,200,0.5),
        542px 97px 0 rgba(255,255,255,0.7), 592px 113px 0 rgba(200,200,255,0.6),
        642px 104px 0 rgba(255,255,255,0.8), 692px 122px 0 rgba(255,255,255,0.5),
        742px 99px 0 rgba(255,200,200,0.6), 792px 116px 0 rgba(255,255,255,0.7),
        842px 106px 0 rgba(200,255,200,0.6), 892px 120px 0 rgba(255,255,255,0.5),
        942px 101px 0 rgba(255,255,200,0.7), 992px 114px 0 rgba(255,255,255,0.6),
        1042px 96px 0 rgba(200,200,255,0.7), 1092px 119px 0 rgba(255,255,255,0.5),
        1142px 107px 0 rgba(255,255,255,0.7), 1192px 115px 0 rgba(255,200,255,0.6),
        1242px 100px 0 rgba(255,255,255,0.8), 1292px 122px 0 rgba(255,255,200,0.5),
        1342px 110px 0 rgba(255,255,255,0.7), 1392px 105px 0 rgba(200,255,255,0.6);
}
@keyframes starsTwinkle {
    0%   { opacity: 1; }
    50%  { opacity: 0.4; }
    100% { opacity: 0.85; }
}

/* ── PIXEL MOON ── */
.px-moon {
    position: absolute;
    top: 28px; right: 80px;
    width: 48px; height: 48px;
    background: #fffde0;
    box-shadow:
        0 0 0 4px #fffde0,
        0 0 16px 8px rgba(255,253,200,0.4),
        0 0 40px 16px rgba(255,240,150,0.2);
    /* Pixel-perfect shape with box-shadows */
    clip-path: polygon(
        16% 0%, 84% 0%,
        100% 16%, 100% 84%,
        84% 100%, 16% 100%,
        0% 84%, 0% 16%
    );
    animation: moonGlow 4s ease-in-out infinite alternate;
}
@keyframes moonGlow {
    0%   { box-shadow: 0 0 0 4px #fffde0, 0 0 16px 8px rgba(255,253,200,0.4), 0 0 40px 16px rgba(255,240,150,0.2); }
    100% { box-shadow: 0 0 0 4px #fffde0, 0 0 24px 12px rgba(255,253,200,0.6), 0 0 60px 24px rgba(255,240,150,0.3); }
}

/* ── PIXEL CLOUDS ── */
.px-cloud {
    position: absolute;
    background: rgba(160, 180, 220, 0.25);
    border-radius: 0;
    image-rendering: pixelated;
}
.px-cloud::before, .px-cloud::after {
    content: '';
    position: absolute;
    background: rgba(160, 180, 220, 0.25);
}
/* Cloud 1 */
.px-cloud-1 {
    top: 90px; left: -120px;
    width: 96px; height: 16px;
    animation: cloudDrift1 28s linear infinite;
}
.px-cloud-1::before {
    top: -16px; left: 16px;
    width: 64px; height: 16px;
}
.px-cloud-1::after {
    top: -8px; left: 8px;
    width: 80px; height: 8px;
}
/* Cloud 2 */
.px-cloud-2 {
    top: 140px; left: -200px;
    width: 80px; height: 16px;
    animation: cloudDrift2 38s linear infinite;
    animation-delay: -12s;
}
.px-cloud-2::before {
    top: -16px; left: 8px;
    width: 56px; height: 16px;
}
.px-cloud-2::after {
    top: -8px; left: 4px;
    width: 72px; height: 8px;
}
/* Cloud 3 */
.px-cloud-3 {
    top: 70px; left: -300px;
    width: 112px; height: 16px;
    opacity: 0.6;
    animation: cloudDrift1 45s linear infinite;
    animation-delay: -20s;
}
.px-cloud-3::before {
    top: -16px; left: 24px;
    width: 72px; height: 16px;
}
.px-cloud-3::after {
    top: -8px; left: 12px;
    width: 88px; height: 8px;
}
@keyframes cloudDrift1 {
    0%   { transform: translateX(0); }
    100% { transform: translateX(calc(100vw + 200px)); }
}
@keyframes cloudDrift2 {
    0%   { transform: translateX(0); }
    100% { transform: translateX(calc(100vw + 300px)); }
}

/* ── PIXEL MOUNTAINS (far) ── */
.px-mountains-far {
    position: absolute;
    bottom: 80px;
    left: 0; right: 0;
    height: 160px;
    background: #1a2a4a;
    clip-path: polygon(
        0% 100%,
        0% 70%,
        3% 50%, 6% 70%, 8% 40%, 11% 70%,
        13% 30%, 16% 70%, 20% 20%, 23% 70%,
        26% 45%, 28% 70%, 30% 55%, 33% 30%,
        36% 70%, 40% 15%, 43% 70%, 46% 40%,
        49% 70%, 52% 25%, 55% 70%, 58% 38%,
        62% 70%, 66% 18%, 70% 70%, 73% 42%,
        76% 70%, 79% 28%, 82% 70%, 85% 36%,
        88% 70%, 91% 22%, 94% 70%, 97% 48%,
        100% 70%, 100% 100%
    );
}
/* Snow caps */
.px-mountains-far::after {
    content: '';
    position: absolute;
    inset: 0;
    background: rgba(200,210,255,0.15);
    clip-path: polygon(
        19% 100%, 20% 20%, 21% 40%, 22% 20%, 23% 70%, 23% 100%,
        39% 100%, 40% 15%, 41% 35%, 42% 15%, 43% 70%, 43% 100%,
        65% 100%, 66% 18%, 67% 38%, 68% 18%, 70% 70%, 70% 100%
    );
}

/* ── PIXEL MOUNTAINS (near) ── */
.px-mountains-near {
    position: absolute;
    bottom: 72px;
    left: 0; right: 0;
    height: 130px;
    background: #12221a;
    clip-path: polygon(
        0% 100%,
        0% 80%,
        4% 60%, 7% 80%, 10% 50%, 13% 80%,
        17% 65%, 20% 80%, 24% 42%, 27% 80%,
        32% 58%, 35% 80%, 39% 35%, 43% 80%,
        47% 62%, 50% 80%, 54% 48%, 57% 80%,
        61% 38%, 64% 80%, 68% 56%, 71% 80%,
        75% 44%, 78% 80%, 82% 52%, 85% 80%,
        89% 40%, 92% 80%, 95% 60%, 98% 80%,
        100% 80%, 100% 100%
    );
}

/* ── PIXEL TREES ── */
.px-trees {
    position: absolute;
    bottom: 62px;
    left: 0; right: 0;
    height: 60px;
    display: flex;
    align-items: flex-end;
    gap: 0;
    overflow: hidden;
}
.px-tree {
    position: relative;
    display: inline-block;
    flex-shrink: 0;
}
.px-tree-trunk {
    width: 8px;
    background: #5a3010;
    margin: 0 auto;
    position: relative;
}
.px-tree-top {
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    clip-path: polygon(50% 0%, 0% 100%, 100% 100%);
}

/* ── PIXEL GROUND / GRASS ── */
.px-ground {
    position: absolute;
    bottom: 0; left: 0; right: 0;
    height: 72px;
    background: #1a2e10;
}
/* Grass top row — pixel-chunky */
.px-grass {
    position: absolute;
    bottom: 56px; left: 0; right: 0;
    height: 20px;
    background: #3d6b27;
    /* Pixel grass blades via repeating clip-path */
    -webkit-mask-image: repeating-linear-gradient(
        90deg,
        #000 0px, #000 8px,
        transparent 8px, transparent 12px
    );
    mask-image: repeating-linear-gradient(
        90deg,
        #000 0px, #000 8px,
        transparent 8px, transparent 12px
    );
}
.px-grass-base {
    position: absolute;
    bottom: 48px; left: 0; right: 0;
    height: 12px;
    background: #2d4a1e;
}
.px-dirt {
    position: absolute;
    bottom: 32px; left: 0; right: 0;
    height: 20px;
    background: #3a2510;
    border-top: 4px solid #5a3510;
}
.px-dirt-2 {
    position: absolute;
    bottom: 0; left: 0; right: 0;
    height: 36px;
    background: #2a1c0c;
}

/* ── PIXEL FIREFLIES / SPARKLES ── */
.px-firefly {
    position: absolute;
    width: 4px; height: 4px;
    background: #aaffaa;
    border-radius: 0;
    animation: fireflyFloat var(--dur, 6s) var(--del, 0s) ease-in-out infinite;
}
@keyframes fireflyFloat {
    0%, 100% { transform: translate(0,0); opacity: 0; }
    20%       { opacity: 1; }
    50%       { transform: translate(var(--mx,20px), var(--my,-30px)); opacity: 0.8; }
    80%       { opacity: 0.3; }
}

/* ═══════════════════════════════════════════
   CONTENT LAYER (above background)
═══════════════════════════════════════════ */
#game-content {
    position: relative;
    z-index: 1;
}

/* ═══════════════════════════════════════════
   RPG PIXEL WINDOW MIXIN
   Uses 4-layer box-shadow for chunky pixel border
═══════════════════════════════════════════ */
.rpg-window {
    background: var(--panel-bg);
    border: 4px solid var(--px-border);
    box-shadow:
        0 0 0 4px var(--px-shadow),
        inset 0 0 0 2px var(--px-border-l),
        4px 4px 0 4px var(--px-shadow);
    position: relative;
}
.rpg-window::before {
    content: '';
    position: absolute;
    inset: 2px;
    border: 2px solid rgba(255,255,255,0.05);
    pointer-events: none;
}

/* ═══════════════════════════════════════════
   HEADER — RPG MENU BAR
═══════════════════════════════════════════ */
.game-header {
    position: sticky; top: 0; z-index: 100;
    background: rgba(10, 10, 26, 0.97);
    border-bottom: 4px solid #4a4a8a;
    box-shadow: 0 4px 0 #000, 0 8px 0 rgba(74,74,138,0.3);
    padding: 10px 20px;
    display: flex; align-items: center; gap: 12px;
    backdrop-filter: blur(4px);
}

/* ── Logo ── */
.game-logo {
    font-family: var(--font-pixel);
    font-size: 10px;
    color: var(--gold);
    letter-spacing: 0;
    display: flex; align-items: center; gap: 8px;
    flex-shrink: 0;
    text-shadow: 2px 2px 0 var(--gold-d), 4px 4px 0 rgba(0,0,0,0.8);
    image-rendering: pixelated;
}
.game-logo .logo-icon {
    display: inline-block;
    width: 24px; height: 24px;
    background: var(--gold);
    clip-path: polygon(
        20% 0%, 80% 0%, 100% 20%,
        100% 80%, 80% 100%, 20% 100%,
        0% 80%, 0% 20%
    );
    position: relative;
    box-shadow: 2px 2px 0 var(--gold-d);
    display: flex; align-items: center; justify-content: center;
    font-size: 12px;
    color: var(--panel-bg);
}

/* ── Game Title ── */
.game-title {
    font-family: var(--font-pixel);
    font-weight: 400;
    font-size: 7px;
    color: var(--cream);
    flex: 1;
    line-height: 1.8;
    text-shadow: 1px 1px 0 #000, 2px 2px 0 rgba(0,0,0,0.6);
    letter-spacing: 1px;
}
.game-title span {
    color: var(--muted);
    font-size: 6px;
    display: block;
    margin-top: 2px;
}

/* ── HUD Chips ── */
.hud-chips { display: flex; gap: 6px; align-items: center; }
.hud-chip {
    background: var(--panel-d);
    border: 3px solid var(--px-border);
    box-shadow: 0 0 0 2px #000, 2px 2px 0 #000;
    padding: 6px 10px;
    text-align: center;
    min-width: 60px;
    position: relative;
}
.hud-chip::after {
    content: '';
    position: absolute;
    inset: 1px;
    border-top: 1px solid rgba(255,255,255,0.1);
    border-left: 1px solid rgba(255,255,255,0.1);
    pointer-events: none;
}
.hud-chip .val {
    font-family: var(--font-pixel);
    font-weight: 400;
    font-size: 14px;
    display: block;
    text-shadow: 2px 2px 0 #000;
}
.hud-chip .lbl {
    font-family: var(--font-pixel);
    font-size: 5px;
    color: var(--muted);
    font-weight: 400;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 2px;
    display: block;
}
.hud-chip.green { border-color: var(--green-d); }
.hud-chip.green .val { color: var(--green); text-shadow: 0 0 8px var(--green-glow), 2px 2px 0 #000; }
.hud-chip.gold  { border-color: var(--gold-d); }
.hud-chip.gold  .val { color: var(--gold);  text-shadow: 0 0 8px var(--gold-glow), 2px 2px 0 #000; }
.hud-chip.red   { border-color: #6a1010; }
.hud-chip.red   .val { color: var(--red-l); text-shadow: 2px 2px 0 #000; }

/* ═══════════════════════════════════════════
   MAIN CONTENT AREA
═══════════════════════════════════════════ */
.main {
    max-width: 780px;
    margin: 0 auto;
    padding: 24px 16px 120px;
}

/* ═══════════════════════════════════════════
   PROGRESS BAR — RPG EXP BAR
═══════════════════════════════════════════ */
.progress-bar-wrap { margin-bottom: 20px; }
.progress-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 6px;
}
.progress-label {
    font-family: var(--font-pixel);
    font-size: 6px;
    font-weight: 400;
    color: var(--gold);
    text-transform: uppercase;
    letter-spacing: 1px;
    text-shadow: 1px 1px 0 #000;
}
.progress-track {
    height: 16px;
    background: var(--panel-d);
    border: 3px solid var(--px-border);
    box-shadow: 0 0 0 2px #000, 2px 2px 0 #000;
    position: relative;
    overflow: hidden;
}
.progress-track::before {
    content: '';
    position: absolute;
    inset: 0;
    background: repeating-linear-gradient(
        90deg,
        transparent 0px,
        transparent 18px,
        rgba(0,0,0,0.2) 18px,
        rgba(0,0,0,0.2) 20px
    );
    z-index: 2;
    pointer-events: none;
}
.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--gold-d), var(--gold), #ffe080);
    transition: width 0.5s steps(20, end);
    position: relative;
}
.progress-fill::after {
    content: '';
    position: absolute;
    top: 2px; left: 0; right: 0;
    height: 4px;
    background: rgba(255,255,255,0.4);
}

/* ═══════════════════════════════════════════
   QUESTION CARD — RPG DIALOGUE BOX
═══════════════════════════════════════════ */
.question-card {
    background: var(--panel-bg);
    border: 4px solid #6a6aaa;
    box-shadow:
        0 0 0 4px #000,
        inset 0 0 0 2px rgba(255,255,255,0.06),
        4px 4px 0 4px #000;
    padding: 20px 22px;
    margin-bottom: 18px;
    position: relative;
}
/* RPG corner decorations */
.question-card::before {
    content: '';
    position: absolute;
    top: -4px; left: -4px; right: -4px; bottom: -4px;
    pointer-events: none;
    background:
        /* TL corner */
        linear-gradient(#000 4px, transparent 4px) top left / 8px 8px no-repeat,
        linear-gradient(to right, #000 4px, transparent 4px) top left / 8px 8px no-repeat,
        /* TR corner */
        linear-gradient(#000 4px, transparent 4px) top right / 8px 8px no-repeat,
        linear-gradient(to left, #000 4px, transparent 4px) top right / 8px 8px no-repeat,
        /* BL corner */
        linear-gradient(to top, #000 4px, transparent 4px) bottom left / 8px 8px no-repeat,
        linear-gradient(to right, #000 4px, transparent 4px) bottom left / 8px 8px no-repeat,
        /* BR corner */
        linear-gradient(to top, #000 4px, transparent 4px) bottom right / 8px 8px no-repeat,
        linear-gradient(to left, #000 4px, transparent 4px) bottom right / 8px 8px no-repeat;
}
/* Scrolling text indicator */
.question-card::after {
    content: '▼';
    position: absolute;
    bottom: 8px; right: 14px;
    font-size: 8px;
    color: var(--white);
    animation: scrollIndicator 1s steps(2) infinite;
    font-family: var(--font-pixel);
}
@keyframes scrollIndicator {
    0%, 100% { opacity: 1; }
    50%       { opacity: 0; }
}

.q-eyebrow {
    font-family: var(--font-pixel);
    font-size: 7px;
    font-weight: 400;
    color: var(--cyan);
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 12px;
    display: flex; align-items: center; gap: 8px;
    text-shadow: 1px 1px 0 #000, 2px 2px 0 rgba(0,150,200,0.3);
}
.q-eyebrow i {
    color: var(--gold);
    text-shadow: 0 0 6px var(--gold-glow);
}

.q-text {
    font-family: var(--font-vt);
    font-size: 22px;
    font-weight: 400;
    line-height: 1.5;
    color: var(--cream);
    position: relative; z-index: 1;
    text-shadow: 1px 1px 0 rgba(0,0,0,0.8);
    letter-spacing: 0.5px;
}

/* ── HINT ROW ── */
.hint-row {
    display: flex; align-items: center; gap: 10px;
    margin-top: 14px; padding-top: 12px;
    border-top: 2px solid rgba(74,74,138,0.4);
    font-family: var(--font-pixel);
    font-size: 6px;
    color: var(--muted);
}
.hint-row .hint-dots { display: flex; gap: 4px; flex-wrap: wrap; }
.hint-dot {
    width: 22px; height: 22px;
    background: var(--panel-d);
    border: 2px solid var(--px-border-d);
    box-shadow: 1px 1px 0 #000;
    display: flex; align-items: center; justify-content: center;
    font-family: var(--font-pixel);
    font-size: 8px;
    color: rgba(255,255,255,0.15);
}

/* ═══════════════════════════════════════════
   ANSWER ZONE — RPG INVENTORY SLOT
═══════════════════════════════════════════ */
.answer-section { margin-bottom: 16px; }
.answer-label {
    font-family: var(--font-pixel);
    font-size: 6px;
    font-weight: 400;
    color: var(--green);
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 8px;
    display: flex; align-items: center; gap: 8px;
    text-shadow: 1px 1px 0 #000, 0 0 6px var(--green-glow);
}
.answer-zone {
    min-height: 64px;
    display: flex; flex-wrap: wrap; gap: 6px;
    align-items: center;
    padding: 12px 14px;
    background: rgba(10, 40, 10, 0.6);
    border: 4px solid rgba(56,200,80,0.4);
    box-shadow:
        0 0 0 2px #000,
        inset 0 0 12px rgba(0,0,0,0.6),
        inset 0 0 0 1px rgba(56,200,80,0.1),
        2px 2px 0 #000;
    transition: border-color .2s, box-shadow .2s, background .2s;
    position: relative;
}
/* Dashed pattern overlay */
.answer-zone::before {
    content: '';
    position: absolute;
    inset: 4px;
    border: 2px dashed rgba(56,200,80,0.2);
    pointer-events: none;
}
.answer-zone.drag-over {
    border-color: var(--green) !important;
    background: rgba(10, 60, 10, 0.7) !important;
    box-shadow: 0 0 0 2px #000, 0 0 20px var(--green-glow), inset 0 0 12px rgba(0,0,0,0.4), 2px 2px 0 #000 !important;
}
.answer-zone.correct {
    border-color: var(--gold) !important;
    background: rgba(40, 30, 0, 0.7) !important;
    box-shadow: 0 0 0 2px #000, 0 0 20px var(--gold-glow), 2px 2px 0 #000 !important;
    animation: correctPulse .5s steps(4) ease;
}
.answer-zone.wrong-ans {
    border-color: var(--red) !important;
    background: rgba(40, 0, 0, 0.7) !important;
    animation: wrongShake .4s steps(4) ease;
}
@keyframes correctPulse { 0%{transform:scale(1)} 40%{transform:scale(1.02)} 100%{transform:scale(1)} }
@keyframes wrongShake   { 0%,100%{transform:translateX(0)} 25%{transform:translateX(-4px)} 75%{transform:translateX(4px)} }

/* ── PLACED LETTERS — Green Stone Blocks ── */
.placed-letter {
    padding: 8px 12px;
    background: linear-gradient(180deg, #2a6e30 0%, #1a4e20 50%, #0e3212 100%);
    border: 3px solid #38c850;
    box-shadow:
        0 0 0 2px #000,
        inset 0 2px 0 rgba(255,255,255,0.2),
        inset 0 -2px 0 rgba(0,0,0,0.4),
        3px 3px 0 #000;
    font-family: var(--font-pixel);
    font-size: 11px;
    font-weight: 400;
    color: #aaffaa;
    cursor: grab;
    transition: transform .1s steps(2), box-shadow .1s;
    user-select: none;
    text-shadow: 1px 1px 0 #000, 0 0 6px rgba(100,255,100,0.4);
    letter-spacing: 1px;
    image-rendering: pixelated;
}
.placed-letter:hover {
    transform: translateY(-2px);
    box-shadow: 0 0 0 2px #000, inset 0 2px 0 rgba(255,255,255,0.3), 3px 5px 0 #000, 0 0 10px var(--green-glow);
}
.placed-letter:active { cursor: grabbing; transform: translateY(1px); }
.placed-space {
    padding: 8px 6px;
    background: var(--panel-d);
    border: 3px solid var(--px-border-d);
    box-shadow: 0 0 0 2px #000, 2px 2px 0 #000;
    font-family: var(--font-pixel);
    font-size: 6px;
    color: var(--muted);
    cursor: grab;
    letter-spacing: 0;
}

/* ═══════════════════════════════════════════
   LETTER BANK — RPG ITEM BAG
═══════════════════════════════════════════ */
.bank-section { margin-bottom: 20px; }
.bank-label {
    font-family: var(--font-pixel);
    font-size: 6px;
    font-weight: 400;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 8px;
    display: flex; align-items: center; gap: 8px;
    text-shadow: 1px 1px 0 #000;
}
.letter-bank {
    min-height: 62px;
    display: flex; flex-wrap: wrap; gap: 6px;
    align-items: center;
    padding: 12px 14px;
    background: rgba(20,20,40,0.7);
    border: 4px solid var(--px-border);
    box-shadow:
        0 0 0 2px #000,
        inset 0 0 16px rgba(0,0,0,0.5),
        2px 2px 0 #000;
    transition: border-color .2s;
}
.letter-bank.drag-over {
    border-color: var(--purple-l);
    background: rgba(40, 20, 60, 0.7);
    box-shadow: 0 0 0 2px #000, 0 0 16px rgba(136,68,204,0.3), 2px 2px 0 #000;
}

/* ── BANK LETTER TILES — Blue Stone Blocks ── */
.bank-letter {
    padding: 10px 13px;
    background: linear-gradient(180deg, #1e4a88 0%, #0e2e60 50%, #071840 100%);
    border: 3px solid #4488ff;
    box-shadow:
        0 0 0 2px #000,
        inset 0 2px 0 rgba(255,255,255,0.2),
        inset 0 -2px 0 rgba(0,0,0,0.5),
        3px 3px 0 #000;
    font-family: var(--font-pixel);
    font-size: 13px;
    font-weight: 400;
    color: #aaddff;
    cursor: grab;
    transition: transform .1s steps(2), box-shadow .1s, opacity .15s;
    user-select: none;
    text-shadow: 1px 1px 0 #000, 0 0 6px rgba(100,180,255,0.4);
    letter-spacing: 1px;
    touch-action: none;
    image-rendering: pixelated;
}
.bank-letter:hover {
    transform: translateY(-3px);
    box-shadow: 0 0 0 2px #000, inset 0 2px 0 rgba(255,255,255,0.3), 3px 6px 0 #000, 0 0 12px rgba(68,136,255,0.4);
    color: #cceeff;
}
.bank-letter:active { cursor: grabbing; transform: translateY(1px); }
.bank-letter.dragging { opacity: .3; transform: scale(.9); }
.bank-letter.space-tile {
    padding: 10px 8px;
    font-size: 6px;
    color: var(--muted);
    border-color: var(--px-border-d);
    background: linear-gradient(180deg, #252545 0%, #151530 100%);
}

/* ═══════════════════════════════════════════
   ACTION BUTTONS — RPG Style
═══════════════════════════════════════════ */
.action-row {
    display: flex; gap: 10px;
    justify-content: center;
    flex-wrap: wrap;
    margin-bottom: 10px;
}
.btn-action {
    padding: 12px 22px;
    border: 3px solid transparent;
    font-family: var(--font-pixel);
    font-weight: 400;
    font-size: 8px;
    cursor: pointer;
    transition: transform .1s steps(2), box-shadow .1s;
    display: flex; align-items: center; gap: 8px;
    letter-spacing: 0.5px;
    position: relative;
    image-rendering: pixelated;
    text-transform: uppercase;
}
.btn-action::after {
    content: '';
    position: absolute;
    inset: 1px;
    border-top: 1px solid rgba(255,255,255,0.15);
    border-left: 1px solid rgba(255,255,255,0.15);
    pointer-events: none;
}
.btn-action:hover  { transform: translateY(-2px); }
.btn-action:active { transform: translateY(2px); }

/* Submit — Green RPG confirm button */
.btn-submit {
    background: linear-gradient(180deg, #2a8a3a 0%, #1a6a2a 60%, #0a4a1a 100%);
    border-color: var(--green);
    color: #aaffaa;
    box-shadow: 0 0 0 2px #000, 0 4px 0 #000, 0 4px 0 #0a4a1a, 4px 4px 0 #000;
    text-shadow: 1px 1px 0 #000, 0 0 8px var(--green-glow);
}
.btn-submit:hover { box-shadow: 0 0 0 2px #000, 0 4px 0 #000, 0 4px 0 #0a4a1a, 4px 4px 0 #000, 0 0 16px var(--green-glow); }
.btn-submit:active { box-shadow: 0 0 0 2px #000, 0 1px 0 #000, 1px 1px 0 #000; }

/* Clear — Gray RPG button */
.btn-clear {
    background: linear-gradient(180deg, #3a3a5a 0%, #252545 60%, #151530 100%);
    border-color: var(--px-border);
    color: var(--muted);
    box-shadow: 0 0 0 2px #000, 0 4px 0 #000, 4px 4px 0 #000;
    text-shadow: 1px 1px 0 #000;
}
.btn-clear:hover { color: var(--white); border-color: var(--px-border-l); }

/* Skip — Red dashed */
.btn-skip {
    background: transparent;
    border: 3px dashed rgba(100,100,150,0.5);
    color: var(--muted);
    box-shadow: none;
    text-shadow: 1px 1px 0 #000;
}
.btn-skip:hover { color: var(--red-l); border-color: var(--red); }

/* ═══════════════════════════════════════════
   FEEDBACK TOAST — RPG Message Box
═══════════════════════════════════════════ */
#feedback {
    position: fixed;
    bottom: 24px; left: 50%;
    transform: translateX(-50%) translateY(80px);
    background: var(--panel-d);
    border: 3px solid var(--px-border);
    box-shadow: 0 0 0 2px #000, 4px 4px 0 #000;
    padding: 10px 22px;
    font-family: var(--font-pixel);
    font-size: 8px;
    font-weight: 400;
    z-index: 300;
    transition: transform .25s steps(4), opacity .25s;
    opacity: 0;
    white-space: nowrap;
    letter-spacing: 0.5px;
    text-shadow: 1px 1px 0 #000;
}
#feedback.show { transform: translateX(-50%) translateY(0); opacity: 1; }
#feedback.ok   { border-color: var(--green); color: var(--green); box-shadow: 0 0 0 2px #000, 4px 4px 0 #000, 0 0 12px var(--green-glow); }
#feedback.bad  { border-color: var(--red); color: var(--red-l); }
#feedback.skip { border-color: var(--gold); color: var(--gold); box-shadow: 0 0 0 2px #000, 4px 4px 0 #000, 0 0 12px var(--gold-glow); }

/* ═══════════════════════════════════════════
   RESULT OVERLAY — RPG VICTORY SCREEN
═══════════════════════════════════════════ */
#result-overlay {
    display: none;
    position: fixed; inset: 0;
    background: radial-gradient(ellipse at center, #1a2e0a 0%, #0a1a04 40%, #050d02 100%);
    z-index: 200;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 30px;
    animation: overlayIn .4s steps(6) ease-out;
}
#result-overlay.show { display: flex; }
@keyframes overlayIn { from{opacity:0;transform:scale(.96)} to{opacity:1;transform:scale(1)} }

/* Stars effect on result screen */
#result-overlay::before {
    content: '';
    position: absolute;
    inset: 0;
    background:
        radial-gradient(2px 2px at 10% 15%, #fff 100%, transparent),
        radial-gradient(2px 2px at 25% 8%, #ffffcc 100%, transparent),
        radial-gradient(1px 1px at 40% 20%, #fff 100%, transparent),
        radial-gradient(2px 2px at 60% 12%, #ccffff 100%, transparent),
        radial-gradient(1px 1px at 75% 18%, #fff 100%, transparent),
        radial-gradient(2px 2px at 88% 8%, #ffffcc 100%, transparent),
        radial-gradient(1px 1px at 5% 35%, #fff 100%, transparent),
        radial-gradient(2px 2px at 50% 5%, #fff 100%, transparent);
    pointer-events: none;
    opacity: 0.6;
}

.result-emoji {
    font-size: 56px;
    margin-bottom: 16px;
    animation: trophyBounce .6s cubic-bezier(.34,1.56,.64,1);
    filter: drop-shadow(0 0 16px var(--gold-glow));
}
@keyframes trophyBounce { from{transform:scale(0)rotate(-20deg)} to{transform:scale(1)rotate(0)} }

.result-title {
    font-family: var(--font-pixel);
    font-size: 22px;
    font-weight: 400;
    color: var(--gold);
    margin-bottom: 8px;
    text-shadow: 2px 2px 0 var(--gold-d), 4px 4px 0 #000, 0 0 20px var(--gold-glow);
    letter-spacing: 2px;
    animation: titleFlicker 0.1s steps(2) infinite;
}
@keyframes titleFlicker {
    0%, 90%, 100% { opacity: 1; }
    95%            { opacity: 0.9; }
}

.result-sub {
    font-family: var(--font-vt);
    color: rgba(220,255,220,0.85);
    font-size: 20px;
    margin-bottom: 24px;
    text-shadow: 1px 1px 0 #000;
    letter-spacing: 1px;
}

.result-stats {
    display: flex; gap: 12px;
    justify-content: center;
    margin-bottom: 22px;
    flex-wrap: wrap;
}
.result-stat {
    background: rgba(0,0,0,0.5);
    border: 3px solid var(--px-border);
    box-shadow: 0 0 0 2px #000, 3px 3px 0 #000;
    padding: 14px 20px;
    min-width: 100px;
    position: relative;
}
.result-stat::after {
    content: '';
    position: absolute;
    inset: 1px;
    border-top: 1px solid rgba(255,255,255,0.08);
    border-left: 1px solid rgba(255,255,255,0.08);
}
.result-stat .rv {
    font-family: var(--font-pixel);
    font-size: 24px;
    font-weight: 400;
    color: var(--gold);
    display: block;
    text-shadow: 2px 2px 0 var(--gold-d), 0 0 10px var(--gold-glow);
}
.result-stat .rl {
    font-family: var(--font-pixel);
    font-size: 5px;
    color: rgba(255,255,255,0.5);
    font-weight: 400;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 4px;
    display: block;
}

.xp-badge {
    background: rgba(20,10,0,0.7);
    border: 3px solid var(--gold);
    box-shadow: 0 0 0 2px #000, 3px 3px 0 #000, 0 0 16px var(--gold-glow);
    color: var(--gold);
    font-family: var(--font-pixel);
    font-size: 12px;
    font-weight: 400;
    padding: 10px 24px;
    margin-bottom: 22px;
    letter-spacing: 1px;
    text-shadow: 1px 1px 0 #000, 0 0 8px var(--gold-glow);
    animation: xpPulse 1s steps(4) infinite;
}
@keyframes xpPulse {
    0%, 100% { box-shadow: 0 0 0 2px #000, 3px 3px 0 #000, 0 0 16px var(--gold-glow); }
    50%       { box-shadow: 0 0 0 2px #000, 3px 3px 0 #000, 0 0 28px var(--gold-glow); }
}

.result-btns { display: flex; gap: 10px; flex-wrap: wrap; justify-content: center; }
.btn-result {
    padding: 13px 28px;
    border: 3px solid transparent;
    font-family: var(--font-pixel);
    font-weight: 400;
    font-size: 8px;
    cursor: pointer;
    transition: transform .1s steps(2);
    letter-spacing: 0.5px;
    text-transform: uppercase;
    text-shadow: 1px 1px 0 #000;
    position: relative;
}
.btn-result::after {
    content: '';
    position: absolute;
    inset: 1px;
    border-top: 1px solid rgba(255,255,255,0.15);
    border-left: 1px solid rgba(255,255,255,0.15);
}
.btn-result:hover  { transform: translateY(-2px); }
.btn-result:active { transform: translateY(2px); }

.btn-green {
    background: linear-gradient(180deg, #2a8a3a 0%, #1a6a2a 60%, #0a4a1a 100%);
    border-color: var(--green);
    color: #aaffaa;
    box-shadow: 0 0 0 2px #000, 0 4px 0 #000, 4px 4px 0 #000;
}
.btn-white {
    background: linear-gradient(180deg, #3a3a5a 0%, #252545 60%, #151530 100%);
    border-color: var(--px-border-l);
    color: var(--cream);
    box-shadow: 0 0 0 2px #000, 0 4px 0 #000, 4px 4px 0 #000;
}

/* ── CONFETTI — Pixel squares ── */
.confetti-dot {
    position: fixed;
    pointer-events: none;
    z-index: 999;
    border-radius: 0 !important;
    image-rendering: pixelated;
    animation: confettiFall var(--dur,2s) var(--del,0s) ease-in forwards;
}
@keyframes confettiFall {
    0%   { transform: translateY(-20px) rotate(0deg); opacity: 1; }
    100% { transform: translateY(110vh) rotate(720deg); opacity: 0; }
}

/* ── RESPONSIVE ── */
@media (max-width: 600px) {
    .bank-letter  { font-size: 11px; padding: 8px 10px; }
    .placed-letter{ font-size: 9px;  padding: 7px 9px; }
    .q-text { font-size: 18px; }
    .result-title { font-size: 14px; }
    .game-logo { font-size: 8px; }
    .hud-chip .val { font-size: 11px; }
    .btn-action { font-size: 6px; padding: 10px 14px; }
}

/* ── SCANLINE OVERLAY (optional CRT effect) ── */
body::after {
    content: '';
    position: fixed;
    inset: 0;
    z-index: 9999;
    pointer-events: none;
    background: repeating-linear-gradient(
        0deg,
        transparent,
        transparent 2px,
        rgba(0,0,0,0.03) 2px,
        rgba(0,0,0,0.03) 4px
    );
    animation: scanlines 8s linear infinite;
}
@keyframes scanlines {
    0%   { background-position: 0 0; }
    100% { background-position: 0 400px; }
}

/* ── INSTRUCTIONS OVERLAY ── */
#inst-overlay {
    position:fixed;inset:0;z-index:400;
    background:rgba(4,4,18,.97);
    display:flex;flex-direction:column;
    align-items:center;justify-content:center;
    padding:24px 18px;text-align:center;
    animation:fadeIn .3s ease-out;
}
@keyframes fadeIn{from{opacity:0}to{opacity:1}}
.inst-win {
    background:var(--panel-bg);
    border:4px solid var(--px-border);
    box-shadow:0 0 0 4px #000,4px 4px 0 4px #000,inset 0 0 0 2px rgba(255,255,255,.06);
    padding:26px 28px;max-width:580px;width:100%;
}
.inst-win-title {
    font-family:var(--font-pixel);font-size:clamp(10px,2.5vw,16px);
    color:var(--gold);text-shadow:2px 2px 0 var(--gold-d),0 0 16px var(--gold-glow);
    letter-spacing:2px;margin-bottom:6px;
}
.inst-win-sub {
    font-family:var(--font-vt);font-size:18px;
    color:var(--muted);margin-bottom:20px;
}
.inst-cards {
    display:grid;grid-template-columns:1fr 1fr;
    gap:10px;margin-bottom:20px;
}
.inst-card {
    background:rgba(0,0,0,.5);
    border:3px solid var(--px-border-d);
    border-top:3px solid var(--px-border);
    box-shadow:2px 2px 0 #000;
    padding:12px 14px;text-align:left;
}
.inst-card .ic-ico{font-size:20px;display:block;margin-bottom:6px;}
.inst-card .ic-txt {
    font-family:var(--font-vt);font-size:clamp(14px,2.2vw,17px);
    color:rgba(220,220,255,.85);line-height:1.4;
}
.inst-card .ic-txt b{color:var(--gold);}
.inst-tip {
    font-family:var(--font-vt);font-size:15px;
    color:var(--muted);margin-bottom:20px;line-height:1.5;
    padding:10px 14px;
    background:rgba(0,0,0,.4);
    border:2px dashed rgba(74,74,138,.5);
}
.inst-tip b{color:var(--cyan);}
.inst-start-btn {
    font-family:var(--font-pixel);font-size:clamp(8px,1.8vw,11px);
    letter-spacing:2px;padding:14px 36px;border:none;
    background:linear-gradient(180deg,#2a8a3a 0%,#1a6a2a 60%,#0a4a1a 100%);
    border:3px solid var(--green);
    color:#aaffaa;cursor:pointer;
    box-shadow:0 0 0 2px #000,0 4px 0 #000,4px 4px 0 #000,0 0 20px var(--green-glow);
    text-shadow:1px 1px 0 #000,0 0 8px var(--green-glow);
    animation:instBtnPulse 1.2s ease-in-out infinite alternate;
}
@keyframes instBtnPulse{
    from{box-shadow:0 0 0 2px #000,0 4px 0 #000,4px 4px 0 #000,0 0 14px var(--green-glow);}
    to  {box-shadow:0 0 0 2px #000,0 4px 0 #000,4px 4px 0 #000,0 0 30px var(--green-glow);}
}
.inst-start-btn:hover{transform:translateY(-2px);}
.inst-start-btn:active{transform:translateY(2px);}
@media(max-width:520px){
    .inst-cards{grid-template-columns:1fr;}
    .inst-win{padding:18px 14px;}
}

/* ── TIMER BAR ── */
#timer-wrap {
    position:sticky;top:0;z-index:99;
    height:10px;background:rgba(4,4,18,.9);
    border-bottom:2px solid rgba(74,74,138,.3);
}
#timer-fill {
    height:100%;
    background:linear-gradient(90deg,var(--green-d),var(--green));
    box-shadow:0 0 8px var(--green-glow);
    transition:width 1s linear,background .3s,box-shadow .3s;
}
#timer-fill.warn   {background:linear-gradient(90deg,#8a6000,var(--gold));box-shadow:0 0 10px var(--gold-glow);}
#timer-fill.danger {
    background:linear-gradient(90deg,#6a0000,var(--red));
    box-shadow:0 0 12px rgba(224,48,48,.7);
    animation:timerPanic .25s steps(2) infinite;
}
@keyframes timerPanic{0%,100%{opacity:1;}50%{opacity:.6;}}

/* ── LIVES DISPLAY ── */
.hud-chip.lives{border-color:#6a1010;min-width:80px;}
.hud-chip.lives .val{font-size:18px;letter-spacing:2px;}

/* ── COMBO BADGE ── */
#combo-badge {
    position:fixed;top:80px;right:16px;z-index:200;
    font-family:var(--font-pixel);font-size:10px;
    color:var(--gold);text-shadow:0 0 10px var(--gold-glow),2px 2px 0 #000;
    background:rgba(10,8,0,.85);
    border:3px solid var(--gold-d);
    box-shadow:0 0 0 2px #000,2px 2px 0 #000,0 0 16px var(--gold-glow);
    padding:8px 14px;letter-spacing:1px;
    opacity:0;transform:scale(.8);
    transition:opacity .25s,transform .25s;
}
#combo-badge.show{opacity:1;transform:scale(1);}

/* ── KEYBOARD HINT ── */
.kbd-hint {
    font-family:var(--font-pixel);font-size:6px;
    color:var(--muted);text-align:center;
    margin-top:10px;letter-spacing:.5px;line-height:1.8;
}
.kbd-hint b{color:var(--cyan);font-weight:400;}
.kbd-key {
    display:inline-block;
    background:var(--panel-d);
    border:2px solid var(--px-border);
    box-shadow:0 2px 0 #000,2px 2px 0 #000;
    padding:1px 5px;font-size:5px;
    margin:0 2px;vertical-align:middle;
    color:var(--cream);
}

/* ── SCREEN FLASH ── */
#screen-flash {
    position:fixed;inset:0;z-index:350;
    pointer-events:none;opacity:0;
}
#screen-flash.correct{background:rgba(56,200,80,.15);}
#screen-flash.wrong  {background:rgba(224,48,48,.2);}
#screen-flash.on     {opacity:1;transition:none;}
#screen-flash        {transition:opacity .35s;}

/* ── TYPING CURSOR in answer zone ── */
.answer-zone.typing-active::after {
    content:'|';
    font-family:var(--font-pixel);font-size:14px;
    color:var(--cyan);animation:blink .7s steps(1) infinite;
    padding:0 2px;align-self:center;
}
@keyframes blink{0%,100%{opacity:1;}50%{opacity:0;}}

</style>
</head>
<body>

<!-- ══════════════════════════════════════════
     PIXEL WORLD BACKGROUND
══════════════════════════════════════════ -->
<!-- ── INSTRUCTIONS OVERLAY ── -->
<div id="inst-overlay">
  <div class="inst-win">
    <div class="inst-win-title">⚔ WORD SCRAMBLE QUEST</div>
    <div class="inst-win-sub">— HOW TO PLAY —</div>
    <div class="inst-cards">
      <div class="inst-card">
        <span class="ic-ico">⌨️</span>
        <div class="ic-txt"><b>TYPE</b> letters on your keyboard — they snap into the answer zone automatically!</div>
      </div>
      <div class="inst-card">
        <span class="ic-ico">🖱️</span>
        <div class="ic-txt"><b>CLICK</b> or <b>DRAG</b> tiles to move them between the bank and answer area.</div>
      </div>
      <div class="inst-card">
        <span class="ic-ico">⏱️</span>
        <div class="ic-txt"><b>TIMER</b> per question! Run out of time and you lose a <b>❤️ Life</b>.</div>
      </div>
      <div class="inst-card">
        <span class="ic-ico">🔥</span>
        <div class="ic-txt">Chain correct answers for a <b>COMBO</b> bonus multiplier!</div>
      </div>
    </div>
    <div class="inst-tip">
      <b>⌨ Keyboard shortcuts:</b><br>
      Type letters to place &nbsp;·&nbsp; <b>Backspace</b> = remove last &nbsp;·&nbsp; <b>Enter</b> = submit
    </div>
    <button class="inst-start-btn" id="inst-start-btn">[ START QUEST ]</button>
  </div>
</div>

<!-- ── SCREEN FLASH ── -->
<div id="screen-flash"></div>

<div id="pixel-world">
    <div class="px-sky"></div>
    <div class="px-stars"></div>
    <div class="px-stars-2"></div>
    <div class="px-moon"></div>
    <div class="px-cloud px-cloud-1"></div>
    <div class="px-cloud px-cloud-2"></div>
    <div class="px-cloud px-cloud-3"></div>
    <div class="px-mountains-far"></div>
    <div class="px-mountains-near"></div>

    <!-- Pixel Trees -->
    <div class="px-trees">
        <!-- Tree set using inline SVG pixel trees -->
        <svg width="100%" height="70" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="xMidYMax meet" style="position:absolute;bottom:0;left:0;right:0;">
            <!-- Dark pine tree silhouettes -->
            <rect x="20"  y="40" width="8"  height="20" fill="#0e1a08"/>
            <polygon points="24,10 10,42 38,42" fill="#1a3010"/>
            <polygon points="24,2 12,30 36,30" fill="#203a14"/>

            <rect x="68"  y="44" width="6"  height="16" fill="#0e1a08"/>
            <polygon points="71,18 60,46 82,46" fill="#1a3010"/>
            <polygon points="71,10 62,34 80,34" fill="#203a14"/>

            <rect x="110" y="42" width="8"  height="18" fill="#0e1a08"/>
            <polygon points="114,12 100,44 128,44" fill="#162808"/>
            <polygon points="114,4 102,32 126,32" fill="#1e3410"/>

            <rect x="160" y="46" width="6"  height="14" fill="#0e1a08"/>
            <polygon points="163,22 154,48 172,48" fill="#1a3010"/>
            <polygon points="163,14 156,36 170,36" fill="#203a14"/>

            <rect x="200" y="40" width="10" height="22" fill="#0e1a08"/>
            <polygon points="205,8 188,44 222,44" fill="#162808"/>
            <polygon points="205,0 190,30 220,30" fill="#1e3410"/>

            <rect x="255" y="44" width="6"  height="16" fill="#0e1a08"/>
            <polygon points="258,18 248,46 268,46" fill="#1a3010"/>
            <polygon points="258,10 250,34 266,34" fill="#203a14"/>

            <rect x="300" y="41" width="8"  height="20" fill="#0e1a08"/>
            <polygon points="304,10 290,44 318,44" fill="#162808"/>
            <polygon points="304,2  292,32 316,32" fill="#1e3410"/>

            <rect x="345" y="45" width="6"  height="15" fill="#0e1a08"/>
            <polygon points="348,20 339,47 357,47" fill="#1a3010"/>
            <polygon points="348,12 341,35 355,35" fill="#203a14"/>

            <rect x="390" y="42" width="9"  height="19" fill="#0e1a08"/>
            <polygon points="394,11 380,44 408,44" fill="#162808"/>
            <polygon points="394,3  382,30 406,30" fill="#1e3410"/>

            <rect x="440" y="44" width="7"  height="17" fill="#0e1a08"/>
            <polygon points="443,16 432,46 454,46" fill="#1a3010"/>
            <polygon points="443,8  434,32 452,32" fill="#203a14"/>

            <rect x="490" y="40" width="8"  height="22" fill="#0e1a08"/>
            <polygon points="494,8 480,44 508,44" fill="#162808"/>
            <polygon points="494,0 482,30 506,30" fill="#1e3410"/>

            <rect x="540" y="43" width="7"  height="18" fill="#0e1a08"/>
            <polygon points="543,14 532,46 554,46" fill="#1a3010"/>
            <polygon points="543,6  534,33 552,33" fill="#203a14"/>

            <rect x="588" y="41" width="8"  height="20" fill="#0e1a08"/>
            <polygon points="592,10 578,44 606,44" fill="#162808"/>
            <polygon points="592,2  580,32 604,32" fill="#1e3410"/>

            <rect x="638" y="44" width="6"  height="16" fill="#0e1a08"/>
            <polygon points="641,19 631,46 651,46" fill="#1a3010"/>
            <polygon points="641,11 633,35 649,35" fill="#203a14"/>

            <rect x="680" y="42" width="9"  height="20" fill="#0e1a08"/>
            <polygon points="684,10 670,44 698,44" fill="#162808"/>
            <polygon points="684,2  672,31 696,31" fill="#1e3410"/>

            <rect x="730" y="45" width="6"  height="15" fill="#0e1a08"/>
            <polygon points="733,19 723,47 743,47" fill="#1a3010"/>
            <polygon points="733,11 725,35 741,35" fill="#203a14"/>

            <rect x="775" y="41" width="8"  height="20" fill="#0e1a08"/>
            <polygon points="779,9  765,44 793,44" fill="#162808"/>
            <polygon points="779,1  767,30 791,30" fill="#1e3410"/>

            <rect x="828" y="43" width="7"  height="18" fill="#0e1a08"/>
            <polygon points="831,14 820,46 842,46" fill="#1a3010"/>
            <polygon points="831,6  822,33 840,33" fill="#203a14"/>

            <rect x="875" y="40" width="9"  height="22" fill="#0e1a08"/>
            <polygon points="879,8  865,44 893,44" fill="#162808"/>
            <polygon points="879,0  867,30 891,30" fill="#1e3410"/>

            <rect x="924" y="44" width="6"  height="16" fill="#0e1a08"/>
            <polygon points="927,18 917,46 937,46" fill="#1a3010"/>
            <polygon points="927,10 919,34 935,34" fill="#203a14"/>

            <rect x="968" y="42" width="8"  height="20" fill="#0e1a08"/>
            <polygon points="972,10 958,44 986,44" fill="#162808"/>
            <polygon points="972,2  960,32 984,32" fill="#1e3410"/>

            <rect x="1018" y="44" width="7"  height="17" fill="#0e1a08"/>
            <polygon points="1021,14 1010,46 1032,46" fill="#1a3010"/>
            <polygon points="1021,6  1012,33 1030,33" fill="#203a14"/>

            <rect x="1065" y="41" width="8"  height="21" fill="#0e1a08"/>
            <polygon points="1069,9  1055,44 1083,44" fill="#162808"/>
            <polygon points="1069,1  1057,31 1081,31" fill="#1e3410"/>

            <rect x="1115" y="43" width="6"  height="18" fill="#0e1a08"/>
            <polygon points="1118,16 1108,45 1128,45" fill="#1a3010"/>
            <polygon points="1118,8  1110,33 1126,33" fill="#203a14"/>

            <rect x="1160" y="42" width="9"  height="20" fill="#0e1a08"/>
            <polygon points="1164,10 1150,44 1178,44" fill="#162808"/>
            <polygon points="1164,2  1152,32 1176,32" fill="#1e3410"/>

            <rect x="1210" y="44" width="7"  height="17" fill="#0e1a08"/>
            <polygon points="1213,18 1203,46 1223,46" fill="#1a3010"/>
            <polygon points="1213,10 1205,34 1221,34" fill="#203a14"/>

            <rect x="1258" y="40" width="8"  height="22" fill="#0e1a08"/>
            <polygon points="1262,8  1248,44 1276,44" fill="#162808"/>
            <polygon points="1262,0  1250,30 1274,30" fill="#1e3410"/>

            <rect x="1308" y="43" width="6"  height="18" fill="#0e1a08"/>
            <polygon points="1311,14 1301,46 1321,46" fill="#1a3010"/>
            <polygon points="1311,6  1303,33 1319,33" fill="#203a14"/>

            <rect x="1355" y="42" width="8"  height="20" fill="#0e1a08"/>
            <polygon points="1359,11 1345,44 1373,44" fill="#162808"/>
            <polygon points="1359,3  1347,32 1371,32" fill="#1e3410"/>

            <rect x="1405" y="44" width="6"  height="16" fill="#0e1a08"/>
            <polygon points="1408,18 1398,46 1418,46" fill="#1a3010"/>
            <polygon points="1408,10 1400,34 1416,34" fill="#203a14"/>

            <rect x="1450" y="41" width="9"  height="21" fill="#0e1a08"/>
            <polygon points="1454,9  1440,44 1468,44" fill="#162808"/>
            <polygon points="1454,1  1442,31 1466,31" fill="#1e3410"/>

            <rect x="1500" y="44" width="7"  height="17" fill="#0e1a08"/>
            <polygon points="1503,16 1493,46 1513,46" fill="#1a3010"/>
            <polygon points="1503,8  1495,33 1511,33" fill="#203a14"/>

            <rect x="1548" y="42" width="8"  height="20" fill="#0e1a08"/>
            <polygon points="1552,10 1538,44 1566,44" fill="#162808"/>
            <polygon points="1552,2  1540,32 1564,32" fill="#1e3410"/>
        </svg>
    </div>

    <!-- Grass / Ground layers -->
    <div class="px-grass-base"></div>
    <div class="px-grass"></div>
    <div class="px-dirt"></div>
    <div class="px-dirt-2"></div>

    <!-- Fireflies -->
    <div class="px-firefly" style="left:15%;bottom:120px;--dur:7s;--del:0s;--mx:25px;--my:-40px;"></div>
    <div class="px-firefly" style="left:30%;bottom:100px;--dur:5s;--del:1s;--mx:-20px;--my:-35px;background:#ffffaa;"></div>
    <div class="px-firefly" style="left:55%;bottom:130px;--dur:8s;--del:2s;--mx:30px;--my:-50px;"></div>
    <div class="px-firefly" style="left:70%;bottom:110px;--dur:6s;--del:0.5s;--mx:-25px;--my:-30px;background:#aaffff;"></div>
    <div class="px-firefly" style="left:85%;bottom:125px;--dur:9s;--del:3s;--mx:20px;--my:-45px;"></div>
    <div class="px-firefly" style="left:45%;bottom:95px;--dur:5.5s;--del:1.5s;--mx:-30px;--my:-25px;background:#ffffaa;"></div>
    <div class="px-firefly" style="left:8%;bottom:115px;--dur:7.5s;--del:2.5s;--mx:15px;--my:-38px;background:#aaffff;"></div>
    <div class="px-firefly" style="left:92%;bottom:105px;--dur:6.5s;--del:4s;--mx:-18px;--my:-42px;"></div>
</div>

<!-- ═══════════════════════════════════════════
     GAME CONTENT
═══════════════════════════════════════════ -->
<div id="game-content">

<!-- ── HEADER ── -->
<div class="game-header">
    <div class="game-logo">
        <span class="logo-icon">⚔</span>
        PinnaQuest
    </div>
    <div class="game-title">
        ⊞ Word Scramble
        <span>&#9658; <?php echo htmlspecialchars($title); ?> &middot; <?php echo $total_items; ?> words</span>
    </div>
    <div class="hud-chips"><div class="hud-chip lives"><span class="val" id="hud-lives">❤️❤️❤️</span><span class="lbl">Lives</span></div>
        <div class="hud-chip green">
            <span class="val" id="hud-correct">0</span>
            <span class="lbl">Correct</span>
        </div>
        <div class="hud-chip gold">
            <span class="val" id="hud-score">0</span>
            <span class="lbl">Score</span>
        </div>
        <div class="hud-chip red">
            <span class="val" id="hud-skips">0</span>
            <span class="lbl">Skipped</span>
        </div>
    </div>
</div>

<!-- ── TIMER BAR ── -->
<div id="timer-wrap"><div id="timer-fill" style="width:100%"></div></div>

<!-- ── COMBO BADGE ── -->
<div id="combo-badge">🔥 ×<span id="combo-val">2</span> COMBO!</div>

<!-- ── MAIN ── -->
<div class="main">

    <!-- Progress / EXP Bar -->
    <div class="progress-bar-wrap">
        <div class="progress-header">
            <span class="progress-label">&#9670; Quest Progress</span>
            <span class="progress-label" id="progress-text">1 / <?php echo $total_items; ?></span>
        </div>
        <div class="progress-track">
            <div class="progress-fill" id="progress-fill" style="width:<?php echo round(1/$total_items*100); ?>%"></div>
        </div>
    </div>

    <!-- Question card — Dialogue Box -->
    <div class="question-card">
        <div class="q-eyebrow"><i class="fa-solid fa-scroll"></i> Unscramble the Answer</div>
        <div class="q-text" id="q-text">Loading…</div>
        <div class="hint-row">
            <span>Length:</span>
            <div class="hint-dots" id="hint-dots"><!-- JS fills --></div>
        </div>
    </div>

    <!-- Answer zone -->
    <div class="answer-section">
        <div class="answer-label"><i class="fa-solid fa-square-check"></i> Your Answer &mdash; drag letters here</div>
        <div class="answer-zone" id="answer-zone"
             ondragover="onZoneDragOver(event,'answer-zone')"
             ondragleave="onZoneDragLeave('answer-zone')"
             ondrop="onZoneDrop(event,'answer-zone')">
        </div>
    </div>

    <!-- Letter bank -->
    <div class="bank-section">
        <div class="bank-label"><i class="fa-solid fa-bag-shopping"></i> Scrambled Letters &mdash; drag to answer area</div>
        <div class="letter-bank" id="letter-bank"
             ondragover="onZoneDragOver(event,'letter-bank')"
             ondragleave="onZoneDragLeave('letter-bank')"
             ondrop="onZoneDrop(event,'letter-bank')">
        </div>
    </div>

    <!-- Keyboard hint -->
    <div class="kbd-hint">
      ⌨ <b>Type letters</b> to fill &nbsp;·&nbsp; <span class="kbd-key">⌫ Backspace</span> removes last &nbsp;·&nbsp; <span class="kbd-key">Enter</span> submits
    </div>

    <!-- Actions -->
    <div class="action-row">
        <button class="btn-action btn-clear" onclick="clearAnswer()">
            <i class="fa-solid fa-rotate-left"></i> Reset
        </button>
        <button class="btn-action btn-submit" onclick="submitAnswer()">
            <i class="fa-solid fa-sword"></i> Submit
        </button>
        <button class="btn-action btn-skip" onclick="skipQuestion()">
            Skip <i class="fa-solid fa-forward"></i>
        </button>
    </div>

</div>

<!-- ── FEEDBACK ── -->
<div id="feedback"></div>

<!-- ── RESULT OVERLAY ── -->
<div id="result-overlay">
    <div class="result-emoji">🏆</div>
    <div class="result-title">Quest Complete!</div>
    <div class="result-sub" id="res-sub">Word Scramble finished.</div>
    <div class="result-stats">
        <div class="result-stat">
            <span class="rv" id="res-correct">0</span>
            <span class="rl">Correct</span>
        </div>
        <div class="result-stat">
            <span class="rv" id="res-score">0</span>
            <span class="rl">Score</span>
        </div>
        <div class="result-stat">
            <span class="rv" id="res-accuracy">0%</span>
            <span class="rl">Accuracy</span>
        </div>
    </div>
    <div class="xp-badge" id="xp-badge">+0 XP earned!</div>
    <div class="result-btns">
        <button class="btn-result btn-white" onclick="retakeCurrentGame(this)">
            <i class="fa-solid fa-rotate-left"></i> Retake Game
        </button>
        <button class="btn-result btn-white" onclick="window.location.href='quizzes.php'">
            <i class="fa-solid fa-plus"></i> New Quest
        </button>
        <button class="btn-result btn-green" onclick="window.location.href='studentdashboard.php'">
            <i class="fa-solid fa-home"></i> Dashboard
        </button>
    </div>
</div>

</div><!-- /#game-content -->

<script>
// ══════════════════════════════════════════════════════════
//  DATA
// ══════════════════════════════════════════════════════════
const ITEMS = <?php echo json_encode($items); ?>;
const TOTAL = ITEMS.length;

// ══════════════════════════════════════════════════════════
//  GAME CONFIG
// ══════════════════════════════════════════════════════════
const TIME_PER_Q = 25;
const MAX_LIVES  = 3;
const BASE_SCORE = 300;
const TIME_BONUS = 12;

// ══════════════════════════════════════════════════════════
//  GAME STATE
// ══════════════════════════════════════════════════════════
let currentIdx   = 0;
let score        = 0;
let correctCount = 0;
let skipCount    = 0;
let wrongCount   = 0;
let lives        = MAX_LIVES;
let combo        = 0;
let timerSecs    = TIME_PER_Q;
let timerIv      = null;
let gameStarted  = false;
let draggingId   = null;
let fromZone     = null;
let letterPool   = [];
let placeCounter = 0;
let quizLog      = [];
const completionToken = (window.crypto && crypto.randomUUID)
    ? crypto.randomUUID() : `${Date.now()}-${Math.random()}`;
// ══════════════════════════════════════════════════════════
//  SCRAMBLE HELPER
// ══════════════════════════════════════════════════════════
function scramble(str) {
    // Split into individual chars including spaces
    const chars = str.split('');
    // Guarantee scrambled ≠ original
    let shuffled;
    let attempts = 0;
    do {
        shuffled = [...chars].sort(() => Math.random() - .5);
        attempts++;
    } while (shuffled.join('') === str && attempts < 20);
    return shuffled;
}


// ══════════════════════════════════════════════════════════
//  WEB AUDIO — 8-bit SFX + looping BGM
// ══════════════════════════════════════════════════════════
let _AC = null;
function getAC() {
    if (!_AC) { try { _AC = new (window.AudioContext||window.webkitAudioContext)(); } catch(e){ return null; } }
    if (_AC.state === 'suspended') _AC.resume();
    return _AC;
}
function osc(wf,f0,f1,dur,vol,startAt) {
    const ac=getAC(); if(!ac) return;
    const t=startAt??ac.currentTime;
    const o=ac.createOscillator(), g=ac.createGain();
    o.connect(g); g.connect(ac.destination);
    o.type=wf; o.frequency.setValueAtTime(f0,t);
    if(f1!==f0) o.frequency.exponentialRampToValueAtTime(f1,t+dur);
    g.gain.setValueAtTime(vol,t); g.gain.exponentialRampToValueAtTime(0.001,t+dur);
    o.start(t); o.stop(t+dur+.01);
}
function noise(dur,vol,startAt) {
    const ac=getAC(); if(!ac) return;
    const t=startAt??ac.currentTime;
    const buf=ac.createBuffer(1,ac.sampleRate*dur,ac.sampleRate);
    const d=buf.getChannelData(0); for(let i=0;i<d.length;i++) d[i]=(Math.random()*2-1)*vol;
    const src=ac.createBufferSource(),g=ac.createGain();
    src.buffer=buf; src.connect(g); g.connect(ac.destination);
    g.gain.setValueAtTime(vol,t); g.gain.exponentialRampToValueAtTime(.001,t+dur);
    src.start(t); src.stop(t+dur+.01);
}
function playSound(type) {
    const ac=getAC(); if(!ac) return;
    const t=ac.currentTime;
    if      (type==='type')     { osc('square',440,440,.055,.06); }
    else if (type==='place')    { osc('square',660,520,.08,.08); }
    else if (type==='backspace'){ osc('square',300,200,.07,.07); }
    else if (type==='correct')  {
        [330,440,550,880].forEach((f,i)=>osc('square',f,f,.18,.14,t+i*.07));
        osc('triangle',880,1200,.25,.09,t+.28);
    }
    else if (type==='wrong')    { osc('sawtooth',260,80,.32,.22); noise(.12,.08); }
    else if (type==='tick')     { osc('square',880,880,.06,.12); }
    else if (type==='loselife') { osc('sawtooth',320,60,.5,.28); osc('sawtooth',200,40,.5,.18,t+.08); }
    else if (type==='skip')     { osc('triangle',440,220,.3,.12); }
    else if (type==='combo')    { osc('square',880,1200,.15,.14); osc('square',1047,1400,.15,.12,t+.12); }
    else if (type==='victory')  {
        const mel=[[523,.5],[659,.5],[784,.5],[1047,1]]; let mt=t;
        mel.forEach(([f,d])=>{ osc('square',f,f,d*.12,.16,mt); mt+=d*.13; });
    }
    else if (type==='gameover') {
        [400,330,260,180].forEach((f,i)=>osc('sawtooth',f,f*.7,.4,.18,t+i*.22));
    }
}
let _bgmOn=false,_bgmTimer=null;
const BGM_BPM=132,BEAT=60/BGM_BPM;
const BGM =[[262,.5],[294,.5],[330,.5],[349,.5],[392,1],[330,.5],[294,.5],[262,1],
            [294,.5],[330,.5],[349,.5],[392,.5],[440,1],[392,.5],[349,.5],[330,1]];
const BASS=[[131,.5],[131,.5],[98,.5],[98,.5],[131,.5],[131,.5],[98,.5],[98,.5],
            [110,.5],[110,.5],[87,.5],[87,.5],[110,.5],[110,.5],[87,.5],[87,.5]];
function _bgmLoop(st) {
    if(!_bgmOn) return;
    const ac=getAC(); if(!ac) return;
    let mt=st,bt=st,dur=0;
    BGM.forEach(([f,b])=>{ const d=b*BEAT; osc('square',f,f,d*.85,.04,mt); mt+=d; dur+=d; });
    BASS.forEach(([f,b])=>{ const d=b*BEAT; osc('triangle',f,f,d*.7,.05,bt); bt+=d; });
    _bgmTimer=setTimeout(()=>{ if(_bgmOn) _bgmLoop(ac.currentTime+.05); },(dur-.3)*1000);
}
function startBGM(){ if(_bgmOn)return; _bgmOn=true; const ac=getAC(); if(ac) _bgmLoop(ac.currentTime+.1); }
function stopBGM() { _bgmOn=false; clearTimeout(_bgmTimer); }

// ══════════════════════════════════════════════════════════
//  LIVES, TIMER & COMBO
// ══════════════════════════════════════════════════════════
function updateLivesHUD() {
    document.getElementById('hud-lives').innerText =
        '❤️'.repeat(lives)+'🖤'.repeat(MAX_LIVES-lives);
}
function flashScreen(type) {
    const el=document.getElementById('screen-flash');
    el.className=`${type} on`;
    setTimeout(()=>el.className=type,80);
    setTimeout(()=>el.className='',420);
}
function startTimer() {
    clearInterval(timerIv);
    timerSecs=TIME_PER_Q;
    renderTimer();
    timerIv=setInterval(()=>{
        timerSecs--;
        renderTimer();
        if(timerSecs>0&&timerSecs<=5) playSound('tick');
        if(timerSecs<=0){ clearInterval(timerIv); onTimeout(); }
    },1000);
}
function renderTimer() {
    const pct=(timerSecs/TIME_PER_Q)*100;
    const fill=document.getElementById('timer-fill');
    fill.style.width=pct+'%';
    fill.className=pct<20?'danger':pct<45?'warn':'';
}
function onTimeout() {
    lives=Math.max(0,lives-1);
    updateLivesHUD(); combo=0; hideComboBadge();
    playSound('loselife'); flashScreen('wrong');
    quizLog.push({q:ITEMS[currentIdx].question,type:'fill_blank',options:[],
        correct_answer:ITEMS[currentIdx].answer,user_answer:null,is_correct:false});
    showFeedback(`⏰ Time's up! Answer: "${ITEMS[currentIdx].answer}"`, 'bad');
    if(lives<=0){ setTimeout(()=>finishGame(),1000); return; }
    setTimeout(()=>{ currentIdx++; loadQuestion(currentIdx); },1400);
}
function showComboBadge(n) {
    document.getElementById('combo-val').innerText=n;
    document.getElementById('combo-badge').classList.add('show');
    playSound('combo');
}
function hideComboBadge() { document.getElementById('combo-badge').classList.remove('show'); }
// ══════════════════════════════════════════════════════════
//  LOAD QUESTION
// ══════════════════════════════════════════════════════════
function loadQuestion(idx) {
    if (idx >= TOTAL || lives <= 0) { finishGame(); return; }

    const item   = ITEMS[idx];
    const answer = item.answer;

    // Update UI text
    document.getElementById('q-text').innerText = item.question;

    // Progress bar
    const pct = ((idx + 1) / TOTAL) * 100;
    document.getElementById('progress-fill').style.width = pct + '%';
    document.getElementById('progress-text').innerText   = `${idx + 1} / ${TOTAL}`;

    // Hint dots (one per non-space character)
    const hintDots = document.getElementById('hint-dots');
    hintDots.innerHTML = '';
    answer.split('').forEach(ch => {
        const d = document.createElement('div');
        d.className = 'hint-dot';
        d.innerText = ch === ' ' ? '·' : '_';
        hintDots.appendChild(d);
    });

    // Build letter pool from scrambled chars
    placeCounter = 0;
    const scrambled = scramble(answer);
    letterPool = scrambled.map((ch, i) => ({
        id:          `tile-${idx}-${i}`,
        char:        ch,
        inAnswer:    false,
        placedOrder: null,
    }));

    document.getElementById('answer-zone').classList.add('typing-active');
    renderBank();
    renderAnswer();
    if (gameStarted) startTimer();
}

// ══════════════════════════════════════════════════════════
//  RENDER HELPERS
// ══════════════════════════════════════════════════════════
function renderBank() {
    const bank = document.getElementById('letter-bank');
    bank.innerHTML = '';
    letterPool.filter(t => !t.inAnswer).forEach(tile => {
        bank.appendChild(makeTileEl(tile));
    });
}

function renderAnswer() {
    const zone = document.getElementById('answer-zone');
    zone.innerHTML = '';
    // Sort by placedOrder so letters appear in the order the user placed them
    const placed = letterPool
        .filter(t => t.inAnswer)
        .sort((a, b) => a.placedOrder - b.placedOrder);
    if (placed.length === 0) {
        zone.style.minHeight = '60px'; // keep drop target visible
    }
    placed.forEach(tile => {
        const el = makeTileEl(tile, true);
        el.classList.replace('bank-letter', 'placed-letter');
        if (tile.char === ' ') el.classList.add('placed-space');
        zone.appendChild(el);
    });
}

function makeTileEl(tile, inAnswer = false) {
    const el = document.createElement('div');
    el.id        = tile.id;
    el.className = 'bank-letter' + (tile.char === ' ' ? ' space-tile' : '');
    el.innerText = tile.char === ' ' ? 'SPACE' : tile.char.toUpperCase();
    el.draggable = true;

    // Drag events
    el.addEventListener('dragstart', e => {
        draggingId = tile.id;
        fromZone   = inAnswer ? 'answer-zone' : 'letter-bank';
        // Required: tell the browser what's being dragged (fixes wrong-letter bug)
        e.dataTransfer.setData('text/plain', tile.id);
        e.dataTransfer.effectAllowed = 'move';
        setTimeout(() => el.classList.add('dragging'), 0);
    });
    el.addEventListener('dragend', () => {
        el.classList.remove('dragging');
        draggingId = null;
        fromZone   = null;
    });

    // Tap/click to move (mobile & desktop shortcut)
    el.addEventListener('click', () => {
        toggleTileZone(tile.id);
    });

    return el;
}

// ── Quick toggle (click to move) ──────────────────────────
function toggleTileZone(tileId) {
    const tile = letterPool.find(t => t.id === tileId);
    if (!tile) return;
    tile.inAnswer    = !tile.inAnswer;
    tile.placedOrder = tile.inAnswer ? placeCounter++ : null;
    playSound(tile.inAnswer ? 'place' : 'backspace');
    renderBank();
    renderAnswer();
}

// ══════════════════════════════════════════════════════════
//  DRAG & DROP
// ══════════════════════════════════════════════════════════
function onZoneDragOver(e, zoneId) {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    document.getElementById(zoneId).classList.add('drag-over');
}
function onZoneDragLeave(zoneId) {
    document.getElementById(zoneId).classList.remove('drag-over');
}
function onZoneDrop(e, zoneId) {
    e.preventDefault();
    e.stopPropagation();
    document.getElementById(zoneId).classList.remove('drag-over');

    // Use dataTransfer as primary source (most reliable cross-browser)
    // Fall back to the global draggingId if dataTransfer is unavailable
    const tileId = e.dataTransfer.getData('text/plain') || draggingId;
    if (!tileId) return;

    const tile = letterPool.find(t => t.id === tileId);
    if (!tile) return;

    const movingToAnswer = (zoneId === 'answer-zone');
    // Only assign a new placedOrder when moving INTO the answer zone
    if (movingToAnswer && !tile.inAnswer) {
        tile.placedOrder = placeCounter++;
    } else if (!movingToAnswer) {
        tile.placedOrder = null;
    }
    tile.inAnswer = movingToAnswer;
    draggingId = null;
    fromZone   = null;
    renderBank();
    renderAnswer();
}

// ══════════════════════════════════════════════════════════
//  SUBMIT
// ══════════════════════════════════════════════════════════
function submitAnswer() {
    const placed  = letterPool
        .filter(t => t.inAnswer)
        .sort((a, b) => a.placedOrder - b.placedOrder)
        .map(t => t.char);
    if (placed.length === 0) { showFeedback('Place some letters first!', 'bad'); return; }

    const userAns = placed.join('').trim().toLowerCase();
    const correct = ITEMS[currentIdx].answer.trim().toLowerCase();

    const isCorrect = userAns === correct;

    const zone = document.getElementById('answer-zone');

    if (isCorrect) {
        zone.classList.add('correct');
        score        += 300 + Math.max(0, (TOTAL - wrongCount) * 20);
        correctCount++;
        document.getElementById('hud-correct').innerText = correctCount;
        document.getElementById('hud-score').innerText   = score;
        showFeedback('✓ Correct! Well done.', 'ok');

        // ── LOG ──
        quizLog.push({
            q: ITEMS[currentIdx].question,
            type: 'fill_blank',
            options: [],
            correct_answer: ITEMS[currentIdx].answer,
            user_answer: placed.join(''),
            is_correct: true
        });

        setTimeout(() => {
            zone.classList.remove('correct');
            currentIdx++;
            loadQuestion(currentIdx);
        }, 1100);
    } else {
        zone.classList.add('wrong-ans');
        wrongCount++;
        showFeedback(`✗ Try again! (${placed.length} letter${placed.length!==1?'s':''} placed)`, 'bad');
        setTimeout(() => zone.classList.remove('wrong-ans'), 500);
    }
}

// ══════════════════════════════════════════════════════════
//  CLEAR / SKIP
// ══════════════════════════════════════════════════════════
function skipQuestion() {
    skipCount++;
    document.getElementById('hud-skips').innerText = skipCount;
    showFeedback(`Skipped — answer was: "${ITEMS[currentIdx].answer}"`, 'skip');

    // ── LOG ──
    quizLog.push({
        q: ITEMS[currentIdx].question,
        type: 'fill_blank',
        options: [],
        correct_answer: ITEMS[currentIdx].answer,
        user_answer: null,
        is_correct: false
    });

    setTimeout(() => {
        currentIdx++;
        loadQuestion(currentIdx);
    }, 1400);
}

// ══════════════════════════════════════════════════════════
//  FEEDBACK TOAST
// ══════════════════════════════════════════════════════════
let fbTimer;
function showFeedback(msg, type) {
    const el = document.getElementById('feedback');
    el.innerText = msg;
    el.className = `show ${type}`;
    clearTimeout(fbTimer);
    fbTimer = setTimeout(() => { el.className = ''; }, 2500);
}

// ══════════════════════════════════════════════════════════
//  FINISH
// ══════════════════════════════════════════════════════════
function finishGame() {
    clearInterval(timerIv);
    stopBGM();
    if (lives <= 0) playSound('gameover'); else playSound('victory');
    const accuracy = TOTAL > 0 ? Math.round((correctCount / TOTAL) * 100) : 0;

    document.getElementById('res-correct').innerText  = correctCount;
    document.getElementById('res-score').innerText    = score;
    document.getElementById('res-accuracy').innerText = accuracy + '%';
    document.getElementById('res-sub').innerText      =
        `You unscrambled ${correctCount} of ${TOTAL} words.`;

    window.gameResultSavePromise = fetch('save_quiz_result.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `score=${score}&correct_answers=${correctCount}&total_questions=${TOTAL}&quiz_log=${encodeURIComponent(JSON.stringify(quizLog))}&completion_token=${encodeURIComponent(completionToken)}`
})
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const badge = document.getElementById('xp-badge');
            badge.innerText = data.xp_earned > 0 ? `+${data.xp_earned} XP earned!` : (data.xp_message || 'No quiz XP awarded.');
            badge.style.display = 'inline-block';
        }
    })
    .catch(() => {});

    setTimeout(() => {
        document.getElementById('result-overlay').classList.add('show');
        spawnConfetti();
    }, 400);
}

// ══════════════════════════════════════════════════════════
//  CONFETTI
// ══════════════════════════════════════════════════════════
function spawnConfetti() {
    const colors = ['#f59e0b','#1db968','#6366f1','#ef4444','#3b82f6'];
    for (let i = 0; i < 70; i++) {
        const el = document.createElement('div');
        el.className = 'confetti-dot';
        const size = 5 + Math.random() * 9;
        el.style.cssText = `
            left:${Math.random()*100}vw;top:-20px;
            width:${size}px;height:${size*(Math.random()>.5?1:2.2)}px;
            background:${colors[Math.floor(Math.random()*colors.length)]};
            --dur:${1.4+Math.random()*1.6}s;--del:${Math.random()*.9}s;
            border-radius:${Math.random()>.5?'50%':'2px'};
        `;
        document.body.appendChild(el);
        setTimeout(() => el.remove(), 3200);
    }
}

// ══════════════════════════════════════════════════════════
//  KEYBOARD SUPPORT — type letters, backspace, enter
// ══════════════════════════════════════════════════════════
document.addEventListener('keydown', e => {
    if (!gameStarted) return;
    if (e.key === 'Enter')     { e.preventDefault(); submitAnswer(); return; }
    if (e.key === 'Backspace') { e.preventDefault(); removeLastPlaced(); return; }
    if (e.key === ' ')         { e.preventDefault(); placeSpaceTile(); return; }
    const ch = e.key.toUpperCase();
    if (ch.length === 1 && /^[A-Z]$/.test(ch)) placeLetterByKey(ch);
});

function placeLetterByKey(ch) {
    const tile = letterPool.find(t => !t.inAnswer && t.char.toUpperCase() === ch);
    if (!tile) return;
    tile.inAnswer    = true;
    tile.placedOrder = placeCounter++;
    playSound('type');
    renderBank();
    renderAnswer();
}
function removeLastPlaced() {
    const placed = letterPool.filter(t=>t.inAnswer).sort((a,b)=>b.placedOrder-a.placedOrder);
    if (!placed.length) return;
    const last = placed[0];
    last.inAnswer    = false;
    last.placedOrder = null;
    playSound('backspace');
    renderBank();
    renderAnswer();
}
function placeSpaceTile() {
    const tile = letterPool.find(t => !t.inAnswer && t.char === ' ');
    if (!tile) return;
    tile.inAnswer    = true;
    tile.placedOrder = placeCounter++;
    playSound('type');
    renderBank();
    renderAnswer();
}

// ══════════════════════════════════════════════════════════
//  INSTRUCTIONS → START
// ══════════════════════════════════════════════════════════
document.getElementById('inst-start-btn').addEventListener('click', () => {
    getAC();
    document.getElementById('inst-overlay').style.display = 'none';
    gameStarted = true;
    updateLivesHUD();
    startBGM();
    loadQuestion(0);
});
</script>
<script src="retake_current_game.js"></script>
</body>
</html>
