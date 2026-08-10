<?php

declare(strict_types=1);

$testConfigCache = dirname(__DIR__).'/bootstrap/cache/config-testing.php';

if (is_file($testConfigCache)) {
    throw new RuntimeException(
        "SEGURIDAD: existe un cache de configuracion de tests no permitido: {$testConfigCache}",
    );
}

$testingEnvironment = [
    'APP_ENV' => 'testing',
    'APP_CONFIG_CACHE' => $testConfigCache,
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'DB_URL' => '',
];

foreach ($testingEnvironment as $name => $value) {
    putenv("{$name}={$value}");
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
}
