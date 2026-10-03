# php-corvus-json-schema

A [Bowtie](https://github.com/bowtie-json-schema/bowtie) test harness for
[corvus-dotnet/corvus-json-schema](https://packagist.org/packages/corvus-dotnet/corvus-json-schema), the PHP
extension of [Corvus.JsonSchema](https://github.com/corvus-dotnet/Corvus.JsonSchema): an extension over the
corvus-json-schema Rust crate, installed with [PIE](https://github.com/php/pie).

Its image is published to `ghcr.io/bowtie-json-schema/php-corvus-json-schema` and run via
`bowtie run -i php-corvus-json-schema`.

The harness decodes each request to objects (so schemas and instances keep `{}` and `[]` apart), compiles each case's
schema with the case's `registry` as the document resolver, and validates each instance. For `annotations` output it
evaluates through a verbose collector and reports each annotation with its instance location and `#…` keyword
location.

The image installs the latest release with PIE; the `IMPLEMENTATION_VERSION` build argument pins another.
