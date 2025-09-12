<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

class Page extends Model
{
    use SoftDeletes;
    public $translatable = ['title', 'body'];
}