<?php
/**
 * Shared quiz XP policy.
 *
 * Quiz XP is intentionally handled here for both SoloQuiz and SynchroQuiz:
 * - maximum of 450 awarded quiz XP per user per calendar day;
 * - no cooldown and no diminishing-return calculation;
 * - one ledger row per user + quiz attempt, so duplicate requests cannot
 *   award XP twice;
 * - requested XP is kept separately from awarded XP so a daily-cap truncation
 *   does not incorrectly make a retake look complete.
 */

require_once __DIR__ . '/xp_config.php';

const QUIZ_DAILY_XP_CAP = PINNAQUEST_DAILY_QUIZ_XP_CAP;

function ensureQuizXpSchema(mysqli $conn): void
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS quiz_xp_awards (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT NOT NULL,
            quiz_kind VARCHAR(20) NOT NULL,
            activity_key VARCHAR(128) NOT NULL,
            attempt_id BIGINT NOT NULL,
            xp_requested INT NOT NULL DEFAULT 0,
            xp_awarded INT NOT NULL DEFAULT 0,
            award_date DATE NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_quiz_xp_attempt
                (user_id, quiz_kind, activity_key, attempt_id),
            KEY idx_quiz_xp_daily (user_id, award_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    // These columns are added lazily so existing local databases continue to
    // work without requiring the developer to recreate the database.
    $columns = [
        'quiz_key' => "ALTER TABLE solo_quiz_results ADD COLUMN quiz_key VARCHAR(64) NULL",
        'perfect_xp' => "ALTER TABLE solo_quiz_results ADD COLUMN perfect_xp INT NOT NULL DEFAULT 0",
        'is_retake' => "ALTER TABLE solo_quiz_results ADD COLUMN is_retake TINYINT(1) NOT NULL DEFAULT 0",
        'retake_of' => "ALTER TABLE solo_quiz_results ADD COLUMN retake_of INT NULL",
    ];
    foreach ($columns as $name => $alter) {
        $check = $conn->query("SHOW COLUMNS FROM solo_quiz_results LIKE '$name'");
        if ($check && $check->num_rows === 0) {
            $conn->query($alter);
        }
    }
}

function quizQuestionsKey(array $questions): string
{
    $items = [];
    foreach ($questions as $question) {
        if (!is_array($question)) {
            continue;
        }
        $items[] = [
            'question' => trim((string)($question['question'] ?? '')),
            'answer' => trim((string)($question['answer'] ?? '')),
            'options' => array_values(array_map('strval', (array)($question['options'] ?? []))),
        ];
    }
    usort($items, static function (array $a, array $b): int {
        return strcmp(json_encode($a, JSON_UNESCAPED_UNICODE), json_encode($b, JSON_UNESCAPED_UNICODE));
    });
    return hash('sha256', json_encode($items, JSON_UNESCAPED_UNICODE));
}

function soloPerfectXp(int $totalQuestions): int
{
    $totalQuestions = max(0, $totalQuestions);
    return $totalQuestions > 0 ? ($totalQuestions * 20) + 50 + 100 : 0;
}

function soloAttemptXp(int $correctAnswers, int $totalQuestions): int
{
     $totalQuestions = max(0, $totalQuestions);
    $correctAnswers = max(0, min($correctAnswers, max(0, $totalQuestions)));

    // The SoloQuiz completion bonus is only earned when at least one answer
    // is correct; a 0/anything run must not grant XP.
    if ($correctAnswers === 0) {
        return 0;
    }

    $xp = ($correctAnswers * 20) + 50;
    if ($totalQuestions > 0 && $correctAnswers === $totalQuestions) {
        $xp += 100;
    }
    return $xp;
}

/**
 * Awards quiz XP atomically and returns the awarded amount.
 * $activityKey identifies the quiz; $attemptId identifies one independent
 * completion of that quiz/session.
 */
function awardQuizXp(
    mysqli $conn,
    int $userId,
    string $quizKind,
    string $activityKey,
    int $attemptId,
    int $requestedXp
): int {
    ensureQuizXpSchema($conn);
    $requestedXp = max(0, $requestedXp);

    $conn->begin_transaction();
    try {
        $existingKey = $conn->real_escape_string($activityKey);
        $existingKind = $conn->real_escape_string($quizKind);
        $existing = $conn->query(
            "SELECT xp_awarded FROM quiz_xp_awards
             WHERE user_id = $userId
               AND quiz_kind = '$existingKind'
               AND activity_key = '$existingKey'
               AND attempt_id = $attemptId
             LIMIT 1"
        );
        if ($existing && $existing->num_rows > 0) {
            $already = intval($existing->fetch_assoc()['xp_awarded']);
            $conn->rollback();
            return $already;
        }

        // Lock the user row so two simultaneous quiz completions cannot both
        // spend the same remaining daily allowance.
        $conn->query("SELECT id FROM users WHERE id = $userId FOR UPDATE");
        $today = $conn->query(
            "SELECT COALESCE(SUM(xp_awarded), 0) AS total
             FROM quiz_xp_awards
             WHERE user_id = $userId AND award_date = CURDATE()"
        );
        $usedToday = $today ? intval($today->fetch_assoc()['total']) : 0;
        $awardedXp = max(0, min($requestedXp, QUIZ_DAILY_XP_CAP - $usedToday));

        $conn->query(
            "INSERT INTO quiz_xp_awards
                (user_id, quiz_kind, activity_key, attempt_id, xp_requested, xp_awarded, award_date)
             VALUES
                ($userId, '$existingKind', '$existingKey', $attemptId,
                 $requestedXp, $awardedXp, CURDATE())"
        );
        if ($awardedXp > 0) {
            $conn->query("UPDATE users SET xp = xp + $awardedXp WHERE id = $userId");
        }
        $conn->commit();
        return $awardedXp;
    } catch (Throwable $error) {
        $conn->rollback();
        error_log('PinnaQuest quiz XP award failed: ' . $error->getMessage());
        return 0;
    }
}

function soloEligibleXpBefore(mysqli $conn, int $userId, string $quizKey, int $sourceResultId = 0): int
{
    $key = $conn->real_escape_string($quizKey);
    $sum = $conn->query(
        "SELECT COALESCE(SUM(xp_requested), 0) AS total
         FROM quiz_xp_awards
         WHERE user_id = $userId AND quiz_kind = 'solo' AND activity_key = '$key'"
    );
    $eligible = $sum ? intval($sum->fetch_assoc()['total']) : 0;

    // Old results created before the ledger existed have no quiz_key. Include
    // the selected source attempt once so legacy history can be retaken.
    if ($sourceResultId > 0) {
        $legacy = $conn->query(
            "SELECT xp_earned FROM solo_quiz_results
             WHERE id = $sourceResultId AND user_id = $userId AND (quiz_key IS NULL OR quiz_key = '')"
        );
        if ($legacy && $legacy->num_rows > 0) {
            $eligible += intval($legacy->fetch_assoc()['xp_earned']);
        }
    }
    return $eligible;
}

function quizXpTotals(mysqli $conn, int $userId): array
{
    $row = $conn->query("SELECT xp FROM users WHERE id = $userId");
    $totalXp = $row ? intval($row->fetch_assoc()['xp'] ?? 0) : 0;
     $level = max(1, floor($totalXp / PINNAQUEST_XP_PER_LEVEL) + 1);
    $thisLevel = $totalXp % PINNAQUEST_XP_PER_LEVEL;
    return [
        'total_xp' => $totalXp,
        'level' => $level,
        'progress_pct' => round(($thisLevel / PINNAQUEST_XP_PER_LEVEL) * 100),
    ];
}