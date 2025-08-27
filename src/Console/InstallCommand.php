<?php

namespace YourOrg\StarterKit\Console;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class InstallCommand extends Command
{
    protected $signature = 'starter:install {feature?}';
    protected $description = 'Install optional starter kit features or core setup';

    public function handle(): int
    {
        $this->info("Step 1: Installing Nova 5...");

        // Nova install (private repo)
        $ok = $this->runComposerRequire([
            'laravel/nova:^5.0'
        ]);
        if (! $ok) {
            $this->error("Failed to install Nova 5. Check your auth token or repo access.");
            return self::FAILURE;
        }
        $this->info("✅ Nova installed!");

        // Step 2: Install other core packages
        $this->info("Step 2: Installing core packages...");
        $this->runComposerRequire([
            'tinymce/tinymce',
            'spatie/laravel-medialibrary'
        ]);
        $this->info("✅ Core packages installed!");

        // Step 3: Optional feature if argument provided
        $feature = $this->argument('feature');
        if ($feature === 'permission') {
            $this->info("Step 3: Installing Spatie Permission...");
            $this->runComposerRequire(['spatie/laravel-permission']);
            $this->callSilent('vendor:publish', [
                '--provider' => 'Spatie\Permission\PermissionServiceProvider',
            ]);
            $this->patchUserModelForHasRoles();
            $this->info("✅ Permission installed and User model updated!");
        }

        $this->info("🎉 Starter kit setup complete!");
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
            $this->warn("User model not found — skipped.");
            return;
        }
        $content = file_get_contents($userModel);
        if (strpos($content, 'HasRoles') === false) {
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
        }
    }
}
