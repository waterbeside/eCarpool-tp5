<?php

namespace app\npd\model;

use think\Db;
use think\Model;
use my\RedisData;

class Nav extends Model
{
    protected $insert = ['create_time'];

    protected $connection = 'database_npd';
    protected $table = 't_nav';
    protected $pk = 'id';


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
        $data = $this->where([['pid', '=', $pid]])->column('id');
        foreach ($data as $key => $value) {
            $children_next = $this->getChildrensId($value, $deep + 1);
            if ($children_next) {
                $data = array_merge($data, $children_next);
            }
        }
        return  $deep ? $data : array_merge($data, [intval($pid)]);
    }


    /**
     * 取得列表
     * @param  integer $siteId 站点id
     * @param  integer $exp 缓存过期时间
     * @return array
     */
    public function getList($siteId = 0, $exp = 3600 * 2)
    {
        $rKey = "npd:nav:list";
        $redis = RedisData::getInstance();
        $data = json_decode($redis->get($rKey), true);
        if (!$data || $exp === -1) {
            $data  = $this->where([['is_delete', '=', Db::raw(0)]])->order(['sort' => 'DESC', 'id' => 'ASC'])->select()->toArray();
            $redis->setex($rKey, $exp, json_encode($data));
        }
        if ($siteId > 0) {
            $newData = [];
            foreach ($data as $key => $value) {
                if ($value['site_id'] == $siteId) {
                    $newData[] = $value;
                }
            }
            $data = $newData;
        }
        return $data;
    }


    public function deleteListCache()
    {
        $redis = RedisData::getInstance();
        $redis->del("npd:nav:list");
    }
}
