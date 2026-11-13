<?php

namespace EduKSX\Core\Tests\Feature;

use EduKSX\Core\Tests\TestCase;
use Illuminate\Support\Facades\Route;

final class SecurityHeadersTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        Route::get('ok', fn () => 'ok');
    }

    public function test_headers_are_set(): void
    {
        $r = $this->get('/ok');

        $r->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString("frame-ancestors 'self'", (string) $r->headers->get('Content-Security-Policy'));
        $this->assertFalse($r->headers->has('Strict-Transport-Security'));
        $this->assertTrue($r->headers->has('X-Request-Id'));
    }

    public function test_hsts_only_over_https(): void
    {
        $this->get('https://localhost/ok')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }
}
