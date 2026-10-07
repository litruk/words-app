<?php

namespace App\GraphQL\Mutations;

use App\GraphQL\Concerns\ResolvesCurrentUser;
use App\Models\ReminderSetting;

class UpdateReminders
{
    use ResolvesCurrentUser;

    /**
     * @param  array{input: array{enabled: bool, times: list<array{time: string, enabled: bool}>, days: list<int>, only_when_due: bool, skip_if_studied: bool}}  $args
     */
    public function __invoke(mixed $root, array $args): ReminderSetting
    {
        $input = $args['input'];

        $times = collect($input['times'])
            ->unique('time')
            ->sortBy('time')
            ->map(fn (array $row) => ['time' => $row['time'], 'enabled' => $row['enabled']])
            ->values()
            ->all();

        $days = collect($input['days'])
            ->filter(fn (int $day) => $day >= 1 && $day <= 7)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return ReminderSetting::updateOrCreate(
            ['user_id' => $this->user()->id],
            [
                'enabled' => $input['enabled'],
                'times' => $times,
                'days' => $days,
                'only_when_due' => $input['only_when_due'],
                'skip_if_studied' => $input['skip_if_studied'],
            ],
        );
    }
}
