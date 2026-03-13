<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class UsageMetric extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'usage_metrics';

    protected $fillable = [
        'date',
        'authenticated_calls',
        'unauthenticated_calls',
        'active_users',
        'countries', // Array of country_code => count
    ];

    protected $casts = [
        'date' => 'date',
        'countries' => 'array',
    ];
}
