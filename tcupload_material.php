<?php
// tcupload_material.php — Teacher material upload with subject
// whitelist validation + AI content validation

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: loginpanel.php');
    exit();
}
$user_id = intval($_SESSION['user_id']);

$conn = new mysqli("localhost", "root", "", "pinnaquest_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ── Ensure display_name column exists (lazy schema upgrade, safe to re-run) ──
$colCheck = $conn->query("SHOW COLUMNS FROM teacher_materials LIKE 'display_name'");
if ($colCheck && $colCheck->num_rows === 0) {
    $conn->query("ALTER TABLE teacher_materials ADD COLUMN display_name VARCHAR(255) NULL AFTER title");
}

$ALLOWED_SUBJECTS = [
    "Readings in Philippine History",
    "Understanding the Self",
    "Art Appreciation",
    "Physical Education",
    "Science and Development of Reading",
];

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // ── Detect PHP-level rejection (post_max_size exceeded) ────────
    if (empty($_FILES) && empty($_POST)) {
        echo "<script>
            alert('❌ Your PDF file exceeds the server upload limit.');
            window.location.href = 'teacher_materials.php';
        </script>";
        exit();
    }

    // ── File upload error codes ─────────────────────────────────────
    $upload_error = $_FILES["material_file"]["error"] ?? -1;
    if ($upload_error === UPLOAD_ERR_INI_SIZE || $upload_error === UPLOAD_ERR_FORM_SIZE) {
        echo "<script>
            alert('❌ The uploaded PDF exceeds the maximum allowed file size.');
            window.location.href = 'teacher_materials.php';
        </script>";
        exit();
    }
    if ($upload_error === UPLOAD_ERR_NO_FILE) {
        echo "<script>
            alert('❌ Please choose a PDF file before uploading.');
            window.location.href = 'teacher_materials.php';
        </script>";
        exit();
    }

    $title        = trim($_POST['title'] ?? '');
    $display_name = trim($_POST['display_name'] ?? '');

    // ── Subject whitelist ───────────────────────────────────────────
    if (!in_array($title, $ALLOWED_SUBJECTS)) {
        echo "<script>
            alert('❌ Please select a valid subject from the allowed list.');
            window.location.href = 'teacher_materials.php';
        </script>";
        exit();
    }

    // ── PDF only ────────────────────────────────────────────────────
    $file_ext = strtolower(pathinfo($_FILES["material_file"]["name"], PATHINFO_EXTENSION));
    if ($file_ext !== 'pdf') {
        echo "<script>
            alert('❌ Only PDF files are accepted.');
            window.location.href = 'teacher_materials.php';
        </script>";
        exit();
    }

    // ── Size limit: 100MB ───────────────────────────────────────────
    if ($_FILES["material_file"]["size"] > 100 * 1024 * 1024) {
        echo "<script>
            alert('❌ The maximum allowed file size is 100MB.');
            window.location.href = 'teacher_materials.php';
        </script>";
        exit();
    }

    // ── Save ────────────────────────────────────────────────────────
    $target_dir = "uploads/teacher_vault/";
    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

    $title_safe  = $conn->real_escape_string($title);

    if (empty($display_name)) {
        $display_name = pathinfo($_FILES["material_file"]["name"], PATHINFO_FILENAME);
    }
    $display_name_safe = $conn->real_escape_string($display_name);

    $file_name   = time() . "_" . basename($_FILES["material_file"]["name"]);
    $target_file = $target_dir . $file_name;

    if (move_uploaded_file($_FILES["material_file"]["tmp_name"], $target_file)) {

        // ── AI CONTENT VALIDATION ────────────────────────────────────
        // Uses the same validator script and separate API key as the student upload.
        // It uses a separate Groq key (GROQ_API_KEY_VALIDATOR) to avoid sharing
        // rate limits with the quiz generation engine.
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

            if (!$isMatch && $confidence !== 'low') {
                unlink($target_file);
                $detected = $validation['detected_subject'] ?? 'another subject';
                echo "<script>
                    alert('❌ The PDF was rejected because it discusses " . addslashes($detected) . " instead of " . addslashes($title) . ".');
                    window.location.href = 'teacher_materials.php';
                </script>";
                exit();
            }
        } else {
            // Fail-open: if the validator script errors, log it and proceed so users are not blocked.
            // (This handles script/API errors, not content mismatch issues).
            error_log('PinnaQuest subject validator failed (teacher upload): ' . ($validator_out ?: 'no output'));
        }
        // ── END AI CONTENT VALIDATION ─────────────────────────────────

        $sql = "INSERT INTO teacher_materials (user_id, title, display_name, file_path, date_uploaded)
                VALUES ($user_id, '$title_safe', '$display_name_safe', '$target_file', NOW())";
        if ($conn->query($sql) === TRUE) {
            $notice_message = "Your material was successfully uploaded and added to your vault.";
            echo "<script>
                window.location.href = 'teacher_materials.php?upload_status=success&upload_message=" . rawurlencode($notice_message) . "';
            </script>";
        } else {
            echo "<script>alert('❌ A database error occurred during upload.'); history.back();</script>";
        }
    } else {
        echo "<script>alert('❌ The file upload failed, please try again.'); history.back();</script>";
    }
}

$conn->close();
?>