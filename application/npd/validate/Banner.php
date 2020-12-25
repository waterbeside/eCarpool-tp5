<?php

namespace app\npd\validate;

use think\Validate;

class Banner extends Validate
{
    protected $rule = [
        // 'cid'   => 'require',
        'title' => 'require',
        'site_id'  => 'require|min:1',
    ];

    protected $message = [
        // 'cid.require'   => '请选择所属分类',
        'title.require' => '请输入标题',
        'site_id.min'     => '请选择站点',
        'site_id.require'     => '请选择站点',
    ];

    protected $scene = [
        'edit'  =>  ['title', 'sort'],
    ];
}
