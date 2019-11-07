<?php

namespace app\user\model;

use app\common\model\BaseModel;
use app\user\model\Department;
use com\nim\Nim as NimServer;
use com\nim\SuperTeam;
use my\RedisData;
use think\Db;

class DepartmentIm extends BaseModel
{

    // 设置当前模型对应的完整数据表名称
    protected $table = 't_department_im';


    // 直接使用配置参数名
    protected $connection = 'database_carpool';
    protected $pk = 'id';

    protected $redisObj;
    protected $nim = null;
    protected $superTeam = null;

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

    protected function getSuperTeam()
    {
        if ($this->superTeam) {
            return $this->superTeam;
        }
        $appKey     = config('secret.nim.appKey');
        $appSecret  = config('secret.nim.appSecret');
        $this->superTeam = new SuperTeam($appKey, $appSecret);
        return $this->superTeam;
    }


    public function createDepartmentIm($department_id)
    {
        $nim = $this->getNim();
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
        // $departmentImData = $this->getDepartmentIm($department_id);
        // if (!$departmentImData) {
        //     $this->errorCode = -1;
        //     return false;
        // }
        // if (!$departmentImData['tid'] || !$departmentImData['owner']) {
        //     $this->errorCode = -2;
        //     return false;
        // }
        
        $ceateData = [

        ];
        $res = $nim->createGroup('t1232131');
        dump($res);
    }

    /**
     * 通过department_id查找一条数据并缓存
     *
     * @param integer $department_id 部门id
     * @param integer $ex 有效时间
     */
    public function getDepartmentIm($department_id, $ex = 60)
    {
        $cacheKey = "carpool:department_im:did_{$department_id}";
        $redis = new RedisData();
        $data = $redis->cache($cacheKey);
        if (!$data) {
            $map = [
                ['is_delete', '=', Db::raw(0)],
                ['department_id', '=', $department_id],
            ];
            $data = $this->where($map)->find();
            if ($data) {
                $data = $data->toArray();
            }
            $redis->cache($cacheKey, $data, $ex);
        }
        return $data;
    }
}
