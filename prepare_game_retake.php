<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please sign in again.']);
    exit();
}

$questions = $_SESSION['quiz_data']['questions'] ?? [];
if (!is_array($questions) || !$questions) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'The quiz is no longer available.']);
    exit();
}

$previousResultId = !empty($_SESSION['solo_result_saved'])
    ? (int) ($_SESSION['solo_result_response']['result_id'] ?? 0)
    : 0;

if ($previousResultId > 0) {
    $_SESSION['solo_retake_of'] = $previousResultId;
} else {
    unset($_SESSION['solo_retake_of']);
}

$_SESSION['solo_attempt_token'] = bin2hex(random_bytes(16));
unset(
    $_SESSION['solo_result_saved'],
    $_SESSION['solo_result_response'],
    $_SESSION['solo_quiz_order_initialized']
);

echo json_encode(['success' => true]);
