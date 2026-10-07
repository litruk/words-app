<?php

namespace App\GraphQL\Mutations;

use App\GraphQL\Concerns\ResolvesCurrentUser;
use App\Models\User;
use GraphQL\Error\Error;

class UpdateProfile
{
    use ResolvesCurrentUser;

    /**
     * @param  array{name: string, email?: ?string}  $args
     */
    public function __invoke(mixed $root, array $args): User
    {
        $user = $this->user();
        $email = $args['email'] ?? $user->email;

        if ($email !== $user->email && User::where('email', $email)->exists()) {
            throw new Error(__('validation.unique', ['attribute' => __('validation.attributes.email')]));
        }

        $user->update(['name' => $args['name'], 'email' => $email]);

        return $user;
    }
}
