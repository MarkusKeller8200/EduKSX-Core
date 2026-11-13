<?php

namespace EduKSX\Core\Tests\Feature;

use EduKSX\Core\Data\DataClasses;
use EduKSX\Core\Database\StrictSchema;
use EduKSX\Core\Testing\AssertsConformance;
use EduKSX\Core\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\AssertionFailedError;

/** Beispiel-Modul «demo»: so binden Module den Konformitätstest ein – und was er findet. */
final class ConformanceTest extends TestCase
{
    use AssertsConformance;

    private const SPEC = __DIR__.'/../Fixtures/openapi/demo-v1.json';

    protected function setUp(): void
    {
        parent::setUp();
        $this->dropDemoTables();
        Schema::create('categories', fn (Blueprint $t) => $t->id());
        StrictSchema::harden('categories');
        Schema::create('terms', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $t->index('category_id');
        });
        StrictSchema::harden('terms');
    }

    protected function tearDown(): void
    {
        $this->dropDemoTables();
        parent::tearDown();
    }

    private function dropDemoTables(): void
    {
        foreach (['Begriffe', 'notes', 'terms', 'categories'] as $table) {
            Schema::dropIfExists($table);
        }
    }

    protected function defineRoutes($router): void
    {
        Route::get('api/v1/terms', fn () => ['data' => []]);
        Route::get('api/v1/terms/{term}', fn () => ['data' => null]);
    }

    public function test_demo_module_conforms(): void
    {
        $this->assertDatabaseConforms();
        $this->assertApiBasicsConform();
        $this->assertOpenApiConforms(self::SPEC);
    }

    public function test_paths_relative_to_server_prefix(): void
    {
        $this->assertOpenApiConforms(__DIR__.'/../Fixtures/openapi/demo-v1-server-prefix.json');
    }

    public function test_finds_foreign_key_without_index(): void
    {
        Schema::create('notes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('term_id')->constrained();
        });
        StrictSchema::harden('notes');

        $this->assertFailsWith('Fremdschlüssel (term_id) ohne Index', fn () => $this->assertDatabaseConforms());
    }

    public function test_composite_foreign_key_covered_by_primary_key(): void
    {
        Schema::create('notes', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('term_id');
            $t->unique(['id', 'term_id']);
        });
        Schema::table('terms', fn (Blueprint $t) => $t->unique(['id', 'category_id']));
        Schema::dropIfExists('notes');
        Schema::create('notes', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('category_id')->nullable();
            $t->foreign(['id', 'category_id'])->references(['id', 'category_id'])->on('terms');
        });
        StrictSchema::harden('notes');

        $this->assertDatabaseConforms();
    }

    public function test_finds_non_strict_or_badly_named_table(): void
    {
        Schema::create('Begriffe', fn (Blueprint $t) => $t->id());

        $this->assertFailsWith('nicht in snake_case', fn () => $this->assertDatabaseConforms());
    }

    public function test_finds_route_missing_in_openapi(): void
    {
        Route::post('api/v1/terms', fn () => ['data' => null]);
        Route::getRoutes()->refreshNameLookups();

        $this->assertFailsWith('POST /api/v1/terms', fn () => $this->assertOpenApiConforms(self::SPEC));
    }

    public function test_wrong_module_key_in_openapi_fails(): void
    {
        config(['eduksx.module' => 'glossary']);

        $this->assertFailsWith('x-eduksx-module', fn () => $this->assertOpenApiConforms(self::SPEC));
    }

    public function test_core_components_are_valid_json_with_problem_schema(): void
    {
        $components = json_decode((string) file_get_contents(__DIR__.'/../../resources/openapi/components.json'), true, flags: JSON_THROW_ON_ERROR);
        $spec = json_decode((string) file_get_contents(self::SPEC), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame($components['schemas']['Problem'], $spec['components']['schemas']['Problem'], 'Beispiel nutzt nicht die aktuellen Core-Schemas.');
    }

    private function assertFailsWith(string $needle, callable $check): void
    {
        try {
            $check();
        } catch (AssertionFailedError $e) {
            $this->assertStringContainsString($needle, $e->getMessage());

            return;
        }
        $this->fail("Konformitätstest hätte «{$needle}» melden müssen.");
    }

    public function test_platform_endpoints_conform(): void
    {
        $this->assertPlatformEndpointsConform();
    }

    public function test_finds_invalid_manifest(): void
    {
        config(['eduksx.manifest.events_consumed' => ['user-deleted']]);

        $this->assertFailsWith('module-manifest', fn () => $this->assertPlatformEndpointsConform());
    }

    public function test_data_classes_for_every_column(): void
    {
        DataClasses::define([
            'categories' => ['id' => 'internal'],
            'terms' => ['id' => 'internal', 'name' => 'public'],
        ]);
        try {
            $this->assertFailsWith('terms.category_id', fn () => $this->assertDataClassesComplete());
            DataClasses::define(['terms' => ['category_id' => 'internal']]);
            $this->assertDataClassesComplete();
        } finally {
            DataClasses::reset();
        }
    }
}
