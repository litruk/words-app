<?php

// The rules this API uses; anything missing falls back to English.

return [
    'array' => 'Поле «:attribute» має бути списком.',
    'date_format' => 'Поле «:attribute» має бути у форматі :format.',
    'email' => 'Введіть коректну адресу електронної пошти.',
    'max' => [
        'array' => 'У полі «:attribute» не може бути більше ніж :max елементів.',
        'string' => 'Поле «:attribute» не може бути довшим за :max символів.',
    ],
    'min' => [
        'array' => 'У полі «:attribute» має бути щонайменше :min елементів.',
        'string' => 'Поле «:attribute» має містити щонайменше :min символів.',
    ],
    'required' => 'Заповніть поле «:attribute».',
    'string' => 'Поле «:attribute» має бути рядком.',
    'unique' => 'Такий :attribute вже використовується.',
    'attributes' => [
        'name' => 'ім\'я',
        'email' => 'email',
        'password' => 'пароль',
        'newPassword' => 'новий пароль',
        'term' => 'слово або фраза',
        'note' => 'нотатка',
        'translations' => 'переклади',
        'native_language' => 'рідна мова',
        'studied_languages' => 'мови, які ви вивчаєте',
    ],
];
