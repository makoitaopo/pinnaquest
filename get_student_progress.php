<?php
// get_student_progress.php
// Returns one student's SynchroQuiz attempt history in chronological order
// (Quiz 1, Quiz 2, Quiz 3, ...) plus summary stats, so the teacher can see
// how that student's score and accuracy moved from one quiz to the next.

header('Content-Type: application/json');
require_once __DIR__ . '/synchro_identity.php';
$conn = new mysqli("localhost", "root", "", "pinnaquest_db");

if ($conn->connect_error) {
    echo json_encode(["error" => "DB connection failed"]);
    exit();
}
ensureSynchroIdentitySchema($conn);

$userId = intval($_GET['user_id'] ?? 0);
$nickname = isset($_GET['nickname']) ? trim($_GET['nickname']) : '';
if ($userId <= 0 && $nickname === '') {
    echo json_encode(["error" => "Missing nickname"]);
    exit();
}
$nicknameEsc = $conn->real_escape_string($nickname);
$studentWhere = $userId > 0 ? "sc.user_id=$userId" : "sc.nickname='$nicknameEsc'";

$sql = "SELECT s.id, s.title, s.room_code, s.difficulty, s.quiz_type,
               s.item_count, s.created_at,
               sc.total_score, sc.correct_answers, sc.streak, sc.avatar_key
        FROM synchro_scores sc
        INNER JOIN synchro_sessions s ON s.id = sc.session_id
        WHERE $studentWhere AND s.status = 'finished'
        ORDER BY s.created_at ASC";

$result   = $conn->query($sql);
$attempts = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $itemCount = max(1, intval($row['item_count']));
        $correct   = intval($row['correct_answers']);
        $accuracy  = round(($correct / $itemCount) * 100);

        $attempts[] = [
            'session_id'      => intval($row['id']),
            'title'           => $row['title'],
            'room_code'       => $row['room_code'],
            'difficulty'      => $row['difficulty'],
            'quiz_type'       => $row['quiz_type'],
            'item_count'      => intval($row['item_count']),
            'created_at'      => $row['created_at'],
            'total_score'     => intval($row['total_score']),
            'correct_answers' => $correct,
            'accuracy'        => $accuracy,
            'streak'          => intval($row['streak']),
            'avatar_key'      => $row['avatar_key'],
        ];
    }
}

$totalQuizzes = count($attempts);
$avgScore     = 0;
$avgAccuracy  = 0;
$bestStreak   = 0;
$trend        = 'flat';

if ($totalQuizzes > 0) {
    $scores    = array_column($attempts, 'total_score');
    $accuracies = array_column($attempts, 'accuracy');
    $streaks   = array_column($attempts, 'streak');

    $avgScore    = round(array_sum($scores) / $totalQuizzes);
    $avgAccuracy = round(array_sum($accuracies) / $totalQuizzes);
    $bestStreak  = max($streaks);

    if ($totalQuizzes >= 2) {
        $first = $attempts[0]['accuracy'];
        $last  = $attempts[$totalQuizzes - 1]['accuracy'];
        if ($last > $first)      $trend = 'up';
        elseif ($last < $first)  $trend = 'down';
    }
}

echo json_encode([
    'user_id' => $userId,
    'nickname' => $nickname,
    'attempts' => $attempts,
    'summary'  => [
        'total_quizzes' => $totalQuizzes,
        'avg_score'     => $avgScore,
        'avg_accuracy'  => $avgAccuracy,
        'best_streak'   => $bestStreak,
        'trend'         => $trend,
    ],
]);
$conn->close();
?>