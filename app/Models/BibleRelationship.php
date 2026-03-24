<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class BibleRelationship extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'bible_relationships';

    protected $fillable = [
        'person_id_1',
        'person_id_2',
        'relationship_type',
        'relationship_category',
        'reference_id',
        'notes',
    ];
}
