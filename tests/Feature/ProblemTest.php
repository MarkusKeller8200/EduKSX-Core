<?php

namespace EduKSX\Core\Tests\Feature;

use EduKSX\Core\Http\ProblemException;
use EduKSX\Core\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use RuntimeException;

final class ProblemTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        Route::get('api/v1/boom', fn () => throw new RuntimeException('geheim: SELECT * FROM users'));
        Route::get('api/v1/term-merged', fn () => throw new ProblemException(409, 'term_merged', 'Begriff wurde zusammengeführt.'));
        Route::post('api/v1/people', fn (Request $r) => $r->validate(['email' => 'required|email']));
        Route::get('api/v1/limited', fn () => abort(429, '', ['Retry-After' => '30']));
        Route::get('web/missing', fn () => abort(404));
    }

    public function test_unknown_endpoint_is_problem_json(): void
    {
        $r = $this->getJson('/api/v1/unknown');

        $r->assertStatus(404)->assertHeader('Content-Type', 'application/problem+json');
        $r->assertJson([
            'type' => 'https://docs.eduksx.ch/problems/not_found',
            'status' => 404,
            'code' => 'not_found',
            'instance' => '/api/v1/unknown',
        ]);
        $this->assertNotEmpty($r->json('request_id'));
        $this->assertSame($r->json('request_id'), $r->headers->get('X-Request-Id'));
    }

    public function test_server_error_hides_internal_details(): void
    {
        $r = $this->getJson('/api/v1/boom');

        $r->assertStatus(500)->assertJson(['code' => 'internal_error']);
        $this->assertArrayNotHasKey('detail', $r->json());
        $this->assertStringNotContainsString('SELECT', $r->getContent());
    }

    public function test_validation_lists_errors_per_field(): void
    {
        $r = $this->postJson('/api/v1/people', ['email' => 'kein-mail']);

        $r->assertStatus(422)->assertJson(['code' => 'validation_failed']);
        $this->assertArrayHasKey('email', $r->json('errors'));
    }

    public function test_module_problem_uses_module_key_in_type(): void
    {
        $this->getJson('/api/v1/term-merged')
            ->assertStatus(409)
            ->assertJson([
                'type' => 'https://docs.eduksx.ch/problems/demo/term_merged',
                'code' => 'term_merged',
                'detail' => 'Begriff wurde zusammengeführt.',
            ]);
    }

    public function test_rate_limit_keeps_retry_after(): void
    {
        $this->getJson('/api/v1/limited')
            ->assertStatus(429)
            ->assertHeader('Retry-After', '30')
            ->assertJson(['code' => 'too_many_requests']);
    }

    public function test_incoming_request_id_is_kept(): void
    {
        $this->getJson('/api/v1/unknown', ['X-Request-Id' => 'abc12345-test'])
            ->assertJson(['request_id' => 'abc12345-test'])
            ->assertHeader('X-Request-Id', 'abc12345-test');
    }

    public function test_unsafe_request_id_is_replaced(): void
    {
        $r = $this->getJson('/api/v1/unknown', ['X-Request-Id' => '<script>']);

        $this->assertNotSame('<script>', $r->json('request_id'));
        $this->assertMatchesRegularExpression('/^[0-9A-Z]{26}$/', (string) $r->json('request_id'));
    }

    public function test_paths_outside_api_are_untouched(): void
    {
        $r = $this->get('/web/missing');

        $r->assertStatus(404);
        $this->assertStringNotContainsString('problem+json', (string) $r->headers->get('Content-Type'));
    }

    public function test_invalid_problem_code_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ProblemException(400, 'Ungültig-Code');
    }
}
