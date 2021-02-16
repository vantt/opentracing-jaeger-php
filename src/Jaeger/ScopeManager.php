<?php

namespace Jaeger;

use OpenTracing\Scope as ScopeInterface;
use OpenTracing\Span as SpanInterface;
use OpenTracing\ScopeManager as ScopeManagerInterface;
use OpenTracing\Span;

class ScopeManager implements ScopeManagerInterface {

    private $scopes = [];

    /**
     * append scope
     *
     * @param SpanInterface $span
     * @param bool $finishSpanOnClose
     * @return Scope
     */
    public function activate(SpanInterface $span, bool $finishSpanOnClose = self::DEFAULT_FINISH_SPAN_ON_CLOSE): ScopeInterface {
        $scope = new Scope($this, $span, $finishSpanOnClose);
        $this->scopes[] = $scope;
        return $scope;
    }


    /**
     * get last scope
     * @return mixed|null
     */
    public function getActive(): ?ScopeInterface {
        if (empty($this->scopes)) {
            return null;
        }

        return $this->scopes[count($this->scopes) - 1];
    }


    /**
     * del scope
     * @param Scope $scope
     * @return bool
     */
    public function delActive(Scope $scope){
        $scopeLength = count($this->scopes);

        if($scopeLength <= 0){
            return false;
        }

        for ($i = 0; $i < $scopeLength; $i++) {
            if ($scope === $this->scopes[$i]) {
                array_splice($this->scopes, $i, 1);
            }
        }

        return true;
    }
}
