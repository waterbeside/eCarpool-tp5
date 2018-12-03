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
  Route::rule('api/:version/passport/:field','api/:version.passport/update_field','PATCH');

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
  Route::rule('api/:version/sms/status/:sendid','api/:version.sms/sms_status');

  //app启动调用
  Route::rule('api/:version/app_initiate','api/:version.app_initiate/index','GET');

  //同步Hr系统
  Route::rule('api/:version/sync_hr/single','api/:version.sync_hr/single','GET');
  Route::rule('api/:version/sync_hr/to_primary','api/:version.sync_hr/to_primary','GET');
  Route::rule('api/:version/sync_hr/all','api/:version.sync_hr/all','GET');

  //行程相关
  Route::rule('api/:version/trips/wall/:id/passengers','api/:version.trips/passengers','GET');
  Route::rule('api/:version/trips/wall/:id','api/:version.trips/wall_detail','GET');
  Route::rule('api/:version/trips/info/:id','api/:version.trips/info_detail','GET');
  Route::rule('api/:version/trips/wall','api/:version.trips/wall_list','GET');
  Route::rule('api/:version/trips/info','api/:version.trips/info_list','GET');
  Route::rule('api/:version/trips/history','api/:version.trips/history','GET');
  Route::rule('api/:version/trips','api/:version.trips/index','GET');
  Route::rule('api/:version/trips/:from','api/:version.trips/add','POST');
  Route::rule('api/:version/trips/:from/:id','api/:version.trips/change','PATCH');
  Route::rule('api/:version/trips/:from/:id','api/:version.trips/cancel','DELETE');

  //地址相关
  Route::resource('api/:version/address','api/:version.address');

  //用户相关
  Route::resource('api/:version/user','api/:version.user');
  Route::rule('api/:version/user/:id/position','api/:version.user_position/read','GET');
  Route::rule('api/:version/user/:id/position','api/:version.user_position/save','POST');



})->header('Access-Control-Allow-Headers', $allowHeader)->allowCrossDomain();

// Route::resource('api/:version/docs','api/:version.docs')->header('Access-Control-Allow-Headers', $allowHeader)->allowCrossDomain();
// Route::resource('api/:version/passport','api/:version.passport')->header('Access-Control-Allow-Headers', $allowHeader)->allowCrossDomain();
