<?php
/**
 * User: lejianwen
 * Date: 2017/4/25
 * Time: 10:26
 * QQ: 84855512
 */
namespace Ljw\Route;

/**
 * @method static Route get(string $route, Callable $middle = null, Callable $callback)
 * @method static Route post(string $route, Callable $middle = null, Callable $callback)
 * @method static Route put(string $route, Callable $middle = null, Callable $callback)
 * @method static Route delete(string $route, Callable $middle = null, Callable $callback)
 * @method static Route options(string $route, Callable $middle = null, Callable $callback)
 * @method static Route head(string $route, Callable $middle = null, Callable $callback)
 * @method static Route any(string $route, Callable $middle = null, Callable $callback)
 */
class Route
{
    public static $routes = [];
    public static $patterns = [
        ':any' => '[^/]+',
        ':str' => '[0-9a-zA-Z_]+',
        ':num' => '[0-9]+',
        ':all' => '.*'
    ];
    public static $controller_namespace = null;
    public static $middleware_namespace = null;
    public static $error_callback;

    /**路由定义
     * @param $method
     * @param $params
     */
    public static function __callstatic($method, $params)
    {
        $uri = '/' . ltrim($params[0], '/');
        $middleware = isset($params[2]) ? $params[1] : null;
        $callback = isset($params[2]) ? $params[2] : $params[1];
        self::$routes[strtoupper($method)][$uri] = [$middleware, $callback];
    }

    /**定义错误路由
     * @param $callback
     */
    public static function error($callback)
    {
        self::$error_callback = $callback;
    }

    /**自定义命名空间
     * @param null $controller
     * @param null $middle
     */
    public static function space($controller = null, $middle = null)
    {
        self::$controller_namespace = $controller;
        self::$middleware_namespace = $middle;
    }


    /**运行
     *
     */
    public static function run()
    {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $method = $_SERVER['REQUEST_METHOD'];
        //ANY匹配所有方式
        if (isset(self::$routes['ANY'][$uri]))
            self::$routes[$method] = array_merge(self::$routes['ANY'], self::$routes[$method]);
        //是否匹配到路由
        $found = false;
        if (isset(self::$routes[$method][$uri]))
        {
            $route = self::$routes[$method][$uri];
            $found = true;
            //有中间件
            if ($route[0] !== null)
            {
                $middleware = $route[0];
                //中间件运行结果
                $middle_result = null;
                //中间件是闭包函数
                if ($middleware instanceof \Closure)
                {
                    $middle_result = $middleware();
                } else
                {
                    list($middleware_class, $middleware_method) = explode('@', $middleware);
                    $middleware_class = self::$middleware_namespace . $middleware_class;
                    $middleware_object = new $middleware_class();
                    $middle_result = $middleware_object->$middleware_method();
                }
                $controller = $route[1];
                //controller是一个闭包函数
                if ($controller instanceof \Closure)
                {
                    $controller($middle_result);
                } else
                {
                    list($controller_class, $controller_method) = explode('@', $controller);
                    $controller_class = self::$controller_namespace . $controller_class;
                    $controller_object = new $controller_class();
                    $controller_object->$controller_method($middle_result);
                }

            } else
            {
                //没有中间件，直接运行controller
                $controller = $route[1];
                //controller是一个闭包函数
                if ($controller instanceof \Closure)
                {
                    $controller();
                } else
                {
                    list($controller_class, $controller_method) = explode('@', $controller);
                    $controller_class = self::$controller_namespace . $controller_class;
                    $controller_object = new $controller_class();
                    $controller_object->$controller_method();
                }
            }
        }
        //匹配失败,进行正则匹配
        if ($found == false)
        {
            $searches = array_keys(static::$patterns);
            $replaces = array_values(static::$patterns);
            foreach (self::$routes[$method] as $route_uri => $route)
            {
                if (strpos($route_uri, ':') !== false)
                {
                    $route_uri = str_replace($searches, $replaces, $route_uri);
                }
                //正则匹配
                if (preg_match('#^' . $route_uri . '$#', $uri, $matched))
                {
                    $found = true;
                    array_shift($matched);
                    //有中间件
                    if ($middleware = $route[0])
                    {
                        //中间件运行结果
                        $middle_result = null;
                        //中间件是闭包函数
                        if ($middleware instanceof \Closure)
                        {
                            $middle_result = $middleware(...$matched);
                        } else
                        {
                            list($middleware_class, $middleware_method) = explode('@', $middleware);
                            $middleware_class = self::$middleware_namespace . $middleware_class;
                            $middleware_object = new $middleware_class();
                            $middle_result = $middleware_object->$middleware_method(...$matched);
                        }
                        $controller = $route[1];
                        //controller是一个闭包函数
                        if ($controller instanceof \Closure)
                        {
                            $controller($middle_result, ...$matched);
                        } else
                        {
                            list($controller_class, $controller_method) = explode('@', $controller);
                            $controller_class = self::$controller_namespace . $controller_class;
                            $controller_object = new $controller_class();
                            $controller_object->$controller_method($middle_result, ...$matched);
                        }

                    } else
                    {
                        //没有中间件，直接运行controller
                        $controller = $route[1];
                        //controller是一个闭包函数
                        if ($controller instanceof \Closure)
                        {
                            $controller(...$matched);
                        } else
                        {
                            list($controller_class, $controller_method) = explode('@', $controller);
                            $controller_class = self::$controller_namespace . $controller_class;
                            $controller_object = new $controller_class();
                            $controller_object->$controller_method(...$matched);
                        }
                    }
                    break;
                }
            }
        }

        //还是匹配失败,执行error
        if ($found == false)
        {
            //默认error
            if (!self::$error_callback)
            {
                self::$error_callback = function ()
                {
                    http_response_code(404);
                    echo '404 Not Found!';
                };
            } else
            {
                if (is_string(self::$error_callback))
                {
                    self::$method($_SERVER['REQUEST_URI'], self::$error_callback);
                    self::$error_callback = null;
                    self::run();
                    return;
                }
            }
            call_user_func(self::$error_callback);
        }

    }
}