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
        'category', // events, struggles, victories, prayer points, knowledge base, plans, preference, other
        'importance', // 1-5
        'is_completed',
        'metadata', // array for extra context like conversation_id
        'occurs_at',
        'last_mentioned_at',
        'mention_count',
        'probe_status', // none, probed, completed
        'is_recurring',
        'is_one_off',
        'significance',
        'expires_at',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'importance' => 'integer',
        'metadata' => 'array',
        'occurs_at' => 'datetime',
        'last_mentioned_at' => 'datetime',
        'mention_count' => 'integer',
        'is_recurring' => 'boolean',
        'is_one_off' => 'boolean',
        'expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
