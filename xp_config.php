<?php
/**
 * Shared XP rules for solo quiz rewards.
 *
 * Keep these values in one place so the progression rules can be tuned
 * without changing quiz scoring or any game-mode code.
 */
const PINNAQUEST_DAILY_QUIZ_XP_CAP = 450;
const PINNAQUEST_XP_PER_LEVEL = 450;
const PINNAQUEST_QUIZ_XP_COOLDOWN_SECONDS = 600;

/**
 * Calculate the normal XP value for a solo quiz before XP controls apply.
 */
function pinnaquestNormalQuizXp(int $correctAnswers, int $totalQuestions): int
{

 $totalQuestions = max(0, $totalQuestions);
    $correctAnswers = max(0, min($correctAnswers, $totalQuestions));

    // A completed quiz with no correct answers earns no SoloQuiz XP.
    if ($correctAnswers === 0) {
        return 0;
    }

    $xp = ($correctAnswers * 20) + 50;

    if ($totalQuestions > 0 && $correctAnswers === $totalQuestions) {
        $xp += 100;
    }

    return max(0, $xp);
}

/**
 * Apply the per-user/per-quiz diminishing return schedule.
 *
 * Qualifying completions are counted only when they actually awarded XP.
 */
function pinnaquestDiminishedQuizXp(int $normalXp, int $qualifyingCompletions): int
{
    if ($qualifyingCompletions <= 0) {
        return $normalXp;
    }

    if ($qualifyingCompletions === 1) {
        return intdiv($normalXp, 2);
    }

    if ($qualifyingCompletions === 2) {
        return intdiv($normalXp, 4);
    }

    return 0;
}