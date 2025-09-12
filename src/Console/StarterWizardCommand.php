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
        $this->warn('⚠️  IMPORTANT: Make sure you have configured your User model and DatabaseSeeder BEFORE running this wizard.');
        $this->info('   - User model should have HasRoles trait');
        $this->info('   - DatabaseSeeder should include PermissionsSeeder');
        $this->newLine();
        $this->info('will be installed by default: 
        -Core (Nova, MediaLibrary, TinyMCE) 
        -Permissions.
        ');
        $this->info('Select any additional features you want to install: (use spacebar to select)');

        $selected = Prompts\multiselect(
            label: 'Optional features:',
            options: [
                'Page' => 'page',
            ],
            default: [],
            hint: 'Core and Permissions are always installed.'
        );

        // Check if core is already installed
        $coreCommand = new \ItsKrayem\StarterPackage\Console\CoreCommand();
        $coreInstalled = $coreCommand->isCoreInstalled();
        if ($coreInstalled) {
            $this->info('✔ Core (Nova, MediaLibrary, TinyMCE) already installed.');
        } else {
            $this->call('starter:core');
        }

        // Check if permissions are already installed
        $permissionsCommand = new \ItsKrayem\StarterPackage\Console\PermissionsCommand();
        $permissionsInstalled = $permissionsCommand->isPermissionsInstalled();
        if ($permissionsInstalled) {
            $this->info('✔ Permissions already installed.');
        } else {
            $this->call('starter:permissions');
        }

        // Install page if selected
        $pagesInstalled = false;
        if (!empty($selected)) {  // If anything is selected, assume Page was chosen
            // Check if pages are already installed
            $pageCommand = new \ItsKrayem\StarterPackage\Console\PageCommand();
            $pagesInstalled = $pageCommand->isPagesInstalled();
            
            if ($pagesInstalled) {
                $this->info('✔ Page features already installed.');
            } else {
                $this->info('📦 Installing page features...');
                $this->call('starter:page');
            }
        }

        $this->info('✅ All selected features installed!');
        $this->newLine();
        $this->info('Next steps:');
        if (!empty($selected) && !$pagesInstalled) {
            $this->warn('⚠️  IMPORTANT: You installed page features - configure PermissionsSeeder');
            $this->line('1️⃣ Update PermissionsSeeder.php to add \'Pages\' to the collection');
        } elseif (!empty($selected) && $pagesInstalled) {
            $this->info('1️⃣ Page features were already installed - PermissionsSeeder should be configured');
        } else {
            $this->info('1️⃣ PermissionsSeeder is already configured (no page features installed)');
        }
        $this->line('2️⃣ Run migrations: php artisan migrate');
        $this->line('3️⃣ Run seeders: php artisan db:seed');
        $this->line('4️⃣ Create your first Nova user: php artisan nova:user');
        $this->newLine();
        $this->info('📖 See README.md for detailed configuration steps.');

        return Command::SUCCESS;
    }
}
