# Starter Package for Laravel

A Laravel starter kit that automates the installation of Nova, Spatie MediaLibrary, Nova TinyMCE Editor, Spatie Permission, and scaffolds models, seeders, migrations, Nova resources, and policies.

## Features

- Installs Laravel Nova, MediaLibrary, Nova TinyMCE Editor, and Spatie Permission via artisan command
- Publishes migrations and config files automatically
- Copies stubs for models, Nova resources, seeders, migrations, and policies
- Runs database migrations and seeds permissions

## Installation

### Prerequisites

- Laravel 10+
- Composer
- Nova license (if using Nova)

### Step-by-Step Installation Guide

Follow these steps in **chronological order**:

#### Step 1: Install the Package
```bash
composer config repositories.starter-kit vcs git@github.com:itskrayem/starter-package.git
composer require itskrayem/starter-package:dev-main
```

#### Step 2: Install Core Components (Optional)
```bash
php artisan starter:install
```
This installs Laravel Nova, MediaLibrary, and Nova TinyMCE Editor. The installer will prompt for your Laravel Nova email and password if Nova is not already installed.

#### Step 3: Install Permission Features
```bash
php artisan starter:permissions
```
This installs Spatie Permission package and publishes all related stubs (models, Nova resources, policies, seeders, migrations).

#### Step 4: Install Page Features (Optional)
```bash
php artisan starter:page
```
This publishes page-related stubs (model, Nova resource, policy, migration).

#### Step 5: Configure Files **BEFORE** Running Migrations

**5.1. Update DatabaseSeeder.php**
Edit `database/seeders/DatabaseSeeder.php` to include the permissions seeder:
```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Database\Seeders\PermissionsSeeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ... other seeders ...
        
        $this->call([
            PermissionsSeeder::class,
        ]);
    }
}
```

**5.2. Update PermissionsSeeder.php (if you ran step 4)**
If you installed page features, edit `database/seeders/PermissionsSeeder.php` to include page permissions:
```php
$collection = collect([
    'Users',
    'Roles',
    'Permissions',
    'Pages'  // Add this line if you installed page features
]);
```

**5.3. Verify User Model (should already be updated)**
Ensure your `app/Models/User.php` includes the HasRoles trait (this should be automatically done by the installer):
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;
    
    // ... rest of your User model ...
}
```

#### Step 6: Run Database Operations
```bash
php artisan migrate
php artisan db:seed
```

## What's Included

After installation, you'll have:

- **Models:** 
  - `app/Models/User.php` with Spatie HasRoles trait (installed via `php artisan starter:permissions`)
  - `app/Models/Permission.php` (installed via `php artisan starter:permissions`)
  - `app/Models/Role.php` (installed via `php artisan starter:permissions`)
  - `app/Models/Page.php` (installed via `php artisan starter:page`)

- **Nova Resources:** 
  - `app/Nova/Permission.php` (installed via `php artisan starter:permissions`)
  - `app/Nova/Role.php` (installed via `php artisan starter:permissions`)
  - `app/Nova/Page.php` (installed via `php artisan starter:page`)

- **Policies:** 
  - `app/Policies/UserPolicy.php` (installed via `php artisan starter:permissions`)
  - `app/Policies/RolePolicy.php` (installed via `php artisan starter:permissions`)
  - `app/Policies/PermissionPolicy.php` (installed via `php artisan starter:permissions`)
  - `app/Policies/PagePolicy.php` (installed via `php artisan starter:page`)

- **Seeders:** 
  - `database/seeders/PermissionsSeeder.php` (installed via `php artisan starter:permissions`)

- **Migrations:** 
  - Spatie Permission tables (installed via `php artisan starter:permissions`)
  - MediaLibrary tables
  - Additional permission columns (installed via `php artisan starter:permissions`)
  - `create_page_table.php` (installed via `php artisan starter:page`)

## Installation Complete!

Your Laravel starter package is now ready to use. You can access Nova at `/nova` and start building your application with the pre-configured permission system.

## Troubleshooting

- If migrations/configs are not published, run:
    ```bash
    php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag=medialibrary-migrations --force
    php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider" --force
    php artisan config:clear
    ```
- If you see trait errors, ensure required packages are installed.


