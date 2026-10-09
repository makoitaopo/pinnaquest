<?php
/**
 * PinnaQuest game-mode access control.
 *
 * XP is never spent. It only determines the student's current level.
 * Every game page must call requireGameModeAccess() so direct URL access
 * cannot bypass the lock shown in the game selector.
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/db.php';

require_once __DIR__ . '/xp_config.php';

/**
 * Return the current student's XP and computed level.
 */
function getStudentProgress(): array
{
    global $conn;

    if (!isset($_SESSION['user_id'])) {
        return [
            'logged_in' => false,
            'xp' => 0,
            'level' => 1,
            'next_level_xp' => PINNAQUEST_XP_PER_LEVEL,
            'xp_to_next_level' => PINNAQUEST_XP_PER_LEVEL,
        ];
    }

    $userId = (int) $_SESSION['user_id'];
    $stmt = $conn->prepare('SELECT xp FROM users WHERE id = ? LIMIT 1');

    if (!$stmt) {
        throw new RuntimeException('Unable to prepare the XP query.');
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    $xp = max(0, (int) ($row['xp'] ?? 0));
    $level = max(1, intdiv($xp, PINNAQUEST_XP_PER_LEVEL) + 1);
    $nextLevelXp = $level * PINNAQUEST_XP_PER_LEVEL;

    return [
        'logged_in' => true,
        'xp' => $xp,
        'level' => $level,
        'next_level_xp' => $nextLevelXp,
        'xp_to_next_level' => max(0, $nextLevelXp - $xp),
    ];
}

/**
 * Required level for each game mode.
 *
 * Classic Quest and Bubble Pop are available to new students.
 * Advanced modes are unlocked through XP progression.
 */
function getGameModeRequirements(): array
{
    return [
        'classic' => [
            'label' => 'Classic Quest',
            'required_level' => 1,
        ],
        'bubble_pop' => [
            'label' => 'Bubble Pop',
            'required_level' => 1,
        ],
        'word_scramble' => [
            'label' => 'Word Scramble',
            'required_level' => 3,
        ],
        'bomb_toss' => [
            'label' => 'Bomb Toss',
            'required_level' => 3,
        ],
        'hit_or_fold' => [
            'label' => 'Hit or Fold',
            'required_level' => 4,
        ],
        'masked_impostor' => [
            'label' => 'Masked Impostor',
            'required_level' => 5,
        ],
        'typo_gremlin' => [
            'label' => 'The Typo Gremlin',
            'required_level' => 5,
        ],
        'decoy_printer' => [
            'label' => 'The Decoy Printer',
            'required_level' => 6,
        ],
        'typo_bomb' => [
            'label' => 'Typo Bomb',
            'required_level' => 6,
        ],
    ];
}

/**
 * Return access information for a mode.
 */
function getGameModeAccess(string $mode, ?array $progress = null): array
{
    $requirements = getGameModeRequirements();

    if (!isset($requirements[$mode])) {
        throw new InvalidArgumentException('Unknown PinnaQuest game mode: ' . $mode);
    }

    $progress = $progress ?? getStudentProgress();
    $requiredLevel = (int) $requirements[$mode]['required_level'];
    $currentLevel = (int) $progress['level'];
    $allowed = !empty($progress['logged_in']) && $currentLevel >= $requiredLevel;

    return [
        'allowed' => $allowed,
        'mode' => $mode,
        'label' => $requirements[$mode]['label'],
        'required_level' => $requiredLevel,
        'current_level' => $currentLevel,
        'xp' => (int) $progress['xp'],
        'xp_to_next_level' => (int) $progress['xp_to_next_level'],
        'level_gap' => max(0, $requiredLevel - $currentLevel),
    ];
}

/**
 * Enforce access on the actual game page.
 *
 * This protects against direct URLs, not only disabled UI cards.
 */
function requireGameModeAccess(string $mode): void
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: loginpanel.php');
        exit();
    }

    if (empty($_SESSION['quiz_data']['questions'])) {
        header('Location: quizzes.php?error=no_questions_found');
        exit();
    }

    $access = getGameModeAccess($mode);

    if (!$access['allowed']) {
        $query = http_build_query([
            'error' => 'mode_locked',
            'mode' => $mode,
            'required_level' => $access['required_level'],
        ]);

        header('Location: pre_quiz_summary.php?' . $query);
        exit();
    }
}

/**
 * Render a small lock notice inside a mode card.
 */
function renderModeLockNotice(string $mode, ?array $progress = null): string
{
    $access = getGameModeAccess($mode, $progress);

    if ($access['allowed']) {
        return '';
    }

    $label = htmlspecialchars($access['label'], ENT_QUOTES, 'UTF-8');
    $required = (int) $access['required_level'];
    $current = (int) $access['current_level'];
    $xpToUnlock = max(0, ($required - $current) * PINNAQUEST_XP_PER_LEVEL
        - ((int) $access['xp'] % PINNAQUEST_XP_PER_LEVEL));

    return '<div class="mode-lock-notice">'
        . '<i class="fa-solid fa-lock"></i> '
        . '<strong>Unlocks at Level ' . $required . '</strong>'
        . '<span>' . $label . ' is locked. You are Level ' . $current . '.'
        . ($xpToUnlock > 0 ? ' About ' . $xpToUnlock . ' XP to go.' : '')
        . '</span></div>';
}
