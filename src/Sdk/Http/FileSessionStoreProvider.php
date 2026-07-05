<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Sdk\Http;

use BEAR\AppMeta\AbstractAppMeta;
use Mcp\Server\Session\FileSessionStore;
use Ray\Di\Di\Named;
use Ray\Di\ProviderInterface;

use function is_dir;
use function mkdir;

/**
 * Persistent session store for the Streamable HTTP transport
 *
 * Sessions live under the app's own var/tmp/{context} directory — the same
 * lifecycle as the DI cache, no extra infrastructure required. Bind
 * SessionStoreInterface yourself (e.g. Psr16SessionStore on Redis) when the
 * server runs on more than one host.
 *
 * The directory is created 0700 before the SDK can mkdir it 0775: file names
 * ARE live session ids, and the session payloads are written umask-default
 * (0644), so an enumerable directory on a shared host means session
 * hijacking. 0700 implies the directory belongs to whichever user creates it
 * first — if stdio (CLI) and PHP-FPM run as different users on the same
 * var/tmp, rebind SessionStoreInterface instead of sharing the directory.
 */
final class FileSessionStoreProvider implements ProviderInterface
{
    public function __construct(
        private readonly AbstractAppMeta $appMeta,
        #[Named('mcp_session_ttl')]
        private readonly int $ttl = 3600,
    ) {
    }

    public function get(): FileSessionStore
    {
        $dir = $this->appMeta->tmpDir . '/mcp-sessions';
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        return new FileSessionStore($dir, $this->ttl);
    }
}
