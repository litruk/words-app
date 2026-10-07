<?php

namespace App\GraphQL\Queries;

use App\GraphQL\Concerns\ResolvesCurrentUser;
use App\Models\Word;
use App\Services\StudyPlanner;
use Illuminate\Support\Collection;

class StudyQueue
{
    use ResolvesCurrentUser;

    public function __construct(private readonly StudyPlanner $planner) {}

    /**
     * @param  array{language: string, ids?: ?list<string>}  $args
     * @return Collection<int, Word>
     */
    public function __invoke(mixed $root, array $args): Collection
    {
        return $this->planner->queue($this->user(), $args['language'], $args['ids'] ?? null);
    }
}
