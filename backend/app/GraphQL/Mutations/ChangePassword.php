<?php

namespace App\GraphQL\Mutations;

use App\Models\User;
use GraphQL\Error\Error;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class ChangePassword
{
    /**
     * @param  array{currentPassword: string, newPassword: string}  $args
     */
    public function __invoke(mixed $root, array $args): bool
    {
        /** @var User $user */
        $user = Auth::guard('sanctum')->user();

        if (! Hash::check($args['currentPassword'], $user->password)) {
            throw new Error(__('Current password is incorrect.'));
        }

        $user->password = $args['newPassword'];
        $user->save();

        $currentTokenId = $user->currentAccessToken()?->id;
        $user->tokens()->when($currentTokenId, fn ($query) => $query->where('id', '!=', $currentTokenId))->delete();

        return true;
    }
}
