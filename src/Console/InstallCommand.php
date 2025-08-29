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
            $this->installNova();
            $this->installMediaLibrary();
            $this->installOptionalFeatures();

            // Run all migrations once at the end
            $this->runMigrations();

            // Seed after migrations
            $this->runSeeding();

            $this->info("🎉 Starter Package installation complete!");
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
        $this->info("Setting up Laravel Nova migrations...");

        $this->publishMigrationsWithFallback(
            provider: 'Laravel\\Nova\\NovaServiceProvider',
            tag: 'nova-migrations',
            vendorPath: base_path('vendor/laravel/nova/database/migrations'),
            expectedPatterns: [
                database_path('migrations/*nova*.php'),
                database_path('migrations/*action_events*.php'),
                database_path('migrations/*nova_notifications*.php'),
                database_path('migrations/*field_attachments*.php'),
            ],
            packageName: 'Nova'
        );
    }

    // Generic publish with fallback to copying migrations from vendor
    protected function publishMigrationsWithFallback(
        string $provider,
        string $tag,
        string $vendorPath,
        array $expectedPatterns,
        string $packageName
    ): void {
        if ($this->migrationsExist($expectedPatterns)) {
            $this->line("{$packageName} migrations already present. Skipping publish.");
            return;
        }

        $this->call('vendor:publish', [
            '--provider' => $provider,
            '--tag' => $tag,
            '--force' => true,
        ]);

        if ($this->migrationsExist($expectedPatterns)) {
            $this->info("✅ {$packageName} migrations published.");
            return;
        }

        $this->info("No {$packageName} migrations published via tag. Falling back to manual copy...");
        $this->copyGenericMigrationsSafely($vendorPath);

        if ($this->migrationsExist($expectedPatterns)) {
            $this->info("✅ {$packageName} migrations copied from vendor.");
        } else {
            $this->warn("⚠️ {$packageName} migrations still not found. Please verify the installed package version.");
        }
    }

    protected function migrationsExist(array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            $files = File::glob($pattern);
            if (!empty($files)) {
                return true;
            }
        }
        return false;
    }

    protected function installMediaLibrary(): void
    {
        $this->info("Setting up Spatie MediaLibrary...");

        $this->publishMigrationsWithFallback(
            provider: 'Spatie\\MediaLibrary\\MediaLibraryServiceProvider',
            tag: 'laravel-medialibrary-migrations',
            vendorPath: base_path('vendor/spatie/laravel-medialibrary/database/migrations'),
            expectedPatterns: [database_path('migrations/*_create_media_table.php')],
            packageName: 'MediaLibrary'
        );
    }

    protected function installPermission(): void
    {
        $this->info("Setting up Spatie Permission...");

        $this->publishMigrationsWithFallback(
            provider: 'Spatie\\Permission\\PermissionServiceProvider',
            tag: 'laravel-permission-migrations',
            vendorPath: base_path('vendor/spatie/laravel-permission/database/migrations'),
            expectedPatterns: [database_path('migrations/*_create_permission_tables.php')],
            packageName: 'Permission'
        );
        
        // Patch User model
        $this->patchUserModelForHasRoles();
        
        $this->info("✅ Permission feature installed.");
    }

    protected function runMigrations(): void
    {
        $this->info("Running migrations...");
        
        try {
            $this->call('migrate', ['--force' => true]);
            $this->info("✅ Migrations applied successfully.");
        } catch (\Exception $e) {
            $this->warn("⚠️ Some migrations may have failed: " . $e->getMessage());
            // Continue execution, don't fail completely
        }
    }

    protected function runSeeding(): void
    {
        $this->info("Seeding database...");
        try {
            $this->call('db:seed', ['--force' => true]);
            $this->info("✅ Seeding completed.");
        } catch (\Exception $e) {
            $this->warn("⚠️ Seeding may have failed: " . $e->getMessage());
            // Continue execution, don't fail completely
        }
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
        // Check if use statement already exists
        if (Str::contains($content, "use {$useStatement};")) {
            return $content;
        }

        // Find the last use statement and add after it
        $lines = explode("\n", $content);
        $insertIndex = -1;
        
        foreach ($lines as $index => $line) {
            if (Str::startsWith(trim($line), 'use ') && Str::endsWith(trim($line), ';')) {
                $insertIndex = $index;
            }
        }
        
        if ($insertIndex !== -1) {
            array_splice($lines, $insertIndex + 1, 0, "use {$useStatement};");
            return implode("\n", $lines);
        }

        // Fallback: add after namespace
        $pattern = '/(\nnamespace\s+App\\\Models;\s*\n)/';
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
        // Check if trait is already used
        if (Str::contains($content, "use {$traitName};")) {
            return $content;
        }

        // Find the class declaration and add trait
        $pattern = '/(class\s+User\s+extends\s+[^{]+\{\s*)/';
        
        $replacement = preg_replace(
            $pattern,
            "$1\n    use {$traitName};\n",
            $content,
            1
        );

        return $replacement ?? $content;
    }

    /**
     * Generic migration copier: copies .php files from a vendor migration folder
     * into the app's database/migrations with unique timestamps. If the migration
     * file already exists in destination (by suffix), it's skipped.
     */
    protected function copyGenericMigrationsSafely(string $sourceDir): void
    {
        if (!is_dir($sourceDir)) {
            $this->warn("Source migrations directory not found: {$sourceDir}");
            return;
        }

        $files = File::files($sourceDir);
        if (empty($files)) {
            $this->warn("No migration files found in: {$sourceDir}");
            return;
        }

        $timestamp = now();
        $copiedAny = false;

        foreach ($files as $file) {
            $name = $file->getFilename();
            // Accept both .php and .php.stub
            $isPhp = Str::endsWith($name, '.php');
            $isStub = Str::endsWith($name, '.php.stub');
            if (!$isPhp && !$isStub) {
                continue;
            }
            // If a migration with this suffix already exists, skip
            $baseSuffix = $isStub ? Str::replaceLast('.stub', '', $name) : $name;
            $existing = File::glob(database_path('migrations/*_' . $baseSuffix));
            if (!empty($existing)) {
                continue;
            }

            $finalName = $baseSuffix; // ensure .php extension only
            $newName = $timestamp->format('Y_m_d_His') . '_' . $finalName;
            $destination = database_path('migrations/' . $newName);

            // Copy and, if stub, strip .stub by writing to destination without .stub
            File::copy($file->getPathname(), $destination);
            $this->line("Published: {$name} -> {$newName}");
            $timestamp->addSecond();
            $copiedAny = true;
        }

        if ($copiedAny) {
            $this->info("✅ Migrations copied successfully from {$sourceDir}.");
        }
    }
}