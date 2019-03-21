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

    public $systemConfig  = NULL;
    public $language  = 'zh-cn';
    public $language_l = NULL;
    public $errorMsg = '';

    protected function initialize()
    {
        $this->language = $this->getLang();

        $this->systemConfig = $this->getSystemConfigs();
        parent::initialize();
    }

    /**
     * 取得使用语言
     * @return [type] [description]
     */
    public function getLang()
    {
      if(!$this->language_l){
        $lang_s =  input('request._language');
        $lang_s = $lang_s ? $lang_s : input('request.lang');
        $lang_s = $lang_s ? $lang_s : request()->header('Accept-Lang');
        $lang_s = $lang_s ? $lang_s : request()->header('Accept-Language');
        $lang_l = $this->formatAcceptLang($lang_s);
        $this->language_l = $lang_l;
        $this->language = $lang_l[0] == 'zh' ? ( isset($lang_l[1]) ? $this->formatZhLang($lang_l[1],'zh-cn') : 'zh-cn') : $this->formatZhLang($lang_l[0]);
      }
      return $this->language;

    }


    public function formatZhLang($language,$default = null)
    {
      if($language == 'zh-hant-hk'){
        return 'zh-hk';
      }
      if($language == 'zh-hant-tw'){
        return 'zh-tw';
      }
      if($language == 'zh-hans'){
        return 'zh-cn';
      }
      if(strpos($language,'zh-hant') !== false  ){
        return 'zh-hk';
      }
      if(strpos($language,'zh-hans') !== false  ){
        return 'zh-cn';
      }
      return $default ? $default : $language  ;
    }

    /**
     * 格式化Accept-language得来的语言。
     * @param  string $language
     * @return array
     */
  	public function formatAcceptLang($language)
    {
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
      $baseLangArray = explode('-',$lang[0]);
      $baseLang  = $baseLangArray[0];
      $lang = array_merge([$baseLang],$lang);
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
  	public function jsonReturn($code, $data, $message = '',$extra = array())
    {
      if(is_string($data)){
        $message = $data;
        $data = [];
      }
      $data = empty($data) ? (object)array() : $data;
      $extra = empty($extra) ? (object)array() : $extra;
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

    /**
	   * 数组去重
	   */
	  public function arrayUniq($arr)
    {
	    $arr = array_unique($arr);
	    $arr = array_values($arr);
	    return $arr;
	  }

		 /**
	   * 二维数组去重
	   */
		 public function arrayUniqByKey($arr,$key)
     {
         //建立一个目标数组
         $res = array();
         foreach ($arr as $value) {
            //查看有没有重复项
            if(isset($res[$value[$key]])){
                  //有：销毁
                  unset($value[$key]);
            }
            else{
                 $res[$value[$key]] = $value;
            }
         }
         return $res;
     }

		/**
	   * 清除数组内每个元素的两头空格
	   * @return array||string
	   */
		public function trimArray($arr)
    {
	    if (!is_array($arr)){
				  return trim($arr);
			}
    	return array_map("self::trimArray", $arr);
		}


    public function jump($isSuccess=1 , $msg = '', $url = null, $data = '', $wait = 3, array $header = [])
    {
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

    public function getSystemConfigs()
    {
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


    /**
     * 请求数据
     * @param  string $url  请求地址
     * @param  array  $data 请求参数
     * @param  string $type 请求类型
     */
    public function clientRequest($url,$data=[],$type='POST',$dataType="json"){
      try {
        $client = new \GuzzleHttp\Client(['verify'=>false]);
        // $params = strtolower($type) == 'get' ? [
        //   'query' => $data
        // ] : [
        //   'form_params' => $data
        // ];
        $params = $data;
        $response = $client->request($type, $url, $params);

        $contents = $response->getBody()->getContents();
        if(mb_strtolower($dataType)=="json"){
          $contents = json_decode($contents,true);
        }
        return $contents;
      } catch (\GuzzleHttp\Exception\RequestException $exception) {
        if ($exception->hasResponse()) {
          $responseBody = $exception->getResponse()->getBody()->getContents();
        }
        $this->errorMsg = $exception->getMessage() ? $exception->getMessage()  :(isset($responseBody) ? $responseBody : '')  ;
        return false;
      }

    }



}
