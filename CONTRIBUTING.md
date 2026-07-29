# Contributing

## Known workarounds for upstream bugs

- `composer.json`'s `conflict.psr/container: <1.1` works around an mcp/sdk bug
  (untyped `psr/container` `^1.0` accepted, but `Container::get()`/`has()`
  require 1.1+ parameter-typed semantics — see [#3](https://github.com/NaokiTsuchiya/BEAR.Mcp/issues/3)).
  Remove this entry once the upstream fix
  ([#74](https://github.com/NaokiTsuchiya/BEAR.Mcp/issues/74)) is merged and
  released, so the workaround does not outlive its cause.
