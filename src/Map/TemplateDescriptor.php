<?php

declare(strict_types=1);

namespace NaokiTsuchiya\BEAR\Mcp\Map;

/**
 * SDK-neutral description of one exposed MCP resource template
 *
 * The projection of a GET method with caller arguments: the method signature
 * becomes an RFC 6570 form-style template (app://self/todo{?id,tag}).
 */
final readonly class TemplateDescriptor
{
    /**
     * @param string                                       $uriTemplate RFC 6570 form-style (app://self/todo{?id,tag})
     * @param list<string>                                 $variables   Template variable names in declaration order
     * @param array<string, list<bool|float|int|string>>   $completions Per-variable completion candidates
     *                                                                  (from #[JsonSchema(params:)] enums)
     */
    public function __construct(
        public string $uriTemplate,
        public string $name,
        public string|null $title,
        public string|null $description,
        public array $variables,
        public array $completions = [],
        public string $mimeType = 'application/json',
    ) {
    }
}
