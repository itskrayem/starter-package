<?php

namespace ItsKrayem\StarterPackage\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallCommand extends Command
{
    protected $signature = 'starter:install-core {features?* : Optional features like permission}';
    protected $description = 'Install the starter package with optional features';

    public function handle()
    {
        $this->info("🚀 Installing Starter Package Core...");

        // Step 1: Install Nova
        $this->installNova();

        // Step 2: Publish and migrate MediaLibrary
        $this->installMediaLibrary();

        // Step 3: Optional features
        $features = $this->argument('features');
        if (in_array('permission', $features)) {
            $this->installPermission();
        }

        $this->info("✅ Starter Package installed successfully!");
    }

    protected function installNova()
    {
        $this->info("Publishing Laravel Nova assets...");
        // Publish Nova assets only (no migrations)
        $this->callSilent('vendor:publish', [
            '--provider' => 'Laravel\Nova\NovaServiceProvider',
            '--tag' => 'public',
            '--force' => true
        ]);
    }

    protected function installMediaLibrary()
    {
        $this->info("Publishing and migrating Spatie MediaLibrary...");

        // Publish migrations only if they do not exist
        $migrationExists = count(File::glob(database_path('migrations/*_create_media_table.php'))) > 0;
        if (! $migrationExists) {
            $this->callSilent('vendor:publish', [
                '--provider' => "Spatie\MediaLibrary\MediaLibraryServiceProvider",
                '--tag' => 'migrations',
                '--force' => true
            ]);
        }

        // Run migrations
        $this->call('migrate', ['--force' => true]);
    }

    protected function installPermission()
    {
        $this->info("Installing Spatie Laravel Permission...");

        // Publish migrations only if they do not exist
        $migrationExists = count(File::glob(database_path('migrations/*_create_permission_tables.php'))) > 0;
        if (! $migrationExists) {
            $this->callSilent('vendor:publish', [
                '--provider' => "Spatie\Permission\PermissionServiceProvider",
                '--tag' => 'migrations',
                '--force' => true
            ]);
        }

        // Run migrations
        $this->call('migrate', ['--force' => true]);

        // Add HasRoles trait to User model if not exists
        $userFile = app_path('Models/User.php');
        if (File::exists($userFile)) {
            $content = File::get($userFile);
            if (! str_contains($content, 'use Spatie\Permission\Traits\HasRoles;')) {
                $content = preg_replace(
                    '/class User extends Authenticatable/',
                    "use Spatie\Permission\Traits\HasRoles;\n\nclass User extends Authenticatable",
                    $content
                );
                File::put($userFile, $content);
                $this->info("Added HasRoles trait to User model.");
            }
        }
    }
}
