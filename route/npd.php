<?php

$allowHeader = 'Authorization, Content-Type, If-Match, If-Modified-Since, If-None-Match, If-Unmodified-Since, X-Requested-With,Accept-Lag,Accept-Language';

Route::group([], function () {
  Route::rule('npd/api/:version/nav','npd/api.:version.nav/index','get');
  Route::rule('npd/api/:version/category','npd/api.:version.category/index','get');

  Route::resource('npd/api/:version/product','npd/api.:version.product');
 

})->header('Access-Control-Allow-Headers', $allowHeader)->allowCrossDomain();

