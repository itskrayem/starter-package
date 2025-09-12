<?php

namespace ItsKrayem\StarterPackage\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class PermissionsCommand extends Command
{
    private const PROVIDER_PERMISSION = 'Spatie\\Permission\\PermissionServiceProvider';

    protected $signature = 'starter:permissions';
    protected $description = 'Install Spatie Permission package and publish all related stubs (models, Nova resources, policies, seeders, migrations)';

    public function handle(): int
    {
        $this->info("🚀 Installing Spatie Permission...");

        try {
            $this->installPermission();
            $this->displayCompletionMessage();

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Permission installation failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    protected function installPermission(): void
    {
        $this->info("Installing Spatie Permission package...");

        if (!$this->isPackageInstalled('spatie/laravel-permission')) {
            $this->runComposerCommand(['require', 'spatie/laravel-permission']);
            $this->runComposerCommand(['dump-autoload']);
            $this->runArtisanCommand(['package:discover']);
        } else {
            $this->line("✔ Spatie Permission already present.");
        }

        // Always publish migration and config after install/discover
        try {
            $this->runArtisanCommand(['vendor:publish', '--provider=' . self::PROVIDER_PERMISSION, '--force']);
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

        // Ensure autoload is updated after publishing stubs
        try {
            $this->runComposerCommand(['dump-autoload']);
        } catch (\Exception $e) {
            // ignore
        }

        $this->info("✅ Spatie Permission installed (package + migrations + stubs).");
    }

    protected function publishPermissionStubs(): void
    {
        $permissionFiles = [
            'models/User.php' => $this->appPath('Models/User.php'),
            'models/Permission.php' => $this->appPath('Models/Permission.php'),
            'models/Role.php' => $this->appPath('Models/Role.php'),
            'nova/Permission.php' => $this->appPath('Nova/Permission.php'),
            'nova/Role.php' => $this->appPath('Nova/Role.php'),
            'Policies/PermissionPolicy.php' => $this->appPath('Policies/PermissionPolicy.php'),
            'Policies/RolePolicy.php' => $this->appPath('Policies/RolePolicy.php'),
            'Policies/UserPolicy.php' => $this->appPath('Policies/UserPolicy.php'),
            'seeders/PermissionsSeeder.php' => $this->databasePath('seeders/PermissionsSeeder.php'),
            'migrations/add_group_column_to_permissions_table.php' => $this->databasePath('migrations/add_group_column_to_permissions_table.php'),
        ];

        foreach ($permissionFiles as $source => $destination) {
            $sourcePath = __DIR__ . '/../stubs/' . $source;

            if (file_exists($sourcePath)) {
                // Ensure destination directory exists
                $destinationDir = dirname($destination);
                File::ensureDirectoryExists($destinationDir);

                // Copy the file
                File::copy($sourcePath, $destination);
                $this->info("✅ Published permission stub: {$source}");
            } else {
                $this->warn("⚠️ Permission stub not found: {$sourcePath}");
            }
        }
    }

    protected function displayCompletionMessage(): void
    {
        $this->info("🎉 Permission installation complete!");
        $this->newLine();
        $this->info("Next steps:");
        $this->line("1️⃣ Configure DatabaseSeeder.php to include PermissionsSeeder");
        $this->line("2️⃣ Run migrations: php artisan migrate");
        $this->line("3️⃣ Run seeders: php artisan db:seed");
        $this->line("4️⃣ Create your first Nova user: php artisan nova:user");
        $this->newLine();
        $this->info("📖 See README.md for detailed configuration steps.");
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

    public function isPermissionsInstalled(): bool
    {
        return $this->isPackageInstalled('spatie/laravel-permission');
    }
}