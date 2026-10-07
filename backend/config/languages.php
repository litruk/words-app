<?php

/*
|--------------------------------------------------------------------------
| Languages
|--------------------------------------------------------------------------
|
| Languages a user can learn or have as their native language. The name is
| what the language model is told; how a language is shown, spoken and
| recognised is the app's business (app/src/utils/languages.ts).
|
*/

return [
    'supported' => [
        'en' => ['name' => 'English', 'accents' => ['uk' => 'British', 'us' => 'American']],
        'de' => ['name' => 'German', 'accents' => []],
        'ru' => ['name' => 'Russian', 'accents' => []],
        'uk' => ['name' => 'Ukrainian', 'accents' => []],
    ],

    // New cards introduced per language and day, on top of the due reviews.
    'new_cards_per_day' => 10,
];
