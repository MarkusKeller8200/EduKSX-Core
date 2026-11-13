<?php

namespace EduKSX\Core\Tests\Feature;

use EduKSX\Core\Eloquent\StaleModel;
use EduKSX\Core\Tests\Fixtures\Note;
use EduKSX\Core\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

final class OptimisticLockingTest extends TestCase
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
        Route::patch('api/v1/notes/{id}', function (Request $request, int $id) {
            $note = Note::query()->findOrFail($id);
            $note->assertLockVersion($request->input('lock_version'));
            $note->update(['title' => $request->input('title')]);

            return ['data' => $note->only('id', 'title', 'lock_version')];
        });
    }

    public function test_each_change_increments_version(): void
    {
        $note = Note::query()->create(['title' => 'A']);
        $note->update(['title' => 'B']);
        $note->update(['title' => 'C']);

        $this->assertSame(2, (int) $note->fresh()?->lock_version);
    }

    public function test_concurrent_change_is_not_overwritten(): void
    {
        $note = Note::query()->create(['title' => 'A']);
        $first = Note::query()->findOrFail($note->id);
        $second = Note::query()->findOrFail($note->id);

        $first->update(['title' => 'von Person 1']);

        try {
            $second->update(['title' => 'von Person 2']);
            $this->fail('StaleModel erwartet');
        } catch (StaleModel) {
            $this->assertSame('von Person 1', Note::query()->findOrFail($note->id)->title);
        }
    }

    public function test_api_answers_conflict_as_problem(): void
    {
        $note = Note::query()->create(['title' => 'A']);
        $this->patchJson("/api/v1/notes/{$note->id}", ['title' => 'B', 'lock_version' => 0])->assertOk()
            ->assertJsonPath('data.lock_version', 1);

        $this->patchJson("/api/v1/notes/{$note->id}", ['title' => 'C', 'lock_version' => 0])
            ->assertStatus(409)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJson(['code' => 'conflict']);
    }
}
