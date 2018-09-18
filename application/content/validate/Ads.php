<?php
namespace app\content\validate;

use think\Validate;

class Ads extends Validate
{
    protected $rule = [
        // 'cid'   => 'require',
        'title' => 'require',
    ];

    protected $message = [
        // 'cid.require'   => '请选择所属分类',
        'title.require' => '请输入标题',
    ];
}
