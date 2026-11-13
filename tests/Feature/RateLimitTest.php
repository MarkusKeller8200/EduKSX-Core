<?php

namespace EduKSX\Core\Tests\Feature;

use EduKSX\Core\Tests\TestCase;
use Illuminate\Support\Facades\Route;

final class RateLimitTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('eduksx.rate_limit.api', 2);
    }

    protected function defineRoutes($router): void
    {
        Route::middleware('throttle:api')->get('api/v1/ping', fn () => ['data' => 'pong']);
    }

    public function test_limit_returns_problem_with_retry_after(): void
    {
        $this->getJson('/api/v1/ping')->assertOk();
        $this->getJson('/api/v1/ping')->assertOk();
        $r = $this->getJson('/api/v1/ping');

        $r->assertStatus(429)->assertJson(['code' => 'too_many_requests']);
        $this->assertTrue($r->headers->has('Retry-After'));
    }

    public function test_limit_is_per_token(): void
    {
        $this->getJson('/api/v1/ping')->assertOk();
        $this->getJson('/api/v1/ping')->assertOk();
        $this->getJson('/api/v1/ping', ['Authorization' => 'Bearer demo_sa_test'])->assertOk();
    }
}
