<?php

namespace ItsKrayem\StarterPackage\Console;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'starter:install-core {features?* : Optional features like permission}';
    protected $description = 'Install core starter package: publish assets, run migrations, and optional features';

    public function handle(): int
    {
        $this->info("🚀 Installing Starter Package Core...");

        // Step 1: Publish core vendor assets
        $this->info("Publishing core assets...");
        $this->callSilent('vendor:publish', [
            '--provider' => 'Laravel\Nova\NovaServiceProvider',
            '--force' => true
        ]);

        $this->callSilent('vendor:publish', [
            '--provider' => 'Spatie\MediaLibrary\MediaLibraryServiceProvider',
            '--force' => true
        ]);
        $this->info("✅ Core assets published.");

        // Step 2: Run migrations
        $this->info("Running migrations...");
        $this->callSilent('migrate', ['--force' => true]);
        $this->info("✅ Migrations complete.");

        // Step 3: Optional features
        $features = $this->argument('features') ?? [];
        foreach ($features as $feature) {
            if ($feature === 'permission') {
                $this->installPermission();
            }
        }

        $this->info("🎉 Starter Package core installation complete!");
        return self::SUCCESS;
    }

    protected function installPermission(): void
    {
        $this->info("Installing Spatie Permission...");
        $this->callSilent('vendor:publish', [
            '--provider' => 'Spatie\Permission\PermissionServiceProvider',
            '--force' => true
        ]);
        $this->callSilent('migrate', ['--force' => true]);
        $this->patchUserModelForHasRoles();
        $this->info("✅ Permission feature installed.");
    }

    protected function patchUserModelForHasRoles(): void
    {
        $userModel = app_path('Models/User.php');
        if (!file_exists($userModel)) {
            $this->warn("User model not found, skipping HasRoles patch.");
            return;
        }

        $content = file_get_contents($userModel);

        // Add trait import if not exists
        if (!str_contains($content, 'HasRoles')) {
            $content = preg_replace(
                '/(\nnamespace\s+App\\\Models;\s*\n(?:use[^\n]+\n)*)/m',
                "$1use Spatie\\Permission\\Traits\\HasRoles;\n",
                $content,
                1
            ) ?? $content;

            $content = preg_replace(
                '/(class\s+User\s+extends\s+[^\\{]+\\{)/m',
                "$1\n    use HasRoles;\n",
                $content,
                1
            ) ?? $content;

            file_put_contents($userModel, $content);
            $this->info("✅ User model patched with HasRoles.");
        } else {
            $this->line("User model already uses HasRoles, skipping patch.");
        }
    }
}
