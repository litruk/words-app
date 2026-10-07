<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One answer given in a study session.
 */
#[Fillable([
    'user_id', 'word_id', 'language', 'rating', 'state_before',
    'interval_before', 'interval_after', 'duration_ms', 'reviewed_at',
])]
class Review extends Model
{
    public $timestamps = false;

    /**
     * @return BelongsTo<Word, $this>
     */
    public function word(): BelongsTo
    {
        return $this->belongsTo(Word::class);
    }

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'interval_before' => 'float',
            'interval_after' => 'float',
            'reviewed_at' => 'datetime',
        ];
    }
}
