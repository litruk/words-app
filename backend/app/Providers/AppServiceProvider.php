<?php

namespace App\Providers;

use App\Models\User;
use App\Support\ClientClock;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One clock per request: the middleware sets the user's time zone on it.
        $this->app->scoped(ClientClock::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The reset link is built from APP_URL, never from the request: the
        // Host header is the caller's to choose, and a link pointing elsewhere
        // would hand the reset token to whoever owns that host.
        ResetPassword::createUrlUsing(fn (User $user, string $token): string => rtrim((string) config('app.url'), '/')
            .route('password.reset', ['token' => $token, 'email' => $user->getEmailForPasswordReset()], false));
    }
}
