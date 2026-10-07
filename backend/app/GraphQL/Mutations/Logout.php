<?php

namespace App\GraphQL\Mutations;

use Illuminate\Support\Facades\Auth;

class Logout
{
    public function __invoke(): bool
    {
        Auth::guard('sanctum')->user()?->currentAccessToken()?->delete();

        return true;
    }
}
