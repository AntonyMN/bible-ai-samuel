<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mongodb';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection($this->connection)->table('posts', function (Blueprint $collection) {
            $collection->unique('slug');
            $collection->index('published_at');
            $collection->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->table('posts', function (Blueprint $collection) {
            $collection->dropIndex(['slug']);
            $collection->dropIndex(['published_at']);
            $collection->dropIndex(['status']);
        });
    }
};
