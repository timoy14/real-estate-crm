<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deals', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('pipeline_id')->constrained()->restrictOnDelete();
            $table->foreignId('stage_id')->constrained('stages')->restrictOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->foreignId('contact_id')->constrained('contacts')->restrictOnDelete();
            $table->foreignId('property_id')->nullable()->constrained('properties')->nullOnDelete();
            $table->unsignedBigInteger('value')->nullable();
            $table->decimal('commission_percent', 5, 2)->default(3);
            $table->date('expected_close_date')->nullable();
            $table->string('status')->default('open');
            $table->text('lost_reason')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['stage_id', 'position']);
            $table->index('owner_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deals');
    }
};
