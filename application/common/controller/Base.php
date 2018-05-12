<?php
namespace app\common\controller;

use think\Controller;

use think\Container;
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


    public function jump($msg = '', $url = null, $data = '', $wait = 3, array $header = []){
      if (is_null($url)) {
          $url = Container::get('request')->isAjax() ? '' : 'javascript:history.back(-1);';
      } elseif ('' !== $url) {
          $url = (strpos($url, '://') || 0 === strpos($url, '/')) ? $url : Container::get('url')->build($url);
      }
      $result = [
          'code' => 0,
          'msg'  => $msg,
          'data' => $data,
          'url'  => $url,
          'wait' => $wait,
      ];
      $type = 'jump';

      $response = Response::create($result, "jump")->header($header)->options(['jump_template' => Container::get('config')->get('dispatch_error_tmpl')]);
      throw new HttpResponseException($response);

    }

}
