<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class PermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        
        $admin = Role::firstOrCreate(['name' => 'Super Admin']);
        $role2 = Role::firstOrCreate(['name' => 'Notifications']);
        $roleCustomer = Role::firstOrCreate(['name' => 'Customer']);
        $roleWalkInCustomer = Role::firstOrCreate(['name' => 'Walk-in Customer']);
        $roleCashier = Role::firstOrCreate(['name' => 'Cashier']);
        $roleManager = Role::firstOrCreate(['name' => 'Manager']);

        $collection = collect([
            'Users',
            'Roles',
            'Sliders',
            'Categories',
            'Brands',
            'Tags',
            'Products',
            'DiscountBanners',
            'PurchaseOrders',
            'Orders',
            'Collections',
            'Promocodes',
            'Pages',
            'PaymentMethods',
            'HomeCollections',
            'CollectionProducts',
            'Addresses',
            'Zones',
            'Reviews',
            'Shifts',
            'Registers',
            'Invoices',
            'Warehouses',
            'InventoryStocks',
            'Subscribers'
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
        Permission::updateOrCreate(['name' => 'System Logs'], ['group' => 'System', 'name' => 'System Logs']);
        Permission::updateOrCreate(['name' => 'Access Horizon'], ['group' => 'System', 'name' => 'Access Horizon']);
        Permission::updateOrCreate(['name' => 'Browse Permissions'], ['group' => 'System', 'name' => 'Browse Permissions']);
        Permission::updateOrCreate(['name' => 'Browse Settings'], ['group' => 'System', 'name' => 'Browse Settings']);
        Permission::updateOrCreate(['name' => 'Cancel Order'], ['group' => 'System', 'name' => 'Cancel Order']);
        Permission::updateOrCreate(['name' => 'Print Order'], ['group' => 'System', 'name' => 'Print Order']);
        
        Permission::updateOrCreate(['name' => 'Access POS'], ['group' => 'POS', 'name' => 'Access POS']);
        Permission::updateOrCreate(['name' => 'Notify Close Register'], ['group' => 'POS', 'name' => 'Notify Close Register']);
        Permission::updateOrCreate(['name' => 'End Shifts'], ['group' => 'POS', 'name' => 'End Shifts']);
        Permission::updateOrCreate(['name' => 'Browse Shifts'], ['group' => 'POS', 'name' => 'Browse Shifts']);
        Permission::updateOrCreate(['name' => 'Print Receipt'], ['group' => 'POS', 'name' => 'Print Receipt']);
        Permission::updateOrCreate(['name' => 'Browse CashIns'], ['group' => 'POS', 'name' => 'Browse CashIns']);
        Permission::updateOrCreate(['name' => 'Browse CashOuts'], ['group' => 'POS', 'name' => 'Browse CashOuts']);
        Permission::updateOrCreate(['name' => 'Browse Invoice Items'], ['group' => 'POS', 'name' => 'Browse Invoice Items']);
        
        Permission::updateOrCreate(['name' => 'Browse Carts'], ['group' => 'Ecommerce', 'name' => 'Browse Carts']);
        Permission::updateOrCreate(['name' => 'Browse Wishlists'], ['group' => 'Ecommerce', 'name' => 'Browse Wishlists']);
        Permission::updateOrCreate(['name' => 'Browse Order Items'], ['group' => 'Ecommerce', 'name' => 'Browse Order Items']);
        
        Permission::updateOrCreate(['name' => 'Export Categories'], ['group' => 'Categories', 'name' => 'Export Categories']);
        Permission::updateOrCreate(['name' => 'Import Products'], ['group' => 'Products', 'name' => 'Import Products']);
        Permission::updateOrCreate(['name' => 'Export Products'], ['group' => 'Products', 'name' => 'Export Products']);
        Permission::updateOrCreate(['name' => 'Bulk Edit Products'], ['group' => 'Products', 'name' => 'Bulk Edit Products']);
        
        Permission::updateOrCreate(['name' => 'Financial Summary'], ['group' => 'Reports', 'name' => 'Financial Summary']);
        Permission::updateOrCreate(['name' => 'Online Sales Reports'], ['group' => 'Reports', 'name' => 'Online Sales Reports']);

        // create admin permissions
        // give Super Admin access to all permissions
        $admin->syncPermissions(Permission::where('name', '<>', 'Notify Close Register')->get());
        $role2->givePermissionTo('Notify Close Register');
        $roleCashier->syncPermissions(Permission::where('name', 'Access POS')->first());

        // check if super admin user exists
        $user = User::whereHas('roles', function ($q) {
            $q->where('name', 'Super Admin');
        })->first();

        if (!$user) {
            $user = User::create([
                'name'      => 'Super Admin',
                'email'     => 'admin@admin.com',
                'password'  => bcrypt('password')
            ]);
        }

        // assign super-admin role to default user
        $user->assignRole($admin->name);

        $cashier = User::whereHas('roles', function ($q) {
            $q->where('name', 'Cashier');
        })->first();

        if (!$cashier) {
            $cashier = User::create([
                'name'      => 'Cashier',
                'email'     => 'cashier@cashier.com',
                'password'  => bcrypt('password')
            ]);
        }

        // assign Cashier role to default user
        $cashier->assignRole($roleCashier->name);
    }
}
