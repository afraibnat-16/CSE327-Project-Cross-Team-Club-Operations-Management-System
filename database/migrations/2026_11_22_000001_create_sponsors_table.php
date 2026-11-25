<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// A sponsor is an organization that supports a CAP Force event.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sponsors', function (Blueprint $table) {
            $table->id();
            $table->string('organization_name');
            $table->string('type');                       // e.g. "Company", "Bank", "Food stall"
            $table->string('contact_name');
            $table->string('contact_phone');
            $table->string('contact_email')->nullable();
            $table->decimal('agreed_amount', 10, 2)->nullable();  // money they agreed to give
            $table->text('items')->nullable();            // things they bring or give away
            $table->text('setup_needs')->nullable();      // tables, chairs, space, etc.
            $table->boolean('requires_electricity')->default(false);
            $table->boolean('setup_arranged')->default(false);
            $table->string('stage')->default('negotiation');  // one of the App\Enums\Stage values
            $table->enum('deal_status', ['negotiating', 'finalized', 'cancelled'])->default('negotiating');
            // The member who is handling this sponsor. Kept empty if that member is deleted.
            $table->foreignId('claimed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsors');
    }
};
