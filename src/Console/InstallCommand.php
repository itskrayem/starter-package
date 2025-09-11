<?php

namespace ItsKrayem\StarterPackage\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class InstallCommand extends Command
{
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

    /**
     * Determine if core components should be installed
     */
    protected function shouldInstallCore(array $features): bool
    {
        return empty($features) || 
               in_array('all', $features, true) || 
               in_array('core', $features, true);
    }

    /**
     * Install all core components
     */
    protected function installCoreComponents(): void
    {
        $this->installNova();
        $this->installTinyMCE();
        $this->installMediaLibrary();
    }

    /**
     * Display completion message with next steps
     */
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

        // Check if Nova is already installed
        if ($this->isPackageInstalled('laravel/nova')) {
            $this->line("✔ Laravel Nova already installed.");
        } else {
            $this->runComposerCommand(['config', 'repositories.nova', 'composer', 'https://nova.laravel.com']);
            $this->runComposerCommand(['require', 'laravel/nova:^5.0']);
            
            $this->call('vendor:publish', [
                '--provider' => 'Laravel\Nova\NovaServiceProvider',
                '--force' => true,
            ]);
        }

        // Try to run nova:install command
        try {
            $this->runArtisanCommand(['nova:install']);
            $this->info("✅ Used nova:install command");
        } catch (\Exception $e) {
            $this->info("✅ Nova setup completed (install command not available or already run)");
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

        // Try to use TinyMCE's install command, fallback to manual setup
        try {
            $this->runArtisanCommand(['tinymce:install']);
            $this->info("✅ Used tinymce:install command");
        } catch (\Exception $e) {
            // TinyMCE doesn't have an install command, just require is enough
            $this->info("✅ TinyMCE package installed (no additional setup required)");
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
        } else {
            $this->line("✔ Spatie MediaLibrary already installed.");
        }

        // Always try to publish migrations if they don't exist
        if (!$this->mediaTableExists()) {
            // MediaLibrary doesn't have an install command, just publish migrations directly
            $this->call('vendor:publish', [
                '--provider' => 'Spatie\MediaLibrary\MediaLibraryServiceProvider'
            ]);
            $this->info("✅ Published MediaLibrary resources (including migrations)");
        } else {
            $this->info("✅ MediaLibrary migrations already exist");
        }

        $this->info("✅ MediaLibrary setup complete.");
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
        } else {
            $this->line("✔ Spatie Permission already present.");
        }

        // Try to use Spatie's install command, fallback to manual publishing
        try {
            $this->runArtisanCommand(['permission:install']);
            $this->info("✅ Used permission:install command");
        } catch (\Exception $e) {
            // Fallback to manual publishing if install command doesn't exist
            $this->call('vendor:publish', [
                '--provider' => 'Spatie\Permission\PermissionServiceProvider',
                '--tag' => 'migrations'
            ]);
            $this->info("✅ Published Spatie Permission migrations manually");
        }

        $this->publishPermissionStubs();

        $this->info("✅ Spatie Permission installed (package required + migrations published + stubs published).");
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
                $this->ensureDirectoryExists($destination);
                $this->copyDirectory($source, $destination);
                $this->info("✅ Published permission stubs: {$folder}");
            } else {
                $this->warn("⚠️ Stub folder not found: {$source}");
            }
        }
    }

    // -------------------------
    // Helper Methods
    // -------------------------

    /**
     * Check if a composer package is installed
     */
    protected function isPackageInstalled(string $packageName): bool
    {
        $vendorPath = $this->basePath("vendor/{$packageName}");
        return is_dir($vendorPath);
    }

    /**
     * Check if media table migration exists
     */
    protected function mediaTableExists(): bool
    {
        $migrationFiles = glob($this->databasePath('migrations/*_create_media_table.php'));
        return !empty($migrationFiles);
    }

    /**
     * Get the base path of the Laravel installation
     */
    protected function basePath(string $path = ''): string
    {
        return base_path($path);
    }

    /**
     * Get the database path
     */
    protected function databasePath(string $path = ''): string
    {
        return database_path($path);
    }

    /**
     * Get the app path
     */
    protected function appPath(string $path = ''): string
    {
        return app_path($path);
    }

    /**
     * Ensure directory exists
     */
    protected function ensureDirectoryExists(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }

    /**
     * Copy directory recursively
     */
    protected function copyDirectory(string $source, string $destination): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $destPath = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
            if ($item->isDir()) {
                $this->ensureDirectoryExists($destPath);
            } else {
                copy($item, $destPath);
            }
        }
    }

    // -------------------------
    // Migrations
    // -------------------------
    protected function runMigrations(): void
    {
        $this->call('migrate', ['--force' => true]);
        $this->info("✅ Database migrated.");
    }

    // -------------------------
    // Helpers
    // -------------------------
    protected function runComposerCommand(array $command): void
    {
        $process = new Process(array_merge(['composer'], $command));
        $process->setTimeout(600);

        try {
            $process->mustRun(function ($type, $buffer) {
                $this->output->write($buffer);
            });
        } catch (ProcessFailedException $exception) {
            throw new \Exception("Composer command failed: " . $exception->getMessage());
        }
    }

    /**
     * Run an artisan command in a separate PHP process and stream output.
     * Uses the same working directory so vendor/bin and vendor/autoload are available after composer changes.
     */
    protected function runArtisanCommand(array $args): void
    {
        $cmd = array_merge([PHP_BINARY, 'artisan'], $args);
        $process = new Process($cmd);
        $process->setTimeout(600);

        try {
            $process->mustRun(function ($type, $buffer) {
                $this->output->write($buffer);
            });
        } catch (ProcessFailedException $exception) {
            throw new \Exception("Artisan command failed: " . $exception->getMessage());
        }
    }
}
