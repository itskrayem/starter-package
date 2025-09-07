<?php

namespace ItsKrayem\StarterPackage;

use Illuminate\Support\ServiceProvider;
use ItsKrayem\StarterPackage\Console\InstallCommand;

class StarterPackageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
            ]);
        }
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {

            // Publish stubs to the app folders
            $stubsPath = realpath(__DIR__ . '/../stubs');
            if ($stubsPath && is_dir($stubsPath)) {
                $this->publishes([
                    $stubsPath . '/models' => app_path('Models'),
                    $stubsPath . '/nova'   => app_path('Nova'),
                ], 'starter-package-stubs');
            }
        }
    }
}
