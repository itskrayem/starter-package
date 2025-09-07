<?php

namespace ItsKrayem\StarterPackage\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class InstallCommand extends Command
{
    protected $signature = 'starter:install {features?*}';
    protected $description = 'Install starter package: Nova, MediaLibrary, Permission, and optional features';

    public function handle(): int
    {
        $this->info("🚀 Installing Starter Package...");

        try {
            $this->installNova();
            $this->installMediaLibrary();
            $this->installOptionalFeatures();

            // Run migrations
            $this->callSilent('migrate');
            $this->info("✅ Database migrated.");

            // Create Nova User resource if not exists
            if (!File::exists(app_path('Nova/User.php'))) {
                $this->callSilent('nova:resource', ['name' => 'User']);
                $this->info("✅ Nova User resource created.");
            } else {
                $this->line("⚠️ User resource already exists, skipping...");
            }

            // Prompt to create Nova user interactively
            if ($this->confirm("Do you want to create your first Nova user now?", true)) {
                $this->call('nova:user');
                $this->info("✅ Nova user created.");
            } else {
                $this->line("➡️ You can create one later using: php artisan nova:user");
            }

            // Publish stubs (Models + Nova)
            $this->publishStubs();

            $this->info("🎉 Starter Package installation complete!");
            $this->newLine();
            $this->info("Next steps:");
            $this->line("1. Start using Nova!");

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

        $this->info("Installing Laravel Nova via Composer...");
        $this->runComposerCommand(['require', 'laravel/nova:^5.0']);

        // Re-register commands so nova:* becomes available
        Artisan::call('package:discover');
        $this->line(Artisan::output());

        $this->call('vendor:publish', [
            '--provider' => 'Laravel\Nova\NovaServiceProvider',
            '--force' => true
        ]);

        $this->info("✅ Laravel Nova installed.");
    }

    protected function installMediaLibrary(): void
    {
        $this->info("Setting up Spatie MediaLibrary...");

        $mediaMigration = database_path('migrations/*_create_media_table.php');
        
        if (empty(File::glob($mediaMigration))) {
            $this->call('vendor:publish', [
                '--provider' => 'Spatie\MediaLibrary\MediaLibraryServiceProvider',
                '--tag' => 'laravel-medialibrary-migrations',
                '--force' => true
            ]);
            $this->info("✅ MediaLibrary migrations published.");
        } else {
            $this->line("⚠️ MediaLibrary migrations already exist.");
        }

        $this->info("✅ MediaLibrary setup complete.");
    }

    protected function installPermission(): void
    {
        $this->info("Setting up Spatie Permission...");

        if (!class_exists(\Spatie\Permission\Models\Permission::class)) {
            $this->runComposerCommand(['require', 'spatie/laravel-permission']);
        }

        $permissionMigration = database_path('migrations/*_create_permission_tables.php');
        
        if (empty(File::glob($permissionMigration))) {
            $this->call('vendor:publish', [
                '--provider' => 'Spatie\Permission\PermissionServiceProvider',
                '--tag' => 'laravel-permission-migrations',
                '--force' => true
            ]);
            $this->info("✅ Permission migrations published.");
        } else {
            $this->line("⚠️ Permission migrations already exist.");
        }

        $this->patchUserModelForHasRoles();
        $this->info("✅ Permission feature installed.");
    }

    protected function patchUserModelForHasRoles(): void
    {
        $userModelPath = app_path('Models/User.php');
        
        if (!File::exists($userModelPath)) {
            $this->warn("⚠️ User model not found at {$userModelPath}, skipping HasRoles patch.");
            return;
        }

        $content = File::get($userModelPath);

        if (str_contains($content, 'HasRoles')) {
            $this->line("ℹ️ User model already has HasRoles trait.");
            return;
        }

        $content = $this->addUseStatement($content, 'Spatie\\Permission\\Traits\\HasRoles');
        $content = $this->addTraitToClass($content, 'HasRoles');

        File::put($userModelPath, $content);
        $this->info("✅ User model patched with HasRoles trait.");
    }

    protected function addUseStatement(string $content, string $useStatement): string
    {
        if (preg_match('/^namespace\s+[^;]+;\s*$/m', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $namespaceEnd = $matches[0][1] + strlen($matches[0][0]);
            $insertPosition = $namespaceEnd;

            if (preg_match_all('/\nuse\s+[^;]+;\s*$/m', $content, $allUseMatches, PREG_OFFSET_CAPTURE, $namespaceEnd)) {
                $lastUse = end($allUseMatches[0]);
                $insertPosition = $lastUse[1] + strlen($lastUse[0]);
            }
            
            return substr($content, 0, $insertPosition) . 
                   "\nuse {$useStatement};" . 
                   substr($content, $insertPosition);
        }
        
        return $content;
    }

    protected function addTraitToClass(string $content, string $traitName): string
    {
        if (preg_match('/(class\s+User\s+extends\s+[^{]+\{)(\s*)/', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $classStart = $matches[1][1] + strlen($matches[1][0]);
            $whitespace = $matches[2][0] ?? "\n";
            
            return substr($content, 0, $classStart) . 
                   $whitespace . "    use {$traitName};" . 
                   substr($content, $classStart);
        }
        
        return $content;
    }

    protected function publishStubs(): void
    {
        $this->info("📦 Publishing stubs...");

        $stubsPath = __DIR__ . '/../../stubs';

        // Models stubs
        $modelsPath = $stubsPath . '/models';
        if (File::exists($modelsPath)) {
            foreach (File::files($modelsPath) as $file) {
                $destination = app_path('Models/' . $file->getFilename());
                if (!File::exists($destination)) {
                    File::copy($file->getPathname(), $destination);
                    $this->info("✅ Model stub published: " . $file->getFilename());
                } else {
                    $this->line("⚠️ Model already exists, skipping: " . $file->getFilename());
                }
            }
        }

        // Nova stubs
        $novaPath = $stubsPath . '/nova';
        if (File::exists($novaPath)) {
            foreach (File::files($novaPath) as $file) {
                $destination = app_path('Nova/' . $file->getFilename());
                if (!File::exists($destination)) {
                    File::copy($file->getPathname(), $destination);
                    $this->info("✅ Nova stub published: " . $file->getFilename());
                } else {
                    $this->line("⚠️ Nova resource already exists, skipping: " . $file->getFilename());
                }
            }
        }

        $this->info("📦 Stubs publishing complete.");
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
