<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Sdk\Registry;

use Mcp\Schema\ResourceTemplate;
use PHPUnit\Framework\TestCase;

final class FormStyleTemplateReferenceTest extends TestCase
{
    public function testSingleVariableExactMatchAndExtraction(): void
    {
        $reference = $this->reference('app://self/todo{?id}');

        $this->assertTrue($reference->matches('app://self/todo?id=42'));
        $this->assertSame(['id' => '42'], $reference->extractVariables('app://self/todo?id=42'));
    }

    public function testMultiVariableMatchAndExtraction(): void
    {
        $reference = $this->reference('app://self/search{?q,limit}');

        $this->assertTrue($reference->matches('app://self/search?q=foo&limit=5'));
        $this->assertSame(
            ['q' => 'foo', 'limit' => '5'],
            $reference->extractVariables('app://self/search?q=foo&limit=5'),
        );
    }

    public function testBareBaseMatchesWithEmptyExtraction(): void
    {
        $reference = $this->reference('app://self/user{?id}');

        $this->assertTrue($reference->matches('app://self/user'));
        $this->assertSame([], $reference->extractVariables('app://self/user'));
    }

    public function testUnknownQueryKeyDoesNotMatch(): void
    {
        $reference = $this->reference('app://self/todo{?id}');

        $this->assertFalse($reference->matches('app://self/todo?bogus=1'));
    }

    public function testNonMatchingBaseDoesNotMatch(): void
    {
        $reference = $this->reference('app://self/todo{?id}');

        $this->assertFalse($reference->matches('app://self/user?id=1'));
    }

    public function testLiteralUriTemplateStringDoesNotFalselyMatch(): void
    {
        // CompletionCompleteHandler calls getResource() with the literal uriTemplate
        // string itself; matches() must not crash or false-positive on the raw
        // '{'/'?'/'}' characters — FormStyleRegistry handles that case via an exact
        // key lookup before ever calling matches().
        $reference = $this->reference('app://self/todo{?id}');

        $this->assertFalse($reference->matches('app://self/todo{?id}'));
    }

    private function reference(string $uriTemplate): FormStyleTemplateReference
    {
        return new FormStyleTemplateReference(
            new ResourceTemplate($uriTemplate, 'name'),
            static fn () => null,
        );
    }
}
