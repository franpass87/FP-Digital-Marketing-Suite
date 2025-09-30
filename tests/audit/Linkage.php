<?php

declare(strict_types=1);

namespace FP\DMS\Audit;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

$root = dirname(__DIR__, 2);
$inventoryFile = __DIR__ . '/inventory.json';
if (! is_file($inventoryFile)) {
    throw new RuntimeException('Inventory not found. Run Phase 1 first.');
}

$inventory = json_decode(file_get_contents($inventoryFile) ?: '', true, 512, JSON_THROW_ON_ERROR);

if (! is_array($inventory)) {
    throw new RuntimeException('Inventory data is invalid.');
}

$methodCatalog = [];
$classShortNames = [];

foreach ($inventory['classes'] ?? [] as $classEntry) {
    $fqcn = $classEntry['name'] ?? null;
    $file = $classEntry['file'] ?? null;

    if (! $fqcn || ! is_string($fqcn) || ! is_array($classEntry['methods'] ?? null)) {
        continue;
    }

    $methodCatalog[$fqcn] = [
        'file' => $file,
        'methods' => [],
    ];

    $short = substr($fqcn, (int) strrpos('\\' . $fqcn, '\\'));
    $short = ltrim($short, '\\');
    if ($short !== '') {
        $classShortNames[$short] ??= [];
        $classShortNames[$short][] = $fqcn;
    }

    foreach ($classEntry['methods'] as $methodEntry) {
        $name = $methodEntry['name'] ?? null;
        $visibility = $methodEntry['visibility'] ?? 'public';
        if (! $name || ! is_string($name)) {
            continue;
        }
        $methodCatalog[$fqcn]['methods'][$name] = [
            'visibility' => $visibility,
            'references' => [],
        ];
    }
}

if ($methodCatalog === []) {
    throw new RuntimeException('No classes discovered in inventory.');
}

$sourceRoots = [
    $root . '/src',
];

$entryFile = $root . '/fp-digital-marketing-suite.php';
if (is_file($entryFile)) {
    $sourceRoots[] = $entryFile;
}

$phpFiles = [];

$collect = static function (string $path) use (&$phpFiles): void {
    if (is_file($path)) {
        if (pathinfo($path, PATHINFO_EXTENSION) === 'php') {
            $phpFiles[] = $path;
        }
        return;
    }

    if (! is_dir($path)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($iterator as $fileInfo) {
        if (! $fileInfo->isFile()) {
            continue;
        }
        if (pathinfo($fileInfo->getPathname(), PATHINFO_EXTENSION) !== 'php') {
            continue;
        }
        $phpFiles[] = $fileInfo->getPathname();
    }
};

foreach ($sourceRoots as $path) {
    $collect($path);
}

$phpFiles = array_values(array_unique($phpFiles));

/**
 * @param array<string, array{file: string|null, methods: array<string, array{visibility: string, references: list<array{file: string, line: int, type: string, context: string}>}>}> $catalog
 * @param array<string, list<string>> $shortNames
 * @return array<string, array{file: string|null, methods: array<string, array{visibility: string, references: list<array{file: string, line: int, type: string, context: string}>}>}>
 */
function analyzeFiles(array &$catalog, array $shortNames, array $files, string $root): void
{
    foreach ($files as $file) {
        $code = file_get_contents($file);
        if ($code === false) {
            continue;
        }

        $tokens = token_get_all($code);
        $lines = explode("\n", $code);
        $namespace = '';
        $useMap = [];
        $braceDepth = 0;
        $classStack = [];
        $pendingClass = null;
        $callableQueue = [];

        $tokenCount = count($tokens);

        for ($i = 0; $i < $tokenCount; $i++) {
            $token = $tokens[$i];

        if (is_string($token)) {
            if ($token === '[') {
                $classIndex = null;
                $j = $i + 1;
                while ($j < $tokenCount) {
                    $next = $tokens[$j];
                    if (is_array($next) && in_array($next[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                        $j++;
                        continue;
                    }
                    if (is_array($next) && in_array($next[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_STATIC], true)) {
                        $classIndex = $j;
                    } elseif ($next === '\\') {
                        $classIndex = $j;
                    }
                    break;
                }

                if ($classIndex !== null) {
                    $cursor = $classIndex;
                    $doubleColonIndex = null;
                    while ($cursor < $tokenCount) {
                        $candidate = $tokens[$cursor];
                        if (is_array($candidate) && in_array($candidate[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                            $cursor++;
                            continue;
                        }
                        if ($candidate === ',' || $candidate === ']' || $candidate === ')') {
                            break;
                        }
                        if ($candidate === '::' || (is_array($candidate) && $candidate[0] === T_DOUBLE_COLON)) {
                            $doubleColonIndex = $cursor;
                            break;
                        }
                        $cursor++;
                    }

                    if ($doubleColonIndex !== null) {
                        $afterDoubleColon = $doubleColonIndex + 1;
                        while ($afterDoubleColon < $tokenCount) {
                            $candidate = $tokens[$afterDoubleColon];
                            if (is_array($candidate) && in_array($candidate[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                                $afterDoubleColon++;
                                continue;
                            }
                            if (is_array($candidate) && in_array($candidate[0], [T_CLASS, T_STRING], true) && strcasecmp($candidate[1], 'class') === 0) {
                                $methodIndex = null;
                                $commaIndex = $afterDoubleColon + 1;
                                while ($commaIndex < $tokenCount) {
                                    $commaToken = $tokens[$commaIndex];
                                    if (is_array($commaToken) && in_array($commaToken[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                                        $commaIndex++;
                                        continue;
                                    }
                                    if ($commaToken === ',') {
                                        $methodIndex = $commaIndex + 1;
                                    }
                                    break;
                                }

                                if ($methodIndex !== null) {
                                    while ($methodIndex < $tokenCount) {
                                        $methodToken = $tokens[$methodIndex];
                                        if (is_array($methodToken) && in_array($methodToken[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                                            $methodIndex++;
                                            continue;
                                        }
                                        if (is_array($methodToken) && $methodToken[0] === T_CONSTANT_ENCAPSED_STRING) {
                                            $methodName = trim($methodToken[1], "'\"");
                                            if ($methodName !== '' && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $methodName) === 1) {
                                                $className = collectQualifiedName($tokens, $doubleColonIndex - 1);
                                                $currentClass = currentClass($classStack);
                                                $resolvedClass = resolveClassName($className, $namespace, $useMap, $currentClass, $catalog, $shortNames);
                                                if ($resolvedClass !== null && isset($catalog[$resolvedClass]['methods'][$methodName])) {
                                                    $line = $methodToken[2] ?? 0;
                                                    addReference($catalog, $resolvedClass, $methodName, $root, $file, $line, 'array_callable', $lines);
                                                }
                                            }
                                        }
                                        break;
                                    }
                                }
                            }
                            break;
                        }
                    }
                }
            }

            if ($token === '{') {
                if ($pendingClass !== null) {
                    $classStack[] = [
                        'fqcn' => $pendingClass['fqcn'],
                            'depth' => $braceDepth,
                        ];
                        $pendingClass = null;
                    }
                    $braceDepth++;
                } elseif ($token === '}') {
                    $braceDepth--;
                    if (! empty($classStack) && $classStack[count($classStack) - 1]['depth'] >= $braceDepth) {
                        array_pop($classStack);
                    }
                }

                continue;
            }

            [$id, $text] = $token;

            if ($id === T_NAMESPACE) {
                $namespace = '';
                $useMap = [];
                $i++;
                while ($i < $tokenCount) {
                    $next = $tokens[$i];
                    if (is_array($next) && in_array($next[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                        $namespace .= $next[1];
                    } elseif (is_array($next) && $next[0] === T_NS_SEPARATOR) {
                        $namespace .= '\\';
                    } elseif ($next === '{' || $next === ';') {
                        break;
                    }
                    $i++;
                }
                continue;
            }

            if ($id === T_USE && $braceDepth === 0) {
                $useStatement = '';
                $j = $i + 1;
                while ($j < $tokenCount) {
                    $segment = $tokens[$j];
                    if ($segment === ';') {
                        break;
                    }
                    $useStatement .= is_array($segment) ? $segment[1] : $segment;
                    $j++;
                }
                $i = $j;

                $parts = array_filter(array_map('trim', explode(',', $useStatement)));
                foreach ($parts as $part) {
                    $alias = null;
                    $fqcnPart = $part;
                    if (stripos($part, ' as ') !== false) {
                        [$fqcnPart, $alias] = preg_split('/\s+as\s+/i', $part) ?: [$part, null];
                        $fqcnPart = trim((string) $fqcnPart);
                        $alias = trim((string) $alias);
                    }
                    $fqcnPart = ltrim($fqcnPart, '\\');
                    if ($alias === null || $alias === '') {
                        $alias = $fqcnPart;
                        if (str_contains($alias, '\\')) {
                            $alias = substr($alias, strrpos($alias, '\\') + 1);
                        }
                    }
                    if ($alias !== '') {
                        $useMap[$alias] = $fqcnPart;
                    }
                }

                continue;
            }

            if (in_array($id, [T_CLASS, T_TRAIT], true)) {
                $prev = null;
                for ($p = $i - 1; $p >= 0; $p--) {
                    $candidate = $tokens[$p];
                    if (is_array($candidate) && in_array($candidate[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                        continue;
                    }
                    $prev = $candidate;
                    break;
                }
                if (is_array($prev) && $prev[0] === T_NEW) {
                    continue;
                }
                if ($prev === '::') {
                    continue;
                }

                $j = $i + 1;
                $className = null;
                while ($j < $tokenCount) {
                    $next = $tokens[$j];
                    if (is_array($next) && $next[0] === T_STRING) {
                        $className = $next[1];
                        break;
                    }
                    if (! (is_array($next) && in_array($next[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true))) {
                        break;
                    }
                    $j++;
                }
                if ($className !== null) {
                    $fqcn = $namespace !== '' ? $namespace . '\\' . $className : $className;
                    $pendingClass = [
                        'fqcn' => $fqcn,
                    ];
                }
                continue;
            }

            if ($id === T_CLASS_C) {
                $currentClass = currentClass($classStack);
                if ($currentClass !== null) {
                    $callableQueue[] = [
                        'fqcn' => $currentClass,
                        'index' => $i,
                    ];
                }
                continue;
            }

            if ($id === T_DOUBLE_COLON) {
                $className = collectQualifiedName($tokens, $i - 1);
                $currentClass = currentClass($classStack);
                $resolved = resolveClassName($className, $namespace, $useMap, $currentClass, $catalog, $shortNames);
                $j = $i + 1;
                while ($j < $tokenCount && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                    $j++;
                }
                if ($j >= $tokenCount) {
                    continue;
                }
                $next = $tokens[$j];
                if (is_array($next) && $next[0] === T_STRING) {
                    $methodName = $next[1];
                    if (strcasecmp($methodName, 'class') === 0) {
                        if ($resolved !== null) {
                            $callableQueue[] = [
                                'fqcn' => $resolved,
                                'index' => $i,
                            ];
                        }
                        continue;
                    }
                    $k = $j + 1;
                    while ($k < $tokenCount && is_array($tokens[$k]) && $tokens[$k][0] === T_WHITESPACE) {
                        $k++;
                    }
                    if ($k < $tokenCount && $tokens[$k] === '(') {
                        if ($resolved !== null && isset($catalog[$resolved]['methods'][$methodName])) {
                            $line = $next[2] ?? 0;
                            addReference($catalog, $resolved, $methodName, $root, $file, $line, 'static_call', $lines);
                        } elseif ($resolved === null && in_array(strtolower($className), ['self', 'static'], true)) {
                            $currentClass = currentClass($classStack);
                            if ($currentClass !== null && isset($catalog[$currentClass]['methods'][$methodName])) {
                                $line = $next[2] ?? 0;
                                addReference($catalog, $currentClass, $methodName, $root, $file, $line, 'static_call', $lines);
                            }
                        }
                    }
                }
                continue;
            }

            if ($id === T_OBJECT_OPERATOR) {
                $prevToken = $tokens[$i - 1] ?? null;
                $nextToken = $tokens[$i + 1] ?? null;
                if (is_array($prevToken) && $prevToken[0] === T_VARIABLE && $prevToken[1] === '$this'
                    && is_array($nextToken) && $nextToken[0] === T_STRING) {
                    $methodName = $nextToken[1];
                    $k = $i + 2;
                    while ($k < $tokenCount && is_array($tokens[$k]) && $tokens[$k][0] === T_WHITESPACE) {
                        $k++;
                    }
                    if ($k < $tokenCount && $tokens[$k] === '(') {
                        $currentClass = currentClass($classStack);
                        if ($currentClass !== null && isset($catalog[$currentClass]['methods'][$methodName])) {
                            $line = $nextToken[2] ?? 0;
                            addReference($catalog, $currentClass, $methodName, $root, $file, $line, 'instance_call', $lines);
                        }
                    }
                }
                continue;
            }

            if ($id === T_CONSTANT_ENCAPSED_STRING) {
                $value = trim($text, "'\"");
                if ($value === '') {
                    continue;
                }

                $decoded = stripcslashes($value);

                if (str_contains($decoded, '::')) {
                    [$classPart, $methodPart] = explode('::', $decoded, 2);
                    $resolved = resolveStringClass($classPart, $namespace, $useMap, $shortNames, $catalog, currentClass($classStack));
                    if ($resolved !== null && isset($catalog[$resolved]['methods'][$methodPart])) {
                        $line = $token[2] ?? 0;
                        addReference($catalog, $resolved, $methodPart, $root, $file, $line, 'string_callable', $lines);
                    }
                    continue;
                }

                $resolved = resolveStringClass($decoded, $namespace, $useMap, $shortNames, $catalog, currentClass($classStack));
                if ($resolved !== null) {
                    $callableQueue[] = [
                        'fqcn' => $resolved,
                        'index' => $i,
                    ];
                    continue;
                }

                if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $decoded) !== 1) {
                    continue;
                }

                $paired = false;
                for ($q = count($callableQueue) - 1; $q >= 0; $q--) {
                    $entry = $callableQueue[$q];
                    if (! isset($entry['fqcn'])) {
                        continue;
                    }
                    if ($i - $entry['index'] > 40) {
                        array_splice($callableQueue, $q, 1);
                        continue;
                    }
                    $fqcn = $entry['fqcn'];
                    if ($fqcn !== null && isset($catalog[$fqcn]['methods'][$decoded])) {
                        $line = $token[2] ?? 0;
                        addReference($catalog, $fqcn, $decoded, $root, $file, $line, 'callable', $lines);
                        array_splice($callableQueue, $q, 1);
                        $paired = true;
                        break;
                    }
                }

                if (! $paired) {
                    $currentClass = currentClass($classStack);
                    if ($currentClass !== null && isset($catalog[$currentClass]['methods'][$decoded])) {
                        $lookback = 0;
                        $foundThis = false;
                        for ($b = $i - 1; $b >= 0 && $lookback < 10; $b--, $lookback++) {
                            $backToken = $tokens[$b];
                            if (is_array($backToken) && in_array($backToken[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                                continue;
                            }
                            if (is_array($backToken) && $backToken[0] === T_VARIABLE && $backToken[1] === '$this') {
                                $foundThis = true;
                            }
                            if (! is_array($backToken) && in_array($backToken, ['[', ',', '(', '=>'], true)) {
                                continue;
                            }
                            if (! $foundThis) {
                                break;
                            }
                            if ($foundThis) {
                                break;
                            }
                        }
                        if ($foundThis) {
                            $line = $token[2] ?? 0;
                            addReference($catalog, $currentClass, $decoded, $root, $file, $line, 'callable', $lines);
                        }
                    }
                }

                continue;
            }
        }
    }
}

/**
 * @param list<array{fqcn: string, depth: int}> $classStack
 */
function currentClass(array $classStack): ?string
{
    if ($classStack === []) {
        return null;
    }

    return $classStack[count($classStack) - 1]['fqcn'];
}

/**
 * @param array<int, mixed> $tokens
 */
function collectQualifiedName(array $tokens, int $start): string
{
    $name = '';
    for ($i = $start; $i >= 0; $i--) {
        $token = $tokens[$i];
        if (is_array($token)) {
            if (in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                $name = $token[1] . $name;
            } elseif ($token[0] === T_NS_SEPARATOR) {
                $name = '\\' . $name;
            } elseif (in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            } else {
                break;
            }
        } elseif ($token === '\\') {
            $name = '\\' . $name;
        } elseif (trim((string) $token) === '') {
            continue;
        } else {
            break;
        }
    }

    return $name;
}

/**
 * @param array<string, array{file: string|null, methods: array<string, array{visibility: string, references: list<array{file: string, line: int, type: string, context: string}>}>}> $catalog
 * @param array<string, list<string>> $shortNames
 */
function resolveClassName(string $name, string $namespace, array $useMap, ?string $currentClass, array $catalog, array $shortNames): ?string
{
    $trimmed = ltrim($name, '\\');
    $lower = strtolower($trimmed);

    if (in_array($lower, ['self', 'static'], true)) {
        return $currentClass;
    }

    if ($trimmed === '' || $lower === 'parent') {
        return null;
    }

    if (isset($catalog[$trimmed])) {
        return $trimmed;
    }

    if (str_contains($trimmed, '\\')) {
        $first = substr($trimmed, 0, strpos($trimmed, '\\'));
        if (isset($useMap[$first])) {
            $resolved = $useMap[$first] . substr($trimmed, strlen($first));
            if (isset($catalog[$resolved])) {
                return $resolved;
            }
        }
        if ($namespace !== '') {
            $candidate = $namespace . '\\' . $trimmed;
            if (isset($catalog[$candidate])) {
                return $candidate;
            }
        }
        if (isset($catalog[$trimmed])) {
            return $trimmed;
        }
    } else {
        if (isset($useMap[$trimmed])) {
            $aliasResolved = $useMap[$trimmed];
            if (isset($catalog[$aliasResolved])) {
                return $aliasResolved;
            }
        }
        if ($namespace !== '') {
            $candidate = $namespace . '\\' . $trimmed;
            if (isset($catalog[$candidate])) {
                return $candidate;
            }
        }
        if (isset($shortNames[$trimmed]) && count($shortNames[$trimmed]) === 1) {
            return $shortNames[$trimmed][0];
        }
    }

    return null;
}

/**
 * @param array<string, list<string>> $shortNames
 * @param array<string, array{file: string|null, methods: array<string, array{visibility: string, references: list<array{file: string, line: int, type: string, context: string}>}>}> $catalog
 */
function resolveStringClass(string $value, string $namespace, array $useMap, array $shortNames, array $catalog, ?string $currentClass): ?string
{
    if ($value === '__CLASS__' && $currentClass !== null) {
        return $currentClass;
    }

    $value = ltrim($value, '\\');

    if ($value === '') {
        return null;
    }

    if (isset($catalog[$value])) {
        return $value;
    }

    if (str_contains($value, '\\')) {
        $first = substr($value, 0, strpos($value, '\\'));
        if (isset($useMap[$first])) {
            $resolved = $useMap[$first] . substr($value, strlen($first));
            if (isset($catalog[$resolved])) {
                return $resolved;
            }
        }

        if ($namespace !== '') {
            $candidate = $namespace . '\\' . $value;
            if (isset($catalog[$candidate])) {
                return $candidate;
            }
        }

        if (isset($catalog[$value])) {
            return $value;
        }
    } else {
        if (isset($useMap[$value])) {
            $aliasResolved = $useMap[$value];
            if (isset($catalog[$aliasResolved])) {
                return $aliasResolved;
            }
        }

        if ($namespace !== '') {
            $candidate = $namespace . '\\' . $value;
            if (isset($catalog[$candidate])) {
                return $candidate;
            }
        }

        if ($currentClass !== null) {
            $lastSep = strrpos($currentClass, '\\');
            if ($lastSep !== false) {
                $currentNamespace = substr($currentClass, 0, $lastSep);
                if ($currentNamespace !== '') {
                    $candidate = $currentNamespace . '\\' . $value;
                    if (isset($catalog[$candidate])) {
                        return $candidate;
                    }
                }
            }
        }

        if (isset($shortNames[$value]) && count($shortNames[$value]) === 1) {
            return $shortNames[$value][0];
        }
    }

    return null;
}

/**
 * @param array<string, array{file: string|null, methods: array<string, array{visibility: string, references: list<array{file: string, line: int, type: string, context: string}>}>}> $catalog
 * @param list<string> $lines
 */
function addReference(array &$catalog, string $class, string $method, string $root, string $file, int $line, string $type, array $lines): void
{
    $relative = str_starts_with($file, $root . '/') ? substr($file, strlen($root) + 1) : $file;
    $line = max($line, 1);
    $context = trim($lines[$line - 1] ?? '');

    foreach ($catalog[$class]['methods'][$method]['references'] as $existing) {
        if ($existing['file'] === $relative && $existing['line'] === $line) {
            return;
        }
    }

    $catalog[$class]['methods'][$method]['references'][] = [
        'file' => $relative,
        'line' => $line,
        'type' => $type,
        'context' => $context,
    ];
}

analyzeFiles($methodCatalog, $classShortNames, $phpFiles, $root);

$output = [
    'methods' => [],
];

$totalMethods = 0;
$referenced = 0;
$unreferenced = [];

foreach ($methodCatalog as $fqcn => $details) {
    foreach ($details['methods'] as $methodName => $meta) {
        $key = $fqcn . '::' . $methodName;
        $references = $meta['references'];
        $referenceCount = count($references);
        $output['methods'][$key] = [
            'class' => $fqcn,
            'method' => $methodName,
            'visibility' => $meta['visibility'],
            'declared_in' => $details['file'],
            'reference_count' => $referenceCount,
            'references' => $references,
        ];
        $totalMethods++;
        if ($referenceCount > 0) {
            $referenced++;
        } else {
            $unreferenced[] = $key;
        }
    }
}

sort($unreferenced);

$output['summary'] = [
    'files_scanned' => array_map(static function ($file) use ($root) {
        return str_starts_with($file, $root . '/') ? substr($file, strlen($root) + 1) : $file;
    }, $phpFiles),
    'total_methods' => $totalMethods,
    'methods_with_references' => $referenced,
    'methods_without_references' => $totalMethods - $referenced,
    'top_unreferenced' => array_slice($unreferenced, 0, 10),
];

file_put_contents(__DIR__ . '/linkage.json', json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo json_encode(
    [
        'total_methods' => $totalMethods,
        'methods_with_references' => $referenced,
        'methods_without_references' => $totalMethods - $referenced,
        'top_unreferenced' => array_slice($unreferenced, 0, 10),
    ],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
) . PHP_EOL;
