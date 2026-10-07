<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A dictionary answer from the language model, shared by every user.
 *
 * `payload` holds the entry as the model gave it: kind, part of speech,
 * transcriptions, candidate translations, meaning, examples, forms, synonyms.
 *
 * @property array<string, mixed> $payload
 */
#[Fillable(['query', 'language', 'translation_language', 'reverse', 'found', 'term', 'payload', 'model'])]
class Lookup extends Model
{
    protected function casts(): array
    {
        return [
            'reverse' => 'boolean',
            'found' => 'boolean',
            'payload' => 'array',
        ];
    }
}
