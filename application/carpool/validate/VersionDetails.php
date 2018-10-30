<?php
namespace app\carpool\validate;

use think\Validate;

class VersionDetails extends Validate
{
    protected $rule = [
      'version_code'  => 'require',
      'language_code'  => 'require',
      'description'  => 'require',
      'app_id'  => 'require|number',

    ];

    protected $message = [
        'version_code.require'  => '版本不能为空',
        'language_code.require'     => '语言不能为空',
        'description.require'     => '描述不能为空',
        'app_id.require'     => 'app_id参数出错',
        'app_id.number'     => 'app_id参数出错',

    ];

    // edit 验证场景定义
    public function sceneEdit()
    {
      return $this->only(['language_code','description']);
    }
}
