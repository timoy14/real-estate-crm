<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->nullable()->unique();
            $table->string('title');
            $table->string('address');
            $table->string('area')->index();
            $table->string('city');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('listing_type')->index();
            $table->string('property_type')->index();
            $table->unsignedBigInteger('price');
            $table->unsignedTinyInteger('bedrooms')->nullable();
            $table->unsignedTinyInteger('bathrooms')->nullable();
            $table->unsignedInteger('floor_area_sqft')->nullable();
            $table->unsignedInteger('lot_area_sqft')->nullable();
            $table->unsignedSmallInteger('year_built')->nullable();
            $table->jsonb('features')->default('[]');
            $table->text('description')->nullable();
            $table->string('status')->default('draft')->index();
            $table->boolean('is_published')->default(false);
            $table->boolean('hide_exact_address')->default(false);
            $table->foreignId('owner_contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('listing_agent_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
