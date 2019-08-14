<?php

namespace app\admin\validate;

use think\Validate;

/**
 * 推送消息验证
 * Class Slide
 * @package app\admin\validate
 */
class Pushmsg extends Validate
{
    protected $rule = [
        'body'  => 'require',
        'title' => 'require',
    ];

    protected $message = [
        'title.require'  => '请填写标题',
        'body.require' => '请填写内容',
    ];
}
