<?php

namespace App\Models;

use Database\Factories\WordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A word or phrase in a user's dictionary, together with its spaced
 * repetition state (see App\Services\Scheduler).
 *
 * @property list<array{accent: ?string, ipa: string}> $transcriptions
 * @property list<string> $translations
 * @property list<array{text: string, translation: ?string}> $examples
 * @property list<array{term: string, translation: ?string, part_of_speech: ?string}> $forms
 * @property list<string> $synonyms
 * @property ?Carbon $due_at
 * @property ?Carbon $last_reviewed_at
 */
#[Fillable([
    'user_id', 'lookup_id', 'language', 'translation_language', 'term', 'kind',
    'part_of_speech', 'part_of_speech_short', 'transcriptions', 'translations', 'note',
    'meaning', 'examples', 'forms', 'synonyms', 'source',
    'state', 'step', 'due_at', 'interval', 'ease', 'reps', 'lapses', 'last_reviewed_at',
])]
class Word extends Model
{
    /** @use HasFactory<WordFactory> */
    use HasFactory;

    public const KIND_WORD = 'word';

    public const KIND_PHRASE = 'phrase';

    public const SOURCE_TEXT = 'text';

    public const SOURCE_VOICE = 'voice';

    public const STATE_NEW = 'new';

    public const STATE_LEARNING = 'learning';

    public const STATE_REVIEW = 'review';

    public const STATE_RELEARNING = 'relearning';

    /** A card whose interval reached this many days counts as learnt. */
    public const LEARNED_INTERVAL = 30;

    /** @var array<string, mixed> */
    protected $attributes = [
        'transcriptions' => '[]',
        'translations' => '[]',
        'examples' => '[]',
        'forms' => '[]',
        'synonyms' => '[]',
        'state' => self::STATE_NEW,
        'step' => 0,
        'interval' => 0,
        'ease' => 2.5,
        'reps' => 0,
        'lapses' => 0,
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Lookup, $this>
     */
    public function lookup(): BelongsTo
    {
        return $this->belongsTo(Lookup::class);
    }

    /**
     * Cards waiting for review by the given moment; new cards are not "due",
     * they are introduced separately, a few per day.
     *
     * @param  Builder<Word>  $query
     */
    public function scopeDueBy(Builder $query, Carbon $moment): void
    {
        $query->where('state', '!=', self::STATE_NEW)->where('due_at', '<=', $moment->copy()->utc());
    }

    /**
     * @param  Builder<Word>  $query
     */
    public function scopeLearned(Builder $query): void
    {
        $query->where('state', self::STATE_REVIEW)->where('interval', '>=', self::LEARNED_INTERVAL);
    }

    /**
     * How well the card is known, 0 (new) to 5 (learnt), from its interval.
     */
    public function level(): int
    {
        if ($this->state === self::STATE_NEW) {
            return 0;
        }

        if ($this->state !== self::STATE_REVIEW || $this->interval < 1) {
            return 1;
        }

        return match (true) {
            $this->interval < 3 => 2,
            $this->interval < 10 => 3,
            $this->interval < self::LEARNED_INTERVAL => 4,
            default => 5,
        };
    }

    protected function casts(): array
    {
        return [
            'transcriptions' => 'array',
            'translations' => 'array',
            'examples' => 'array',
            'forms' => 'array',
            'synonyms' => 'array',
            'step' => 'integer',
            'due_at' => 'datetime',
            'interval' => 'float',
            'ease' => 'float',
            'reps' => 'integer',
            'lapses' => 'integer',
            'last_reviewed_at' => 'datetime',
        ];
    }
}
