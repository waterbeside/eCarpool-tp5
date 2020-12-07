<?php

namespace app\npd\validate;

use think\Validate;

class Article extends Validate
{
    protected $rule = [
        // 'cid'   => 'require',
        'title' => 'require',
        'sort'  => 'require|number',
        'site_id'  => 'require|min:1',
    ];

    protected $message = [
        // 'cid.require'   => '请选择所属分类',
        'title.require' => '请输入标题',
        'sort.require'  => '请输入排序',
        'sort.number'   => '排序只能填写数字',
        'site_id.min'     => '请选择站点',
        'site_id.require'     => '请选择站点',
    ];

    protected $scene = [
        'edit'  =>  ['title', 'sort'],
    ];
}
