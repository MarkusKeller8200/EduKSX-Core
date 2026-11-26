<?php

namespace EduKSX\Core\Tests\Feature;

use EduKSX\Core\Access\Abilities;
use EduKSX\Core\Access\AbilityMatrix;
use EduKSX\Core\Platform\Manifest;
use EduKSX\Core\Testing\AssertsConformance;
use EduKSX\Core\Tests\TestCase;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/** Rechte und Rollen (Core #39, KONZEPT-ROLLEN RO1, RO2, §8). */
final class AbilitiesTest extends TestCase
{
    use AssertsConformance;

    private string $matrix;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('eduksx.access', [
            'abilities' => [
                'terms.read' => ['scope_action' => 'read', 'label' => 'Begriffe lesen'],
                'terms.write' => ['scope_action' => 'write', 'label' => 'Begriffe bearbeiten'],
                'terms.publish' => ['scope_action' => 'write', 'label' => 'Veröffentlichen', 'class' => 'privileged'],
                'proposals.moderate' => ['scope_action' => 'write', 'label' => 'Vorschläge prüfen'],
                'users.admin' => ['scope_action' => 'admin', 'label' => 'Benutzer verwalten'],
                'system.admin' => ['scope_action' => 'admin', 'label' => 'System', 'class' => 'system'],
            ],
            'roles' => [
                'reader' => ['terms.read'],
                'editor' => ['reader', 'terms.write'],
                'admin' => ['editor', 'moderator', 'terms.publish', 'users.admin'],
                'moderator' => ['reader', 'proposals.moderate'],
            ],
        ]);
        $this->matrix = sys_get_temp_dir().'/eduksx-rollen-'.bin2hex(random_bytes(4)).'.md';
    }

    protected function tearDown(): void
    {
        @unlink($this->matrix);
        parent::tearDown();
    }

    public function test_rollen_werden_aufgeloest_und_vereinigt(): void
    {
        $roles = Abilities::roles();
        $this->assertSame(['terms.read', 'terms.write'], $roles['editor']);
        $this->assertSame(['proposals.moderate', 'terms.publish', 'terms.read', 'terms.write', 'users.admin'], $roles['admin']);
        $this->assertSame(['proposals.moderate', 'terms.read', 'terms.write'], Abilities::forRoles(['editor', 'moderator', 'unbekannt']));
        $this->assertSame('demo:terms.write', Abilities::scopeFor('terms.publish'));
        $this->assertSame([], Abilities::problems());
    }

    public function test_gates_pruefen_das_recht_und_den_scope_des_tokens(): void
    {
        $user = new User;
        $scopes = null;
        Abilities::registerGates(fn () => ['editor'], function () use (&$scopes) {
            return $scopes;
        });

        $this->assertTrue(Gate::forUser($user)->allows('terms.write'));
        $this->assertFalse(Gate::forUser($user)->allows('terms.publish'));
        $scopes = ['demo:terms.read'];
        $this->assertFalse(Gate::forUser($user)->allows('terms.write'), 'Scope begrenzt (API C2).');
        $this->assertTrue(Gate::forUser($user)->allows('terms.read'));
    }

    public function test_regeln_aus_ro1_bis_ro3(): void
    {
        config(['eduksx.access.roles' => [
            'reader' => [],
            'editor' => ['terms.write'],
            'admin' => ['editor', 'system.admin', 'gibt.es_nicht'],
        ]]);
        $abilities = (array) config('eduksx.access.abilities');
        $abilities['people.read_sensitive'] = ['scope_action' => 'read', 'sensitive' => true];
        config(['eduksx.access.abilities' => $abilities]);
        config(['eduksx.access.roles.editor' => ['terms.write', 'people.read_sensitive']]);

        $problems = implode("\n", Abilities::problems());
        $this->assertStringContainsString('«reader» fehlt «terms.read»', $problems);
        $this->assertStringContainsString('«system.admin» ist «system»', $problems);
        $this->assertStringContainsString('unbekanntes Recht «gibt.es_nicht»', $problems);
        $this->assertStringContainsString('people.read_sensitive', $problems);

        config(['eduksx.access.roles' => ['editor' => ['admin'], 'admin' => ['editor']]]);
        $this->expectException(InvalidArgumentException::class);
        Abilities::roles();
    }

    public function test_steckbrief_nennt_rollen_und_rechte(): void
    {
        $manifest = Manifest::build();
        $this->assertSame(['reader', 'editor', 'admin', 'moderator'], $manifest['roles']);
        $this->assertContains(['name' => 'terms.publish', 'scope_action' => 'write'], $manifest['abilities']);
        $this->assertSame([], Manifest::errors());
    }

    public function test_matrix_wird_erzeugt_und_geprueft(): void
    {
        $relative = $this->matrix;
        $this->artisan('eduksx:rollen-rechte', ['--path' => $relative, '--check' => true])->assertFailed();
        $this->artisan('eduksx:rollen-rechte', ['--path' => $relative])->assertSuccessful();
        $this->artisan('eduksx:rollen-rechte', ['--path' => $relative, '--check' => true])
            ->expectsOutputToContain('aktuell')->assertSuccessful();

        $markdown = AbilityMatrix::markdown();
        $this->assertStringContainsString('| `terms.publish` | Veröffentlichen | `demo:terms.write` | privileged |', $markdown);
        $this->assertStringContainsString('| `reader` | `editor` | `admin` | `moderator` | Super-Admin |', $markdown);
        $this->assertRolesConform($this->matrix);
    }
}
