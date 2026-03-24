<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class BiblePlace extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'bible_places';

    protected $fillable = [
        'place_id',
        'name',
        'place_type',
        'modern_equivalent',
        'notes',
        'openbible_id',
    ];
}
