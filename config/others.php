<?php

return [
    'app_id_list' => [
        "1" => '溢起拼车',
        "2" => '国际版APP',
    ],
    'platform_list' => [
        "0" => '未知',
        "1" => 'iOS',
        "2" => 'Android',
        "3" => 'H5',
        "4" => 'master',
    ],
    'user_oauth_type' => [
        '1' => '微信',
    ],
    'local_hr_sync_api' => [
        'all' => 'http://127.0.0.1:8082/api/v1/sync_hr/all',
        'single' => 'http://127.0.0.1:8082/api/v1/sync_hr/single'
    ],
    'langs_select_list' => [
        'zh-cn' => '中文',
        'en' => 'EN',
        'vi' => 'Tiếng việt',
    ],
    //是否开启单点登入
    'is_single_sign' => true,
    //是否过度第一批单点登入用户
    'is_smooth_single_sign' => true,
];
