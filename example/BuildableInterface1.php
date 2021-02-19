<?php

require_once dirname(dirname(__FILE__)) . '/vendor/autoload.php';

use Jaeger\Config;

unset($_SERVER['argv']);


//init server span start
$config = Config::getInstance();

$tracer = $config->initTracer('example', '10.254.254.254:6831');

$top    = $tracer->buildSpan('level top')
                 ->withTag('name', 'level top')
                 ->startActive();

$second = $tracer->buildSpan('level second')
                 ->withTag('name', 'level second')
                 ->startActive();

$third = $tracer->buildSpan('level third')
                ->withTag('name', 'level third')
                ->startActive();

$num = 0;
for ($i = 0; $i < 10; $i++) {
    $num += 1;
}

$third->getSpan()->setTag("num", $num);
sleep(1);
$third->close();

$num = 0;
for ($i = 0; $i < 10; $i++) {
    $num += 2;
}
$third->getSpan()->setTag("num", $num);

sleep(1);
$second->close();


$top->close();

//trace flush
$config->flush();

echo "success\r\n";
