<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Post extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'posts';

    protected $fillable = [
        'title',
        'slug',
        'content',
        'image_url',
        'topic',
        'status', // draft, published
        'published_at',
        'meta_description',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public function getUrlAttribute()
    {
        return config('app.url') . "/blog/{$this->slug}";
    }
}
