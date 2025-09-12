<?php

namespace App\Models;

use App\Observers\PageObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

#[ObservedBy([PageObserver::class])]
class Page extends Model
{
    use SoftDeletes, HasTranslations;
    public $translatable = ['title', 'body'];
}