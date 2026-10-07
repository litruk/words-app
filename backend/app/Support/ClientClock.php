<?php

namespace App\Support;

use DateTimeZone;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * "Today" as the person using the app sees it. The app sends its time zone
 * with every request (X-Timezone); due counts, the new-card budget and
 * "already studied today" all follow the user's calendar day, not UTC's.
 */
class ClientClock
{
    private string $timezone = 'UTC';

    public function useTimezone(?string $timezone): void
    {
        if ($timezone === null || $timezone === '') {
            return;
        }

        try {
            new DateTimeZone($timezone);
            $this->timezone = $timezone;
        } catch (Throwable) {
            // An unknown zone leaves the default in place.
        }
    }

    public function timezone(): string
    {
        return $this->timezone;
    }

    public function now(): Carbon
    {
        return Carbon::now($this->timezone);
    }

    /**
     * Day boundaries come back in UTC, the zone timestamps are stored in: a
     * Carbon in another zone would be compared by its wall-clock digits.
     */
    public function startOfToday(): Carbon
    {
        return $this->now()->startOfDay()->utc();
    }

    public function endOfToday(): Carbon
    {
        return $this->now()->endOfDay()->utc();
    }

    /**
     * The calendar day of a moment, in the user's time zone.
     */
    public function local(Carbon $moment): Carbon
    {
        return $moment->copy()->setTimezone($this->timezone);
    }
}
