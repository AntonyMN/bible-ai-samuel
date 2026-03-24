<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Memory extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'memories';

    protected $fillable = [
        'user_id',
        'content',
        'category', // plan, struggle, event, preference, other
        'importance', // 1-5
        'is_completed',
        'metadata', // array for extra context like conversation_id
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'importance' => 'integer',
        'metadata' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
