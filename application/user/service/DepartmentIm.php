<?php

namespace app\user\service;

use think\Db;
use app\common\service\Service;
use app\user\model\DepartmentIm as DepartmentImModel;
use app\user\model\Department as DepartmentModel;
use app\carpool\model\User;
use com\nim\Nim as NimServer;
use my\RedisData;

class DepartmentIm extends Service
{
    protected $nim = null;
    protected function getNim()
    {
        if ($this->nim) {
            return $this->nim;
        }
        $appKey     = config('secret.nim.appKey');
        $appSecret  = config('secret.nim.appSecret');
        $this->nim = new NimServer($appKey, $appSecret);
        return $this->nim;
    }

    /**
     * 创建虚拟群主
     *
     * @return void
     */
    public function createDepartmentOwner($department_id)
    {
        $nim = $this->getNim();
        $departmentModel = new DepartmentModel();
        $departmentData = $departmentModel->getItem($department_id);
        $ownerName = $this->createOwnerName($departmentData);
        $icon = 'http://gitsite.net/carpool/images/users/default/default_avatar.png';
        $token = 'e79a6679d37822692a660e1e88a49dff';
        // $res = $nim->getUinfos(['GET0294174']);

        $setData = [
            'accid' => "sys_department_{$department_id}",
            'name'  => $ownerName,
            'icon'  => $icon,
            'token' => $token
        ];
        $res = $nim->createUserId($setData);
        if ($res['code'] == 414 && $res['desc'] = 'already register') {
            $res = $nim->updateUserId($setData);
        }
        if ($res['code'] == 200) {
            // dump($nim->getUinfos([$setData['accid']]));
            dump($setData);
            return $setData;
        }
        return false;
    }

    /**
     * 创建群主名
     *
     * @param array||integer $department 部门数据或id;
     * @param string $default 默认名
     * @return string
     */
    public function createOwnerName($department, $default = 'Group owner')
    {
        $departmentModel = new DepartmentModel();
        if (is_array($department)) {
            $data = $department;
        } elseif (is_numeric($department)) {
            $data = $departmentModel->getItem($department);
        } else {
            return $default;
        }
        if (!$data || !isset($data['fullname'])) {
            return $default;
        }
        $department_id = $data['id'];
        $branchName = $departmentModel->getDeepName($data, 3);
        $ownerName = "System user {$branchName}_{$department_id}";
        return $ownerName;
    }

    /**
     * 跟椐department data或id生成对应的群名
     *
     * @param array||integer $department 部门数据或id;
     * @param string $default 默认名
     * @return string
     */
    public function createDepartmentImName($department, $default = 'Department Group')
    {
        $data = null;
        $departmentModel = new DepartmentModel();
        if (is_array($department)) {
            $data = $department;
        } elseif (is_numeric($department)) {
            $data = $departmentModel->getItem($department);
        } else {
            return $default;
        }
        // $branch_name = $departmentModel->getDeepName($data, 3);
        // $dept_name = $departmentModel->getDeepName($data, 4);
        // $company_name = $departmentModel->getDeepName($data, 0);
        // $eare_name = $departmentModel->getDeepName($data, 1);
        // $company_eare_name = $eare_name ? "{$company_name($eare_name)}" : $company_name;
        // $name = "{$company_eare_name}/{$branch_name}/{$dept_name})" ;
        if (!$data || !isset($data['fullname']) || !$data['fullname']) {
            return $default;
        }
        $fullname = $data['fullname'];
        $name = str_replace(',', '/', $fullname);
        return $name;
    }

    /**
     * 检查部门的tid是否有效
     *
     * @param [type] $department_id
     * @return void
     */
    public function checkDepartmentIm($department_id = 0)
    {

        $nim = $this->getNim();

        // 从数据库,查出该department是否已经有群
        $departmentImData = $this->getDepartmentIm($department_id);
        if (!$departmentImData) {
            $this->errorCode = -1;
            return false;
        }
        if (!$departmentImData['tid'] || !$departmentImData['owner']) {
            $this->errorCode = -2;
            return false;
        }
        // 验证云信是否真实存在该群
        $res = $nim->queryGroup([$departmentImData['tid']], 0);
        if (!$res || $res['code'] != 200) {
            $this->errorCode = -3;
            return false;
        }
        return $departmentImData;
    }

    /**
     * 创建部门云信群im账号
     *
     * @param integer $department_id 部门id
     * @return void
     */
    public function createDepartmentIm($department_id)
    {
        // $createData = [
        //     'tname' => 'TEST_'.time(),
        //     'owner' => 'get0294174',
        //     'members' => [],
        //     'intro' => '',
        //     'msg' => '系统邀请你加入部门群',
        //     'magree' => 0, //0不需要被邀请人同意加入群，1需要被邀请人同意才可以加入群。其它会返回414
        //     'joinmode' => 2, // sdk操作时，0不用验证，1需要验证,2不允许任何人加入。其它返回414
        //     'custom' => json_encode(null),
        //     // 'beinvitemode' => 0, //被邀请人同意方式，0-需要同意(默认),1-不需要同意。其它返回414
            
        // ];
        // $res = $nim->createGroup($createData);
        // dump($res);
    }
}
