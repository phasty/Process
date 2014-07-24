#!/usr/bin/php
<?php
if (file_exists($config = dirname(__FILE__) . "/config.php")) {
    require $config;
}
if ($argc < 3) {
    echo("Wrong arguments count");
    exit(1);
}
$calleeEntity = unserialize(base64_decode($argv[1]));
$calleeMethod = unserialize(base64_decode($argv[2]));
$arguments    = $argc > 3 ? unserialize(base64_decode($argv[3])) : [];

try {
    if (is_string($calleeEntity)) {
        $calleeEntity = new $calleeEntity();
    }
    $child = new \Phasty\Process\Child($calleeEntity);
    call_user_func_array([ $calleeEntity, $calleeMethod ], $arguments);
} catch (\Exception $e) {
    \Phasty\Log\File::error($e->getMessage());
}

