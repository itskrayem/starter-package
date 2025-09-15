<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Page extends Model implements HasMedia
{
    use SoftDeletes, InteractsWithMedia;
    public $translatable = ['title', 'body'];

    /**
     * Register media collections
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
    }

    /**
     * Get the main image URL
     */
    public function getImageUrl(string $conversion = ''): string
    {
        $media = $this->getFirstMedia('images');
        if (!$media) {
            return '';
        }
        
        return $conversion ? $media->getUrl($conversion) : $media->getUrl();
    }
}