# 一个简单的路由工具

* 项目地址 [https://github.com/lejianwen/route](https://github.com/lejianwen/route)

## 安装

~~~
composer require "ljw/route": "dev-master"
~~~
## 使用方式

~~~
Route::$method($uri, $controller);
Route::$method($uri, $middleware, $controller);
~~~

## 基本使用

~~~php
use \Ljw\Route\Route;

Route::get('', 'app\\controllers\\IndexController@index');

Route::post('index/test', 'app\\middleware\\Index@test', 'app\\controllers\\IndexController@test');

~~~
## 当中间件没有返回值，或者返回值为NULL时是否中断，不在进行controller
~~~php
use \Ljw\Route\Route;
Route::middleCanStop(false);  //不中断
Route::middleCanStop(true);  //中断;默认
~~~
## 命名空间自定义

~~~php
use \Ljw\Route\Route;
//定义控制器和中间件的命名空间
Route::space('app\\controllers\\', 'app\\middleware\\'); 

Route::get('', 'IndexController@index');

Route::post('index/test', 'Index@test', 'IndexController@test');

~~~

## 使用闭包

~~~php
use \Ljw\Route\Route;
//定义控制器和中间件的命名空间
Route::get('', function (){
    echo 'index';
});

Route::get('index/index',function(){
    return 'middle';
}, function ($middle_re) {
    echo $middle_re;    //结果为 middle
});

~~~



## 错误处理

~~~php
//错误处理
Route::error(function (){
     http_response_code(404);
     echo '404 Not Found!';
});
//或者
Route::error('app\\controllers\\IndexController@test');
~~~

## 使用正则匹配

~~~php
//闭包
Route::get('test/(:num)',function($num){
    $num = $num+5;
    return $num;
}, function ($m_re, $num) {
    var_dump($m_re);
    var_dump($num);
});
Route::get('index/(:num)', 'Index@test', 'IndexController@test');
//=========
//中间件
class Index{
    public function test($num)
    {
        $num += 5;
        return $num;
    }
}
//控制器
class IndexController{
    public function test($md_re, $num)
    {
        echo $md_re;
        echo '<br>';
        echo $num;
    }
}
//GET /index/5 结果为 5 10



~~~

# TIPS
1. 路由只有在匹配不到的时候才会匹配正则,正则如果也匹配不到则会调用error

~~~php
Route::get('index/test', 'IndexController@test');
Route::get('index/(:str)', 'IndexController@index');
//GET /index/test时，会匹配第一条
~~~

2. 相同的路由规则,后面的路由会覆盖前面的

~~~php
Route::get('index/test', 'IndexController@test');
Route::get('index/test', 'IndexController@index');
//GET /index/test时，会匹配第二条
~~~

3. 方式any中的路由优先级低于get,post等具体方式

~~~php
Route::any('index/test', 'IndexController@any');
Route::get('index/test', 'IndexController@test');
Route::any('index/test', 'IndexController@index');
//GET /index/test时，会匹配第二条
//POST /index/test时，会匹配第三条
~~~