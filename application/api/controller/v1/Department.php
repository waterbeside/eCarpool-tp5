<?php

namespace app\api\controller\v1;

use app\api\controller\ApiBase;
use app\user\model\Department as DepartmentModel;
use my\RedisData;


use think\Db;
use function GuzzleHttp\json_encode;

/**
 * 部门相关
 * Class Department
 * @package app\api\controller
 */
class Department extends ApiBase
{

    protected function initialize()
    {
        parent::initialize();
        // $this->checkPassport(1);
    }

    public function selects($deep = 2)
    {
        if ($deep > 4) {
            return $this->jsonReturn(30001, '无法查询');
        }
        $DepartmentModel = new DepartmentModel();
        $res = $DepartmentModel->getListByDeep($deep);
        if (!$res) {
            return $this->jsonReturn(20002, ["lists" => []], '没有数据');
        }
        $list = [];
        foreach ($res as $key => $value) {
            $list[] = [
                'id' => $value['id'],
                'name' => $value['name'],
                'fullname' => $value['fullname'],
            ];
        }
        return $this->jsonReturn(0, ["lists" => $list], 'Successfully');
    }
}
