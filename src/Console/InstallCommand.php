<?php

namespace ItsKrayem\StarterPackage\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class InstallCommand extends Command
{
    protected $signature = 'starter:install {features?*}';
    protected $description = 'Install Starter Package: Nova, MediaLibrary, TinyMCE, and optional features';

    public function handle(): int
    {
        $this->info('🚀 Starting Starter Package installation...');

        // Step 1: Configure Nova credentials
        $this->configureNovaCredentials();

        try {
            // Step 2: Install Nova
            $this->installNova();

            // Step 3: Install TinyMCE
            $this->installTinyMCE();

            // Step 4: Install MediaLibrary
            $this->installMediaLibrary();

            // Step 5: Install optional features
            $this->installOptionalFeatures();

            // Step 6: Run migrations
            $this->runMigrations();

            $this->info('🎉 Starter Package installation complete!');
            $this->line("Next steps:");
            $this->line("1️⃣ Generate Nova User resource: php artisan nova:resource User");
            $this->line("2️⃣ Create your first Nova user: php artisan nova:user");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Installation failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    protected function configureNovaCredentials(): void
    {
        $this->info('🔐 Checking for existing Nova credentials...');

        // Check global config
        $checkGlobal = new Process(['composer', 'config', '--global', 'http-basic.nova.laravel.com']);
        $checkGlobal->run();

        if ($checkGlobal->isSuccessful() && trim($checkGlobal->getOutput()) !== '') {
            $this->info('✔ Nova credentials already configured globally.');
            return;
        }

        // Check local (project) config
        $checkLocal = new Process(['composer', 'config', 'http-basic.nova.laravel.com']);
        $checkLocal->run();

        if ($checkLocal->isSuccessful() && trim($checkLocal->getOutput()) !== '') {
            $this->info('✔ Nova credentials already configured for this project.');
            return;
        }

        // Ask user for credentials
        $email = $this->ask('📧 Enter your Nova account email');
        $password = $this->secret('🔑 Enter your Nova account password (or API token)');
        $scope = $this->choice(
            'Where do you want to save these credentials?',
            ['local (this project only)', 'global (for all projects)'],
            0
        );

        $this->info('⚙ Configuring Nova credentials...');

        $args = ['composer', 'config'];
        if (str_starts_with($scope, 'global')) {
            $args[] = '--global';
        }

        $args = array_merge($args, [
            'http-basic.nova.laravel.com',
            $email,
            $password,
        ]);

        $process = new Process($args);
        $process->setTimeout(null);
        $process->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        if ($process->isSuccessful()) {
            $this->info('✅ Nova credentials configured successfully.');
        } else {
            $this->error('❌ Failed to configure Nova credentials.');
        }
    }

    protected function installNova(): void
    {
        $this->info('📦 Installing Laravel Nova...');

        if (class_exists(\Laravel\Nova\Nova::class)) {
            $this->line("✔ Laravel Nova is already installed.");
            return;
        }

        $this->runComposerCommand(['require', 'laravel/nova:^5.0']);

        $this->call('vendor:publish', [
            '--provider' => 'Laravel\Nova\NovaServiceProvider',
            '--force' => true,
        ]);

        $this->info('✅ Laravel Nova installed. Run Nova commands separately.');
    }

    protected function installTinyMCE(): void
    {
        $this->info('📦 Installing TinyMCE...');
        $this->runComposerCommand(['require', 'tinymce/tinymce']);
        $this->info('✅ TinyMCE installed.');
    }

    protected function installMediaLibrary(): void
    {
        $this->info('📦 Setting up Spatie MediaLibrary...');
        $this->runComposerCommand(['require', 'spatie/laravel-medialibrary']);

        $migrationFiles = database_path('migrations/*_create_media_table.php');
        if (empty(File::glob($migrationFiles))) {
            $this->call('vendor:publish', [
                '--provider' => 'Spatie\MediaLibrary\MediaLibraryServiceProvider',
                '--tag' => 'laravel-medialibrary-migrations',
                '--force' => true,
            ]);
        }

        $this->info('✅ MediaLibrary setup complete.');
    }

    protected function installOptionalFeatures(): void
    {
        $features = $this->argument('features') ?? [];
        foreach ($features as $feature) {
            if ($feature === 'permission') {
                $this->installPermission();
            } else {
                $this->warn("⚠ Unknown feature: {$feature}");
            }
        }
    }

    protected function installPermission(): void
    {
        $this->info('📦 Installing Spatie Permission...');
        $this->runComposerCommand(['require', 'spatie/laravel-permission']);

        $migrationFiles = database_path('migrations/*_create_permission_tables.php');
        if (empty(File::glob($migrationFiles))) {
            $this->call('vendor:publish', [
                '--provider' => 'Spatie\Permission\PermissionServiceProvider',
                '--tag' => 'laravel-permission-migrations',
                '--force' => true,
            ]);
        }

        $this->publishPermissionStubs();
        $this->info('✅ Spatie Permission installed.');
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
                $this->warn("⚠ Stub folder not found: {$source}");
            }
        }
    }

    protected function runMigrations(): void
    {
        $this->call('migrate', ['--force' => true]);
        $this->info('✅ Database migrated.');
    }

    protected function runComposerCommand(array $command): void
    {
        $process = new Process(array_merge(['composer'], $command));
        $process->setTimeout(600);

        try {
            $process->mustRun();
            $this->line($process->getOutput());
        } catch (ProcessFailedException $exception) {
            throw new \Exception("Composer command failed: " . $exception->getMessage());
        }
    }
}
