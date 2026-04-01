<?php

namespace App\Filament\Pages;

use Filament\Auth\Pages\Login as FilamentLogin;

class Login extends FilamentLogin
{
    protected static ?string $title = 'Admin Login';

    protected string $view = 'filament.auth.login';

    protected static string $layout = 'filament-panels::components.layout.simple';
}
