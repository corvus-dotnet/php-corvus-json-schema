<?php
// A Bowtie (https://github.com/bowtie-json-schema/bowtie) harness for the corvus_json_schema PHP extension.
//
// It speaks IHOP (one JSON request per line on standard input, one response per line on standard output):
// - `start` reports the implementation and its dialects;
// - `dialect` sets the dialect for schemas without `$schema`;
// - `run` compiles the case's schema with the case's `registry` as the document resolver and validates each instance
//   (for `annotations` output, through a verbose collector, reporting each annotation with its instance location and
//   `#…` keyword location);
// - `stop` exits.
// Requests are decoded to objects (stdClass), so schemas and instances keep `{}` and `[]` apart.

use Corvus\JsonSchema\{Collector, Dialect, ResultsLevel, Validator};

const DIALECTS = [
    'https://json-schema.org/draft/2020-12/schema' => 'Draft202012',
    'https://json-schema.org/draft/2019-09/schema' => 'Draft201909',
    'http://json-schema.org/draft-07/schema#' => 'Draft7',
    'http://json-schema.org/draft-06/schema#' => 'Draft6',
    'http://json-schema.org/draft-04/schema#' => 'Draft4',
];

function errored(Throwable $error): array
{
    return ['errored' => true, 'context' => ['message' => $error->getMessage(), 'traceback' => (string) $error]];
}

function strip_fragment(string $uri): string
{
    return explode('#', $uri, 2)[0];
}

// Percent-encodes text as a URI fragment does (upper-case hex, UTF-8), keeping the characters a fragment allows.
function percent_encode(string $text): string
{
    $out = '';
    foreach (str_split($text) as $c) {
        $out .= preg_match('/^[A-Za-z0-9\-._~!$&\'()*+,;=:@\/?]$/', $c) ? $c : sprintf('%%%02X', ord($c));
    }
    return $out;
}

// The annotations a verbose collector grouped (instance location, then keyword as a JSON-pointer token, then schema
// location as a `#…` fragment; the same in every Corvus implementation) as Bowtie lists them: each with its keyword
// unescaped, and its keyword location the schema location's fragment followed by `/` and the keyword token,
// percent-encoded as the fragment is.
function annotations_of(array $grouped): array
{
    $found = [];
    foreach ($grouped as $instanceLocation => $keywords) {
        foreach ($keywords as $token => $locations) {
            foreach ($locations as $schemaLocation => $value) {
                $found[] = [
                    'keyword' => str_replace(['~1', '~0'], ['/', '~'], (string) $token),
                    'instanceLocation' => (string) $instanceLocation,
                    'keywordLocation' => $schemaLocation . '/' . percent_encode((string) $token),
                    'annotation' => $value,
                ];
            }
        }
    }
    return $found;
}

$started = false;
$dialect = Dialect::Draft202012;

while (($line = fgets(STDIN)) !== false) {
    if (trim($line) === '') {
        continue;
    }
    $request = json_decode($line, false, 512, JSON_THROW_ON_ERROR);
    switch ($request->cmd ?? null) {
        case 'start':
            if (($request->version ?? null) !== 1) {
                throw new RuntimeException('Unsupported IHOP version ' . json_encode($request->version ?? null));
            }
            $started = true;
            $response = [
                'version' => 1,
                'implementation' => [
                    'language' => 'php',
                    'name' => 'corvus-json-schema',
                    'version' => phpversion('corvus_json_schema'),
                    'homepage' => 'https://github.com/corvus-dotnet/Corvus.JsonSchema',
                    'documentation' => 'https://packagist.org/packages/corvus-dotnet/corvus-json-schema',
                    'issues' => 'https://github.com/corvus-dotnet/Corvus.JsonSchema/issues',
                    'source' => 'https://github.com/corvus-dotnet/Corvus.JsonSchema',
                    'dialects' => array_keys(DIALECTS),
                    'os' => PHP_OS,
                    'os_version' => php_uname('r'),
                    'language_version' => PHP_VERSION,
                ],
            ];
            break;
        case 'dialect':
            $started or throw new RuntimeException('Not started');
            $name = DIALECTS[$request->dialect] ?? null;
            if ($name === null) {
                $response = ['ok' => false];
            } else {
                $dialect = constant(Dialect::class . '::' . $name);
                $response = ['ok' => true];
            }
            break;
        case 'run':
            $started or throw new RuntimeException('Not started');
            $case = $request->case;
            $registry = [];
            foreach ($case->registry ?? [] as $uri => $schema) {
                $registry[strip_fragment($uri)] = $schema;
            }
            try {
                $validator = Validator::compile($case->schema, [
                    'defaultDialect' => $dialect,
                    'resolver' => fn (string $uri) => $registry[strip_fragment($uri)] ?? null,
                ]);
            } catch (Throwable $e) { // every compilation failure is reported for the case
                $response = ['seq' => $request->seq] + errored($e);
                break;
            }
            $annotations = ($request->output ?? null) === 'annotations';
            $results = [];
            foreach ($case->tests as $test) {
                try { // an error for one instance does not stop the others
                    if ($annotations) {
                        $collector = new Collector(ResultsLevel::Verbose);
                        $valid = $validator->evaluate($test->instance, $collector);
                        $results[] = ['valid' => $valid, 'annotations' => annotations_of($collector->annotations())];
                    } else {
                        $results[] = ['valid' => $validator->isValid($test->instance)];
                    }
                } catch (Throwable $e) {
                    $results[] = errored($e);
                }
            }
            $response = ['seq' => $request->seq, 'results' => $results];
            break;
        case 'stop':
            $started or throw new RuntimeException('Not started');
            exit(0);
        default:
            throw new RuntimeException('Unknown command ' . json_encode($request->cmd ?? null));
    }
    fwrite(STDOUT, json_encode($response, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION) . "\n");
}
