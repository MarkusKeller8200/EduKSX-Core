<?php

namespace EduKSX\Core\Tests\Unit;

use EduKSX\Core\Module;
use EduKSX\Core\Tests\TestCase;
use InvalidArgumentException;

final class ModuleTest extends TestCase
{
    public function test_prefixes_use_module_key(): void
    {
        $this->assertSame('demo', Module::key());
        $this->assertSame('demo_sa_', Module::tokenPrefix('sa'));
        $this->assertSame('demo.term.published', Module::event('term', 'published'));
    }

    public function test_invalid_keys_are_rejected(): void
    {
        foreach (['Glossary', 'time-table', 'a', 'stunden_plan', ''] as $key) {
            try {
                Module::assertValid($key);
                $this->fail("«{$key}» hätte abgelehnt werden müssen.");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
