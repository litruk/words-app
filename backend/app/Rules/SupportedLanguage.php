<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A language code from config/languages.php.
 */
class SupportedLanguage implements ValidationRule
{
    public static function passes(mixed $value): bool
    {
        return is_string($value) && array_key_exists($value, config('languages.supported'));
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value !== null && ! self::passes($value)) {
            $fail(__('This language is not supported.'));
        }
    }
}
