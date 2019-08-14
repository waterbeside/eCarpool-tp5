<?php

namespace app\user\model;

use think\Model;

class User extends Model
{


    protected $table = 't_user';
    protected $connection = 'database_carpool';
    protected $pk = 'uid';

    public $errorMsg = '';

    public function profile()
    {
        return $this->hasOne('tUserProfile', 'uid');
    }

    public function hashPassword($password)
    {
        return md5($password);
    }

    public function createPasswordFromCode($code, $hash = 1)
    {
        $n = preg_match('/\d+/', $code, $arr);
        $pw = $n ? @$arr[0] : $code;
        $pw_len = strlen($pw);
        if ($pw_len < 6) {
            for ($i = 0; $i < (6 - $pw_len); $i++) {
                $pw = "0" . $pw;
            }
        }
        return $hash ? $this->hashPassword($pw) : $pw;
    }

    /**
     * 从temp拿到数
     * @var [type]
     */
    public function syncDataFromTemp($data)
    {
        $inputUserData = [
            "name" => $data['name'],
            "loginname" => $data['code'],
            "sex" => $data['sex'],
            "status" => 1,
            "modifty_time" => $data['modifty_time'],
            "department_id" => $data['department_id'],
            'company_id' => isset($data['department_city']) && mb_strtolower($data['department_city']) == "vietnam" ? 11 : 1,
        ];

        //查找用户旧数据
        $oldData = $this->where("loginname", $data['code'])->find();
        if (!$oldData) { // 不存在用户则添加一个
            $pw = $this->createPasswordFromCode($data['code']);

            $inputUserData_default = [
                'indentifier' => uuid_create(),
                'nickname' => $data['name'],
                // 'status' => 1,
                'password' => $pw,
            ];
            $inputUserData = array_merge($inputUserData_default, $inputUserData);
            $returnId = $this->insertGetId($inputUserData);
            if ($returnId) {
                $this->errorMsg = "添加到t_user成功";
                $inputUserData['uid'] = $returnId;
                $inputUserData['success'] = 1;
                return $inputUserData;
            } else {
                $this->errorMsg = "从临时库入库到正式库时，失败(新)";
                return false;
            }
        } else { //存在用户，则更新用户信息
            $inputUserData['uid'] = $oldData['uid'];
            if (strtotime($oldData['modifty_time']) >= strtotime($data['modifty_time'])) {
                $this->errorMsg = "用户已存在，并且信息已最新，无须更新(新)";
                $inputUserData['success'] = -2;
                return $inputUserData;
            }
            $res = $this->where("uid", $oldData["uid"])->update($inputUserData);
            if ($res === false) {
                $this->errorMsg = "用户已存在，但更新信息时，更新失败(新)";
                return false;
            } else {
                $this->errorMsg = "更新到t_user成功";
                $inputUserData['success'] = 2;
                return $inputUserData;
            }
        }
        //更新profile表
        // $profileData = $this->where("uid",$inputUserData['uid'])->find();
    }
}
