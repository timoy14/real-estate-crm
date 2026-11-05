<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->string('listing_type');
            $table->jsonb('property_types')->default('[]');
            $table->unsignedBigInteger('min_budget')->nullable();
            $table->unsignedBigInteger('max_budget')->nullable();
            $table->unsignedSmallInteger('min_bedrooms')->nullable();
            $table->unsignedSmallInteger('min_bathrooms')->nullable();
            $table->jsonb('areas')->default('[]');
            $table->jsonb('must_have_features')->default('[]');
            $table->date('move_in_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wishlists');
    }
};
