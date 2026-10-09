<?php
/**
 * Keeps SynchroQuiz records tied to the logged-in account instead of only
 * relying on a nickname, which can be reused by another student.
 */
function ensureSynchroIdentitySchema(mysqli $conn): void
{
    $columns = [
        'synchro_participants' => 'user_id INT NULL',
        'synchro_scores' => 'user_id INT NULL',
    ];

    foreach ($columns as $table => $definition) {
        $check = $conn->query("SHOW COLUMNS FROM `$table` LIKE 'user_id'");
        if ($check && $check->num_rows === 0) {
            $conn->query("ALTER TABLE `$table` ADD COLUMN $definition");
        }
    }

    // Preserve existing history when the project is upgraded from the
    // nickname-only schema. Exact matches are used only to connect records
    // to an account; new records always receive user_id during join.
    $conn->query(
        "UPDATE synchro_participants p
         INNER JOIN users u ON p.user_id IS NULL
          AND (p.nickname = u.display_name OR p.nickname = u.full_name)
         SET p.user_id = u.id"
    );
    $conn->query(
        "UPDATE synchro_scores sc
         INNER JOIN synchro_participants p
           ON p.session_id = sc.session_id AND p.nickname = sc.nickname
         SET sc.user_id = p.user_id
         WHERE sc.user_id IS NULL AND p.user_id IS NOT NULL"
    );
}

function simpleScore(int $score, int $correct, int $total): string
{
    $total = max(0, $total);
    $correct = max(0, min($correct, $total));
    $accuracy = $total > 0 ? round(($correct / $total) * 100) : 0;
    return number_format($score) . ' pts · ' . $correct . '/' . $total . ' correct (' . $accuracy . '%)';
}