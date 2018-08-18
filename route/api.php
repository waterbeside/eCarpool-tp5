<?php

$allowHeader = 'Authorization, Content-Type, If-Match, If-Modified-Since, If-None-Match, If-Unmodified-Since, X-Requested-With,Accept-Lag,Accept-Language';

Route::group([], function () {
  Route::resource('api/:version/docs','api/:version.docs');
  Route::resource('api/:version/passport','api/:version.passport');
  Route::rule('api/:version/passport','api/:version.passport/delete','DELETE');
  Route::resource('api/:version/attachment','api/:version.attachment');
  Route::rule('api/:version/attachment/:type','api/:version.attachment/save','POST');
})->header('Access-Control-Allow-Headers', $allowHeader)->allowCrossDomain();

// Route::resource('api/:version/docs','api/:version.docs')->header('Access-Control-Allow-Headers', $allowHeader)->allowCrossDomain();
// Route::resource('api/:version/passport','api/:version.passport')->header('Access-Control-Allow-Headers', $allowHeader)->allowCrossDomain();
