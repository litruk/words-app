<?php

use App\Http\Controllers\ResetPasswordController;
use Illuminate\Support\Facades\Route;

// This host serves the GraphQL API (/graphql) and the password reset page.
Route::get('/', fn () => response('Slovnik API', 200, ['Content-Type' => 'text/plain']));

Route::get('/reset-password/{token}', [ResetPasswordController::class, 'show'])->name('password.reset');
Route::post('/reset-password', [ResetPasswordController::class, 'update'])->name('password.update');
