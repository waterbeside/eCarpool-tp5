<?php
namespace app\api\controller;

use app\common\controller\Base;
use app\carpool\model\User as UserModel;
use think\facade\Cache;
use think\Controller;
use think\Db;
use Firebase\JWT\JWT;

class ApiBase extends Base
{

    protected $jwtInfo ;
    public $userBaseInfo;
    public $passportError;

    protected function initialize()
    {
        parent::initialize();

    }


    /**
     * 验证jwt
     */
    public function checkPassport($returnType=0){
        $Authorization = request()->header('Authorization');
        $temp_array    = explode('Bearer ',$Authorization);
		    $Authorization = count($temp_array)>1 ? $temp_array[1] : '';
        $Authorization = $Authorization ? $Authorization : cookie('admin_token');
        $Authorization = $Authorization ? $Authorization : input('request.admin_token');

        if(!$Authorization){
          $this->passportError = [10004,'您尚未登入'];
          return $returnType ? $this->jsonReturn(10004,$this->passportError[1]) : false;
        }else{
          try{
            $jwtDecode = JWT::decode($Authorization, config('front_setting')['jwt_key'], array('HS256'));
            $this->jwtInfo = $jwtDecode;
          } catch(\Firebase\JWT\SignatureInvalidException $e) {  //签名不正确
  	    		$msg =  $e->getMessage();
  	    	}catch(\Firebase\JWT\BeforeValidException $e) {  // 签名在某个时间点之后才能用
  	    		$msg =  $e->getMessage();
  	    	}catch(\Firebase\JWT\ExpiredException $e) {  // token过期
  	    		$msg =  $e->getMessage();
  	   	  }catch(\Exception $e) {  //其他错误
  	    		$msg =  $e->getMessage();
  	    	}catch(\DomainException $e) {  //其他错误
  	    		$msg =  $e->getMessage();
  	    	}
          if(isset($msg)){
            $this->passportError = [10004,$msg];
            return $returnType ? $this->jsonReturn(10004,$this->passportError[1]) : false;
          }
          if(isset($jwtDecode->uid) && isset($jwtDecode->loginname) ){
            $now = time();
            if( $now  > $jwtDecode->exp){
                $this->passportError = [10004,'登入超时，请重新登入'];
                return $returnType ? $this->jsonReturn(10004,$this->passportError[1]) : false;
            }
            $this->userBaseInfo  = array(
              'loginname' => $jwtDecode->loginname,
              'uid' => $jwtDecode->uid,
            );
            return true;
          }else{
            $this->passportError = [10004,'您尚未登入'];
            return $returnType ? $this->jsonReturn(10004,$this->passportError[1]) : false;
          }

        }

    }


    public function getUserData($returnType=0){
      $uid = $this->userBaseInfo['uid'];
      if($uid){
        $userInfo = UserModel::find($uid);
      }
      if(!$uid || !$userInfo){
        return $returnType ? $this->jsonReturn(10004,'您尚未登入') : false;
      }
      return $userInfo;

    }



}
