<?php

namespace EduKSX\Core\Tests\Feature;

use EduKSX\Core\Http\CursorPagination;
use EduKSX\Core\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

final class CursorPaginationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropIfExists('terms');
        Schema::create('terms', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('status');
        });
        // 7 Einträge, zwei mit gleichem Namen (stabile Reihenfolge über id)
        foreach (['Bibliothek', 'Aula', 'Mensa', 'Aula', 'Turnhalle', 'Zimmer', 'Labor'] as $i => $name) {
            DB::table('terms')->insert(['name' => $name, 'status' => $i % 2 === 0 ? 'published' : 'draft']);
        }
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('terms');
        parent::tearDown();
    }

    protected function defineRoutes($router): void
    {
        Route::get('api/v1/terms', function (Request $request) {
            $query = DB::table('terms');
            if ($request->filled('status')) {
                $query->whereIn('status', explode(',', (string) $request->query('status')));
            }

            return CursorPagination::paginate($query, $request, ['name'], 'name', fn ($row) => ['id' => $row->id, 'name' => $row->name]);
        });
    }

    public function test_walks_all_entries_without_gaps(): void
    {
        $seen = [];
        $url = '/api/v1/terms?limit=3';
        $pages = 0;
        while ($url !== null && $pages < 10) {
            $r = $this->getJson($url)->assertOk();
            $seen = array_merge($seen, array_column($r->json('data'), 'name'));
            $url = $r->json('links.next');
            $this->assertSame($url !== null, $r->json('meta.has_more'));
            $pages++;
        }

        $this->assertSame(3, $pages);
        $this->assertSame(['Aula', 'Aula', 'Bibliothek', 'Labor', 'Mensa', 'Turnhalle', 'Zimmer'], $seen);
    }

    public function test_descending_sort_and_filter(): void
    {
        $r = $this->getJson('/api/v1/terms?sort=-name&status=published&limit=2')->assertOk();

        $this->assertSame(['Turnhalle', 'Mensa'], array_column($r->json('data'), 'name'));
        $next = $this->getJson((string) $r->json('links.next'))->assertOk();
        $this->assertSame(['Labor', 'Bibliothek'], array_column($next->json('data'), 'name'));
    }

    public function test_cursor_with_other_filter_is_rejected(): void
    {
        $cursor = $this->getJson('/api/v1/terms?limit=2')->json('meta.next_cursor');

        $this->getJson('/api/v1/terms?limit=2&status=draft&cursor='.$cursor)
            ->assertStatus(422)
            ->assertJson(['code' => 'invalid_cursor', 'type' => 'https://docs.eduksx.ch/problems/invalid_cursor']);
    }

    public function test_invalid_parameters_are_rejected(): void
    {
        $this->getJson('/api/v1/terms?limit=0')->assertStatus(422)->assertJson(['code' => 'invalid_limit']);
        $this->getJson('/api/v1/terms?limit=201')->assertStatus(422)->assertJson(['code' => 'invalid_limit']);
        $this->getJson('/api/v1/terms?sort=status')->assertStatus(422)->assertJson(['code' => 'invalid_sort']);
        $this->getJson('/api/v1/terms?cursor=kaputt')->assertStatus(422)->assertJson(['code' => 'invalid_cursor']);
    }

    public function test_default_limit_and_last_page(): void
    {
        $r = $this->getJson('/api/v1/terms')->assertOk();

        $this->assertCount(7, $r->json('data'));
        $r->assertJson(['meta' => ['limit' => 50, 'next_cursor' => null, 'has_more' => false], 'links' => ['next' => null]]);
    }
}
