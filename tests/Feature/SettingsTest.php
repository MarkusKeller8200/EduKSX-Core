<?php

namespace EduKSX\Core\Tests\Feature;

use EduKSX\Core\Settings\SettingDefinition;
use EduKSX\Core\Settings\Settings;
use EduKSX\Core\Settings\SettingSource;
use EduKSX\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Einstellungen und Feature-Schalter (Core #31, KONZEPT-STACK ST5) – Verhalten wie im Glossary (#106, #366). */
final class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private const ACTOR = '0193a1b2-0000-7000-8000-00000000b001';

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations/history');
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations/settings');
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        $app['config']->set('app.cipher', 'AES-256-CBC');
        $app['config']->set('eduksx.settings.definitions', [
            'mcp.enabled' => ['type' => 'bool', 'label' => 'MCP-Server', 'default' => false, 'env' => 'DEMO_MCP'],
            'retention.months' => ['type' => 'int', 'label' => 'Löschfrist', 'default' => 24, 'min' => 6, 'max' => 60, 'unit' => 'Monate'],
            'updates.policy' => ['type' => 'choice', 'label' => 'Updates', 'default' => 'korrektur', 'options' => ['aus' => 'aus', 'korrektur' => 'nur Korrekturen']],
            'imprint.email' => ['type' => 'email', 'label' => 'Kontakt', 'default' => ''],
            'links.source' => ['type' => 'url', 'label' => 'Quellcode', 'default' => fn () => 'https://example.org/code'],
            'metrics.token' => ['type' => 'secret', 'label' => 'Token für /metrics'],
            'links.github_repo' => ['type' => 'text', 'label' => 'GitHub-Repository', 'default' => '',
                'pattern' => '#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', 'pattern_hint' => 'Form «Besitzer/Repository».'],
        ]);
    }

    protected function tearDown(): void
    {
        unset($_ENV['DEMO_MCP'], $_SERVER['DEMO_MCP']);
        putenv('DEMO_MCP');
        parent::tearDown();
    }

    private function settings(): Settings
    {
        return app(Settings::class);
    }

    public function test_vorgaben_gelten_ohne_gespeicherte_werte(): void
    {
        $this->assertFalse($this->settings()->enabled('mcp.enabled'));
        $this->assertSame(24, $this->settings()->int('retention.months'));
        $this->assertSame('https://example.org/code', $this->settings()->string('links.source'));
        $this->assertSame('', $this->settings()->string('metrics.token'));
        $this->assertSame(SettingSource::Default, $this->settings()->source('retention.months'));
    }

    public function test_nur_abweichungen_werden_gespeichert_und_protokolliert(): void
    {
        $changed = $this->settings()->set(['retention.months' => '36', 'mcp.enabled' => '1'], self::ACTOR);

        $this->assertSame(['retention.months', 'mcp.enabled'], $changed);
        $this->assertSame(36, $this->settings()->int('retention.months'));
        $this->assertTrue($this->settings()->enabled('mcp.enabled'));
        $this->assertSame(SettingSource::Administration, $this->settings()->source('retention.months'));
        $this->assertSame(self::ACTOR, DB::table('settings')->where('key', 'retention.months')->value('updated_by_id'));
        $this->assertSame(2, DB::table('data_changes')->where('subject_type', 'settings')->count());

        // zurück auf die Vorgabe: Eintrag entfernt, Änderung trotzdem in der Historie
        $this->settings()->set(['retention.months' => 24]);
        $this->assertFalse(DB::table('settings')->where('key', 'retention.months')->exists());
        $this->assertSame(SettingSource::Default, $this->settings()->source('retention.months'));
        $this->assertSame(3, DB::table('data_changes')->where('subject_type', 'settings')->count());

        // unveränderter Wert: keine Änderung
        $this->assertSame([], $this->settings()->set(['mcp.enabled' => true]));
    }

    public function test_ungueltige_werte_speichern_nichts(): void
    {
        try {
            $this->settings()->set(['mcp.enabled' => true, 'retention.months' => 99]);
            $this->fail('Ausnahme erwartet');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Löschfrist', $e->getMessage());
        }
        $this->assertSame(0, DB::table('settings')->count());

        $this->expectException(InvalidArgumentException::class);
        $this->settings()->set(['updates.policy' => 'immer']);
    }

    public function test_url_und_email_werden_geprueft(): void
    {
        $this->settings()->set(['imprint.email' => 'info@example.org', 'links.source' => 'https://example.org/fork']);
        $this->assertSame('info@example.org', $this->settings()->string('imprint.email'));

        $this->expectException(InvalidArgumentException::class);
        $this->settings()->set(['links.source' => 'http://unsicher.example.org']);
    }

    public function test_umgebung_hat_vorrang_und_sperrt(): void
    {
        $this->settings()->set(['mcp.enabled' => true]);
        $_ENV['DEMO_MCP'] = 'false';
        $this->settings()->flush();

        $this->assertFalse($this->settings()->enabled('mcp.enabled'));
        $this->assertSame('DEMO_MCP', $this->settings()->locked('mcp.enabled'));
        $this->assertSame(SettingSource::Environment, $this->settings()->source('mcp.enabled'));
        // gesperrt: set() überspringt den Wert
        $this->assertSame([], $this->settings()->set(['mcp.enabled' => true]));
        app()->setLocale('de');
        $this->assertSame('Umgebung', SettingSource::Environment->label());
    }

    public function test_geheimnisse_verschluesselt_und_ohne_wert_in_der_historie(): void
    {
        $this->settings()->setSecret('metrics.token', 'geheimer-wert', self::ACTOR);

        $raw = (string) DB::table('settings')->where('key', 'metrics.token')->value('value');
        $this->assertStringNotContainsString('geheimer-wert', $raw);
        $this->assertSame('geheimer-wert', $this->settings()->string('metrics.token'));
        $history = (string) DB::table('data_changes')->where('subject_id', 'metrics.token')->value('changes');
        $this->assertStringNotContainsString('geheimer-wert', $history);
        $this->assertStringContainsString('gesetzt', $history);

        // set() lässt Geheimnisse aus
        $this->assertSame([], $this->settings()->set(['metrics.token' => 'anders']));

        $this->settings()->setSecret('metrics.token', null);
        $this->assertSame('', $this->settings()->string('metrics.token'));
    }

    public function test_definition_im_code_und_schalter_nur_fuer_bool(): void
    {
        $this->settings()->define('beta.feature', new SettingDefinition('beta.feature', 'bool', 'Neue Ansicht', true));
        $this->assertTrue($this->settings()->enabled('beta.feature'));
        $this->assertArrayHasKey('beta.feature', $this->settings()->definitions());

        $this->expectException(InvalidArgumentException::class);
        $this->settings()->enabled('retention.months');
    }

    public function test_ungueltige_definitionen_werden_abgelehnt(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new SettingDefinition('Falsch-Key', 'bool', 'x');
    }

    public function test_unbekannte_einstellung(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->settings()->get('gibt.es.nicht');
    }

    public function test_text_mit_muster(): void
    {
        $this->assertSame(['links.github_repo'], $this->settings()->set(['links.github_repo' => ' MarkusKeller8200/EduKSX-Glossary ']));
        $this->assertSame('MarkusKeller8200/EduKSX-Glossary', $this->settings()->string('links.github_repo'));
        $this->settings()->set(['links.github_repo' => '']);   // leer blendet aus, erlaubt

        try {
            $this->settings()->set(['links.github_repo' => 'https://github.com/x']);
            $this->fail('Muster nicht geprüft');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('«GitHub-Repository»: Form «Besitzer/Repository».', $e->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        SettingDefinition::fromArray('x.y', ['type' => 'int', 'pattern' => '#^\\d+$#']);
    }
}
