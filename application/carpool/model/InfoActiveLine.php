<?php

namespace app\carpool\model;

use think\Model;

class InfoActiveLine extends Model
{

    // 设置当前模型对应的完整数据表名称
    protected $table = 'info_active_line';


    // 直接使用配置参数名
    protected $connection = 'database_carpool';

    protected $pk = 'info_active_line_id';


    public $errorMsg = "";
    public $errorData = "";


    public function getInfoByData()
    {
        //TODO::取得详情
    }
}
