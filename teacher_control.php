<?php
require_once __DIR__ . '/synchro_identity.php';
// teacher_control.php
// Teacher AJAX actions: next_question, show_results, show_leaderboard, finish_quiz

header('Content-Type: application/json');
$conn = new mysqli("localhost", "root", "", "pinnaquest_db");
ensureSynchroIdentitySchema($conn);

if ($conn->connect_error) {
    echo json_encode(["success" => false, "error" => "DB connection failed"]);
    exit();
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$session_id = intval($_POST['session_id'] ?? $_GET['session_id'] ?? 0);

if (!$session_id) {
    echo json_encode(["success" => false, "error" => "No session ID"]);
    exit();
}

// Get current state
$state_res = $conn->query("SELECT * FROM synchro_game_state WHERE session_id = $session_id");
$state = $state_res ? $state_res->fetch_assoc() : null;

// Get total questions
$total_res = $conn->query("SELECT COUNT(*) as cnt FROM synchro_questions WHERE session_id = $session_id");
$total_questions = intval($total_res->fetch_assoc()['cnt']);

/**
 * Finalize all answers for the active question exactly once.
 *
 * submit_answer.php stores answers with negative points_earned while the
 * question is live. A negative value is the pending marker; this function
 * converts those rows into final correctness/points and updates scores only
 * when the teacher reveals results.
 */
function finalizeQuestionAnswers(mysqli $conn, int $sessionId, int $questionId): array
{
    $qRes = $conn->query(
        "SELECT correct_answer, question_type, time_limit
         FROM synchro_questions
         WHERE id = $questionId AND session_id = $sessionId
         LIMIT 1"
    );
    $question = $qRes ? $qRes->fetch_assoc() : null;
    if (!$question) {
        return ['finalized' => 0, 'error' => 'Question not found'];
    }

    $correctAnswer = strtoupper(trim((string) $question['correct_answer']));
    $isIdentification = $question['question_type'] === 'identification';
    $timeLimitMs = max(1, (int) $question['time_limit'] * 1000);

    $pendingRes = $conn->query(
        "SELECT id, participant_nickname, answer_given, time_taken_ms, points_earned
         FROM synchro_answers
         WHERE session_id = $sessionId
           AND question_id = $questionId
           AND points_earned < 0
         ORDER BY id ASC"
    );

    $finalized = 0;
    while ($answer = $pendingRes ? $pendingRes->fetch_assoc() : null) {
        $answerId = (int) $answer['id'];
        $nickname = $conn->real_escape_string($answer['participant_nickname']);
        $given = strtoupper(trim((string) $answer['answer_given']));
        $marker = (int) $answer['points_earned'];

        $isSkip = $given === 'SKIP' || $marker === -3;
        $isCorrect = !$isSkip && (
            $isIdentification
                ? strtolower($given) === strtolower($correctAnswer)
                : $given === $correctAnswer
        );

        $scoreRes = $conn->query(
            "SELECT streak FROM synchro_scores
             WHERE session_id = $sessionId AND nickname = '$nickname'
             LIMIT 1"
        );
        $currentStreak = $scoreRes ? (int) (($scoreRes->fetch_assoc()['streak'] ?? 0)) : 0;

        // Markers: -1 normal, -2 double points, -3 shield skip,
        // -4 streak saver, -5 double points + streak saver.
        $doublePoints = in_array($marker, [-2, -5], true);
        $streakSaver = in_array($marker, [-4, -5], true) || $isSkip;

        $basePoints = 0;
        $speedBonus = 0;
        $streakBonus = 0;
        $newStreak = $currentStreak;

        if ($isCorrect) {
            $basePoints = 1000;
            $timeRatio = max(0, 1 - ((int) $answer['time_taken_ms'] / $timeLimitMs));
            $speedBonus = (int) (500 * $timeRatio);
            $newStreak = $currentStreak + 1;
            $streakBonus = min(500, $newStreak * 100);
        } elseif (!$streakSaver) {
            $newStreak = 0;
        }

        $totalPoints = $basePoints + $speedBonus + $streakBonus;
        if ($doublePoints && $isCorrect) {
            $totalPoints *= 2;
        }

        $correctInt = $isCorrect ? 1 : 0;
        $updated = $conn->query(
            "UPDATE synchro_answers
             SET is_correct = $correctInt, points_earned = $totalPoints
             WHERE id = $answerId AND points_earned < 0"
        );

        if (!$updated || $conn->affected_rows !== 1) {
            continue;
        }

        $scoreRes = $conn->query(
            "SELECT id FROM synchro_scores
             WHERE session_id = $sessionId AND nickname = '$nickname'
             LIMIT 1"
        );
        if ($scoreRes && $scoreRes->num_rows > 0) {
            $conn->query(
                "UPDATE synchro_scores
                 SET total_score = total_score + $totalPoints,
                     correct_answers = correct_answers + $correctInt,
                     streak = $newStreak
                 WHERE session_id = $sessionId AND nickname = '$nickname'"
            );
        } else {
            $conn->query(
                "INSERT INTO synchro_scores
                    (session_id, nickname, user_id, total_score, correct_answers, streak)
                 VALUES
                    ($sessionId, '$nickname', NULL, $totalPoints, $correctInt, $newStreak)"
            );
        }

        $finalized++;
    }

    return ['finalized' => $finalized];
}

switch ($action) {
    case 'start_question':
        // Next Question is pressed from the results screen. Leaderboard is
        // also supported when the teacher chooses to show it first.
        if ($state && !in_array($state['phase'], ['lobby', 'results', 'leaderboard'], true)) {
            echo json_encode([
                "success" => false,
                "error" => "Finish or reveal the current question before starting another",
            ]);
            break;
        }

        // Move to next question (or first question)
        $next_q = $state ? intval($state['current_question']) + 1 : 1;
        
        if ($next_q > $total_questions) {
            // No more questions - finish
            if ($state) {
                $conn->query("UPDATE synchro_game_state SET phase = 'finished' WHERE session_id = $session_id");
                $conn->query("UPDATE synchro_sessions SET status = 'finished' WHERE id = $session_id");
            }
            echo json_encode(["success" => true, "phase" => "finished"]);
            break;
        }
        
        $now = date('Y-m-d H:i:s');
        if ($state) {
            $conn->query("UPDATE synchro_game_state SET current_question = $next_q, phase = 'question', question_started_at = '$now' WHERE session_id = $session_id");
        } else {
            $conn->query("INSERT INTO synchro_game_state (session_id, current_question, phase, question_started_at) VALUES ($session_id, $next_q, 'question', '$now')");
        }
        
        // Update session status
        $conn->query("UPDATE synchro_sessions SET status = 'started' WHERE id = $session_id");
        
        // Initialize scores for all participants (if not already done)
        $parts_res = $conn->query("SELECT nickname, avatar_key FROM synchro_participants WHERE session_id = $session_id");
        while ($part = $parts_res->fetch_assoc()) {
            $nick = $conn->real_escape_string($part['nickname']);
            $av = $conn->real_escape_string($part['avatar_key'] ?? '');
            $conn->query("INSERT IGNORE INTO synchro_scores (session_id, nickname, avatar_key) VALUES ($session_id, '$nick', '$av')");
        }
        
        echo json_encode(["success" => true, "phase" => "question", "question_index" => $next_q]);
        break;
        
    case 'show_results':
        if ($state && $state['phase'] === 'question') {
            $current_question = (int) $state['current_question'];
            $qRes = $conn->query(
                "SELECT id FROM synchro_questions
                 WHERE session_id = $session_id
                   AND question_order = $current_question
                 LIMIT 1"
            );
            $question = $qRes ? $qRes->fetch_assoc() : null;

            if (!$question) {
                echo json_encode(["success" => false, "error" => "Active question not found"]);
                break;
            }

            $finalized = finalizeQuestionAnswers($conn, $session_id, (int) $question['id']);
            $conn->query(
                "UPDATE synchro_game_state
                 SET phase = 'results'
                 WHERE session_id = $session_id"
            );

            echo json_encode([
                "success" => true,
                "phase" => "results",
                "finalized_answers" => $finalized['finalized'],
            ]);
        } elseif ($state && $state['phase'] === 'results') {
            echo json_encode(["success" => true, "phase" => "results", "finalized_answers" => 0]);
        } else {
            echo json_encode(["success" => false, "error" => "No active question to reveal"]);
        }
        break;
        
    case 'show_leaderboard':
        if ($state && $state['phase'] === 'results') {
            $conn->query("UPDATE synchro_game_state SET phase = 'leaderboard' WHERE session_id = $session_id");
            echo json_encode(["success" => true, "phase" => "leaderboard"]);
        } elseif ($state && $state['phase'] === 'leaderboard') {
            echo json_encode(["success" => true, "phase" => "leaderboard"]);
        } else {
            echo json_encode(["success" => false, "error" => "Show results before opening the leaderboard"]);
        }
        break;
        
    case 'finish_quiz':
        $conn->query("UPDATE synchro_game_state SET phase = 'finished' WHERE session_id = $session_id");
        $conn->query("UPDATE synchro_sessions SET status = 'finished' WHERE id = $session_id");
        echo json_encode(["success" => true, "phase" => "finished"]);
        break;
        
    case 'get_state':
        // Just return state info
        $q_idx = $state ? intval($state['current_question']) : 0;
        $phase = $state ? $state['phase'] : 'lobby';
        echo json_encode([
            "success" => true,
            "phase" => $phase,
            "current_question" => $q_idx,
            "total_questions" => $total_questions
        ]);
        break;
        
    default:
        echo json_encode(["success" => false, "error" => "Unknown action: $action"]);
}

$conn->close();
?>