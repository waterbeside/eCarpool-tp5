<?php
namespace app\api\controller\v1;

use app\api\controller\ApiBase;
use app\carpool\model\User as UserModel;
use Firebase\JWT\JWT;
use think\Db;

/**
 * 发放通行证jwt
 * Class Passport
 * @package app\api\controller
 */
class Passport extends ApiBase
{

    protected function initialize()
    {
        parent::initialize();
    }

    /**
     * 验证登入
     */
    public function index()
    {
       $this->checkPassport(true);
       $more = request()->param('more');
       $type = $more == 1 ? 1 : request()->param('type');
       $userInfo = $this->userBaseInfo;
       if(in_array($type,[1,2])){
         $uid = $userInfo['uid'];
         $userInfo_ex = $this->getUserData(true);
         if($type==2){
           $userInfo = $userInfo_ex;
           unset($userInfo['passwd']);
           unset($userInfo['md5password']);
         }
         if($type == 1){
           $userInfo['name'] = $userInfo_ex['name'];
           $userInfo['Department'] = $userInfo_ex['Department'];
         }
         $userInfo['avatar'] = $userInfo_ex['imgpath'];

       }


       return $this->jsonReturn(0,$userInfo,"success");

    }



    /**
     * 登入，并生成jwt反回
     * @return mixed
     */
    public function save()
    {
        $data = $this->request->post();
        if(empty($data['username'])){
          $this->jsonReturn(-10002,'请输入用户名');
        }
        if(empty($data['password'])){
          $this->jsonReturn(-10002,'请输入密码');
        }
        $data['client'] = isset($data['client']) ? strtolower($data['client']) : '';
        if(!in_array($data['client'],array('ios','android','h5','web','third'))){
          $this->jsonReturn(-1,'client error');
  			};


        $userModel = new UserModel();
        $userData = $userModel->where('loginname',$data['username'])->find();
    		if(!$userData){
          $userData = $userModel->where('phone',$data['username'])->find();
    			if(!$userData){
            $this->jsonReturn(10001,'用户名或密码错误');
    				return false;
    			}
    		}
        if(!$userData['is_active']){
          $this->jsonReturn(10003,'该用户被封禁');
        }

    		if(strtolower($userData['md5password']) != strtolower($userModel->hashPassword($data['password']))){
          $this->jsonReturn(10001,'用户名或密码错误');

    		}


        if(!$userData){
          $this->jsonReturn(10001,'用户名或密码错误');

        }

        $jwt = $this->createPassportJwt(['uid'=>$userData['uid'],'loginname'=>$userData['loginname'],'client' => $data['client']]);
        $returnData = array(
    				'user' => array(
    					'uid'=> $userData['uid'],
    					'loginname' => $userData['loginname'],
    					'name'=> $userData['name'],
    					'company_id'=>$userData['company_id'],
    					'avatar' => $userData['imgpath'],
    				),
    				'token'	=> $jwt
    			);
          $isAllUserData = in_array($data['client'],['ios','android']) ? 1 : 0;
    			if($isAllUserData){
    				$returnData['user'] = $userData;
    				if(isset($returnData['user']['md5password'])){
    					$returnData['user']['md5password'] = '';
    				}
    				if(isset($returnData['user']['passwd'])){
    					$returnData['user']['passwd'] = '';
    				}
    			}

          return $this->jsonReturn(0,$returnData,"success");


    }

    public function update_field($field=""){
      $fields = array('carnumber','carcolor','cartype','password','sex','company_id','department','name');
      if(!in_array($field,$fields)){
        return $this->jsonReturn(-10002,"Error");
      }


        $type = $this->sPost('type');
        $val =  $this->sPost($type);
        $uid = $this->userBaseInfo->uid;
        $type = strtolower($type);
        $userData = $this->getUser();

        switch ($type) {
          case 'password':
            $old_password = trim($val);
            // $userInfo = $this->getUser();
            if( $old_password ==''/* ||  md5($old_password) != $userInfo['md5password']*/){
              $this->ajaxReturn(-10001,[],'旧密码不能为空');
            }
            if($userData->md5password != md5($old_password)){
              $this->ajaxReturn(10001,[],'请输入正确的旧密码');
            }
            $pw_new     = $this->sPost('pw_new');
            $pw_confirm = $this->sPost('pw_confirm');
            if( $pw_new  != $pw_confirm ){
              return $this->ajaxReturn(-10002,[],"两次密码不一至");
              // return $this->error('两次密码不一至');
            }
            if(strlen($pw_new) < 6){
              return $this->ajaxReturn(-10002,[],"密码不能少于6位");
              // return $this->error('密码不能少于6位');
            }
            $hashPassword = md5($pw_new); //加密后的密码
            $status = CP_User::model()->updateByPk($uid,array('md5password'=>$hashPassword));
            if($status!==false){
              return $this->ajaxReturn(0,[],"success");
              // $this->success('修改成功');
            }else{
              return $this->ajaxReturn(-1,[],"fail");
              // $this->error('修改失败');
            }
            break;

          case 'department':
            $department_id = $this->iPost('departmentid');
            $departmentData = Department::model()->findByPk($department_id);
            if(!$departmentData){
              return $this->ajaxReturn(-1,[],"fail");
            }
            $status = CP_User::model()->updateByPk($uid,array('Department'=>$departmentData->department_name));
            if($status!==false){
              return $this->ajaxReturn(0,[],"success");
            }else{
              return $this->ajaxReturn(-1,[],"fail");
            }
            break;

          default:
            if(!in_array($type,array('carnumber','carcolor'))){
              if($val==''){
                return $this->ajaxReturn(-1,[],"不能为空");
              }
            }

            $status = CP_User::model()->updateByPk($uid,array($type=>$val));
            // var_dump($status);
            if($status!==false){
              return $this->ajaxReturn(0,[],"success");
              // $this->success('修改成功');
            }else{
              return $this->ajaxReturn(-1,[],"fail");
              // $this->error('修改失败');
            }
            break;
        }
    }

    /**
     * 登出
     */
    public function delete()
    {
      return $this->jsonReturn(0,"success");

    }





}
