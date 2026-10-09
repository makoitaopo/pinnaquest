<?php
session_start();
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'teacher') {
    header('Location: loginpanel.php');
    exit();
}

require_once __DIR__ . '/config.local.php';
require_once __DIR__ . '/synchro_invite_support.php';
$conn = new mysqli("localhost", "root", "", "pinnaquest_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
ensureSynchroInviteSchema($conn);
$teacher_user_id = intval($_SESSION['user_id']);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title       = mysqli_real_escape_string($conn, $_POST['quest_title']);
    $material_id = intval($_POST['source_material']);
    $difficulty  = mysqli_real_escape_string($conn, $_POST['difficulty']);
    $quiz_type   = mysqli_real_escape_string($conn, $_POST['quiz_type']);
    $item_count  = max(5, min(30, intval($_POST['item_count'])));
    $timer       = intval($_POST['timer_mins']);

    // Get the file path of the selected material
    $mat_res = $conn->query("SELECT file_path FROM teacher_materials WHERE id = $material_id");
    if (!$mat_res || $mat_res->num_rows === 0) {
        die("Error: Material not found in database.");
    }
    $material = $mat_res->fetch_assoc();
    $file_path = $material['file_path'];

    if (!file_exists($file_path)) {
        die("Error: Material file not found on server at: " . htmlspecialchars($file_path));
    }

    // Generate Room Code
    $room_code = "PQ-" . str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

    // Insert the session into DB
    $sql = "INSERT INTO synchro_sessions (room_code, title, material_id, difficulty, quiz_type, item_count, timer_mins, status, created_at, teacher_user_id)
            VALUES ('$room_code', '$title', '$material_id', '$difficulty', '$quiz_type', '$item_count', '$timer', 'waiting', NOW(), $teacher_user_id)";
    if (!$conn->query($sql)) {
        die("Database Error: " . $conn->error);
    }
    $session_id = $conn->insert_id;

    $pythonPath  = '"C:\Users\DREAM PC\AppData\Local\Programs\Python\Python311\python.exe"';
    $escapedPath = escapeshellarg($file_path);
    $command = "chcp 65001 && $pythonPath engine.py $escapedPath \"$difficulty\" \"$quiz_type\" $item_count 2>&1";
    $output = shell_exec($command);

    // Extract JSON from output
    $jsonStart = strpos($output, '{');
    $jsonEnd   = strrpos($output, '}');
    $data = null;
    if ($jsonStart !== false && $jsonEnd !== false) {
        $jsonOnly = substr($output, $jsonStart, ($jsonEnd - $jsonStart) + 1);
        $data = json_decode($jsonOnly, true);
    }

    if ($data && !isset($data['error']) && isset($data['quiz'])) {
        // Save questions to synchro_questions table
        $questions = $data['quiz'];
        if (!is_array($questions) || count($questions) < $item_count) {
            error_log("PinnaQuest: Engine returned an incomplete quiz for session $session_id.");
            // Remove the just-created empty session; "cancelled" is not a
            // value supported by the existing status enum.
            $conn->query("DELETE FROM synchro_sessions WHERE id=$session_id");
            die("Quiz generation failed: the requested number of questions could not be generated from this material.");
        }
        if (count($questions) > $item_count) {
            $questions = array_slice($questions, 0, $item_count);
        }

        // ── SHUFFLE: question order (kada generate, iba ang sunod) ──
        shuffle($questions);

        $order = 0;
        $normalized_quiz_type = strtolower($quiz_type);
        $is_mc = stripos($normalized_quiz_type, 'identif') === false
            && stripos($normalized_quiz_type, 'fill') === false;
        $letters = ['A', 'B', 'C', 'D'];

        foreach ($questions as $q) {
            $order++;
            $question_text = $conn->real_escape_string($q['question'] ?? '');
            $q_type = $is_mc ? 'multiple_choice' : 'identification';

            if ($q_type === 'multiple_choice') {
                $options = $q['options'] ?? ['', '', '', ''];

                // ── SHUFFLE: multiple-choice options at i-remap ang answer_index ──
                $opts    = array_values($options);
                $ansIdx  = intval($q['answer_index'] ?? 0);
                $correct = $opts[$ansIdx] ?? null;

                shuffle($opts);

                if ($correct !== null) {
                    $found = array_search($correct, $opts, true);
                    if ($found !== false) $ansIdx = $found;
                }

                $opt_a = $conn->real_escape_string($opts[0] ?? '');
                $opt_b = $conn->real_escape_string($opts[1] ?? '');
                $opt_c = $conn->real_escape_string($opts[2] ?? '');
                $opt_d = $conn->real_escape_string($opts[3] ?? '');

                $correct_answer = $conn->real_escape_string($letters[$ansIdx] ?? 'A');
                $time_lim = 20;

                $q_sql = "INSERT INTO synchro_questions
                    (session_id, question_order, question_text, option_a, option_b, option_c, option_d, correct_answer, question_type, time_limit)
                    VALUES ($session_id, $order, '$question_text', '$opt_a', '$opt_b', '$opt_c', '$opt_d', '$correct_answer', 'multiple_choice', $time_lim)";
            } else {
                // Identification — fill in the blank style
                $correct_answer = $conn->real_escape_string($q['answer'] ?? '');
                // Clean question text (remove ____ for display, keep it as the question)
                $q_sql = "INSERT INTO synchro_questions
                    (session_id, question_order, question_text, option_a, option_b, option_c, option_d, correct_answer, question_type, time_limit)
                    VALUES ($session_id, $order, '$question_text', '', '', '', '', '$correct_answer', 'identification', 30)";
            }
            $conn->query($q_sql);
        }

        // Initialize game state
        $conn->query("INSERT INTO synchro_game_state (session_id, current_question, phase) VALUES ($session_id, 0, 'lobby') ON DUPLICATE KEY UPDATE phase='lobby', current_question=0");
    } else {
        
        error_log("PinnaQuest: Python Engine failed for session $session_id. Output: " . substr($output, 0, 500));
    }

    // Save session info and redirect to lobby
    $_SESSION['active_room_code'] = $room_code;
    header("Location: teacher_lobby.php?room=$room_code");
    exit();
}
?>
