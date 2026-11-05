<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('phone_normalized')->nullable()->index();
            $table->jsonb('roles')->default('[]');
            $table->string('status')->default('new_lead');
            $table->string('source')->nullable();
            $table->jsonb('tags')->default('[]');
            $table->text('notes')->nullable();
            // Staff users with CRM records cannot be deleted.
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('owner_id');
        });

        DB::statement('create index contacts_lower_email_index on contacts (lower(email))');
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
