<?php

$allowHeader = 'Authorization, Content-Type, If-Match, If-Modified-Since, If-None-Match, If-Unmodified-Since, X-Requested-With, X-Token, Accept-Language';
Route::header('Access-Control-Allow-Headers', $allowHeader)
    ->allowCrossDomain();

Route::group([], function () {
    Route::rule('api/:version/index', 'api/:version.index');
    //文案声明相关
    Route::resource('api/:version/docs', 'api/:version.docs');
    //通知公告相关
    Route::resource('api/:version/notice', 'api/:version.notice');
    //banner相关
    Route::resource('api/:version/ads', 'api/:version.ads');
    //通行证相关
    Route::resource('api/:version/passport', 'api/:version.passport');
    Route::rule('api/:version/passport', 'api/:version.passport/delete', 'DELETE');
    Route::rule('api/:version/passport/:field', 'api/:version.passport/update_field', 'PATCH');

    //附件相关
    Route::resource('api/:version/attachment', 'api/:version.attachment');
    Route::rule('api/:version/attachment/:type', 'api/:version.attachment/save', 'POST');
    Route::rule('api/:version/attachment/:id', 'api/:version.attachment/delete', 'DELETE')->pattern(['id' => '\S+']);
    Route::rule('api/:version/attachment', 'api/:version.attachment/delete', 'DELETE');

    //发送短信相关
    Route::rule('api/:version/sms/send', 'api/:version.sms/send');
    Route::rule('api/:version/sms/verify', 'api/:version.sms/verify');
    Route::rule('api/:version/sms/:usage', 'api/:version.sms/send', 'GET')->pattern(['usage' => '\d+']);
    Route::rule('api/:version/sms/:usage', 'api/:version.sms/verify', 'POST')->pattern(['usage' => '\d+']);
    Route::rule('api/:version/sms/status/:sendid', 'api/:version.sms/sms_status');

    //app启动调用
    Route::rule('api/:version/app_initiate/url', 'api/:version.app_initiate/get_url', 'GET');
    Route::rule('api/:version/app_initiate', 'api/:version.app_initiate/index', 'GET');

    //同步Hr系统
    Route::rule('api/:version/sync_hr/single', 'api/:version.sync_hr/single', 'GET');
    Route::rule('api/:version/sync_hr/to_primary', 'api/:version.sync_hr/to_primary', 'GET');
    Route::rule('api/:version/sync_hr/all', 'api/:version.sync_hr/all', 'GET');
    Route::rule('api/:version/sync_hr/department', 'api/:version.sync_hr/create_department', 'POST');
    Route::rule('api/:version/sync_hr/department/:id', 'api/:version.sync_hr/department', 'GET');

    //行程相关
    Route::rule('api/:version/trips/:from/:id/user/:uid/position', 'api/:version.trips/user_position', 'GET'); //行程的用户位置
    Route::rule('api/:version/trips/wall/:id/comments', 'api/:version.trip_comments/index', 'GET')->pattern(['id' => '\d+']); //取得行程评论
    Route::rule('api/:version/trips/wall/:id/comments', 'api/:version.trip_comments/save', 'POST')->pattern(['id' => '\d+']); //发布行程评论
    Route::rule('api/:version/trips/wall/:id/passengers', 'api/:version.trips/passengers', 'GET')->pattern(['id' => '\d+']);
    Route::rule('api/:version/trips/wall/:id', 'api/:version.trips/wall_detail', 'GET')->pattern(['id' => '\d+']); //空坐位详情
    Route::rule('api/:version/trips/wall', 'api/:version.trips/wall_list', 'GET');

    Route::rule('api/:version/trips/info/my', 'api/:version.trips/my_info', 'GET'); //我的行程的所有info
    Route::rule('api/:version/trips/info/:id', 'api/:version.trips/info_detail', 'GET')->pattern(['id' => '\d+']); //约车需求详情
    Route::rule('api/:version/trips/info', 'api/:version.trips/info_list', 'GET'); //我的行程

    Route::rule('api/:version/trips/map_cars', 'api/:version.trips/map_cars', 'GET'); //显示在地图上的行程
    Route::rule('api/:version/trips/history', 'api/:version.trips/history', 'GET');
    Route::rule('api/:version/trips/check_my_status', 'api/:version.trips/check_my_status', 'GET');
    Route::rule('api/:version/trips', 'api/:version.trips/index', 'GET'); //我的行程

    Route::rule('api/:version/trips/:from', 'api/:version.trips/add', 'POST'); //发布行程
    Route::rule('api/:version/trips/:from/:id', 'api/:version.trips/change', 'PATCH')->pattern(['id' => '\d+']);// 变更行程（搭车，取消，完结）
    Route::rule('api/:version/trips/:from/:id', 'api/:version.trips/cancel', 'DELETE')->pattern(['id' => '\d+']); //取消行程
    Route::rule('api/:version/trip_active/gps', 'api/:version.trip_active/gps', 'GET');


    //班车行程相关
    Route::rule('api/:version/shuttle/line/common$', 'api/:version.shuttle.line/common', 'GET'); // 常用路线列表
    // Route::rule('api/:version/shuttle/line/companys$', 'api/:version.shuttle.line/companys', 'GET'); // 路线公司列表    
    Route::rule('api/:version/shuttle/line$', 'api/:version.shuttle.line/index', 'GET'); // 路线列表
    Route::rule('api/:version/shuttle/time$', 'api/:version.shuttle.time/index', 'GET'); // 时刻列表
    Route::rule('api/:version/shuttle/trip/cars$', 'api/:version.shuttle.trip/cars', 'GET'); // 班车空座位列表
    Route::rule('api/:version/shuttle/trip/map_cars$', 'api/:version.shuttle.trip/map_cars', 'GET'); // 地图上的空座位列表
    Route::rule('api/:version/shuttle/trip/requests$', 'api/:version.shuttle.trip/requests', 'GET'); // 班车需求列表
    Route::rule('api/:version/shuttle/trip/my$', 'api/:version.shuttle.trip/my', 'GET'); // 历史行程
    Route::rule('api/:version/shuttle/trip/history$', 'api/:version.shuttle.trip/history', 'GET'); // 我的行程
    Route::rule('api/:version/shuttle/trip/:id/matching$', 'api/:version.shuttle.trip/matching', 'GET')->pattern(['id' => '\d+']); // 匹配行程
    Route::rule('api/:version/shuttle/trip/:id/merge$', 'api/:version.shuttle.trip/merge', 'PATCH')->pattern(['id' => '\d+']); // 合并行程
    Route::rule('api/:version/shuttle/trip/:id/pickup$', 'api/:version.shuttle.trip/pickup', 'POST')->pattern(['id' => '\d+']); // 接乘客
    Route::rule('api/:version/shuttle/trip/:id/hitchhiking$', 'api/:version.shuttle.trip/hitchhiking', 'POST')->pattern(['id' => '\d+']); // 乘客上车
    Route::rule('api/:version/shuttle/trip$', 'api/:version.shuttle.trip/save', 'POST'); // 发布班车行程
    Route::rule('api/:version/shuttle/trip/:id/passengers$', 'api/:version.shuttle.trip/passengers', 'GET')->pattern(['id' => '\d+']); //行程的乘客列表
    Route::rule('api/:version/shuttle/trip/:id/partners$', 'api/:version.shuttle.partner/list?from=shuttle_trip', 'GET')->pattern(['id' => '\d+']); // 我行程的同行者
    Route::rule('api/:version/shuttle/trip/:id', 'api/:version.shuttle.trip/show', 'GET')->pattern(['id' => '\d+']); //行程明细
    Route::rule('api/:version/shuttle/trip/:id', 'api/:version.shuttle.trip/change', 'PATCH')->pattern(['id' => '\d+']); // 变更行程（修改座位数，取消，完结）
    
    // 同行者相关
    Route::rule('api/:version/shuttle/partner/my$', 'api/:version.shuttle.partner/my', 'GET'); // 我的常选同行者
    Route::rule('api/:version/shuttle/partner$', 'api/:version.shuttle.partner/list', 'GET'); // 行程的同行者列表
    Route::rule('api/:version/shuttle/partner$', 'api/:version.shuttle.partner/save', 'POST'); // 添加同行者
    Route::rule('api/:version/shuttle/partner/:id', 'api/:version.shuttle.partner/del', 'DELETE'); // 删除同行者

    // 混合行程
    Route::rule('api/:version/mixtrip/my_coming$', 'api/:version.trip.mix_trip/my_coming', 'GET'); // 取得我将要发生的行程列表，包括普通行程和班车行程
    Route::rule('api/:version/mixtrip/my$', 'api/:version.trip.mix_trip/my', 'GET'); // 取得我将要发生的行程列表，包括普通行程和班车行程
    Route::rule('api/:version/mixtrip/cars$', 'api/:version.trip.mix_trip/cars', 'GET'); // 混合行程cars
    Route::rule('api/:version/mixtrip/requests$', 'api/:version.trip.mix_trip/requests', 'GET'); // 混合行程cars
    Route::rule('api/:version/mixtrip/rclist$', 'api/:version.trip.mix_trip/rclist', 'GET'); // 混合行程rclist
    Route::rule('api/:version/mixtrip/history$', 'api/:version.trip.mix_trip/history', 'GET'); // 混合历史行程


    //地址相关
    Route::rule('api/:version/address/:id', 'api/:version.address/read', 'GET')->pattern(['id' => '\d+']);
    Route::rule('api/:version/address/my', 'api/:version.address/my', 'GET');
    Route::rule('api/:version/address/citys', 'api/:version.address/citys', 'GET');
    Route::rule('api/:version/address', 'api/:version.address/save', 'POST');
    Route::rule('api/:version/trip_address/citys$', 'api/:version.shuttle.trip_address/citys', 'GET');
    Route::rule('api/:version/trip_address/my$', 'api/:version.shuttle.trip_address/my', 'GET');
    Route::rule('api/:version/trip_address/companys$', 'api/:version.shuttle.trip_address/companys', 'GET');


    Route::rule('api/:version/grade$', 'api/:version.grade/index', 'GET');
    Route::rule('api/:version/grade$', 'api/:version.grade/save', 'POST');

    //用户相关
    Route::resource('api/:version/user', 'api/:version.user');
    Route::rule('api/:version/user/recommendation', 'api/:version.user/recommendation', 'GET');
    Route::rule('api/:version/user/:id/position', 'api/:version.user_position/read', 'GET');
    Route::rule('api/:version/user/:id/position', 'api/:version.user_position/save', 'POST');

    //部门相关
    Route::rule('api/:version/department/selects$', 'api/:version.department/selects', 'GET');

    //im_group
    Route::rule('api/:version/im_group/placeholders', 'api/:version.im_group/placeholders', 'GET');
    Route::rule('api/:version/im_group/invitation', 'api/:version.im_group/invitation', 'GET');
    Route::rule('api/:version/im_group/external_invite_writeback', 'api/:version.im_group/external_invite_writeback', 'POST');
    Route::rule('api/:version/im_group/external_invite', 'api/:version.im_group/external_invite', 'POST');
    Route::rule('api/:version/im_group/kick_placeholder', 'api/:version.im_group/kick_placeholder', 'DELETE');
    Route::rule('api/:version/im_group/signin_invitation', 'api/:version.im_group/signin_invitation', 'POST');



    //*********公开的
    Route::resource('api/:version/publics/idle', 'api/:version.publics.idle');
    Route::rule('api/:version/publics/comments', 'api/:version.publics.comments/index', 'GET');
    Route::rule('api/:version/app/url', 'api/:version.app/get_url', 'GET');
    
    //报表相关
    Route::rule('api/:version/publics/reports/trips_summary', 'api/:version.publics.reports/trips_summary', 'GET');
    Route::rule('api/:version/publics/reports/month_statis', 'api/:version.publics.reports/month_statis', 'GET');
    Route::rule('api/:version/publics/reports/month_ranking', 'api/:version.publics.reports/month_ranking', 'GET');
    Route::rule('api/:version/publics/reports/today_info', 'api/:version.publics.reports/today_info', 'GET');
    Route::rule('api/:version/publics/reports/user_carpool_statis', 'api/:version.publics.reports/user_carpool_statis', 'GET');
});
// ->header('Access-Control-Allow-Headers', $allowHeader)->allowCrossDomain();

// Route::resource('api/:version/docs','api/:version.docs')->header('Access-Control-Allow-Headers', $allowHeader)->allowCrossDomain();
// Route::resource('api/:version/passport','api/:version.passport')->header('Access-Control-Allow-Headers', $allowHeader)->allowCrossDomain();
