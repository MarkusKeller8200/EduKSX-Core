<?php

use EduKSX\Core\Database\StrictSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * eduksx/core: Einstellungen und Feature-Schalter (EduKSX-Architektur KONZEPT-STACK ST5). Gespeichert werden nur
 * Abweichungen von der Vorgabe; Geheimnisse verschlüsselt. Veröffentlicht mit
 * php artisan vendor:publish --tag=eduksx-settings-migrations. Besteht die Tabelle schon (Glossary), bleibt sie
 * unverändert (Core C8).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('settings')) {
            return;
        }
        Schema::create('settings', function (Blueprint $t) {
            $t->string('key', 120)->primary();
            $t->text('value');
            $t->uuid('updated_by_id')->nullable()->index();
            $t->timestamp('updated_at')->nullable();
        });
        StrictSchema::harden('settings', []);
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
