<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// One day a sponsor is on campus: passes, arrival and leaving times.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sponsor_id')->constrained()->cascadeOnDelete();
            $table->date('event_date');
            $table->string('location')->nullable();
            $table->boolean('entry_pass')->default(false);   // has the gate entry pass been issued?
            $table->boolean('exit_pass')->default(false);    // has the gate exit pass been issued?
            $table->dateTime('arrived_at')->nullable();
            $table->dateTime('left_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_days');
    }
};
