<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A notice is a short message left for a whole team, e.g. "Kite Coffee moved
// from Negotiation to Approval." The Observer pattern creates these.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notices', function (Blueprint $table) {
            $table->id();
            $table->string('team');   // which team should read it (an App\Enums\Team value)
            // The sponsor it is about. Kept (with an empty sponsor) if the sponsor is deleted.
            $table->foreignId('sponsor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('message');
            $table->timestamp('read_at')->nullable();   // empty until someone marks it as read
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notices');
    }
};
