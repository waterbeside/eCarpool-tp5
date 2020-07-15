<?php

namespace app\common\controller;

use think\Controller;
use app\common\model\Configs;
use think\Response;
use think\exception\HttpResponseException;
use think\facade\Cache;
use think\facade\Hook;
use my\RedisData;
use think\facade\Env;
use think\facade\Lang;

/**
 * 后台公用基础控制器
 * Class AdminBase
 * @package app\common\controller
 */
class Base extends Controller
{

    public $systemConfig  = null;
    public $language  = 'zh-cn';
    public $language_l = null;
    public $errorMsg = '';

    protected function initialize()
    {
        $this->getLang();
        $this->systemConfig = $this->getSystemConfigs();
        parent::initialize();
        $this->loadLanguagePack('common');
    }

    /**
     * 取得使用语言
     * @return [type] [description]
     */
    public function getLang($type = 0)
    {
        $getLangRes =  Hook::exec('app\\common\\behavior\\GetLang', $this, []);
        return $getLangRes;
    }

    /**
     * 格式化最后要得到的语言码
     * @param  string $language
     * @return string
     */
    public function formatLangCode($language)
    {
        $getLangRes =  Hook::exec(['app\\common\\behavior\\GetLang', 'formatLangCode'], $language);
        return $getLangRes;
    }


    /**
     * 加载语言包
     * @param  string $formCommon 模块, 当为空时，为当前模块
     * @param  string  $language   语言，当不设时，自动选择
     */
    public function loadLanguagePack($module = null, $language = null)
    {
        $module = $module ?: strtolower($this->request->module());

        $path =  Env::get('root_path') . "application/$module/lang/";
        $lang = $language ? $language  : $this->language;
        return Lang::load($path . $lang . '.php');
    }



    /**
     * 返回json数据
     * @param  integer $code    [状态码]
     * @param  array $data    [主要数据]
     * @param  string $message [描述]
     * @param  array  $extra   [其它]
     */
    public function jsonReturn($code, $data, $message = '', $extra = array(), $isObject = true)
    {
        if (is_string($data)) {
            $message = $data;
            $data = [];
        }
        if ($isObject) {
            $data = empty($data) ? (object) array() : $data;
        } else {
            $data = empty($data) ? array() : $data;
        }
        $extra = empty($extra) ? (object) array() : $extra;
        $data = array(
            'code' => $code,
            'desc' => $message,
            'data' => $data,
            // 'date' => date("Y-m-d H:i:s", time()),
            'date' => time(),
            'extra' => $extra
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
    public function arrayUniqByKey($arr, $key)
    {
        //建立一个目标数组
        $res = array();
        foreach ($arr as $value) {
            //查看有没有重复项
            if (isset($res[$value[$key]])) {
                //有：销毁
                unset($value[$key]);
            } else {
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
        if (!is_array($arr)) {
            return trim($arr);
        }
        return array_map("self::trimArray", $arr);
    }


    public function jump($isSuccess = 1, $msg = '', $url = null, $data = '', $wait = 3, array $header = [])
    {
        if (is_null($url)) {
            if ($isSuccess === 1) {
                $url =  $_SERVER["HTTP_REFERER"] ?? ((strpos($url, '://') || 0 === strpos($url, '/')) ? url($url) : '');
            } else {
                $url = $this->request->header('X-Requested-With') == "modal-html"  ? 'javascript:void(0);' : 'javascript:history.back(-1);';
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

    /**
     * 从数据库最得系统配置
     * @return [type] [description]
     */
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
    public function success($msg = '', $url = null, $data = '', $wait = 3, array $header = [])
    {
        if ($this->request->isAjax()) {
            $extra = is_array($wait) ? $wait : [];
            if (!isset($extra['url']) && is_string($url) && !is_numeric($url)) {
                $extra['url'] = $url;
            }
            $code = is_numeric($url) ? intval($url) : (is_numeric($data) ? $data : 0);
            $data = is_array($data) ? $data : [];
            $this->jsonReturn($code, $data, $msg, $extra);
        } else {
            $this->jump(1, $msg, $url, $data, $wait, $header);
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
    public function error($msg = '', $url = null, $data = '', $wait = 3, array $header = [])
    {
        if ($this->request->isAjax()) {
            $extra = is_array($wait) ? $wait : [];
            if (!isset($extra['url']) && is_string($url) && !is_numeric($url)) {
                $extra['url'] = $url;
            }
            $code = is_numeric($url) ? intval($url) : (is_numeric($data) ? $data : -1);
            $data = is_array($data) ? $data : [];
            $this->jsonReturn($code, $data, $msg, $extra);
        } else {
            $this->jump(0, $msg, $url, $data, $wait, $header);
        }
    }


    /**
     * 请求数据
     * @param  string $url  请求地址
     * @param  array  $data 请求参数
     * @param  string $type 请求类型
     */
    public function clientRequest($url, $data = [], $type = 'POST', $dataType = "json")
    {
        try {
            $client = new \GuzzleHttp\Client(['verify' => false]);
            // $params = strtolower($type) == 'get' ? [
            //   'query' => $data
            // ] : [
            //   'form_params' => $data
            // ];
            $params = $data;
            $response = $client->request($type, $url, $params);

            $contents = $response->getBody()->getContents();
            if (mb_strtolower($dataType) == "json") {
                $contents = json_decode($contents, true);
            }
            return $contents;
        } catch (\GuzzleHttp\Exception\RequestException $exception) {
            if ($exception->hasResponse()) {
                $responseBody = $exception->getResponse()->getBody()->getContents();
            }
            $this->errorMsg = $exception->getMessage() ?: ($responseBody ?? '');
            return false;
        }
    }

    /**
     * 给指定的json字段加值
     *
     * @param array $data 更新的数据
     * @param array||string $target 原json字段的值
     * @return array 返回处理后的字典数组
     */
    public function addDataToData($data, $target)
    {
        if (is_string($target)) {
            $target = json_decode($target, true);
        }
        $target = $target && is_array($target) ? $target : [];
        $data   = $data && is_array($data) ? $data : [];
        foreach ($data as $key => $value) {
            $target[$key] = $value;
        }
        if (empty($target)) {
            return null;
        }
        return $target;
    }
    
    /**
     * 从json字段里取值
     *
     * @param string $key KEY
     * @param array||string $target 原json字段的值
     */
    public function getValueByKey($key, $target, $default = null)
    {
        if (is_string($target)) {
            $target = json_decode($target, true);
        }
        $target = $target && is_array($target) ? $target : [];
        return $target[$key] ?? $default;
    }


    /**
     * 取得当前module,controller,action
     *
     * @param string $sp 如果有值，则以此为分格符拼成字符串返回
     * @return mixed
     */
    public function getMCA($sp = false)
    {
        $module     = strtolower($this->request->module());
        $controller = strtolower($this->request->controller());
        $action     = strtolower($this->request->action());
        $mca = [$module, $controller, $action];
        if (is_string($sp)) {
            return implode($sp, $mca);
        }
        return $mca;
    }

    /**
     * 锁定操作
     *
     * @param array $setting 设置
     * @return boolean
     */
    public function lockAction($setting = [])
    {
        $cacheKey = $this->getMCA(':');
        $defaultSetting = [
            'cacheKey' => $cacheKey,
            'ex' => 8, // cacheKey过期时间
            'runCount' => 100, // 等待次数
            'sleep' => 20 * 1000, //微秒为单位，每次等待时间
        ];
        $setting = is_array($setting) ? array_merge($defaultSetting, $setting) : $defaultSetting;
        $redis = RedisData::getInstance();
        return $redis->lock($setting['cacheKey'], $setting['ex'], $setting['runCount'], $setting['sleep']);
    }

    /**
     * 解锁操作
     */
    public function unlockAction($setting = [])
    {
        $cacheKey = $this->getMCA(':');
        $defaultSetting = [
            'cacheKey' => $cacheKey,
        ];
        $setting = is_array($setting) ? array_merge($defaultSetting, $setting) : $defaultSetting;
        $redis = RedisData::getInstance();
        return $redis->unlock($setting['cacheKey']);
    }
}
