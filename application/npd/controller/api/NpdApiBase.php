<?php

namespace app\npd\controller\api;

use app\api\controller\ApiBase;
use app\carpool\model\User as CarpoolUser;
use app\npd\model\User as NpdUser;

use think\Db;

class NpdApiBase extends ApiBase
{

    protected function initialize()
    {
        // config('default_lang', 'zh-cn');
        parent::initialize();
    }


    /**
     * 取得登录用户的信息
     */
    public function getUserData($returnType = 0)
    {

        $jwtInfo = $this->jwtInfo;
        if (!$jwtInfo) {
            $this->checkPassport($returnType);
            $jwtInfo = $this->jwtInfo;
        }
        $iss =  $jwtInfo->iss;
        $uid = $this->userBaseInfo['uid'];
        if ($this->userData) {
            return $this->userData;
        }
        $username = '';
        if (strtolower($iss) === 'npd') {
            $NpdUser = new NpdUser();
            $userData = $NpdUser->findByUid($uid);
            if ($userData) {
                $username = $userData['account'];
                $is_active = $userData['status'];
            }
        } else {
            $CarpoolUser = new CarpoolUser();
            $userData = $CarpoolUser->findByUid($uid);
            if ($userData) {
                $username = $userData['loginname'];
                $is_active = $userData['is_active'];
            }
        }

        if (!$uid || !$userData) {
            return $returnType ? $this->jsonReturn(10004, lang('You are not logged in')) : false;
        }
        if (!$is_active) {
            return $returnType ? $this->jsonReturn(10003, lang('The user is banned')) : false;
        }
        if ($userData['is_delete']) {
            return $returnType ? $this->jsonReturn(10003, lang('The user is deleted')) : false;
        }

        $this->userData = [
            'uid'=>$uid,
            'username'=>$username,
            'iss'=>$iss,
            'data'=>$userData,
        ];
        return $this->userData;
    }

    /**
     * 替换附件地址的域名
     */
    public function replaceAttachmentDomain($data, $field = '')
    {
        if (is_string($data)) {
            $urls = config('npd.replace_attachment_url');
            foreach ($urls as $key => $value) {
                $data = str_replace($key, $value, $data);
            }
        }
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key][$field] = $this->replaceAttachmentDomain($data[$key][$field]);
            }
        }
        return $data;
    }
}
