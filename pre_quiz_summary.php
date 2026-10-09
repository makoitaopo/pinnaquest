<?php 
session_start(); 
require_once __DIR__ . '/game_mode_access.php';

$summary = isset($_SESSION['summary']) ? $_SESSION['summary'] : null;
$title   = isset($_SESSION['title'])   ? $_SESSION['title']   : 'Generated Quest';

// Detect available game modes based on question data
$questions   = $_SESSION['quiz_data']['questions'] ?? [];
$quiz_type   = $_SESSION['quiz_data']['type']      ?? 'multiple_choice';
$total_q     = count($questions);

// Fill-in-the-blanks game modes accept the complete quiz question set.
// Each game is responsible for presenting longer answers in a usable layout;
// answer length must not make the mode appear unavailable here.
$can_scramble = true;

// Masked Impostor: needs at least 3 REAL multiple-choice questions
// (4 real options + a resolvable answer). Fill-in-the-blank questions
// won't have 4 options, so this naturally gates the mode to MC quizzes.
$mcq_count = 0;
foreach ($questions as $q) {
    if (!empty($q['options']) && count($q['options']) >= 4 && (isset($q['answer_index']) || !empty($q['answer']))) {
        $mcq_count++;
    }
}
$can_masquerade = ($mcq_count >= 3);

// All fill-in-the-blanks modes are content-eligible. They can handle answers
// of different lengths, so only the XP-based access check remains below.
$can_decoy  = true;
$can_typo   = true;
$can_gremlin = true;

// XP-based game-mode progression remains the only access requirement for
// advanced modes.
$studentProgress = getStudentProgress();
$studentLevel = (int) $studentProgress['level'];
$modeAccess = [];
foreach (array_keys(getGameModeRequirements()) as $modeKey) {
    $modeAccess[$modeKey] = getGameModeAccess($modeKey, $studentProgress);
}

$modeCanUse = [
    'bubble_pop' => $modeAccess['bubble_pop']['allowed'],
    'masked_impostor' => $modeAccess['masked_impostor']['allowed'] && $can_masquerade,
    'bomb_toss' => $modeAccess['bomb_toss']['allowed'] && $can_masquerade,
    'hit_or_fold' => $modeAccess['hit_or_fold']['allowed'] && $can_masquerade,
    'word_scramble' => $modeAccess['word_scramble']['allowed'] && $can_scramble,
    'decoy_printer' => $modeAccess['decoy_printer']['allowed'] && $can_decoy,
    'typo_bomb' => $modeAccess['typo_bomb']['allowed'] && $can_typo,
    'typo_gremlin' => $modeAccess['typo_gremlin']['allowed'] && $can_gremlin,
];

$modeLockText = static function (string $mode, bool $contentOkay = true) use ($modeAccess): string {
    if (!$modeAccess[$mode]['allowed']) {
        return 'Unlocks at Level ' . (int) $modeAccess[$mode]['required_level'];
    }
    return $contentOkay ? '' : 'Quiz format unavailable';
};

// Which quest track are we on? Drives which game cards are shown below.
$is_fib      = ($quiz_type === 'fill_blanks');
$difficulty  = $_SESSION['quiz_data']['difficulty'] ?? 'easy';
$diff_label  = ucfirst($difficulty);
$track_label = $is_fib ? 'Fill in the Blanks' : 'Multiple Choice';
$track_icon  = $is_fib ? 'fa-pen-nib' : 'fa-list-check';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script src="notifications.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Study Reviewer | PinnaQuest</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@700;800&family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary:      #1db968;
            --primary-dark: #1a4d2e;
            --bg-light:     #fcfdfa;
            --text-dark:    #2d3748;
            --text-gray:    #718096;
            --border:       #f1f5f9;
            --gold:         #f59e0b;
            --purple:       #6366f1;
        }

        *,*::before,*::after{ box-sizing:border-box;margin:0;padding:0; }

        body {
            font-family: 'Inter', sans-serif;
            background:
                radial-gradient(circle at 8% 6%, rgba(29,185,104,.10) 0%, transparent 42%),
                radial-gradient(circle at 96% 92%, rgba(29,185,104,.08) 0%, transparent 40%),
                var(--bg-light);
            background-attachment: fixed;
            padding: 20px;
            color: var(--text-dark);
            line-height: 1.6;
        }

        .reviewer-container {
            max-width: 880px;
            margin: 40px auto;
            background: white;
            padding: 50px;
            border-radius: 30px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.08);
            position: relative;
            overflow: hidden;
        }
        /* corner "quest scroll" accents */
        .reviewer-container::before, .reviewer-container::after {
            content: "";
            position: absolute; width: 160px; height: 160px;
            background: radial-gradient(circle, rgba(29,185,104,.10), transparent 70%);
            pointer-events: none; z-index: 0;
        }
        .reviewer-container::before { top: -60px; left: -60px; }
        .reviewer-container::after  { bottom: -60px; right: -60px; }
        .reviewer-container > * { position: relative; z-index: 1; }

        /* ── Header / Quest Briefing HUD ── */
        .header-section { text-align: center; margin-bottom: 30px; }
        .badge {
            background: var(--primary); color: white;
            padding: 5px 15px; border-radius: 20px;
            font-size: 12px; text-transform: uppercase; letter-spacing: 1px;
            display: inline-flex; align-items: center; gap: 6px;
            box-shadow: 0 4px 10px rgba(29,185,104,.35);
        }
        .badge i { font-size: 10px; }
        h1 {
            color: var(--primary-dark); font-size: 27px;
            font-weight: 800; margin: 14px 0 8px;
            font-family: 'Lexend', sans-serif;
        }
        .header-sub { color: var(--text-gray); font-size: 14px; margin-bottom: 16px; }

        /* HUD chip row: quest track / difficulty / question count */
        .quest-hud {
            display: flex; justify-content: center; flex-wrap: wrap; gap: 10px;
            margin-top: 4px;
        }
        .hud-chip {
            display: inline-flex; align-items: center; gap: 8px;
            background: #f0fff4; border: 1.5px solid #a7f3d0;
            padding: 8px 16px; border-radius: 30px;
            font-size: 12.5px; font-weight: 700; color: var(--primary-dark);
        }
        .hud-chip i { color: var(--primary); font-size: 13px; }
        .hud-chip.diff-easy   { background: #f0fff4; border-color: #a7f3d0; color: #14532d; }
        .hud-chip.diff-medium { background: #fffbeb; border-color: #fde68a; color: #854d0e; }
        .hud-chip.diff-hard   { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
        .hud-chip.diff-medium i, .hud-chip.diff-hard i { color: inherit; }

        /* ── Section headings — styled as a "quest checklist" path ── */
        .section-title {
            font-size: 14px; font-weight: 800; color: var(--primary);
            display: flex; align-items: center; gap: 10px;
            text-transform: uppercase; letter-spacing: .5px;
            margin: 32px 0 14px;
        }
        .section-title .step-dot {
            width: 24px; height: 24px; border-radius: 50%;
            background: var(--primary); color: white;
            display: flex; align-items: center; justify-content: center;
            font-size: 11px; flex-shrink: 0;
            box-shadow: 0 3px 8px rgba(29,185,104,.4);
        }

        /* ── Overview ── */
        .overview-box {
            background: #f0fff4; padding: 22px; border-radius: 18px;
            border-left: 5px solid var(--primary); font-size: 15px;
            color: var(--text-dark); line-height: 1.7;
            position: relative;
        }

        /* ── Terms ── */
        .term-card {
            background: #fffcf0; border: 1px solid #ffeeba;
            padding: 13px 18px; border-radius: 12px; margin-bottom: 10px;
            transition: .2s;
        }
        .term-card:hover { transform: translateX(4px); border-color: var(--primary); box-shadow: 0 6px 16px rgba(29,185,104,.12); }

        /* ── Study points ── */
        .point-item {
            padding: 12px 12px 12px 38px; position: relative;
            border-bottom: 1px solid var(--border); font-size: 14px;
            transition: background .2s;
        }
        .point-item:hover { background: #f7fffa; }
        .point-item::before {
            content: "✔"; position: absolute; left: 8px; top: 12px;
            width: 18px; height: 18px; border-radius: 50%;
            background: var(--primary); color: white;
            font-size: 10px; font-weight: 900;
            display: flex; align-items: center; justify-content: center;
        }

        /* ── "Ready to play" CTA banner between summary and games ── */
        .ready-banner {
            margin-top: 36px;
            display: flex; align-items: center; gap: 14px;
            background: linear-gradient(90deg, var(--primary-dark), var(--primary));
            color: white; padding: 16px 24px; border-radius: 18px;
            box-shadow: 0 10px 24px rgba(29,185,104,.28);
        }
        .ready-banner i { font-size: 22px; color: #d9ffe9; flex-shrink: 0; }
        .ready-banner strong { font-family: 'Lexend', sans-serif; font-size: 15px; display: block; }
        .ready-banner span { font-size: 12.5px; opacity: .9; }

        /* ════════════════════════════════════════════
           GAME MODE SELECTOR
        ════════════════════════════════════════════ */
        .game-mode-section {
            margin-top: 44px;
            padding: 32px;
            background: linear-gradient(135deg, #f0fff4 0%, #fff 60%);
            border-radius: 24px;
            border: 1.5px solid #a7f3d0;
        }
        .game-mode-heading {
            text-align: center;
            margin-bottom: 6px;
        }
        .game-mode-heading h2 {
            font-family: 'Lexend', sans-serif;
            font-size: 20px; font-weight: 800;
            color: var(--primary-dark);
            display: flex; align-items: center; justify-content: center; gap: 10px;
        }
        .game-mode-heading p {
            font-size: 13px; color: var(--text-gray); margin-top: 5px; margin-bottom: 22px;
        }

        .mode-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin-bottom: 0;
        }

        .mode-card {
            border-radius: 20px;
            padding: 22px 18px;
            border: 2px solid transparent;
            cursor: pointer;
            transition: all .22s cubic-bezier(.34,1.56,.64,1);
            text-align: center;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            position: relative;
            overflow: hidden;
        }
        .mode-card:hover { transform: translateY(-6px); }
        .mode-card:active { transform: translateY(-2px) scale(.98); }

        /* Classic quiz */
        .mode-classic {
            background: linear-gradient(135deg, #1a4d2e, #1db968);
            border-color: #1db968;
            box-shadow: 0 8px 0 #14452b, 0 12px 24px rgba(29,185,104,.25);
        }
        .mode-classic:hover {
            box-shadow: 0 12px 0 #14452b, 0 18px 32px rgba(29,185,104,.35);
        }
        .mode-classic .mode-title, .mode-classic .mode-desc { color: white; }
        .mode-classic .mode-icon-wrap { background: rgba(255,255,255,.18); }

       

        /* Word Scramble */
        .mode-scramble {
            background: linear-gradient(135deg, #4c1d95, #6366f1);
            border-color: #6366f1;
            box-shadow: 0 8px 0 #3730a3, 0 12px 24px rgba(99,102,241,.25);
        }
        .mode-scramble:hover {
            box-shadow: 0 12px 0 #3730a3, 0 18px 32px rgba(99,102,241,.35);
        }
        .mode-scramble .mode-title, .mode-scramble .mode-desc { color: white; }
        .mode-scramble .mode-icon-wrap { background: rgba(255,255,255,.15); }

        /* Bubble Pop */
        .mode-bubble {
            background: linear-gradient(135deg, #0c4a6e, #0891b2);
            border-color: #06b6d4;
            box-shadow: 0 8px 0 #0e7490, 0 12px 24px rgba(6,182,212,.25);
        }
        .mode-bubble:hover {
            box-shadow: 0 12px 0 #0e7490, 0 18px 32px rgba(6,182,212,.35);
        }
        .mode-bubble .mode-title, .mode-bubble .mode-desc { color: white; }
        .mode-bubble .mode-icon-wrap { background: rgba(255,255,255,.15); }

        /* Masked Impostor */
        .mode-masquerade {
            background: linear-gradient(135deg, #150a26, #3d1f5c);
            border-color: #d4af37;
            box-shadow: 0 8px 0 #0d0617, 0 12px 24px rgba(212,175,55,.25);
        }
        .mode-masquerade:hover {
            box-shadow: 0 12px 0 #0d0617, 0 18px 32px rgba(212,175,55,.35);
        }
        .mode-masquerade .mode-title, .mode-masquerade .mode-desc { color: white; }
        .mode-masquerade .mode-icon-wrap { background: rgba(212,175,55,.2); color: #f3d876; }

        .mode-bomb {
    background: linear-gradient(135deg, #7a1f1f, #ff3b3b);
    border-color: #ff8c2b;
    box-shadow: 0 8px 0 #5c1414, 0 12px 24px rgba(255,59,59,.25);
}
.mode-bomb:hover { box-shadow: 0 12px 0 #5c1414, 0 18px 32px rgba(255,59,59,.35); }
.mode-bomb .mode-title, .mode-bomb .mode-desc { color: white; }
.mode-bomb .mode-icon-wrap { background: rgba(255,255,255,.15); }

/* Hit or Fold — red felt / gold casino theme */
.mode-hitfold {
    background: linear-gradient(135deg, #7a1f1a, #c0392b);
    border-color: #f7c948;
    box-shadow: 0 8px 0 #4a0f0c, 0 12px 24px rgba(192,57,43,.35);
}
.mode-hitfold:hover {
    box-shadow: 0 12px 0 #4a0f0c, 0 18px 32px rgba(192,57,43,.45);
}
.mode-hitfold .mode-title, .mode-hitfold .mode-desc { color: white; }
.mode-hitfold .mode-icon-wrap {
    background: rgba(247,201,72,.18);
    color: #f7c948;
    font-size: 26px;
}
/* The Decoy Printer — dot-matrix / analog interference theme */
.mode-decoy {
    background: linear-gradient(135deg, #16130f, #2b6fef);
    border-color: #f5a623;
    box-shadow: 0 8px 0 #0a0908, 0 12px 24px rgba(43,111,239,.3);
}
.mode-decoy:hover {
    box-shadow: 0 12px 0 #0a0908, 0 18px 32px rgba(43,111,239,.4);
}
.mode-decoy .mode-title, .mode-decoy .mode-desc { color: white; }
.mode-decoy .mode-icon-wrap {
    background: rgba(245,166,35,.18);
    color: #f5a623;
    font-size: 26px;
}

/* Typo Bomb — comic red/orange danger theme */
.mode-typobomb {
    background: linear-gradient(135deg, #3a1414, #ff5a3a);
    border-color: #ffd23f;
    box-shadow: 0 8px 0 #200a0a, 0 12px 24px rgba(255,90,58,.3);
}
.mode-typobomb:hover {
    box-shadow: 0 12px 0 #200a0a, 0 18px 32px rgba(255,90,58,.4);
}
.mode-typobomb .mode-title, .mode-typobomb .mode-desc { color: white; }
.mode-typobomb .mode-icon-wrap {
    background: rgba(255,210,63,.18);
    color: #ffd23f;
    font-size: 26px;
}

/* Typo Gremlin — mischievous goblin-workshop theme */
.mode-gremlin {
    background: linear-gradient(135deg, #1b1030, #4f9020);
    border-color: #7fd93b;
    box-shadow: 0 8px 0 #10251a, 0 12px 24px rgba(127,217,59,.25);
}
.mode-gremlin:hover {
    box-shadow: 0 12px 0 #10251a, 0 18px 32px rgba(127,217,59,.35);
}
.mode-gremlin .mode-title, .mode-gremlin .mode-desc { color: white; }
.mode-gremlin .mode-icon-wrap {
    background: rgba(127,217,59,.2);
    color: #7fd93b;
    font-size: 26px;
}

        /* Disabled state */
        .mode-card.disabled {
            opacity: .45;
            cursor: not-allowed;
            filter: grayscale(.6);
            pointer-events: none;
        }
        .mode-lock-tag {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 2;
            padding: 5px 8px;
            border-radius: 999px;
            background: rgba(15, 23, 42, .82);
            color: #fff;
            font-size: 10px;
            font-weight: 800;
            line-height: 1.1;
        }

        .mode-icon-wrap {
            width: 56px; height: 56px; border-radius: 18px;
            display: flex; align-items: center; justify-content: center;
            font-size: 24px; flex-shrink: 0;
        }
        .mode-title {
            font-family: 'Lexend', sans-serif;
            font-size: 15px; font-weight: 800;
            letter-spacing: .3px;
        }
        .mode-desc {
            font-size: 11px; font-weight: 600;
            opacity: .8; line-height: 1.4;
        }
        .mode-badge {
            position: absolute; top: 10px; right: 10px;
            font-size: 9px; font-weight: 900;
            padding: 3px 8px; border-radius: 20px;
            background: rgba(255,255,255,.2);
            color: rgba(255,255,255,.9);
            text-transform: uppercase; letter-spacing: .5px;
        }

        /* Responsive */
        @media (max-width: 700px) {
            .mode-cards { grid-template-columns: repeat(2, 1fr); }
            .reviewer-container { padding: 28px 22px; }
        }
        @media (max-width: 420px) {
            .mode-cards { grid-template-columns: 1fr; }
        }

        /* ── Error ── */
        .error-msg {
            color: #c53030; background: #fff5f5;
            padding: 20px; border-radius: 15px;
            border: 1px solid #feb2b2; text-align: center;
        }

        /* ── Text-to-Speech controls ── */
        .tts-bar {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 16px;
            flex-wrap: wrap;
        }
        .tts-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: white;
            border: 1.5px solid var(--primary);
            color: var(--primary);
            padding: 9px 20px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 700;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            transition: .2s;
        }
        .tts-btn:hover { background: var(--primary); color: white; }
        .tts-btn.tts-stop { border-color: #ef4444; color: #ef4444; }
        .tts-btn.tts-stop:hover { background: #ef4444; color: white; }

        .tts-highlight {
            background: #fff9db !important;
            box-shadow: 0 0 0 2px #f5c842 !important;
            border-radius: 10px;
            transition: background .3s, box-shadow .3s;
        }
    </style>
</head>
<body>

<div class="reviewer-container">

    <!-- Header -->
    <div class="header-section">
        <span class="badge"><i class="fa-solid fa-circle-check"></i> Study Material Ready</span>
        <h1><i class="fa-solid fa-scroll"></i> <?php echo htmlspecialchars($title); ?></h1>
        <p class="header-sub">Review your quest briefing below, then pick how you want to train.</p>

        <?php if ($summary && !isset($summary['error'])): ?>
        <div class="quest-hud">
            <span class="hud-chip"><i class="fa-solid <?php echo $track_icon; ?>"></i> <?php echo htmlspecialchars($track_label); ?> Track</span>
            <span class="hud-chip diff-<?php echo htmlspecialchars($difficulty); ?>"><i class="fa-solid fa-fire"></i> <?php echo htmlspecialchars($diff_label); ?> Difficulty</span>
            <span class="hud-chip"><i class="fa-solid fa-layer-group"></i> <?php echo $total_q; ?> Questions Loaded</span>
        </div>
        <div class="tts-bar">
            <button type="button" class="tts-btn" id="ttsToggleBtn" onclick="toggleReadAloud()">
                <i class="fa-solid fa-volume-high"></i> <span id="ttsBtnLabel">Read Summary Aloud</span>
            </button>
            <button type="button" class="tts-btn tts-stop" id="ttsStopBtn" onclick="stopReadAloud()" style="display:none;">
                <i class="fa-solid fa-stop"></i> Stop
            </button>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($summary && !isset($summary['error'])): ?>

        <!-- Quick Overview -->
        <div class="section-title"><span class="step-dot">1</span> Quick Overview</div>
        <div class="overview-box" id="ttsOverview">
            <?php echo is_array($summary['overview'])
                ? htmlspecialchars(implode(' ', $summary['overview']))
                : htmlspecialchars($summary['overview']); ?>
        </div>

        <!-- Key Terminologies -->
        <div class="section-title"><span class="step-dot">2</span> Key Terminologies</div>
        <?php
        if (!empty($summary['key_terminologies'])) {
            foreach ($summary['key_terminologies'] as $term) {
                $termStr  = is_array($term) ? implode(': ', $term) : $term;
                $parts    = explode(':', $termStr, 2);
                $dispTerm = trim($parts[0] ?? 'Term');
                $dispDef  = trim($parts[1] ?? '');
                echo '<div class="term-card"><p style="margin:0;">
                    <strong style="color:#854d0e;">'.htmlspecialchars($dispTerm).':</strong> '.
                    htmlspecialchars($dispDef).'</p></div>';
            }
        } else {
            echo '<p style="color:var(--text-gray);font-size:14px;">No terminologies available.</p>';
        }
        ?>

        <!-- Main Study Points -->
        <div class="section-title"><span class="step-dot">3</span> Main Study Points</div>
        <?php
        if (!empty($summary['main_study_points'])) {
            foreach ($summary['main_study_points'] as $point) {
                $pointStr = is_array($point) ? implode(' ', $point) : $point;
                echo '<div class="point-item">'.htmlspecialchars($pointStr).'</div>';
            }
        } else {
            echo '<p style="color:var(--text-gray);font-size:14px;">No study points available.</p>';
        }
        ?>

        <div class="ready-banner">
            <i class="fa-solid fa-shield-halved"></i>
            <div>
                <strong>Briefing complete — you're ready for the quest.</strong>
                <span>Skim the overview and terms above one more time, then choose your game mode below.</span>
            </div>
        </div>

        <!-- ════════════════════════════════════════
             GAME MODE SELECTOR — split by quiz track
        ════════════════════════════════════════ -->
        <div class="game-mode-section">
            <div class="game-mode-heading">
                <h2><i class="fa-solid fa-gamepad" style="color:var(--primary);"></i> Choose Your <?php echo htmlspecialchars($track_label); ?> Game Mode</h2>
                <p>Same <?php echo $total_q; ?> questions — five different ways to master the material.</p>
            </div>

            <div class="mode-cards">

                <!-- Classic Quest — label follows the active track -->
                <form action="quiz.php" method="POST">
                    <input type="hidden" name="quiz_id" value="<?php echo session_id(); ?>">
                    <button type="submit" class="mode-card mode-classic" style="width:100%;cursor:pointer;border:none;">
                        <span class="mode-badge">Original</span>
                        <div class="mode-icon-wrap"><i class="fa-solid fa-brain"></i></div>
                        <div class="mode-title">Classic Quest <?php echo $is_fib ? '(Fill in the Blanks)' : '(Multiple Choice)'; ?></div>
                        <div class="mode-desc">Answer questions one by one against the timer. Power-ups included!</div>
                    </button>
                </form>

                <?php if (!$is_fib): ?>
                <!-- ═══ MULTIPLE CHOICE TRACK ═══ -->

                <!-- Bubble Pop -->
                <a href="game_bubble_pop.php"
                   class="mode-card mode-bubble <?php echo $modeCanUse['bubble_pop'] ? '' : 'disabled'; ?>">
                    <?php if (!$modeCanUse['bubble_pop']): ?>
                    <span class="mode-lock-tag"><i class="fa-solid fa-lock"></i> <?php echo $modeLockText('bubble_pop'); ?></span>
                    <?php endif; ?>
                    <div class="mode-icon-wrap"><i class="fa-solid fa-soap"></i></div>
                    <div class="mode-title">Bubble Pop</div>
                    <div class="mode-desc">Pop the correct answer bubble before it floats away! 3 lives, beat the clock.</div>
                </a>

                <!-- Masked Impostor -->
                <a href="game_masked_imposter.php"
                   class="mode-card mode-masquerade <?php echo $modeCanUse['masked_impostor'] ? '' : 'disabled'; ?>">
                    <?php if (!$modeCanUse['masked_impostor']): ?>
                    <span class="mode-lock-tag"><i class="fa-solid fa-lock"></i> <?php echo $modeLockText('masked_impostor', $can_masquerade); ?></span>
                    <?php endif; ?>
                    <div class="mode-icon-wrap"><i class="fa-solid fa-masks-theater"></i></div>
                    <div class="mode-title">Masked Impostor</div>
                    <div class="mode-desc">Correct answers reveal clues. Unmask the impostor before your disguises run out!</div>
                </a>

                <!-- Bomb Toss -->
                <a href="game_bomb_toss.php"
                   class="mode-card mode-bomb <?php echo $modeCanUse['bomb_toss'] ? '' : 'disabled'; ?>">
                    <?php if (!$modeCanUse['bomb_toss']): ?>
                    <span class="mode-lock-tag"><i class="fa-solid fa-lock"></i> <?php echo $modeLockText('bomb_toss', $can_masquerade); ?></span>
                    <?php endif; ?>
                    <div class="mode-icon-wrap"><i class="fa-solid fa-bomb"></i></div>
                    <div class="mode-title">Bomb Toss</div>
                    <div class="mode-desc">One option at a time — Lock In or Toss before the fuse runs out!</div>
                </a>

                <!-- Hit or Fold -->
                <a href="game_blackjack_dealer.php"
                   class="mode-card mode-hitfold <?php echo $modeCanUse['hit_or_fold'] ? '' : 'disabled'; ?>">
                    <?php if (!$modeCanUse['hit_or_fold']): ?>
                    <span class="mode-lock-tag"><i class="fa-solid fa-lock"></i> <?php echo $modeLockText('hit_or_fold', $can_masquerade); ?></span>
                    <?php endif; ?>
                    <div class="mode-icon-wrap">🃏</div>
                    <div class="mode-title">Hit or Fold</div>
                    <div class="mode-desc">Flip your choices one by one. HIT to lock in, FOLD to burn — but beware the Blind Fold!</div>
                </a>

                <?php else: ?>
                <!-- ═══ FILL IN THE BLANKS TRACK ═══ -->

                <!-- Word Scramble -->
                <a href="game_word_scramble.php"
                   class="mode-card mode-scramble <?php echo $modeCanUse['word_scramble'] ? '' : 'disabled'; ?>">
                    <?php if (!$modeCanUse['word_scramble']): ?>
                    <span class="mode-lock-tag"><i class="fa-solid fa-lock"></i> <?php echo $modeLockText('word_scramble', $can_scramble); ?></span>
                    <?php endif; ?>
                    <div class="mode-icon-wrap"><i class="fa-solid fa-shuffle"></i></div>
                    <div class="mode-title">Word Scramble</div>
                    <div class="mode-desc">Drag scrambled letter tiles into the right order. Tests recall, not just recognition.</div>
                </a>

                <!-- The Decoy Printer -->
                <a href="game_decoy_printer.php"
                   class="mode-card mode-decoy <?php echo $modeCanUse['decoy_printer'] ? '' : 'disabled'; ?>">
                    <?php if (!$modeCanUse['decoy_printer']): ?>
                    <span class="mode-lock-tag"><i class="fa-solid fa-lock"></i> <?php echo $modeLockText('decoy_printer', $can_decoy); ?></span>
                    <?php endif; ?>
                    <div class="mode-icon-wrap">🖨️</div>
                    <div class="mode-title">The Decoy Printer</div>
                    <div class="mode-desc">Type the missing word while a Ghost Typist prints a decoy into the same slots. Trust muscle memory, not your eyes!</div>
                </a>

                <!-- Typo Bomb -->
                <a href="game_typo_bomb.php"
                   class="mode-card mode-typobomb <?php echo $modeCanUse['typo_bomb'] ? '' : 'disabled'; ?>">
                    <?php if (!$modeCanUse['typo_bomb']): ?>
                    <span class="mode-lock-tag"><i class="fa-solid fa-lock"></i> <?php echo $modeLockText('typo_bomb', $can_typo); ?></span>
                    <?php endif; ?>
                    <div class="mode-icon-wrap">💣</div>
                    <div class="mode-title">Typo Bomb</div>
                    <div class="mode-desc">Your keyboard is sabotaged! Type the answer while junk letters sneak in — clean it up before the bomb blows.</div>
                </a>

                <!-- The Typo Gremlin -->
                <a href="game_typo_gremlin.php"
                   class="mode-card mode-gremlin <?php echo $modeCanUse['typo_gremlin'] ? '' : 'disabled'; ?>">
                    <?php if (!$modeCanUse['typo_gremlin']): ?>
                    <span class="mode-lock-tag"><i class="fa-solid fa-lock"></i> <?php echo $modeLockText('typo_gremlin', $can_gremlin); ?></span>
                    <?php endif; ?>
                    <div class="mode-icon-wrap">👺</div>
                    <div class="mode-title">The Typo Gremlin</div>
                    <div class="mode-desc">A gremlin wakes up the moment you start typing and swaps your keyboard's keys. Adapt on the fly, or smash him to reset it!</div>
                </a>

                <?php endif; ?>

            </div>

            <?php
                $show_notes = $is_fib
                    ? (!$can_scramble || !$can_decoy || !$can_typo || !$can_gremlin)
                    : (!$can_masquerade);
            ?>
            <?php if ($show_notes): ?>
            <div style="margin-top:14px;text-align:center;">
                <?php if (!$is_fib && !$can_masquerade): ?>
                <p style="font-size:12px;color:var(--text-gray);">
                    <i class="fa-solid fa-circle-info"></i>
                    Masked Impostor needs at least 3 <em>Multiple Choice</em> questions to unlock.
                </p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <div class="error-msg">
            <i class="fa-solid fa-circle-exclamation"></i>
            <strong>System Notice:</strong>
            <?php echo isset($summary['error'])
                ? htmlspecialchars($summary['error'])
                : "Summarization failed. Please check your Python engine."; ?>
        </div>
        <a href="quizzes.php" style="display:block;text-align:center;margin-top:20px;color:var(--primary);">
            Go back and try again
        </a>
    <?php endif; ?>

</div>

<script>
(function() {
    let ttsQueue    = [];
    let ttsIndex    = 0;
    let isPaused    = false;
    let isReading   = false;
    let isStopped   = false; // guards against cancel() still firing onend in some browsers

    function buildQueue() {
        const blocks = [];
        const overview = document.getElementById('ttsOverview');
        if (overview) blocks.push({ el: overview, text: overview.innerText.trim() });

        document.querySelectorAll('.term-card').forEach(el => {
            blocks.push({ el, text: el.innerText.trim() });
        });

        document.querySelectorAll('.point-item').forEach(el => {
            blocks.push({ el, text: el.innerText.trim() });
        });

        return blocks.filter(b => b.text.length > 0);
    }

    function clearHighlights() {
        document.querySelectorAll('.tts-highlight').forEach(el => el.classList.remove('tts-highlight'));
    }

    function speakNext() {
        if (isStopped) return; // don't continue the queue after Stop was pressed

        clearHighlights();
        if (ttsIndex >= ttsQueue.length) {
            finishReading();
            return;
        }
        const block = ttsQueue[ttsIndex];
        block.el.classList.add('tts-highlight');
        block.el.scrollIntoView({ behavior: 'smooth', block: 'center' });

        const utterance = new SpeechSynthesisUtterance(block.text);
        utterance.rate  = 1;
        utterance.pitch = 1;
        utterance.onend = () => {
            if (isStopped) return;
            ttsIndex++;
            speakNext();
        };
        utterance.onerror = () => {
            if (isStopped) return;
            ttsIndex++;
            speakNext();
        };
        window.speechSynthesis.speak(utterance);
    }

    function startReading() {
        if (!('speechSynthesis' in window)) {
            pqNotify('Sorry, your browser does not support text-to-speech.', 'error');
            return;
        }
        ttsQueue  = buildQueue();
        if (ttsQueue.length === 0) return;
        ttsIndex  = 0;
        isReading = true;
        isPaused  = false;
        isStopped = false;
        document.getElementById('ttsBtnLabel').innerText = 'Pause';
        document.querySelector('#ttsToggleBtn i').className = 'fa-solid fa-pause';
        document.getElementById('ttsStopBtn').style.display = 'inline-flex';
        speakNext();
    }

    function pauseReading() {
        window.speechSynthesis.pause();
        isPaused = true;
        document.getElementById('ttsBtnLabel').innerText = 'Resume';
        document.querySelector('#ttsToggleBtn i').className = 'fa-solid fa-play';
    }

    function resumeReading() {
        window.speechSynthesis.resume();
        isPaused = false;
        document.getElementById('ttsBtnLabel').innerText = 'Pause';
        document.querySelector('#ttsToggleBtn i').className = 'fa-solid fa-pause';
    }

    function finishReading() {
        isReading = false;
        isPaused  = false;
        clearHighlights();
        document.getElementById('ttsBtnLabel').innerText = 'Read Summary Aloud';
        document.querySelector('#ttsToggleBtn i').className = 'fa-solid fa-volume-high';
        document.getElementById('ttsStopBtn').style.display = 'none';
    }

    window.toggleReadAloud = function() {
        if (!isReading)      startReading();
        else if (isPaused)   resumeReading();
        else                 pauseReading();
    };

    window.stopReadAloud = function() {
        isStopped = true;              // set BEFORE cancel() so any late onend/onerror is ignored
        window.speechSynthesis.cancel();
        finishReading();
    };

    // Stop speech if the student navigates away (e.g. clicks a game mode card)
    window.addEventListener('beforeunload', () => {
        isStopped = true;
        window.speechSynthesis.cancel();
    });
})();
</script>
</body>
</html>