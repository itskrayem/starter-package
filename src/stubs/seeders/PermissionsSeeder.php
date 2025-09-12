<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class PermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // create Super Admin role if doesn't exist
        $admin = Role::firstOrCreate(['name' => 'Super Admin']);

        $collection = collect([
            'Users',
            'Roles',
            'Permissions'
        ]);

        $collection->each(function ($item, $key) {
            Permission::updateOrCreate(['name' => 'Browse ' . $item], ['group' => $item, 'name' => 'Browse ' . $item]);
            Permission::updateOrCreate(['name' => 'Read ' . $item], ['group' => $item, 'name' => 'Read ' . $item]);
            Permission::updateOrCreate(['name' => 'Add ' . $item], ['group' => $item, 'name' => 'Add ' . $item]);
            Permission::updateOrCreate(['name' => 'Edit ' . $item], ['group' => $item, 'name' => 'Edit ' . $item]);
            Permission::updateOrCreate(['name' => 'Delete ' . $item], ['group' => $item, 'name' => 'Delete ' . $item]);
            Permission::updateOrCreate(['name' => 'Force Delete ' . $item], ['group' => $item, 'name' => 'Force Delete ' . $item]);
            Permission::updateOrCreate(['name' => 'Restore ' . $item], ['group' => $item, 'name' => 'Restore ' . $item]);
        });

        Permission::updateOrCreate(['name' => 'System Access'], ['group' => 'System', 'name' => 'System Access']);

        // give Super Admin access to all permissions
        $admin->syncPermissions(Permission::all());

        // check if super admin user exists
        $user = User::whereHas('roles', function($q){
            $q->where('name', 'Super Admin');
        })->first();

        if (!$user) {
            $user = User::create([
                'name'      => 'Super Admin',
                'email'     => 'admin@admin.com',
                'password'  => bcrypt('Saf3Hav3n!')
            ]);
        }

        // assign super-admin role to default user
        $user->assignRole($admin);
    }
}
