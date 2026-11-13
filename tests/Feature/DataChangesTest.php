<?php

namespace EduKSX\Core\Tests\Feature;

use EduKSX\Core\Data\Actor;
use EduKSX\Core\Data\DataChanges;
use EduKSX\Core\Data\DataClass;
use EduKSX\Core\Data\DataClasses;
use EduKSX\Core\Database\StrictSchema;
use EduKSX\Core\Tests\Fixtures\Account;
use EduKSX\Core\Tests\Fixtures\Folder;
use EduKSX\Core\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/** Historie data_changes mit Datenklassen (Core #15) – gleiches Verhalten wie bisher im Glossary. */
final class DataChangesTest extends TestCase
{
    use RefreshDatabase;

    private const ORG = '0193a1b2-0000-7000-8000-00000000a001';

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations/history');
    }

    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('accounts', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('email');
            $t->timestamps();
        });
        StrictSchema::harden('accounts');
        Schema::create('folders', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('name');
            $t->string('note')->nullable();
            $t->uuid('organization_id')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
        StrictSchema::harden('folders');
        DataClasses::define([
            'accounts' => ['id' => DataClass::Internal, 'email' => DataClass::Personal, 'created_at' => 'internal', 'updated_at' => 'internal'],
            'folders' => ['id' => 'internal', 'name' => DataClass::Public, 'note' => DataClass::Sensitive, 'organization_id' => 'internal',
                'created_at' => 'internal', 'updated_at' => 'internal', 'deleted_at' => 'internal'],
        ]);
    }

    protected function tearDown(): void
    {
        DataClasses::reset();
        DataChanges::resolveActorUsing(null);
        Schema::dropIfExists('folders');
        Schema::dropIfExists('accounts');
        parent::tearDown();
    }

    /** @return list<array<string, mixed>> */
    private function rows(?string $subjectType = null): array
    {
        return DB::table('data_changes')->when($subjectType, fn ($q) => $q->where('subject_type', $subjectType))
            ->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
    }

    public function test_changes_with_actor_and_without_personal_values(): void
    {
        $account = Account::query()->create(['email' => 'lea@schule.example']);
        $this->actingAs($account);

        $folder = Folder::query()->create(['name' => 'Personal', 'note' => 'vertraulich', 'organization_id' => self::ORG]);
        $folder->update(['name' => 'Personal und Anstellung', 'note' => 'geheimer']);
        $account->update(['email' => 'neu@schule.example']);

        $rows = $this->rows('folders');
        $this->assertSame(['created', 'updated'], array_column($rows, 'action'));
        $this->assertSame(['name' => ['old' => 'Personal', 'new' => 'Personal und Anstellung'], 'note' => 'changed'], json_decode((string) $rows[1]['changes'], true));
        $this->assertSame('changed', json_decode((string) $rows[0]['changes'], true)['note']);
        $this->assertSame($account->id, $rows[1]['actor_id']);
        $this->assertSame('user', $rows[1]['actor_type']);
        $this->assertSame(self::ORG, $rows[1]['organization_id']);
        $this->assertSame('web', $rows[1]['source']);
        $this->assertSame('0.0.0', $rows[1]['schema_version']);

        $accountRow = $this->rows('accounts')[1];
        $this->assertSame(['email' => 'changed'], json_decode((string) $accountRow['changes'], true));
        $this->assertStringNotContainsString('neu@schule.example', (string) json_encode($this->rows()));
        $this->assertStringNotContainsString('vertraulich', (string) json_encode($this->rows()));
    }

    public function test_trash_and_restore(): void
    {
        $folder = Folder::query()->create(['name' => 'Alt']);
        $folder->delete();
        $folder->restore();
        $folder->forceDelete();

        $this->assertSame(['created', 'deleted', 'restored', 'deleted'], array_column($this->rows('folders'), 'action'));
        $this->assertSame('system', $this->rows('folders')[0]['actor_type']);
    }

    public function test_request_id_and_api_source_in_http_requests(): void
    {
        Route::post('/api/v1/folders', fn () => Folder::query()->create(['name' => 'Über API'])->id);

        $this->postJson('/api/v1/folders', [], ['X-Request-Id' => 'req-4711'])->assertOk();

        $row = $this->rows('folders')[0];
        $this->assertSame('req-4711', $row['request_id']);
        $this->assertSame('api', $row['source']);
    }

    public function test_one_transaction_id_per_transaction(): void
    {
        DB::transaction(function () {
            Folder::query()->create(['name' => 'A']);
            Folder::query()->create(['name' => 'B']);
        });
        Folder::query()->create(['name' => 'C']);

        $ids = array_column($this->rows('folders'), 'transaction_id');
        $this->assertSame($ids[0], $ids[1]);
        $this->assertNotSame($ids[1], $ids[2]);
    }

    public function test_source_and_actor_set_by_module(): void
    {
        DataChanges::resolveActorUsing(fn () => new Actor('admin', '0193a1b2-0000-7000-8000-00000000b001', 'Lea L.'));

        DataChanges::withSource('import', fn () => Folder::query()->create(['name' => 'Import']));

        $row = $this->rows('folders')[0];
        $this->assertSame('import', $row['source']);
        $this->assertSame('admin', $row['actor_type']);
        $this->assertSame('Lea L.', $row['actor_label']);
    }

    public function test_self_deletion_leaves_no_reference_and_forget_pseudonymises(): void
    {
        $account = Account::query()->create(['email' => 'lea@schule.example']);
        $this->actingAs($account);
        Folder::query()->create(['name' => 'Eigenes']);

        $account->delete();
        $this->assertSame('system', $this->rows('accounts')[1]['actor_type']);
        $this->assertNull($this->rows('accounts')[1]['actor_id']);

        DataChanges::forget($account->id);
        $folderRow = $this->rows('folders')[0];
        $this->assertNull($folderRow['actor_id']);
        $this->assertSame(DataChanges::DELETED_LABEL, $folderRow['actor_label']);
    }

    public function test_unknown_columns_are_treated_as_personal(): void
    {
        $this->assertSame(DataClass::Personal, DataClasses::of('folders', 'unbekannt'));
        $this->assertFalse(DataClasses::has('folders', 'unbekannt'));
        $this->assertTrue(DataClasses::has('data_changes', 'actor_label'));
    }

    public function test_existing_table_is_kept(): void
    {
        $migration = require __DIR__.'/../../database/migrations/history/2026_11_14_000200_create_data_changes_table.php';
        $migration->up();   // Tabelle besteht schon (wie im Glossary): nichts geschieht

        $this->assertTrue(Schema::hasColumn('data_changes', 'transaction_id'));
    }
}
