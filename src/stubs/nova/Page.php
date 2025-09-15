<?php

namespace App\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Slug;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Image;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Http\Requests\NovaRequest;
use Murdercode\TinymceEditor\TinymceEditor;
use Illuminate\Support\Facades\Storage;

class Page extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\Page>
     */
    public static $model = \App\Models\Page::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'id';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id',
    ];

    /**
     * Get the fields displayed by the resource.
     *
     * @return array<int, \Laravel\Nova\Fields\Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),

            Text::make('Title')
                ->rules(['nullable', 'max:255'])
                ->sortable(),
                
            Slug::make('Slug')->from('Title'),

            TinymceEditor::make('Description', 'body')
                ->rules(['nullable'])
                ->fullWidth(),

            Image::make('Image')
                ->disk('public')
                ->path('pages')
                ->store(function ($request, $model) {
                    if ($request->image) {
                        $model->addMediaFromRequest('image')
                            ->toMediaCollection('images');
                    }
                    return null;
                })
                ->preview(function ($value, $disk) {
                    return $value ? \Illuminate\Support\Facades\Storage::disk($disk)->url($value) : null;
                })
                ->thumbnail(function () {
                    return $this->getImageUrl('thumb') ?: $this->getImageUrl();
                })
                ->deletable(false),

            Boolean::make('Active?', 'is_active')
                ->default(0)
                ->filterable()
                ->help('If Page is not active it will be hidden in all website'),
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