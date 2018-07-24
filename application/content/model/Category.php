<?php
namespace app\content\model;

use think\Db;
use think\Model;

class Category extends Model
{
    protected $insert = ['create_time'];

    protected $connection = 'database_content';
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
    public function getChildrensId($pid = 0, $deep = 0){
      $data = $this->where([['parent_id','=', $pid]])->column('id');
      foreach ($data as $key => $value) {
        $children_next = $this->getChildrensId($value, $deep+1);
        if($children_next){
          $data = array_merge($data,$children_next);
        }
      }
      return  $deep ? $data : array_merge($data,[intval($pid)]) ;
    }




}
