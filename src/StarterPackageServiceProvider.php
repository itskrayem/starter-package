<?php

namespace ItsKrayem\StarterPackage;

use Illuminate\Support\ServiceProvider;
use ItsKrayem\StarterPackage\Console\InstallCommand;
use ItsKrayem\StarterPackage\Console\PageCommand;

class StarterPackageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                PageCommand::class,
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
                    $stubsPath . '/seeders' => database_path('seeders'),
                    $stubsPath . '/migrations' => database_path('migrations'),
                    $stubsPath . '/Policies' => app_path('Policies'),
                ], 'starter-package-stubs');
            }
        }
    }
}
