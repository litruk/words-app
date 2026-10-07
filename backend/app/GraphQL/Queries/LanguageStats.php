<?php

namespace App\GraphQL\Queries;

use App\GraphQL\Concerns\ResolvesCurrentUser;
use App\Services\StudyPlanner;

class LanguageStats
{
    use ResolvesCurrentUser;

    public function __construct(private readonly StudyPlanner $planner) {}

    /**
     * @return list<array{language: string, total: int, due: int, learned: int, new: int}>
     */
    public function __invoke(): array
    {
        $user = $this->user();

        return array_map(
            fn (string $language) => $this->planner->stats($user, $language),
            $user->studiedLanguages(),
        );
    }
}
