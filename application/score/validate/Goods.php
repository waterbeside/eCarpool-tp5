<?php
namespace app\score\validate;

use think\Validate;

class Goods extends Validate
{
    protected $rule = [
      'name'  => 'require',
      'price'  => 'require|float',
      'inventory'  => 'require',
    ];

    protected $message = [
        'name.require'  => '商品名不能为空',
        'price.require'     => '价格不能为空',
        'inventory.require'     => '库存不能为空',
        'price.float'     => '价格必须为数字',
        'inventory.number'     => '库存必须为数字',
    ];


}
