<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class BiblePerson extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'bible_people';

    protected $fillable = [
        'person_id',
        'name',
        'sex',
        'tribe',
        'notes',
        'aliases',
    ];
}
