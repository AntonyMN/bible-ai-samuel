<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Conversation extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'conversations';

    protected $fillable = [
        'user_id',
        'title',
        'messages', // Array of {role, content, citations}
    ];

    protected $casts = [
        'messages' => 'array',
    ];
}
