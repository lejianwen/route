<?php

$path = dirname(__FILE__);
require_once $path . "/../Route/Route.php";
require_once $path . "/../Route/Group.php";

use Ljw\Route\Route;

//Route::get('', 'index');
//Route::post('/a/b/c', 'cccc');
//Route::get('get', '/a/c', [3]);
//Route::get('get', '/a/c/b', [4]);
Route::middleware('mid1', function () {
    Route::get('/m1', 'm1@controller');
    Route::middleware('mid1_1', function () {
        Route::get('/m1_1', 'm1_1@controller');
    });
});
Route::get('m0', 'm0@CCC');

$api_group = Route::group('api', function () {
    Route::group('admin', function () {
        Route::get('a1', 'ad1@c');
    });
    Route::get('a1', 'a1@c');
});

Route::group('rpc', function () {
    Route::group('rpc_admin', function () {
        Route::get('rad1', 'rad1@c');
    });
    Route::get('ra1', 'ra1@c');
});
$api_group->get('a2', 'a2@c');
$api_group->group('ad2', function () {
    Route::get('ad2_1', 'ad2_1@c');
});

Route::get('common', 'common@CCC');
var_dump(Route::$routes);