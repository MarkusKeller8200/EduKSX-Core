<?php

namespace EduKSX\Core\Tests\Feature;

use EduKSX\Core\Tests\TestCase;
use Illuminate\Support\Facades\Route;

final class CorsTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        Route::get('api/v1/public', fn () => ['data' => 'ok']);
    }

    public function test_public_get_allows_any_origin_without_credentials(): void
    {
        $r = $this->getJson('/api/v1/public', ['Origin' => 'https://schule.example.ch'])->assertOk();

        $r->assertHeader('Access-Control-Allow-Origin', '*');
        $this->assertFalse($r->headers->has('Access-Control-Allow-Credentials'));
    }

    public function test_preflight_for_write_is_not_allowed_by_default(): void
    {
        $r = $this->call('OPTIONS', '/api/v1/public', [], [], [], [
            'HTTP_ORIGIN' => 'https://fremd.example.ch',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'DELETE',
        ]);

        $this->assertStringNotContainsString('DELETE', (string) $r->headers->get('Access-Control-Allow-Methods'));
    }
}
