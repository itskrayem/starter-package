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
        $existingNovaMigrations = File::glob(database_path('migrations/*nova*.php'));
        
        if (!empty($existingNovaMigrations)) {
            $this->warn("Found existing Nova migrations, cleaning up duplicates...");
            
            foreach ($existingNovaMigrations as $migration) {
                File::delete($migration);
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

        $novaMigrations = File::files($novaMigrationsPath);
        $baseTimestamp = now();
        $counter = 0;

        // Get all existing migration class names to avoid conflicts
        $existingClassNames = $this->getExistingMigrationClassNames();

        foreach ($novaMigrations as $file) {
            $filename = $file->getFilename();
            
            // Skip if not a PHP file
            if (!Str::endsWith($filename, '.php')) {
                continue;
            }

            // Generate unique timestamp for each migration
            $timestamp = $baseTimestamp->copy()->addSeconds($counter);
            $newFilename = $timestamp->format('Y_m_d_His') . '_nova_' . $filename;
            $destination = database_path('migrations/' . $newFilename);

            // Read the original file content
            $content = File::get($file->getPathname());

            // Generate a unique class name
            $originalClassName = $this->extractClassName($content);
            $newClassName = $this->generateUniqueClassName($originalClassName, $timestamp, $existingClassNames);

            // Replace the class name in the content
            $content = str_replace(
                "class {$originalClassName}",
                "class {$newClassName}",
                $content
            );

            // Write the modified content to the new file
            File::put($destination, $content);
            $this->line("Copied and renamed: {$filename} -> {$newFilename}");

            // Add to existing class names to prevent future conflicts
            $existingClassNames[] = $newClassName;
            $counter++;
        }

        $this->info("✅ Nova migrations copied with unique class names.");
    }

    protected function getExistingMigrationClassNames(): array
    {
        $allMigrationFiles = File::glob(database_path('migrations/*.php'));
        $existingClassNames = [];

        foreach ($allMigrationFiles as $migrationFile) {
            $migrationContent = File::get($migrationFile);
            if (preg_match('/class\s+(\w+)\s+extends\s+Migration/', $migrationContent, $matches)) {
                $existingClassNames[] = $matches[1];
            }
        }

        return $existingClassNames;
    }

    protected function generateUniqueClassName(string $originalClassName, $timestamp, array $existingClassNames): string
    {
        $baseClassName = 'Nova' . $timestamp->format('YmdHis') . $originalClassName;
        $className = $baseClassName;
        $suffix = 1;

        // Ensure uniqueness by adding a suffix if needed
        while (in_array($className, $existingClassNames)) {
            $className = $baseClassName . $suffix;
            $suffix++;
        }

        return $className;
    }

    protected function extractClassName(string $content): string
    {
        if (preg_match('/class\s+(\w+)\s+extends\s+Migration/', $content, $matches)) {
            return $matches[1];
        }
        
        // Fallback: generate a generic class name
        return 'NovaMigration' . Str::random(4);
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

    protected function publishAssets(string $provider, string $tag): void
    {
        $result = $this->callSilent('vendor:publish', [
            '--provider' => $provider,
            '--tag' => $tag,
            '--force' => true
        ]);

        if ($result === 0) {
            $this->info("✅ {$tag} published.");
        } else {
            $this->warn("⚠️ Failed to publish {$tag}");
        }
    }

    protected function runMigrations(): void
    {
        $this->info("Running migrations...");
        $result = $this->callSilent('migrate', ['--force' => true]);
        
        if ($result === 0) {
            $this->info("✅ Migrations applied successfully.");
        } else {
            $this->warn("⚠️ Some migrations may have failed. Check your database.");
        }
    }

    protected function installMediaLibrary(): void
    {
        $this->info("Setting up Spatie MediaLibrary...");

        $mediaMigration = File::glob(database_path('migrations/*_create_media_table.php'));
        
        if (empty($mediaMigration)) {
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

        $permissionMigration = File::glob(database_path('migrations/*_create_permission_tables.php'));
        
        if (empty($permissionMigration)) {
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
        // Find the namespace line and add use statement after existing use statements
        $pattern = '/(\nnamespace\s+App\\\Models;\s*\n(?:use[^\n]+\n)*)/';
        
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
        // Add trait inside the class after the opening brace
        $pattern = '/(class\s+User\s+extends\s+[^{]+\{)/';
        
        $replacement = preg_replace(
            $pattern,
            "$1\n    use {$traitName};\n",
            $content,
            1
        );

        return $replacement ?? $content;
    }
}