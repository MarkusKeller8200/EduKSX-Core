<?php

namespace EduKSX\Core\Tests\Feature;

use EduKSX\Core\Logging\MaskPersonalData;
use EduKSX\Core\Logging\TraceContext;
use EduKSX\Core\Logging\UseEduksxFormat;
use EduKSX\Core\Testing\AssertsConformance;
use EduKSX\Core\Tests\TestCase;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/** Protokollierung: Maskierung, JSON-Format, traceparent (Core #33, KONZEPT-PROTOKOLLIERUNG §6, §7). */
final class LoggingTest extends TestCase
{
    use AssertsConformance;

    private const TRACE = '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01';

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('logging.channels.stderr.tap', [UseEduksxFormat::class]);
        $app['config']->set('logging.default', 'stderr');
    }

    public function test_muster_in_texten_werden_maskiert(): void
    {
        $text = MaskPersonalData::maskText(
            'Mail an anna.muster@example.org, Tel. +41 79 123 45 67 bzw. 044 123 45 67, AHV 756.1234.5678.97, '
            .'IBAN CH93 0076 2011 6238 5295 7, Authorization: Bearer abcdefghijk.lmnop, Anfrage 4711'
        );

        foreach (['anna.muster@example.org', '+41 79 123 45 67', '044 123 45 67', '756.1234.5678.97', 'CH93 0076', 'abcdefghijk'] as $value) {
            $this->assertStringNotContainsString($value, $text);
        }
        $this->assertStringContainsString('Anfrage 4711', $text);
    }

    public function test_felder_werden_nach_namen_maskiert_ids_bleiben(): void
    {
        $masked = (new MaskPersonalData(['schulklasse']))->maskArray([
            'user_id' => '0193a1b2-0000-7000-8000-00000000a001',
            'email' => 'x@example.org',
            'Display-Name' => 'Anna',
            'nested' => ['password' => 'geheim', 'count' => 3],
            'schulklasse' => '2b',
            'ip' => '192.0.2.10',
        ]);

        $this->assertSame('0193a1b2-0000-7000-8000-00000000a001', $masked['user_id']);
        $this->assertSame(MaskPersonalData::MASK, $masked['email']);
        $this->assertSame(MaskPersonalData::MASK, $masked['Display-Name']);
        $this->assertSame(MaskPersonalData::MASK, $masked['nested']['password']);
        $this->assertSame(3, $masked['nested']['count']);
        $this->assertSame(MaskPersonalData::MASK, $masked['schulklasse']);
        $this->assertSame('192.0.2.10', $masked['ip'], 'Quell-Adresse bleibt im Zugriffsprotokoll (API C7).');
    }

    public function test_kanal_mit_tap_besteht_den_konformitaetstest(): void
    {
        $this->assertLoggingMasksPersonalData();
    }

    public function test_json_format_mit_festen_feldern(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'eduksx-test-');
        config(['logging.channels.json' => ['driver' => 'single', 'path' => $file, 'tap' => [UseEduksxFormat::class]]]);
        Context::add('request_id', 'req-12345678');
        TraceContext::start(self::TRACE);

        Log::channel('json')->warning('Auftrag fehlgeschlagen', ['job' => 'sync']);

        $line = json_decode(trim((string) file_get_contents($file)), true);
        @unlink($file);
        $this->assertIsArray($line);
        $this->assertSame('Auftrag fehlgeschlagen', $line['message']);
        $this->assertSame('WARNING', $line['level_name']);
        $this->assertSame('demo', $line['extra']['module']);
        $this->assertSame('req-12345678', $line['extra']['request_id']);
        $this->assertSame('4bf92f3577b34da6a3ce929d0e0e4736', $line['extra']['trace_id']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/', $line['extra']['time']);
        $this->assertSame(['job' => 'sync'], $line['context']);
    }

    public function test_traceparent_wird_uebernommen_oder_neu_begonnen(): void
    {
        $own = TraceContext::start(self::TRACE);
        $this->assertStringStartsWith('00-4bf92f3577b34da6a3ce929d0e0e4736-', $own);
        $this->assertNotSame(self::TRACE, $own, 'eigene span_id');
        $this->assertSame('4bf92f3577b34da6a3ce929d0e0e4736', TraceContext::traceId());

        $child = TraceContext::headers();
        $this->assertStringStartsWith('00-4bf92f3577b34da6a3ce929d0e0e4736-', $child['traceparent']);

        foreach (['kaputt', '00-'.str_repeat('0', 32).'-00f067aa0ba902b7-01', null] as $invalid) {
            TraceContext::start($invalid);
            $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', (string) TraceContext::traceId());
            $this->assertNotSame('4bf92f3577b34da6a3ce929d0e0e4736', TraceContext::traceId());
        }
    }

    public function test_middleware_setzt_request_id_und_trace_im_context(): void
    {
        Route::get('/api/v1/trace', fn () => response()->json([
            'request_id' => Context::get('request_id'),
            'trace_id' => TraceContext::traceId(),
        ]));

        $this->get('/api/v1/trace', ['X-Request-Id' => 'abc12345-trace', 'traceparent' => self::TRACE])
            ->assertOk()
            ->assertHeader('X-Request-Id', 'abc12345-trace')
            ->assertJson(['request_id' => 'abc12345-trace', 'trace_id' => '4bf92f3577b34da6a3ce929d0e0e4736']);
    }
}
