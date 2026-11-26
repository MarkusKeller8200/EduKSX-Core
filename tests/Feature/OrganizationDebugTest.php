<?php

namespace EduKSX\Core\Tests\Feature;

use EduKSX\Core\Data\Actor;
use EduKSX\Core\Logging\LogPackage;
use EduKSX\Core\Logging\OrganizationDebug;
use EduKSX\Core\Logging\UseEduksxFormat;
use EduKSX\Core\Security\SecurityLog;
use EduKSX\Core\Settings\Settings;
use EduKSX\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/** Befristetes Debug je Organisation und Protokollpaket (Core #35, KONZEPT-PROTOKOLLIERUNG §5, §9). */
final class OrganizationDebugTest extends TestCase
{
    use RefreshDatabase;

    private const ORG = '0193a1b2-0000-7000-8000-00000000c001';

    private const OTHER = '0193a1b2-0000-7000-8000-00000000c002';

    private const ADMIN = '0193a1b2-0000-7000-8000-00000000b001';

    private string $logFile;

    private string $packageDir;

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations/history');
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations/settings');
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations/security');
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $this->logFile = sys_get_temp_dir().'/eduksx-debug-'.bin2hex(random_bytes(4)).'.log';
        $this->packageDir = sys_get_temp_dir().'/eduksx-pakete-'.bin2hex(random_bytes(4));
        $app['config']->set('logging.channels.app', ['driver' => 'single', 'path' => $this->logFile, 'level' => 'info', 'tap' => [UseEduksxFormat::class]]);
        $app['config']->set('logging.default', 'app');
        $app['config']->set('eduksx.logging.package.sources', [$this->logFile]);
        $app['config']->set('eduksx.logging.package.directory', $this->packageDir);
    }

    protected function tearDown(): void
    {
        @unlink($this->logFile);
        foreach (glob($this->packageDir.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->packageDir);
        parent::tearDown();
    }

    private function admin(): Actor
    {
        return new Actor('admin', self::ADMIN);
    }

    private function logged(): string
    {
        return is_file($this->logFile) ? (string) file_get_contents($this->logFile) : '';
    }

    public function test_debug_nur_fuer_die_organisation_und_hoechstens_60_minuten(): void
    {
        Context::add('organization_id', self::ORG);
        Log::debug('vorher-debug');
        $this->assertStringNotContainsString('vorher-debug', $this->logged(), 'Stufe info gilt ohne Debug.');

        OrganizationDebug::enable(self::ORG, 60, 'Fehlersuche Login', $this->admin());
        Log::debug('waehrend-debug', ['email' => 'anna.muster@example.org']);
        Context::add('organization_id', self::OTHER);
        Log::debug('andere-organisation');

        $logged = $this->logged();
        $this->assertStringContainsString('waehrend-debug', $logged);
        $this->assertStringNotContainsString('anna.muster@example.org', $logged, 'Maskierung bleibt im Debug.');
        $this->assertStringNotContainsString('andere-organisation', $logged);

        $this->expectException(InvalidArgumentException::class);
        OrganizationDebug::enable(self::ORG, 61, 'zu lang', $this->admin());
    }

    public function test_debug_laeuft_nach_60_minuten_ab_und_steht_im_sicherheitsprotokoll(): void
    {
        OrganizationDebug::enable(self::ORG, 60, 'Fehlersuche', $this->admin());
        $this->assertTrue(OrganizationDebug::active(self::ORG));

        $this->travel(61)->minutes();
        app(Settings::class)->flush();
        $this->assertFalse(OrganizationDebug::active(self::ORG), 'Abgelaufen gilt sofort als aus.');
        Context::add('organization_id', self::ORG);
        Log::debug('nach-ablauf');
        $this->assertStringNotContainsString('nach-ablauf', $this->logged());

        $this->artisan('eduksx:debug', ['--expire' => true])->expectsOutputToContain('1 Sitzung(en) beendet.')->assertSuccessful();
        $this->assertSame([], OrganizationDebug::sessions());

        $actions = DB::table('security_events')->orderBy('id')->pluck('action')->all();
        $this->assertSame(['debug.enabled', 'debug.expired'], $actions);
        $first = DB::table('security_events')->first();
        $this->assertSame(self::ORG, $first->organization_id);
        $this->assertSame('Fehlersuche', $first->reason);
        $this->assertTrue(SecurityLog::verify()['valid']);
    }

    public function test_grund_ist_pflicht_und_ausschalten_wird_protokolliert(): void
    {
        $this->artisan('eduksx:debug', ['organization' => self::ORG])->assertExitCode(2);
        $this->artisan('eduksx:debug', ['organization' => self::ORG, '--minutes' => 15, '--reason' => 'Ticket 42'])->assertSuccessful();
        $this->artisan('eduksx:debug', ['organization' => self::ORG, '--off' => true, '--reason' => 'erledigt'])
            ->expectsOutputToContain('Debug ausgeschaltet.')->assertSuccessful();

        $this->assertSame(['debug.enabled', 'debug.disabled'], DB::table('security_events')->orderBy('id')->pluck('action')->all());
        $this->assertSame('console', DB::table('security_events')->value('actor_type'));
    }

    public function test_protokollpaket_ist_verschluesselt_und_enthaelt_keine_personendaten(): void
    {
        $keys = LogPackage::generateKeypair();
        config(['eduksx.logging.package.public_key' => $keys['public_key']]);
        Log::warning('Anmeldung fehlgeschlagen fuer anna.muster@example.org', ['name' => 'Anna Muster', 'user_id' => self::ADMIN]);
        file_put_contents($this->logFile, '['.now()->format('Y-m-d H:i:s').'] testing.ERROR: Mail an peter@example.org, Tel. 079 123 45 67'."\n", FILE_APPEND);
        file_put_contents($this->logFile, '#0 /app/Stacktrace.php(12): IBAN CH93 0076 2011 6238 5295 7'."\n", FILE_APPEND);
        file_put_contents($this->logFile, '[2020-01-01 00:00:00] testing.INFO: ausserhalb-des-zeitraums'."\n", FILE_APPEND);

        $result = LogPackage::create(now()->subHour()->toImmutable(), now()->addMinute()->toImmutable(), $this->admin(), 'Fall 7');

        $sealed = (string) file_get_contents($result['path']);
        $this->assertSame(0600, fileperms($result['path']) & 0777);
        $this->assertStringNotContainsString('Anmeldung', $sealed, 'verschlüsselt');
        $package = LogPackage::open($sealed, $keys['keypair']);
        $text = (string) json_encode($package, JSON_UNESCAPED_UNICODE);

        foreach (['anna.muster@example.org', 'Anna Muster', 'peter@example.org', '079 123 45 67', 'CH93 0076'] as $personal) {
            $this->assertStringNotContainsString($personal, $text);
        }
        $this->assertStringContainsString('Anmeldung fehlgeschlagen', $text);
        $this->assertStringContainsString('Stacktrace.php', $text, 'Folgezeile gehört zur Zeile davor.');
        $this->assertStringNotContainsString('ausserhalb-des-zeitraums', $text);
        $this->assertSame('demo', $package['module']);
        $this->assertArrayHasKey('health', $package);
        $this->assertArrayHasKey('manifest', $package);

        $event = DB::table('security_events')->where('action', 'logs.package_created')->first();
        $this->assertNotNull($event);
        $this->assertSame($result['sha256'], json_decode((string) $event->details, true)['sha256']);
    }

    public function test_protokollpaket_ohne_schluessel_bricht_ab_und_alte_pakete_werden_geloescht(): void
    {
        $this->artisan('eduksx:protokollpaket')->expectsOutputToContain('EDUKSX_SUPPORT_PUBLIC_KEY')->assertFailed();

        mkdir($this->packageDir, 0700, true);
        $old = $this->packageDir.'/alt'.LogPackage::EXTENSION;
        touch($old, now()->subDays(31)->getTimestamp());
        $this->artisan('eduksx:protokollpaket', ['--prune' => true])->expectsOutputToContain('1 Paket(e) gelöscht.')->assertSuccessful();
        $this->assertFileDoesNotExist($old);
    }
}
