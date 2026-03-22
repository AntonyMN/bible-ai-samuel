<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class UsageMetric extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'usage_metrics';

    protected $fillable = [
        'date',
        'authenticated_calls', // Legacy/General
        'unauthenticated_calls', // Legacy/General
        'page_views',
        'authenticated_queries',
        'unauthenticated_queries',
        'active_users',
        'countries', // Array of country_code => count
        'post_views', // Array of slug => count
    ];

    protected $casts = [
        'date' => 'date',
        'countries' => 'array',
    ];
}
