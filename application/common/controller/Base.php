<?php
namespace app\common\controller;

use think\Controller;

use think\Response;
use think\exception\HttpResponseException;


/**
 * 后台公用基础控制器
 * Class AdminBase
 * @package app\common\controller
 */
class Base extends Controller
{


    protected function initialize()
    {
        parent::initialize();

    }



    /**
     * 返回json数据
     * @param  integer $code    [状态码]
     * @param  array $data    [主要数据]
     * @param  string $message [描述]
     * @param  array  $extra   [其它]
     */
  	public function jsonReturn($code, $data, $message = '',$extra = array()) {
  		header('Access-Control-Allow-Origin: *');
  		header('Access-Control-Allow-Headers:*');
  		if($_SERVER['REQUEST_METHOD']=='OPTIONS'){
  			exit;
  		}
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

}
