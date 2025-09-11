<?php

namespace ItsKrayem\StarterPackage\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class InstallCommand extends Command
{
    private const PROVIDER_MEDIALIBRARY = 'Spatie\\MediaLibrary\\MediaLibraryServiceProvider';
    private const PROVIDER_PERMISSION = 'Spatie\\Permission\\PermissionServiceProvider';

    protected $signature = 'starter:install {features?* : Optional features to install (permission, etc.). Use "all" or "core" to install everything}';
    protected $description = 'Install starter package components. Installs core (Nova, MediaLibrary, TinyMCE) by default, or specific features only.';

    public function handle(): int
    {
        $this->info("🚀 Installing Starter Package...");

        try {
            $features = $this->argument('features') ?? [];

            if ($this->shouldInstallCore($features)) {
                $this->installCoreComponents();
            } else {
                $this->info("ℹ️ Skipping core components. Installing features: " . implode(', ', $features));
            }

            $this->installOptionalFeatures();
            $this->runMigrations();
            $this->displayCompletionMessage();

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Installation failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    protected function shouldInstallCore(array $features): bool
    {
        return empty($features) || in_array('all', $features, true) || in_array('core', $features, true);
    }

    protected function installCoreComponents(): void
    {
        $this->installNova();
        $this->installTinyMCE();
        $this->installMediaLibrary();
    }

    protected function displayCompletionMessage(): void
    {
        $this->info("🎉 Starter Package installation complete!");
        $this->newLine();
        $this->info("Next steps:");
        $this->line("1️⃣ Generate Nova User resource: php artisan nova:resource User");
        $this->line("2️⃣ Create your first Nova user: php artisan nova:user");
    }

    // -------------------------
    // Nova
    // -------------------------
    protected function installNova(): void
    {
        $this->info("Installing Laravel Nova...");

        if (!$this->isPackageInstalled('laravel/nova')) {
            $this->runComposerCommand(['config', 'repositories.nova', 'composer', 'https://nova.laravel.com']);
            $this->runComposerCommand(['require', 'laravel/nova']);
        } else {
            $this->line("✔ Laravel Nova already installed.");
        }

        try {
            $this->runArtisanCommand(['nova:install']);
            $this->info("✅ Used nova:install command");
        } catch (\Exception $e) {
            $this->info("ℹ️ Skipped nova:install (command unavailable or already run)");
        }

        $this->info("✅ Laravel Nova installed.");
    }

    // -------------------------
    // TinyMCE
    // -------------------------
    protected function installTinyMCE(): void
    {
        $this->info("Installing TinyMCE...");

        if (!$this->isPackageInstalled('tinymce/tinymce')) {
            $this->runComposerCommand(['require', 'tinymce/tinymce']);
        } else {
            $this->line("✔ TinyMCE already installed.");
        }

        $this->info("✅ TinyMCE setup complete.");
    }

    // -------------------------
    // MediaLibrary
    // -------------------------
    protected function installMediaLibrary(): void
    {
        $this->info("Setting up Spatie MediaLibrary...");

        if (!$this->isPackageInstalled('spatie/laravel-medialibrary')) {
            $this->runComposerCommand(['require', 'spatie/laravel-medialibrary']);
            // Ensure Laravel discovers the package before publishing
            $this->runArtisanCommand(['package:discover']);
        } else {
            $this->line("✔ Spatie MediaLibrary already installed.");
        }

        // Always try to publish the migration after install/discover
        try {
            $this->call('vendor:publish', [
                '--provider' => self::PROVIDER_MEDIALIBRARY,
                '--tag' => 'medialibrary-migrations',
                '--force' => true,
            ]);
            $this->info("✅ Published MediaLibrary migrations with tag: medialibrary-migrations");
        } catch (\Exception $e) {
            $this->warn("⚠️ Failed to publish MediaLibrary migrations. You may need to run: php artisan vendor:publish --provider=\"" . self::PROVIDER_MEDIALIBRARY . "\" --tag=medialibrary-migrations --force");
        }
    }

    // -------------------------
    // Optional Features
    // -------------------------
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

        if (!$this->isPackageInstalled('spatie/laravel-permission')) {
            $this->runComposerCommand(['require', 'spatie/laravel-permission']);
            $this->runArtisanCommand(['package:discover']);
        } else {
            $this->line("✔ Spatie Permission already present.");
        }

        // Always publish migration and config after install/discover
        try {
            $this->call('vendor:publish', [
                '--provider' => self::PROVIDER_PERMISSION,
                '--force' => true,
            ]);
            $this->info("✅ Published Spatie Permission migration and config.");
        } catch (\Exception $e) {
            $this->warn("⚠️ Failed to publish Permission migration/config. You may need to run: php artisan vendor:publish --provider=\"" . self::PROVIDER_PERMISSION . "\" --force");
        }

        // Optionally clear config cache
        try {
            $this->runArtisanCommand(['config:clear']);
        } catch (\Exception $e) {
            // ignore
        }

        $this->publishPermissionStubs();

        $this->info("✅ Spatie Permission installed (package + migrations + stubs).");
    }

    protected function publishPermissionStubs(): void
    {
        $stubFolders = [
            'models' => $this->appPath('Models'),
            'nova' => $this->appPath('Nova'),
            'seeders' => $this->databasePath('seeders')
        ];

        foreach ($stubFolders as $folder => $destination) {
            $source = __DIR__ . '/../stubs/' . $folder;

            if (is_dir($source)) {
                File::ensureDirectoryExists($destination);
                File::copyDirectory($source, $destination);
                $this->info("✅ Published permission stubs: {$folder}");
            } else {
                $this->warn("⚠️ Stub folder not found: {$source}");
            }
        }
    }

    // -------------------------
    // Helpers
    // -------------------------
    protected function isPackageInstalled(string $packageName): bool
    {
        return is_dir($this->basePath("vendor/{$packageName}"));
    }

    protected function basePath(string $path = ''): string
    {
        return base_path($path);
    }

    protected function databasePath(string $path = ''): string
    {
        return database_path($path);
    }

    protected function appPath(string $path = ''): string
    {
        return app_path($path);
    }

    protected function runMigrations(): void
    {
        $this->call('migrate', ['--force' => true]);
        $this->info("✅ Database migrated.");
    }

    protected function runComposerCommand(array $command): void
    {
        $process = new Process(array_merge(['composer'], $command));
        $process->setTimeout(600);

        try {
            $process->mustRun(fn($type, $buffer) => $this->output->write($buffer));
        } catch (ProcessFailedException $exception) {
            throw new \Exception("Composer command failed: " . $exception->getMessage());
        }
    }

    protected function runArtisanCommand(array $args): void
    {
        $cmd = array_merge([PHP_BINARY, 'artisan'], $args);
        $process = new Process($cmd);
        $process->setTimeout(600);

        try {
            $process->mustRun(fn($type, $buffer) => $this->output->write($buffer));
        } catch (ProcessFailedException $exception) {
            throw new \Exception("Artisan command failed: " . $exception->getMessage());
        }
    }
}
