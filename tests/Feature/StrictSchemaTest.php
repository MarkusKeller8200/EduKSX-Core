<?php

namespace EduKSX\Core\Tests\Feature;

use EduKSX\Core\Database\StrictSchema;
use EduKSX\Core\Tests\TestCase;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class StrictSchemaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropIfExists('items');
        Schema::dropIfExists('tags');
        Schema::create('items', function (Blueprint $t) {
            $t->id();
            $t->string('status', 20);
            $t->integer('position');
            $t->boolean('active')->default(true);
            $t->index('status');
        });
        StrictSchema::harden('items', [
            'items_status_check' => "\"status\" in ('draft', 'published')",
            'items_active_check' => '"active" in (0, 1)',
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('items');
        Schema::dropIfExists('tags');
        parent::tearDown();
    }

    public function test_check_rule_is_enforced(): void
    {
        DB::table('items')->insert(['status' => 'draft', 'position' => 1, 'active' => true]);

        $this->expectException(QueryException::class);
        DB::table('items')->insert(['status' => 'deleted', 'position' => 2, 'active' => true]);
    }

    public function test_sqlite_table_is_strict_and_keeps_indexes(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('nur SQLite');
        }
        $sql = (string) DB::scalar("select sql from sqlite_master where type = 'table' and name = 'items'");
        $this->assertStringEndsWith(') strict', strtolower($sql));
        $this->assertTrue(Schema::hasIndex('items', ['status']));

        $this->expectException(QueryException::class);
        DB::table('items')->insert(['status' => 'draft', 'position' => 'kein Text in Zahl', 'active' => true]);
    }

    public function test_harden_refuses_filled_table_on_sqlite(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('nur SQLite');
        }
        DB::table('items')->insert(['status' => 'draft', 'position' => 1, 'active' => true]);

        $this->expectException(RuntimeException::class);
        StrictSchema::harden('items');
    }

    public function test_add_columns_with_check_and_reference(): void
    {
        Schema::create('tags', fn (Blueprint $t) => $t->id());
        StrictSchema::harden('tags');
        StrictSchema::addColumns(
            'items',
            fn (Blueprint $t) => $t->string('kind', 10)->nullable(),
            ['kind' => ['items_kind_check', "\"kind\" in ('a', 'b')"]],
        );
        StrictSchema::addColumns(
            'items',
            fn (Blueprint $t) => $t->unsignedBigInteger('tag_id')->nullable(),
            references: ['tag_id' => ['tags', 'set null']],
        );

        DB::table('items')->insert(['status' => 'draft', 'position' => 1, 'active' => true, 'kind' => 'a']);
        $this->expectException(QueryException::class);
        DB::table('items')->insert(['status' => 'draft', 'position' => 2, 'active' => true, 'kind' => 'x']);
    }

    public function test_add_columns_refuses_rebuild_on_sqlite(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('nur SQLite');
        }
        $this->expectException(RuntimeException::class);
        StrictSchema::addColumns('items', fn (Blueprint $t) => $t->dropColumn('position'));
    }
}
