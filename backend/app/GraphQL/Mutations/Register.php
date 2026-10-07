<?php

namespace App\GraphQL\Mutations;

use App\Models\User;

class Register
{
    /**
     * @param  array{name: string, email: string, password: string, native_language?: ?string}  $args
     * @return array{token: string, user: User}
     */
    public function __invoke(mixed $root, array $args): array
    {
        $user = User::create([
            'name' => $args['name'],
            'email' => $args['email'],
            'password' => $args['password'],
            // The app suggests the system language; it is confirmed on the next step.
            'native_language' => $args['native_language'] ?? app()->getLocale(),
        ]);

        return [
            'token' => $user->createToken('api')->plainTextToken,
            'user' => $user,
        ];
    }
}
