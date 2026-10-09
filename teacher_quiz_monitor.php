<?php
// teacher_quiz_monitor.php
session_start();
$conn = new mysqli("localhost", "root", "", "pinnaquest_db");

$session_id = isset($_GET['session_id']) ? intval($_GET['session_id']) : 0;
if (!$session_id) { die("Invalid session."); }

$sess_res = $conn->query("SELECT s.*, tm.title as material_title FROM synchro_sessions s LEFT JOIN teacher_materials tm ON s.material_id = tm.id WHERE s.id = $session_id");
if (!$sess_res || $sess_res->num_rows === 0) { die("Session not found."); }
$session = $sess_res->fetch_assoc();

$total_q_res = $conn->query("SELECT COUNT(*) as cnt FROM synchro_questions WHERE session_id = $session_id");
$total_questions = intval($total_q_res->fetch_assoc()['cnt']);

// Avatar map
$avatars = ['gamer_girl'=>'1a.JPG','blue_robot'=>'2a.JPG','gorilla_vr'=>'3a.JPG','grey_cat'=>'4a.JPG','monkey_cap'=>'5a.JPG','astronaut'=>'6a.JPG','bear_angry'=>'7a.JPG','bear_bee'=>'8a.JPG'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PinnaQuest | Live Control Panel</title>
<link href="https://fonts.googleapis.com/css2?family=Luckiest+Guy&family=Nunito:wght@400;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
    --purple: #6366f1;
    --purple-dark: #4338ca;
    --gold: #f59e0b;
    --green: #10b981;
    --red: #ef4444;
    --blue: #3b82f6;
    --bg: #0f0e17;
    --surface: #1a1a2e;
    --surface2: #16213e;
    --border: rgba(255,255,255,0.08);
    --text: #e2e8f0;
    --muted: #94a3b8;
}
* { margin:0; padding:0; box-sizing:border-box; }
body {
    font-family: 'Nunito', sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    overflow-x: hidden;
}

/* === HEADER === */
.monitor-header {
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    padding: 16px 30px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: sticky;
    top: 0;
    z-index: 100;
}
.header-left { display: flex; align-items: center; gap: 15px; }
.pq-badge {
    font-family: 'Luckiest Guy', cursive;
    font-size: 20px;
    color: var(--purple);
    letter-spacing: 1px;
}
.session-title { font-size: 16px; font-weight: 800; color: var(--text); }
.session-meta { font-size: 12px; color: var(--muted); }
.header-stats { display: flex; gap: 20px; align-items: center; }
.stat-chip {
    background: rgba(255,255,255,0.06);
    border: 1px solid var(--border);
    padding: 8px 16px;
    border-radius: 10px;
    text-align: center;
    min-width: 80px;
}
.stat-chip .val { font-size: 20px; font-weight: 900; color: var(--gold); }
.stat-chip .lbl { font-size: 10px; color: var(--muted); text-transform: uppercase; font-weight: 700; }

/* === MAIN LAYOUT === */
.monitor-body {
    display: grid;
    grid-template-columns: 1fr 320px;
    gap: 20px;
    padding: 20px 30px;
    max-width: 1400px;
    margin: 0 auto;
}

/* === CENTER STAGE === */
.center-stage {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 24px;
    overflow: hidden;
    min-height: 520px;
    display: flex;
    flex-direction: column;
}
.stage-topbar {
    background: var(--purple);
    padding: 12px 25px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.stage-topbar .phase-label {
    font-family: 'Luckiest Guy', cursive;
    font-size: 18px;
    letter-spacing: 1px;
    color: white;
}
.progress-dots { display: flex; gap: 6px; }
.progress-dot {
    width: 10px; height: 10px;
    border-radius: 50%;
    background: rgba(255,255,255,0.3);
    transition: 0.3s;
}
.progress-dot.done { background: var(--gold); }
.progress-dot.current { background: white; transform: scale(1.3); }

.stage-content {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 40px 50px;
    text-align: center;
}

/* Lobby state */
.lobby-state h2 {
    font-family: 'Luckiest Guy', cursive;
    font-size: 48px;
    color: var(--gold);
    text-shadow: 0 4px 20px rgba(245,158,11,0.4);
    margin-bottom: 10px;
    letter-spacing: 2px;
}
.room-code-display {
    font-family: 'Luckiest Guy', cursive;
    font-size: 64px;
    color: white;
    letter-spacing: 8px;
    text-shadow: 0 0 30px rgba(99,102,241,0.8);
    background: linear-gradient(135deg, var(--purple), #a855f7);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin: 15px 0;
}
.join-instruction {
    font-size: 15px;
    color: var(--muted);
    margin-bottom: 30px;
}

/* Question state */
.question-state { width: 100%; }
.q-counter {
    font-size: 12px;
    font-weight: 800;
    color: var(--purple);
    text-transform: uppercase;
    letter-spacing: 2px;
    margin-bottom: 15px;
}
.q-text {
    font-size: 26px;
    font-weight: 800;
    color: white;
    line-height: 1.4;
    margin-bottom: 30px;
    max-width: 700px;
    margin-left: auto;
    margin-right: auto;
}
.options-display {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    width: 100%;
    max-width: 700px;
    margin: 0 auto;
}
.opt-tile {
    padding: 18px 20px;
    border-radius: 14px;
    font-weight: 800;
    font-size: 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    color: white;
}
.opt-tile.a { background: #ef4444; }
.opt-tile.b { background: #3b82f6; }
.opt-tile.c { background: #f59e0b; }
.opt-tile.d { background: #10b981; }
.opt-shape { font-size: 20px; opacity: 0.8; }

/* Timer ring */
.timer-ring-container {
    position: relative;
    width: 80px;
    height: 80px;
    margin: 20px auto 10px;
}
.timer-ring { transform: rotate(-90deg); }
.timer-ring-bg { fill: none; stroke: rgba(255,255,255,0.1); stroke-width: 6; }
.timer-ring-progress { fill: none; stroke: var(--gold); stroke-width: 6; stroke-linecap: round; transition: stroke-dashoffset 1s linear; }
.timer-number {
    position: absolute; inset: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 24px; font-weight: 900; color: var(--gold);
}

/* Results state */
.results-state { width: 100%; }
.results-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
    margin: 20px 0;
    width: 100%;
    max-width: 700px;
    margin: 20px auto;
}
.result-bar-container { text-align: center; }
.result-bar-label { font-size: 11px; color: var(--muted); margin-bottom: 6px; font-weight: 700; }
.result-bar {
    height: 120px;
    background: rgba(255,255,255,0.05);
    border-radius: 8px;
    position: relative;
    overflow: hidden;
}
.result-bar-fill {
    position: absolute;
    bottom: 0; left: 0; right: 0;
    border-radius: 8px;
    transition: height 0.8s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.result-bar-fill.a { background: #ef4444; }
.result-bar-fill.b { background: #3b82f6; }
.result-bar-fill.c { background: #f59e0b; }
.result-bar-fill.d { background: #10b981; }
.result-bar-count { font-weight: 900; font-size: 18px; color: white; margin-top: 6px; }
.correct-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(16,185,129,0.15);
    border: 1px solid rgba(16,185,129,0.4);
    color: var(--green);
    padding: 8px 16px;
    border-radius: 10px;
    font-weight: 800;
    font-size: 14px;
    margin-top: 10px;
}

/* Identification answer display */
.id-answer-reveal {
    background: rgba(16,185,129,0.1);
    border: 2px solid var(--green);
    border-radius: 16px;
    padding: 25px 40px;
    margin: 20px auto;
    max-width: 500px;
}
.id-answer-reveal .label { font-size: 12px; color: var(--muted); font-weight: 700; letter-spacing: 1px; margin-bottom: 10px; }
.id-answer-reveal .answer-text { font-size: 36px; font-weight: 900; color: var(--green); }

/* Respond progress bar */
.respond-bar-container { margin: 15px 0; }
.respond-label { font-size: 13px; color: var(--muted); margin-bottom: 6px; font-weight: 700; }
.respond-track { height: 8px; background: rgba(255,255,255,0.1); border-radius: 10px; overflow: hidden; }
.respond-fill { height: 100%; background: var(--purple); border-radius: 10px; transition: width 0.5s; }

/* === CONTROL BUTTONS === */
.control-area {
    padding: 20px 25px;
    border-top: 1px solid var(--border);
    display: flex;
    gap: 12px;
    justify-content: center;
    flex-wrap: wrap;
}
.ctrl-btn {
    padding: 14px 28px;
    border-radius: 14px;
    border: none;
    font-family: 'Nunito', sans-serif;
    font-weight: 900;
    font-size: 15px;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 10px;
}
.ctrl-btn:hover { transform: translateY(-2px); }
.ctrl-btn:active { transform: translateY(1px); }
.btn-primary { background: var(--purple); color: white; box-shadow: 0 4px 0 var(--purple-dark); }
.btn-primary:active { box-shadow: 0 1px 0 var(--purple-dark); }
.btn-success { background: var(--green); color: white; box-shadow: 0 4px 0 #059669; }
.btn-gold { background: var(--gold); color: #1a1a2e; box-shadow: 0 4px 0 #d97706; }
.btn-danger { background: var(--red); color: white; box-shadow: 0 4px 0 #dc2626; }
.ctrl-btn:disabled { opacity: 0.4; cursor: not-allowed; transform: none; }

/* === SIDEBAR === */
.sidebar-panel { display: flex; flex-direction: column; gap: 16px; }

.panel-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 18px;
    overflow: hidden;
}
.panel-header {
    padding: 14px 20px;
    border-bottom: 1px solid var(--border);
    font-weight: 800;
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.panel-body { padding: 16px; }

/* Leaderboard */
.lb-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    border-radius: 12px;
    margin-bottom: 6px;
    transition: 0.2s;
}
.lb-row:hover { background: rgba(255,255,255,0.04); }
.lb-rank {
    width: 28px; height: 28px;
    border-radius: 8px;
    background: rgba(255,255,255,0.08);
    display: flex; align-items: center; justify-content: center;
    font-weight: 900; font-size: 12px; color: var(--muted);
    flex-shrink: 0;
}
.lb-rank.gold { background: var(--gold); color: #1a1a2e; }
.lb-rank.silver { background: #94a3b8; color: white; }
.lb-rank.bronze { background: #cd7f32; color: white; }
.lb-avatar {
    width: 34px; height: 34px;
    border-radius: 8px;
    object-fit: cover;
    background: #1a1a2e;
    flex-shrink: 0;
}
.lb-info { flex: 1; min-width: 0; }
.lb-name { font-weight: 800; font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.lb-score { font-size: 11px; color: var(--gold); font-weight: 700; }
.lb-delta { font-size: 11px; font-weight: 700; }
.lb-delta.up { color: var(--green); }
.lb-delta.same { color: var(--muted); }

/* Participants chips */
.participant-chips { display: flex; flex-wrap: wrap; gap: 8px; }
.p-chip {
    background: rgba(255,255,255,0.05);
    border: 1px solid var(--border);
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 6px;
}
.p-chip img { width: 18px; height: 18px; border-radius: 4px; object-fit: cover; }
.p-chip.answered { border-color: var(--green); color: var(--green); }

/* Full leaderboard overlay */
.lb-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(10,10,20,0.95);
    z-index: 200;
    align-items: center;
    justify-content: center;
    flex-direction: column;
}
.lb-overlay.visible { display: flex; }
.lb-full-card {
    background: var(--surface);
    border-radius: 30px;
    padding: 40px;
    width: 90%;
    max-width: 700px;
    border: 1px solid var(--border);
    max-height: 80vh;
    overflow-y: auto;
}
.podium-row {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 14px 20px;
    border-radius: 16px;
    margin-bottom: 10px;
    animation: slideInLeft 0.4s ease backwards;
}
@keyframes slideInLeft { from { opacity: 0; transform: translateX(-20px); } to { opacity: 1; transform: translateX(0); } }
.podium-row:nth-child(1) { background: rgba(245,158,11,0.15); border: 1px solid rgba(245,158,11,0.3); animation-delay: 0.1s; }
.podium-row:nth-child(2) { background: rgba(148,163,184,0.1); border: 1px solid rgba(148,163,184,0.2); animation-delay: 0.2s; }
.podium-row:nth-child(3) { background: rgba(205,127,50,0.1); border: 1px solid rgba(205,127,50,0.2); animation-delay: 0.3s; }
.podium-row:nth-child(n+4) { animation-delay: calc(0.3s + var(--i, 0) * 0.05s); }

.finish-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: linear-gradient(135deg, #0f0e17, #1a0a3d);
    z-index: 300;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    text-align: center;
}
.finish-overlay.visible { display: flex; }
.finish-title {
    font-family: 'Luckiest Guy', cursive;
    font-size: 60px;
    color: var(--gold);
    text-shadow: 0 0 40px rgba(245,158,11,0.6);
    animation: bounce 0.6s ease infinite alternate;
    letter-spacing: 3px;
}
@keyframes bounce { from { transform: translateY(0); } to { transform: translateY(-10px); } }

/* Pulse animation for waiting */
@keyframes pulseGlow {
    0% { box-shadow: 0 0 0 0 rgba(99,102,241,0.5); }
    70% { box-shadow: 0 0 0 20px rgba(99,102,241,0); }
    100% { box-shadow: 0 0 0 0 rgba(99,102,241,0); }
}
.pulse { animation: pulseGlow 2s infinite; }

/* Toast */
.toast {
    position: fixed;
    bottom: 30px; right: 30px;
    background: var(--surface);
    border: 1px solid var(--border);
    padding: 14px 20px;
    border-radius: 14px;
    font-weight: 700;
    z-index: 999;
    transform: translateY(100px);
    opacity: 0;
    transition: 0.3s;
}
.toast.show { transform: translateY(0); opacity: 1; }
.toast.success { border-color: var(--green); color: var(--green); }
.toast.error { border-color: var(--red); color: var(--red); }

/* --- CUSTOM CONFIRM MODAL (replaces browser confirm() "localhost says") --- */
.confirm-overlay {
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(0,0,0,0.6);
    backdrop-filter: blur(6px);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 10000;
}
.confirm-overlay.active { display: flex; }

.confirm-box {
    background: var(--surface);
    border: 1px solid var(--border);
    width: 90%;
    max-width: 400px;
    border-radius: 24px;
    padding: 34px 30px;
    text-align: center;
    box-shadow: 0 0 0 4px rgba(239,68,68,0.15), 0 25px 60px rgba(0,0,0,0.5);
    animation: confirmPop 0.28s cubic-bezier(.34,1.56,.64,1);
}
@keyframes confirmPop {
    from { transform: scale(0.85); opacity: 0; }
    to   { transform: scale(1);    opacity: 1; }
}

.confirm-icon {
    width: 64px; height: 64px;
    background: rgba(239,68,68,0.12);
    color: var(--red);
    border: 2px solid rgba(239,68,68,0.35);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 26px;
    margin: 0 auto 18px;
}

.confirm-box h3 {
    font-family: 'Luckiest Guy', cursive;
    font-size: 22px;
    letter-spacing: 1px;
    color: var(--gold);
    margin-bottom: 10px;
}

.confirm-box p {
    font-family: 'Nunito', sans-serif;
    font-size: 13.5px;
    font-weight: 700;
    color: var(--muted);
    line-height: 1.5;
    margin-bottom: 26px;
}

.confirm-actions { display: flex; gap: 12px; }
.confirm-actions button {
    flex: 1;
    padding: 13px;
    border-radius: 14px;
    font-family: 'Nunito', sans-serif;
    font-weight: 900;
    font-size: 14px;
    cursor: pointer;
    transition: 0.15s;
    border: none;
}
.confirm-btn-cancel { background: rgba(255,255,255,0.07); color: var(--muted); }
.confirm-btn-cancel:hover { background: rgba(255,255,255,0.13); color: var(--text); }

.confirm-btn-danger {
    background: var(--red);
    color: white;
    box-shadow: 0 4px 0 #b91c1c;
}
.confirm-btn-danger:hover { background: #dc2626; }
.confirm-btn-danger:active { transform: translateY(3px); box-shadow: 0 1px 0 #b91c1c; }
</style>
</head>
<body>

<!-- ====== HEADER ====== -->
<div class="monitor-header">
    <div class="header-left">
        <div class="pq-badge">⚡ PINNAQUEST</div>
        <div>
            <div class="session-title"><?php echo htmlspecialchars($session['title']); ?></div>
            <div class="session-meta"><?php echo htmlspecialchars($session['material_title'] ?? 'No Material'); ?> · <?php echo strtoupper($session['difficulty']); ?> · <?php echo $session['quiz_type'] === 'multiple_choice' ? 'MCQ' : 'Identification'; ?></div>
        </div>
    </div>
    <div class="header-stats">
        <div class="stat-chip">
            <div class="val" id="hdr-players">0</div>
            <div class="lbl">Players</div>
        </div>
        <div class="stat-chip">
            <div class="val" id="hdr-responded">0</div>
            <div class="lbl">Answered</div>
        </div>
        <div class="stat-chip">
            <div class="val"><?php echo $total_questions; ?></div>
            <div class="lbl">Questions</div>
        </div>
    </div>
</div>

<!-- ====== MAIN BODY ====== -->
<div class="monitor-body">
    <!-- CENTER STAGE -->
    <div class="center-stage">
        <div class="stage-topbar">
            <span class="phase-label" id="phase-label">LOBBY — WAITING FOR PLAYERS</span>
            <div class="progress-dots" id="progress-dots">
                <?php for($i=1; $i<=$total_questions; $i++): ?>
                    <div class="progress-dot" id="dot-<?php echo $i; ?>"></div>
                <?php endfor; ?>
            </div>
        </div>
        
        <div class="stage-content" id="stage-content">
            <!-- Lobby state (default) -->
            <div class="lobby-state" id="view-lobby">
                <div style="font-size:14px; color:var(--muted); font-weight:700; letter-spacing:2px; margin-bottom:10px;">ROOM CODE</div>
                <div class="room-code-display"><?php echo $session['room_code']; ?></div>
                <div class="join-instruction">
                    <i class="fa-solid fa-mobile-screen-button" style="color:var(--purple)"></i>
                    Students: Go to the <b>Synchro-Quiz Portal</b> and enter this code
                </div>
                <div style="display:flex; gap:10px; justify-content:center; align-items:center; margin-top:20px; flex-wrap:wrap;" id="lobby-avatars"></div>
                <div style="margin-top:25px; padding:15px 25px; background:rgba(99,102,241,0.1); border:1px solid rgba(99,102,241,0.3); border-radius:14px; font-size:14px; color:var(--muted);">
                    <b style="color:var(--text);"><?php echo $total_questions; ?> questions</b> ready · 
                    <b style="color:var(--text);"><?php echo strtoupper($session['difficulty']); ?></b> difficulty ·
                    <?php echo $session['timer_mins'] > 0 ? '<b style="color:var(--text);">'.$session['timer_mins'].'min</b> time limit' : 'No time limit'; ?>
                </div>
            </div>
            
            <!-- Question state -->
            <div class="question-state" id="view-question" style="display:none;">
                <div class="q-counter" id="q-counter">Question 1 of <?php echo $total_questions; ?></div>
                <div class="timer-ring-container">
                    <svg class="timer-ring" width="80" height="80" viewBox="0 0 80 80">
                        <circle class="timer-ring-bg" cx="40" cy="40" r="32"/>
                        <circle class="timer-ring-progress" id="timer-ring-prog" cx="40" cy="40" r="32" stroke-dasharray="201" stroke-dashoffset="0"/>
                    </svg>
                    <div class="timer-number" id="timer-display">20</div>
                </div>
                <div class="q-text" id="q-text">Loading question...</div>
                <div class="respond-bar-container">
                    <div class="respond-label"><span id="responded-count">0</span> / <span id="total-participants">0</span> answered</div>
                    <div class="respond-track"><div class="respond-fill" id="respond-fill" style="width:0%"></div></div>
                </div>
                <!-- MCQ options -->
                <div class="options-display" id="mcq-options">
                    <div class="opt-tile a"><span class="opt-shape">▲</span><span id="opt-a"></span></div>
                    <div class="opt-tile b"><span class="opt-shape">◆</span><span id="opt-b"></span></div>
                    <div class="opt-tile c"><span class="opt-shape">●</span><span id="opt-c"></span></div>
                    <div class="opt-tile d"><span class="opt-shape">■</span><span id="opt-d"></span></div>
                </div>
                <!-- Identification hint -->
                <div class="id-answer-reveal" id="id-question-display" style="display:none;">
                    <div class="label">STUDENTS: TYPE YOUR ANSWER</div>
                    <div style="font-size:15px; color:var(--muted); margin-top:10px;">Waiting for responses...</div>
                </div>
            </div>
            
            <!-- Results state -->
            <div class="results-state" id="view-results" style="display:none;">
                <div style="font-size:14px; font-weight:800; color:var(--muted); letter-spacing:2px; margin-bottom:20px;">ANSWER RESULTS</div>
                <!-- MCQ bar chart -->
                <div class="results-grid" id="mcq-results-grid">
                    <div class="result-bar-container">
                        <div class="result-bar-label">A</div>
                        <div class="result-bar"><div class="result-bar-fill a" id="bar-a" style="height:0%"></div></div>
                        <div class="result-bar-count" id="count-a">0</div>
                    </div>
                    <div class="result-bar-container">
                        <div class="result-bar-label">B</div>
                        <div class="result-bar"><div class="result-bar-fill b" id="bar-b" style="height:0%"></div></div>
                        <div class="result-bar-count" id="count-b">0</div>
                    </div>
                    <div class="result-bar-container">
                        <div class="result-bar-label">C</div>
                        <div class="result-bar"><div class="result-bar-fill c" id="bar-c" style="height:0%"></div></div>
                        <div class="result-bar-count" id="count-c">0</div>
                    </div>
                    <div class="result-bar-container">
                        <div class="result-bar-label">D</div>
                        <div class="result-bar"><div class="result-bar-fill d" id="bar-d" style="height:0%"></div></div>
                        <div class="result-bar-count" id="count-d">0</div>
                    </div>
                </div>
                <!-- Identification results -->
                <div id="id-results-display" style="display:none; margin: 20px auto; max-width:500px;">
                    <div style="font-size:13px; color:var(--muted); font-weight:700; margin-bottom:8px;">CORRECT ANSWER</div>
                    <div class="id-answer-reveal">
                        <div class="answer-text" id="id-correct-answer">...</div>
                    </div>
                    <div style="margin-top:15px; font-size:14px; color:var(--muted);">
                        <span id="id-correct-count" style="color:var(--green); font-weight:800; font-size:20px;">0</span> students got it right!
                    </div>
                </div>
                <div class="correct-badge" id="correct-badge">
                    <i class="fa-solid fa-check-circle"></i> Correct: <span id="correct-label"></span>
                </div>
            </div>
            
            <!-- Leaderboard teaser -->
            <div id="view-leaderboard" style="display:none; width:100%; text-align:center; padding:20px;">
                <div style="font-family:'Luckiest Guy',cursive; font-size:36px; color:var(--gold); margin-bottom:30px; letter-spacing:2px;">🏆 LEADERBOARD</div>
                <div id="lb-teaser-rows"></div>
            </div>
        </div>
        
        <!-- Control Buttons -->
        <div class="control-area">
            <button class="ctrl-btn btn-primary pulse" id="btn-start-q" onclick="startQuestion()">
                <i class="fa-solid fa-play"></i> <span id="btn-start-label">Launch First Question</span>
            </button>
            <button class="ctrl-btn btn-success" id="btn-show-results" onclick="showResults()" style="display:none;">
                <i class="fa-solid fa-chart-bar"></i> Show Results
            </button>
            <button class="ctrl-btn btn-gold" id="btn-show-lb" onclick="showLeaderboard()" style="display:none;">
                <i class="fa-solid fa-trophy"></i> Show Leaderboard
            </button>
            <button class="ctrl-btn btn-primary" id="btn-next-q" onclick="startQuestion()" style="display:none;">
                <i class="fa-solid fa-forward"></i> Next Question
            </button>
            <button class="ctrl-btn btn-danger" id="btn-finish" onclick="finishQuiz()" style="display:none;">
                <i class="fa-solid fa-flag-checkered"></i> End Quiz
            </button>
        </div>
    </div>
    
    <!-- SIDEBAR -->
    <div class="sidebar-panel">
        <!-- Mini Leaderboard -->
        <div class="panel-card">
            <div class="panel-header"><i class="fa-solid fa-trophy" style="color:var(--gold)"></i> Live Rankings</div>
            <div class="panel-body" id="mini-lb" style="max-height:280px; overflow-y:auto;">
                <div style="text-align:center; color:var(--muted); font-size:13px; padding:20px;">Waiting for players...</div>
            </div>
        </div>
        
        <!-- Participants -->
        <div class="panel-card">
            <div class="panel-header"><i class="fa-solid fa-users" style="color:var(--purple)"></i> Players <span id="player-count-badge" style="margin-left:auto; background:var(--purple); color:white; padding:2px 8px; border-radius:6px; font-size:11px;">0</span></div>
            <div class="panel-body">
                <div class="participant-chips" id="participant-chips"></div>
            </div>
        </div>
        
        <!-- Session Info -->
        <div class="panel-card">
            <div class="panel-header"><i class="fa-solid fa-info-circle"></i> Session Info</div>
            <div class="panel-body" style="font-size:13px; color:var(--muted); display:flex; flex-direction:column; gap:8px;">
                <div><b style="color:var(--text);">Room:</b> <?php echo $session['room_code']; ?></div>
                <div><b style="color:var(--text);">Material:</b> <?php echo htmlspecialchars($session['material_title'] ?? 'N/A'); ?></div>
                <div><b style="color:var(--text);">Difficulty:</b> <?php echo strtoupper($session['difficulty']); ?></div>
                <div><b style="color:var(--text);">Type:</b> <?php echo ucfirst(str_replace('_', ' ', $session['quiz_type'])); ?></div>
                <div><b style="color:var(--text);">Questions:</b> <?php echo $total_questions; ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Full Leaderboard Overlay -->
<div class="lb-overlay" id="lb-overlay">
    <div class="lb-full-card">
        <div style="text-align:center; margin-bottom:30px;">
            <div style="font-family:'Luckiest Guy',cursive; font-size:42px; color:var(--gold); letter-spacing:2px; text-shadow:0 0 30px rgba(245,158,11,0.5);">HALL OF FAME</div>
            <div style="color:var(--muted); font-size:14px; margin-top:5px;">Current Rankings</div>
        </div>
        <div id="full-lb-rows"></div>
        <div style="text-align:center; margin-top:20px;">
            <button class="ctrl-btn btn-primary" onclick="document.getElementById('lb-overlay').classList.remove('visible')">
                <i class="fa-solid fa-times"></i> Close
            </button>
        </div>
    </div>
</div>

<!-- Finish Overlay -->
<div class="finish-overlay" id="finish-overlay">
    <div style="font-size:60px; margin-bottom:20px; animation: bounce 0.5s infinite alternate;">🎉</div>
    <div class="finish-title">QUEST COMPLETE!</div>
    <div style="color:var(--muted); font-size:18px; margin:20px 0;">Final standings are in!</div>
    <div id="final-lb-rows" style="width:100%; max-width:600px; margin:20px auto;"></div>
    <div style="display:flex; gap:15px; margin-top:30px; flex-wrap:wrap; justify-content:center;">
        <button class="ctrl-btn btn-gold" onclick="window.location.href='synchro_manage.php'">
            <i class="fa-solid fa-home"></i> Back to Dashboard
        </button>
    </div>
</div>

<!-- Custom Confirm Modal (replaces browser confirm()) -->
<div class="confirm-overlay" id="confirmOverlay">
    <div class="confirm-box">
        <div class="confirm-icon"><i class="fa-solid fa-flag-checkered"></i></div>
        <h3>End the Quiz?</h3>
        <p id="confirmMessage">End the quiz for all students?</p>
        <div class="confirm-actions">
            <button class="confirm-btn-cancel" id="confirmCancelBtn">Cancel</button>
            <button class="confirm-btn-danger" id="confirmOkBtn">Yes, End Quiz</button>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
const SESSION_ID = <?php echo $session_id; ?>;
const TOTAL_QUESTIONS = <?php echo $total_questions; ?>;
const AVATARS = <?php echo json_encode($avatars); ?>;

let currentPhase = 'lobby';
let currentQIdx = 0;
let timerInterval = null;
let pollInterval = null;
let timeLeft = 0;

// === POLLING ===
function poll() {
    fetch(`get_quiz_state.php?session_id=${SESSION_ID}`)
        .then(r => r.json())
        .then(data => {
            updateUI(data);
        })
        .catch(err => console.error("Poll error:", err));
}

function updateUI(data) {
    const prevPhase = currentPhase;
    currentPhase = data.phase || 'lobby';
    currentQIdx = data.current_question_index || 0;
    
    // Update header stats
    document.getElementById('hdr-players').innerText = data.participant_count || 0;
    document.getElementById('hdr-responded').innerText = data.responded_count || 0;
    
    // Update leaderboard
    if (data.leaderboard) {
        updateMiniLeaderboard(data.leaderboard);
    }
    
    // Update participants (only in lobby/question)
    updateParticipantChips(data);
    
    // Phase-specific UI
    switch(currentPhase) {
        case 'lobby':
            showView('lobby');
            setPhaseLabel('LOBBY — WAITING FOR PLAYERS');
            showButtons(['start-q']);
            document.getElementById('btn-start-label').innerText = 'Launch First Question';
            if (data.participant_count > 0) {
                document.getElementById('btn-start-q').classList.add('pulse');
            }
            break;
            
        case 'question':
            if (data.question) {
                showView('question');
                updateQuestionView(data);
                setPhaseLabel(`QUESTION ${currentQIdx} OF ${TOTAL_QUESTIONS}`);
                updateProgressDots(currentQIdx);
                showButtons(['show-results']);
                
                // Update respond bar
                const pCount = data.participant_count || 1;
                const rCount = data.responded_count || 0;
                document.getElementById('responded-count').innerText = rCount;
                document.getElementById('total-participants').innerText = pCount;
                document.getElementById('respond-fill').style.width = ((rCount/pCount)*100) + '%';
            }
            break;
            
        case 'results':
            if (prevPhase !== 'results' && data.question) {
                showView('results');
                updateResultsView(data);
                setPhaseLabel(`RESULTS — Q${currentQIdx}`);
                
                const isLast = currentQIdx >= TOTAL_QUESTIONS;
                if (isLast) {
                    showButtons(['show-lb', 'finish']);
                } else {
                    showButtons(['show-lb', 'next-q']);
                }
            }
            break;
            
        case 'leaderboard':
            showView('leaderboard');
            setPhaseLabel('LEADERBOARD');
            updateLbTeaser(data.leaderboard || []);
            
            const isLastQ = currentQIdx >= TOTAL_QUESTIONS;
            showButtons(isLastQ ? ['finish'] : ['next-q']);
            
            // Show full overlay
            showFullLeaderboard(data.leaderboard || []);
            break;
            
        case 'finished':
            showFinishScreen(data.leaderboard || []);
            break;
    }
}

function showView(viewName) {
    ['lobby','question','results','leaderboard'].forEach(v => {
        const el = document.getElementById(`view-${v}`);
        if (el) el.style.display = v === viewName ? 'block' : 'none';
    });
    if (viewName === 'lobby') {
        document.getElementById('view-lobby').style.display = 'flex';
        document.getElementById('view-lobby').style.flexDirection = 'column';
    }
}

function showButtons(active) {
    const all = ['start-q', 'show-results', 'show-lb', 'next-q', 'finish'];
    all.forEach(id => {
        const btn = document.getElementById(`btn-${id}`);
        if (btn) btn.style.display = active.includes(id) ? 'flex' : 'none';
    });
}

function setPhaseLabel(text) {
    document.getElementById('phase-label').innerText = text;
}

function updateProgressDots(current) {
    for (let i = 1; i <= TOTAL_QUESTIONS; i++) {
        const dot = document.getElementById(`dot-${i}`);
        if (!dot) continue;
        dot.className = 'progress-dot';
        if (i < current) dot.classList.add('done');
        else if (i === current) dot.classList.add('current');
    }
}

function updateQuestionView(data) {
    const q = data.question;
    document.getElementById('q-counter').innerText = `Question ${currentQIdx} of ${TOTAL_QUESTIONS}`;
    document.getElementById('q-text').innerText = q.text;
    
    if (q.type === 'multiple_choice') {
        document.getElementById('mcq-options').style.display = 'grid';
        document.getElementById('id-question-display').style.display = 'none';
        document.getElementById('opt-a').innerText = q.options.A || '';
        document.getElementById('opt-b').innerText = q.options.B || '';
        document.getElementById('opt-c').innerText = q.options.C || '';
        document.getElementById('opt-d').innerText = q.options.D || '';
    } else {
        document.getElementById('mcq-options').style.display = 'none';
        document.getElementById('id-question-display').style.display = 'block';
    }
    
    // Update timer display
    const tl = data.time_left || 0;
    document.getElementById('timer-display').innerText = Math.ceil(tl);
    const maxTime = q.time_limit || 20;
    const pct = tl / maxTime;
    const circ = 201;
    document.getElementById('timer-ring-prog').style.strokeDashoffset = circ * (1 - pct);
    document.getElementById('timer-ring-prog').style.stroke = pct > 0.33 ? '#f59e0b' : '#ef4444';
}

function updateResultsView(data) {
    const q = data.question;
    const dist = data.answer_distribution || {};
    
    if (q.type === 'multiple_choice') {
        document.getElementById('mcq-results-grid').style.display = 'grid';
        document.getElementById('id-results-display').style.display = 'none';
        
        const letters = ['A','B','C','D'];
        const maxCount = Math.max(...letters.map(l => dist[l] || 0), 1);
        
        letters.forEach(l => {
            const count = dist[l] || 0;
            const pct = (count / maxCount) * 100;
            const barEl = document.getElementById(`bar-${l.toLowerCase()}`);
            const countEl = document.getElementById(`count-${l.toLowerCase()}`);
            if (barEl) barEl.style.height = pct + '%';
            if (countEl) countEl.innerText = count;
        });
        
        const correct = q.correct_answer;
        document.getElementById('correct-badge').style.display = 'inline-flex';
        document.getElementById('correct-label').innerText = correct + ': ' + (q.options[correct] || '');
        document.getElementById('id-results-display').style.display = 'none';
    } else {
        document.getElementById('mcq-results-grid').style.display = 'none';
        document.getElementById('correct-badge').style.display = 'none';
        document.getElementById('id-results-display').style.display = 'block';
        document.getElementById('id-correct-answer').innerText = q.correct_answer;
        
        const correctCount = Number(data.correct_count) || 0;
        document.getElementById('id-correct-count').innerText = correctCount;
    }
}

function updateMiniLeaderboard(lb) {
    const container = document.getElementById('mini-lb');
    if (!lb || lb.length === 0) {
        container.innerHTML = '<div style="text-align:center; color:var(--muted); font-size:13px; padding:15px;">No scores yet</div>';
        return;
    }
    const ranks = ['gold','silver','bronze'];
    container.innerHTML = lb.slice(0, 8).map((p, i) => `
        <div class="lb-row">
            <div class="lb-rank ${ranks[i] || ''}">${i+1}</div>
            <img class="lb-avatar" src="${AVATARS[p.avatar_key] || '1a.JPG'}" onerror="this.style.display='none'">
            <div class="lb-info">
                <div class="lb-name">${p.nickname}</div>
                <div class="lb-score">${Number(p.total_score).toLocaleString()} pts</div>
            </div>
            <div class="lb-delta up">${p.streak > 1 ? '🔥'+p.streak : ''}</div>
        </div>
    `).join('');
}

function updateLbTeaser(lb) {
    const container = document.getElementById('lb-teaser-rows');
    const ranks = ['🥇','🥈','🥉'];
    container.innerHTML = lb.slice(0,3).map((p,i) => `
        <div class="podium-row" style="--i:${i}">
            <span style="font-size:28px;">${ranks[i]}</span>
            <img style="width:45px;height:45px;border-radius:10px;object-fit:cover;" src="${AVATARS[p.avatar_key]||'1a.JPG'}" onerror="this.src=''">
            <div style="text-align:left; flex:1;">
                <div style="font-weight:900; font-size:18px;">${p.nickname}</div>
                <div style="font-size:13px; color:var(--gold); font-weight:700;">${Number(p.total_score).toLocaleString()} pts</div>
            </div>
        </div>
    `).join('');
}

function showFullLeaderboard(lb) {
    const overlay = document.getElementById('lb-overlay');
    overlay.classList.add('visible');
    const container = document.getElementById('full-lb-rows');
    const ranks = ['🥇','🥈','🥉'];
    container.innerHTML = lb.map((p,i) => `
        <div class="podium-row" style="--i:${i}">
            <span style="font-size:22px; min-width:35px;">${ranks[i] || '#'+(i+1)}</span>
            <img style="width:40px;height:40px;border-radius:10px;object-fit:cover;" src="${AVATARS[p.avatar_key]||'1a.JPG'}" onerror="this.style.opacity='0'">
            <div style="flex:1; text-align:left;">
                <div style="font-weight:900; font-size:16px;">${p.nickname}</div>
                <div style="font-size:12px; color:var(--muted);">${p.correct_answers} correct · ${p.streak > 0 ? '🔥 ' + p.streak + ' streak' : ''}</div>
            </div>
            <div style="font-weight:900; font-size:20px; color:var(--gold);">${Number(p.total_score).toLocaleString()}</div>
        </div>
    `).join('');
}

function showFinishScreen(lb) {
    document.getElementById('finish-overlay').classList.add('visible');
    const container = document.getElementById('final-lb-rows');
    const ranks = ['🥇','🥈','🥉'];
    container.innerHTML = lb.map((p,i) => `
        <div style="display:flex;align-items:center;gap:12px;padding:14px 20px;background:rgba(255,255,255,0.05);border-radius:14px;margin-bottom:8px;">
            <span style="font-size:24px;">${ranks[i]||'#'+(i+1)}</span>
            <img style="width:40px;height:40px;border-radius:10px;object-fit:cover;" src="${AVATARS[p.avatar_key]||''}" onerror="this.style.display='none'">
            <div style="flex:1; text-align:left;"><div style="font-weight:900;">${p.nickname}</div></div>
            <div style="font-weight:900;color:var(--gold);font-size:20px;">${Number(p.total_score).toLocaleString()}</div>
        </div>
    `).join('');
    
    clearInterval(pollInterval);
}

function updateParticipantChips(data) {
    // Get participants from leaderboard (they have nicknames and avatars)
    const lb = data.leaderboard || [];
    const pCount = data.participant_count || 0;
    
    document.getElementById('player-count-badge').innerText = pCount;
    document.getElementById('hdr-players').innerText = pCount;
    
    const chipsContainer = document.getElementById('participant-chips');
    if (lb.length === 0) {
        chipsContainer.innerHTML = '<div style="font-size:12px; color:var(--muted);">No players yet</div>';
        return;
    }
    chipsContainer.innerHTML = lb.map(p => `
        <div class="p-chip">
            <img src="${AVATARS[p.avatar_key]||''}" style="width:18px;height:18px;border-radius:4px;" onerror="this.style.display='none'">
            ${p.nickname}
        </div>
    `).join('');
    
    // Update lobby avatars
    const lobbyAvatars = document.getElementById('lobby-avatars');
    if (lobbyAvatars) {
        lobbyAvatars.innerHTML = lb.map(p => `
            <div style="text-align:center;">
                <img src="${AVATARS[p.avatar_key]||''}" style="width:55px;height:55px;border-radius:12px;border:2px solid rgba(99,102,241,0.5);" onerror="this.style.display='none'">
                <div style="font-size:10px; font-weight:700; margin-top:4px; color:var(--muted);">${p.nickname}</div>
            </div>
        `).join('');
    }
}

// --- Custom confirm modal helper (replaces browser confirm()) ---
function showConfirm(message) {
    return new Promise((resolve) => {
        const overlay   = document.getElementById('confirmOverlay');
        const msgEl     = document.getElementById('confirmMessage');
        const okBtn     = document.getElementById('confirmOkBtn');
        const cancelBtn = document.getElementById('confirmCancelBtn');

        msgEl.textContent = message;
        overlay.classList.add('active');

        function cleanup(result) {
            overlay.classList.remove('active');
            okBtn.removeEventListener('click', onOk);
            cancelBtn.removeEventListener('click', onCancel);
            resolve(result);
        }
        function onOk() { cleanup(true); }
        function onCancel() { cleanup(false); }

        okBtn.addEventListener('click', onOk);
        cancelBtn.addEventListener('click', onCancel);
    });
}

// === TEACHER CONTROLS ===
function startQuestion() {
    fetch('teacher_control.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=start_question&session_id=${SESSION_ID}`
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) {
            showToast(data.error || 'Could not launch the next question.', 'error');
        } else if (data.phase === 'finished') {
            showToast('Quiz ended!', 'success');
        } else {
            const questionIndex = data.question_index ?? data.current_question_index;
            showToast(
                questionIndex
                    ? `Question ${questionIndex} launched! ⚡`
                    : 'Next question launched! ⚡',
                'success'
            );
        }
    })
    .catch(() => showToast('Connection error. Please try again.', 'error'));
}

function showResults() {
    fetch('teacher_control.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=show_results&session_id=${SESSION_ID}`
    })
    .then(r => r.json())
    .then(() => showToast('Showing results! 📊', 'success'));
}

function showLeaderboard() {
    fetch('teacher_control.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=show_leaderboard&session_id=${SESSION_ID}`
    })
    .then(r => r.json())
    .then(() => showToast('Leaderboard time! 🏆', 'success'));
}

async function finishQuiz() {
    const confirmed = await showConfirm('End the quiz for all students?');
    if (!confirmed) return;

    fetch('teacher_control.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=finish_quiz&session_id=${SESSION_ID}`
    })
    .then(r => r.json())
    .then(() => showToast('Quest finished! 🎉', 'success'));
}

function showToast(msg, type = '') {
    const t = document.getElementById('toast');
    t.innerText = msg;
    t.className = `toast ${type} show`;
    setTimeout(() => t.classList.remove('show'), 3000);
}

// Start polling
pollInterval = setInterval(poll, 1500);
poll();
</script>

</body>
</html>