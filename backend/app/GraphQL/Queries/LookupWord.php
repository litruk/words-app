<?php

namespace App\GraphQL\Queries;

use App\GraphQL\Concerns\ResolvesCurrentUser;
use App\Services\LookupService;

class LookupWord
{
    use ResolvesCurrentUser;

    public function __construct(private readonly LookupService $lookups) {}

    /**
     * @param  array{text: string, language: string, reverse?: bool}  $args
     * @return array<string, mixed>
     */
    public function __invoke(mixed $root, array $args): array
    {
        $user = $this->user();
        $language = $this->studiedLanguage($args['language']);
        $lookup = $this->lookups->lookup($args['text'], $language, $user->native_language, $args['reverse'] ?? false);

        $existing = $lookup->term === null ? null : $user->words()
            ->where('language', $language)
            ->whereRaw('lower(term) = ?', [mb_strtolower($lookup->term)])
            ->value('id');

        return [
            ...$lookup->payload,
            'id' => $lookup->id,
            'found' => $lookup->found,
            'term' => $lookup->term,
            'language' => $lookup->language,
            'translation_language' => $lookup->translation_language,
            'existing_word_id' => $existing,
        ];
    }
}
