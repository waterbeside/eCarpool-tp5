<?php

namespace app\npd\controller\api\v1;

use app\npd\controller\api\NpdApiBase;
use app\carpool\model\User as CarpoolUser;
use app\npd\model\CpaUser;
use app\npd\model\CpaDepartment;
use app\npd\model\User as NpdUser;
use app\user\model\Department as DepartmentModel;
use app\user\model\JwtToken;
use Firebase\JWT\JWT;
use think\Db;

/**
 * 发放通行证jwt
 * Class Passport
 * @package app\npd\controller\api\v1
 */
class Passport extends NpdApiBase
{

    protected function initialize()
    {
        parent::initialize();
    }

    /**
     * 取得登入用户信息
     */
    public function index()
    {
        $this->checkPassport(true);

        $userInfo = $this->userBaseInfo;
        $uid = $userInfo['uid'];
        $userInfo_ex = $this->getUserData(true);
        $userData = $userInfo_ex['data'];

        $userData['avatar'] = isset($userData['imgpath']) ? $userData['imgpath'] : '';
        $userData['department'] = isset($userData['Department']) ? $userData['Department'] : '';

        if (strtolower($userInfo_ex['iss'])=== 'npd') {
            $fields = [
                'id', 'account', 'nickname'
            ];
        } else {
            $fields = [
                'uid', 'loginname', 'name', 'nativename',
                'department', 'company_id', 'department_id',
                'avatar', 'imgpath'
            ];
        }
        $userData = $this->filtFields($userData, $fields);
        $userInfo_ex['data'] = $userData;
        return $this->jsonReturn(0, $userInfo_ex, "success");
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
        
        $data['usertype'] = isset($data['usertype']) ? $data['usertype'] : 'carpool';

        if ($data['usertype'] === 'npd') { // 如果是NPD账户
            $NpdUser = new NpdUser();
            $where = [
                ['account', '=', $data['username']],
            ];
            $userData = $NpdUser->where($where)->where([['is_delete','=',Db::raw(0)]])->find();
            if (!$userData) {
                $userData = $NpdUser->where($where)->find();
                if ($userData) {
                    return $this->jsonReturn(10003, lang('The user is deleted'));
                } else {
                    return $this->jsonReturn(10001, lang('User name or password error'));
                }
            }
            if (!$userData['status']) {
                $this->jsonReturn(10003, [], lang('The user is banned'));
            }
            if (strtolower($userData['password']) != strtolower($NpdUser->hashPassword($data['password'], $userData['salt'], 1))) {
                $this->jsonReturn(10001, lang('User name or password error'));
            }
            $iss = 'npd';
            $userData['uid'] = $userData['id'];
            $userData['loginname'] = $userData['account'];
            $userData['imgpath'] = '';
        } else { // 如果是Carpool账户
            $CarpoolUser = new CarpoolUser();
            $where = [
                ['loginname', '=', $data['username']],
            ];
            $userData = $CarpoolUser->where($where)->where([['is_delete','=',Db::raw(0)]])->find();
            if (!$userData) {
                $userData = $CarpoolUser->where($where)->find();
                if ($userData) {
                    return $this->jsonReturn(10003, lang('The user is deleted'));
                } else {
                    return $this->jsonReturn(10001, lang('User name or password error'));
                }
            }
            if (!$userData['is_active']) {
                $this->jsonReturn(10003, [], lang('The user is banned'));
            }
            if (strtolower($userData['md5password']) != strtolower($CarpoolUser->hashPassword($data['password']))) {
                $this->jsonReturn(10001, lang('User name or password error'));
            }
            if (isset($data['name']) && $data['name'] != $userData['nativename']) {
                $this->jsonReturn(10001, lang('Name error'));
            }

            //验证用户是否离职
            $checkActiveRes = $CarpoolUser->checkDimission($userData['loginname'], 1);
            if (!$checkActiveRes) {
                if ($CarpoolUser->errorCode == 10003) {
                    $errorMsg = lang('The user is deleted');
                } else {
                    $errorMsg = lang('Login failed');
                }
                $this->jsonReturn(-1, [], $errorMsg, ['error' => $CarpoolUser->errorMsg]);
            }
            $iss = 'carpool';
            $userData['nickname'] = $userData['name'];
            // 检查该用户是否授权登入NPD网站
            
            $carpoolUserAccess = config('npd.carpool_user_access') ?? 0;
            
            if ($carpoolUserAccess != 1) {
                if ($carpoolUserAccess == 0) {
                    return $this->jsonReturn(10005, lang('Permission denied'));
                }
                $CpaUser  = new CpaUser();
                $userCheckRes = $CpaUser->checkAccess($userData['uid']);

                if (!$userCheckRes) {
                    $CpaDepartment  = new CpaDepartment();
                    $DepartmentModel = new DepartmentModel();
                    $departmentData = $DepartmentModel->getItem($userData['department_id']);
                    if (empty($departmentData)) {
                        return $this->jsonReturn(10005, lang('Permission denied'));
                    }
                    $path = "{$departmentData['path']},{$userData['department_id']}";
                    if (!$CpaDepartment->checkAccess($path)) {
                        return $this->jsonReturn(10005, lang('Permission denied'));
                    }
                }
            }
        }
        
        $jwtPaylod = ['uid' => $userData['uid'], 'loginname' => $userData['loginname'], 'client' => $data['client']];
        $jwt = $this->createPassportJwt($jwtPaylod, 3600*24, $iss);

        $returnData = array(
            'user' => array(
                'uid' => $userData['uid'],
                'loginname' => $userData['loginname'],
                'nickname' => $userData['nickname'],
                'avatar' => $userData['imgpath'],
                'usertype'=> $iss,
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
