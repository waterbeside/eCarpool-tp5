<?php

namespace app\content\validate;

use think\Validate;

class Idle extends Validate
{
    protected $rule = [
        'title' => 'require',
        'categories' => 'checkCategories'
    ];

    protected $message = [
        'title.require' => '请输入标题',
        // 'categories' => '',
    ];

    // 自定义验证规则
    protected function checkCategories($value)
    {
        if (!is_array($value)) {
            return '参数格式不正角';
        }
        if (empty($value)) {
            return '请选择分类';
        }
        return true;
    }
}
