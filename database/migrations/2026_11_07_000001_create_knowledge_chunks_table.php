<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

        Schema::create('knowledge_chunks', function (Blueprint $table) {
            $table->id();
            $table->string('source_type');
            $table->unsignedBigInteger('source_id');
            $table->unsignedInteger('chunk_index');
            $table->text('content');
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->unsignedBigInteger('property_id')->nullable();
            $table->unsignedBigInteger('deal_id')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();

            $table->unique(['source_type', 'source_id', 'chunk_index']);
            $table->index('owner_id');
            $table->index('contact_id');
            $table->index('property_id');
            $table->index('deal_id');
        });

        DB::statement('ALTER TABLE knowledge_chunks ADD COLUMN embedding vector(1024) NOT NULL');
        DB::statement("ALTER TABLE knowledge_chunks ADD COLUMN content_tsv tsvector GENERATED ALWAYS AS (to_tsvector('english', content)) STORED");
        DB::statement('CREATE INDEX knowledge_chunks_embedding_hnsw ON knowledge_chunks USING hnsw (embedding vector_cosine_ops)');
        DB::statement('CREATE INDEX knowledge_chunks_content_tsv_gin ON knowledge_chunks USING gin (content_tsv)');
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_chunks');
    }
};
