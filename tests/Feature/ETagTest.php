<?php

namespace EduKSX\Core\Tests\Feature;

use EduKSX\Core\Http\ETag;
use EduKSX\Core\Tests\Fixtures\Note;
use EduKSX\Core\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

final class ETagTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::dropIfExists('notes');
        Schema::create('notes', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->unsignedInteger('lock_version')->default(0);
            $t->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('notes');
        parent::tearDown();
    }

    protected function defineRoutes($router): void
    {
        Route::get('api/v1/notes/{id}', function (int $id) {
            $note = Note::query()->findOrFail($id);

            return ETag::withModel(response()->json(['data' => $note->only('id', 'title', 'lock_version')], 200, ETag::privateHeaders()), $note);
        });
        Route::patch('api/v1/notes/{id}', function (Request $request, int $id) {
            $note = Note::query()->findOrFail($id);
            ETag::requireMatch($request, $note);
            $note->update(['title' => $request->input('title')]);

            return ETag::withModel(response()->json(['data' => $note->only('id', 'title', 'lock_version')]), $note);
        });
        Route::get('api/v1/public', fn (Request $request) => ETag::publicJson($request, ['data' => ['term' => 'Aula']], 60, ['EduKSX-Release' => '7']));
    }

    public function test_object_etag_is_strong_and_private(): void
    {
        $note = Note::query()->create(['title' => 'A']);

        $r = $this->getJson("/api/v1/notes/{$note->id}")->assertOk();
        $r->assertHeader('ETag', "\"{$note->id}.0\"");
        $this->assertStringContainsString('no-store', (string) $r->headers->get('Cache-Control'));
    }

    public function test_patch_requires_if_match(): void
    {
        $note = Note::query()->create(['title' => 'A']);

        $this->patchJson("/api/v1/notes/{$note->id}", ['title' => 'B'])
            ->assertStatus(428)
            ->assertJson(['code' => 'precondition_required'])
            ->assertHeader('ETag', "\"{$note->id}.0\"");
    }

    public function test_patch_with_matching_etag_and_stale_etag(): void
    {
        $note = Note::query()->create(['title' => 'A']);

        $this->patchJson("/api/v1/notes/{$note->id}", ['title' => 'B'], ['If-Match' => "\"{$note->id}.0\""])
            ->assertOk()
            ->assertHeader('ETag', "\"{$note->id}.1\"");

        $this->patchJson("/api/v1/notes/{$note->id}", ['title' => 'C'], ['If-Match' => "\"{$note->id}.0\""])
            ->assertStatus(412)
            ->assertJson(['code' => 'precondition_failed'])
            ->assertHeader('ETag', "\"{$note->id}.1\"");
        $this->assertSame('B', Note::query()->findOrFail($note->id)->title);
    }

    public function test_public_etag_answers_304(): void
    {
        $r = $this->getJson('/api/v1/public')->assertOk();
        $etag = (string) $r->headers->get('ETag');

        $this->assertMatchesRegularExpression('/^"[0-9a-f]{64}"$/', $etag);
        $r->assertHeader('Vary', 'Accept-Language')->assertHeader('EduKSX-Release', '7');
        $this->assertStringContainsString('public', (string) $r->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=60', (string) $r->headers->get('Cache-Control'));

        $this->get('/api/v1/public', ['If-None-Match' => $etag])->assertStatus(304)->assertHeader('ETag', $etag);
        $this->get('/api/v1/public', ['If-None-Match' => '"anders"'])->assertOk();
    }
}
