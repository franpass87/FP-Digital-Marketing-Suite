<?php
/**
 * Build script for admin CSS assets.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$srcDir = $root . '/assets/src/admin';
$distDir = $root . '/assets/dist/admin';

if (! is_dir($srcDir)) {
    fwrite(STDERR, "Source directory {$srcDir} not found." . PHP_EOL);
    exit(1);
}

if (! is_dir($distDir) && ! mkdir($distDir, 0775, true) && ! is_dir($distDir)) {
    fwrite(STDERR, "Unable to create dist directory {$distDir}." . PHP_EOL);
    exit(1);
}

$files = ['tokens.css', 'base.css'];

foreach ($files as $file) {
    $source = $srcDir . '/' . $file;
    $destination = $distDir . '/' . $file;

    if (! file_exists($source)) {
        fwrite(STDERR, "Missing source file: {$source}" . PHP_EOL);
        exit(1);
    }

    $contents = file_get_contents($source);

    if ($contents === false) {
        fwrite(STDERR, "Unable to read {$source}." . PHP_EOL);
        exit(1);
    }

    $banner = '/* Generated via scripts/build-admin-styles.php. Do not edit in dist. */' . PHP_EOL;

    $result = file_put_contents($destination, $banner . $contents);

    if ($result === false) {
        fwrite(STDERR, "Unable to write {$destination}." . PHP_EOL);
        exit(1);
    }

    echo sprintf("✅ Built %s\n", $destination);
}

