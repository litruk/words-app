<?php

namespace App\GraphQL\Queries;

use App\Models\Word;

class SuggestedTranslations
{
    /**
     * @return list<string>
     */
    public function __invoke(Word $word): array
    {
        if ($word->lookup === null || $word->lookup->translation_language !== $word->translation_language) {
            return [];
        }

        $chosen = array_map('mb_strtolower', $word->translations);

        return collect($word->lookup->payload['translations'] ?? [])
            ->reject(fn (string $translation) => in_array(mb_strtolower($translation), $chosen, true))
            ->values()
            ->all();
    }
}
