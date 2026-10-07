<?php

namespace App\GraphQL\Mutations;

use Illuminate\Support\Facades\Password;

class ForgotPassword
{
    /**
     * Mails a link to the password reset page (routes/web.php), in the
     * account's language. The answer is the same for unknown addresses and
     * throttled requests, so it can't be used to probe for accounts.
     *
     * @param  array{email: string}  $args
     */
    public function __invoke(mixed $root, array $args): bool
    {
        Password::sendResetLink(['email' => $args['email']]);

        return true;
    }
}
