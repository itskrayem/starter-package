<?php

namespace ItsKrayem\StarterPackage;

use Illuminate\Support\ServiceProvider;
use ItsKrayem\StarterPackage\Console\InstallCommand;

class StarterPackageServiceProvider extends ServiceProvider
{
    /**
     * Register any package services.
     */
    public function register(): void
    {
        // Register your install command
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
            ]);
        }
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {

            // Publish stubs to the app folders
            $this->publishes([
                __DIR__ . '/../stubs/models' => app_path('Models'),
                __DIR__ . '/../stubs/nova'   => app_path('Nova'),
            ], 'starter-package-stubs');

            // You can publish migrations or config files here if needed
        }
    }
}
