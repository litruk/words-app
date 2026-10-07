<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * When the app should remind the user to study. The notifications are
 * scheduled on the device; these settings only travel with the account.
 *
 * @property list<array{time: string, enabled: bool}> $times
 * @property list<int> $days ISO weekdays, 1 = Monday
 */
#[Fillable(['user_id', 'enabled', 'times', 'days', 'only_when_due', 'skip_if_studied'])]
class ReminderSetting extends Model
{
    /**
     * Morning and evening on weekdays, the way the design proposes it.
     */
    public static function defaultsFor(User $user): self
    {
        return new self([
            'user_id' => $user->id,
            'enabled' => true,
            'times' => [
                ['time' => '09:00', 'enabled' => true],
                ['time' => '13:00', 'enabled' => false],
                ['time' => '20:30', 'enabled' => true],
            ],
            'days' => [1, 2, 3, 4, 5],
            'only_when_due' => true,
            'skip_if_studied' => false,
        ]);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'times' => 'array',
            'days' => 'array',
            'only_when_due' => 'boolean',
            'skip_if_studied' => 'boolean',
        ];
    }
}
