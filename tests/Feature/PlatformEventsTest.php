<?php

namespace EduKSX\Core\Tests\Feature;

use EduKSX\Core\Events\CloudEvent;
use EduKSX\Core\Events\PlatformEventReceived;
use EduKSX\Core\Events\ReceivedEvent;
use EduKSX\Core\Spec\InvalidSchemaData;
use EduKSX\Core\Testing\SendsPlatformEvents;
use EduKSX\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

/** Empfang von S4-Ereignissen (Core #14). */
final class PlatformEventsTest extends TestCase
{
    use RefreshDatabase, SendsPlatformEvents;

    private const KEY = 'empfang-schluessel-0123456789abcdef01';

    private const USER = '0193a1b2-0000-7000-8000-00000000b001';

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('eduksx.events.keys', [self::KEY]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations/events');
    }

    public function test_valid_event_is_dispatched_once(): void
    {
        $received = [];
        Event::listen('eduksx:ident.user.deleted', function (PlatformEventReceived $e) use (&$received) {
            $received[] = $e;
        });
        $id = (string) Str::uuid7();

        $this->postPlatformEvent('ident.user.deleted', ['user_id' => self::USER, 'subject' => '312845729384756201'], id: $id)->assertNoContent();
        $this->postPlatformEvent('ident.user.deleted', ['user_id' => self::USER, 'subject' => '312845729384756201'], id: $id)->assertNoContent();

        $this->assertCount(1, $received, 'Wiederholung wird nicht erneut verarbeitet');
        $this->assertSame(self::USER, $received[0]->data['user_id']);
        $this->assertSame('ident.user.deleted', $received[0]->type);
        $this->assertSame(1, ReceivedEvent::query()->count());
    }

    public function test_generic_listener_receives_all_types(): void
    {
        Event::fake([PlatformEventReceived::class]);

        $this->postPlatformEvent('ident.membership.added', ['organization_id' => self::USER, 'user_id' => self::USER, 'role' => 'member'])->assertNoContent();

        Event::assertDispatched(PlatformEventReceived::class, fn ($e) => $e->type === 'ident.membership.added');
    }

    public function test_wrong_missing_and_old_signatures_are_rejected(): void
    {
        $data = ['user_id' => self::USER, 'subject' => '1'];
        $this->postPlatformEvent('ident.user.deleted', $data, key: 'falscher-schluessel-0123456789abcdef0')
            ->assertStatus(401)->assertJsonPath('code', 'invalid_signature')->assertHeader('Content-Type', 'application/problem+json');
        $this->postRawPlatformEvent('{}', key: '')->assertStatus(401);
        $this->postPlatformEvent('ident.user.deleted', $data, time: time() - 301)->assertStatus(401);

        $this->assertSame(0, ReceivedEvent::query()->count());
    }

    public function test_invalid_event_is_422_problem(): void
    {
        // Personendaten im Ereignis: Schema verbietet zusätzliche Felder (SP7)
        $this->postPlatformEvent('ident.user.deleted', ['user_id' => self::USER, 'subject' => '1', 'email' => 'lea@schule.example'])
            ->assertStatus(422)->assertJsonPath('code', 'invalid_event')->assertJsonStructure(['errors']);
        $this->postRawPlatformEvent('kein json')->assertStatus(422);

        $this->assertSame(0, ReceivedEvent::query()->count());
    }

    public function test_unknown_source_is_rejected_when_sources_are_listed(): void
    {
        config(['eduksx.events.sources' => ['https://ident.eduksx.ch']]);

        $this->postPlatformEvent('ident.user.deleted', ['user_id' => self::USER, 'subject' => '1'])->assertStatus(403);
    }

    public function test_failing_listener_leaves_event_unprocessed_for_retry(): void
    {
        Event::listen('eduksx:ident.user.deleted', fn () => throw new \RuntimeException('Datenbank weg'));
        $id = (string) Str::uuid7();

        $this->postPlatformEvent('ident.user.deleted', ['user_id' => self::USER, 'subject' => '1'], id: $id)->assertStatus(500);

        $this->assertSame(0, ReceivedEvent::query()->count());
    }

    public function test_too_large_body_is_413(): void
    {
        config(['eduksx.events.max_bytes' => 100]);

        $this->postRawPlatformEvent(str_repeat('x', 101))->assertStatus(413);
    }

    public function test_cloud_event_encode_validates(): void
    {
        $event = CloudEvent::make('https://ident.eduksx.ch', 'ident.role.changed', [
            'user_id' => self::USER, 'organization_id' => self::USER, 'module' => 'glossary', 'roles' => ['reader'],
        ]);
        $this->assertJson(CloudEvent::encode($event));

        $this->expectException(InvalidSchemaData::class);
        CloudEvent::encode(CloudEvent::make('https://ident.eduksx.ch', 'ident.user.deleted', ['name' => 'Lea']));
    }

    public function test_old_entries_are_pruned(): void
    {
        ReceivedEvent::query()->insert([
            ['event_id' => (string) Str::uuid7(), 'type' => 'ident.user.deleted', 'source' => 'x', 'received_at' => now()->subDays(31)],
            ['event_id' => (string) Str::uuid7(), 'type' => 'ident.user.deleted', 'source' => 'x', 'received_at' => now()->subDays(1)],
        ]);

        $this->artisan('model:prune', ['--model' => [ReceivedEvent::class]])->assertSuccessful();

        $this->assertSame(1, ReceivedEvent::query()->count());
    }
}
