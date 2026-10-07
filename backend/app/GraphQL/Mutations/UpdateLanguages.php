<?php

namespace App\GraphQL\Mutations;

use App\GraphQL\Concerns\ResolvesCurrentUser;
use App\Models\User;
use App\Rules\SupportedLanguage;
use GraphQL\Error\Error;
use Illuminate\Support\Facades\DB;

class UpdateLanguages
{
    use ResolvesCurrentUser;

    /**
     * @param  array{native_language: string, studied_languages: list<string>}  $args
     */
    public function __invoke(mixed $root, array $args): User
    {
        $native = $args['native_language'];
        $studied = array_values(array_unique($args['studied_languages']));

        foreach ($studied as $language) {
            if (! SupportedLanguage::passes($language)) {
                throw new Error(__('This language is not supported.'));
            }
        }

        if (in_array($native, $studied, true)) {
            throw new Error(__('Your native language cannot be one you are learning.'));
        }

        $user = $this->user();

        DB::transaction(function () use ($user, $native, $studied) {
            $user->update(['native_language' => $native]);
            $user->languages()->whereNotIn('language', $studied)->delete();

            foreach ($studied as $position => $language) {
                $user->languages()->updateOrCreate(['language' => $language], ['position' => $position]);
            }
        });

        return $user->load('languages');
    }
}
