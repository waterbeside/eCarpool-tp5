<?php

namespace app\content\model;

use think\Db;
use think\Model;
use my\RedisData;

class Category extends Model
{
    protected $insert = ['create_time'];

    protected $connection = 'database_carpool';
    protected $table = 't_category';
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
     * 取得所有子栏目id
     * @param  integer $pid  当前栏目id
     * @param  integer $deep  0 包抱当前栏目id， 1不包括
     */
    public function getChildrensId($pid = 0, $deep = 0)
    {
        $data = $this->where([['parent_id', '=', $pid]])->column('id');
        foreach ($data as $key => $value) {
            $children_next = $this->getChildrensId($value, $deep + 1);
            if ($children_next) {
                $data = array_merge($data, $children_next);
            }
        }
        return  $deep ? $data : array_merge($data, [intval($pid)]);
    }


    /**
     * 取得列表，如果redis有
     * @param  integer $recache [description]
     * @return [type]           [description]
     */
    public function getList($recache = 0)
    {
        $rKey = "carpool:category:list";
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
        $redis->del("carpool:category:list");
    }
}
