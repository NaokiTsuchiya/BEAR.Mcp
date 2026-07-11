<?php

declare(strict_types=1);

namespace FakeVendor\ProjectionProject\Resource\App;

use BEAR\Resource\ResourceObject;
use NaokiTsuchiya\BEAR\Mcp\Attribute\Mcp;
use Ray\WebContextParam\Annotation\CookieParam;

/** Web-context parameters are not caller arguments: excluded from the template */
class Session extends ResourceObject
{
    /**
     * Session-scoped lookup
     *
     * @param string $id Entry ID
     */
    #[Mcp]
    public function onGet(string $id, #[CookieParam(key: 'session_id')] string $session = ''): static
    {
        $this->body = ['id' => $id, 'session' => $session];

        return $this;
    }
}
