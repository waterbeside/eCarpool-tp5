<?php
namespace app\api\controller;

use app\common\controller\Base;
use app\carpool\model\User as UserModel;
use app\user\model\Department;
use think\facade\Cache;
use think\Controller;
use think\Db;
use Firebase\JWT\JWT;
use think\facade\Env;
use think\facade\Lang;

class ApiBase extends Base
{

    protected $jwtInfo ;
    public $userBaseInfo;
    public $passportError;
    public $userData = NULL;

    protected function initialize()
    {
        // config('default_lang', 'zh-cn');
        parent::initialize();
        $this->loadLanguagePack();

    }


    public function getRequestPlatform(){
      $user_agent = request()->header('USER_AGENT');
      if(strpos($user_agent, 'iPhone')||strpos($user_agent, 'iPad')){
          return 1;
      }else if(strpos($user_agent, 'Android')){
          return 2;
      }else{
          return 0;
      }
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
          $this->passportError = [10004,lang('You are not logged in')];
          return $returnType ? $this->jsonReturn(10004,$this->passportError[1]) : false;
        }else{
          try{
            $jwtDecode = JWT::decode($Authorization, config('secret.front_setting')['jwt_key'], array('HS256'));
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
                $this->passportError = [10004,lang('Login status expired, please login again')];
                return $returnType ? $this->jsonReturn(10004,$this->passportError[1]) : false;
            }
            $this->userBaseInfo  = array(
              'loginname' => $jwtDecode->loginname,
              'uid' => $jwtDecode->uid,
            );
            return true;
          }else{
            $this->passportError = [10004,lang('You are not logged in')];
            return $returnType ? $this->jsonReturn(10004,$this->passportError[1]) : false;
          }
        }
    }

    /**
     * 生成jwt
     * @param  Array $data [uid,loginname,client]
     * @return [type]       [description]
     */
    public function createPassportJwt($data){
      $exp = in_array($data['client'],['ios','android']) ? (time() + 36* 30 * 86400) : (time() + 30 * 86400);
      $jwtData  = array(
        'exp'=> $exp, //过期时间
        'iat'=> time(), //发行时间
        'iss'=> 'carpool', //发行者，值为固定carpool
        'uid'=> $data['uid'],
        'loginname' => $data['loginname'],
        'client' => $data['client'], //客户端
      );
      $key = config('secret.front_setting')['jwt_key'];
      $jwt = JWT::encode($jwtData, $key);
      return $jwt;
    }

    /**
     * 加载语言包
     * @param  string  $language   语言，当不设时，自动选择
     * @param  integer $formCommon 语言包路径位置。
     */
    public function loadLanguagePack($language = NULL,$formCommon = 0){
      $path = $formCommon ? Env::get('root_path') .'application/common/lang/' : Env::get('root_path') .'application/api/lang/';
      $lang = $language ? $language  : $this->language;
      Lang::load( $path.$lang.'.php');
    }



    /**
     * 取得登录用户的信息
     */
    public function getUserData($returnType=0){
      $uid = $this->userBaseInfo['uid'];
      if(!$uid){
        $this->checkPassport($returnType);
        $uid = $this->userBaseInfo['uid'];
      }
      if($this->userData){
        return $this->userData;
      }
      if($uid){
        $userData = UserModel::find($uid);
      }
      if(!$uid || !$userData){
        return $returnType ? $this->jsonReturn(10004,lang('You are not logged in')) : false;
      }
      if(!$userData['is_active']){
        return $returnType ? $this->jsonReturn(10003,lang('The user is banned')) : false;
      }
      if($userData['is_delete']){
        return $returnType ? $this->jsonReturn(10003,lang('The user is deleted')) : false;
      }
      $this->userData = $userData;
      return $userData;

    }

    /**
     * 验证是否本地请求
     * @param  integer $returnType 0返回true or false，其它当false时，返json
     * @return boolean
     */
    public function check_localhost($returnType = 0)
    {
        $host = $this->request->host();
        // dump($this->request->port());exit;
        // && !in_array($host,["gitsite.net:8082","admin.carpoolchina.test"])
        if (strpos($host, '127.0.0.1') === false && strpos($host, 'localhost') === false) {
            return $returnType ?  $this->jsonReturn(30001,lang('Illegal access')) : false ;
        } else {
            return true;
        }
    }


    /**
     * 接口日圮
     * @param  string  $desc   描述
     * @param  integer $status 状态 -1失败，1成功
     */
    public function log($desc='',$status=0,$uid = null){
      $request = request();
      $data['uid'] = is_numeric($uid) ? $uid : ($this->userBaseInfo['uid'] ? $this->userBaseInfo['uid'] : 0);
      $data['ip'] = $request->ip();
      $isAjaxShow =  $request->isAjax() ? " (Ajax)" : "";
      $data['type'] = $request->method()."$isAjaxShow";
      $data['route']= $request->module().'/'.$request->controller().'/'.$request->action();
      $data['query_string'] = $request->query();
      $data['description'] = $desc;
      $data['status'] = $status;
      $data['time'] = time();
      Db::name('log')->insert($data);
    }

    /** 
     * 检查部门权限
    */
    public function checkDeptAuth($userDid,$dataRid){
      $Department = new Department();
      $deptData = $Department->getItem($userDid);
      $dataRid = explode(',',$dataRid);
      $arrayIts = array_intersect($dataRid, explode(',',($deptData['path'].','.$userDid)));
      if(!empty($arrayIts) ){
        return true;
      }else{
        return false;
      }
    }
}
