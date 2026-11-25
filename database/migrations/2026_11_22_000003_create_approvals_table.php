<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// University offices that must approve a sponsor before it can come to campus.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sponsor_id')->constrained()->cascadeOnDelete();
            $table->enum('office', ['student_affairs', 'ops_management', 'security_gate_pass']);
            $table->enum('status', ['pending', 'submitted', 'approved'])->default('pending');
            $table->date('submitted_on')->nullable();   // the day the request was handed in
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approvals');
    }
};
