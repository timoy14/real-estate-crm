<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Postgres does not index foreign keys on its own. Existing indexes are skipped. */
return new class extends Migration
{
    private const INDEXES = [
        'deals' => [['contact_id'], ['property_id'], ['pipeline_id']],
        'wishlists' => [['contact_id']],
        'inquiries' => [['contact_id'], ['deal_id']],
        'properties' => [['listing_agent_id']],
        'tasks' => [['inquiry_id']],
        'activities' => [['type', 'viewing_status', 'scheduled_at']],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $sets) {
            foreach ($sets as $columns) {
                if (! Schema::hasIndex($table, $columns)) {
                    Schema::table($table, fn (Blueprint $t) => $t->index($columns, $this->name($table, $columns)));
                }
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $sets) {
            foreach ($sets as $columns) {
                if (Schema::hasIndex($table, $this->name($table, $columns))) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropIndex($this->name($table, $columns)));
                }
            }
        }
    }

    private function name(string $table, array $columns): string
    {
        return $table.'_'.implode('_', $columns).'_index';
    }
};
