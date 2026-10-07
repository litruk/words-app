<?php

namespace App\Http\Middleware;

use App\Support\ClientClock;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Speaks the app's interface language (Accept-Language) in error messages
 * and mails, and counts days in the app's time zone (X-Timezone).
 */
class ApplyClientPreferences
{
    /** Interface languages with translations; anything else falls back to English. */
    public const LOCALES = ['en', 'ru', 'uk', 'de'];

    public function __construct(private readonly ClientClock $clock) {}

    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale($request->getPreferredLanguage(self::LOCALES) ?? 'en');
        $this->clock->useTimezone($request->header('X-Timezone'));

        return $next($request);
    }
}
