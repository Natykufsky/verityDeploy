<?php

namespace App\Providers;

use App\Services\Terminal\SshTerminalTransport;
use App\Services\Terminal\TerminalTransport;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(TerminalTransport::class, SshTerminalTransport::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $compiledPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'veritydeploy-blade';

        config(['view.compiled' => $compiledPath]);
        File::ensureDirectoryExists($compiledPath);

        FilamentAsset::register([
            Js::make('deployment-stream')
                ->html(File::get(resource_path('js/deployment-stream.js'))),
        ]);
    }
}
