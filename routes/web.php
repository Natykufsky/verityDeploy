<?php

use App\Http\Controllers\GitHubOAuthController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');
Route::webhooks('webhooks/github');
Route::get('/github/oauth/redirect', [GitHubOAuthController::class, 'redirect'])
    ->name('github.oauth.redirect');
Route::get('/github/oauth/callback', [GitHubOAuthController::class, 'callback'])
    ->name('github.oauth.callback');
