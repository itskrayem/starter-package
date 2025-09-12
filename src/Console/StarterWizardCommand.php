<?php

namespace ItsKrayem\StarterPackage\Console;

use Illuminate\Console\Command;

class StarterWizardCommand extends Command
{
    protected $signature = 'starter:wizard';
    protected $description = 'Interactive wizard to choose which starter package features to install.';

    public function handle(): int
    {
        $this->info('🧙 Welcome to the Starter Package Wizard!');
        $this->newLine();
        $this->info('Select which features you want to install:');

        $choices = [
            'Core (Nova, MediaLibrary, TinyMCE, required)' => true,
            'Permissions (required)' => true,
            'Page (optional)' => false,
        ];

        $selected = $this->choice(
            'Choose additional features to install (use comma to select multiple):',
            array_keys($choices),
            null,
            null,
            true
        );

        // Always install core and permissions
        $this->call('starter:install');
        $this->call('starter:permissions');

        // Install page if selected
        if (in_array('Page (optional)', $selected)) {
            $this->call('starter:page');
        }

        $this->info('✅ All selected features installed!');
        $this->newLine();
        $this->info('Next steps:');
        $this->line('1️⃣ Run migrations: php artisan migrate');
        $this->line('2️⃣ Run seeders: php artisan db:seed');
        $this->line('3️⃣ Configure DatabaseSeeder.php and PermissionsSeeder.php as needed.');
        $this->line('4️⃣ See README.md for more details.');

        return Command::SUCCESS;
    }
}
