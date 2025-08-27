<?php

namespace ItsKrayem\StarterPackage\Console;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'starter:install-core {features?* : Optional features like permission}';
    protected $description = 'Install core starter package: publish assets, migrate DB, optional features';

    public function handle(): int
    {
        $this->info("🚀 Installing Starter Package Core...");

        // 1️⃣ Publish and migrate Nova
        $this->info("Publishing and migrating Laravel Nova...");
        $this->callSilent('vendor:publish', [
            '--provider' => 'Laravel\Nova\NovaServiceProvider',
            '--force' => true
        ]);

        // Nova does not have migrations by default; if your custom Nova tools need migration, publish/migrate them here
        $this->callSilent('migrate', ['--force' => true]);
        $this->info("✅ Nova setup complete.");

        // 2️⃣ Publish and migrate MediaLibrary
        $this->info("Publishing and migrating Spatie MediaLibrary...");
        $this->callSilent('vendor:publish', [
            '--provider' => 'Spatie\MediaLibrary\MediaLibraryServiceProvider',
            '--force' => true
        ]);
        $this->callSilent('migrate', ['--force' => true]);
        $this->info("✅ MediaLibrary setup complete.");

        // 3️⃣ Optional features
        $features = $this->argument('features') ?? [];
        foreach ($features as $feature) {
            if ($feature === 'permission') {
                $this->installPermission();
            }
        }

        $this->info("🎉 Starter Package installation complete!");
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
        $this->info("✅ Permission installed.");
    }

    protected function patchUserModelForHasRoles(): void
    {
        $userModel = app_path('Models/User.php');
        if (! file_exists($userModel)) {
            $this->warn("User model not found, skipping HasRoles patch.");
            return;
        }

        $content = file_get_contents($userModel);

        if (! str_contains($content, 'HasRoles')) {
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
        }
    }
}
