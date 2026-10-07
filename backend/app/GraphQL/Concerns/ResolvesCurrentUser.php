<?php

namespace App\GraphQL\Concerns;

use App\Models\User;
use App\Models\Word;
use GraphQL\Error\Error;
use Illuminate\Support\Facades\Auth;

trait ResolvesCurrentUser
{
    protected function user(): User
    {
        /** @var User $user */
        $user = Auth::guard('sanctum')->user();

        return $user;
    }

    /**
     * One of the current user's words; anybody else's is reported as missing.
     */
    protected function ownWord(string|int $id): Word
    {
        $word = $this->user()->words()->find($id);

        if ($word === null) {
            throw new Error(__('This word was not found.'));
        }

        return $word;
    }

    /**
     * A language the current user learns.
     */
    protected function studiedLanguage(string $language): string
    {
        if (! in_array($language, $this->user()->studiedLanguages(), true)) {
            throw new Error(__('You are not learning this language.'));
        }

        return $language;
    }
}
