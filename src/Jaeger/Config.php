<?php
/*
 * Copyright (c) 2019, The Jaeger Authors
 *
 * Licensed under the Apache License, Version 2.0 (the "License"); you may not use this file except
 * in compliance with the License. You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software distributed under the License
 * is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
 * or implied. See the License for the specific language governing permissions and limitations under
 * the License.
 */

namespace Jaeger;

use Exception;
use InvalidArgumentException;
use Jaeger\Reporter\RemoteReporter;
use Jaeger\Reporter\Reporter;
use Jaeger\Transport\TransportUdp;
use OpenTracing\NoopTracer;
use Jaeger\Sampler\Sampler;
use Jaeger\Sampler\ConstSampler;
use Jaeger\Propagator\JaegerPropagator;
use Jaeger\Propagator\ZipkinPropagator;

class Config {

    private $transport = null;

    private $reporter = null;

    private $sampler = null;

    private $scopeManager = null;

    private $gen128bit = false;

    public static $tracer = null;

    public static $span = null;

    public static $instance = null;

    public static $disabled = false;

    public static $propagator = \Jaeger\Constants\PROPAGATOR_JAEGER;


    private function __construct(){

    }


    private function __clone(){

    }

    /**
     * @param array $arrConfig
     *
     * @return Jaeger|null
     * @throws Exception
     */
    public static  function fromArray(array $arrConfig = []) {

        if (empty($arrConfig['name']) || empty($arrConfig['host_port']) || empty($arrConfig['sampler_type'])) {
            throw new InvalidArgumentException('Missing arguments: [name, host, sampler_type]');
        }

        $strName        = trim((string)$arrConfig['name']);
        $strHostPort    = trim((string)$arrConfig['host_port']);
        $strSamplerType = trim((string)$arrConfig['sampler_type']);

        if ($strSamplerType !== 'const' && $strSamplerType !== 'probabilistic') {
            throw new InvalidArgumentException('Only support sampling type: [const, probabilistic]');
        }

        $tracerConfig = self::getInstance();
        $tracerConfig->gen128bit();

        $tracerConfig::$propagator = Constants\PROPAGATOR_JAEGER;

        $samplerValue = null;
        if (isset($arrConfig['sampler_value'])) {
            $samplerValue = $arrConfig['sampler_value'];
        }

        switch ($strSamplerType) {
            case 'const':
                $isSampled = false;
                if ($samplerValue !== null) {
                    $isSampled = (bool)$samplerValue;
                }

                $tracerConfig->setSampler(new ConstSampler($isSampled));
                break;

            case 'probabilistic':
                $floatValue = 0.0;
                if ($samplerValue !== null) {
                    $floatValue = (float)$samplerValue;
                    if ($floatValue < 0 || $floatValue > 1) {
                        $floatValue = 0.0;
                    }
                }

                $tracerConfig->setSampler(new ProbabilisticSampler($floatValue));
                break;
        }

        $tracer = $tracerConfig->initTracer($strName, $strHostPort);

        if (!$tracer) {
            throw new RuntimeException('Could not initialize Jaeger');
        }

        return $tracer;
    }


    public static function getInstance()
    {
        if(! (self::$instance instanceof self) )
        {
            self::$instance = new self();
        }
        return self::$instance;
    }


    /**
     * init jaeger, return can use flush  buffers
     *
     * @param $serviceName
     * @param string $agentHostPort
     *
     * @return Jaeger|NoopTracer|null
     * @throws Exception
     */
    public function initTracer($serverName, $agentHostPort = ''){

        if(self::$disabled){
            return NoopTracer::create();
        }

        if($serverName === ''){
            throw new Exception("serverName require");
        }

        if(isset(self::$tracer[$serverName]) && !empty(self::$tracer[$serverName])){
            return self::$tracer[$serverName];
        }


        if($this->transport === null){
            $this->transport = new TransportUdp($agentHostPort);
        }

        if($this->reporter === null) {
            $this->reporter = new RemoteReporter($this->transport);
        }

        if($this->sampler === null){
            $this->sampler = new ConstSampler(true);
        }

        if($this->scopeManager === null){
            $this->scopeManager = new ScopeManager();
        }

        $tracer = new Jaeger($serverName, $this->reporter, $this->sampler, $this->scopeManager);

        if($this->gen128bit === true){
            $tracer->gen128bit();
        }

        if(self::$propagator === \Jaeger\Constants\PROPAGATOR_ZIPKIN){
            $tracer->setPropagator(new ZipkinPropagator());
        }else{
            $tracer->setPropagator(new JaegerPropagator());
        }

        self::$tracer[$serverName] = $tracer;

        return $tracer;
    }


    /**
     * close tracer
     * @param $disabled
     */
    public function setDisabled($disabled){
        self::$disabled = $disabled;

        return $this;
    }


    public function setTransport(Transport\Transport $transport){
        $this->transport = $transport;

        return $this;
    }


    public function setReporter(Reporter $reporter){
        $this->reporter = $reporter;

        return $this;
    }


    public function setSampler(Sampler $sampler){
        $this->sampler = $sampler;

        return $this;
    }


    public function gen128bit(){
        $this->gen128bit = true;

        return $this;
    }


    public function flush(){
        if(count(self::$tracer) > 0) {
            foreach(self::$tracer as $tracer){
                $tracer->reportSpan();
            }
            $this->reporter->close();
        }

        return true;
    }
}
