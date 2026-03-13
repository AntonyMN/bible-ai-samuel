<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $user = \App\Models\User::where('email', 'antonymuriuki7@gmail.com')->first();
        if ($user) {
            $user->is_admin = true;
            $user->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $user = \App\Models\User::where('email', 'antonymuriuki7@gmail.com')->first();
        if ($user) {
            $user->is_admin = false;
            $user->save();
        }
    }
};
