<?php

namespace ItsKrayem\StarterPackage\Console;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'starter:install {features?* : Optional features like permission}';
    protected $description = 'Install the starter package: core packages, optional features, publish assets, run migrations';

    public function handle(): int
    {
        $this->info("🚀 Starting Starter Package Installation...");

        // Step 1: Publish core vendor assets
        $this->info("Publishing core vendor assets...");
        $this->callSilent('vendor:publish', [
            '--provider' => 'Laravel\Nova\NovaServiceProvider',
            '--force' => true
        ]);

        $this->callSilent('vendor:publish', [
            '--provider' => 'Spatie\MediaLibrary\MediaLibraryServiceProvider',
            '--force' => true
        ]);
        $this->info("✅ Core vendor assets published.");

        // Step 2: Run core migrations
        $this->info("Running core migrations...");
        $this->callSilent('migrate', ['--force' => true]);
        $this->info("✅ Core migrations complete.");

        // Step 3: Optional features
        $features = $this->argument('features') ?? [];
        foreach ($features as $feature) {
            if ($feature === 'permission') {
                $this->installPermissionFeature();
            }
            // Add more optional features here in the future
        }

        $this->info("🎉 Starter Package setup complete!");
        return self::SUCCESS;
    }

    protected function installPermissionFeature(): void
    {
        $this->info("Installing Spatie Permission...");
        $this->callSilent('vendor:publish', [
            '--provider' => 'Spatie\Permission\PermissionServiceProvider',
            '--force' => true
        ]);

        $this->callSilent('migrate', ['--force' => true]);

        $this->patchUserModelForHasRoles();
        $this->info("✅ Spatie Permission installed.");
    }

    protected function patchUserModelForHasRoles(): void
    {
        $userModel = app_path('Models/User.php');
        if (! file_exists($userModel)) {
            $this->warn("User model not found at app/Models/User.php — skipped HasRoles patch.");
            return;
        }

        $content = file_get_contents($userModel);

        // Add use statement
        if (! str_contains($content, 'HasRoles')) {
            $content = preg_replace(
                '/(\nnamespace\s+App\\\Models;\s*\n(?:use[^\n]+\n)*)/m',
                "$1use Spatie\\Permission\\Traits\\HasRoles;\n",
                $content,
                1
            ) ?? $content;

            // Add trait in class
            $content = preg_replace(
                '/(class\s+User\s+extends\s+[^\\{]+\\{)/m',
                "$1\n    use HasRoles;\n",
                $content,
                1
            ) ?? $content;

            file_put_contents($userModel, $content);
            $this->info("✅ User model patched with HasRoles trait.");
        } else {
            $this->line("User model already uses HasRoles — skipped.");
        }
    }
}
