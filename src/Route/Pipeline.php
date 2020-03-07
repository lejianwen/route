<?php
/**
 * Class Pipleline
 * Author lejianwen
 * Date: 2020/3/6 10:17
 */

namespace Ljw\Route;

/**
 * 管道方法
 * Class Pipeline
 * @package lib
 */
class Pipeline
{
    private $pipes = [];
    private $method = 'handle';
    private $params;

    public function send($params)
    {
        $this->params = $params;
        return $this;
    }

    /**
     * 管道
     * @param $pipes
     * @return $this
     */
    public function through($pipes)
    {
        $this->pipes = $pipes;
        return $this;
    }

    public function getSlice()
    {
        return function ($stack, $pipe) {
            return function ($params = []) use ($stack, $pipe) {
                if ($pipe instanceof \Closure) {
                    return $pipe($params, $stack);
                } elseif (!is_object($pipe)) {
                    $pipe = new $pipe();
                    $parameters = [$params, $stack];
                } else {
                    $parameters = [$params, $stack];
                }
                return $pipe->{$this->method}(...$parameters);
            };
        };
    }

    public function then(\Closure $destination)
    {
        $callable = array_reduce(
            array_reverse($this->pipes),
            $this->getSlice(),
            function ($params = []) use ($destination) {
                return $destination($params);
            }
        );
        return $callable($this->params);
    }
}
