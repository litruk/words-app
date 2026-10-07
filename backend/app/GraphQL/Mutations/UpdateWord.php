<?php

namespace App\GraphQL\Mutations;

use App\GraphQL\Concerns\ResolvesCurrentUser;
use App\Models\Word;
use GraphQL\Error\Error;
use Illuminate\Support\Str;

class UpdateWord
{
    use ResolvesCurrentUser;

    /**
     * @param  array{id: string, term?: ?string, translations?: ?list<string>, note?: ?string}  $args
     */
    public function __invoke(mixed $root, array $args): Word
    {
        $word = $this->ownWord($args['id']);

        if (isset($args['term'])) {
            $term = Str::squish($args['term']);

            $taken = $this->user()->words()
                ->where('language', $word->language)
                ->whereKeyNot($word->id)
                ->whereRaw('lower(term) = ?', [mb_strtolower($term)])
                ->exists();

            if ($term === '' || $taken) {
                throw new Error(__('This word is already in your dictionary.'));
            }

            $word->term = $term;
        }

        if (isset($args['translations'])) {
            $word->translations = AddWord::cleanTranslations($args['translations']);
        }

        if (array_key_exists('note', $args)) {
            $word->note = isset($args['note']) ? (Str::squish($args['note']) ?: null) : null;
        }

        if ($word->translations === [] && $word->note === null) {
            throw new Error(__('Choose a translation or write your own.'));
        }

        $word->save();

        return $word;
    }
}
