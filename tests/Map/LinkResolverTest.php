<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Map;

use BEAR\Resource\Annotation\Link;
use PHPUnit\Framework\TestCase;

final class LinkResolverTest extends TestCase
{
    private LinkResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new LinkResolver();
    }

    public function testResolvesTemplateVariablesFromBody(): void
    {
        $links = [new Link(rel: 'comments', href: 'app://self/todo/comments?todo_id={id}')];

        $resolved = ($this->resolver)($links, ['id' => 42, 'title' => 'irrelevant']);

        $this->assertCount(1, $resolved);
        $this->assertSame('comments', $resolved[0]->rel);
        $this->assertSame('app://self/todo/comments?todo_id=42', $resolved[0]->uri);
        $this->assertNull($resolved[0]->title);
    }

    public function testLiteralHrefPassesThrough(): void
    {
        $links = [new Link(rel: 'home', href: 'app://self/index', title: 'Home')];

        $resolved = ($this->resolver)($links, []);

        $this->assertSame('app://self/index', $resolved[0]->uri);
        $this->assertSame('Home', $resolved[0]->title);
    }

    public function testMissingBodyValueOmitsTheLinkSilently(): void
    {
        $links = [
            new Link(rel: 'author', href: 'app://self/user?id={user_id}'),
            new Link(rel: 'self', href: 'app://self/todo?id={id}'),
        ];

        $resolved = ($this->resolver)($links, ['id' => 1]);

        $this->assertCount(1, $resolved, 'unresolvable author link is dropped, resolvable self link kept');
        $this->assertSame('self', $resolved[0]->rel);
    }

    public function testNonScalarBodyValueOmitsTheLink(): void
    {
        $links = [new Link(rel: 'tags', href: 'app://self/tag?name={tags}')];

        $this->assertSame([], ($this->resolver)($links, ['tags' => ['a', 'b']]));
    }

    public function testNonArrayBodyResolvesNothing(): void
    {
        $links = [new Link(rel: 'self', href: 'app://self/raw')];

        $this->assertSame([], ($this->resolver)($links, 'scalar body'));
    }

    public function testNonGetLinkIsNotASafeTransition(): void
    {
        $links = [new Link(rel: 'delete', href: 'app://self/todo?id={id}', method: 'delete')];

        $this->assertSame([], ($this->resolver)($links, ['id' => 1]));
    }

    public function testFormStyleExpressionExpands(): void
    {
        // the same idiom bear/resource expands for HAL _links must resolve here
        $links = [new Link(rel: 'comments', href: 'app://self/comments{?todo_id}')];

        $resolved = ($this->resolver)($links, ['todo_id' => 42]);

        $this->assertSame('app://self/comments?todo_id=42', $resolved[0]->uri);
    }

    public function testMultiExpressionHrefExpands(): void
    {
        $links = [new Link(rel: 'item', href: 'app://self/item{/id}{?tag}')];

        $resolved = ($this->resolver)($links, ['id' => 7, 'tag' => 'x y']);

        $this->assertSame('app://self/item/7?tag=x%20y', $resolved[0]->uri);
    }

    public function testFormStyleWithMissingValueIsOmittedNotHalfBuilt(): void
    {
        // uri_template() alone would render 'app://self/comments' and silently
        // lose the parameter; the conservative contract omits the link instead
        $links = [new Link(rel: 'comments', href: 'app://self/comments{?todo_id}')];

        $this->assertSame([], ($this->resolver)($links, ['id' => 1]));
    }

    public function testMalformedExpressionIsOmittedNotPublishedVerbatim(): void
    {
        $links = [new Link(rel: 'odd', href: 'app://self/odd{x$}')];

        $this->assertSame([], ($this->resolver)($links, ['x' => 1]), 'no unexpanded {…} may reach the wire');
    }

    public function testValuesArePercentEncoded(): void
    {
        $links = [new Link(rel: 'search', href: 'app://self/search?q={q}')];

        $resolved = ($this->resolver)($links, ['q' => 'a b/c']);

        $this->assertSame('app://self/search?q=a%20b%2Fc', $resolved[0]->uri);
    }

    public function testBooleanValueEncodesAsOneOrZero(): void
    {
        $links = [new Link(rel: 'done', href: 'app://self/todo?done={done}')];

        $resolved = ($this->resolver)($links, ['done' => false]);

        $this->assertSame('app://self/todo?done=0', $resolved[0]->uri, '(string) false would be an empty string');
    }
}
