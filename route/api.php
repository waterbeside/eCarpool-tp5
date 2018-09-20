<?php

$allowHeader = 'Authorization, Content-Type, If-Match, If-Modified-Since, If-None-Match, If-Unmodified-Since, X-Requested-With,Accept-Lag,Accept-Language';

Route::group([], function () {
  Route::rule('api/:version/index','api/:version.index');
  //文案声明相关
  Route::resource('api/:version/docs','api/:version.docs');
  //通知公告相关
  Route::resource('api/:version/notice','api/:version.notice');
  //通行证相关
  Route::resource('api/:version/passport','api/:version.passport');
  Route::rule('api/:version/passport','api/:version.passport/delete','DELETE');
  //附件相关
  Route::resource('api/:version/attachment','api/:version.attachment');
  Route::rule('api/:version/attachment/:type','api/:version.attachment/save','POST');
  Route::rule('api/:version/attachment/:id','api/:version.attachment/delete','DELETE')->pattern(['id' => '\S+']);
  Route::rule('api/:version/attachment','api/:version.attachment/delete','DELETE');

  //发送短信相关
  Route::rule('api/:version/sms/send','api/:version.sms/send');
  Route::rule('api/:version/sms/verify','api/:version.sms/verify');
  Route::rule('api/:version/sms/:usage','api/:version.sms/send','GET')->pattern(['usage' => '\d+']);
  Route::rule('api/:version/sms/:usage','api/:version.sms/verify','POST')->pattern(['usage' => '\d+']);

  //app启动调用
  Route::rule('api/:version/app_initiate','api/:version.app_initiate/index','GET');

})->header('Access-Control-Allow-Headers', $allowHeader)->allowCrossDomain();

// Route::resource('api/:version/docs','api/:version.docs')->header('Access-Control-Allow-Headers', $allowHeader)->allowCrossDomain();
// Route::resource('api/:version/passport','api/:version.passport')->header('Access-Control-Allow-Headers', $allowHeader)->allowCrossDomain();
