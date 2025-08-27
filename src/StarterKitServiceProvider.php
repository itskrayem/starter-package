<?php

namespace YourOrg\StarterKit;

use Illuminate\Support\ServiceProvider;
use YourOrg\StarterKit\Console\InstallCommand;

class StarterKitServiceProvider extends ServiceProvider
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
