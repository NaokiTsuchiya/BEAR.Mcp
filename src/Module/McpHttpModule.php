<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Module;

use Mcp\Server\Session\SessionStoreInterface;
use NaokiTsuchiya\BEAR\Mcp\Sdk\Http\FileSessionStoreProvider;
use NaokiTsuchiya\BEAR\Mcp\Sdk\Transport\McpRequestHandler;
use Ray\Di\AbstractModule;
use Ray\Di\Scope;

/**
 * Streamable HTTP bindings, installed on top of McpModule
 *
 * Sessions default to files under var/tmp/{context}/mcp-sessions; rebind
 * SessionStoreInterface (e.g. the SDK's Psr16SessionStore on Redis) for
 * multi-host deployments.
 */
final class McpHttpModule extends AbstractModule
{
    /**
     * @param list<string>|null $allowedHosts Host allowlist for DNS-rebinding
     *   protection (null = localhost only). Required when serving on a real domain.
     */
    public function __construct(
        private readonly array|null $allowedHosts = null,
        private readonly int $sessionTtl = 3600,
        AbstractModule|null $module = null,
    ) {
        parent::__construct($module);
    }

    protected function configure(): void
    {
        $this->bind(SessionStoreInterface::class)->toProvider(FileSessionStoreProvider::class)->in(Scope::SINGLETON);
        $this->bind()->annotatedWith('mcp_session_ttl')->toInstance($this->sessionTtl);
        $this->bind(McpRequestHandler::class)->in(Scope::SINGLETON);
        if ($this->allowedHosts !== null) {
            $this->bind()->annotatedWith('mcp_allowed_hosts')->toInstance($this->allowedHosts);
        }
    }
}
