# Starter Package for Laravel

A Laravel starter kit that automates the installation of Nova, Spatie MediaLibrary, TinyMCE, Spatie Permission, and scaffolds models, seeders, migrations, Nova resources, and policies.

## Features

- Installs Laravel Nova, MediaLibrary, TinyMCE, and Spatie Permission via artisan command
- Publishes migrations and config files automatically
- Copies stubs for models, Nova resources, seeders, migrations, and policies
- Runs database migrations and seeds permissions

## Installation

### Prerequisites

- Laravel 10+
- Composer
- Nova license (if using Nova)

### Steps

1. **Add the starter package repository and install:**
    ```bash
    composer config repositories.starter-kit vcs git@github.com:itskrayem/starter-package.git
    composer require itskrayem/starter-package:dev-main
    ```

2. **Run the installer:**
    ```bash
    php artisan starter:install
    ```

    The installer will automatically prompt for your Laravel Nova email and password if Nova is not already installed. Your credentials will be configured locally for this project only.

    To install permission features:
    ```bash
    php artisan starter:install permission
    ```

    To publish page-related stubs (model, nova resource, policy, migration):
    ```bash
    php artisan starter:page
    ```

3. **Run migrations and seeders:**
    ```bash
    php artisan migrate
    php artisan db:seed
    ```

    **Important:** To enable the permissions seeder, add the following to your `database/seeders/DatabaseSeeder.php`:
    ```php
    use Database\Seeders\PermissionsSeeder;

    // ...

    public function run(): void
    {
        // ... other seeders ...

        $this->call([
            PermissionsSeeder::class,
        ]);
    }
    ```

    **Configure User Model for Permissions:** Update your `app/Models/User.php` to include the HasRoles trait:
    ```php
    <?php

    namespace App\Models;

    // ... other imports ...
    use Spatie\Permission\Traits\HasRoles;

    class User extends Authenticatable
    {
        use HasRoles; // Add this trait

        // ... rest of your User model ...

        protected $fillable = [
            'name',
            'email',
            'password',
            // Add any other fillable attributes you need
        ];

        // ... rest of your User model ...
    }
    ```

## What’s Included

- **Models:** `app/Models/User.php` with Spatie HasRoles trait, `app/Models/Page.php`
- **Nova Resources:** `app/Nova/User.php`, `app/Nova/Page.php`
- **Seeders:** `database/seeders/PermissionsSeeder.php`
- **Migrations:** MediaLibrary and Permission migrations, plus custom migrations from stubs including `create_page_table.php`
- **Policies:** `app/Policies/UserPolicy.php`, `RolePolicy.php`, `PermissionPolicy.php`, `PagePolicy.php`

## Next Steps

- Generate Nova User resource:
    ```bash
    php artisan nova:resource User
    ```
- Create your first Nova user:
    ```bash
    php artisan nova:user
    ```

## Troubleshooting

- If migrations/configs are not published, run:
    ```bash
    php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag=medialibrary-migrations --force
    php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider" --force
    php artisan config:clear
    ```
- If you see trait errors, ensure required packages are installed.


