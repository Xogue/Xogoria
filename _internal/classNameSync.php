<?php

$root = dirname(__DIR__);
$reportFile = __DIR__ . '/classNameSyncReport.json';

$options = parseArguments($argv);
$dryRun = !$options['apply'];
$scanPaths = $options['paths'] ?: ['html', 'private'];

$projectFiles = collectProjectFiles($root, $scanPaths, $options['includeArchive'], $options['includeOld']);
$projectFiles = array_values(array_filter(
    $projectFiles,
    static fn (string $file): bool => normalizePath($file) !== normalizePath($reportFile)
));
$phpFiles = array_values(array_filter(
    $projectFiles,
    static fn (string $file): bool => strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'php'
));
$changes = [];
$fileReferenceRenames = [];
$skipped = [];

foreach ($phpFiles as $file) {
    $source = file_get_contents($file);

    if ($source === false) {
        $skipped[] = [
            'file' => relativePath($root, $file),
            'reason' => 'Could not read file.',
        ];
        continue;
    }

    $declarations = findTypeDeclarations($source, $options);

    if (count($declarations) === 0) {
        continue;
    }

    if (count($declarations) > 1) {
        $names = array_map(static fn (array $declaration): string => $declaration['name'], $declarations);
        $skipped[] = [
            'file' => relativePath($root, $file),
            'reason' => 'Multiple type declarations found: ' . implode(', ', $names),
        ];
        continue;
    }

    $declaration = $declarations[0];
    $expectedName = expectedTypeNameFromFile($file);

    if ($declaration['name'] === $expectedName) {
        continue;
    }

    if (!isValidTypeName($expectedName)) {
        $skipped[] = [
            'file' => relativePath($root, $file),
            'reason' => "Filename '{$expectedName}' is not a valid PHP type name.",
        ];
        continue;
    }

    $changes[] = [
        'file' => relativePath($root, $file),
        'kind' => $declaration['kind'],
        'old' => $declaration['name'],
        'new' => $expectedName,
    ];
}

$renameMap = [];
$variableRenameMap = [];

foreach ($changes as $change) {
    if (isset($renameMap[$change['old']]) && $renameMap[$change['old']] !== $change['new']) {
        fwrite(STDERR, "Conflicting rename for {$change['old']}: {$renameMap[$change['old']]} and {$change['new']}.\n");
        exit(1);
    }

    $renameMap[$change['old']] = $change['new'];

    $oldVariableName = typeNameToVariableName($change['old']);
    $newVariableName = typeNameToVariableName($change['new']);

    if ($oldVariableName !== $newVariableName) {
        if (isset($variableRenameMap[$oldVariableName]) && $variableRenameMap[$oldVariableName] !== $newVariableName) {
            fwrite(STDERR, "Conflicting variable rename for {$oldVariableName}: {$variableRenameMap[$oldVariableName]} and {$newVariableName}.\n");
            exit(1);
        }

        $variableRenameMap[$oldVariableName] = $newVariableName;
    }
}

$fileReferenceRenames = buildFileReferenceRenameMap($root, $projectFiles, $changes);

$updatedFiles = [];

if ($renameMap !== [] || $fileReferenceRenames !== []) {
    foreach ($projectFiles as $file) {
        $source = file_get_contents($file);

        if ($source === false || !isTextFileContent($source)) {
            continue;
        }

        if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'php') {
            $updated = rewritePhpSource($source, $renameMap, $variableRenameMap, $fileReferenceRenames, !$options['codeOnly']);
        } else {
            $updated = replaceFileReferences($source, $fileReferenceRenames);
        }

        if ($updated === $source) {
            continue;
        }

        $updatedFiles[] = relativePath($root, $file);

        if (!$dryRun) {
            file_put_contents($file, $updated);
        }
    }
}

$report = [
    'generatedAt' => date('c'),
    'mode' => $dryRun ? 'dry-run' : 'apply',
    'scanPaths' => $scanPaths,
    'includeArchive' => $options['includeArchive'],
    'includeOld' => $options['includeOld'],
    'includeInterfaces' => $options['includeInterfaces'],
    'includeTraits' => $options['includeTraits'],
    'codeOnly' => $options['codeOnly'],
    'renames' => $changes,
    'variableRenames' => $variableRenameMap,
    'fileReferenceRenames' => $fileReferenceRenames,
    'updatedFiles' => array_values(array_unique($updatedFiles)),
    'skipped' => $skipped,
];

file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

echo ($dryRun ? 'Dry run complete.' : 'Class names synchronized.') . PHP_EOL;
echo 'Renames found: ' . count($changes) . PHP_EOL;
echo 'Files ' . ($dryRun ? 'that would be updated: ' : 'updated: ') . count($report['updatedFiles']) . PHP_EOL;
echo 'Skipped files: ' . count($skipped) . PHP_EOL;
echo 'Report: ' . $reportFile . PHP_EOL;

if ($changes !== []) {
    echo PHP_EOL . 'Renames:' . PHP_EOL;

    foreach ($changes as $change) {
        echo " - {$change['file']}: {$change['old']} -> {$change['new']}" . PHP_EOL;
    }
}

if ($variableRenameMap !== []) {
    echo PHP_EOL . 'Variable/property renames:' . PHP_EOL;

    foreach ($variableRenameMap as $old => $new) {
        echo " - \${$old} -> \${$new}" . PHP_EOL;
    }
}

if ($fileReferenceRenames !== []) {
    echo PHP_EOL . 'File reference renames:' . PHP_EOL;

    foreach ($fileReferenceRenames as $old => $new) {
        echo " - {$old} -> {$new}" . PHP_EOL;
    }
}

function parseArguments(array $argv): array
{
    $options = [
        'apply' => false,
        'paths' => [],
        'includeArchive' => false,
        'includeOld' => false,
        'includeInterfaces' => false,
        'includeTraits' => false,
        'codeOnly' => false,
    ];

    foreach (array_slice($argv, 1) as $argument) {
        if ($argument === '--apply') {
            $options['apply'] = true;
            continue;
        }

        if ($argument === '--dry-run') {
            $options['apply'] = false;
            continue;
        }

        if ($argument === '--include-archive') {
            $options['includeArchive'] = true;
            continue;
        }

        if ($argument === '--include-old') {
            $options['includeOld'] = true;
            continue;
        }

        if ($argument === '--include-interfaces') {
            $options['includeInterfaces'] = true;
            continue;
        }

        if ($argument === '--include-traits') {
            $options['includeTraits'] = true;
            continue;
        }

        if ($argument === '--code-only') {
            $options['codeOnly'] = true;
            continue;
        }

        if (strpos($argument, '--path=') === 0) {
            $path = trim(substr($argument, strlen('--path=')));

            if ($path !== '') {
                $options['paths'][] = $path;
            }
        }
    }

    return $options;
}

function collectProjectFiles(string $root, array $scanPaths, bool $includeArchive, bool $includeOld): array
{
    $files = [];

    foreach ($scanPaths as $scanPath) {
        $path = normalizePath($root . DIRECTORY_SEPARATOR . $scanPath);

        if (!is_dir($path)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
                static function (SplFileInfo $current) use ($includeArchive, $includeOld): bool {
                    if (!$current->isDir()) {
                        return true;
                    }

                    $name = $current->getFilename();

                    if ($name === 'vendor' || $name === '_internal' || $name === 'node_modules' || $name === '.git') {
                        return false;
                    }

                    if (!$includeArchive && $name === '__archive') {
                        return false;
                    }

                    if (!$includeOld && $name === '_core_old') {
                        return false;
                    }

                    return true;
                }
            )
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $files[] = normalizePath($file->getPathname());
        }
    }

    sort($files);

    return array_values(array_unique($files));
}

function expectedTypeNameFromFile(string $file): string
{
    return ltrim(pathinfo($file, PATHINFO_FILENAME), '_');
}

function findTypeDeclarations(string $source, array $options): array
{
    $tokens = token_get_all($source);
    $declarations = [];
    $count = count($tokens);

    for ($index = 0; $index < $count; $index++) {
        $token = $tokens[$index];

        if (!is_array($token) || !isDeclarationToken($token[0], $options)) {
            continue;
        }

        if ($token[0] === T_CLASS && previousMeaningfulToken($tokens, $index) === T_NEW) {
            continue;
        }

        $nameIndex = nextMeaningfulTokenIndex($tokens, $index + 1);

        if ($nameIndex === null || !is_array($tokens[$nameIndex]) || $tokens[$nameIndex][0] !== T_STRING) {
            continue;
        }

        $declarations[] = [
            'kind' => strtolower($token[1]),
            'name' => $tokens[$nameIndex][1],
        ];
    }

    return $declarations;
}

function rewritePhpSource(string $source, array $renameMap, array $variableRenameMap, array $fileReferenceRenames, bool $includeText): string
{
    $tokens = token_get_all($source);
    $updated = '';

    foreach ($tokens as $token) {
        if (is_string($token)) {
            $updated .= $token;
            continue;
        }

        [$id, $text] = $token;

        if ($id === T_STRING && isset($renameMap[$text])) {
            $updated .= $renameMap[$text];
            continue;
        }

        if ($id === T_STRING && isset($variableRenameMap[$text])) {
            $updated .= $variableRenameMap[$text];
            continue;
        }

        if ($id === T_VARIABLE) {
            $variableName = substr($text, 1);

            if (isset($variableRenameMap[$variableName])) {
                $updated .= '$' . $variableRenameMap[$variableName];
                continue;
            }
        }

        if ($id === T_COMMENT || $id === T_DOC_COMMENT || isTextToken($id)) {
            if ($includeText) {
                $text = replaceNamesInText($text, $renameMap, $variableRenameMap);
            }

            $updated .= replaceFileReferences($text, $fileReferenceRenames);
            continue;
        }

        $updated .= $text;
    }

    return $updated;
}

function buildFileReferenceRenameMap(string $root, array $projectFiles, array $typeChanges): array
{
    $renames = [];

    foreach ($typeChanges as $change) {
        $oldFileName = $change['old'] . '.' . pathinfo($change['file'], PATHINFO_EXTENSION);
        $newFileName = referenceBasenameForFile($change['file']);

        if ($oldFileName !== $newFileName) {
            $renames[$oldFileName] = $newFileName;
        }
    }

    $referencedPaths = findReferencedFilePaths($projectFiles);

    foreach ($referencedPaths as $reference) {
        $candidate = findCurrentFileForReference($root, $projectFiles, $reference);

        if ($candidate === null) {
            continue;
        }

        $newReference = replaceReferenceBasename($reference, referenceBasenameForFile($candidate));

        if ($newReference !== $reference) {
            $renames[$reference] = $newReference;
            $renames[basename($reference)] = referenceBasenameForFile($candidate);
        }
    }

    uksort($renames, static fn (string $left, string $right): int => strlen($right) <=> strlen($left));

    return $renames;
}

function findReferencedFilePaths(array $projectFiles): array
{
    $references = [];

    foreach ($projectFiles as $file) {
        $source = file_get_contents($file);

        if ($source === false || !isTextFileContent($source)) {
            continue;
        }

        if (preg_match_all('/(?<![A-Za-z0-9_])(?:[A-Za-z]:)?[A-Za-z0-9_.\/\\\\-]+\.[A-Za-z0-9]{1,8}(?![A-Za-z0-9_])/', $source, $matches)) {
            foreach ($matches[0] as $match) {
                $reference = trim($match, " \t\r\n'\"()[]{}<>,;:");

                if ($reference !== '') {
                    $references[$reference] = true;
                }
            }
        }
    }

    return array_keys($references);
}

function findCurrentFileForReference(string $root, array $projectFiles, string $reference): ?string
{
    $normalizedReference = normalizePath($reference);
    $referenceBasename = basename($normalizedReference);

    if ($referenceBasename === '' || fileReferenceExists($root, $projectFiles, $normalizedReference)) {
        return null;
    }

    $referenceDirectory = dirname($normalizedReference);
    $referenceExtension = strtolower(pathinfo($referenceBasename, PATHINFO_EXTENSION));
    $candidates = [];

    foreach ($projectFiles as $file) {
        if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== $referenceExtension) {
            continue;
        }

        if (!referenceDirectoryMatches($root, $file, $referenceDirectory)) {
            continue;
        }

        $score = fileNameSimilarityScore(pathinfo($referenceBasename, PATHINFO_FILENAME), pathinfo($file, PATHINFO_FILENAME));

        if ($score < 4) {
            continue;
        }

        $candidates[] = [
            'file' => $file,
            'score' => $score,
        ];
    }

    usort($candidates, static fn (array $left, array $right): int => $right['score'] <=> $left['score']);

    if (count($candidates) === 0) {
        return null;
    }

    if (count($candidates) > 1 && $candidates[0]['score'] === $candidates[1]['score']) {
        return null;
    }

    return $candidates[0]['file'];
}

function fileReferenceExists(string $root, array $projectFiles, string $reference): bool
{
    foreach (possibleReferencePaths($root, $reference) as $path) {
        if (is_file($path)) {
            return true;
        }
    }

    $referencePath = str_replace(DIRECTORY_SEPARATOR, '/', ltrim($reference, DIRECTORY_SEPARATOR));

    foreach ($projectFiles as $file) {
        if (str_ends_with(str_replace(DIRECTORY_SEPARATOR, '/', $file), '/' . $referencePath)) {
            return true;
        }
    }

    return false;
}

function referenceDirectoryMatches(string $root, string $file, string $referenceDirectory): bool
{
    if ($referenceDirectory === '.' || $referenceDirectory === '') {
        return true;
    }

    $fileDirectory = str_replace(DIRECTORY_SEPARATOR, '/', relativePath($root, dirname($file)));
    $referenceDirectory = str_replace(DIRECTORY_SEPARATOR, '/', trim($referenceDirectory, '/\\'));

    return str_ends_with($fileDirectory, $referenceDirectory)
        || str_ends_with($fileDirectory, 'html/' . $referenceDirectory);
}

function possibleReferencePaths(string $root, string $reference): array
{
    $trimmedReference = ltrim($reference, DIRECTORY_SEPARATOR);

    return [
        normalizePath($root . DIRECTORY_SEPARATOR . $trimmedReference),
        normalizePath($root . DIRECTORY_SEPARATOR . 'html' . DIRECTORY_SEPARATOR . $trimmedReference),
    ];
}

function fileNameSimilarityScore(string $oldName, string $newName): int
{
    $oldName = strtolower(ltrim($oldName, '_'));
    $newName = strtolower(ltrim($newName, '_'));

    return commonSuffixLength($oldName, $newName);
}

function commonSuffixLength(string $left, string $right): int
{
    $leftIndex = strlen($left) - 1;
    $rightIndex = strlen($right) - 1;
    $length = 0;

    while ($leftIndex >= 0 && $rightIndex >= 0 && $left[$leftIndex] === $right[$rightIndex]) {
        $length++;
        $leftIndex--;
        $rightIndex--;
    }

    return $length;
}

function replaceReferenceBasename(string $reference, string $newBasename): string
{
    return preg_replace('/[^\/\\\\]+$/', $newBasename, $reference) ?? $reference;
}

function referenceBasenameForFile(string $file): string
{
    $extension = pathinfo($file, PATHINFO_EXTENSION);
    $filename = ltrim(pathinfo($file, PATHINFO_FILENAME), '_');

    return $extension === '' ? $filename : $filename . '.' . $extension;
}

function replaceFileReferences(string $text, array $fileReferenceRenames): string
{
    foreach ($fileReferenceRenames as $old => $new) {
        $text = preg_replace('/(?<![A-Za-z0-9_])' . preg_quote($old, '/') . '(?![A-Za-z0-9_])/', $new, $text);
    }

    return $text;
}

function replaceNamesInText(string $text, array $renameMap, array $variableRenameMap): string
{
    foreach ($renameMap as $old => $new) {
        $text = preg_replace('/(?<![A-Za-z0-9_])' . preg_quote($old, '/') . '(?![A-Za-z0-9_])/', $new, $text);
    }

    foreach ($variableRenameMap as $old => $new) {
        $text = preg_replace('/(?<![A-Za-z0-9_])' . preg_quote($old, '/') . '(?![A-Za-z0-9_])/', $new, $text);
        $text = preg_replace('/\$' . preg_quote($old, '/') . '(?![A-Za-z0-9_])/', '$' . $new, $text);
    }

    return $text;
}

function typeNameToVariableName(string $typeName): string
{
    return lcfirst($typeName);
}

function isDeclarationToken(int $id, array $options): bool
{
    return $id === T_CLASS
        || ($options['includeInterfaces'] && $id === T_INTERFACE)
        || ($options['includeTraits'] && $id === T_TRAIT)
        || (defined('T_ENUM') && $id === T_ENUM);
}

function isTextToken(int $id): bool
{
    return $id === T_CONSTANT_ENCAPSED_STRING
        || $id === T_ENCAPSED_AND_WHITESPACE
        || $id === T_INLINE_HTML;
}

function isTextFileContent(string $source): bool
{
    return strpos($source, "\0") === false;
}

function previousMeaningfulToken(array $tokens, int $index): ?int
{
    for ($cursor = $index - 1; $cursor >= 0; $cursor--) {
        $token = $tokens[$cursor];

        if (is_array($token) && ($token[0] === T_WHITESPACE || $token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT)) {
            continue;
        }

        return is_array($token) ? $token[0] : null;
    }

    return null;
}

function nextMeaningfulTokenIndex(array $tokens, int $index): ?int
{
    $count = count($tokens);

    for ($cursor = $index; $cursor < $count; $cursor++) {
        $token = $tokens[$cursor];

        if (is_array($token) && ($token[0] === T_WHITESPACE || $token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT)) {
            continue;
        }

        return $cursor;
    }

    return null;
}

function isValidTypeName(string $name): bool
{
    return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) === 1;
}

function relativePath(string $root, string $path): string
{
    $root = rtrim(normalizePath($root), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $path = normalizePath($path);

    if (strpos($path, $root) === 0) {
        return str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($root)));
    }

    return str_replace(DIRECTORY_SEPARATOR, '/', $path);
}

function normalizePath(string $path): string
{
    return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
}
