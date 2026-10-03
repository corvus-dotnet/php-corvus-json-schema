FROM php:8.5-cli-alpine
WORKDIR /usr/src/harness
# Optionally pin the installed version so `build-all` can rebuild historical versions. An empty value (the normal
# build) installs the latest release, as Packagist lists it.
ARG IMPLEMENTATION_VERSION
# The prebuilt extension for this PHP (its minor version, thread safety, architecture, musl), downloaded directly from
# the release: the archive PIE would install, without PIE's calls to GitHub's API, which unauthenticated CI runners
# can find rate-limited.
RUN apk add --no-cache --virtual .fetch curl jq unzip \
 && version="${IMPLEMENTATION_VERSION}" \
 && if [ -z "$version" ]; then \
      version=$(curl -sSfL https://repo.packagist.org/p2/corvus-dotnet/corvus-json-schema.json \
        | jq -r '[.packages["corvus-dotnet/corvus-json-schema"][].version | select(test("^[0-9]+\\.[0-9]+\\.[0-9]+$"))] | sort_by(split(".") | map(tonumber)) | last'); \
    fi \
 && name="php_corvus_json_schema-${version}_php$(php -r 'echo PHP_MAJOR_VERSION, ".", PHP_MINOR_VERSION;')-$(uname -m | sed 's/aarch64/arm64/')-linux-musl$(php -r 'echo PHP_ZTS ? "-zts" : "";')" \
 && curl -sSfL -o /tmp/extension.zip "https://github.com/corvus-dotnet/corvus-json-schema-php/releases/download/${version}/${name}.zip" \
 && unzip -q /tmp/extension.zip -d /tmp/extension \
 && cp /tmp/extension/corvus_json_schema.so "$(php-config --extension-dir)/" \
 && docker-php-ext-enable corvus_json_schema \
 && rm -rf /tmp/extension /tmp/extension.zip \
 && apk del .fetch \
 && php -r 'exit(extension_loaded("corvus_json_schema") ? 0 : 1);'
COPY bowtie_corvus_json_schema.php .
# No memory limit: some cases build large values.
CMD ["php", "-d", "memory_limit=-1", "bowtie_corvus_json_schema.php"]
