<?php

declare(strict_types=1);

namespace FP\DMS\Audit;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

$root = dirname(__DIR__, 2);
$srcDir = $root . '/src';

$inventory = [
    'classes' => [],
    'traits' => [],
    'interfaces' => [],
    'rest_routes' => [],
    'admin_pages' => [],
    'cli_commands' => [],
];

$structures = [];

$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($srcDir));

foreach ($rii as $file) {
    if (! $file->isFile()) {
        continue;
    }

    $path = $file->getPathname();
    if (pathinfo($path, PATHINFO_EXTENSION) !== 'php') {
        continue;
    }

    $code = file_get_contents($path);
    if ($code === false) {
        continue;
    }

    $relativePath = str_replace($root . '/', '', $path);
    $tokens = token_get_all($code);

    $namespace = '';
    $pendingVisibility = null;
    $currentIndex = null;

    for ($i = 0, $c = count($tokens); $i < $c; $i++) {
        $token = $tokens[$i];

        if (is_array($token)) {
            [$id, $text] = $token;

            if ($id === T_NAMESPACE) {
                $namespace = '';
                $i++;
                while ($i < $c) {
                    $next = $tokens[$i];
                    if (is_array($next) && in_array($next[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                        $namespace .= $next[1];
                    } elseif ($next === '{' || $next === ';') {
                        break;
                    } elseif (is_array($next) && $next[0] === T_NS_SEPARATOR) {
                        $namespace .= '\\';
                    }
                    $i++;
                }
            } elseif (in_array($id, [T_CLASS, T_INTERFACE, T_TRAIT], true)) {
                $prevToken = null;
                for ($j = $i - 1; $j >= 0; $j--) {
                    $prev = $tokens[$j];
                    if (is_array($prev) && in_array($prev[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                        continue;
                    }
                    $prevToken = $prev;
                    break;
                }

                if ($prevToken === '::' || (is_array($prevToken) && $prevToken[0] === T_DOUBLE_COLON)) {
                    continue;
                }
                if (is_array($prevToken) && $prevToken[0] === T_NEW) {
                    continue;
                }

                $i++;
                while ($i < $c) {
                    $nameToken = $tokens[$i];
                    if (is_array($nameToken) && $nameToken[0] === T_STRING) {
                        $shortName = $nameToken[1];
                        $fqcn = ltrim($namespace . '\\' . $shortName, '\\');
                        $structure = [
                            'type' => $id === T_CLASS ? 'class' : ($id === T_INTERFACE ? 'interface' : 'trait'),
                            'name' => $fqcn,
                            'short_name' => $shortName,
                            'file' => $relativePath,
                            'methods' => [],
                        ];
                        $structures[] = $structure;
                        $currentIndex = array_key_last($structures);
                        if ($structure['type'] === 'class') {
                            $inventory['classes'][] = &$structures[$currentIndex];
                        } elseif ($structure['type'] === 'interface') {
                            $inventory['interfaces'][] = &$structures[$currentIndex];
                        } else {
                            $inventory['traits'][] = &$structures[$currentIndex];
                        }
                        break;
                    }
                    $i++;
                }
            } elseif (in_array($id, [T_PUBLIC, T_PROTECTED, T_PRIVATE], true)) {
                $pendingVisibility = $id;
            } elseif ($id === T_FUNCTION && $currentIndex !== null) {
                $j = $i + 1;
                $isClosure = false;
                while ($j < $c) {
                    $next = $tokens[$j];
                    if (is_array($next) && in_array($next[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                        $j++;
                        continue;
                    }
                    if ($next === '&') {
                        $j++;
                        continue;
                    }
                    if ($next === '(') {
                        $isClosure = true;
                    }
                    break;
                }

                if ($isClosure) {
                    $pendingVisibility = null;
                    continue;
                }

                while ($j < $c) {
                    $nameToken = $tokens[$j];
                    if (is_array($nameToken) && $nameToken[0] === T_STRING) {
                        $visibility = $pendingVisibility ?? T_PUBLIC;
                        if ($visibility !== T_PRIVATE) {
                            $structures[$currentIndex]['methods'][] = [
                                'name' => $nameToken[1],
                                'visibility' => $visibility === T_PROTECTED ? 'protected' : 'public',
                            ];
                        }
                        $pendingVisibility = null;
                        break;
                    }
                    if (! (is_array($nameToken) && in_array($nameToken[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true))) {
                        break;
                    }
                    $j++;
                }
            } elseif ($id === T_VARIABLE) {
                $pendingVisibility = null;
            }
        } elseif ($token === ';' || $token === '{' || $token === ',') {
            $pendingVisibility = null;
        }
    }
}

foreach (['classes', 'interfaces', 'traits'] as $key) {
    foreach ($inventory[$key] as &$entry) {
        $entry['methods'] = array_values($entry['methods']);
    }
}
unset($entry);

$routesFile = $srcDir . '/Http/Routes.php';
if (file_exists($routesFile)) {
    $routesCode = file_get_contents($routesFile) ?: '';
    $offset = 0;
    while (($pos = strpos($routesCode, 'register_rest_route', $offset)) !== false) {
        $openParen = strpos($routesCode, '(', $pos);
        if ($openParen === false) {
            break;
        }
        $depth = 1;
        $cursor = $openParen + 1;
        $length = strlen($routesCode);
        while ($cursor < $length && $depth > 0) {
            $char = $routesCode[$cursor];
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
            }
            $cursor++;
        }
        $argsString = substr($routesCode, $openParen + 1, $cursor - $openParen - 2);
        $offset = $cursor;

        $namespace = null;
        $route = null;
        $methods = null;
        $callback = null;

        if (preg_match_all("/'([^']+)'/", $argsString, $matches)) {
            if (isset($matches[1][0])) {
                $namespace = $matches[1][0];
            }
            if (isset($matches[1][1])) {
                $route = $matches[1][1];
            }
        }

        if (preg_match("/'methods'\s*=>\s*'([^']+)'/", $argsString, $m)) {
            $methods = $m[1];
        }

        if (preg_match('/\bcallback\b\s*=>\s*\[(.*?)\]/s', $argsString, $m)) {
            $callbackParts = array_values(array_filter(array_map(static function ($piece) {
                $piece = trim($piece);
                $piece = trim($piece, "'\" ");
                return $piece !== '' ? $piece : null;
            }, explode(',', $m[1]))));
            $callback = $callbackParts;
        }

        $inventory['rest_routes'][] = array_filter([
            'namespace' => $namespace,
            'route' => $route,
            'methods' => $methods,
            'callback' => $callback,
        ]);
    }
}

foreach ($structures as $structure) {
    if (! isset($structure['file'])) {
        continue;
    }
    if (str_starts_with($structure['file'], 'src/Admin/Pages/')) {
        $inventory['admin_pages'][] = [
            'class' => $structure['name'],
            'file' => $structure['file'],
            'methods' => $structure['methods'],
        ];
    }
}

$cliPattern = "/WP_CLI::add_command\\s*\\(\\s*'([^']+)'/";
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($srcDir));
foreach ($rii as $file) {
    if (! $file->isFile() || pathinfo($file->getFilename(), PATHINFO_EXTENSION) !== 'php') {
        continue;
    }
    $code = file_get_contents($file->getPathname());
    if ($code === false) {
        continue;
    }
    if (preg_match_all($cliPattern, $code, $matches)) {
        foreach ($matches[1] as $command) {
            $inventory['cli_commands'][] = [
                'command' => $command,
                'file' => str_replace($root . '/', '', $file->getPathname()),
            ];
        }
    }
}

$inventory['summary'] = [
    'classes' => count(array_filter($structures, static fn($s) => $s['type'] === 'class')),
    'interfaces' => count(array_filter($structures, static fn($s) => $s['type'] === 'interface')),
    'traits' => count(array_filter($structures, static fn($s) => $s['type'] === 'trait')),
    'methods' => array_sum(array_map(static fn($s) => count($s['methods']), $structures)),
    'rest_routes' => count($inventory['rest_routes']),
    'admin_pages' => count($inventory['admin_pages']),
    'cli_commands' => count($inventory['cli_commands']),
];

file_put_contents(__DIR__ . '/inventory.json', json_encode($inventory, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo json_encode($inventory['summary'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
