<?php

namespace EduKSX\Core\Tests\Feature;

use EduKSX\Core\Data\Actor;
use EduKSX\Core\Security\SecurityLog;
use EduKSX\Core\Testing\AssertsConformance;
use EduKSX\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Sicherheitsprotokoll mit Hash-Kette (Core #36, DB-Standard A7, DB-E7). */
final class SecurityLogTest extends TestCase
{
    use AssertsConformance;
    use RefreshDatabase;

    private const ORG = '0193A1B2-0000-7000-8000-00000000C001';

    private const ACTOR = '0193a1b2-0000-7000-8000-00000000b001';

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations/security');
    }

    public function test_eintraege_sind_verkettet_und_pruefbar(): void
    {
        SecurityLog::record('roles.changed', self::ORG, ['user_id' => self::ACTOR, 'role' => 'editor'], 'Antrag 12', new Actor('admin', self::ACTOR));
        SecurityLog::record('debug.enabled', self::ORG, ['minutes' => 30], 'Fehlersuche', new Actor('console'));
        SecurityLog::record('logs.package_created', null, ['lines' => 3]);

        $rows = DB::table('security_events')->orderBy('id')->get();
        $this->assertCount(3, $rows);
        $this->assertNull($rows[0]->previous_hash);
        $this->assertSame($rows[0]->hash, $rows[1]->previous_hash);
        $this->assertSame($rows[1]->hash, $rows[2]->previous_hash);
        $this->assertSame(strtolower(self::ORG), $rows[0]->organization_id);

        $result = SecurityLog::verify();
        $this->assertTrue($result['valid']);
        $this->assertSame(3, $result['entries']);
        $this->assertSame($rows[2]->hash, $result['last_hash']);
    }

    public function test_geaenderter_oder_geloeschter_eintrag_bricht_die_kette(): void
    {
        foreach (['a.one', 'a.two', 'a.three'] as $action) {
            SecurityLog::record($action, self::ORG, [], 'Test');
        }
        $second = DB::table('security_events')->orderBy('id')->skip(1)->first();

        DB::table('security_events')->where('id', $second->id)->update(['reason' => 'verändert']);
        $result = SecurityLog::verify();
        $this->assertFalse($result['valid']);
        $this->assertSame($second->event_id, $result['broken_at']);

        DB::table('security_events')->where('id', $second->id)->delete();
        $this->assertFalse(SecurityLog::verify()['valid'], 'Lücke in der Kette');
    }

    public function test_pseudonymisieren_haelt_die_kette_gueltig(): void
    {
        SecurityLog::record('login.succeeded', self::ORG, [], null, new Actor('user', self::ACTOR));
        SecurityLog::record('login.failed', self::ORG, [], null, new Actor('user', self::ACTOR));

        $this->assertSame(2, SecurityLog::forget(self::ACTOR));
        $this->assertSame(0, DB::table('security_events')->whereNotNull('actor_id')->count());
        $this->assertTrue(SecurityLog::verify()['valid']);

        DB::table('security_events')->where('action', 'login.failed')->update(['actor_id' => '0193a1b2-0000-7000-8000-00000000ffff']);
        $this->assertFalse(SecurityLog::verify()['valid'], 'andere Person untergeschoben');
    }

    public function test_angaben_werden_maskiert(): void
    {
        SecurityLog::record('export.created', self::ORG, ['email' => 'anna.muster@example.org', 'note' => 'an anna.muster@example.org'], 'Mail von anna.muster@example.org');

        $row = DB::table('security_events')->first();
        $this->assertStringNotContainsString('anna.muster@example.org', (string) $row->details.$row->reason);
        $this->assertTrue(SecurityLog::verify()['valid']);
    }

    public function test_aktion_folgt_dem_muster(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SecurityLog::record('Debug ein');
    }

    public function test_frist_und_pruefpunkt(): void
    {
        $this->travel(-400)->days();
        SecurityLog::record('old.entry');
        $this->travelBack();
        SecurityLog::record('new.entry');

        $this->assertSame(1, SecurityLog::prune());
        $this->artisan('eduksx:sicherheitsprotokoll')->expectsOutputToContain('1 Einträge, Kette gültig.')->assertSuccessful();
    }

    public function test_tabelle_entspricht_dem_db_standard(): void
    {
        $this->assertDatabaseConforms();
        $this->assertDataClassesComplete();
    }
}
