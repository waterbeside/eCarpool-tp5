<?php

namespace app\user\model;

use think\Model;

class UserOauth extends Model
{

    // 设置当前模型对应的完整数据表名称
    protected $table = 't_user_oauth';


    // 直接使用配置参数名
    protected $connection = 'database_carpool';
    protected $pk = 'id';

    /**
     * 按条件解绑
     *
     * @param array $map 筛选条件
     */
    public function unbind($map)
    {
        $upData = [
            'is_delete' => 1,
            'unbinding_date' => date("Y-m-d H:i:s"),
        ];
        $res = $this->where($map)->update($upData);
        return $res;
    }

    /**
     * 跟据uid解绑
     *
     * @param integer $uid 用户id
     */
    public function unbindByUid($uid)
    {
        $map = [
            ['user_id', '=', $uid]
        ];
        return $this->unbind($map);
    }
}
