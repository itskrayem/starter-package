<?php

namespace App\Nova;
use Laravel\Nova\Http\Requests\NovaRequest; 
use Illuminate\Http\Request;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\File;
use Laravel\Nova\Fields\Image;
use Laravel\Nova\Panel;
use Laravel\Nova\Fields\Slug;
use Murdercode\TinymceEditor\TinymceEditor;
use Laravel\Nova\Fields\Boolean;


class Page extends Resource
{
    public static $model = \App\Models\Page::class;
   public static $title = 'id';
    public static $search = ['id'];

    public function fields(NovaRequest $request)
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

            File::make('Upload Image', 'image') 
                ->store(function ($request, $model) {
                    if ($request->hasFile('image')) {
                        $model->addMediaFromRequest('image')
                            ->toMediaCollection('images', 'public'); 
                    }
                    return []; 
                })
                ->onlyOnForms(),

            Panel::make('Media Files', [
          
                Text::make('Image Gallery', function () {
                    $mediaItems = $this->getMedia('images');
                    if ($mediaItems->isEmpty()) return 'No images';
                    $gallery = '';
                    foreach ($mediaItems as $media) {
                        $url = $media->getFullUrl();
                        $gallery .= "<img src='{$url}' style='max-width:200px; border-radius:8px; margin:5px;' />";
                    }
                    return $gallery;
                })->asHtml()->onlyOnDetail(), 
                
                // Text::make('File Downloads', function () {
                //     $mediaItems = $this->getMedia('files');
                //     if ($mediaItems->isEmpty()) return 'No files';
                //     $links = '';
                //     foreach ($mediaItems as $media) {
                //         $url = $media->getFullUrl();
                //         $name = $media->file_name;
                //         $links .= "<a href='{$url}' download class='text-primary underline block mb-1'>{$name}</a>";
                //     }
                //     return $links;
                // })->asHtml()->onlyOnDetail(),
            ]),

            Boolean::make('Active?', 'is_active')
            ->default(0)
            ->filterable()
            ->help('If Page is not active it will be hidden in all website'),
        ];
    }
}