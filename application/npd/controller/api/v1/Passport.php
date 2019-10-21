<?php

namespace app\npd\controller\api\v1;

use app\api\controller\ApiBase;
use app\carpool\model\User as UserModel;
use app\user\model\Department as DepartmentModel;
use app\user\model\JwtToken;
use Firebase\JWT\JWT;
use think\Db;

/**
 * 发放通行证jwt
 * Class Passport
 * @package app\npd\controller\api\v1
 */
class Passport extends ApiBase
{

    protected function initialize()
    {
        parent::initialize();
    }

    /**
     * 取得登入用户信息
     */
    public function index($type = 1)
    {
        $this->checkPassport(true);

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
        }

        return $this->jsonReturn(0, $userInfo, "success");
    }


    /**
     * 登入，并生成jwt反回
     * @return mixed
     */
    public function login()
    {
        $bodyData = $this->request->post();
        $ciphertext = $bodyData['data'];

        $decrypttext = openssl_decrypt($ciphertext, 'aes-256-cbc', config('secret.api_hash.aes.key'), false, config('secret.api_hash.aes.iv'));
        $data = json_decode($decrypttext, true);
        if (!$data) {
            $this->jsonReturn(993, lang('Param error'));
        }

        if (empty($data['username'])) {
            $this->jsonReturn(993, lang('Please enter user name'));
        }
        if (empty($data['password'])) {
            $this->jsonReturn(993, lang('Please enter your password'));
        }
        $data['client'] = isset($data['client']) ? strtolower($data['client']) : '';
        if (!in_array($data['client'], array('web_npd'))) {
            $this->jsonReturn(-1, 'client error');
        };


        $userModel = new UserModel();
        $where = [
            ['loginname', '=', $data['username']],
        ];
        $userData = $userModel->where($where)->where([['is_delete','=',Db::raw(0)]])->find();
        if (!$userData) {
            $userData = $userModel->where($where)->find();
            if ($userData) {
                return $this->jsonReturn(10003, lang('The user is deleted'));
            } else {
                return $this->jsonReturn(10001, lang('User name or password error'));
            }
        }
        if (!$userData['is_active']) {
            $this->jsonReturn(10003, [], lang('The user is banned'));
        }

        if (strtolower($userData['md5password']) != strtolower($userData->hashPassword($data['password']))) {
            $this->jsonReturn(10001, lang('User name or password error'));
        }

        if (isset($data['name']) && $data['name'] != $userData['nativename']) {
            $this->jsonReturn(10001, lang('Name error'));
        }

        //验证用户是否离职
        $checkActiveRes = $userModel->checkDimission($userData['loginname'], 1);
        if (!$checkActiveRes) {
            if ($userModel->errorCode == 10003) {
                $errorMsg = lang('The user is deleted');
            } else {
                $errorMsg = lang('Login failed');
            }
            $this->jsonReturn(-1, [], $errorMsg, ['error' => $userModel->errorMsg]);
        }

        $jwt = $this->createPassportJwt(['uid' => $userData['uid'], 'loginname' => $userData['loginname'], 'client' => $data['client']], 3600*24);
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

        return $this->jsonReturn(0, $returnData, "success");
    }

    /**
     * 登出
     *
     */
    public function logout()
    {
        // TODO: 登出相关操作。
        return $this->jsonReturn(0, 'Successful');
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
