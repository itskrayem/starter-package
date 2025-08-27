<?php

namespace YourOrg\StarterKit\Console;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class InstallCommand extends Command
{
    protected $signature = 'starter:install {feature : e.g. permission}';
    protected $description = 'Install optional starter kit features';

    public function handle(): int
    {
        $feature = $this->argument('feature');

        if ($feature === 'permission') {
            $this->info('Installing spatie/laravel-permission ...');

            // Run: composer require spatie/laravel-permission
            $ok = $this->runComposerRequire(['spatie/laravel-permission']);
            if (! $ok) {
                $this->error('Composer require failed. Please run it manually.');
                return self::FAILURE;
            }

            // Publish vendor assets
            $this->callSilent('vendor:publish', [
                '--provider' => 'Spatie\Permission\PermissionServiceProvider',
            ]);

            // Try to update User model to use HasRoles
            $this->patchUserModelForHasRoles();

            $this->info('✅ Permission installed & User model updated (if found).');
            $this->warn('Run: php artisan migrate');
            return self::SUCCESS;
        }

        $this->error("Unknown feature: {$feature}");
        return self::INVALID;
    }

    protected function runComposerRequire(array $packages): bool
    {
        $cmd = array_merge(['composer', 'require'], $packages);
        $process = new Process($cmd, getcwd(), null, null, 900);
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
        if (strpos($content, 'HasRoles') !== false) {
            $this->line('User model already uses HasRoles — skipped.');
            return;
        }

        if (! str_contains($content, 'use Spatie\\Permission\\Traits\\HasRoles;')) {
            $content = preg_replace(
                '/(\nnamespace\s+App\\\Models;\s*\n(?:use[^\n]+\n)*)/m',
                "$1use Spatie\\Permission\\Traits\\HasRoles;\n",
                $content,
                1
            ) ?? $content;
        }

        $content = preg_replace(
            '/(class\s+User\s+extends\s+[^\\{]+\\{)/m',
            "$1\n    use HasRoles;\n",
            $content,
            1
        ) ?? $content;

        file_put_contents($userModel, $content);
    }
}
