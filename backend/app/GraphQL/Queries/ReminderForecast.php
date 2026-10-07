<?php

namespace App\GraphQL\Queries;

use App\GraphQL\Concerns\ResolvesCurrentUser;
use App\Services\StudyPlanner;
use Illuminate\Support\Carbon;

class ReminderForecast
{
    use ResolvesCurrentUser;

    /** A week of reminders at most a few times a day. */
    private const MAX_MOMENTS = 64;

    public function __construct(private readonly StudyPlanner $planner) {}

    /**
     * @param  array{at: list<Carbon>}  $args
     * @return list<array{at: Carbon, count: int, minutes: int, first_term: ?string}>
     */
    public function __invoke(mixed $root, array $args): array
    {
        return $this->planner->forecast($this->user(), array_slice($args['at'], 0, self::MAX_MOMENTS));
    }
}
