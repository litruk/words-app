<?php

namespace App\GraphQL\Mutations;

use App\GraphQL\Concerns\ResolvesCurrentUser;
use App\Models\Lookup;
use App\Models\Word;
use App\Services\LookupService;
use GraphQL\Error\Error;
use Illuminate\Support\Str;

class AddWord
{
    use ResolvesCurrentUser;

    /** Translations taken when the word arrives without any (saved offline). */
    private const AUTOMATIC_TRANSLATIONS = 2;

    public function __construct(private readonly LookupService $lookups) {}

    /**
     * @param  array{language: string, term: string, translations: list<string>, note?: ?string, source?: string, lookup_id?: ?string}  $args
     */
    public function __invoke(mixed $root, array $args): Word
    {
        $user = $this->user();
        $language = $this->studiedLanguage($args['language']);
        $term = Str::squish($args['term']);
        $translations = self::cleanTranslations($args['translations']);
        $note = isset($args['note']) ? (Str::squish($args['note']) ?: null) : null;

        $exists = $user->words()
            ->where('language', $language)
            ->whereRaw('lower(term) = ?', [mb_strtolower($term)])
            ->exists();

        if ($exists) {
            throw new Error(__('This word is already in your dictionary.'));
        }

        $lookup = $this->lookupFor($args['lookup_id'] ?? null, $term, $language, $user->native_language);
        $details = $lookup !== null && $lookup->found && $lookup->language === $language ? $lookup->payload : [];

        // A word saved without a connection gets its translations filled in here.
        if ($translations === [] && $note === null) {
            $translations = array_slice($details['translations'] ?? [], 0, self::AUTOMATIC_TRANSLATIONS);
        }

        if ($translations === [] && $note === null) {
            throw new Error(__('Choose a translation or write your own.'));
        }

        return $user->words()->create([
            'lookup_id' => $lookup?->id,
            'language' => $language,
            'translation_language' => $user->native_language,
            'term' => $term,
            'kind' => $details['kind'] ?? (str_contains($term, ' ') ? Word::KIND_PHRASE : Word::KIND_WORD),
            'part_of_speech' => $details['part_of_speech'] ?? null,
            'part_of_speech_short' => $details['part_of_speech_short'] ?? null,
            'transcriptions' => $details['transcriptions'] ?? [],
            'translations' => $translations,
            'note' => $note,
            'meaning' => $details['meaning'] ?? null,
            'examples' => $details['examples'] ?? [],
            'forms' => $details['forms'] ?? [],
            'synonyms' => $details['synonyms'] ?? [],
            'source' => $args['source'] ?? Word::SOURCE_TEXT,
        ]);
    }

    /**
     * The dictionary answer the app showed, or one looked up now for a word
     * typed while offline. A dictionary that is down only leaves the details
     * out; a word with a translation of its own is still saved.
     */
    private function lookupFor(?string $lookupId, string $term, string $language, string $translationLanguage): ?Lookup
    {
        if ($lookupId !== null) {
            return Lookup::find($lookupId);
        }

        try {
            return $this->lookups->lookup($term, $language, $translationLanguage);
        } catch (Error) {
            return null;
        }
    }

    /**
     * @param  list<string>  $translations
     * @return list<string>
     */
    public static function cleanTranslations(array $translations): array
    {
        return collect($translations)
            ->map(fn (string $translation) => Str::squish($translation))
            ->filter()
            ->unique(fn (string $translation) => mb_strtolower($translation))
            ->values()
            ->all();
    }
}
