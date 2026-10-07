<?php

namespace App\GraphQL\Queries;

use App\GraphQL\Concerns\ResolvesCurrentUser;
use App\Services\StudyPlanner;

class Dashboard
{
    use ResolvesCurrentUser;

    public function __construct(private readonly StudyPlanner $planner) {}

    /**
     * @param  array{language: string}  $args
     * @return array<string, mixed>
     */
    public function __invoke(mixed $root, array $args): array
    {
        $user = $this->user();
        $language = $this->studiedLanguage($args['language']);
        $next = $this->planner->nextReview($user, $language);

        return [
            'language' => $language,
            ...$this->planner->today($user, $language),
            'studied_today' => $this->planner->studiedToday($user),
            'next_review_at' => $next['at'],
            'next_review_count' => $next['count'],
        ];
    }
}
