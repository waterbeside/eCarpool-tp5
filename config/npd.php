<?php
return [
    "banner_type" => [
        '1' => '首页Banner',
    ],

    "category_model_list" => [
        [
            'value' => 'single',
            'name' => '单页模型'
        ], [
            'value' => 'product',
            'name' => '产品模型'
        ], [
            'value' => 'article',
            'name' => '文章模型'
        ]
    ],

    "customer_group" => [
        'europe',
        'asia',
        'north america',
        'other',
    ],

    "replace_attachment_url" => [
        'http://gitsite.net:8082/' => 'https://cm.gitsite.net/',
    ],

    "patent_type" => [
        ['name' => '发明专利', 'name_en' => 'Invention patent'],
        ['name' => '实用新型专利', 'name_en' => 'Utility model patent'],
        ['name' => '外观设计专利', 'name_en' => 'Design patent'],
    ],

    "carpool_user_access" => 2 // 是否允许 carpool用户庐问，0，不允许，1允许，2，授权过的指定用户允许。
];
