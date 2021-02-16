<?php

namespace Jaeger;


use OpenTracing\Scope as ScopeInterface;
use OpenTracing\Span as SpanInterface;
use OpenTracing\ScopeManager as ScopeManagerInterface;

class Scope implements ScopeInterface {

    /**
     * @var ScopeManagerInterface
     */
    private $scopeManager = null;

    /**
     * @var SpanInterface
     */
    private $span = null;

    /**
     * @var bool
     */
    private $finishSpanOnClose;


    /**
     * Scope constructor.
     * @param ScopeManagerInterface $scopeManager
     * @param SpanInterface $span
     * @param bool $finishSpanOnClose
     */
    public function __construct(ScopeManagerInterface $scopeManager, SpanInterface $span, $finishSpanOnClose){
        $this->scopeManager = $scopeManager;
        $this->span = $span;
        $this->finishSpanOnClose = $finishSpanOnClose;
    }


    public function close(): void{
        if ($this->finishSpanOnClose) {
            $this->span->finish();
        }

        $this->scopeManager->delActive($this);
    }


    public function getSpan(): SpanInterface {
        return $this->span;
    }
}
