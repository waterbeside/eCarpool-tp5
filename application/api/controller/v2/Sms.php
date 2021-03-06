<?php

namespace app\api\controller\v2;

use think\facade\Log;
use think\Db;
use app\api\controller\ApiBase;
use app\carpool\model\User as UserModel;
use app\user\model\JwtToken;
use app\score\model\AccountMix as ScoreAccountModel;
use my\RedisData;
use com\nim\Nim as NimServer;
use app\common\model\Configs;
use app\user\model\UserOauth as UserOauthModel;

/**
 * 发送短信
 * Class Sms
 * @package app\api\controller
 */
class Sms extends ApiBase
{
    protected $appKey = '';
    protected $appSecret = '';
    protected $test_inter = 0;
    // usage 与对应的模板id
    // protected $SmsTemplate  = array(
    //     // 'u_000'   => '4022147',
    //     'u_100' => '4072148', //用于登录
    //     'u_101' => '4022223', //用于注册
    //     'u_102' => '4022224', //用于重置
    //     'u_103' => '3892309', //用于绑定手机号
    //     'u_104' => '4022147', //用于合并帐号

    //     'u_200' => '4022147', //用于确认通用操作
    //     'u_201' => '4022147', //用于开通支付
    //     'u_202' => '4022147', //用于重置支付密码

    //     'u_300' => '4012242', //用于邀请下载
    //     'u_301' => '4012242', //用于邀请建立好友关系
    //     'u_302' => '4012426', //用于邀请进群
    // );

    protected $usageSetting = [
        'u_100' => [ //用于登录
            'tpl' => '4072148',
            'isGetPhone' => true,
            'isGetPhoneUser' => true,
            'isCheckUser' => false,
            'isSendCode' => true,
        ],
        'u_101' => [ //用于注册
            'tpl' => '4022223',
            'isGetPhone' => true,
            'isGetPhoneUser' => true,
            'isCheckUser' => false,
            'isSendCode' => true,
        ],
        'u_102' => [ //用于重置
            'tpl' => '4022224',
            'isGetPhone' => true,
            'isGetPhoneUser' => true,
            'isCheckUser' => false, // 重置分登录后和登录前，所以先设为false
            'isSendCode' => true,
        ],
        'u_103' => [ //用于绑定手机号
            'tpl' => '3892309',
            'isGetPhone' => true,
            'isGetPhoneUser' => true,
            'isCheckUser' => true,
            'isSendCode' => true,
        ],
        'u_104' => [ //用于合并帐号
            'tpl' => '4022147',
            'isGetPhone' => true,
            'isGetPhoneUser' => false,
            'isCheckUser' => false,
            'isSendCode' => true,
        ],
        'u_200' => [ //通用
            'tpl' => '4022147',
            'isGetPhone' => true,
            'isGetPhoneUser' => true,
            'isCheckUser' => true,
            'isSendCode' => true,
        ],
        'u_201' => [ //用于开通支付
            'tpl' => '14839075',
            'isGetPhone' => false,
            'isGetPhoneUser' => false,
            'isCheckUser' => true,
            'isSendCode' => true,
        ],
        'u_202' => [ //用于重置支付码
            'tpl' => '14844112',
            'isGetPhone' => false,
            'isGetPhoneUser' => false,
            'isCheckUser' => true,
            'isSendCode' => true,
        ],
        'u_300' => [ //用于邀请
            'tpl' => '4012242',
            'isGetPhone' => false,
            'isGetPhoneUser' => false,
            'isCheckUser' => true,
            'isSendCode' => false,
        ],
        'u_301' => [ //用于邀请建立好友关系
            'tpl' => '4012242',
            'isGetPhone' => false,
            'isGetPhoneUser' => false,
            'isCheckUser' => true,
            'isSendCode' => false,
        ],
        'u_302' => [ //用于邀请进群
            'tpl' => '4012426',
            'isGetPhone' => false,
            'isGetPhoneUser' => false,
            'isCheckUser' => true,
            'isSendCode' => false,
        ],
    ];

    

    protected function initialize()
    {
        parent::initialize();
        $this->appKey     = config('secret.nim.appKey');
        $this->appSecret  = config('secret.nim.appSecret');
    }

    /**
     * 取得场景设置
     *
     * @param String||Integer $usage 场景
     * @return Array
     */
    protected function getUsageSetting($usage)
    {
        $default = [
            'tpl' => '4022147',
            'isGetPhone' => true,
            'checkUserData' => true,
            'getPhoneUserData' => true,
            'isSendCode' => true,
        ];
        $settingData = isset($this->usageSetting['u_' .$usage]) ? array_merge($default, $this->usageSetting['u_' .$usage]) : false;
        return $settingData;
    }

    /**
     * 处理验证码缓存
     * @param  integer  $usage 场景
     * @param  string  $phone 电话号
     * @param  mixed  $code  false时为取值，null为清空，其它为插值
     * @param  string  $msg   插值留的信息
     * @param  integer $exp   过期时间
     */
    protected function codeCache($usage, $phone, $code = false, $msg = "", $exp = 600)
    {
        $redis = RedisData::getInstance();
        $key = "common:sms_code:" . $usage . ":" . $phone;
        if ($code) {
            $data = [
                'code' => $code,
                'msg' => $msg,
                'time' => time()
            ];
            // Cache::tag('public')->set($key, $data ,$exp);
            $redis->cache($key, $data, $exp);
            return true;
        }
        if ($code === false) {
            // $res_array = Cache::tag('public')->get($key);
            $res = $redis->cache($key);
            if (!$res) {
                return false;
            }
            return $res;
        }
        if ($code === null) {
            // Cache::tag('public')->rm($key);
            $res = $redis->del($key);
            return true;
        }
    }



    /**
     * 格式手机号参数
     *
     * @param String $phones 当手机号为字符串时，处理为数组.多个手机号以','格开。例如 phone=[13112345678,13212345678] 或 phone=13112345678,13212345678 或 phone=13112345678有形式皆可
     */
    protected function formatPhones($phones)
    {
        if (is_string($phones)) {
            if (trim($phones) == '') {
                $this->jsonReturn(992, [], 'phone empty');
                exit;
            }
            if (strpos($phones, '[') !== false) {
                $phones = preg_replace('/\[||\]/i', '', $phones);
            }
            $phones = explode(',', $phones);
        }
        if (is_array($phones)) {
            $phones = $this->arrayUniq(array_filter($this->trimArray($phones))); //去电话号除空制和重复值。
        } else {
            $this->jsonReturn(992, [], 'phone error');
            exit;
        }
        if (!$phones || trim($phones[0]) == '') {
            $this->jsonReturn(992, [], 'phone empty');
            exit;
        }
        return $phones;
    }



    /**
     * 发送验证码
     */
    public function send($usage = 0, $phone = null)
    {
        // $dev = input('param.dev', 0);
        $dev = 0;
        if (!$usage) {
            $this->jsonReturn(992, [], 'usage empty');
            exit;
        }

        $usageSetting = $this->getUsageSetting($usage);
        if (!$usageSetting) {
            $this->jsonReturn(992, [], 'usage error');
            exit;
        }

        if ($usageSetting['isGetPhone']) {
            $phones = $this->formatPhones($phone);
            $phone = $phones[0];
        }

        // var_dump($phones);
        $sendCallBack = array();
        $isSuccess = 0;

        /******* 非群发的验证场景 ******/
        if ($usageSetting['isSendCode']) {
            if ($usageSetting['isGetPhoneUser']) {
                $phoneUserData = UserModel::where([['phone', '=', $phone], ['is_delete', '=', Db::raw(0)]])->find();
            }

            if ($usageSetting['isCheckUser']) { // 验证是否登入
                $userData = $this->getUserData(1);
            }

            switch ($usage) {
                case 100: //登入
                    if (!$phoneUserData) { //登入验证手机号是否存在。
                        $this->jsonReturn(10002, [], lang('The mobile phone number has no associated employee account'));
                    }
                    break;
                case 101: //注册
                    if ($phoneUserData) { //注册 验证手机号是否存在。
                        $this->jsonReturn(10006, [], lang('User already exists'));
                    }
                    break;
                case 102: //重置密码
                    $jwt = $this->getJwt();
                    if ($jwt) {
                        $userData = $this->getUserData(1);
                        if ($userData && $userData['phone'] != $phone) {
                            $this->jsonReturn(10001, [], lang('The phone number you entered is not the phone number of the current account'));
                        }
                    }
                    if (!$phoneUserData) { //验证手机号是否存在。
                        $this->jsonReturn(10002, [], lang('The mobile phone number has no associated employee account'));
                    }
                    break;
                case 103: //重绑定
                    if ($userData['phone'] == $phone) {
                        $this->jsonReturn(10100, [], lang('Already bound, please enter a new phone number'));
                    }
                    $phoneUserData2 = UserModel::where([['loginname', '=', $phone],['is_delete', '=', Db::raw(0)]])->find();
                    if ($phoneUserData2) {
                        $this->jsonReturn(10006, [], lang('The mobile phone number has been marked with a new account, whether to merge?'));
                    }
                    /*  if($phoneUserData && $phoneUserData['phone'] == $phones[0]){
                    $this->jsonReturn(10006,[],'该手机号已绑定其它帐号');
                    }*/
                    break;
                case 104: //合并账号
                    $type = input('param.type', 0) ?: ( input('post.type', 0) ?: input('get.type', 0));
                    if (!$type) { // 默认必先做jwt验证
                        $userData = $this->getUserData(1);
                        if ($userData['phone'] == $phone) {
                            $this->jsonReturn(10100, [], lang('The phone number has been bound to this account, no need to merge.'));
                        }
                    }
                    $phoneUserData2 = UserModel::where([['loginname', '=', $phone],['is_delete', '=', Db::raw(0)]])->find();
                    if (!$phoneUserData2) {
                        $this->jsonReturn(10006, [], lang('No need to merge'));
                    }
                    break;
                case 201: //开通支付
                    $phone = $userData['phone'];
                    break;
                case 202: //重置支付
                    $phone = $userData['phone'];
                    break;
                default:
                    # code...
                    break;
            }
            // foreach ($phones as $key => $phone) {
            $sendCallBack[$phone] = $this->sendCode($phone, $usage, 6, 360, $dev);
            if ($sendCallBack['' . $phone]['code'] == 200) {
                $isSuccess = 1;
            }
            // break;
            // }
            if ($isSuccess) {
                $this->jsonReturn(0, $sendCallBack, 'success');
            } else {
                if ($sendCallBack['' . $phone]['code'] == 10200) {
                    $this->jsonReturn(10200, $sendCallBack, 'too often');
                }
                if ($sendCallBack['' . $phone]['code'] == 414) {
                    $msg = lang('Phone number format is not correct');
                    $this->jsonReturn(992, $sendCallBack, $msg);
                }
                if ($sendCallBack['' . $phone]['code'] == 416) {
                    $this->jsonReturn(30010, $sendCallBack, lang('The number of verification codes sent to this mobile phone number has reached the upper limit today'));
                }
                $this->jsonReturn(-1, $sendCallBack, 'fail');
            }
        } else {
        /*******  模板短信场景 ******/
            $this->checkPassport(1);
            $sendCallBack = $this->sendTemplate($phones, $usage);
            if ($sendCallBack['code'] == 200) {
                $this->jsonReturn(0, $sendCallBack, 'success');
            } else {
                $this->jsonReturn(-1, $sendCallBack, 'fail');
            }
        }
    }

    /**
     * 验证短信验证码
     * @param  [type] $phone [description]
     * @param  [type] $code  [description]
     * @param  [type] $usage [description]
     * @return [type]        [description]
     */
    public function checkSMSCode($phone, $code, $usage)
    {
        $cacheData = $this->codeCache($usage, $phone);
        $code_o = $cacheData['code'];
        return $cacheData && $code == $code_o ? $code_o : false;
    }

    /**
     * 验证短信验证码
     * @return mixed
     */
    public function verify($usage = 0, $code = null, $phone = null, $step = 0)
    {
        if (!$code) {
            $this->jsonReturn(992, lang('Verification code cannot be empty'));
        }
        if (!$this->checkSMSCode($phone, $code, $usage)) {
            $this->jsonReturn(10101, lang('Verification code error'));
            exit;
        }

        $usageSetting = $this->getUsageSetting($usage);
        if (!$usageSetting) {
            $this->jsonReturn(992, [], 'usage error');
            exit;
        }

        $returnData =  [];
        if ($usageSetting['isCheckUser']) { // 验证是否登入
            $userData = $this->getUserData(1);
            $uid = $this->userBaseInfo['uid'];
        }

        switch ($usage) {
                /******** 验证登入 *********/
            case 100:
                $client = input('param.client') ?: input('post.client') ?: input('get.client');

                if (!in_array(strtolower($client), array('ios', 'android', 'h5', 'web', 'third'))) {
                    return  $this->jsonReturn(992, [], 'client error');
                };
                ////////////////////
                $scoreConfigs = (new Configs())->getConfigs("score");
                $url = config("secret.inner_api.carpool.login");
                $token =  $scoreConfigs['score_token'];
                $postData = [
                    'phone' => $phone,
                    'client' => $client,
                    'token' => $token,
                ];

                if (input('post.getui_id')) {
                    $postData['getui_id'] = input('post.getui_id');
                }

                $form_params = [
                    'json' => $postData,
                    // 'header' => [
                    //   'Content-Type'     => 'application/json',
                    // ],
                ];
                $res = $this->clientRequest($url, $form_params, 'POST');

                if ($res && is_array($res)) {
                    $this->codeCache($usage, $phone, null); //清除使用后的验证码缓存
                    return json($res);
                } else {
                    $this->jsonReturn(-1, [], 'Failed', ['errorMsg' => $this->errorMsg]);
                }

                break;

                /******** 重置密码 *********/
            case 102:
                if (isset($_POST['password'])) {
                    $jwt = $this->getJwt();
                    if ($jwt) {
                        $userData = $this->getUserData(1);
                        if ($userData && $userData['phone'] != $phone) {
                            $this->jsonReturn(10001, [], lang('The phone number you entered is not the phone number of the current account'));
                        }
                    }
                    $password = input('post.password');
                    if (strlen($password) < 6) {
                        return $this->jsonReturn(992, [], lang('The new password should be no less than 6 characters'));
                    }
                    $hashPassword = md5($password); //加密后的密码
                    $status = UserModel::where([['phone', '=', $phone], ['is_delete', '=', Db::raw(0)]])->update(['md5password' => $hashPassword]);
                    // dump($status);
                    if ($status !== false) {
                        $step = 0;
                        //TODO: 单点登入如果开启，则执行踢出工动作。
                        $JwtToken = new JwtToken();
                        if ($jwt) {
                            $uid = $userData['uid'];
                            // $JwtToken->invalidate($jwt, -4);
                        } else {
                            $phoneUserData = UserModel::where([['phone', '=', $phone], ['is_delete', '=', Db::raw(0)]])->find();
                            $uid = $phoneUserData['uid'];
                        }
                        $JwtToken->invalidateByUid($uid, -4, []);
                        break;
                    } else {
                        return $this->jsonReturn(-1, [], "fail");
                    }
                }
                break;

                /******** 绑定手机号 *********/
            case 103:
                if ($userData['phone'] == $phone) {
                    break;
                }
                $UserModel = new UserModel();
                $phoneUserData = $UserModel->where([['loginname', '=', $phone], ['is_delete', '=', Db::raw(0)]])->find();
                if ($phoneUserData) {
                    $this->jsonReturn(10006, [], lang('The phone number has been registered for another account'));
                }
                $phoneUserData2 = $UserModel->where([['phone', '=', $phone], ['is_delete', '=', Db::raw(0)]])->find();
                try {
                    if ($phoneUserData2) {
                        $UserModel->where([['phone', '=', $phone], ['loginname', '<>', $phone]])->setField('phone', ''); //解绑其它帐号
                    }
                    $update_count = $UserModel->where('uid', $uid)->setField('phone', $phone); //绑定新号码
                    if (!$update_count) {
                        throw new \Exception(lang('Fail'));
                    }
                    $UserModel->deleteDetailCache($uid, true);
                    $UserModel->deleteDetailCache($phoneUserData['loginname']);
                    $UserModel->deleteDetailCache($phoneUserData2['uid'], true);
                    $UserModel->deleteDetailCache($phoneUserData2['loginname']);
                    // 提交事务
                    Db::commit();
                } catch (\Exception $e) {
                    // 回滚事务
                    Db::rollback();
                    $logMsg = '绑定手机号失败' . json_encode($this->request->post());
                    $this->log($logMsg, -1);
                    $this->codeCache($usage, $phone, null); //清除使用后的验证码缓存
                    $this->jsonReturn(-1, [], lang('Failed'));
                }
                break;


                /******** 合并帐号 *********/
            case 104:
                $type = input('param.type', 0);

                $UserModel = new UserModel();

                if (!$type) { // 默认必先做jwt验证
                    $userData = $this->getUserData(1);
                } else { //没有jwt，则使用账号密码登入验证
                    $username = input('post.username');

                    $pw_m = input('post.pw_m');
                    $password = input('post.password');

                    $userData = $UserModel->checkedPassword($username, $password);
                    if (!$userData) {
                        $errorCode = $UserModel->errorCode ? $UserModel->errorCode : -1;
                        $this->jsonReturn($errorCode, [], $UserModel->errorMsg);
                    }
                }
                $uid = $userData['uid'];
                if ($userData['phone'] == $phone) {
                    $this->jsonReturn(10100, [], lang('The phone number has been bound to this account, no need to merge.'));
                }

                // $phoneUserData = UserModel::where([['phone','=',$phone]])->find(); //取得要合并的手机号信息。
                $phoneUserData = $UserModel->where([['loginname', '=', $phone],['is_delete', '=', Db::raw(0)]])->find();

                if (!$phoneUserData) {
                    $this->jsonReturn(-1, lang('No need to merge'));
                }

                $AccountModel = new ScoreAccountModel();
                $checkScoreAccount = $AccountModel->registerAccount($userData['loginname']);
                if (!$checkScoreAccount || (isset($checkScoreAccount['code']) && $checkScoreAccount['code'] !== 0)) {
                    $this->jsonReturn(-1, null, lang('Failed'), ['debug'=>'查询或注册工号的积分账号失败']);
                }

                Db::connect('database_carpool')->startTrans();
                try {
                    $nowTime = time();
                    $update_phoneUserData = [
                        "loginname" => 'delete_' . $phoneUserData['loginname'] . '_' . $nowTime,
                        "phone" => 'delete_' . $phoneUserData['phone'],
                        "is_active" => 0,
                        "is_delete" => 1
                    ];
                    Db::connect('database_carpool')->table('user')->where('uid', $phoneUserData['uid'])->update($update_phoneUserData); //更改原手机账号状态为禁用。
                    $extra = $userData['extra_info'] ? json_decode($userData['extra_info'], true) : [];
                    $extra = $extra ? $extra :[];
                    $extra['merge_id'] = isset($extra['merge_id']) && is_array($extra['merge_id']) ? array_push($extra['merge_id'], $phoneUserData['uid']) : [$phoneUserData['uid']];
                    $update_userData = [
                        "phone" => $phone,
                        "extra_info" => json_encode($extra)
                    ];
                    Db::connect('database_carpool')->table('user')->where('uid', $uid)->update($update_userData); //工号账号绑定手机号
                    $AccountModel->mergeScore($userData['loginname'], $phoneUserData['loginname'], $nowTime);
                    // TODO: 解绑第三方账号
                    $UserOauthModel = new UserOauthModel();
                    $UserOauthModel->unbindByUid($phoneUserData['uid']);
                    // 提交事务
                    Db::connect('database_carpool')->commit();
                } catch (\Exception $e) {
                    // 回滚事务
                    Db::connect('database_carpool')->rollback();
                    $logMsg = "合并账号失败:" . json_encode($this->request->post());
                    $this->log($logMsg, -1);
                    if ($e->getMessage() == "10002") {
                        $this->jsonReturn(10002, [], lang("Please log in directly to the employee number to perform the binding operation"), ['debug' => $e->getMessage()]);
                    } else {
                        $this->jsonReturn(-1, [], lang('Fail'), ['debug' => $e->getMessage()]);
                    }
                }
                $this->log('合并账号成功', 0);
                break;

            case 201: //开通支付
                //  TODO: 开通支付
                break;

            case 202: //重置支付
                //  TODO: 重置支付
                break;

            default:
                # code...
                break;
        }

        if (!$step) {
            $this->codeCache($usage, $phone, null); //清除使用后的验证码缓存
        }
        $this->jsonReturn(0, $returnData, 'success');
        exit;
    }



    /**
     * 发送手机短信验证码
     * @param  string  $phone      电话
     * @param  integer  $usage      场境
     * @param  integer $codeLen    验证码长度
     * @param  integer  $expiration 缓存时间 默认15分钟。
     * @return [json]              []
     */
    public function sendCode($phone, $usage, $codeLen = 6, $expiration = 900, $dev = 0)
    {
        $usageSetting = $this->getUsageSetting($usage);
        if (!$usageSetting) {
            $this->jsonReturn(992, [], 'usage error');
            exit;
        }
        $templateid = $usageSetting['tpl'];

        // $templates = $this->SmsTemplate;
        // $templateid =   $templates['u_' . $usage]; //短信验证码的模板ID

        if ($this->test_inter) {
            $templateid = '9284311';
        }
        $cacheData_o = $this->codeCache($usage, $phone);

        if ($cacheData_o && time() - $cacheData_o['time'] < 52 && !$dev) {  //1分钟内不准再发。
            return array('code' => 10200, 'desc' => 'too often');
        }
        $NIM = new NimServer($this->appKey, $this->appSecret, 'fsockopen');
        // var_dump($SMS);
        $phone = preg_replace('# #', '', $phone);
        if ($dev) {
            $sendRes  = array( //test
                'code'  => 200,
                'msg'   => '',
                'obj'   => 561111
            );
        } else {
            // 随机生成一个验证码
            $code = getRandomString($codeLen, 1);
            $params = [
                'templateid' => $templateid,
                'mobile' => $phone,
                'authCode' => $code,
            ];
            $sendRes = $NIM->sendSmsCode($params);  //调用接口发送验证码
        }
        /**/
        if (isset($sendRes['code']) && $sendRes['code'] == 200 && isset($sendRes['obj'])) {
            $this->codeCache($usage, $phone, $sendRes['obj'], $sendRes['msg'], $expiration);
        }
        if (isset($sendRes['obj'])) {
            unset($sendRes['obj']);
        }

        return  $sendRes;
    }

    /**
     * 验证模版短信
     * @param  [type] $phone [description]
     * @param  [type] $code  [description]
     * @param  [type] $usage [description]
     * @return [type]        [description]
     */
    public function sendTemplate($phone = array(), $usage = null)
    {
        $usageSetting = $this->getUsageSetting($usage);
        if (!$usageSetting) {
            $this->jsonReturn(992, [], 'usage error');
            exit;
        }
        $templateid = $usageSetting['tpl'];
        // $templates = $this->SmsTemplate;
        // $templateid =   $templates['u_' . $usage]; //短信验证码的模板ID

        $NIM = new NimServer($this->appKey, $this->appSecret, 'fsockopen');
        $userData = $this->getUserData(1);

        switch ($usage) {
            case 300:
                $params = [$userData['name']];
                break;
            case 302:
                $param  = input('param.param');
                $link_code  = input('param.link_code');
                if (!$param) {
                    $this->jsonReturn(992, [], 'param empty');
                }
                $params = [$userData['name'], $link_code];
                break;

            default:
                # code...
                break;
        }

        foreach ($phone as $key => $value) {
            $phone[$key] = preg_replace('# #', '', $value);
        }
        $sendRes = $NIM->sendSMSTemplate($templateid, $phone, $params);  //调用接口发送验证码
        /*$sendRes  = array( //test
            'code'  => 200,
            'msg'   => 'sendid',
            'obj'   => 101
        );*/
        return  $sendRes;
    }


    /**
     * 查询短信发送情况
     */
    public function sms_status($sendid)
    {
        $NIM = new NimServer($this->appKey, $this->appSecret, 'fsockopen');
        $res = $NIM->querySMSStatus($sendid);
        $this->jsonReturn(0, $res);
    }
}
