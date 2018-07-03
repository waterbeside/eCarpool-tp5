<?php

$allowHeader = 'Authorization, Content-Type, If-Match, If-Modified-Since, If-None-Match, If-Unmodified-Since, X-Requested-With,Accept-Lag,Accept-Language';

Route::resource('api/:version/docs','api/:version.docs')
->header('Access-Control-Allow-Headers', $allowHeader)
->allowCrossDomain();   //注册一个资源路由，对应restful各个方法,.为目录
