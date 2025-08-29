<?php

namespace ItsKrayem\StarterPackage\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class InstallCommand extends Command
{
    protected $signature = 'starter:install-core {features?*}';
    protected $description = 'Install starter package: Nova, MediaLibrary, and optional features';

    public function handle(): int
    {
        $this->info("🚀 Installing Starter Package Core...");

        try {
            $this->installNova();
            $this->installMediaLibrary();
            $this->installOptionalFeatures();

            $this->info("🎉 Starter Package installation complete!");
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
        $this->info("Setting up Laravel Nova...");

        // Add Nova repository if not already configured
        $this->executeCommand('composer config repositories.nova composer https://nova.laravel.com');

        // Install Nova if not present
        if (!class_exists(\Laravel\Nova\Nova::class)) {
            $this->info("Installing Laravel Nova via Composer...");
            $this->executeCommand('composer require laravel/nova:^5.0');
            $this->info("✅ Laravel Nova installed.");
        } else {
            $this->line("Laravel Nova is already installed.");
        }

        // Clean up any existing Nova migrations to prevent conflicts
        $this->cleanupExistingNovaMigrations();

        // Publish Nova assets and migrations using Laravel's built-in system
        $this->call('nova:install');
        
        $this->info("✅ Nova setup complete.");
    }

    protected function cleanupExistingNovaMigrations(): void
    {
        $patterns = [
            database_path('migrations/*nova*.php'),
            database_path('migrations/*action_events*.php'),
            database_path('migrations/*nova_notifications*.php'),
            database_path('migrations/*field_attachments*.php'),
        ];

        $cleaned = false;
        foreach ($patterns as $pattern) {
            $files = File::glob($pattern);
            if (!empty($files)) {
                $cleaned = true;
                foreach ($files as $file) {
                    File::delete($file);
                    $this->line("Removed existing migration: " . basename($file));
                }
            }
        }

        if ($cleaned) {
            $this->info("✅ Existing Nova migrations cleaned up.");
        }
    }

    protected function executeCommand(string $command): void
    {
        $this->info("Executing: {$command}");
        
        $process = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ], $pipes, base_path());

        if (!is_resource($process)) {
            throw new \Exception("Failed to execute command: {$command}");
        }

        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            throw new \Exception("Command failed: {$command}\nOutput: {$output}\nError: {$error}");
        }

        if (!empty($output)) {
            $this->line($output);
        }
    }

    protected function installMediaLibrary(): void
    {
        $this->info("Setting up Spatie MediaLibrary...");

        // Check if migrations already exist
        $mediaMigration = File::glob(database_path('migrations/*_create_media_table.php'));
        
        if (empty($mediaMigration)) {
            $this->call('vendor:publish', [
                '--provider' => 'Spatie\MediaLibrary\MediaLibraryServiceProvider',
                '--tag' => 'laravel-medialibrary-migrations',
                '--force' => true
            ]);
            $this->info("✅ MediaLibrary migrations published.");
        } else {
            $this->line("MediaLibrary migrations already exist, skipping publish.");
        }

        // Run migrations
        $this->runMigrations();
        $this->info("✅ MediaLibrary setup complete.");
    }

    protected function installPermission(): void
    {
        $this->info("Setting up Spatie Permission...");

        // Check if migrations already exist
        $permissionMigration = File::glob(database_path('migrations/*_create_permission_tables.php'));
        
        if (empty($permissionMigration)) {
            $this->call('vendor:publish', [
                '--provider' => 'Spatie\Permission\PermissionServiceProvider',
                '--tag' => 'laravel-permission-migrations',
                '--force' => true
            ]);
            $this->info("✅ Permission migrations published.");
        } else {
            $this->line("Permission migrations already exist, skipping publish.");
        }

        // Run migrations
        $this->runMigrations();
        
        // Patch User model
        $this->patchUserModelForHasRoles();
        
        $this->info("✅ Permission feature installed.");
    }

    protected function runMigrations(): void
    {
        $this->info("Running migrations...");
        
        try {
            $this->call('migrate', ['--force' => true]);
            $this->info("✅ Migrations applied successfully.");
        } catch (\Exception $e) {
            $this->warn("⚠️ Some migrations may have failed: " . $e->getMessage());
            // Continue execution, don't fail completely
        }
    }

    protected function patchUserModelForHasRoles(): void
    {
        $userModelPath = app_path('Models/User.php');
        
        if (!File::exists($userModelPath)) {
            $this->warn("User model not found at {$userModelPath}, skipping HasRoles patch.");
            return;
        }

        $content = File::get($userModelPath);

        // Skip if already patched
        if (Str::contains($content, 'HasRoles')) {
            $this->line("User model already has HasRoles trait.");
            return;
        }

        // Add use statement for HasRoles trait
        $content = $this->addUseStatement($content, 'Spatie\\Permission\\Traits\\HasRoles');
        
        // Add trait to class
        $content = $this->addTraitToClass($content, 'HasRoles');

        File::put($userModelPath, $content);
        $this->info("✅ User model patched with HasRoles trait.");
    }

    protected function addUseStatement(string $content, string $useStatement): string
    {
        // Check if use statement already exists
        if (Str::contains($content, "use {$useStatement};")) {
            return $content;
        }

        // Find the last use statement and add after it
        $lines = explode("\n", $content);
        $insertIndex = -1;
        
        foreach ($lines as $index => $line) {
            if (Str::startsWith(trim($line), 'use ') && Str::endsWith(trim($line), ';')) {
                $insertIndex = $index;
            }
        }
        
        if ($insertIndex !== -1) {
            array_splice($lines, $insertIndex + 1, 0, "use {$useStatement};");
            return implode("\n", $lines);
        }

        // Fallback: add after namespace
        $pattern = '/(\nnamespace\s+App\\\Models;\s*\n)/';
        $replacement = preg_replace(
            $pattern,
            "$1use {$useStatement};\n",
            $content,
            1
        );

        return $replacement ?? $content;
    }

    protected function addTraitToClass(string $content, string $traitName): string
    {
        // Check if trait is already used
        if (Str::contains($content, "use {$traitName};")) {
            return $content;
        }

        // Find the class declaration and add trait
        $pattern = '/(class\s+User\s+extends\s+[^{]+\{\s*)/';
        
        $replacement = preg_replace(
            $pattern,
            "$1\n    use {$traitName};\n",
            $content,
            1
        );

        return $replacement ?? $content;
    }
}