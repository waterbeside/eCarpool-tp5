<?php

namespace app\npd\model;

use think\Db;
use think\Model;
use my\RedisData;

class Category extends Model
{
    protected $insert = ['create_time'];

    protected $connection = 'database_npd';
    protected $table = 't_category';
    protected $pk = 'id';

    protected static function init()
    {
        parent::init();
        self::event('after_insert', function ($category) {
            $pid = $category->parent_id;
            if ($pid > 0) {
                $parent         = self::get($pid);
                $category->path = $parent->path . $pid . ',';
            } else {
                $category->path = 0 . ',';
            }

            $category->save();
        });

        self::event('after_update', function ($category) {
            $id   = $category->id;
            $pid  = $category->parent_id;
            $data = [];
            if ($pid == 0) {
                $data['path'] = 0 . ',';
            } else {
                $parent       = self::get($pid);
                $data['path'] = $parent->path . $pid . ',';
            }

            if ($category->where('id', $id)->update($data) !== false) {
                $children = self::all(['path' => ['like', "%{$id},%"]]);
                foreach ($children as $value) {
                    $value->path = $data['path'] . $id . ',';
                    $value->save();
                }
            }
        });
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
    public function getChildrensId($pid = 0, $deep = 0, $map_ex = null)
    {
        $map = [
            ['parent_id', '=', $pid],
        ];
        if (is_array($map_ex)) {
            $map = array_merge($map, $map_ex);
        }
        $data = $this->where($map)->column('id');
        foreach ($data as $key => $value) {
            $children_next = $this->getChildrensId($value, $deep + 1);
            if ($children_next) {
                $data = array_merge($data, $children_next);
            }
        }
        return  $deep ? $data : array_merge($data, [intval($pid)]);
    }


    /**
     * 取得栏目的所有子栏目id
     *
     * @param integer $pid 当前栏目id
     * @param integer $exp 缓存时间
     * @return array
     */
    public function getCateChildrenIds($pid, $model, $exp = 3600 * 24)
    {
        $redis = new RedisData();
        $cacheKey = "NPD:category:children_id,id:model_$model:pid_{$pid}";
        $cacheData = $redis->cache($cacheKey);
        if ($cacheData) {
            return $cacheData;
        }
        $CategoryModel = new Category();
        $map = [
            ['is_delete', '=', 0],
            ['status', '=', 1],
            ['model', '=', $model]
        ];
        $cate_Ids = $CategoryModel->getChildrensId($pid, 0, $map);
        if ($cate_Ids) {
            $redis->cache($cacheKey, $cate_Ids, $exp);
        }
        return $cate_Ids;
    }



    /**
     * 取得列表，如果redis有
     * @param  integer $exp 过期时间
     * @return array
     */
    public function getList($exp = 3600 * 2)
    {
        $rKey = "NPD:category:list";
        $redis = new RedisData();
        $data = json_decode($redis->get($rKey), true);
        if ($exp === -2) {
            return $data;
        }
        if (!$data || $exp === -1) {
            $data  = $this->where([['is_delete', '=', 0]])->order(['sort' => 'DESC', 'id' => 'ASC'])->select()->toArray();
            $redis->setex($rKey, $exp, json_encode($data));
        }
        return $data;
    }


    public function deleteListCache()
    {
        $redis = new RedisData();
        $redis->delete("NPD:category:list");
    }


    public function getListByModel($model = '', $status = false)
    {
        $res = $this->getList();
        $returnList = [];
        foreach ($res as $key => $value) {
            if (($model === '' || $value['model'] == $model) && ($status === false || $value['status'] == $status)) {
                $returnList[] = $value;
            }
        }
        return $returnList;
    }


    /**
     * 取得分类详情
     *
     * @param integer $cid 分类id
     * @param integer $exp 缓存时间
     * @return void
     */
    public function getDetail($cid, $exp = 3600 * 24)
    {
        $redis = new RedisData();
        $cacheKey = "NPD:category:detail:$cid";
        $cacheData = $redis->cache($cacheKey);
        if ($cacheData) {
            return $cacheData;
        }
        $data = Category::find($cid);
        if ($data) {
            $redis->cache($cacheKey, $data, $exp);
        }
        return $data;
    }


    /**
     * 取得栏目面包屑导航列精数据
     *
     * @param integer|string|array $cid integer:栏目id,string:栏目path,array:栏目数据
     * @return array
     */
    public function getCateBreadcrumb($cid, $model = '')
    {
        if (is_numeric($cid)) {
            $cateDetail = $this->getDetail($cid);
            if (!$cateDetail) {
                return false;
            }
            $path = $cateDetail['path'] . $cid;
        } elseif (is_array($cid) && isset($cid['path'])) {
            $path = $cid['path'] . $cid['id'];
        } else {
            return false;
        }
        $path_arr = explode(',', $path);
        $list_data = $this->getListByModel($model, 1);
        $list_temp = [];
        foreach ($list_data as $k => $v) {
            if (in_array($v['id'], $path_arr)) {
                $list_temp[$v['id']] = $v;
            }
        }
        $list = [];
        foreach ($path_arr as $k => $v) {
            if (isset($list_temp[$v])) {
                $item = $list_temp[$v];
                $list[] =  [
                    'id' => $item['id'],
                    'parent_id' => $item['parent_id'],
                    'name' => $item['name'],
                    'name_en' => $item['name_en'],
                    'icon' => $item['icon'],
                    'path' => $item['path'],
                    'model' => $item['model'],
                    'children_count' => $this->getChildCount($item['id'], 1),
                ];
            }
        }
        return $list;
    }

    /**
     * 查询有多少字栏目
     *
     * @param integer $cid 要查的栏目id
     * @param integer $isOnlyActive 是否只包括有效的
     * @return integer
     */
    public function getChildCount($cid, $isOnlyActive = 0)
    {
        $list = $this->getList();
        $c = 0;
        foreach ($list as $key => $value) {
            if ($cid == $value['parent_id'] && ( $isOnlyActive === 0 || ($isOnlyActive && $value['status']))) {
                $c ++;
            }
        }
        return $c;
    }
}
