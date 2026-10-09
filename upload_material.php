<?php
// upload_material.php — Student material upload with subject whitelist
// validation + AI content validation (checks if the PDF content
// actually matches the selected subject).

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: loginpanel.php');
    exit();
}
$user_id = intval($_SESSION['user_id']);

/**
 * Redirect to $target with a toast notice instead of a browser alert().
 * The receiving page (materials.php) reads ?notice= / ?notice_type=
 * and renders it as an in-app toast.
 */
function pinnaquest_redirect_notice(string $target, string $message, string $type = 'success'): void
{
    header('Location: ' . $target . '?notice=' . rawurlencode($message) . '&notice_type=' . rawurlencode($type));
    exit();
}

$conn = new mysqli("localhost", "root", "", "pinnaquest_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ── Allowed subjects whitelist ─────────────────────────────────────
$ALLOWED_SUBJECTS = [
    "Readings in Philippine History",
    "Understanding the Self",
    "Art Appreciation",
    "Physical Education",
    "Science and Development of Reading",
];

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Detect PHP-level upload failure (post_max_size exceeded)
    if (empty($_FILES) && empty($_POST)) {
        pinnaquest_redirect_notice(
            'materials.php',
            'Your PDF file exceeds the server upload limit.',
            'error'
        );
    }

    // Detect individual file upload error codes
    $upload_error = $_FILES["material_file"]["error"] ?? -1;
    if ($upload_error === UPLOAD_ERR_INI_SIZE || $upload_error === UPLOAD_ERR_FORM_SIZE) {
        pinnaquest_redirect_notice(
            'materials.php',
            'The uploaded PDF exceeds the maximum allowed file size.',
            'error'
        );
    }
    if ($upload_error === UPLOAD_ERR_NO_FILE) {
        pinnaquest_redirect_notice('materials.php', 'Please choose a PDF file before uploading.', 'error');
    }

    $title = trim($_POST['title'] ?? '');
    $display_name = trim($_POST['display_name'] ?? '');

    // ── Server-side subject validation ─────────────────────────────
    if (!in_array($title, $ALLOWED_SUBJECTS)) {
        pinnaquest_redirect_notice(
            'materials.php',
            'Please select a valid subject from the allowed list.',
            'error'
        );
    }

    // ── File type validation — PDF only ────────────────────────────
    $file_ext = strtolower(pathinfo($_FILES["material_file"]["name"], PATHINFO_EXTENSION));
    if ($file_ext !== 'pdf') {
        pinnaquest_redirect_notice('materials.php', 'Only PDF files are accepted.', 'error');
    }

    // ── File size limit: 100MB ──────────────────────────────────────
    $max_bytes = 100 * 1024 * 1024;
    if ($_FILES["material_file"]["size"] > $max_bytes) {
        pinnaquest_redirect_notice(
            'materials.php',
            'The maximum allowed file size is 100MB.',
            'error'
        );
    }

    // ── Save file ───────────────────────────────────────────────────
    $target_dir = "uploads/";
    $title_safe        = $conn->real_escape_string($title);

    if (empty($display_name)) {
        $display_name = pathinfo($_FILES["material_file"]["name"], PATHINFO_FILENAME);
    }
    $display_name_safe = $conn->real_escape_string($display_name);

    $file_name   = time() . "_" . basename($_FILES["material_file"]["name"]);
    $target_file = $target_dir . $file_name;

    if (move_uploaded_file($_FILES["material_file"]["tmp_name"], $target_file)) {

        // ── AI CONTENT VALIDATION ────────────────────────────────────
        // Checks if the PDF content actually matches the selected
        // subject using a SEPARATE Groq API key (GROQ_API_KEY_VALIDATOR)
        // — separate from the GROQ_API_KEY used by engine.py for
        // quiz generation, so they do not share daily limits.
        $pythonPath    = '"C:\Users\DREAM PC\AppData\Local\Programs\Python\Python311\python.exe"';
        $escapedPdf    = escapeshellarg($target_file);
        $escapedSubj   = escapeshellarg($title);
        $command       = "chcp 65001 && $pythonPath validate_subject.py $escapedPdf $escapedSubj 2>&1";
        $validator_out = shell_exec($command);

        $jsonStart  = strpos($validator_out, '{');
        $jsonEnd    = strrpos($validator_out, '}');
        $validation = null;
        if ($jsonStart !== false && $jsonEnd !== false) {
            $jsonOnly   = substr($validator_out, $jsonStart, ($jsonEnd - $jsonStart) + 1);
            $validation = json_decode($jsonOnly, true);
        }

        if ($validation && empty($validation['validation_error']) && empty($validation['error'])) {
            $isMatch    = !empty($validation['matches']);
            $confidence = $validation['confidence'] ?? 'low';

            // Only reject if the AI is CONFIDENT (medium/high) that it
            // does not match — avoiding false rejections in "low"
            // confidence cases.
            if (!$isMatch && $confidence !== 'low') {
                unlink($target_file);
                $detected = $validation['detected_subject'] ?? 'another subject';
                pinnaquest_redirect_notice(
                    'materials.php',
                    "The PDF was rejected because it discusses $detected instead of $title.",
                    'error'
                );
            }
        } else {
            // Fail-open: if the validator script itself errors
            // (e.g., rate-limited, missing env var, no internet),
            // continue the upload so legit students are not blocked,
            // but log the error for admin review.
            error_log('PinnaQuest subject validator failed (student upload): ' . ($validator_out ?: 'no output'));
        }
        // ── END AI CONTENT VALIDATION ─────────────────────────────────

        $sql = "INSERT INTO materials (user_id, title, display_name, file_path, date_uploaded)
                VALUES ($user_id, '$title_safe', '$display_name_safe', '$target_file', NOW())";
        if ($conn->query($sql) === TRUE) {
            pinnaquest_redirect_notice(
                'materials.php',
                "Your material was successfully uploaded and is ready for quiz generation.",
                'success'
            );
        } else {
            pinnaquest_redirect_notice('materials.php', 'A database error occurred during upload.', 'error');
        }
    }
}

$conn->close();
?>