<?php

require_once dirname(dirname(__FILE__)) . '/vendor/autoload.php';

use Jaeger\Config;

unset($_SERVER['argv']);


//init server span start
$config = Config::getInstance();

$tracer = $config->initTracer('example', '10.254.254.254:6831');

$top = $tracer->buildSpan('level top')
              ->withTag('name', 'level top')
              ->startActive();

sleep(2);

$second = $tracer->buildSpan('level second')
                 ->asChildOf($top->getSpan())
                 ->withTag('name', 'level second')
                 ->start();

sleep(1);
$third = $tracer->buildSpan('level third')
                ->addReference(\OpenTracing\Reference::CHILD_OF, $second)
                ->withTag('name', 'level third')
                ->start();

$fourth = $tracer->buildSpan('level fourth')
                 ->addReference(\OpenTracing\Reference::FOLLOWS_FROM, $second)
                 ->withTag('name', 'level fourth')
                 ->start();

$num = 0;
for ($i = 0; $i < 30; $i++) {
    $num += 1;
}
$third->setTag("num", $num);
sleep(1);
$third->finish();

$num = 0;
for ($i = 0; $i < 10; $i++) {
    $num += 2;
}
$third->setTag("num", $num);

sleep(1);
$second->finish();


$top->close();

//trace flush
$config->flush();

echo "success\r\n";
