<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Sdk\Handler;

use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Schema\Implementation;
use Mcp\Schema\JsonRpc\MessageInterface;
use Mcp\Schema\JsonRpc\Request;
use Mcp\Schema\JsonRpc\Response;
use Mcp\Schema\Request\InitializeRequest;
use Mcp\Schema\Result\InitializeResult;
use Mcp\Schema\ServerCapabilities;
use Mcp\Server\Handler\Request\RequestHandlerInterface;
use Mcp\Server\Session\SessionInterface;

use function assert;

/**
 * Spec-conforming version negotiation for initialize
 *
 * The MCP lifecycle spec: "If the server supports the requested protocol
 * version, it MUST respond with the same version." mcp/sdk v0.6's own
 * InitializeHandler ignores the requested version and always answers with
 * its latest, which makes older spec-conforming clients disconnect.
 * Registered via Builder::addRequestHandler(), custom handlers take
 * precedence over the SDK defaults.
 */
final class InitializeHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly ServerCapabilities $capabilities,
        private readonly Implementation $serverInfo,
        private readonly string|null $instructions,
    ) {
    }

    public function supports(Request $request): bool
    {
        return $request instanceof InitializeRequest;
    }

    public function handle(Request $request, SessionInterface $session): Response
    {
        assert($request instanceof InitializeRequest);

        // Same session bookkeeping as the SDK handler: ClientGateway reads these keys
        $session->set('client_info', $request->clientInfo->jsonSerialize());
        $session->set('client_capabilities', $request->capabilities->jsonSerialize());

        $version = ProtocolVersion::tryFrom($request->protocolVersion) ?? MessageInterface::PROTOCOL_VERSION;

        return new Response($request->getId(), new InitializeResult(
            $this->capabilities,
            $this->serverInfo,
            $this->instructions,
            null,
            $version,
        ));
    }
}
