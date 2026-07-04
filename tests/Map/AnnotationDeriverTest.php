<?php

declare(strict_types=1);

namespace BEAR\Mcp\Map;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AnnotationDeriverTest extends TestCase
{
    /** @return array<string, array{0: string, 1: bool, 2: bool, 3: bool}> */
    public static function verbProvider(): array
    {
        // verb => [readOnly, destructive, idempotent]
        return [
            'get' => ['get', true, false, true],
            'post' => ['post', false, false, false],
            'put' => ['put', false, true, true],
            'patch' => ['patch', false, true, false],
            'delete' => ['delete', false, true, true],
        ];
    }

    #[DataProvider('verbProvider')]
    public function testVerbDerivation(string $verb, bool $readOnly, bool $destructive, bool $idempotent): void
    {
        $safety = (new AnnotationDeriver())($verb);

        $this->assertSame($readOnly, $safety->readOnly);
        $this->assertSame($destructive, $safety->destructive);
        $this->assertSame($idempotent, $safety->idempotent);
        $this->assertFalse($safety->openWorld, 'openWorld defaults to false: the app is a closed domain');
    }

    public function testAttributeOverrides(): void
    {
        $safety = (new AnnotationDeriver())('delete', destructive: false, idempotent: false, openWorld: true);

        $this->assertFalse($safety->readOnly);
        $this->assertFalse($safety->destructive, 'soft-delete override');
        $this->assertFalse($safety->idempotent);
        $this->assertTrue($safety->openWorld);
    }

    public function testNullOverridesKeepVerbDefaults(): void
    {
        $safety = (new AnnotationDeriver())('put', destructive: null, idempotent: null, openWorld: null);

        $this->assertTrue($safety->destructive);
        $this->assertTrue($safety->idempotent);
        $this->assertFalse($safety->openWorld);
    }
}
