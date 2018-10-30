<?php
namespace app\carpool\validate;

use think\Validate;

class UpdateVersion extends Validate
{
    protected $rule = [
      'latest_version'  => 'require',
      'current_versioncode'  => 'require|number',
      'min_versioncode'  => 'require|number',
      'max_versioncode'  => 'require|number',

    ];

    protected $message = [
        'latest_version.require'  => '版本不能为空',
        'current_versioncode.require'     => '当前版本号不能为空',
        'current_versioncode.number'     => '当前版本号必须为数字',
        'min_versioncode.require'     => '最小版本号不能为空',
        'min_versioncode.number'     => '最小版本号必须为数字',
        'max_versioncode.require'     => '最大版本号不能为空',
        'max_versioncode.number'     => '最大版本号为数字',

    ];


}
