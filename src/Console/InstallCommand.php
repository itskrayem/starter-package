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

    protected function executeCommand(string $command): void
    {
        $process = new Process(explode(' ', $command));
        $process->setTimeout(300);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
        $this->line($process->getOutput());
    }
{
    protected $signature = 'starter:install {features?*}';
    protected $description = 'Install starter package: Nova, MediaLibrary, and optional features';

    public function handle(): int
    {
        $this->info("🚀 Installing Starter Package...");

        try {
            $this->installNova();
            $this->installMediaLibrary();
            $this->installOptionalFeatures();

            $this->info("🎉 Starter Package installation complete!");
            $this->newLine();
            $this->info("Next steps:");
            $this->line("1. Run: php artisan migrate");
            $this->line("2. Run: php artisan nova:user (to create your first Nova user)");
            
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

        // Generate app/Nova folder and default User resource
        $this->callSilent('nova:install');

        // Copy and run Nova migrations
        $this->copyNovaMigrations();
        $this->runMigrations();
        $this->info("✅ Nova setup complete.");
        $this->call('vendor:publish', [
            '--provider' => 'Laravel\Nova\NovaServiceProvider',
            '--force' => true
        ]);

        $this->info("✅ Laravel Nova installed.");
    }

    protected function installMediaLibrary(): void
    {
        $this->info("Setting up Spatie MediaLibrary...");

        // Check if MediaLibrary migrations already exist
        $mediaMigration = database_path('migrations/*_create_media_table.php');
        
        if (empty(File::glob($mediaMigration))) {
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

        // Install the package if not already installed
        if (!class_exists(\Spatie\Permission\Models\Permission::class)) {
            $this->runComposerCommand(['require', 'spatie/laravel-permission']);
        }

        // Publish migrations if they don't exist
        $permissionMigration = database_path('migrations/*_create_permission_tables.php');
        
        if (empty(File::glob($permissionMigration))) {
            $this->call('vendor:publish', [
                '--provider' => 'Spatie\Permission\PermissionServiceProvider',
                '--tag' => 'laravel-permission-migrations',
                '--force' => true
            ]);
            $this->info("✅ Permission migrations published.");
        } else {
            $this->line("Permission migrations already exist.");
        }

        // Patch User model to include HasRoles trait
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
        if (preg_match('/^namespace\s+[^;]+;\s*$/m', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $namespaceEnd = $matches[0][1] + strlen($matches[0][0]);
            
            // Find where use statements end or class begins
            $insertPosition = $namespaceEnd;
            if (preg_match('/\nuse\s+[^;]+;\s*$/m', $content, $useMatches, PREG_OFFSET_CAPTURE, $namespaceEnd)) {
                // Find the last use statement
                if (preg_match_all('/\nuse\s+[^;]+;\s*$/m', $content, $allUseMatches, PREG_OFFSET_CAPTURE, $namespaceEnd)) {
                    $lastUse = end($allUseMatches[0]);
                    $insertPosition = $lastUse[1] + strlen($lastUse[0]);
                }
            }
            
            $newContent = substr($content, 0, $insertPosition) . 
                         "\nuse {$useStatement};" . 
                         substr($content, $insertPosition);
            
            return $newContent;
        }
        
        return $content;
    }

    protected function addTraitToClass(string $content, string $traitName): string
    {
        // Find the User class and add trait after opening brace
        if (preg_match('/(class\s+User\s+extends\s+[^{]+\{)(\s*)/', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $classStart = $matches[1][1] + strlen($matches[1][0]);
            $whitespace = $matches[2][0] ?? "\n";
            
            $newContent = substr($content, 0, $classStart) . 
                         $whitespace . "    use {$traitName};" . 
                         substr($content, $classStart);
            
            return $newContent;
        }
        
        return $content;
    }

    protected function runComposerCommand(array $command): void
    {
        $process = new Process(array_merge(['composer'], $command));
        $process->setTimeout(300); // 5 minutes timeout
        
        try {
            $process->mustRun();
            $this->line($process->getOutput());
        } catch (ProcessFailedException $exception) {
            throw new \Exception("Composer command failed: " . $exception->getMessage());
        }
    }

    public function copyStubFiles()
{
    $files = [
        [
            'source' => __DIR__ . '/../stubs/models/Role.php',
            'destination' => app_path('Models/Role.php'),
        ],
        [
            'source' => __DIR__ . '/../stubs/models/Permission.php',
            'destination' => app_path('Models/Permission.php'),
        ],
        [
            'source' => __DIR__ . '/../stubs/nova/Role.php',
            'destination' => app_path('Nova/Role.php'),
        ],
        [
            'source' => __DIR__ . '/../stubs/nova/Permission.php',
            'destination' => app_path('Nova/Permission.php'),
        ],
    ];

    foreach ($files as $file) {
        if (!File::exists($file['destination'])) {
            File::copy($file['source'], $file['destination']);
            $this->info("✅ {$file['destination']} added.");
        } else {
            $this->warn("{$file['destination']} already exists.");
        }
    }
}
}