<?php
// get_student_list.php
// Returns the roster of students who have completed at least one finished
// SynchroQuiz session, with lightweight summary stats for the roster panel
// in student_progress_report.php.

header('Content-Type: application/json');
require_once __DIR__ . '/synchro_identity.php';
$conn = new mysqli("localhost", "root", "", "pinnaquest_db");

if ($conn->connect_error) {
    echo json_encode(["error" => "DB connection failed"]);
    exit();
}
ensureSynchroIdentitySchema($conn);

$search    = isset($_GET['search']) ? trim($_GET['search']) : '';
$searchSql = '';
if ($search !== '') {
    $searchEsc = $conn->real_escape_string($search);
    $searchSql = "AND sc.nickname LIKE '%$searchEsc%'";
}

// One row per student (nickname), aggregated across every finished session
// they have a score row for. avatar_key uses the most recently seen value
// so the roster shows their latest chosen avatar.
$sql = "SELECT sc.user_id,
               sc.nickname,
               SUBSTRING_INDEX(GROUP_CONCAT(sc.avatar_key ORDER BY s.created_at DESC), ',', 1) AS avatar_key,
               COUNT(DISTINCT sc.session_id) AS quiz_count,
               MAX(s.created_at) AS last_active,
               AVG(sc.total_score) AS avg_score,
               AVG(CASE WHEN s.item_count > 0
                        THEN (sc.correct_answers / s.item_count) * 100
                        ELSE 0 END) AS avg_accuracy
        FROM synchro_scores sc
        INNER JOIN synchro_sessions s ON s.id = sc.session_id
        WHERE s.status = 'finished' $searchSql
        GROUP BY sc.user_id, sc.nickname
        ORDER BY last_active DESC";

$result   = $conn->query($sql);
$students = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $students[] = [
            'user_id'      => intval($row['user_id']),
            'nickname'     => $row['nickname'],
            'avatar_key'   => $row['avatar_key'],
            'quiz_count'   => intval($row['quiz_count']),
            'avg_score'    => round(floatval($row['avg_score'])),
            'avg_accuracy' => round(floatval($row['avg_accuracy'])),
            'last_active'  => $row['last_active'],
        ];
    }
}

echo json_encode(['students' => $students]);
$conn->close();
?>