<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Schema;

use FakeVendor\FakeProject\Resource\App\Multi;
use FakeVendor\FakeProject\Resource\App\Search;
use FakeVendor\FakeProject\Resource\App\Todo;
use FakeVendor\ProjectionProject\Resource\App\Session;
use PHPUnit\Framework\TestCase;

final class UriTemplateFactoryTest extends TestCase
{
    private UriTemplateFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new UriTemplateFactory();
    }

    public function testSingleParameter(): void
    {
        $template = ($this->factory)('app://self/todo', Todo::class, 'get');

        $this->assertNotNull($template);
        $this->assertSame('app://self/todo{?id}', $template->template);
        $this->assertSame(['id'], $template->variables);
    }

    public function testParametersKeepDeclarationOrder(): void
    {
        $template = ($this->factory)('app://self/search', Search::class, 'get');

        $this->assertNotNull($template);
        $this->assertSame('app://self/search{?q,limit}', $template->template);
        $this->assertSame(['q', 'limit'], $template->variables);
    }

    public function testArgumentLessMethodIsNotATemplate(): void
    {
        $this->assertNull(($this->factory)('app://self/multi', Multi::class, 'get'));
    }

    public function testWebContextParameterIsExcluded(): void
    {
        $template = ($this->factory)('app://self/session', Session::class, 'get');

        $this->assertNotNull($template);
        $this->assertSame('app://self/session{?id}', $template->template);
    }
}
