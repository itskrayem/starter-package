<?php

namespace ItsKrayem\StarterPackage\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class InstallCommand extends Command
{
    protected $signature = 'starter:install {features?*}';
    protected $description = 'Install starter package: Nova, MediaLibrary, optional features, and stubs';

    public function handle(): int
    {
        $this->info("🚀 Installing Starter Package...");

        try {
            $this->installNova();
            $this->installMediaLibrary();
            $this->installOptionalFeatures();
            $this->publishStubs();

            // Run migrations
            $this->call('migrate');
            $this->info("✅ Database migrated.");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Installation failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    protected function installOptionalFeatures(): void
    {
        $features = $this->argument('features') ?? [];
        
        foreach ($features as $feature) {
            match ($feature) {
                'permission' => $this->installPermission(),
                default => $this->warn("Unknown feature: {$feature}")
            };
        }
    }

    protected function installNova(): void
    {
        $this->info("Installing Laravel Nova...");

        if (class_exists(\Laravel\Nova\Nova::class)) {
            $this->line("Laravel Nova is already installed.");
        } else {
            $this->runComposerCommand([
                'config',
                'repositories.nova',
                'composer',
                'https://nova.laravel.com'
            ]);
            $this->runComposerCommand(['require', 'laravel/nova:^5.0']);
            Artisan::call('vendor:publish', [
                '--provider' => 'Laravel\Nova\NovaServiceProvider',
                '--force' => true
            ]);
        }

        // Create Nova User resource
        if (!File::exists(app_path('Nova/User.php'))) {
            $this->call('nova:resource', ['name' => 'User']);
        } else {
            $this->line("User resource already exists, skipping...");
        }

        // Prompt to create first Nova user
        if ($this->confirm("Do you want to create your first Nova user now?", true)) {
            $this->call('nova:user');
        } else {
            $this->line("➡️ You can create one later using: php artisan nova:user");
        }

        $this->info("✅ Laravel Nova installed.");
    }

    protected function installMediaLibrary(): void
    {
        $this->info("Setting up Spatie MediaLibrary...");

        $migrationFiles = database_path('migrations/*_create_media_table.php');
        if (empty(File::glob($migrationFiles))) {
            $this->call('vendor:publish', [
                '--provider' => 'Spatie\MediaLibrary\MediaLibraryServiceProvider',
                '--tag' => 'laravel-medialibrary-migrations',
                '--force' => true
            ]);
            $this->info("✅ MediaLibrary migrations published.");
        } else {
            $this->line("MediaLibrary migrations already exist.");
        }

        $this->info("✅ MediaLibrary setup complete.");
    }

    protected function installPermission(): void
    {
        $this->info("Setting up Spatie Permission...");

        if (!class_exists(\Spatie\Permission\Models\Permission::class)) {
            $this->runComposerCommand(['require', 'spatie/laravel-permission']);
        }

        $migrationFiles = database_path('migrations/*_create_permission_tables.php');
        if (empty(File::glob($migrationFiles))) {
            $this->call('vendor:publish', [
                '--provider' => 'Spatie\Permission\PermissionServiceProvider',
                '--tag' => 'laravel-permission-migrations',
                '--force' => true
            ]);
            $this->info("✅ Permission migrations published.");
        } else {
            $this->line("Permission migrations already exist.");
        }

        $this->patchUserModelForHasRoles();
        $this->info("✅ Permission feature installed.");
    }

    protected function patchUserModelForHasRoles(): void
    {
        $userModelPath = app_path('Models/User.php');
        if (!File::exists($userModelPath)) {
            $this->warn("User model not found at {$userModelPath}, skipping HasRoles patch.");
            return;
        }

        $content = File::get($userModelPath);

        if (!str_contains($content, 'HasRoles')) {
            $content = preg_replace(
                '/(namespace\s+[^;]+;)/',
                "$1\nuse Spatie\\Permission\\Traits\\HasRoles;",
                $content,
                1
            );

            $content = preg_replace(
                '/(class\s+User\s+extends\s+[^{]+\{)/',
                "$1\n    use HasRoles;",
                $content,
                1
            );

            File::put($userModelPath, $content);
            $this->info("✅ User model patched with HasRoles trait.");
        } else {
            $this->line("User model already has HasRoles trait.");
        }
    }

    protected function publishStubs(): void
    {
        $stubsPath = realpath(__DIR__ . '/../../stubs');
        if ($stubsPath && is_dir($stubsPath)) {
            $this->info("📦 Publishing stubs...");
            $this->callSilent('vendor:publish', [
                '--tag' => 'starter-package-stubs',
                '--force' => true
            ]);
            $this->info("📦 Stubs publishing complete.");
        }
    }

    protected function runComposerCommand(array $command): void
    {
        $process = new Process(array_merge(['composer'], $command));
        $process->setTimeout(300);

        try {
            $process->mustRun();
            $this->line($process->getOutput());
        } catch (ProcessFailedException $exception) {
            throw new \Exception("Composer command failed: " . $exception->getMessage());
        }
    }
}
