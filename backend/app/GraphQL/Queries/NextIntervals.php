<?php

namespace App\GraphQL\Queries;

use App\Models\Word;
use App\Services\Scheduler;
use Illuminate\Support\Carbon;

class NextIntervals
{
    public function __construct(private readonly Scheduler $scheduler) {}

    /**
     * @return array{again: int, hard: int, good: int, easy: int}
     */
    public function __invoke(Word $word): array
    {
        return $this->scheduler->preview($word, Carbon::now());
    }
}
