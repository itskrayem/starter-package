<?php

namespace ItsKrayem\StarterPackage\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallCommand extends Command
{
    protected $signature = 'starter:install-core {features?* : Optional features like permission}';
    protected $description = 'Install core starter package: install Nova, publish assets, migrate DB, optional features';

    public function handle(): int
    {
        $this->info("🚀 Installing Starter Package Core...");

        $this->installNova();
        $this->installMediaLibrary();

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

        // Configure Nova repo for composer
        exec('composer config repositories.nova composer https://nova.laravel.com');

        if (! class_exists(\Laravel\Nova\Nova::class)) {
            $this->info("Installing Laravel Nova via Composer...");
            exec('composer require laravel/nova:^5.0 -W');
        } else {
            $this->line("Laravel Nova already installed, skipping Composer install.");
        }

        // Publish assets safely
        $novaAssets = public_path('vendor/nova');
        if (! File::exists($novaAssets)) {
            $this->callSilent('vendor:publish', [
                '--provider' => 'Laravel\Nova\NovaServiceProvider',
                '--force' => true
            ]);
            $this->info("✅ Nova assets published.");
        } else {
            $this->line("Nova assets already published, skipping.");
        }

        // Run migrations safely (ignore duplicates)
        $this->callSilent('migrate', ['--force' => true]);
        $this->info("✅ Migrations applied.");
    }

    protected function installMediaLibrary(): void
    {
        $this->info("Publishing Spatie MediaLibrary migrations...");

        $mediaMigration = database_path('migrations/*_create_media_table.php');
        if (empty(File::glob($mediaMigration))) {
            $this->callSilent('vendor:publish', [
                '--provider' => 'Spatie\MediaLibrary\MediaLibraryServiceProvider',
                '--tag' => 'migrations',
                '--force' => true
            ]);
            $this->info("✅ MediaLibrary migrations published.");
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
                '--tag' => 'migrations',
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
