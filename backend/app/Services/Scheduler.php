<?php

namespace App\Services;

use App\Models\Word;
use Illuminate\Support\Carbon;

/**
 * Spaced repetition in the SM-2 family, the way Anki does it: a new card
 * climbs a few short learning steps, then graduates to day-long intervals that
 * grow by the card's ease factor; forgetting it ("again") sends it back to a
 * short relearning step and cuts both its interval and ease.
 *
 * Ratings: 1 again, 2 hard, 3 good, 4 easy.
 */
class Scheduler
{
    public const AGAIN = 1;

    public const HARD = 2;

    public const GOOD = 3;

    public const EASY = 4;

    public const RATINGS = [self::AGAIN, self::HARD, self::GOOD, self::EASY];

    /** Learning steps of a new card, in minutes. */
    private const LEARNING_STEPS = [1, 10];

    /** Relearning steps after a lapse, in minutes. */
    private const RELEARNING_STEPS = [1];

    /** Days, when a new card graduates with "good" / with "easy". */
    private const GRADUATING_INTERVAL = 1;

    private const EASY_INTERVAL = 4;

    private const MINIMUM_EASE = 1.3;

    private const HARD_FACTOR = 1.2;

    private const EASY_BONUS = 1.3;

    /** A lapsed card keeps this share of its interval. */
    private const LAPSE_FACTOR = 0.3;

    /**
     * The card's new state after the given answer. Nothing is saved.
     *
     * @return array{state: string, step: int, interval: float, ease: float, due_at: Carbon}
     */
    public function answer(Word $word, int $rating, Carbon $now): array
    {
        return match ($word->state) {
            Word::STATE_REVIEW => $this->answerReview($word, $rating, $now),
            Word::STATE_RELEARNING => $this->answerSteps($word, $rating, $now, self::RELEARNING_STEPS),
            default => $this->answerSteps($word, $rating, $now, self::LEARNING_STEPS),
        };
    }

    /**
     * How long each answer would put the card away for, in seconds; shown on
     * the rating buttons.
     *
     * @return array{again: int, hard: int, good: int, easy: int}
     */
    public function preview(Word $word, Carbon $now): array
    {
        $seconds = fn (int $rating): int => (int) round($now->diffInSeconds($this->answer($word, $rating, $now)['due_at']));

        return [
            'again' => $seconds(self::AGAIN),
            'hard' => $seconds(self::HARD),
            'good' => $seconds(self::GOOD),
            'easy' => $seconds(self::EASY),
        ];
    }

    /**
     * New, learning and relearning cards: short steps measured in minutes.
     *
     * @param  list<int>  $steps
     * @return array{state: string, step: int, interval: float, ease: float, due_at: Carbon}
     */
    private function answerSteps(Word $word, int $rating, Carbon $now, array $steps): array
    {
        $relearning = $word->state === Word::STATE_RELEARNING;
        $state = $relearning ? Word::STATE_RELEARNING : Word::STATE_LEARNING;
        $step = min($word->state === Word::STATE_NEW ? 0 : $word->step, count($steps) - 1);
        $ease = $word->ease;

        switch ($rating) {
            case self::AGAIN:
                return $this->inMinutes($state, 0, $word->interval, $ease, $now, $steps[0]);

            case self::HARD:
                // On the first step "hard" sits between the first two steps.
                $minutes = $step === 0 && count($steps) > 1
                    ? ($steps[0] + $steps[1]) / 2
                    : $steps[$step] * 1.5;

                return $this->inMinutes($state, $step, $word->interval, $ease, $now, $minutes);

            case self::GOOD:
                if ($step + 1 < count($steps)) {
                    return $this->inMinutes($state, $step + 1, $word->interval, $ease, $now, $steps[$step + 1]);
                }

                // A relearnt card returns to the interval it kept at the lapse.
                $interval = $relearning ? max(1.0, $word->interval) : self::GRADUATING_INTERVAL;

                return $this->inDays($interval, $ease, $now);

            default:
                $interval = $relearning ? max(1.0, $word->interval) + 1 : self::EASY_INTERVAL;

                return $this->inDays($interval, $ease, $now);
        }
    }

    /**
     * Graduated cards: intervals in whole days.
     *
     * @return array{state: string, step: int, interval: float, ease: float, due_at: Carbon}
     */
    private function answerReview(Word $word, int $rating, Carbon $now): array
    {
        $interval = max(1.0, $word->interval);
        $ease = $word->ease;
        $elapsed = $word->last_reviewed_at !== null
            ? max(0.0, $word->last_reviewed_at->diffInSeconds($now) / 86400)
            : $interval;
        $overdue = max(0.0, $elapsed - $interval);

        if ($rating === self::AGAIN) {
            $kept = max(1.0, round($interval * self::LAPSE_FACTOR));

            return $this->inMinutes(
                Word::STATE_RELEARNING, 0, $kept, max(self::MINIMUM_EASE, $ease - 0.2), $now, self::RELEARNING_STEPS[0],
            );
        }

        $hard = max(round($interval * self::HARD_FACTOR), $interval + 1);
        $good = max(round(($interval + $overdue / 2) * $ease), $hard + 1);
        $easy = max(round(($interval + $overdue) * $ease * self::EASY_BONUS), $good + 1);

        return match ($rating) {
            self::HARD => $this->inDays($hard, max(self::MINIMUM_EASE, $ease - 0.15), $now),
            self::GOOD => $this->inDays($good, $ease, $now),
            default => $this->inDays($easy, $ease + 0.15, $now),
        };
    }

    /**
     * @return array{state: string, step: int, interval: float, ease: float, due_at: Carbon}
     */
    private function inMinutes(string $state, int $step, float $interval, float $ease, Carbon $now, float $minutes): array
    {
        return [
            'state' => $state,
            'step' => $step,
            'interval' => $interval,
            'ease' => $ease,
            'due_at' => $now->copy()->addSeconds((int) round($minutes * 60)),
        ];
    }

    /**
     * @return array{state: string, step: int, interval: float, ease: float, due_at: Carbon}
     */
    private function inDays(float $days, float $ease, Carbon $now): array
    {
        return [
            'state' => Word::STATE_REVIEW,
            'step' => 0,
            'interval' => $days,
            'ease' => $ease,
            'due_at' => $now->copy()->addDays((int) $days),
        ];
    }
}
