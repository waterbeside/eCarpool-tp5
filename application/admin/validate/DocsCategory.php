<?php
namespace app\admin\validate;

use think\Validate;

class DocsCategory extends Validate
{
    protected $rule = [
        'title'  => 'require',
        'name' => 'require|unique:DocsCategory',
        'listorder' => 'require|number'
    ];

    protected $message = [
        'title.require'  => '请输入标题',
        'name.require' => '请输入分类标识',
        'name.unique' => '该分类标识已存在',
        'listorder.require' => '请输入排序',
        'listorder.number'  => '排序只能填写数字'
    ];
}
