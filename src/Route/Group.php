<?php


namespace Ljw\Route;

/**
 * @method Group get(string $route, ...$controller)
 * @method Group post(string $route, ...$controller)
 * @method Group put(string $route, ...$controller)
 * @method Group delete(string $route, ...$controller)
 * @method Group options(string $route, ...$controller)
 * @method Group head(string $route, ...$controller)
 * @method Group any(string $route, ...$controller)
 * @method Group group(string $group, $callback)
 */
class Group
{
    public $prefix = [];

    public function __call($method, $params)
    {
        Route::group(implode('/', $this->prefix), function () use ($method, $params) {
            Route::$method(...$params);
        });
    }
}