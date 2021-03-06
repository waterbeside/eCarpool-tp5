<?php

namespace app\api\controller\v1;

use think\Db;
use app\api\controller\ApiBase;
use app\carpool\model\User as UserModel;
use app\user\model\UserOauth;
use think\facade\Cache;
use my\RedisData;
use com\nim\Nim as NimServer;

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
    protected $SmsTemplate  = array(
        // 'u_000'   => '4022147',
        'u_100' => '4072148', //用于登录
        'u_101' => '4022223', //用于注册
        'u_102' => '4022224', //用于重置
        'u_103' => '3892309', //用于绑定手机号
        'u_104' => '4022147', //用于合并帐号

        'u_200' => '4022147', //用于确认通用操作
        'u_201' => '4022147', //用于确认支付

        'u_300' => '4012242', //用于邀请下载
        'u_301' => '4012242', //用于邀请建立好友关系
        'u_302' => '4012426', //用于邀请进群
    );

    protected function initialize()
    {
        parent::initialize();
        $test_inter = input('param.test_inter');
        if ($test_inter) {
            $this->appKey = "8542794600d06d41ec89c8956972f886";
            $this->appSecret  = "7310b481e48f";
            $this->test_inter = 1;
        } else {
            $this->appKey     = config('secret.nim.appKey');
            $this->appSecret  = config('secret.nim.appSecret');
        }
    }


    /**
     * 处理验证码缓存
     * @param  integer  $usage 场景
     * @param  string  $phone 电话号
     * @param  mixed  $code  false时为取值，null为清空，其它为插值
     * @param  string  $msg   插值留的信息
     * @param  integer $exp   过期时间
     */
    protected function codeCache($usage, $phone, $code = false, $msg = "", $exp = 900)
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
            $redis->setex($key, $exp, json_encode($data));
            return true;
        }
        if ($code === false) {
            // $res_array = Cache::tag('public')->get($key);
            $res = $redis->get($key);
            if (!$res) {
                return false;
            }
            $res_array = json_decode($res, true);
            return $res_array;
        }
        if ($code === null) {
            // Cache::tag('public')->rm($key);
            $res = $redis->del($key);
            return true;
        }
    }


    /**
     * 合并积分
     * tAccount  目标账号
     * $oAccount 被删账号
     */
    protected function mergeScore($tAccount, $oAccount, $nowTime)
    {
        Db::connect('database_score')->startTrans();
        try {
            $scoreAccount_t = Db::connect('database_score')->table('t_account')->where([['carpool_account', '=', $tAccount], ['is_delete', '=', Db::raw(0)]])->find(); //取出目标员工号的积分账号信息
            $scoreAccount_o = Db::connect('database_score')->table('t_account')->where([['carpool_account', '=', $oAccount], ['is_delete', '=', Db::raw(0)]])->find(); //取出手机号的积分账号信息
            if (!$scoreAccount_t) {
                throw new \Exception('10002');
            } else {
                $score_t = $scoreAccount_t['balance'];
                $score_o = $scoreAccount_o['balance'];
                $score_new = $score_t + $score_o; //合并积分
                $historyData = [
                    "account_id" => $scoreAccount_t['id'],
                    "operand" => abs($score_o),
                    "reason" => $score_o > 0 ? 301 : -301,
                    "result" => $score_new,
                    "extra_info" => '{}',
                    "is_delete" => 0,
                    "time" => date('Y-m-d H:i:s'),
                ];
                Db::connect('database_score')->table('t_history')->insert($historyData); //插入因合并
                Db::connect('database_score')->table('t_account')->where([['carpool_account', '=', $tAccount]])->setField('balance', $score_new); //员工账号加到手号账的积分
                $historyData_o = [
                    "account_id" => $scoreAccount_o['id'],
                    "operand" => abs($score_o),
                    "reason" => -301,
                    "result" => 0,
                    "extra_info" => '{}',
                    "is_delete" => 0,
                    "time" => date('Y-m-d H:i:s'),
                ];
                Db::connect('database_score')->table('t_history')->insert($historyData_o); //旧账号添加一条扣分的历史
                Db::connect('database_score')->table('t_account')->where([['carpool_account', '=', $oAccount]])->update(['carpool_account' => 'delete_' . $oAccount . '_' . $nowTime, 'is_delete' => 0, 'balance' => 0]); //删除手机账号的积分账号
                // 提交事务
                Db::connect('database_score')->commit();
            }
        } catch (\Exception $e) {
            // echo($e);
            Db::connect('database_score')->rollback();
            throw new \Exception($e->getMessage());
        }
    }




    /*
    解释手机号
    当手机号为字符串时，处理为数组.多个手机号以','格开。
    例如 phone=[13112345678,13212345678] 或 phone=13112345678,13212345678 或 phone=13112345678有形式皆可
     */
    protected function formatPhones($phones)
    {
        if (is_string($phones)) {
            if (trim($phones) == '') {
                $this->jsonReturn(-10001, [], 'phone empty');
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
            $this->jsonReturn(-10002, [], 'phone error');
            exit;
        }
        if (!$phones || trim($phones[0]) == '') {
            $this->jsonReturn(-10001, [], 'phone empty');
            exit;
        }
        return $phones;
    }



    /**
     * 发送验证码
     */
    public function send($usage = 0, $phone = null)
    {
        $dev = input('param.dev');
        if (!$usage) {
            $this->jsonReturn(992, [], 'usage empty');
            exit;
        }
        if (!isset($this->SmsTemplate['u_' . $usage])) {
            $this->jsonReturn(992, [], 'usage error');
            exit;
        }
        $phones = $this->formatPhones($phone);
        $phone = $phones[0];
        // var_dump($phones);
        $sendCallBack = array();
        $isSuccess = 0;

        /******* 非群发的验证场景 ******/
        if (in_array($usage, array(100, 101, 102, 103, 104, 201, 200))) {
            $phoneUserData = UserModel::where([['phone', '=', $phone]])->find();

            if (in_array($usage, array(103, 104, 200, 201))) { // 验证是否登入
                $userData = $this->getUserData(1);
            }

            switch ($usage) {
                case 100: //登入
                    if (!$phoneUserData) { //登入验证手机号是否存在。
                        $this->jsonReturn(10002, [], lang('User does not exist'));
                    }
                    break;
                case 101: //注册
                    if ($phoneUserData) { //注册 验证手机号是否存在。
                        $this->jsonReturn(10006, [], lang('User already exists'));
                    }
                    break;
                case 102: //重置密码
                    $userData = $this->getUserData();
                    if ($userData && $userData['uid'] != $phoneUserData['uid']) {
                        $this->jsonReturn(10001, [], lang('The phone number you entered is incorrect'));
                    }
                    if (!$phoneUserData) { //验证手机号是否存在。
                        $this->jsonReturn(10002, [], lang('User does not exist'));
                    }
                    break;
                case 103: //重绑定
                    if ($userData['phone'] == $phone) {
                        $this->jsonReturn(10100, [], lang('Already bound, please enter a new phone number'));
                    }
                    $phoneUserData2 = UserModel::where([['loginname', '=', $phone]])->find();
                    if ($phoneUserData2) {
                        $this->jsonReturn(10006, [], lang('The mobile phone number has been marked with a new account, whether to merge?'));
                    }
                    /*  if($phoneUserData && $phoneUserData['phone'] == $phones[0]){
                    $this->jsonReturn(10006,[],'该手机号已绑定其它帐号');
                    }*/
                    break;
                case 104: //合并账号
                    if ($userData['phone'] == $phone) {
                        $this->jsonReturn(10100, [], lang('The phone number has been bound to this account, no need to merge.'));
                    }
                    $phoneUserData2 = UserModel::where([['loginname', '=', $phone]])->find();
                    if (!$phoneUserData2) {
                        $this->jsonReturn(10006, [], lang('No need to merge'));
                    }

                    break;

                default:
                    # code...
                    break;
            }
            foreach ($phones as $key => $phone) {
                $sendCallBack[$phone] = $this->sendCode($phone, $usage, 6, 900, $dev);
                if ($sendCallBack['' . $phone]['code'] == 200) {
                    $isSuccess = 1;
                }
                break;
            }
            if ($isSuccess) {
                $this->jsonReturn(0, $sendCallBack, 'success');
            } else {
                if ($sendCallBack['' . $phone]['code'] == 10200) {
                    $this->jsonReturn(10200, $sendCallBack, 'too often');
                }
                if ($sendCallBack['' . $phone]['code'] == 414) {
                    $this->jsonReturn(992, $sendCallBack, 'bad format');
                }
                $this->jsonReturn(-1, $sendCallBack, 'fail');
            }
        }

        /*******  模板短信场景 ******/
        if (in_array($usage, array(300, 301, 302))) {
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
        if (!$this->checkSMSCode($phone, $code, $usage)) {
            $this->jsonReturn(10101, [], 'fail (code error)');
            exit;
        }

        $returnData =  [];
        if (in_array($usage, array(103, 104, 200, 201))) { // 验证是否登入
            $userData = $this->getUserData(1);
            $uid = $this->userBaseInfo['uid'];
        }

        switch ($usage) {
                /******** 验证登入 *********/
            case 100:
                $client = input('param.client');
                if (!in_array($client, array('ios', 'android', 'h5', 'web', 'third'))) {
                    return  $this->jsonReturn(992, [], 'client error');
                };
                // collect user input data
                $phoneUserData = UserModel::where([['phone', '=', $phone]])->find();
                if (!$phoneUserData) {
                    $this->jsonReturn(10002, [], 'user does not exist');
                }
                if (!$phoneUserData['is_active']) {
                    $this->jsonReturn(10003, [], 'user does not active');
                }

                if ($phoneUserData['is_delete']) {
                    $this->jsonReturn(10003, [], 'user does not active');
                }



                $jwt = $this->createPassportJwt(['uid' => $phoneUserData['uid'], 'loginname' => $phoneUserData['loginname'], 'client' => $client]);
                $returnData = array(
                    'user' => array(
                        'uid' => $phoneUserData['uid'],
                        'loginname' => $phoneUserData['loginname'],
                        'name' => $phoneUserData['name'],
                        'company_id' => $phoneUserData['company_id'],
                        'avatar' => $phoneUserData['imgpath'],
                    ),
                    'token'  => $jwt
                );


                $isAllUserData = in_array($client, ['ios', 'android']) ? 1 : 0;
                if ($isAllUserData) {
                    $returnData['user'] = $phoneUserData;
                    if (isset($returnData['user']['md5password'])) {
                        $returnData['user']['md5password'] = '';
                    }
                    if (isset($returnData['user']['passwd'])) {
                        $returnData['user']['passwd'] = '';
                    }
                }
                $oAuthList = UserOauth::where('user_id', $phoneUserData['uid'])->select();
                if ($oAuthList) {
                    foreach ($oAuthList as $key => $value) {
                        $returnData['user']['open_id_type_' . $value['type']] = $value['identifier'];
                    }
                }
                if (isset($returnData['user'])) {
                    break;
                } else {
                    $this->jsonReturn(-1, [], 'fail');
                }
                break;

                /******** 重置密码 *********/
            case 102:
                if (isset($_POST['password'])) {
                    $userData = $this->getUserData();
                    if ($userData && $userData['phone'] != $phone) {
                        $this->jsonReturn(10001, [], lang('The phone number you entered is incorrect'));
                    }
                    $password = input('post.password');
                    if (strlen($password) < 6) {
                        return $this->jsonReturn(992, [], lang('The new password should be no less than 6 characters'));
                    }
                    $hashPassword = md5($password); //加密后的密码
                    $status = UserModel::where([['phone', '=', $phone], ['is_delete', '=', Db::raw(0)], ['is_active', '=', Db::raw(1)]])
                        ->update(['md5password' => $hashPassword]);
                    if ($status !== false) {
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
                $phoneUserData = UserModel::where([['loginname', '=', $phone]])->find();
                if ($phoneUserData) {
                    $this->jsonReturn(10006, [], lang('The phone number has been registered for another account'));
                }
                $phoneUserData2 = UserModel::where([['phone', '=', $phone]])->find();
                try {
                    if ($phoneUserData2) {
                        UserModel::where([['phone', '=', $phone], ['loginname', '<>', $phone]])->setField('phone', ''); //解绑其它帐号
                    }
                    $update_count = UserModel::where('uid', $uid)->setField('phone', $phone); //绑定新号码
                    if (!$update_count) {
                        throw new \Exception(lang('Fail'));
                    }
                    // 提交事务
                    Db::commit();
                } catch (\Exception $e) {
                    // 回滚事务
                    Db::rollback();
                    $logMsg = '绑定手机号失败' . json_encode($this->request->post());
                    $this->log($logMsg, -1);
                    $this->codeCache($usage, $phone, null); //清除使用后的验证码缓存
                    $this->jsonReturn(-1, [], lang('Fail'));
                }
                break;
                /******** 合并帐号 *********/
            case 104:
                // $phoneUserData = UserModel::where([['phone','=',$phone]])->find(); //取得要合并的手机号信息。
                $phoneUserData = UserModel::where([['loginname', '=', $phone]])->find();
                if (!$phoneUserData) {
                    $this->jsonReturn(-1, [], lang('No need to merge'));
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
                    $extra['merge_id'] = isset($extra['merge_id']) && is_array($extra['merge_id']) ?
                        array_push($extra['merge_id'], $phoneUserData['uid']) : [$phoneUserData['uid']];
                    $update_userData = [
                        "phone" => $phone,
                        "extra_info" => json_encode($extra)
                    ];
                    Db::connect('database_carpool')->table('user')->where('uid', $uid)->update($update_userData); //工号账号绑定手机号
                    $this->mergeScore($userData['loginname'], $phoneUserData['loginname'], $nowTime);
                    // 提交事务
                    Db::connect('database_carpool')->commit();
                } catch (\Exception $e) {
                    // echo($e);
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
        $templates = $this->SmsTemplate;
        $templateid =   $templates['u_' . $usage]; //短信验证码的模板ID

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
            $sendRes = $NIM->sendSmsCode($templateid, $phone, '', $codeLen);  //调用接口发送验证码
        }
        /**/
        if (isset($sendRes['obj'])) {
            $this->codeCache($usage, $phone, $sendRes['obj'], $sendRes['msg'], $expiration);
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
        $templates = $this->SmsTemplate;
        $templateid =   $templates['u_' . $usage]; //短信验证码的模板ID

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
                    $this->jsonReturn(-10001, [], 'param empty');
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
