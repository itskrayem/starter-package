<?php

namespace ItsKrayem\StarterPackage\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallCommand extends Command
{
    protected $signature = 'starter:install-core {features?*}';
    protected $description = 'Install starter package: Nova, MediaLibrary, and optional features';

    public function handle(): int
    {
        $this->info("🚀 Installing Starter Package Core...");

        try {
            // Clean up any existing duplicate Nova migrations first
            $this->cleanupDuplicateNovaMigrations();
            
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

    protected function cleanupDuplicateNovaMigrations(): void
    {
        // Remove any existing Nova migrations that might cause conflicts
        $existingNovaMigrations = glob(database_path('migrations/*nova*.php'));
        
        if (!empty($existingNovaMigrations)) {
            $this->warn("Found existing Nova migrations, cleaning up duplicates...");
            
            foreach ($existingNovaMigrations as $migration) {
                unlink($migration);
                $this->line("Removed: " . basename($migration));
            }
            
            $this->info("✅ Cleanup complete.");
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
        $this->info("Checking Laravel Nova...");

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

        // Publish Nova assets
        $this->publishAssets('Laravel\Nova\NovaServiceProvider', 'nova-assets');
        
        // Copy and run Nova migrations
        $this->copyNovaMigrations();
        $this->runMigrations();
        $this->info("✅ Nova setup complete.");
    }

    protected function copyNovaMigrations(): void
    {
        $novaMigrationsPath = base_path('vendor/laravel/nova/database/migrations/');
        
        if (!is_dir($novaMigrationsPath)) {
            $this->warn("Nova migrations directory not found, skipping migration copy.");
            return;
        }

        // Check if Nova migrations are already copied
        $existingNovaMigrations = glob(database_path('migrations/*nova*.php'));
        if (!empty($existingNovaMigrations)) {
            $this->line("Nova migrations already exist, skipping copy.");
            return;
        }

        $novaMigrations = glob($novaMigrationsPath . '*.php');
        $timestamp = now();
        
        foreach ($novaMigrations as $file) {
            $filename = basename($file);
            $newFilename = $timestamp->format('Y_m_d_His') . '_nova_' . $filename;
            $destination = database_path('migrations/' . $newFilename);
            
            // Read the original file content
            $content = file_get_contents($file);
            
            // Generate a unique class name by prefixing with Nova and timestamp
            $originalClassName = $this->extractClassName($content);
            $newClassName = 'Nova' . $timestamp->format('YmdHis') . $originalClassName;
            
            // Replace the class name in the content
            $content = str_replace(
                "class {$originalClassName}",
                "class {$newClassName}",
                $content
            );
            
            // Write the modified content to the new file
            file_put_contents($destination, $content);
            $this->line("Copied and renamed: {$filename} -> {$newFilename}");
            
            $timestamp->addSecond(); // Ensure unique timestamps
        }
        
        $this->info("✅ Nova migrations copied with unique class names.");
    }

    protected function extractClassName(string $content): string
    {
        if (preg_match('/class\s+(\w+)\s+extends\s+Migration/', $content, $matches)) {
            return $matches[1];
        }
        
        // Fallback: generate a generic class name
        return 'NovaMigration';
    }

    protected function executeCommand(string $command): void
    {
        $process = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ], $pipes);

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
            throw new \Exception("Command failed: {$command}. Error: {$error}");
        }
    }

    protected function publishAssets(string $provider, string $tag): void
    {
        $this->callSilent('vendor:publish', [
            '--provider' => $provider,
            '--tag' => $tag,
            '--force' => true
        ]);
        $this->info("✅ {$tag} published.");
    }

    protected function runMigrations(): void
    {
        $this->callSilent('migrate', ['--force' => true]);
        $this->info("✅ Migrations applied.");
    }


    protected function installMediaLibrary(): void
    {
        $this->info("Setting up Spatie MediaLibrary...");

        $mediaMigration = database_path('migrations/*_create_media_table.php');
        
        if (empty(File::glob($mediaMigration))) {
            $this->publishAssets(
                'Spatie\MediaLibrary\MediaLibraryServiceProvider',
                'laravel-medialibrary-migrations'
            );
            $this->info("✅ MediaLibrary migrations published.");
        } else {
            $this->line("MediaLibrary migrations already exist, skipping publish.");
        }

        $this->runMigrations();
        $this->info("✅ MediaLibrary setup complete.");
    }

    protected function installPermission(): void
    {
        $this->info("Setting up Spatie Permission...");

        $permissionMigration = database_path('migrations/*_create_permission_tables.php');
        
        if (empty(File::glob($permissionMigration))) {
            $this->publishAssets(
                'Spatie\Permission\PermissionServiceProvider',
                'laravel-permission-migrations'
            );
            $this->info("✅ Permission migrations published.");
        } else {
            $this->line("Permission migrations already exist, skipping publish.");
        }

        $this->runMigrations();
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

        // Skip if already patched
        if (str_contains($content, 'HasRoles')) {
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
        // Find the namespace line and add use statement after existing use statements
        $pattern = '/(\nnamespace\s+App\\\Models;\s*\n(?:use[^\n]+\n)*)/';
        
        return preg_replace(
            $pattern,
            "$1use {$useStatement};\n",
            $content,
            1
        ) ?? $content;
    }

    protected function addTraitToClass(string $content, string $traitName): string
    {
        // Add trait inside the class after the opening brace
        $pattern = '/(class\s+User\s+extends\s+[^{]+\{)/';
        
        return preg_replace(
            $pattern,
            "$1\n    use {$traitName};\n",
            $content,
            1
        ) ?? $content;
    }
}
