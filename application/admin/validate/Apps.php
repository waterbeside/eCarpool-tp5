<?php

namespace app\admin\validate;

use think\Validate;

/**
 * Apps验证器
 * Class Apps
 * @package app\admin\validate
 */
class Apps extends Validate
{
    protected $rule = [
        'name' => 'require',
        'domain' => 'require',
    ];

    protected $message = [
        'name.require' => '请输入名称',
        'domain.require' => '请输入域名'
    ];
}
