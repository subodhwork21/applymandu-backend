<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Article extends Model
{
    protected $table = 'articles';
    protected $fillable = [
        'title',
        'content',
        'slug',
        'image',
        'author',
    ];

    public function imagePath(): Attribute
    {
        if ($this?->image) {
            $path =  Storage::disk('public')->path($this->image);
        }

        if (isset($path) && file_exists($path)) {
            return Attribute::make(
                get: fn() => asset('storage/' . $this->image),
            );
        } else {
            return Attribute::make(
                get: fn() => asset('image.png')
            );
        }
    }
}
