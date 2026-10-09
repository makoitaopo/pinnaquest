<?php
/**
 * Shared mission-map progress rules.
 *
 * The mission map is a projection of activity already recorded by the
 * application. It does not award XP by itself; quiz endpoints remain the
 * single source of truth for XP awards.
 */
require_once __DIR__ . '/xp_config.php';

function pinnaquestMissionDefinitions(array $stats, array $unlocked = []): array
{
    $quizCount = intval($stats['quiz_count'] ?? 0);
    $totalCorrect = intval($stats['total_correct'] ?? 0);
    $synchroCount = intval($stats['synchro_count'] ?? 0);
    $maxStreak = intval($stats['max_streak'] ?? 0);
    $totalXp = intval($stats['total_xp'] ?? 0);
    $level = intval($stats['level'] ?? 1);
    $perfectCount = intval($stats['perfect_count'] ?? 0);

    $missions = [
        [
            'key' => 'first_quest',
            'zone' => 1,
            'name' => 'First Scroll',
            'desc' => 'Complete your first solo quiz',
            'emoji' => '📖',
            'xp' => 50,
            'stars' => 3,
            'boss' => false,
            'check' => $quizCount >= 1,
            'progress_value' => min($quizCount, 1),
            'progress_target' => 1,
            'progress_label' => 'solo quiz',
            'href' => 'quizzes.php',
        ],
        [
            'key' => 'sharp_shooter',
            'zone' => 1,
            'name' => 'Sharp Eye',
            'desc' => 'Answer 10 questions correctly',
            'emoji' => '🎯',
            'xp' => 75,
            'stars' => 2,
            'boss' => false,
            'check' => $totalCorrect >= 10,
            'progress_value' => min($totalCorrect, 10),
            'progress_target' => 10,
            'progress_label' => 'correct answers',
            'href' => 'quizzes.php',
        ],
        [
            'key' => 'synchro_debut',
            'zone' => 1,
            'name' => 'Synchro Entry',
            'desc' => 'Join a live Synchro-Quiz battle',
            'emoji' => '⚡',
            'xp' => 30,
            'stars' => 2,
            'boss' => false,
            'check' => $synchroCount >= 1,
            'progress_value' => min($synchroCount, 1),
            'progress_target' => 1,
            'progress_label' => 'live battle',
            'href' => 'synchro_portal.php',
        ],
        [
            'key' => 'zone1_boss',
            'zone' => 1,
            'name' => 'ZONE 1 GUARDIAN',
            'desc' => 'Clear all Scholar’s Road challenges',
            'emoji' => '🗡️',
            'xp' => 100,
            'stars' => 3,
            'boss' => true,
            'check' => $quizCount >= 1 && $totalCorrect >= 10 && $synchroCount >= 1,
            'progress_value' => 0,
            'progress_target' => 3,
            'progress_label' => 'Zone 1 quests',
            'href' => 'quizzes.php',
        ],
        [
            'key' => 'streak_master',
            'zone' => 2,
            'name' => 'Flame Streak',
            'desc' => 'Land a 5-answer streak in Synchro',
            'emoji' => '🔥',
            'xp' => 75,
            'stars' => 3,
            'boss' => false,
            'check' => $maxStreak >= 5,
            'progress_value' => min($maxStreak, 5),
            'progress_target' => 5,
            'progress_label' => 'answer streak',
            'href' => 'synchro_portal.php',
        ],
        [
            'key' => 'centurion',
            'zone' => 2,
            'name' => 'Centurion',
            'desc' => 'Answer 50 questions correctly',
            'emoji' => '🛡️',
            'xp' => 100,
            'stars' => 3,
            'boss' => false,
            'check' => $totalCorrect >= 50,
            'progress_value' => min($totalCorrect, 50),
            'progress_target' => 50,
            'progress_label' => 'correct answers',
            'href' => 'quizzes.php',
        ],
        [
            'key' => 'xp_warrior',
            'zone' => 2,
            'name' => 'XP Warrior',
            'desc' => 'Earn your first 500 XP',
            'emoji' => '⭐',
            'xp' => 50,
            'stars' => 2,
            'boss' => false,
            'check' => $totalXp >= 500,
            'progress_value' => min($totalXp, 500),
            'progress_target' => 500,
            'progress_label' => 'XP',
            'href' => 'quizzes.php',
        ],
        [
            'key' => 'zone2_boss',
            'zone' => 2,
            'name' => 'ZONE 2 GUARDIAN',
            'desc' => 'Master the Battle Arena challenges',
            'emoji' => '⚔️',
            'xp' => 150,
            'stars' => 3,
            'boss' => true,
            'check' => $maxStreak >= 5 && $totalCorrect >= 50 && $totalXp >= 500,
            'progress_value' => 0,
            'progress_target' => 3,
            'progress_label' => 'Zone 2 quests',
            'href' => 'synchro_portal.php',
        ],
        [
            'key' => 'perfect_run',
            'zone' => 3,
            'name' => 'Flawless Run',
            'desc' => 'Finish a quiz with 100% score',
            'emoji' => '💎',
            'xp' => 100,
            'stars' => 3,
            'boss' => false,
            'check' => $perfectCount >= 1,
            'progress_value' => min($perfectCount, 1),
            'progress_target' => 1,
            'progress_label' => 'perfect quiz',
            'href' => 'quizzes.php',
        ],
        [
            'key' => 'legend',
            'zone' => 3,
            'name' => 'Legend',
            'desc' => 'Reach Level 5',
            'emoji' => '👑',
            'xp' => 200,
            'stars' => 3,
            'boss' => false,
            'check' => $level >= 5,
            'progress_value' => min($level, 5),
            'progress_target' => 5,
            'progress_label' => 'level',
            'href' => 'quizzes.php',
        ],
        [
            'key' => 'final_boss',
            'zone' => 3,
            'name' => 'THE PINNACLE',
            'desc' => 'Conquer all quests and ascend',
            'emoji' => '🏰',
            'xp' => 500,
            'stars' => 3,
            'boss' => true,
            'check' => $level >= 5 && $perfectCount >= 1,
            'progress_value' => 0,
            'progress_target' => 10,
            'progress_label' => 'other quests',
            'href' => 'quizzes.php',
        ],
    ];

    $doneKeys = [];
    foreach ($missions as $mission) {
        if (!empty($unlocked[$mission['key']]) || $mission['check']) {
            $doneKeys[$mission['key']] = true;
        }
    }

    $zoneRequirements = [
        'zone1_boss' => ['first_quest', 'sharp_shooter', 'synchro_debut'],
        'zone2_boss' => ['streak_master', 'centurion', 'xp_warrior'],
        'final_boss' => [
            'first_quest', 'sharp_shooter', 'synchro_debut', 'zone1_boss',
            'streak_master', 'centurion', 'xp_warrior', 'zone2_boss',
            'perfect_run', 'legend',
        ],
    ];
    foreach ($missions as &$mission) {
        if (isset($zoneRequirements[$mission['key']])) {
            $requirements = $zoneRequirements[$mission['key']];
            $mission['progress_value'] = count(array_filter(
                $requirements,
                static fn(string $key): bool => isset($doneKeys[$key])
            ));
        }
    }
    unset($mission);

    $foundActive = false;
    foreach ($missions as &$mission) {
        $done = isset($doneKeys[$mission['key']]);
        if ($done) {
            $mission['state'] = 'done';
        } elseif (!$foundActive) {
            $mission['state'] = 'active';
            $foundActive = true;
        } else {
            $mission['state'] = 'locked';
        }
    }
    unset($mission);

    return $missions;
}

function pinnaquestMissionProgress(mysqli $conn, int $userId): array
{
    $empty = [
        'user' => [],
        'stats' => [
            'quiz_count' => 0,
            'solo_correct' => 0,
            'synchro_correct' => 0,
            'total_correct' => 0,
            'synchro_count' => 0,
            'max_streak' => 0,
            'perfect_count' => 0,
            'total_xp' => 0,
            'level' => 1,
        ],
        'unlocked' => [],
        'missions' => [],
    ];
    if ($userId <= 0) {
        return $empty;
    }

    $userResult = $conn->query(
        "SELECT id, full_name, display_name, avatar_key, xp
         FROM users WHERE id = $userId AND role = 'student' LIMIT 1"
    );
    if (!$userResult || $userResult->num_rows === 0) {
        return $empty;
    }
    $user = $userResult->fetch_assoc();
    $displayName = trim((string)($user['display_name'] ?: $user['full_name'] ?: 'Adventurer'));
    $displayNameSql = $conn->real_escape_string($displayName);
    $totalXp = intval($user['xp'] ?? 0);

    $solo = $conn->query(
        "SELECT COUNT(*) AS quiz_count,
                COALESCE(SUM(correct_answers), 0) AS solo_correct,
                COALESCE(MAX(CASE WHEN total_questions > 0
                    AND correct_answers = total_questions THEN 1 ELSE 0 END), 0) AS perfect_count
         FROM solo_quiz_results WHERE user_id = $userId"
    );
    $soloStats = $solo ? $solo->fetch_assoc() : [];

    // Newer records carry user_id. The nickname fallback preserves progress
    // from older Synchro rows without merging rows owned by another user.
    $synchro = $conn->query(
        "SELECT COUNT(DISTINCT session_id) AS synchro_count,
                COALESCE(SUM(correct_answers), 0) AS synchro_correct,
                COALESCE(MAX(streak), 0) AS max_streak
         FROM synchro_scores ss
         WHERE ss.user_id = $userId
            OR (
                ss.user_id IS NULL
                AND (
                    ss.nickname = '$displayNameSql'
                    OR EXISTS (
                        SELECT 1
                        FROM synchro_participants sp
                        WHERE sp.session_id = ss.session_id
                          AND sp.nickname = ss.nickname
                          AND sp.user_id = $userId
                    )
                )
            )"
    );
    $synchroStats = $synchro ? $synchro->fetch_assoc() : [];

    $participantResult = $conn->query(
        "SELECT COUNT(DISTINCT session_id) AS participant_count
         FROM synchro_participants sp
         WHERE sp.user_id = $userId
            OR (sp.user_id IS NULL AND sp.nickname = '$displayNameSql')"
    );
    $participantStats = $participantResult ? $participantResult->fetch_assoc() : [];

    $unlocked = [];
    $achievementResult = $conn->query(
        "SELECT achievement_key FROM user_achievements WHERE user_id = $userId"
    );
    if ($achievementResult) {
        while ($row = $achievementResult->fetch_assoc()) {
            $unlocked[(string)$row['achievement_key']] = true;
        }
    }

    $stats = [
        'quiz_count' => intval($soloStats['quiz_count'] ?? 0),
        'solo_correct' => intval($soloStats['solo_correct'] ?? 0),
        'synchro_correct' => intval($synchroStats['synchro_correct'] ?? 0),
        'total_correct' => intval($soloStats['solo_correct'] ?? 0)
            + intval($synchroStats['synchro_correct'] ?? 0),
        'synchro_count' => max(
            intval($synchroStats['synchro_count'] ?? 0),
            intval($participantStats['participant_count'] ?? 0)
        ),
        'max_streak' => intval($synchroStats['max_streak'] ?? 0),
        'perfect_count' => intval($soloStats['perfect_count'] ?? 0),
        'total_xp' => $totalXp,
        'level' => max(1, intdiv($totalXp, PINNAQUEST_XP_PER_LEVEL) + 1),
    ];

    return [
        'user' => $user,
        'stats' => $stats,
        'unlocked' => $unlocked,
        'missions' => pinnaquestMissionDefinitions($stats, $unlocked),
    ];
}

/**
 * Persist milestone completion after an activity is completed.
 * This is intentionally separate from XP awarding to prevent duplicate XP.
 */
function pinnaquestSyncMissionAchievements(mysqli $conn, int $userId): void
{
    $progress = pinnaquestMissionProgress($conn, $userId);
    foreach ($progress['missions'] as $mission) {
        if (empty($mission['check'])) {
            continue;
        }
        $key = $conn->real_escape_string((string)$mission['key']);
        $conn->query(
            "INSERT IGNORE INTO user_achievements (user_id, achievement_key)
             VALUES ($userId, '$key')"
        );
    }
}