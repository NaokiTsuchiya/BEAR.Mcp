<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Map;

use BEAR\Resource\Annotation\Link;

use function explode;
use function is_array;
use function is_bool;
use function is_scalar;
use function ltrim;
use function preg_match;
use function preg_match_all;
use function preg_replace;
use function str_contains;
use function strtolower;
use function uri_template;

/**
 * Resolve #[Link] href templates against a response body
 *
 * app://self/comments{?todo_id} + body ['todo_id' => 42]
 * => {rel: comments, uri: app://self/comments?todo_id=42}
 *
 * Expansion delegates to the same uri_template() (RFC 6570) that bear/resource
 * uses for HAL _links, so resolved URIs match the HAL rendering byte for byte.
 *
 * Conservative by design: uri_template() expands missing variables to nothing,
 * which would publish half-built URIs — so a link whose variables cannot all be
 * resolved to scalars from the body is silently omitted instead. A missing
 * hyperlink is harmless, a broken URI is not. Only safe (GET) links qualify:
 * they are followed via resources/read.
 */
final class LinkResolver
{
    /**
     * @param list<Link> $links OptionsMethods extras 'links' for the called method
     *
     * @return list<ResolvedLink>
     */
    public function __invoke(array $links, mixed $body): array
    {
        if (! is_array($body)) {
            return [];
        }

        $resolved = [];
        foreach ($links as $link) {
            if ($link->rel === '' || $link->href === '' || strtolower($link->method) !== 'get') {
                continue;
            }

            $uri = $this->expand($link->href, $body);
            if ($uri === null) {
                continue;
            }

            $resolved[] = new ResolvedLink($link->rel, $uri, $link->title === '' ? null : $link->title);
        }

        return $resolved;
    }

    /** @param array<array-key, mixed> $body */
    private function expand(string $href, array $body): string|null
    {
        $names = $this->variables($href);
        if ($names === null) {
            return null;
        }

        $values = [];
        foreach ($names as $name) {
            $value = $body[$name] ?? null;
            if (! is_scalar($value)) {
                return null;
            }

            // uri_template() renders false as '' — encode booleans explicitly
            $values[$name] = is_bool($value) ? ($value ? '1' : '0') : $value;
        }

        $uri = uri_template($href, $values);

        // an expression the extractor accepted but the expander did not
        return str_contains($uri, '{') || str_contains($uri, '}') ? null : $uri;
    }

    /**
     * Variable names referenced by the href's RFC 6570 expressions,
     * or null when an expression is malformed (=> omit the link)
     *
     * @return list<string>|null
     */
    private function variables(string $href): array|null
    {
        preg_match_all('/\{([^{}]*)\}/', $href, $matches);

        $names = [];
        foreach ($matches[1] as $expression) {
            $varList = ltrim($expression, '+#./;?&'); // strip the operator
            foreach (explode(',', $varList) as $varSpec) {
                $name = (string) preg_replace('/(\*|:\d+)$/', '', $varSpec); // strip explode/prefix modifiers
                if (preg_match('/^[A-Za-z0-9_.%]+$/', $name) !== 1) {
                    return null;
                }

                $names[] = $name;
            }
        }

        return $names;
    }
}
