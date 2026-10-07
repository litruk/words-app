<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;

abstract class TestCase extends BaseTestCase
{
    use MakesGraphQLRequests;

    /**
     * A signed-in user who speaks `$native` and learns `$studied`.
     *
     * @param  list<string>  $studied
     */
    protected function signIn(string $native = 'ru', array $studied = ['en']): User
    {
        $user = User::factory()->create(['native_language' => $native]);

        foreach ($studied as $position => $language) {
            $user->languages()->create(['language' => $language, 'position' => $position]);
        }

        Sanctum::actingAs($user);

        return $user;
    }
}
