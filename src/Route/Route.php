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
    public static $halts = false;
    public static $routes = [];
    public static $patterns = [
        ':any' => '[^/]+',
        ':str' => '[0-9a-zA-Z_]+',
        ':num' => '[0-9]+',
        ':all' => '.*'
    ];
    public static $controller_namespace = 'app\\controllers\\';
    public static $middleware_namespace = 'app\\middleware\\';
    public static $error_callback;

    /**路由定义
     * @param $method
     * @param $params
     */
    public static function __callstatic($method, $params)
    {
        $uri = '/' . trim($params[0], '/');
        $middleware = isset($params[2]) ? $params[1] : null;
        $callback = isset($params[2]) ? $params[2] : $params[1];
        self::$routes[$uri] = [strtoupper($method), $middleware, $callback];
    }

    /**定义错误路由
     * @param $callback
     */
    public static function error($callback)
    {
        self::$error_callback = $callback;
    }

    /**运行匹配
     *
     */
    public static function run()
    {

        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $method = $_SERVER['REQUEST_METHOD'];
        //是否匹配到路由
        $found = false;
        if (isset(self::$routes[$uri]))
        {
            $route = self::$routes[$uri];
            //ANY 可以用来同时匹配所有方式
            if ($route[0] == $method || $route[0] == 'ANY')
            {
                $found = true;
                //有中间件
                if ($route[1] !== null)
                {
                    $middleware = $route[1];
                    //中间件运行结果
                    $middle_result = false;
                    //中间件是闭包函数
                    if ($middleware instanceof \Closure)
                    {
                        $middle_result = $middleware();
                    } else
                    {
                        $middle_arr = explode('@', $middleware);
                        $middleware_class = self::$middleware_namespace . $middle_arr[0];
                        $middleware_object = new $middleware_class();
                        $middle_result = $middleware_object->$middle_arr[1]();
                    }
                    $controller = $route[2];
                    //controller是一个闭包函数
                    if ($controller instanceof \Closure)
                    {
                        $controller($middle_result);
                    } else
                    {
                        $controller_arr = explode('@', $controller);
                        $controller_class = self::$controller_namespace . $controller_arr[0];
                        $controller_object = new $controller_class();
                        $controller_object->$controller_arr[1]($middle_result);
                    }

                } else
                {
                    //没有中间件，直接运行controller
                    $controller = $route[2];
                    //controller是一个闭包函数
                    if ($controller instanceof \Closure)
                    {
                        $controller();
                    } else
                    {
                        $controller_arr = explode('@', $controller);
                        $controller_class = self::$controller_namespace . $controller_arr[0];
                        $controller_object = new $controller_class();
                        $controller_object->$controller_arr[1]();
                    }
                }
            }
        }
        //匹配失败,进行正则匹配
        if ($found == false)
        {
            $searches = array_keys(static::$patterns);
            $replaces = array_values(static::$patterns);
            foreach (self::$routes as $route_uri => $route)
            {
                if (strpos($route_uri, ':') !== false)
                {
                    $route_uri = str_replace($searches, $replaces, $route_uri);
                }
                //正则匹配
                if (preg_match('#^' . $route_uri . '$#', $uri, $matched))
                {
                    //ANY 可以用来同时匹配所有方式
                    if ($route[0] == $method || $route[0] == 'ANY')
                    {
                        $found = true;
                        array_shift($matched);
                        //有中间件
                        if ($middleware = $route[1])
                        {
                            //中间件运行结果
                            $middle_result = false;
                            //中间件是闭包函数
                            if ($middleware instanceof \Closure)
                            {
                                $middle_result = $middleware(...$matched);
                            } else
                            {
                                $middle_arr = explode('@', $middleware);
                                $middleware_class = self::$middleware_namespace . $middle_arr[0];
                                $middleware_object = new $middleware_class();
                                $middle_result = $middleware_object->$middle_arr[1](...$matched);
                            }
                            $controller = $route[2];
                            //controller是一个闭包函数
                            if ($controller instanceof \Closure)
                            {
                                $controller($middle_result, ...$matched);
                            } else
                            {
                                $controller_arr = explode('@', $controller);
                                $controller_class = self::$controller_namespace . $controller_arr[0];
                                $controller_object = new $controller_class();
                                $controller_object->$controller_arr[1]($middle_result, ...$matched);
                            }

                        } else
                        {
                            //没有中间件，直接运行controller
                            $controller = $route[2];
                            //controller是一个闭包函数
                            if ($controller instanceof \Closure)
                            {
                                $controller(...$matched);
                            } else
                            {
                                $controller_arr = explode('@', $controller);
                                $controller_class = self::$controller_namespace . $controller_arr[0];
                                $controller_object = new $controller_class();
                                $controller_object->$controller_arr[1](...$matched);
                            }
                        }
                        break;
                    }
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
                };
            } else
            {
                if (is_string(self::$error_callback))
                {
                    self::any($_SERVER['REQUEST_URI'], self::$error_callback);
                    self::$error_callback = null;
                    self::run();
                    return;
                }
            }
            call_user_func(self::$error_callback);
        }

    }
}