<?php
session_start();
require_once __DIR__ . '/config.local.php';
// Sa loob ng forge_logic.php


/**
 * Redirect to $target with a toast notice instead of a browser alert().
 * quizzes.php / teacher_quizzes.php read ?notice= / ?notice_type= and
 * render it as an in-app toast instead of a native "localhost says" popup.
 */
function pinnaquest_redirect_notice(string $target, string $message, string $type = 'error'): void
{
    header('Location: ' . $target . '?notice=' . rawurlencode($message) . '&notice_type=' . rawurlencode($type));
    exit();
}


function pinnaquest_quiz_redirect_target(): string
{
    return (isset($_SESSION['role']) && $_SESSION['role'] === 'teacher')
        ? 'teacher_quizzes.php'
        : 'quizzes.php';
}


if (isset($_POST['timer_mins'])) {
    $_SESSION['quiz_time_limit'] = intval($_POST['timer_mins']);
}


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    unset($_SESSION['solo_retake_of'], $_SESSION['solo_result_saved'], $_SESSION['solo_result_response']);


    $title       = $_POST['quest_title'] ?? "Untitled Quest";
    $filePath    = $_POST['source_material'] ?? "";
    $difficulty  = $_POST['difficulty'] ?? "easy";
    $quiz_type   = $_POST['quiz_type'] ?? "multiple_choice";
    $item_count  = max(5, min(30, (int)($_POST['item_count'] ?? 10)));
    $timer       = (int)($_POST['timer_mins'] ?? 0);


    if (empty($filePath) || !file_exists($filePath)) {
        pinnaquest_redirect_notice(
            pinnaquest_quiz_redirect_target(),
            'Selected source material could not be found on the server. Please pick a different file and try again.',
            'error'
        );
    }


    $pythonPath  = '"C:\Users\DREAM PC\AppData\Local\Programs\Python\Python311\python.exe"';
    $escapedPath = escapeshellarg($filePath);


    // Command with all arguments
    $command = "chcp 65001 && $pythonPath engine.py $escapedPath \"$difficulty\" \"$quiz_type\" $item_count 2>&1";
    $output = shell_exec($command);


    // BOMB-PROOF JSON EXTRACTION
    $jsonStart = strpos($output, '{');
    $jsonEnd   = strrpos($output, '}');
    $data = null;
    if ($jsonStart !== false && $jsonEnd !== false) {
        $jsonOnly = substr($output, $jsonStart, ($jsonEnd - $jsonStart) + 1);
        $data = json_decode($jsonOnly, true);
    }


    if ($data && !isset($data['error'])) {
        // Save to Session
        $_SESSION['summary'] = [
            'overview'          => $data['overview'] ?? "No overview generated.",
            'key_terminologies' => $data['key_terminologies'] ?? [],
            'main_study_points' => $data['main_study_points'] ?? []
        ];


        // Ito ang critical fix para sa quiz.php
        $quiz_questions = $data['quiz'] ?? [];

        // Never start a partial quiz. The requested count must be preserved
        // from generation through every game mode.
        if (!is_array($quiz_questions) || count($quiz_questions) < $item_count) {
            $generated_count = is_array($quiz_questions) ? count($quiz_questions) : 0;
            pinnaquest_redirect_notice(
                pinnaquest_quiz_redirect_target(),
                "Could not generate exactly {$item_count} questions from this material (only {$generated_count} usable questions were returned). Please choose a material with more distinct content and try again.",
                'error'
            );
        }
        if (count($quiz_questions) > $item_count) {
            $quiz_questions = array_slice($quiz_questions, 0, $item_count);
        }


        // ── FINAL SAFETY NET: never let an empty/placeholder quiz through ──
        // engine.py already validates content before generating, but this
        // guards against any edge case (e.g. a malformed AI reply) that
        // could otherwise let a fabricated/empty quiz reach the student
        // or teacher.
        if (empty($quiz_questions) || !is_array($quiz_questions)) {
            pinnaquest_redirect_notice(
                pinnaquest_quiz_redirect_target(),
                'The uploaded file does not contain enough usable content to generate a quiz.',
                'error'
            );
        }


        // ── SHUFFLE: question order (kada generate, iba ang sunod) ──
        if (!empty($quiz_questions) && is_array($quiz_questions)) {
            shuffle($quiz_questions);
        }


        // ── SHUFFLE: multiple-choice options (at i-remap ang answer_index) ──
        $normalized_quiz_type = strtolower($quiz_type);
        $is_mc = stripos($normalized_quiz_type, 'identif') === false
            && stripos($normalized_quiz_type, 'fill') === false;
        foreach ($quiz_questions as &$q) {
            if (!$is_mc) continue;
            if (!empty($q['options']) && is_array($q['options'])) {
                $opts    = array_values($q['options']);
                $ansIdx  = intval($q['answer_index'] ?? 0);
                $correct = $opts[$ansIdx] ?? null;


                shuffle($opts);


                // i-update ang options sa shuffled order
                $q['options'] = $opts;


                // i-remap ang answer_index sa bagong posisyon ng tamang sagot
                if ($correct !== null) {
                    $newIdx = array_search($correct, $opts, true);
                    if ($newIdx !== false) {
                        $q['answer_index'] = intval($newIdx);
                    }
                }


                // (opsyonal) kung may 'answer' na text field, panatilihing tama
                if (isset($q['answer'])) {
                    $q['answer'] = $correct;
                }
            }
        }
        unset($q);


        $_SESSION['questions'] = $quiz_questions;
        unset($_SESSION['solo_quiz_order_initialized']);


        $_SESSION['quiz_data'] = [
            'questions'   => $quiz_questions,
            'type'        => $quiz_type,
            'difficulty'  => $difficulty,
            'timer'       => $timer,
            'title'       => $title
        ];
        $_SESSION['solo_attempt_token'] = bin2hex(random_bytes(16));
        $_SESSION['title'] = $title;
        header("Location: pre_quiz_summary.php");
        exit();
    } else {
        // ── engine.py rejected the upload OR another failure occurred ──
        // If engine.py flagged this as a content-validation failure (empty
        // PDF or insufficient usable text), show the exact reason it gave
        // instead of a raw debug dump. This applies identically to both
        // student (quizzes.php) and teacher (teacher_quizzes.php) accounts,
        // since both submit to this same forge_logic.php endpoint.
        $is_validation_error = is_array($data) && !empty($data['validation_error']);


        $friendly_message = $is_validation_error && !empty($data['error'])
            ? $data['error']
            : "We couldn't generate a quiz from this material. Please try again or use a different file.";


        pinnaquest_redirect_notice(pinnaquest_quiz_redirect_target(), $friendly_message, 'error');
    }
}
?>
