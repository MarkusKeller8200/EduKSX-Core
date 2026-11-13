<?php

namespace EduKSX\Core\Tests\Feature;

use EduKSX\Core\Module;
use EduKSX\Core\Platform\Health;
use EduKSX\Core\Platform\HealthResult;
use EduKSX\Core\Platform\Manifest;
use EduKSX\Core\Spec\Schemas;
use EduKSX\Core\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/** Plattform S5: Steckbrief und /health (Core #13). */
final class ManifestHealthTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('eduksx.version', '1.4.0');
        $app['config']->set('eduksx.manifest', array_merge($app['config']->get('eduksx.manifest'), [
            'name' => 'Demo',
            'scopes_offered' => ['demo:notes.read'],
            'roles' => ['reader', 'editor'],
            'events_consumed' => ['ident.user.deleted'],
            'classification' => 'internal',
            'urls' => ['openapi' => '/openapi/demo-v1.json'],
        ]));
    }

    protected function tearDown(): void
    {
        Health::forget();
        parent::tearDown();
    }

    public function test_manifest_matches_schema(): void
    {
        $response = $this->get('/.well-known/eduksx-module.json');

        $response->assertOk()->assertHeader('Cache-Control', 'max-age=300, public');
        $this->assertSame([], Schemas::errors('module-manifest', json_decode((string) $response->getContent())));
        $response->assertJson([
            'manifest_version' => '1',
            'module' => 'demo',
            'name' => 'Demo',
            'version' => '1.4.0',
            'standards' => ['db' => '1.2.1', 'api' => '1.1.1'],
            'roles' => ['reader', 'editor'],
            'events_consumed' => ['ident.user.deleted'],
            'urls' => ['up' => '/up', 'health' => '/health', 'openapi' => '/openapi/demo-v1.json'],
        ]);
        $this->assertSame([], Manifest::errors());
    }

    public function test_manifest_is_cacheable_with_etag(): void
    {
        $etag = $this->get('/.well-known/eduksx-module.json')->headers->get('ETag');

        $this->get('/.well-known/eduksx-module.json', ['If-None-Match' => $etag])->assertStatus(304);
    }

    public function test_invalid_configuration_is_reported(): void
    {
        config(['eduksx.manifest.roles' => ['Editor-in-Chief']]);

        $this->assertNotSame([], Manifest::errors());
    }

    public function test_core_standards_match_composer_json(): void
    {
        $composer = json_decode((string) file_get_contents(__DIR__.'/../../composer.json'), true);

        $this->assertSame($composer['extra']['eduksx']['standards'], Module::STANDARDS);
    }

    public function test_health_ok(): void
    {
        $response = $this->get('/health');

        $response->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame([], Schemas::errors('health', json_decode((string) $response->getContent())));
        $response->assertJson(['status' => 'ok', 'module' => 'demo', 'version' => '1.4.0', 'checks' => [
            'database' => ['status' => 'ok'], 'migrations' => ['status' => 'ok'], 'queue' => ['status' => 'ok'], 'storage' => ['status' => 'ok'],
        ]]);
    }

    public function test_health_fails_with_503_without_database(): void
    {
        config(['database.connections.broken' => ['driver' => 'sqlite', 'database' => '/nonexistent/dir/db.sqlite', 'prefix' => '']]);
        $default = DB::getDefaultConnection();
        DB::setDefaultConnection('broken');
        try {
            $response = $this->get('/health');
        } finally {
            DB::setDefaultConnection($default);
        }

        $response->assertStatus(503)->assertJsonPath('status', 'fail')->assertJsonPath('checks.database.status', 'fail');
        $this->assertStringNotContainsString('nonexistent', (string) $response->getContent(), 'keine Pfade oder Hostnamen');
        $this->assertSame([], Schemas::errors('health', json_decode((string) $response->getContent())));
    }

    public function test_non_critical_failure_is_degraded(): void
    {
        Health::register('ident', fn () => HealthResult::fail('Ident nicht erreichbar.'));

        $this->get('/health')->assertOk()->assertJsonPath('status', 'degraded')
            ->assertJsonPath('checks.ident.message', 'Ident nicht erreichbar.');
    }

    public function test_only_schema_check_names_are_allowed(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Health::register('redis', fn () => HealthResult::ok());
    }
}
