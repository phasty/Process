#!/usr/bin/php
<?php
$dir = dirname(dirname(__FILE__));
if (is_file("$dir/vendor/autoload.php")) {
    $autoloadFile = "$dir/vendor/autoload.php";
} else {
    $autoloadFile = dirname(dirname($dir)) . "/autoload.php";
}
include_once $autoloadFile;

if ($argc < 4) {
    echo("Wrong argument count");
    exit(1);
}
$requiredFiles = unserialize(base64_decode($argv[ 1 ]));
foreach($requiredFiles as $requiredFile) {
    require $requiredFile;
}
$calleeEntity  = unserialize(base64_decode($argv[ 2 ]));
$calleeMethod  = unserialize(base64_decode($argv[ 3 ]));
$arguments     = $argc > 4 ? unserialize(base64_decode($argv[ 4 ])) : [];
if (is_string($calleeEntity)) {
    $calleeEntity = new $calleeEntity();
}
$child = new \Phasty\Process\Child($calleeEntity);
try {
    call_user_func_array([ $calleeEntity, $calleeMethod ], $arguments);
} catch (\Exception $e) {
    $child->trigger("error", $e->getMessage());
    \Phasty\Log\File::error($e->getMessage());
}

