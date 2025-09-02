<?php

namespace App\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\BooleanGroup;
use Laravel\Nova\Panel;
use Laravel\Nova\Http\Requests\NovaRequest;
use Spatie\Permission\Models\Permission;

class Role extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\Role>
     */
    public static $model = \App\Models\Role::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'name';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
    	'name',
    ];

    /**
     * Get the fields displayed by the resource.
     *
     * @return array<int, \Laravel\Nova\Fields\Field>
     */
    public function fields(NovaRequest $request): array
    {
    	$permissions = Permission::select('id', 'name', 'group')
    	->orderBy('group')
    	->orderBy('id')
    	->get()
    	->groupBy('group');

    	$permission_fields = [];

    	foreach ($permissions as $group => $permissionsInGroup) {
    		$permission_fields[] = BooleanGroup::make(ucfirst($group), "permissions_{$group}")
    		->options(
    			$permissionsInGroup->pluck('name', 'id')->toArray()
    		)
    		->resolveUsing(function ($value, $model) use ($group) {

    			$assignedPermissions = $model->permissions
    			->where('group', $group)
    			->pluck('id')
    			->toArray();

    			return array_fill_keys($assignedPermissions, true);
    		})
    		->fillUsing(function ($request, $model, $attribute, $requestAttribute) use ($group) {

    			$selectedPermissions = json_decode($request->$requestAttribute, true);

    			if (!is_array($selectedPermissions)) {
    				$selectedPermissions = [];
    			}

    			$model->permissionsForGroups[$group] = $selectedPermissions;
    		})->hideFromIndex();
    	}

    	return [

    		ID::make()
    		->sortable(),

    		Text::make('Name')
    		->rules('required', 'max:255')
    		->creationRules('unique:roles,name')
    		->updateRules('unique:roles,name,{{resourceId}}'),

    		Panel::make('Permissions', $permission_fields)
    		->collapsedByDefault()
    	];
    }

    /**
     * Get the cards available for the resource.
     *
     * @return array<int, \Laravel\Nova\Card>
     */
    public function cards(NovaRequest $request): array
    {
    	return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @return array<int, \Laravel\Nova\Filters\Filter>
     */
    public function filters(NovaRequest $request): array
    {
    	return [];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @return array<int, \Laravel\Nova\Lenses\Lens>
     */
    public function lenses(NovaRequest $request): array
    {
    	return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @return array<int, \Laravel\Nova\Actions\Action>
     */
    public function actions(NovaRequest $request): array
    {
    	return [];
    }
}
