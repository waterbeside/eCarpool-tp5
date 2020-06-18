<?php

namespace app\api\controller\v2;

use app\api\controller\ApiBase;
use app\carpool\model\User as UserModel;
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
     * 登入，并生成jwt反回
     * @return mixed
     */
    public function save()
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
        if (!in_array($data['client'], array('ios', 'android', 'h5', 'web', 'third', 'web_market'))) {
            $this->jsonReturn(-1, 'client error');
        };


        $UserModel = new UserModel();
        $userData = $UserModel->where('loginname', $data['username'])->find();
        if (!$userData) {
            $userData = $UserModel->where('phone', $data['username'])->find();
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

        if (strtolower($userData['md5password']) != strtolower($UserModel->hashPassword($data['password']))) {
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

        //验证用户是否离职
        $checkActiveRes = $UserModel->checkDimission($userData['loginname']);
        if (!$checkActiveRes) {
            if ($UserModel->errorCode == 10003) {
                $errorMsg = lang('This account employee has left');
            } else {
                $errorMsg = lang('Login failed');
            }
            $this->jsonReturn(-1, [], $errorMsg, ['error' => $UserModel->errorMsg]);
        }

        return $this->jsonReturn(0, $returnData, "success");
    }
}
