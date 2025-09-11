# Laravel Starter Package

A comprehensive Laravel package that provides quick setup for commonly used components including Laravel Nova, TinyMCE, Spatie MediaLibrary, and permission management system.

## Installation

### 1. Add the package repository to your Laravel project

```bash
composer config repositories.starter-kit vcs git@github.com:itskrayem/starter-package.git
```

### 2. Install the package

```bash
composer require itskrayem/starter-package:dev-main
```

### 3. Configure Nova repository (if using Nova)

```bash
composer config repositories.nova '{"type": "composer", "url": "https://nova.laravel.com"}'
composer config http-basic.nova.laravel.com your-email your-license-key
```

## Usage

The package provides a flexible installation command that allows you to install either all components or specific features only.

### Install All Components (Full Installation)

```bash
php artisan starter:install
```

This installs:
- ✅ Laravel Nova
- ✅ TinyMCE
- ✅ Spatie MediaLibrary
- ✅ Database migrations

### Install Core Components Only

```bash
php artisan starter:install core
```

Same as the full installation above.

### Install Specific Features Only

```bash
php artisan starter:install permission
```

This installs **only** the permission system:
- ✅ Spatie Permission package
- ✅ Permission & Role models
- ✅ Nova resources for permissions
- ✅ Database seeder with pre-configured roles and permissions
- ❌ Skips Nova, TinyMCE, and MediaLibrary

### Install Multiple Features

```bash
php artisan starter:install permission feature2 feature3
```

## What Gets Installed

### Core Components (Nova, TinyMCE, MediaLibrary)

When you run the full installation, the following packages are installed and configured:

#### Laravel Nova
- Adds Nova repository configuration
- Installs `laravel/nova:^5.0`
- Publishes Nova assets
- Runs `nova:install` command

#### TinyMCE
- Installs `tinymce/tinymce` package
- Ready to use in your forms

#### Spatie MediaLibrary
- Installs `spatie/laravel-medialibrary` package
- Publishes migration files
- Ready for file uploads and media management

### Permission Feature

When you install the permission feature, you get:

#### Models
- `app/Models/Permission.php` - Extended Spatie Permission model
- `app/Models/Role.php` - Extended Spatie Role model

#### Nova Resources
- `app/Nova/Permission.php` - Nova resource for managing permissions
- `app/Nova/Role.php` - Nova resource with permission assignment

#### Database Seeder
- `database/seeders/PermissionsSeeder.php` - Comprehensive permission seeder

The seeder creates:

**Default Roles:**
- Super Admin (full access)
- Manager
- Cashier (POS access only)
- Customer
- Walk-in Customer
- Notifications (specific notifications only)

**Permission Groups:**
- Users, Roles, Products, Orders, Categories, etc.
- POS permissions (Access POS, Print Receipt, etc.)
- System permissions (System Access, Browse Settings, etc.)
- Ecommerce permissions (Carts, Wishlists, etc.)

**Default Users:**
- Super Admin (`admin@admin.com` / `password`)
- Cashier (`cashier@cashier.com` / `password`)

## Post-Installation Steps

### 1. Run Migrations

```bash
php artisan migrate
```

### 2. Seed Permissions (if using permission feature)

```bash
php artisan db:seed --class=PermissionsSeeder
```

Or add to your `DatabaseSeeder.php`:

```php
public function run()
{
    $this->call([
        PermissionsSeeder::class,
    ]);
}
```

### 3. Create Nova User (if using Nova)

```bash
php artisan nova:user
```

Or use the pre-seeded admin account:
- Email: `admin@admin.com`
- Password: `password`

## Alternative Publishing Method

You can also publish the stubs manually using Laravel's standard publishing:

```bash
php artisan vendor:publish --tag=starter-package-stubs
```

This publishes all stub files to their respective directories.

## Package Structure

```
src/
├── Console/
│   └── InstallCommand.php          # Main installation command
├── stubs/
│   ├── models/
│   │   ├── Permission.php          # Extended Permission model
│   │   └── Role.php               # Extended Role model
│   ├── nova/
│   │   ├── Permission.php          # Permission Nova resource
│   │   └── Role.php               # Role Nova resource
│   └── seeders/
│       └── PermissionsSeeder.php   # Comprehensive permissions seeder
└── StarterPackageServiceProvider.php
```

## Features

- ✅ **Conditional Installation**: Install only what you need
- ✅ **Smart Package Detection**: Avoids reinstalling existing packages
- ✅ **Comprehensive Permissions**: Pre-configured roles and permissions for common use cases
- ✅ **POS Integration**: Built-in permissions for Point of Sale systems
- ✅ **Nova Integration**: Ready-to-use Nova resources
- ✅ **Flexible Architecture**: Easy to extend with additional features

## Requirements

- Laravel 10.x or 11.x
- PHP 8.1+
- Composer

## License

[Add your license information here]

## Contributing

[Add contributing guidelines here]

## Support

[Add support information here]
