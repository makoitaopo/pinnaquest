<?php
// student_quiz_game.php
// Power-ups: 5 for MCQ  |  5 for Fill-blank  (synchro only, single-use each)
session_start();

if (!isset($_SESSION['current_session_id'])) {
    header("Location: synchro_portal.php");
    exit();
}

$conn       = new mysqli("localhost", "root", "", "pinnaquest_db");
$session_id = intval($_SESSION['current_session_id']);
$nickname   = $_SESSION['user_name']   ?? 'Student';
$avatar_key = $_SESSION['user_avatar'] ?? 'blue_robot';

$sess_res = $conn->query("SELECT * FROM synchro_sessions WHERE id = $session_id");
$session  = $sess_res ? $sess_res->fetch_assoc() : [];

$avatars = [
    'gamer_girl' => '1a.JPG', 'blue_robot' => '2a.JPG', 'gorilla_vr' => '3a.JPG',
    'grey_cat'   => '4a.JPG', 'monkey_cap' => '5a.JPG', 'astronaut'  => '6a.JPG',
    'bear_angry' => '7a.JPG', 'bear_bee'   => '8a.JPG',
];
$avatar_file = $avatars[$avatar_key] ?? '1a.JPG';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>PinnaQuest | Quest in Progress</title>
<link href="https://fonts.googleapis.com/css2?family=Luckiest+Guy&family=Nunito:wght@400;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* ═══════════════════════════════════════════════════════════════════════
   ROOT & RESET
   ═══════════════════════════════════════════════════════════════════════ */
:root{
    --purple:#6366f1; --purple-dark:#4338ca;
    --gold:#f59e0b;   --green:#10b981;
    --red:#ef4444;    --blue:#3b82f6;
    --cyan:#06b6d4;
    --bg:#0f0e17;     --surface:rgba(255,255,255,.06);
}
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent;}
html,body{font-family:'Nunito',sans-serif;background:var(--bg);color:white;height:100%;overflow:hidden;user-select:none;}
body::before{content:'';position:fixed;inset:0;background:radial-gradient(circle at 20% 20%,rgba(99,102,241,.15) 0%,transparent 50%),radial-gradient(circle at 80% 80%,rgba(168,85,247,.1) 0%,transparent 50%);pointer-events:none;z-index:0;}

/* ═══════════════════════════════════════════════════════════════════════
   SCREENS
   ═══════════════════════════════════════════════════════════════════════ */
.screen{position:fixed;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;z-index:10;padding:20px;opacity:0;pointer-events:none;transition:opacity .4s ease;}
.screen.active{opacity:1;pointer-events:all;}

/* ═══════════════════════════════════════════════════════════════════════
   HUD
   ═══════════════════════════════════════════════════════════════════════ */
.hud{position:fixed;top:0;left:0;right:0;z-index:50;background:rgba(10,10,20,.85);backdrop-filter:blur(12px);border-bottom:1px solid rgba(255,255,255,.08);padding:10px 20px;display:flex;align-items:center;gap:12px;}
.hud-avatar{width:38px;height:38px;border-radius:10px;object-fit:cover;border:2px solid var(--purple);}
.hud-name{font-weight:900;font-size:14px;flex:1;}
.hud-pu-count{background:rgba(99,102,241,.15);border:1px solid rgba(99,102,241,.35);padding:5px 12px;border-radius:10px;font-weight:900;font-size:12px;color:#a5b4fc;display:flex;align-items:center;gap:5px;}
.hud-score{background:rgba(245,158,11,.15);border:1px solid rgba(245,158,11,.4);padding:5px 14px;border-radius:10px;font-weight:900;font-size:15px;color:var(--gold);}
.hud-streak{display:none;background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.4);padding:5px 11px;border-radius:10px;font-weight:900;font-size:13px;color:#ff6b6b;}
@keyframes streakPulse{0%,100%{transform:scale(1)}50%{transform:scale(1.15)}}

/* ═══════════════════════════════════════════════════════════════════════
   WAITING SCREEN
   ═══════════════════════════════════════════════════════════════════════ */
.waiting-icon{width:90px;height:90px;background:rgba(99,102,241,.15);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:38px;margin:0 auto 25px;animation:floatBob 2s ease-in-out infinite;}
@keyframes floatBob{0%,100%{transform:translateY(0)}50%{transform:translateY(-12px)}}
.waiting-title{font-family:'Luckiest Guy',cursive;font-size:30px;color:var(--purple);letter-spacing:2px;margin-bottom:8px;}
.waiting-sub{color:rgba(255,255,255,.5);font-size:14px;margin-bottom:28px;}
.waiting-dots span{display:inline-block;width:9px;height:9px;background:var(--purple);border-radius:50%;margin:0 4px;animation:dotBounce 1.2s infinite;}
.waiting-dots span:nth-child(2){animation-delay:.2s}
.waiting-dots span:nth-child(3){animation-delay:.4s}
@keyframes dotBounce{0%,100%{transform:translateY(0);opacity:.4}50%{transform:translateY(-8px);opacity:1}}
.player-card{background:var(--surface);border:1px solid rgba(255,255,255,.1);border-radius:20px;padding:18px 28px;margin-top:22px;display:flex;align-items:center;gap:14px;}
.player-card img{width:58px;height:58px;border-radius:12px;object-fit:cover;}
.player-card-name{font-size:20px;font-weight:900;}
.player-card-sub{font-size:12px;color:rgba(255,255,255,.4);}

/* ═══════════════════════════════════════════════════════════════════════
   GET READY
   ═══════════════════════════════════════════════════════════════════════ */
.countdown-big{font-family:'Luckiest Guy',cursive;font-size:100px;color:white;text-shadow:0 0 40px rgba(99,102,241,.8);animation:countAnim .5s cubic-bezier(.34,1.56,.64,1) forwards;letter-spacing:5px;}
@keyframes countAnim{from{transform:scale(2);opacity:0}to{transform:scale(1);opacity:1}}
.getready-label{font-family:'Luckiest Guy',cursive;font-size:26px;color:var(--gold);letter-spacing:3px;margin-bottom:18px;}

/* ═══════════════════════════════════════════════════════════════════════
   QUESTION SCREEN
   ═══════════════════════════════════════════════════════════════════════ */
.question-screen{justify-content:flex-start;padding-top:68px;padding-bottom:80px;/* leaves room for power-up bar */}
.timer-track{position:fixed;top:60px;left:0;right:0;height:7px;background:rgba(255,255,255,.08);z-index:45;}
.timer-fill{height:100%;background:linear-gradient(90deg,var(--purple),#a855f7);border-radius:0 4px 4px 0;transition:width 1s linear;}
.timer-fill.smooth{transition:width 1s linear;}
.timer-fill.danger{background:linear-gradient(90deg,#ef4444,#f97316);}
.timer-fill.frozen{background:linear-gradient(90deg,var(--cyan),#38bdf8)!important;animation:icePulse 1s ease-in-out infinite;}
@keyframes icePulse{0%,100%{opacity:1}50%{opacity:.6}}
.q-header{width:100%;max-width:680px;margin:16px auto 12px;display:flex;align-items:center;justify-content:space-between;padding:0 5px;}
.q-badge{background:rgba(99,102,241,.2);border:1px solid rgba(99,102,241,.4);padding:4px 13px;border-radius:8px;font-size:11px;font-weight:800;color:var(--purple);letter-spacing:1px;text-transform:uppercase;}
.timer-orb{width:48px;height:48px;border-radius:50%;background:rgba(99,102,241,.2);border:3px solid var(--purple);display:flex;align-items:center;justify-content:center;font-weight:900;font-size:19px;transition:border-color .5s,color .5s;}
.timer-orb.warning{border-color:var(--gold);color:var(--gold);}
.timer-orb.danger{border-color:var(--red);color:var(--red);animation:timerShake .3s infinite;}
.timer-orb.frozen{border-color:var(--cyan);color:var(--cyan);}
@keyframes timerShake{0%,100%{transform:translateX(0)}25%{transform:translateX(-3px)}75%{transform:translateX(3px)}}
.question-card{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:22px;padding:26px 30px;width:100%;max-width:680px;margin:0 auto 14px;backdrop-filter:blur(10px);}
.question-text{font-size:20px;font-weight:800;line-height:1.5;color:white;text-align:center;}
.question-text.fill-blank-text{line-height:1.85;}
.inline-id-input{
    display:inline-block;vertical-align:baseline;
    width:min(220px,42vw);min-width:105px;
    margin:0 7px;padding:3px 9px 2px;
    background:rgba(16,185,129,.12);
    border:none;border-bottom:3px solid var(--green);
    border-radius:7px 7px 0 0;
    color:white;font:inherit;font-size:.95em;text-align:center;
    outline:none;transition:background .2s,border-color .2s,box-shadow .2s;
    user-select:text;text-transform:none;
}
.inline-id-input:focus{background:rgba(16,185,129,.2);border-color:#34d399;box-shadow:0 4px 14px rgba(16,185,129,.2);}
.inline-id-input.shake{animation:idShake .35s ease;border-color:var(--red)!important;}
.fill-answer-hint{color:rgba(255,255,255,.42);font-size:12px;font-weight:700;text-align:center;margin:0 0 9px;}

/* ═══════════════════════════════════════════════════════════════════════
   MCQ ANSWER BUTTONS  —  RPG Arcane Quest Card style
   ═══════════════════════════════════════════════════════════════════════ */
.answers-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;width:100%;max-width:680px;margin:0 auto;}

/* Per-option accent palette */
.ans-btn.opt-a{--c:#7c3aed;--cl:rgba(124,58,237,.12);--cs:rgba(124,58,237,.28);}
.ans-btn.opt-b{--c:#0891b2;--cl:rgba(8,145,178,.12);--cs:rgba(8,145,178,.28);}
.ans-btn.opt-c{--c:#d97706;--cl:rgba(217,119,6,.12);--cs:rgba(217,119,6,.28);}
.ans-btn.opt-d{--c:#0f766e;--cl:rgba(15,118,110,.12);--cs:rgba(15,118,110,.28);}

.ans-btn{
    position:relative;display:flex;align-items:stretch;
    border:2px solid rgba(255,255,255,.1);
    border-radius:18px;cursor:pointer;min-height:78px;
    background:rgba(255,255,255,.06);
    overflow:hidden;padding:0;text-align:left;
    transition:transform .22s cubic-bezier(.34,1.56,.64,1),box-shadow .22s,border-color .2s,background .2s;
    box-shadow:0 3px 12px rgba(0,0,0,.25);
}
/* Left accent stripe */
.ans-btn::before{
    content:'';position:absolute;left:0;top:0;bottom:0;
    width:6px;background:var(--c);border-radius:18px 0 0 18px;
}
/* Top inner shine */
.ans-btn::after{
    content:'';position:absolute;top:0;left:6px;right:0;
    height:1px;background:linear-gradient(90deg,var(--cs) 0%,transparent 60%);
}
.ans-btn:hover:not(:disabled){
    transform:translateY(-5px) scale(1.02);
    border-color:var(--c);
    background:var(--cl);
    box-shadow:0 14px 32px var(--cs),0 4px 8px rgba(0,0,0,.3),inset 0 0 0 1px var(--cs);
}
.ans-btn:active:not(:disabled){transform:translateY(1px) scale(.99);box-shadow:0 2px 6px var(--cs);}
.ans-btn:disabled{cursor:default;}

/* Letter badge section */
.ans-badge-wrap{
    width:70px;min-height:78px;display:flex;align-items:center;
    justify-content:center;flex-shrink:0;padding-left:8px;
    background:var(--cl);border-right:1.5px solid var(--cs);
}
.ans-badge{
    width:44px;height:44px;border-radius:12px;background:var(--c);
    display:flex;align-items:center;justify-content:center;
    font-family:'Nunito',sans-serif;font-size:1.15rem;font-weight:900;
    color:white;letter-spacing:.5px;
    box-shadow:0 3px 10px var(--cs);
    transition:transform .2s,box-shadow .2s;
}
.ans-btn:hover:not(:disabled) .ans-badge{transform:scale(1.1) rotate(-4deg);box-shadow:0 6px 18px var(--cs);}

/* Answer text */
.ans-text-area{
    flex:1;padding:16px 18px 16px 14px;
    display:flex;align-items:center;
    font-size:14px;font-weight:800;
    color:rgba(255,255,255,.95);line-height:1.4;
}

/* Result states */
.ans-btn.selected{border-color:var(--c)!important;background:var(--cl)!important;}
.ans-btn.correct-reveal{
    border-color:#10b981!important;
    background:rgba(16,185,129,.15)!important;
    box-shadow:0 0 0 3px rgba(16,185,129,.3),0 10px 24px rgba(16,185,129,.2)!important;
    animation:correctPulse .45s ease;
}
.ans-btn.wrong-reveal{opacity:.28;filter:grayscale(.6);transform:none!important;}
@keyframes correctPulse{0%{transform:scale(1)}40%{transform:scale(1.04)}100%{transform:scale(1)}}

/* ═══════════════════════════════════════════════════════════════════════
   IDENTIFICATION INPUT
   ═══════════════════════════════════════════════════════════════════════ */
.id-wrap{width:100%;max-width:680px;margin:0 auto;}
.id-input{width:100%;background:rgba(255,255,255,.06);border:2px solid rgba(99,102,241,.5);border-radius:16px;padding:17px 22px;font-size:21px;font-weight:800;font-family:'Nunito',sans-serif;color:white;text-align:center;outline:none;transition:.3s;text-transform:uppercase;}
.id-input:focus{border-color:var(--purple);box-shadow:0 0 18px rgba(99,102,241,.3);}
.id-input.shake{animation:idShake .35s ease;border-color:var(--red)!important;}
@keyframes idShake{0%,100%{transform:translateX(0)}20%{transform:translateX(-7px)}60%{transform:translateX(7px)}}
.id-submit-btn{width:100%;margin-top:11px;padding:15px;border:none;border-radius:16px;background:linear-gradient(135deg,var(--purple),#a855f7);color:white;font-family:'Nunito',sans-serif;font-weight:900;font-size:16px;cursor:pointer;box-shadow:0 6px 0 var(--purple-dark);transition:.2s;display:flex;align-items:center;justify-content:center;gap:10px;}
.id-submit-btn:active{transform:translateY(3px);box-shadow:0 2px 0 var(--purple-dark);}
.id-submit-btn:disabled{opacity:.45;cursor:not-allowed;}

/* Hint strip */
.hint-strip{display:none;margin:8px auto 0;max-width:680px;background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.3);border-radius:12px;padding:10px 18px;font-size:13px;font-weight:700;color:#fcd34d;line-height:1.4;text-align:center;}

/* ═══════════════════════════════════════════════════════════════════════
   POWER-UP BAR  (fixed bottom, replaces old powerup-bar)
   ═══════════════════════════════════════════════════════════════════════ */
.pu-bar{
    position:fixed;bottom:0;left:0;right:0;z-index:50;
    background:rgba(8,8,18,.92);backdrop-filter:blur(16px);
    border-top:1px solid rgba(255,255,255,.07);
    padding:8px 12px;
    display:none; /* shown only on question screen */
    align-items:center;justify-content:center;gap:8px;
}

/* individual power-up card */
.pu-card{
    display:flex;flex-direction:column;align-items:center;gap:3px;
    border:1.5px solid rgba(255,255,255,.12);border-radius:14px;
    background:rgba(255,255,255,.04);
    padding:8px 10px;cursor:pointer;min-width:68px;flex-shrink:0;
    position:relative;transition:transform .18s,border-color .18s,background .18s;
    font-family:'Nunito',sans-serif;
}
.pu-card:hover:not(.pu-spent){border-color:rgba(255,255,255,.3);background:rgba(255,255,255,.09);transform:translateY(-3px);}
.pu-card.pu-active{border-color:var(--gold);background:rgba(245,158,11,.12);}
.pu-card.pu-spent{opacity:.25;cursor:not-allowed;filter:grayscale(.8);}

.pu-emoji{font-size:1.35rem;line-height:1;}
.pu-name{font-size:.6rem;font-weight:800;color:rgba(255,255,255,.65);text-align:center;white-space:nowrap;max-width:68px;overflow:hidden;text-overflow:ellipsis;}
.pu-uses{
    position:absolute;top:-7px;right:-7px;
    background:var(--red);color:white;
    font-size:.5rem;font-weight:900;
    padding:2px 5px;border-radius:20px;
    line-height:1.2;
}

/* divider between groups */
.pu-sep{width:1px;height:50px;background:rgba(255,255,255,.08);flex-shrink:0;}

/* pu-bar section label */
.pu-sec{
    font-size:.55rem;font-weight:800;
    color:rgba(255,255,255,.25);text-transform:uppercase;letter-spacing:.5px;
    writing-mode:vertical-rl;transform:rotate(180deg);flex-shrink:0;
}

/* ═══════════════════════════════════════════════════════════════════════
   SHIELD / STREAK SAVER active banners
   ═══════════════════════════════════════════════════════════════════════ */
.active-banner{
    display:none;width:100%;max-width:680px;margin:0 auto 8px;
    align-items:center;gap:8px;padding:7px 16px;border-radius:12px;
    font-size:13px;font-weight:800;
}
#banner-shield{background:rgba(99,102,241,.14);border:1px solid rgba(99,102,241,.35);color:#a5b4fc;}
#banner-streak{background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.35);color:#fca5a5;}

/* ═══════════════════════════════════════════════════════════════════════
   POWER-UP TOAST
   ═══════════════════════════════════════════════════════════════════════ */
#pu-toast{
    position:fixed;top:72px;left:50%;
    transform:translateX(-50%) translateY(-14px);
    background:rgba(8,8,18,.96);border:1px solid rgba(255,255,255,.14);
    color:white;padding:9px 22px;border-radius:30px;
    font-size:13px;font-weight:800;pointer-events:none;
    opacity:0;transition:opacity .22s,transform .22s;
    z-index:200;white-space:nowrap;
}
#pu-toast.show{opacity:1;transform:translateX(-50%) translateY(0);}

/* ═══════════════════════════════════════════════════════════════════════
   ANSWERED SCREEN
   ═══════════════════════════════════════════════════════════════════════ */
.answered-icon{font-size:68px;margin-bottom:18px;animation:bounceIn .5s cubic-bezier(.34,1.56,.64,1);}
@keyframes bounceIn{from{transform:scale(0)}to{transform:scale(1)}}
.answered-label{font-family:'Luckiest Guy',cursive;font-size:34px;letter-spacing:2px;margin-bottom:8px;}
.points-earned{font-family:'Luckiest Guy',cursive;font-size:60px;color:var(--gold);text-shadow:0 0 28px rgba(245,158,11,.5);margin:8px 0;animation:pointsPop .6s cubic-bezier(.34,1.56,.64,1);}
@keyframes pointsPop{from{transform:scale(.5) translateY(20px);opacity:0}to{transform:scale(1) translateY(0);opacity:1}}
.pts-breakdown{display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin:12px 0;}
.pts-chip{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);padding:7px 14px;border-radius:10px;font-size:12px;font-weight:800;}
.streak-badge{display:inline-flex;align-items:center;gap:8px;background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.4);padding:7px 18px;border-radius:12px;font-weight:900;font-size:17px;color:#ff6b6b;margin:8px 0;}
.wait-next{color:rgba(255,255,255,.4);font-size:13px;margin-top:18px;animation:fadeInOut 2s infinite;}
@keyframes fadeInOut{0%,100%{opacity:.4}50%{opacity:1}}

/* ═══════════════════════════════════════════════════════════════════════
   LEADERBOARD
   ═══════════════════════════════════════════════════════════════════════ */
.lb-screen{justify-content:flex-start;padding-top:80px;overflow-y:auto;}
.lb-title{font-family:'Luckiest Guy',cursive;font-size:40px;color:var(--gold);letter-spacing:3px;text-shadow:0 0 28px rgba(245,158,11,.5);margin-bottom:22px;text-align:center;}
.lb-player-row{display:flex;align-items:center;gap:11px;padding:13px 18px;border-radius:18px;margin-bottom:8px;width:100%;max-width:500px;animation:rowSlide .4s ease backwards;}
@keyframes rowSlide{from{transform:translateX(-30px);opacity:0}to{transform:translateX(0);opacity:1}}
.lb-player-row.me{background:rgba(99,102,241,.2);border:1px solid rgba(99,102,241,.5);transform:scale(1.03);}
.lb-player-row:not(.me){background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.06);}
.lb-pos{font-size:21px;min-width:34px;text-align:center;}
.lb-av{width:40px;height:40px;border-radius:10px;object-fit:cover;}
.lb-details{flex:1;text-align:left;}
.lb-nick{font-weight:900;font-size:15px;}
.lb-pts{font-size:12px;color:rgba(255,255,255,.5);}
.lb-score-big{font-weight:900;font-size:21px;color:var(--gold);}

/* ═══════════════════════════════════════════════════════════════════════
   FINISHED
   ═══════════════════════════════════════════════════════════════════════ */
.trophy-emoji{font-size:78px;animation:trophySpin 1s cubic-bezier(.34,1.56,.64,1) forwards;display:block;margin-bottom:18px;}
@keyframes trophySpin{from{transform:scale(0) rotate(-180deg)}to{transform:scale(1) rotate(0deg)}}
.finished-title{font-family:'Luckiest Guy',cursive;font-size:46px;color:var(--gold);letter-spacing:3px;text-shadow:0 0 38px rgba(245,158,11,.6);}
.my-final-rank{background:rgba(99,102,241,.15);border:1px solid rgba(99,102,241,.4);padding:18px 38px;border-radius:20px;margin:18px 0;}
.my-rank-num{font-family:'Luckiest Guy',cursive;font-size:68px;line-height:1;}
.my-rank-label{color:rgba(255,255,255,.5);font-size:13px;font-weight:700;}
.my-final-score{font-size:30px;font-weight:900;color:var(--gold);margin:8px 0;}

@keyframes floatParticle{0%{transform:translateY(0) rotate(0deg);opacity:1}100%{transform:translateY(-300px) rotate(720deg);opacity:0}}
</style>
</head>
<body>

<!-- ─── HUD ──────────────────────────────────────────────────────────────── -->
<div class="hud" id="main-hud" style="display:none;">
    <img class="hud-avatar" src="<?php echo $avatar_file; ?>" onerror="this.style.display='none'">
    <div class="hud-name"><?php echo htmlspecialchars(strtoupper($nickname)); ?></div>
    <div class="hud-streak" id="hud-streak">🔥 <span id="streak-count">0</span>x</div>
    <div class="hud-pu-count" id="hud-pu-count"><i class="fa-solid fa-bolt" style="font-size:10px;"></i> <span id="pu-left">5</span> power-ups left</div>
    <div class="hud-score">⭐ <span id="hud-score-display">0</span></div>
</div>

<!-- ─── SCREEN 1: WAITING ─────────────────────────────────────────────────── -->
<div class="screen active" id="screen-waiting">
    <div style="text-align:center;">
        <div class="waiting-icon">⚡</div>
        <div class="waiting-title">YOU'RE IN!</div>
        <div class="waiting-sub">Waiting for the teacher to start the quest...</div>
        <div class="waiting-dots"><span></span><span></span><span></span></div>
        <div class="player-card">
            <img src="<?php echo $avatar_file; ?>" onerror="this.style.display='none'" style="width:56px;height:56px;border-radius:12px;object-fit:cover;">
            <div>
                <div class="player-card-name"><?php echo htmlspecialchars(strtoupper($nickname)); ?></div>
                <div class="player-card-sub"><?php echo htmlspecialchars($session['room_code'] ?? ''); ?> · Ready to Quest!</div>
            </div>
        </div>
    </div>
</div>

<!-- ─── SCREEN 2: GET READY ───────────────────────────────────────────────── -->
<div class="screen" id="screen-getready">
    <div style="text-align:center;">
        <div class="getready-label">GET READY!</div>
        <div class="countdown-big" id="countdown">3</div>
        <div style="color:rgba(255,255,255,.4);font-size:14px;margin-top:18px;">Question incoming...</div>
    </div>
</div>

<!-- ─── SCREEN 3: QUESTION ────────────────────────────────────────────────── -->
<div class="screen question-screen" id="screen-question">
    <div class="timer-track"><div class="timer-fill smooth" id="timer-fill" style="width:100%"></div></div>

    <div class="q-header">
        <div class="q-badge" id="q-badge">Q1</div>
        <div class="timer-orb" id="timer-orb">20</div>
    </div>

    <div class="question-card">
        <div class="question-text" id="question-text">Loading...</div>
    </div>

    <!-- Hint strip (fill-blank clue power-up result) -->
    <div class="hint-strip" id="hint-strip"></div>

    <!-- Shield / Streak Saver active banners -->
    <div class="active-banner" id="banner-shield">
        <i class="fa-solid fa-shield-halved"></i> Immunity Shield active — you won't lose points or streak this question
    </div>
    <div class="active-banner" id="banner-streak">
        <i class="fa-solid fa-fire"></i> Streak Saver active — your streak is protected this question
    </div>

    <!-- MCQ answer buttons — RPG Arcane Quest Card style -->
    <div class="answers-grid" id="mcq-grid">
        <button class="ans-btn opt-a" id="btn-a" onclick="submitMCQ('A')">
            <div class="ans-badge-wrap"><div class="ans-badge">A</div></div>
            <span class="ans-text-area" id="txt-a"></span>
        </button>
        <button class="ans-btn opt-b" id="btn-b" onclick="submitMCQ('B')">
            <div class="ans-badge-wrap"><div class="ans-badge">B</div></div>
            <span class="ans-text-area" id="txt-b"></span>
        </button>
        <button class="ans-btn opt-c" id="btn-c" onclick="submitMCQ('C')">
            <div class="ans-badge-wrap"><div class="ans-badge">C</div></div>
            <span class="ans-text-area" id="txt-c"></span>
        </button>
        <button class="ans-btn opt-d" id="btn-d" onclick="submitMCQ('D')">
            <div class="ans-badge-wrap"><div class="ans-badge">D</div></div>
            <span class="ans-text-area" id="txt-d"></span>
        </button>
    </div>

    <!-- Identification input (fill-blank) -->
    <div class="id-wrap" id="id-grid" style="display:none;">
        <div class="fill-answer-hint"><i class="fa-solid fa-keyboard"></i> Type your answer above, then press Submit or Enter</div>
        <button class="id-submit-btn" id="id-submit-btn" onclick="submitIdentification()">
            <i class="fa-solid fa-paper-plane"></i> SUBMIT ANSWER
        </button>
    </div>
</div>

<!-- ─── SCREEN 4: ANSWERED ───────────────────────────────────────────────── -->
<div class="screen" id="screen-answered">
    <div style="text-align:center;">
        <div class="answered-icon" id="ans-icon">✅</div>
        <div class="answered-label" id="ans-label">CORRECT!</div>
        <div class="points-earned" id="ans-points">+1000</div>
        <div class="pts-breakdown" id="pts-breakdown"></div>
        <div class="streak-badge" id="streak-badge" style="display:none;">
            🔥 <span id="streak-badge-num">0</span> Streak!
        </div>
        <div class="wait-next" id="answered-wait-text">Waiting for teacher to advance...</div>
    </div>
</div>

<!-- ─── SCREEN 5: RESULTS ─────────────────────────────────────────────────── -->
<div class="screen" id="screen-results">
    <div style="text-align:center;">
        <div id="result-card"></div>
        <div style="color:rgba(255,255,255,.4);font-size:13px;margin-top:18px;animation:fadeInOut 2s infinite;">
            Waiting for leaderboard...
        </div>
    </div>
</div>

<!-- ─── SCREEN 6: LEADERBOARD ────────────────────────────────────────────── -->
<div class="screen" id="screen-leaderboard">
    <div class="lb-screen" style="width:100%;display:flex;flex-direction:column;align-items:center;">
        <div class="lb-title">🏆 RANKINGS</div>
        <div id="lb-rows" style="width:100%;max-width:500px;"></div>
    </div>
</div>

<!-- ─── SCREEN 7: FINISHED ───────────────────────────────────────────────── -->
<div class="screen" id="screen-finished">
    <div style="text-align:center;">
        <span class="trophy-emoji">🏆</span>
        <div class="finished-title">QUEST COMPLETE!</div>
        <div class="my-final-rank">
            <div class="my-rank-label">YOUR FINAL RANK</div>
            <div class="my-rank-num" id="final-rank">#?</div>
            <div class="my-final-score" id="final-score">0 pts</div>
        </div>
        <div id="finished-lb" style="width:100%;max-width:500px;margin:0 auto 18px;"></div>
        <button onclick="window.location.href='studentdashboard.php'"
                style="padding:15px 38px;border:none;border-radius:16px;background:linear-gradient(135deg,var(--purple),#a855f7);color:white;font-family:'Nunito';font-weight:900;font-size:17px;cursor:pointer;box-shadow:0 5px 0 var(--purple-dark);">
            <i class="fa-solid fa-home"></i> Back to Dashboard
        </button>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     POWER-UP BAR
     — shown on question screen only
     — swaps content dynamically based on question type (MCQ vs fill-blank)
     ═══════════════════════════════════════════════════════════════════════════ -->
<div class="pu-bar" id="pu-bar">

    <!-- ── MCQ set (5 power-ups) ───────────────────────────── -->
    <div id="pu-mcq-set" style="display:none;align-items:center;gap:8px;">
        <span class="pu-sec">MCQ</span>

        <!-- 1. The Eraser (50/50) -->
        <div class="pu-card" id="pu-eraser" onclick="puMCQ_Eraser()">
            <span class="pu-emoji">✂️</span>
            <span class="pu-name">The Eraser</span>
            <span class="pu-uses">1×</span>
        </div>

        <!-- 2. Time Freeze -->
        <div class="pu-card" id="pu-mcq-freeze" onclick="puMCQ_TimeFreeze()">
            <span class="pu-emoji">❄️</span>
            <span class="pu-name" id="mcq-freeze-lbl">Time Freeze</span>
            <span class="pu-uses">1×</span>
        </div>

        <!-- 3. Double Points -->
        <div class="pu-card" id="pu-mcq-double" onclick="puMCQ_DoublePoints()">
            <span class="pu-emoji">⚡</span>
            <span class="pu-name">2× Points</span>
            <span class="pu-uses">1×</span>
        </div>

        <!-- 4. Streak Saver -->
        <div class="pu-card" id="pu-mcq-streak" onclick="puMCQ_StreakSaver()">
            <span class="pu-emoji">🔥</span>
            <span class="pu-name">Streak Saver</span>
            <span class="pu-uses">1×</span>
        </div>

        <!-- 5. Immunity Shield -->
        <div class="pu-card" id="pu-mcq-shield" onclick="puMCQ_Shield()">
            <span class="pu-emoji">🛡️</span>
            <span class="pu-name">Immunity</span>
            <span class="pu-uses">1×</span>
        </div>
    </div>

    <!-- ── Fill-blank set (5 power-ups) ────────────────────── -->
    <div id="pu-fb-set" style="display:none;align-items:center;gap:8px;">
        <span class="pu-sec">HINT</span>

        <!-- 1. Clue / Hint -->
        <div class="pu-card" id="pu-fb-clue" onclick="puFB_Clue()">
            <span class="pu-emoji">💡</span>
            <span class="pu-name">Clue</span>
            <span class="pu-uses">1×</span>
        </div>

        <!-- 2. Time Freeze -->
        <div class="pu-card" id="pu-fb-freeze" onclick="puFB_TimeFreeze()">
            <span class="pu-emoji">❄️</span>
            <span class="pu-name" id="fb-freeze-lbl">Time Freeze</span>
            <span class="pu-uses">1×</span>
        </div>

        <!-- 3. Double Points -->
        <div class="pu-card" id="pu-fb-double" onclick="puFB_DoublePoints()">
            <span class="pu-emoji">⚡</span>
            <span class="pu-name">2× Points</span>
            <span class="pu-uses">1×</span>
        </div>

        <!-- 4. Streak Saver -->
        <div class="pu-card" id="pu-fb-streak" onclick="puFB_StreakSaver()">
            <span class="pu-emoji">🔥</span>
            <span class="pu-name">Streak Saver</span>
            <span class="pu-uses">1×</span>
        </div>

        <!-- 5. Immunity Shield -->
        <div class="pu-card" id="pu-fb-shield" onclick="puFB_Shield()">
            <span class="pu-emoji">🛡️</span>
            <span class="pu-name">Immunity</span>
            <span class="pu-uses">1×</span>
        </div>
    </div>

</div><!-- end pu-bar -->

<!-- Power-up toast -->
<div id="pu-toast"></div>

<!-- ═══════════════════════════════════════════════════════════════════════════
     JAVASCRIPT
     ═══════════════════════════════════════════════════════════════════════════ -->
<script>
const SESSION_ID      = <?php echo $session_id; ?>;
const NICKNAME        = "<?php echo addslashes($nickname); ?>";
const TOTAL_QUESTIONS = <?php echo $session['item_count'] ?? 10; ?>;
const AVATARS         = <?php echo json_encode($avatars); ?>;

/* ── Core state ─────────────────────────────────────────────────────────── */
let currentPhase      = 'waiting';
let myScore           = 0;
let myStreak          = 0;
let lastQId           = null;
let hasAnswered       = false;
let questionStartTime = null;
let pollInterval      = null;
let lastSeenQIdx      = -1;

/* ── Server-synced timer ────────────────────────────────────────────────── */
let localTimeLeft   = 0;
let maxTimeLimit    = 20;
let timerInterval   = null;
const SYNC_THRESHOLD = 1.5;

/* ══════════════════════════════════════════════════════════════════════════
   POWER-UP STATE
   ─────────────────────────────────────────────────────────────────────────
   Rules:
   • 5 power-ups per question type, single-use per quiz session
   • Total usage tracked for the HUD counter (shared pool: 5 uses across
     whichever type the student encounters)
   • Per-question activation flags reset on each new question
   ══════════════════════════════════════════════════════════════════════════ */
const MAX_USES = 5;
let totalPUUsed = 0;   // counts all activations, max 5 across the whole quiz

/* MCQ power-ups — session usage flags */
const MCQ_PU = {
    eraser:       { used: false },
    timeFreeze:   { used: false },
    doublePoints: { used: false },
    streakSaver:  { used: false },
    shield:       { used: false },
};

/* Fill-blank power-ups — session usage flags */
const FB_PU = {
    clue:         { used: false },
    timeFreeze:   { used: false },
    doublePoints: { used: false },
    streakSaver:  { used: false },
    shield:       { used: false },
};

/* Per-question activation state (reset each question) */
let mcqDoubleActive  = false;
let mcqStreakActive  = false;
let mcqShieldActive  = false;
let fbDoubleActive   = false;
let fbStreakActive   = false;
let fbShieldActive   = false;
let timeFrozen       = false;
let freezeCD         = null;   // countdown interval for time freeze
let currentQType     = 'multiple_choice';
let currentQText     = '';

/* ── HUD counter ─────────────────────────────────────────────────────────── */
function updatePUCounter() {
    const left = MAX_USES - totalPUUsed;
    document.getElementById('pu-left').innerText = left;
}

function canUsePU() {
    return totalPUUsed < MAX_USES;
}

/* ═══════════════════════════════════════════════════════════════════════════
   TIMER
   ═══════════════════════════════════════════════════════════════════════════ */
function startTimer(serverTL, maxTime) {
    clearInterval(timerInterval);
    maxTimeLimit  = maxTime;
    localTimeLeft = Math.ceil(serverTL);
    renderTimerUI(localTimeLeft, maxTimeLimit);

    timerInterval = setInterval(() => {
        if (hasAnswered || timeFrozen) return;
        localTimeLeft = Math.max(0, localTimeLeft - 1);
        renderTimerUI(localTimeLeft, maxTimeLimit);
        if (localTimeLeft <= 0) { clearInterval(timerInterval); disableAnswerBtns(); }
    }, 1000);
}

function syncTimerToServer(serverTL) {
    if (hasAnswered || timeFrozen || serverTL == null) return;
    if (Math.abs(localTimeLeft - serverTL) > SYNC_THRESHOLD) {
        const fill = document.getElementById('timer-fill');
        if (fill) fill.classList.remove('smooth');
        localTimeLeft = Math.max(0, Math.ceil(serverTL));
        renderTimerUI(localTimeLeft, maxTimeLimit);
        requestAnimationFrame(() => requestAnimationFrame(() => { if (fill) fill.classList.add('smooth'); }));
    }
}

function renderTimerUI(tl, max) {
    const pct      = (tl / max) * 100;
    const fill     = document.getElementById('timer-fill');
    const orb      = document.getElementById('timer-orb');
    const isDanger = tl <= 5, isWarn = tl <= 10 && tl > 5;
    if (fill) {
        if (timeFrozen) { fill.className = 'timer-fill frozen'; }
        else { fill.style.width = pct + '%'; fill.className = 'timer-fill smooth' + (isDanger ? ' danger' : ''); }
    }
    if (orb) {
        orb.innerText  = tl;
        orb.className  = 'timer-orb' + (timeFrozen ? ' frozen' : isDanger ? ' danger' : isWarn ? ' warning' : '');
    }
}

/* ═══════════════════════════════════════════════════════════════════════════
   POLLING
   ═══════════════════════════════════════════════════════════════════════════ */
function poll() {
    fetch(`get_quiz_state.php?session_id=${SESSION_ID}&nickname=${encodeURIComponent(NICKNAME)}`)
        .then(r => r.json()).then(handleState)
        .catch(e => console.error('Poll error:', e));
}

function handleState(data) {
    const phase = data.phase || 'lobby';
    const qIdx  = data.current_question_index || 0;

    if (phase === 'question' && currentPhase === 'question' && data.time_left !== undefined)
        syncTimerToServer(data.time_left);

    // A pending/answered student is still on the same question. Only show
    // the countdown when the teacher actually advances to a new question.
    if (phase === 'question' && qIdx !== lastSeenQIdx) {
        lastSeenQIdx = qIdx;
        hasAnswered  = false;
        showGetReady(() => {
            showQuestionScreen(data.question, qIdx, data.time_left);
            if (data.my_answer && data.my_answer.submitted) {
                hasAnswered = true;
                clearInterval(timerInterval);
                disableAnswerBtns();
                showPendingScreen();
            }
        });

    } else if (phase === 'results' && currentPhase !== 'results') {
        clearInterval(timerInterval);
        const result = data.my_answer;
        if (result && result.submitted && !result.pending) {
            const resultData = {
                is_correct: result.is_correct,
                points_earned: result.points_earned,
                streak: result.streak || 0,
                base_points: result.is_correct ? 1000 : 0,
                correct_answer: result.correct_answer || null,
            };
            const protectedStreak = !result.is_correct
                && (result.answer_given === 'SKIP' || result.streak > 0);
            processResult(resultData, protectedStreak, true);
        } else if (result && result.pending) {
            showPendingScreen();
        } else {
            showAnsweredScreen(false, 0, null, "NO ANSWER", '⏰');
        }

    } else if (phase === 'leaderboard' && currentPhase !== 'leaderboard') {
        showLeaderboardScreen(data.leaderboard || []);

    } else if (phase === 'finished' && currentPhase !== 'finished') {
        showFinishedScreen(data.leaderboard || []);
    }

    currentPhase = phase;
    if (phase === 'leaderboard' && data.leaderboard) renderLeaderboardRows(data.leaderboard);
}

/* ═══════════════════════════════════════════════════════════════════════════
   SCREEN LOGIC
   ═══════════════════════════════════════════════════════════════════════════ */
function showGetReady(callback) {
    showScreen('getready');
    document.getElementById('main-hud').style.display = 'flex';
    let count = 3;
    const el  = document.getElementById('countdown');
    const tick = () => {
        el.innerText = count;
        el.style.animation = 'none'; el.offsetHeight;
        el.style.animation = 'countAnim .5s cubic-bezier(.34,1.56,.64,1) forwards';
    };
    tick();
    const iv = setInterval(() => { count--; if (count <= 0) { clearInterval(iv); callback(); } else tick(); }, 900);
}

function showQuestionScreen(q, qIdx, serverTL) {
    if (!q) return;
    currentPhase      = 'question';
    lastQId           = q.id;
    hasAnswered       = false;
    questionStartTime = Date.now();
    currentQText      = q.text;
    currentQType      = q.type;

    resetPerQuestionPU();

    document.getElementById('q-badge').innerText    = `Q${qIdx} OF ${TOTAL_QUESTIONS}`;

    const isFB = (q.type === 'identification');
    renderQuestionText(q.text, isFB);

    if (!isFB) {
        /* ── MCQ ── */
        document.getElementById('mcq-grid').style.display = 'grid';
        document.getElementById('id-grid').style.display  = 'none';
        document.getElementById('pu-mcq-set').style.display = 'flex';
        document.getElementById('pu-fb-set').style.display  = 'none';

        ['A','B','C','D'].forEach(l => {
            const btn = document.getElementById(`btn-${l.toLowerCase()}`);
            const txt = document.getElementById(`txt-${l.toLowerCase()}`);
            if (btn) { btn.disabled = false; btn.style.opacity = '1'; btn.className = `ans-btn opt-${l.toLowerCase()}`; }
            if (txt && q.options) txt.innerText = q.options[l] || '';
        });
    } else {
        /* ── Fill-blank ── */
        document.getElementById('mcq-grid').style.display = 'none';
        document.getElementById('id-grid').style.display  = 'block';
        document.getElementById('pu-mcq-set').style.display = 'none';
        document.getElementById('pu-fb-set').style.display  = 'flex';

        const inp = document.getElementById('id-input');
        const btn = document.getElementById('id-submit-btn');
        if (inp) { inp.value = ''; inp.disabled = false; inp.className = 'inline-id-input'; }
        if (btn) btn.disabled = false;
        setTimeout(() => inp && inp.focus(), 300);
    }

    /* Show power-up bar */
    document.getElementById('pu-bar').style.display = 'flex';
    document.getElementById('hint-strip').style.display = 'none';

    startTimer(serverTL !== undefined ? serverTL : q.time_limit, q.time_limit);
    showScreen('question');
}

function renderQuestionText(text, isFillBlank) {
    const questionText = document.getElementById('question-text');
    if (!questionText) return;

    questionText.replaceChildren();
    questionText.classList.toggle('fill-blank-text', isFillBlank);

    if (!isFillBlank) {
        questionText.appendChild(document.createTextNode(String(text || '')));
        return;
    }

    // Fill-blank questions use the same inline blank style as the solo quiz.
    // Only the first blank is interactive because the Synchro answer contract
    // accepts one canonical answer per question.
    const parts = String(text || '').split(/_{3,}/);
    if (parts.length === 1) parts.push('');
    parts.forEach((part, index) => {
        questionText.appendChild(document.createTextNode(part));
        if (index >= parts.length - 1) return;

        if (index > 0) {
            questionText.appendChild(document.createTextNode('____'));
            return;
        }

        const input = document.createElement('input');
        input.type = 'text';
        input.id = 'id-input';
        input.className = 'inline-id-input';
        input.placeholder = '...';
        input.autocomplete = 'off';
        input.autocorrect = 'off';
        input.spellcheck = false;
        input.maxLength = 100;
        input.addEventListener('keydown', event => {
            if (event.key === 'Enter') submitIdentification();
        });
        questionText.appendChild(input);
    });
}

/* Per-question reset (not per-session) */
function resetPerQuestionPU() {
    mcqDoubleActive = false; mcqStreakActive = false; mcqShieldActive = false;
    fbDoubleActive  = false; fbStreakActive  = false; fbShieldActive  = false;
    timeFrozen      = false;

    if (freezeCD) { clearInterval(freezeCD); freezeCD = null; }

    document.getElementById('banner-shield').style.display = 'none';
    document.getElementById('banner-streak').style.display = 'none';
    document.getElementById('hint-strip').style.display    = 'none';
    document.getElementById('hint-strip').innerText        = '';
    document.getElementById('mcq-freeze-lbl').innerText    = 'Time Freeze';
    document.getElementById('fb-freeze-lbl').innerText     = 'Time Freeze';

    // Remove active glow (but not spent state)
    ['pu-mcq-double','pu-mcq-streak','pu-mcq-shield',
     'pu-fb-double', 'pu-fb-streak', 'pu-fb-shield'].forEach(id => {
        document.getElementById(id)?.classList.remove('pu-active');
    });
}

/* ── Disable answer buttons ────────────────────────────────────────────── */
function disableAnswerBtns() {
    ['a','b','c','d'].forEach(l => { const b = document.getElementById(`btn-${l}`); if (b) b.disabled = true; });
    const ib = document.getElementById('id-submit-btn');
    if (ib) ib.disabled = true;
}

function enableAnswerBtns() {
    if (hasAnswered) return;
    ['a','b','c','d'].forEach(l => {
        const b = document.getElementById(`btn-${l}`);
        if (b) b.disabled = false;
    });
    const ib = document.getElementById('id-submit-btn');
    if (ib && document.getElementById('id-input')) ib.disabled = false;
}

/* ═══════════════════════════════════════════════════════════════════════════
   MCQ SUBMISSION
   ═══════════════════════════════════════════════════════════════════════════ */
function submitMCQ(letter) {
    if (hasAnswered || !lastQId) return;

    // Immunity Shield: skip safely
    if (mcqShieldActive) { activateShieldSkip(); return; }

    hasAnswered = true;
    clearInterval(timerInterval);
    stopFreeze();

    const timeTaken = Date.now() - (questionStartTime || Date.now());
    document.getElementById(`btn-${letter.toLowerCase()}`)?.classList.add('selected');
    disableAnswerBtns();

    const powerups = [];
    if (mcqDoubleActive) powerups.push('double_points');
    if (mcqStreakActive) powerups.push('streak_saver');
    const pu = powerups.join(',');

    fetch('submit_answer.php', {
        method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`session_id=${SESSION_ID}&question_id=${lastQId}&nickname=${encodeURIComponent(NICKNAME)}&answer=${letter}&time_taken_ms=${timeTaken}&powerup=${pu}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.pending) {
            showPendingScreen();
        } else if (!data.success) {
            hasAnswered = false;
            enableAnswerBtns();
            showPUToast(data.error || 'Could not lock your answer.');
        }
    })
    .catch(() => {
        hasAnswered = false;
        enableAnswerBtns();
        showPUToast('Connection error. Please try again.');
    });
}

/* ═══════════════════════════════════════════════════════════════════════════
   FILL-BLANK SUBMISSION
   ═══════════════════════════════════════════════════════════════════════════ */
function submitIdentification() {
    if (hasAnswered || !lastQId) return;

    const input  = document.getElementById('id-input');
    const answer = input ? input.value.trim() : '';
    if (!answer) {
        if (input) { input.classList.add('shake'); setTimeout(() => input.classList.remove('shake'), 400); }
        return;
    }

    // Immunity Shield: skip safely
    if (fbShieldActive) { activateShieldSkip(); return; }

    hasAnswered = true;
    clearInterval(timerInterval);
    stopFreeze();

    const timeTaken = Date.now() - (questionStartTime || Date.now());
    if (input) input.disabled = true;
    document.getElementById('id-submit-btn').disabled = true;
    document.getElementById('banner-shield').style.display = 'none';
    document.getElementById('banner-streak').style.display = 'none';

    const powerups = [];
    if (fbDoubleActive) powerups.push('double_points');
    if (fbStreakActive) powerups.push('streak_saver');
    const pu = powerups.join(',');

    fetch('submit_answer.php', {
        method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`session_id=${SESSION_ID}&question_id=${lastQId}&nickname=${encodeURIComponent(NICKNAME)}&answer=${encodeURIComponent(answer.toUpperCase())}&time_taken_ms=${timeTaken}&powerup=${pu}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.pending) {
            showPendingScreen();
        } else if (!data.success) {
            hasAnswered = false;
            if (input) input.disabled = false;
            document.getElementById('id-submit-btn').disabled = false;
            showPUToast(data.error || 'Could not lock your answer.');
        }
    })
    .catch(() => {
        hasAnswered = false;
        if (input) input.disabled = false;
        document.getElementById('id-submit-btn').disabled = false;
        showPUToast('Connection error. Please try again.');
    });
}

/* Shield skip — submit SKIP so server records non-answer but no streak penalty */
function activateShieldSkip() {
    hasAnswered = true;
    clearInterval(timerInterval);
    stopFreeze();
    disableAnswerBtns();
    document.getElementById('banner-shield').style.display = 'none';
    document.getElementById('banner-streak').style.display = 'none';

    fetch('submit_answer.php', {
        method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`session_id=${SESSION_ID}&question_id=${lastQId}&nickname=${encodeURIComponent(NICKNAME)}&answer=SKIP&time_taken_ms=0&powerup=shield_skip`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.pending) {
            showPendingScreen('SKIPPED — ANSWER LOCKED', '🛡️');
        } else if (!data.success) {
            hasAnswered = false;
            showPUToast(data.error || 'Could not lock the skip.');
        }
    })
    .catch(() => {
        hasAnswered = false;
        showPUToast('Connection error. Please try again.');
    });
}

/* ═══════════════════════════════════════════════════════════════════════════
   PROCESS RESULT
   ═══════════════════════════════════════════════════════════════════════════ */
function showPendingScreen(label = 'ANSWER LOCKED', icon = '🔒') {
    currentPhase = 'pending';
    document.getElementById('pu-bar').style.display = 'none';
    document.getElementById('ans-icon').innerText = icon;
    document.getElementById('ans-label').innerText = label;
    document.getElementById('ans-label').style.color = '#a5b4fc';
    document.getElementById('ans-points').innerText = '—';
    document.getElementById('ans-points').style.color = 'rgba(255,255,255,.55)';
    document.getElementById('pts-breakdown').innerHTML =
        '<div class="pts-chip">The teacher will reveal if it is correct.</div>';
    document.getElementById('streak-badge').style.display = 'none';
    document.getElementById('answered-wait-text').innerText =
        'Waiting for the teacher to reveal results...';
    showScreen('answered');
}

function processResult(data, streakProtected, revealed = false) {
    const correct = data.is_correct;
    const points  = data.points_earned || 0;
    let   streak  = data.streak || 0;

    // Streak Saver: if wrong but streak protected, keep previous streak display
    if (!correct && streakProtected) { streak = myStreak; }

    myScore  += points;
    myStreak  = correct ? streak : (streakProtected ? myStreak : 0);

    document.getElementById('hud-score-display').innerText = myScore.toLocaleString();
    if (myStreak >= 2) {
        document.getElementById('hud-streak').style.display = 'flex';
        document.getElementById('streak-count').innerText   = myStreak;
    } else {
        document.getElementById('hud-streak').style.display = 'none';
    }

    document.getElementById('pu-bar').style.display = 'none';
    showAnsweredScreen(correct, points, data, correct ? 'CORRECT!' : 'WRONG!', correct ? '✅' : '❌');
}

function showAnsweredScreen(correct, points, data, label, icon) {
    currentPhase = correct ? 'answered-correct' : 'answered-wrong';
    document.getElementById('ans-icon').innerText    = icon;
    document.getElementById('ans-label').innerText   = label;
    document.getElementById('ans-label').style.color = correct ? '#10b981' : '#ef4444';
    document.getElementById('ans-points').innerText  = correct ? `+${points.toLocaleString()}` : '0';
    document.getElementById('ans-points').style.color = correct ? 'var(--gold)' : 'rgba(255,255,255,.3)';

    const chips = [];
    if (data && correct) {
        if (data.base_points)  chips.push(`🎯 Base: +${data.base_points}`);
        if (data.speed_bonus)  chips.push(`⚡ Speed: +${data.speed_bonus}`);
        if (data.streak_bonus) chips.push(`🔥 Streak: +${data.streak_bonus}`);
    } else if (data && !correct && data.correct_answer) {
        chips.push(`Correct answer: ${data.correct_answer}`);
    }
    document.getElementById('pts-breakdown').innerHTML = chips.map(c => `<div class="pts-chip">${c}</div>`).join('');

    const showStreak = myStreak >= 2;
    document.getElementById('streak-badge').style.display    = showStreak ? 'inline-flex' : 'none';
    document.getElementById('streak-badge-num').innerText    = myStreak;
    document.getElementById('answered-wait-text').innerText =
        'Waiting for teacher to advance...';

    showScreen('answered');
    if (correct) spawnParticles();
}

/* ═══════════════════════════════════════════════════════════════════════════
   MCQ POWER-UP ACTIVATORS
   ═══════════════════════════════════════════════════════════════════════════ */

/* ① The Eraser (50/50) — removes 2 wrong answer buttons */
function puMCQ_Eraser() {
    if (MCQ_PU.eraser.used || !canUsePU() || hasAnswered) return;
    MCQ_PU.eraser.used = true;
    spendPU('pu-eraser');

    // Collect the letter buttons, shuffle, remove 2 (ensure correct survives)
    const allLetters = ['a','b','c','d'];
    const shuffled   = allLetters.sort(() => Math.random() - .5);
    let removed = 0;
    for (const l of shuffled) {
        if (removed >= 2) break;
        const btn = document.getElementById(`btn-${l}`);
        if (btn && !btn.disabled) {
            btn.style.opacity = '.15';
            btn.disabled = true;
            removed++;
        }
    }
    showPUToast('✂️ Two wrong answers removed!');
}

/* ② Time Freeze (MCQ) */
function puMCQ_TimeFreeze() {
    if (MCQ_PU.timeFreeze.used || !canUsePU() || timeFrozen || hasAnswered) return;
    MCQ_PU.timeFreeze.used = true;
    spendPU('pu-mcq-freeze');
    startFreeze('mcq-freeze-lbl');
}

/* ③ Double Points (MCQ) */
function puMCQ_DoublePoints() {
    if (MCQ_PU.doublePoints.used || !canUsePU() || hasAnswered) return;
    MCQ_PU.doublePoints.used = true;
    mcqDoubleActive = true;
    spendPU('pu-mcq-double');
    document.getElementById('pu-mcq-double').classList.add('pu-active');
    showPUToast('⚡ Double points active for this question!');
}

/* ④ Streak Saver (MCQ) */
function puMCQ_StreakSaver() {
    if (MCQ_PU.streakSaver.used || !canUsePU() || hasAnswered) return;
    MCQ_PU.streakSaver.used = true;
    mcqStreakActive = true;
    spendPU('pu-mcq-streak');
    document.getElementById('pu-mcq-streak').classList.add('pu-active');
    document.getElementById('banner-streak').style.display = 'flex';
    showPUToast('🔥 Streak Saver active — streak protected this question!');
}

/* ⑤ Immunity Shield (MCQ) — next click skips safely */
function puMCQ_Shield() {
    if (MCQ_PU.shield.used || !canUsePU() || hasAnswered) return;
    MCQ_PU.shield.used = true;
    mcqShieldActive = true;
    spendPU('pu-mcq-shield');
    document.getElementById('pu-mcq-shield').classList.add('pu-active');
    document.getElementById('banner-shield').style.display = 'flex';
    showPUToast('🛡️ Immunity Shield active — tap any answer to skip safely!');
}

/* ═══════════════════════════════════════════════════════════════════════════
   FILL-BLANK POWER-UP ACTIVATORS
   ═══════════════════════════════════════════════════════════════════════════ */

/* ① Clue / Hint — derives a context clue from the question sentence */
function puFB_Clue() {
    if (FB_PU.clue.used || !canUsePU() || hasAnswered) return;
    FB_PU.clue.used = true;
    spendPU('pu-fb-clue');

    const words = currentQText.toLowerCase()
        .replace(/_{3,}/g, '')
        .split(/\W+/)
        .filter(w => w.length > 3);
    const clueWords = [...new Set(words)].slice(0, 3);
    const clue = clueWords.length
        ? `💡 Clue: related to — "${clueWords.join('  ·  ')}"`
        : '💡 Look closely at the sentence for context clues';

    const strip = document.getElementById('hint-strip');
    strip.innerText     = clue;
    strip.style.display = 'block';
    showPUToast('💡 Clue revealed!');
}

/* ② Time Freeze (fill-blank) */
function puFB_TimeFreeze() {
    if (FB_PU.timeFreeze.used || !canUsePU() || timeFrozen || hasAnswered) return;
    FB_PU.timeFreeze.used = true;
    spendPU('pu-fb-freeze');
    startFreeze('fb-freeze-lbl');
}

/* ③ Double Points (fill-blank) */
function puFB_DoublePoints() {
    if (FB_PU.doublePoints.used || !canUsePU() || hasAnswered) return;
    FB_PU.doublePoints.used = true;
    fbDoubleActive = true;
    spendPU('pu-fb-double');
    document.getElementById('pu-fb-double').classList.add('pu-active');
    showPUToast('⚡ Double points active for this question!');
}

/* ④ Streak Saver (fill-blank) */
function puFB_StreakSaver() {
    if (FB_PU.streakSaver.used || !canUsePU() || hasAnswered) return;
    FB_PU.streakSaver.used = true;
    fbStreakActive = true;
    spendPU('pu-fb-streak');
    document.getElementById('pu-fb-streak').classList.add('pu-active');
    document.getElementById('banner-streak').style.display = 'flex';
    showPUToast('🔥 Streak Saver active — streak protected this question!');
}

/* ⑤ Immunity Shield (fill-blank) — submitting auto-skips safely */
function puFB_Shield() {
    if (FB_PU.shield.used || !canUsePU() || hasAnswered) return;
    FB_PU.shield.used = true;
    fbShieldActive = true;
    spendPU('pu-fb-shield');
    document.getElementById('pu-fb-shield').classList.add('pu-active');
    document.getElementById('banner-shield').style.display = 'flex';
    showPUToast('🛡️ Immunity Shield active — Submit to skip this question safely!');
}

/* ═══════════════════════════════════════════════════════════════════════════
   SHARED POWER-UP HELPERS
   ═══════════════════════════════════════════════════════════════════════════ */

/* Mark button spent, increment global counter */
function spendPU(btnId) {
    const el = document.getElementById(btnId);
    if (el) el.classList.add('pu-spent');
    totalPUUsed++;
    updatePUCounter();
    // Disable ALL remaining power-ups if the cap is reached
    if (totalPUUsed >= MAX_USES) disableAllRemainingPU();
}

function disableAllRemainingPU() {
    document.querySelectorAll('.pu-card:not(.pu-spent)').forEach(el => el.classList.add('pu-spent'));
    showPUToast('⚠️ All power-up uses spent for this quiz!', 3000);
}

/* Time Freeze shared logic */
function startFreeze(labelId) {
    timeFrozen = true;
    let secs = 15;
    renderTimerUI(localTimeLeft, maxTimeLimit);
    document.getElementById(labelId).innerText = `❄️ ${secs}s`;
    showPUToast('❄️ Timer frozen for 15 seconds!');

    freezeCD = setInterval(() => {
        secs--;
        document.getElementById(labelId).innerText = `❄️ ${secs}s`;
        if (secs <= 0) {
            clearInterval(freezeCD); freezeCD = null;
            timeFrozen = false;
            document.getElementById(labelId).innerText = 'Unfrozen ✓';
            renderTimerUI(localTimeLeft, maxTimeLimit);
            showPUToast('❄️ Timer resumed!');
        }
    }, 1000);
}

function stopFreeze() {
    if (freezeCD) { clearInterval(freezeCD); freezeCD = null; }
    timeFrozen = false;
}

/* Toast */
let toastTimer;
function showPUToast(msg, ms = 2500) {
    const t = document.getElementById('pu-toast');
    if (!t) return;
    t.innerText = msg; t.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => t.classList.remove('show'), ms);
}

/* ═══════════════════════════════════════════════════════════════════════════
   LEADERBOARD & FINISH
   ═══════════════════════════════════════════════════════════════════════════ */
function showLeaderboardScreen(lb) {
    currentPhase = 'leaderboard';
    document.getElementById('main-hud').style.display = 'flex';
    document.getElementById('pu-bar').style.display    = 'none';
    renderLeaderboardRows(lb);
    showScreen('leaderboard');
}

function renderLeaderboardRows(lb) {
    const medals = ['🥇','🥈','🥉'];
    document.getElementById('lb-rows').innerHTML = lb.map((p, i) => `
        <div class="lb-player-row ${p.nickname===NICKNAME?'me':''}" style="animation-delay:${i*.08}s">
            <div class="lb-pos">${medals[i] || '#'+(i+1)}</div>
            <img class="lb-av" src="${AVATARS[p.avatar_key]||''}" onerror="this.style.display='none'">
            <div class="lb-details">
                <div class="lb-nick">${p.nickname}${p.nickname===NICKNAME?' ← YOU':''}</div>
                <div class="lb-pts">${p.correct_answers} correct${p.streak>1?' · 🔥'+p.streak+' streak':''}</div>
            </div>
            <div class="lb-score-big">${Number(p.total_score).toLocaleString()}</div>
        </div>`).join('');
}

function showFinishedScreen(lb) {
    currentPhase = 'finished';
    clearInterval(pollInterval);
    document.getElementById('pu-bar').style.display = 'none';
    const medals   = ['🥇','🥈','🥉'];
    const myRankIdx = lb.findIndex(p => p.nickname === NICKNAME);
    document.getElementById('final-rank').innerText  = myRankIdx >= 0 ? (medals[myRankIdx] || `#${myRankIdx+1}`) : '?';
    document.getElementById('final-score').innerText = `${myScore.toLocaleString()} pts`;
    document.getElementById('finished-lb').innerHTML = lb.slice(0,5).map((p,i) => `
        <div style="display:flex;align-items:center;gap:10px;padding:11px 18px;background:rgba(255,255,255,.04);border-radius:14px;margin-bottom:7px;${p.nickname===NICKNAME?'border:1px solid rgba(99,102,241,.5);':''}">
            <span style="font-size:18px;">${medals[i]||'#'+(i+1)}</span>
            <img style="width:34px;height:34px;border-radius:8px;" src="${AVATARS[p.avatar_key]||''}" onerror="this.style.display='none'">
            <div style="flex:1;font-weight:800;">${p.nickname}</div>
            <div style="font-weight:900;color:var(--gold);">${Number(p.total_score).toLocaleString()}</div>
        </div>`).join('');

    fetch('award_synchro_xp.php', {
        method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`session_id=${SESSION_ID}&nickname=${encodeURIComponent(NICKNAME)}`
    }).catch(()=>{});

    showScreen('finished');
    setTimeout(spawnFireworks, 500);
}

/* ═══════════════════════════════════════════════════════════════════════════
   UTILITIES
   ═══════════════════════════════════════════════════════════════════════════ */
function showScreen(name) {
    document.querySelectorAll('.screen').forEach(s => s.classList.remove('active'));
    document.getElementById(`screen-${name}`)?.classList.add('active');
}

function spawnParticles() {
    const colors = ['#f59e0b','#10b981','#6366f1','#ef4444','#3b82f6'];
    for (let i = 0; i < 15; i++) {
        setTimeout(() => {
            const p = document.createElement('div');
            p.style.cssText = `position:fixed;left:${Math.random()*100}vw;top:${Math.random()*50+25}vh;width:8px;height:8px;background:${colors[i%colors.length]};border-radius:${Math.random()>.5?'50%':'2px'};pointer-events:none;z-index:999;animation:floatParticle ${1+Math.random()}s ease forwards;`;
            document.body.appendChild(p);
            setTimeout(() => p.remove(), 2000);
        }, i * 80);
    }
}

function spawnFireworks() {
    const colors = ['#f59e0b','#10b981','#6366f1','#ef4444','#a855f7','#3b82f6'];
    for (let burst = 0; burst < 5; burst++) {
        setTimeout(() => {
            const cx = Math.random() * window.innerWidth;
            const cy = Math.random() * window.innerHeight * .6 + 50;
            for (let i = 0; i < 20; i++) {
                const angle = (i/20) * Math.PI * 2;
                const dist  = 60 + Math.random() * 80;
                const el    = document.createElement('div');
                el.style.cssText = `position:fixed;left:${cx}px;top:${cy}px;width:6px;height:6px;background:${colors[i%colors.length]};border-radius:50%;pointer-events:none;z-index:999;transition:all ${.8+Math.random()*.5}s cubic-bezier(0,.9,.57,1);opacity:1;`;
                document.body.appendChild(el);
                requestAnimationFrame(() => { el.style.transform = `translate(${Math.cos(angle)*dist}px,${Math.sin(angle)*dist}px)`; el.style.opacity = '0'; });
                setTimeout(() => el.remove(), 1500);
            }
        }, burst * 400);
    }
}

pollInterval = setInterval(poll, 1500);
poll();
</script>
</body>
</html>