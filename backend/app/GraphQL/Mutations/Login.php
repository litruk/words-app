<?php

namespace App\GraphQL\Mutations;

use App\Models\User;
use GraphQL\Error\Error;
use Illuminate\Support\Facades\Hash;

class Login
{
    /**
     * @param  array{email: string, password: string}  $args
     * @return array{token: string, user: User}
     */
    public function __invoke(mixed $root, array $args): array
    {
        $user = User::where('email', $args['email'])->first();

        if (! $user || ! Hash::check($args['password'], $user->password)) {
            throw new Error(__('These credentials do not match our records.'));
        }

        return [
            'token' => $user->createToken('api')->plainTextToken,
            'user' => $user,
        ];
    }
}
