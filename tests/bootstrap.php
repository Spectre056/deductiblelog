<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// Nextcloud is not installed here; the OCP package provides interfaces and the
// AppFramework base classes, which is all the unit tests need.
spl_autoload_register(function (string $class): void {
    $prefix = 'OCA\\DeductibleLog\\';
    if (str_starts_with($class, $prefix)) {
        $file = __DIR__ . '/../lib/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    }
});
