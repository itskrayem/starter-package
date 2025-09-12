<?php

namespace ItsKrayem\StarterPackage;

use Illuminate\Support\ServiceProvider;
use ItsKrayem\StarterPackage\Console\CoreCommand;
use ItsKrayem\StarterPackage\Console\PageCommand;
use ItsKrayem\StarterPackage\Console\PermissionsCommand;
use ItsKrayem\StarterPackage\Console\StarterWizardCommand;

class StarterPackageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                CoreCommand::class,
                PageCommand::class,
                PermissionsCommand::class,
                StarterWizardCommand::class,
            ]);
        }
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {

            // Publish stubs to the app folders
            $stubsPath = realpath(__DIR__ . '/stubs');
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
