<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Interop;

use BEAR\ToolUse\Schema\SchemaConverter;
use BEAR\ToolUse\Schema\SchemaConverterInterface;
use FakeVendor\ToolUseProject\Resource\App\Legacy;
use FakeVendor\ToolUseProject\Resource\App\Order;
use FakeVendor\ToolUseProject\Resource\App\Ping;
use FakeVendor\ToolUseProject\Resource\App\Retired;
use NaokiTsuchiya\BEAR\Mcp\Exception\DuplicateToolNameException;
use NaokiTsuchiya\BEAR\Mcp\Exception\ToolUseContractException;
use NaokiTsuchiya\BEAR\Mcp\Fake\FakeResourceFactory;
use NaokiTsuchiya\BEAR\Mcp\Map\AnnotationDeriver;
use NaokiTsuchiya\BEAR\Mcp\Map\McpMap;
use NaokiTsuchiya\BEAR\Mcp\Map\ToolDescriptor;
use PHPUnit\Framework\TestCase;
use phpDocumentor\Reflection\DocBlockFactory;

use function array_map;

final class ToolUseBridgeTest extends TestCase
{
    private ToolUseBridge $bridge;

    protected function setUp(): void
    {
        $this->bridge = new ToolUseBridge(
            new SchemaConverter(DocBlockFactory::createInstance()),
            new FakeResourceFactory([
                'app://self/order' => Order::class,
                'app://self/legacy' => Legacy::class,
                'app://self/ping' => Ping::class,
                'app://self/retired' => Retired::class,
            ]),
            new AnnotationDeriver(),
        );
    }

    /** @return array<string, ToolDescriptor> */
    private function orderTools(): array
    {
        $byName = [];
        foreach (($this->bridge)(['app://self/order']) as $descriptor) {
            $byName[$descriptor->name] = $descriptor;
        }

        return $byName;
    }

    public function testIsAvailableWithToolUseInstalled(): void
    {
        $this->assertTrue(ToolUseBridge::isAvailable());
    }

    public function testCollectsOnlyExplicitlyPassedUris(): void
    {
        $names = array_map(
            static fn (ToolDescriptor $t): string => $t->name,
            ($this->bridge)(['app://self/order']),
        );

        $this->assertSame(['order_get', 'order_post', 'order_cancel'], $names, 'legacy is not collected');
    }

    public function testCustomToolNameKeepsItsRealVerb(): void
    {
        $cancel = $this->orderTools()['order_cancel'];

        // ToolUse's own registry would infer 'cancel' (or fall back to 'get')
        // from the custom name; the bridge pairs at collection time instead
        $this->assertSame('delete', $cancel->verb);
        $this->assertSame('app://self/order', $cancel->uri);
        $this->assertTrue($cancel->safety->destructive, 'verb-derived');
        $this->assertTrue($cancel->safety->idempotent);
    }

    public function testConfirmTranslatesToDestructiveHint(): void
    {
        $post = $this->orderTools()['order_post'];

        $this->assertFalse($post->safety->readOnly);
        $this->assertTrue($post->safety->destructive, 'POST derives false; confirm: true overrides');
        $this->assertSame('Place an order', $post->description, 'phpdoc summary via SchemaConverter');
    }

    public function testConfirmKeyNeverReachesTheWireSchema(): void
    {
        foreach ($this->orderTools() as $descriptor) {
            $this->assertArrayNotHasKey('confirm', $descriptor->inputSchema);
        }
    }

    public function testNullableNormalizesToTypeArray(): void
    {
        $get = $this->orderTools()['order_get'];

        $this->assertSame(['integer', 'null'], $get->inputSchema['properties']['id']['type']);
        $this->assertArrayNotHasKey('nullable', $get->inputSchema['properties']['id']);
        $this->assertArrayNotHasKey('required', $get->inputSchema, 'empty required is omitted, not sent as []');
        $this->assertTrue($get->safety->readOnly);
        $this->assertNull($get->outputSchema, 'ToolUse carries no output schema');
    }

    public function testArgumentLessMethodOmitsEmptyPropertiesAndRequired(): void
    {
        $descriptors = ($this->bridge)(['app://self/ping']);

        // SchemaConverter emits properties: [] / required: [] — as JSON these
        // would be arrays, and [] is not a valid `properties` object
        $this->assertSame(['type' => 'object'], $descriptors[0]->inputSchema);
    }

    public function testClassLevelExcludeCollectsNothing(): void
    {
        $this->assertSame([], ($this->bridge)(['app://self/retired']));
    }

    public function testConverterContractDriftFailsFast(): void
    {
        $emptyConverter = new class implements SchemaConverterInterface {
            /** @return list<\BEAR\ToolUse\Schema\Tool> */
            public function convert(string $resourceClass, string $resourcePath): array
            {
                return []; // pretends upstream stopped returning one tool per verb method
            }
        };
        $bridge = new ToolUseBridge(
            $emptyConverter,
            new FakeResourceFactory(['app://self/order' => Order::class]),
            new AnnotationDeriver(),
        );

        $this->expectException(ToolUseContractException::class);

        $bridge(['app://self/order']);
    }

    public function testExcludedMethodDoesNotShiftVerbPairing(): void
    {
        $names = [];
        foreach (($this->bridge)(['app://self/legacy']) as $descriptor) {
            $names[$descriptor->name] = $descriptor->verb;
        }

        $this->assertSame(['legacy_get' => 'get', 'legacy_delete' => 'delete'], $names, 'onPut is #[Exclude]d');
    }

    public function testUnionTypeAnyOfPassesThroughUnchanged(): void
    {
        $descriptors = ($this->bridge)(['app://self/legacy']);

        $this->assertSame(
            // reflection reports union members in canonical order, not declaration order
            [['type' => 'string'], ['type' => 'integer']],
            $descriptors[0]->inputSchema['properties']['key']['anyOf'],
        );
    }

    public function testBridgedToolCollidingWithMcpToolFailsFast(): void
    {
        $bridged = ($this->bridge)(['app://self/order']);

        $this->expectException(DuplicateToolNameException::class);
        $this->expectExceptionMessageMatches('/order_get/');

        new McpMap([...$bridged, ...$bridged]);
    }
}
