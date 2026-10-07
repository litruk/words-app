<?php

// The rules this API uses; anything missing falls back to English.

return [
    'array' => 'Поле «:attribute» должно быть списком.',
    'date_format' => 'Поле «:attribute» должно быть в формате :format.',
    'email' => 'Введите корректный адрес электронной почты.',
    'max' => [
        'array' => 'В поле «:attribute» не может быть больше :max элементов.',
        'string' => 'Поле «:attribute» не может быть длиннее :max символов.',
    ],
    'min' => [
        'array' => 'В поле «:attribute» должно быть не меньше :min элементов.',
        'string' => 'Поле «:attribute» должно быть не короче :min символов.',
    ],
    'required' => 'Заполните поле «:attribute».',
    'string' => 'Поле «:attribute» должно быть строкой.',
    'unique' => 'Такой :attribute уже используется.',
    'attributes' => [
        'name' => 'имя',
        'email' => 'email',
        'password' => 'пароль',
        'newPassword' => 'новый пароль',
        'term' => 'слово или фраза',
        'note' => 'заметка',
        'translations' => 'переводы',
        'native_language' => 'родной язык',
        'studied_languages' => 'изучаемые языки',
    ],
];
