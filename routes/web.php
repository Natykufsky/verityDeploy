<?php

use App\Http\Controllers\GitHubOAuthController;
use App\Http\Controllers\SiteTerminalFeedController;
use App\Http\Controllers\ServerTerminalFeedController;
use App\Http\Controllers\ServerTerminalSessionController;
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
Route::post('/servers/{record}/terminal-session/open', [ServerTerminalSessionController::class, 'open'])
    ->middleware(['auth'])
    ->name('servers.terminal-session.open');
Route::post('/servers/{record}/terminal-session/heartbeat', [ServerTerminalSessionController::class, 'heartbeat'])
    ->middleware(['auth'])
    ->name('servers.terminal-session.heartbeat');
Route::post('/servers/{record}/terminal-session/close', [ServerTerminalSessionController::class, 'close'])
    ->middleware(['auth'])
    ->name('servers.terminal-session.close');
Route::get('/sites/{record}/terminal-feed', SiteTerminalFeedController::class)
    ->middleware(['auth'])
    ->name('sites.terminal-feed');
