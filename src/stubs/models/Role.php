<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    public $permissionsForGroups = [];

    protected static function boot()
    {
        parent::boot();

        static::saved(function ($model) {

            $model->permissionsForGroups = $model->permissionsForGroups ?? [];

            $permissions = [];

            foreach ($model->permissionsForGroups as $group => $perms) {
                foreach ($perms as $key => $value) {
                    if ($value) {
                        $permissions[] = $key;
                    }
                }
            }

            $model->permissions()->sync($permissions);
        });
    }
}