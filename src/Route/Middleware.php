<?php
/**
 * Class Middleware
 * Author lejianwen
 * Date: 2020/3/6 11:01
 */

namespace Ljw\Route;

/**
 * 路由中间件接口
 * 必须时限handle
 * Interface Middleware
 * @package Ljw\Route
 */
interface Middleware
{
    public function handle($params, $stack);
}
