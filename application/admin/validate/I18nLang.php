<?php

namespace app\admin\validate;

use think\Validate;

class I18nLang extends Validate
{

    protected $rule = [
        'code'   => 'require|unique:I18nLang,code',
        'name'   => 'require|unique:I18nLang,name',
    ];
    protected $msg = [
        'code.require' => '请填写语言码',
        'code.unique' => '该语言码已经存在',
        'name.require' => '请填写语言名称',
        'name.unique' => '该语言名称已经存在',
    ];
}
