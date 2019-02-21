<?php
namespace app\api\controller\v1;

use app\api\controller\ApiBase;
use app\carpool\model\UserPosition;
use app\carpool\model\User as UserModel;
use app\user\model\Department as DepartmentModel;

use think\Db;

/**
 * 用户相关接口
 * Class Docs
 * @package app\api\controller
 */
class User extends ApiBase
{

    protected function initialize()
    {
        parent::initialize();
        // $this->checkPassport(1);
    }


    /**
     *
     * @param  int  $id
     */
    public function read($id)
    {
      $this->checkPassport(1);
      $field = 't.uid, t.name , t.loginname, t.phone, t.mobile, t.sex,  t.Department as department, d.fullname as full_department';
      $map   = [];
      $map[] = ['is_delete','<>',1];
      $map[] = ['uid','=',$id];
      $join[] = ['t_department d','d.id = t.department_id', 'left'];
      $data  = UserModel::field($field)->alias('t')->join($join)->where($map)->find();
      if(!$data){
        return $this->jsonReturn(20002,$data);
      }
      return $this->jsonReturn(0,$data);
    }



}
