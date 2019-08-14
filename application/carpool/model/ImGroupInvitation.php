<?php

namespace app\carpool\model;

use think\Model;

class ImGroupInvitation extends Model
{

    // 设置当前模型对应的完整数据表名称
    protected $table = 'im_group_invitation';

    // 直接使用配置参数名
    protected $connection = 'database_carpool';

    protected $pk = 'id';



    /**
     * 创建链接唯一随机码。
     */
    public function create_link_code($len = 6)
    {
        $link_code = strtolower(getRandomString($len));
        $map = [
            ['link_code', '=', $link_code]
        ];
        $count_checkHas  = $this->where($map)->count();
        if ($count_checkHas > 0) {
            return $this->create_link_code($len);
        } else {
            return $link_code;
        }
    }
}
