<?php

use EduKSX\Core\Database\StrictSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * eduksx/core: Sicherheitsprotokoll security_events (DB-Standard A7, DB-E7, KONZEPT-PROTOKOLLIERUNG §3).
 * Unveränderlich und hash-verkettet: `previous_hash` ist eindeutig, so bleibt die Kette linear. Frist 1 Jahr (DB-E15).
 * `actor_ref` ist ein Pseudonym der handelnden Person und geht in den Hash ein; `actor_id` darf bei einer
 * Kontolöschung entfernt werden, ohne die Kette zu brechen (A7: keine Updates ausser Pseudonymisierung).
 * Veröffentlicht mit php artisan vendor:publish --tag=eduksx-security-migrations.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('security_events')) {
            return;
        }
        Schema::create('security_events', function (Blueprint $t) {
            $t->id();
            $t->uuid('event_id')->unique();
            $t->timestamp('occurred_at')->index();
            $t->uuid('organization_id')->nullable()->index();
            $t->string('action', 60)->index();
            $t->string('actor_type', 20);
            $t->uuid('actor_id')->nullable()->index();
            $t->char('actor_ref', 64)->nullable();
            $t->string('reason', 500)->nullable();
            $t->json('details')->nullable();
            $t->string('request_id', 100)->nullable();
            $t->char('previous_hash', 64)->nullable()->unique();
            $t->char('hash', 64)->unique();
        });
        StrictSchema::harden('security_events', [
            'security_events_hash_check' => 'length("hash") = 64',
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('security_events');
    }
};
