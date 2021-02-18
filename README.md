[![Build Status](https://travis-ci.com/jukylin/jaeger-php.svg?branch=master)](https://travis-ci.com/jukylin/jaeger-php)
[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%205.6-8892BF.svg)](https://php.net/)
[![License](https://img.shields.io/github/license/jukylin/jaeger-php.svg)](https://github.com/jukylin/jaeger-php/blob/master/LICENSE)
[![Coverage Status](https://coveralls.io/repos/github/jukylin/jaeger-php/badge.svg?branch=master)](https://coveralls.io/github/jukylin/jaeger-php?branch=master)

# jaeger-php

## Install

Install via composer.

```
composer config minimum-stability dev
composer require vantt/opentracing-jaeger-php
```

## Init Jaeger-php

```php
$config = Config::getInstance();
$tracer = $config->initTracer('example', '0.0.0.0:6831');
```

## 128bit

```php
$config->gen128bit();
```

## Extracting span context from request header
```php
$rootContext = $tracer->extract(Formats\TEXT_MAP, getallheaders());
```

## Injecting span context into request header

```php
use OpenTracing\Formats;

$arrHeader = [];
$tracer->inject($span->getContext(), Formats\TEXT_MAP, $arrHeader);
$httpClient->request($url, $arrHeader);

```

## Usage
### Using `SpanBuilder`

This library extends the original api to add a new method `buildSpan(operationName):SpanBuilderInterface`.
When consuming this library one really only need to worry about the `buildSpan(operationName)` on the `$tracer` instance: `Tracer::buildSpan(operationName)`

With SpanBuilder, we can leverage the power of editor to do auto code completion for us with following APIs:

- `asChildOf($parentContext)` is an object of type `OpenTracing\SpanContext` or `OpenTracing\Span`.
- `withStartTimestamp(time())` is a float, int or `\DateTime` representing a timestamp with arbitrary precision.
- `withTag(key,val)` is an array with string keys and scalar values that represent OpenTracing tags.
- `ignoreActiveSpan(bool)`
- `finishSpanOnClose()` is a boolean that determines whether a span should be finished or not when the scope is closed.
- `addReference()`

Here are code snippets demonstrating some important use cases:

```php
$rootContext = $tracer->extract(Formats\TEXT_MAP, getallheaders());
$span = $tracer->buildSpan('my_span')
               ->asChildOf($rootContext)
               ->withTag('foo', 'bar')               
               ->withStartTimestamp(time())
               ->start();

$scope = $tracer->buildSpan('my_span')
                ->asChildOf($rootContext)
                ->withTag('foo', 'bar')               
                ->withStartTimestamp(time())
                ->startActive();
```

### Creating a Span given an existing Request

To start a new `Span`, you can use the `startSpan` method.

```php
use OpenTracing\Formats;
use OpenTracing\GlobalTracer;

...

// extract the span context
$spanContext = GlobalTracer::get()->extract(
    Formats\TEXT_MAP,
    getallheaders()
);

function doSomething() {
    ...

    // start a new span called 'my_span' and make it a child of the $spanContext
    $span = GlobalTracer::get()->buildSpan('my_operation_span_name')
                               ->start();
    ...
    
    // add some logs to the span
    $span->log([
        'event' => 'soft error',
        'type' => 'cache timeout',
        'waiter.millis' => 1500,
    ]);

    // finish the the span
    $span->finish();
}
```

### Starting a new trace by creating a "root span"

It's always possible to create a "root" `Span` with no parent or other causal reference.

```php
$span = $tracer->buildSpan('my_first_span')->start();
...
$span->finish();
```

### Active Spans and Scope Manager

For most use cases, it is recommended that you use the `Tracer::startActiveSpan` function for
creating new spans.

An example of a linear, two level deep span tree using active spans looks like
this in PHP code:
```php
// At dispatcher level
$scope = $tracer->buildSpan('request')->start();
...
$scope->close();
```
```php
// At controller level
$scope = $tracer->buildSpan('controller')->startActive();
...
$scope->close();
```

```php
// At RPC calls level
$scope = $tracer->buildSpan('http')->startActive();
file_get_contents('http://php.net');
$scope->close();
```

When using the `Tracer::startActiveSpan` function the underlying tracer uses an
abstraction called scope manager to keep track of the currently active span.

Starting an active span will always use the currently active span as a parent.
If no parent is available, then the newly created span is considered to be the
root span of the trace.

Unless you are using asynchronous code that tracks multiple spans at the same
time, such as when using cURL Multi Exec or MySQLi Polling it is recommended that you
use `Tracer::startActiveSpan` everywhere in your application.

The currently active span gets automatically finished when you call `$scope->close()`
as you can see in the previous examples.

If you don't want a span to automatically close when `$scope->close()` is called
then you must specify `'finish_span_on_close'=> false,` in the `$options`
argument of `startActiveSpan`.

#### Creating a child span assigning parent manually

```php
$tracer = GlobalTracer::get();
$parent = $tracer->startSpan('parent');

$child = $tracer->buildSpan('child_operation')
                ->asChildOf($parent)
                ->start();
...

$child->finish();

...

$parent->finish();
```

#### Creating a child span using automatic active span management

Every new span will take the active span as parent and it will take its spot.

```php
$parent = GlobalTracer::get()->buildSpan('parent')->startActive();

...

/*
 * Since the parent span has been created by using startActiveSpan we don't need
 * to pass a reference for this child span
 */
$child = GlobalTracer::get()->buildSpan('my_second_span')->startActive();

...

$child->close();

...

$parent->close();
```

### Serializing to the wire

```php
use GuzzleHttp\Client;
use OpenTracing\Formats;

...

$tracer = GlobalTracer::get();

$spanContext = $tracer->extract(
    Formats\HTTP_HEADERS,
    getallheaders()
);

try {
    $span = $tracer->buildSpan('my_span')->asChildOf($spanContext)->start();

    $client = new Client;

    $headers = [];

    $tracer->inject(
        $span->getContext(),
        Formats\HTTP_HEADERS,
        $headers
    );

    $request = new \GuzzleHttp\Psr7\Request('GET', 'http://myservice', $headers);
    $client->send($request);
    ...

} catch (\Exception $e) {
    ...
}
...
```

### Deserializing from the wire

When using http header for context propagation you can use either the `Request` or the `$_SERVER`
variable:

```php
use OpenTracing\GlobalTracer;
use OpenTracing\Formats;

$tracer = GlobalTracer::get();
$spanContext = $tracer->extract(Formats\TEXT_MAP, getallheaders());
$tracer->buildSpan('my_span')->asChildOf($spanContext)->startActive();

```

## Start Span

```php
$serverSpan = $tracer->startSpan('example HTTP', ['child_of' => $spanContext]);
```

## Distributed context propagation
```php
$serverSpan->addBaggageItem("version", "2.0.0");
```

## Inject into Superglobals

```php
$clientTrace->inject($clientSpan1->spanContext, Formats\TEXT_MAP, $_SERVER);
```

## Tags and Log

```php
// tags are searchable in Jaeger UI
$span->setTag('http.status', '200');

// log record
$span->log(['error' => 'HTTP request timeout']);
```

## Close Tracer

```php
$config->setDisabled(true);
```

## Zipkin B3 Propagation

*no support for* `Distributed context propagation`

```php
$config::$propagator = \Jaeger\Constants\PROPAGATOR_ZIPKIN;
```

## Finish span and flush Tracer

```php
$span->finish();
$config->flush();
```

## Features

- Transports
    - via Thrift over UDP

- Sampling
    - ConstSampler
    - ProbabilisticSampler

## Reference

[OpenTracing](https://opentracing.io/)

[Jaeger](https://uber.github.io/jaeger/)
