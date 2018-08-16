<?php
namespace app\common\controller;

use think\Controller;
use app\common\model\Configs;
use think\Response;
use think\exception\HttpResponseException;
use think\facade\Cache;


/**
 * 后台公用基础控制器
 * Class AdminBase
 * @package app\common\controller
 */
class Base extends Controller
{

    public $systemConfig  = null;
    public $language  = 'zh-cn';
    public $language_l = [];
    protected function initialize()
    {
        // header('Access-Control-Allow-Origin: *');
    		// header('Access-Control-Allow-Headers:*');
    		// if($_SERVER['REQUEST_METHOD']=='OPTIONS'){
    		// 	exit;
    		// }
        $lang_s = request()->header('Accept-Lag');
        $lang_s = $lang_s ? $lang_s : request()->header('Accept-Language');
        $lang_l = $this->formatAcceptLang($lang_s);
        $this->language_l = $lang_l;
        $this->language = $lang_l[0] ? ( $lang_l[0] == 'zh' ? 'zh-cn' : $lang_l[0]  )   : 'zh-cn' ;

        $this->systemConfig = $this->getSystemConfigs();
        parent::initialize();

    }


    /**
     * 返回json数据
     * @param  integer $code    [状态码]
     * @param  array $data    [主要数据]
     * @param  string $message [描述]
     * @param  array  $extra   [其它]
     */
  	public function formatAcceptLang($language) {
      $lang_l = explode(',',$language);
      $lang_format_list = [];
      $q_array = [];
      foreach ($lang_l as $key => $value) {
        $temp_arr = explode(';',$value);
        $q = isset($temp_arr[1]) ? $temp_arr[1] : 1;
        $q_array[]  = $q;
        $lang_format_list[$key] = ['lang'=>$temp_arr[0],'q'=>$q];
      }
      array_multisort($q_array, SORT_DESC,  $lang_format_list);
      $lang = [];
      foreach ($lang_format_list as $key => $value) {
        $lang[] = strtolower(trim($value['lang']));
      }
      $lang = array_unique($lang);
      return $lang;
  	}



    /**
     * 返回json数据
     * @param  integer $code    [状态码]
     * @param  array $data    [主要数据]
     * @param  string $message [描述]
     * @param  array  $extra   [其它]
     */
  	public function jsonReturn($code, $data, $message = '',$extra = array()) {

      if(is_string($data)){
        $message = $data;
        $data = [];
      }
  		$data = array(
  			'code'=>$code,
  			'desc'=>$message,
  			'data'=>$data,
  			'date'=>date("Y-m-d H:i:s",time()),
  			'extra'=>$extra
  		);
      // return json($data);
      throw new HttpResponseException(json($data));
  		// echo json_encode($data);
  		// exit;
  	}


    public function jump($isSuccess=1 , $msg = '', $url = null, $data = '', $wait = 3, array $header = []){
      if (is_null($url)) {
        if($isSuccess===1){
          $url =  isset($_SERVER["HTTP_REFERER"]) ? $_SERVER["HTTP_REFERER"] : ((strpos($url, '://') || 0 === strpos($url, '/')) ? url($url) : '') ;
        }else{
          $url = $this->request->header('X-Requested-With')=="modal-html"  ? 'javascript:void(0);' : 'javascript:history.back(-1);';
          // $url = $this->request->header('X-Requested-With')=="modal-html"  ? (isset($_SERVER["HTTP_REFERER"])?$_SERVER["HTTP_REFERER"]:'') : 'javascript:history.back(-1);';
        }
      } elseif ('' !== $url) {
          $url = (strpos($url, '://') || 0 === strpos($url, '/')) ? $url : url($url);
      }
      $tmpl = $isSuccess === 1 ? 'dispatch_success_tmpl' : 'dispatch_error_tmpl';
      $result = [
          'code' => $isSuccess,
          'msg'  => $msg,
          'data' => $data,
          'url'  => $url,
          'wait' => $wait,
      ];
      $response = Response::create($result, "jump")->header($header)->options(['jump_template' => config($tmpl)]);
      throw new HttpResponseException($response);

    }

    public function getSystemConfigs(){
      $ConfigsModel = new Configs();
      $configs = $ConfigsModel->getConfigs();
      return $configs;
    }

    /**
     * 操作成功跳转的快捷方法
     * @access protected
     * @param  mixed     $msg 提示信息
     * @param  string    $url 跳转的URL地址
     * @param  mixed     $data 返回的数据
     * @param  integer   $wait 跳转等待时间
     * @param  array     $header 发送的Header信息
     * @return void
     */
    protected function success($msg = '', $url = null, $data = '', $wait = 3, array $header = [])
    {
      if($this->request->isAjax()){
        $extra = is_array($wait) ? $wait :[];
        if(!isset($extra['url']) &&   is_string($url) && !is_numeric($url) ){
          $extra['url'] = $url;
        }
        $code = is_numeric($url) ? intval($url) : (is_numeric($data) ? $data : 0);
        $data = is_array($data) ? $data : [];
        $this->jsonReturn($code, $data, $msg ,$extra);
      }else{
        $this->jump(1 , $msg, $url, $data , $wait ,  $header );
      }
    }

    /**
     * 操作错误跳转的快捷方法
     * @access protected
     * @param  mixed     $msg 提示信息
     * @param  string    $url 跳转的URL地址
     * @param  mixed     $data 返回的数据
     * @param  integer   $wait 跳转等待时间
     * @param  array     $header 发送的Header信息
     * @return void
     */
    protected function error($msg = '', $url = null, $data = '', $wait = 3, array $header = [])
    {
      if($this->request->isAjax()){
        $extra = is_array($wait) ? $wait :[];
        if(!isset($extra['url']) &&   is_string($url) && !is_numeric($url) ){
          $extra['url'] = $url;
        }
        $code = is_numeric($url) ? intval($url) : (is_numeric($data) ? $data : -1);
        $data = is_array($data) ? $data : [];
        $this->jsonReturn($code, $data, $msg ,$extra);

      }else{
        $this->jump(0 , $msg, $url, $data , $wait ,  $header );
      }
    }

}
