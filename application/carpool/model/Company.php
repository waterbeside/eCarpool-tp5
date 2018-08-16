<?php
namespace app\carpool\model;

use think\facade\Cache;
use think\Model;

class Company extends Model
{

  // 设置当前模型对应的完整数据表名称
  protected $table = 'company';

    // 直接使用配置参数名
   protected $connection = 'database_carpool';

   protected $pk = 'company_id';

   protected $insert = ['create_date','status' => 1,'is_sync_photo'=>0];
   protected $update = [];

    protected function setCreateDateAttr()
    {
        return date('Y-m-d');
    }

    public function getCompanys(){
      $lists_cache = Cache::tag('public')->get('companys');
      if($lists_cache){
        $lists = $lists_cache;
      }else{
        $lists = $this->order('company_id ASC , company_name ')->select();
        if($lists){
          Cache::tag('public')->set('companys',$lists,3600*24);
        }
      }
      return $lists;
    }

}
