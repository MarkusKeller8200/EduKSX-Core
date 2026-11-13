<?php

namespace EduKSX\Core\Tests\Unit;

use EduKSX\Core\Events\InvalidSignature;
use EduKSX\Core\Events\Signer;
use EduKSX\Core\Spec\Schemas;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Signatur S4 (SP6): dieselbe Klasse für Absender und Empfänger (Core #14). */
final class SignerTest extends TestCase
{
    private const KEY = 'test-schluessel-0123456789abcdef0123';

    private const NEW_KEY = 'neuer-schluessel-0123456789abcdef012';

    public function test_header_matches_schema_and_verifies(): void
    {
        $header = Signer::header('{"a":1}', self::KEY, 1763062800);

        $this->assertSame([], Schemas::errors('signature', $header));
        $this->assertSame('t=1763062800,v1='.hash_hmac('sha256', '1763062800.{"a":1}', self::KEY), $header);
        Signer::verify($header, '{"a":1}', [self::KEY], now: 1763062800 + 299);
        $this->addToAssertionCount(1);
    }

    public function test_key_rotation_on_both_sides(): void
    {
        $both = Signer::header('x', [self::KEY, self::NEW_KEY], 1000);
        $this->assertSame(2, substr_count($both, 'v1='));
        Signer::verify($both, 'x', [self::NEW_KEY], now: 1000);   // Empfänger schon umgestellt
        Signer::verify(Signer::header('x', self::KEY, 1000), 'x', [self::NEW_KEY, self::KEY], now: 1000);   // Empfänger mit beiden
        $this->addToAssertionCount(2);
    }

    /** @return iterable<string, array{?string, string, int}> */
    public static function rejected(): iterable
    {
        $good = Signer::header('body', self::KEY, 1000);
        yield 'fehlt' => [null, 'missing', 1000];
        yield 'leer' => ['', 'missing', 1000];
        yield 'kaputt' => ['v1=abc', 'malformed', 1000];
        yield 'ohne v1' => ['t=1000', 'malformed', 1000];
        yield 'zu alt' => [$good, 'expired', 1301];
        yield 'aus der Zukunft' => [$good, 'expired', 699];
        yield 'falscher Schlüssel' => [Signer::header('body', self::NEW_KEY, 1000), 'mismatch', 1000];
        yield 'veränderter Body' => [Signer::header('body2', self::KEY, 1000), 'mismatch', 1000];
    }

    #[DataProvider('rejected')]
    public function test_rejections(?string $header, string $reason, int $now): void
    {
        try {
            Signer::verify($header, 'body', [self::KEY], now: $now);
            $this->fail('nicht abgewiesen');
        } catch (InvalidSignature $e) {
            $this->assertSame($reason, $e->reason);
        }
    }

    public function test_short_keys_are_refused_when_signing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Signer::header('x', 'kurz');
    }
}
