<?php
session_start();
require_once __DIR__ . '/xp_policy.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: loginpanel.php');
    exit();
}

$conn = new mysqli("localhost", "root", "", "pinnaquest_db");
$userId = intval($_SESSION['user_id']);
$resultId = intval($_GET['result_id'] ?? 0);
ensureQuizXpSchema($conn);

$result = null;
$resultQuery = $conn->query(
    "SELECT id, quiz_title, total_questions, quiz_key
     FROM solo_quiz_results
     WHERE id = $resultId AND user_id = $userId
     LIMIT 1"
);
if ($resultQuery && $resultQuery->num_rows > 0) {
    $result = $resultQuery->fetch_assoc();
}

if (!$result) {
    header('Location: quizzes.php?error=retake_not_found');
    exit();
}

$questions = [];
$answers = $conn->query(
    "SELECT question_text, question_type, options, correct_answer
     FROM solo_quiz_answers
     WHERE result_id = $resultId
     ORDER BY question_number ASC"
);
if ($answers) {
    while ($row = $answers->fetch_assoc()) {
        $options = json_decode((string)($row['options'] ?? '[]'), true);
        $options = is_array($options) ? array_values($options) : [];
        $question = [
            'question' => $row['question_text'],
            'type' => $row['question_type'] ?: 'multiple_choice',
            'answer' => $row['correct_answer'],
        ];
        if ($question['type'] === 'multiple_choice' && count($options) > 0) {
            $question['options'] = $options;
            $question['answer_index'] = array_search($row['correct_answer'], $options, true);
            if ($question['answer_index'] === false) {
                $question['answer_index'] = 0;
            }
        }
        $questions[] = $question;
    }
}

if (!$questions) {
    header('Location: quizzes.php?error=retake_questions_not_found');
    exit();
}

$_SESSION['quiz_data'] = [
    'questions' => $questions,
    'type' => ($questions[0]['type'] ?? 'multiple_choice') === 'fill_blank'
        ? 'fill_blanks'
        : 'multiple_choice',
    'difficulty' => 'easy',
    'timer' => 0,
    'title' => $result['quiz_title'] ?: 'Solo Quiz',
];
$_SESSION['quiz_time_limit'] = 0;
$_SESSION['solo_retake_of'] = $resultId;
$_SESSION['solo_attempt_token'] = bin2hex(random_bytes(16));
unset($_SESSION['solo_quiz_order_initialized'], $_SESSION['solo_result_saved'], $_SESSION['solo_result_response']);

$conn->close();
header('Location: quiz.php?retake=1');
exit();