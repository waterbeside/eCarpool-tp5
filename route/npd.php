<?php


    
Route::group([], function () {
    Route::rule('npd/api/:version/nav', 'npd/api.:version.nav/index', 'GET');
    Route::rule('npd/api/:version/category', 'npd/api.:version.category/index', 'GET');
    Route::rule('npd/api/:version/banner', 'npd/api.:version.banner/index', 'GET');
    Route::rule('npd/api/:version/customer', 'npd/api.:version.customer/index', 'GET');

    Route::resource('npd/api/:version/product', 'npd/api.:version.product');
    Route::resource('npd/api/:version/article', 'npd/api.:version.article');
    Route::rule('npd/api/:version/product_rcm', 'npd/api.:version.product_rcm/index', 'GET');
    Route::rule('npd/api/:version/single', 'npd/api.:version.single/index', 'GET');
    Route::rule('npd/api/:version/single/:id', 'npd/api.:version.single/read', 'GET');

    //通行证相关
    Route::rule('npd/api/:version/passport', 'npd/api.:version.passport/login', 'POST');
    Route::rule('npd/api/:version/passport', 'npd/api.:version.passport/index', 'GET');
    Route::rule('npd/api/:version/passport', 'npd/api.:version.passport/logout', 'DELETE');
});
