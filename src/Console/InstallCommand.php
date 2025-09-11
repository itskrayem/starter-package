<?php

namespace ItsKrayem\StarterPackage\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class InstallCommand extends Command
{
    protected $signature = 'starter:install {features?*}';
    protected $description = 'Install starter package: Nova, MediaLibrary, and optional features';

    public function handle(): int
    {
        $this->info("🚀 Installing Starter Package...");

        try {
            $this->installNova();
            $this->installTinyMCE();
            $this->installMediaLibrary();
            $this->installOptionalFeatures();
            $this->runMigrations();

            $this->info("🎉 Starter Package installation complete!");
            $this->newLine();
            $this->info("Next steps:");
            $this->line("1️⃣ Generate Nova User resource: php artisan nova:resource User");
            $this->line("2️⃣ Create your first Nova user: php artisan nova:user");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Installation failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    protected function installNova(): void
    {
        $this->info("Installing Laravel Nova...");

        if (class_exists(\Laravel\Nova\Nova::class)) {
            $this->line("✅ Laravel Nova is already installed.");
            return;
        }

        $this->runComposerCommand([
            'config',
            'repositories.nova',
            'composer',
            'https://nova.laravel.com'
        ]);

        $this->runComposerCommand(['require', 'laravel/nova:^5.0']);

        $this->callSilent('vendor:publish', [
            '--provider' => 'Laravel\Nova\NovaServiceProvider',
            '--force' => true,
        ]);

        $this->info("✅ Laravel Nova installed. Please run the Nova commands separately after this.");
    }

    protected function installTinyMCE(): void
    {
        $this->info("Installing TinyMCE...");

        if (!is_dir(base_path('vendor/tinymce/tinymce'))) {
            $this->runComposerCommand(['require', 'tinymce/tinymce:^7.0']);
        }

        $this->info("✅ TinyMCE installed.");
    }

    protected function installMediaLibrary(): void
    {
        $this->info("Setting up Spatie MediaLibrary...");

        if (!class_exists(\Spatie\MediaLibrary\MediaCollections\Models\Media::class)) {
            $this->runComposerCommand(['require', 'spatie/laravel-medialibrary:^11.0']);
        }

        $migrationFiles = database_path('migrations/*_create_media_table.php');
        if (empty(File::glob($migrationFiles))) {
            $this->call('vendor:publish', [
                '--provider' => 'Spatie\MediaLibrary\MediaLibraryServiceProvider',
                '--tag' => 'laravel-medialibrary-migrations',
                '--force' => true,
            ]);
        }

        $this->info("✅ MediaLibrary setup complete.");
    }

    protected function installOptionalFeatures(): void
    {
        $features = $this->argument('features') ?? [];
        foreach ($features as $feature) {
            if ($feature === 'permission') {
                $this->installPermission();
            } else {
                $this->warn("⚠️ Unknown feature: {$feature}");
            }
        }
    }

    protected function installPermission(): void
    {
        $this->info("Installing Spatie Permission...");

        if (!class_exists(\Spatie\Permission\Models\Permission::class)) {
            $this->runComposerCommand(['require', 'spatie/laravel-permission:^6.0']);
        }

        $migrationFiles = database_path('migrations/*_create_permission_tables.php');
        if (empty(File::glob($migrationFiles))) {
            $this->call('vendor:publish', [
                '--provider' => 'Spatie\Permission\PermissionServiceProvider',
                '--tag' => 'laravel-permission-migrations',
                '--force' => true,
            ]);
        }

        // Only now copy stubs
        $this->publishPermissionStubs();

        $this->info("✅ Spatie Permission installed.");
    }

    protected function publishPermissionStubs(): void
    {
        $stubFolders = ['models', 'nova'];

        foreach ($stubFolders as $folder) {
            $source = __DIR__ . "/../stubs/{$folder}";
            $destination = app_path($folder);

            if (File::exists($source)) {
                File::ensureDirectoryExists($destination);
                File::copyDirectory($source, $destination);
                $this->info("✅ Published permission stubs: {$folder}");
            } else {
                $this->warn("⚠️ Stub folder not found: {$source}");
            }
        }
    }

    protected function runMigrations(): void
    {
        $this->call('migrate', ['--force' => true]);
        $this->info("✅ Database migrated.");
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
