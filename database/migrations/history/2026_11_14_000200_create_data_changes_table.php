<?php

use EduKSX\Core\Database\StrictSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * eduksx/core: Änderungshistorie data_changes (DB-Standard A7, B2), gleicher Aufbau wie bisher im Glossary.
 * Veröffentlicht mit php artisan vendor:publish --tag=eduksx-history-migrations. Besteht die Tabelle schon
 * (Glossary), bleibt sie unverändert (Core C8).
 */
return new class extends Migration
{
    private const ACTIONS = "'created', 'updated', 'deleted', 'restored'";

    private const SOURCES = "'web', 'api', 'import', 'mcp', 'job', 'system', 'migration'";

    public function up(): void
    {
        if (Schema::hasTable('data_changes')) {
            return;
        }
        Schema::create('data_changes', function (Blueprint $t) {
            $t->id();
            $t->uuid('event_id')->unique();
            $t->string('subject_type', 60);
            $t->string('subject_id', 64);
            $t->uuid('organization_id')->nullable()->index();
            $t->string('action', 20);
            $t->json('changes')->nullable();
            $t->string('actor_type', 20);
            $t->uuid('actor_id')->nullable()->index();
            $t->string('actor_label', 120)->nullable();
            $t->string('source', 20);
            $t->uuid('transaction_id')->index();
            $t->string('request_id', 100)->nullable();
            $t->string('schema_version', 20);
            $t->timestamp('occurred_at')->index();
            $t->index(['subject_type', 'subject_id']);
        });
        StrictSchema::harden('data_changes', [
            'data_changes_action_check' => '"action" in ('.self::ACTIONS.')',
            'data_changes_source_check' => '"source" in ('.self::SOURCES.')',
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('data_changes');
    }
};
