<?php

namespace App\Services;

use App\Models\Review;
use App\Models\User;
use App\Models\Word;
use App\Support\ClientClock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * What there is to study: cards due today, the day's budget of new cards,
 * the session queue, and how much will be waiting at a given moment.
 */
class StudyPlanner
{
    /** Roughly how long one card takes, for the "≈ 8 min" estimates. */
    public const SECONDS_PER_CARD = 20;

    public function __construct(private readonly ClientClock $clock) {}

    /**
     * Due reviews and today's new cards for one language.
     *
     * @return array{due: int, new: int, total: int, minutes: int}
     */
    public function today(User $user, string $language): array
    {
        $due = $this->words($user, $language)->dueBy($this->clock->endOfToday())->count();
        $new = $this->newCardsLeft($user, $language, $this->clock->now());

        return [
            'due' => $due,
            'new' => $new,
            'total' => $due + $new,
            'minutes' => $this->minutes($due + $new),
        ];
    }

    /**
     * The next day after today with reviews waiting, and how many.
     *
     * @return array{at: ?Carbon, count: int}
     */
    public function nextReview(User $user, string $language): array
    {
        $first = $this->words($user, $language)
            ->where('state', '!=', Word::STATE_NEW)
            ->where('due_at', '>', $this->clock->endOfToday())
            ->min('due_at');

        if ($first === null) {
            return ['at' => null, 'count' => 0];
        }

        $at = $this->clock->local(Carbon::parse($first));
        $count = $this->words($user, $language)
            ->where('state', '!=', Word::STATE_NEW)
            ->where('due_at', '>', $this->clock->endOfToday())
            ->where('due_at', '<=', $at->copy()->endOfDay()->utc())
            ->count();

        return ['at' => $at, 'count' => $count];
    }

    /**
     * Cards for a study session: what is due first, oldest due first, then
     * today's new cards in the order they were added. With `wordIds` the
     * session is exactly those cards (a single card from its page, or the
     * ones answered "again" at the end of a session).
     *
     * @param  list<string|int>|null  $wordIds
     * @return Collection<int, Word>
     */
    public function queue(User $user, string $language, ?array $wordIds = null): Collection
    {
        if ($wordIds !== null) {
            return $user->words()->whereIn('id', $wordIds)->get()
                ->sortBy(fn (Word $word) => array_search((string) $word->id, array_map('strval', $wordIds), true))
                ->values();
        }

        $due = $this->words($user, $language)
            ->dueBy($this->clock->endOfToday())
            ->orderBy('due_at')
            ->get();

        $new = $this->words($user, $language)
            ->where('state', Word::STATE_NEW)
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit($this->newCardsLeft($user, $language, $this->clock->now()))
            ->get();

        return $due->concat($new)->values();
    }

    /**
     * How much will be waiting at each moment, for the reminders the app
     * schedules ahead: the cards due by then plus that day's new cards.
     *
     * @param  list<Carbon>  $moments
     * @return list<array{at: Carbon, count: int, minutes: int, first_term: ?string}>
     */
    public function forecast(User $user, array $moments): array
    {
        $languages = $user->studiedLanguages();

        return array_map(function (Carbon $at) use ($user, $languages) {
            $due = Word::query()
                ->where('user_id', $user->id)
                ->whereIn('language', $languages)
                ->dueBy($at)
                ->orderBy('due_at');

            $count = (clone $due)->count();
            $first = (clone $due)->value('term');

            foreach ($languages as $language) {
                $new = $this->newCardsLeft($user, $language, $at);
                $count += $new;

                if ($first === null && $new > 0) {
                    $first = $this->words($user, $language)
                        ->where('state', Word::STATE_NEW)
                        ->orderBy('created_at')
                        ->value('term');
                }
            }

            return ['at' => $at, 'count' => $count, 'minutes' => $this->minutes($count), 'first_term' => $first];
        }, $moments);
    }

    /**
     * Counts behind the dictionary tabs and the settings list.
     *
     * @return array{language: string, total: int, due: int, learned: int, new: int}
     */
    public function stats(User $user, string $language): array
    {
        return [
            'language' => $language,
            'total' => $this->words($user, $language)->count(),
            'due' => $this->words($user, $language)->dueBy($this->clock->endOfToday())->count(),
            'learned' => $this->words($user, $language)->learned()->count(),
            'new' => $this->words($user, $language)->where('state', Word::STATE_NEW)->count(),
        ];
    }

    public function studiedToday(User $user): bool
    {
        return Review::query()
            ->where('user_id', $user->id)
            ->where('reviewed_at', '>=', $this->clock->startOfToday())
            ->exists();
    }

    /**
     * New cards still allowed on the day of `$at`: the daily budget minus
     * what that day already introduced, capped by the new cards there are.
     */
    public function newCardsLeft(User $user, string $language, Carbon $at): int
    {
        $day = $this->clock->local($at);
        $introduced = Review::query()
            ->where('user_id', $user->id)
            ->where('language', $language)
            ->where('state_before', Word::STATE_NEW)
            ->whereBetween('reviewed_at', [$day->copy()->startOfDay()->utc(), $day->copy()->endOfDay()->utc()])
            ->count();

        $budget = max(0, (int) config('languages.new_cards_per_day') - $introduced);

        if ($budget === 0) {
            return 0;
        }

        return min($budget, $this->words($user, $language)->where('state', Word::STATE_NEW)->count());
    }

    private function minutes(int $cards): int
    {
        return (int) ceil($cards * self::SECONDS_PER_CARD / 60);
    }

    /**
     * @return Builder<Word>
     */
    private function words(User $user, string $language): Builder
    {
        return Word::query()->where('user_id', $user->id)->where('language', $language);
    }
}
