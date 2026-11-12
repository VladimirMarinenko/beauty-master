<?php

declare(strict_types=1);

// Только для PHPUnit: ключ тестов не связан с боевым и не сохраняется.
// Задаём все три источника переменных, которые может читать Laravel.
$testEnvironment = [
    'APP_ENV' => 'testing',
    'APP_KEY' => 'base64:' . base64_encode(random_bytes(32)),
    'APP_PREVIOUS_KEYS' => '',
    // Уникальный несуществующий путь исключает чтение боевого кэша конфигурации.
    // Этот файл здесь не создаётся.
    'APP_CONFIG_CACHE' => sys_get_temp_dir()
        . '/beauty-master-tests-config-' . bin2hex(random_bytes(16)) . '.php',
];

foreach ($testEnvironment as $name => $value) {
    putenv($name . '=' . $value);
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
}

unset($testEnvironment, $name, $value);

require dirname(__DIR__) . '/vendor/autoload.php';
