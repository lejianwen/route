<?php
/**
 * User: lejianwen
 * Date: 2017/4/25
 * Time: 10:26
 * QQ: 84855512
 */

namespace Ljw\Route;

/**
 * @method static Route get(string $route, ...$controller)
 * @method static Route post(string $route, ...$controller)
 * @method static Route put(string $route, ...$controller)
 * @method static Route delete(string $route, ...$controller)
 * @method static Route options(string $route, ...$controller)
 * @method static Route head(string $route, ...$controller)
 * @method static Route any(string $route, ...$controller)
 */
class Route
{
    public static $routes = [];
    /** @var array $patterns_routes 正则路由 */
    public static $patterns_routes = [];
    public static $patterns = [
        ':any' => '[^/]+',
        ':str' => '[0-9a-zA-Z_]+',
        ':num' => '[0-9]+',
        ':all' => '.*'
    ];
    public static $controller_namespace = null;
    public static $middleware_namespace = null;
    public static $error_callback;
    public static $temp_middleware;

    public static $params = [];

    public static $temp_groups = [];
    public static $match_route_uri = ''; //

    /**
     * 路由定义
     * @param $method
     * @param $params
     */
    public static function __callstatic($method, $params)
    {
        $uri = '/' . ltrim($params[0], '/');
        $middleware = [];
        if (!empty(self::$temp_middleware)) {
            self::prepareMiddleware(self::$temp_middleware, $middleware);
        }
        if (count($params) > 2) {
            self::prepareMiddleware(array_slice($params, 1, count($params) - 2), $middleware);
        }
        $controller = end($params);
        if (is_string($controller)) {
            $controller = self::$controller_namespace . $controller;
        }
        if (!empty(self::$temp_groups)) {
            $uri = implode('/', self::$temp_groups) . $uri;
        }
        if (strpos($uri, ':') !== false) {
            self::$patterns_routes[strtoupper($method)][$uri] = [$middleware, $controller];
        } else {
            self::$routes[strtoupper($method)][$uri] = [$middleware, $controller];
        }
    }

    public static function prepareMiddleware($middles, &$middleware)
    {
        foreach ($middles as $middle) {
            if (is_string($middle)) {
                $middleware[] = self::$middleware_namespace . $middle;
            } elseif (is_array($middle)) {
                self::prepareMiddleware($middle, $middleware);
            } else {
                $middleware[] = $middle;
            }
        }
    }

    /**
     * @param string|array|\Closure $middleware
     * @param \Closure $callback
     */
    public static function middleware($middleware, \Closure $callback)
    {
        $old_middlewares = self::$temp_middleware;
        self::$temp_middleware[] = [$middleware];
        $callback();
        self::$temp_middleware = $old_middlewares;
    }

    /**
     * 定义错误路由
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
        if (isset(self::$routes[$method][$uri])) {
            $route = self::$routes[$method][$uri];
            self::$match_route_uri = $uri;
            self::action($route[1], $route[0]);
            return;
        }
        //匹配失败,进行正则匹配
        if (!empty(self::$patterns_routes[$method])) {
            $searches = array_keys(static::$patterns);
            $replaces = array_values(static::$patterns);
            foreach (self::$patterns_routes[$method] as $route_uri => $route) {
                $route_uri = str_replace('.', '\.', $route_uri);
                $route_uri = str_replace($searches, $replaces, $route_uri);
                //正则匹配
                if (preg_match('#^' . $route_uri . '$#', $uri, $matched)) {
                    self::$match_route_uri = $route_uri;
                    array_shift($matched);
                    self::action($route[1], $route[0], $matched);
                    return;
                }
            }
        }

        //还是匹配失败,执行error
        //默认error
        if (!self::$error_callback) {
            self::$error_callback = function () {
                http_response_code(404);
                echo '404 Not Found!';
            };
        } elseif (is_string(self::$error_callback)) {
            self::$error_callback = self::$controller_namespace . self::$error_callback;
        }
        self::action(self::$error_callback);
    }

    /**
     * @param $controller
     * @param array $middleware
     * @param array $matched
     */
    public static function action($controller, $middleware = [], $matched = [])
    {
        $pipe = new Pipeline();
        $pipe->send($matched)->through($middleware)->then(
            function ($matched) use ($controller) {
                Route::$params = $matched;
                //controller是一个闭包函数
                if ($controller instanceof \Closure) {
                    $controller(...$matched);
                } else {
                    list($controller_class, $controller_method) = explode('@', $controller);
                    $controller_object = new $controller_class();
                    $controller_object->$controller_method(...$matched);
                }
            }
        );
    }

    public static function group($prefix, $callback = null)
    {
        $old_prefix = self::$temp_groups;
        self::$temp_groups[] = $prefix;
        $group = new Group();
        $group->prefix = self::$temp_groups;
        $callback();
        self::$temp_groups = $old_prefix;
        return $group;
    }
}
