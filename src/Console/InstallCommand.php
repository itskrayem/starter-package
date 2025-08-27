<?php

namespace ItsKrayem\StarterPackage\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallCommand extends Command
{
    protected $signature = 'starter:install-core {features?* : Optional features like permission}';
    protected $description = 'Install core starter package: publish assets, migrate DB, optional features';

    public function handle(): int
    {
        $this->info("🚀 Installing Starter Package Core...");

        // Step 1: Nova
        $this->installNova();

        // Step 2: MediaLibrary
        $this->installMediaLibrary();

        // Step 3: Optional features
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
        $this->info("Publishing Laravel Nova assets and migrations...");

        if (! class_exists('CreateActionEventsTable')) {
            $this->callSilent('vendor:publish', [
                '--provider' => 'Laravel\Nova\NovaServiceProvider',
                '--force' => true,
            ]);
            $this->info("✅ Nova assets and migrations published.");
        } else {
            $this->line("Nova migrations already exist, skipping publish.");
        }

        $this->callSilent('migrate', ['--force' => true]);
        $this->info("✅ Nova migrations applied.");
    }

    protected function installMediaLibrary(): void
    {
        $this->info("Publishing Spatie MediaLibrary assets and migrations...");

        if (! class_exists('CreateMediaTable')) {
            $this->callSilent('vendor:publish', [
                '--provider' => 'Spatie\MediaLibrary\MediaLibraryServiceProvider',
                '--force' => true,
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

        if (! class_exists('CreatePermissionTables')) {
            $this->callSilent('vendor:publish', [
                '--provider' => 'Spatie\Permission\PermissionServiceProvider',
                '--force' => true,
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
