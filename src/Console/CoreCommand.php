<?php

namespace ItsKrayem\StarterPackage\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class CoreCommand extends Command
{
    private const PROVIDER_MEDIALIBRARY = 'Spatie\\MediaLibrary\\MediaLibraryServiceProvider';

    protected $signature = 'starter:core {features?* : Optional features to install. Use "all" or "core" to install everything}';
    protected $description = 'Install core starter package components. Installs Nova, MediaLibrary, Nova TinyMCE Editor by default, or specific features only.';

    public function handle(): int
    {
        $this->info("🚀 Installing Starter Package...");

        try {
            $features = $this->argument('features') ?? [];

            if ($this->shouldInstallCore($features)) {
                $this->installCoreComponents();
            } else {
                $this->info("ℹ️ Skipping core components. Installing features: " . implode(', ', $features));
                
                // Handle permission feature separately
                if (in_array('permission', $features)) {
                    $this->warn("⚠️ Permission installation has been moved to a separate command.");
                    $this->info("Please run: php artisan starter:permissions");
                    return Command::SUCCESS;
                }
            }

            // $this->runMigrations(); // Removed to avoid autoload issues
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
        $this->line("1️⃣ Run migrations: php artisan migrate");
        $this->line("2️⃣ Run seeders: php artisan db:seed");
    }

    // -------------------------
    // Nova
    // -------------------------
    protected function installNova(): void
    {
        $this->info("Installing Laravel Nova...");

        if (!$this->isPackageInstalled('laravel/nova')) {
            // Prompt for Nova credentials
            $email = $this->ask('Enter your Laravel Nova email:');
            $password = $this->secret('Enter your Laravel Nova password:');

            // Configure Nova repository locally (not globally)
            $this->runComposerCommand(['config', 'repositories.nova', '{"type": "composer", "url": "https://nova.laravel.com"}']);

            // Set authentication for Nova (exact command: composer config http-basic.nova.laravel.com email password)
            $this->runComposerCommand(['config', 'http-basic.nova.laravel.com', $email, $password]);

            // Require Nova package
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
        $this->info("Installing Nova TinyMCE Editor...");

        if (!$this->isPackageInstalled('murdercode/nova4-tinymce-editor')) {
            $this->runComposerCommand(['require', 'murdercode/nova4-tinymce-editor']);
        } else {
            $this->line("✔ Nova TinyMCE Editor already installed.");
        }

        $this->info("✅ Nova TinyMCE Editor setup complete.");
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
            $this->runArtisanCommand(['vendor:publish', '--provider=' . self::PROVIDER_MEDIALIBRARY, '--tag=medialibrary-migrations', '--force']);
            $this->info("✅ Published MediaLibrary migrations with tag: medialibrary-migrations");
        } catch (\Exception $e) {
            $this->warn("⚠️ Failed to publish MediaLibrary migrations. You may need to run: php artisan vendor:publish --provider=\"" . self::PROVIDER_MEDIALIBRARY . "\" --tag=medialibrary-migrations --force");
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

    public function isCoreInstalled(): bool
    {
        return $this->isPackageInstalled('laravel/nova')
            && $this->isPackageInstalled('spatie/laravel-medialibrary')
            && $this->isPackageInstalled('murdercode/nova4-tinymce-editor');
    }
}
