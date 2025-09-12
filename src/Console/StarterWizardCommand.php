<?php

namespace ItsKrayem\StarterPackage\Console;

use Illuminate\Console\Command;
use Laravel\Prompts;

class StarterWizardCommand extends Command
{
    protected $signature = 'starter:wizard';
    protected $description = 'Interactive wizard to choose which starter package features to install.';

    public function handle(): int
    {
        $this->info('🧙 Welcome to the Starter Package Wizard!');
        $this->newLine();
        $this->info('will be installed by default: 
        -Core (Nova, MediaLibrary, TinyMCE) 
        -Permissions.');
        $this->info('Select any additional features you want to install:');

        $selected = Prompts\multiselect(
            label: 'Optional features:',
            options: [
                'Page' => 'page',
            ],
            default: [],
            hint: 'Core and Permissions are always installed.'
        );

        // Always install core and permissions
        $this->call('starter:install');
        $this->call('starter:permissions');

        // Install page if selected
        if (in_array('page', $selected)) {
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
