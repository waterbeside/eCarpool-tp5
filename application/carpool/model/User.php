<?php
namespace app\carpool\model;

use think\Model;

class User extends Model
{
    // protected $insert = ['create_time'];

    /**
     * 创建时间
     * @return bool|string
     */
    /*protected function setCreateTimeAttr()
    {
        return date('Y-m-d H:i:s');
    }*/

    // 直接使用配置参数名
   protected $connection = 'database_carpool';

   protected $pk = 'uid';

   public function getDetail($account="",$uid=0){
     if(!$account && !$uid){
       return false;
     }
     $carpoolUser_jion =  [
       ['company c','u.company_id = c.company_id','left'],
     ];
     return  $this->alias('u')->join($carpoolUser_jion)->where(['loginname'=>$account])->find();

   }


}
