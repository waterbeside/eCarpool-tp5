<?php

namespace app\api\controller\v1;

use app\api\controller\ApiBase;
use app\carpool\model\UserPosition;
use app\carpool\model\User as UserModel;
use app\user\model\Department as DepartmentModel;

use think\Db;

/**
 * 用户相关接口
 * Class User
 * @package app\api\controller\v1
 */
class User extends ApiBase
{

  protected function initialize()
  {
    parent::initialize();
    // $this->checkPassport(1);
  }


  /**
   * 读取用户信息
   * 
   * @param  int  $id 用户id
   */
  public function read($id)
  {
    $this->checkPassport(1);
    $field = 't.uid, t.name , t.loginname, t.phone, t.mobile, t.sex,  t.Department as department, d.fullname as full_department';
    $map   = [];
    $map[] = ['is_delete', '<>', 1];
    $map[] = ['uid', '=', $id];
    $join[] = ['t_department d', 'd.id = t.department_id', 'left'];
    $data  = UserModel::field($field)->alias('t')->join($join)->where($map)->find();
    if (!$data) {
      return $this->jsonReturn(20002, $data);
    }
    return $this->jsonReturn(0, $data);
  }

  /**
   * 用户推荐
   * @param  boolean,integer $type         [场境，1为推荐同部门好友，2为推荐搭过车的好友，0为从1和2场境中抽取若干好友]
   * @param  integer         $limit        [抽取条数]
   * @param  boolean         $isAjaxReturn [1为输出json ,0为return 列表数组]
   * @return array
   */
  public function recommendation($type = 0, $limit = 20, $isJsonReturn = true)
  {
    $userData = $this->getUserData(1);
    $uid =  $userData['uid'];

    switch ($type) {
      case 1:  //推荐同部门好友

        $map = [
          ['is_active', '=', 1],
          ['is_delete', '=', 0],
          ['company_id', '=', $userData['company_id']],
          ['uid', '<>', $uid],
          ['', 'exp', Db::raw('im_md5password IS NOT NULL')],
        ];
        if ($userData['department_id'] > 0) {
          $map[] = ['department_id', '=', $userData['department_id']];
        }

        $fields = "uid, im_id, name, imgpath, department , department_id, company_id";
        $res = UserModel::field($fields)->alias('t')->where($map)->order(Db::raw('rand()'))->limit($limit)->select();
        if(!$res){
          return $isJsonReturn ? $this->jsonReturn(20002, 'No data') : [];
        }
        $user_list = [];

        foreach ($res as $key => $value) {
          $returnValue = $value;
          $returnValue["avatar"] = $value['imgpath'] ? $value['imgpath'] : "im/default.png";
          unset($returnValue["imgpath"]);
          $user_list[] = $returnValue;
        }

        return $isJsonReturn ? $this->jsonReturn(0, array("lists" => $user_list), "success") : $user_list;
      

        break;
      case 2: //推荐拼过车的好友

        $sql = "SELECT DISTINCT t.uid ,  u.im_id, u.name, u.imgpath, u.department, u.department_id, u.company_id FROM
            (SELECT DISTINCT
              if(i.passengerid <> $uid,i.passengerid,i.carownid) AS uid , time
              FROM info as i
              WHERE (i.carownid =  '$uid' OR  i.passengerid =  '$uid') AND i.passengerid IS NOT NULL AND i.carownid IS NOT NULL
              ORDER BY i.time DESC
            ) as t
            LEFT JOIN user AS u ON t.uid = u.uid AND u.is_active = 1 AND u.is_delete = 0 AND im_md5password IS NOT NULL
            WHERE u.uid <> $uid LIMIT $limit
          ";
        $res  =  Db::connect('database_carpool')->query($sql);
        if (!$res) {
          return $isJsonReturn ? $this->jsonReturn(20002, 'No data') : [];
        }
   
        $user_list = [];
        foreach ($res as $key => $value) {
          $returnValue = $value;
          $returnValue["avatar"] = $value['imgpath'] ? $value['imgpath'] : "im/default.png";

          $user_list[] = $returnValue;
        }
        return $isJsonReturn ? $this->jsonReturn(0, array("lists" => $user_list), "success") : $user_list;

        break;

      default:  //随机查找好友

        $user_list_01 = $this->recommendation(1, $limit, false);
        $user_list_02 = $this->recommendation(2, $limit, false);
        $user_list = array_merge($user_list_02, $user_list_01);
        $user_list = $this->arrayUniqByKey($user_list, "uid");
        shuffle($user_list); //随机排序数组
        $user_list = array_slice($user_list, 0, $limit);
        // var_dump(count($user_list));
        $this->jsonReturn(0, array("lists" => $user_list), "success");

        break;
    }

  }




}
