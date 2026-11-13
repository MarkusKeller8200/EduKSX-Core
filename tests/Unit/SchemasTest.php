<?php

namespace EduKSX\Core\Tests\Unit;

use EduKSX\Core\Spec\InvalidSchemaData;
use EduKSX\Core\Spec\Schemas;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SchemasTest extends TestCase
{
    /** @return iterable<string, array{string, string, bool}> */
    public static function examples(): iterable
    {
        foreach (Schemas::NAMES as $name) {
            foreach (['valid' => true, 'invalid' => false] as $dir => $expected) {
                foreach (glob(Schemas::directory()."/v1/examples/{$name}/{$dir}/*.json") ?: [] as $file) {
                    yield "{$name}/{$dir}/".basename($file) => [$name, $file, $expected];
                }
            }
        }
    }

    /** Die Beispiele aus dem Architektur-Repo ergeben in PHP dasselbe Urteil wie dort in Python. */
    #[DataProvider('examples')]
    public function test_examples_from_architecture_repo(string $name, string $file, bool $expected): void
    {
        $data = json_decode((string) file_get_contents($file), flags: JSON_THROW_ON_ERROR);   // Objekte bleiben Objekte
        $errors = Schemas::errors($name, $data);

        $this->assertSame($expected, $errors === [], basename($file).': '.implode('; ', $errors));
    }

    public function test_copy_matches_source_checksums(): void
    {
        $source = json_decode((string) file_get_contents(Schemas::directory().'/SOURCE.json'), true);
        $this->assertNotEmpty($source['files']);
        foreach ($source['files'] as $path => $hash) {
            $this->assertSame($hash, hash_file('sha256', Schemas::directory().'/'.$path), $path);
        }
        $this->assertMatchesRegularExpression('/^[0-9a-f]{40}$/', Schemas::source()['commit']);
    }

    public function test_assert_valid_throws_with_errors(): void
    {
        try {
            Schemas::assertValid('health', ['status' => 'ok']);
            $this->fail('keine Ausnahme');
        } catch (InvalidSchemaData $e) {
            $this->assertSame('health', $e->schema);
            $this->assertNotEmpty($e->errors);
        }
    }

    public function test_unknown_schema_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Schemas::errors('unbekannt', []);
    }
}
