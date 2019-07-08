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
    public function getChildrensId($pid = 0, $deep = 0)
    {
      $data = $this->where([['parent_id','=', $pid]])->column('id');
      foreach ($data as $key => $value) {
        $children_next = $this->getChildrensId($value, $deep+1);
        if($children_next){
          $data = array_merge($data,$children_next);
        }
      }
      return  $deep ? $data : array_merge($data,[intval($pid)]) ;
    }


    /**
     * 取得列表，如果redis有
     * @param  integer $exp 过期时间
     * @return array           
     */
    public function getList($exp = 3600 * 2 )
    {
      $rKey = "NPD:category:list";
      $redis = new RedisData();
      $data = json_decode($redis->get($rKey),true);

      if(!$data || $exp === -1){
        $data  = $this->where([['is_delete','=',0]])->order(['sort' => 'DESC', 'id' => 'ASC'])->select()->toArray();
        $redis->setex($rKey,$exp,json_encode($data));
      }
      return $data;
    }


    public function deleteListCache()
    {
      $redis = new RedisData();
      $redis->delete("NPD:category:list");

    }


    public function getListByModel($model='',$status = false){
      $res = $this->getList();
      $returnList = [];
      foreach($res as $key=>$value){
        if( ($model === '' || $value['model'] == $model) && ($status === false || $value['status'] == $status) ){
          $returnList[] = $value;
        }
      }
      return $returnList;
    }




}
