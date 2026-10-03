FROM php:8.4-cli-alpine
WORKDIR /usr/src/harness
# PIE, PHP's extension installer.
ADD --chmod=755 https://github.com/php/pie/releases/latest/download/pie.phar /usr/local/bin/pie
# Optionally pin the installed version so `build-all` can rebuild historical versions.
# An empty value (the normal build) installs the latest release: PIE downloads the prebuilt extension for this PHP
# (8.4, NTS, musl, x86_64 or arm64) and enables it.
ARG IMPLEMENTATION_VERSION
RUN pie install "corvus-dotnet/corvus-json-schema${IMPLEMENTATION_VERSION:+:${IMPLEMENTATION_VERSION}}" \
 && php -r 'exit(extension_loaded("corvus_json_schema") ? 0 : 1);'
COPY bowtie_corvus_json_schema.php .
# No memory limit: some cases build large values.
CMD ["php", "-d", "memory_limit=-1", "bowtie_corvus_json_schema.php"]
