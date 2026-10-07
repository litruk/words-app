<?php

// The rules this API uses; anything missing falls back to English.

return [
    'array' => 'Das Feld „:attribute“ muss eine Liste sein.',
    'date_format' => 'Das Feld „:attribute“ muss dem Format :format entsprechen.',
    'email' => 'Bitte gib eine gültige E-Mail-Adresse ein.',
    'max' => [
        'array' => 'Das Feld „:attribute“ darf höchstens :max Einträge haben.',
        'string' => 'Das Feld „:attribute“ darf höchstens :max Zeichen lang sein.',
    ],
    'min' => [
        'array' => 'Das Feld „:attribute“ muss mindestens :min Einträge haben.',
        'string' => 'Das Feld „:attribute“ muss mindestens :min Zeichen lang sein.',
    ],
    'required' => 'Bitte fülle das Feld „:attribute“ aus.',
    'string' => 'Das Feld „:attribute“ muss ein Text sein.',
    'unique' => 'Der Wert für „:attribute“ wird bereits verwendet.',
    'attributes' => [
        'name' => 'Name',
        'email' => 'E-Mail',
        'password' => 'Passwort',
        'newPassword' => 'neues Passwort',
        'term' => 'Wort oder Ausdruck',
        'note' => 'Notiz',
        'translations' => 'Übersetzungen',
        'native_language' => 'Muttersprache',
        'studied_languages' => 'Lernsprachen',
    ],
];
