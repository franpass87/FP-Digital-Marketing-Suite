<?php
declare(strict_types=1);

const PLUGIN_FILE = __DIR__ . '/../fp-digital-marketing-suite.php';

function usage(): void
{
    fwrite(STDERR, "Usage: php tools/bump-version.php [--major|--minor|--patch] [--set=X.Y.Z]\n");
    exit(1);
}

$options = getopt('', ['major', 'minor', 'patch', 'set:', 'help']);
if ($options === false || isset($options['help'])) {
    usage();
}

$setVersion = $options['set'] ?? null;
$flags = array_intersect_key($options, array_flip(['major', 'minor', 'patch']));

if ($setVersion !== null && !empty($flags)) {
    fwrite(STDERR, "--set is mutually exclusive with --major/--minor/--patch\n");
    exit(1);
}

if ($setVersion !== null) {
    $setVersion = trim((string) $setVersion);
    if ($setVersion === '') {
        fwrite(STDERR, "--set requires a non-empty version\n");
        exit(1);
    }
    if (!preg_match('/^\d+\.\d+\.\d+$/', $setVersion)) {
        fwrite(STDERR, "Version must follow semantic versioning (X.Y.Z)\n");
        exit(1);
    }
    $newVersion = $setVersion;
} else {
    $bump = 'patch';
    if (!empty($flags)) {
        $keys = array_keys($flags);
        if (count($keys) > 1) {
            fwrite(STDERR, "Only one of --major/--minor/--patch can be specified\n");
            exit(1);
        }
        $bump = $keys[0];
    }

    $contents = file_get_contents(PLUGIN_FILE);
    if ($contents === false) {
        fwrite(STDERR, "Unable to read plugin file: " . PLUGIN_FILE . "\n");
        exit(1);
    }

    if (!preg_match('/^\s*\*\s*Version:\s*(.+)$/mi', $contents, $matches)) {
        fwrite(STDERR, "Version header not found in plugin file\n");
        exit(1);
    }

    $currentVersion = trim($matches[1]);
    if (!preg_match('/^(\d+)\.(\d+)\.(\d+)$/', $currentVersion, $parts)) {
        fwrite(STDERR, "Current version is not in X.Y.Z format\n");
        exit(1);
    }

    [$major, $minor, $patch] = array_map('intval', array_slice($parts, 1));

    switch ($bump) {
        case 'major':
            $major++;
            $minor = 0;
            $patch = 0;
            break;
        case 'minor':
            $minor++;
            $patch = 0;
            break;
        case 'patch':
        default:
            $patch++;
            break;
    }

    $newVersion = sprintf('%d.%d.%d', $major, $minor, $patch);
    $contents = null;
}

$contents = $contents ?? file_get_contents(PLUGIN_FILE);
if ($contents === false) {
    fwrite(STDERR, "Unable to read plugin file: " . PLUGIN_FILE . "\n");
    exit(1);
}

$updated = preg_replace_callback(
    '/^(\s*\*\s*Version:\s*)(.+)$/mi',
    static function (array $matches) use ($newVersion): string {
        return $matches[1] . $newVersion;
    },
    $contents,
    1,
    $count
);

if ($updated === null || $count !== 1) {
    fwrite(STDERR, "Failed to update Version header\n");
    exit(1);
}

$updated = preg_replace_callback(
    '/(const\s+FP_DMS_VERSION\s*=\s*[\'\"])([^\'\"]+)([\'\";])/',
    static function (array $matches) use ($newVersion): string {
        return $matches[1] . $newVersion . $matches[3];
    },
    $updated,
    1,
    $constCount
);

if ($updated === null) {
    fwrite(STDERR, "Failed to update version constant\n");
    exit(1);
}

if (file_put_contents(PLUGIN_FILE, $updated) === false) {
    fwrite(STDERR, "Unable to write plugin file: " . PLUGIN_FILE . "\n");
    exit(1);
}

echo $newVersion . PHP_EOL;
