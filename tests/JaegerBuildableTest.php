<?php

namespace tests;

use Jaeger\Reporter\RemoteReporter;
use Jaeger\Sampler\ConstSampler;
use Jaeger\ScopeManager;
use Jaeger\Span;
use Jaeger\Transport\TransportUdp;
use Jaeger\Jaeger;
use OpenTracing\Reference;
use OpenTracing\StartSpanOptions;
use PHPUnit\Framework\TestCase;


/**
 * @covers StartSpanOptions
 */
final class JaegerBuildableTest extends TestCase {
    const OPERATION_NAME = 'test_operation';

    public function getJaeger() {

        $tranSport = new TransportUdp();
        $reporter = new RemoteReporter($tranSport);
        $sampler = new ConstSampler();
        $scopeManager = new ScopeManager();

        return new Jaeger('jaeger', $reporter, $sampler, $scopeManager);
    }


    public function testNew() {
        $Jaeger = $this->getJaeger();
        $this->assertInstanceOf(Jaeger::class, $Jaeger);
    }

    public function testGetEnvTags(){

        $_SERVER['JAEGER_TAGS'] = 'a=b,c=d';
        $Jaeger = $this->getJaeger();
        $tags = $Jaeger->getEnvTags();

        $this->assertTrue(count($tags) > 0);
    }

    public function testStartSpan() {
        $Jaeger = $this->getJaeger();
        $span   = $Jaeger->buildSpan('test')->start();

        $this->assertNotEmpty($span->startTime);
        $this->assertNotEmpty($Jaeger->getSpans());
    }

    public function testStartSpanWithFollowsFromTypeRef() {
        $jaeger    = $this->getJaeger();
        $rootSpan  = $jaeger->buildSpan('root-a')->start();
        $childSpan = $jaeger->buildSpan('span-a')
                            ->addReference(Reference::FOLLOWS_FROM, $rootSpan->getContext())
                            ->start();


        $this->assertSame($childSpan->spanContext->traceIdLow, $rootSpan->spanContext->traceIdLow);
        $this->assertEquals(current($childSpan->references)->getSpanContext(), $rootSpan->spanContext);

        $otherRootSpan = $jaeger->buildSpan('root-a')->start();
        $childSpan     = $jaeger->buildSpan('span-b')
                                ->addReference(Reference::FOLLOWS_FROM, $rootSpan->getContext())
                                ->addReference(Reference::FOLLOWS_FROM, $otherRootSpan->getContext())
                                ->start();

        $this->assertSame($childSpan->spanContext->traceIdLow, $otherRootSpan->spanContext->traceIdLow);
    }


    public function testStartSpanWithChildOfTypeRef() {
        $jaeger        = $this->getJaeger();
        $rootSpan      = $jaeger->buildSpan('root-a')->start();
        $otherRootSpan = $jaeger->buildSpan('root-b')->start();

        $childSpan = $jaeger->buildSpan('span-a')
                            ->addReference(Reference::CHILD_OF, $rootSpan->getContext())
                            ->addReference(Reference::CHILD_OF, $otherRootSpan->getContext())
                            ->start();

        $this->assertSame($childSpan->spanContext->traceIdLow, $rootSpan->spanContext->traceIdLow);
    }

    public function testStartSpanWithCustomStartTime() {
        $time = time();

        $jaeger = $this->getJaeger();
        $span   = $jaeger->buildSpan('test')
                         ->withStartTimestamp($time)
                         ->start();

        $this->assertEquals($time*1000000, $span->startTime);
    }

    public function testStartSpanWithAllRefType() {
        $jaeger        = $this->getJaeger();
        $rootSpan      = $jaeger->buildSpan('root-a')->start();
        $otherRootSpan = $jaeger->buildSpan('root-b')->start();

        $childSpan = $jaeger->buildSpan('span-a')
                            ->addReference(Reference::FOLLOWS_FROM, $rootSpan->getContext())
                            ->addReference(Reference::CHILD_OF, $otherRootSpan->getContext())
                            ->start();

        $this->assertSame($childSpan->spanContext->traceIdLow, $otherRootSpan->spanContext->traceIdLow);
    }

    public function testReportSpan(){
        $Jaeger = $this->getJaeger();
        $Jaeger->buildSpan('test')->start();
        $Jaeger->reportSpan();
        $this->assertEmpty($Jaeger->getSpans());
    }

    public function testStartActiveSpan() {
        $Jaeger = $this->getJaeger();
        $Jaeger->buildSpan('test')->startActive();

        $this->assertNotEmpty($Jaeger->getSpans());
    }

    public function testGetActiveSpan() {
        $Jaeger     = $this->getJaeger();
        $scope      = $Jaeger->buildSpan('test')->startActive();
        $activeSpan = $Jaeger->getActiveSpan();

        $this->assertInstanceOf(Span::class, $activeSpan);
        $this->assertEquals($scope->getSpan(), $activeSpan);
    }

    public function testFlush() {
        $Jaeger = $this->getJaeger();
        $Jaeger->buildSpan('test')->start();
        $Jaeger->flush();
        $this->assertEmpty($Jaeger->getSpans());
    }


    public function testNestedSpanBaggage() {
        $tracer = $this->getJaeger();

        $parent = $tracer->buildSpan('parent')->start();
        $parent->addBaggageItem('key', 'value');

        $child = $tracer->buildSpan('child')->asChildOf($parent)->start();

        $this->assertEquals($parent->getBaggageItem('key'), $child->getBaggageItem('key'));
    }

    public function test__StartSpan__With_AsChildOf() {
        $jaeger        = $this->getJaeger();
        $rootSpan      = $jaeger->buildSpan('root-a')->start();

        $childSpan = $jaeger->buildSpan('span-a')
                            ->asChildOf($rootSpan)
                            ->start();

        $this->assertSame($childSpan->getContext()->getParentId(), $rootSpan->getContext()->getSpanId());
    }
}