<?php

namespace EduKSX\Core\Tests\Feature;

use EduKSX\Core\Auth\LoginThrottle;
use EduKSX\Core\Auth\PasswordRules;
use EduKSX\Core\Auth\RecoveryCodes;
use EduKSX\Core\Http\Middleware\SessionTimeout;
use EduKSX\Core\Settings\Settings;
use EduKSX\Core\Tests\TestCase;
use Illuminate\Foundation\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;

/** Anmeldung Stufe 1 (Core #41, KONZEPT-ANMELDUNG §3, AN5). */
final class LocalLoginTest extends TestCase
{
    use RefreshDatabase;

    private string $blocklist;

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations/history');
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations/settings');
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations/security');
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $this->blocklist = sys_get_temp_dir().'/eduksx-blocklist-'.bin2hex(random_bytes(4)).'.txt';
        file_put_contents($this->blocklist, "# bekannte Passwörter\npasswortpasswort\nSommer2026Sommer2026\n");
        $app['config']->set('eduksx.auth.local', true);
        $app['config']->set('eduksx.auth.password_blocklist', $this->blocklist);
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('session.driver', 'array');
    }

    protected function tearDown(): void
    {
        @unlink($this->blocklist);
        PasswordRules::flush();
        parent::tearDown();
    }

    private function passes(string $password, bool $secondFactor = false): bool
    {
        return Validator::make(['password' => $password], ['password' => [PasswordRules::rule($secondFactor)]])->passes();
    }

    public function test_passwortregeln_nach_nist(): void
    {
        $this->assertFalse($this->passes('vierzehnzeiche'), '14 Zeichen ohne zweiten Faktor');
        $this->assertTrue($this->passes('fuenfzehnzeiche'), '15 Zeichen, keine Zeichenklassen nötig');
        $this->assertTrue($this->passes('zwoelfzeiche', true), '12 Zeichen mit zweitem Faktor');
        $this->assertFalse($this->passes('sommer2026sommer2026'), 'auf der Liste bekannter Passwörter');
        $this->assertFalse($this->passes(str_repeat('a', 129)));
        $this->assertTrue($this->passes(str_repeat('ä', 15)), 'Unicode zählt je Zeichen');
    }

    public function test_wiederherstellungscodes_sind_einmalig(): void
    {
        $codes = RecoveryCodes::generate();
        $this->assertCount(8, $codes['plain']);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{5}-[A-Z0-9]{5}$/', $codes['plain'][0]);
        $this->assertNotContains($codes['plain'][0], $codes['hashes']);

        $left = RecoveryCodes::use(strtolower(str_replace('-', '', $codes['plain'][3])), $codes['hashes']);
        $this->assertNotNull($left);
        $this->assertCount(7, $left);
        $this->assertNull(RecoveryCodes::use($codes['plain'][3], $left), 'verbraucht');
        $this->assertNull(RecoveryCodes::use('kaputt', $left));
    }

    public function test_versuche_werden_je_konto_und_adresse_begrenzt(): void
    {
        for ($i = 0; $i < LoginThrottle::PER_ACCOUNT; $i++) {
            $this->assertSame(0, LoginThrottle::blockedFor('Anna@Example.org', '192.0.2.1'));
            LoginThrottle::hit('anna@example.org', '192.0.2.1');
        }
        $this->assertGreaterThan(0, LoginThrottle::blockedFor('anna@example.org ', '192.0.2.1'));
        $this->assertSame(0, LoginThrottle::blockedFor('anna@example.org', '192.0.2.99'), 'andere Adresse');
        LoginThrottle::clear('anna@example.org', '192.0.2.1');
        $this->assertSame(0, LoginThrottle::blockedFor('anna@example.org', '192.0.2.1'));
    }

    public function test_einstellungen_aus_an5_sind_hoechstwerte(): void
    {
        $settings = app(Settings::class);
        $this->assertSame(30, $settings->int('auth.session_idle_admin_minutes'));
        $this->assertSame(480, $settings->int('auth.session_idle_minutes'));
        $this->assertSame(720, $settings->int('auth.session_absolute_minutes'));
        $this->assertSame(30, $settings->int('auth.federation_grace_days'));
        $this->assertFalse($settings->bool('auth.password_breach_check'));

        $this->expectException(\InvalidArgumentException::class);
        $settings->set(['auth.session_idle_admin_minutes' => 60]);
    }

    public function test_sitzung_laeuft_nach_leerlauf_in_der_administration_ab(): void
    {
        Route::middleware(['web', SessionTimeout::class])->get('/admin', fn () => 'admin');
        Route::middleware(['web', SessionTimeout::class])->get('/start', fn () => 'start');
        $user = new User;
        $user->forceFill(['id' => '0193a1b2-0000-7000-8000-00000000b001']);

        $this->actingAs($user)->get('/admin')->assertOk();
        $this->travel(29)->minutes();
        $this->actingAs($user)->get('/admin')->assertOk();
        $this->travel(31)->minutes();
        $this->actingAs($user)->get('/start')->assertOk();   // 8 Std. ausserhalb der Administration
        $this->travel(31)->minutes();
        $this->actingAs($user)->get('/admin')->assertRedirect('/anmelden');

        $this->assertSame('session.expired', DB::table('security_events')->value('action'));
    }

    public function test_sitzung_endet_spaetestens_nach_12_stunden(): void
    {
        Route::middleware(['web', SessionTimeout::class])->get('/start', fn () => 'start');
        $user = new User;
        $user->forceFill(['id' => 7]);

        $this->actingAs($user)->get('/start')->assertOk();
        for ($hour = 0; $hour < 11; $hour++) {
            $this->travel(1)->hours();
            $this->actingAs($user)->get('/start')->assertOk();
        }
        $this->travel(61)->minutes();
        $this->actingAs($user)->getJson('/start')->assertStatus(401);
    }
}
