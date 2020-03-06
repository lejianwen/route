# 一个简单的路由工具

* 项目地址 [https://github.com/lejianwen/route](https://github.com/lejianwen/route)

## 安装

~~~
composer require "ljw/route": "dev-master"
~~~
## 使用方式

~~~php
use \Ljw\Route\Route;
// 第一个参数是uri， 最后一个是controller， 中间的都为中间件
Route::$method($uri, $controller);
Route::$method($uri, $middleware, $controller);
~~~

## 基本使用

~~~php
use \Ljw\Route\Route;

Route::get('', 'app\\controllers\\IndexController@index');

Route::post('index/test', 'app\\middleware\\Index', 'app\\controllers\\IndexController@test');

Route::post('index/test2/(:str)/(:num)', 'app\\middleware\\Index', 'app\\controllers\\IndexController@test2');
Route::post('index/test2/(:str)/(:num)', 
function($params, $next)
{
    // todo 前置方法
    $next($params); // 如果这个不写则在此处中断
    // todo 后置方法
}, 'app\\controllers\\IndexController@test2');
~~~
### 1.中间件如果是类要实现Middleware接口，或者有handle方法

~~~php
namespace app\middleware;

use Ljw\Route\Middleware;

class Index implements Middleware
{
    public function handle($params, $next)
    {
        // todo 前置方法
        $next($params); // 如果这个不写则在此处中断
        // todo 后置方法
    }
}
~~~
### 2.如果中间是闭包则和handle方法一样，需要接收2个参数
~~~php
function($params, $next)
{
        // todo 前置方法
        $next($params); // 如果这个不写则在此处中断
        // todo 后置方法
}
~~~

### 3.控制器中可通过参数获取到匹配的值

~~~php
namespace app\controllers;

class IndexController
{
    public function test()
    {
        echo 'ok';
    }

    public function test2($str, $num)
    {
        var_dump($str, $num);
    }
}
~~~ 


## 命名空间自定义
### 如果传入的中间件和控制是字符串， 则会自动加上命名空间
~~~php
use \Ljw\Route\Route;
//定义控制器和中间件的命名空间
Route::space('app\\controllers\\', 'app\\middleware\\'); 

Route::get('', 'IndexController@index');

Route::post('index/test', 'Index@test', 'IndexController@test');

~~~

## 通过中间件批量添加路由
~~~php
use \Ljw\Route\Route;

Route::middleware('app\\middleware\\Index',
    function () {
        Route::get('/',  'IndexController@test');
    }
);
//定义控制器和中间件的命名空间
Route::space('app\\controllers\\', 'app\\middleware\\'); 
Route::middleware(['Index', 'Index'],
    function () {
        Route::get('/',  'IndexController@test');
    }
);
Route::middleware('Index',    function () {
        Route::get('/',  'IndexController@test');
    }
);
~~~

## 使用闭包

~~~php
use \Ljw\Route\Route;
//定义控制器和中间件的命名空间
Route::get('', function (){
    echo 'index';
});

Route::get('index/index/(:str)/(:num)',function($params, $next)
                                       {
                                               // todo 前置方法
                                               $next($params); // 如果这个不写则在此处中断
                                               // todo 后置方法
                                       },
 function ($str, $num) {
    var_dump($str, $num);    //结果为 middle
});

~~~



## 错误处理

~~~php
use \Ljw\Route\Route;
//错误处理
Route::error(function (){
     http_response_code(404);
     echo '404 Not Found!';
});
//或者
Route::error('app\\controllers\\IndexController@error');
~~~

## 使用正则匹配

~~~php
//正则匹配支持的方式
 $patterns = [
        ':any' => '[^/]+',
        ':str' => '[0-9a-zA-Z_]+',
        ':num' => '[0-9]+',
        ':all' => '.*'
 ];
// 也可以通过\Ljw\Route\Route::$patterns, 自行添加和修改
use \Ljw\Route\Route;
Route::space('app\\controllers\\', 'app\\middleware\\'); 
Route::get('index/(:num)', 'Index', 'IndexController@test');
~~~

~~~php
//中间件
namespace app\middleware;

use Ljw\Route\Middleware;

class Index implements Middleware
{
    public function handle($params, $next)
    {
        // todo 前置方法
        $params[0]++;
        $next($params); // 如果这个不写则在此处中断
        // todo 后置方法
    }
}
~~~
~~~php
//控制器
class IndexController{
    public function test($num)
    {
        echo $num;
    }
}
//GET /index/5 结果为6
~~~

# TIPS
1. 路由只有在匹配不到的时候才会匹配正则,正则如果也匹配不到则会调用error

~~~php
use \Ljw\Route\Route;
Route::get('index/test', 'IndexController@test');
Route::get('index/(:str)', 'IndexController@index');
//GET /index/test时，会匹配第一条
~~~

2. 相同的路由规则,后面的路由会覆盖前面的

~~~php
use \Ljw\Route\Route;
Route::get('index/test', 'IndexController@test');
Route::get('index/test', 'IndexController@index');
//GET /index/test时，会匹配第二条
~~~

3. 方式any中的路由优先级低于get,post等具体方式

~~~php
use \Ljw\Route\Route;
Route::any('index/test', 'IndexController@any');
Route::get('index/test', 'IndexController@test');
Route::any('index/test', 'IndexController@index');
//GET /index/test时，会匹配第二条
//POST /index/test时，会匹配第三条
~~~
