<?php

namespace ItsKrayem\StarterPackage\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class PageCommand extends Command
{
    protected $signature = 'starter:page';
    protected $description = 'Publish page-related stubs (model, nova resource, policy, and migration)';

    public function handle(): int
    {
        $this->info("🚀 Publishing Page stubs...");

        try {
            $this->publishPageStubs();
            $this->info("✅ Page stubs published successfully!");
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Failed to publish page stubs: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    protected function publishPageStubs(): void
    {
        $pageFiles = [
            'models/Page.php' => $this->appPath('Models/Page.php'),
            'nova/Page.php' => $this->appPath('Nova/Page.php'),
            'Policies/PagePolicy.php' => $this->appPath('Policies/PagePolicy.php'),
            'migrations/create_page_table.php' => $this->databasePath('migrations/create_page_table.php'),
        ];

        foreach ($pageFiles as $source => $destination) {
            $sourcePath = __DIR__ . '/../stubs/' . $source;

            if (file_exists($sourcePath)) {
                // Ensure destination directory exists
                $destinationDir = dirname($destination);
                File::ensureDirectoryExists($destinationDir);

                // Copy the file
                File::copy($sourcePath, $destination);
                $this->info("✅ Published: {$destination}");
            } else {
                $this->warn("⚠️ Source file not found: {$sourcePath}");
            }
        }
    }

    protected function appPath(string $path = ''): string
    {
        return app_path($path);
    }

    protected function databasePath(string $path = ''): string
    {
        return database_path($path);
    }

    public function isPagesInstalled(): bool
    {
        $modelPath = $this->appPath('Models/Page.php');
        $novaPath = $this->appPath('Nova/Page.php');
        $policyPath = $this->appPath('Policies/PagePolicy.php');
        
        $modelExists = file_exists($modelPath);
        $novaExists = file_exists($novaPath);
        $policyExists = file_exists($policyPath);
        
        return $modelExists || $novaExists || $policyExists;
    }
}