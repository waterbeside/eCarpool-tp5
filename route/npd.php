<?php

$allowHeader = 'Authorization, Content-Type, If-Match, If-Modified-Since, If-None-Match, If-Unmodified-Since, X-Requested-With,Accept-Lag,Accept-Language';

Route::group([], function () {
  Route::rule('npd/api/:version/nav','npd/api.:version.nav/index','get');
  Route::rule('npd/api/:version/category','npd/api.:version.category/index','get');
  Route::rule('npd/api/:version/banner','npd/api.:version.banner/index','get');
  Route::rule('npd/api/:version/customer','npd/api.:version.customer/index','get');

  Route::resource('npd/api/:version/product','npd/api.:version.product');
  Route::resource('npd/api/:version/article','npd/api.:version.article');
  Route::rule('npd/api/:version/single','npd/api.:version.single/index','get');
  Route::rule('npd/api/:version/single/:id','npd/api.:version.single/read','get');
 

})->header('Access-Control-Allow-Headers', $allowHeader)->allowCrossDomain();

