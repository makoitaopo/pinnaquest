<?php
/**
 * companion.php — PinnaQuest Companion Widget
 * Include this file on any page: <?php include 'companion.php'; ?>
 *
 * Optional: pass $companion_page = 'dashboard' | 'materials' | 'quizzes'
 *           | 'synchro' | 'leaderboard' | 'quiz_active' | 'login'
 *           before including. Falls back to auto-detection.
 */

if (!isset($companion_page)) {
    $self = basename($_SERVER['PHP_SELF'] ?? '');
    $companion_page =
        str_contains($self, 'dashboard')  ? 'dashboard'  :
        (str_contains($self, 'material')  ? 'materials'  :
        (str_contains($self, 'quiz')      ? 'quizzes'    :
        (str_contains($self, 'synchro')   ? 'synchro'    :
        (str_contains($self, 'login')     ? 'login'      :
        (str_contains($self, 'leader')    ? 'leaderboard':
        (str_contains($self, 'waiting')   ? 'waiting'    :
        'default'))))));
}

$companion_name = "Pinna";
?>

<!-- ═══════════════════════════════════════════════════════
     PINNA — THE PINNAQUEST COMPANION
     Drop this anywhere. Floats in bottom-right corner.
     ═══════════════════════════════════════════════════════ -->

<style>
/* ── RESET & BASE ────────────────────────────────────────────────────── */
#pinna-root * { box-sizing: border-box; }

/* ── WRAPPER ─────────────────────────────────────────────────────────── */
#pinna-root {
    position: fixed;
    bottom: 28px;
    right: 28px;
    z-index: 9999;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 10px;
    font-family: 'Nunito', 'Lexend', 'Inter', sans-serif;
    pointer-events: none; /* children re-enable */
}

/* ── SPEECH BUBBLE ───────────────────────────────────────────────────── */
#pinna-bubble {
    pointer-events: all;
    position: relative;
    background: #ffffff;
    border: 2.5px solid #1a1a2e;
    border-radius: 22px 22px 6px 22px;
    padding: 14px 18px 14px 16px;
    max-width: 240px;
    min-width: 160px;
    box-shadow: 4px 5px 0 #1a1a2e;
    transform: scale(0);
    transform-origin: bottom right;
    transition: transform 0.32s cubic-bezier(.34,1.56,.64,1), opacity 0.25s;
    opacity: 0;
}
#pinna-bubble.visible {
    transform: scale(1);
    opacity: 1;
}

/* Tail */
#pinna-bubble::after {
    content: '';
    position: absolute;
    bottom: -14px;
    right: 20px;
    width: 0;
    height: 0;
    border-left: 10px solid transparent;
    border-right: 4px solid transparent;
    border-top: 14px solid #1a1a2e;
}
#pinna-bubble::before {
    content: '';
    position: absolute;
    bottom: -10px;
    right: 21px;
    width: 0;
    height: 0;
    border-left: 9px solid transparent;
    border-right: 3px solid transparent;
    border-top: 12px solid #ffffff;
    z-index: 1;
}

/* Bubble header */
.pinna-bubble-header {
    display: flex;
    align-items: center;
    gap: 7px;
    margin-bottom: 8px;
}
.pinna-name-tag {
    background: #1db968;
    color: white;
    font-size: 9px;
    font-weight: 900;
    padding: 2px 8px;
    border-radius: 20px;
    letter-spacing: 1px;
    text-transform: uppercase;
}
.pinna-mood-tag {
    font-size: 13px;
    line-height: 1;
}

/* Message text */
#pinna-msg {
    font-size: 13px;
    font-weight: 700;
    color: #1a202c;
    line-height: 1.5;
    min-height: 18px;
}

/* Typing dots */
.pinna-typing {
    display: none;
    gap: 4px;
    align-items: center;
    height: 18px;
    margin-top: 2px;
}
.pinna-typing.active { display: flex; }
#pinna-msg.typing    { display: none; }
.pinna-typing span {
    width: 6px;
    height: 6px;
    background: #94a3b8;
    border-radius: 50%;
    animation: ptDot 1s infinite;
}
.pinna-typing span:nth-child(2) { animation-delay: .2s; }
.pinna-typing span:nth-child(3) { animation-delay: .4s; }
@keyframes ptDot {
    0%,100% { transform: translateY(0); opacity: .4; }
    50%      { transform: translateY(-5px); opacity: 1; }
}

/* Close / next btn */
.pinna-dismiss {
    position: absolute;
    top: 8px;
    right: 10px;
    background: none;
    border: none;
    font-size: 15px;
    cursor: pointer;
    color: #94a3b8;
    line-height: 1;
    padding: 0;
    transition: color .2s;
}
.pinna-dismiss:hover { color: #ef4444; }

/* Quick-action chips */
.pinna-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    margin-top: 10px;
}
.pinna-chip {
    background: #f0fff4;
    border: 1.5px solid #1db968;
    color: #1a4d2e;
    font-size: 11px;
    font-weight: 800;
    padding: 4px 10px;
    border-radius: 20px;
    cursor: pointer;
    transition: .18s;
    white-space: nowrap;
}
.pinna-chip:hover { background: #1db968; color: white; }

/* ── DRAGON BODY ─────────────────────────────────────────────────────── */
#pinna-body {
    pointer-events: all;
    cursor: pointer;
    position: relative;
    width: 72px;
    height: 80px;
    transition: transform .2s;
    animation: pinnaBob 3s ease-in-out infinite;
    filter: drop-shadow(3px 4px 0 rgba(0,0,0,.25));
}
#pinna-body:hover { transform: scale(1.1); }
#pinna-body:active { transform: scale(.96); }

@keyframes pinnaBob {
    0%,100% { transform: translateY(0)  rotate(-1deg); }
    40%      { transform: translateY(-9px) rotate(1.5deg); }
    70%      { transform: translateY(-4px) rotate(-.5deg); }
}
#pinna-body.pinna-happy {
    animation: pinnaHappy .5s cubic-bezier(.34,1.56,.64,1) forwards, pinnaBob 3s ease-in-out 0.5s infinite;
}
@keyframes pinnaHappy {
    0%   { transform: scale(1) rotate(0deg); }
    30%  { transform: scale(1.25) rotate(-8deg); }
    60%  { transform: scale(1.2) rotate(8deg); }
    100% { transform: scale(1) rotate(0deg); }
}
#pinna-body.pinna-talk {
    animation: pinnaTalk .35s ease-in-out infinite alternate;
}
@keyframes pinnaTalk {
    from { transform: translateY(0) rotate(-1deg) scaleY(1); }
    to   { transform: translateY(-4px) rotate(1deg) scaleY(1.03); }
}

/* Notification dot */
#pinna-dot {
    position: absolute;
    top: -2px;
    right: -2px;
    width: 14px;
    height: 14px;
    background: #ef4444;
    border: 2.5px solid white;
    border-radius: 50%;
    animation: dotPing 1.5s ease-in-out infinite;
    display: none;
}
#pinna-dot.show { display: block; }
@keyframes dotPing {
    0%,100% { transform: scale(1); }
    50%      { transform: scale(1.35); }
}

/* ── XP CELEBRATION BURST ────────────────────────────────────────────── */
.pinna-burst {
    position: absolute;
    pointer-events: none;
    font-size: 18px;
    font-weight: 900;
    color: #f59e0b;
    animation: burstFly 1.1s ease-out forwards;
    z-index: 100;
}
@keyframes burstFly {
    0%   { transform: translateY(0)   scale(0.5); opacity: 1; }
    100% { transform: translateY(-70px) scale(1.3); opacity: 0; }
}
</style>

<!-- GOOGLE FONT FALLBACK (Nunito already loaded on most pinna pages) -->
<link rel="preconnect" href="https://fonts.googleapis.com">

<div id="pinna-root">

    <!-- SPEECH BUBBLE -->
    <div id="pinna-bubble">
        <button class="pinna-dismiss" id="pinna-close" title="Dismiss">✕</button>
        <div class="pinna-bubble-header">
            <span class="pinna-name-tag">Pinna</span>
            <span class="pinna-mood-tag" id="pinna-mood">🐉</span>
        </div>
        <div class="pinna-typing" id="pinna-typing">
            <span></span><span></span><span></span>
        </div>
        <div id="pinna-msg"></div>
        <div class="pinna-chips" id="pinna-chips"></div>
    </div>

    <!-- DRAGON MASCOT (SVG) -->
    <div id="pinna-body" title="Click Pinna!">
        <div id="pinna-dot"></div>
        <svg width="72" height="80" viewBox="0 0 72 80" fill="none" xmlns="http://www.w3.org/2000/svg">
            <!-- Tail -->
            <path d="M12 65 Q4 72 8 78 Q14 74 18 68Z" fill="#22c55e" stroke="#1a1a2e" stroke-width="2" stroke-linejoin="round"/>
            <!-- Body -->
            <ellipse cx="36" cy="52" rx="22" ry="20" fill="#22c55e" stroke="#1a1a2e" stroke-width="2.5"/>
            <!-- Belly -->
            <ellipse cx="36" cy="55" rx="13" ry="13" fill="#bbf7d0" stroke="none"/>
            <!-- Neck -->
            <rect x="27" y="28" width="18" height="16" rx="8" fill="#22c55e" stroke="#1a1a2e" stroke-width="2"/>
            <!-- Head -->
            <ellipse cx="36" cy="24" rx="17" ry="15" fill="#22c55e" stroke="#1a1a2e" stroke-width="2.5"/>
            <!-- Snout -->
            <ellipse cx="36" cy="31" rx="8" ry="5" fill="#16a34a" stroke="#1a1a2e" stroke-width="2"/>
            <!-- Nostrils -->
            <circle cx="33" cy="31.5" r="1.2" fill="#1a1a2e"/>
            <circle cx="39" cy="31.5" r="1.2" fill="#1a1a2e"/>
            <!-- Eyes -->
            <ellipse cx="28.5" cy="20" rx="4.5" ry="4.5" fill="white" stroke="#1a1a2e" stroke-width="2"/>
            <ellipse cx="43.5" cy="20" rx="4.5" ry="4.5" fill="white" stroke="#1a1a2e" stroke-width="2"/>
            <!-- Pupils -->
            <circle id="pinna-eye-l" cx="29.5" cy="21" r="2.5" fill="#1a1a2e"/>
            <circle id="pinna-eye-r" cx="44.5" cy="21" r="2.5" fill="#1a1a2e"/>
            <!-- Eye shine -->
            <circle cx="30.5" cy="20" r="1" fill="white"/>
            <circle cx="45.5" cy="20" r="1" fill="white"/>
            <!-- Horns -->
            <path d="M27 12 L24 4 L30 11Z" fill="#f59e0b" stroke="#1a1a2e" stroke-width="2" stroke-linejoin="round"/>
            <path d="M45 12 L48 4 L42 11Z" fill="#f59e0b" stroke="#1a1a2e" stroke-width="2" stroke-linejoin="round"/>
            <!-- Left wing -->
            <path d="M14 42 Q2 30 6 18 Q14 26 18 40Z" fill="#16a34a" stroke="#1a1a2e" stroke-width="2" stroke-linejoin="round"/>
            <!-- Right wing -->
            <path d="M58 42 Q70 30 66 18 Q58 26 54 40Z" fill="#16a34a" stroke="#1a1a2e" stroke-width="2" stroke-linejoin="round"/>
            <!-- Leg left -->
            <path d="M22 68 L19 76 L24 77 L25 72 L30 71Z" fill="#22c55e" stroke="#1a1a2e" stroke-width="2" stroke-linejoin="round"/>
            <!-- Leg right -->
            <path d="M50 68 L53 76 L48 77 L47 72 L42 71Z" fill="#22c55e" stroke="#1a1a2e" stroke-width="2" stroke-linejoin="round"/>
            <!-- Star badge on chest -->
            <text x="31" y="57" font-size="11" fill="#f59e0b" font-family="serif">⭐</text>
        </svg>
    </div>

</div>

<script>
/* ═══════════════════════════════════════════════════════════════════
   PINNA COMPANION — SCRIPT
   ═══════════════════════════════════════════════════════════════════ */
(function() {

/* ── Message Library ─────────────────────────────────────────────── */
const MESSAGES = {
    login: [
        { mood:'🐉', text:"Welcome back, adventurer! Ready to continue your quest? 🗡️", chips:[] },
        { mood:'✨', text:"PinnaQuest awaits! Log in and earn that XP!", chips:[] },
        { mood:'🌟', text:"Your knowledge journey continues here. Let's go!", chips:[] },
    ],
    dashboard: [
        { mood:'👋', text:"Hey! I'm Pinna, your quest companion. I'll guide you through PinnaQuest!", chips:['Got it!', 'Tell me more'] },
        { mood:'🗺️', text:"Your dashboard shows your XP, level, and recent activity. Keep grinding!", chips:[] },
        { mood:'⚡', text:"Tip: Try Synchro-Quiz for live battles with classmates — great XP!", chips:['Open Synchro'] },
        { mood:'🏆', text:"Check the Leaderboard to see how you rank against your class!", chips:['View Leaderboard'] },
        { mood:'📈', text:"The more quizzes you complete, the faster you level up. Let's go!", chips:[] },
    ],
    materials: [
        { mood:'📚', text:"Upload your PDF study materials here. I'll help turn them into quizzes!", chips:[] },
        { mood:'💡', text:"Tip: The clearer your PDF, the better questions our engine generates!", chips:[] },
        { mood:'🗂️', text:"Organize your materials and they'll be ready when you forge a quiz!", chips:[] },
    ],
    quizzes: [
        { mood:'🔥', text:"Time to forge a quest! Pick a material and I'll generate questions for you.", chips:['Forge Quiz'] },
        { mood:'🧠', text:"Tip: Start with Easy difficulty, then work your way up to Hard!", chips:[] },
        { mood:'⚡', text:"Fill-in-the-blank quizzes are tougher but give great XP rewards!", chips:[] },
        { mood:'🎯', text:"Remember: speed matters! Faster correct answers = more points!", chips:[] },
    ],
    synchro: [
        { mood:'⚡', text:"Synchro-Quiz — real-time battles! Enter a room code to join!", chips:[] },
        { mood:'🏟️', text:"Tip: Use power-ups wisely in Synchro. You only get 5 per quiz!", chips:[] },
        { mood:'🔥', text:"Build a streak for bonus points! Answer correctly in a row!", chips:[] },
    ],
    leaderboard: [
        { mood:'🏆', text:"The Hall of Fame! Complete missions to climb the ranks!", chips:[] },
        { mood:'⭐', text:"Check the Mission Map tab — clear quests for bonus XP rewards!", chips:[] },
        { mood:'👑', text:"Reach Level 5 to unlock the Legend achievement. Keep grinding!", chips:[] },
    ],
    waiting: [
        { mood:'⏳', text:"Hang tight! The teacher will start the quest soon. Warm up your brain! 🧠", chips:[] },
        { mood:'💪', text:"While waiting: remember to use power-ups strategically — only 5 per quiz!", chips:[] },
    ],
    default: [
        { mood:'🐉', text:"I'm Pinna! Click me whenever you need a tip or a pep talk! 🌟", chips:[] },
        { mood:'✨', text:"Earning XP is how you level up. Quiz more, level more!", chips:[] },
    ],
    celebrate: [
        { mood:'🎉', text:"INCREDIBLE! You're on fire! 🔥 Keep that streak alive!", chips:[] },
        { mood:'🏆', text:"Achievement unlocked vibes! You're crushing it!", chips:[] },
        { mood:'⭐', text:"Look at you go! XP earned, adventure continues!", chips:[] },
    ],
};

/* ── State ───────────────────────────────────────────────────────── */
const PAGE     = "<?php echo $companion_page; ?>";
const STORAGE  = 'pinna_state';
let msgIndex   = 0;
let isOpen     = false;
let isTalking  = false;
let greetTimer = null;

const root    = document.getElementById('pinna-root');
const bubble  = document.getElementById('pinna-bubble');
const msgEl   = document.getElementById('pinna-msg');
const typingEl= document.getElementById('pinna-typing');
const moodEl  = document.getElementById('pinna-mood');
const chipsEl = document.getElementById('pinna-chips');
const body    = document.getElementById('pinna-body');
const dot     = document.getElementById('pinna-dot');
const closeBtn= document.getElementById('pinna-close');

/* ── Typewriter ─────────────────────────────────────────────────── */
function typewrite(text, cb) {
    msgEl.classList.add('typing');
    typingEl.classList.add('active');
    let i = 0;
    const showTyping = setTimeout(() => {
        typingEl.classList.remove('active');
        msgEl.classList.remove('typing');
        msgEl.innerText = '';
        const iv = setInterval(() => {
            msgEl.innerText += text[i++];
            if (i >= text.length) { clearInterval(iv); if (cb) cb(); }
        }, 22);
    }, 600);
}

/* ── Show message ───────────────────────────────────────────────── */
function showMessage(msgObj, animate) {
    moodEl.innerText  = msgObj.mood;
    chipsEl.innerHTML = '';

    if (!isOpen) {
        bubble.classList.add('visible');
        isOpen = true;
        dot.classList.remove('show');
    }

    body.classList.remove('pinna-talk');
    void body.offsetWidth;
    body.classList.add('pinna-talk');
    isTalking = true;

    if (animate) {
        typewrite(msgObj.text, () => {
            isTalking = false;
            body.classList.remove('pinna-talk');
            buildChips(msgObj.chips || []);
        });
    } else {
        msgEl.innerText = msgObj.text;
        buildChips(msgObj.chips || []);
        isTalking = false;
        body.classList.remove('pinna-talk');
    }
}

/* ── Chips ──────────────────────────────────────────────────────── */
function buildChips(chips) {
    chipsEl.innerHTML = '';
    chips.forEach(label => {
        const btn = document.createElement('button');
        btn.className = 'pinna-chip';
        btn.innerText = label;
        btn.onclick = () => handleChip(label);
        chipsEl.appendChild(btn);
    });
}

function handleChip(label) {
    const nav = {
        'Got it!':          null,
        'Tell me more':     () => cycleMessage(),
        'Open Synchro':     () => window.location.href = 'synchro_portal.php',
        'View Leaderboard': () => window.location.href = 'leaderboard.php',
        'Forge Quiz':       () => document.getElementById('openForgeBtn')?.click(),
    };
    if (nav[label]) nav[label]();
    else closeBubble();
}

/* ── Cycle through messages ─────────────────────────────────────── */
function cycleMessage() {
    const pool = MESSAGES[PAGE] || MESSAGES.default;
    msgIndex   = (msgIndex + 1) % pool.length;
    showMessage(pool[msgIndex], true);
}

/* ── Close bubble ───────────────────────────────────────────────── */
function closeBubble() {
    bubble.classList.remove('visible');
    isOpen    = false;
    isTalking = false;
    body.classList.remove('pinna-talk', 'pinna-happy');
    saveClosed();
}

/* ── Click mascot body ──────────────────────────────────────────── */
body.addEventListener('click', () => {
    if (isOpen) {
        cycleMessage();
    } else {
        const pool = MESSAGES[PAGE] || MESSAGES.default;
        showMessage(pool[msgIndex % pool.length], true);
        body.classList.add('pinna-happy');
        setTimeout(() => body.classList.remove('pinna-happy'), 800);
        clearClosed();
    }
});

closeBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    closeBubble();
});

/* ── Auto-greet on first visit per page ─────────────────────────── */
const stateKey = STORAGE + '_' + PAGE;
function wasClosed()   { return sessionStorage.getItem(stateKey) === 'closed'; }
function saveClosed()  { sessionStorage.setItem(stateKey, 'closed'); }
function clearClosed() { sessionStorage.removeItem(stateKey); }

function greet() {
    if (wasClosed()) {
        dot.classList.add('show'); /* show red dot instead */
        return;
    }
    // Show notification dot first, then pop up after 1.5s
    dot.classList.add('show');
    greetTimer = setTimeout(() => {
        dot.classList.remove('show');
        const pool = MESSAGES[PAGE] || MESSAGES.default;
        showMessage(pool[0], true);
    }, 1500);
}

/* ── XP / Achievement celebration — call window.pinnaCelebrate() ── */
window.pinnaCelebrate = function(message) {
    const pool = MESSAGES.celebrate;
    const msg  = message
        ? { mood: '🎉', text: message, chips: [] }
        : pool[Math.floor(Math.random() * pool.length)];

    body.classList.add('pinna-happy');
    showMessage(msg, true);
    spawnBurst();
    setTimeout(() => body.classList.remove('pinna-happy'), 800);
};

/* ── XP Burst ───────────────────────────────────────────────────── */
function spawnBurst() {
    const symbols = ['⭐','✨','🔥','💥','🌟','⚡'];
    for (let i = 0; i < 5; i++) {
        setTimeout(() => {
            const el = document.createElement('div');
            el.className = 'pinna-burst';
            el.innerText = symbols[Math.floor(Math.random() * symbols.length)];
            el.style.left = (Math.random() * 60 - 10) + 'px';
            el.style.bottom = '80px';
            body.appendChild(el);
            setTimeout(() => el.remove(), 1200);
        }, i * 120);
    }
}

/* ── Blink animation ────────────────────────────────────────────── */
function blink() {
    const eyes = [
        document.getElementById('pinna-eye-l'),
        document.getElementById('pinna-eye-r'),
    ];
    if (!eyes[0]) return;
    eyes.forEach(e => { e.setAttribute('ry','0.4'); e.setAttribute('rx', e.getAttribute('rx')||'2.5'); });
    setTimeout(() => eyes.forEach(e => e.setAttribute('ry','2.5')), 120);
}
setInterval(blink, 3800 + Math.random() * 2000);

/* ── Idle nudge (notification dot after 45s of silence) ─────────── */
let idleTimer;
function resetIdle() {
    clearTimeout(idleTimer);
    idleTimer = setTimeout(() => {
        if (!isOpen) dot.classList.add('show');
    }, 45000);
}
['click','keydown','scroll','mousemove'].forEach(ev => document.addEventListener(ev, resetIdle, {passive:true}));

/* ── Boot ───────────────────────────────────────────────────────── */
window.addEventListener('DOMContentLoaded', greet);
// Also catch cases where DOM is already ready
if (document.readyState !== 'loading') greet();

})();
</script>
</body>
</html>