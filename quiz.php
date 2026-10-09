<?php
session_start();

$selectedMinutes   = isset($_SESSION['quiz_time_limit']) ? intval($_SESSION['quiz_time_limit']) : 30;
$questions         = isset($_SESSION['quiz_data']['questions']) ? $_SESSION['quiz_data']['questions'] : [];

// Shuffle once when this solo attempt starts. The shuffled array stays in the
// session so refreshes continue using the same question order.
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['quiz_id'])
    && empty($_SESSION['solo_quiz_order_initialized'])) {
    shuffle($questions);
    $_SESSION['quiz_data']['questions'] = $questions;
    $_SESSION['solo_quiz_order_initialized'] = true;
    $_SESSION['solo_attempt_token'] = bin2hex(random_bytes(16));
    unset($_SESSION['solo_result_saved'], $_SESSION['solo_result_response']);
}

if (empty($questions)) {
    header("Location: quizzes.php?error=no_questions_found");
    exit();
}

$isNoTimer         = ($selectedMinutes <= 0);
$globalTimeSeconds = $isNoTimer ? 59940 : ($selectedMinutes * 60);
$questionTimeLimit = 30;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quest in Progress | PinnaQuest</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&family=Lexend:wght@700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary:     #1db968;
            --accent-gold: #fbbf24;
            --bg-light:    #f4f6fb;
            --white:       #ffffff;
            --danger:      #ef4444;
            --blue:        #3b82f6;
            --purple:      #6366f1;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-light);
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
        }

        /* ── PROGRESS BAR ──────────────────────────────────────── */
        .timer-bar-wrap { width:100%; height:12px; background:#e2e8f0; flex-shrink:0; }
        .timer-bar {
            height:100%;
            background: var(--primary);
            width:100%;
            transition: width 1s linear, background-color 0.5s;
        }

        /* ── HEADER ────────────────────────────────────────────── */
        .quiz-header {
            padding: 12px 30px;
            background: var(--white);
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
        }

        /* ── QUESTION AREA ─────────────────────────────────────── */
        .question-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 16px 20px 10px;
            overflow: hidden;
        }

        .question-card {
            background: white;
            padding: 30px 48px;
            border-radius: 24px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.06);
            max-width: 860px;
            width: 100%;
            min-height: 130px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
            margin-bottom: 18px;
            border: 1px solid rgba(0,0,0,0.05);
        }

        .question-text {
            font-family: 'Lexend', sans-serif;
            font-size: 1.65rem;
            font-weight: 800;
            color: #1e293b;
            line-height: 1.6;
            text-align: center;
        }

        /* ══════════════════════════════════════════════════════════
           RPG ARCANE QUEST CARD — MCQ CHOICES
           ══════════════════════════════════════════════════════════
           Design: dark-bordered card, colored left-accent stripe,
           glowing letter badge, lift-on-hover. No Kahoot colors.   */
        .answers-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
            width: 100%;
            max-width: 940px;
            padding: 0 8px;
        }

        /* Per-option accent palette */
        .answer-btn.btn-0 { --c:#7c3aed; --cl:rgba(124,58,237,.10); --cs:rgba(124,58,237,.22); }
        .answer-btn.btn-1 { --c:#0891b2; --cl:rgba(8,145,178,.10);  --cs:rgba(8,145,178,.22);  }
        .answer-btn.btn-2 { --c:#d97706; --cl:rgba(217,119,6,.10);  --cs:rgba(217,119,6,.22);  }
        .answer-btn.btn-3 { --c:#0f766e; --cl:rgba(15,118,110,.10); --cs:rgba(15,118,110,.22); }

        .answer-btn {
            position: relative;
            display: flex;
            align-items: stretch;
            border: 2px solid rgba(0,0,0,0.07);
            border-radius: 18px;
            cursor: pointer;
            min-height: 78px;
            background: var(--white);
            overflow: hidden;
            padding: 0;
            text-align: left;
            transition:
                transform 0.22s cubic-bezier(.34,1.56,.64,1),
                box-shadow 0.22s,
                border-color 0.2s,
                background 0.2s;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        }

        /* Left accent stripe */
        .answer-btn::before {
            content: '';
            position: absolute;
            left: 0; top: 0; bottom: 0;
            width: 6px;
            background: var(--c);
            border-radius: 18px 0 0 18px;
        }

        /* Subtle inner shine at top */
        .answer-btn::after {
            content: '';
            position: absolute;
            top: 0; left: 6px; right: 0;
            height: 1px;
            background: linear-gradient(90deg, var(--cs) 0%, transparent 60%);
        }

        .answer-btn:hover:not(:disabled) {
            transform: translateY(-6px) scale(1.025);
            border-color: var(--c);
            background: var(--cl);
            box-shadow:
                0 14px 32px var(--cs),
                0 4px 8px rgba(0,0,0,0.06),
                inset 0 0 0 1px var(--cs);
        }

        .answer-btn:active:not(:disabled) {
            transform: translateY(1px) scale(0.99);
            box-shadow: 0 2px 6px var(--cs);
        }

        .answer-btn:disabled { cursor: default; }

        /* ── Letter badge section ── */
        .ans-badge-wrap {
            width: 70px;
            min-height: 78px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            padding-left: 8px; /* offset past the stripe */
            background: var(--cl);
            border-right: 1.5px solid var(--cs);
        }

        .ans-badge {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: var(--c);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Lexend', sans-serif;
            font-size: 1.15rem;
            font-weight: 800;
            color: white;
            letter-spacing: 0.5px;
            box-shadow: 0 3px 10px var(--cs);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .answer-btn:hover:not(:disabled) .ans-badge {
            transform: scale(1.1) rotate(-4deg);
            box-shadow: 0 6px 18px var(--cs);
        }

        /* ── Answer text ── */
        .ans-text-area {
            flex: 1;
            padding: 16px 22px 16px 16px;
            display: flex;
            align-items: center;
            font-size: 1.1rem;
            font-weight: 700;
            color: #1e293b;
            line-height: 1.4;
        }

        /* ── Result states ── */
        .answer-btn.correct-flash {
            border-color: var(--primary) !important;
            background: #f0fdf4 !important;
            box-shadow: 0 0 0 3px rgba(29,185,104,.25), 0 10px 24px rgba(29,185,104,.15) !important;
            animation: correctBounce 0.45s ease;
        }
        .answer-btn.wrong-fade {
            opacity: 0.28;
            filter: grayscale(0.6);
            transform: none !important;
        }
        @keyframes correctBounce {
            0%   { transform: scale(1); }
            40%  { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        /* ── FILL-BLANK INPUT ──────────────────────────────────── */
        .blank-input {
            display: inline-block;
            border: none;
            border-bottom: 4px solid var(--blue);
            background: #f0f7ff;
            border-radius: 8px 8px 0 0;
            padding: 6px 16px;
            font-size: 1.55rem;
            font-weight: 800;
            font-family: 'Inter', sans-serif;
            color: #1e293b;
            text-align: center;
            width: 220px;
            outline: none;
            margin: 0 8px;
            transition: border-color 0.2s, background 0.2s;
            vertical-align: middle;
        }
        .blank-input:focus   { border-bottom-color: var(--primary); background: #f0fff6; }
        .blank-input.correct { border-bottom-color: var(--primary); background: #dcfce7; color: #15803d; }
        .blank-input.wrong   { border-bottom-color: var(--danger);  background: #fee2e2; color: #b91c1c;
                               animation: shake 0.35s ease; }
        @keyframes shake {
            0%,100%{ transform:translateX(0); }
            20%    { transform:translateX(-6px); }
            60%    { transform:translateX(6px); }
        }

        /* ── HINT DISPLAY ──────────────────────────────────────── */
        #hintDisplay { display:none; flex-wrap:wrap; justify-content:center; gap:5px; margin-bottom:12px; max-width:860px; width:100%; }
        .lb { display:inline-flex; align-items:center; justify-content:center; width:36px; height:42px; border:2px solid #cbd5e1; border-radius:8px; font-size:1rem; font-weight:800; color:#94a3b8; background:#f8fafc; transition:all .35s; }
        .lb.lbr { border-color:var(--primary); background:#f0fff4; color:#15803d; }
        .lb.lbv { border-color:var(--blue);    background:#eff6ff; color:#1d4ed8; }
        .ls     { display:inline-block; width:14px; }

        /* ── DOUBLE-DIP BANNER ─────────────────────────────────── */
        #dipBanner { display:none; align-items:center; gap:8px; background:#fef3c7; border:1.5px solid #fbbf24; border-radius:10px; padding:6px 18px; font-size:.83rem; font-weight:700; color:#92400e; margin-bottom:10px; }

        /* ── SUBMIT CONTROLS ───────────────────────────────────── */
        .blank-submit-area { display:none; flex-direction:column; align-items:center; gap:8px; width:100%; max-width:500px; margin-bottom:6px; }
        .blank-hint-txt { font-size:.82rem; color:#94a3b8; font-weight:600; }
        .btn-submit-blank {
            background: var(--blue);
            color: white;
            border: none;
            padding: 14px 50px;
            border-radius: 14px;
            font-size: 1.05rem;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 5px 0 #1e40af;
            transition: transform 0.1s, box-shadow 0.1s;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .btn-submit-blank:hover  { background: #2563eb; }
        .btn-submit-blank:active { transform: translateY(4px); box-shadow: 0 1px 0 #1e40af; }
        .btn-submit-blank:disabled { opacity:.5; cursor:not-allowed; transform:none; }

        /* ── FILL-BLANK POWER-UP BAR ───────────────────────────── */
        #fillBlankPowerups {
            display: none;
            width: 100%;
            background: white;
            border-top: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
            padding: 8px 14px;
            flex-shrink: 0;
            overflow-x: auto;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .pu-btn {
            display:flex; flex-direction:column; align-items:center; gap:3px;
            border:2px solid #e2e8f0; border-radius:12px; background:#f8fafc;
            padding:7px 9px; cursor:pointer; transition:border-color .2s, background .2s;
            min-width:68px; flex-shrink:0; position:relative;
        }
        .pu-btn:hover:not(.pu-used) { border-color:var(--blue); background:#eff6ff; }
        .pu-btn.pu-active           { border-color:var(--accent-gold); background:#fffbeb; }
        .pu-btn.pu-used             { opacity:.32; cursor:not-allowed; filter:grayscale(.6); }
        .pu-icon  { font-size:1.25rem; line-height:1; }
        .pu-label { font-size:.62rem; font-weight:700; color:#475569; text-align:center; white-space:nowrap; }
        .pu-badge { position:absolute; top:-6px; right:-6px; background:var(--danger); color:white; font-size:.52rem; font-weight:800; padding:2px 5px; border-radius:20px; }
        .pu-divider     { width:1px; height:44px; background:#e2e8f0; flex-shrink:0; }
        .pu-group-label { font-size:.58rem; font-weight:800; color:#94a3b8; text-transform:uppercase; letter-spacing:.5px; writing-mode:vertical-rl; transform:rotate(180deg); flex-shrink:0; padding:0 2px; }

        /* ── SCORE FOOTER ──────────────────────────────────────── */
        .score-footer { padding:13px 40px; background:var(--white); display:flex; justify-content:space-between; font-weight:800; border-top:1px solid #e2e8f0; flex-shrink:0; }

        /* ── RESULT OVERLAY ────────────────────────────────────── */
        #resultOverlay { display:none; position:fixed; inset:0; background:var(--primary); color:white; z-index:200; flex-direction:column; align-items:center; justify-content:center; text-align:center; padding:20px; }
        @keyframes xpPop { 0%{transform:scale(.5);opacity:0} 70%{transform:scale(1.1)} 100%{transform:scale(1);opacity:1} }
    </style>
</head>
<body>

<div class="timer-bar-wrap"><div class="timer-bar" id="timerBar"></div></div>

<div class="quiz-header">
    <div style="color:var(--primary);font-weight:800;letter-spacing:1px;font-family:'Lexend',sans-serif;">PINNAQUEST</div>
    <span id="questionNumber" style="font-weight:600;">Question 1 of <?php echo count($questions); ?></span>
    <div id="timerText" style="font-weight:800;font-size:1.2rem;">--:--</div>
</div>

<div class="question-container">

    <div class="question-card">
        <div class="question-text" id="qText">Loading your quest...</div>
    </div>

    <!-- Hint letter tiles -->
    <div id="hintDisplay"></div>

    <!-- Double-dip banner -->
    <div id="dipBanner">
        <i class="fa-solid fa-crosshairs"></i>
        Double-Dip active — <span class="dip-count">2 attempts</span> available
    </div>

    <!-- Fill-blank submit -->
    <div class="blank-submit-area" id="blankSubmitArea">
        <span class="blank-hint-txt"><i class="fa-solid fa-keyboard"></i> Type your answer above, then press Submit or Enter</span>
        <button class="btn-submit-blank" id="blankSubmitBtn" onclick="submitFillBlank()">
            <i class="fa-solid fa-paper-plane"></i> Submit Answer
        </button>
    </div>

    <!-- ═══ RPG ARCANE QUEST CARDS — MCQ ═════════════════════════════════ -->
    <div class="answers-grid" id="mcqGrid">

        <button class="answer-btn btn-0" id="mcq-btn-0" onclick="checkMCQ(0)">
            <div class="ans-badge-wrap"><div class="ans-badge">A</div></div>
            <span class="ans-text-area" id="opt0">Option A</span>
        </button>

        <button class="answer-btn btn-1" id="mcq-btn-1" onclick="checkMCQ(1)">
            <div class="ans-badge-wrap"><div class="ans-badge">B</div></div>
            <span class="ans-text-area" id="opt1">Option B</span>
        </button>

        <button class="answer-btn btn-2" id="mcq-btn-2" onclick="checkMCQ(2)">
            <div class="ans-badge-wrap"><div class="ans-badge">C</div></div>
            <span class="ans-text-area" id="opt2">Option C</span>
        </button>

        <button class="answer-btn btn-3" id="mcq-btn-3" onclick="checkMCQ(3)">
            <div class="ans-badge-wrap"><div class="ans-badge">D</div></div>
            <span class="ans-text-area" id="opt3">Option D</span>
        </button>

    </div>

</div>

<!-- Fill-blank power-up bar -->
<div id="fillBlankPowerups">
    <span class="pu-group-label">Hints</span>
    <button class="pu-btn" id="pu-vowel"   onclick="activateVowelReveal()"  title="Reveal all vowels"><span class="pu-icon">🔤</span><span class="pu-label">Vowel Reveal</span><span class="pu-badge">1×</span></button>
    <button class="pu-btn" id="pu-first"   onclick="activateFirstLetter()" title="Reveal first letter"><span class="pu-icon">🔡</span><span class="pu-label">First Letter</span><span class="pu-badge">1×</span></button>
    <button class="pu-btn" id="pu-length"  onclick="activateWordLength()"  title="Show word length"><span class="pu-icon">📏</span><span class="pu-label">Word Length</span><span class="pu-badge">1×</span></button>
    <button class="pu-btn" id="pu-context" onclick="activateContextHint()" title="Context clue"><span class="pu-icon">💡</span><span class="pu-label">Context Hint</span><span class="pu-badge">1×</span></button>
    <div class="pu-divider"></div>
    <span class="pu-group-label">Timer</span>
    <button class="pu-btn" id="pu-freeze" onclick="activateTimeFreeze()" title="Freeze timer 15s"><span class="pu-icon">❄️</span><span class="pu-label" id="pu-freeze-label">Time Freeze</span><span class="pu-badge">1×</span></button>
    <button class="pu-btn" id="pu-double" onclick="activateDoublePoints()" title="2× points"><span class="pu-icon">⚡</span><span class="pu-label">2× Points</span><span class="pu-badge">1×</span></button>
    <div class="pu-divider"></div>
    <span class="pu-group-label">Lifeline</span>
    <button class="pu-btn" id="pu-dip"  onclick="activateDoubleDip()" title="Two attempts"><span class="pu-icon">🎯</span><span class="pu-label">Double-Dip</span><span class="pu-badge">1×</span></button>
    <button class="pu-btn" id="pu-skip" onclick="activateSkip()"       title="Skip question"><span class="pu-icon">⏭️</span><span class="pu-label">Skip</span><span class="pu-badge">1×</span></button>
</div>

<div class="score-footer">
    <div><i class="fa-solid fa-user"></i> <?php echo htmlspecialchars($_SESSION['username'] ?? 'Student'); ?></div>
    <div id="scoreDisplay" style="color:var(--primary);">Score: 0</div>
</div>

<!-- Result overlay -->
<div id="resultOverlay">
    <i class="fa-solid fa-trophy" style="font-size:5rem;color:var(--accent-gold);margin-bottom:20px;"></i>
    <h1 style="font-size:3rem;font-family:'Lexend',sans-serif;">QUEST COMPLETED!</h1>
    <p id="correctCount" style="font-size:1.2rem;opacity:.85;margin-top:10px;"></p>
    <p style="font-size:1.5rem;margin-top:10px;">Your final score is:</p>
    <div style="font-size:5rem;font-weight:800;margin:20px 0;" id="finalScore">0</div>
    <div id="xpEarnedBadge" style="display:none;background:rgba(255,255,255,.2);border:2px solid rgba(255,255,255,.5);padding:12px 28px;border-radius:50px;font-size:1.3rem;font-weight:800;margin-bottom:20px;animation:xpPop .4s ease;"></div>
    <button onclick="retakeCurrentGame(this)" style="padding:14px 32px;border-radius:50px;border:2px solid white;background:var(--accent-gold);color:#1e293b;font-weight:800;cursor:pointer;font-size:1.05rem;margin-top:8px;">↻ RETAKE GAME</button>
    <button onclick="window.location.href='quizzes.php'" style="padding:18px 45px;border-radius:50px;border:none;background:white;color:var(--primary);font-weight:800;cursor:pointer;font-size:1.3rem;box-shadow:0 4px 15px rgba(0,0,0,.2);margin-top:10px;">FORGE ANOTHER QUEST</button>
    <a href="studentdashboard.php" style="margin-top:15px;color:rgba(255,255,255,.8);font-size:14px;font-weight:600;text-decoration:none;">← Back to Dashboard</a>
</div>

<script>
const questions      = <?php echo json_encode($questions); ?>;
const quizType       = <?php echo json_encode($_SESSION['quiz_data']['type'] ?? 'multiple_choice'); ?>;
let currentIdx       = 0;
let score            = 0;
let correctAnswers   = 0;
let globalTimeLeft   = <?php echo $globalTimeSeconds; ?>;
let questionTimeLeft = <?php echo $questionTimeLimit; ?>;
let isNoTimer        = <?php echo $isNoTimer ? 'true' : 'false'; ?>;
let globalInterval, questionInterval, freezeCountdown;

/* ── Per-question answer log ──────────────────────────────────────────
   Used purely for record-keeping / test-case data: which question was
   shown, the correct answer, what the student actually answered, and
   whether they got it right. Sent to save_quiz_result.php on finish and
   stored in the solo_quiz_answers table. Does NOT affect gameplay. ──── */
let quizLog = [];

/* ── Power-up state ───────────────────────────────────────────────────── */
const PU = {
    vowelReveal:  { used:false }, firstLetter:  { used:false },
    wordLength:   { used:false }, contextHint:  { used:false },
    timeFreeze:   { used:false }, doublePoints: { used:false },
    doubleDip:    { used:false }, skip:         { used:false },
};
let doublePointsActive = false;
let doubleDipActive    = false;
let attemptsLeft       = 1;
let timeFrozen         = false;
let hintMask           = [];

/* ── Question type detection ───────────────────────────────────────────
   The selected format is stored in quiz_data.type. Generated questions
   may not include their own type field, so check both sources. */
function isFillBlankQuestion(q) {
    const selectedType = String(quizType || '').toLowerCase();
    const questionType = String(q?.type || '').toLowerCase();
    const questionText = String(q?.question || '');
    const hasOptions = Array.isArray(q?.options) && q.options.length >= 2;

    return selectedType.includes('fill')
        || questionType === 'fill_blank'
        || questionType === 'fill_blanks'
        || questionType === 'fill_in_the_blank'
        || questionType === 'fill_in_the_blanks'
        || (/_{2,}/.test(questionText) && !hasOptions);
}

/* ── Init ─────────────────────────────────────────────────────────────── */
function initQuiz() { if (!questions.length) return; startGlobalTimer(); showQuestion(); }

/* ── Global timer ─────────────────────────────────────────────────────── */
function startGlobalTimer() {
    if (isNoTimer) { document.getElementById('timerText').innerText = '∞'; return; }
    updateGlobalUI();
    globalInterval = setInterval(() => {
        globalTimeLeft--;
        updateGlobalUI();
        if (globalTimeLeft <= 0) {
            clearInterval(globalInterval);
            Swal.fire({ title:'QUEST TIME OVER! ⌛', text:'You ran out of time.', icon:'warning', confirmButtonText:'View Final Score' })
                .then(() => finishQuiz());
        }
    }, 1000);
}
function updateGlobalUI() {
    const m = Math.floor(globalTimeLeft/60), s = globalTimeLeft%60;
    document.getElementById('timerText').innerText = `${m}:${s<10?'0':''}${s}`;
}

/* ── Show question ────────────────────────────────────────────────────── */
function showQuestion() {
    const q         = questions[currentIdx];
    const qTextEl   = document.getElementById('qText');
    const mcqGrid   = document.getElementById('mcqGrid');
    const blankArea = document.getElementById('blankSubmitArea');
    const blankBtn  = document.getElementById('blankSubmitBtn');

    resetPerQuestionState();

    const isFill = isFillBlankQuestion(q);

    if (isFill) {
        mcqGrid.style.display   = 'none';
        blankArea.style.display = 'flex';
        document.getElementById('fillBlankPowerups').style.display = 'flex';

        const parts = String(q.question || '').split(/_{2,}/);
        qTextEl.innerHTML = '';
        if (parts[0]) qTextEl.appendChild(document.createTextNode(parts[0]));

        const inp = document.createElement('input');
        inp.type='text'; inp.id='blankInput'; inp.className='blank-input';
        inp.placeholder='...'; inp.autocomplete='off'; inp.spellcheck=false;
        inp.addEventListener('keydown', e => {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            e.stopPropagation();
            if (!e.repeat) submitFillBlank();
        });
        qTextEl.appendChild(inp);
        if (parts.length > 1 && parts[1]) {
            qTextEl.appendChild(document.createTextNode(parts.slice(1).join(' ')));
        }

        blankBtn.disabled = false;
        initHintMask(q.answer);
        setTimeout(() => inp.focus(), 200);

    } else {
        blankArea.style.display = 'none';
        mcqGrid.style.display   = 'grid';
        document.getElementById('fillBlankPowerups').style.display = 'none';
        qTextEl.innerText = q.question;

        const opts = q.options || [];
        for (let i = 0; i < 4; i++) {
            const btn = document.getElementById(`mcq-btn-${i}`);
            const el  = document.getElementById('opt'+i);
            if (btn) { btn.disabled=false; btn.style.opacity='1'; btn.classList.remove('correct-flash','wrong-fade'); }
            if (el)  el.innerText = opts[i] || '';
        }
    }

    document.getElementById('questionNumber').innerText = `Question ${currentIdx+1} of ${questions.length}`;
    resetQuestionTimer();
}

/* ── Per-question reset ───────────────────────────────────────────────── */
function resetPerQuestionState() {
    doubleDipActive = false; attemptsLeft = 1; hintMask = [];
    if (freezeCountdown) { clearInterval(freezeCountdown); freezeCountdown=null; }
    timeFrozen = false;
    document.getElementById('hintDisplay').style.display='none';
    document.getElementById('hintDisplay').innerHTML='';
    document.getElementById('dipBanner').style.display='none';
    document.getElementById('pu-double').classList.remove('pu-active');
    document.getElementById('pu-dip').classList.remove('pu-active');
    document.getElementById('pu-freeze-label').innerText='Time Freeze';
}

/* ── Question timer ───────────────────────────────────────────────────── */
function resetQuestionTimer() {
    clearInterval(questionInterval);
    questionTimeLeft = <?php echo $questionTimeLimit; ?>;
    updateQuestionBar();
    questionInterval = setInterval(() => {
        if (timeFrozen) return;
        questionTimeLeft--;
        updateQuestionBar();
        if (questionTimeLeft <= 0) {
            clearInterval(questionInterval);
            const q = questions[currentIdx];
            if (isFillBlankQuestion(q)) submitFillBlank(true); else checkMCQ(-99);
        }
    }, 1000);
}
function updateQuestionBar() {
    const bar = document.getElementById('timerBar');
    bar.style.width           = (questionTimeLeft/<?php echo $questionTimeLimit; ?>)*100+'%';
    bar.style.backgroundColor = questionTimeLeft<10 ? 'var(--danger)' : 'var(--primary)';
}

/* ── Hint mask ────────────────────────────────────────────────────────── */
function initHintMask(answer) { hintMask = answer.split('').map(ch => ch===' '?' ':null); }
function renderHintDisplay() {
    const answer = questions[currentIdx].answer;
    const vowels = new Set(['a','e','i','o','u']);
    const disp   = document.getElementById('hintDisplay');
    disp.innerHTML = hintMask.map((ch,i) => {
        if (ch===' ') return '<span class="ls"> </span>';
        if (!ch)      return '<span class="lb">_</span>';
        return `<span class="lb ${vowels.has(answer[i]?.toLowerCase())?'lbv':'lbr'}">${ch.toUpperCase()}</span>`;
    }).join('');
    disp.style.display='flex';
}

/* ══════════════════════════════════════════════════════════════
   POWER-UP ACTIVATORS
   ══════════════════════════════════════════════════════════════ */
function activateVowelReveal() {
    if (PU.vowelReveal.used) return; PU.vowelReveal.used=true; markUsed('pu-vowel');
    const v=new Set(['a','e','i','o','u']);
    questions[currentIdx].answer.split('').forEach((c,i)=>{ if(v.has(c.toLowerCase())) hintMask[i]=c; });
    renderHintDisplay(); showPuToast('🔤 Vowels revealed!');
}
function activateFirstLetter() {
    if (PU.firstLetter.used) return; PU.firstLetter.used=true; markUsed('pu-first');
    hintMask[0]=questions[currentIdx].answer[0]; renderHintDisplay();
    showPuToast(`🔡 First letter is "${questions[currentIdx].answer[0].toUpperCase()}"`);
}
function activateWordLength() {
    if (PU.wordLength.used) return; PU.wordLength.used=true; markUsed('pu-length');
    const l=questions[currentIdx].answer.replace(/ /g,'').length;
    renderHintDisplay(); showPuToast(`📏 Answer has ${l} letter${l!==1?'s':''}`);
}
function activateContextHint() {
    if (PU.contextHint.used) return; PU.contextHint.used=true; markUsed('pu-context');
    const q=questions[currentIdx], ans=q.answer.toLowerCase();
    const words=q.question.toLowerCase().replace(/____+/g,'').split(/\W+/).filter(w=>w.length>3&&!ans.includes(w));
    const clue=words.length?`Related to: "${words.slice(0,3).join(', ')}"`:
        `Starts with "${ans[0].toUpperCase()}", ends with "${ans[ans.length-1].toUpperCase()}"`;
    showPuToast('💡 '+clue,4000);
}
function activateTimeFreeze() {
    if (PU.timeFreeze.used||timeFrozen) return; PU.timeFreeze.used=true; markUsed('pu-freeze');
    timeFrozen=true; let secs=15;
    document.getElementById('pu-freeze-label').innerText=`❄️ ${secs}s`;
    showPuToast('❄️ Timer frozen for 15 seconds!');
    freezeCountdown=setInterval(()=>{
        secs--; document.getElementById('pu-freeze-label').innerText=`❄️ ${secs}s`;
        if(secs<=0){ clearInterval(freezeCountdown); freezeCountdown=null; timeFrozen=false;
            document.getElementById('pu-freeze-label').innerText='Unfrozen ✓'; showPuToast('❄️ Timer resumed!'); }
    },1000);
}
function activateDoublePoints() {
    if (PU.doublePoints.used) return; PU.doublePoints.used=true; doublePointsActive=true;
    markUsed('pu-double'); document.getElementById('pu-double').classList.add('pu-active');
    showPuToast('⚡ Double points active for this question!');
}
function activateDoubleDip() {
    if (PU.doubleDip.used||doubleDipActive) return; PU.doubleDip.used=true; doubleDipActive=true; attemptsLeft=2;
    markUsed('pu-dip'); document.getElementById('pu-dip').classList.add('pu-active');
    document.getElementById('dipBanner').style.display='flex';
    document.querySelector('.dip-count').innerText='2 attempts';
    showPuToast('🎯 Double-Dip active — 2 attempts this question!');
}
function activateSkip() {
    if (PU.skip.used) return; PU.skip.used=true; markUsed('pu-skip');
    clearInterval(questionInterval);
    if(freezeCountdown){clearInterval(freezeCountdown);freezeCountdown=null;}
    quizLog.push({
        q: questions[currentIdx].question,
        type: isFillBlankQuestion(questions[currentIdx]) ? 'fill_blank' : 'multiple_choice',
        options: questions[currentIdx].options || [],
        correct_answer: questions[currentIdx].answer,
        user_answer: null,
        is_correct: false
    });
    Swal.fire({ title:'Question Skipped ⏭️', html:`The correct answer was: <b>${questions[currentIdx].answer}</b>`,
        icon:'info', confirmButtonText:'Next Question', confirmButtonColor:'#6366f1' })
        .then(()=>proceedToNext());
}

function markUsed(id) { document.getElementById(id)?.classList.add('pu-used'); }

let toastTimer;
function showPuToast(msg,ms=2400) {
    /* Reuse SweetAlert2 minimal toast */
    Swal.fire({ toast:true, position:'top', html:`<span style="font-weight:700;font-size:14px;">${msg}</span>`,
        showConfirmButton:false, timer:ms, background:'#1e293b', color:'#fff',
        customClass:{popup:'swal2-toast-custom'} });
}

/* ══════════════════════════════════════════════════════════════
   FILL-BLANK SUBMISSION
   ══════════════════════════════════════════════════════════════ */
function submitFillBlank(timedOut = false) {
    const input = document.getElementById('blankInput');
    if (!input||input.disabled) return;
    const typed = input.value.trim();
    if (!timedOut && !typed) {
        input.style.borderBottomColor='var(--danger)';
        setTimeout(()=>{input.style.borderBottomColor='';},700);
        input.focus();
        return;
    }

    const q=questions[currentIdx], isCorrect=typed.toLowerCase()===q.answer.toLowerCase();

    if (!timedOut && !isCorrect&&doubleDipActive&&attemptsLeft>1) {
        attemptsLeft--; doubleDipActive=false;
        input.classList.add('wrong'); setTimeout(()=>{ input.classList.remove('wrong'); input.value=''; input.focus(); },750);
        document.querySelector('.dip-count').innerText=`${attemptsLeft} attempt remaining`;
        showPuToast('🎯 Wrong! One more chance...'); return;
    }

    clearInterval(questionInterval);
    if(freezeCountdown){clearInterval(freezeCountdown);freezeCountdown=null;timeFrozen=false;}
    input.disabled=true; document.getElementById('blankSubmitBtn').disabled=true;
    document.getElementById('dipBanner').style.display='none';

    // ── Log this answer for the test-case record ────────────────────────
    quizLog.push({
        q: q.question,
        type: 'fill_blank',
        options: [],
        correct_answer: q.answer,
        user_answer: typed || null,
        is_correct: isCorrect
    });

    if (isCorrect) {
        input.classList.add('correct'); correctAnswers++;
        let pts=500+(questionTimeLeft*10);
        if(doublePointsActive){pts*=2;doublePointsActive=false;document.getElementById('pu-double').classList.remove('pu-active');}
        score+=pts; document.getElementById('scoreDisplay').innerText='Score: '+score;
        Swal.fire({ title:'CORRECT! 🎉', html:`<span style="color:#1db968;font-weight:bold;font-size:1.5rem;">+${pts} pts</span><br>Excellent work!`,
            icon:'success', timer:2500, showConfirmButton:false }).then(()=>proceedToNext());
    } else {
        input.classList.add('wrong'); document.getElementById('scoreDisplay').innerText='Score: '+score;
        Swal.fire({ title:timedOut ? 'TIME’S UP! ⏰' : 'WRONG! ❌',
            html:`The correct answer was: <b>${q.answer}</b>`,
            icon:'error', confirmButtonText:'Next Question', confirmButtonColor:'#ef4444' }).then(()=>proceedToNext());
    }
}

/* ══════════════════════════════════════════════════════════════
   MCQ SUBMISSION
   ══════════════════════════════════════════════════════════════ */
function checkMCQ(selectedIndex) {
    clearInterval(questionInterval);
    if(freezeCountdown){clearInterval(freezeCountdown);freezeCountdown=null;timeFrozen=false;}

    const q=questions[currentIdx];
    const isCorrect=(selectedIndex===q.answer_index);

    // ── Log this answer for the test-case record ────────────────────────
    // selectedIndex is -99 when the timer runs out with nothing picked.
    quizLog.push({
        q: q.question,
        type: 'multiple_choice',
        options: q.options || [],
        correct_answer: q.answer,
        user_answer: (selectedIndex >= 0 && q.options) ? (q.options[selectedIndex] ?? null) : null,
        is_correct: isCorrect
    });

    // Disable & visually reveal all cards
    for (let i=0;i<4;i++) {
        const btn=document.getElementById(`mcq-btn-${i}`);
        if (!btn) continue;
        btn.disabled=true;
        if (i===q.answer_index) {
            btn.classList.add('correct-flash');
        } else if (i===selectedIndex && !isCorrect) {
            btn.classList.add('wrong-fade');
        } else {
            btn.style.opacity='0.35';
        }
    }

    if (isCorrect) {
        correctAnswers++;
        const pts=500+(questionTimeLeft*10);
        score+=pts;
        Swal.fire({ title:'CORRECT! 🎉', html:`<span style="color:#1db968;font-weight:bold;font-size:1.5rem;">+${pts} pts</span><br>Excellent work!`,
            icon:'success', timer:1500, showConfirmButton:false }).then(()=>proceedToNext());
    } else {
        Swal.fire({ title:'WRONG! ❌', html:`The correct answer was: <b>${q.answer}</b>`,
            icon:'error', confirmButtonText:'Next Question', confirmButtonColor:'#ef4444' }).then(()=>proceedToNext());
    }
    document.getElementById('scoreDisplay').innerText='Score: '+score;
}

/* ── Navigation ───────────────────────────────────────────────────────── */
function proceedToNext() {
    currentIdx++;
    if (currentIdx<questions.length) showQuestion(); else finishQuiz();
}

/* ── Finish ───────────────────────────────────────────────────────────── */
function finishQuiz() {
    clearInterval(globalInterval); clearInterval(questionInterval);
    if(freezeCountdown) clearInterval(freezeCountdown);
    window.gameResultSavePromise = fetch('save_quiz_result.php',{ method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`score=${score}&correct_answers=${correctAnswers}&total_questions=${questions.length}&quiz_log=${encodeURIComponent(JSON.stringify(quizLog))}` })
    .then(r=>r.json()).then(data=>{
        if(data.success&&data.xp_earned>0){
            const b=document.getElementById('xpEarnedBadge');
            if(b){b.innerText=`+${data.xp_earned} XP earned!`;b.style.display='block';}
        }
    }).catch(()=>{});
    document.getElementById('resultOverlay').style.display='flex';
    document.getElementById('finalScore').innerText=score;
    const cnt=document.getElementById('correctCount');
    if(cnt) cnt.innerText=`${correctAnswers} / ${questions.length} correct`;
}

initQuiz();
</script>
<script src="retake_current_game.js"></script>
</body>
</html>