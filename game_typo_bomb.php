<?php
// game_typo_bomb.php
// "TYPO BOMB: PASS THE PARCEL" — Sabotaged-keyboard fill-in-the-blank game.
// A sentence with a blank appears. The moment the student starts typing,
// the game randomly injects "debris" (duplicate letters / stray digits)
// into their answer. They must backspace the junk out and submit the
// CLEAN, correctly spelled word before the bomb (which grows the longer
// the box stays dirty / time passes) fills the screen and explodes.
// Uses $_SESSION['quiz_data']['questions'] — same contract as the other games.
// Saves XP via save_quiz_result.php.

session_start();
require_once __DIR__ . '/game_mode_access.php';
requireGameModeAccess('typo_bomb');

$questions = $_SESSION['quiz_data']['questions'] ?? [];
if (empty($questions)) {
    header("Location: quizzes.php?error=no_questions_found");
    exit();
}

// Build typing items from every valid question. Answer length is not an
// availability gate; the input and timer handle longer answers normally.
$items = [];
foreach ($questions as $q) {
    $answer = trim($q['answer'] ?? '');
    $qtext  = $q['question'] ?? '';
    if (!$answer || !$qtext) continue;

    $display_q = str_replace(['____', '___'], '______', $qtext);
    if (mb_strlen($display_q) > 140) $display_q = mb_substr($display_q, 0, 137) . '…';

    $items[] = ['question' => $display_q, 'answer' => $answer];
}

if (count($items) < 2) {
    header("Location: quizzes.php?error=not_enough_questions");
    exit();
}

$title       = $_SESSION['quiz_data']['title'] ?? 'Typo Bomb Quest';
$total_items = count($items);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>Typo Bomb! | PinnaQuest</title>
<link href="https://fonts.googleapis.com/css2?family=Bangers&family=Baloo+2:wght@500;700;800&family=JetBrains+Mono:wght@700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* ══════════════════════════════════════════════════════════════
   ROOT — Cartoon Comic Palette (twin of Bomb Toss)
══════════════════════════════════════════════════════════════ */
:root{
    --paper:#eef6ff; --ink:#1a1a1a;
    --red:#ff3b3b; --red-d:#c81f1f;
    --green:#38d45a; --green-d:#1e9e3c;
    --yellow:#ffd23f; --yellow-d:#e6ac00;
    --blue:#3fa9ff; --blue-d:#1c7fd6;
    --purple:#a855f7;
    --orange:#ff8c2b;
}
*{margin:0;padding:0;box-sizing:border-box;font-family:'Baloo 2',sans-serif;-webkit-tap-highlight-color:transparent;user-select:none;}
.comic{font-family:'Bangers',cursive;letter-spacing:1px;}
.mono{font-family:'JetBrains Mono',monospace;}

html,body{height:100%;overflow:hidden;background:var(--paper);color:var(--ink);}

#bg{
    position:fixed;inset:0;z-index:0;
    background-image:radial-gradient(circle,rgba(0,0,0,.06) 2px,transparent 2.2px);
    background-size:16px 16px;background-color:var(--paper);
}
#bg::before{
    content:'';position:absolute;inset:0;
    background:radial-gradient(circle at 15% 20%,rgba(255,140,43,.18),transparent 45%),
               radial-gradient(circle at 85% 85%,rgba(63,169,255,.22),transparent 45%);
}
.ink-border{ border:4px solid var(--ink); box-shadow:6px 6px 0 var(--ink); }

/* ══════════════════════════════════════════════════════════════
   HUD
══════════════════════════════════════════════════════════════ */
#hud{
    position:fixed;top:0;left:0;right:0;z-index:80;
    display:flex;align-items:center;gap:10px;padding:10px 16px;
    background:var(--yellow);border-bottom:4px solid var(--ink);
}
.hud-logo{font-size:19px;color:var(--red-d);text-shadow:2px 2px 0 #fff;flex-shrink:0;}
.hud-spacer{flex:1;}
.hud-chip{background:#fff;border:3px solid var(--ink);border-radius:12px;padding:5px 12px;text-align:center;box-shadow:3px 3px 0 var(--ink);}
.hud-chip .v{font-size:17px;font-weight:800;}
.hud-chip .l{font-size:8px;font-weight:700;color:#666;text-transform:uppercase;letter-spacing:.5px;}
#lives-chip .v{color:var(--red-d);}
#score-chip .v{color:var(--blue-d);}
#streak-chip{display:none;}
#streak-chip .v{color:var(--orange);}

/* ══════════════════════════════════════════════════════════════
   INSTRUCTIONS OVERLAY
══════════════════════════════════════════════════════════════ */
#inst-screen{
    position:fixed;inset:0;z-index:400;background:rgba(10,10,25,.88);
    display:flex;align-items:center;justify-content:center;padding:20px;
}
.inst-card{background:var(--paper);border-radius:22px;max-width:640px;width:100%;padding:28px 30px;text-align:center;}
.inst-title{font-size:38px;color:var(--red-d);text-shadow:3px 3px 0 #000;margin-bottom:2px;}
.inst-sub{font-size:13.5px;color:#555;font-weight:700;margin-bottom:16px;}
.inst-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px;text-align:left;}
.inst-item{background:#fff;border:3px solid var(--ink);border-radius:14px;padding:12px 14px;box-shadow:3px 3px 0 var(--ink);font-size:12.5px;line-height:1.5;font-weight:600;}
.inst-item b.tag{display:inline-block;font-family:'Bangers',cursive;font-size:13px;padding:1px 8px;border-radius:6px;margin-right:4px;color:#fff;}
.tag-type{background:var(--blue-d);}
.tag-glitch{background:var(--orange);}
.tag-clean{background:var(--green-d);}
.tag-boom{background:var(--red-d);}
.inst-demo{
    background:#fff;border:3px dashed var(--ink);border-radius:14px;padding:10px 14px;
    margin-bottom:16px;font-size:13px;font-weight:700;
}
.inst-demo .mono{color:var(--red-d);font-size:15px;letter-spacing:1px;}
.inst-start-btn{
    font-family:'Bangers',cursive;font-size:22px;letter-spacing:2px;
    background:var(--green);color:#fff;border:4px solid var(--ink);padding:14px 42px;border-radius:16px;
    cursor:pointer;box-shadow:0 6px 0 var(--green-d);transition:.08s;
}
.inst-start-btn:active{transform:translateY(4px);box-shadow:0 2px 0 var(--green-d);}

/* ══════════════════════════════════════════════════════════════
   GAME STAGE
══════════════════════════════════════════════════════════════ */
#stage{position:fixed;inset:0;z-index:10;display:none;flex-direction:column;align-items:center;padding:78px 16px 20px;}
#stage.active{display:flex;}
.q-progress{font-size:12px;font-weight:800;color:#3a5a7a;margin-bottom:6px;letter-spacing:.5px;}

#q-card{background:#fff;max-width:640px;width:100%;border-radius:20px;padding:16px 22px;text-align:center;margin-bottom:16px;position:relative;}
#q-card::after{content:'';position:absolute;bottom:-14px;left:50%;transform:translateX(-50%);border-left:14px solid transparent;border-right:14px solid transparent;border-top:14px solid var(--ink);}
#q-card::before{content:'';position:absolute;bottom:-9px;left:50%;transform:translateX(-50%);border-left:10px solid transparent;border-right:10px solid transparent;border-top:10px solid #fff;z-index:1;}
#q-text{font-size:16.5px;font-weight:800;line-height:1.5;}
#q-text .blank{display:inline-block;border-bottom:3px solid var(--red-d);min-width:70px;}

#arena{flex:1;width:100%;max-width:520px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:14px;position:relative;}

/* Bomb that grows */
#bomb-zone{position:relative;width:100%;display:flex;justify-content:center;align-items:flex-end;height:150px;}
#bomb-svg-wrap{transition:transform .18s ease-out;transform:scale(1);}
#bomb-svg-wrap.danger{animation:bombDanger .3s infinite;}
@keyframes bombDanger{0%,100%{transform:scale(var(--bs,1))}50%{transform:scale(calc(var(--bs,1) * 1.06))}}
#bomb-svg-wrap.shake{animation:bombShake .4s;}
@keyframes bombShake{
    0%,100%{transform:translateX(0) scale(var(--bs,1))}
    20%{transform:translateX(-10px) rotate(-4deg) scale(var(--bs,1))}
    40%{transform:translateX(10px) rotate(4deg) scale(var(--bs,1))}
    60%{transform:translateX(-8px) rotate(-3deg) scale(var(--bs,1))}
    80%{transform:translateX(8px) rotate(3deg) scale(var(--bs,1))}
}

/* Expressive face swap — calm / nervous / critical */
.bomb-face-state{ transition: opacity .35s ease; }
#sweat-drop, #sweat-drop-2, #sweat-drop-3{ animation: sweatDrip 1.1s ease-in infinite; transform-origin: center top; }
#sweat-drop-2{ animation-delay:.25s; }
#sweat-drop-3{ animation-delay:.55s; }
@keyframes sweatDrip{
    0%{ transform: translateY(0) scaleY(1); opacity:1; }
    80%{ transform: translateY(14px) scaleY(1.3); opacity:.6; }
    100%{ transform: translateY(18px) scaleY(1.3); opacity:0; }
}
#fuse-ember{
    filter: drop-shadow(0 0 4px #ffcc33);
    offset-path: path('M60 22 Q76 4 92 10');
    offset-rotate: 0deg;
    transition: opacity .25s ease;
}
#bomb-body{ transition: fill 1.2s ease; }
#bomb-svg-wrap.critical-state #bomb-body{ fill:#3a1414; }

/* Screen-level tension at critical pressure */
#stage.critical-shake{ animation: stageShake .5s infinite; }
@keyframes stageShake{
    0%,100%{ transform: translate(0,0); }
    25%{ transform: translate(-1.5px,1px); }
    50%{ transform: translate(1.5px,-1px); }
    75%{ transform: translate(-1px,-1.5px); }
}
#critical-vignette{
    position:fixed; inset:0; z-index:60; pointer-events:none; opacity:0;
    background: radial-gradient(ellipse at center, transparent 45%, rgba(255,20,20,.35) 100%);
    transition: opacity .3s ease;
}
#critical-vignette.on{ opacity:1; animation: vignettePulse .55s infinite; }
@keyframes vignettePulse{ 0%,100%{opacity:.55;} 50%{opacity:1;} }

/* Fill meter (bomb pressure) */
.meter-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;padding:0 2px;}
#meter-text{font-size:10px;font-weight:800;color:#555;letter-spacing:.5px;text-transform:uppercase;}
#timer-text{font-family:'Bangers',cursive;font-size:15px;color:var(--blue-d);letter-spacing:.5px;}
#timer-text.danger{color:var(--red-d);animation:timerPulse .4s infinite;}
@keyframes timerPulse{0%,100%{transform:scale(1)}50%{transform:scale(1.18)}}
#meter-track{width:100%;max-width:420px;height:14px;background:#fff;border:3px solid var(--ink);border-radius:20px;overflow:hidden;box-shadow:3px 3px 0 var(--ink);}
#meter-fill{height:100%;width:0%;background:linear-gradient(90deg,var(--green),var(--yellow));transition:width .12s linear, background .2s;}

/* Hint button */
#hint-btn{
    margin-top:10px;width:100%;font-family:'Baloo 2',sans-serif;font-weight:800;font-size:13px;
    background:#fff;color:var(--orange);border:3px solid var(--orange);border-radius:12px;padding:9px;
    cursor:pointer;transition:.15s;
}
#hint-btn:hover:not(:disabled){background:#fff6ec;}
#hint-btn:disabled{opacity:.45;cursor:not-allowed;}
#hint-text{font-size:12.5px;font-weight:800;color:var(--orange);margin-top:6px;min-height:16px;}

/* Debris particles */
.debris-bit{
    position:fixed;z-index:120;pointer-events:none;
    font-family:'JetBrains Mono',monospace;font-weight:800;font-size:20px;color:var(--red-d);
    text-shadow:1px 1px 0 #000;animation:debrisFly .7s ease-out forwards;
}
@keyframes debrisFly{
    0%{transform:translate(0,0) rotate(0);opacity:1;}
    100%{transform:translate(var(--dx),var(--dy)) rotate(var(--dr));opacity:0;}
}

/* ── INPUT ZONE ── */
#type-zone{width:100%;max-width:420px;text-align:center;}
#type-label{font-size:11px;font-weight:800;color:#555;margin-bottom:6px;text-transform:uppercase;letter-spacing:.6px;}
#type-input{
    width:100%;padding:16px 18px;font-size:24px;font-weight:800;text-align:center;
    border:4px solid var(--ink);border-radius:16px;outline:none;letter-spacing:1px;
    background:#fff;box-shadow:5px 5px 0 var(--ink);transition:border-color .12s,box-shadow .12s;
}
#type-input.glitching{border-color:var(--red-d);animation:inputGlitch .18s;}
@keyframes inputGlitch{
    0%,100%{transform:translateX(0)}
    25%{transform:translateX(-4px)}
    75%{transform:translateX(4px)}
}
#type-input.clean{border-color:var(--green-d);}

#submit-btn{
    margin-top:12px;width:100%;font-family:'Bangers',cursive;font-size:20px;letter-spacing:1.5px;
    background:var(--green);color:#fff;border:4px solid var(--ink);border-radius:16px;padding:13px;
    cursor:pointer;box-shadow:0 6px 0 var(--green-d);transition:transform .08s;
}
#submit-btn:active{transform:translateY(4px);box-shadow:0 2px 0 var(--green-d);}
#type-hint{font-size:11px;color:#777;font-weight:700;margin-top:8px;}

/* Explosion burst text */
.boom-burst{
    position:fixed;z-index:500;pointer-events:none;
    font-family:'Bangers',cursive;font-size:52px;color:var(--red-d);
    text-shadow:3px 3px 0 #fff,3px 3px 0 #000;
    animation:boomPop .8s ease-out forwards;
}
@keyframes boomPop{
    0%{transform:translate(-50%,-50%) scale(.2) rotate(-12deg);opacity:0;}
    30%{transform:translate(-50%,-50%) scale(1.25) rotate(4deg);opacity:1;}
    100%{transform:translate(-50%,-50%) scale(1) rotate(0);opacity:0;}
}
.spark{position:fixed;z-index:499;pointer-events:none;width:8px;height:8px;border-radius:50%;animation:sparkOut .55s ease-out forwards;}
@keyframes sparkOut{0%{transform:translate(0,0) scale(1);opacity:1;}100%{transform:translate(var(--sx),var(--sy)) scale(.2);opacity:0;}}

/* Soot overlay on explosion */
#soot{position:fixed;inset:0;z-index:480;pointer-events:none;background:radial-gradient(circle,rgba(20,20,20,.0) 0%,rgba(10,10,10,.0) 100%);opacity:0;transition:opacity .15s;}
#soot.on{opacity:1;background:radial-gradient(circle,rgba(30,30,30,.75) 0%,rgba(10,10,10,.92) 100%);}

#flash{position:fixed;inset:0;z-index:490;pointer-events:none;opacity:0;transition:opacity .08s;}
#flash.boom{background:rgba(255,60,60,.35);}
#flash.win{background:rgba(56,212,90,.28);}
#flash.on{opacity:1;transition:none;}

/* ══════════════════════════════════════════════════════════════
   BETWEEN-QUESTION OVERLAY
══════════════════════════════════════════════════════════════ */
#overlay{position:fixed;inset:0;z-index:150;background:rgba(10,10,25,.85);display:flex;flex-direction:column;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:opacity .2s;text-align:center;padding:20px;}
#overlay.show{opacity:1;pointer-events:all;}
#ov-title{font-family:'Bangers',cursive;font-size:44px;color:#fff;text-shadow:3px 3px 0 #000;}
#ov-sub{font-size:14px;color:#ddd;margin-top:8px;font-weight:700;}

/* ══════════════════════════════════════════════════════════════
   RESULT SCREEN
══════════════════════════════════════════════════════════════ */
#result-screen{display:none;position:fixed;inset:0;z-index:300;background:linear-gradient(160deg,#eef6ff,#d6e9ff);flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:30px;}
#result-screen.show{display:flex;}
.res-emoji{font-size:68px;margin-bottom:8px;}
.res-title{font-size:42px;color:var(--red-d);text-shadow:2px 2px 0 #000;}
.res-sub{font-size:14px;color:#456;font-weight:700;margin:8px 0 18px;}
.res-stats{display:flex;gap:12px;flex-wrap:wrap;justify-content:center;margin-bottom:18px;}
.res-stat{background:#fff;border:3px solid var(--ink);border-radius:14px;padding:14px 22px;box-shadow:4px 4px 0 var(--ink);min-width:100px;}
.res-stat .v{font-family:'Bangers',cursive;font-size:26px;color:var(--blue-d);}
.res-stat .l{font-size:9px;font-weight:800;color:#888;text-transform:uppercase;}
#xp-badge{font-family:'Bangers',cursive;font-size:16px;color:var(--green-d);background:#fff;border:3px solid var(--green-d);border-radius:20px;padding:8px 22px;margin-bottom:20px;}
.res-btns{display:flex;gap:12px;flex-wrap:wrap;justify-content:center;}
.btn-r{font-family:'Bangers',cursive;font-size:15px;letter-spacing:1px;border:3px solid var(--ink);border-radius:14px;padding:12px 26px;cursor:pointer;}
.btn-r-green{background:var(--green);color:#fff;box-shadow:0 5px 0 var(--green-d);}
.btn-r-ghost{background:#fff;color:#333;box-shadow:0 5px 0 #999;}

.confetti-bit{position:fixed;z-index:999;pointer-events:none;animation:confFall var(--cd) var(--cl) ease-in forwards;}
@keyframes confFall{0%{transform:translateY(-20px) rotate(0);opacity:1;}100%{transform:translateY(110vh) rotate(720deg);opacity:0;}}

@media(max-width:480px){
    .inst-grid{grid-template-columns:1fr;}
    #type-input{font-size:19px;}
    #bomb-zone{height:120px;}
}
</style>
</head>
<body>

<div id="bg"></div>
<div id="flash"></div>
<div id="soot"></div>
<div id="critical-vignette"></div>

<!-- ── HUD ── -->
<div id="hud">
    <div class="hud-logo comic">💣 TYPO BOMB!</div>
    <div class="hud-spacer"></div>
    <div class="hud-chip" id="streak-chip"><div class="v" id="hud-streak">0x</div><div class="l">Streak</div></div>
    <div class="hud-chip" id="lives-chip"><div class="v" id="hud-lives">❤️❤️❤️</div><div class="l">Lives</div></div>
    <div class="hud-chip" id="score-chip"><div class="v" id="hud-score">0</div><div class="l">Score</div></div>
</div>

<!-- ── INSTRUCTIONS ── -->
<div id="inst-screen">
    <div class="inst-card ink-border">
        <div class="inst-title comic">💣 TYPO BOMB: PASS THE PARCEL</div>
        <div class="inst-sub">YOUR KEYBOARD IS SABOTAGED. TYPE FAST, CLEAN FASTER.</div>
        <div class="inst-grid">
            <div class="inst-item"><b class="tag tag-type">TYPE</b> Read the sentence and type the missing word in the box.</div>
            <div class="inst-item"><b class="tag tag-glitch">GLITCH!</b> The moment you start typing, random junk letters/numbers sneak into your answer.</div>
            <div class="inst-item"><b class="tag tag-clean">CLEAN UP</b> Smash <b>Backspace</b> to delete the junk, then finish the real word.</div>
            <div class="inst-item"><b class="tag tag-boom">BOOM!</b> You have <b>24 seconds</b> per question — the messier &amp; slower you are, the faster the clock burns and the bigger the bomb grows!</div>
        </div>
        <div class="inst-demo">
            You want to type <span class="mono">PARIS</span> but the game glitches it into
            <span class="mono">PAP7RIS2</span> — backspace out the junk, then hit <b>Submit</b>!<br>
            Stuck? Tap <b>💡 Need a Hint?</b> once per question for a free letter-count clue.
        </div>
        <button class="inst-start-btn" id="inst-start-btn">START! 💥</button>
    </div>
</div>

<!-- ── GET READY / NEXT-QUESTION OVERLAY ── -->
<div id="overlay">
    <div id="ov-title" class="comic">READY?</div>
    <div id="ov-sub"><?php echo htmlspecialchars($title); ?> · <?php echo $total_items; ?> questions</div>
</div>

<!-- ── GAME STAGE ── -->
<div id="stage">
    <div class="q-progress" id="q-progress">QUESTION 1 / <?php echo $total_items; ?></div>
    <div id="q-card" class="ink-border"><div id="q-text">Loading...</div></div>

    <div id="arena">
        <div id="bomb-zone">
            <div id="bomb-svg-wrap">
                <svg width="110" height="110" viewBox="0 0 110 110" xmlns="http://www.w3.org/2000/svg">
                    <ellipse cx="55" cy="98" rx="34" ry="8" fill="#00000018"/>
                    <path id="bomb-fuse" d="M60 22 Q76 4 92 10" stroke="#7a4a2a" stroke-width="6" fill="none" stroke-linecap="round"/>
                    <circle id="fuse-ember" cx="60" cy="22" r="3" fill="#ffcc33" opacity="0"/>
                    <circle id="bomb-spark" cx="92" cy="10" r="6" fill="#ffcc33"/>
                    <circle id="bomb-body" cx="55" cy="62" r="42" fill="#2b2b2b" stroke="#000" stroke-width="4"/>
                    <ellipse cx="40" cy="45" rx="13" ry="9" fill="#5a5a5a" opacity=".55"/>

                    <!-- CALM face (pressure: safe) -->
                    <g id="face-calm" class="bomb-face-state">
                        <circle cx="42" cy="60" r="4.5" fill="#fff"/>
                        <circle cx="68" cy="60" r="4.5" fill="#fff"/>
                        <path d="M42 76 Q55 68 68 76" stroke="#fff" stroke-width="3" fill="none" stroke-linecap="round"/>
                    </g>

                    <!-- NERVOUS face (pressure: rising) -->
                    <g id="face-nervous" class="bomb-face-state" style="opacity:0">
                        <path d="M37 57 L47 60" stroke="#fff" stroke-width="3" stroke-linecap="round"/>
                        <path d="M73 57 L63 60" stroke="#fff" stroke-width="3" stroke-linecap="round"/>
                        <circle cx="42" cy="62" r="3.6" fill="#fff"/>
                        <circle cx="68" cy="62" r="3.6" fill="#fff"/>
                        <path d="M44 78 Q55 73 66 78" stroke="#fff" stroke-width="3" fill="none" stroke-linecap="round"/>
                        <ellipse id="sweat-drop" cx="76" cy="50" rx="3" ry="5" fill="#8fd3ff"/>
                    </g>

                    <!-- CRITICAL face (pressure: about to blow) -->
                    <g id="face-critical" class="bomb-face-state" style="opacity:0">
                        <path d="M36 55 L48 62" stroke="#fff" stroke-width="3.2" stroke-linecap="round"/>
                        <path d="M36 62 L48 55" stroke="#fff" stroke-width="3.2" stroke-linecap="round"/>
                        <path d="M74 55 L62 62" stroke="#fff" stroke-width="3.2" stroke-linecap="round"/>
                        <path d="M74 62 L62 55" stroke="#fff" stroke-width="3.2" stroke-linecap="round"/>
                        <ellipse cx="55" cy="78" rx="9" ry="7" fill="#fff"/>
                        <ellipse id="sweat-drop-2" cx="34" cy="48" rx="3.4" ry="5.6" fill="#8fd3ff"/>
                        <ellipse id="sweat-drop-3" cx="78" cy="52" rx="3" ry="5" fill="#8fd3ff"/>
                    </g>
                </svg>
            </div>
        </div>

        <div style="width:100%;max-width:420px;">
            <div class="meter-header">
                <span id="meter-text">PRESSURE: SAFE</span>
                <span id="timer-text">⏱ <span id="timer-num">24</span>s</span>
            </div>
            <div id="meter-track"><div id="meter-fill"></div></div>
        </div>

        <div id="type-zone">
            <div id="type-label">Type the missing word:</div>
            <input type="text" id="type-input" class="mono" autocomplete="off" autocorrect="off" spellcheck="false" placeholder="...">
            <button id="submit-btn" onclick="submitAnswer()">SUBMIT ✅</button>
            <div id="type-hint">Press <b>Enter</b> to submit · Backspace to clean junk</div>
            <button id="hint-btn" onclick="useHint()">💡 Need a Hint? (1 use)</button>
            <div id="hint-text"></div>
        </div>
    </div>
</div>

<!-- ── RESULT SCREEN ── -->
<div id="result-screen">
    <div class="res-emoji" id="res-emoji">🏆</div>
    <div class="res-title comic" id="res-title">QUEST CLEARED!</div>
    <div class="res-sub" id="res-sub">You defused every typo bomb.</div>
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

<script>
/* ═══════════════════════════════════════════════════════════
   DATA
═══════════════════════════════════════════════════════════ */
const ITEMS = <?php echo json_encode($items); ?>;
const TOTAL = ITEMS.length;
const MAX_LIVES = 3;
const QUESTION_TIME_S = 24;                    // per-question timer (seconds)
const FILL_TIME_MS = QUESTION_TIME_S * 1000;   // bomb reaches full at the timer's end
const GLITCH_MIN_MS = 1100;      // periodic sabotage tick range
const GLITCH_MAX_MS = 1700;

/* ═══════════════════════════════════════════════════════════
   STATE
═══════════════════════════════════════════════════════════ */
let qIdx = 0, score = 0, correct = 0, lives = MAX_LIVES, streak = 0;
let submitted = false;
let fillPct = 0;
let elapsedMs = 0;
let junkCount = 0;
let growthTimer = null, sabotageTimer = null;
let typingStarted = false;
let hintUsed = false;
let quizLog = [];
const completionToken = (window.crypto && crypto.randomUUID)
    ? crypto.randomUUID() : `${Date.now()}-${Math.random()}`;

/* ═══════════════════════════════════════════════════════════
   AUDIO
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
function sndGlitch(){ beep(rand(600,900),rand(200,400),.09,.09,'sawtooth'); }
function sndClean(){ beep(500,700,.06,.05); }
function sndCorrect(){ [523,659,784,1047].forEach((f,i)=>setTimeout(()=>beep(f,f,.16,.12),i*80)); }
function sndBoom(){ beep(140,30,.5,.3,'sawtooth'); }
function sndTick(){ beep(700,700,.05,.05); }

/* ── extra gamified SFX: bomb-squad tension pack ── */
function sndClockTick(urgent){
    // classic bomb clock tick — pitch/volume rise as the countdown gets scarier
    beep(urgent ? 980 : 820, urgent ? 980 : 820, urgent ? .045 : .035, urgent ? .11 : .06, 'square');
}
function sndAlarm(){ beep(920, 640, .18, .12, 'sawtooth'); }
function sndHintChime(){ beep(660,990,.12,.08,'triangle'); }
function sndSubmitClick(){ beep(300,180,.07,.07,'square'); }
function sndStreakUp(n){
    const base = 500 + Math.min(n,6)*40;
    [base, base*1.25, base*1.5].forEach((f,i)=>setTimeout(()=>beep(f,f,.1,.08,'triangle'),i*55));
}
function sndLifeLost(){ beep(200,70,.35,.14,'square'); }
function sndFanfareWin(){ [523,659,784,1047,1319].forEach((f,i)=>setTimeout(()=>beep(f,f,.2,.12),i*100)); }
function sndFanfareLose(){ [392,349,311,262].forEach((f,i)=>setTimeout(()=>beep(f,f,.3,.12,'sawtooth'),i*140)); }

/* Sizzling fuse loop — a continuous noise-hiss whose volume/pitch tracks bomb pressure */
let _fuseNode = null, _fuseGain = null, _fuseFilter = null;
function startFuseSizzle(){
    const ac = getAC(); if (!ac || _fuseNode) return;
    const bufSize = ac.sampleRate * 2;
    const buf = ac.createBuffer(1, bufSize, ac.sampleRate);
    const data = buf.getChannelData(0);
    for (let i=0;i<bufSize;i++) data[i] = Math.random()*2-1;
    _fuseNode = ac.createBufferSource(); _fuseNode.buffer = buf; _fuseNode.loop = true;
    _fuseFilter = ac.createBiquadFilter(); _fuseFilter.type = 'bandpass'; _fuseFilter.frequency.value = 2200; _fuseFilter.Q.value = 0.7;
    _fuseGain = ac.createGain(); _fuseGain.gain.value = 0;
    _fuseNode.connect(_fuseFilter).connect(_fuseGain).connect(ac.destination);
    _fuseNode.start();
}
function setFuseSizzleIntensity(pct01){
    if (!_fuseGain) return;
    _fuseGain.gain.setTargetAtTime(Math.min(.05, pct01 * .06), getAC().currentTime, .08);
    if (_fuseFilter) _fuseFilter.frequency.setTargetAtTime(1800 + pct01*2600, getAC().currentTime, .1);
}
function stopFuseSizzle(){
    if (_fuseNode) { try { _fuseNode.stop(); } catch(e){} _fuseNode.disconnect(); _fuseNode = null; }
    _fuseGain = null; _fuseFilter = null;
}

/* ═══════════════════════════════════════════════════════════
   UTIL
═══════════════════════════════════════════════════════════ */
function rand(min,max){ return Math.random()*(max-min)+min; }
function updateHUD(){
    document.getElementById('hud-score').innerText = score;
    document.getElementById('hud-lives').innerText = '❤️'.repeat(lives) + '🖤'.repeat(MAX_LIVES-lives);
    const sc = document.getElementById('streak-chip');
    if (streak >= 2) { sc.style.display='block'; document.getElementById('hud-streak').innerText = streak+'x'; }
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
        if(c<0){ clearInterval(iv); ov.classList.remove('show'); getAC(); showQuestion(qIdx); }
    },700);
}

function showQuestion(idx){
    if (idx >= TOTAL || lives <= 0) { finishGame(); return; }
    document.getElementById('stage').classList.add('active');
    const item = ITEMS[idx];
    document.getElementById('q-progress').innerText = `QUESTION ${idx+1} / ${TOTAL}`;
    document.getElementById('q-text').innerHTML =
        (item.question || '(no question text)').replace(/_{3,}/g, '<span class="blank">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>');

    const input = document.getElementById('type-input');
    input.value = '';
    input.disabled = false;
    input.classList.remove('glitching','clean');
    document.getElementById('submit-btn').disabled = false;

    submitted = false; fillPct = 0; elapsedMs = 0; junkCount = 0; typingStarted = false;
    hintUsed = false;
    document.getElementById('hint-btn').disabled = false;
    document.getElementById('hint-btn').innerText = '💡 Need a Hint? (1 use)';
    document.getElementById('hint-text').innerText = '';
    document.getElementById('bomb-svg-wrap').style.setProperty('--bs', 1);
    document.getElementById('bomb-svg-wrap').style.transform = 'scale(1)';
    document.getElementById('bomb-svg-wrap').classList.remove('danger','critical-state');
    document.getElementById('stage').classList.remove('critical-shake');
    document.getElementById('critical-vignette').classList.remove('on');
    document.getElementById('fuse-ember').style.opacity = 0;
    setFace('calm');
    _lastRemainSec = null;
    startFuseSizzle();
    updateMeter();

    clearInterval(growthTimer); clearTimeout(sabotageTimer);
    growthTimer = setInterval(growTick, 100);

    setTimeout(()=> input.focus(), 250);
}

/* ═══════════════════════════════════════════════════════════
   HINT — one free peek per question to keep it fair
═══════════════════════════════════════════════════════════ */
function useHint(){
    if (hintUsed || submitted) return;
    hintUsed = true;
    document.getElementById('hint-btn').disabled = true;
    document.getElementById('hint-btn').innerText = '💡 Hint Used';
    const answer = ITEMS[qIdx].answer;
    const letters = answer.replace(/\s/g, '').length;
    const masked = answer.split('').map((c,i)=> c===' ' ? '  ' : (i===0 ? c.toUpperCase() : '_')).join(' ');
    document.getElementById('hint-text').innerText = `Hint: ${letters} letters — ${masked}`;
    sndHintChime();
}

/* ═══════════════════════════════════════════════════════════
   BOMB GROWTH (pressure meter)
═══════════════════════════════════════════════════════════ */
function growTick(){
    if (submitted) return;
    // growth speeds up the more junk is currently sitting in the box —
    // a messy box burns through the 24s clock faster.
    const speedMult = 1 + junkCount * 0.45;
    elapsedMs += 100 * speedMult;
    fillPct = Math.min(100, (elapsedMs / FILL_TIME_MS) * 100);
    updateMeter();
    if (fillPct >= 100) { explode(true); return; }
}

let _lastRemainSec = null;
function setFace(state){
    ['face-calm','face-nervous','face-critical'].forEach(id => {
        document.getElementById(id).style.opacity = (id === 'face-' + state) ? '1' : '0';
    });
}
function updateMeter(){
    const pct = Math.min(100, fillPct);
    document.getElementById('meter-fill').style.width = pct + '%';
    const scale = 1 + (pct/100) * 1.9;
    const wrap = document.getElementById('bomb-svg-wrap');
    wrap.style.setProperty('--bs', scale.toFixed(2));
    wrap.style.transform = `scale(${scale.toFixed(2)})`;

    // Explicit countdown display (seconds remaining on the 24s clock)
    const remainSec = Math.max(0, Math.ceil((FILL_TIME_MS - elapsedMs) / 1000));
    const timerEl = document.getElementById('timer-text');
    document.getElementById('timer-num').innerText = remainSec;
    const urgent = remainSec <= 6;
    timerEl.classList.toggle('danger', urgent);
    if (!submitted && remainSec !== _lastRemainSec) {
        _lastRemainSec = remainSec;
        sndClockTick(urgent);
        if (urgent && remainSec > 0) setTimeout(sndAlarm, 60);
    }

    let label = 'PRESSURE: SAFE', color = 'linear-gradient(90deg,#38d45a,#ffd23f)';
    const stage = document.getElementById('stage');
    if (pct >= 75) {
        label = 'PRESSURE: CRITICAL!!'; color = 'linear-gradient(90deg,#ff8c2b,#ff3b3b)';
        wrap.classList.add('danger'); wrap.classList.add('critical-state');
        setFace('critical');
        stage.classList.add('critical-shake');
        document.getElementById('critical-vignette').classList.add('on');
    } else if (pct >= 40) {
        label = 'PRESSURE: RISING'; color = 'linear-gradient(90deg,#ffd23f,#ff8c2b)';
        wrap.classList.remove('danger'); wrap.classList.remove('critical-state');
        setFace('nervous');
        stage.classList.remove('critical-shake');
        document.getElementById('critical-vignette').classList.remove('on');
    } else {
        wrap.classList.remove('danger'); wrap.classList.remove('critical-state');
        setFace('calm');
        stage.classList.remove('critical-shake');
        document.getElementById('critical-vignette').classList.remove('on');
    }
    document.getElementById('meter-fill').style.background = color;
    document.getElementById('meter-text').innerText = label;
    setFuseSizzleIntensity(pct/100);
    const ember = document.getElementById('fuse-ember');
    ember.style.opacity = pct > 8 ? Math.min(1, pct/60) : 0;
    ember.style.offsetDistance = Math.min(100, pct) + '%';
}

/* ═══════════════════════════════════════════════════════════
   SABOTAGE — inject junk characters into the input
═══════════════════════════════════════════════════════════ */
function scheduleSabotage(){
    clearTimeout(sabotageTimer);
    sabotageTimer = setTimeout(()=>{
        if (!submitted && typingStarted) injectJunk();
        scheduleSabotage();
    }, rand(GLITCH_MIN_MS, GLITCH_MAX_MS));
}

function injectJunk(){
    const input = document.getElementById('type-input');
    if (!input.value) return;
    const junkChars = '0123456789';
    const useDigit = Math.random() < 0.55;
    const lastChar = input.value.slice(-1) || 'X';
    const junk = useDigit
        ? junkChars[Math.floor(Math.random()*junkChars.length)]
        : lastChar;

    input.value = input.value + junk;
    junkCount++;
    sndGlitch();
    flashGlitch(input);
    spawnDebris(junk);
}

function flashGlitch(input){
    input.classList.remove('clean');
    input.classList.add('glitching');
    setTimeout(()=> input.classList.remove('glitching'), 180);
}

function spawnDebris(ch){
    const input = document.getElementById('type-input');
    const rect = input.getBoundingClientRect();
    const el = document.createElement('div');
    el.className = 'debris-bit';
    el.innerText = ch;
    const x = rect.left + rect.width * rand(0.2,0.8);
    const y = rect.top + rect.height/2;
    el.style.left = x+'px'; el.style.top = y+'px';
    el.style.setProperty('--dx', rand(-40,40)+'px');
    el.style.setProperty('--dy', rand(-60,-20)+'px');
    el.style.setProperty('--dr', rand(-60,60)+'deg');
    document.body.appendChild(el);
    setTimeout(()=>el.remove(), 750);
}

/* ═══════════════════════════════════════════════════════════
   INPUT HANDLING
═══════════════════════════════════════════════════════════ */
const inputEl = document.getElementById('type-input');
inputEl.addEventListener('input', ()=>{
    if (submitted) return;
    if (!typingStarted && inputEl.value.length > 0) {
        typingStarted = true;
        scheduleSabotage();
    }
    if (inputEl.value.length === 0) {
        typingStarted = false;
        junkCount = 0;
        clearTimeout(sabotageTimer);
    }
});
inputEl.addEventListener('keydown', (e)=>{
    if (submitted) return;
    if (e.key === 'Backspace' && junkCount > 0) {
        junkCount = Math.max(0, junkCount - 1);
        sndClean();
        if (junkCount === 0) { inputEl.classList.add('clean'); setTimeout(()=>inputEl.classList.remove('clean'), 500); }
    }
    if (e.key === 'Enter') { e.preventDefault(); submitAnswer(); }
});

/* ═══════════════════════════════════════════════════════════
   SUBMIT
═══════════════════════════════════════════════════════════ */
function submitAnswer(){
    if (submitted) return;
    const input = document.getElementById('type-input');
    const typed = input.value.trim();
    if (!typed) { flashGlitch(input); return; }
    sndSubmitClick();

    submitted = true;
    clearInterval(growthTimer); clearTimeout(sabotageTimer);
    input.disabled = true; document.getElementById('submit-btn').disabled = true;
    document.getElementById('stage').classList.remove('critical-shake');
    document.getElementById('critical-vignette').classList.remove('on');
    setFuseSizzleIntensity(0);

    const item = ITEMS[qIdx];
    const isCorrect = typed.toLowerCase() === item.answer.trim().toLowerCase();

    quizLog.push({
        q: item.question, type:'fill_blank', options: [],
        correct_answer: item.answer, user_answer: typed, is_correct: isCorrect
    });

    if (isCorrect) {
        correct++; streak++;
        const remainFrac = Math.max(0, (100 - fillPct) / 100);
        const pts = 400 + Math.round(remainFrac * 400) + Math.min(300, streak*40);
        score += pts;
        updateHUD();
        setFace('calm');
        sndCorrect();
        if (streak >= 2) setTimeout(()=>sndStreakUp(streak), 180);
        flashScreen('win');
        showBoomText('#38d45a', 'DEFUSED!', `+${pts}`);
        setTimeout(()=>advance(), 950);
    } else {
        streak = 0;
        updateHUD();
        explode(false);
    }
}

/* ═══════════════════════════════════════════════════════════
   EXPLODE (timeout OR wrong submit)
═══════════════════════════════════════════════════════════ */
function explode(wasTimeout){
    submitted = true;
    clearInterval(growthTimer); clearTimeout(sabotageTimer);
    const input = document.getElementById('type-input');
    input.disabled = true; document.getElementById('submit-btn').disabled = true;
    document.getElementById('stage').classList.remove('critical-shake');
    document.getElementById('critical-vignette').classList.remove('on');
    setFuseSizzleIntensity(0);

    lives = Math.max(0, lives-1);
    streak = 0;
    updateHUD();
    sndBoom();
    setTimeout(sndLifeLost, 90);
    flashScreen('boom');
    document.getElementById('soot').classList.add('on');
    setTimeout(()=>document.getElementById('soot').classList.remove('on'), 700);
    document.getElementById('bomb-svg-wrap').classList.add('shake');
    setTimeout(()=>document.getElementById('bomb-svg-wrap').classList.remove('shake'), 420);
    spawnSparks();

    const item = ITEMS[qIdx];
    showBoomText('#ff3b3b', 'BOOM!', wasTimeout ? "TOO SLOW!" : `Answer: ${item.answer}`);

    if (wasTimeout) {
        quizLog.push({
            q: item.question, type:'fill_blank', options: [],
            correct_answer: item.answer, user_answer: input.value || null, is_correct: false
        });
    }

    if (lives <= 0) { setTimeout(()=>finishGame(), 1200); return; }
    setTimeout(()=>advance(), 1200);
}

function advance(){
    qIdx++;
    if (qIdx >= TOTAL || lives <= 0) { finishGame(); return; }
    const ov = document.getElementById('overlay');
    document.getElementById('ov-title').innerText = 'NEXT UP!';
    document.getElementById('ov-sub').innerText = `Question ${qIdx+1} of ${TOTAL}`;
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
    const wrap = document.getElementById('bomb-zone');
    const rect = wrap.getBoundingClientRect();
    const x = rect.left + rect.width/2, y = rect.top + rect.height*0.4;
    const el = document.createElement('div');
    el.className = 'boom-burst';
    el.style.left = x+'px'; el.style.top = y+'px'; el.style.color = color;
    el.innerHTML = `${big}<div style="font-size:15px;color:#333;text-shadow:none;margin-top:2px;">${small}</div>`;
    document.body.appendChild(el);
    setTimeout(()=>el.remove(), 850);
}
function spawnSparks(){
    const wrap = document.getElementById('bomb-zone');
    const rect = wrap.getBoundingClientRect();
    const cx = rect.left+rect.width/2, cy = rect.top+rect.height*0.55;
    const colors = ['#ff3b3b','#ffd23f','#ff8c2b','#fff'];
    for(let i=0;i<18;i++){
        const s = document.createElement('div');
        s.className='spark';
        const a = (i/18)*Math.PI*2, d = 45+Math.random()*70;
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
    document.getElementById('stage').classList.remove('active','critical-shake');
    document.getElementById('critical-vignette').classList.remove('on');
    stopFuseSizzle();
    const acc = TOTAL>0 ? Math.round((correct/TOTAL)*100) : 0;
    const ranOut = lives <= 0;
    setTimeout(()=> ranOut ? sndFanfareLose() : sndFanfareWin(), 250);
    document.getElementById('res-emoji').innerText = ranOut ? '💥' : (acc>=80 ? '🏆' : (acc>=50 ? '🎖️' : '🧨'));
    document.getElementById('res-title').innerText = ranOut ? 'BOOM! GAME OVER' : 'QUEST CLEARED!';
    document.getElementById('res-sub').innerText = `${correct} of ${TOTAL} bombs defused clean.`;
    document.getElementById('res-score').innerText = score;
    document.getElementById('res-correct').innerText = correct;
    document.getElementById('res-acc').innerText = acc+'%';

    window.gameResultSavePromise = fetch('save_quiz_result.php', {
        method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`score=${score}&correct_answers=${correct}&total_questions=${TOTAL}&quiz_log=${encodeURIComponent(JSON.stringify(quizLog))}&completion_token=${encodeURIComponent(completionToken)}`
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
    startCountdown();
});
updateHUD();
</script>
<script src="retake_current_game.js"></script>
</body>
</html>