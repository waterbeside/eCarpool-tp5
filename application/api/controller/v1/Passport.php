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


    /**
     * 登出
     */
    public function delete()
    {
      return $this->jsonReturn(0,"success");

    }





}
