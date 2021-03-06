<?php

namespace app\api\controller\v1;

use app\api\controller\ApiBase;
use app\carpool\model\User as UserModel_o;
use app\carpool\model\Department as DepartmentModel_o;
use app\user\model\Department as DepartmentModel;
use app\score\model\AccountMix;
use app\user\model\JwtToken;
use com\nim\Nim as NimServer;
use app\carpool\model\Address;
use Firebase\JWT\JWT;
use think\Db;
use my\RedisData;

/**
 * 发放通行证jwt
 * Class Passport
 * @package app\api\controller
 */
class Passport extends ApiBase
{

    protected function initialize()
    {
        parent::initialize();
    }

    /**
     * 验证登入
     */
    public function index()
    {
        $this->checkPassport(true);
        $more = request()->param('more');
        $type = $more == 1 ? 1 : request()->param('type');
        $userInfo = $this->userBaseInfo;

        if (in_array($type, [1, 2])) {
            $uid = $userInfo['uid'];
            $userInfo_ex = $this->getUserData(true);
            $userInfo_ex['avatar'] = $userInfo_ex['imgpath'];
            $userInfo_ex['department'] = $userInfo_ex['Department'];

            if ($type == 2) {
                $fields = [
                    'uid', 'loginname', 'name', 'nativename',
                    'department', 'company_id', 'department_id',
                    'phone', 'mobile', 'avatar', 'imgpath', 'sex',
                    'companyname', 'home_address_id', 'company_address_id', 'indentifier',
                    'im_md5password', 'is_active', 'modifty_time', 'extra_info',
                    'carnumber', 'carcolor', 'client_id',
                ];
            }
            if ($type == 1) {
                $fields = [
                    'uid', 'loginname', 'name', 'nativename',
                    'department', 'company_id', 'department_id',
                    'avatar', 'imgpath'
                ];
            }
            $userInfo = $this->filtFields($userInfo_ex, $fields);
            if (isset($userInfo['sex'])) {
                $userInfo['sex'] = intval($userInfo['sex']);
            }
            if (isset($userInfo['company_id'])) {
                $userInfo['company_id'] = intval($userInfo['company_id']);
            }
            $userInfo['full_department'] = DepartmentModel::where("id", $userInfo['department_id'])->value('fullname');
        }

        return $this->jsonReturn(0, $userInfo, "success");
    }

    /**
     * 通过loginname账号取得用户信息并注册积分账号
     *
     */
    public function quick_info()
    {
        $type = request()->post('type', 0, 'intval');
        $account = request()->post('account', '', 'trim');
        if ($type === 1 && empty($account)) {
            return $this->jsonReturn(-1, '请输入工号');
        } elseif (!$type) {
            $userData = $this->getUserData(0);
            if (!$account && !$userData) {
                return $this->jsonReturn(-1, 'Error account');
            }
            $account = $account ?: $userData['loginname'];
        }
        if (!$account) {
            return $this->jsonReturn(-1, 'Error account');
        }

        $UserModel = new UserModel_o();
        $userDetail = $UserModel->getDetail($account);
        if (!$userDetail) {
            return $this->jsonReturn(20002, '用户不存在');
        }
        $userDetail['avatar'] = $userDetail['imgpath'];
        $userDetail['department'] = $userDetail['Department'];
        $userDetail['full_department'] = $userDetail['department_fullname'];
        
        $fields = [
            'uid', 'loginname', 'name', 'nativename',
            'department', 'company_id', 'department_id',
            'avatar', 'full_department'
        ];
        $userDetail = $this->filtFields($userDetail, $fields);
        if (isset($userDetail['sex'])) {
            $userDetail['sex'] = intval($userDetail['sex']);
        }
        if (isset($userDetail['company_id'])) {
            $userDetail['company_id'] = intval($userDetail['company_id']);
        }

        // 注册积分账号
        $AccountMix = new AccountMix();
        $checkScoreAccount = $AccountMix->registerAccount($userDetail['loginname']);
        if (!$checkScoreAccount || (isset($checkScoreAccount['code']) && $checkScoreAccount['code'] !== 0)) {
            return $this->jsonReturn(-1, null, lang('Failed'), ['debug'=>'查询或注册工号的积分账号失败']);
        }
        $returnData = [
            'userData' => $userDetail,
            'scoreAccount' => $checkScoreAccount,
        ];
        return $this->jsonReturn(0, $returnData, "success");
    }

    /**
     * 登入，并生成jwt反回
     * @return mixed
     */
    public function save()
    {
        $data = $this->request->post();
        if (empty($data['username'])) {
            $this->jsonReturn(-10002, lang('Please enter user name'));
        }
        if (empty($data['password'])) {
            $this->jsonReturn(-10002, lang('Please enter your password'));
        }
        $data['client'] = isset($data['client']) ? strtolower($data['client']) : '';
        if (!in_array($data['client'], array('ios', 'android', 'h5', 'web', 'third'))) {
            $this->jsonReturn(-1, 'client error');
        };


        $userModel_o = new UserModel_o();
        $userData = $userModel_o->where('loginname', $data['username'])->find();
        if (!$userData) {
            $userData = $userModel_o->where('phone', $data['username'])->find();
        }
        if (!$userData) {
            $this->jsonReturn(10001, lang('User name or password error'));
            return false;
        }
        if ($userData['is_delete']) {
            $this->jsonReturn(10003, lang('The user is banned'));
        }

        if (!$userData['is_active']) {
            $this->jsonReturn(10003, [], lang('The user is deleted'));
        }

        if (strtolower($userData['md5password']) != strtolower($userModel_o->hashPassword($data['password']))) {
            $this->jsonReturn(10001, lang('User name or password error'));
        }

        if (isset($data['name']) && $data['name'] != $userData['nativename']) {
            $this->jsonReturn(10001, lang('Name error'));
        }


        $jwt = $this->createPassportJwt(['uid' => $userData['uid'], 'loginname' => $userData['loginname'], 'client' => $data['client']]);
        $returnData = array(
            'user' => array(
                'uid' => $userData['uid'],
                'loginname' => $userData['loginname'],
                'name' => $userData['name'],
                'company_id' => $userData['company_id'],
                'avatar' => $userData['imgpath'],
            ),
            'token'    => $jwt
        );
        $isAllUserData = in_array($data['client'], ['ios', 'android']) ? 1 : 0;
        if ($isAllUserData) {
            $returnData['user'] = $userData;
            if (isset($returnData['user']['md5password'])) {
                $returnData['user']['md5password'] = '';
            }
            if (isset($returnData['user']['passwd'])) {
                $returnData['user']['passwd'] = '';
            }
        }

        return $this->jsonReturn(0, $returnData, "success");
    }


    /**
     * 更新用户资料（PATCH）
     */
    public function update_field($field = "")
    {
        //验证字段是否可以被改
        $field = strtolower($field);
        $fields = [
            'carnumber', 'carcolor', 'cartype', 'password', 'company_id',
            'name', 'mobile', 'myaddress', 'bbs_home_page_bg_img'
        ];
        if (!in_array($field, $fields)) {
            return $this->jsonReturn(-10002, "Error");
        }
        //验证帐号并取得账号信息
        $userData = $this->getUserData(true);
        $uid = $userData['uid'];

        $value = trim($this->request->param($field));
        if ($field == 'password') {
            $old_password = trim($value);
            if ($old_password == '') {
                $this->jsonReturn(10001, [], lang('Please enter the correct old password'));
            }
            if ($userData['md5password'] != md5($old_password)) {
                $this->jsonReturn(10001, [], lang('Please enter the correct old password'));
            }
            $pw_new = $this->request->param('pw_new');
            $pw_confirm = $this->request->param('pw_confirm');

            if ($pw_new  != $pw_confirm) {
                return $this->jsonReturn(-10002, [], lang('Two passwords are different'));
                // return $this->error('两次密码不一至');
            }
            if (strlen($pw_new) < 6) {
                return $this->jsonReturn(-10002, [], lang('The new password should be no less than 6 characters'));
                // return $this->error('密码不能少于6位');
            }
            $hashPassword = md5($pw_new); //加密后的密码
            $status = UserModel_o::where("uid", $uid)->update(['md5password' => $hashPassword]);
            if ($status !== false) {
                //TODO: 单点登入如果开启，则执行踢出工动作。
                $jwt = $this->getJwt();
                $JwtToken = new JwtToken();
                $JwtToken->invalidate($jwt, -4);

                return $this->jsonReturn(0, [], "success");
                // $this->success('修改成功');
            } else {
                return $this->jsonReturn(-1, [], "fail");
                // $this->error('修改失败');
            }
        } elseif ($field == 'department') { // 不再允许修改
            $department_id = $value;
            $departmentData = DepartmentModel_o::where(['departmentid' => $department_id])->find();

            if (!$departmentData) {
                return $this->jsonReturn(-1, [], "fail");
            }
            $status = UserModel_o::where("uid", $uid)->update(['Department' => $departmentData['department_name']]);
            if ($status !== false) {
                return $this->jsonReturn(0, [], "success");
            } else {
                return $this->jsonReturn(-1, [], "fail");
            }
        } elseif ($field == 'myaddress') {
            return $this->change_address();
        } elseif ($field == 'name') {
            if ($value == '') {
                return $this->jsonReturn(-1, [], lang('Can not be empty'));
            }
            $status = UserModel_o::where("uid", $uid)->update([$field => $value]);
            if ($status !== "false") {
                $appKey     = config('secret.nim.appKey');
                $appSecret  = config('secret.nim.appSecret');
                $NIM = new NimServer($appKey, $appSecret);
                $upNimData = [
                    'accid' => $this->userBaseInfo['loginname'],
                    'name'  => $value,
                ];
                $upNimRes = $NIM->updateUinfoByData($upNimData);
                return $this->jsonReturn(0, "Successful");
            } else {
                return $this->jsonReturn(-1, "Failed");
            }
        } elseif ($field == 'bbs_home_page_bg_img') {
            if (empty($value)) {
                return $this->jsonReturn(-1, [], lang('Can not be empty'));
            }
            $extra = $this->addDataToData(['bbs_home_page_bg_img' => $value], $userData['extra_info']);
            $status = UserModel_o::where("uid", $uid)->update(['extra_info' => json_encode($extra)]);
            if ($status !== false) {
                $redis = RedisData::getInstance();
                $redis->del("carpool:tweet:user:info:$uid");
                return $this->jsonReturn(0, "Successful");
            } else {
                return $this->jsonReturn(-1, "Failed");
            }
        } else {
            if (!in_array($field, array('carnumber', 'carcolor'))) {
                if ($value == '') {
                    return $this->jsonReturn(-1, lang('Can not be empty'));
                }
            }
            $status = UserModel_o::where("uid", $uid)->update([$field => $value]);
            // var_dump($status);
            if ($status !== false) {
                return $this->jsonReturn(0, "Successful");
                // $this->success('修改成功');
            } else {
                return $this->jsonReturn(-1, "Failed");
                // $this->error('修改失败');
            }
        }
    }


    /**
     * 改变地址
     */
    public function change_address()
    {
        $userData                 = $this->getUserData(true);
        $uid                      = $userData['uid'];
        $data['company_id']       = $userData['company_id'];
        $data['addressid']       =  $this->request->post('addressid');
        $data['addressname']      =  $this->request->post('addressname');
        $data['latitude']         =  $this->request->post('latitude');
        $data['longitude']        =  $this->request->post('longitude');
        $data['city']             = $this->request->post('city');
        $data['address']             = $this->request->post('address');
        $data['district']             = $this->request->post('district');
        $from                     = $this->request->post('from');
        if ($from == "work") {
            $from = "company";
        }


        if (!in_array($from, array('home', 'company'))) {
            $this->jsonReturn(992, [], lang('Parameter error'));
        }
        $createAddress = [];
        //处理起点
        if (!isset($data['addressid']) || !$data['addressid'] || !is_numeric($data['addressid'])) {
            $AddressModel = new Address();
            $data['create_uid'] = $uid;
            $res = $AddressModel->addOne($data, 50);
            if (!$res) {
                $errorMsg = $AddressModel->errorMsg ? $AddressModel->errorMsg  : lang("Fail");
                $this->jsonReturn(-1, [], $errorMsg);
            }
            $data['addressid'] = $res['addressid'];
            $createAddress[0] = $res;
        }
        if (!is_numeric($data['addressid'])) {
            $this->jsonReturn(992, [], lang('Parameter error'));
        }
        $status = UserModel_o::where("uid", $uid)->update([$from . '_address_id' => $data['addressid']]);
        if ($status !== false) {
            return $this->jsonReturn(0, ['createAddress' => $createAddress], "success");
        } else {
            return $this->jsonReturn(-1, [], "fail");
        }
    }

    /**
     * 登出
     */
    public function delete()
    {
        return $this->jsonReturn(0, "success");
    }

    protected function filtFields($datas, $fields)
    {
        if (is_string($fields)) {
            $fields = explode(",", $fields);
        }
        $datas_n = [];
        if (is_array($fields)) {
            foreach ($fields as $key => $value) {
                $datas_n[$value] = isset($datas[$value]) ? $datas[$value] : null;
            }
            return $datas_n;
        } else {
            return $datas;
        }
    }
}
