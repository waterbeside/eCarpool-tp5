<?php

namespace app\content\model;

use think\Db;
use think\Model;

class CommonNotice extends Model
{

    protected $connection = 'database_carpool';
    protected $table = 't_common_notice';
    protected $pk = 'id';

    protected static function init()
    {
        parent::init();
    }



    /**
     * 自动生成时间
     * @return bool|strings
     */
    protected function setCreateTimeAttr()
    {
        return date('Y-m-d H:i:s');
    }




    /**
     * 取得列表，如果redis有
     * @param  integer $recache [description]
     * @return [type]           [description]
     */
    public function getList($recache = 0)
    {
        $rKey = "common:notice:list";
        $redis = new RedisData();
        $data = json_decode($redis->get($rKey), true);

        if (!$data || $recache) {
            $data  = $this->where([['is_delete', '=', Db::raw(0)]])->order(['sort' => 'DESC', 'id' => 'ASC'])->select()->toArray();
            $redis->set($rKey, json_encode($data));
        }
        return $data;
    }


    public function deleteListCache()
    {
        $redis = new RedisData();
        $redis->delete("common:notice:list");
    }
}
