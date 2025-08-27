<?php

namespace ItsKrayem\StarterPackage\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallCommand extends Command
{
    protected $signature = 'starter:install-core {features?*}';
    protected $description = 'Install starter package: Nova, MediaLibrary, optional features';

    public function handle(): int
    {
        $this->info("🚀 Installing Starter Package Core...");

        // Step 1: Install Nova
        $this->installNova();

        // Step 2: MediaLibrary
        $this->installMediaLibrary();

        // Step 3: Optional features (like permission)
        $features = $this->argument('features') ?? [];
        foreach ($features as $feature) {
            if ($feature === 'permission') {
                $this->installPermission();
            }
        }

        $this->info("🎉 Starter Package installation complete!");
        return self::SUCCESS;
    }

    protected function installNova(): void
    {
        $this->info("Checking Laravel Nova...");

        // Step 1: Add Nova repository
        exec('composer config repositories.nova composer https://nova.laravel.com');

        // Step 2: Check if Nova is installed
        if (! class_exists(\Laravel\Nova\Nova::class)) {

            $this->info("Installing Laravel Nova via Composer...");
            $output = [];
            $status = null;
            exec('composer require laravel/nova:^5.0', $output, $status);

            if ($status !== 0) {
                $this->error("❌ Nova installation failed. Make sure you configured your credentials: composer config http-basic.nova.laravel.com <EMAIL> <KEY>");
                return;
            }

            $this->info("✅ Laravel Nova installed.");
        } else {
            $this->line("Laravel Nova already installed, skipping Composer install.");
        }

        // Step 3: Publish Nova assets/migrations
        $novaMigration = database_path('migrations/*_create_action_events_table.php');
        if (empty(File::glob($novaMigration))) {
            $this->callSilent('vendor:publish', [
                '--provider' => 'Laravel\Nova\NovaServiceProvider',
                '--force' => true
            ]);
            $this->info("✅ Nova assets and migrations published.");
        } else {
            $this->line("Nova migrations already exist, skipping publish.");
        }

        // Step 4: Run migrations
        $this->callSilent('migrate', ['--force' => true]);
        $this->info("✅ Nova migrations applied.");
    }

    protected function installMediaLibrary(): void
    {
        $this->info("Publishing Spatie MediaLibrary assets/migrations...");

        $mediaMigration = database_path('migrations/*_create_media_table.php');
        if (empty(File::glob($mediaMigration))) {
            $this->callSilent('vendor:publish', [
                '--provider' => 'Spatie\MediaLibrary\MediaLibraryServiceProvider',
                '--force' => true
            ]);
            $this->info("✅ MediaLibrary assets and migrations published.");
        } else {
            $this->line("MediaLibrary migrations already exist, skipping publish.");
        }

        $this->callSilent('migrate', ['--force' => true]);
        $this->info("✅ MediaLibrary migrations applied.");
    }

    protected function installPermission(): void
    {
        $this->info("Installing Spatie Permission...");

        $permissionMigration = database_path('migrations/*_create_permission_tables.php');
        if (empty(File::glob($permissionMigration))) {
            $this->callSilent('vendor:publish', [
                '--provider' => 'Spatie\Permission\PermissionServiceProvider',
                '--force' => true
            ]);
            $this->info("✅ Permission migrations published.");
        } else {
            $this->line("Permission migrations already exist, skipping publish.");
        }

        $this->callSilent('migrate', ['--force' => true]);
        $this->patchUserModelForHasRoles();
        $this->info("✅ Permission feature installed.");
    }

    protected function patchUserModelForHasRoles(): void
    {
        $userModel = app_path('Models/User.php');
        if (! File::exists($userModel)) {
            $this->warn("User model not found, skipping HasRoles patch.");
            return;
        }

        $content = File::get($userModel);

        if (! str_contains($content, 'HasRoles')) {
            $content = preg_replace(
                '/(\nnamespace\s+App\\\Models;\s*\n(?:use[^\n]+\n)*)/m',
                "$1use Spatie\\Permission\\Traits\\HasRoles;\n",
                $content,
                1
            ) ?? $content;

            $content = preg_replace(
                '/(class\s+User\s+extends\s+[^\\{]+\\{)/m',
                "$1\n    use HasRoles;\n",
                $content,
                1
            ) ?? $content;

            File::put($userModel, $content);
            $this->info("✅ User model patched with HasRoles.");
        }
    }
}
