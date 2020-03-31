<?php

namespace app\content\model;

use think\Db;
use think\Model;
use my\RedisData;

class Label extends Model
{

    protected $connection = 'database_carpool';
    protected $table = 't_label';
    protected $pk = 'id';

    protected static function init()
    {
        parent::init();
    }



    /**
     * 自动生成时间
     * @return bool|string
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
        $rKey = "carpool:label:list";
        $redis = RedisData::getInstance();
        $data = json_decode($redis->get($rKey), true);

        if (!$data || $recache) {
            $data  = $this->where([['is_delete', '=', Db::raw(0)]])->order(['sort' => 'DESC', 'id' => 'ASC'])->select()->toArray();
            $redis->set($rKey, json_encode($data));
        }
        return $data;
    }


    public function deleteListCache()
    {
        $redis = RedisData::getInstance();
        $redis->del("carpool:label:list");
    }
}
