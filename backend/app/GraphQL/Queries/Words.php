<?php

namespace App\GraphQL\Queries;

use App\GraphQL\Concerns\ResolvesCurrentUser;
use App\Models\Word;
use App\Support\ClientClock;
use Illuminate\Database\Eloquent\Builder;

/**
 * The dictionary list: one language, filtered, searched and sorted.
 */
class Words
{
    use ResolvesCurrentUser;

    public function __construct(private readonly ClientClock $clock) {}

    /**
     * @param  array{language: string, filter?: string, search?: ?string, sort?: string}  $args
     * @return Builder<Word>
     */
    public function __invoke(mixed $root, array $args): Builder
    {
        $query = Word::query()
            ->where('user_id', $this->user()->id)
            ->where('language', $args['language']);

        match ($args['filter'] ?? 'ALL') {
            'DUE' => $query->dueBy($this->clock->endOfToday()),
            'WORDS' => $query->where('kind', Word::KIND_WORD),
            'PHRASES' => $query->where('kind', Word::KIND_PHRASE),
            'LEARNED' => $query->learned(),
            default => null,
        };

        $search = trim($args['search'] ?? '');

        if ($search !== '') {
            $like = '%'.addcslashes(mb_strtolower($search), '%_\\').'%';
            $query->where(fn (Builder $query) => $query
                ->whereRaw('lower(term) like ?', [$like])
                ->orWhereRaw('lower(translations::text) like ?', [$like])
                ->orWhereRaw('lower(coalesce(note, \'\')) like ?', [$like]));
        }

        match ($args['sort'] ?? 'RECENT') {
            'ALPHABETICAL' => $query->orderByRaw('lower(term)'),
            'DUE' => $query->orderByRaw('due_at asc nulls last')->orderBy('created_at'),
            default => $query->latest()->orderByDesc('id'),
        };

        return $query;
    }
}
