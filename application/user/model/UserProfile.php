<?php

namespace app\user\model;

use think\Model;

class UserProfile extends Model
{


    protected $table = 't_user_profile';
    protected $connection = 'database_carpool';
    protected $pk = 'id';

    public $errorMsg = '';


    public function syncUpdate($data)
    {
        if (!$data) {
            $this->errorMsg = "Data false";
            return false;
        }
        if (!isset($data['uid']) || !$data['uid']) {
            $this->errorMsg = "Did not set uid";
            return false;
        }
        //查找用户旧数据
        $oldData = $this->where("uid", $data['uid'])->find();
        $inputProfileData = [
            "realname" => $data['name'],
            "sex" => $data['sex'],
        ];
        if (!$oldData) { // 不存在用户则添加一个
            $data_default = [
                "uid" => $data["uid"],
            ];
            $inputProfileData = array_merge($data_default, $inputProfileData);
            $returnId = $this->insertGetId($inputProfileData);
            if ($returnId) {
                $this->errorMsg = "添加到profile成功";
                $inputProfileData['id'] = $returnId;
                $inputProfileData['success'] = 1;
                return $inputProfileData;
            } else {
                $this->errorMsg = "添加到profile失败";
                return false;
            }
        } else { //存在用户，则更新用户信息
            $res = $this->where("uid", $oldData["uid"])->update($inputProfileData);
            if ($res === false) {
                $this->errorMsg = "更新到profile失败";
                return false;
            } else {
                $this->errorMsg = "更新到profile成功";
                $inputProfileData['id'] = $oldData['id'];
                $inputProfileData['success'] = 2;
                return $inputProfileData;
            }
        }
    }
}
