<?php

namespace YourOrg\StarterKit\Console;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class InstallCommand extends Command
{
    protected $signature = 'starter:install {feature? : Optional feature like permission}';
    protected $description = 'Install starter kit: Nova, core packages, optional features, publish assets, migrate DB';

    public function handle(): int
    {
        $this->info("🚀 Starting Starter Kit Installation...");

        // Step 1: Install Nova 5
        $this->info("Step 1: Installing Laravel Nova 5...");
        if (! $this->runComposerRequire(['laravel/nova:^5.0'])) {
            $this->error("❌ Nova installation failed. Make sure Composer auth is configured.");
            return self::FAILURE;
        }
        $this->info("✅ Nova installed!");

        // Step 2: Install core packages
        $this->info("Step 2: Installing core packages...");
        $this->runComposerRequire([
            'tinymce/tinymce',
            'spatie/laravel-medialibrary'
        ]);
        $this->info("✅ Core packages installed!");

        // Step 3: Optional feature
        $feature = $this->argument('feature');
        if ($feature === 'permission') {
            $this->info("Step 3: Installing Spatie Permission...");
            $this->runComposerRequire(['spatie/laravel-permission']);
            $this->callSilent('vendor:publish', [
                '--provider' => 'Spatie\Permission\PermissionServiceProvider',
                '--force' => true
            ]);
            $this->patchUserModelForHasRoles();
            $this->info("✅ Spatie Permission installed and User model updated!");
        }

        // Step 4: Publish vendor assets
        $this->info("Step 4: Publishing vendor assets...");
        $this->callSilent('vendor:publish', [
            '--provider' => 'Laravel\Nova\NovaServiceProvider',
            '--force' => true
        ]);
        $this->callSilent('vendor:publish', [
            '--provider' => 'Spatie\MediaLibrary\MediaLibraryServiceProvider',
            '--force' => true
        ]);
        $this->info("✅ Vendor assets published!");

        // Step 5: Run migrations
        $this->info("Step 5: Running migrations...");
        $this->callSilent('migrate', ['--force' => true]);
        $this->info("✅ Database migrated!");

        $this->info("🎉 Starter Kit setup complete!");
        return self::SUCCESS;
    }

    protected function runComposerRequire(array $packages): bool
    {
        $cmd = array_merge(['composer', 'require', '--with-all-dependencies'], $packages);
        $process = new Process($cmd, base_path(), null, null, 900);
        $process->setTty(true);
        $process->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });
        return $process->isSuccessful();
    }

    protected function patchUserModelForHasRoles(): void
    {
        $userModel = app_path('Models/User.php');
        if (! file_exists($userModel)) {
            $this->warn("User model not found at app/Models/User.php — skipped.");
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
