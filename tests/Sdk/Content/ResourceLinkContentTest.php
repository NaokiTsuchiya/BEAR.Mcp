<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Sdk\Content;

use PHPUnit\Framework\TestCase;

final class ResourceLinkContentTest extends TestCase
{
    public function testTypeIsResourceLink(): void
    {
        $content = new ResourceLinkContent('app://self/todo?id=1', 'todo');

        $this->assertSame('resource_link', $content->type);
    }

    public function testJsonSerializeWithoutTitleOmitsTitleKey(): void
    {
        $content = new ResourceLinkContent('app://self/todo?id=1', 'todo');

        $this->assertSame([
            'type' => 'resource_link',
            'uri' => 'app://self/todo?id=1',
            'name' => 'todo',
        ], $content->jsonSerialize());
    }

    public function testJsonSerializeWithTitleIncludesTitleKey(): void
    {
        $content = new ResourceLinkContent('app://self/todo?id=1', 'todo', 'Todo #1');

        $this->assertSame([
            'type' => 'resource_link',
            'uri' => 'app://self/todo?id=1',
            'name' => 'todo',
            'title' => 'Todo #1',
        ], $content->jsonSerialize());
    }
}
