<?php
$lines = file("test_vals.txt", FILE_IGNORE_NEW_LINES);
foreach ($lines as $v) {
    if (trim($v) === "") continue;
    $s = trim($v);
    $s = preg_replace("/^[\\x27\\x22]+/", "", $s);
    $s = str_replace(array("\xC2\xA0", "\xA0"), " ", $s);
    $s = trim($s);
    if (preg_match("/^PPO\\s*(\\d+)\\s*[-\\x{2010}-\\x{2015}-]/iu", $s, $m)) {
        echo $v . " => " . $m[1] . PHP_EOL;
    } else {
        echo $v . " => NULL" . PHP_EOL;
    }
}
