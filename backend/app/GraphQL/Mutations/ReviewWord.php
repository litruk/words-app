<?php

namespace App\GraphQL\Mutations;

use App\GraphQL\Concerns\ResolvesCurrentUser;
use App\Models\Review;
use App\Models\Word;
use App\Services\Scheduler;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReviewWord
{
    use ResolvesCurrentUser;

    public function __construct(private readonly Scheduler $scheduler) {}

    /**
     * @param  array{id: string, rating: int, duration_ms?: ?int}  $args
     */
    public function __invoke(mixed $root, array $args): Word
    {
        $word = $this->ownWord($args['id']);
        $rating = $args['rating'];
        $now = Carbon::now();
        $next = $this->scheduler->answer($word, $rating, $now);

        DB::transaction(function () use ($word, $rating, $now, $next, $args) {
            Review::create([
                'user_id' => $word->user_id,
                'word_id' => $word->id,
                'language' => $word->language,
                'rating' => $rating,
                'state_before' => $word->state,
                'interval_before' => $word->interval,
                'interval_after' => $next['interval'],
                'duration_ms' => isset($args['duration_ms']) ? max(0, min($args['duration_ms'], 3_600_000)) : null,
                'reviewed_at' => $now,
            ]);

            $word->update([
                ...$next,
                'reps' => $word->reps + 1,
                'lapses' => $word->lapses + ($rating === Scheduler::AGAIN ? 1 : 0),
                'last_reviewed_at' => $now,
            ]);
        });

        return $word;
    }
}
