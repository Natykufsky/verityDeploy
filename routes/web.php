<?php

use App\Http\Controllers\GitHubOAuthController;
use App\Http\Controllers\SiteTerminalFeedController;
use App\Http\Controllers\ServerTerminalFeedController;
use App\Http\Controllers\TeamInvitationController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');
Route::webhooks('webhooks/github');
Route::get('/github/oauth/redirect', [GitHubOAuthController::class, 'redirect'])
    ->name('github.oauth.redirect');
Route::get('/github/oauth/callback', [GitHubOAuthController::class, 'callback'])
    ->name('github.oauth.callback');
Route::get('/team-invitations/{token}', [TeamInvitationController::class, 'show'])
    ->name('team-invitations.show');
Route::post('/team-invitations/{token}', [TeamInvitationController::class, 'accept'])
    ->name('team-invitations.accept');
Route::get('/servers/{record}/terminal-feed', ServerTerminalFeedController::class)
    ->middleware(['auth'])
    ->name('servers.terminal-feed');
Route::get('/sites/{record}/terminal-feed', SiteTerminalFeedController::class)
    ->middleware(['auth'])
    ->name('sites.terminal-feed');
