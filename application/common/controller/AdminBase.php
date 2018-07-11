<?php
namespace app\common\controller;

use org\Auth;
use think\Loader;
use think\facade\Cache;
// use think\Controller;
use think\Response;
use app\common\controller\Base;
use think\Db;
use think\facade\Session;
use Firebase\JWT\JWT;


/**
 * 后台公用基础控制器
 * Class AdminBase
 * @package app\common\controller
 */
class AdminBase extends Base
{

    protected $jwtInfo ;
    public $userBaseInfo;

    protected function initialize()
    {
        parent::initialize();
        $this->checkAuth();
        // 输出当前请求控制器（配合后台侧边菜单选中状态）
        $this->assign('controller', Loader::parseName($this->request->controller()));

    }

    /**
     * 权限检查
     * @return bool
     */
    protected function checkAuth()
    {


       // $this->checkToken(); //如果使用jwt验证
        $this->checkAuthSession(); //如果使用Session验证

        $module     = $this->request->module();
        $controller = $this->request->controller();
        $action     = $this->request->action();

        // dump($this->request);exit;

        // 排除权限
        $un_check = ['admin/Index/index', 'admin/AuthGroup/getjson', 'admin/System/clear','admin/Index/main'];
        $un_check_controller = ['Publics','Uploader'];

        if (!in_array($module . '/' . $controller . '/' . $action, $un_check) && !in_array($controller,$un_check_controller) && strpos($action,'public_' ) === false) {
            $auth     = new Auth();
            $admin_id = $this->userBaseInfo['uid'];

            // $admin_id = Session::get('admin_id');
            if (!$auth->check($module . '/' . $controller . '/' . $action, $admin_id) && $admin_id != 1) {
                $referer = $this->request->header('referer');
                if($this->request->isAjax()){
                  $accept = $this->request->header('accept');
                  if(strpos($accept,"text/html") !== false){
                    $this->error('没有权限');
                  }else{
                    return $this->jsonReturn(10004,'没有权限');
                  }
                }else{
                    $this->error('没有权限');
                }
            }
        }
    }

    /**
     * 验证验入session
     * @return [type] [description]
     */
    public function checkAuthSession(){

      if (!Session::has('admin_id')) {
        if($this->request->isAjax()){
          $this->jsonReturn(10004,'您尚未登入');
        }else{
          return $this->error('您尚未登入','admin/login/index');
        }
      }
      $this->userBaseInfo = [
        'uid'=>Session::get('admin_id'),
        'username'=>Session::get('admin_name')
      ];

    }

    /**
     * 验证验入session
     * @return [type] [description]
     */
    public function checkActionAuth($r){
      $auth     = new Auth();
      $admin_id = $this->userBaseInfo['uid'];
      // $admin_id = Session::get('admin_id');
      if (!$auth->check($r, $admin_id) && $admin_id != 1) {
        return false;
      }else{
        return true;
      }

    }

    /**
     * 验证jwt
     */
    public function checkToken(){
        $Authorization = request()->header('Authorization');
        $temp_array    = explode('Bearer ',$Authorization);
		    $Authorization = count($temp_array)>1 ? $temp_array[1] : '';
        $Authorization = $Authorization ? $Authorization : cookie('admin_token');
        $Authorization = $Authorization ? $Authorization : input('request.admin_token');


        if(!$Authorization){
          if($this->request->isAjax()){
            $this->jsonReturn(10004,'您尚未登入');
          }else{
            return $this->error('您尚未登入','admin/login/index');
          }
        }else{


          $jwtDecode = JWT::decode($Authorization, config('admin_setting')['jwt_key'], array('HS256'));
          $this->jwtInfo = $jwtDecode;
          if(isset($jwtDecode->uid) && isset($jwtDecode->username) ){

            $now = time();
            if( $now  > $jwtDecode->exp){
              if($this->request->isAjax()){
                $this->jsonReturn(10004,'登入超时，请重新登入');
              }else{
                return $this->error('登入超时，请重新登入','admin/login/index');
              }
            }
            $this->userBaseInfo  = array(
              'username' => $jwtDecode->username,
              'uid' => $jwtDecode->uid,
            );
            return true;
          }else{
            if($this->request->isAjax()){
              $this->jsonReturn(10004,'您尚未登入');
            }else{
              return $this->error('您尚未登入','admin/login/index');
            }
          }
        }

    }

    /**
     * 获取侧边栏菜单
     */
    protected function getMenu()
    {
        $menu     = [];
        // $admin_id = Session::get('admin_id');
        $admin_id = $this->userBaseInfo['uid'];
        $auth     = new Auth();

        $auth_rule_list = Db::name('auth_rule')->where('status', 1)->order(['sort' => 'DESC', 'id' => 'ASC'])->select();

        foreach ($auth_rule_list as $value) {
            if ($auth->check($value['name'], $admin_id) || $admin_id == 1) {
                $menu[] = $value;
            }
        }
        $menu = !empty($menu) ? array2tree($menu) : [];
        foreach ($menu as $key => $value) {
          $reChildren = $value['children'];
          array_multisort(array_column($reChildren,'sort'),SORT_DESC,$reChildren);
          $menu[$key]['children'] = $reChildren;
        }
        array_multisort(array_column($menu,'sort'),SORT_DESC,$menu);
        $this->assign('menu', $menu);

    }

    public function log($desc='',$status=2){
      $request = request();
      $data['uid'] = $this->userBaseInfo['uid'];
      $data['ip'] = $request->ip();
      // $data['path'] = $request->path();
      $isAjaxShow =  $request->isAjax() ? " (Ajax)" : "";
      $data['type'] = $request->method()."$isAjaxShow";
      $data['route']= $request->module().'/'.$request->controller().'/'.$request->action();
      $data['query_string'] = $request->query();
      $data['description'] = $desc;
      $data['status'] = $status;
      $data['time'] = time();
      Db::name('admin_log')->insert($data);
    }


}
