<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

spl_autoload_register(static function (string $class): void {
    if (strpos($class, 'FP\\DMS\\') !== 0) {
        return;
    }

    $relative = substr($class, strlen('FP\\DMS\\'));
    $relative = str_replace('\\', DIRECTORY_SEPARATOR, $relative);
    $path = __DIR__ . '/../src/' . $relative . '.php';

    if (file_exists($path)) {
        require_once $path;
    }
});

if (! function_exists('apply_filters')) {
    function apply_filters(string $hook_name, $value)
    {
        return $value;
    }
}