<?php

namespace App\Services;

use App\Models\Lookup;
use GraphQL\Error\Error;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use JsonException;

/**
 * Dictionary entries from Gemini: translations into the learner's native
 * language, transcriptions, a meaning, examples, related forms and synonyms.
 * Answers are cached per text and direction for everybody.
 */
class LookupService
{
    public const MAX_LENGTH = 160;

    /**
     * @param  bool  $reverse  the text is in the translation language and the
     *                         entry is for its equivalent in `$language`
     */
    public function lookup(string $text, string $language, string $translationLanguage, bool $reverse = false): Lookup
    {
        $query = $this->normalize($text);

        $cached = Lookup::query()
            ->where('query', $query)
            ->where('language', $language)
            ->where('translation_language', $translationLanguage)
            ->where('reverse', $reverse)
            ->first();

        if ($cached !== null) {
            return $cached;
        }

        $entry = $this->ask($text, $language, $translationLanguage, $reverse);

        return Lookup::query()->firstOrCreate(
            [
                'query' => $query,
                'language' => $language,
                'translation_language' => $translationLanguage,
                'reverse' => $reverse,
            ],
            [
                'found' => $entry['found'],
                'term' => $entry['term'],
                'payload' => $entry,
                'model' => (string) config('services.gemini.model'),
            ],
        );
    }

    /**
     * Cache key for a typed text: case and spacing do not make another word.
     */
    public function normalize(string $text): string
    {
        return Str::limit(mb_strtolower(Str::squish($text)), self::MAX_LENGTH, '');
    }

    /**
     * @return array<string, mixed>
     */
    private function ask(string $text, string $language, string $translationLanguage, bool $reverse): array
    {
        $model = (string) config('services.gemini.model');

        try {
            $response = Http::withHeaders(['x-goog-api-key' => config('services.gemini.key')])
                ->timeout(30)
                ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                    'contents' => [['parts' => [['text' => $this->prompt($text, $language, $translationLanguage, $reverse)]]]],
                    'generationConfig' => [
                        'thinkingConfig' => ['thinkingLevel' => 'LOW'],
                        'responseMimeType' => 'application/json',
                        'responseSchema' => $this->schema(),
                    ],
                ]);
        } catch (ConnectionException $e) {
            Log::warning('Gemini lookup unreachable', ['error' => $e->getMessage()]);

            throw new Error(__('The dictionary is unavailable right now. Try again in a moment.'));
        }

        $content = $response->successful() ? $response->json('candidates.0.content.parts.0.text') : null;

        if (! is_string($content)) {
            Log::warning('Gemini lookup failed', ['status' => $response->status(), 'body' => Str::limit($response->body(), 500)]);

            throw new Error(__('The dictionary is unavailable right now. Try again in a moment.'));
        }

        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($content, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new Error(__('The dictionary is unavailable right now. Try again in a moment.'));
        }

        return $this->clean($decoded, $language);
    }

    private function prompt(string $text, string $language, string $translationLanguage, bool $reverse): string
    {
        $studied = config("languages.supported.{$language}.name");
        $native = config("languages.supported.{$translationLanguage}.name");
        $accents = config("languages.supported.{$language}.accents", []);

        $input = $reverse
            ? "The learner typed this in {$native} and wants the {$studied} word or phrase for it: \"{$text}\"."
            : "The learner typed this {$studied} word or phrase: \"{$text}\".";

        $transcriptions = $accents === []
            ? 'transcriptions: one entry with accent null and the IPA in slashes.'
            : 'transcriptions: one entry per accent - '.collect($accents)->map(fn ($name, $code) => "\"{$code}\" ({$name})")->join(', ').' - with the IPA in slashes.';

        return "You are a learner's dictionary for someone who studies {$studied} and whose native language is {$native}. "
            .$input
            .' Answer with the dictionary entry for it.'
            .' found: false when it is not a real '.($reverse ? $native : $studied).' word, phrase or expression (gibberish, a typo you cannot resolve, another language); then leave the other fields empty.'
            ." term: the {$studied} headword in its dictionary form - fix obvious typos and inflections (\"went\" -> \"to go\" only when it is clearly a form of it; keep a set phrase whole, e.g. \"to come across\", \"it's up to you\"). Keep nouns' natural capitalisation."
            .' kind: "word" for a single word, "phrase" for a phrasal verb, idiom, collocation or longer expression.'
            ." part_of_speech: the part of speech in {$native}, lower case, e.g. the {$native} for \"adjective\" or \"phrasal verb\"; part_of_speech_short: its usual abbreviation in {$native}."
            .' '.$transcriptions
            ." translations: 3 to 6 {$native} translations, the most common sense first, each short (one to three words) and in lower case unless it is a proper noun or a noun that is always capitalised, no duplicates or explanations in brackets."
            .($reverse ? ' The text the learner typed must be among them, first if it fits.' : '')
            ." meaning: a one-sentence learner's-dictionary definition in {$studied}."
            ." examples: two natural, everyday {$studied} sentences using the term, each with its {$native} translation."
            ." forms: up to four related words or forms of the same family (derived adverb, noun, verb...; for a verb its irregular past forms), each with a {$native} translation and the {$native} part-of-speech abbreviation. Empty for phrases."
            ." synonyms: up to four {$studied} synonyms, single words or short phrases.";
    }

    /**
     * @return array<string, mixed>
     */
    private function schema(): array
    {
        $string = ['type' => 'STRING'];
        $nullable = ['type' => 'STRING', 'nullable' => true];

        return [
            'type' => 'OBJECT',
            'properties' => [
                'found' => ['type' => 'BOOLEAN'],
                'term' => $nullable,
                'kind' => ['type' => 'STRING', 'enum' => ['word', 'phrase']],
                'part_of_speech' => $nullable,
                'part_of_speech_short' => $nullable,
                'transcriptions' => [
                    'type' => 'ARRAY',
                    'items' => [
                        'type' => 'OBJECT',
                        'properties' => ['accent' => $nullable, 'ipa' => $string],
                        'required' => ['accent', 'ipa'],
                    ],
                ],
                'translations' => ['type' => 'ARRAY', 'items' => $string],
                'meaning' => $nullable,
                'examples' => [
                    'type' => 'ARRAY',
                    'items' => [
                        'type' => 'OBJECT',
                        'properties' => ['text' => $string, 'translation' => $nullable],
                        'required' => ['text', 'translation'],
                    ],
                ],
                'forms' => [
                    'type' => 'ARRAY',
                    'items' => [
                        'type' => 'OBJECT',
                        'properties' => ['term' => $string, 'translation' => $nullable, 'part_of_speech' => $nullable],
                        'required' => ['term', 'translation', 'part_of_speech'],
                    ],
                ],
                'synonyms' => ['type' => 'ARRAY', 'items' => $string],
            ],
            'required' => [
                'found', 'term', 'kind', 'part_of_speech', 'part_of_speech_short', 'transcriptions',
                'translations', 'meaning', 'examples', 'forms', 'synonyms',
            ],
        ];
    }

    /**
     * The model's answer in the shape the rest of the app relies on.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function clean(array $data, string $language): array
    {
        $strings = fn (mixed $list, int $max): array => collect(is_array($list) ? $list : [])
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn (string $value) => trim($value))
            ->unique(fn (string $value) => mb_strtolower($value))
            ->take($max)
            ->values()
            ->all();

        $term = is_string($data['term'] ?? null) ? trim($data['term']) : '';
        $found = ($data['found'] ?? false) === true && $term !== '';
        $accents = array_keys(config("languages.supported.{$language}.accents", []));

        $transcriptions = collect(is_array($data['transcriptions'] ?? null) ? $data['transcriptions'] : [])
            ->filter(fn ($row) => is_array($row) && is_string($row['ipa'] ?? null) && trim($row['ipa']) !== '')
            ->map(fn (array $row) => [
                'accent' => in_array($row['accent'] ?? null, $accents, true) ? $row['accent'] : null,
                'ipa' => '/'.trim($row['ipa'], " /\t\n").'/',
            ])
            ->unique('accent')
            ->values()
            ->all();

        $pairs = fn (mixed $list, string $key, array $extra) => collect(is_array($list) ? $list : [])
            ->filter(fn ($row) => is_array($row) && is_string($row[$key] ?? null) && trim($row[$key]) !== '')
            ->map(fn (array $row) => collect([$key, ...$extra])
                ->mapWithKeys(fn ($field) => [$field => is_string($row[$field] ?? null) ? trim($row[$field]) : null])
                ->all())
            ->take(4)
            ->values()
            ->all();

        return [
            'found' => $found,
            'term' => $found ? $term : null,
            'kind' => ($data['kind'] ?? null) === 'phrase' ? 'phrase' : 'word',
            'part_of_speech' => $found && is_string($data['part_of_speech'] ?? null) ? $data['part_of_speech'] : null,
            'part_of_speech_short' => $found && is_string($data['part_of_speech_short'] ?? null) ? $data['part_of_speech_short'] : null,
            'transcriptions' => $found ? $transcriptions : [],
            'translations' => $found ? $strings($data['translations'] ?? [], 6) : [],
            'meaning' => $found && is_string($data['meaning'] ?? null) ? $data['meaning'] : null,
            'examples' => $found ? array_slice($pairs($data['examples'] ?? [], 'text', ['translation']), 0, 2) : [],
            'forms' => $found ? $pairs($data['forms'] ?? [], 'term', ['translation', 'part_of_speech']) : [],
            'synonyms' => $found ? $strings($data['synonyms'] ?? [], 4) : [],
        ];
    }
}
