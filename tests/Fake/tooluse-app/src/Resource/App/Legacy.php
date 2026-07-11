<?php

declare(strict_types=1);

namespace FakeVendor\ToolUseProject\Resource\App;

use BEAR\Resource\ResourceObject;
use BEAR\ToolUse\Attribute\Exclude;

class Legacy extends ResourceObject
{
    /**
     * Look up a legacy record
     */
    public function onGet(int|string $key): static
    {
        $this->body = ['key' => $key];

        return $this;
    }

    /** Excluded: the bridge must still pair the remaining tools with their real verbs */
    #[Exclude]
    public function onPut(int $id): static
    {
        $this->body = ['id' => $id];

        return $this;
    }

    /**
     * Purge a legacy record
     */
    public function onDelete(int $id): static
    {
        $this->body = ['purged' => $id];

        return $this;
    }
}
