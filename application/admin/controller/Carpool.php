<?php

namespace app\admin\controller;

use app\carpool\model\Configs as ConfigsModel;
use app\admin\controller\AdminBase;
use my\RedisData;
use think\Db;

/**
 * 拼车管理
 * Class Carpool
 * @package app\admin\controller
 */
class Carpool extends AdminBase
{

    public $check_dept_setting = [
        // "action" => ['index']
    ];

    /**
     * Carpool管理配置管理
     */
    public function configs()
    {
        if ($this->request->isPost()) {
            $name = input('name');
            $ConfigsModel = new ConfigsModel();
            switch ($name) {
                case 'trip_company_group':
                    $trip_company_group_input = input('trip_company_group');
                    $trip_company_group = [];
                    foreach (explode('|||', $trip_company_group_input) as $key => $value) {
                        $item = [];
                        foreach (explode(',', $value) as $k => $v) {
                            if (is_numeric($v)) {
                                $item[] = intval($v);
                            }
                        }
                        if (!empty($item) && !in_array($item, $trip_company_group)) {
                            $trip_company_group[] = $item;
                        }
                    }
                    $value = json_encode($trip_company_group);
                    $res = $ConfigsModel->where('name', 'trip_company_group')->update(['value' => $value]);
                    if ($res !== false) {
                        $ConfigsModel->deleteListCache(1);
                        $this->log('修改 trip_company_group 配置成功', 0);
                        $this->jsonReturn(0, '修改成功');
                    } else {
                        $this->log('修改 trip_company_group 配置失败', -1);
                        $this->jsonReturn(-1, '修改失败');
                    }
                    break;
                    // TODO: 更多配置提交请继续添加 case
                default:
                    # code...
                    break;
            }
        } else {
            $trip_company_group = ConfigsModel::where('name', 'trip_company_group')->value('value');
            $trip_company_group = json_decode($trip_company_group, true);
            $returnData = [
                'trip_company_group' => $trip_company_group,
            ];
            return $this->fetch('configs', $returnData);
        }
    }
}
