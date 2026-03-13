<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Verse extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'verses';

    protected $fillable = [
        'version',
        'book',
        'chapter',
        'verse',
        'text',
        'full_reference', // e.g., "John 3:16"
    ];
}
