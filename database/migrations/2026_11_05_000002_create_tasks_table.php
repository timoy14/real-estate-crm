<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->timestamp('due_at')->nullable();
            $table->string('priority')->default('normal');
            $table->timestamp('completed_at')->nullable();
            // The assignee.
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('property_id')->nullable()->constrained('properties')->nullOnDelete();
            $table->foreignId('deal_id')->nullable()->constrained('deals')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['owner_id', 'completed_at', 'due_at']);
            $table->index('contact_id');
            $table->index('property_id');
            $table->index('deal_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
