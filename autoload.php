<?php

declare(strict_types=1);

/**
 * 极简自动加载：把 `Tplscan\Foo` 映射到 `src/Foo.php`。
 * 只用 PHP 标准库；不依赖 composer。
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'Tplscan\\';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/src/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require $file;
    }
});
