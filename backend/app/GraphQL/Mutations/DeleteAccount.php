<?php

namespace App\GraphQL\Mutations;

use App\GraphQL\Concerns\ResolvesCurrentUser;
use GraphQL\Error\Error;
use Illuminate\Support\Facades\Hash;

class DeleteAccount
{
    use ResolvesCurrentUser;

    /**
     * @param  array{password: string}  $args
     */
    public function __invoke(mixed $root, array $args): bool
    {
        $user = $this->user();

        if (! Hash::check($args['password'], $user->password)) {
            throw new Error(__('Current password is incorrect.'));
        }

        // Words, reviews, languages and reminders go with it (cascading keys).
        $user->tokens()->delete();
        $user->delete();

        return true;
    }
}
