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
composer require itskrayem/starter-package:dev-main
```

> **Note**: Using HTTPS URL for better compatibility. If you prefer SSH and have it configured, you can replace `https://github.com/itskrayem/starter-package.git` with `git@github.com:itskrayem/starter-package.git`.

#### Step 2: Configure User Model (BEFORE running commands)
Edit `app/Models/User.php` to include the HasRoles trait:
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

#### Step 3: Configure DatabaseSeeder (BEFORE running commands)
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

#### Step 4: Run Installation Commands

**Interactive Wizard (Recommended)**
```bash
php artisan starter:wizard
```
The wizard will automatically install core components and permissions, then ask if you want to install page features.

#### Step 5: Configure PermissionsSeeder (ONLY if you installed page features)
> ⚠️ **IMPORTANT**: This step is only required if you installed page features in Step 4.

If you installed page features, edit `database/seeders/PermissionsSeeder.php` to include page permissions:
```php
$collection = collect([
    'Users',
    'Roles',
    'Permissions',
    'Pages'  // Add this line ONLY if you installed page features
]);
```

**If you did NOT install page features, skip this step - your PermissionsSeeder is already properly configured.**

#### Step 6: Run Database Operations
```bash
php artisan migrate
php artisan db:seed
```

#### Step 7: Create Your First Nova User
```bash
php artisan nova:user
```

## Manual Installation (Alternative to Wizard)

If you prefer to run commands individually instead of using the wizard, follow these steps:

**Install Core Components**
```bash
php artisan starter:core
```
This installs Laravel Nova, MediaLibrary, and Nova TinyMCE Editor.

**Install Permission System**
```bash
php artisan starter:permissions
```
This installs Spatie Permission package and publishes all related stubs.

**Install Page Features (Optional)**
```bash
php artisan starter:page
```
This publishes page-related stubs. If you run this command, remember to configure PermissionsSeeder in Step 5.

## Available Commands

- `php artisan starter:wizard` - Interactive wizard to select features (recommended)
- `php artisan starter:core` - Install core components (Nova, MediaLibrary, TinyMCE)
- `php artisan starter:permissions` - Install permission system
- `php artisan starter:page` - Install page features

## Important: Configuration Order

**BEFORE running any installation commands:**
1. ✅ Install the package
2. ✅ Configure User model with HasRoles trait
3. ✅ Configure DatabaseSeeder to include PermissionsSeeder

**AFTER running installation commands:**
1. ✅ Configure PermissionsSeeder **ONLY if you installed page features** (add 'Pages' to collection)
2. ✅ Run migrations: `php artisan migrate`
3. ✅ Run seeders: `php artisan db:seed`
4. ✅ Create Nova user: `php artisan nova:user`

This order prevents trait errors and ensures proper database setup.

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


