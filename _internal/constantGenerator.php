<?php

$root = dirname(__DIR__);

$libraries = [
    [
        'source' => $root . '/html/_core/_init/config/reportCodes.json',
        'output' => $root . '/html/_core/_init/_status/ReportLibrary.php',
        'className' => 'ReportLibrary',
    ],
    [
        'source' => $root . '/html/_core/_init/config/responseCodes.json',
        'output' => $root . '/html/_core/_init/_status/ResponseLibrary.php',
        'className' => 'ResponseLibrary',
    ],
];

$requestedLibrary = strtolower((string)($argv[1] ?? 'all'));
if ($requestedLibrary !== 'all') {
    $libraries = array_values(array_filter(
        $libraries,
        static fn(array $library): bool => strtolower($library['className']) === $requestedLibrary
    ));
    if ($libraries === []) {
        throw new InvalidArgumentException('Use all, reportlibrary, or responselibrary.');
    }
}

foreach ($libraries as $library) {
    generateConstantLibrary($library['source'], $library['output'], $library['className']);
}

function generateConstantLibrary(string $source, string $output, string $className): void
{
    $json = file_get_contents($source);

    if ($json === false) {
        throw new RuntimeException("Could not read {$source}");
    }

    $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    $constants = [];

    foreach ($data as $code => $entry) {
        if (strpos($code, 'SECTION') === 0) { continue; }

        if (empty($entry['constantName'])) {
            throw new RuntimeException("Missing constantName for code {$code} in {$source}");
        }

        $constantName = strtoupper($entry['constantName']);

        if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $constantName)) {
            throw new RuntimeException("Invalid constantName '{$constantName}' for code {$code} in {$source}");
        }

        if (isset($constants[$constantName])) {
            throw new RuntimeException(
                "Duplicate constantName '{$constantName}' for {$constants[$constantName]} and {$code} in {$source}"
            );
        }

        $constants[$constantName] = $code;
    }

    ksort($constants);

    $php = "<?php\n\n";
    $php .= '// This file is generated from ' . basename($source) . ". Do not edit manually.\n\n";
    $php .= "final class {$className} {\n";

    foreach ($constants as $constantName => $code) {
        $php .= "    public const {$constantName} = '{$code}';\n";
    }

    $php .= "}\n";

    $outputDirectory = dirname($output);
    if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0775, true) && !is_dir($outputDirectory)) {
        throw new RuntimeException("Could not create {$outputDirectory}");
    }

    if (file_put_contents($output, $php) === false) {
        throw new RuntimeException("Could not write {$output}");
    }

    echo "Generated {$output}\n";
}
