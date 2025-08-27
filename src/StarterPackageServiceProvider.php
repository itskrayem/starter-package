<?php

namespace ItsKrayem\StarterPackage;

use Illuminate\Support\ServiceProvider;
use ItsKrayem\StarterPackage\Console\InstallCommand;

class StarterPackageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
            ]);
        }
    }
}
