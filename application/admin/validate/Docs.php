<?php
namespace app\admin\validate;

use think\Validate;

class Docs extends Validate
{
    protected $rule = [
        // 'cid'   => 'require',
        'title' => 'require',
        'listorder'  => 'require|number'
    ];

    protected $message = [
        // 'cid.require'   => '请选择所属分类',
        'title.require' => '请输入标题',
        'listorder.require'  => '请输入排序',
        'listorder.number'   => '排序只能填写数字'
    ];
}
