<?php

namespace app\carpool\model;

use app\common\model\Configs;
use app\common\model\BaseModel;
use my\RedisData;

// use think\Model;

class User extends BaseModel
{
    // protected $insert = ['create_time'];

    /**
     * 创建时间
     * @return bool|string
     */
    /*protected function setCreateTimeAttr()
    {
        return date('Y-m-d H:i:s');
    }*/

    protected $table = 'user';
    protected $connection = 'database_carpool';
    protected $pk = 'uid';



    /**
     * 取得账号详情
     *
     * @param integer $uid 用户id
     * @param integer $exp 缓存有效时间
     */
    public function findByUid($uid = "", $exp = 60 * 5)
    {
        if (!$uid) {
            return false;
        }
        $cacheKey = "carpool:user:detail:uid_" . $uid;
        $redis = new RedisData();
        $cacheData = $redis->cache($cacheKey);
        if ($cacheData) {
            return $cacheData;
        }
        $res = $this->find($uid);

        if ($res) {
            $res = $res->toArray();
            $exp_offset = getRandValFromArray([1,2,3]);
            $exp +=  $exp_offset * 60;
            $cacheData = $redis->cache($cacheKey, $res, $exp);
        }
        return $res;
    }

    /**
     * 取得账号详情
     *
     * @param string 用户名
     */
    public function getDetail($account = "", $exp = 3600)
    {
        if (!$account) {
            return false;
        }
        $cacheKey = "carpool:user:detail:ac_" . strtolower($account);
        $redis = new RedisData();
        $cacheData = $redis->cache($cacheKey);
        if ($cacheData) {
            return $cacheData;
        }
        $field = "u.*, c.company_name, d.name as department_name , d.path, d.fullname as department_fullname ";
        $map = [
            ['loginname', '=', $account],
            ['is_delete', '=', 0],
        ];
        $carpoolUser_jion =  [
            ['company c', 'u.company_id = c.company_id', 'left'],
            ['t_department d', 'u.department_id = d.id', 'left'],
        ];
        $res = $this->alias('u')->field($field)->join($carpoolUser_jion)->where($map)->find();
        if (!$res) {
            $map = [
                ['loginname', '=', $account],
            ];
            $res = $this->alias('u')->field($field)->join($carpoolUser_jion)->where($map)->find();
        }

        if ($res) {
            $res = $res->toArray();
            $exp_offset = getRandValFromArray([0, 5, 10, 15, 20, 25, 30]);
            $exp +=  $exp_offset * 60;
            $cacheData = $redis->cache($cacheKey, $res, $exp);
        }
        return $res;
    }

    /**
     * 删除用户详情数据
     *
     * @param string $account
     * @return void
     */
    public function deleteDetailCache($account = "")
    {
        $cacheKey = "carpool:user:detail:ac_" . strtolower($account);
        $redis = new RedisData();
        $redis->delete($cacheKey);
    }

    /**
     * 检查旧用户数据是否不同
     *
     * @param array $data 要比较的数据
     * @param integer $ruleType 0：以key为字段比较，传什么比什么。1：比较HR的同步数据
     * @return void
     */
    public function checkUserDifferent($data, $ruleType = 0)
    {
        $isDifferent = false;
        $fields = [];

        if (($ruleType && !isset($data['Code'])) || (!$ruleType && !isset($data['loginname']))) {
            $this->errorCode = 992;
            $this->errorMsg = '参数缺少用户名';
            return false;
        }

        if ($ruleType) {
            $checkData = [
                "loginname" => $data['Code'],
                "general_name" => $data['EmployeeName'],
                "department_fullname" => str_replace('/', ',', $data['OrgFullName']),
                "mail" => $data['EMail'] ?  $data['EMail'] : '',
                "sex" => $data['Sex'],
            ];
            $modifty_time = $data['ModiftyTime'];
        } else {
            $checkData = $data;
        }


        //查出旧数据
        $loginname = $checkData['loginname'];
        $oldData = $this->getDetail($loginname);

        if (!$oldData) {
            $this->errorCode = 20002;
            $this->errorMsg = '查无数据';
            return true;
        }

        //开始比较新旧数据
        foreach ($checkData as $key => $value) {
            if ($key == 'loginname') {
                continue;
            }
            if (!isset($oldData[$key])) {
                continue;
            }
            if ($oldData[$key] != $value) {
                $isDifferent = true;
                $fields[] = $key;
            }
        }

        if ($ruleType  && strtotime($oldData['modifty_time']) < strtotime($modifty_time)) {
            if (count($fields) < 1) {
                //TODO: 更新正式表的修改时间。（暂时不作修改）
                // $this->where([['loginname','=',$loginname]])->update(['modifty_time'=>$modifty_time]);
                // $this->deleteDetailCache($loginname);
            }
        }

        if ($isDifferent) {
            return $fields;
        } else {
            return false;
        }
    }

    /**
     * 加密密码
     *
     * @param [type] $password
     * @param boolean $salt
     * @return string
     */
    public function hashPassword($password, $salt = false)
    {
        if (is_string($salt)) {
            return md5(md5($password) . $salt);
        } else {
            return md5($password);
        }
    }

    /**
     * 创建加密后的密码
     *
     * @param string $password
     * @return array
     */
    public function createHashPassword($password)
    {
        $salt = getRandomString(6);
        return [
            "hash" => md5(md5($password) . $salt),
            "salt" => $salt,
        ];
    }



    /**
     * 取工号数字部分并作为密码
     * @param  string  $code 工号
     * @param  integer $hash 1：加密， 2:不加密
     * @return string 返回工号数字部分
     */
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
     * 通过账号密码，取得用户信息
     * @param  string $loginname 用户名
     * @param  string $password  密码
     * @return array||false
     */
    public function checkedPassword($loginname, $password)
    {
        $userData = $this->where([['loginname', '=', $loginname], ['is_delete', '<>', 1]])->find();
        if (!$userData) {
            $this->errorCode = 10002;
            $this->errorMsg = lang('User does not exist');
            return false;
        }

        if (!$userData['is_active']) {
            $this->errorCode = 10003;
            $this->errorMsg = lang('The user is banned');
            return false;
        }
        if (!$userData['md5password']) { // 当md5password字段为空时，使用hr初始密码验证
            $checkPassword = $this->checkInitPwd($loginname, $password);
            if ($checkPassword) {
                return $userData;
            } else {
                $this->errorCode = 10001;
                $this->errorMsg = lang('User name or password error');
                return false;
            }
        }

        if (isset($userData['salt']) && $userData['salt']) {
            if (strtolower($userData['md5password']) != strtolower($this->hashPassword($password, $userData['salt']))) {
                $this->errorCode = 10001;
                $this->errorMsg = lang('User name or password error');
                return false;
            }
        } elseif (strtolower($userData['md5password']) != strtolower($this->hashPassword($password))) {
            $this->errorCode = 10001;
            $this->errorMsg = lang('User name or password error');
            return false;
        }
        return $userData;
    }


    /**
     * 验证hr系统账号密码是否正确
     * @param  string $loginname 用户名
     * @param  string $password  密码
     * @return boolean
     */
    public function checkInitPwd($loginname, $password)
    {
        // $scoreConfigs = (new Configs())->getConfigs("score");
        // $token =  $scoreConfigs['score_token'];
        $url = config("secret.HR_api.checkPwd");
        $postData = [
            'code' => $loginname,
            'pwd' => $password,
        ];
        $scoreAccountRes = $this->clientRequest($url, ['form_params' => $postData], 'POST', 'xml');

        if (!$scoreAccountRes) {
            return false;
        } else {
            $bodyObj = new \SimpleXMLElement($scoreAccountRes);
            $bodyString = json_decode(json_encode($bodyObj), true)[0];
            if ($bodyString !== "OK") {
                $this->errorMsg = $bodyString;
                return false;
            } else {
                return  true;
            }
        }
    }


    /**
     * 从临时表同步数据到正式库
     *
     * @param array $data
     */
    public function syncDataFromTemp($data)
    {
        $company_id = isset($data['company_id']) ? $data['company_id'] : 1;
        $inputUserData = [
            "loginname" => $data['code'],
            "sex" => $data['sex'],
            "modifty_time" => $data['modifty_time'],
            "department_id" => $data['department_id'],
            'company_id' => $company_id,
        ];

        if ($data['name']) {
            $inputUserData['nativename'] = $data['name'];
        }
        if ($data['general_name']) {
            $inputUserData['general_name'] = $data['general_name'];
        }

        if ($data['email']) {
            $inputUserData['mail'] = $data['email'];
        }

        if (isset($data['department_format']) && $data['department_format']) {
            $inputUserData['Department'] = $data['department_format'];
        }
        if (isset($data['department_branch']) && $data['department_branch']) {
            $inputUserData['companyname'] = $data['department_branch'];
        }

        //查找用户旧数据
        $oldData = $this->where("loginname", $data['code'])->find();
        if (!$oldData) { // 不存在用户则添加一个
            $pw = $this->createPasswordFromCode($data['code']);
            $inputUserData_default = [
                'indentifier' => uuid_create(),
                "name" => $data['name'] ? $data['name'] : $data['general_name'],
                'deptid' => $data['code'],
                'route_short_name' => 'XY',
                'md5password' => $pw, //日后将会取消初始密码的写入
                'is_active' => 1,
                // 'is_delete' => 0,
            ];
            $inputUserData = array_merge($inputUserData_default, $inputUserData);
            $returnId = $this->insertGetId($inputUserData);
            if ($returnId) {
                $inputUserData['uid'] = $returnId;
                $inputUserData['success'] = 1;
                $this->errorMsg = "user:添加成功。";
                $this->deleteDetailCache($oldData['loginname']); //删除用户信息缓存
                return $inputUserData;
            } else {
                $this->errorMsg = "从临时库入库到正式库时，失败（旧）";
                return false;
            }
        } else { //存在用户，则更新用户信息
            $inputUserData['uid'] = $oldData["uid"];
            if (strtotime($oldData['modifty_time']) >= strtotime($data['modifty_time'])) {
                $this->errorMsg = "用户已存在，并且信息己最新，无须更新（旧）";
                $inputUserData['success'] = -2;
                return $inputUserData;
            }
            $res = $this->where("uid", $oldData["uid"])->update($inputUserData);
            if ($res === false) {
                $this->errorMsg = "用户已存在，但更新信息时，更新失败（旧）";
                return false;
            } else {
                $inputUserData['success'] = 2;
                $this->errorMsg = "user:更新成功。";
                $this->deleteDetailCache($oldData['loginname']); //删除用户信息缓存
                return $inputUserData;
            }
        }
    }


    /**
     * 验证用户是否离职
     *
     * @param [type] $username
     * @return void
     */
    public function checkDimission($username)
    {
        $checkActiveUrl  = config('others.local_hr_sync_api.single');
        $params = [
            'query' => [
                'code' => $username,
                'is_sync' => 0,
            ]
        ];
        $checkActiveRes = $this->clientRequest($checkActiveUrl, $params, 'GET');
        if (!$checkActiveRes) {
            return false;
        }
        // $checkActiveRes = ['code'=>0];
        if ($checkActiveRes['code'] === 10003) {
            $this->errorMsg = "后台用户登入失败，用户关联的capool账号已离职";
            $this->errorCode = 10003;
            return false;
        }
        return $checkActiveRes;
    }
}
