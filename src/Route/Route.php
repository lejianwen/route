<?php
/**
 * User: lejianwen
 * Date: 2017/4/25
 * Time: 10:26
 * QQ: 84855512
 */

namespace Ljw\Route;

/**
 * @method static Route get(string $route, Callable $middle = null, Callable $controller)
 * @method static Route post(string $route, Callable $middle = null, Callable $controller)
 * @method static Route put(string $route, Callable $middle = null, Callable $controller)
 * @method static Route delete(string $route, Callable $middle = null, Callable $controller)
 * @method static Route options(string $route, Callable $middle = null, Callable $controller)
 * @method static Route head(string $route, Callable $middle = null, Callable $controller)
 * @method static Route any(string $route, Callable $middle = null, Callable $controller)
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
    //是否在中间件中断
    public static $middle_can_stop = true;

    /**路由定义
     * @param $method
     * @param $params
     */
    public static function __callstatic($method, $params)
    {
        $uri = '/' . ltrim($params[0], '/');
        $middleware = isset($params[2]) ? $params[1] : null;
        $controller = isset($params[2]) ? $params[2] : $params[1];
        is_string($middleware) && $middleware = self::$middleware_namespace . $middleware;
        is_string($controller) && $controller = self::$controller_namespace . $controller;
        self::$routes[strtoupper($method)][$uri] = [$middleware, $controller];
    }

    /**定义错误路由
     * @param $callback
     */
    public static function error($callback)
    {
        self::$error_callback = $callback;
    }

    /**
     * 自定义命名空间
     * @param null $controller
     * @param null $middle
     */
    public static function space($controller = null, $middle = null)
    {
        self::$controller_namespace = $controller;
        self::$middleware_namespace = $middle;
    }

    public static function middleCanStop($can = true)
    {
        self::$middle_can_stop = $can;
    }

    /**
     * 运行
     */
    public static function run()
    {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $method = $_SERVER['REQUEST_METHOD'];
        //ANY匹配所有方式
        if (isset(self::$routes['ANY'])) {
            if (isset(self::$routes[$method])) {
                self::$routes[$method] = array_merge(self::$routes['ANY'], self::$routes[$method]);
            } else {
                self::$routes[$method] = self::$routes['ANY'];
            }
        }
        //是否匹配到路由
        $found = false;
        if (isset(self::$routes[$method][$uri])) {
            $route = self::$routes[$method][$uri];
            $found = true;
            /*
            * $middleware = $route[0];
            * $controller = $route[1];
            * */
            self::action($route[1], $route[0]);
        }
        //匹配失败,进行正则匹配
        if ($found == false && isset(self::$routes[$method])) {
            $searches = array_keys(static::$patterns);
            $replaces = array_values(static::$patterns);
            foreach (self::$routes[$method] as $route_uri => $route) {
                $route_uri = str_replace('.', '\.', $route_uri);
                if (strpos($route_uri, ':') !== false) {
                    $route_uri = str_replace($searches, $replaces, $route_uri);
                }
                //正则匹配
                if (preg_match('#^' . $route_uri . '$#', $uri, $matched)) {
                    $found = true;
                    array_shift($matched);
                    /*
                     * $middleware = $route[0];
                     * $controller = $route[1];
                     * */
                    self::action($route[1], $route[0], $matched);
                    break;
                }
            }
        }

        //还是匹配失败,执行error
        if ($found == false) {
            //默认error
            if (!self::$error_callback) {
                self::$error_callback = function () {
                    http_response_code(404);
                    echo '404 Not Found!';
                };
            } else {
                if (is_string(self::$error_callback)) {
                    self::$method($_SERVER['REQUEST_URI'], self::$error_callback);
                    self::$error_callback = null;
                    self::run();
                    return;
                }
            }
            call_user_func(self::$error_callback);
        }

    }

    public static function action($controller, $middleware = null, $matched = [])
    {
        $middle_result = null;
        if ($middleware) {
            //中间件是闭包函数
            if ($middleware instanceof \Closure) {
                if (!empty($matched)) {
                    $middle_result = $middleware(...$matched);
                } else {
                    $middle_result = $middleware();
                }
            } else {
                list($middleware_class, $middleware_method) = explode('@', $middleware);
                $middleware_object = new $middleware_class();
                if (!empty($matched)) {
                    $middle_result = $middleware_object->$middleware_method(...$matched);
                } else {
                    $middle_result = $middleware_object->$middleware_method();
                }
            }
            if ($middle_result === null && self::$middle_can_stop) {
                return;
            }
        }
        //
        if ($middle_result) {
            array_unshift($matched, $middle_result);
        }
        //controller是一个闭包函数
        if ($controller instanceof \Closure) {
            if (!empty($matched)) {
                $controller(...$matched);
            } else {
                $controller();
            }
        } else {
            list($controller_class, $controller_method) = explode('@', $controller);
            $controller_object = new $controller_class();
            if (!empty($matched)) {
                $controller_object->$controller_method(...$matched);
            } else {
                $controller_object->$controller_method();
            }
        }
    }
}