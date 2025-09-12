<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

class Page extends Model
{
    use SoftDeletes, HasTranslations;
    public $translatable = ['title', 'body'];
}