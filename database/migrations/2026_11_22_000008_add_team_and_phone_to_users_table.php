<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A member is a row in the users table. This adds the member's team and phone.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // One of the App\Enums\Team values. Empty is allowed so the
            // normal sign-up page still works before a team is given.
            $table->string('team')->nullable()->after('email');
            $table->string('phone')->nullable()->after('team');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['team', 'phone']);
        });
    }
};
