<?php

namespace ItsKrayem\StarterPackage\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class InstallCommand extends Command
{
    protected $signature = 'starter:install {features?*}';
    protected $description = 'Install starter package: Nova, MediaLibrary, TinyMCE, and optional features';

    public function handle(): int
    {
        $this->info("🚀 Installing Starter Package...");

        try {
                $features = $this->argument('features') ?? [];

                // If no features were passed, or the caller explicitly asked for "all" or "core",
                // run the full/core installers. Otherwise skip the core installers and only
                // run the requested feature installers (e.g. "permission").
                if (empty($features) || in_array('all', $features, true) || in_array('core', $features, true)) {
                    $this->installNova();
                    $this->installTinyMCE();
                    $this->installMediaLibrary();
                } else {
                    $this->info("\u2139\ufe0f Skipping core installers (Nova, TinyMCE, MediaLibrary). Installing requested features: " . implode(', ', $features));
                }

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

    // -------------------------
    // Nova
    // -------------------------
    protected function installNova(): void
    {
        $this->info("Installing Laravel Nova...");

        $this->runComposerCommand([
            'config',
            'repositories.nova',
            'composer',
            'https://nova.laravel.com'
        ]);

        $this->runComposerCommand(['require', 'laravel/nova:^5.0']);

        $this->call('vendor:publish', [
            '--provider' => 'Laravel\Nova\NovaServiceProvider',
            '--force' => true,
        ]);

    // Run nova:install after publishing in a separate PHP process so Artisan can load the newly required package
    $this->runArtisanCommand(['nova:install']);

        $this->info("✅ Laravel Nova installed.");
    }

    // -------------------------
    // TinyMCE
    // -------------------------
    protected function installTinyMCE(): void
    {
        $this->info("Installing TinyMCE...");

        if (!class_exists(\TinyMCE\TinyMCE::class) && !is_dir(base_path('vendor/tinymce/tinymce'))) {
            $this->runComposerCommand(['require', 'tinymce/tinymce']);
        }

        $this->info("✅ TinyMCE installed.");
    }

    // -------------------------
    // MediaLibrary
    // -------------------------
    protected function installMediaLibrary(): void
    {
        $this->info("Setting up Spatie MediaLibrary...");

        if (!class_exists(\Spatie\MediaLibrary\MediaCollections\Models\Media::class)) {
            $this->runComposerCommand(['require', 'spatie/laravel-medialibrary']);
        }

        $migrationFiles = glob(database_path('migrations/*_create_media_table.php'));
        if (empty($migrationFiles)) {
            $this->call('vendor:publish', [
                '--provider' => 'Spatie\MediaLibrary\MediaLibraryServiceProvider',
                '--tag' => 'migrations',
                '--force' => true,
            ]);
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
        // Only run composer require if the package isn't present in vendor
        $vendorPath = base_path('vendor/spatie/laravel-permission');
        if (!class_exists(\Spatie\Permission\Models\Permission::class) && !is_dir($vendorPath)) {
            $this->runComposerCommand(['require', 'spatie/laravel-permission']);
        } else {
            $this->line("✔ Spatie Permission already present.");
        }

        // Publish the package migrations (so we can migrate only those files)
        $this->call('vendor:publish', [
            '--provider' => 'Spatie\\Permission\\PermissionServiceProvider',
            '--tag' => 'migrations',
            '--force' => true,
        ]);

        // Copy package stubs into the application (models, Nova resources)
        $this->publishPermissionStubs();

        // Run only the published permission migrations (don't run global migrate unless necessary)
        $migrationPatterns = [
            database_path('migrations/*permission*.php'),
            database_path('migrations/*create_permission*.php'),
            database_path('migrations/*create_permission_tables*.php'),
        ];

        $found = false;
        foreach ($migrationPatterns as $pattern) {
            foreach (glob($pattern) as $file) {
                $found = true;
                // run migrate for this specific migration file path relative to project root
                $relativePath = str_replace(base_path() . '/', '', $file);
                $this->runArtisanCommand(['migrate', '--path=' . $relativePath, '--force']);
            }
        }

        if (! $found) {
            $this->warn("\u26a0\ufe0f No permission migrations were published — nothing to migrate.");
        }

        $this->info("✅ Spatie Permission installed (package required + stubs published + migrations run).");
    }

    protected function publishPermissionStubs(): void
    {
        $stubFolders = ['models', 'nova'];
        foreach ($stubFolders as $folder) {
            $source = __DIR__ . '/../stubs/' . $folder;
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
