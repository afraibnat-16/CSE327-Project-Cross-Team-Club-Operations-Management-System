<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// People a sponsor sends to campus on event day.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('representatives', function (Blueprint $table) {
            $table->id();
            // If the sponsor is deleted, its representatives are deleted too.
            $table->foreignId('sponsor_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('representatives');
    }
};
