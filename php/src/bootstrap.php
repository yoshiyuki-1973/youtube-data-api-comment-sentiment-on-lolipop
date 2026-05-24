<?php

declare(strict_types=1);

$srcDir    = __DIR__;
$configFile = dirname($srcDir) . '/config.php';

require_once $srcDir . '/Exceptions.php';

// クラスオートロード
spl_autoload_register(function (string $class) use ($srcDir): void {
    $file = $srcDir . '/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// 設定読み込み
Config::load($configFile);
