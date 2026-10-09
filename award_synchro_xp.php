<?php
// award_synchro_xp.php
// Called by student_quiz_game.php when the quiz ends.
// Finds the student's synchro score and awards proportional XP.

header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/xp_policy.php';
require_once __DIR__ . '/mission_progress.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false]);
    exit();
}

$conn       = new mysqli("localhost", "root", "", "pinnaquest_db");
$user_id    = intval($_SESSION['user_id']);
$session_id = intval($_POST['session_id'] ?? 0);
$nickname   = $conn->real_escape_string(trim($_POST['nickname'] ?? ''));

if (!$session_id || !$nickname) {
    echo json_encode(['success' => false, 'error' => 'Missing fields']);
    exit();
}

// ── Get this student's synchro score ─────────────────────────────
$score_res = $conn->query(
    "SELECT total_score, correct_answers, streak 
     FROM synchro_scores 
     WHERE session_id = $session_id AND nickname = '$nickname'"
);

if (!$score_res || $score_res->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Score not found']);
    exit();
}
$s = $score_res->fetch_assoc();

// ── XP formula for synchro ────────────────────────────────────────
// 15 XP per correct answer + participation bonus of 30
$xp_earned = (intval($s['correct_answers']) * 15) + 30;
// Streak bonus: 5 XP per streak point (max 50)
$xp_earned += min(50, intval($s['streak']) * 5);

// One completed session is one independent XP attempt. The shared ledger
// applies the 300 daily cap and blocks duplicate finish/reconnect requests.
$xp_earned = awardQuizXp($conn, $user_id, 'synchro', 'session:' . $session_id, $session_id, $xp_earned);

// ── Check achievements ─────────────────────────────────────────────
awardAchievementsForUser($conn, $user_id);

// ── Return new totals ─────────────────────────────────────────────
$totals = quizXpTotals($conn, $user_id);

echo json_encode([
    'success'      => true,
    'xp_earned'    => $xp_earned,
    'total_xp'     => $totals['total_xp'],
    'level'        => $totals['level'],
    'progress_pct' => $totals['progress_pct'],
]);

$conn->close();

// ── Local achievement helper (mirrors save_quiz_result.php) ───────
function awardAchievementsForUser($conn, $user_id) {
    pinnaquestSyncMissionAchievements($conn, $user_id);
}
?>